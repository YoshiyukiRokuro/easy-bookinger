<?php
/**
 * Public-facing functionality
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class EasyBookinger_Public {
    
    private static $instance = null;
    
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
        add_action('wp_head', array($this, 'add_meta_tags'));
    }
    
    /**
     * Enqueue public styles
     */
    public function enqueue_styles() {
        wp_enqueue_style(
            'easy-bookinger-public',
            EASY_BOOKINGER_PLUGIN_URL . 'assets/css/easy-bookinger-public.css',
            array(),
            EASY_BOOKINGER_VERSION
        );
    }
    
    /**
     * Add meta tags for responsive design
     */
    public function add_meta_tags() {
        global $post;
        
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'easy_bookinger')) {
            echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">' . "\n";
        }
    }
}