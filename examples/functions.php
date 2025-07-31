<?php
/**
 * Easy Bookinger theme integration examples
 * 
 * Add these functions to your theme's functions.php file to customize Easy Bookinger
 */

/**
 * Custom form field validation
 */
add_filter('easy_bookinger_validate_form_data', 'custom_booking_validation', 10, 2);
function custom_booking_validation($errors, $form_data) {
    // Example: Custom phone number validation
    if (!empty($form_data['phone'])) {
        $phone = preg_replace('/[^0-9]/', '', $form_data['phone']);
        if (strlen($phone) < 10 || strlen($phone) > 11) {
            $errors['phone'] = '電話番号は10-11桁で入力してください';
        }
    }
    
    // Example: Business hours validation
    $selected_date = $form_data['booking_date'] ?? '';
    if (!empty($selected_date)) {
        $date = new DateTime($selected_date);
        $day_of_week = $date->format('w'); // 0 = Sunday, 6 = Saturday
        
        if ($day_of_week == 0 || $day_of_week == 6) {
            $errors['booking_date'] = '土日は営業しておりません';
        }
    }
    
    return $errors;
}

/**
 * Customize email template
 */
add_filter('easy_bookinger_admin_email_template', 'custom_admin_email_template', 10, 3);
function custom_admin_email_template($template, $booking, $form_data) {
    // Add custom header
    $custom_header = '<div style="background-color: #007cba; color: white; padding: 20px; text-align: center;">';
    $custom_header .= '<h1>' . get_bloginfo('name') . '</h1>';
    $custom_header .= '<p>新しい予約が入りました</p>';
    $custom_header .= '</div>';
    
    return $custom_header . $template;
}

/**
 * Add custom booking fields
 */
add_filter('easy_bookinger_default_form_fields', 'add_custom_booking_fields');
function add_custom_booking_fields($fields) {
    // Add company name field
    $fields[] = array(
        'name' => 'company_name',
        'label' => '会社名',
        'type' => 'text',
        'required' => false,
        'maxlength' => 100
    );
    
    // Add purpose field
    $fields[] = array(
        'name' => 'purpose',
        'label' => 'ご利用目的',
        'type' => 'select',
        'required' => true,
        'options' => array(
            array('value' => 'meeting', 'label' => '会議'),
            array('value' => 'interview', 'label' => '面接'),
            array('value' => 'training', 'label' => '研修'),
            array('value' => 'other', 'label' => 'その他')
        )
    );
    
    return $fields;
}

/**
 * Customize calendar display
 */
add_filter('easy_bookinger_calendar_settings', 'custom_calendar_settings');
function custom_calendar_settings($settings) {
    // Customize for specific post/page
    if (is_page('vip-booking')) {
        $settings['max_selectable_days'] = 10; // VIP customers can book more days
        $settings['allowed_days'] = array(0, 1, 2, 3, 4, 5, 6); // Allow weekends
    }
    
    return $settings;
}

/**
 * Add custom CSS for booking calendar
 */
add_action('wp_head', 'custom_booking_styles');
function custom_booking_styles() {
    if (has_shortcode(get_post()->post_content ?? '', 'easy_bookinger')) {
        ?>
        <style>
        /* カスタムカラースキーム */
        .easy-bookinger-container .eb-calendar-day.selectable {
            background-color: #e8f5e8;
            border: 1px solid #c8e6c9;
        }
        
        .easy-bookinger-container .eb-calendar-day.selected {
            background: linear-gradient(135deg, #1976d2, #1565c0);
            color: white;
            box-shadow: 0 2px 8px rgba(25, 118, 210, 0.3);
        }
        
        .easy-bookinger-container .eb-button.eb-primary {
            background: linear-gradient(135deg, #007cba, #005a87);
            border: none;
            box-shadow: 0 2px 8px rgba(0, 124, 186, 0.3);
            transition: all 0.3s ease;
        }
        
        .easy-bookinger-container .eb-button.eb-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 124, 186, 0.4);
        }
        
        /* モバイル対応のカスタマイズ */
        @media (max-width: 768px) {
            .easy-bookinger-container {
                padding: 10px;
            }
            
            .easy-bookinger-container .eb-calendar-day {
                min-height: 45px;
                font-size: 12px;
            }
        }
        </style>
        <?php
    }
}

/**
 * Custom booking confirmation actions
 */
add_action('easy_bookinger_booking_confirmed', 'custom_booking_actions', 10, 2);
function custom_booking_actions($booking_id, $booking_data) {
    // Example: Send notification to Slack
    send_slack_notification($booking_data);
    
    // Example: Add to external calendar system
    add_to_external_calendar($booking_data);
    
    // Example: Update inventory or availability
    update_resource_availability($booking_data);
}

function send_slack_notification($booking_data) {
    // Implement Slack webhook notification
    $webhook_url = 'https://hooks.slack.com/your-webhook-url';
    
    $message = array(
        'text' => sprintf(
            '新しい予約: %s様 - %s',
            $booking_data['user_name'],
            $booking_data['booking_date']
        )
    );
    
    wp_remote_post($webhook_url, array(
        'body' => json_encode($message),
        'headers' => array('Content-Type' => 'application/json')
    ));
}

function add_to_external_calendar($booking_data) {
    // Implement Google Calendar or other calendar integration
    // This is just a placeholder
}

function update_resource_availability($booking_data) {
    // Update room or resource availability
    // This is just a placeholder
}

/**
 * Custom shortcode attributes
 */
add_filter('easy_bookinger_shortcode_atts', 'custom_shortcode_atts', 10, 2);
function custom_shortcode_atts($atts, $original_atts) {
    // Add custom theme support
    if (isset($original_atts['style'])) {
        $atts['custom_style'] = $original_atts['style'];
    }
    
    return $atts;
}

/**
 * Restrict booking by user role
 */
add_filter('easy_bookinger_can_book', 'restrict_booking_by_role');
function restrict_booking_by_role($can_book) {
    // Example: Only allow logged-in users to book
    if (!is_user_logged_in()) {
        return false;
    }
    
    // Example: Only allow specific roles
    $current_user = wp_get_current_user();
    $allowed_roles = array('administrator', 'editor', 'subscriber');
    
    if (!array_intersect($allowed_roles, $current_user->roles)) {
        return false;
    }
    
    return $can_book;
}

/**
 * Add booking widget to dashboard
 */
add_action('wp_dashboard_setup', 'add_booking_dashboard_widget');
function add_booking_dashboard_widget() {
    wp_add_dashboard_widget(
        'easy_bookinger_dashboard',
        'Easy Bookinger - 今日の予約',
        'display_today_bookings_widget'
    );
}

function display_today_bookings_widget() {
    $today = date('Y-m-d');
    $database = EasyBookinger_Database::instance();
    $today_bookings = $database->get_bookings(array(
        'date_from' => $today,
        'date_to' => $today,
        'status' => 'active'
    ));
    
    if (empty($today_bookings)) {
        echo '<p>今日の予約はありません。</p>';
    } else {
        echo '<ul>';
        foreach ($today_bookings as $booking) {
            echo '<li>';
            echo '<strong>' . esc_html($booking->user_name) . '</strong><br>';
            echo '時間: ' . esc_html($booking->booking_time) . '<br>';
            echo 'メール: ' . esc_html($booking->email);
            echo '</li>';
        }
        echo '</ul>';
    }
    
    echo '<p><a href="' . admin_url('admin.php?page=easy-bookinger') . '">すべての予約を見る</a></p>';
}