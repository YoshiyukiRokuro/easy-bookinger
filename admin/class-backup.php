<?php
/**
 * Backup and restore functionality
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class EasyBookinger_Backup {
    
    /**
     * Render backup page
     */
    public function render_backup_page() {
        // Handle backup actions
        if (isset($_POST['create_backup'])) {
            $this->create_backup();
        }
        
        if (isset($_POST['restore_backup']) && !empty($_FILES['backup_file']['tmp_name'])) {
            $this->restore_backup();
        }
        
        ?>
        <div class="wrap">
            <h1><?php _e('Easy Bookinger - バックアップ・復元', EASY_BOOKINGER_TEXT_DOMAIN); ?></h1>
            
            <div class="eb-backup-section">
                <h2><?php _e('バックアップの作成', EASY_BOOKINGER_TEXT_DOMAIN); ?></h2>
                <p><?php _e('現在の予約データをバックアップファイルとしてダウンロードできます。', EASY_BOOKINGER_TEXT_DOMAIN); ?></p>
                
                <form method="post" action="">
                    <?php wp_nonce_field('easy_bookinger_backup', 'easy_bookinger_backup_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('ファイル名', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                            <td>
                                <input type="text" name="backup_filename" value="<?php echo esc_attr('easy_bookinger_backup_' . date('Y-m-d')); ?>" style="width: 300px;" />
                                <p class="description"><?php _e('バックアップファイルの名前を指定してください（拡張子は自動で付きます）', EASY_BOOKINGER_TEXT_DOMAIN); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('ファイル形式', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                            <td>
                                <label>
                                    <input type="radio" name="backup_format" value="json" checked />
                                    <?php _e('JSON形式（復元可能）', EASY_BOOKINGER_TEXT_DOMAIN); ?>
                                    <span class="description"><?php _e('- 完全なバックアップ・復元に対応', EASY_BOOKINGER_TEXT_DOMAIN); ?></span>
                                </label><br>
                                <label>
                                    <input type="radio" name="backup_format" value="csv" />
                                    <?php _e('CSV形式（閲覧用）', EASY_BOOKINGER_TEXT_DOMAIN); ?>
                                    <span class="description"><?php _e('- 予約データのみ、Excel等で閲覧可能', EASY_BOOKINGER_TEXT_DOMAIN); ?></span>
                                </label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('バックアップに含める項目', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="backup_bookings" value="1" checked />
                                    <?php _e('予約データ', EASY_BOOKINGER_TEXT_DOMAIN); ?>
                                </label><br>
                                <label>
                                    <input type="checkbox" name="backup_settings" value="1" checked />
                                    <?php _e('プラグイン設定', EASY_BOOKINGER_TEXT_DOMAIN); ?>
                                </label><br>
                                <label>
                                    <input type="checkbox" name="backup_restrictions" value="1" checked />
                                    <?php _e('日付制限', EASY_BOOKINGER_TEXT_DOMAIN); ?>
                                </label><br>
                                <label>
                                    <input type="checkbox" name="backup_quotas" value="1" checked />
                                    <?php _e('予約枠設定', EASY_BOOKINGER_TEXT_DOMAIN); ?>
                                </label><br>
                                <label>
                                    <input type="checkbox" name="backup_timeslots" value="1" checked />
                                    <?php _e('時間帯設定', EASY_BOOKINGER_TEXT_DOMAIN); ?>
                                </label><br>
                                <label>
                                    <input type="checkbox" name="backup_special_availability" value="1" checked />
                                    <?php _e('臨時予約設定', EASY_BOOKINGER_TEXT_DOMAIN); ?>
                                </label><br>
                                <label>
                                    <input type="checkbox" name="backup_admin_emails" value="1" checked />
                                    <?php _e('管理者メール設定', EASY_BOOKINGER_TEXT_DOMAIN); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button(__('バックアップファイルを作成', EASY_BOOKINGER_TEXT_DOMAIN), 'primary', 'create_backup'); ?>
                </form>
            </div>
            
            <div class="eb-backup-section">
                <h2><?php _e('バックアップの復元', EASY_BOOKINGER_TEXT_DOMAIN); ?></h2>
                <p><?php _e('以前に作成したバックアップファイルからデータを復元します。', EASY_BOOKINGER_TEXT_DOMAIN); ?></p>
                <p class="description"><?php _e('※ 復元を実行すると現在のデータは上書きされます。事前にバックアップを作成することをお勧めします。', EASY_BOOKINGER_TEXT_DOMAIN); ?></p>
                
                <form method="post" action="" enctype="multipart/form-data">
                    <?php wp_nonce_field('easy_bookinger_restore', 'easy_bookinger_restore_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('バックアップファイル', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                            <td>
                                <input type="file" name="backup_file" accept=".json" required />
                                <p class="description"><?php _e('Easy Bookingerで作成されたJSONバックアップファイルを選択してください。', EASY_BOOKINGER_TEXT_DOMAIN); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('復元オプション', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                            <td>
                                <label>
                                    <input type="radio" name="restore_mode" value="replace" checked />
                                    <?php _e('既存データを置換', EASY_BOOKINGER_TEXT_DOMAIN); ?>
                                    <span class="description"><?php _e('（現在のデータを削除してバックアップデータを復元）', EASY_BOOKINGER_TEXT_DOMAIN); ?></span>
                                </label><br>
                                <label>
                                    <input type="radio" name="restore_mode" value="merge" />
                                    <?php _e('既存データに追加', EASY_BOOKINGER_TEXT_DOMAIN); ?>
                                    <span class="description"><?php _e('（現在のデータを保持してバックアップデータを追加）', EASY_BOOKINGER_TEXT_DOMAIN); ?></span>
                                </label>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button(__('バックアップを復元', EASY_BOOKINGER_TEXT_DOMAIN), 'secondary', 'restore_backup', true, array('onclick' => 'return confirm("' . esc_js(__('本当に復元を実行しますか？この操作は取り消せません。', EASY_BOOKINGER_TEXT_DOMAIN)) . '")')); ?>
                </form>
            </div>
            
            <div class="eb-backup-section">
                <h2><?php _e('データ統計', EASY_BOOKINGER_TEXT_DOMAIN); ?></h2>
                <?php $this->show_data_statistics(); ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Create backup
     */
    private function create_backup() {
        if (!wp_verify_nonce($_POST['easy_bookinger_backup_nonce'], 'easy_bookinger_backup')) {
            wp_die(__('セキュリティチェックに失敗しました', EASY_BOOKINGER_TEXT_DOMAIN));
        }
        
        $backup_format = sanitize_text_field($_POST['backup_format'] ?? 'json');
        $custom_filename = !empty($_POST['backup_filename']) ? sanitize_file_name($_POST['backup_filename']) : 'easy_bookinger_backup_' . date('Y-m-d');
        
        if ($backup_format === 'csv') {
            $this->create_csv_backup($custom_filename);
        } else {
            $this->create_json_backup($custom_filename);
        }
    }
    
    /**
     * Create JSON backup
     */
    private function create_json_backup($filename) {
        global $wpdb;
        $database = EasyBookinger_Database::instance();
        
        $backup_data = array(
            'plugin' => 'easy-bookinger',
            'version' => EASY_BOOKINGER_VERSION,
            'created_at' => current_time('mysql'),
            'site_url' => get_site_url(),
            'data' => array()
        );
        
        // Backup bookings
        if (isset($_POST['backup_bookings'])) {
            $bookings = $database->get_bookings(array('status' => ''));
            $backup_data['data']['bookings'] = $bookings;
        }
        
        // Backup settings
        if (isset($_POST['backup_settings'])) {
            $settings = get_option('easy_bookinger_settings', array());
            $backup_data['data']['settings'] = $settings;
        }
        
        // Backup restrictions
        if (isset($_POST['backup_restrictions'])) {
            $restrictions = $database->get_restricted_dates();
            $backup_data['data']['restrictions'] = $restrictions;
        }
        
        // Backup quotas
        if (isset($_POST['backup_quotas'])) {
            $quotas_table = $wpdb->prefix . 'easy_bookinger_booking_quotas';
            $quotas = $wpdb->get_results("SELECT * FROM $quotas_table");
            $backup_data['data']['quotas'] = $quotas;
        }
        
        // Backup time slots
        if (isset($_POST['backup_timeslots'])) {
            $timeslots = $database->get_time_slots();
            $backup_data['data']['timeslots'] = $timeslots;
        }
        
        // Backup special availability
        if (isset($_POST['backup_special_availability'])) {
            $special_availability = $database->get_special_availability();
            $backup_data['data']['special_availability'] = $special_availability;
        }
        
        // Backup admin emails
        if (isset($_POST['backup_admin_emails'])) {
            $admin_emails = $database->get_admin_emails();
            $backup_data['data']['admin_emails'] = $admin_emails;
        }
        
        // Generate filename
        $full_filename = $filename . '.json';
        
        // Try direct download first
        if ($this->try_direct_json_download($backup_data, $full_filename)) {
            return;
        }
        
        // Fallback: Save to server and provide download link
        $this->save_json_to_server_and_notify($backup_data, $full_filename);
    }
    
    /**
     * Create CSV backup
     */
    private function create_csv_backup($filename) {
        $database = EasyBookinger_Database::instance();
        
        // For CSV backup, we primarily export booking data
        if (!isset($_POST['backup_bookings'])) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-warning is-dismissible"><p>' . __('CSV形式では予約データの選択が必要です', EASY_BOOKINGER_TEXT_DOMAIN) . '</p></div>';
            });
            return;
        }
        
        $bookings = $database->get_bookings(array('status' => ''));
        
        if (empty($bookings)) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-warning is-dismissible"><p>' . __('バックアップするデータがありません', EASY_BOOKINGER_TEXT_DOMAIN) . '</p></div>';
            });
            return;
        }
        
        // Generate filename
        $full_filename = $filename . '.csv';
        
        // Try direct download first
        if ($this->try_direct_csv_download($bookings, $full_filename)) {
            return;
        }
        
        // Fallback: Save to server and provide download link
        $this->save_csv_to_server_and_notify($bookings, $full_filename);
    }
    
    /**
     * Generate CSV content for backup
     */
    private function generate_backup_csv_content($bookings) {
        $settings = get_option('easy_bookinger_settings', array());
        $booking_fields = isset($settings['booking_fields']) ? $settings['booking_fields'] : array();
        
        // Create header row
        $headers = array(
            __('ID', EASY_BOOKINGER_TEXT_DOMAIN),
            __('予約日', EASY_BOOKINGER_TEXT_DOMAIN),
            __('時間', EASY_BOOKINGER_TEXT_DOMAIN),
            __('氏名', EASY_BOOKINGER_TEXT_DOMAIN),
            __('メール', EASY_BOOKINGER_TEXT_DOMAIN),
            __('電話', EASY_BOOKINGER_TEXT_DOMAIN),
            __('コメント', EASY_BOOKINGER_TEXT_DOMAIN),
            __('ステータス', EASY_BOOKINGER_TEXT_DOMAIN),
            __('登録日時', EASY_BOOKINGER_TEXT_DOMAIN),
            __('更新日時', EASY_BOOKINGER_TEXT_DOMAIN)
        );
        
        // Add custom field headers
        foreach ($booking_fields as $field) {
            if (!in_array($field['name'], array('user_name', 'email', 'email_confirm', 'phone', 'comment', 'booking_time'))) {
                $headers[] = $field['label'];
            }
        }
        
        // Open output stream
        $output = fopen('php://output', 'w');
        
        // Write header row
        fputcsv($output, $headers);
        
        // Write data rows
        foreach ($bookings as $booking) {
            $form_data = maybe_unserialize($booking->form_data);
            
            $row_data = array(
                $booking->id,
                $booking->booking_date,
                $booking->booking_time,
                $booking->user_name,
                $booking->email,
                $booking->phone,
                $booking->comment,
                $booking->status === 'active' ? __('有効', EASY_BOOKINGER_TEXT_DOMAIN) : __('無効', EASY_BOOKINGER_TEXT_DOMAIN),
                $booking->created_at,
                $booking->updated_at
            );
            
            // Add custom field data
            foreach ($booking_fields as $field) {
                if (!in_array($field['name'], array('user_name', 'email', 'email_confirm', 'phone', 'comment', 'booking_time'))) {
                    $value = isset($form_data[$field['name']]) ? $form_data[$field['name']] : '';
                    $row_data[] = $value;
                }
            }
            
            fputcsv($output, $row_data);
        }
        
        fclose($output);
    }
    
    /**
     * Restore backup
     */
    private function restore_backup() {
        if (!wp_verify_nonce($_POST['easy_bookinger_restore_nonce'], 'easy_bookinger_restore')) {
            wp_die(__('セキュリティチェックに失敗しました', EASY_BOOKINGER_TEXT_DOMAIN));
        }
        
        // Validate file
        if (empty($_FILES['backup_file']['tmp_name'])) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error is-dismissible"><p>' . __('バックアップファイルが選択されていません', EASY_BOOKINGER_TEXT_DOMAIN) . '</p></div>';
            });
            return;
        }
        
        $file_content = file_get_contents($_FILES['backup_file']['tmp_name']);
        $backup_data = json_decode($file_content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE || !isset($backup_data['plugin']) || $backup_data['plugin'] !== 'easy-bookinger') {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error is-dismissible"><p>' . __('無効なバックアップファイルです', EASY_BOOKINGER_TEXT_DOMAIN) . '</p></div>';
            });
            return;
        }
        
        global $wpdb;
        $database = EasyBookinger_Database::instance();
        $restore_mode = sanitize_text_field($_POST['restore_mode']);
        $restored_items = array();
        
        try {
            // Restore bookings
            if (isset($backup_data['data']['bookings'])) {
                if ($restore_mode === 'replace') {
                    $bookings_table = $wpdb->prefix . 'easy_bookinger_bookings';
                    $wpdb->query("TRUNCATE TABLE $bookings_table");
                }
                
                foreach ($backup_data['data']['bookings'] as $booking) {
                    $booking_array = (array)$booking;
                    unset($booking_array['id']); // Remove ID to create new records
                    $wpdb->insert($wpdb->prefix . 'easy_bookinger_bookings', $booking_array);
                }
                $restored_items[] = __('予約データ', EASY_BOOKINGER_TEXT_DOMAIN);
            }
            
            // Restore settings
            if (isset($backup_data['data']['settings'])) {
                update_option('easy_bookinger_settings', $backup_data['data']['settings']);
                $restored_items[] = __('プラグイン設定', EASY_BOOKINGER_TEXT_DOMAIN);
            }
            
            // Restore restrictions
            if (isset($backup_data['data']['restrictions'])) {
                if ($restore_mode === 'replace') {
                    $restrictions_table = $wpdb->prefix . 'easy_bookinger_date_restrictions';
                    $wpdb->query("TRUNCATE TABLE $restrictions_table");
                }
                
                foreach ($backup_data['data']['restrictions'] as $restriction) {
                    $restriction_array = (array)$restriction;
                    unset($restriction_array['id']);
                    $wpdb->insert($wpdb->prefix . 'easy_bookinger_date_restrictions', $restriction_array);
                }
                $restored_items[] = __('日付制限', EASY_BOOKINGER_TEXT_DOMAIN);
            }
            
            // Restore quotas
            if (isset($backup_data['data']['quotas'])) {
                if ($restore_mode === 'replace') {
                    $quotas_table = $wpdb->prefix . 'easy_bookinger_booking_quotas';
                    $wpdb->query("TRUNCATE TABLE $quotas_table");
                }
                
                foreach ($backup_data['data']['quotas'] as $quota) {
                    $quota_array = (array)$quota;
                    unset($quota_array['id']);
                    $wpdb->insert($wpdb->prefix . 'easy_bookinger_booking_quotas', $quota_array);
                }
                $restored_items[] = __('予約枠設定', EASY_BOOKINGER_TEXT_DOMAIN);
            }
            
            // Restore time slots
            if (isset($backup_data['data']['timeslots'])) {
                if ($restore_mode === 'replace') {
                    $timeslots_table = $wpdb->prefix . 'easy_bookinger_time_slots';
                    $wpdb->query("TRUNCATE TABLE $timeslots_table");
                }
                
                foreach ($backup_data['data']['timeslots'] as $timeslot) {
                    $timeslot_array = (array)$timeslot;
                    unset($timeslot_array['id']);
                    $wpdb->insert($wpdb->prefix . 'easy_bookinger_time_slots', $timeslot_array);
                }
                $restored_items[] = __('時間帯設定', EASY_BOOKINGER_TEXT_DOMAIN);
            }
            
            $message = __('バックアップの復元が完了しました', EASY_BOOKINGER_TEXT_DOMAIN) . ': ' . implode(', ', $restored_items);
            add_action('admin_notices', function() use ($message) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
            });
            
        } catch (Exception $e) {
            add_action('admin_notices', function() use ($e) {
                echo '<div class="notice notice-error is-dismissible"><p>' . __('復元中にエラーが発生しました', EASY_BOOKINGER_TEXT_DOMAIN) . ': ' . esc_html($e->getMessage()) . '</p></div>';
            });
        }
    }
    
    /**
     * Show data statistics
     */
    private function show_data_statistics() {
        global $wpdb;
        $database = EasyBookinger_Database::instance();
        
        // Get counts
        $bookings_count = count($database->get_bookings(array('status' => '')));
        $active_bookings_count = count($database->get_bookings(array('status' => 'active')));
        $inactive_bookings_count = count($database->get_bookings(array('status' => 'inactive')));
        
        $restrictions_count = count($database->get_restricted_dates());
        $timeslots_count = count($database->get_time_slots());
        
        $quotas_table = $wpdb->prefix . 'easy_bookinger_booking_quotas';
        $quotas_count = $wpdb->get_var("SELECT COUNT(*) FROM $quotas_table");
        
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('データ種別', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                    <th><?php _e('件数', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?php _e('予約データ（総数）', EASY_BOOKINGER_TEXT_DOMAIN); ?></td>
                    <td><?php echo esc_html($bookings_count); ?>件</td>
                </tr>
                <tr>
                    <td><?php _e('有効な予約', EASY_BOOKINGER_TEXT_DOMAIN); ?></td>
                    <td><?php echo esc_html($active_bookings_count); ?>件</td>
                </tr>
                <tr>
                    <td><?php _e('無効化された予約', EASY_BOOKINGER_TEXT_DOMAIN); ?></td>
                    <td><?php echo esc_html($inactive_bookings_count); ?>件</td>
                </tr>
                <tr>
                    <td><?php _e('日付制限', EASY_BOOKINGER_TEXT_DOMAIN); ?></td>
                    <td><?php echo esc_html($restrictions_count); ?>件</td>
                </tr>
                <tr>
                    <td><?php _e('予約枠設定', EASY_BOOKINGER_TEXT_DOMAIN); ?></td>
                    <td><?php echo esc_html($quotas_count); ?>件</td>
                </tr>
                <tr>
                    <td><?php _e('時間帯設定', EASY_BOOKINGER_TEXT_DOMAIN); ?></td>
                    <td><?php echo esc_html($timeslots_count); ?>件</td>
                </tr>
            </tbody>
        </table>
        <?php
    }
    
    /**
     * Try direct JSON download
     */
    private function try_direct_json_download($backup_data, $filename) {
        // Check if headers have already been sent
        if (headers_sent()) {
            return false;
        }
        
        try {
            // Set headers for download
            header('Content-Type: application/json; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Transfer-Encoding: binary');
            header('Pragma: no-cache');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Expires: 0');
            
            // Prevent any previous output
            if (ob_get_level()) {
                ob_end_clean();
            }
            
            echo json_encode($backup_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Try direct CSV download
     */
    private function try_direct_csv_download($bookings, $filename) {
        // Check if headers have already been sent
        if (headers_sent()) {
            return false;
        }
        
        try {
            // Set headers for download
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Transfer-Encoding: binary');
            header('Pragma: no-cache');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Expires: 0');
            
            // Prevent any previous output
            if (ob_get_level()) {
                ob_end_clean();
            }
            
            // Add BOM for UTF-8 (helps with Excel compatibility)
            echo "\xEF\xBB\xBF";
            
            // Generate CSV content
            $this->generate_backup_csv_content($bookings);
            exit;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Save JSON to server and provide download link
     */
    private function save_json_to_server_and_notify($backup_data, $filename) {
        // Create exports directory if it doesn't exist
        $upload_dir = wp_upload_dir();
        $exports_dir = $upload_dir['basedir'] . '/easy-bookinger-exports';
        
        if (!file_exists($exports_dir)) {
            wp_mkdir_p($exports_dir);
        }
        
        // Add .htaccess to prevent direct access listing
        $htaccess_file = $exports_dir . '/.htaccess';
        if (!file_exists($htaccess_file)) {
            file_put_contents($htaccess_file, "Options -Indexes\n");
        }
        
        // Create unique filename with timestamp
        $unique_filename = date('Y-m-d_H-i-s') . '_' . $filename;
        $file_path = $exports_dir . '/' . $unique_filename;
        
        try {
            // Generate JSON content to file
            $json_content = json_encode($backup_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            
            if (file_put_contents($file_path, $json_content) === false) {
                throw new Exception(__('ファイルの作成に失敗しました', EASY_BOOKINGER_TEXT_DOMAIN));
            }
            
            // Create download URL
            $download_url = $upload_dir['baseurl'] . '/easy-bookinger-exports/' . $unique_filename;
            
            add_action('admin_notices', function() use ($download_url, $unique_filename) {
                echo '<div class="notice notice-success is-dismissible">';
                echo '<p><strong>' . __('バックアップが完了しました', EASY_BOOKINGER_TEXT_DOMAIN) . '</strong></p>';
                echo '<p>' . __('ファイルをサーバーに保存しました。下記のリンクからダウンロードできます。', EASY_BOOKINGER_TEXT_DOMAIN) . '</p>';
                echo '<p><a href="' . esc_url($download_url) . '" class="button button-primary" target="_blank">';
                echo esc_html($unique_filename) . ' をダウンロード</a></p>';
                echo '<p><small>' . __('※ このファイルは30日後に自動削除されます。', EASY_BOOKINGER_TEXT_DOMAIN) . '</small></p>';
                echo '</div>';
            });
            
        } catch (Exception $e) {
            add_action('admin_notices', function() use ($e) {
                echo '<div class="notice notice-error is-dismissible"><p>' . __('バックアップ中にエラーが発生しました', EASY_BOOKINGER_TEXT_DOMAIN) . ': ' . esc_html($e->getMessage()) . '</p></div>';
            });
        }
    }
    
    /**
     * Save CSV to server and provide download link
     */
    private function save_csv_to_server_and_notify($bookings, $filename) {
        // Create exports directory if it doesn't exist
        $upload_dir = wp_upload_dir();
        $exports_dir = $upload_dir['basedir'] . '/easy-bookinger-exports';
        
        if (!file_exists($exports_dir)) {
            wp_mkdir_p($exports_dir);
        }
        
        // Add .htaccess to prevent direct access listing
        $htaccess_file = $exports_dir . '/.htaccess';
        if (!file_exists($htaccess_file)) {
            file_put_contents($htaccess_file, "Options -Indexes\n");
        }
        
        // Create unique filename with timestamp
        $unique_filename = date('Y-m-d_H-i-s') . '_' . $filename;
        $file_path = $exports_dir . '/' . $unique_filename;
        
        try {
            // Generate CSV content to file
            $file_handle = fopen($file_path, 'w');
            
            if ($file_handle === false) {
                throw new Exception(__('ファイルの作成に失敗しました', EASY_BOOKINGER_TEXT_DOMAIN));
            }
            
            // Add BOM for UTF-8 (helps with Excel compatibility)
            fwrite($file_handle, "\xEF\xBB\xBF");
            
            // Generate CSV content
            $this->generate_backup_csv_to_file($bookings, $file_handle);
            fclose($file_handle);
            
            // Create download URL
            $download_url = $upload_dir['baseurl'] . '/easy-bookinger-exports/' . $unique_filename;
            
            add_action('admin_notices', function() use ($download_url, $unique_filename) {
                echo '<div class="notice notice-success is-dismissible">';
                echo '<p><strong>' . __('バックアップが完了しました', EASY_BOOKINGER_TEXT_DOMAIN) . '</strong></p>';
                echo '<p>' . __('ファイルをサーバーに保存しました。下記のリンクからダウンロードできます。', EASY_BOOKINGER_TEXT_DOMAIN) . '</p>';
                echo '<p><a href="' . esc_url($download_url) . '" class="button button-primary" target="_blank">';
                echo esc_html($unique_filename) . ' をダウンロード</a></p>';
                echo '<p><small>' . __('※ このファイルは30日後に自動削除されます。', EASY_BOOKINGER_TEXT_DOMAIN) . '</small></p>';
                echo '</div>';
            });
            
        } catch (Exception $e) {
            add_action('admin_notices', function() use ($e) {
                echo '<div class="notice notice-error is-dismissible"><p>' . __('バックアップ中にエラーが発生しました', EASY_BOOKINGER_TEXT_DOMAIN) . ': ' . esc_html($e->getMessage()) . '</p></div>';
            });
        }
    }
    
    /**
     * Generate CSV content for backup to file handle
     */
    private function generate_backup_csv_to_file($bookings, $file_handle) {
        $settings = get_option('easy_bookinger_settings', array());
        $booking_fields = isset($settings['booking_fields']) ? $settings['booking_fields'] : array();
        
        // Create header row
        $headers = array(
            __('ID', EASY_BOOKINGER_TEXT_DOMAIN),
            __('予約日', EASY_BOOKINGER_TEXT_DOMAIN),
            __('時間', EASY_BOOKINGER_TEXT_DOMAIN),
            __('氏名', EASY_BOOKINGER_TEXT_DOMAIN),
            __('メール', EASY_BOOKINGER_TEXT_DOMAIN),
            __('電話', EASY_BOOKINGER_TEXT_DOMAIN),
            __('コメント', EASY_BOOKINGER_TEXT_DOMAIN),
            __('ステータス', EASY_BOOKINGER_TEXT_DOMAIN),
            __('登録日時', EASY_BOOKINGER_TEXT_DOMAIN),
            __('更新日時', EASY_BOOKINGER_TEXT_DOMAIN)
        );
        
        // Add custom field headers
        foreach ($booking_fields as $field) {
            if (!in_array($field['name'], array('user_name', 'email', 'email_confirm', 'phone', 'comment', 'booking_time'))) {
                $headers[] = $field['label'];
            }
        }
        
        // Write header row
        fputcsv($file_handle, $headers);
        
        // Write data rows
        foreach ($bookings as $booking) {
            $form_data = maybe_unserialize($booking->form_data);
            
            $row_data = array(
                $booking->id,
                $booking->booking_date,
                $booking->booking_time,
                $booking->user_name,
                $booking->email,
                $booking->phone,
                $booking->comment,
                $booking->status === 'active' ? __('有効', EASY_BOOKINGER_TEXT_DOMAIN) : __('無効', EASY_BOOKINGER_TEXT_DOMAIN),
                $booking->created_at,
                $booking->updated_at
            );
            
            // Add custom field data
            foreach ($booking_fields as $field) {
                if (!in_array($field['name'], array('user_name', 'email', 'email_confirm', 'phone', 'comment', 'booking_time'))) {
                    $value = isset($form_data[$field['name']]) ? $form_data[$field['name']] : '';
                    $row_data[] = $value;
                }
            }
            
            fputcsv($file_handle, $row_data);
        }
    }
}