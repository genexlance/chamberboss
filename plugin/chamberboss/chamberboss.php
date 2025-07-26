<?php
/**
 * Plugin Name: Chamberboss
 * Plugin URI: https://genexmarketing.com/chamberboss
 * Description: A comprehensive chamber of commerce management plugin with member management, business listings, Stripe payments, and MailPoet integration.
 * Version: 1.0.0
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

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('CHAMBERBOSS_VERSION', '1.0.0');
define('CHAMBERBOSS_PLUGIN_FILE', __FILE__);
define('CHAMBERBOSS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CHAMBERBOSS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CHAMBERBOSS_PLUGIN_BASENAME', plugin_basename(__FILE__));



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
        // Initialize core components
        new Chamberboss\Core\PostTypes(); // Re-enabled
        new Chamberboss\Core\Database(); // Re-enabled
        new Chamberboss\Admin\AdminMenu(); // Re-enabled
        new Chamberboss\Public\Directory(); // Re-enabled
        new Chamberboss\Public\MemberDashboard();
        new Chamberboss\Payments\StripeIntegration(); // Re-enabled
        new Chamberboss\Email\MailPoetIntegration(); // Re-enabled
        new Chamberboss\Notifications\NotificationSystem(); // Re-enabled
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
                'edit_posts' => true,
                'delete_posts' => true,
                'edit_published_posts' => true,
                'upload_files' => true,
                'edit_chamberboss_listing' => true,
                'read_chamberboss_listing' => true,
                'delete_chamberboss_listing' => true,
            ]
        );
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

