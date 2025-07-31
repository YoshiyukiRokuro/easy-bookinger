<?php
/**
 * Database management class
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class EasyBookinger_Database {
    
    private static $instance = null;
    
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Constructor
    }
    
    /**
     * Create database tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Bookings table
        $bookings_table = $wpdb->prefix . 'easy_bookinger_bookings';
        $sql_bookings = "CREATE TABLE $bookings_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            booking_date date NOT NULL,
            booking_time varchar(20) DEFAULT NULL,
            user_name varchar(255) NOT NULL,
            email varchar(255) NOT NULL,
            phone varchar(50) DEFAULT NULL,
            comment text DEFAULT NULL,
            form_data longtext DEFAULT NULL,
            status varchar(20) DEFAULT 'active',
            pdf_token varchar(255) DEFAULT NULL,
            pdf_password varchar(12) DEFAULT NULL,
            pdf_expires datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY booking_date (booking_date),
            KEY email (email),
            KEY status (status),
            KEY pdf_token (pdf_token)
        ) $charset_collate;";
        
        // Settings table
        $settings_table = $wpdb->prefix . 'easy_bookinger_settings';
        $sql_settings = "CREATE TABLE $settings_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            setting_key varchar(255) NOT NULL,
            setting_value longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY setting_key (setting_key)
        ) $charset_collate;";
        
        // PDF links table
        $pdf_links_table = $wpdb->prefix . 'easy_bookinger_pdf_links';
        $sql_pdf_links = "CREATE TABLE $pdf_links_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            booking_id mediumint(9) NOT NULL,
            token varchar(255) NOT NULL,
            password varchar(12) NOT NULL,
            expires_at datetime NOT NULL,
            download_count int DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY token (token),
            KEY booking_id (booking_id),
            KEY expires_at (expires_at),
            FOREIGN KEY (booking_id) REFERENCES $bookings_table(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_bookings);
        dbDelta($sql_settings);
        dbDelta($sql_pdf_links);
    }
    
    /**
     * Get bookings
     */
    public function get_bookings($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'status' => 'active',
            'date_from' => null,
            'date_to' => null,
            'limit' => -1,
            'offset' => 0,
            'orderby' => 'booking_date',
            'order' => 'ASC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $table = $wpdb->prefix . 'easy_bookinger_bookings';
        $where_clauses = array();
        $values = array();
        
        if (!empty($args['status'])) {
            $where_clauses[] = 'status = %s';
            $values[] = $args['status'];
        }
        
        if (!empty($args['date_from'])) {
            $where_clauses[] = 'booking_date >= %s';
            $values[] = $args['date_from'];
        }
        
        if (!empty($args['date_to'])) {
            $where_clauses[] = 'booking_date <= %s';
            $values[] = $args['date_to'];
        }
        
        $where_sql = '';
        if (!empty($where_clauses)) {
            $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        }
        
        $order_sql = sprintf('ORDER BY %s %s', 
            sanitize_sql_orderby($args['orderby']), 
            ($args['order'] === 'DESC') ? 'DESC' : 'ASC'
        );
        
        $limit_sql = '';
        if ($args['limit'] > 0) {
            $limit_sql = $wpdb->prepare('LIMIT %d OFFSET %d', $args['limit'], $args['offset']);
        }
        
        $sql = "SELECT * FROM $table $where_sql $order_sql $limit_sql";
        
        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Get booking by ID
     */
    public function get_booking($id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'easy_bookinger_bookings';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    }
    
    /**
     * Create booking
     */
    public function create_booking($data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'easy_bookinger_bookings';
        
        $booking_data = array(
            'booking_date' => sanitize_text_field($data['booking_date']),
            'booking_time' => sanitize_text_field($data['booking_time'] ?? ''),
            'user_name' => sanitize_text_field($data['user_name']),
            'email' => sanitize_email($data['email']),
            'phone' => sanitize_text_field($data['phone'] ?? ''),
            'comment' => sanitize_textarea_field($data['comment'] ?? ''),
            'form_data' => maybe_serialize($data['form_data'] ?? array()),
            'status' => 'active',
            'pdf_token' => $this->generate_token(),
            'pdf_password' => $this->generate_password(),
            'pdf_expires' => date('Y-m-d H:i:s', strtotime('+180 days'))
        );
        
        $result = $wpdb->insert($table, $booking_data);
        
        if ($result === false) {
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update booking
     */
    public function update_booking($id, $data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'easy_bookinger_bookings';
        
        $update_data = array();
        
        if (isset($data['booking_date'])) {
            $update_data['booking_date'] = sanitize_text_field($data['booking_date']);
        }
        
        if (isset($data['booking_time'])) {
            $update_data['booking_time'] = sanitize_text_field($data['booking_time']);
        }
        
        if (isset($data['user_name'])) {
            $update_data['user_name'] = sanitize_text_field($data['user_name']);
        }
        
        if (isset($data['email'])) {
            $update_data['email'] = sanitize_email($data['email']);
        }
        
        if (isset($data['phone'])) {
            $update_data['phone'] = sanitize_text_field($data['phone']);
        }
        
        if (isset($data['comment'])) {
            $update_data['comment'] = sanitize_textarea_field($data['comment']);
        }
        
        if (isset($data['status'])) {
            $update_data['status'] = sanitize_text_field($data['status']);
        }
        
        if (isset($data['form_data'])) {
            $update_data['form_data'] = maybe_serialize($data['form_data']);
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        return $wpdb->update($table, $update_data, array('id' => $id));
    }
    
    /**
     * Delete booking
     */
    public function delete_booking($id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'easy_bookinger_bookings';
        return $wpdb->delete($table, array('id' => $id));
    }
    
    /**
     * Get booked dates
     */
    public function get_booked_dates($date_from = null, $date_to = null) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'easy_bookinger_bookings';
        $where_clauses = array("status = 'active'");
        $values = array();
        
        if ($date_from) {
            $where_clauses[] = 'booking_date >= %s';
            $values[] = $date_from;
        }
        
        if ($date_to) {
            $where_clauses[] = 'booking_date <= %s';
            $values[] = $date_to;
        }
        
        $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        $sql = "SELECT booking_date, COUNT(*) as booking_count FROM $table $where_sql GROUP BY booking_date";
        
        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }
        
        $results = $wpdb->get_results($sql);
        
        $booked_dates = array();
        foreach ($results as $result) {
            $booked_dates[$result->booking_date] = $result->booking_count;
        }
        
        return $booked_dates;
    }
    
    /**
     * Generate random token
     */
    private function generate_token() {
        return wp_generate_password(32, false);
    }
    
    /**
     * Generate random password
     */
    private function generate_password() {
        return wp_generate_password(12, false);
    }
    
    /**
     * Get booking by PDF token
     */
    public function get_booking_by_token($token) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'easy_bookinger_bookings';
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE pdf_token = %s AND pdf_expires > NOW() AND status = 'active'", 
            $token
        ));
    }
}