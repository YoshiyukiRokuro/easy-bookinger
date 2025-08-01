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
            status varchar(20) DEFAULT 'pending',
            confirmation_token varchar(255) DEFAULT NULL,
            token_expires_at datetime DEFAULT NULL,
            confirmed_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY booking_date (booking_date),
            KEY email (email),
            KEY status (status),
            KEY confirmation_token (confirmation_token),
            KEY token_expires_at (token_expires_at)
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
            slot_name varchar(50) DEFAULT NULL,
            is_active tinyint(1) DEFAULT 1,
            max_bookings int DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY start_time (start_time),
            KEY is_active (is_active)
        ) $charset_collate;";
        
        // Special availability table (for temporary booking days)
        $special_availability_table = $wpdb->prefix . 'easy_bookinger_special_availability';
        $sql_special_availability = "CREATE TABLE $special_availability_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            availability_date date NOT NULL,
            is_available tinyint(1) DEFAULT 1,
            reason varchar(255) DEFAULT NULL,
            max_bookings int DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY availability_date (availability_date),
            KEY is_available (is_available)
        ) $charset_collate;";
        
        // Admin emails table (for multiple admin email management)
        $admin_emails_table = $wpdb->prefix . 'easy_bookinger_admin_emails';
        $sql_admin_emails = "CREATE TABLE $admin_emails_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            email_address varchar(255) NOT NULL,
            notification_types longtext DEFAULT NULL,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY email_address (email_address),
            KEY is_active (is_active)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_bookings);
        dbDelta($sql_settings);
        dbDelta($sql_restrictions);
        dbDelta($sql_quotas);
        dbDelta($sql_timeslots);
        dbDelta($sql_special_availability);
        dbDelta($sql_admin_emails);
        
        // Update existing bookings table if needed
        self::update_bookings_table_schema();
    }
    
    /**
     * Update bookings table schema for existing installations
     */
    private static function update_bookings_table_schema() {
        global $wpdb;
        
        $bookings_table = $wpdb->prefix . 'easy_bookinger_bookings';
        
        // Check if new columns exist and add them if they don't
        $columns = $wpdb->get_col("DESC `{$bookings_table}`", 0);
        
        if (!in_array('confirmation_token', $columns)) {
            $wpdb->query("ALTER TABLE `{$bookings_table}` ADD COLUMN `confirmation_token` varchar(255) DEFAULT NULL");
            $wpdb->query("ALTER TABLE `{$bookings_table}` ADD INDEX `confirmation_token` (`confirmation_token`)");
        }
        
        if (!in_array('token_expires_at', $columns)) {
            $wpdb->query("ALTER TABLE `{$bookings_table}` ADD COLUMN `token_expires_at` datetime DEFAULT NULL");
            $wpdb->query("ALTER TABLE `{$bookings_table}` ADD INDEX `token_expires_at` (`token_expires_at`)");
        }
        
        if (!in_array('confirmed_at', $columns)) {
            $wpdb->query("ALTER TABLE `{$bookings_table}` ADD COLUMN `confirmed_at` datetime DEFAULT NULL");
        }
        
        // Update existing bookings to confirmed status if they have 'active' status
        $wpdb->query("UPDATE `{$bookings_table}` SET `status` = 'confirmed', `confirmed_at` = `created_at` WHERE `status` = 'active'");
    }
    
    /**
     * Get bookings
     */
    public function get_bookings($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'status' => 'confirmed',
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
            if ($args['status'] === 'confirmed') {
                // For confirmed status, include both 'confirmed' and legacy 'active' status
                $where_clauses[] = "(status = 'confirmed' OR status = 'active')";
            } else {
                $where_clauses[] = 'status = %s';
                $values[] = $args['status'];
            }
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
            'status' => sanitize_text_field($data['status'] ?? 'pending')
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
     * Generate confirmation token for booking
     */
    public function generate_confirmation_token($booking_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'easy_bookinger_bookings';
        $token = wp_generate_password(32, false, false);
        $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        $result = $wpdb->update(
            $table,
            array(
                'confirmation_token' => $token,
                'token_expires_at' => $expires_at
            ),
            array('id' => $booking_id)
        );
        
        return $result ? $token : false;
    }
    
    /**
     * Confirm booking by token
     */
    public function confirm_booking_by_token($token) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'easy_bookinger_bookings';
        
        // First, check if token exists and is not expired
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE confirmation_token = %s AND token_expires_at > NOW() AND status = 'pending'",
            $token
        ));
        
        if (!$booking) {
            return false;
        }
        
        // Update booking status to confirmed
        $result = $wpdb->update(
            $table,
            array(
                'status' => 'confirmed',
                'confirmed_at' => current_time('mysql'),
                'confirmation_token' => null,
                'token_expires_at' => null
            ),
            array('id' => $booking->id)
        );
        
        return $result ? $booking : false;
    }
    
    /**
     * Get booking by confirmation token
     */
    public function get_booking_by_token($token) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'easy_bookinger_bookings';
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE confirmation_token = %s",
            $token
        ));
    }
    
    /**
     * Clean up expired temporary bookings
     */
    public function cleanup_expired_bookings() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'easy_bookinger_bookings';
        
        // Delete expired pending bookings
        $result = $wpdb->query("DELETE FROM $table WHERE status = 'pending' AND token_expires_at < NOW()");
        
        return $result;
    }
    
    /**
     * Get booked dates
     */
    public function get_booked_dates($date_from = null, $date_to = null) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'easy_bookinger_bookings';
        $where_clauses = array("(status = 'confirmed' OR status = 'active')");
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
     * Update date restriction
     */
    public function update_date_restriction($id, $data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'easy_bookinger_date_restrictions';
        
        $update_data = array();
        
        if (isset($data['restriction_date'])) {
            $update_data['restriction_date'] = sanitize_text_field($data['restriction_date']);
        }
        
        if (isset($data['restriction_type'])) {
            $update_data['restriction_type'] = sanitize_text_field($data['restriction_type']);
        }
        
        if (isset($data['reason'])) {
            $update_data['reason'] = sanitize_text_field($data['reason']);
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        return $wpdb->update($table, $update_data, array('id' => (int)$id));
    }

    /**
     * Get date restriction by ID
     */
    public function get_date_restriction_by_id($id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'easy_bookinger_date_restrictions';
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $id
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
            "SELECT COUNT(*) FROM $bookings_table WHERE booking_date = %s AND (status = 'confirmed' OR status = 'active')",
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
            "SELECT COUNT(*) FROM $bookings_table WHERE booking_date = %s AND (status = 'confirmed' OR status = 'active')",
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
        // Check for special availability first
        $special_availability = $this->get_special_availability($date, $date);
        if (!empty($special_availability) && $special_availability[0]->is_available && !is_null($special_availability[0]->max_bookings)) {
            // Use special availability quota
            global $wpdb;
            $bookings_table = $wpdb->prefix . 'easy_bookinger_bookings';
            $current_bookings = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $bookings_table WHERE booking_date = %s AND (status = 'confirmed' OR status = 'active')",
                $date
            ));
            
            return max(0, $special_availability[0]->max_bookings - (int)$current_bookings);
        }
        
        $quota = $this->get_booking_quota($date);
        
        if (!$quota) {
            // Get default quota from settings
            $settings = get_option('easy_bookinger_settings', array());
            $default_quota = isset($settings['default_daily_quota']) ? (int)$settings['default_daily_quota'] : 3;
            
            // Get current bookings
            global $wpdb;
            $bookings_table = $wpdb->prefix . 'easy_bookinger_bookings';
            $current_bookings = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $bookings_table WHERE booking_date = %s AND (status = 'confirmed' OR status = 'active')",
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
     * Get time slot by ID
     */
    public function get_time_slot_by_id($slot_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'easy_bookinger_time_slots';
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $slot_id
        ));
    }
    
    /**
     * Add time slot
     */
    public function add_time_slot($start_time, $slot_name = '', $max_bookings = 1) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'easy_bookinger_time_slots';
        
        return $wpdb->insert($table, array(
            'start_time' => sanitize_text_field($start_time),
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
        
        // Generate hourly slots from 9:00 to 17:00
        for ($hour = 9; $hour < 17; $hour++) {
            $start_time = sprintf('%02d:00:00', $hour);
            
            $slots[] = array(
                'start_time' => $start_time,
                'slot_name' => sprintf('%02d:00', $hour),
                'max_bookings' => 1
            );
        }
        
        return $slots;
    }
    
    /**
     * Special Availability Methods
     */
    
    /**
     * Get special availability dates
     */
    public function get_special_availability($date_from = null, $date_to = null) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'easy_bookinger_special_availability';
        $where_clauses = array();
        $values = array();
        
        if ($date_from) {
            $where_clauses[] = 'availability_date >= %s';
            $values[] = $date_from;
        }
        
        if ($date_to) {
            $where_clauses[] = 'availability_date <= %s';
            $values[] = $date_to;
        }
        
        $where_sql = '';
        if (!empty($where_clauses)) {
            $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        }
        
        $sql = "SELECT * FROM $table $where_sql ORDER BY availability_date";
        
        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Add special availability date
     */
    public function add_special_availability($date, $is_available = 1, $reason = '', $max_bookings = null) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'easy_bookinger_special_availability';
        
        return $wpdb->insert($table, array(
            'availability_date' => sanitize_text_field($date),
            'is_available' => (int)$is_available,
            'reason' => sanitize_text_field($reason),
            'max_bookings' => $max_bookings ? (int)$max_bookings : null
        ));
    }
    
    /**
     * Update special availability
     */
    public function update_special_availability($id, $data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'easy_bookinger_special_availability';
        
        $update_data = array();
        
        if (isset($data['availability_date'])) {
            $update_data['availability_date'] = sanitize_text_field($data['availability_date']);
        }
        
        if (isset($data['is_available'])) {
            $update_data['is_available'] = (int)$data['is_available'];
        }
        
        if (isset($data['reason'])) {
            $update_data['reason'] = sanitize_text_field($data['reason']);
        }
        
        if (isset($data['max_bookings'])) {
            $update_data['max_bookings'] = $data['max_bookings'] ? (int)$data['max_bookings'] : null;
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        return $wpdb->update($table, $update_data, array('id' => (int)$id));
    }
    
    /**
     * Remove special availability
     */
    public function remove_special_availability($date) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'easy_bookinger_special_availability';
        return $wpdb->delete($table, array('availability_date' => $date));
    }
    
    /**
     * Check if date has special availability
     */
    public function is_date_special_available($date) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'easy_bookinger_special_availability';
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE availability_date = %s",
            $date
        ));
        
        return $result && $result->is_available;
    }
    
    /**
     * Admin Emails Methods
     */
    
    /**
     * Get active admin emails
     */
    public function get_active_admin_emails() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'easy_bookinger_admin_emails';
        return $wpdb->get_results(
            "SELECT * FROM $table WHERE is_active = 1 ORDER BY id"
        );
    }
    
    /**
     * Get all admin emails
     */
    public function get_admin_emails() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'easy_bookinger_admin_emails';
        return $wpdb->get_results(
            "SELECT * FROM $table ORDER BY id"
        );
    }
    
    /**
     * Add admin email
     */
    public function add_admin_email($email_address, $notification_types = array()) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'easy_bookinger_admin_emails';
        
        return $wpdb->insert($table, array(
            'email_address' => sanitize_email($email_address),
            'notification_types' => maybe_serialize($notification_types),
            'is_active' => 1
        ));
    }
    
    /**
     * Update admin email
     */
    public function update_admin_email($id, $data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'easy_bookinger_admin_emails';
        
        $update_data = array();
        
        if (isset($data['email_address'])) {
            $update_data['email_address'] = sanitize_email($data['email_address']);
        }
        
        if (isset($data['notification_types'])) {
            $update_data['notification_types'] = maybe_serialize($data['notification_types']);
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
     * Delete admin email
     */
    public function delete_admin_email($id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'easy_bookinger_admin_emails';
        return $wpdb->delete($table, array('id' => (int)$id));
    }
}