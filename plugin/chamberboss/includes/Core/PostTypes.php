<?php
namespace Chamberboss\Core;

use Chamberboss\Core\Database;

/**
 * Custom Post Types Handler
 */
class PostTypes extends BaseClass {
    
    /**
     * Initialize post types
     */
    protected function init() {
        add_action('init', [$this, 'register_post_types']);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post', [$this, 'save_meta_boxes']);
    }
    
    /**
     * Register custom post types
     */
    public function register_post_types() {
        $this->register_members_post_type();
        $this->register_business_listings_post_type();
    }
    
    /**
     * Register Members post type
     */
    private function register_members_post_type() {
        $labels = [
            'name' => __('Members', 'chamberboss'),
            'singular_name' => __('Member', 'chamberboss'),
            'menu_name' => __('Members', 'chamberboss'),
            'add_new' => __('Add New Member', 'chamberboss'),
            'add_new_item' => __('Add New Member', 'chamberboss'),
            'edit_item' => __('Edit Member', 'chamberboss'),
            'new_item' => __('New Member', 'chamberboss'),
            'view_item' => __('View Member', 'chamberboss'),
            'search_items' => __('Search Members', 'chamberboss'),
            'not_found' => __('No members found', 'chamberboss'),
            'not_found_in_trash' => __('No members found in trash', 'chamberboss'),
        ];
        
        $args = [
            'labels' => $labels,
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false, // We'll add to custom admin menu
            'show_in_admin_bar' => false,
            'capability_type' => 'chamberboss_member',
            'capabilities' => [
                'create_posts' => 'create_chamberboss_members',
                'edit_post' => 'edit_chamberboss_member',
                'read_post' => 'read_chamberboss_member',
                'delete_post' => 'delete_chamberboss_member',
                'edit_posts' => 'edit_chamberboss_members',
                'edit_others_posts' => 'edit_others_chamberboss_members',
                'publish_posts' => 'publish_chamberboss_members',
                'read_private_posts' => 'read_private_chamberboss_members',
                'delete_posts' => 'delete_chamberboss_members',
                'delete_private_posts' => 'delete_private_chamberboss_members',
                'delete_published_posts' => 'delete_published_chamberboss_members',
                'delete_others_posts' => 'delete_others_chamberboss_members',
                'edit_private_posts' => 'edit_private_chamberboss_members',
                'edit_published_posts' => 'edit_published_chamberboss_members',
            ],
            'hierarchical' => false,
            'supports' => ['title', 'editor'],
            'has_archive' => false,
            'rewrite' => false,
            'query_var' => false,
        ];
        
        register_post_type('chamberboss_member', $args);
    }
    
    /**
     * Register Business Listings post type
     */
    private function register_business_listings_post_type() {
        $labels = [
            'name' => __('Business Listings', 'chamberboss'),
            'singular_name' => __('Business Listing', 'chamberboss'),
            'menu_name' => __('Business Listings', 'chamberboss'),
            'add_new' => __('Add New Listing', 'chamberboss'),
            'add_new_item' => __('Add New Business Listing', 'chamberboss'),
            'edit_item' => __('Edit Business Listing', 'chamberboss'),
            'new_item' => __('New Business Listing', 'chamberboss'),
            'view_item' => __('View Business Listing', 'chamberboss'),
            'search_items' => __('Search Business Listings', 'chamberboss'),
            'not_found' => __('No business listings found', 'chamberboss'),
            'not_found_in_trash' => __('No business listings found in trash', 'chamberboss'),
        ];
        
        $args = [
            'labels' => $labels,
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => false, // We'll add to custom admin menu
            'show_in_admin_bar' => true,
            'capability_type' => 'chamberboss_member',
            'capabilities' => [
                'create_posts' => 'create_chamberboss_members',
                'edit_post' => 'edit_chamberboss_member',
                'read_post' => 'read_chamberboss_member',
                'delete_post' => 'delete_chamberboss_member',
                'edit_posts' => 'edit_chamberboss_members',
                'edit_others_posts' => 'edit_others_chamberboss_members',
                'publish_posts' => 'publish_chamberboss_members',
                'read_private_posts' => 'read_private_chamberboss_members',
                'delete_posts' => 'delete_chamberboss_members',
                'delete_private_posts' => 'delete_private_chamberboss_members',
                'delete_published_posts' => 'delete_published_chamberboss_members',
                'delete_others_posts' => 'delete_others_chamberboss_members',
                'edit_private_posts' => 'edit_private_chamberboss_members',
                'edit_published_posts' => 'edit_published_chamberboss_members',
            ],
            'hierarchical' => false,
            'supports' => ['title', 'editor', 'author', 'thumbnail'],
            'has_archive' => true,
            'rewrite' => ['slug' => 'business-directory'],
            'query_var' => true,
            'menu_icon' => 'dashicons-building',
        ];
        
        register_post_type('chamberboss_listing', $args);
    }
    
    /**
     * Add meta boxes
     */
    public function add_meta_boxes() {
        // Member meta boxes
        add_meta_box(
            'chamberboss_member_details',
            __('Member Details', 'chamberboss'),
            [$this, 'member_details_meta_box'],
            'chamberboss_member',
            'normal',
            'high'
        );
        
        add_meta_box(
            'chamberboss_member_subscription',
            __('Subscription Details', 'chamberboss'),
            [$this, 'member_subscription_meta_box'],
            'chamberboss_member',
            'side',
            'high'
        );
        
        // Business listing meta boxes
        add_meta_box(
            'chamberboss_listing_details',
            __('Business Details', 'chamberboss'),
            [$this, 'listing_details_meta_box'],
            'chamberboss_listing',
            'normal',
            'high'
        );
    }
    
    /**
     * Member details meta box
     */
    public function member_details_meta_box($post) {
        wp_nonce_field('chamberboss_member_meta', 'chamberboss_member_nonce');
        
        $email = get_post_meta($post->ID, '_chamberboss_member_email', true);
        $phone = get_post_meta($post->ID, '_chamberboss_member_phone', true);
        $company = get_post_meta($post->ID, '_chamberboss_member_company', true);
        $address = get_post_meta($post->ID, '_chamberboss_member_address', true);
        $website = get_post_meta($post->ID, '_chamberboss_member_website', true);
        
        ?>
        <table class="form-table">
            <tr>
                <th><label for="chamberboss_member_email"><?php _e('Email Address', 'chamberboss'); ?></label></th>
                <td><input type="email" id="chamberboss_member_email" name="chamberboss_member_email" value="<?php echo esc_attr($email); ?>" class="regular-text" required /></td>
            </tr>
            <tr>
                <th><label for="chamberboss_member_phone"><?php _e('Phone Number', 'chamberboss'); ?></label></th>
                <td><input type="tel" id="chamberboss_member_phone" name="chamberboss_member_phone" value="<?php echo esc_attr($phone); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="chamberboss_member_company"><?php _e('Company Name', 'chamberboss'); ?></label></th>
                <td><input type="text" id="chamberboss_member_company" name="chamberboss_member_company" value="<?php echo esc_attr($company); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="chamberboss_member_address"><?php _e('Address', 'chamberboss'); ?></label></th>
                <td><textarea id="chamberboss_member_address" name="chamberboss_member_address" rows="3" class="large-text"><?php echo esc_textarea($address); ?></textarea></td>
            </tr>
            <tr>
                <th><label for="chamberboss_member_website"><?php _e('Website', 'chamberboss'); ?></label></th>
                <td><input type="url" id="chamberboss_member_website" name="chamberboss_member_website" value="<?php echo esc_attr($website); ?>" class="regular-text" /></td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Member subscription meta box
     */
    public function member_subscription_meta_box($post) {
        $status = get_post_meta($post->ID, '_chamberboss_subscription_status', true) ?: 'inactive';
        $start_date = get_post_meta($post->ID, '_chamberboss_subscription_start', true);
        $end_date = get_post_meta($post->ID, '_chamberboss_subscription_end', true);
        $stripe_customer_id = get_post_meta($post->ID, '_chamberboss_stripe_customer_id', true);
        $stripe_subscription_id = get_post_meta($post->ID, '_chamberboss_stripe_subscription_id', true);
        
        ?>
        <p>
            <label for="chamberboss_subscription_status"><?php _e('Status', 'chamberboss'); ?></label><br>
            <select id="chamberboss_subscription_status" name="chamberboss_subscription_status">
                <option value="inactive" <?php selected($status, 'inactive'); ?>><?php _e('Inactive', 'chamberboss'); ?></option>
                <option value="active" <?php selected($status, 'active'); ?>><?php _e('Active', 'chamberboss'); ?></option>
                <option value="expired" <?php selected($status, 'expired'); ?>><?php _e('Expired', 'chamberboss'); ?></option>
                <option value="cancelled" <?php selected($status, 'cancelled'); ?>><?php _e('Cancelled', 'chamberboss'); ?></option>
            </select>
        </p>
        
        <p>
            <label for="chamberboss_subscription_start"><?php _e('Start Date', 'chamberboss'); ?></label><br>
            <input type="date" id="chamberboss_subscription_start" name="chamberboss_subscription_start" value="<?php echo esc_attr($start_date); ?>" />
        </p>
        
        <p>
            <label for="chamberboss_subscription_end"><?php _e('End Date', 'chamberboss'); ?></label><br>
            <input type="date" id="chamberboss_subscription_end" name="chamberboss_subscription_end" value="<?php echo esc_attr($end_date); ?>" />
        </p>
        
        <?php if ($stripe_customer_id): ?>
        <p>
            <strong><?php _e('Stripe Customer ID:', 'chamberboss'); ?></strong><br>
            <code><?php echo esc_html($stripe_customer_id); ?></code>
        </p>
        <?php endif; ?>
        
        <?php if ($stripe_subscription_id): ?>
        <p>
            <strong><?php _e('Stripe Subscription ID:', 'chamberboss'); ?></strong><br>
            <code><?php echo esc_html($stripe_subscription_id); ?></code>
        </p>
        <?php endif; ?>
        <?php
    }
    
    /**
     * Business listing details meta box
     */
    public function listing_details_meta_box($post) {
        wp_nonce_field('chamberboss_listing_meta', 'chamberboss_listing_nonce');
        
        $phone = get_post_meta($post->ID, '_chamberboss_listing_phone', true);
        $address = get_post_meta($post->ID, '_chamberboss_listing_address', true);
        $website = get_post_meta($post->ID, '_chamberboss_listing_website', true);
        $category = get_post_meta($post->ID, '_chamberboss_listing_category', true);
        $featured = get_post_meta($post->ID, '_chamberboss_listing_featured', true);

        $database = new Database();
        $categories = $database->get_listing_categories();
        
        ?>
        <table class="form-table">
            <tr>
                <th><label for="chamberboss_listing_phone"><?php _e('Phone Number', 'chamberboss'); ?></label></th>
                <td><input type="tel" id="chamberboss_listing_phone" name="chamberboss_listing_phone" value="<?php echo esc_attr($phone); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="chamberboss_listing_address"><?php _e('Address', 'chamberboss'); ?></label></th>
                <td><textarea id="chamberboss_listing_address" name="chamberboss_listing_address" rows="3" class="large-text"><?php echo esc_textarea($address); ?></textarea></td>
            </tr>
            <tr>
                <th><label for="chamberboss_listing_website"><?php _e('Website', 'chamberboss'); ?></label></th>
                <td><input type="url" id="chamberboss_listing_website" name="chamberboss_listing_website" value="<?php echo esc_attr($website); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="chamberboss_listing_category"><?php _e('Category', 'chamberboss'); ?></label></th>
                <td>
                    <select id="chamberboss_listing_category" name="chamberboss_listing_category">
                        <option value=""><?php _e('Select Category', 'chamberboss'); ?></option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo esc_attr($cat->slug); ?>" <?php selected($category, $cat->slug); ?>><?php echo esc_html($cat->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="chamberboss_listing_featured"><?php _e('Featured Listing', 'chamberboss'); ?></label></th>
                <td><input type="checkbox" id="chamberboss_listing_featured" name="chamberboss_listing_featured" value="1" <?php checked($featured, '1'); ?> /></td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Save meta box data
     */
    public function save_meta_boxes($post_id) {
        // Check if this is an autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check user permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        $post_type = get_post_type($post_id);
        
        if ($post_type === 'chamberboss_member') {
            $this->save_member_meta($post_id);
        } elseif ($post_type === 'chamberboss_listing') {
            $this->save_listing_meta($post_id);
        }
    }
    
    /**
     * Save member meta data
     */
    private function save_member_meta($post_id) {
        if (!isset($_POST['chamberboss_member_nonce']) || !wp_verify_nonce($_POST['chamberboss_member_nonce'], 'chamberboss_member_meta')) {
            return;
        }
        
        $fields = [
            'chamberboss_member_email' => '_chamberboss_member_email',
            'chamberboss_member_phone' => '_chamberboss_member_phone',
            'chamberboss_member_company' => '_chamberboss_member_company',
            'chamberboss_member_address' => '_chamberboss_member_address',
            'chamberboss_member_website' => '_chamberboss_member_website',
            'chamberboss_member_notes' => '_chamberboss_member_notes',
            'chamberboss_subscription_status' => '_chamberboss_subscription_status',
            'chamberboss_subscription_start' => '_chamberboss_subscription_start',
            'chamberboss_subscription_end' => '_chamberboss_subscription_end',
        ];
        
        foreach ($fields as $field => $meta_key) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, $meta_key, $this->sanitize_input($_POST[$field]));
            }
        }
    }
    
    /**
     * Save listing meta data
     */
    private function save_listing_meta($post_id) {
        if (!isset($_POST['chamberboss_listing_nonce']) || !wp_verify_nonce($_POST['chamberboss_listing_nonce'], 'chamberboss_listing_meta')) {
            return;
        }
        
        $fields = [
            'chamberboss_listing_phone' => '_chamberboss_listing_phone',
            'chamberboss_listing_address' => '_chamberboss_listing_address',
            'chamberboss_listing_website' => '_chamberboss_listing_website',
            'chamberboss_listing_category' => '_chamberboss_listing_category',
            'chamberboss_listing_featured' => '_chamberboss_listing_featured',
        ];
        
        foreach ($fields as $field => $meta_key) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, $meta_key, $this->sanitize_input($_POST[$field]));
            } else if ($meta_key === '_chamberboss_listing_featured') {
                update_post_meta($post_id, $meta_key, '0');
            } else {
                delete_post_meta($post_id, $meta_key);
            }
        }
    }
    
    /**
     * Static method for activation hook
     */
    public static function on_activation_register_post_types() {
        $instance = new self();
        $instance->register_members_post_type();
        $instance->register_business_listings_post_type();
    }
}

