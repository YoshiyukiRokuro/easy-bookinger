<?php
/**
 * File manager for exports and backups
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class EasyBookinger_File_Manager {
    
    /**
     * Get exports directory path
     */
    public static function get_exports_dir() {
        return WP_CONTENT_DIR . '/easy-bookinger/exports';
    }
    
    /**
     * Get exports directory URL
     */
    public static function get_exports_url() {
        return WP_CONTENT_URL . '/easy-bookinger/exports';
    }
    
    /**
     * Ensure exports directory exists
     */
    public static function ensure_exports_dir() {
        $exports_dir = self::get_exports_dir();
        
        if (!file_exists($exports_dir)) {
            wp_mkdir_p($exports_dir);
        }
        
        // Add .htaccess to prevent direct access listing
        $htaccess_file = $exports_dir . '/.htaccess';
        if (!file_exists($htaccess_file)) {
            file_put_contents($htaccess_file, "Options -Indexes\nDeny from all\n");
        }
        
        // Add index.php to prevent directory listing
        $index_file = $exports_dir . '/index.php';
        if (!file_exists($index_file)) {
            file_put_contents($index_file, "<?php\n// Silence is golden.\n");
        }
        
        return $exports_dir;
    }
    
    /**
     * Save file to exports directory
     */
    public static function save_file($content, $filename, $type = 'export') {
        $exports_dir = self::ensure_exports_dir();
        
        // Create unique filename with timestamp and type
        $timestamp = date('Y-m-d_H-i-s');
        $unique_filename = $timestamp . '_' . $type . '_' . $filename;
        $file_path = $exports_dir . '/' . $unique_filename;
        
        try {
            $result = file_put_contents($file_path, $content);
            
            if ($result === false) {
                throw new Exception(__('ファイルの作成に失敗しました', EASY_BOOKINGER_TEXT_DOMAIN));
            }
            
            // Store file info in database
            self::store_file_info($unique_filename, $file_path, $type, strlen($content));
            
            return array(
                'success' => true,
                'filename' => $unique_filename,
                'path' => $file_path,
                'size' => $result
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Store file info in database
     */
    private static function store_file_info($filename, $file_path, $type, $size) {
        $files = get_option('easy_bookinger_stored_files', array());
        
        $files[$filename] = array(
            'path' => $file_path,
            'type' => $type,
            'size' => $size,
            'created' => time(),
            'expires' => time() + (30 * 24 * 60 * 60) // 30 days
        );
        
        update_option('easy_bookinger_stored_files', $files);
    }
    
    /**
     * Get list of stored files
     */
    public static function get_stored_files($type = null) {
        $files = get_option('easy_bookinger_stored_files', array());
        $result = array();
        
        foreach ($files as $filename => $info) {
            // Check if file still exists
            if (!file_exists($info['path'])) {
                continue;
            }
            
            // Filter by type if specified
            if ($type && $info['type'] !== $type) {
                continue;
            }
            
            // Check if expired
            if (time() > $info['expires']) {
                // Mark for cleanup but don't remove here
                continue;
            }
            
            $result[$filename] = $info;
        }
        
        return $result;
    }
    
    /**
     * Delete a stored file
     */
    public static function delete_file($filename) {
        $files = get_option('easy_bookinger_stored_files', array());
        
        if (!isset($files[$filename])) {
            return array(
                'success' => false,
                'error' => __('ファイルが見つかりません', EASY_BOOKINGER_TEXT_DOMAIN)
            );
        }
        
        $file_info = $files[$filename];
        
        try {
            // Delete physical file
            if (file_exists($file_info['path'])) {
                if (!unlink($file_info['path'])) {
                    throw new Exception(__('ファイルの削除に失敗しました', EASY_BOOKINGER_TEXT_DOMAIN));
                }
            }
            
            // Remove from database
            unset($files[$filename]);
            update_option('easy_bookinger_stored_files', $files);
            
            return array(
                'success' => true,
                'message' => __('ファイルを削除しました', EASY_BOOKINGER_TEXT_DOMAIN)
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Get download URL for a file
     */
    public static function get_download_url($filename) {
        $files = get_option('easy_bookinger_stored_files', array());
        
        if (!isset($files[$filename])) {
            return false;
        }
        
        // Create secure download URL with nonce
        return add_query_arg(array(
            'action' => 'easy_bookinger_download_file',
            'filename' => $filename,
            'nonce' => wp_create_nonce('easy_bookinger_download_' . $filename)
        ), admin_url('admin.php'));
    }
    
    /**
     * Handle file download
     */
    public static function handle_download() {
        if (!isset($_GET['filename']) || !isset($_GET['nonce'])) {
            wp_die(__('無効なリクエストです', EASY_BOOKINGER_TEXT_DOMAIN));
        }
        
        $filename = sanitize_file_name($_GET['filename']);
        $nonce = $_GET['nonce'];
        
        if (!wp_verify_nonce($nonce, 'easy_bookinger_download_' . $filename)) {
            wp_die(__('セキュリティチェックに失敗しました', EASY_BOOKINGER_TEXT_DOMAIN));
        }
        
        $files = get_option('easy_bookinger_stored_files', array());
        
        if (!isset($files[$filename])) {
            wp_die(__('ファイルが見つかりません', EASY_BOOKINGER_TEXT_DOMAIN));
        }
        
        $file_info = $files[$filename];
        $file_path = $file_info['path'];
        
        if (!file_exists($file_path)) {
            wp_die(__('ファイルが見つかりません', EASY_BOOKINGER_TEXT_DOMAIN));
        }
        
        // Determine content type
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $content_type = 'application/octet-stream';
        if ($extension === 'csv') {
            $content_type = 'text/csv; charset=UTF-8';
        } elseif ($extension === 'json') {
            $content_type = 'application/json; charset=UTF-8';
        }
        
        // Set headers for download
        header('Content-Type: ' . $content_type);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($file_path));
        header('Content-Transfer-Encoding: binary');
        header('Pragma: no-cache');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Expires: 0');
        
        // Clean output buffer
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Output file
        readfile($file_path);
        exit;
    }
    
    /**
     * Cleanup expired files
     */
    public static function cleanup_expired_files() {
        $files = get_option('easy_bookinger_stored_files', array());
        $updated = false;
        
        foreach ($files as $filename => $info) {
            // Check if expired or file doesn't exist
            if (time() > $info['expires'] || !file_exists($info['path'])) {
                // Delete physical file if it exists
                if (file_exists($info['path'])) {
                    unlink($info['path']);
                }
                
                // Remove from database
                unset($files[$filename]);
                $updated = true;
            }
        }
        
        if ($updated) {
            update_option('easy_bookinger_stored_files', $files);
        }
        
        return $updated;
    }
    
    /**
     * Render file list table
     */
    public static function render_file_list($type = null, $show_actions = true) {
        $files = self::get_stored_files($type);
        
        if (empty($files)) {
            echo '<p>' . __('保存されたファイルがありません', EASY_BOOKINGER_TEXT_DOMAIN) . '</p>';
            return;
        }
        
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('ファイル名', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                    <th><?php _e('タイプ', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                    <th><?php _e('サイズ', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                    <th><?php _e('作成日時', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                    <th><?php _e('有効期限', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                    <?php if ($show_actions): ?>
                    <th><?php _e('操作', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($files as $filename => $info): ?>
                <tr>
                    <td><code><?php echo esc_html($filename); ?></code></td>
                    <td>
                        <?php 
                        $type_labels = array(
                            'export' => __('エクスポート', EASY_BOOKINGER_TEXT_DOMAIN),
                            'backup' => __('バックアップ', EASY_BOOKINGER_TEXT_DOMAIN)
                        );
                        echo esc_html($type_labels[$info['type']] ?? $info['type']);
                        ?>
                    </td>
                    <td><?php echo esc_html(size_format($info['size'])); ?></td>
                    <td><?php echo esc_html(date('Y/m/d H:i', $info['created'])); ?></td>
                    <td><?php echo esc_html(date('Y/m/d H:i', $info['expires'])); ?></td>
                    <?php if ($show_actions): ?>
                    <td>
                        <a href="<?php echo esc_url(self::get_download_url($filename)); ?>" 
                           class="button button-small button-primary">
                            <?php _e('ダウンロード', EASY_BOOKINGER_TEXT_DOMAIN); ?>
                        </a>
                        
                        <form method="post" style="display: inline;" 
                              onsubmit="return confirm('<?php echo esc_js(__('本当に削除しますか？', EASY_BOOKINGER_TEXT_DOMAIN)); ?>')">
                            <?php wp_nonce_field('easy_bookinger_delete_file', 'delete_file_nonce'); ?>
                            <input type="hidden" name="action" value="delete_file">
                            <input type="hidden" name="filename" value="<?php echo esc_attr($filename); ?>">
                            <button type="submit" class="button button-small button-link-delete">
                                <?php _e('削除', EASY_BOOKINGER_TEXT_DOMAIN); ?>
                            </button>
                        </form>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
}