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
        
        // Generate filename
        $filename = 'easy_bookinger_backup_' . date('Y-m-d_H-i-s') . '.json';
        
        // Set headers for download
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        echo json_encode($backup_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
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
}