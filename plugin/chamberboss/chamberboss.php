<?php
/**
 * Plugin Name: Chamberboss
 * Plugin URI: https://genexmarketing.com/chamberboss
 * Description: A comprehensive chamber of commerce management plugin with member management, business listings, Stripe payments, and MailPoet integration.
 * Version: 1.0.26
 * Author: Genex Marketing Agency Ltd
 * Author URI: https://genexmarketing.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: chamberboss
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 */

// GEMINI_DEBUG_MARKER_20250726

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('CHAMBERBOSS_VERSION', '1.0.26');
define('CHAMBERBOSS_PLUGIN_FILE', __FILE__);
define('CHAMBERBOSS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CHAMBERBOSS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CHAMBERBOSS_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Load Composer autoloader for Stripe PHP SDK - will be loaded in init() method

require_once CHAMBERBOSS_PLUGIN_DIR . 'includes/Core/BaseClass.php';
require_once CHAMBERBOSS_PLUGIN_DIR . 'includes/Core/Database.php';
require_once CHAMBERBOSS_PLUGIN_DIR . 'includes/Core/PostTypes.php';



// Autoloader for plugin classes
spl_autoload_register(function ($class) {
    $prefix = 'Chamberboss\\';
    $base_dir = CHAMBERBOSS_PLUGIN_DIR . 'includes/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

/**
 * Main Chamberboss Plugin Class
 */
final class Chamberboss {
    
    /**
     * Plugin instance
     * @var Chamberboss
     */
    private static $instance = null;
    
    /**
     * Get plugin instance
     * @return Chamberboss
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
        register_activation_hook(CHAMBERBOSS_PLUGIN_FILE, [$this, 'activate']);
        register_deactivation_hook(CHAMBERBOSS_PLUGIN_FILE, [$this, 'deactivate']);
        
        add_action('plugins_loaded', [$this, 'init']);
        add_action('init', [$this, 'load_textdomain']);
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // DEBUG: Check if Stripe classes are available during main init
        // Try to manually load Stripe classes if not available
        if (!class_exists('\\Stripe\\Stripe')) {
            $vendor_autoload = CHAMBERBOSS_PLUGIN_DIR . 'vendor/autoload.php';
            if (file_exists($vendor_autoload)) {
                require_once $vendor_autoload;
            }
        }
        
        // Initialize core components
        new Chamberboss\Core\PostTypes();
        new Chamberboss\Core\Database();
        new Chamberboss\Admin\AdminMenu();
        new Chamberboss\Public\Directory();
        new Chamberboss\Public\MemberDashboard();
        new Chamberboss\Payments\StripeIntegration();
        new Chamberboss\Email\MailPoetIntegration();
        new Chamberboss\Notifications\NotificationSystem();
    }
    
    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain('chamberboss', false, dirname(CHAMBERBOSS_PLUGIN_BASENAME) . '/languages');
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        Chamberboss\Core\Database::on_activation_create_tables();
        
        // Create custom post types
        Chamberboss\Core\PostTypes::on_activation_register_post_types();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Set default options
        $this->set_default_options();

        // Add member role
        $this->add_member_role();
        
        // Update existing member capabilities
        $this->update_existing_member_capabilities();
        
        // Block member access to blog posts and comments (but allow business listings)
        $this->restrict_member_access();
    }

    /**
     * Add member role
     */
    private function add_member_role() {
        add_role(
            'chamberboss_member',
            __('Member', 'chamberboss'),
            [
                'read' => true,
                'upload_files' => true,
                // Basic WordPress capabilities needed for admin access and custom post types
                'edit_posts' => true,
                'delete_posts' => true,
                'edit_published_posts' => true,
                // Business listing capabilities
                'create_chamberboss_members' => true,
                'edit_chamberboss_member' => true,
                'read_chamberboss_member' => true,
                'delete_chamberboss_member' => true,
                'edit_chamberboss_members' => true,
                'delete_chamberboss_members' => true,
                'read_private_chamberboss_members' => true,
                'edit_private_chamberboss_members' => true,
                // Note: No publish capabilities - listings require admin approval
                // Note: Blog posts and comments blocked via specific access control hooks
            ]
        );
        
        // Also update existing member role if it exists
        $role = get_role('chamberboss_member');
        if ($role) {
            // Add basic WordPress capabilities needed for admin access
            $role->add_cap('edit_posts');
            $role->add_cap('delete_posts');
            $role->add_cap('edit_published_posts');
            
            // Add business listing capabilities
            $role->add_cap('create_chamberboss_members');
            $role->add_cap('edit_chamberboss_member');
            $role->add_cap('read_chamberboss_member');
            $role->add_cap('delete_chamberboss_member');
            $role->add_cap('edit_chamberboss_members');
            $role->add_cap('delete_chamberboss_members');
            $role->add_cap('read_private_chamberboss_members');
            $role->add_cap('edit_private_chamberboss_members');
            
            // Remove publish capabilities (listings require admin approval)
            $role->remove_cap('publish_chamberboss_members');
            $role->remove_cap('edit_published_chamberboss_members');
            $role->remove_cap('delete_published_chamberboss_members');
            $role->remove_cap('publish_posts');
            
            // Remove advanced capabilities they don't need
            $role->remove_cap('edit_others_posts');
            $role->remove_cap('delete_others_posts');
            $role->remove_cap('read_private_posts');
            $role->remove_cap('edit_private_posts');
            $role->remove_cap('moderate_comments');
            $role->remove_cap('edit_comment');
            $role->remove_cap('edit_comments');
            $role->remove_cap('edit_others_chamberboss_members');
            $role->remove_cap('delete_others_chamberboss_members');
            $role->remove_cap('delete_private_chamberboss_members');
        }
    }
    
    /**
     * Update existing member capabilities
     */
    private function update_existing_member_capabilities() {
        // Get all users with chamberboss_member role
        $members = get_users([
            'role' => 'chamberboss_member',
            'fields' => 'ID'
        ]);
        
        // Capabilities to add (basic WordPress + business listing capabilities)
        $add_capabilities = [
            'edit_posts',
            'delete_posts',
            'edit_published_posts',
            'create_chamberboss_members',
            'edit_chamberboss_member',
            'read_chamberboss_member',
            'delete_chamberboss_member',
            'edit_chamberboss_members',
            'delete_chamberboss_members',
            'read_private_chamberboss_members',
            'edit_private_chamberboss_members'
        ];
        
        // Capabilities to remove (publish permissions and advanced capabilities)
        $remove_capabilities = [
            'publish_posts',
            'edit_others_posts',
            'delete_others_posts',
            'read_private_posts',
            'edit_private_posts',
            'moderate_comments',
            'edit_comment',
            'edit_comments',
            'publish_chamberboss_members',
            'edit_published_chamberboss_members',
            'delete_published_chamberboss_members',
            'edit_others_chamberboss_members',
            'delete_others_chamberboss_members',
            'delete_private_chamberboss_members'
        ];
        
        foreach ($members as $user_id) {
            $user = new WP_User($user_id);
            
            // Add necessary capabilities
            foreach ($add_capabilities as $cap) {
                $user->add_cap($cap);
            }
            
            // Remove unwanted capabilities
            foreach ($remove_capabilities as $cap) {
                $user->remove_cap($cap);
            }
        }
    }
    
    /**
     * Restrict member access to blog posts and comments only
     */
    private function restrict_member_access() {
        // Block access to blog posts for members (but allow business listings)
        add_action('admin_init', [$this, 'block_member_blog_post_access']);
        add_action('wp_ajax_inline-save', [$this, 'block_member_blog_post_ajax'], 0);
        
        // Block comment access for members
        add_action('admin_menu', [$this, 'remove_comment_menu_for_members'], 999);
        add_action('admin_init', [$this, 'block_member_comment_access']);
    }
    
    /**
     * Block member access to blog posts only (allow business listings)
     */
    public function block_member_blog_post_access() {
        if (!current_user_can('administrator') && current_user_can('chamberboss_member')) {
            global $pagenow;
            
            // Block access to regular blog posts (but NOT business listings)
            if (isset($_GET['post_type']) && $_GET['post_type'] === 'post') {
                wp_die(__('You do not have permission to access blog posts.', 'chamberboss'));
            }
            
            if (isset($_GET['post']) && get_post_type($_GET['post']) === 'post') {
                wp_die(__('You do not have permission to edit blog posts.', 'chamberboss'));
            }
            
            // Block access to posts.php (blog post list) but allow business listings
            if ($pagenow === 'edit.php' && (!isset($_GET['post_type']) || $_GET['post_type'] === 'post')) {
                wp_die(__('You do not have permission to access blog posts.', 'chamberboss'));
            }
            
            // Block creating new blog posts (but allow business listings)
            if ($pagenow === 'post-new.php' && (!isset($_GET['post_type']) || $_GET['post_type'] === 'post')) {
                wp_die(__('You do not have permission to create blog posts.', 'chamberboss'));
            }
            
            // Block editing blog posts (but allow business listings)
            if ($pagenow === 'post.php' && isset($_GET['post']) && get_post_type($_GET['post']) === 'post') {
                wp_die(__('You do not have permission to edit blog posts.', 'chamberboss'));
            }
        }
    }
    
    /**
     * Block member AJAX access to blog posts only
     */
    public function block_member_blog_post_ajax() {
        if (!current_user_can('administrator') && current_user_can('chamberboss_member')) {
            if (isset($_POST['post_ID']) && get_post_type($_POST['post_ID']) === 'post') {
                wp_die(__('You do not have permission to edit blog posts.', 'chamberboss'));
            }
        }
    }
    
    /**
     * Remove comment menu for members
     */
    public function remove_comment_menu_for_members() {
        if (!current_user_can('administrator') && current_user_can('chamberboss_member')) {
            remove_menu_page('edit-comments.php');
        }
    }
    
    /**
     * Block member access to comments
     */
    public function block_member_comment_access() {
        if (!current_user_can('administrator') && current_user_can('chamberboss_member')) {
            global $pagenow;
            if ($pagenow === 'edit-comments.php' || $pagenow === 'comment.php') {
                wp_die(__('You do not have permission to access comments.', 'chamberboss'));
            }
        }
    }
     
     /**
      * Plugin deactivation
      */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Set default plugin options
     */
    private function set_default_options() {
        $defaults = [
            'chamberboss_membership_price' => '100.00',
            'chamberboss_currency' => 'USD',
            'chamberboss_stripe_mode' => 'test',
            'chamberboss_mailpoet_list_id' => '',
            'chamberboss_renewal_days' => 30,
        ];
        
        foreach ($defaults as $option => $value) {
            if (!get_option($option)) {
                add_option($option, $value);
            }
        }
    }
}

// Initialize the plugin
function chamberboss() {
    return Chamberboss::instance();
}

// Start the plugin
chamberboss();

