<?php
/**
 * Shortcode management class
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class EasyBookinger_Shortcode {
    
    private static $instance = null;
    
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_shortcode('easy_bookinger', array($this, 'render_booking_calendar'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        global $post;
        
        // Only enqueue on pages that contain the shortcode
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'easy_bookinger')) {
            // Styles
            wp_enqueue_style(
                'easy-bookinger-style',
                EASY_BOOKINGER_PLUGIN_URL . 'assets/css/easy-bookinger.css',
                array(),
                EASY_BOOKINGER_VERSION
            );
            
            // Toastr for notifications
            wp_enqueue_style(
                'toastr',
                'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css',
                array(),
                '2.1.4'
            );
            
            // Scripts
            wp_enqueue_script('jquery');
            
            wp_enqueue_script(
                'toastr',
                'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js',
                array('jquery'),
                '2.1.4',
                true
            );
            
            wp_enqueue_script(
                'easy-bookinger-script',
                EASY_BOOKINGER_PLUGIN_URL . 'assets/js/easy-bookinger.js',
                array('jquery', 'toastr'),
                EASY_BOOKINGER_VERSION,
                true
            );
            
            // Localize script
            wp_localize_script('easy-bookinger-script', 'easyBookinger', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('easy_bookinger_nonce'),
                'text' => array(
                    'selectDate' => __('日付を選択してください', EASY_BOOKINGER_TEXT_DOMAIN),
                    'maxDaysExceeded' => __('選択できる日数の上限を超えています', EASY_BOOKINGER_TEXT_DOMAIN),
                    'confirmBooking' => __('この内容で予約を登録しますか？', EASY_BOOKINGER_TEXT_DOMAIN),
                    'bookingSuccess' => __('予約が完了しました', EASY_BOOKINGER_TEXT_DOMAIN),
                    'bookingError' => __('予約の登録に失敗しました', EASY_BOOKINGER_TEXT_DOMAIN),
                    'validationError' => __('入力内容に誤りがあります', EASY_BOOKINGER_TEXT_DOMAIN),
                    'loading' => __('処理中...', EASY_BOOKINGER_TEXT_DOMAIN),
                    'prev' => __('前月', EASY_BOOKINGER_TEXT_DOMAIN),
                    'next' => __('次月', EASY_BOOKINGER_TEXT_DOMAIN),
                    'today' => __('今日', EASY_BOOKINGER_TEXT_DOMAIN),
                    'book' => __('予約', EASY_BOOKINGER_TEXT_DOMAIN),
                    'cancel' => __('キャンセル', EASY_BOOKINGER_TEXT_DOMAIN),
                    'submit' => __('登録', EASY_BOOKINGER_TEXT_DOMAIN),
                    'close' => __('閉じる', EASY_BOOKINGER_TEXT_DOMAIN)
                ),
                'dayNames' => array(
                    __('日', EASY_BOOKINGER_TEXT_DOMAIN),
                    __('月', EASY_BOOKINGER_TEXT_DOMAIN),
                    __('火', EASY_BOOKINGER_TEXT_DOMAIN),
                    __('水', EASY_BOOKINGER_TEXT_DOMAIN),
                    __('木', EASY_BOOKINGER_TEXT_DOMAIN),
                    __('金', EASY_BOOKINGER_TEXT_DOMAIN),
                    __('土', EASY_BOOKINGER_TEXT_DOMAIN)
                ),
                'monthNames' => array(
                    __('1月', EASY_BOOKINGER_TEXT_DOMAIN),
                    __('2月', EASY_BOOKINGER_TEXT_DOMAIN),
                    __('3月', EASY_BOOKINGER_TEXT_DOMAIN),
                    __('4月', EASY_BOOKINGER_TEXT_DOMAIN),
                    __('5月', EASY_BOOKINGER_TEXT_DOMAIN),
                    __('6月', EASY_BOOKINGER_TEXT_DOMAIN),
                    __('7月', EASY_BOOKINGER_TEXT_DOMAIN),
                    __('8月', EASY_BOOKINGER_TEXT_DOMAIN),
                    __('9月', EASY_BOOKINGER_TEXT_DOMAIN),
                    __('10月', EASY_BOOKINGER_TEXT_DOMAIN),
                    __('11月', EASY_BOOKINGER_TEXT_DOMAIN),
                    __('12月', EASY_BOOKINGER_TEXT_DOMAIN)
                )
            ));
        }
    }
    
    /**
     * Render booking calendar shortcode
     */
    public function render_booking_calendar($atts) {
        $atts = shortcode_atts(array(
            'months' => 3,
            'theme' => 'default'
        ), $atts, 'easy_bookinger');
        
        // Get settings
        $settings = get_option('easy_bookinger_settings', array());
        $display_months = isset($settings['display_months']) ? (int)$settings['display_months'] : 3;
        $max_selectable_days = isset($settings['max_selectable_days']) ? (int)$settings['max_selectable_days'] : 5;
        $allowed_days = isset($settings['allowed_days']) ? $settings['allowed_days'] : array(1, 2, 3, 4, 5);
        $booking_fields = isset($settings['booking_fields']) ? $settings['booking_fields'] : array();
        $allow_same_day_booking = isset($settings['allow_same_day_booking']) ? $settings['allow_same_day_booking'] : true;
        $enable_time_slots = isset($settings['enable_time_slots']) ? $settings['enable_time_slots'] : false;
        
        // Override with shortcode attributes
        if (!empty($atts['months'])) {
            $display_months = (int)$atts['months'];
        }
        
        // Get booked dates
        $date_from = date('Y-m-01');
        $date_to = date('Y-m-t', strtotime("+{$display_months} months"));
        
        $database = EasyBookinger_Database::instance();
        $booked_dates = $database->get_booked_dates($date_from, $date_to);
        
        // Get restricted dates
        $restricted_dates = $database->get_restricted_dates($date_from, $date_to);
        $restricted_dates_array = array();
        foreach ($restricted_dates as $restriction) {
            $restricted_dates_array[] = $restriction->restriction_date;
        }
        
        // Get quotas data
        $quotas_data = array();
        $current_date = $date_from;
        while ($current_date <= $date_to) {
            $remaining = $database->get_remaining_quota($current_date);
            $quotas_data[$current_date] = $remaining;
            $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
        }
        
        // Get special availability dates
        $special_availability = $database->get_special_availability($date_from, $date_to);
        $special_availability_data = array();
        foreach ($special_availability as $special) {
            if ($special->is_available) {
                $special_availability_data[$special->availability_date] = array(
                    'reason' => $special->reason,
                    'max_bookings' => $special->max_bookings
                );
            }
        }
        
        // Get time slots if enabled
        $time_slots = array();
        if ($enable_time_slots) {
            $slots = $database->get_active_time_slots();
            foreach ($slots as $slot) {
                $time_slots[] = array(
                    'id' => $slot->id,
                    'start_time' => $slot->start_time,
                    'slot_name' => $slot->slot_name ?: date('H:i', strtotime($slot->start_time)),
                    'max_bookings' => $slot->max_bookings
                );
            }
        }
        
        ob_start();
        ?>
        <div id="easy-bookinger-container" class="easy-bookinger-theme-<?php echo esc_attr($atts['theme']); ?>">
            <div id="easy-bookinger-calendar">
                <div class="eb-calendar-header">
                    <button type="button" class="eb-nav-btn eb-prev-month" data-direction="prev">
                        <span><?php _e('前月', EASY_BOOKINGER_TEXT_DOMAIN); ?></span>
                    </button>
                    <div class="eb-current-month">
                        <span id="eb-current-month-text"></span>
                    </div>
                    <button type="button" class="eb-nav-btn eb-next-month" data-direction="next">
                        <span><?php _e('次月', EASY_BOOKINGER_TEXT_DOMAIN); ?></span>
                    </button>
                </div>
                
                <div class="eb-calendar-grid">
                    <div class="eb-calendar-days-header">
                        <div class="eb-day-header"><?php _e('日', EASY_BOOKINGER_TEXT_DOMAIN); ?></div>
                        <div class="eb-day-header"><?php _e('月', EASY_BOOKINGER_TEXT_DOMAIN); ?></div>
                        <div class="eb-day-header"><?php _e('火', EASY_BOOKINGER_TEXT_DOMAIN); ?></div>
                        <div class="eb-day-header"><?php _e('水', EASY_BOOKINGER_TEXT_DOMAIN); ?></div>
                        <div class="eb-day-header"><?php _e('木', EASY_BOOKINGER_TEXT_DOMAIN); ?></div>
                        <div class="eb-day-header"><?php _e('金', EASY_BOOKINGER_TEXT_DOMAIN); ?></div>
                        <div class="eb-day-header"><?php _e('土', EASY_BOOKINGER_TEXT_DOMAIN); ?></div>
                    </div>
                    <div id="eb-calendar-days" class="eb-calendar-days">
                        <!-- Calendar days will be populated by JavaScript -->
                    </div>
                </div>
                
                <div class="eb-selected-dates">
                    <h4><?php _e('選択された日付', EASY_BOOKINGER_TEXT_DOMAIN); ?></h4>
                    <div id="eb-selected-dates-list" class="eb-selected-dates-list">
                        <!-- Selected dates will be displayed here -->
                    </div>
                </div>
                
                <div class="eb-calendar-actions">
                    <button type="button" id="eb-book-button" class="eb-button eb-primary" disabled>
                        <?php _e('予約', EASY_BOOKINGER_TEXT_DOMAIN); ?>
                    </button>
                </div>
            </div>
            
            <!-- Booking Form Modal -->
            <div id="eb-booking-modal" class="eb-modal" style="display: none;">
                <div class="eb-modal-content">
                    <div class="eb-modal-header">
                        <h3><?php _e('予約フォーム', EASY_BOOKINGER_TEXT_DOMAIN); ?></h3>
                        <button type="button" class="eb-modal-close">&times;</button>
                    </div>
                    
                    <form id="eb-booking-form">
                        <div class="eb-form-section">
                            <h4><?php _e('選択日程', EASY_BOOKINGER_TEXT_DOMAIN); ?></h4>
                            <div id="eb-form-selected-dates" class="eb-form-selected-dates">
                                <!-- Selected dates will be displayed here -->
                            </div>
                        </div>
                        
                        <?php if ($enable_time_slots && !empty($time_slots)): ?>
                        <div class="eb-form-section">
                            <h4><?php _e('時間帯選択', EASY_BOOKINGER_TEXT_DOMAIN); ?></h4>
                            <div class="eb-time-slots">
                                <select name="booking_time_slot" class="eb-time-slot-select">
                                    <option value=""><?php _e('時間帯を選択してください', EASY_BOOKINGER_TEXT_DOMAIN); ?></option>
                                    <?php foreach ($time_slots as $slot): ?>
                                    <option value="<?php echo esc_attr($slot['id']); ?>">
                                        <?php echo esc_html($slot['slot_name']); ?> (最大<?php echo esc_html($slot['max_bookings']); ?>名)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="eb-form-section">
                            <h4><?php _e('お客様情報', EASY_BOOKINGER_TEXT_DOMAIN); ?></h4>
                            <?php
                            foreach ($booking_fields as $field) {
                                $this->render_form_field($field);
                            }
                            ?>
                        </div>
                        
                        <div class="eb-form-actions">
                            <button type="button" class="eb-button eb-secondary" id="eb-form-cancel">
                                <?php _e('キャンセル', EASY_BOOKINGER_TEXT_DOMAIN); ?>
                            </button>
                            <button type="submit" class="eb-button eb-primary" id="eb-form-submit">
                                <?php _e('予約を登録', EASY_BOOKINGER_TEXT_DOMAIN); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Success Modal -->
            <div id="eb-success-modal" class="eb-modal" style="display: none;">
                <div class="eb-modal-content">
                    <div class="eb-modal-header">
                        <h3><?php _e('予約完了', EASY_BOOKINGER_TEXT_DOMAIN); ?></h3>
                        <button class="eb-modal-close" type="button" aria-label="<?php _e('閉じる', EASY_BOOKINGER_TEXT_DOMAIN); ?>">&times;</button>
                    </div>
                    
                    <div id="eb-success-content" class="eb-success-content">
                        <!-- Success content will be populated by JavaScript -->
                    </div>
                </div>
            </div>
        </div>
        
        <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function() {
                if (typeof window.EasyBookingerCalendar !== 'undefined') {
                    window.EasyBookingerCalendar.init({
                        displayMonths: <?php echo (int)$display_months; ?>,
                        maxSelectableDays: <?php echo (int)$max_selectable_days; ?>,
                        allowedDays: <?php echo json_encode(array_map('intval', $allowed_days)); ?>,
                        allowSameDayBooking: <?php echo json_encode($allow_same_day_booking); ?>,
                        bookedDates: <?php echo json_encode($booked_dates); ?>,
                        restrictedDates: <?php echo json_encode($restricted_dates_array); ?>,
                        quotasData: <?php echo json_encode($quotas_data); ?>,
                        specialAvailability: <?php echo json_encode($special_availability_data); ?>,
                        enableTimeSlots: <?php echo json_encode($enable_time_slots); ?>,
                        timeSlots: <?php echo json_encode($time_slots); ?>,
                        bookingFields: <?php echo json_encode($booking_fields); ?>
                    });
                }
            });
        </script>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Render form field
     */
    private function render_form_field($field) {
        $name = isset($field['name']) ? $field['name'] : '';
        $label = isset($field['label']) ? $field['label'] : '';
        $type = isset($field['type']) ? $field['type'] : 'text';
        $required = isset($field['required']) ? $field['required'] : false;
        $maxlength = isset($field['maxlength']) ? $field['maxlength'] : '';
        $options = isset($field['options']) ? $field['options'] : array();
        
        $required_attr = $required ? 'required' : '';
        $maxlength_attr = $maxlength ? "maxlength=\"{$maxlength}\"" : '';
        $required_indicator = $required ? ' <span class="eb-required">*</span>' : '';
        
        echo '<div class="eb-form-field">';
        echo '<label for="eb-field-' . esc_attr($name) . '">' . esc_html($label) . $required_indicator . '</label>';
        
        switch ($type) {
            case 'textarea':
                echo '<textarea id="eb-field-' . esc_attr($name) . '" name="' . esc_attr($name) . '" ' . $required_attr . ' ' . $maxlength_attr . '></textarea>';
                if ($maxlength) {
                    echo '<div class="eb-field-help">' . sprintf(__('最大%d文字', EASY_BOOKINGER_TEXT_DOMAIN), $maxlength) . '</div>';
                }
                break;
                
            case 'select':
                echo '<select id="eb-field-' . esc_attr($name) . '" name="' . esc_attr($name) . '" ' . $required_attr . '>';
                echo '<option value="">' . __('選択してください', EASY_BOOKINGER_TEXT_DOMAIN) . '</option>';
                foreach ($options as $option) {
                    echo '<option value="' . esc_attr($option['value']) . '">' . esc_html($option['label']) . '</option>';
                }
                echo '</select>';
                break;
                
            case 'radio':
                foreach ($options as $option) {
                    echo '<label class="eb-radio-label">';
                    echo '<input type="radio" name="' . esc_attr($name) . '" value="' . esc_attr($option['value']) . '" ' . $required_attr . '>';
                    echo esc_html($option['label']);
                    echo '</label>';
                }
                break;
                
            case 'checkbox':
                foreach ($options as $option) {
                    echo '<label class="eb-checkbox-label">';
                    echo '<input type="checkbox" name="' . esc_attr($name) . '[]" value="' . esc_attr($option['value']) . '">';
                    echo esc_html($option['label']);
                    echo '</label>';
                }
                break;
                
            default:
                echo '<input type="' . esc_attr($type) . '" id="eb-field-' . esc_attr($name) . '" name="' . esc_attr($name) . '" ' . $required_attr . ' ' . $maxlength_attr . '>';
                break;
        }
        
        echo '</div>';
    }
}