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
        add_shortcode('chamber-signup', array($this, 'member_signup_shortcode'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // MailPoet integration
        add_action('user_register', array($this, 'add_user_to_mailpoet_list'));
        add_action('profile_update', array($this, 'update_user_in_mailpoet_list'));
        
        // Add meta boxes for business listings
        add_action('add_meta_boxes', array($this, 'add_business_listing_meta_boxes'));
        add_action('save_post', array($this, 'save_business_listing_meta'));
        
        // Handle member signup form
        add_action('wp_ajax_cb_member_signup', array($this, 'handle_member_signup'));
        add_action('wp_ajax_nopriv_cb_member_signup', array($this, 'handle_member_signup'));
        
        // Add admin page for manual member management
        add_action('admin_menu', array($this, 'add_member_management_page'));
        add_shortcode('chamber-featured-listings', array($this, 'featured_business_listings_shortcode'));
    }
    
    /**
     * Initialize the plugin
     */
    public function init() {
        // Load text domain for translations
        load_plugin_textdomain('chamber-boss', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Register custom post types
        $this->register_post_types();
        
        // Register custom taxonomies
        $this->register_taxonomies();
        
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
            'supports'           => array('title', 'editor', 'author', 'thumbnail', 'excerpt', 'custom-fields', 'comments'),
            'show_in_rest'       => true,
            'taxonomies'         => array('business_category'), // Add taxonomy support
        );
        
        register_post_type('business_listing', $args);
    }
    
    /**
     * Register custom taxonomies
     */
    public function register_taxonomies() {
        // Business Categories
        $labels = array(
            'name'              => _x('Business Categories', 'taxonomy general name', 'chamber-boss'),
            'singular_name'     => _x('Business Category', 'taxonomy singular name', 'chamber-boss'),
            'search_items'      => __('Search Categories', 'chamber-boss'),
            'all_items'         => __('All Categories', 'chamber-boss'),
            'parent_item'       => __('Parent Category', 'chamber-boss'),
            'parent_item_colon' => __('Parent Category:', 'chamber-boss'),
            'edit_item'         => __('Edit Category', 'chamber-boss'),
            'update_item'       => __('Update Category', 'chamber-boss'),
            'add_new_item'      => __('Add New Category', 'chamber-boss'),
            'new_item_name'     => __('New Category Name', 'chamber-boss'),
            'menu_name'         => __('Categories', 'chamber-boss'),
        );
        
        $args = array(
            'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array('slug' => 'business-category'),
            'show_in_rest'      => true, // Enable Gutenberg support
        );
        
        register_taxonomy('business_category', array('business_listing'), $args);

        // Flush rewrite rules on taxonomy registration. This is crucial for custom taxonomies to appear.
        // We only want to do this once on plugin activation or update.
        if (!get_option('cb_business_category_flushed')) {
            flush_rewrite_rules();
            update_option('cb_business_category_flushed', true);
        }
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
        
        // Localize script for AJAX
        wp_localize_script('cb-scripts', 'cb_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cb_member_signup')
        ));
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
     * Add member management page
     */
    public function add_member_management_page() {
        add_submenu_page(
            'chamber-boss',
            'Manage Members',
            'Manage Members',
            'manage_options',
            'chamber-boss-members',
            array($this, 'member_management_page')
        );
    }
    
    /**
     * Member management page
     */
    public function member_management_page() {
        // Handle form submission
        if (isset($_POST['cb_add_member']) && wp_verify_nonce($_POST['cb_member_nonce'], 'cb_add_member')) {
            $this->add_member_manually($_POST);
        }
        
        ?>
        <div class="wrap">
            <h1>Manage Members</h1>
            
            <h2>Add New Member</h2>
            <form method="post">
                <?php wp_nonce_field('cb_add_member', 'cb_member_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th><label for="first_name">First Name</label></th>
                        <td><input type="text" id="first_name" name="first_name" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th><label for="last_name">Last Name</label></th>
                        <td><input type="text" id="last_name" name="last_name" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th><label for="email">Email</label></th>
                        <td><input type="email" id="email" name="email" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th><label for="business_name">Business Name</label></th>
                        <td><input type="text" id="business_name" name="business_name" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th><label for="membership_type">Membership Type</label></th>
                        <td>
                            <select id="membership_type" name="membership_type">
                                <option value="basic">Basic</option>
                                <option value="premium">Premium</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="status">Status</label></th>
                        <td>
                            <select id="status" name="status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('Add Member', 'primary', 'cb_add_member'); ?>
            </form>
            
            <h2>Current Members</h2>
            <?php
            // Display members table
            $users = get_users(array('role' => 'chamber_member'));
            if (!empty($users)) {
                echo '<table class="widefat">';
                echo '<thead><tr><th>Name</th><th>Email</th><th>Business</th><th>Status</th></tr></thead>';
                echo '<tbody>';
                foreach ($users as $user) {
                    $business = get_page_by_title($user->business_name ?? '', OBJECT, 'business_listing');
                    echo '<tr>';
                    echo '<td>' . esc_html($user->first_name . ' ' . $user->last_name) . '</td>';
                    echo '<td>' . esc_html($user->user_email) . '</td>';
                    echo '<td>' . ($business ? '<a href="' . get_edit_post_link($business->ID) . '">' . esc_html($user->business_name) . '</a>' : 'N/A') . '</td>';
                    echo '<td>Active</td>';
                    echo '</tr>';
                }
                echo '</tbody>';
                echo '</table>';
            } else {
                echo '<p>No members found.</p>';
            }
            ?>
        </div>
        <?php
    }
    
    /**
     * Add member manually
     */
    private function add_member_manually($data) {
        // Create user
        $userdata = array(
            'user_login'  => $data['email'],
            'user_email'  => $data['email'],
            'user_pass'   => wp_generate_password(),
            'first_name'  => $data['first_name'],
            'last_name'   => $data['last_name'],
            'role'        => 'chamber_member'
        );
        
        $user_id = wp_insert_user($userdata);
        
        if (is_wp_error($user_id)) {
            add_action('admin_notices', function() use ($user_id) {
                echo '<div class="notice notice-error"><p>Error creating user: ' . $user_id->get_error_message() . '</p></div>';
            });
            return;
        }
        
        // Update user meta
        update_user_meta($user_id, 'business_name', $data['business_name']);
        
        // Create membership record
        global $wpdb;
        $table_name = $wpdb->prefix . 'cb_memberships';
        
        $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'membership_type' => $data['membership_type'],
                'status' => $data['status']
            )
        );
        
        // Create business listing
        $post_id = wp_insert_post(array(
            'post_title' => $data['business_name'],
            'post_content' => '',
            'post_status' => 'publish',
            'post_author' => $user_id,
            'post_type' => 'business_listing'
        ));
        
        if (!is_wp_error($post_id)) {
            // Set business name as user meta
            update_user_meta($user_id, 'business_name', $data['business_name']);
        }
        
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success"><p>Member added successfully!</p></div>';
        });
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
        echo '<li>Total Members: ' . count(get_users(array('role' => 'chamber_member'))) . '</li>';
        echo '<li>Active Memberships: ' . $this->get_active_membership_count() . '</li>';
        echo '<li>Business Listings: ' . wp_count_posts('business_listing')->publish . '</li>';
        echo '</ul>';
        echo '</div>';
    }
    
    /**
     * Get active membership count
     */
    private function get_active_membership_count() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cb_memberships';
        return $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'active'");
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
     * Add meta boxes for business listings
     */
    public function add_business_listing_meta_boxes() {
        add_meta_box(
            'business_listing_details',
            'Business Details',
            array($this, 'business_listing_meta_box_callback'),
            'business_listing',
            'normal',
            'high'
        );
    }
    
    /**
     * Business listing meta box callback
     */
    public function business_listing_meta_box_callback($post) {
        // Add nonce for security
        wp_nonce_field('save_business_listing_meta', 'business_listing_meta_nonce');
        
        // Get current values
        $phone = get_post_meta($post->ID, '_business_phone', true);
        $website = get_post_meta($post->ID, '_business_website', true);
        $address = get_post_meta($post->ID, '_business_address', true);
        $is_featured = get_post_meta($post->ID, '_is_featured', true);
        
        ?>
        <table class="form-table">
            <tr>
                <th><label for="business_phone">Phone Number</label></th>
                <td><input type="text" id="business_phone" name="business_phone" value="<?php echo esc_attr($phone); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="business_website">Website</label></th>
                <td><input type="url" id="business_website" name="business_website" value="<?php echo esc_attr($website); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="business_address">Address</label></th>
                <td><textarea id="business_address" name="business_address" rows="3" class="large-text"><?php echo esc_textarea($address); ?></textarea></td>
            </tr>
            <tr>
                <th><label for="is_featured">Featured Listing</label></th>
                <td><input type="checkbox" id="is_featured" name="is_featured" value="1" <?php checked(1, $is_featured); ?> />
                <p class="description">Check this box to mark this business as a featured listing.</p></td>
            </tr>
        </table>

        <?php
    }

    /**
     * Save business listing meta
     */
    public function save_business_listing_meta($post_id) {
        // Check if nonce is valid
        if (!isset($_POST['business_listing_meta_nonce']) || !wp_verify_nonce($_POST['business_listing_meta_nonce'], 'save_business_listing_meta')) {
            return;
        }
        
        // Check if user has permission to edit
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Save phone number
        if (isset($_POST['business_phone'])) {
            update_post_meta($post_id, '_business_phone', sanitize_text_field($_POST['business_phone']));
        }
        
        // Save website
        if (isset($_POST['business_website'])) {
            update_post_meta($post_id, '_business_website', esc_url_raw($_POST['business_website']));
        }
        
        // Save address
        if (isset($_POST['business_address'])) {
            update_post_meta($post_id, '_business_address', sanitize_textarea_field($_POST['business_address']));
        }

        // Save featured status
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        update_post_meta($post_id, '_is_featured', $is_featured);

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
            
            <div class="cb-categories">
                <h3>Business Categories</h3>
                <ul>
                    <?php
                    $categories = get_terms(array(
                        'taxonomy' => 'business_category',
                        'hide_empty' => true,
                    ));
                    
                    // Add "All" category
                    $current_category = isset($_GET['category']) ? $_GET['category'] : '';
                    $all_class = ($current_category == '') ? ' class="active"' : '';
                    echo '<li><a href="?' . remove_query_arg('category') . '"' . $all_class . '>All</a></li>';
                    
                    foreach ($categories as $category) {
                        $class = ($current_category == $category->slug) ? ' class="active"' : '';
                        echo '<li><a href="?' . add_query_arg('category', $category->slug) . '"' . $class . '>' . $category->name . '</a></li>';
                    }
                    ?>
                </ul>
            </div>
            
            <div class="cb-listings">
                <?php
                $args = array(
                    'post_type' => 'business_listing',
                    'post_status' => 'publish',
                    'posts_per_page' => 10,
                );
                
                // Add search filter
                if (isset($_GET['search']) && !empty($_GET['search'])) {
                    $args['s'] = sanitize_text_field($_GET['search']);
                }
                
                // Add category filter
                if (isset($_GET['category']) && !empty($_GET['category'])) {
                    $args['tax_query'] = array(
                        array(
                            'taxonomy' => 'business_category',
                            'field'    => 'slug',
                            'terms'    => sanitize_text_field($_GET['category']),
                        ),
                    );
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
                            
                            <?php
                            $phone = get_post_meta(get_the_ID(), '_business_phone', true);
                            $website = get_post_meta(get_the_ID(), '_business_website', true);
                            $address = get_post_meta(get_the_ID(), '_business_address', true);
                            ?>
                            
                            <div class="cb-listing-content">
                                <?php the_excerpt(); ?>
                                
                                <?php if ($phone) : ?>
                                    <p><strong>Phone:</strong> <?php echo esc_html($phone); ?></p>
                                <?php endif; ?>
                                
                                <?php if ($website) : ?>
                                    <p><strong>Website:</strong> <a href="<?php echo esc_url($website); ?>" target="_blank"><?php echo esc_html($website); ?></a></p>
                                <?php endif; ?>
                                
                                <?php if ($address) : ?>
                                    <p><strong>Address:</strong> <?php echo esc_html($address); ?></p>
                                <?php endif; ?>
                                
                                <div class="cb-listing-categories">
                                    <?php
                                    $categories = get_the_terms(get_the_ID(), 'business_category');
                                    if ($categories && !is_wp_error($categories)) {
                                        echo '<strong>Categories:</strong> ';
                                        $category_names = array();
                                        foreach ($categories as $category) {
                                            $category_names[] = $category->name;
                                        }
                                        echo implode(', ', $category_names);
                                    }
                                    ?>
                                </div>
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
     * Featured business listings shortcode
     */
    public function featured_business_listings_shortcode($atts) {
        ob_start();
        
        $args = array(
            'post_type'      => 'business_listing',
            'post_status'    => 'publish',
            'posts_per_page' => -1, // Display all featured listings
            'meta_query'     => array(
                array(
                    'key'   => '_is_featured',
                    'value' => 1,
                    'compare' => '=',
                ),
            ),
        );
        
        $featured_listings = new WP_Query($args);
        
        ?>
        <div class="cb-featured-listings">
            <h2>Featured Business Listings</h2>
            <?php
            if ($featured_listings->have_posts()) {
                while ($featured_listings->have_posts()) {
                    $featured_listings->the_post();
                    ?>
                    <div class="cb-listing">
                        <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                        <?php if (has_post_thumbnail()) : ?>
                            <div class="cb-listing-image">
                                <?php the_post_thumbnail('thumbnail'); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php
                        $phone = get_post_meta(get_the_ID(), '_business_phone', true);
                        $website = get_post_meta(get_the_ID(), '_business_website', true);
                        $address = get_post_meta(get_the_ID(), '_business_address', true);
                        ?>
                        
                        <div class="cb-listing-content">
                            <?php the_excerpt(); ?>
                            
                            <?php if ($phone) : ?>
                                <p><strong>Phone:</strong> <?php echo esc_html($phone); ?></p>
                            <?php endif; ?>
                            
                            <?php if ($website) : ?>
                                <p><strong>Website:</strong> <a href="<?php echo esc_url($website); ?>" target="_blank"><?php echo esc_html($website); ?></a></p>
                            <?php endif; ?>
                            
                            <?php if ($address) : ?>
                                <p><strong>Address:</strong> <?php echo esc_html($address); ?></p>
                            <?php endif; ?>
                            
                            <div class="cb-listing-categories">
                                <?php
                                $categories = get_the_terms(get_the_ID(), 'business_category');
                                if ($categories && !is_wp_error($categories)) {
                                    echo '<strong>Categories:</strong> ';
                                    $category_names = array();
                                    foreach ($categories as $category) {
                                        $category_names[] = $category->name;
                                    }
                                    echo implode(', ', $category_names);
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                    <?php
                }
                wp_reset_postdata();
            } else {
                echo '<p>No featured business listings found.</p>';
            }
            ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Member signup shortcode
     */
    public function member_signup_shortcode($atts) {
        ob_start();
        ?>
        <div class="cb-signup">
            <h2>Chamber Member Sign Up</h2>
            <form method="post" class="cb-membership-form">
                <?php wp_nonce_field('cb_member_signup', 'cb_signup_nonce'); ?>
                
                <p>
                    <label for="cb_first_name">First Name</label>
                    <input type="text" id="cb_first_name" name="cb_first_name" required>
                </p>
                
                <p>
                    <label for="cb_last_name">Last Name</label>
                    <input type="text" id="cb_last_name" name="cb_last_name" required>
                </p>
                
                <p>
                    <label for="cb_email">Email</label>
                    <input type="email" id="cb_email" name="cb_email" required>
                </p>
                
                <p>
                    <label for="cb_password">Password</label>
                    <input type="password" id="cb_password" name="cb_password" required>
                </p>
                
                <p>
                    <label for="cb_business_name">Business Name</label>
                    <input type="text" id="cb_business_name" name="cb_business_name" required>
                </p>
                
                <p>
                    <input type="submit" value="Sign Up" class="btn-primary">
                </p>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Handle member signup
     */
    public function handle_member_signup() {
        // Check nonce
        if (!wp_verify_nonce($_POST['cb_signup_nonce'], 'cb_member_signup')) {
            wp_die('Security check failed');
        }
        
        // Get form data
        $first_name = sanitize_text_field($_POST['cb_first_name']);
        $last_name = sanitize_text_field($_POST['cb_last_name']);
        $email = sanitize_email($_POST['cb_email']);
        $password = $_POST['cb_password'];
        $business_name = sanitize_text_field($_POST['cb_business_name']);
        
        // Create user
        $userdata = array(
            'user_login'  => $email,
            'user_email'  => $email,
            'user_pass'   => $password,
            'first_name'  => $first_name,
            'last_name'   => $last_name,
            'role'        => 'chamber_member'
        );
        
        $user_id = wp_insert_user($userdata);
        
        if (is_wp_error($user_id)) {
            wp_send_json_error($user_id->get_error_message());
        }
        
        // Update user meta
        update_user_meta($user_id, 'business_name', $business_name);
        
        // Create business listing
        $post_id = wp_insert_post(array(
            'post_title' => $business_name,
            'post_content' => '',
            'post_status' => 'publish',
            'post_author' => $user_id,
            'post_type' => 'business_listing'
        ));
        
        // Create membership record (without Stripe for testing)
        global $wpdb;
        $table_name = $wpdb->prefix . 'cb_memberships';
        
        $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'membership_type' => 'basic',
                'status' => 'active'
            )
        );
        
        // Add to MailPoet if available
        $this->add_user_to_mailpoet_list($user_id);
        
        wp_send_json_success('Member registered successfully!');
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