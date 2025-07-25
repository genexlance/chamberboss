<?php
namespace Chamberboss\Core;

/**
 * Database Handler for Custom Tables
 */
class Database extends BaseClass {
    
    /**
     * Database version
     * @var string
     */
    private $db_version = '1.0.0';
    
    /**
     * Initialize database
     */
    protected function init() {
        add_action('plugins_loaded', [$this, 'check_database_version']);
    }
    
    /**
     * Check database version and update if needed
     */
    public function check_database_version() {
        $installed_version = get_option('chamberboss_db_version', '0.0.0');
        
        if (version_compare($installed_version, $this->db_version, '<')) {
            $this->create_tables();
            update_option('chamberboss_db_version', $this->db_version);
        }
    }
    
    /**
     * Create custom database tables
     */
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Membership transactions table
        $transactions_table = $wpdb->prefix . 'chamberboss_transactions';
        $transactions_sql = "CREATE TABLE $transactions_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            member_id bigint(20) unsigned NOT NULL,
            stripe_payment_intent_id varchar(255) DEFAULT NULL,
            stripe_subscription_id varchar(255) DEFAULT NULL,
            amount decimal(10,2) NOT NULL,
            currency varchar(3) NOT NULL DEFAULT 'USD',
            status varchar(50) NOT NULL DEFAULT 'pending',
            transaction_type varchar(50) NOT NULL DEFAULT 'subscription',
            metadata longtext DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY member_id (member_id),
            KEY stripe_payment_intent_id (stripe_payment_intent_id),
            KEY stripe_subscription_id (stripe_subscription_id),
            KEY status (status),
            KEY transaction_type (transaction_type),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Member subscriptions table
        $subscriptions_table = $wpdb->prefix . 'chamberboss_subscriptions';
        $subscriptions_sql = "CREATE TABLE $subscriptions_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            member_id bigint(20) unsigned NOT NULL,
            stripe_customer_id varchar(255) DEFAULT NULL,
            stripe_subscription_id varchar(255) DEFAULT NULL,
            status varchar(50) NOT NULL DEFAULT 'inactive',
            plan_name varchar(100) NOT NULL DEFAULT 'basic',
            amount decimal(10,2) NOT NULL,
            currency varchar(3) NOT NULL DEFAULT 'USD',
            billing_cycle varchar(20) NOT NULL DEFAULT 'yearly',
            start_date datetime DEFAULT NULL,
            end_date datetime DEFAULT NULL,
            next_billing_date datetime DEFAULT NULL,
            cancelled_at datetime DEFAULT NULL,
            metadata longtext DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY member_id (member_id),
            KEY stripe_customer_id (stripe_customer_id),
            KEY stripe_subscription_id (stripe_subscription_id),
            KEY status (status),
            KEY end_date (end_date),
            KEY next_billing_date (next_billing_date)
        ) $charset_collate;";
        
        // Email notifications log table
        $notifications_table = $wpdb->prefix . 'chamberboss_notifications';
        $notifications_sql = "CREATE TABLE $notifications_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            member_id bigint(20) unsigned NOT NULL,
            notification_type varchar(50) NOT NULL,
            subject varchar(255) NOT NULL,
            message longtext NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            scheduled_at datetime DEFAULT NULL,
            sent_at datetime DEFAULT NULL,
            metadata longtext DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY member_id (member_id),
            KEY notification_type (notification_type),
            KEY status (status),
            KEY scheduled_at (scheduled_at),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // MailPoet integration log table
        $mailpoet_log_table = $wpdb->prefix . 'chamberboss_mailpoet_log';
        $mailpoet_log_sql = "CREATE TABLE $mailpoet_log_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            member_id bigint(20) unsigned NOT NULL,
            mailpoet_subscriber_id bigint(20) unsigned DEFAULT NULL,
            list_id bigint(20) unsigned DEFAULT NULL,
            action varchar(50) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            error_message text DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY member_id (member_id),
            KEY mailpoet_subscriber_id (mailpoet_subscriber_id),
            KEY list_id (list_id),
            KEY action (action),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Listing categories table
        $categories_table = $wpdb->prefix . 'chamberboss_listing_categories';
        $categories_sql = "CREATE TABLE $categories_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            slug varchar(100) NOT NULL,
            description text DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($transactions_sql);
        dbDelta($subscriptions_sql);
        dbDelta($notifications_sql);
        dbDelta($mailpoet_log_sql);
        dbDelta($categories_sql);
        
        $this->log('Database tables created successfully');
    }
    
    /**
     * Get member subscription
     * @param int $member_id
     * @return object|null
     */
    public function get_member_subscription($member_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'chamberboss_subscriptions';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE member_id = %d",
            $member_id
        ));
    }
    
    /**
     * Create or update member subscription
     * @param int $member_id
     * @param array $data
     * @return bool|int
     */
    public function upsert_member_subscription($member_id, $data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'chamberboss_subscriptions';
        
        $existing = $this->get_member_subscription($member_id);
        
        $data['member_id'] = $member_id;
        $data['updated_at'] = current_time('mysql');
        
        if ($existing) {
            return $wpdb->update($table, $data, ['member_id' => $member_id]);
        } else {
            $data['created_at'] = current_time('mysql');
            return $wpdb->insert($table, $data);
        }
    }
    
    /**
     * Add transaction record
     * @param array $data
     * @return bool|int
     */
    public function add_transaction($data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'chamberboss_transactions';
        
        $data['created_at'] = current_time('mysql');
        $data['updated_at'] = current_time('mysql');
        
        return $wpdb->insert($table, $data);
    }
    
    /**
     * Update transaction status
     * @param int $transaction_id
     * @param string $status
     * @param array $metadata
     * @return bool|int
     */
    public function update_transaction_status($transaction_id, $status, $metadata = []) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'chamberboss_transactions';
        
        $data = [
            'status' => $status,
            'updated_at' => current_time('mysql')
        ];
        
        if (!empty($metadata)) {
            $data['metadata'] = json_encode($metadata);
        }
        
        return $wpdb->update($table, $data, ['id' => $transaction_id]);
    }
    
    /**
     * Get member transactions
     * @param int $member_id
     * @param int $limit
     * @return array
     */
    public function get_member_transactions($member_id, $limit = 10) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'chamberboss_transactions';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE member_id = %d ORDER BY created_at DESC LIMIT %d",
            $member_id,
            $limit
        ));
    }
    
    /**
     * Add notification
     * @param array $data
     * @return bool|int
     */
    public function add_notification($data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'chamberboss_notifications';
        
        $data['created_at'] = current_time('mysql');
        
        return $wpdb->insert($table, $data);
    }
    
    /**
     * Get pending notifications
     * @param int $limit
     * @return array
     */
    public function get_pending_notifications($limit = 50) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'chamberboss_notifications';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table 
             WHERE status = 'pending' 
             AND (scheduled_at IS NULL OR scheduled_at <= %s)
             ORDER BY created_at ASC 
             LIMIT %d",
            current_time('mysql'),
            $limit
        ));
    }
    
    /**
     * Update notification status
     * @param int $notification_id
     * @param string $status
     * @return bool|int
     */
    public function update_notification_status($notification_id, $status) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'chamberboss_notifications';
        
        $data = ['status' => $status];
        
        if ($status === 'sent') {
            $data['sent_at'] = current_time('mysql');
        }
        
        return $wpdb->update($table, $data, ['id' => $notification_id]);
    }
    
    /**
     * Log MailPoet action
     * @param array $data
     * @return bool|int
     */
    public function log_mailpoet_action($data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'chamberboss_mailpoet_log';
        
        $data['created_at'] = current_time('mysql');
        
        return $wpdb->insert($table, $data);
    }
    
    /**
     * Get expiring subscriptions
     * @param int $days_ahead
     * @return array
     */
    public function get_expiring_subscriptions($days_ahead = 30) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'chamberboss_subscriptions';
        $future_date = date('Y-m-d H:i:s', strtotime("+{$days_ahead} days"));
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table 
             WHERE status = 'active' 
             AND end_date <= %s 
             AND end_date > %s
             ORDER BY end_date ASC",
            $future_date,
            current_time('mysql')
        ));
    }
    
    /**
     * Get expired subscriptions
     * @return array
     */
    public function get_expired_subscriptions() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'chamberboss_subscriptions';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table 
             WHERE status = 'active' 
             AND end_date < %s
             ORDER BY end_date ASC",
            current_time('mysql')
        ));
    }
    
    /**
     * Static method for activation hook
     */
    public static function on_activation_create_tables() {
        $instance = new self();
        $instance->create_tables();
    }

    /**
     * Get all listing categories
     * @return array
     */
    public function get_listing_categories() {
        global $wpdb;
        $table = $wpdb->prefix . 'chamberboss_listing_categories';
        return $wpdb->get_results("SELECT * FROM $table ORDER BY name ASC");
    }

    /**
     * Add a new listing category
     * @param array $data
     * @return bool|int
     */
    public function add_listing_category($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'chamberboss_listing_categories';
        return $wpdb->insert($table, $data);
    }

    /**
     * Update a listing category
     * @param int $id
     * @param array $data
     * @return bool|int
     */
    public function update_listing_category($id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'chamberboss_listing_categories';
        return $wpdb->update($table, $data, ['id' => $id]);
    }

    /**
     * Delete a listing category
     * @param int $id
     * @return bool|int
     */
    public function delete_listing_category($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'chamberboss_listing_categories';
        return $wpdb->delete($table, ['id' => $id]);
    }
}

