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
        
        // Date restrictions table
        $restrictions_table = $wpdb->prefix . 'easy_bookinger_date_restrictions';
        $sql_restrictions = "CREATE TABLE $restrictions_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            restriction_date date NOT NULL,
            restriction_type varchar(20) DEFAULT 'custom',
            reason varchar(255) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY restriction_date (restriction_date),
            KEY restriction_type (restriction_type)
        ) $charset_collate;";
        
        // Booking quotas table
        $quotas_table = $wpdb->prefix . 'easy_bookinger_booking_quotas';
        $sql_quotas = "CREATE TABLE $quotas_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            quota_date date NOT NULL,
            max_bookings int DEFAULT 3,
            current_bookings int DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY quota_date (quota_date)
        ) $charset_collate;";
        
        // Time slots table
        $timeslots_table = $wpdb->prefix . 'easy_bookinger_time_slots';
        $sql_timeslots = "CREATE TABLE $timeslots_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            start_time time NOT NULL,
            end_time time NOT NULL,
            slot_name varchar(50) DEFAULT NULL,
            is_active tinyint(1) DEFAULT 1,
            max_bookings int DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY start_time (start_time),
            KEY is_active (is_active)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_bookings);
        dbDelta($sql_settings);
        dbDelta($sql_pdf_links);
        dbDelta($sql_restrictions);
        dbDelta($sql_quotas);
        dbDelta($sql_timeslots);
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
    
    /**
     * Date Restrictions Methods
     */
    
    /**
     * Get restricted dates
     */
    public function get_restricted_dates($date_from = null, $date_to = null) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'easy_bookinger_date_restrictions';
        $where_clauses = array();
        $values = array();
        
        if ($date_from) {
            $where_clauses[] = 'restriction_date >= %s';
            $values[] = $date_from;
        }
        
        if ($date_to) {
            $where_clauses[] = 'restriction_date <= %s';
            $values[] = $date_to;
        }
        
        $where_sql = '';
        if (!empty($where_clauses)) {
            $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        }
        
        $sql = "SELECT * FROM $table $where_sql ORDER BY restriction_date";
        
        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Add date restriction
     */
    public function add_date_restriction($date, $type = 'custom', $reason = '') {
        global $wpdb;
        
        $table = $wpdb->prefix . 'easy_bookinger_date_restrictions';
        
        return $wpdb->insert($table, array(
            'restriction_date' => sanitize_text_field($date),
            'restriction_type' => sanitize_text_field($type),
            'reason' => sanitize_text_field($reason)
        ));
    }
    
    /**
     * Remove date restriction
     */
    public function remove_date_restriction($date) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'easy_bookinger_date_restrictions';
        return $wpdb->delete($table, array('restriction_date' => $date));
    }
    
    /**
     * Check if date is restricted
     */
    public function is_date_restricted($date) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'easy_bookinger_date_restrictions';
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE restriction_date = %s",
            $date
        ));
        
        return $result > 0;
    }
    
    /**
     * Booking Quota Methods
     */
    
    /**
     * Get booking quota for date
     */
    public function get_booking_quota($date) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'easy_bookinger_booking_quotas';
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE quota_date = %s",
            $date
        ));
    }
    
    /**
     * Set booking quota for date
     */
    public function set_booking_quota($date, $max_bookings) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'easy_bookinger_booking_quotas';
        
        // Get current bookings count for this date
        $bookings_table = $wpdb->prefix . 'easy_bookinger_bookings';
        $current_bookings = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $bookings_table WHERE booking_date = %s AND status = 'active'",
            $date
        ));
        
        // Check if quota already exists
        $existing = $this->get_booking_quota($date);
        
        if ($existing) {
            return $wpdb->update($table, 
                array(
                    'max_bookings' => (int)$max_bookings,
                    'current_bookings' => (int)$current_bookings
                ),
                array('quota_date' => $date)
            );
        } else {
            return $wpdb->insert($table, array(
                'quota_date' => sanitize_text_field($date),
                'max_bookings' => (int)$max_bookings,
                'current_bookings' => (int)$current_bookings
            ));
        }
    }
    
    /**
     * Update booking quota count
     */
    public function update_booking_quota_count($date) {
        global $wpdb;
        
        $quotas_table = $wpdb->prefix . 'easy_bookinger_booking_quotas';
        $bookings_table = $wpdb->prefix . 'easy_bookinger_bookings';
        
        // Get current bookings count
        $current_bookings = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $bookings_table WHERE booking_date = %s AND status = 'active'",
            $date
        ));
        
        // Update quota table
        return $wpdb->update($quotas_table,
            array('current_bookings' => (int)$current_bookings),
            array('quota_date' => $date)
        );
    }
    
    /**
     * Get remaining quota for date
     */
    public function get_remaining_quota($date) {
        $quota = $this->get_booking_quota($date);
        
        if (!$quota) {
            // Get default quota from settings
            $settings = get_option('easy_bookinger_settings', array());
            $default_quota = isset($settings['default_daily_quota']) ? (int)$settings['default_daily_quota'] : 3;
            
            // Get current bookings
            global $wpdb;
            $bookings_table = $wpdb->prefix . 'easy_bookinger_bookings';
            $current_bookings = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $bookings_table WHERE booking_date = %s AND status = 'active'",
                $date
            ));
            
            return max(0, $default_quota - (int)$current_bookings);
        }
        
        return max(0, $quota->max_bookings - $quota->current_bookings);
    }
    
    /**
     * Time Slots Methods
     */
    
    /**
     * Get active time slots
     */
    public function get_active_time_slots() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'easy_bookinger_time_slots';
        return $wpdb->get_results(
            "SELECT * FROM $table WHERE is_active = 1 ORDER BY start_time"
        );
    }
    
    /**
     * Get all time slots
     */
    public function get_time_slots() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'easy_bookinger_time_slots';
        return $wpdb->get_results(
            "SELECT * FROM $table ORDER BY start_time"
        );
    }
    
    /**
     * Add time slot
     */
    public function add_time_slot($start_time, $end_time, $slot_name = '', $max_bookings = 1) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'easy_bookinger_time_slots';
        
        return $wpdb->insert($table, array(
            'start_time' => sanitize_text_field($start_time),
            'end_time' => sanitize_text_field($end_time),
            'slot_name' => sanitize_text_field($slot_name),
            'max_bookings' => (int)$max_bookings,
            'is_active' => 1
        ));
    }
    
    /**
     * Update time slot
     */
    public function update_time_slot($id, $data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'easy_bookinger_time_slots';
        
        $update_data = array();
        
        if (isset($data['start_time'])) {
            $update_data['start_time'] = sanitize_text_field($data['start_time']);
        }
        
        if (isset($data['end_time'])) {
            $update_data['end_time'] = sanitize_text_field($data['end_time']);
        }
        
        if (isset($data['slot_name'])) {
            $update_data['slot_name'] = sanitize_text_field($data['slot_name']);
        }
        
        if (isset($data['max_bookings'])) {
            $update_data['max_bookings'] = (int)$data['max_bookings'];
        }
        
        if (isset($data['is_active'])) {
            $update_data['is_active'] = (int)$data['is_active'];
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        return $wpdb->update($table, $update_data, array('id' => (int)$id));
    }
    
    /**
     * Delete time slot
     */
    public function delete_time_slot($id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'easy_bookinger_time_slots';
        return $wpdb->delete($table, array('id' => (int)$id));
    }
    
    /**
     * Get default 15-minute time slots
     */
    public static function get_default_time_slots() {
        $slots = array();
        
        // Generate 15-minute slots from 9:00 to 17:00
        for ($hour = 9; $hour < 17; $hour++) {
            for ($minute = 0; $minute < 60; $minute += 15) {
                $start_time = sprintf('%02d:%02d:00', $hour, $minute);
                $end_minute = $minute + 15;
                $end_hour = $hour;
                
                if ($end_minute >= 60) {
                    $end_minute = 0;
                    $end_hour++;
                }
                
                $end_time = sprintf('%02d:%02d:00', $end_hour, $end_minute);
                
                $slots[] = array(
                    'start_time' => $start_time,
                    'end_time' => $end_time,
                    'slot_name' => sprintf('%02d:%02d-%02d:%02d', $hour, $minute, $end_hour, $end_minute),
                    'max_bookings' => 1
                );
            }
        }
        
        return $slots;
    }
}