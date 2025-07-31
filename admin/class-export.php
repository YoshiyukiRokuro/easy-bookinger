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
        if (isset($_POST['export_excel'])) {
            $this->export_to_excel();
            return;
        }
        
        ?>
        <div class="wrap">
            <h1><?php _e('Easy Bookinger - エクスポート', EASY_BOOKINGER_TEXT_DOMAIN); ?></h1>
            
            <div class="eb-export-section">
                <h2><?php _e('予約データのエクスポート', EASY_BOOKINGER_TEXT_DOMAIN); ?></h2>
                <p><?php _e('登録された予約データをExcel形式でダウンロードできます。', EASY_BOOKINGER_TEXT_DOMAIN); ?></p>
                
                <form method="post" action="">
                    <?php wp_nonce_field('easy_bookinger_export', 'easy_bookinger_export_nonce'); ?>
                    
                    <table class="form-table">
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
                    
                    <?php submit_button(__('Excelファイルをダウンロード', EASY_BOOKINGER_TEXT_DOMAIN), 'primary', 'export_excel'); ?>
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
     * Export to Excel
     */
    private function export_to_excel() {
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
        
        // Generate Excel content
        $excel_content = $this->generate_excel_content($bookings);
        
        // Set headers for download
        $filename = 'easy_bookinger_export_' . date('Y-m-d_H-i-s') . '.xls';
        
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        echo $excel_content;
        exit;
    }
    
    /**
     * Generate Excel content
     */
    private function generate_excel_content($bookings) {
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
        
        // Start building Excel XML
        $excel_xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $excel_xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet" xmlns:html="http://www.w3.org/TR/REC-html40">' . "\n";
        $excel_xml .= '<Worksheet ss:Name="予約データ">' . "\n";
        $excel_xml .= '<Table>' . "\n";
        
        // Header row
        $excel_xml .= '<Row>' . "\n";
        foreach ($headers as $header) {
            $excel_xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars($header, ENT_XML1, 'UTF-8') . '</Data></Cell>' . "\n";
        }
        $excel_xml .= '</Row>' . "\n";
        
        // Data rows
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
            
            $excel_xml .= '<Row>' . "\n";
            foreach ($row_data as $cell_data) {
                $excel_xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars($cell_data, ENT_XML1, 'UTF-8') . '</Data></Cell>' . "\n";
            }
            $excel_xml .= '</Row>' . "\n";
        }
        
        $excel_xml .= '</Table>' . "\n";
        $excel_xml .= '</Worksheet>' . "\n";
        $excel_xml .= '</Workbook>';
        
        return $excel_xml;
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