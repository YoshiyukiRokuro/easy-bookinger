<?php
/**
 * Admin functionality
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class EasyBookinger_Admin {
    
    private static $instance = null;
    
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_init', array($this, 'admin_init'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Easy Bookinger', EASY_BOOKINGER_TEXT_DOMAIN),
            __('Easy Bookinger', EASY_BOOKINGER_TEXT_DOMAIN),
            'manage_options',
            'easy-bookinger',
            array($this, 'admin_page'),
            'dashicons-calendar-alt',
            30
        );
        
        add_submenu_page(
            'easy-bookinger',
            __('予約管理', EASY_BOOKINGER_TEXT_DOMAIN),
            __('予約管理', EASY_BOOKINGER_TEXT_DOMAIN),
            'manage_options',
            'easy-bookinger',
            array($this, 'admin_page')
        );
        
        add_submenu_page(
            'easy-bookinger',
            __('設定', EASY_BOOKINGER_TEXT_DOMAIN),
            __('設定', EASY_BOOKINGER_TEXT_DOMAIN),
            'manage_options',
            'easy-bookinger-settings',
            array($this, 'settings_page')
        );
        
        add_submenu_page(
            'easy-bookinger',
            __('エクスポート', EASY_BOOKINGER_TEXT_DOMAIN),
            __('エクスポート', EASY_BOOKINGER_TEXT_DOMAIN),
            'manage_options',
            'easy-bookinger-export',
            array($this, 'export_page')
        );
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'easy-bookinger') === false) {
            return;
        }
        
        wp_enqueue_style(
            'easy-bookinger-admin',
            EASY_BOOKINGER_PLUGIN_URL . 'assets/css/easy-bookinger-admin.css',
            array(),
            EASY_BOOKINGER_VERSION
        );
        
        wp_enqueue_script(
            'easy-bookinger-admin',
            EASY_BOOKINGER_PLUGIN_URL . 'assets/js/easy-bookinger-admin.js',
            array('jquery'),
            EASY_BOOKINGER_VERSION,
            true
        );
        
        wp_localize_script('easy-bookinger-admin', 'easyBookingerAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('easy_bookinger_admin_nonce')
        ));
    }
    
    /**
     * Admin init
     */
    public function admin_init() {
        // Register settings
        register_setting('easy_bookinger_settings', 'easy_bookinger_settings');
    }
    
    /**
     * Main admin page
     */
    public function admin_page() {
        $database = EasyBookinger_Database::instance();
        
        // Handle actions
        if (isset($_GET['action']) && isset($_GET['booking_id'])) {
            $this->handle_booking_action($_GET['action'], intval($_GET['booking_id']));
        }
        
        // Get bookings
        $bookings = $database->get_bookings(array(
            'orderby' => 'created_at',
            'order' => 'DESC',
            'limit' => 50
        ));
        
        ?>
        <div class="wrap">
            <h1><?php _e('Easy Bookinger - 予約管理', EASY_BOOKINGER_TEXT_DOMAIN); ?></h1>
            
            <div class="eb-admin-header">
                <h2><?php _e('予約一覧', EASY_BOOKINGER_TEXT_DOMAIN); ?></h2>
                <p><?php _e('登録された予約の管理を行います。', EASY_BOOKINGER_TEXT_DOMAIN); ?></p>
            </div>
            
            <?php if (empty($bookings)): ?>
                <div class="notice notice-info">
                    <p><?php _e('まだ予約がありません。', EASY_BOOKINGER_TEXT_DOMAIN); ?></p>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('ID', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                            <th><?php _e('予約日', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                            <th><?php _e('時間', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                            <th><?php _e('氏名', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                            <th><?php _e('メール', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                            <th><?php _e('電話', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                            <th><?php _e('ステータス', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                            <th><?php _e('登録日', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                            <th><?php _e('操作', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $booking): ?>
                        <tr>
                            <td><?php echo esc_html($booking->id); ?></td>
                            <td><?php echo esc_html(date('Y/m/d', strtotime($booking->booking_date))); ?></td>
                            <td><?php echo esc_html($booking->booking_time); ?></td>
                            <td><?php echo esc_html($booking->user_name); ?></td>
                            <td><a href="mailto:<?php echo esc_attr($booking->email); ?>"><?php echo esc_html($booking->email); ?></a></td>
                            <td><?php echo esc_html($booking->phone); ?></td>
                            <td>
                                <?php
                                $status_class = $booking->status === 'active' ? 'status-active' : 'status-inactive';
                                $status_text = $booking->status === 'active' ? __('有効', EASY_BOOKINGER_TEXT_DOMAIN) : __('無効', EASY_BOOKINGER_TEXT_DOMAIN);
                                ?>
                                <span class="status <?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_text); ?></span>
                            </td>
                            <td><?php echo esc_html(date('Y/m/d H:i', strtotime($booking->created_at))); ?></td>
                            <td>
                                <a href="<?php echo esc_url(add_query_arg(array('action' => 'view', 'booking_id' => $booking->id))); ?>" class="button button-small">
                                    <?php _e('詳細', EASY_BOOKINGER_TEXT_DOMAIN); ?>
                                </a>
                                <?php if ($booking->status === 'active'): ?>
                                <a href="<?php echo esc_url(add_query_arg(array('action' => 'deactivate', 'booking_id' => $booking->id))); ?>" class="button button-small">
                                    <?php _e('無効化', EASY_BOOKINGER_TEXT_DOMAIN); ?>
                                </a>
                                <?php else: ?>
                                <a href="<?php echo esc_url(add_query_arg(array('action' => 'activate', 'booking_id' => $booking->id))); ?>" class="button button-small">
                                    <?php _e('有効化', EASY_BOOKINGER_TEXT_DOMAIN); ?>
                                </a>
                                <?php endif; ?>
                                <a href="<?php echo esc_url(add_query_arg(array('action' => 'delete', 'booking_id' => $booking->id))); ?>" class="button button-small button-link-delete" onclick="return confirm('<?php _e('本当に削除しますか？', EASY_BOOKINGER_TEXT_DOMAIN); ?>')">
                                    <?php _e('削除', EASY_BOOKINGER_TEXT_DOMAIN); ?>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            
            <div class="eb-admin-help">
                <h3><?php _e('使用方法', EASY_BOOKINGER_TEXT_DOMAIN); ?></h3>
                <p><?php _e('予約フォームを表示するには、投稿やページに以下のショートコードを追加してください：', EASY_BOOKINGER_TEXT_DOMAIN); ?></p>
                <code>[easy_bookinger]</code>
                <p><?php _e('オプション：', EASY_BOOKINGER_TEXT_DOMAIN); ?></p>
                <ul>
                    <li><code>[easy_bookinger months="3"]</code> - <?php _e('表示月数を指定', EASY_BOOKINGER_TEXT_DOMAIN); ?></li>
                    <li><code>[easy_bookinger theme="custom"]</code> - <?php _e('テーマを指定', EASY_BOOKINGER_TEXT_DOMAIN); ?></li>
                </ul>
            </div>
        </div>
        <?php
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        $settings_handler = new EasyBookinger_Settings();
        $settings_handler->render_settings_page();
    }
    
    /**
     * Export page
     */
    public function export_page() {
        $export_handler = new EasyBookinger_Export();
        $export_handler->render_export_page();
    }
    
    /**
     * Handle booking actions
     */
    private function handle_booking_action($action, $booking_id) {
        if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'easy_bookinger_admin_nonce')) {
            wp_die(__('セキュリティチェックに失敗しました', EASY_BOOKINGER_TEXT_DOMAIN));
        }
        
        $database = EasyBookinger_Database::instance();
        
        switch ($action) {
            case 'activate':
                $database->update_booking($booking_id, array('status' => 'active'));
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-success is-dismissible"><p>' . __('予約を有効化しました', EASY_BOOKINGER_TEXT_DOMAIN) . '</p></div>';
                });
                break;
                
            case 'deactivate':
                $database->update_booking($booking_id, array('status' => 'inactive'));
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-success is-dismissible"><p>' . __('予約を無効化しました', EASY_BOOKINGER_TEXT_DOMAIN) . '</p></div>';
                });
                break;
                
            case 'delete':
                $database->delete_booking($booking_id);
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-success is-dismissible"><p>' . __('予約を削除しました', EASY_BOOKINGER_TEXT_DOMAIN) . '</p></div>';
                });
                break;
        }
    }
}