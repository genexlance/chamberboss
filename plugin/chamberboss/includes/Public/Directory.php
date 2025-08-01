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
        // DEBUG: Log that init is being called
        // Check if Stripe classes are available during init
        // Add shortcodes
        add_shortcode('chamberboss_directory', [$this, 'directory_shortcode']);
        add_shortcode('chamberboss_member_registration', [$this, 'member_registration_shortcode']);
        add_shortcode('chamberboss_listing_form', [$this, 'listing_form_shortcode']);
        
        // Register AJAX handlers
        add_action('wp_ajax_chamberboss_register_member', array($this, 'handle_member_registration'));
        add_action('wp_ajax_nopriv_chamberboss_register_member', array($this, 'handle_member_registration'));
        
        add_action('wp_ajax_chamberboss_create_payment_intent', array($this, 'handle_create_payment_intent'));
        add_action('wp_ajax_nopriv_chamberboss_create_payment_intent', array($this, 'handle_create_payment_intent'));
        
        // Listing submission handler
        add_action('wp_ajax_chamberboss_listing_submission', array($this, 'handle_listing_submission'));
        
        // TEMPORARY - Test AJAX handler
        add_action('wp_ajax_chamberboss_test_ajax', [$this, 'handle_test_ajax']);
        add_action('wp_ajax_nopriv_chamberboss_test_ajax', [$this, 'handle_test_ajax']);

        
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
        // FORCE DEBUG - Log every time shortcode is called
        error_log('=== CHAMBERBOSS SHORTCODE CALLED === at ' . current_time('mysql'));
        
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
                    <div class="category-navigation">
                        <div class="category-buttons">
                            <a href="<?php echo esc_url(remove_query_arg('directory_category')); ?>" 
                               class="category-button <?php echo empty($category_filter) ? 'active' : ''; ?>">
                                <?php _e('All Categories', 'chamberboss'); ?>
                            </a>
                            <?php foreach ($categories as $category_obj): ?>
                                <a href="<?php echo esc_url(add_query_arg('directory_category', $category_obj->slug)); ?>" 
                                   class="category-button <?php echo ($category_filter === $category_obj->slug) ? 'active' : ''; ?>">
                                    <?php echo esc_html($category_obj->name); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
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
        $thumbnail = get_the_post_thumbnail($listing_id, 'large');
        
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
                        <?php echo wp_kses_post($description); ?>
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
        // FORCE VISIBLE DEBUG - This MUST show up
        error_log('=== CHAMBERBOSS REGISTRATION FORM CALLED ===');
        
        if (is_user_logged_in()) {
            echo '<p>' . __('Welcome.', 'chamberboss') . '</p>';
            return;
        }
        
        // Check if Stripe is configured for payment processing
        $stripe_config = new \Chamberboss\Payments\StripeConfig();
        $payment_enabled = $args['show_payment'] && $stripe_config->is_configured();
        
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
                
                <?php if ($payment_enabled): ?>
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
                <?php elseif ($args['show_payment']): ?>
                <div class="form-section">
                    <div class="stripe-not-configured-notice">
                        <p><em><?php _e('Payment processing is not configured. Registration is currently free.', 'chamberboss'); ?></em></p>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- TEMPORARY DEBUG INFO -->
                <div style="background: #fff3cd; padding: 10px; margin: 10px 0; border: 1px solid #ffeaa7;">
                    <strong>🔧 DEBUG INFO (Version 1.0.1):</strong><br>
                    Form ID: chamberboss-member-registration<br>
                    Stripe Configured: <?php echo $stripe_config->is_configured() ? 'YES' : 'NO'; ?><br>
                    Payment Enabled: <?php echo $payment_enabled ? 'YES' : 'NO'; ?><br>
                    Current Time: <?php echo current_time('mysql'); ?><br>
                    Plugin Version: <?php echo CHAMBERBOSS_VERSION; ?><br>
                    <strong>🚨 If you see this, my updated code IS working!</strong>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="submit-button" <?php echo $payment_enabled ? 'id="submit-payment"' : ''; ?>>
                        <?php echo $payment_enabled ? __('Join & Pay Now', 'chamberboss') : __('Register', 'chamberboss'); ?>
                    </button>
                    
                    <!-- TEMPORARY DEBUG BUTTON -->
                    <button type="button" id="test-ajax-button" style="margin-left: 10px; background: #ccc;">Test AJAX</button>
                    <button type="button" onclick="console.log('Button clicked, jQuery available:', !!window.jQuery)" style="margin-left: 10px; background: #f0f;">Test Console</button>
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
        error_log('[ChamberBoss Registration] Starting registration process');
        
        // TEMPORARY DEBUG - Remove this after testing
        if (defined('WP_DEBUG') && WP_DEBUG) {
            file_put_contents('/tmp/chamberboss_debug.log', date('Y-m-d H:i:s') . " - Registration handler called\n", FILE_APPEND);
        }
        
        // TEMPORARY - Add to WordPress debug log
        if (function_exists('error_log')) {
            error_log('CHAMBERBOSS DEBUG: Frontend registration handler called at ' . current_time('mysql'));
        }
        

        
        if (!$this->verify_nonce($_POST['registration_nonce'] ?? '', 'chamberboss_member_registration')) {
            error_log('[ChamberBoss Registration] Nonce verification failed');
            $this->send_json_response(['message' => 'Invalid nonce'], false);
            return;
        }
        
        $data = $this->sanitize_input($_POST);
        error_log('[ChamberBoss Registration] Form data: ' . print_r($data, true));
        
        // TEMPORARY DEBUG - Add debug info to response
        $debug_info = [
            'handler_called' => true,
            'timestamp' => current_time('mysql'),
            'data_received' => $data
        ];
        
        // Validate required fields
        if (empty($data['member_name']) || empty($data['member_email'])) {
            $this->send_json_response(['message' => 'Name and email are required', 'debug' => $debug_info], false);
            return;
        }
        
        // Check if Stripe is configured and payment is required
        $stripe_config = new \Chamberboss\Payments\StripeConfig();
        $payment_required = $stripe_config->is_configured();
        error_log('[ChamberBoss Registration] Payment required: ' . ($payment_required ? 'YES' : 'NO'));
        
        // Validate payment intent ID if payment is required
        if ($payment_required && empty($data['payment_intent_id'])) {
            error_log('[ChamberBoss Registration] Payment required but payment_intent_id missing');
            error_log('[ChamberBoss Registration] This suggests the frontend payment flow was not triggered');
            error_log('[ChamberBoss Registration] Check: 1) Stripe.js loaded? 2) Payment element rendered? 3) JavaScript errors?');
            $this->send_json_response([
                'message' => 'Payment is required for membership registration. Please ensure JavaScript is enabled and try again.',
                'debug' => 'payment_intent_id_missing'
            ], false);
            return;
        }
        
        // Check if email already exists as WordPress user
        if (email_exists($data['member_email'])) {
            error_log('CHAMBERBOSS: Email exists check - user already exists: ' . $data['member_email']);
            $this->send_json_response(['message' => 'A user with this email already exists'], false);
            return;
        }
        
        // Check if email already exists as member
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
        
        // Verify payment with Stripe if payment is required
        if ($payment_required) {
            $payment_verified = $this->verify_stripe_payment($data['payment_intent_id']);
            
            if (!$payment_verified) {
                $this->send_json_response(['message' => 'Payment verification failed'], false);
                return;
            }
        }
        
        // Parse name into first and last
        $name_parts = explode(' ', trim($data['member_name']), 2);
        $first_name = $name_parts[0];
        $last_name = isset($name_parts[1]) ? $name_parts[1] : '';
        
        // Generate username from email
        $username = sanitize_user(substr($data['member_email'], 0, strpos($data['member_email'], '@')));
        if (username_exists($username)) {
            $username = $username . '_' . wp_rand(100, 999);
        }
        
        // Generate temporary password
        $password = wp_generate_password(12, false);
        
        // Create WordPress user
        error_log('[ChamberBoss Registration] Attempting to create user - Username: ' . $username . ', Email: ' . $data['member_email']);
        $user_id = wp_create_user($username, $password, $data['member_email']);
        
        if (is_wp_error($user_id)) {
            error_log('[ChamberBoss Registration] User creation failed: ' . $user_id->get_error_message());
            $this->send_json_response(['message' => 'Failed to create user account: ' . $user_id->get_error_message(), 'debug' => $debug_info], false);
            return;
        }
        
        error_log('[ChamberBoss Registration] User created successfully - User ID: ' . $user_id);
        
        // Update user data
        error_log('[ChamberBoss Registration] Updating user data and role');
        $user_update_result = wp_update_user([
            'ID' => $user_id,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'display_name' => $data['member_name'],
            'role' => 'chamberboss_member'
        ]);
        
        if (is_wp_error($user_update_result)) {
            error_log('[ChamberBoss Registration] User update failed: ' . $user_update_result->get_error_message());
        } else {
            error_log('[ChamberBoss Registration] User data updated successfully');
        }
        
        // Store additional member data in user meta
        update_user_meta($user_id, '_chamberboss_member_phone', $data['member_phone'] ?? '');
        update_user_meta($user_id, '_chamberboss_member_company', $data['member_company'] ?? '');
        update_user_meta($user_id, '_chamberboss_member_address', $data['member_address'] ?? '');
        update_user_meta($user_id, '_chamberboss_member_website', $data['member_website'] ?? '');
        
        // Store payment info if payment was made
        if ($payment_required && !empty($data['payment_intent_id'])) {
            update_user_meta($user_id, '_chamberboss_stripe_payment_intent', $data['payment_intent_id']);
        }
        
        // Prepare member meta data
        $member_meta = [
            '_chamberboss_member_email' => $data['member_email'],
            '_chamberboss_member_phone' => $data['member_phone'] ?? '',
            '_chamberboss_member_company' => $data['member_company'] ?? '',
            '_chamberboss_member_address' => $data['member_address'] ?? '',
            '_chamberboss_member_website' => $data['member_website'] ?? '',
            '_chamberboss_subscription_status' => 'active',
            '_chamberboss_subscription_start' => current_time('mysql'),
            '_chamberboss_subscription_end' => date('Y-m-d H:i:s', strtotime('+1 year')),
            '_chamberboss_user_id' => $user_id, // Store user ID reference
        ];
        
        // Add payment info if payment was made
        if ($payment_required && !empty($data['payment_intent_id'])) {
            $member_meta['_chamberboss_stripe_payment_intent'] = $data['payment_intent_id'];
        }
        
        // Create member post linked to user
        $member_id = wp_insert_post([
            'post_type' => 'chamberboss_member',
            'post_title' => $data['member_name'],
            'post_status' => 'publish',
            'post_author' => $user_id, // Link to WordPress user
            'meta_input' => $member_meta
        ]);
        
        if (is_wp_error($member_id)) {
            error_log('[ChamberBoss Registration] Member post creation failed: ' . $member_id->get_error_message());
            // If member post creation fails, clean up the user
            wp_delete_user($user_id);
            $this->send_json_response(['message' => 'Failed to create member profile: ' . $member_id->get_error_message(), 'debug' => $debug_info], false);
            return;
        }
        
        error_log('[ChamberBoss Registration] Member post created successfully - Member ID: ' . $member_id);
        
        // Send welcome email with login credentials
        $this->send_welcome_email($user_id, $username, $password, $data['member_email']);
        
        // Trigger member registration action
        do_action('chamberboss_member_registered', $member_id, $user_id);
        
        error_log('[ChamberBoss Registration] Registration completed successfully - User ID: ' . $user_id . ', Member ID: ' . $member_id);
        
        // Send appropriate success message
        $success_message = $payment_required 
            ? 'Registration and payment successful! Welcome email sent with login details.'
            : 'Registration successful! Welcome email sent with login details.';
        
        $this->send_json_response([
            'message' => $success_message,
            'member_id' => $member_id,
            'user_id' => $user_id,
            'redirect_url' => home_url('/members/'),
            'payment_required' => $payment_required,
            'debug' => array_merge($debug_info, [
                'user_created' => $user_id,
                'member_created' => $member_id,
                'username' => $username,
                'email_sent' => true
            ])
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
        // DEBUG: Log that this method is being called

        wp_enqueue_style(
            'chamberboss-frontend',
            CHAMBERBOSS_PLUGIN_URL . 'assets/css/frontend.css',
            [],
            CHAMBERBOSS_VERSION . '-' . time() // Force cache refresh
        );
        
        // Enqueue Stripe.js if needed
        $stripe_config = new \Chamberboss\Payments\StripeConfig();
        if ($stripe_config->is_configured()) {
            wp_enqueue_script(
                'stripe-js',
                'https://js.stripe.com/v3/',
                [],
                null,
                true
            );
        }
        

        wp_enqueue_script(
            'chamberboss-frontend',
            CHAMBERBOSS_PLUGIN_URL . 'assets/js/frontend.js',
            ['jquery'],
            CHAMBERBOSS_VERSION . '-' . time(), // Force cache refresh
            true
        );
        
        // Prepare localization data
        $localize_data = [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('chamberboss_frontend'),
            'strings' => [
                'processing' => __('Processing...', 'chamberboss'),
                'error' => __('An error occurred. Please try again.', 'chamberboss'),
                'success' => __('Success!', 'chamberboss'),
            ]
        ];
        
        // Add Stripe key if configured
        if ($stripe_config->is_configured()) {
            $localize_data['stripe_publishable_key'] = $stripe_config->get_publishable_key();
        }
        wp_localize_script('chamberboss-frontend', 'chamberboss_frontend', $localize_data);
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

    /**
     * Verify Stripe payment intent
     * @param string $payment_intent_id
     * @return bool
     */
    private function verify_stripe_payment($payment_intent_id) {
        try {
            // Check if Stripe SDK is available
            if (!class_exists('\\Stripe\\Stripe')) {
                error_log('Stripe SDK not available for payment verification');
                return false;
            }
            
            $stripe_config = new \Chamberboss\Payments\StripeConfig();
            if (!$stripe_config->is_configured()) {
                error_log('Stripe not configured for payment verification');
                return false;
            }
            
            \Stripe\Stripe::setApiKey($stripe_config->get_secret_key());
            $intent = \Stripe\PaymentIntent::retrieve($payment_intent_id);
            
            return $intent->status === 'succeeded';
        } catch (Exception $e) {
            error_log('Stripe payment verification error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send welcome email to new member
     * @param int $user_id
     * @param string $username
     * @param string $password
     * @param string $email
     */
    private function send_welcome_email($user_id, $username, $password, $email) {
        $user = get_user_by('id', $user_id);
        $display_name = $user ? $user->display_name : 'Member';
        $site_name = get_bloginfo('name');
        $login_url = home_url('/members/');
        
        $subject = sprintf(__('Welcome to %s - Your Membership Account Details', 'chamberboss'), $site_name);
        
        $message = sprintf(__('
Hello %s,

Welcome to %s! Your membership account has been successfully created and activated.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
LOGIN CREDENTIALS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Username: %s
Password: %s
Member Dashboard: %s

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
WHAT YOU CAN DO NOW
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

✓ Log in to your member dashboard at: %s
✓ Complete and update your member profile
✓ Submit your business listings to our directory
✓ Manage your existing business listings
✓ Connect with other chamber members
✓ Access member-only resources and benefits

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
IMPORTANT SECURITY NOTE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

For your security, please log in and change your password as soon as possible.
You can do this from your member dashboard after logging in.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

If you have any questions or need assistance, please don\'t hesitate to contact us.

Thank you for joining our chamber community!

Best regards,
The %s Team
        ', 'chamberboss'), 
            $display_name,
            $site_name,
            $username, 
            $password,
            $login_url,
            $login_url,
            $site_name
        );
        
        $headers = [
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $site_name . ' <' . get_option('admin_email') . '>'
        ];
        
        $email_sent = wp_mail($email, $subject, $message, $headers);
        
        if (!$email_sent) {
            error_log('ChamberBoss: Failed to send welcome email to: ' . $email);
        }
        
        return $email_sent;
    }
    
    /**
     * Handle payment intent creation for registration
     */
    public function handle_create_payment_intent() {
        try {
            if (!$this->verify_nonce($_POST['nonce'] ?? '', 'chamberboss_frontend')) {
                $this->send_json_response(['message' => 'Invalid nonce'], false);
                return;
            }
            
            // Check if this is just for UI setup
            $setup_only = !empty($_POST['setup_only']);
            
            // Get member data from the form (only if not setup_only)
            $member_name = $setup_only ? '' : sanitize_text_field($_POST['member_name'] ?? '');
            $member_email = $setup_only ? '' : sanitize_email($_POST['member_email'] ?? '');
            
            // Get membership price
            $membership_price = floatval($this->get_option('chamberboss_membership_price', '100.00'));
            $currency = $this->get_option('chamberboss_currency', 'USD');
            
            // Create payment intent via Stripe integration
            $stripe_integration = new \Chamberboss\Payments\StripeIntegration();
            
            try {
                // Check if Stripe SDK is available
                if (!class_exists('\\Stripe\\Stripe')) {
                    error_log('Stripe SDK not available for payment intent creation');
                    $this->send_json_response(['message' => 'Payment system not available'], false);
                    return;
                }
                
                $stripe_config = new \Chamberboss\Payments\StripeConfig();
                if (!$stripe_config->is_configured()) {
                    $this->send_json_response(['message' => 'Payment system not configured'], false);
                    return;
                }
                
                \Stripe\Stripe::setApiKey($stripe_config->get_secret_key());
                
                // Create payment intent with member information
                $intent_data = [
                    'amount' => intval($membership_price * 100), // Convert to cents
                    'currency' => strtolower($currency),
                    'automatic_payment_methods' => ['enabled' => true],
                    'metadata' => [
                        'type' => $setup_only ? 'setup_intent' : 'membership_registration'
                    ]
                ];
                
                // Add member data to metadata if available
                if (!$setup_only && !empty($member_name)) {
                    $intent_data['metadata']['member_name'] = $member_name;
                }
                if (!$setup_only && !empty($member_email)) {
                    $intent_data['metadata']['member_email'] = $member_email;
                }
                
                // Create Stripe customer if we have member data (not for setup_only)
                if (!$setup_only && !empty($member_email) && !empty($member_name)) {
                    try {
                        $customer = \Stripe\Customer::create([
                            'email' => $member_email,
                            'name' => $member_name,
                            'metadata' => [
                                'source' => 'chamberboss_registration'
                            ]
                        ]);
                        
                        $intent_data['customer'] = $customer->id;
                        $intent_data['metadata']['stripe_customer_id'] = $customer->id;
                        
                    } catch (Exception $e) {
                        error_log('Customer creation failed: ' . $e->getMessage());
                        // Continue without customer - payment can still work
                    }
                }
                
                $intent = \Stripe\PaymentIntent::create($intent_data);
                
                $this->send_json_response([
                    'clientSecret' => $intent->client_secret,
                    'paymentIntentId' => $intent->id
                ]);
                
            } catch (Exception $e) {
                error_log('Payment intent creation error: ' . $e->getMessage());
                $this->send_json_response(['message' => 'Failed to initialize payment: ' . $e->getMessage()], false);
            }
            
        } catch (Exception $e) {
            error_log('ChamberBoss: Payment intent error: ' . $e->getMessage());
            $this->send_json_response(['message' => 'Payment system error: ' . $e->getMessage()], false);
        }
    }

    /**
     * Handle test AJAX
     */
    public function handle_test_ajax() {
        $this->send_json_response(['message' => 'Test AJAX handler is working!']);
    }
}

