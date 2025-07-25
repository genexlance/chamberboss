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
            return '<p>' . __('Please log in to view your dashboard.', 'chamberboss') . '</p>';
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

        // Get member meta (assuming these are stored as user meta for the member)
        $first_name = get_user_meta($user_id, 'first_name', true) ?: $user->first_name;
        $last_name = get_user_meta($user_id, 'last_name', true) ?: $user->last_name;
        $member_email = get_user_meta($user_id, '_chamberboss_member_email', true) ?: $user->user_email;
        $member_phone = get_user_meta($user_id, '_chamberboss_member_phone', true);
        $member_company = get_user_meta($user_id, '_chamberboss_member_company', true);
        $member_address = get_user_meta($user_id, '_chamberboss_member_address', true);
        $member_website = get_user_meta($user_id, '_chamberboss_member_website', true);
        $member_notes = get_user_meta($user_id, '_chamberboss_member_notes', true);

        ?>
        <h2><?php _e('Edit My Profile', 'chamberboss'); ?></h2>
        <form method="post" action="<?php echo esc_url(get_permalink()); ?>">
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
                    <td><input type="email" name="user_email" id="user_email" value="<?php echo esc_attr($member_email); ?>" class="regular-text" required /></td>
                </tr>
                <tr>
                    <th><label for="member_phone"><?php _e('Phone', 'chamberboss'); ?></label></th>
                    <td><input type="text" name="member_phone" id="member_phone" value="<?php echo esc_attr($member_phone); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th><label for="member_company"><?php _e('Company', 'chamberboss'); ?></label></th>
                    <td><input type="text" name="member_company" id="member_company" value="<?php echo esc_attr($member_company); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th><label for="member_address"><?php _e('Address', 'chamberboss'); ?></label></th>
                    <td><textarea name="member_address" id="member_address" class="large-text" rows="3"><?php echo esc_textarea($member_address); ?></textarea></td>
                </tr>
                <tr>
                    <th><label for="member_website"><?php _e('Website', 'chamberboss'); ?></label></th>
                    <td><input type="url" name="member_website" id="member_website" value="<?php echo esc_attr($member_website); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th><label for="member_notes"><?php _e('Notes', 'chamberboss'); ?></label></th>
                    <td><textarea name="member_notes" id="member_notes" class="large-text" rows="5"><?php echo esc_textarea($member_notes); ?></textarea></td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php _e('Update Profile', 'chamberboss'); ?>">
            </p>
        </form>
        <?php
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

                // Update user data
                wp_update_user([
                    'ID' => $user_id,
                    'first_name' => $data['first_name'] ?? '',
                    'last_name' => $data['last_name'] ?? '',
                    'user_email' => $data['user_email'] ?? $current_user->user_email, // Allow email update
                ]);

                // Update user meta
                update_user_meta($user_id, 'first_name', $data['first_name'] ?? '');
                update_user_meta($user_id, 'last_name', $data['last_name'] ?? '');
                update_user_meta($user_id, '_chamberboss_member_email', $data['user_email'] ?? '');
                update_user_meta($user_id, '_chamberboss_member_phone', $data['member_phone'] ?? '');
                update_user_meta($user_id, '_chamberboss_member_company', $data['member_company'] ?? '');
                update_user_meta($user_id, '_chamberboss_member_address', $data['member_address'] ?? '');
                update_user_meta($user_id, '_chamberboss_member_website', $data['member_website'] ?? '');
                update_user_meta($user_id, '_chamberboss_member_notes', $data['member_notes'] ?? '');

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
