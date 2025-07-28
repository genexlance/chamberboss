<?php
namespace Chamberboss\Public;

use Chamberboss\Core\BaseClass;
use Chamberboss\Core\Database;

class MemberDashboard extends BaseClass {
    private $database;

    protected function init() {
        $this->database = new Database();
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_shortcode('chamberboss_member_dashboard', [$this, 'render_dashboard']);
        add_action('template_redirect', [$this, 'handle_form_submissions']);
    }

    public function enqueue_scripts() {
        if (is_page('member-dashboard')) { // Assuming a page with slug 'member-dashboard'
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
        }
    }

    public function render_dashboard() {
        if (!is_user_logged_in()) {
            return $this->render_login_and_signup_forms();
        }

        $current_user = wp_get_current_user();
        $member_id = $current_user->ID;

        $action = $_GET['action'] ?? 'profile';

        ob_start();
        ?>
        <div class="chamberboss-member-dashboard">
            <h1><?php _e('Member Dashboard', 'chamberboss'); ?></h1>
            <div class="dashboard-navigation">
                <ul>
                    <li><a href="<?php echo esc_url(add_query_arg('action', 'profile', get_permalink())); ?>" class="<?php echo ($action === 'profile') ? 'active' : ''; ?>"><?php _e('My Profile', 'chamberboss'); ?></a></li>
                    <li><a href="<?php echo esc_url(add_query_arg('action', 'listings', get_permalink())); ?>" class="<?php echo ($action === 'listings') ? 'active' : ''; ?>"><?php _e('My Listings', 'chamberboss'); ?></a></li>
                </ul>
            </div>

            <div class="dashboard-content">
                <?php
                switch ($action) {
                    case 'profile':
                        $this->render_profile_editor($member_id);
                        break;
                    case 'listings':
                        $this->render_member_listings($member_id);
                        break;
                    case 'edit_listing':
                        $listing_id = intval($_GET['listing_id'] ?? 0);
                        $this->render_edit_listing($member_id, $listing_id);
                        break;
                    default:
                        $this->render_profile_editor($member_id);
                }
                ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_profile_editor($user_id) {
        $user = get_userdata($user_id);
        if (!$user) {
            echo '<p>' . __('User not found.', 'chamberboss') . '</p>';
            return;
        }

        $is_edit_mode = isset($_GET['edit']) && $_GET['edit'] === 'true';

        // Fetch profile data with debugging
        $first_name = $user->first_name;
        $last_name = $user->last_name;
        $email = $user->user_email;
        $phone = get_user_meta($user_id, '_chamberboss_member_phone', true);
        $company = get_user_meta($user_id, '_chamberboss_member_company', true);
        $address = get_user_meta($user_id, '_chamberboss_member_address', true);
        $website = get_user_meta($user_id, '_chamberboss_member_website', true);
        
        // Debug logging for empty fields
        if (WP_DEBUG) {
            error_log("[ChamberBoss Dashboard] Profile data for user {$user_id}:");
            error_log("  First Name: " . ($first_name ?: 'empty'));
            error_log("  Last Name: " . ($last_name ?: 'empty'));
            error_log("  Phone: " . ($phone ?: 'empty'));
            error_log("  Company: " . ($company ?: 'empty'));
            error_log("  Address: " . ($address ?: 'empty'));
            error_log("  Website: " . ($website ?: 'empty'));
        }

        if ($is_edit_mode) {
            // EDITING VIEW (the form)
            ?>
            <h2><?php _e('Edit My Profile', 'chamberboss'); ?></h2>
            <form method="post" action="<?php echo esc_url(remove_query_arg('edit')); ?>">
                <?php wp_nonce_field('chamberboss_update_profile', 'profile_nonce'); ?>
                <input type="hidden" name="action" value="update_profile">
                <input type="hidden" name="user_id" value="<?php echo esc_attr($user_id); ?>">

                <table class="form-table">
                    <tr>
                        <th><label for="first_name"><?php _e('First Name', 'chamberboss'); ?></label></th>
                        <td><input type="text" name="first_name" id="first_name" value="<?php echo esc_attr($first_name); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th><label for="last_name"><?php _e('Last Name', 'chamberboss'); ?></label></th>
                        <td><input type="text" name="last_name" id="last_name" value="<?php echo esc_attr($last_name); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th><label for="user_email"><?php _e('Email', 'chamberboss'); ?></label></th>
                        <td><input type="email" name="user_email" id="user_email" value="<?php echo esc_attr($email); ?>" class="regular-text" required /></td>
                    </tr>
                    <tr>
                        <th><label for="member_phone"><?php _e('Phone', 'chamberboss'); ?></label></th>
                        <td><input type="text" name="member_phone" id="member_phone" value="<?php echo esc_attr($phone); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th><label for="member_company"><?php _e('Company', 'chamberboss'); ?></label></th>
                        <td><input type="text" name="member_company" id="member_company" value="<?php echo esc_attr($company); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th><label for="member_address"><?php _e('Address', 'chamberboss'); ?></label></th>
                        <td><textarea name="member_address" id="member_address" class="large-text" rows="3"><?php echo esc_textarea($address); ?></textarea></td>
                    </tr>
                    <tr>
                        <th><label for="member_website"><?php _e('Website', 'chamberboss'); ?></label></th>
                        <td><input type="url" name="member_website" id="member_website" value="<?php echo esc_attr($website); ?>" class="regular-text" /></td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php _e('Update Profile', 'chamberboss'); ?>">
                    <a href="<?php echo esc_url(remove_query_arg('edit')); ?>" class="button"><?php _e('Cancel', 'chamberboss'); ?></a>
                </p>
            </form>
            <?php
        } else {
            // VIEWING VIEW (read-only)
            ?>
            <h2><?php _e('My Profile', 'chamberboss'); ?></h2>
            <?php if (isset($_GET['profile_updated']) && $_GET['profile_updated'] === 'true') : ?>
                <div class="chamberboss-notice chamberboss-notice-success" style="margin-bottom: 15px;">
                    <p><?php _e('Profile updated successfully.', 'chamberboss'); ?></p>
                </div>
            <?php endif; ?>
            <a href="<?php echo esc_url(add_query_arg('edit', 'true')); ?>" class="button button-primary" style="margin-bottom: 15px;"><?php _e('Edit Profile', 'chamberboss'); ?></a>
            <table class="form-table">
                <tr>
                    <th><?php _e('First Name', 'chamberboss'); ?></th>
                    <td><?php echo $first_name ? esc_html($first_name) : '<em>Not provided</em>'; ?></td>
                </tr>
                <tr>
                    <th><?php _e('Last Name', 'chamberboss'); ?></th>
                    <td><?php echo $last_name ? esc_html($last_name) : '<em>Not provided</em>'; ?></td>
                </tr>
                <tr>
                    <th><?php _e('Email', 'chamberboss'); ?></th>
                    <td><?php echo esc_html($email); ?></td>
                </tr>
                <tr>
                    <th><?php _e('Phone', 'chamberboss'); ?></th>
                    <td><?php echo $phone ? esc_html($phone) : '<em>Not provided</em>'; ?></td>
                </tr>
                <tr>
                    <th><?php _e('Company', 'chamberboss'); ?></th>
                    <td><?php echo $company ? esc_html($company) : '<em>Not provided</em>'; ?></td>
                </tr>
                <tr>
                    <th><?php _e('Address', 'chamberboss'); ?></th>
                    <td><?php echo $address ? nl2br(esc_html($address)) : '<em>Not provided</em>'; ?></td>
                </tr>
                <tr>
                    <th><?php _e('Website', 'chamberboss'); ?></th>
                    <td><?php echo $website ? '<a href="' . esc_url($website) . '" target="_blank" rel="noopener noreferrer">' . esc_html($website) . '</a>' : '<em>Not provided</em>'; ?></td>
                </tr>
            </table>
            <?php
        }
    }

    private function render_member_listings($user_id) {
        $args = [
            'post_type' => 'chamberboss_listing',
            'author' => $user_id,
            'posts_per_page' => -1,
            'post_status' => ['publish', 'pending', 'draft'],
            'orderby' => 'post_date',
            'order' => 'DESC'
        ];
        $member_listings = get_posts($args);

        ?>
        <h2><?php _e('My Business Listings', 'chamberboss'); ?></h2>
        <a href="<?php echo esc_url(admin_url('post-new.php?post_type=chamberboss_listing')); ?>" class="button button-primary"><?php _e('Add New Listing', 'chamberboss'); ?></a>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Business Name', 'chamberboss'); ?></th>
                    <th><?php _e('Status', 'chamberboss'); ?></th>
                    <th><?php _e('Actions', 'chamberboss'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($member_listings)): ?>
                    <?php foreach ($member_listings as $listing): ?>
                        <tr>
                            <td><?php echo esc_html($listing->post_title); ?></td>
                            <td><?php echo $this->get_status_badge($listing->post_status); ?></td>
                            <td>
                                <a href="<?php echo esc_url(add_query_arg(['action' => 'edit_listing', 'listing_id' => $listing->ID], get_permalink())); ?>" class="button button-small"><?php _e('Edit', 'chamberboss'); ?></a>
                                <a href="<?php echo esc_url(get_permalink($listing->ID)); ?>" class="button button-small" target="_blank"><?php _e('View', 'chamberboss'); ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3"><?php _e('No listings found.', 'chamberboss'); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
    }

    private function render_edit_listing($user_id, $listing_id) {
        $listing = get_post($listing_id);

        if (!$listing || $listing->post_type !== 'chamberboss_listing' || $listing->post_author != $user_id) {
            echo '<p>' . __('Listing not found or you do not have permission to edit this listing.', 'chamberboss') . '</p>';
            return;
        }

        // Get listing meta
        $listing_title = $listing->post_title;
        $listing_description = $listing->post_content;
        $listing_phone = get_post_meta($listing_id, '_chamberboss_listing_phone', true);
        $listing_address = get_post_meta($listing_id, '_chamberboss_listing_address', true);
        $listing_website = get_post_meta($listing_id, '_chamberboss_listing_website', true);
        $listing_category = get_post_meta($listing_id, '_chamberboss_listing_category', true);
        $listing_featured = get_post_meta($listing_id, '_chamberboss_listing_featured', true);

        $categories = $this->database->get_listing_categories();

        ?>
        <h2><?php _e('Edit Business Listing', 'chamberboss'); ?></h2>
        <form method="post" action="<?php echo esc_url(get_permalink()); ?>">
            <?php wp_nonce_field('chamberboss_update_listing', 'listing_nonce'); ?>
            <input type="hidden" name="action" value="update_listing">
            <input type="hidden" name="listing_id" value="<?php echo esc_attr($listing_id); ?>">

            <table class="form-table">
                <tr>
                    <th><label for="listing_title"><?php _e('Business Name', 'chamberboss'); ?></label></th>
                    <td><input type="text" name="listing_title" id="listing_title" value="<?php echo esc_attr($listing_title); ?>" class="regular-text" required /></td>
                </tr>
                <tr>
                    <th><label for="listing_description"><?php _e('Description', 'chamberboss'); ?></label></th>
                    <td><textarea name="listing_description" id="listing_description" class="large-text" rows="5" required><?php echo esc_textarea($listing_description); ?></textarea></td>
                </tr>
                <tr>
                    <th><label for="listing_phone"><?php _e('Phone', 'chamberboss'); ?></label></th>
                    <td><input type="text" name="listing_phone" id="listing_phone" value="<?php echo esc_attr($listing_phone); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th><label for="listing_address"><?php _e('Address', 'chamberboss'); ?></label></th>
                    <td><textarea name="listing_address" id="listing_address" class="large-text" rows="3"><?php echo esc_textarea($listing_address); ?></textarea></td>
                </tr>
                <tr>
                    <th><label for="listing_website"><?php _e('Website', 'chamberboss'); ?></label></th>
                    <td><input type="url" name="listing_website" id="listing_website" value="<?php echo esc_attr($listing_website); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th><label for="listing_category"><?php _e('Category', 'chamberboss'); ?></label></th>
                    <td>
                        <select name="listing_category" id="listing_category">
                            <option value=""><?php _e('Select a category', 'chamberboss'); ?></option>
                            <?php foreach ($categories as $category_obj): ?>
                                <option value="<?php echo esc_attr($category_obj->slug); ?>" <?php selected($listing_category, $category_obj->slug); ?>><?php echo esc_html($category_obj->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="listing_featured"><?php _e('Featured Listing', 'chamberboss'); ?></label></th>
                    <td><input type="checkbox" name="listing_featured" id="listing_featured" value="1" <?php checked($listing_featured, '1'); ?> disabled /> <small><?php _e('(Admins only)', 'chamberboss'); ?></small></td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php _e('Update Listing', 'chamberboss'); ?>">
            </p>
        </form>
        <?php
    }

    /**
     * Render login and signup forms for non-logged-in users
     */
    private function render_login_and_signup_forms() {
        ob_start();
        ?>
        <div class="chamberboss-member-access">
            <!-- Login Form Section -->
            <div class="chamberboss-login-section" id="login-section">
                <h2><?php _e('Member Login', 'chamberboss'); ?></h2>
                <p><?php _e('Please log in to access your member dashboard.', 'chamberboss'); ?></p>
                
                <?php 
                // Show login error messages if any
                if (isset($_GET['login']) && $_GET['login'] === 'failed') {
                    echo '<div class="chamberboss-notice chamberboss-notice-error"><p>' . __('Invalid username or password. Please try again.', 'chamberboss') . '</p></div>';
                }
                ?>
                
                <form id="chamberboss-login-form" method="post" action="<?php echo esc_url(wp_login_url(get_permalink())); ?>">
                    <div class="form-field">
                        <label for="user_login"><?php _e('Username or Email', 'chamberboss'); ?></label>
                        <input type="text" name="log" id="user_login" required>
                    </div>
                    
                    <div class="form-field">
                        <label for="user_pass"><?php _e('Password', 'chamberboss'); ?></label>
                        <input type="password" name="pwd" id="user_pass" required>
                    </div>
                    
                    <div class="form-field checkbox-field">
                        <label>
                            <input type="checkbox" name="rememberme" value="forever">
                            <?php _e('Remember Me', 'chamberboss'); ?>
                        </label>
                    </div>
                    
                    <div class="form-field">
                        <input type="submit" name="wp-submit" value="<?php _e('Log In', 'chamberboss'); ?>" class="button button-primary">
                        <input type="hidden" name="redirect_to" value="<?php echo esc_url(get_permalink()); ?>">
                    </div>
                </form>
                
                <div class="login-extras">
                    <p>
                        <a href="<?php echo esc_url(wp_lostpassword_url(get_permalink())); ?>"><?php _e('Forgot your password?', 'chamberboss'); ?></a>
                    </p>
                    <p class="signup-link">
                        <?php _e('New member?', 'chamberboss'); ?> 
                        <a href="#" id="show-signup-form"><?php _e('Sign up here', 'chamberboss'); ?></a>
                    </p>
                </div>
            </div>

            <!-- Registration Form Section (Hidden by default) -->
            <div class="chamberboss-registration-section" id="registration-section" style="display: none;">
                <div class="registration-header">
                    <h2><?php _e('Member Registration', 'chamberboss'); ?></h2>
                    <p><?php _e('Join our chamber community to access exclusive member benefits and manage your business listings.', 'chamberboss'); ?></p>
                    <p class="back-to-login">
                        <?php _e('Already have an account?', 'chamberboss'); ?> 
                        <a href="#" id="show-login-form"><?php _e('Log in here', 'chamberboss'); ?></a>
                    </p>
                </div>
                
                <?php echo do_shortcode('[chamberboss_member_registration]'); ?>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var loginSection = document.getElementById('login-section');
            var registrationSection = document.getElementById('registration-section');
            var showSignupLink = document.getElementById('show-signup-form');
            var showLoginLink = document.getElementById('show-login-form');
            
            if (showSignupLink) {
                showSignupLink.addEventListener('click', function(e) {
                    e.preventDefault();
                    loginSection.style.display = 'none';
                    registrationSection.style.display = 'block';
                    // Scroll to top of form
                    registrationSection.scrollIntoView({ behavior: 'smooth' });
                });
            }
            
            if (showLoginLink) {
                showLoginLink.addEventListener('click', function(e) {
                    e.preventDefault(); 
                    registrationSection.style.display = 'none';
                    loginSection.style.display = 'block';
                    // Scroll to top of form
                    loginSection.scrollIntoView({ behavior: 'smooth' });
                });
            }
        });
        </script>

        <style>
        .chamberboss-member-access {
            max-width: 600px;
            margin: 0 auto;
        }
        
        .chamberboss-login-section,
        .chamberboss-registration-section {
            background: #fff;
            padding: 30px;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .chamberboss-login-section h2,
        .chamberboss-registration-section h2 {
            margin-top: 0;
            color: #333;
            text-align: center;
        }
        
        .form-field {
            margin-bottom: 20px;
        }
        
        .form-field label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #555;
        }
        
        .form-field input[type="text"],
        .form-field input[type="email"], 
        .form-field input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            box-sizing: border-box;
        }
        
        .form-field input[type="text"]:focus,
        .form-field input[type="email"]:focus,
        .form-field input[type="password"]:focus {
            border-color: #0073aa;
            outline: none;
            box-shadow: 0 0 0 2px rgba(0, 115, 170, 0.1);
        }
        
        .checkbox-field label {
            display: flex;
            align-items: center;
            font-weight: normal;
        }
        
        .checkbox-field input[type="checkbox"] {
            width: auto;
            margin-right: 8px;
        }
        
        .button-primary {
            background: #0073aa;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
        }
        
        .button-primary:hover {
            background: #005a87;
        }
        
        .login-extras {
            text-align: center;
            margin-top: 20px;
        }
        
        .login-extras p {
            margin: 10px 0;
        }
        
        .signup-link {
            padding-top: 10px;
            border-top: 1px solid #eee;
        }
        
        .registration-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .back-to-login {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        
        .chamberboss-notice {
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .chamberboss-notice-error {
            background: #fef7f7;
            border-left: 4px solid #dc3232;
            color: #dc3232;
        }
        
        /* Responsive design */
        @media (max-width: 600px) {
            .chamberboss-login-section,
            .chamberboss-registration-section {
                padding: 20px;
                margin: 0 10px 20px 10px;
            }
        }
        </style>
        <?php
        return ob_get_clean();
    }

    public function handle_form_submissions() {
        if (!is_user_logged_in()) {
            return;
        }

        if (!isset($_POST['action'])) {
            return;
        }

        $current_user = wp_get_current_user();
        $user_id = $current_user->ID;

        switch ($_POST['action']) {
            case 'update_profile':
                if (!isset($_POST['profile_nonce']) || !wp_verify_nonce($_POST['profile_nonce'], 'chamberboss_update_profile')) {
                    wp_die(__('Security check failed.', 'chamberboss'));
                }

                $data = $this->sanitize_input($_POST);

                // Debug logging for update
                if (WP_DEBUG) {
                    error_log("[ChamberBoss Dashboard] Updating profile for user {$user_id}:");
                    error_log("  First Name: " . ($data['first_name'] ?? 'not provided'));
                    error_log("  Last Name: " . ($data['last_name'] ?? 'not provided')); 
                    error_log("  Email: " . ($data['user_email'] ?? 'not provided'));
                    error_log("  Phone: " . ($data['member_phone'] ?? 'not provided'));
                    error_log("  Company: " . ($data['member_company'] ?? 'not provided'));
                    error_log("  Address: " . ($data['member_address'] ?? 'not provided'));
                    error_log("  Website: " . ($data['member_website'] ?? 'not provided'));
                }

                // Update user data
                $user_update_result = wp_update_user([
                    'ID' => $user_id,
                    'first_name' => $data['first_name'] ?? '',
                    'last_name' => $data['last_name'] ?? '',
                    'user_email' => $data['user_email'] ?? $current_user->user_email, // Allow email update
                ]);

                if (is_wp_error($user_update_result)) {
                    error_log('[ChamberBoss Dashboard] User update failed: ' . $user_update_result->get_error_message());
                } else {
                    error_log('[ChamberBoss Dashboard] User data updated successfully');
                }

                // Update user meta
                update_user_meta($user_id, '_chamberboss_member_phone', $data['member_phone'] ?? '');
                update_user_meta($user_id, '_chamberboss_member_company', $data['member_company'] ?? '');
                update_user_meta($user_id, '_chamberboss_member_address', $data['member_address'] ?? '');
                update_user_meta($user_id, '_chamberboss_member_website', $data['member_website'] ?? '');

                wp_redirect(add_query_arg('profile_updated', 'true', get_permalink()));
                exit;

            case 'update_listing':
                if (!isset($_POST['listing_nonce']) || !wp_verify_nonce($_POST['listing_nonce'], 'chamberboss_update_listing')) {
                    wp_die(__('Security check failed.', 'chamberboss'));
                }

                $listing_id = intval($_POST['listing_id'] ?? 0);
                $listing = get_post($listing_id);

                // Ensure user owns the listing
                if (!$listing || $listing->post_author != $user_id) {
                    wp_die(__('You do not have permission to edit this listing.', 'chamberboss'));
                }

                $data = $this->sanitize_input($_POST);

                wp_update_post([
                    'ID' => $listing_id,
                    'post_title' => $data['listing_title'],
                    'post_content' => $data['listing_description'],
                ]);

                update_post_meta($listing_id, '_chamberboss_listing_phone', $data['listing_phone'] ?? '');
                update_post_meta($listing_id, '_chamberboss_listing_address', $data['listing_address'] ?? '');
                update_post_meta($listing_id, '_chamberboss_listing_website', $data['listing_website'] ?? '');
                update_post_meta($listing_id, '_chamberboss_listing_category', $data['listing_category'] ?? '');
                // Featured status can only be changed by admin, so we don't update it here

                wp_redirect(add_query_arg(['action' => 'listings', 'listing_updated' => 'true'], get_permalink()));
                exit;
        }
    }
}
