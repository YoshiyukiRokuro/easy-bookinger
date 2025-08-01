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
        // Display success message if settings were saved
        if (isset($_GET['settings_saved']) && $_GET['settings_saved'] === '1') {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('設定を保存しました', EASY_BOOKINGER_TEXT_DOMAIN) . '</p></div>';
        }
        
        if (isset($_POST['submit'])) {
            $this->save_settings();
        }
        
        $settings = get_option('easy_bookinger_settings', array());
        ?>
        <div class="wrap easy-bookinger-settings">
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
                    
                    <tr>
                        <th scope="row"><?php _e('当日予約', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="allow_same_day_booking" value="1" <?php checked($settings['allow_same_day_booking'] ?? true); ?> />
                                <?php _e('当日予約を許可する', EASY_BOOKINGER_TEXT_DOMAIN); ?>
                            </label>
                            <p class="description"><?php _e('無効にすると、当日の予約ができなくなります。予約は翌日以降のみ可能になります。', EASY_BOOKINGER_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('タイムゾーン', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                        <td>
                            <select name="timezone">
                                <?php
                                $selected_timezone = $settings['timezone'] ?? 'Asia/Tokyo';
                                $timezone_options = array(
                                    'Asia/Tokyo' => __('日本標準時 (JST)', EASY_BOOKINGER_TEXT_DOMAIN),
                                    'UTC' => __('協定世界時 (UTC)', EASY_BOOKINGER_TEXT_DOMAIN),
                                    'America/New_York' => __('東部標準時 (EST)', EASY_BOOKINGER_TEXT_DOMAIN),
                                    'America/Los_Angeles' => __('太平洋標準時 (PST)', EASY_BOOKINGER_TEXT_DOMAIN),
                                    'Europe/London' => __('グリニッジ標準時 (GMT)', EASY_BOOKINGER_TEXT_DOMAIN),
                                    'Asia/Seoul' => __('韓国標準時 (KST)', EASY_BOOKINGER_TEXT_DOMAIN),
                                    'Asia/Shanghai' => __('中国標準時 (CST)', EASY_BOOKINGER_TEXT_DOMAIN),
                                    'Australia/Sydney' => __('オーストラリア東部標準時 (AEST)', EASY_BOOKINGER_TEXT_DOMAIN)
                                );
                                
                                foreach ($timezone_options as $tz => $name):
                                ?>
                                <option value="<?php echo esc_attr($tz); ?>" <?php selected($selected_timezone, $tz); ?>><?php echo esc_html($name); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php _e('カレンダーで使用するタイムゾーンを設定します。デフォルトは日本標準時です。', EASY_BOOKINGER_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('予約可能未来日数', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                        <td>
                            <input type="number" name="max_future_days" value="<?php echo esc_attr($settings['max_future_days'] ?? 0); ?>" min="0" max="365" />
                            <span><?php _e('日後まで', EASY_BOOKINGER_TEXT_DOMAIN); ?></span>
                            <p class="description"><?php _e('0に設定すると制限なし。例：14と設定すると2週間先まで予約可能。表示月数とは別に予約受付期間を制限できます。', EASY_BOOKINGER_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                </table>
                
                <h2><?php _e('フォーム設定', EASY_BOOKINGER_TEXT_DOMAIN); ?></h2>
                <p><?php _e('予約フォームの入力項目を設定します。項目をドラッグ&ドロップで並び替えできます。', EASY_BOOKINGER_TEXT_DOMAIN); ?></p>
                
                <div id="booking-fields-container" class="sortable-fields">
                    <?php
                    $booking_fields = $settings['booking_fields'] ?? array();
                    foreach ($booking_fields as $index => $field):
                    ?>
                    <div class="booking-field-row" data-index="<?php echo esc_attr($index); ?>">
                        <div class="field-handle">
                            <span class="dashicons dashicons-sort"></span>
                        </div>
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
                                    <?php 
                                    $is_email_field = in_array($field['name'], ['email', 'email_confirm']);
                                    if ($is_email_field): ?>
                                        <select name="booking_fields[<?php echo esc_attr($index); ?>][type]" disabled>
                                            <option value="email" selected><?php _e('メール', EASY_BOOKINGER_TEXT_DOMAIN); ?></option>
                                        </select>
                                        <input type="hidden" name="booking_fields[<?php echo esc_attr($index); ?>][type]" value="email" />
                                        <p class="description" style="color: #666; font-style: italic;"><?php _e('※ メールアドレス項目のタイプは変更できません', EASY_BOOKINGER_TEXT_DOMAIN); ?></p>
                                    <?php else: ?>
                                        <select name="booking_fields[<?php echo esc_attr($index); ?>][type]">
                                            <option value="text" <?php selected($field['type'], 'text'); ?>><?php _e('テキスト', EASY_BOOKINGER_TEXT_DOMAIN); ?></option>
                                            <option value="email" <?php selected($field['type'], 'email'); ?>><?php _e('メール', EASY_BOOKINGER_TEXT_DOMAIN); ?></option>
                                            <option value="tel" <?php selected($field['type'], 'tel'); ?>><?php _e('電話番号', EASY_BOOKINGER_TEXT_DOMAIN); ?></option>
                                            <option value="textarea" <?php selected($field['type'], 'textarea'); ?>><?php _e('テキストエリア', EASY_BOOKINGER_TEXT_DOMAIN); ?></option>
                                            <option value="select" <?php selected($field['type'], 'select'); ?>><?php _e('セレクト', EASY_BOOKINGER_TEXT_DOMAIN); ?></option>
                                            <option value="radio" <?php selected($field['type'], 'radio'); ?>><?php _e('ラジオボタン', EASY_BOOKINGER_TEXT_DOMAIN); ?></option>
                                            <option value="checkbox" <?php selected($field['type'], 'checkbox'); ?>><?php _e('チェックボックス', EASY_BOOKINGER_TEXT_DOMAIN); ?></option>
                                        </select>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th><?php _e('必須', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                                <td>
                                    <?php if ($is_email_field): ?>
                                        <label>
                                            <input type="checkbox" checked disabled />
                                            <?php _e('必須項目にする', EASY_BOOKINGER_TEXT_DOMAIN); ?>
                                        </label>
                                        <input type="hidden" name="booking_fields[<?php echo esc_attr($index); ?>][required]" value="1" />
                                        <p class="description" style="color: #666; font-style: italic;"><?php _e('※ メールアドレス項目は常に必須です', EASY_BOOKINGER_TEXT_DOMAIN); ?></p>
                                    <?php else: ?>
                                        <label>
                                            <input type="checkbox" name="booking_fields[<?php echo esc_attr($index); ?>][required]" value="1" <?php checked($field['required'] ?? false); ?> />
                                            <?php _e('必須項目にする', EASY_BOOKINGER_TEXT_DOMAIN); ?>
                                        </label>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th><?php _e('最大文字数', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                                <td>
                                    <?php if ($is_email_field): ?>
                                        <input type="number" value="256" disabled min="1" />
                                        <input type="hidden" name="booking_fields[<?php echo esc_attr($index); ?>][maxlength]" value="256" />
                                        <p class="description" style="color: #666; font-style: italic;"><?php _e('※ メールアドレス項目の最大文字数は256文字に固定されています', EASY_BOOKINGER_TEXT_DOMAIN); ?></p>
                                    <?php else: ?>
                                        <input type="number" name="booking_fields[<?php echo esc_attr($index); ?>][maxlength]" value="<?php echo esc_attr($field['maxlength'] ?? ''); ?>" min="1" />
                                        <p class="description"><?php _e('テキスト・テキストエリア項目の最大文字数（空の場合は制限なし）', EASY_BOOKINGER_TEXT_DOMAIN); ?></p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                        <button type="button" class="button remove-field"><?php _e('この項目を削除', EASY_BOOKINGER_TEXT_DOMAIN); ?></button>
                        <hr>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <button type="button" id="add-field" class="button"><?php _e('項目を追加', EASY_BOOKINGER_TEXT_DOMAIN); ?></button>
                
                <h2><?php _e('メールテンプレート設定', EASY_BOOKINGER_TEXT_DOMAIN); ?></h2>
                <p><?php _e('利用者への確認メールと予約完了画面のメッセージを設定します。', EASY_BOOKINGER_TEXT_DOMAIN); ?></p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('確認メール件名', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                        <td>
                            <input type="text" name="user_email_subject" value="<?php echo esc_attr($settings['user_email_subject'] ?? '[{site_name}] 予約確認メール'); ?>" class="regular-text" />
                            <p class="description"><?php _e('利用者への確認メールの件名です。{site_name}でサイト名を挿入できます。', EASY_BOOKINGER_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('確認メール本文', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                        <td>
                            <textarea name="user_email_body" rows="10" cols="50" class="large-text"><?php echo esc_textarea($settings['user_email_body'] ?? $this->get_default_user_email_template()); ?></textarea>
                            <p class="description"><?php _e('利用者への確認メールの本文です。{user_name}、{booking_dates}、{site_name}、{site_url}などのプレースホルダーが使用できます。', EASY_BOOKINGER_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('予約完了画面メッセージ', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                        <td>
                            <textarea name="booking_success_message" rows="5" cols="50" class="large-text"><?php echo esc_textarea($settings['booking_success_message'] ?? $this->get_default_success_message()); ?></textarea>
                            <p class="description"><?php _e('予約完了後に表示されるメッセージです。{user_name}、{booking_dates}などのプレースホルダーが使用できます。', EASY_BOOKINGER_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
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
            'allow_same_day_booking' => isset($_POST['allow_same_day_booking']),
            'timezone' => sanitize_text_field($_POST['timezone'] ?? 'Asia/Tokyo'),
            'max_future_days' => intval($_POST['max_future_days'] ?? 0),
            'user_email_subject' => sanitize_text_field($_POST['user_email_subject'] ?? '[{site_name}] 予約確認メール'),
            'user_email_body' => sanitize_textarea_field($_POST['user_email_body'] ?? $this->get_default_user_email_template()),
            'booking_success_message' => sanitize_textarea_field($_POST['booking_success_message'] ?? $this->get_default_success_message()),
            'booking_fields' => array()
        );
        
        // Process booking fields and ensure email is required
        if (isset($_POST['booking_fields']) && is_array($_POST['booking_fields'])) {
            $has_email = false;
            $has_email_confirm = false;
            
            foreach ($_POST['booking_fields'] as $field) {
                if (!empty($field['name']) && !empty($field['label'])) {
                    $field_data = array(
                        'name' => sanitize_text_field($field['name']),
                        'label' => sanitize_text_field($field['label']),
                        'type' => sanitize_text_field($field['type']),
                        'required' => isset($field['required']),
                        'maxlength' => !empty($field['maxlength']) ? intval($field['maxlength']) : 0
                    );
                    
                    // Make email fields required by force and set max length to 256
                    if ($field['type'] === 'email' || $field['name'] === 'email' || $field['name'] === 'email_confirm') {
                        $field_data['required'] = true;
                        $field_data['maxlength'] = 256;
                    }
                    
                    // Track if we have email fields
                    if ($field['name'] === 'email') {
                        $has_email = true;
                    }
                    if ($field['name'] === 'email_confirm') {
                        $has_email_confirm = true;
                    }
                    
                    $settings['booking_fields'][] = $field_data;
                }
            }
            
            // Ensure email and email_confirm fields are always present
            if (!$has_email) {
                $settings['booking_fields'][] = array(
                    'name' => 'email',
                    'label' => __('メールアドレス', EASY_BOOKINGER_TEXT_DOMAIN),
                    'type' => 'email',
                    'required' => true,
                    'maxlength' => 256
                );
            }
            
            if (!$has_email_confirm) {
                $settings['booking_fields'][] = array(
                    'name' => 'email_confirm',
                    'label' => __('メールアドレス（確認用）', EASY_BOOKINGER_TEXT_DOMAIN),
                    'type' => 'email',
                    'required' => true,
                    'maxlength' => 256
                );
            }
        } else {
            // If no booking fields are submitted, add default required fields
            $settings['booking_fields'] = array(
                array(
                    'name' => 'user_name',
                    'label' => __('氏名', EASY_BOOKINGER_TEXT_DOMAIN),
                    'type' => 'text',
                    'required' => true,
                    'maxlength' => 0
                ),
                array(
                    'name' => 'email',
                    'label' => __('メールアドレス', EASY_BOOKINGER_TEXT_DOMAIN),
                    'type' => 'email',
                    'required' => true,
                    'maxlength' => 256
                ),
                array(
                    'name' => 'email_confirm',
                    'label' => __('メールアドレス（確認用）', EASY_BOOKINGER_TEXT_DOMAIN),
                    'type' => 'email',
                    'required' => true,
                    'maxlength' => 256
                )
            );
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
        
        // Redirect to prevent resubmission and show success message
        $redirect_url = add_query_arg(array(
            'page' => 'easy-bookinger-settings',
            'settings_saved' => '1'
        ), admin_url('admin.php'));
        
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Get default user email template
     */
    private function get_default_user_email_template() {
        return "お世話になっております。

この度は、{site_name}にて予約をいただき、ありがとうございます。
以下の内容で予約を承りました。

■ 予約者名：{user_name}
■ 予約日程：{booking_dates}
■ メールアドレス：{email}

ご不明な点がございましたら、お気軽にお問い合わせください。

{site_name}
{site_url}";
    }
    
    /**
     * Get default success message
     */
    private function get_default_success_message() {
        return "予約が完了しました。

{user_name}様の予約内容：
{booking_dates}

確認メールをお送りいたしましたので、ご確認ください。
ありがとうございました。";
    }
}