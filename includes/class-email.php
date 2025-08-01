<?php
/**
 * Email handling class
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class EasyBookinger_Email {
    
    /**
     * Send admin notification email
     */
    public function send_admin_notification($booking_id) {
        $database = EasyBookinger_Database::instance();
        $booking = $database->get_booking($booking_id);
        
        if (!$booking) {
            return false;
        }
        
        $settings = get_option('easy_bookinger_settings', array());
        $admin_email = get_option('admin_email');
        
        $subject = sprintf(__('[%s] 新しい予約が登録されました', EASY_BOOKINGER_TEXT_DOMAIN), get_bloginfo('name'));
        
        $form_data = maybe_unserialize($booking->form_data);
        
        $message = $this->get_admin_email_template($booking, $form_data);
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . $admin_email . '>'
        );
        
        return wp_mail($admin_email, $subject, $message, $headers);
    }
    
    /**
     * Send user confirmation email
     */
    public function send_user_confirmation($booking_id) {
        $database = EasyBookinger_Database::instance();
        $booking = $database->get_booking($booking_id);
        
        if (!$booking) {
            return false;
        }
        
        $settings = get_option('easy_bookinger_settings', array());
        $admin_email = get_option('admin_email');
        
        // Use customizable subject
        $subject_template = isset($settings['user_email_subject']) ? $settings['user_email_subject'] : '[{site_name}] 予約確認メール';
        $subject = $this->replace_placeholders($subject_template, $booking, null);
        
        $form_data = maybe_unserialize($booking->form_data);
        
        $message = $this->get_user_email_template($booking, $form_data);
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . $admin_email . '>'
        );
        
        return wp_mail($booking->email, $subject, $message, $headers);
    }
    
    /**
     * Get admin email template
     */
    private function get_admin_email_template($booking, $form_data) {
        $site_name = get_bloginfo('name');
        $site_url = get_bloginfo('url');
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title><?php echo esc_html($site_name); ?> - 新しい予約</title>
            <style>
                body { font-family: 'Hiragino Sans', 'Hiragino Kaku Gothic ProN', Meiryo, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #f8f9fa; padding: 20px; text-align: center; margin-bottom: 30px; }
                .content { padding: 20px 0; }
                .booking-info { background-color: #f8f9fa; padding: 20px; margin: 20px 0; border-radius: 5px; }
                .info-row { margin-bottom: 10px; }
                .label { font-weight: bold; display: inline-block; width: 120px; }
                .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; color: #666; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1><?php echo esc_html($site_name); ?></h1>
                    <h2>新しい予約が登録されました</h2>
                </div>
                
                <div class="content">
                    <p>新しい予約が登録されました。詳細は以下の通りです。</p>
                    
                    <div class="booking-info">
                        <h3>予約情報</h3>
                        <div class="info-row">
                            <span class="label">予約日:</span>
                            <span><?php echo esc_html($booking->booking_date); ?></span>
                        </div>
                        <?php if (!empty($booking->booking_time)): ?>
                        <div class="info-row">
                            <span class="label">時間:</span>
                            <span><?php echo esc_html($booking->booking_time); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="info-row">
                            <span class="label">氏名:</span>
                            <span><?php echo esc_html($booking->user_name); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label">メール:</span>
                            <span><?php echo esc_html($booking->email); ?></span>
                        </div>
                        <?php if (!empty($booking->phone)): ?>
                        <div class="info-row">
                            <span class="label">電話番号:</span>
                            <span><?php echo esc_html($booking->phone); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($booking->comment)): ?>
                        <div class="info-row">
                            <span class="label">コメント:</span>
                            <span><?php echo nl2br(esc_html($booking->comment)); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="info-row">
                            <span class="label">登録日時:</span>
                            <span><?php echo esc_html($booking->created_at); ?></span>
                        </div>
                    </div>
                    
                    <?php if (!empty($form_data) && is_array($form_data)): ?>
                    <div class="booking-info">
                        <h3>追加情報</h3>
                        <?php foreach ($form_data as $key => $value): ?>
                            <?php if (!in_array($key, array('user_name', 'email', 'email_confirm', 'phone', 'comment', 'booking_time'))): ?>
                            <div class="info-row">
                                <span class="label"><?php echo esc_html($key); ?>:</span>
                                <span><?php echo is_array($value) ? esc_html(implode(', ', $value)) : esc_html($value); ?></span>
                            </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                    <p>管理画面にて予約の詳細をご確認ください。</p>
                </div>
                
                <div class="footer">
                    <p><?php echo esc_html($site_name); ?><br>
                    <a href="<?php echo esc_url($site_url); ?>"><?php echo esc_url($site_url); ?></a></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get user email template
     */
    private function get_user_email_template($booking, $form_data) {
        $settings = get_option('easy_bookinger_settings', array());
        
        // Get custom template or use default
        if (isset($settings['user_email_body']) && !empty($settings['user_email_body'])) {
            $template = $settings['user_email_body'];
            // Use text template with placeholder replacement
            $message = $this->replace_placeholders($template, $booking, $form_data);
            // Convert line breaks to HTML for email
            $message = nl2br(esc_html($message));
        } else {
            // Fallback to original HTML template
            $message = $this->get_default_user_email_html($booking, $form_data);
        }
        
        return $message;
    }
    
    /**
     * Replace placeholders in template
     */
    private function replace_placeholders($template, $booking, $form_data = null) {
        $site_name = get_bloginfo('name');
        $site_url = get_bloginfo('url');
        
        // Create booking dates display
        $booking_dates = date('Y年n月j日', strtotime($booking->booking_date));
        if (!empty($booking->booking_time)) {
            $booking_dates .= ' ' . $booking->booking_time;
        }
        
        $replacements = array(
            '{site_name}' => $site_name,
            '{site_url}' => $site_url,
            '{user_name}' => $booking->user_name,
            '{email}' => $booking->email,
            '{booking_dates}' => $booking_dates,
            '{booking_date}' => date('Y年n月j日', strtotime($booking->booking_date)),
            '{booking_time}' => $booking->booking_time ?: '',
            '{phone}' => $booking->phone ?: '',
            '{comment}' => $booking->comment ?: ''
        );
        
        // Add form data fields if available
        if ($form_data && is_array($form_data)) {
            foreach ($form_data as $key => $value) {
                $replacements['{' . $key . '}'] = $value;
            }
        }
        
        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }
    
    /**
     * Get default user email HTML template (fallback)
     */
    private function get_default_user_email_html($booking, $form_data) {
        $site_name = get_bloginfo('name');
        $site_url = get_bloginfo('url');
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title><?php echo esc_html($site_name); ?> - 予約確認</title>
            <style>
                body { font-family: 'Hiragino Sans', 'Hiragino Kaku Gothic ProN', Meiryo, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #f8f9fa; padding: 20px; text-align: center; margin-bottom: 30px; }
                .content { padding: 20px 0; }
                .booking-info { background-color: #f8f9fa; padding: 20px; margin: 20px 0; border-radius: 5px; }
                .info-row { margin-bottom: 10px; }
                .label { font-weight: bold; display: inline-block; width: 120px; }
                .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; color: #666; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1><?php echo esc_html($site_name); ?></h1>
                    <h2>予約確認メール</h2>
                </div>
                
                <div class="content">
                    <p><?php echo esc_html($booking->user_name); ?> 様</p>
                    
                    <p>この度は、<?php echo esc_html($site_name); ?>をご利用いただき、ありがとうございます。<br>
                    ご予約の内容をご確認ください。</p>
                    
                    <div class="booking-info">
                        <h3>ご予約内容</h3>
                        <div class="info-row">
                            <span class="label">予約日:</span>
                            <span><?php echo esc_html($booking->booking_date); ?></span>
                        </div>
                        <?php if (!empty($booking->booking_time)): ?>
                        <div class="info-row">
                            <span class="label">時間:</span>
                            <span><?php echo esc_html($booking->booking_time); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="info-row">
                            <span class="label">氏名:</span>
                            <span><?php echo esc_html($booking->user_name); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label">メール:</span>
                            <span><?php echo esc_html($booking->email); ?></span>
                        </div>
                        <?php if (!empty($booking->phone)): ?>
                        <div class="info-row">
                            <span class="label">電話番号:</span>
                            <span><?php echo esc_html($booking->phone); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($booking->comment)): ?>
                        <div class="info-row">
                            <span class="label">コメント:</span>
                            <span><?php echo nl2br(esc_html($booking->comment)); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <p>ご不明な点がございましたら、お気軽にお問い合わせください。</p>
                    
                    <p>今後ともよろしくお願いいたします。</p>
                </div>
                
                <div class="footer">
                    <p><?php echo esc_html($site_name); ?><br>
                    <a href="<?php echo esc_url($site_url); ?>"><?php echo esc_url($site_url); ?></a></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}