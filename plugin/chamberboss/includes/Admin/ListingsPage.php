<?php
namespace Chamberboss\Admin;

use Chamberboss\Core\BaseClass;
use Chamberboss\Core\Database;

/**
 * Business Listings Admin Page
 */
class ListingsPage extends BaseClass {

    private $database;
    
    /**
     * Initialize listings page
     */
    protected function init() {
        $this->database = new Database();
        // Handle form submissions
        add_action('admin_post_add_new_listing', [$this, 'process_add_listing']);
        add_action('admin_post_chamberboss_quick_action', [$this, 'process_quick_actions']);
        add_action('admin_post_chamberboss_feature_toggle', [$this, 'process_quick_actions']);
        add_action('admin_init', [$this, 'handle_bulk_actions']); // Keep existing bulk actions on admin_init
    }
    
    /**
     * Render listings page
     */
    public function render() {
        $action = $_GET['action'] ?? 'list';
        $listing_id = intval($_GET['listing_id'] ?? 0);
        
        switch ($action) {
            case 'add':
                $this->render_add_listing();
                break;
            case 'view':
                $this->render_view_listing($listing_id);
                break;
            default:
                $this->render_listings_list();
        }
    }
    
    /**
     * Render listings list
     */
    private function render_listings_list() {
        $status_filter = $_GET['post_status'] ?? 'all';
        $search = $_GET['s'] ?? '';
        $paged = intval($_GET['paged'] ?? 1);
        $per_page = 20;
        
        $listings = $this->get_listings($status_filter, $search, $paged, $per_page);
        $total_listings = $this->get_listings_count($status_filter, $search);
        $total_pages = ceil($total_listings / $per_page);
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e('Business Listings', 'chamberboss'); ?></h1>
            <a href="<?php echo admin_url('admin.php?page=chamberboss-listings&action=add'); ?>" class="page-title-action">
                <?php _e('Add New Listing', 'chamberboss'); ?>
            </a>
            
            <!-- Status Filters -->
            <div class="chamberboss-filters">
                <ul class="subsubsub">
                    <li>
                        <a href="<?php echo admin_url('admin.php?page=chamberboss-listings'); ?>" 
                           class="<?php echo $status_filter === 'all' ? 'current' : ''; ?>">
                            <?php _e('All', 'chamberboss'); ?> 
                            <span class="count">(<?php echo $this->get_listings_count('all'); ?>)</span>
                        </a> |
                    </li>
                    <li>
                        <a href="<?php echo admin_url('admin.php?page=chamberboss-listings&post_status=publish'); ?>" 
                           class="<?php echo $status_filter === 'publish' ? 'current' : ''; ?>">
                            <?php _e('Published', 'chamberboss'); ?> 
                            <span class="count">(<?php echo $this->get_listings_count('publish'); ?>)</span>
                        </a> |
                    </li>
                    <li>
                        <a href="<?php echo admin_url('admin.php?page=chamberboss-listings&post_status=pending'); ?>" 
                           class="<?php echo $status_filter === 'pending' ? 'current' : ''; ?>">
                            <?php _e('Pending', 'chamberboss'); ?> 
                            <span class="count">(<?php echo $this->get_listings_count('pending'); ?>)</span>
                        </a> |
                    </li>
                    <li>
                        <a href="<?php echo admin_url('admin.php?page=chamberboss-listings&post_status=featured'); ?>" 
                           class="<?php echo $status_filter === 'featured' ? 'current' : ''; ?>">
                            <?php _e('Featured', 'chamberboss'); ?> 
                            <span class="count">(<?php echo $this->get_listings_count('featured'); ?>)</span>
                        </a>
                    </li>
                </ul>
                
                <!-- Search -->
                <form method="get" class="search-form">
                    <input type="hidden" name="page" value="chamberboss-listings">
                    <?php if ($status_filter !== 'all'): ?>
                        <input type="hidden" name="post_status" value="<?php echo esc_attr($status_filter); ?>">
                    <?php endif; ?>
                    <p class="search-box">
                        <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php _e('Search listings...', 'chamberboss'); ?>">
                        <input type="submit" class="button" value="<?php _e('Search', 'chamberboss'); ?>">
                    </p>
                </form>
            </div>
            
            <!-- Bulk Actions -->
            <form method="post" id="listings-bulk-form">
                <?php wp_nonce_field('chamberboss_bulk_listings', 'bulk_nonce'); ?>
                <div class="tablenav top">
                    <div class="alignleft actions bulkactions">
                        <select name="bulk_action">
                            <option value=""><?php _e('Bulk Actions', 'chamberboss'); ?></option>
                            <option value="publish"><?php _e('Publish', 'chamberboss'); ?></option>
                            <option value="unpublish"><?php _e('Unpublish', 'chamberboss'); ?></option>
                            <option value="feature"><?php _e('Mark as Featured', 'chamberboss'); ?></option>
                            <option value="unfeature"><?php _e('Remove Featured', 'chamberboss'); ?></option>
                            <option value="delete"><?php _e('Delete', 'chamberboss'); ?></option>
                        </select>
                        <input type="submit" class="button action" value="<?php _e('Apply', 'chamberboss'); ?>">
                    </div>
                </div>
                
                <!-- Listings Table -->
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <td class="manage-column check-column">
                                <input type="checkbox" id="cb-select-all">
                            </td>
                            <th scope="col" class="manage-column"><?php _e('Business Name', 'chamberboss'); ?></th>
                            <th scope="col" class="manage-column"><?php _e('Category', 'chamberboss'); ?></th>
                            <th scope="col" class="manage-column"><?php _e('Author', 'chamberboss'); ?></th>
                            <th scope="col" class="manage-column"><?php _e('Status', 'chamberboss'); ?></th>
                            <th scope="col" class="manage-column"><?php _e('Date', 'chamberboss'); ?></th>
                            <th scope="col" class="manage-column"><?php _e('Actions', 'chamberboss'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($listings)): ?>
                            <?php foreach ($listings as $listing): ?>
                                <?php
                                $category = get_post_meta($listing->ID, '_chamberboss_listing_category', true);
                                $featured = get_post_meta($listing->ID, '_chamberboss_listing_featured', true);
                                $author = get_user_by('id', $listing->post_author);
                                ?>
                                <tr>
                                    <th scope="row" class="check-column">
                                        <input type="checkbox" name="listing_ids[]" value="<?php echo $listing->ID; ?>">
                                    </th>
                                    <td>
                                        <strong>
                                            <a href="<?php echo admin_url('admin.php?page=chamberboss-listings&action=view&listing_id=' . $listing->ID); ?>">
                                                <?php echo esc_html($listing->post_title); ?> 
                                                (<?php echo $this->get_status_badge($listing->post_status); ?>)
                                            </a>
                                        </strong>
                                        <?php if ($featured): ?>
                                            <span class="chamberboss-badge chamberboss-badge-warning"><?php _e('Featured', 'chamberboss'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $category ? esc_html(ucfirst($category)) : '—'; ?></td>
                                    <td><?php echo $author ? esc_html($author->display_name) : '—'; ?></td>
                                    <td><?php echo $this->get_status_badge($listing->post_status); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($listing->post_date)); ?></td>
                                    <td>
                                        <a href="<?php echo admin_url('admin.php?page=chamberboss-listings&action=view&listing_id=' . $listing->ID); ?>" 
                                           class="button button-small"><?php _e('View', 'chamberboss'); ?></a>
                                        
                                        <?php if ($listing->post_status === 'publish'): ?>
                                            <a href="<?php echo get_permalink($listing->ID); ?>" 
                                               class="button button-small" target="_blank"><?php _e('View Live', 'chamberboss'); ?></a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7"><?php _e('No business listings found.', 'chamberboss'); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </form>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <?php
                        $pagination_args = [
                            'base' => admin_url('admin.php?page=chamberboss-listings&%_%'),
                            'format' => '&paged=%#%',
                            'current' => $paged,
                            'total' => $total_pages,
                            'prev_text' => '‹',
                            'next_text' => '›',
                        ];
                        
                        if ($status_filter !== 'all') {
                            $pagination_args['base'] .= '&post_status=' . $status_filter;
                        }
                        
                        if ($search) {
                            $pagination_args['base'] .= '&s=' . urlencode($search);
                        }
                        
                        echo paginate_links($pagination_args);
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Select all checkbox functionality
            $('#cb-select-all').on('change', function() {
                $('input[name="listing_ids[]"]').prop('checked', this.checked);
            });
            
            // Bulk form confirmation
            $('#listings-bulk-form').on('submit', function(e) {
                var action = $('select[name="bulk_action"]').val();
                var selected = $('input[name="listing_ids[]"]:checked').length;
                
                if (!action) {
                    e.preventDefault();
                    alert('<?php _e('Please select a bulk action.', 'chamberboss'); ?>');
                    return false;
                }
                
                if (selected === 0) {
                    e.preventDefault();
                    alert('<?php _e('Please select at least one listing.', 'chamberboss'); ?>');
                    return false;
                }
                
                if (action === 'delete') {
                    if (!confirm('<?php _e('Are you sure you want to delete the selected listings? This action cannot be undone.', 'chamberboss'); ?>')) {
                        e.preventDefault();
                        return false;
                    }
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render add new listing page
     */
    private function render_add_listing() {
        // Default values for the form
        $listing_title = '';
        $listing_description = '';
        $listing_phone = '';
        $listing_address = '';
        $listing_website = '';
        $listing_category = '';
        $listing_featured = '0';

        $categories = $this->database->get_listing_categories();
        
        ?>
        <div class="wrap">
            <h1><?php _e('Add New Business Listing', 'chamberboss'); ?></h1>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('chamberboss_add_listing_action', 'add_listing_nonce'); ?>
                <input type="hidden" name="action" value="add_new_listing">
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="listing_title"><?php _e('Business Name', 'chamberboss'); ?></label></th>
                        <td><input type="text" name="listing_title" id="listing_title" class="regular-text" value="<?php echo esc_attr($listing_title); ?>" required /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="listing_description"><?php _e('Description', 'chamberboss'); ?></label></th>
                        <td><textarea name="listing_description" id="listing_description" class="large-text" rows="5" required><?php echo esc_textarea($listing_description); ?></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="listing_phone"><?php _e('Phone', 'chamberboss'); ?></label></th>
                        <td><input type="text" name="listing_phone" id="listing_phone" class="regular-text" value="<?php echo esc_attr($listing_phone); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="listing_address"><?php _e('Address', 'chamberboss'); ?></label></th>
                        <td><textarea name="listing_address" id="listing_address" class="large-text" rows="3"><?php echo esc_textarea($listing_address); ?></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="listing_website"><?php _e('Website', 'chamberboss'); ?></label></th>
                        <td><input type="url" name="listing_website" id="listing_website" class="regular-text" value="<?php echo esc_attr($listing_website); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="listing_category"><?php _e('Category', 'chamberboss'); ?></label></th>
                        <td>
                            <select name="listing_category" id="listing_category">
                                <option value=""><?php _e('Select a category', 'chamberboss'); ?></option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo esc_attr($category->slug); ?>" <?php selected($listing_category, $category->slug); ?>><?php echo esc_html($category->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="listing_featured"><?php _e('Featured Listing', 'chamberboss'); ?></label></th>
                        <td><input type="checkbox" name="listing_featured" id="listing_featured" value="1" <?php checked($listing_featured, '1'); ?> /></td>
                    </tr>
                </table>
                
                <?php submit_button('Add Listing'); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render listing view page
     */
    private function render_view_listing($listing_id) {
        $listing = get_post($listing_id);
        
        if (!$listing || $listing->post_type !== 'chamberboss_listing') {
            wp_die(__('Listing not found.', 'chamberboss'));
        }
        
        // Get listing meta
        $phone = get_post_meta($listing_id, '_chamberboss_listing_phone', true);
        $address = get_post_meta($listing_id, '_chamberboss_listing_address', true);
        $website = get_post_meta($listing_id, '_chamberboss_listing_website', true);
        $category = get_post_meta($listing_id, '_chamberboss_listing_category', true);
        $featured = get_post_meta($listing_id, '_chamberboss_listing_featured', true);
        $author = get_user_by('id', $listing->post_author);
        $thumbnail = get_the_post_thumbnail($listing_id, 'medium');
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html($listing->post_title); ?></h1>
            
            <div class="chamberboss-listing-view">
                <div class="listing-info-grid">
                    <!-- Listing Details -->
                    <div class="listing-details-card">
                        <h2><?php _e('Business Details', 'chamberboss'); ?></h2>
                        
                        <?php if ($thumbnail): ?>
                            <div class="listing-thumbnail">
                                <?php echo $thumbnail; ?>
                            </div>
                        <?php endif; ?>
                        
                        <table class="form-table">
                            <tr>
                                <th><?php _e('Business Name', 'chamberboss'); ?></th>
                                <td><?php echo esc_html($listing->post_title); ?></td>
                            </tr>
                            <tr>
                                <th><?php _e('Description', 'chamberboss'); ?></th>
                                <td><?php echo wpautop(esc_html($listing->post_content)); ?></td>
                            </tr>
                            <?php if ($category): ?>
                            <tr>
                                <th><?php _e('Category', 'chamberboss'); ?></th>
                                <td><?php echo esc_html(ucfirst($category)); ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if ($phone): ?>
                            <tr>
                                <th><?php _e('Phone', 'chamberboss'); ?></th>
                                <td><a href="tel:<?php echo esc_attr($phone); ?>"><?php echo esc_html($phone); ?></a></td>
                            </tr>
                            <?php endif; ?>
                            <?php if ($address): ?>
                            <tr>
                                <th><?php _e('Address', 'chamberboss'); ?></th>
                                <td><?php echo nl2br(esc_html($address)); ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if ($website): ?>
                            <tr>
                                <th><?php _e('Website', 'chamberboss'); ?></th>
                                <td><a href="<?php echo esc_url($website); ?>" target="_blank"><?php echo esc_html($website); ?></a></td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <th><?php _e('Featured', 'chamberboss'); ?></th>
                                <td><?php echo $featured ? __('Yes', 'chamberboss') : __('No', 'chamberboss'); ?></td>
                            </tr>
                            <tr>
                                <th><?php _e('Submitted By', 'chamberboss'); ?></th>
                                <td><?php echo $author ? esc_html($author->display_name) : __('Unknown', 'chamberboss'); ?></td>
                            </tr>
                            <tr>
                                <th><?php _e('Submission Date', 'chamberboss'); ?></th>
                                <td><?php echo date('F j, Y g:i A', strtotime($listing->post_date)); ?></td>
                            </tr>
                        </table>
                        
                        <p>
                            <a href="<?php echo get_edit_post_link($listing_id); ?>" class="button button-primary">
                                <?php _e('Edit Listing', 'chamberboss'); ?>
                            </a>
                            <?php if ($listing->post_status === 'publish'): ?>
                                <a href="<?php echo get_permalink($listing->ID); ?>" class="button" target="_blank">
                                    <?php _e('View Live', 'chamberboss'); ?>
                                </a>
                            <?php endif; ?>
                            <a href="<?php echo admin_url('admin.php?page=chamberboss-listings'); ?>" class="button">
                                <?php _e('Back to Listings', 'chamberboss'); ?>
                            </a>
                        </p>
                    </div>
                    
                    <!-- Status and Actions -->
                    <div class="listing-status-card">
                        <h2><?php _e('Status & Actions', 'chamberboss'); ?></h2>
                        
                        <div class="status-display">
                            <p><strong><?php _e('Current Status:', 'chamberboss'); ?></strong></p>
                            <p><?php echo $this->get_status_badge($listing->post_status); ?></p>
                        </div>
                        
                        <?php if ($listing->post_status === 'pending'): ?>
                            <div class="quick-actions">
                                <h3><?php _e('Quick Actions', 'chamberboss'); ?></h3>
                                <form method="post" style="margin-bottom: 10px;" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                    <?php wp_nonce_field('chamberboss_quick_action', 'quick_action_nonce'); ?>
                                    <input type="hidden" name="action" value="chamberboss_quick_action">
                                    <input type="hidden" name="listing_id" value="<?php echo $listing_id; ?>">
                                    <input type="hidden" name="quick_action" value="publish">
                                    <button type="submit" class="button button-primary">
                                        <?php _e('Approve & Publish', 'chamberboss'); ?>
                                    </button>
                                </form>
                                
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                    <?php wp_nonce_field('chamberboss_quick_action', 'quick_action_nonce'); ?>
                                    <input type="hidden" name="action" value="chamberboss_quick_action">
                                    <input type="hidden" name="listing_id" value="<?php echo $listing_id; ?>">
                                    <input type="hidden" name="quick_action" value="reject">
                                    <button type="submit" class="button chamberboss-button-danger" 
                                            onclick="return confirm('<?php _e('Are you sure you want to reject this listing?', 'chamberboss'); ?>')">
                                        <?php _e('Reject', 'chamberboss'); ?>
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                        
                        <div class="feature-toggle">
                            <h3><?php _e('Featured Status', 'chamberboss'); ?></h3>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <?php wp_nonce_field('chamberboss_feature_toggle', 'feature_toggle_nonce'); ?>
                                <input type="hidden" name="action" value="chamberboss_feature_toggle">
                                <input type="hidden" name="listing_id" value="<?php echo $listing_id; ?>">
                                <input type="hidden" name="feature_action" value="<?php echo $featured ? 'unfeature' : 'feature'; ?>">
                                <button type="submit" class="button">
                                    <?php echo $featured ? __('Remove Featured', 'chamberboss') : __('Mark as Featured', 'chamberboss'); ?>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Process add new listing submission
     */
    public function process_add_listing() {
        error_log('[Chamberboss Debug] process_add_listing called.');
        error_log('[Chamberboss Debug] $_POST data: ' . print_r($_POST, true));

        if (!isset($_POST['action']) || $_POST['action'] !== 'add_new_listing' || !isset($_POST['add_listing_nonce'])) {
            error_log('[Chamberboss Debug] Missing action, incorrect action, or missing nonce for add_new_listing.');
            return;
        }

        if (!wp_verify_nonce($_POST['add_listing_nonce'], 'chamberboss_add_listing_action')) {
            error_log('[Chamberboss Debug] Nonce verification failed for add_new_listing.');
            wp_die(__('Security check failed', 'chamberboss'));
        }

        $data = $this->sanitize_input($_POST);
        error_log('[Chamberboss Debug] Sanitized listing data: ' . print_r($data, true));

        // Validate required fields
        if (empty($data['listing_title']) || empty($data['listing_description'])) {
            error_log('[Chamberboss Debug] Listing title or description is empty.');
            add_settings_error('chamberboss_listings', 'add_listing_error', __('Business name and description are required.', 'chamberboss'), 'error');
            return;
        }

        // Create listing post
        $listing_id = wp_insert_post([
            'post_type' => 'chamberboss_listing',
            'post_title' => $data['listing_title'],
            'post_content' => $data['listing_description'],
            'post_status' => 'pending', // Require admin approval
            'post_author' => get_current_user_id(),
            'meta_input' => [
                '_chamberboss_listing_phone' => $data['listing_phone'] ?? '',
                '_chamberboss_listing_address' => $data['listing_address'] ?? '',
                '_chamberboss_listing_website' => $data['listing_website'] ?? '',
                '_chamberboss_listing_category' => $data['listing_category'] ?? '',
                '_chamberboss_listing_featured' => isset($data['listing_featured']) ? '1' : '0'
            ]
        ]);
        error_log('[Chamberboss Debug] wp_insert_post returned: ' . print_r($listing_id, true));

        if (is_wp_error($listing_id)) {
            add_settings_error('chamberboss_listings', 'add_listing_error', __('Failed to create listing.', 'chamberboss'), 'error');
            return;
        }

        add_settings_error('chamberboss_listings', 'add_listing_success', __('Business listing added successfully! It is currently pending approval.', 'chamberboss'), 'updated');
        wp_redirect(admin_url('admin.php?page=chamberboss-listings&settings-updated=true'));
        exit;
    }

    /**
     * Process quick actions (approve/reject/feature/unfeature)
     */
    public function process_quick_actions() {
        if (!current_user_can('manage_chamberboss_listings')) {
            wp_die(__('You do not have permission to perform this action.', 'chamberboss'));
        }

        $listing_id = intval($_POST['listing_id'] ?? 0);
        $action = sanitize_text_field($_POST['quick_action'] ?? $_POST['feature_action'] ?? '');
        $nonce = sanitize_text_field($_POST['quick_action_nonce'] ?? $_POST['feature_toggle_nonce'] ?? '');

        if (!$listing_id || empty($action) || empty($nonce)) {
            wp_die(__('Invalid request.', 'chamberboss'));
        }

        if (!wp_verify_nonce($nonce, 'chamberboss_quick_action') && !wp_verify_nonce($nonce, 'chamberboss_feature_toggle')) {
            wp_die(__('Security check failed.', 'chamberboss'));
        }

        switch ($action) {
            case 'publish':
                wp_update_post(['ID' => $listing_id, 'post_status' => 'publish']);
                add_settings_error('chamberboss_listings', 'listing_approved', __('Listing approved and published.', 'chamberboss'), 'updated');
                break;
            case 'reject':
                wp_delete_post($listing_id, true); // Permanently delete rejected listing
                add_settings_error('chamberboss_listings', 'listing_rejected', __('Listing rejected and deleted.', 'chamberboss'), 'error');
                break;
            case 'feature':
                update_post_meta($listing_id, '_chamberboss_listing_featured', '1');
                add_settings_error('chamberboss_listings', 'listing_featured', __('Listing marked as featured.', 'chamberboss'), 'updated');
                break;
            case 'unfeature':
                update_post_meta($listing_id, '_chamberboss_listing_featured', '0');
                add_settings_error('chamberboss_listings', 'listing_unfeatured', __('Listing removed from featured.', 'chamberboss'), 'updated');
                break;
        }

        wp_redirect(admin_url('admin.php?page=chamberboss-listings&action=view&listing_id=' . $listing_id . '&settings-updated=true'));
        exit;
    }
    
    /**
     * Handle bulk actions
     */
    public function handle_bulk_actions() {
        if (!isset($_POST['bulk_action']) || !isset($_POST['listing_ids'])) {
            return;
        }
        
        if (!$this->verify_nonce($_POST['bulk_nonce'] ?? '', 'chamberboss_bulk_listings')) {
            return;
        }
        
        if (!$this->user_can('manage_chamberboss_listings')) {
            return;
        }
        
        $action = $_POST['bulk_action'];
        $listing_ids = array_map('intval', $_POST['listing_ids']);
        
        foreach ($listing_ids as $listing_id) {
            switch ($action) {
                case 'publish':
                    wp_update_post(['ID' => $listing_id, 'post_status' => 'publish']);
                    break;
                case 'unpublish':
                    wp_update_post(['ID' => $listing_id, 'post_status' => 'draft']);
                    break;
                case 'feature':
                    update_post_meta($listing_id, '_chamberboss_listing_featured', '1');
                    break;
                case 'unfeature':
                    update_post_meta($listing_id, '_chamberboss_listing_featured', '0');
                    break;
                case 'delete':
                    wp_delete_post($listing_id, true);
                    break;
            }
        }
        
        wp_redirect(admin_url('admin.php?page=chamberboss-listings&bulk_updated=' . count($listing_ids)));
        exit;
    }
    
    /**
     * Get listings based on filters
     */
    private function get_listings($status_filter = 'all', $search = '', $paged = 1, $per_page = 20) {
        $args = [
            'post_type' => 'chamberboss_listing',
            'posts_per_page' => $per_page,
            'paged' => $paged,
            'orderby' => 'date',
            'order' => 'DESC'
        ];
        
        if ($search) {
            $args['s'] = $search;
        }
        
        switch ($status_filter) {
            case 'publish':
                $args['post_status'] = 'publish';
                break;
            case 'pending':
                $args['post_status'] = 'pending';
                break;
            case 'featured':
                $args['post_status'] = 'publish';
                $args['meta_query'] = [
                    [
                        'key' => '_chamberboss_listing_featured',
                        'value' => '1',
                        'compare' => '='
                    ]
                ];
                break;
            default:
                $args['post_status'] = ['publish', 'pending', 'draft'];
        }
        
        return get_posts($args);
    }
    
    /**
     * Get listings count
     */
    private function get_listings_count($status_filter = 'all', $search = '') {
        global $wpdb;
        
        $sql = "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p";
        $where = ["p.post_type = 'chamberboss_listing'"];
        
        if ($search) {
            $where[] = $wpdb->prepare("p.post_title LIKE %s", '%' . $wpdb->esc_like($search) . '%');
        }
        
        switch ($status_filter) {
            case 'publish':
                $where[] = "p.post_status = 'publish'";
                break;
            case 'pending':
                $where[] = "p.post_status = 'pending'";
                break;
            case 'featured':
                $sql .= " LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id";
                $where[] = "p.post_status = 'publish'";
                $where[] = "pm.meta_key = '_chamberboss_listing_featured' AND pm.meta_value = '1'";
                break;
            default:
                $where[] = "p.post_status IN ('publish', 'pending', 'draft')";
        }
        
        $sql .= " WHERE " . implode(' AND ', $where);
        
        return intval($wpdb->get_var($sql));
    }

    }

