<?php
/**
 * PDF generation class
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class EasyBookinger_PDF {
    
    /**
     * Generate booking PDF
     */
    public function generate_booking_pdf($booking) {
        // Load TCPDF
        if (!class_exists('TCPDF')) {
            $tcpdf_path = EASY_BOOKINGER_PLUGIN_DIR . 'vendor/tcpdf/tcpdf.php';
            if (!file_exists($tcpdf_path)) {
                wp_die(__('PDFライブラリが見つかりません。管理者にお問い合わせください。', EASY_BOOKINGER_TEXT_DOMAIN));
            }
            require_once $tcpdf_path;
        }
        
        try {
            // Create uploads directory for PDFs if it doesn't exist
            $upload_dir = wp_upload_dir();
            $pdf_dir = $upload_dir['basedir'] . '/easy-bookinger-pdfs';
            
            if (!file_exists($pdf_dir)) {
                wp_mkdir_p($pdf_dir);
            }
            
            // Create .htaccess file to protect PDF directory
            $htaccess_file = $pdf_dir . '/.htaccess';
            if (!file_exists($htaccess_file)) {
                file_put_contents($htaccess_file, "deny from all\n");
            }
            
            // Create new PDF document
            $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            
            // Set document information
            $pdf->SetCreator(get_bloginfo('name'));
            $pdf->SetAuthor(get_bloginfo('name'));
            $pdf->SetTitle(__('予約確認書', EASY_BOOKINGER_TEXT_DOMAIN));
            $pdf->SetSubject(__('予約確認書', EASY_BOOKINGER_TEXT_DOMAIN));
            
            // Set default header data
            $pdf->SetHeaderData('', 0, get_bloginfo('name'), __('予約確認書', EASY_BOOKINGER_TEXT_DOMAIN));
            
            // Set header and footer fonts
            $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
            $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
            
            // Set default monospaced font
            $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
            
            // Set margins
            $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
            $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
            $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
            
            // Set auto page breaks
            $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
            
            // Set image scale factor
            $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
            
            // Add a page
            $pdf->AddPage();
            
            // Set font for content
            $pdf->SetFont('helvetica', '', 12);
            
            // Get form data
            $form_data = maybe_unserialize($booking->form_data);
            $settings = get_option('easy_bookinger_settings', array());
            $booking_fields = isset($settings['booking_fields']) ? $settings['booking_fields'] : array();
            
            // Generate PDF content
            $html = $this->get_pdf_content($booking, $form_data, $booking_fields);
            
            // Print content
            $pdf->writeHTML($html, true, false, true, false, '');
            
            // Set password protection if password exists
            if (!empty($booking->pdf_password)) {
                $pdf->SetProtection(array('print', 'copy'), $booking->pdf_password, null, 0, null);
            }
            
            // Generate filename
            $filename = sprintf('booking_%s_%s.pdf', 
                $booking->id, 
                date('Ymd', strtotime($booking->booking_date))
            );
            
            // Output PDF for download
            $pdf->Output($filename, 'D');
            exit;
            
        } catch (Exception $e) {
            error_log('Easy Bookinger PDF Error: ' . $e->getMessage());
            wp_die(__('PDFの生成中にエラーが発生しました。しばらく時間をおいて再試行してください。', EASY_BOOKINGER_TEXT_DOMAIN));
        }
    }
    
    /**
     * Get PDF content HTML
     */
    private function get_pdf_content($booking, $form_data, $booking_fields) {
        $site_name = get_bloginfo('name');
        $site_url = get_bloginfo('url');
        
        ob_start();
        ?>
        <style>
            body { font-family: 'Hiragino Sans', 'Hiragino Kaku Gothic ProN', sans-serif; }
            .header { text-align: center; margin-bottom: 30px; }
            .title { font-size: 24px; font-weight: bold; margin-bottom: 20px; }
            .subtitle { font-size: 16px; color: #666; }
            .content { margin: 30px 0; }
            .section { margin-bottom: 25px; }
            .section-title { font-size: 18px; font-weight: bold; margin-bottom: 15px; color: #333; background-color: #f0f0f0; padding: 8px 12px; }
            .info-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            .info-table th, .info-table td { border: 1px solid #ddd; padding: 10px; text-align: left; }
            .info-table th { background-color: #f8f9fa; font-weight: bold; width: 30%; }
            .footer { margin-top: 50px; padding-top: 20px; border-top: 1px solid #ddd; text-align: center; font-size: 12px; color: #666; }
            .qr-code { text-align: center; margin: 20px 0; }
            .generated-date { text-align: right; margin-top: 30px; font-size: 12px; color: #666; }
        </style>
        
        <div class="header">
            <div class="title"><?php echo esc_html($site_name); ?></div>
            <div class="subtitle">予約確認書</div>
        </div>
        
        <div class="content">
            <div class="section">
                <div class="section-title">予約情報</div>
                <table class="info-table">
                    <tr>
                        <th>予約番号</th>
                        <td>#<?php echo esc_html($booking->id); ?></td>
                    </tr>
                    <tr>
                        <th>予約日</th>
                        <td><?php echo esc_html(date('Y年n月j日（l）', strtotime($booking->booking_date))); ?></td>
                    </tr>
                    <?php if (!empty($booking->booking_time)): ?>
                    <tr>
                        <th>時間</th>
                        <td><?php echo esc_html($booking->booking_time); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th>予約者名</th>
                        <td><?php echo esc_html($booking->user_name); ?></td>
                    </tr>
                    <tr>
                        <th>メールアドレス</th>
                        <td><?php echo esc_html($booking->email); ?></td>
                    </tr>
                    <?php if (!empty($booking->phone)): ?>
                    <tr>
                        <th>電話番号</th>
                        <td><?php echo esc_html($booking->phone); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th>登録日時</th>
                        <td><?php echo esc_html(date('Y年n月j日 H:i', strtotime($booking->created_at))); ?></td>
                    </tr>
                    <tr>
                        <th>ステータス</th>
                        <td><?php echo $booking->status === 'active' ? '有効' : esc_html($booking->status); ?></td>
                    </tr>
                </table>
            </div>
            
            <?php if (!empty($booking->comment)): ?>
            <div class="section">
                <div class="section-title">コメント</div>
                <div style="border: 1px solid #ddd; padding: 15px; background-color: #f9f9f9;">
                    <?php echo nl2br(esc_html($booking->comment)); ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($form_data) && is_array($form_data)): ?>
            <div class="section">
                <div class="section-title">追加情報</div>
                <table class="info-table">
                    <?php
                    $field_labels = array();
                    foreach ($booking_fields as $field) {
                        $field_labels[$field['name']] = $field['label'];
                    }
                    
                    foreach ($form_data as $key => $value):
                        if (in_array($key, array('user_name', 'email', 'email_confirm', 'phone', 'comment', 'booking_time'))) {
                            continue;
                        }
                        
                        $label = isset($field_labels[$key]) ? $field_labels[$key] : $key;
                        $display_value = is_array($value) ? implode(', ', $value) : $value;
                    ?>
                    <tr>
                        <th><?php echo esc_html($label); ?></th>
                        <td><?php echo esc_html($display_value); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
            <?php endif; ?>
            
            <div class="section">
                <div class="section-title">重要事項</div>
                <div style="border: 1px solid #ddd; padding: 15px; background-color: #fff3cd;">
                    <ul style="margin: 0; padding-left: 20px;">
                        <li>この確認書は予約の証明となります。大切に保管してください。</li>
                        <li>予約内容に変更がある場合は、事前にご連絡ください。</li>
                        <li>当日は予約時間の10分前にお越しください。</li>
                        <li>キャンセルや変更については、当社規定に従ってください。</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="footer">
            <div><?php echo esc_html($site_name); ?></div>
            <div><?php echo esc_html($site_url); ?></div>
        </div>
        
        <div class="generated-date">
            発行日: <?php echo date('Y年n月j日 H:i'); ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Check if TCPDF is available
     */
    public static function is_tcpdf_available() {
        $tcpdf_path = EASY_BOOKINGER_PLUGIN_DIR . 'vendor/tcpdf/tcpdf.php';
        return file_exists($tcpdf_path);
    }
    
    /**
     * Download TCPDF if not available
     */
    public static function ensure_tcpdf() {
        if (!self::is_tcpdf_available()) {
            // For now, we'll provide instructions to manually install TCPDF
            // In a production environment, you might want to include TCPDF in the plugin
            return false;
        }
        return true;
    }
}