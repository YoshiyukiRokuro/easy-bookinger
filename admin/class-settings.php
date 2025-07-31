<?php
/**
 * Settings management class
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class EasyBookinger_Settings {
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (isset($_POST['submit'])) {
            $this->save_settings();
        }
        
        $settings = get_option('easy_bookinger_settings', array());
        ?>
        <div class="wrap">
            <h1><?php _e('Easy Bookinger - 設定', EASY_BOOKINGER_TEXT_DOMAIN); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('easy_bookinger_settings', 'easy_bookinger_settings_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('表示月数', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                        <td>
                            <input type="number" name="display_months" value="<?php echo esc_attr($settings['display_months'] ?? 3); ?>" min="1" max="12" />
                            <p class="description"><?php _e('カレンダーに表示する月数を設定します（1-12）', EASY_BOOKINGER_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('最大選択可能日数', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                        <td>
                            <input type="number" name="max_selectable_days" value="<?php echo esc_attr($settings['max_selectable_days'] ?? 5); ?>" min="1" max="30" />
                            <p class="description"><?php _e('一度に選択できる日数の上限を設定します', EASY_BOOKINGER_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('予約可能曜日', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                        <td>
                            <?php
                            $allowed_days = $settings['allowed_days'] ?? array(1, 2, 3, 4, 5);
                            $day_names = array(
                                0 => __('日曜日', EASY_BOOKINGER_TEXT_DOMAIN),
                                1 => __('月曜日', EASY_BOOKINGER_TEXT_DOMAIN),
                                2 => __('火曜日', EASY_BOOKINGER_TEXT_DOMAIN),
                                3 => __('水曜日', EASY_BOOKINGER_TEXT_DOMAIN),
                                4 => __('木曜日', EASY_BOOKINGER_TEXT_DOMAIN),
                                5 => __('金曜日', EASY_BOOKINGER_TEXT_DOMAIN),
                                6 => __('土曜日', EASY_BOOKINGER_TEXT_DOMAIN)
                            );
                            
                            foreach ($day_names as $day_num => $day_name):
                                $checked = in_array($day_num, $allowed_days) ? 'checked' : '';
                            ?>
                            <label>
                                <input type="checkbox" name="allowed_days[]" value="<?php echo esc_attr($day_num); ?>" <?php echo $checked; ?> />
                                <?php echo esc_html($day_name); ?>
                            </label><br>
                            <?php endforeach; ?>
                            <p class="description"><?php _e('予約を受け付ける曜日を選択してください', EASY_BOOKINGER_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('メール通知設定', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="admin_email_enabled" value="1" <?php checked($settings['admin_email_enabled'] ?? true); ?> />
                                <?php _e('管理者への通知メールを送信する', EASY_BOOKINGER_TEXT_DOMAIN); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="user_email_enabled" value="1" <?php checked($settings['user_email_enabled'] ?? true); ?> />
                                <?php _e('利用者への確認メールを送信する', EASY_BOOKINGER_TEXT_DOMAIN); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('管理者メールアドレス', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                        <td>
                            <div id="admin-emails-container">
                                <?php
                                $database = EasyBookinger_Database::instance();
                                $admin_emails = $database->get_admin_emails();
                                
                                if (empty($admin_emails)) {
                                    // Add default admin email
                                    $admin_email = get_option('admin_email');
                                    if ($admin_email) {
                                        echo '<div class="admin-email-row">';
                                        echo '<input type="email" name="admin_emails[]" value="' . esc_attr($admin_email) . '" placeholder="admin@example.com" style="width: 300px;" />';
                                        echo ' <button type="button" class="button remove-admin-email">' . __('削除', EASY_BOOKINGER_TEXT_DOMAIN) . '</button>';
                                        echo '</div>';
                                    }
                                } else {
                                    foreach ($admin_emails as $email_obj) {
                                        echo '<div class="admin-email-row">';
                                        echo '<input type="email" name="admin_emails[]" value="' . esc_attr($email_obj->email_address) . '" placeholder="admin@example.com" style="width: 300px;" />';
                                        echo ' <button type="button" class="button remove-admin-email">' . __('削除', EASY_BOOKINGER_TEXT_DOMAIN) . '</button>';
                                        echo '</div>';
                                    }
                                }
                                ?>
                            </div>
                            <button type="button" id="add-admin-email" class="button"><?php _e('メールアドレスを追加', EASY_BOOKINGER_TEXT_DOMAIN); ?></button>
                            <p class="description"><?php _e('予約通知を送信する管理者のメールアドレスを設定してください。複数設定可能です。', EASY_BOOKINGER_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('デフォルト予約枠数', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                        <td>
                            <input type="number" name="default_daily_quota" value="<?php echo esc_attr($settings['default_daily_quota'] ?? 3); ?>" min="1" max="20" />
                            <span><?php _e('件/日', EASY_BOOKINGER_TEXT_DOMAIN); ?></span>
                            <p class="description"><?php _e('個別に設定されていない日の予約枠数（デフォルト値）', EASY_BOOKINGER_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('時間帯選択機能', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_time_slots" value="1" <?php checked($settings['enable_time_slots'] ?? false); ?> />
                                <?php _e('時間帯選択機能を有効にする', EASY_BOOKINGER_TEXT_DOMAIN); ?>
                            </label>
                            <p class="description"><?php _e('有効にすると、予約フォームで時間帯を選択できるようになります。時間帯は「時間帯設定」ページで管理できます。', EASY_BOOKINGER_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                </table>
                
                <h2><?php _e('フォーム設定', EASY_BOOKINGER_TEXT_DOMAIN); ?></h2>
                <p><?php _e('予約フォームの入力項目を設定します。', EASY_BOOKINGER_TEXT_DOMAIN); ?></p>
                
                <div id="booking-fields-container">
                    <?php
                    $booking_fields = $settings['booking_fields'] ?? array();
                    foreach ($booking_fields as $index => $field):
                    ?>
                    <div class="booking-field-row" data-index="<?php echo esc_attr($index); ?>">
                        <table class="form-table">
                            <tr>
                                <th><?php _e('項目名', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                                <td>
                                    <input type="text" name="booking_fields[<?php echo esc_attr($index); ?>][name]" value="<?php echo esc_attr($field['name']); ?>" />
                                </td>
                            </tr>
                            <tr>
                                <th><?php _e('ラベル', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                                <td>
                                    <input type="text" name="booking_fields[<?php echo esc_attr($index); ?>][label]" value="<?php echo esc_attr($field['label']); ?>" />
                                </td>
                            </tr>
                            <tr>
                                <th><?php _e('タイプ', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                                <td>
                                    <select name="booking_fields[<?php echo esc_attr($index); ?>][type]">
                                        <option value="text" <?php selected($field['type'], 'text'); ?>><?php _e('テキスト', EASY_BOOKINGER_TEXT_DOMAIN); ?></option>
                                        <option value="email" <?php selected($field['type'], 'email'); ?>><?php _e('メール', EASY_BOOKINGER_TEXT_DOMAIN); ?></option>
                                        <option value="tel" <?php selected($field['type'], 'tel'); ?>><?php _e('電話番号', EASY_BOOKINGER_TEXT_DOMAIN); ?></option>
                                        <option value="textarea" <?php selected($field['type'], 'textarea'); ?>><?php _e('テキストエリア', EASY_BOOKINGER_TEXT_DOMAIN); ?></option>
                                        <option value="select" <?php selected($field['type'], 'select'); ?>><?php _e('セレクト', EASY_BOOKINGER_TEXT_DOMAIN); ?></option>
                                        <option value="radio" <?php selected($field['type'], 'radio'); ?>><?php _e('ラジオボタン', EASY_BOOKINGER_TEXT_DOMAIN); ?></option>
                                        <option value="checkbox" <?php selected($field['type'], 'checkbox'); ?>><?php _e('チェックボックス', EASY_BOOKINGER_TEXT_DOMAIN); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><?php _e('必須', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="booking_fields[<?php echo esc_attr($index); ?>][required]" value="1" <?php checked($field['required'] ?? false); ?> />
                                        <?php _e('必須項目にする', EASY_BOOKINGER_TEXT_DOMAIN); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th><?php _e('最大文字数', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                                <td>
                                    <input type="number" name="booking_fields[<?php echo esc_attr($index); ?>][maxlength]" value="<?php echo esc_attr($field['maxlength'] ?? ''); ?>" min="1" />
                                    <p class="description"><?php _e('テキスト・テキストエリア項目の最大文字数（空の場合は制限なし）', EASY_BOOKINGER_TEXT_DOMAIN); ?></p>
                                </td>
                            </tr>
                        </table>
                        <button type="button" class="button remove-field"><?php _e('この項目を削除', EASY_BOOKINGER_TEXT_DOMAIN); ?></button>
                        <hr>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <button type="button" id="add-field" class="button"><?php _e('項目を追加', EASY_BOOKINGER_TEXT_DOMAIN); ?></button>
                
                <?php submit_button(); ?>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Add new booking field
            $('#add-field').click(function() {
                var fieldCount = $('#booking-fields-container .booking-field-row').length;
                var fieldHtml = '<div class="booking-field-row" data-index="' + fieldCount + '">' +
                    '<table class="form-table">' +
                    '<tr><th><?php _e('項目名', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>' +
                    '<td><input type="text" name="booking_fields[' + fieldCount + '][name]" value="" /></td></tr>' +
                    '<tr><th><?php _e('ラベル', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>' +
                    '<td><input type="text" name="booking_fields[' + fieldCount + '][label]" value="" /></td></tr>' +
                    '<tr><th><?php _e('タイプ', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>' +
                    '<td><select name="booking_fields[' + fieldCount + '][type]">' +
                    '<option value="text"><?php _e('テキスト', EASY_BOOKINGER_TEXT_DOMAIN); ?></option>' +
                    '<option value="email"><?php _e('メール', EASY_BOOKINGER_TEXT_DOMAIN); ?></option>' +
                    '<option value="tel"><?php _e('電話番号', EASY_BOOKINGER_TEXT_DOMAIN); ?></option>' +
                    '<option value="textarea"><?php _e('テキストエリア', EASY_BOOKINGER_TEXT_DOMAIN); ?></option>' +
                    '<option value="select"><?php _e('セレクト', EASY_BOOKINGER_TEXT_DOMAIN); ?></option>' +
                    '<option value="radio"><?php _e('ラジオボタン', EASY_BOOKINGER_TEXT_DOMAIN); ?></option>' +
                    '<option value="checkbox"><?php _e('チェックボックス', EASY_BOOKINGER_TEXT_DOMAIN); ?></option>' +
                    '</select></td></tr>' +
                    '<tr><th><?php _e('必須', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>' +
                    '<td><label><input type="checkbox" name="booking_fields[' + fieldCount + '][required]" value="1" /> <?php _e('必須項目にする', EASY_BOOKINGER_TEXT_DOMAIN); ?></label></td></tr>' +
                    '<tr><th><?php _e('最大文字数', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>' +
                    '<td><input type="number" name="booking_fields[' + fieldCount + '][maxlength]" value="" min="1" />' +
                    '<p class="description"><?php _e('テキスト・テキストエリア項目の最大文字数（空の場合は制限なし）', EASY_BOOKINGER_TEXT_DOMAIN); ?></p></td></tr>' +
                    '</table>' +
                    '<button type="button" class="button remove-field"><?php _e('この項目を削除', EASY_BOOKINGER_TEXT_DOMAIN); ?></button>' +
                    '<hr></div>';
                $('#booking-fields-container').append(fieldHtml);
            });
            
            // Remove booking field
            $(document).on('click', '.remove-field', function() {
                $(this).closest('.booking-field-row').remove();
            });
            
            // Add admin email
            $('#add-admin-email').click(function() {
                var emailHtml = '<div class="admin-email-row">' +
                    '<input type="email" name="admin_emails[]" value="" placeholder="admin@example.com" style="width: 300px;" />' +
                    ' <button type="button" class="button remove-admin-email"><?php _e('削除', EASY_BOOKINGER_TEXT_DOMAIN); ?></button>' +
                    '</div>';
                $('#admin-emails-container').append(emailHtml);
            });
            
            // Remove admin email
            $(document).on('click', '.remove-admin-email', function() {
                if ($('#admin-emails-container .admin-email-row').length > 1) {
                    $(this).closest('.admin-email-row').remove();
                } else {
                    alert('<?php _e('最低1つのメールアドレスが必要です', EASY_BOOKINGER_TEXT_DOMAIN); ?>');
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Save settings
     */
    private function save_settings() {
        if (!wp_verify_nonce($_POST['easy_bookinger_settings_nonce'], 'easy_bookinger_settings')) {
            wp_die(__('セキュリティチェックに失敗しました', EASY_BOOKINGER_TEXT_DOMAIN));
        }
        
        $settings = array(
            'display_months' => intval($_POST['display_months']),
            'max_selectable_days' => intval($_POST['max_selectable_days']),
            'allowed_days' => array_map('intval', $_POST['allowed_days'] ?? array()),
            'admin_email_enabled' => isset($_POST['admin_email_enabled']),
            'user_email_enabled' => isset($_POST['user_email_enabled']),
            'default_daily_quota' => intval($_POST['default_daily_quota']),
            'enable_time_slots' => isset($_POST['enable_time_slots']),
            'booking_fields' => array()
        );
        
        // Process booking fields and ensure email is required
        if (isset($_POST['booking_fields']) && is_array($_POST['booking_fields'])) {
            foreach ($_POST['booking_fields'] as $field) {
                if (!empty($field['name']) && !empty($field['label'])) {
                    $field_data = array(
                        'name' => sanitize_text_field($field['name']),
                        'label' => sanitize_text_field($field['label']),
                        'type' => sanitize_text_field($field['type']),
                        'required' => isset($field['required']),
                        'maxlength' => !empty($field['maxlength']) ? intval($field['maxlength']) : 0
                    );
                    
                    // Make email fields required by force (requirement #7)
                    if ($field['type'] === 'email' || $field['name'] === 'email') {
                        $field_data['required'] = true;
                    }
                    
                    $settings['booking_fields'][] = $field_data;
                }
            }
        }
        
        // Save admin emails
        if (isset($_POST['admin_emails']) && is_array($_POST['admin_emails'])) {
            $database = EasyBookinger_Database::instance();
            
            // Clear existing admin emails
            $existing_emails = $database->get_admin_emails();
            foreach ($existing_emails as $email) {
                $database->delete_admin_email($email->id);
            }
            
            // Add new admin emails
            foreach ($_POST['admin_emails'] as $email_address) {
                $email_address = sanitize_email($email_address);
                if (is_email($email_address)) {
                    $database->add_admin_email($email_address, array('booking_notification', 'general_notification'));
                }
            }
        }
        
        update_option('easy_bookinger_settings', $settings);
        
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('設定を保存しました', EASY_BOOKINGER_TEXT_DOMAIN) . '</p></div>';
        });
    }
}