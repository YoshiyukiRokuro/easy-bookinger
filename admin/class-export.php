<?php
/**
 * Export functionality
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class EasyBookinger_Export {
    
    /**
     * Render export page
     */
    public function render_export_page() {
        if (isset($_POST['export_csv'])) {
            $this->export_to_csv();
            return;
        }
        
        ?>
        <div class="wrap">
            <h1><?php _e('Easy Bookinger - エクスポート', EASY_BOOKINGER_TEXT_DOMAIN); ?></h1>
            
            <div class="eb-export-section">
                <h2><?php _e('予約データのエクスポート', EASY_BOOKINGER_TEXT_DOMAIN); ?></h2>
                <p><?php _e('登録された予約データをCSV形式でダウンロードできます。', EASY_BOOKINGER_TEXT_DOMAIN); ?></p>
                
                <form method="post" action="">
                    <?php wp_nonce_field('easy_bookinger_export', 'easy_bookinger_export_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('ファイル名', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                            <td>
                                <input type="text" name="filename" value="<?php echo esc_attr('easy_bookinger_export_' . date('Y-m-d')); ?>" style="width: 300px;" />
                                <span>.csv</span>
                                <p class="description"><?php _e('ダウンロードするCSVファイルの名前を指定してください（拡張子は自動で付きます）', EASY_BOOKINGER_TEXT_DOMAIN); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('エクスポート期間', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                            <td>
                                <label>
                                    <input type="radio" name="date_range" value="all" checked />
                                    <?php _e('全期間', EASY_BOOKINGER_TEXT_DOMAIN); ?>
                                </label><br>
                                <label>
                                    <input type="radio" name="date_range" value="custom" />
                                    <?php _e('期間を指定', EASY_BOOKINGER_TEXT_DOMAIN); ?>
                                </label>
                                <div id="custom-date-range" style="margin-top: 10px; display: none;">
                                    <input type="date" name="date_from" />
                                    <?php _e('〜', EASY_BOOKINGER_TEXT_DOMAIN); ?>
                                    <input type="date" name="date_to" />
                                </div>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('ステータス', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                            <td>
                                <label>
                                    <input type="radio" name="status_filter" value="all" checked />
                                    <?php _e('すべて', EASY_BOOKINGER_TEXT_DOMAIN); ?>
                                </label><br>
                                <label>
                                    <input type="radio" name="status_filter" value="active" />
                                    <?php _e('有効のみ', EASY_BOOKINGER_TEXT_DOMAIN); ?>
                                </label><br>
                                <label>
                                    <input type="radio" name="status_filter" value="inactive" />
                                    <?php _e('無効のみ', EASY_BOOKINGER_TEXT_DOMAIN); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button(__('CSVファイルをダウンロード', EASY_BOOKINGER_TEXT_DOMAIN), 'primary', 'export_csv'); ?>
                </form>
            </div>
            
            <div class="eb-export-section">
                <h2><?php _e('統計情報', EASY_BOOKINGER_TEXT_DOMAIN); ?></h2>
                <?php $this->show_statistics(); ?>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('input[name="date_range"]').change(function() {
                if ($(this).val() === 'custom') {
                    $('#custom-date-range').show();
                } else {
                    $('#custom-date-range').hide();
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Export to CSV
     */
    private function export_to_csv() {
        if (!wp_verify_nonce($_POST['easy_bookinger_export_nonce'], 'easy_bookinger_export')) {
            wp_die(__('セキュリティチェックに失敗しました', EASY_BOOKINGER_TEXT_DOMAIN));
        }
        
        $database = EasyBookinger_Database::instance();
        
        // Prepare query arguments
        $args = array(
            'orderby' => 'booking_date',
            'order' => 'ASC'
        );
        
        // Date range filter
        if ($_POST['date_range'] === 'custom') {
            if (!empty($_POST['date_from'])) {
                $args['date_from'] = sanitize_text_field($_POST['date_from']);
            }
            if (!empty($_POST['date_to'])) {
                $args['date_to'] = sanitize_text_field($_POST['date_to']);
            }
        }
        
        // Status filter
        if ($_POST['status_filter'] !== 'all') {
            $args['status'] = sanitize_text_field($_POST['status_filter']);
        } else {
            unset($args['status']); // Remove status filter to get all records
        }
        
        $bookings = $database->get_bookings($args);
        
        if (empty($bookings)) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-warning is-dismissible"><p>' . __('エクスポートするデータがありません', EASY_BOOKINGER_TEXT_DOMAIN) . '</p></div>';
            });
            return;
        }
        
        // Get custom filename or use default
        $filename = !empty($_POST['filename']) ? sanitize_file_name($_POST['filename']) : 'easy_bookinger_export_' . date('Y-m-d');
        $filename .= '.csv';
        
        // Try direct download first
        if ($this->try_direct_download($bookings, $filename)) {
            return;
        }
        
        // Fallback: Save to server and provide download link
        $this->save_to_server_and_notify($bookings, $filename);
    }
    
    /**
     * Generate CSV content
     */
    private function generate_csv_content($bookings) {
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
            __('登録日時', EASY_BOOKINGER_TEXT_DOMAIN)
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
                $booking->created_at
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
     * Try direct download
     */
    private function try_direct_download($bookings, $filename) {
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
            $this->generate_csv_content($bookings);
            exit;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Save to server and provide download link
     */
    private function save_to_server_and_notify($bookings, $filename) {
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
            $this->generate_csv_to_file($bookings, $file_handle);
            fclose($file_handle);
            
            // Create download URL
            $download_url = $upload_dir['baseurl'] . '/easy-bookinger-exports/' . $unique_filename;
            
            // Store file info for cleanup (optional)
            $this->store_export_file_info($unique_filename, $file_path);
            
            add_action('admin_notices', function() use ($download_url, $unique_filename) {
                echo '<div class="notice notice-success is-dismissible">';
                echo '<p><strong>' . __('エクスポートが完了しました', EASY_BOOKINGER_TEXT_DOMAIN) . '</strong></p>';
                echo '<p>' . __('ファイルをサーバーに保存しました。下記のリンクからダウンロードできます。', EASY_BOOKINGER_TEXT_DOMAIN) . '</p>';
                echo '<p><a href="' . esc_url($download_url) . '" class="button button-primary" target="_blank">';
                echo esc_html($unique_filename) . ' をダウンロード</a></p>';
                echo '<p><small>' . __('※ このファイルは30日後に自動削除されます。', EASY_BOOKINGER_TEXT_DOMAIN) . '</small></p>';
                echo '</div>';
            });
            
        } catch (Exception $e) {
            add_action('admin_notices', function() use ($e) {
                echo '<div class="notice notice-error is-dismissible"><p>' . __('エクスポート中にエラーが発生しました', EASY_BOOKINGER_TEXT_DOMAIN) . ': ' . esc_html($e->getMessage()) . '</p></div>';
            });
        }
    }
    
    /**
     * Generate CSV content to file handle
     */
    private function generate_csv_to_file($bookings, $file_handle) {
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
            __('登録日時', EASY_BOOKINGER_TEXT_DOMAIN)
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
                $booking->created_at
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
    
    /**
     * Store export file info for cleanup
     */
    private function store_export_file_info($filename, $file_path) {
        $export_files = get_option('easy_bookinger_export_files', array());
        $export_files[$filename] = array(
            'path' => $file_path,
            'created' => time(),
            'expires' => time() + (30 * 24 * 60 * 60) // 30 days
        );
        update_option('easy_bookinger_export_files', $export_files);
    }
    
    /**
     * Show statistics
     */
    private function show_statistics() {
        $database = EasyBookinger_Database::instance();
        
        // Get total bookings
        $total_bookings = count($database->get_bookings(array('status' => '')));
        $active_bookings = count($database->get_bookings(array('status' => 'active')));
        $inactive_bookings = count($database->get_bookings(array('status' => 'inactive')));
        
        // Get this month's bookings
        $this_month_start = date('Y-m-01');
        $this_month_end = date('Y-m-t');
        $this_month_bookings = count($database->get_bookings(array(
            'status' => 'active',
            'date_from' => $this_month_start,
            'date_to' => $this_month_end
        )));
        
        // Get upcoming bookings
        $upcoming_bookings = count($database->get_bookings(array(
            'status' => 'active',
            'date_from' => date('Y-m-d')
        )));
        
        ?>
        <div class="eb-statistics">
            <div class="eb-stat-box">
                <h3><?php _e('総予約数', EASY_BOOKINGER_TEXT_DOMAIN); ?></h3>
                <div class="eb-stat-number"><?php echo esc_html($total_bookings); ?></div>
            </div>
            
            <div class="eb-stat-box">
                <h3><?php _e('有効予約数', EASY_BOOKINGER_TEXT_DOMAIN); ?></h3>
                <div class="eb-stat-number"><?php echo esc_html($active_bookings); ?></div>
            </div>
            
            <div class="eb-stat-box">
                <h3><?php _e('無効予約数', EASY_BOOKINGER_TEXT_DOMAIN); ?></h3>
                <div class="eb-stat-number"><?php echo esc_html($inactive_bookings); ?></div>
            </div>
            
            <div class="eb-stat-box">
                <h3><?php _e('今月の予約', EASY_BOOKINGER_TEXT_DOMAIN); ?></h3>
                <div class="eb-stat-number"><?php echo esc_html($this_month_bookings); ?></div>
            </div>
            
            <div class="eb-stat-box">
                <h3><?php _e('今後の予約', EASY_BOOKINGER_TEXT_DOMAIN); ?></h3>
                <div class="eb-stat-number"><?php echo esc_html($upcoming_bookings); ?></div>
            </div>
        </div>
        
        <style>
        .eb-statistics {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-top: 20px;
        }
        
        .eb-stat-box {
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 20px;
            text-align: center;
            min-width: 150px;
        }
        
        .eb-stat-box h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #666;
        }
        
        .eb-stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #0073aa;
        }
        </style>
        <?php
    }
}