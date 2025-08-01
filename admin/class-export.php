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
        // Show success message if redirected after export
        if (isset($_GET['export_success']) && $_GET['export_success'] === '1') {
            $filename = isset($_GET['filename']) ? sanitize_text_field($_GET['filename']) : '';
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p><strong>' . __('エクスポートが完了しました', EASY_BOOKINGER_TEXT_DOMAIN) . '</strong></p>';
            echo '<p>' . __('ファイルを保存しました。下記の「保存済みエクスポートファイル」セクションからダウンロードできます。', EASY_BOOKINGER_TEXT_DOMAIN) . '</p>';
            if ($filename) {
                echo '<p>' . __('ファイル名:', EASY_BOOKINGER_TEXT_DOMAIN) . ' <code>' . esc_html($filename) . '</code></p>';
            }
            echo '</div>';
        }
        
        // Show error message if redirected after export error
        if (isset($_GET['export_error']) && $_GET['export_error'] === '1') {
            $error_message = isset($_GET['error_message']) ? urldecode(sanitize_text_field($_GET['error_message'])) : __('エクスポート中にエラーが発生しました', EASY_BOOKINGER_TEXT_DOMAIN);
            echo '<div class="notice notice-error is-dismissible">';
            echo '<p><strong>' . esc_html($error_message) . '</strong></p>';
            echo '</div>';
        }
        
        // Handle file deletion
        if (isset($_POST['action']) && $_POST['action'] === 'delete_file') {
            $this->handle_file_deletion();
        }
        
        if (isset($_POST['export_csv'])) {
            $this->export_to_csv();
            return;
        }
        
        ?>
        <div class="wrap">
            <h1><?php _e('Easy Bookinger - エクスポート', EASY_BOOKINGER_TEXT_DOMAIN); ?></h1>
            
            <div class="eb-export-section">
                <h2><?php _e('予約データのエクスポート', EASY_BOOKINGER_TEXT_DOMAIN); ?></h2>
                <p><?php _e('登録された予約データをCSV形式で保存できます。', EASY_BOOKINGER_TEXT_DOMAIN); ?></p>
                
                <form method="post" action="">
                    <?php wp_nonce_field('easy_bookinger_export', 'easy_bookinger_export_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('ファイル名', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                            <td>
                                <?php 
                                $timezone = get_option('timezone_string') ?: 'Asia/Tokyo';
                                $current_time = new DateTime('now', new DateTimeZone($timezone));
                                $default_filename = 'easy_bookinger_export_' . $current_time->format('Y-m-d');
                                ?>
                                <input type="text" name="filename" value="<?php echo esc_attr($default_filename); ?>" style="width: 300px;" />
                                <span>.csv</span>
                                <p class="description"><?php _e('保存するCSVファイルの名前を指定してください（拡張子は自動で付きます）', EASY_BOOKINGER_TEXT_DOMAIN); ?></p>
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
                    
                    <?php submit_button(__('CSVファイルを保存', EASY_BOOKINGER_TEXT_DOMAIN), 'primary', 'export_csv'); ?>
                </form>
            </div>
            
            <div class="eb-export-section">
                <h2><?php _e('保存済みエクスポートファイル', EASY_BOOKINGER_TEXT_DOMAIN); ?></h2>
                <p><?php _e('以前に作成されたエクスポートファイルの一覧です。ダウンロードまたは削除することができます。', EASY_BOOKINGER_TEXT_DOMAIN); ?></p>
                <?php 
                // Cleanup expired files first
                EasyBookinger_File_Manager::cleanup_expired_files();
                // Render file list
                EasyBookinger_File_Manager::render_file_list('export'); 
                ?>
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
        
        // Start output buffering to prevent header issues
        if (!headers_sent()) {
            ob_start();
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
            // Clean output buffer before adding admin notice
            if (ob_get_level()) {
                ob_end_clean();
            }
            
            // Redirect back to export page with error message
            $redirect_url = add_query_arg(array(
                'page' => 'easy-bookinger-export',
                'export_error' => '1',
                'error_message' => urlencode(__('エクスポートするデータが0件です。データが登録されていることを確認してください。', EASY_BOOKINGER_TEXT_DOMAIN))
            ), admin_url('admin.php'));
            
            wp_redirect($redirect_url);
            exit;
        }
        
        // Get custom filename or use default with WordPress locale time
        $timezone = get_option('timezone_string') ?: 'Asia/Tokyo';
        $current_time = new DateTime('now', new DateTimeZone($timezone));
        $default_filename = 'easy_bookinger_export_' . $current_time->format('Y-m-d');
        
        $filename = !empty($_POST['filename']) ? sanitize_file_name($_POST['filename']) : $default_filename;
        $filename .= '.csv';
        
        // Generate CSV content
        $csv_content = $this->generate_csv_content_string($bookings);
        
        // Save file using file manager
        $result = EasyBookinger_File_Manager::save_file($csv_content, $filename, 'export');
        
        if ($result['success']) {
            // Clean output buffer before redirect
            if (ob_get_level()) {
                ob_end_clean();
            }
            
            // Check if headers have already been sent
            if (headers_sent()) {
                // If headers are already sent, show a JavaScript redirect instead
                echo '<script type="text/javascript">';
                echo 'window.location.href = "' . esc_url(add_query_arg(array(
                    'page' => 'easy-bookinger-export',
                    'export_success' => '1',
                    'filename' => urlencode($result['filename'])
                ), admin_url('admin.php'))) . '";';
                echo '</script>';
                exit;
            } else {
                // Show success notice and redirect to prevent resubmission
                $redirect_url = add_query_arg(array(
                    'page' => 'easy-bookinger-export',
                    'export_success' => '1',
                    'filename' => urlencode($result['filename'])
                ), admin_url('admin.php'));
                wp_redirect($redirect_url);
                exit;
            }
        } else {
            // Clean output buffer before adding admin notice
            if (ob_get_level()) {
                ob_end_clean();
            }
            add_action('admin_notices', function() use ($result) {
                echo '<div class="notice notice-error is-dismissible"><p>' . __('エクスポート中にエラーが発生しました', EASY_BOOKINGER_TEXT_DOMAIN) . ': ' . esc_html($result['error']) . '</p></div>';
            });
        }
    }
    
    /**
     * Generate CSV content as string
     */
    private function generate_csv_content_string($bookings) {
        $settings = get_option('easy_bookinger_settings', array());
        $booking_fields = isset($settings['booking_fields']) ? $settings['booking_fields'] : array();
        $timezone = isset($settings['timezone']) ? $settings['timezone'] : 'Asia/Tokyo';
        
        // Create header row to match admin booking list
        $headers = array(
            __('ID', EASY_BOOKINGER_TEXT_DOMAIN),
            __('予約日', EASY_BOOKINGER_TEXT_DOMAIN),
            __('予約時間', EASY_BOOKINGER_TEXT_DOMAIN)
        );
        
        // Add booking fields headers (excluding email_confirm)
        foreach ($booking_fields as $field) {
            if ($field['name'] !== 'email_confirm') {
                $headers[] = $field['label'];
            }
        }
        
        // Add status and registration date
        $headers[] = __('ステータス', EASY_BOOKINGER_TEXT_DOMAIN);
        $headers[] = __('登録日', EASY_BOOKINGER_TEXT_DOMAIN);
        
        // Start with BOM for UTF-8 (helps with Excel compatibility)
        $csv_content = "\xEF\xBB\xBF";
        
        // Create CSV content using memory stream
        $stream = fopen('php://memory', 'r+');
        
        // Write header row
        fputcsv($stream, $headers);
        
        // Write data rows
        foreach ($bookings as $booking) {
            $form_data = maybe_unserialize($booking->form_data);
            
            // Convert created_at timestamp to configured timezone  
            $created_datetime = '';
            if (!empty($booking->created_at)) {
                try {
                    $date = new DateTime($booking->created_at, new DateTimeZone('UTC'));
                    $date->setTimezone(new DateTimeZone($timezone));
                    $created_datetime = $date->format('Y/m/d H:i'); // Match admin display format
                } catch (Exception $e) {
                    $created_datetime = $booking->created_at; // Fallback to original
                }
            }
            
            $row_data = array(
                $booking->id,
                date('Y/m/d', strtotime($booking->booking_date)), // Match admin display format
                $this->format_booking_time_for_export($booking->booking_time) // Format like admin
            );
            
            // Add booking field data (excluding email_confirm)
            foreach ($booking_fields as $field) {
                if ($field['name'] !== 'email_confirm') {
                    $value = isset($form_data[$field['name']]) ? $form_data[$field['name']] : '';
                    $row_data[] = $value;
                }
            }
            
            // Add status and created date
            $row_data[] = $booking->status === 'active' ? __('有効', EASY_BOOKINGER_TEXT_DOMAIN) : __('無効', EASY_BOOKINGER_TEXT_DOMAIN);
            $row_data[] = $created_datetime;
            
            fputcsv($stream, $row_data);
        }
        
        // Get the content
        rewind($stream);
        $csv_content .= stream_get_contents($stream);
        fclose($stream);
        
        return $csv_content;
    }
    
    /**
     * Handle file deletion
     */
    private function handle_file_deletion() {
        if (!wp_verify_nonce($_POST['delete_file_nonce'], 'easy_bookinger_delete_file')) {
            wp_die(__('セキュリティチェックに失敗しました', EASY_BOOKINGER_TEXT_DOMAIN));
        }
        
        $filename = sanitize_file_name($_POST['filename']);
        $result = EasyBookinger_File_Manager::delete_file($filename);
        
        if ($result['success']) {
            add_action('admin_notices', function() use ($result) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($result['message']) . '</p></div>';
            });
        } else {
            add_action('admin_notices', function() use ($result) {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($result['error']) . '</p></div>';
            });
        }
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
            'expires' => 0 // No expiration
        );
        update_option('easy_bookinger_export_files', $export_files);
    }
    
    /**
     * Format booking time display for export (same as admin)
     */
    private function format_booking_time_for_export($booking_time) {
        // If booking_time is empty, return a dash
        if (empty($booking_time)) {
            return '-';
        }
        
        // If booking_time is a number (time slot ID), convert it to time format
        if (is_numeric($booking_time)) {
            $database = EasyBookinger_Database::instance();
            $time_slot = $database->get_time_slot_by_id((int)$booking_time);
            
            if ($time_slot) {
                // Return formatted time from time slot
                return date('H:i', strtotime($time_slot->start_time));
            } else {
                // Time slot not found, return the raw value
                return $booking_time;
            }
        }
        
        // If it's already in time format (HH:MM), return as is
        if (preg_match('/^\d{1,2}:\d{2}$/', $booking_time)) {
            return $booking_time;
        }
        
        // Try to parse as time and format it
        $parsed_time = strtotime($booking_time);
        if ($parsed_time !== false) {
            return date('H:i', $parsed_time);
        }
        
        // If all else fails, return the original value
        return $booking_time;
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