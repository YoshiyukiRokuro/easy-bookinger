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
        
        add_submenu_page(
            'easy-bookinger',
            __('バックアップ・復元', EASY_BOOKINGER_TEXT_DOMAIN),
            __('バックアップ・復元', EASY_BOOKINGER_TEXT_DOMAIN),
            'manage_options',
            'easy-bookinger-backup',
            array($this, 'backup_page')
        );
        
        add_submenu_page(
            'easy-bookinger',
            __('日付制限', EASY_BOOKINGER_TEXT_DOMAIN),
            __('日付制限', EASY_BOOKINGER_TEXT_DOMAIN),
            'manage_options',
            'easy-bookinger-restrictions',
            array($this, 'restrictions_page')
        );
        
        add_submenu_page(
            'easy-bookinger',
            __('臨時予約設定', EASY_BOOKINGER_TEXT_DOMAIN),
            __('臨時予約設定', EASY_BOOKINGER_TEXT_DOMAIN),
            'manage_options',
            'easy-bookinger-special-availability',
            array($this, 'special_availability_page')
        );
        
        add_submenu_page(
            'easy-bookinger',
            __('予約枠管理', EASY_BOOKINGER_TEXT_DOMAIN),
            __('予約枠管理', EASY_BOOKINGER_TEXT_DOMAIN),
            'manage_options',
            'easy-bookinger-quotas',
            array($this, 'quotas_page')
        );
        
        add_submenu_page(
            'easy-bookinger',
            __('時間帯設定', EASY_BOOKINGER_TEXT_DOMAIN),
            __('時間帯設定', EASY_BOOKINGER_TEXT_DOMAIN),
            'manage_options',
            'easy-bookinger-timeslots',
            array($this, 'timeslots_page')
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
            array('jquery', 'jquery-ui-sortable'),
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
        
        // Get bookings (include all statuses)
        $bookings = $database->get_bookings(array(
            'status' => '', // Empty status to get all bookings
            'orderby' => 'created_at',
            'order' => 'DESC',
            'limit' => 50
        ));
        
        // Get form fields for dynamic headers
        $settings = get_option('easy_bookinger_settings', array());
        $booking_fields = isset($settings['booking_fields']) ? $settings['booking_fields'] : array();
        
        ?>
        <div class="wrap">
            <h1><?php _e('Easy Bookinger - 予約管理', EASY_BOOKINGER_TEXT_DOMAIN); ?></h1>
            
            <?php
            // Display security error message if present
            if (isset($_GET['security_error']) && $_GET['security_error'] === '1') {
                $error_message = isset($_GET['error_message']) ? urldecode($_GET['error_message']) : __('セキュリティエラーが発生しました', EASY_BOOKINGER_TEXT_DOMAIN);
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($error_message) . '</p></div>';
            }
            ?>
            
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
                            <th><?php _e('予約時間', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                            <?php foreach ($booking_fields as $field): ?>
                                <?php if ($field['name'] !== 'email_confirm'): ?>
                                <th><?php echo esc_html($field['label']); ?></th>
                                <?php endif; ?>
                            <?php endforeach; ?>
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
                            <td><?php echo esc_html($this->format_booking_time($booking->booking_time)); ?></td>
                            <?php 
                            $form_data = maybe_unserialize($booking->form_data);
                            foreach ($booking_fields as $field): 
                                if ($field['name'] !== 'email_confirm'):
                            ?>
                                <td>
                                    <?php 
                                    if (isset($form_data[$field['name']])) {
                                        if ($field['type'] === 'email') {
                                            echo '<a href="mailto:' . esc_attr($form_data[$field['name']]) . '">' . esc_html($form_data[$field['name']]) . '</a>';
                                        } else {
                                            echo esc_html($form_data[$field['name']]);
                                        }
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                            <td>
                                <?php
                                switch ($booking->status) {
                                    case 'confirmed':
                                        $status_class = 'status-confirmed';
                                        $status_text = __('確定', EASY_BOOKINGER_TEXT_DOMAIN);
                                        break;
                                    case 'pending':
                                        $status_class = 'status-pending';
                                        $status_text = __('仮予約', EASY_BOOKINGER_TEXT_DOMAIN);
                                        break;
                                    case 'active': // Legacy status for backward compatibility
                                        $status_class = 'status-confirmed';
                                        $status_text = __('確定', EASY_BOOKINGER_TEXT_DOMAIN);
                                        break;
                                    default:
                                        $status_class = 'status-inactive';
                                        $status_text = __('無効', EASY_BOOKINGER_TEXT_DOMAIN);
                                }
                                ?>
                                <span class="status <?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_text); ?></span>
                            </td>
                            <td><?php echo esc_html(date('Y/m/d H:i', strtotime($booking->created_at))); ?></td>
                            <td>
                                <a href="<?php echo esc_url(add_query_arg(array('action' => 'view', 'booking_id' => $booking->id, '_wpnonce' => wp_create_nonce('easy_bookinger_admin_action_' . $booking->id)))); ?>" class="button button-small">
                                    <?php _e('詳細', EASY_BOOKINGER_TEXT_DOMAIN); ?>
                                </a>
                                <?php if ($booking->status === 'confirmed' || $booking->status === 'active'): ?>
                                <a href="<?php echo esc_url(add_query_arg(array('action' => 'deactivate', 'booking_id' => $booking->id, '_wpnonce' => wp_create_nonce('easy_bookinger_admin_action_' . $booking->id)))); ?>" class="button button-small">
                                    <?php _e('無効化', EASY_BOOKINGER_TEXT_DOMAIN); ?>
                                </a>
                                <?php elseif ($booking->status === 'pending'): ?>
                                <a href="<?php echo esc_url(add_query_arg(array('action' => 'confirm', 'booking_id' => $booking->id, '_wpnonce' => wp_create_nonce('easy_bookinger_admin_action_' . $booking->id)))); ?>" class="button button-small">
                                    <?php _e('確定', EASY_BOOKINGER_TEXT_DOMAIN); ?>
                                </a>
                                <?php else: ?>
                                <a href="<?php echo esc_url(add_query_arg(array('action' => 'activate', 'booking_id' => $booking->id, '_wpnonce' => wp_create_nonce('easy_bookinger_admin_action_' . $booking->id)))); ?>" class="button button-small">
                                    <?php _e('有効化', EASY_BOOKINGER_TEXT_DOMAIN); ?>
                                </a>
                                <?php endif; ?>
                                <a href="<?php echo esc_url(add_query_arg(array('action' => 'delete', 'booking_id' => $booking->id, '_wpnonce' => wp_create_nonce('easy_bookinger_admin_action_' . $booking->id)))); ?>" class="button button-small button-link-delete">
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
     * Backup page
     */
    public function backup_page() {
        $backup_handler = new EasyBookinger_Backup();
        $backup_handler->render_backup_page();
    }
    
    /**
     * Date restrictions page
     */
    public function restrictions_page() {
        $database = EasyBookinger_Database::instance();
        
        // Handle actions
        if (isset($_POST['action'])) {
            $this->handle_restrictions_action($_POST);
        }
        
        // Check if we're editing a restriction
        $editing_restriction = null;
        if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
            $editing_restriction = $database->get_date_restriction_by_id($_GET['edit']);
        }
        
        // Get restricted dates
        $restricted_dates = $database->get_restricted_dates();
        
        ?>
        <div class="wrap">
            <h1><?php _e('日付制限管理', EASY_BOOKINGER_TEXT_DOMAIN); ?></h1>
            
            <div class="eb-admin-section">
                <h2><?php _e($editing_restriction ? '制限日を編集' : '新しい制限日を追加', EASY_BOOKINGER_TEXT_DOMAIN); ?></h2>
                <form method="post" action="">
                    <?php wp_nonce_field('eb_restrictions_action', 'eb_restrictions_nonce'); ?>
                    <input type="hidden" name="action" value="<?php echo $editing_restriction ? 'update_restriction' : 'add_restriction'; ?>">
                    <?php if ($editing_restriction): ?>
                        <input type="hidden" name="restriction_id" value="<?php echo esc_attr($editing_restriction->id); ?>">
                    <?php endif; ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('日付', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                            <td>
                                <input type="date" name="restriction_date" value="<?php echo $editing_restriction ? esc_attr($editing_restriction->restriction_date) : ''; ?>" required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('制限タイプ', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                            <td>
                                <select name="restriction_type">
                                    <option value="custom" <?php selected($editing_restriction ? $editing_restriction->restriction_type : '', 'custom'); ?>><?php _e('カスタム', EASY_BOOKINGER_TEXT_DOMAIN); ?></option>
                                    <option value="holiday" <?php selected($editing_restriction ? $editing_restriction->restriction_type : '', 'holiday'); ?>><?php _e('祝日', EASY_BOOKINGER_TEXT_DOMAIN); ?></option>
                                    <option value="closed" <?php selected($editing_restriction ? $editing_restriction->restriction_type : '', 'closed'); ?>><?php _e('定休日', EASY_BOOKINGER_TEXT_DOMAIN); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('理由', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                            <td>
                                <input type="text" name="reason" value="<?php echo $editing_restriction ? esc_attr($editing_restriction->reason) : ''; ?>" class="regular-text">
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button(__($editing_restriction ? '制限を更新' : '制限を追加', EASY_BOOKINGER_TEXT_DOMAIN)); ?>
                    <?php if ($editing_restriction): ?>
                        <a href="<?php echo esc_url(remove_query_arg('edit')); ?>" class="button"><?php _e('編集をキャンセル', EASY_BOOKINGER_TEXT_DOMAIN); ?></a>
                    <?php endif; ?>
                </form>
            </div>
            
            <div class="eb-admin-section">
                <h2><?php _e('設定済み制限日', EASY_BOOKINGER_TEXT_DOMAIN); ?></h2>
                <?php if (empty($restricted_dates)): ?>
                    <p><?php _e('制限日が設定されていません。', EASY_BOOKINGER_TEXT_DOMAIN); ?></p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('日付', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                                <th><?php _e('タイプ', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                                <th><?php _e('理由', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                                <th><?php _e('操作', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($restricted_dates as $restriction): ?>
                            <tr>
                                <td><?php echo esc_html(date('Y年n月j日', strtotime($restriction->restriction_date))); ?></td>
                                <td>
                                    <?php
                                    $type_labels = array(
                                        'custom' => 'カスタム',
                                        'holiday' => '祝日',
                                        'closed' => '定休日'
                                    );
                                    echo esc_html($type_labels[$restriction->restriction_type] ?? $restriction->restriction_type);
                                    ?>
                                </td>
                                <td><?php echo esc_html($restriction->reason); ?></td>
                                <td>
                                    <a href="<?php echo esc_url(add_query_arg('edit', $restriction->id)); ?>" class="button button-small">
                                        <?php _e('編集', EASY_BOOKINGER_TEXT_DOMAIN); ?>
                                    </a>
                                    <form method="post" style="display: inline;">
                                        <?php wp_nonce_field('eb_restrictions_action', 'eb_restrictions_nonce'); ?>
                                        <input type="hidden" name="action" value="remove_restriction">
                                        <input type="hidden" name="restriction_date" value="<?php echo esc_attr($restriction->restriction_date); ?>">
                                        <input type="submit" class="button button-small button-link-delete" value="<?php _e('削除', EASY_BOOKINGER_TEXT_DOMAIN); ?>" onclick="return confirm('<?php _e('この制限日を削除しますか？', EASY_BOOKINGER_TEXT_DOMAIN); ?>')">
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Special availability page
     */
    public function special_availability_page() {
        $database = EasyBookinger_Database::instance();
        
        // Handle actions
        if (isset($_POST['action'])) {
            $this->handle_special_availability_action($_POST);
        }
        
        // Get current special availability dates
        $special_dates = $database->get_special_availability();
        
        ?>
        <div class="wrap">
            <h1><?php _e('臨時予約設定', EASY_BOOKINGER_TEXT_DOMAIN); ?></h1>
            
            <div class="eb-admin-section">
                <h2><?php _e('臨時予約可能日の追加', EASY_BOOKINGER_TEXT_DOMAIN); ?></h2>
                <p><?php _e('通常は予約できない曜日でも、特定の日に限り予約を受け付けることができます。', EASY_BOOKINGER_TEXT_DOMAIN); ?></p>
                
                <form method="post" action="">
                    <?php wp_nonce_field('eb_special_availability_action', 'eb_special_availability_nonce'); ?>
                    <input type="hidden" name="action" value="add_special_availability">
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('日付', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                            <td>
                                <input type="date" name="availability_date" required />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('理由', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                            <td>
                                <input type="text" name="reason" placeholder="<?php _e('例：振替営業日', EASY_BOOKINGER_TEXT_DOMAIN); ?>" style="width: 300px;" />
                                <p class="description"><?php _e('臨時予約を受け付ける理由を入力してください（任意）', EASY_BOOKINGER_TEXT_DOMAIN); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('予約枠数', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                            <td>
                                <input type="number" name="max_bookings" value="3" min="1" />
                                <span><?php _e('件', EASY_BOOKINGER_TEXT_DOMAIN); ?></span>
                                <p class="description"><?php _e('この日の最大予約受付件数を設定してください（制限なし）', EASY_BOOKINGER_TEXT_DOMAIN); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button(__('臨時予約日を追加', EASY_BOOKINGER_TEXT_DOMAIN)); ?>
                </form>
            </div>
            
            <div class="eb-admin-section">
                <h2><?php _e('設定済み臨時予約日', EASY_BOOKINGER_TEXT_DOMAIN); ?></h2>
                <?php if (empty($special_dates)): ?>
                    <p><?php _e('臨時予約日が設定されていません。', EASY_BOOKINGER_TEXT_DOMAIN); ?></p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('日付', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                                <th><?php _e('曜日', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                                <th><?php _e('理由', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                                <th><?php _e('予約枠数', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                                <th><?php _e('ステータス', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                                <th><?php _e('操作', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($special_dates as $date): ?>
                            <tr>
                                <td><?php echo esc_html($date->availability_date); ?></td>
                                <td><?php echo esc_html($this->get_japanese_day_name($date->availability_date)); ?></td>
                                <td><?php echo esc_html($date->reason ?: '-'); ?></td>
                                <td><?php echo esc_html($date->max_bookings ?: __('デフォルト', EASY_BOOKINGER_TEXT_DOMAIN)); ?></td>
                                <td>
                                    <?php if ($date->is_available): ?>
                                        <span class="eb-status-active"><?php _e('有効', EASY_BOOKINGER_TEXT_DOMAIN); ?></span>
                                    <?php else: ?>
                                        <span class="eb-status-inactive"><?php _e('無効', EASY_BOOKINGER_TEXT_DOMAIN); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="post" style="display: inline;">
                                        <?php wp_nonce_field('eb_special_availability_action', 'eb_special_availability_nonce'); ?>
                                        <input type="hidden" name="action" value="toggle_special_availability">
                                        <input type="hidden" name="date" value="<?php echo esc_attr($date->availability_date); ?>">
                                        <input type="submit" class="button-small" 
                                               value="<?php echo $date->is_available ? __('無効化', EASY_BOOKINGER_TEXT_DOMAIN) : __('有効化', EASY_BOOKINGER_TEXT_DOMAIN); ?>" />
                                    </form>
                                    
                                    <form method="post" style="display: inline;">
                                        <?php wp_nonce_field('eb_special_availability_action', 'eb_special_availability_nonce'); ?>
                                        <input type="hidden" name="action" value="delete_special_availability">
                                        <input type="hidden" name="date" value="<?php echo esc_attr($date->availability_date); ?>">
                                        <input type="submit" class="button-small button-link-delete" 
                                               value="<?php _e('削除', EASY_BOOKINGER_TEXT_DOMAIN); ?>"
                                               onclick="return confirm('<?php _e('本当に削除しますか？', EASY_BOOKINGER_TEXT_DOMAIN); ?>')" />
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Booking quotas page
     */
    public function quotas_page() {
        $database = EasyBookinger_Database::instance();
        
        // Handle actions
        if (isset($_POST['action'])) {
            $this->handle_quotas_action($_POST);
        }
        
        // Get quotas for next 3 months
        $date_from = date('Y-m-01');
        $date_to = date('Y-m-t', strtotime('+3 months'));
        
        global $wpdb;
        $quotas_table = $wpdb->prefix . 'easy_bookinger_booking_quotas';
        $bookings_table = $wpdb->prefix . 'easy_bookinger_bookings';
        
        $quotas = $wpdb->get_results($wpdb->prepare("
            SELECT q.*, 
                   COALESCE(b.actual_bookings, 0) as actual_bookings
            FROM $quotas_table q
            LEFT JOIN (
                SELECT booking_date, COUNT(*) as actual_bookings 
                FROM $bookings_table 
                WHERE status = 'confirmed' AND booking_date BETWEEN %s AND %s
                GROUP BY booking_date
            ) b ON q.quota_date = b.booking_date
            WHERE q.quota_date BETWEEN %s AND %s
            ORDER BY q.quota_date
        ", $date_from, $date_to, $date_from, $date_to));
        
        ?>
        <div class="wrap">
            <h1><?php _e('予約枠管理', EASY_BOOKINGER_TEXT_DOMAIN); ?></h1>
            
            <div class="eb-admin-section">
                <h2><?php _e('日別予約枠設定', EASY_BOOKINGER_TEXT_DOMAIN); ?></h2>
                <form method="post" action="">
                    <?php wp_nonce_field('eb_quotas_action', 'eb_quotas_nonce'); ?>
                    <input type="hidden" name="action" value="set_quota">
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('日付', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                            <td>
                                <input type="date" name="quota_date" required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('最大予約数', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                            <td>
                                <input type="number" name="max_bookings" min="0" max="20" value="3" required>
                                <p class="description"><?php _e('その日の最大予約受付数を設定してください。', EASY_BOOKINGER_TEXT_DOMAIN); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button(__('予約枠を設定', EASY_BOOKINGER_TEXT_DOMAIN)); ?>
                </form>
            </div>
            
            <div class="eb-admin-section">
                <h2><?php _e('設定済み予約枠', EASY_BOOKINGER_TEXT_DOMAIN); ?></h2>
                <?php if (empty($quotas)): ?>
                    <p><?php _e('予約枠が設定されていません。', EASY_BOOKINGER_TEXT_DOMAIN); ?></p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('日付', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                                <th><?php _e('最大予約数', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                                <th><?php _e('現在予約数', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                                <th><?php _e('実際予約数', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                                <th><?php _e('残り', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                                <th><?php _e('操作', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($quotas as $quota): ?>
                            <tr>
                                <td><?php echo esc_html(date('Y年n月j日', strtotime($quota->quota_date))); ?></td>
                                <td><?php echo esc_html($quota->max_bookings); ?></td>
                                <td><?php echo esc_html($quota->current_bookings); ?></td>
                                <td><?php echo esc_html($quota->actual_bookings); ?></td>
                                <td>
                                    <?php 
                                    $remaining = max(0, $quota->max_bookings - $quota->actual_bookings);
                                    echo esc_html($remaining);
                                    if ($remaining === 0) {
                                        echo ' <span style="color: #d63638;">' . __('(満枠)', EASY_BOOKINGER_TEXT_DOMAIN) . '</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <form method="post" style="display: inline;">
                                        <?php wp_nonce_field('eb_quotas_action', 'eb_quotas_nonce'); ?>
                                        <input type="hidden" name="action" value="delete_quota">
                                        <input type="hidden" name="quota_date" value="<?php echo esc_attr($quota->quota_date); ?>">
                                        <input type="submit" class="button-small button-link-delete" 
                                               value="<?php _e('削除', EASY_BOOKINGER_TEXT_DOMAIN); ?>"
                                               onclick="return confirm('<?php _e('この予約枠設定を削除しますか？削除後はデフォルト値が適用されます。', EASY_BOOKINGER_TEXT_DOMAIN); ?>')" />
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Time slots page
     */
    public function timeslots_page() {
        $database = EasyBookinger_Database::instance();
        
        // Handle actions
        if (isset($_POST['action'])) {
            $this->handle_timeslots_action($_POST);
        }
        
        // Get all time slots
        $time_slots = $database->get_time_slots();
        
        ?>
        <div class="wrap">
            <h1><?php _e('時間帯設定', EASY_BOOKINGER_TEXT_DOMAIN); ?></h1>
            
            <div class="eb-admin-section">
                <h2><?php _e('新しい時間帯を追加', EASY_BOOKINGER_TEXT_DOMAIN); ?></h2>
                <form method="post" action="">
                    <?php wp_nonce_field('eb_timeslots_action', 'eb_timeslots_nonce'); ?>
                    <input type="hidden" name="action" value="add_timeslot">
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('開始時間', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                            <td>
                                <input type="time" name="start_time" required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('表示名', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                            <td>
                                <input type="text" name="slot_name" class="regular-text" placeholder="例: 午前の部">
                                <p class="description"><?php _e('空欄の場合は時間を表示名として使用します。', EASY_BOOKINGER_TEXT_DOMAIN); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button(__('時間帯を追加', EASY_BOOKINGER_TEXT_DOMAIN)); ?>
                </form>
            </div>
            
            <div class="eb-admin-section">
                <h2><?php _e('設定済み時間帯', EASY_BOOKINGER_TEXT_DOMAIN); ?></h2>
                <?php if (empty($time_slots)): ?>
                    <p><?php _e('時間帯が設定されていません。', EASY_BOOKINGER_TEXT_DOMAIN); ?></p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('開始時間', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                                <th><?php _e('表示名', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                                <th><?php _e('状態', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                                <th><?php _e('操作', EASY_BOOKINGER_TEXT_DOMAIN); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($time_slots as $slot): ?>
                            <tr>
                                <td><?php echo esc_html(date('H:i', strtotime($slot->start_time))); ?></td>
                                <td><?php echo esc_html($slot->slot_name ?: date('H:i', strtotime($slot->start_time))); ?></td>
                                <td>
                                    <?php if ($slot->is_active): ?>
                                        <span style="color: #46b450;"><?php _e('有効', EASY_BOOKINGER_TEXT_DOMAIN); ?></span>
                                    <?php else: ?>
                                        <span style="color: #dc3232;"><?php _e('無効', EASY_BOOKINGER_TEXT_DOMAIN); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="post" style="display: inline;">
                                        <?php wp_nonce_field('eb_timeslots_action', 'eb_timeslots_nonce'); ?>
                                        <input type="hidden" name="action" value="toggle_timeslot">
                                        <input type="hidden" name="slot_id" value="<?php echo esc_attr($slot->id); ?>">
                                        <input type="submit" class="button button-small" value="<?php echo $slot->is_active ? __('無効化', EASY_BOOKINGER_TEXT_DOMAIN) : __('有効化', EASY_BOOKINGER_TEXT_DOMAIN); ?>">
                                    </form>
                                    <form method="post" style="display: inline;">
                                        <?php wp_nonce_field('eb_timeslots_action', 'eb_timeslots_nonce'); ?>
                                        <input type="hidden" name="action" value="delete_timeslot">
                                        <input type="hidden" name="slot_id" value="<?php echo esc_attr($slot->id); ?>">
                                        <input type="submit" class="button button-small button-link-delete" value="<?php _e('削除', EASY_BOOKINGER_TEXT_DOMAIN); ?>">
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Handle booking actions
     */
    private function handle_booking_action($action, $booking_id) {
        // Create nonce for verification
        $nonce = wp_create_nonce('easy_bookinger_admin_action_' . $booking_id);
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'easy_bookinger_admin_action_' . $booking_id)) {
            // Add nonce to URLs if not present
            $current_url = remove_query_arg(array('action', 'booking_id', '_wpnonce'));
            $redirect_url = add_query_arg(array(
                'action' => $action,
                'booking_id' => $booking_id,
                '_wpnonce' => $nonce
            ), $current_url);
            wp_redirect($redirect_url);
            exit;
        }
        
        $database = EasyBookinger_Database::instance();
        
        switch ($action) {
            case 'activate':
                $database->update_booking($booking_id, array('status' => 'confirmed'));
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-success is-dismissible"><p>' . __('予約を有効化しました', EASY_BOOKINGER_TEXT_DOMAIN) . '</p></div>';
                });
                break;
                
            case 'confirm':
                $database->update_booking($booking_id, array(
                    'status' => 'confirmed',
                    'confirmed_at' => current_time('mysql'),
                    'confirmation_token' => null,
                    'token_expires_at' => null
                ));
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-success is-dismissible"><p>' . __('予約を確定しました', EASY_BOOKINGER_TEXT_DOMAIN) . '</p></div>';
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
    
    /**
     * Safe error handling to prevent white screens
     * Use this instead of wp_die() to handle nonce failures gracefully
     */
    private function handle_security_error($page = null, $message = null) {
        if (!$page) {
            $page = 'easy-bookinger';
        }
        
        if (!$message) {
            $message = __('セキュリティチェックに失敗しました', EASY_BOOKINGER_TEXT_DOMAIN);
        }
        
        // Log the security error for debugging
        error_log('Easy Bookinger Security Error: ' . $message . ' on page: ' . $page);
        
        // Redirect with error message instead of wp_die to prevent white screen
        $redirect_url = add_query_arg(array(
            'page' => $page,
            'security_error' => '1',
            'error_message' => urlencode($message)
        ), admin_url('admin.php'));
        
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Handle date restrictions actions
     */
    private function handle_restrictions_action($post_data) {
        if (!wp_verify_nonce($post_data['eb_restrictions_nonce'], 'eb_restrictions_action')) {
            $this->handle_security_error('easy-bookinger-restrictions');
            return;
        }
        
        $database = EasyBookinger_Database::instance();
        
        switch ($post_data['action']) {
            case 'add_restriction':
                $date = sanitize_text_field($post_data['restriction_date']);
                $type = sanitize_text_field($post_data['restriction_type']);
                $reason = sanitize_text_field($post_data['reason']);
                
                if ($database->add_date_restriction($date, $type, $reason)) {
                    add_action('admin_notices', function() {
                        echo '<div class="notice notice-success is-dismissible"><p>' . __('制限日を追加しました', EASY_BOOKINGER_TEXT_DOMAIN) . '</p></div>';
                    });
                } else {
                    add_action('admin_notices', function() {
                        echo '<div class="notice notice-error is-dismissible"><p>' . __('制限日の追加に失敗しました', EASY_BOOKINGER_TEXT_DOMAIN) . '</p></div>';
                    });
                }
                break;
                
            case 'update_restriction':
                $restriction_id = (int)$post_data['restriction_id'];
                $data = array(
                    'restriction_date' => sanitize_text_field($post_data['restriction_date']),
                    'restriction_type' => sanitize_text_field($post_data['restriction_type']),
                    'reason' => sanitize_text_field($post_data['reason'])
                );
                
                if ($database->update_date_restriction($restriction_id, $data)) {
                    add_action('admin_notices', function() {
                        echo '<div class="notice notice-success is-dismissible"><p>' . __('制限日を更新しました', EASY_BOOKINGER_TEXT_DOMAIN) . '</p></div>';
                    });
                } else {
                    add_action('admin_notices', function() {
                        echo '<div class="notice notice-error is-dismissible"><p>' . __('制限日の更新に失敗しました', EASY_BOOKINGER_TEXT_DOMAIN) . '</p></div>';
                    });
                }
                break;
                
            case 'remove_restriction':
                $date = sanitize_text_field($post_data['restriction_date']);
                
                if ($database->remove_date_restriction($date)) {
                    add_action('admin_notices', function() {
                        echo '<div class="notice notice-success is-dismissible"><p>' . __('制限日を削除しました', EASY_BOOKINGER_TEXT_DOMAIN) . '</p></div>';
                    });
                } else {
                    add_action('admin_notices', function() {
                        echo '<div class="notice notice-error is-dismissible"><p>' . __('制限日の削除に失敗しました', EASY_BOOKINGER_TEXT_DOMAIN) . '</p></div>';
                    });
                }
                break;
        }
    }
    
    /**
     * Handle special availability actions
     */
    private function handle_special_availability_action($post_data) {
        if (!wp_verify_nonce($post_data['eb_special_availability_nonce'], 'eb_special_availability_action')) {
            $this->handle_security_error('easy-bookinger-special-availability');
            return;
        }
        
        $database = EasyBookinger_Database::instance();
        
        switch ($post_data['action']) {
            case 'add_special_availability':
                $date = sanitize_text_field($post_data['availability_date']);
                $reason = sanitize_text_field($post_data['reason'] ?? '');
                $max_bookings = !empty($post_data['max_bookings']) ? intval($post_data['max_bookings']) : null;
                
                if ($database->add_special_availability($date, 1, $reason, $max_bookings)) {
                    add_action('admin_notices', function() {
                        echo '<div class="notice notice-success is-dismissible"><p>' . __('臨時予約日を追加しました', EASY_BOOKINGER_TEXT_DOMAIN) . '</p></div>';
                    });
                } else {
                    add_action('admin_notices', function() {
                        echo '<div class="notice notice-error is-dismissible"><p>' . __('臨時予約日の追加に失敗しました', EASY_BOOKINGER_TEXT_DOMAIN) . '</p></div>';
                    });
                }
                break;
                
            case 'toggle_special_availability':
                $date = sanitize_text_field($post_data['date']);
                $special_date = $database->get_special_availability($date, $date);
                
                if (!empty($special_date)) {
                    $current_status = $special_date[0]->is_available;
                    $new_status = $current_status ? 0 : 1;
                    
                    if ($database->update_special_availability($special_date[0]->id, array('is_available' => $new_status))) {
                        $message = $new_status ? __('臨時予約日を有効化しました', EASY_BOOKINGER_TEXT_DOMAIN) : __('臨時予約日を無効化しました', EASY_BOOKINGER_TEXT_DOMAIN);
                        add_action('admin_notices', function() use ($message) {
                            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
                        });
                    }
                }
                break;
                
            case 'delete_special_availability':
                $date = sanitize_text_field($post_data['date']);
                
                if ($database->remove_special_availability($date)) {
                    add_action('admin_notices', function() {
                        echo '<div class="notice notice-success is-dismissible"><p>' . __('臨時予約日を削除しました', EASY_BOOKINGER_TEXT_DOMAIN) . '</p></div>';
                    });
                } else {
                    add_action('admin_notices', function() {
                        echo '<div class="notice notice-error is-dismissible"><p>' . __('臨時予約日の削除に失敗しました', EASY_BOOKINGER_TEXT_DOMAIN) . '</p></div>';
                    });
                }
                break;
        }
    }
    
    /**
     * Handle booking quotas actions
     */
    private function handle_quotas_action($post_data) {
        if (!wp_verify_nonce($post_data['eb_quotas_nonce'], 'eb_quotas_action')) {
            $this->handle_security_error('easy-bookinger-quotas');
            return;
        }
        
        $database = EasyBookinger_Database::instance();
        
        switch ($post_data['action']) {
            case 'set_quota':
                $date = sanitize_text_field($post_data['quota_date']);
                $max_bookings = (int)$post_data['max_bookings'];
                
                if ($database->set_booking_quota($date, $max_bookings)) {
                    add_action('admin_notices', function() {
                        echo '<div class="notice notice-success is-dismissible"><p>' . __('予約枠を設定しました', EASY_BOOKINGER_TEXT_DOMAIN) . '</p></div>';
                    });
                } else {
                    add_action('admin_notices', function() {
                        echo '<div class="notice notice-error is-dismissible"><p>' . __('予約枠の設定に失敗しました', EASY_BOOKINGER_TEXT_DOMAIN) . '</p></div>';
                    });
                }
                break;
                
            case 'update_quota_count':
                $date = sanitize_text_field($post_data['quota_date']);
                
                if ($database->update_booking_quota_count($date)) {
                    add_action('admin_notices', function() {
                        echo '<div class="notice notice-success is-dismissible"><p>' . __('予約数を更新しました', EASY_BOOKINGER_TEXT_DOMAIN) . '</p></div>';
                    });
                } else {
                    add_action('admin_notices', function() {
                        echo '<div class="notice notice-error is-dismissible"><p>' . __('予約数の更新に失敗しました', EASY_BOOKINGER_TEXT_DOMAIN) . '</p></div>';
                    });
                }
                break;
                
            case 'delete_quota':
                $date = sanitize_text_field($post_data['quota_date']);
                
                // Delete the quota setting - the date will revert to default quota
                global $wpdb;
                $quotas_table = $wpdb->prefix . 'easy_bookinger_booking_quotas';
                
                if ($wpdb->delete($quotas_table, array('quota_date' => $date))) {
                    add_action('admin_notices', function() {
                        echo '<div class="notice notice-success is-dismissible"><p>' . __('予約枠設定を削除しました。この日はデフォルト設定が適用されます。', EASY_BOOKINGER_TEXT_DOMAIN) . '</p></div>';
                    });
                } else {
                    add_action('admin_notices', function() {
                        echo '<div class="notice notice-error is-dismissible"><p>' . __('予約枠設定の削除に失敗しました', EASY_BOOKINGER_TEXT_DOMAIN) . '</p></div>';
                    });
                }
                break;
        }
    }
    
    /**
     * Handle time slots actions
     */
    private function handle_timeslots_action($post_data) {
        if (!wp_verify_nonce($post_data['eb_timeslots_nonce'], 'eb_timeslots_action')) {
            $this->handle_security_error('easy-bookinger-timeslots');
            return;
        }
        
        $database = EasyBookinger_Database::instance();
        
        switch ($post_data['action']) {
            case 'add_timeslot':
                $start_time = sanitize_text_field($post_data['start_time']) . ':00';
                $slot_name = sanitize_text_field($post_data['slot_name']);
                $max_bookings = 1; // Default to 1 booking per slot
                
                if ($database->add_time_slot($start_time, $slot_name, $max_bookings)) {
                    add_action('admin_notices', function() {
                        echo '<div class="notice notice-success is-dismissible"><p>' . __('時間帯を追加しました', EASY_BOOKINGER_TEXT_DOMAIN) . '</p></div>';
                    });
                } else {
                    add_action('admin_notices', function() {
                        echo '<div class="notice notice-error is-dismissible"><p>' . __('時間帯の追加に失敗しました', EASY_BOOKINGER_TEXT_DOMAIN) . '</p></div>';
                    });
                }
                break;
                
            case 'toggle_timeslot':
                $slot_id = (int)$post_data['slot_id'];
                
                // Get current status
                $slots = $database->get_time_slots();
                $current_slot = null;
                foreach ($slots as $slot) {
                    if ($slot->id == $slot_id) {
                        $current_slot = $slot;
                        break;
                    }
                }
                
                if ($current_slot) {
                    $new_status = $current_slot->is_active ? 0 : 1;
                    if ($database->update_time_slot($slot_id, array('is_active' => $new_status))) {
                        $status_text = $new_status ? __('有効化', EASY_BOOKINGER_TEXT_DOMAIN) : __('無効化', EASY_BOOKINGER_TEXT_DOMAIN);
                        add_action('admin_notices', function() use ($status_text) {
                            echo '<div class="notice notice-success is-dismissible"><p>' . sprintf(__('時間帯を%sしました', EASY_BOOKINGER_TEXT_DOMAIN), $status_text) . '</p></div>';
                        });
                    }
                }
                break;
                
            case 'delete_timeslot':
                $slot_id = (int)$post_data['slot_id'];
                
                if ($database->delete_time_slot($slot_id)) {
                    add_action('admin_notices', function() {
                        echo '<div class="notice notice-success is-dismissible"><p>' . __('時間帯を削除しました', EASY_BOOKINGER_TEXT_DOMAIN) . '</p></div>';
                    });
                } else {
                    add_action('admin_notices', function() {
                        echo '<div class="notice notice-error is-dismissible"><p>' . __('時間帯の削除に失敗しました', EASY_BOOKINGER_TEXT_DOMAIN) . '</p></div>';
                    });
                }
                break;
        }
    }
    
    /**
     * Get Japanese day name for date
     */
    private function get_japanese_day_name($date) {
        $day_of_week = date('w', strtotime($date)); // 0 = Sunday, 6 = Saturday
        $japanese_days = array(
            0 => __('日曜日', EASY_BOOKINGER_TEXT_DOMAIN),
            1 => __('月曜日', EASY_BOOKINGER_TEXT_DOMAIN),
            2 => __('火曜日', EASY_BOOKINGER_TEXT_DOMAIN),
            3 => __('水曜日', EASY_BOOKINGER_TEXT_DOMAIN),
            4 => __('木曜日', EASY_BOOKINGER_TEXT_DOMAIN),
            5 => __('金曜日', EASY_BOOKINGER_TEXT_DOMAIN),
            6 => __('土曜日', EASY_BOOKINGER_TEXT_DOMAIN)
        );
        
        return $japanese_days[$day_of_week];
    }

    /**
     * Format booking time display
     */
    private function format_booking_time($booking_time) {
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
}