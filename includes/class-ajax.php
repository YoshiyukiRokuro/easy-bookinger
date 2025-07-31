<?php
/**
 * AJAX handler class
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class EasyBookinger_Ajax {
    
    private static $instance = null;
    
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // AJAX actions for logged in and non-logged in users
        add_action('wp_ajax_eb_submit_booking', array($this, 'submit_booking'));
        add_action('wp_ajax_nopriv_eb_submit_booking', array($this, 'submit_booking'));
        
        add_action('wp_ajax_eb_get_calendar_data', array($this, 'get_calendar_data'));
        add_action('wp_ajax_nopriv_eb_get_calendar_data', array($this, 'get_calendar_data'));
        
        add_action('wp_ajax_eb_get_time_slots', array($this, 'get_time_slots'));
        add_action('wp_ajax_nopriv_eb_get_time_slots', array($this, 'get_time_slots'));
        
        add_action('wp_ajax_eb_check_availability', array($this, 'check_availability'));
        add_action('wp_ajax_nopriv_eb_check_availability', array($this, 'check_availability'));
    }
    
    /**
     * Submit booking
     */
    public function submit_booking() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'easy_bookinger_nonce')) {
            wp_die(__('セキュリティチェックに失敗しました', EASY_BOOKINGER_TEXT_DOMAIN));
        }
        
        // Validate and sanitize input
        $booking_dates = isset($_POST['booking_dates']) ? array_map('sanitize_text_field', $_POST['booking_dates']) : array();
        $form_data = isset($_POST['form_data']) ? $_POST['form_data'] : array();
        
        // Validate required data
        if (empty($booking_dates)) {
            wp_send_json_error(array(
                'message' => __('予約日を選択してください', EASY_BOOKINGER_TEXT_DOMAIN)
            ));
        }
        
        // Get settings
        $settings = get_option('easy_bookinger_settings', array());
        $booking_fields = isset($settings['booking_fields']) ? $settings['booking_fields'] : array();
        $max_selectable_days = isset($settings['max_selectable_days']) ? (int)$settings['max_selectable_days'] : 5;
        
        // Validate maximum selectable days
        if (count($booking_dates) > $max_selectable_days) {
            wp_send_json_error(array(
                'message' => sprintf(__('選択できる日数は最大%d日です', EASY_BOOKINGER_TEXT_DOMAIN), $max_selectable_days)
            ));
        }
        
        // Validate form fields
        $validation_errors = $this->validate_form_data($form_data, $booking_fields);
        if (!empty($validation_errors)) {
            wp_send_json_error(array(
                'message' => __('入力内容に誤りがあります', EASY_BOOKINGER_TEXT_DOMAIN),
                'errors' => $validation_errors
            ));
        }
        
        // Check if dates are available (not restricted and within quota)
        $database = EasyBookinger_Database::instance();
        $booked_dates = $database->get_booked_dates();
        
        foreach ($booking_dates as $date) {
            // Check date restrictions
            if ($database->is_date_restricted($date)) {
                wp_send_json_error(array(
                    'message' => sprintf(__('選択された日付（%s）は予約できません', EASY_BOOKINGER_TEXT_DOMAIN), $date)
                ));
            }
            
            // Check quota availability
            $remaining_quota = $database->get_remaining_quota($date);
            if ($remaining_quota <= 0) {
                wp_send_json_error(array(
                    'message' => sprintf(__('選択された日付（%s）は予約枠が満杯です', EASY_BOOKINGER_TEXT_DOMAIN), $date)
                ));
            }
        }
        
        // Create bookings for each selected date
        $booking_ids = array();
        $time_slot_id = isset($form_data['booking_time_slot']) ? (int)$form_data['booking_time_slot'] : null;
        
        // Get time slot details if selected
        $booking_time = '';
        if ($time_slot_id) {
            $time_slot = $database->get_time_slot_by_id($time_slot_id);
            if ($time_slot) {
                $booking_time = date('H:i', strtotime($time_slot->start_time));
            }
        }
        
        foreach ($booking_dates as $date) {
            $booking_data = array(
                'booking_date' => $date,
                'booking_time' => $booking_time,
                'user_name' => sanitize_text_field($form_data['user_name']),
                'email' => sanitize_email($form_data['email']),
                'phone' => sanitize_text_field($form_data['phone'] ?? ''),
                'comment' => sanitize_textarea_field($form_data['comment'] ?? ''),
                'form_data' => $form_data
            );
            
            $booking_id = $database->create_booking($booking_data);
            if ($booking_id) {
                $booking_ids[] = $booking_id;
            }
        }
        
        if (empty($booking_ids)) {
            wp_send_json_error(array(
                'message' => __('予約の登録に失敗しました', EASY_BOOKINGER_TEXT_DOMAIN)
            ));
        }
        
        // Update booking quotas for registered dates
        foreach ($booking_dates as $date) {
            $database->update_booking_quota_count($date);
        }
        
        // Send emails
        $email_handler = new EasyBookinger_Email();
        
        // Send admin notification
        if (isset($settings['admin_email_enabled']) && $settings['admin_email_enabled']) {
            $email_handler->send_admin_notification($booking_ids[0]);
        }
        
        // Send user confirmation
        if (isset($settings['user_email_enabled']) && $settings['user_email_enabled']) {
            $email_handler->send_user_confirmation($booking_ids[0]);
        }
        
        wp_send_json_success(array(
            'message' => __('予約が完了しました', EASY_BOOKINGER_TEXT_DOMAIN),
            'booking_ids' => $booking_ids,
            'booking_dates' => $booking_dates
        ));
    }
    
    /**
     * Get calendar data
     */
    public function get_calendar_data() {
        // Verify nonce
        if (!wp_verify_nonce($_GET['nonce'], 'easy_bookinger_nonce')) {
            wp_die(__('セキュリティチェックに失敗しました', EASY_BOOKINGER_TEXT_DOMAIN));
        }
        
        $year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
        $month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
        
        // Get settings
        $settings = get_option('easy_bookinger_settings', array());
        $display_months = isset($settings['display_months']) ? (int)$settings['display_months'] : 3;
        $allowed_days = isset($settings['allowed_days']) ? $settings['allowed_days'] : array(1, 2, 3, 4, 5);
        
        // Calculate date range
        $date_from = sprintf('%04d-%02d-01', $year, $month);
        $date_to = date('Y-m-t', strtotime("{$date_from} +{$display_months} months"));
        
        // Get booked dates
        $database = EasyBookinger_Database::instance();
        $booked_dates = $database->get_booked_dates($date_from, $date_to);
        
        // Get restricted dates
        $restricted_dates = $database->get_restricted_dates($date_from, $date_to);
        $restricted_dates_array = array();
        foreach ($restricted_dates as $restriction) {
            $restricted_dates_array[] = $restriction->restriction_date;
        }
        
        // Get quotas and calculate remaining slots
        $quotas_data = array();
        $current_date = $date_from;
        while ($current_date <= $date_to) {
            $remaining = $database->get_remaining_quota($current_date);
            $quotas_data[$current_date] = $remaining;
            $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
        }
        
        wp_send_json_success(array(
            'booked_dates' => $booked_dates,
            'restricted_dates' => $restricted_dates_array,
            'quotas_data' => $quotas_data,
            'allowed_days' => $allowed_days,
            'current_date' => date('Y-m-d')
        ));
    }
    
    /**
     * Get time slots
     */
    public function get_time_slots() {
        // Verify nonce
        if (!wp_verify_nonce($_GET['nonce'], 'easy_bookinger_nonce')) {
            wp_die(__('セキュリティチェックに失敗しました', EASY_BOOKINGER_TEXT_DOMAIN));
        }
        
        $database = EasyBookinger_Database::instance();
        $time_slots = $database->get_active_time_slots();
        
        $formatted_slots = array();
        foreach ($time_slots as $slot) {
            $formatted_slots[] = array(
                'id' => $slot->id,
                'start_time' => $slot->start_time,
                'slot_name' => $slot->slot_name ?: date('H:i', strtotime($slot->start_time)),
                'max_bookings' => $slot->max_bookings
            );
        }
        
        wp_send_json_success(array(
            'time_slots' => $formatted_slots
        ));
    }
    
    /**
     * Check availability for a specific date and time
     */
    public function check_availability() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'easy_bookinger_nonce')) {
            wp_die(__('セキュリティチェックに失敗しました', EASY_BOOKINGER_TEXT_DOMAIN));
        }
        
        $date = sanitize_text_field($_POST['date']);
        $time_slot_id = isset($_POST['time_slot_id']) ? (int)$_POST['time_slot_id'] : null;
        
        $database = EasyBookinger_Database::instance();
        
        // Check if date is restricted
        if ($database->is_date_restricted($date)) {
            wp_send_json_error(array(
                'message' => __('この日付は予約できません', EASY_BOOKINGER_TEXT_DOMAIN)
            ));
        }
        
        // Check quota
        $remaining_quota = $database->get_remaining_quota($date);
        if ($remaining_quota <= 0) {
            wp_send_json_error(array(
                'message' => __('この日付の予約枠は満杯です', EASY_BOOKINGER_TEXT_DOMAIN)
            ));
        }
        
        // If time slot is specified, check time slot availability
        if ($time_slot_id) {
            global $wpdb;
            $bookings_table = $wpdb->prefix . 'easy_bookinger_bookings';
            $slots_table = $wpdb->prefix . 'easy_bookinger_time_slots';
            
            // Get time slot info
            $time_slot = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $slots_table WHERE id = %d AND is_active = 1",
                $time_slot_id
            ));
            
            if (!$time_slot) {
                wp_send_json_error(array(
                    'message' => __('無効な時間帯です', EASY_BOOKINGER_TEXT_DOMAIN)
                ));
            }
            
            // Check current bookings for this time slot
            $time_format = date('H:i', strtotime($time_slot->start_time));
            $current_bookings = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $bookings_table WHERE booking_date = %s AND booking_time = %s AND status = 'active'",
                $date,
                $time_format
            ));
            
            if ($current_bookings >= $time_slot->max_bookings) {
                wp_send_json_error(array(
                    'message' => __('この時間帯は満杯です', EASY_BOOKINGER_TEXT_DOMAIN)
                ));
            }
        }
        
        wp_send_json_success(array(
            'available' => true,
            'remaining_quota' => $remaining_quota,
            'message' => __('予約可能です', EASY_BOOKINGER_TEXT_DOMAIN)
        ));
    }
    
    /**
     * Validate form data
     */
    private function validate_form_data($form_data, $booking_fields) {
        $errors = array();
        
        foreach ($booking_fields as $field) {
            $name = $field['name'];
            $label = $field['label'];
            $type = $field['type'];
            $required = isset($field['required']) ? $field['required'] : false;
            $maxlength = isset($field['maxlength']) ? (int)$field['maxlength'] : 0;
            
            $value = isset($form_data[$name]) ? $form_data[$name] : '';
            
            // Required field validation
            if ($required && empty($value)) {
                $errors[$name] = sprintf(__('%sは必須項目です', EASY_BOOKINGER_TEXT_DOMAIN), $label);
                continue;
            }
            
            // Skip validation if field is empty and not required
            if (empty($value)) {
                continue;
            }
            
            // Type-specific validation
            switch ($type) {
                case 'email':
                    if (!is_email($value)) {
                        $errors[$name] = sprintf(__('%sの形式が正しくありません', EASY_BOOKINGER_TEXT_DOMAIN), $label);
                    }
                    break;
                    
                case 'textarea':
                case 'text':
                    if ($maxlength > 0 && mb_strlen($value) > $maxlength) {
                        $errors[$name] = sprintf(__('%sは%d文字以内で入力してください', EASY_BOOKINGER_TEXT_DOMAIN), $label, $maxlength);
                    }
                    break;
                    
                case 'url':
                    if (!filter_var($value, FILTER_VALIDATE_URL)) {
                        $errors[$name] = sprintf(__('%sの形式が正しくありません', EASY_BOOKINGER_TEXT_DOMAIN), $label);
                    }
                    break;
                    
                case 'number':
                    if (!is_numeric($value)) {
                        $errors[$name] = sprintf(__('%sは数値で入力してください', EASY_BOOKINGER_TEXT_DOMAIN), $label);
                    }
                    break;
            }
        }
        
        // Email confirmation validation
        if (isset($form_data['email']) && isset($form_data['email_confirm'])) {
            if ($form_data['email'] !== $form_data['email_confirm']) {
                $errors['email_confirm'] = __('メールアドレスが一致しません', EASY_BOOKINGER_TEXT_DOMAIN);
            }
        }
        
        return $errors;
    }
}