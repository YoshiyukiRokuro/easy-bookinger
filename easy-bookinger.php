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
        
        // Activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
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
        require_once EASY_BOOKINGER_PLUGIN_DIR . 'includes/class-pdf.php';
        
        // Admin includes
        if (is_admin()) {
            require_once EASY_BOOKINGER_PLUGIN_DIR . 'admin/class-admin.php';
            require_once EASY_BOOKINGER_PLUGIN_DIR . 'admin/class-settings.php';
            require_once EASY_BOOKINGER_PLUGIN_DIR . 'admin/class-export.php';
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
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
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
            'pdf_expiry_days' => 180,
            'admin_email_enabled' => true,
            'user_email_enabled' => true
        );
        
        add_option('easy_bookinger_settings', $default_settings);
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