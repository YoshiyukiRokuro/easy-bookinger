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
        
        add_action('wp_ajax_eb_download_pdf', array($this, 'download_pdf'));
        add_action('wp_ajax_nopriv_eb_download_pdf', array($this, 'download_pdf'));
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
        
        // Check if dates are available
        $database = EasyBookinger_Database::instance();
        $booked_dates = $database->get_booked_dates();
        
        foreach ($booking_dates as $date) {
            if (isset($booked_dates[$date])) {
                wp_send_json_error(array(
                    'message' => sprintf(__('選択された日付（%s）は既に予約済みです', EASY_BOOKINGER_TEXT_DOMAIN), $date)
                ));
            }
        }
        
        // Create bookings for each selected date
        $booking_ids = array();
        $pdf_token = wp_generate_password(32, false);
        $pdf_password = wp_generate_password(12, false);
        
        foreach ($booking_dates as $date) {
            $booking_data = array(
                'booking_date' => $date,
                'booking_time' => sanitize_text_field($form_data['booking_time'] ?? ''),
                'user_name' => sanitize_text_field($form_data['user_name']),
                'email' => sanitize_email($form_data['email']),
                'phone' => sanitize_text_field($form_data['phone'] ?? ''),
                'comment' => sanitize_textarea_field($form_data['comment'] ?? ''),
                'form_data' => $form_data,
                'pdf_token' => $pdf_token,
                'pdf_password' => $pdf_password
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
        
        // Prepare response
        $pdf_url = add_query_arg(array(
            'action' => 'eb_download_pdf',
            'token' => $pdf_token
        ), admin_url('admin-ajax.php'));
        
        wp_send_json_success(array(
            'message' => __('予約が完了しました', EASY_BOOKINGER_TEXT_DOMAIN),
            'booking_ids' => $booking_ids,
            'pdf_url' => $pdf_url,
            'pdf_password' => $pdf_password,
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
        
        wp_send_json_success(array(
            'booked_dates' => $booked_dates,
            'allowed_days' => $allowed_days,
            'current_date' => date('Y-m-d')
        ));
    }
    
    /**
     * Download PDF
     */
    public function download_pdf() {
        $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
        $password = isset($_GET['password']) ? sanitize_text_field($_GET['password']) : '';
        
        if (empty($token)) {
            wp_die(__('無効なアクセスです', EASY_BOOKINGER_TEXT_DOMAIN));
        }
        
        // Get booking by token
        $database = EasyBookinger_Database::instance();
        $booking = $database->get_booking_by_token($token);
        
        if (!$booking) {
            wp_die(__('予約が見つからないか、有効期限が切れています', EASY_BOOKINGER_TEXT_DOMAIN));
        }
        
        // Verify password if provided
        if (!empty($password) && $password !== $booking->pdf_password) {
            wp_die(__('パスワードが正しくありません', EASY_BOOKINGER_TEXT_DOMAIN));
        }
        
        // Generate PDF
        $pdf_handler = new EasyBookinger_PDF();
        $pdf_handler->generate_booking_pdf($booking);
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