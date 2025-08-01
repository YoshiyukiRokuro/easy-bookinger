<?php
/**
 * Plugin Name: Easy Bookinger
 * Plugin URI: https://github.com/YoshiyukiRokuro/easy-bookinger
 * Description: WordPress予約日登録・集計システム - Calendar-based booking system with PDF generation and admin management.
 * Version: 1.0.0
 * Author: YoshiyukiRokuro
 * License: GPL v2 or later
 * Text Domain: easy-bookinger
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 8.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('EASY_BOOKINGER_VERSION', '1.0.0');
define('EASY_BOOKINGER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('EASY_BOOKINGER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('EASY_BOOKINGER_PLUGIN_FILE', __FILE__);
define('EASY_BOOKINGER_TEXT_DOMAIN', 'easy-bookinger');

/**
 * Main Plugin Class
 */
final class EasyBookinger {
    
    /**
     * Plugin instance
     */
    private static $instance = null;
    
    /**
     * Get plugin instance
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('init', array($this, 'init'));
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // File download handler
        add_action('admin_init', array($this, 'handle_file_downloads'));
        
        // Booking confirmation handler
        add_action('init', array($this, 'handle_booking_confirmation'));
        
        // Cleanup expired bookings cron job
        add_action('easy_bookinger_cleanup_expired', array($this, 'cleanup_expired_bookings'));
        
        // Activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Handle file downloads
     */
    public function handle_file_downloads() {
        if (isset($_GET['action']) && $_GET['action'] === 'easy_bookinger_download_file') {
            EasyBookinger_File_Manager::handle_download();
        }
    }
    
    /**
     * Handle booking confirmation
     */
    public function handle_booking_confirmation() {
        if (isset($_GET['action']) && $_GET['action'] === 'easy_bookinger_confirm' && isset($_GET['token'])) {
            $token = sanitize_text_field($_GET['token']);
            
            // Load database class if not already loaded
            if (!class_exists('EasyBookinger_Database')) {
                require_once EASY_BOOKINGER_PLUGIN_DIR . 'includes/class-database.php';
            }
            
            $database = EasyBookinger_Database::instance();
            $confirmed_booking = $database->confirm_booking_by_token($token);
            
            if ($confirmed_booking) {
                // Booking confirmed successfully
                wp_redirect(add_query_arg(array(
                    'eb_confirmation' => 'success',
                    'booking_id' => $confirmed_booking->id
                ), home_url()));
                exit;
            } else {
                // Confirmation failed (token expired, invalid, etc.)
                wp_redirect(add_query_arg(array(
                    'eb_confirmation' => 'failed'
                ), home_url()));
                exit;
            }
        }
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        $this->includes();
        $this->init_components();
    }
    
    /**
     * Include required files
     */
    private function includes() {
        // Core includes
        require_once EASY_BOOKINGER_PLUGIN_DIR . 'includes/class-database.php';
        require_once EASY_BOOKINGER_PLUGIN_DIR . 'includes/class-shortcode.php';
        require_once EASY_BOOKINGER_PLUGIN_DIR . 'includes/class-ajax.php';
        require_once EASY_BOOKINGER_PLUGIN_DIR . 'includes/class-email.php';
        require_once EASY_BOOKINGER_PLUGIN_DIR . 'includes/class-file-manager.php';
        
        // Admin includes
        if (is_admin()) {
            require_once EASY_BOOKINGER_PLUGIN_DIR . 'admin/class-admin.php';
            require_once EASY_BOOKINGER_PLUGIN_DIR . 'admin/class-settings.php';
            require_once EASY_BOOKINGER_PLUGIN_DIR . 'admin/class-export.php';
            require_once EASY_BOOKINGER_PLUGIN_DIR . 'admin/class-backup.php';
        }
        
        // Public includes
        if (!is_admin()) {
            require_once EASY_BOOKINGER_PLUGIN_DIR . 'public/class-public.php';
        }
    }
    
    /**
     * Initialize components
     */
    private function init_components() {
        // Initialize database
        EasyBookinger_Database::instance();
        
        // Initialize shortcode
        EasyBookinger_Shortcode::instance();
        
        // Initialize AJAX
        EasyBookinger_Ajax::instance();
        
        // Initialize admin
        if (is_admin()) {
            EasyBookinger_Admin::instance();
        }
        
        // Initialize public
        if (!is_admin()) {
            EasyBookinger_Public::instance();
        }
    }
    
    /**
     * Load text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            EASY_BOOKINGER_TEXT_DOMAIN,
            false,
            dirname(plugin_basename(__FILE__)) . '/languages/'
        );
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Load database class if not already loaded
        if (!class_exists('EasyBookinger_Database')) {
            require_once EASY_BOOKINGER_PLUGIN_DIR . 'includes/class-database.php';
        }
        
        // Create database tables
        if (class_exists('EasyBookinger_Database')) {
            EasyBookinger_Database::create_tables();
        }
        
        // Add default settings
        $this->add_default_settings();
        
        // Schedule cleanup cron job
        if (!wp_next_scheduled('easy_bookinger_cleanup_expired')) {
            wp_schedule_event(time(), 'hourly', 'easy_bookinger_cleanup_expired');
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled cleanup job
        wp_clear_scheduled_hook('easy_bookinger_cleanup_expired');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Add default settings
     */
    private function add_default_settings() {
        $default_settings = array(
            'display_months' => 3,
            'max_selectable_days' => 5,
            'allowed_days' => array(1, 2, 3, 4, 5), // Monday to Friday
            'default_daily_quota' => 3, // Default daily booking quota
            'enable_time_slots' => false, // Enable time slot functionality
            'allow_same_day_booking' => true, // Allow same-day booking by default
            'booking_fields' => array(
                array(
                    'name' => 'user_name',
                    'label' => __('氏名', EASY_BOOKINGER_TEXT_DOMAIN),
                    'type' => 'text',
                    'required' => true
                ),
                array(
                    'name' => 'email',
                    'label' => __('メールアドレス', EASY_BOOKINGER_TEXT_DOMAIN),
                    'type' => 'email',
                    'required' => true
                ),
                array(
                    'name' => 'email_confirm',
                    'label' => __('メールアドレス（確認用）', EASY_BOOKINGER_TEXT_DOMAIN),
                    'type' => 'email',
                    'required' => true
                ),
                array(
                    'name' => 'comment',
                    'label' => __('コメント', EASY_BOOKINGER_TEXT_DOMAIN),
                    'type' => 'textarea',
                    'required' => false,
                    'maxlength' => 200
                )
            ),
            'admin_email_enabled' => true,
            'user_email_enabled' => true
        );
        
        add_option('easy_bookinger_settings', $default_settings);
        
        // Add default time slots if enabled
        $this->add_default_time_slots();
    }
    
    /**
     * Add default time slots
     */
    private function add_default_time_slots() {
        // Load database class if not already loaded
        if (!class_exists('EasyBookinger_Database')) {
            require_once EASY_BOOKINGER_PLUGIN_DIR . 'includes/class-database.php';
        }
        
        $database = EasyBookinger_Database::instance();
        $existing_slots = $database->get_time_slots();
        
        // Only add default slots if none exist
        if (empty($existing_slots)) {
            $default_slots = EasyBookinger_Database::get_default_time_slots();
            
            foreach ($default_slots as $slot) {
                $database->add_time_slot(
                    $slot['start_time'],
                    $slot['slot_name'],
                    $slot['max_bookings']
                );
            }
        }
        
        // Add common Japanese holidays for current year
        $this->add_default_holidays();
    }
    
    /**
     * Add default holidays for the current year
     */
    private function add_default_holidays() {
        $database = EasyBookinger_Database::instance();
        $current_year = date('Y');
        
        // Common Japanese holidays (basic list)
        $holidays = array(
            $current_year . '-01-01' => '元日',
            $current_year . '-01-08' => '成人の日（第2月曜日）',
            $current_year . '-02-11' => '建国記念の日',
            $current_year . '-02-23' => '天皇誕生日',
            $current_year . '-03-20' => '春分の日',
            $current_year . '-04-29' => '昭和の日',
            $current_year . '-05-03' => '憲法記念日',
            $current_year . '-05-04' => 'みどりの日',
            $current_year . '-05-05' => 'こどもの日',
            $current_year . '-07-15' => '海の日（第3月曜日）',
            $current_year . '-08-11' => '山の日',
            $current_year . '-09-16' => '敬老の日（第3月曜日）',
            $current_year . '-09-23' => '秋分の日',
            $current_year . '-10-14' => 'スポーツの日（第2月曜日）',
            $current_year . '-11-03' => '文化の日',
            $current_year . '-11-23' => '勤労感謝の日',
            $current_year . '-12-29' => '年末',
            $current_year . '-12-30' => '年末',
            $current_year . '-12-31' => '大晦日'
        );
        
        foreach ($holidays as $date => $reason) {
            // Check if holiday already exists
            if (!$database->is_date_restricted($date)) {
                $database->add_date_restriction($date, 'holiday', $reason);
            }
        }
    }
    
    /**
     * Cleanup expired bookings
     */
    public function cleanup_expired_bookings() {
        // Load database class if not already loaded
        if (!class_exists('EasyBookinger_Database')) {
            require_once EASY_BOOKINGER_PLUGIN_DIR . 'includes/class-database.php';
        }
        
        $database = EasyBookinger_Database::instance();
        $database->cleanup_expired_bookings();
    }
}

/**
 * Initialize the plugin
 */
function easy_bookinger() {
    return EasyBookinger::instance();
}

// Initialize plugin
easy_bookinger();