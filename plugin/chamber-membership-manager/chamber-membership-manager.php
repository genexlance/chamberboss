<?php
/**
 * Plugin Name: Chamber Boss
 * Plugin URI: https://chamber-membership-manager.com
 * Description: A WordPress plugin for Chambers of Commerce to manage memberships, process payments via Stripe, and maintain a business directory.
 * Version: 1.0.0
 * Author: Genex Marketing Agency Ltd.
 * Author URI: https://genexmarketing.com
 * Text Domain: chamber-boss
 * Domain Path: /languages
 * License: GPL v2 or later
 * 
 * Assets:
 * Banner-1200x600: assets/banner-1200x600.svg
 * Icon: assets/icon.svg
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('CB_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('CB_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CB_PLUGIN_VERSION', '1.0.0');

// Include Stripe library if it exists
if (file_exists(CB_PLUGIN_PATH . 'vendor/autoload.php')) {
    require_once CB_PLUGIN_PATH . 'vendor/autoload.php';
}

// Include webhook handler
require_once CB_PLUGIN_PATH . 'includes/stripe-webhook-handler.php';

// Include membership renewal notifications
require_once CB_PLUGIN_PATH . 'includes/membership-renewal-notifications.php';

// Include security utilities
require_once CB_PLUGIN_PATH . 'includes/security.php';

/**
 * Chamber Boss Plugin Class
 */
class Chamber_Boss {
    
    /**
     * Instance of the class
     * @var Chamber_Boss
     */
    private static $instance = null;
    
    /**
     * Initialize the plugin
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Hook into WordPress
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'settings_init'));
        add_shortcode('chamber-directory', array($this, 'business_directory_shortcode'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // MailPoet integration
        add_action('user_register', array($this, 'add_user_to_mailpoet_list'));
        add_action('profile_update', array($this, 'update_user_in_mailpoet_list'));
    }
    
    /**
     * Initialize the plugin
     */
    public function init() {
        // Load text domain for translations
        load_plugin_textdomain('chamber-boss', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Register custom post types
        $this->register_post_types();
        
        // Register custom roles
        $this->register_roles();
        
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * Activation hook
     */
    public function activate() {
        // Create database tables
        $this->create_tables();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Schedule daily check for expiring memberships
        if (!wp_next_scheduled('cb_check_membership_renewals')) {
            wp_schedule_event(time(), 'daily', 'cb_check_membership_renewals');
        }
    }
    
    /**
     * Deactivation hook
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Clear scheduled event
        wp_clear_scheduled_hook('cb_check_membership_renewals');
    }
    
    /**
     * Register custom post types
     */
    public function register_post_types() {
        // Business Listings CPT
        $labels = array(
            'name'                  => _x('Business Listings', 'Post type general name', 'chamber-boss'),
            'singular_name'         => _x('Business Listing', 'Post type singular name', 'chamber-boss'),
            'menu_name'             => _x('Business Listings', 'Admin Menu text', 'chamber-boss'),
            'name_admin_bar'        => _x('Business Listing', 'Add New on Toolbar', 'chamber-boss'),
            'add_new'               => __('Add New', 'chamber-boss'),
            'add_new_item'          => __('Add New Business Listing', 'chamber-boss'),
            'new_item'              => __('New Business Listing', 'chamber-boss'),
            'edit_item'             => __('Edit Business Listing', 'chamber-boss'),
            'view_item'             => __('View Business Listing', 'chamber-boss'),
            'all_items'             => __('All Business Listings', 'chamber-boss'),
            'search_items'          => __('Search Business Listings', 'chamber-boss'),
            'parent_item_colon'     => __('Parent Business Listings:', 'chamber-boss'),
            'not_found'             => __('No business listings found.', 'chamber-boss'),
            'not_found_in_trash'    => __('No business listings found in Trash.', 'chamber-boss'),
            'featured_image'        => _x('Business Logo', 'Overrides the "Featured Image" phrase', 'chamber-boss'),
            'set_featured_image'    => _x('Set business logo', 'Overrides the "Set featured image" phrase', 'chamber-boss'),
            'remove_featured_image' => _x('Remove business logo', 'Overrides the "Remove featured image" phrase', 'chamber-boss'),
            'use_featured_image'    => _x('Use as business logo', 'Overrides the "Use as featured image" phrase', 'chamber-boss'),
            'archives'              => _x('Business listing archives', 'The post type archive label used in nav menus', 'chamber-boss'),
            'insert_into_item'      => _x('Insert into business listing', 'Overrides the "Insert into post" phrase', 'chamber-boss'),
            'uploaded_to_this_item' => _x('Uploaded to this business listing', 'Overrides the "Uploaded to this post" phrase', 'chamber-boss'),
            'filter_items_list'     => _x('Filter business listings list', 'Screen reader text for the filter links', 'chamber-boss'),
            'items_list_navigation' => _x('Business listings list navigation', 'Screen reader text for the pagination', 'chamber-boss'),
            'items_list'            => _x('Business listings list', 'Screen reader text for the items list', 'chamber-boss'),
        );
        
        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array('slug' => 'business-listing'),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => null,
            'supports'           => array('title', 'editor', 'author', 'thumbnail', 'excerpt', 'custom-fields'),
            'show_in_rest'       => true,
        );
        
        register_post_type('business_listing', $args);
    }
    
    /**
     * Register custom roles
     */
    public function register_roles() {
        // Add 'Chamber Member' role
        add_role(
            'chamber_member',
            'Chamber Member',
            array(
                'read' => true,
                'edit_posts' => true,
                'delete_posts' => true,
                'publish_posts' => true,
                'upload_files' => true,
            )
        );
    }
    
    /**
     * Create database tables
     */
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Membership table
        $table_name = $wpdb->prefix . 'cb_memberships';
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id mediumint(9) NOT NULL,
            membership_type varchar(50) NOT NULL,
            start_date datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            end_date datetime DEFAULT NULL,
            status varchar(20) DEFAULT 'active' NOT NULL,
            stripe_customer_id varchar(255) DEFAULT NULL,
            stripe_subscription_id varchar(255) DEFAULT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        wp_enqueue_style('cb-styles', CB_PLUGIN_URL . 'assets/css/styles.css', array(), CB_PLUGIN_VERSION);
        wp_enqueue_script('cb-scripts', CB_PLUGIN_URL . 'assets/js/scripts.js', array('jquery'), CB_PLUGIN_VERSION, true);
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            'Chamber Boss',
            'Chamber Members',
            'manage_options',
            'chamber-boss',
            array($this, 'admin_page'),
            'dashicons-businessman',
            25
        );
        
        add_submenu_page(
            'chamber-boss',
            'Settings',
            'Settings',
            'manage_options',
            'chamber-boss-settings',
            array($this, 'settings_page')
        );
    }
    
    /**
     * Initialize settings
     */
    public function settings_init() {
        register_setting('cb_settings', 'cb_stripe_publishable_key');
        register_setting('cb_settings', 'cb_stripe_secret_key');
        register_setting('cb_settings', 'cb_stripe_webhook_secret');
        register_setting('cb_settings', 'cb_test_mode');
        register_setting('cb_settings', 'cb_mailpoet_members_list_id');
        register_setting('cb_settings', 'cb_mailpoet_nonmembers_list_id');
        
        add_settings_section(
            'cb_settings_section',
            'Stripe Settings',
            array($this, 'settings_section_callback'),
            'cb_settings'
        );
        
        add_settings_field(
            'cb_stripe_publishable_key',
            'Stripe Publishable Key',
            array($this, 'stripe_publishable_key_render'),
            'cb_settings',
            'cb_settings_section'
        );
        
        add_settings_field(
            'cb_stripe_secret_key',
            'Stripe Secret Key',
            array($this, 'stripe_secret_key_render'),
            'cb_settings',
            'cb_settings_section'
        );
        
        add_settings_field(
            'cb_stripe_webhook_secret',
            'Stripe Webhook Secret',
            array($this, 'stripe_webhook_secret_render'),
            'cb_settings',
            'cb_settings_section'
        );
        
        add_settings_field(
            'cb_test_mode',
            'Test Mode',
            array($this, 'test_mode_render'),
            'cb_settings',
            'cb_settings_section'
        );
        
        // MailPoet settings section
        add_settings_section(
            'cb_mailpoet_settings_section',
            'MailPoet Settings',
            array($this, 'mailpoet_settings_section_callback'),
            'cb_settings'
        );
        
        add_settings_field(
            'cb_mailpoet_members_list_id',
            'Members List ID',
            array($this, 'mailpoet_members_list_id_render'),
            'cb_settings',
            'cb_mailpoet_settings_section'
        );
        
        add_settings_field(
            'cb_mailpoet_nonmembers_list_id',
            'Non-Members List ID',
            array($this, 'mailpoet_nonmembers_list_id_render'),
            'cb_settings',
            'cb_mailpoet_settings_section'
        );
    }
    
    /**
     * Settings section callback
     */
    public function settings_section_callback() {
        echo '<p>Enter your Stripe API keys to enable payment processing.</p>';
        echo '<p>Your webhook URL is: <code>' . site_url('?cb-stripe-webhook=1') . '</code></p>';
    }
    
    /**
     * MailPoet settings section callback
     */
    public function mailpoet_settings_section_callback() {
        echo '<p>Configure your MailPoet integration settings.</p>';
        if (!class_exists(\MailPoet\API\API::class)) {
            echo '<p class="description" style="color: #dc3232;">MailPoet plugin is not active. Please install and activate the MailPoet plugin to use these features.</p>';
        }
    }
    
    /**
     * Render publishable key field
     */
    public function stripe_publishable_key_render() {
        $value = CB_Security::get_secure_option('cb_stripe_publishable_key');
        echo '<input type="text" name="cb_stripe_publishable_key" value="' . esc_attr($value) . '" class="regular-text">';
    }
    
    /**
     * Render secret key field
     */
    public function stripe_secret_key_render() {
        $value = CB_Security::get_secure_option('cb_stripe_secret_key');
        echo '<input type="password" name="cb_stripe_secret_key" value="' . esc_attr($value) . '" class="regular-text">';
    }
    
    /**
     * Render webhook secret field
     */
    public function stripe_webhook_secret_render() {
        $value = CB_Security::get_secure_option('cb_stripe_webhook_secret');
        echo '<input type="password" name="cb_stripe_webhook_secret" value="' . esc_attr($value) . '" class="regular-text">';
        echo '<p class="description">Enter your Stripe webhook secret. You can find this in your Stripe Dashboard under Developers > Webhooks.</p>';
    }
    
    /**
     * Render test mode field
     */
    public function test_mode_render() {
        $value = get_option('cb_test_mode');
        echo '<label><input type="checkbox" name="cb_test_mode" value="1" ' . checked(1, $value, false) . '> Enable test mode</label>';
        echo '<p class="description">Check this box to use Stripe test keys and mode.</p>';
    }
    
    /**
     * Render MailPoet members list ID field
     */
    public function mailpoet_members_list_id_render() {
        $value = get_option('cb_mailpoet_members_list_id');
        echo '<input type="text" name="cb_mailpoet_members_list_id" value="' . esc_attr($value) . '" class="regular-text">';
        echo '<p class="description">Enter the ID of the MailPoet list for members.</p>';
    }
    
    /**
     * Render MailPoet non-members list ID field
     */
    public function mailpoet_nonmembers_list_id_render() {
        $value = get_option('cb_mailpoet_nonmembers_list_id');
        echo '<input type="text" name="cb_mailpoet_nonmembers_list_id" value="' . esc_attr($value) . '" class="regular-text">';
        echo '<p class="description">Enter the ID of the MailPoet list for non-members.</p>';
    }
    
    /**
     * Admin page callback
     */
    public function admin_page() {
        echo '<div class="wrap">';
        echo '<h1>Chamber Boss</h1>';
        echo '<p>Manage your chamber memberships and business listings.</p>';
        echo '<h2>Quick Stats</h2>';
        echo '<ul>';
        echo '<li>Total Members: 0</li>';
        echo '<li>Active Memberships: 0</li>';
        echo '<li>Pending Listings: 0</li>';
        echo '</ul>';
        echo '</div>';
    }
    
    /**
     * Settings page callback
     */
    public function settings_page() {
        ?>
        <div class="wrap">
            <h1>Chamber Boss Settings</h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('cb_settings');
                do_settings_sections('cb_settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Business directory shortcode
     */
    public function business_directory_shortcode($atts) {
        ob_start();
        ?>
        <div class="cb-directory">
            <div class="cb-search">
                <form method="get">
                    <input type="text" name="search" placeholder="Search businesses..." value="<?php echo isset($_GET['search']) ? esc_attr($_GET['search']) : ''; ?>">
                    <button type="submit">Search</button>
                </form>
            </div>
            
            <div class="cb-listings">
                <?php
                $args = array(
                    'post_type' => 'business_listing',
                    'post_status' => 'publish',
                    'posts_per_page' => 10,
                );
                
                if (isset($_GET['search']) && !empty($_GET['search'])) {
                    $args['s'] = sanitize_text_field($_GET['search']);
                }
                
                $listings = new WP_Query($args);
                
                if ($listings->have_posts()) {
                    while ($listings->have_posts()) {
                        $listings->the_post();
                        ?>
                        <div class="cb-listing">
                            <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                            <?php if (has_post_thumbnail()) : ?>
                                <div class="cb-listing-image">
                                    <?php the_post_thumbnail('thumbnail'); ?>
                                </div>
                            <?php endif; ?>
                            <div class="cb-listing-content">
                                <?php the_excerpt(); ?>
                            </div>
                        </div>
                        <?php
                    }
                    wp_reset_postdata();
                } else {
                    echo '<p>No business listings found.</p>';
                }
                ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Add user to MailPoet list when they register
     */
    public function add_user_to_mailpoet_list($user_id) {
        // Check if MailPoet is active
        if (!class_exists(\MailPoet\API\API::class)) {
            return;
        }
        
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            return;
        }
        
        // Check if user has a membership
        $membership = $this->get_user_membership($user_id);
        
        try {
            $mailpoet_api = \MailPoet\API\API::MP('v1');
            
            if ($membership) {
                // Add to members list
                $members_list_id = get_option('cb_mailpoet_members_list_id');
                if ($members_list_id) {
                    $mailpoet_api->subscribe($user->user_email, array($members_list_id));
                }
            } else {
                // Add to non-members list
                $nonmembers_list_id = get_option('cb_mailpoet_nonmembers_list_id');
                if ($nonmembers_list_id) {
                    $mailpoet_api->subscribe($user->user_email, array($nonmembers_list_id));
                }
            }
        } catch (Exception $e) {
            // Log error
            error_log('Chamber Boss MailPoet integration error: ' . $e->getMessage());
        }
    }
    
    /**
     * Update user in MailPoet list when their profile is updated
     */
    public function update_user_in_mailpoet_list($user_id) {
        // Check if MailPoet is active
        if (!class_exists(\MailPoet\API\API::class)) {
            return;
        }
        
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            return;
        }
        
        // Check if user has a membership
        $membership = $this->get_user_membership($user_id);
        
        try {
            $mailpoet_api = \MailPoet\API\API::MP('v1');
            
            $members_list_id = get_option('cb_mailpoet_members_list_id');
            $nonmembers_list_id = get_option('cb_mailpoet_nonmembers_list_id');
            
            if ($membership) {
                // Add to members list, remove from non-members list
                if ($members_list_id) {
                    $mailpoet_api->subscribe($user->user_email, array($members_list_id));
                }
                if ($nonmembers_list_id) {
                    $mailpoet_api->unsubscribe($user->user_email, array($nonmembers_list_id));
                }
            } else {
                // Add to non-members list, remove from members list
                if ($nonmembers_list_id) {
                    $mailpoet_api->subscribe($user->user_email, array($nonmembers_list_id));
                }
                if ($members_list_id) {
                    $mailpoet_api->unsubscribe($user->user_email, array($members_list_id));
                }
            }
        } catch (Exception $e) {
            // Log error
            error_log('Chamber Boss MailPoet integration error: ' . $e->getMessage());
        }
    }
    
    /**
     * Get user membership
     */
    private function get_user_membership($user_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cb_memberships';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE user_id = %d AND status = 'active'", $user_id));
    }
}

// Initialize the plugin
Chamber_Boss::get_instance();

// Hook to save encrypted options
add_action('update_option_cb_stripe_publishable_key', function($old_value, $value) {
    CB_Security::update_secure_option('cb_stripe_publishable_key', $value);
}, 10, 2);

add_action('update_option_cb_stripe_secret_key', function($old_value, $value) {
    CB_Security::update_secure_option('cb_stripe_secret_key', $value);
}, 10, 2);

add_action('update_option_cb_stripe_webhook_secret', function($old_value, $value) {
    CB_Security::update_secure_option('cb_stripe_webhook_secret', $value);
}, 10, 2);