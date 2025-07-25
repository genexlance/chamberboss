<?php
namespace Chamberboss\Public;

use Chamberboss\Core\BaseClass;
use Chamberboss\Core\Database;

/**
 * Public Directory Handler
 */
class Directory extends BaseClass {
    
    /**
     * Initialize directory
     */
    protected function init() {
        // Add shortcodes
        add_shortcode('chamberboss_directory', [$this, 'directory_shortcode']);
        add_shortcode('chamberboss_member_registration', [$this, 'member_registration_shortcode']);
        add_shortcode('chamberboss_listing_form', [$this, 'listing_form_shortcode']);
        
        // Handle form submissions
        add_action('wp_ajax_chamberboss_register_member', [$this, 'handle_member_registration']);
        add_action('wp_ajax_nopriv_chamberboss_register_member', [$this, 'handle_member_registration']);
        add_action('wp_ajax_chamberboss_submit_listing', [$this, 'handle_listing_submission']);
        add_action('wp_ajax_nopriv_chamberboss_submit_listing', [$this, 'handle_listing_submission']);
        
        // Enqueue frontend scripts and styles
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        
        // Custom template for business listings archive
        add_filter('template_include', [$this, 'custom_template_include']);
        
        // Modify main query for business listings
        add_action('pre_get_posts', [$this, 'modify_listings_query']);
    }
    
    /**
     * Business directory shortcode
     * @param array $atts
     * @return string
     */
    public function directory_shortcode($atts) {
        $atts = shortcode_atts([
            'per_page' => 12,
            'category' => '',
            'featured_only' => false,
            'show_search' => true,
            'show_filters' => true,
            'layout' => 'grid' // grid or list
        ], $atts);
        
        ob_start();
        $this->render_directory($atts);
        return ob_get_clean();
    }
    
    /**
     * Member registration shortcode
     * @param array $atts
     * @return string
     */
    public function member_registration_shortcode($atts) {
        $atts = shortcode_atts([
            'redirect_url' => '',
            'show_payment' => true
        ], $atts);
        
        ob_start();
        $this->render_member_registration_form($atts);
        return ob_get_clean();
    }
    
    /**
     * Listing submission form shortcode
     * @param array $atts
     * @return string
     */
    public function listing_form_shortcode($atts) {
        $atts = shortcode_atts([
            'redirect_url' => '',
            'require_membership' => true
        ], $atts);
        
        ob_start();
        $this->render_listing_form($atts);
        return ob_get_clean();
    }
    
    /**
     * Render business directory
     * @param array $args
     */
    private function render_directory($args) {
        $paged = get_query_var('paged') ? get_query_var('paged') : 1;
        $search = $_GET['directory_search'] ?? '';
        $category_filter = $_GET['directory_category'] ?? $args['category'];
        
        // Build query arguments
        $query_args = [
            'post_type' => 'chamberboss_listing',
            'post_status' => 'publish',
            'posts_per_page' => intval($args['per_page']),
            'paged' => $paged,
            'orderby' => 'menu_order title',
            'order' => 'ASC'
        ];
        
        // Add meta query for featured listings
        if ($args['featured_only']) {
            $query_args['meta_query'] = [
                [
                    'key' => '_chamberboss_listing_featured',
                    'value' => '1',
                    'compare' => '='
                ]
            ];
        }
        
        // Add search
        if ($search) {
            $query_args['s'] = $search;
        }
        
        $database = new Database();
        $categories = $database->get_listing_categories();

        // Add category filter
        if ($category_filter) {
            if (!isset($query_args['meta_query'])) {
                $query_args['meta_query'] = [];
            }
            $query_args['meta_query'][] = [
                'key' => '_chamberboss_listing_category',
                'value' => $category_filter,
                'compare' => '='
            ];
        }
        
        $listings_query = new \WP_Query($query_args);
        
        ?>
        <div class="chamberboss-directory" data-layout="<?php echo esc_attr($args['layout']); ?>">
            
            <?php if ($args['show_search'] || $args['show_filters']): ?>
            <div class="directory-filters">
                <form method="get" class="directory-search-form">
                    <?php if ($args['show_search']): ?>
                    <div class="search-field">
                        <input type="text" 
                               name="directory_search" 
                               value="<?php echo esc_attr($search); ?>" 
                               placeholder="<?php _e('Search businesses...', 'chamberboss'); ?>"
                               class="directory-search-input">
                        <button type="submit" class="directory-search-button">
                            <span class="dashicons dashicons-search"></span>
                            <?php _e('Search', 'chamberboss'); ?>
                        </button>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($args['show_filters']): ?>
                    <div class="filter-field">
                        <select name="directory_category" class="directory-category-filter">
                            <option value=""><?php _e('All Categories', 'chamberboss'); ?></option>
                            <?php foreach ($categories as $category_obj): ?>
                                <option value="<?php echo esc_attr($category_obj->slug); ?>" <?php selected($category_filter, $category_obj->slug); ?>><?php echo esc_html($category_obj->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
            <?php endif; ?>
            
            <div class="directory-results">
                <?php if ($listings_query->have_posts()): ?>
                    <div class="directory-listings directory-<?php echo esc_attr($args['layout']); ?>">
                        <?php while ($listings_query->have_posts()): $listings_query->the_post(); ?>
                            <?php $this->render_listing_card(get_the_ID(), $args['layout']); ?>
                        <?php endwhile; ?>
                    </div>
                    
                    <?php if ($listings_query->max_num_pages > 1): ?>
                    <div class="directory-pagination">
                        <?php
                        echo paginate_links([
                            'base' => str_replace(999999999, '%#%', esc_url(get_pagenum_link(999999999))),
                            'format' => '?paged=%#%',
                            'current' => max(1, $paged),
                            'total' => $listings_query->max_num_pages,
                            'prev_text' => '‹ ' . __('Previous', 'chamberboss'),
                            'next_text' => __('Next', 'chamberboss') . ' ›',
                        ]);
                        ?>
                    </div>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <div class="directory-no-results">
                        <p><?php _e('No business listings found.', 'chamberboss'); ?></p>
                        <?php if ($search || $category_filter): ?>
                            <a href="<?php echo remove_query_arg(['directory_search', 'directory_category']); ?>" class="button">
                                <?php _e('Clear Filters', 'chamberboss'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php
        wp_reset_postdata();
    }
    
    /**
     * Render individual listing card
     * @param int $listing_id
     * @param string $layout
     */
    private function render_listing_card($listing_id, $layout = 'grid') {
        $title = get_the_title($listing_id);
        $description = get_the_excerpt($listing_id);
        $phone = get_post_meta($listing_id, '_chamberboss_listing_phone', true);
        $address = get_post_meta($listing_id, '_chamberboss_listing_address', true);
        $website = get_post_meta($listing_id, '_chamberboss_listing_website', true);
        $category = get_post_meta($listing_id, '_chamberboss_listing_category', true);
        $featured = get_post_meta($listing_id, '_chamberboss_listing_featured', true);
        $thumbnail = get_the_post_thumbnail($listing_id, 'medium');
        
        ?>
        <div class="listing-card <?php echo $featured ? 'featured-listing' : ''; ?>">
            <?php if ($thumbnail): ?>
                <div class="listing-image">
                    <?php echo $thumbnail; ?>
                    <?php if ($featured): ?>
                        <span class="featured-badge"><?php _e('Featured', 'chamberboss'); ?></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div class="listing-content">
                <div class="listing-header">
                    <h3 class="listing-title">
                        <a href="<?php echo get_permalink($listing_id); ?>">
                            <?php echo esc_html($title); ?>
                        </a>
                    </h3>
                    <?php if ($category): ?>
                        <span class="listing-category"><?php echo esc_html(ucfirst($category)); ?></span>
                    <?php endif; ?>
                </div>
                
                <?php if ($description): ?>
                    <div class="listing-description">
                        <p><?php echo esc_html($description); ?></p>
                    </div>
                <?php endif; ?>
                
                <div class="listing-details">
                    <?php if ($address): ?>
                        <div class="listing-address">
                            <span class="dashicons dashicons-location"></span>
                            <?php echo esc_html($address); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($phone): ?>
                        <div class="listing-phone">
                            <span class="dashicons dashicons-phone"></span>
                            <a href="tel:<?php echo esc_attr($phone); ?>"><?php echo esc_html($phone); ?></a>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($website): ?>
                        <div class="listing-website">
                            <span class="dashicons dashicons-admin-links"></span>
                            <a href="<?php echo esc_url($website); ?>" target="_blank" rel="noopener">
                                <?php _e('Visit Website', 'chamberboss'); ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render member registration form
     * @param array $args
     */
    private function render_member_registration_form($args) {
        if (is_user_logged_in()) {
            echo '<p>' . __('You are already logged in.', 'chamberboss') . '</p>';
            return;
        }
        
        $membership_price = $this->get_option('chamberboss_membership_price', '100.00');
        $currency = $this->get_option('chamberboss_currency', 'USD');
        
        ?>
        <div class="chamberboss-registration-form">
            <form id="chamberboss-member-registration" class="member-registration-form">
                <?php wp_nonce_field('chamberboss_member_registration', 'registration_nonce'); ?>
                
                <div class="form-section">
                    <h3><?php _e('Personal Information', 'chamberboss'); ?></h3>
                    
                    <div class="form-row">
                        <div class="form-field">
                            <label for="member_name"><?php _e('Full Name', 'chamberboss'); ?> *</label>
                            <input type="text" id="member_name" name="member_name" required>
                        </div>
                        
                        <div class="form-field">
                            <label for="member_email"><?php _e('Email Address', 'chamberboss'); ?> *</label>
                            <input type="email" id="member_email" name="member_email" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-field">
                            <label for="member_phone"><?php _e('Phone Number', 'chamberboss'); ?></label>
                            <input type="tel" id="member_phone" name="member_phone">
                        </div>
                        
                        <div class="form-field">
                            <label for="member_company"><?php _e('Company Name', 'chamberboss'); ?></label>
                            <input type="text" id="member_company" name="member_company">
                        </div>
                    </div>
                    
                    <div class="form-field">
                        <label for="member_address"><?php _e('Address', 'chamberboss'); ?></label>
                        <textarea id="member_address" name="member_address" rows="3"></textarea>
                    </div>
                    
                    <div class="form-field">
                        <label for="member_website"><?php _e('Website', 'chamberboss'); ?></label>
                        <input type="url" id="member_website" name="member_website">
                    </div>
                </div>
                
                <?php if ($args['show_payment']): ?>
                <div class="form-section">
                    <h3><?php _e('Membership Payment', 'chamberboss'); ?></h3>
                    
                    <div class="membership-pricing">
                        <div class="price-display">
                            <span class="price-amount"><?php echo $this->format_currency($membership_price, $currency); ?></span>
                            <span class="price-period"><?php _e('per year', 'chamberboss'); ?></span>
                        </div>
                        <p class="price-description">
                            <?php _e('Annual membership includes access to our business directory, networking events, and member resources.', 'chamberboss'); ?>
                        </p>
                    </div>
                    
                    <div id="payment-element">
                        <!-- Stripe Elements will be inserted here -->
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="form-actions">
                    <button type="submit" class="submit-button" <?php echo $args['show_payment'] ? 'id="submit-payment"' : ''; ?>>
                        <?php echo $args['show_payment'] ? __('Join & Pay Now', 'chamberboss') : __('Register', 'chamberboss'); ?>
                    </button>
                </div>
                
                <div id="registration-messages" class="form-messages"></div>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render listing submission form
     * @param array $args
     */
    private function render_listing_form($args) {
        if ($args['require_membership'] && !$this->is_member_active()) {
            ?>
            <div class="membership-required-notice">
                <p><?php _e('You must be an active member to submit business listings.', 'chamberboss'); ?></p>
                <a href="<?php echo home_url('/member-registration/'); ?>" class="button">
                    <?php _e('Become a Member', 'chamberboss'); ?>
                </a>
            </div>
            <?php
            return;
        }
        
        $database = new Database();
        $categories = $database->get_listing_categories();

        ?>
        <div class="chamberboss-listing-form">
            <form id="chamberboss-listing-submission" class="listing-submission-form">
                <?php wp_nonce_field('chamberboss_listing_submission', 'listing_nonce'); ?>
                
                <div class="form-section">
                    <h3><?php _e('Business Information', 'chamberboss'); ?></h3>
                    
                    <div class="form-field">
                        <label for="listing_title"><?php _e('Business Name', 'chamberboss'); ?> *</label>
                        <input type="text" id="listing_title" name="listing_title" required>
                    </div>
                    
                    <div class="form-field">
                        <label for="listing_description"><?php _e('Business Description', 'chamberboss'); ?> *</label>
                        <textarea id="listing_description" name="listing_description" rows="5" required></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-field">
                            <label for="listing_phone"><?php _e('Phone Number', 'chamberboss'); ?></label>
                            <input type="tel" id="listing_phone" name="listing_phone">
                        </div>
                        
                        <div class="form-field">
                            <label for="listing_website"><?php _e('Website', 'chamberboss'); ?></label>
                            <input type="url" id="listing_website" name="listing_website">
                        </div>
                    </div>
                    
                    <div class="form-field">
                        <label for="listing_address"><?php _e('Address', 'chamberboss'); ?></label>
                        <textarea id="listing_address" name="listing_address" rows="3"></textarea>
                    </div>
                    
                    <div class="form-field">
                        <label for="listing_category"><?php _e('Category', 'chamberboss'); ?></label>
                        <select id="listing_category" name="listing_category">
                            <option value=""><?php _e('Select Category', 'chamberboss'); ?></option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo esc_attr($category->slug); ?>"><?php echo esc_html($category->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-field">
                        <label for="listing_image"><?php _e('Business Image', 'chamberboss'); ?></label>
                        <input type="file" id="listing_image" name="listing_image" accept="image/*">
                        <p class="field-description"><?php _e('Upload a photo of your business (optional).', 'chamberboss'); ?></p>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="submit-button">
                        <?php _e('Submit Listing', 'chamberboss'); ?>
                    </button>
                </div>
                
                <div id="listing-messages" class="form-messages"></div>
            </form>
        </div>
        <?php
    }
    
    /**
     * Handle member registration AJAX
     */
    public function handle_member_registration() {
        if (!$this->verify_nonce($_POST['registration_nonce'] ?? '', 'chamberboss_member_registration')) {
            $this->send_json_response(['message' => 'Invalid nonce'], false);
            return;
        }
        
        $data = $this->sanitize_input($_POST);
        
        // Validate required fields
        if (empty($data['member_name']) || empty($data['member_email'])) {
            $this->send_json_response(['message' => 'Name and email are required'], false);
            return;
        }
        
        // Check if email already exists
        $existing_member = get_posts([
            'post_type' => 'chamberboss_member',
            'meta_query' => [
                [
                    'key' => '_chamberboss_member_email',
                    'value' => $data['member_email'],
                    'compare' => '='
                ]
            ]
        ]);
        
        if (!empty($existing_member)) {
            $this->send_json_response(['message' => 'A member with this email already exists'], false);
            return;
        }
        
        // Create member post
        $member_id = wp_insert_post([
            'post_type' => 'chamberboss_member',
            'post_title' => $data['member_name'],
            'post_status' => 'publish',
            'meta_input' => [
                '_chamberboss_member_email' => $data['member_email'],
                '_chamberboss_member_phone' => $data['member_phone'] ?? '',
                '_chamberboss_member_company' => $data['member_company'] ?? '',
                '_chamberboss_member_address' => $data['member_address'] ?? '',
                '_chamberboss_member_website' => $data['member_website'] ?? '',
                '_chamberboss_subscription_status' => 'inactive'
            ]
        ]);
        
        if (is_wp_error($member_id)) {
            $this->send_json_response(['message' => 'Failed to create member'], false);
            return;
        }
        
        // Trigger member registration action
        do_action('chamberboss_member_registered', $member_id);
        
        $this->send_json_response([
            'message' => 'Registration successful',
            'member_id' => $member_id
        ]);
    }
    
    /**
     * Handle listing submission AJAX
     */
    public function handle_listing_submission() {
        if (!$this->verify_nonce($_POST['listing_nonce'] ?? '', 'chamberboss_listing_submission')) {
            $this->send_json_response(['message' => 'Invalid nonce'], false);
            return;
        }
        
        $data = $this->sanitize_input($_POST);
        
        // Validate required fields
        if (empty($data['listing_title']) || empty($data['listing_description'])) {
            $this->send_json_response(['message' => 'Business name and description are required'], false);
            return;
        }
        
        // Create listing post
        $listing_id = wp_insert_post([
            'post_type' => 'chamberboss_listing',
            'post_title' => $data['listing_title'],
            'post_content' => $data['listing_description'],
            'post_status' => 'pending', // Require admin approval
            'post_author' => $this->get_current_user_id(),
            'meta_input' => [
                '_chamberboss_listing_phone' => $data['listing_phone'] ?? '',
                '_chamberboss_listing_address' => $data['listing_address'] ?? '',
                '_chamberboss_listing_website' => $data['listing_website'] ?? '',
                '_chamberboss_listing_category' => $data['listing_category'] ?? '',
                '_chamberboss_listing_featured' => '0'
            ]
        ]);
        
        if (is_wp_error($listing_id)) {
            $this->send_json_response(['message' => 'Failed to create listing'], false);
            return;
        }
        
        // Handle image upload if present
        if (!empty($_FILES['listing_image']['name'])) {
            $this->handle_listing_image_upload($listing_id);
        }
        
        // Trigger listing submission action
        do_action('chamberboss_listing_submitted', $listing_id);
        
        $this->send_json_response([
            'message' => 'Listing submitted successfully and is pending approval',
            'listing_id' => $listing_id
        ]);
    }
    
    /**
     * Handle listing image upload
     * @param int $listing_id
     */
    private function handle_listing_image_upload($listing_id) {
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        
        $upload = wp_handle_upload($_FILES['listing_image'], ['test_form' => false]);
        
        if (!isset($upload['error']) && isset($upload['file'])) {
            $attachment_id = wp_insert_attachment([
                'post_mime_type' => $upload['type'],
                'post_title' => sanitize_file_name(basename($upload['file'])),
                'post_content' => '',
                'post_status' => 'inherit'
            ], $upload['file'], $listing_id);
            
            if (!is_wp_error($attachment_id)) {
                require_once(ABSPATH . 'wp-admin/includes/image.php');
                wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, $upload['file']));
                set_post_thumbnail($listing_id, $attachment_id);
            }
        }
    }
    
    /**
     * Check if current user is an active member
     * @return bool
     */
    private function is_member_active() {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $user_id = $this->get_current_user_id();
        $user = get_user_by('id', $user_id);
        
        if (!$user) {
            return false;
        }
        
        // Find member by email
        $members = get_posts([
            'post_type' => 'chamberboss_member',
            'meta_query' => [
                [
                    'key' => '_chamberboss_member_email',
                    'value' => $user->user_email,
                    'compare' => '='
                ],
                [
                    'key' => '_chamberboss_subscription_status',
                    'value' => 'active',
                    'compare' => '='
                ]
            ]
        ]);
        
        return !empty($members);
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        wp_enqueue_style(
            'chamberboss-frontend',
            CHAMBERBOSS_PLUGIN_URL . 'assets/css/frontend.css',
            [],
            CHAMBERBOSS_VERSION
        );
        
        wp_enqueue_script(
            'chamberboss-frontend',
            CHAMBERBOSS_PLUGIN_URL . 'assets/js/frontend.js',
            ['jquery'],
            CHAMBERBOSS_VERSION,
            true
        );
        
        wp_localize_script('chamberboss-frontend', 'chamberboss_frontend', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('chamberboss_frontend'),
            'strings' => [
                'processing' => __('Processing...', 'chamberboss'),
                'error' => __('An error occurred. Please try again.', 'chamberboss'),
                'success' => __('Success!', 'chamberboss'),
            ]
        ]);
    }
    
    /**
     * Custom template include for business listings
     * @param string $template
     * @return string
     */
    public function custom_template_include($template) {
        if (is_post_type_archive('chamberboss_listing') || is_singular('chamberboss_listing')) {
            $custom_template = CHAMBERBOSS_PLUGIN_DIR . 'templates/' . basename($template);
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }
        
        return $template;
    }
    
    /**
     * Modify listings query
     * @param \WP_Query $query
     */
    public function modify_listings_query($query) {
        if (!is_admin() && $query->is_main_query() && is_post_type_archive('chamberboss_listing')) {
            $query->set('orderby', 'menu_order title');
            $query->set('order', 'ASC');
            $query->set('posts_per_page', 12);
        }
    }
    
    /**
     * Format currency amount
     * @param float $amount
     * @param string $currency
     * @return string
     */
    private function format_currency($amount, $currency = 'USD') {
        $symbols = [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'CAD' => 'C$',
            'AUD' => 'A$'
        ];
        
        $symbol = $symbols[$currency] ?? $currency . ' ';
        
        return $symbol . number_format($amount, 2);
    }
}

