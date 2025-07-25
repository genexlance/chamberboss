<?php
namespace Chamberboss\Admin;

use Chamberboss\Core\BaseClass;

/**
 * Admin Menu Handler
 */
class AdminMenu extends BaseClass {
    
    /**
     * Initialize admin menu
     */
    protected function init() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('admin_init', [$this, 'register_settings']);
        
        // Add custom capabilities to administrator role
        add_action('admin_init', [$this, 'add_custom_capabilities']);

        // Instantiate pages to ensure hooks are registered
        new MembersPage();
        new CategoriesPage();
        new ListingsPage();
    }
    
    /**
     * Add admin menu pages
     */
    public function add_admin_menu() {
        // Main menu page
        add_menu_page(
            __('Chamberboss', 'chamberboss'),
            __('Chamberboss', 'chamberboss'),
            'manage_chamberboss',
            'chamberboss',
            [$this, 'dashboard_page'],
            'dashicons-groups',
            30
        );
        
        // Dashboard (same as main page)
        add_submenu_page(
            'chamberboss',
            __('Dashboard', 'chamberboss'),
            __('Dashboard', 'chamberboss'),
            'manage_chamberboss',
            'chamberboss',
            [$this, 'dashboard_page']
        );
        
        // Members management
        add_submenu_page(
            'chamberboss',
            __('Members', 'chamberboss'),
            __('Members', 'chamberboss'),
            'manage_chamberboss_members',
            'chamberboss-members',
            [$this, 'members_page']
        );
        
        // Business listings management
        add_submenu_page(
            'chamberboss',
            __('Business Listings', 'chamberboss'),
            __('Business Listings', 'chamberboss'),
            'manage_chamberboss_listings',
            'chamberboss-listings',
            [$this, 'listings_page']
        );

        // Categories
        add_submenu_page(
            'chamberboss',
            __('Categories', 'chamberboss'),
            __('Categories', 'chamberboss'),
            'manage_chamberboss',
            'chamberboss-categories',
            [$this, 'categories_page']
        );
        
        // Transactions
        add_submenu_page(
            'chamberboss',
            __('Transactions', 'chamberboss'),
            __('Transactions', 'chamberboss'),
            'manage_chamberboss',
            'chamberboss-transactions',
            [$this, 'transactions_page']
        );
        
        // Settings
        add_submenu_page(
            'chamberboss',
            __('Settings', 'chamberboss'),
            __('Settings', 'chamberboss'),
            'manage_chamberboss',
            'chamberboss-settings',
            [$this, 'settings_page']
        );
    }
    
    /**
     * Add custom capabilities to administrator role
     */
    public function add_custom_capabilities() {
        $role = get_role('administrator');
        
        if ($role) {
            $capabilities = [
                'manage_chamberboss_members',
                'manage_chamberboss_listings',
                'chamberboss_create_listings',
                'chamberboss_edit_listings',
                'chamberboss_publish_listings',
                'chamberboss_delete_listings',
                'manage_chamberboss',
                'create_chamberboss_members',
                'edit_chamberboss_member',
                'read_chamberboss_member',
                'delete_chamberboss_member',
                'edit_chamberboss_members',
                'edit_others_chamberboss_members',
                'publish_chamberboss_members',
                'read_private_chamberboss_members',
                'delete_chamberboss_members',
                'delete_private_chamberboss_members',
                'delete_published_chamberboss_members',
                'delete_others_chamberboss_members',
                'edit_private_chamberboss_members',
                'edit_published_chamberboss_members'
            ];
            
            foreach ($capabilities as $cap) {
                $role->add_cap($cap);
            }
        }
    }
    
    /**
     * Dashboard page
     */
    public function dashboard_page() {
        $dashboard = new DashboardPage();
        $dashboard->render();
    }
    
    /**
     * Members management page
     */
    public function members_page() {
        $members = new MembersPage(); // Re-enabled for debugging
        $members->render(); // Re-enabled for debugging
    }
    
    /**
     * Business listings management page
     */
    public function listings_page() {
        $listings = new ListingsPage();
        $listings->render();
    }

    /**
     * Categories page
     */
    public function categories_page() {
        $categories = new CategoriesPage();
        $categories->render();
    }
    
    /**
     * Transactions page
     */
    public function transactions_page() {
        $transactions = new TransactionsPage();
        $transactions->render();
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        $settings = new SettingsPage();
        $settings->render();
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on Chamberboss admin pages
        if (strpos($hook, 'chamberboss') === false) {
            return;
        }
        
        wp_enqueue_style(
            'chamberboss-admin',
            CHAMBERBOSS_PLUGIN_URL . 'assets/css/admin.css',
            [],
            CHAMBERBOSS_VERSION
        );
        
        wp_enqueue_script(
            'chamberboss-admin',
            CHAMBERBOSS_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            CHAMBERBOSS_VERSION,
            true
        );
        
        wp_localize_script('chamberboss-admin', 'chamberboss_admin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('chamberboss_admin'),
            'strings' => [
                'confirm_delete' => __('Are you sure you want to delete this item?', 'chamberboss'),
                'processing' => __('Processing...', 'chamberboss'),
                'error' => __('An error occurred. Please try again.', 'chamberboss'),
            ]
        ]);
    }
    
    /**
     * Register plugin settings
     */
    public function register_settings() {
        // General settings
        register_setting('chamberboss_general', 'chamberboss_membership_price');
        register_setting('chamberboss_general', 'chamberboss_currency');
        register_setting('chamberboss_general', 'chamberboss_renewal_days');
        
        // Stripe settings
        register_setting('chamberboss_stripe', 'chamberboss_stripe_mode');
        register_setting('chamberboss_stripe', 'chamberboss_stripe_test_publishable_key');
        register_setting('chamberboss_stripe', 'chamberboss_stripe_test_secret_key');
        register_setting('chamberboss_stripe', 'chamberboss_stripe_live_publishable_key');
        register_setting('chamberboss_stripe', 'chamberboss_stripe_live_secret_key');
        register_setting('chamberboss_stripe', 'chamberboss_stripe_webhook_secret');
        
        // MailPoet settings
        register_setting('chamberboss_mailpoet', 'chamberboss_mailpoet_enabled');
        register_setting('chamberboss_mailpoet', 'chamberboss_mailpoet_list_id');
        register_setting('chamberboss_mailpoet', 'chamberboss_mailpoet_auto_add');
        
        // Email settings
        register_setting('chamberboss_email', 'chamberboss_email_from_name');
        register_setting('chamberboss_email', 'chamberboss_email_from_address');
        register_setting('chamberboss_email', 'chamberboss_email_renewal_subject');
        register_setting('chamberboss_email', 'chamberboss_email_renewal_message');
        register_setting('chamberboss_email', 'chamberboss_email_welcome_subject');
        register_setting('chamberboss_email', 'chamberboss_email_welcome_message');
    }
}

