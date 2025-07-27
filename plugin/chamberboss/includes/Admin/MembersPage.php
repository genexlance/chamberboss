<?php
namespace Chamberboss\Admin;

use Chamberboss\Core\BaseClass;
use Chamberboss\Core\Database;

/**
 * Members Admin Page
 */
class MembersPage extends BaseClass {
    
    /**
     * Database instance
     * @var Database
     */
    private $database;
    
    /**
     * Initialize members page
     */
    protected function init() {
        $this->database = new Database();
        
        // Handle form submissions
        add_action('admin_post_add_new_member', [$this, 'process_add_member']);
        add_action('admin_post_edit_member', [$this, 'process_edit_member']);
    }
    
    /**
     * Render members page
     */
    public function render() {
        $action = $_GET['action'] ?? 'list';
        $member_id = intval($_GET['member_id'] ?? 0);
        
        switch ($action) {
            case 'add':
                $this->render_add_member();
                break;
            case 'edit':
                $this->render_edit_member($member_id);
                break;
            case 'view':
                $this->render_view_member($member_id);
                break;
            default:
                $this->render_members_list();
        }
    }
    
    /**
     * Render members list
     */
    private function render_members_list() {
        $filter = $_GET['filter'] ?? 'all';
        $search = $_GET['s'] ?? '';
        $paged = intval($_GET['paged'] ?? 1);
        $per_page = 20;
        
        $members = $this->get_members($filter, $search, $paged, $per_page);
        $total_members = $this->get_members_count($filter, $search);
        $total_pages = ceil($total_members / $per_page);
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e('Members', 'chamberboss'); ?></h1>
            <a href="<?php echo admin_url('admin.php?page=chamberboss-members&action=add'); ?>" class="page-title-action"><?php _e('Add New', 'chamberboss'); ?></a>
            
            <!-- Filters -->
            <div class="chamberboss-filters">
                <ul class="subsubsub">
                    <li>
                        <a href="<?php echo admin_url('admin.php?page=chamberboss-members'); ?>" 
                           class="<?php echo $filter === 'all' ? 'current' : ''; ?>">
                            <?php _e('All', 'chamberboss'); ?> 
                            <span class="count">(<?php echo $this->get_members_count('all'); ?>)</span>
                        </a> |
                    </li>
                    <li>
                        <a href="<?php echo admin_url('admin.php?page=chamberboss-members&filter=active'); ?>" 
                           class="<?php echo $filter === 'active' ? 'current' : ''; ?>">
                            <?php _e('Active', 'chamberboss'); ?> 
                            <span class="count">(<?php echo $this->get_members_count('active'); ?>)</span>
                        </a> |
                    </li>
                    <li>
                        <a href="<?php echo admin_url('admin.php?page=chamberboss-members&filter=expired'); ?>" 
                           class="<?php echo $filter === 'expired' ? 'current' : ''; ?>">
                            <?php _e('Expired', 'chamberboss'); ?> 
                            <span class="count">(<?php echo $this->get_members_count('expired'); ?>)</span>
                        </a> |
                    </li>
                    <li>
                        <a href="<?php echo admin_url('admin.php?page=chamberboss-members&filter=expiring'); ?>" 
                           class="<?php echo $filter === 'expiring' ? 'current' : ''; ?>">
                            <?php _e('Expiring Soon', 'chamberboss'); ?> 
                            <span class="count">(<?php echo $this->get_members_count('expiring'); ?>)</span>
                        </a>
                    </li>
                </ul>
                
                <!-- Search -->
                <form method="get" class="search-form">
                    <input type="hidden" name="page" value="chamberboss-members">
                    <?php if ($filter !== 'all'): ?>
                        <input type="hidden" name="filter" value="<?php echo esc_attr($filter); ?>">
                    <?php endif; ?>
                    <p class="search-box">
                        <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php _e('Search members...', 'chamberboss'); ?>">
                        <input type="submit" class="button" value="<?php _e('Search', 'chamberboss'); ?>">
                    </p>
                </form>
            </div>
            
            <!-- Members Table -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col" class="manage-column"><?php _e('Name', 'chamberboss'); ?></th>
                        <th scope="col" class="manage-column"><?php _e('Email', 'chamberboss'); ?></th>
                        <th scope="col" class="manage-column"><?php _e('Company', 'chamberboss'); ?></th>
                        <th scope="col" class="manage-column"><?php _e('Status', 'chamberboss'); ?></th>
                        <th scope="col" class="manage-column"><?php _e('Membership End', 'chamberboss'); ?></th>
                        <th scope="col" class="manage-column"><?php _e('Actions', 'chamberboss'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($members)): ?>
                        <?php foreach ($members as $member): ?>
                            <?php
                            $email = get_post_meta($member->ID, '_chamberboss_member_email', true);
                            $company = get_post_meta($member->ID, '_chamberboss_member_company', true);
                            $status = get_post_meta($member->ID, '_chamberboss_subscription_status', true) ?: 'inactive';
                            $end_date = get_post_meta($member->ID, '_chamberboss_subscription_end', true);
                            ?>
                            <tr>
                                <td>
                                    <strong>
                                        <a href="<?php echo admin_url('admin.php?page=chamberboss-members&action=view&member_id=' . $member->ID); ?>">
                                            <?php echo esc_html($member->post_title); ?>
                                        </a>
                                    </strong>
                                </td>
                                <td><?php echo esc_html($email); ?></td>
                                <td><?php echo esc_html($company); ?></td>
                                <td><?php echo $this->get_status_badge($status); ?></td>
                                <td>
                                    <?php 
                                    if ($end_date) {
                                        echo date('M j, Y', strtotime($end_date));
                                    } else {
                                        echo '—';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=chamberboss-members&action=view&member_id=' . $member->ID); ?>" 
                                       class="button button-small"><?php _e('View', 'chamberboss'); ?></a>
                    
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6"><?php _e('No members found.', 'chamberboss'); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <?php
                        $pagination_args = [
                            'base' => admin_url('admin.php?page=chamberboss-members&%_%'),
                            'format' => '&paged=%#%',
                            'current' => $paged,
                            'total' => $total_pages,
                            'prev_text' => '‹',
                            'next_text' => '›',
                        ];
                        
                        if ($filter !== 'all') {
                            $pagination_args['base'] .= '&filter=' . $filter;
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
        <?php
    }
    
    /**
     * Render add new member page
     */
    private function render_add_member() {
        ?>
        <div class="wrap">
            <h1><?php _e('Add New Member', 'chamberboss'); ?></h1>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('chamberboss_add_member_action', 'add_member_nonce'); ?>
                <input type="hidden" name="action" value="add_new_member">
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="member_name"><?php _e('Name', 'chamberboss'); ?></label></th>
                        <td><input type="text" name="member_name" id="member_name" class="regular-text" required /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="member_email"><?php _e('Email', 'chamberboss'); ?></label></th>
                        <td><input type="email" name="member_email" id="member_email" class="regular-text" required /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="member_phone"><?php _e('Phone', 'chamberboss'); ?></label></th>
                        <td><input type="text" name="member_phone" id="member_phone" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="member_company"><?php _e('Company', 'chamberboss'); ?></label></th>
                        <td><input type="text" name="member_company" id="member_company" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="member_address"><?php _e('Address', 'chamberboss'); ?></label></th>
                        <td><textarea name="member_address" id="member_address" class="large-text code" rows="3"></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="member_website"><?php _e('Website', 'chamberboss'); ?></label></th>
                        <td><input type="url" name="member_website" id="member_website" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="member_notes"><?php _e('Notes', 'chamberboss'); ?></label></th>
                        <td><textarea name="member_notes" id="member_notes" class="large-text code" rows="5"></textarea></td>
                    </tr>
                </table>
                
                <?php submit_button('Add Member'); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render edit member page
     */
    private function render_edit_member($member_id) {
        $member = get_post($member_id);

        if (!$member || $member->post_type !== 'chamberboss_member') {
            wp_die(__('Member not found.', 'chamberboss'));
        }

        // Get member meta
        $email = get_post_meta($member_id, '_chamberboss_member_email', true);
        $phone = get_post_meta($member_id, '_chamberboss_member_phone', true);
        $company = get_post_meta($member_id, '_chamberboss_member_company', true);
        $address = get_post_meta($member_id, '_chamberboss_member_address', true);
        $website = get_post_meta($member_id, '_chamberboss_member_website', true);
        $notes = get_post_meta($member_id, '_chamberboss_member_notes', true);
        $subscription_status = get_post_meta($member_id, '_chamberboss_subscription_status', true);
        $subscription_start = get_post_meta($member_id, '_chamberboss_subscription_start', true);
        $subscription_end = get_post_meta($member_id, '_chamberboss_subscription_end', true);

        ?>
        <div class="wrap">
            <h1><?php _e('Edit Member', 'chamberboss'); ?>: <?php echo esc_html($member->post_title); ?></h1>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('chamberboss_edit_member_action', 'edit_member_nonce'); ?>
                <input type="hidden" name="action" value="edit_member">
                <input type="hidden" name="member_id" value="<?php echo esc_attr($member_id); ?>">
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="member_name"><?php _e('Name', 'chamberboss'); ?></label></th>
                        <td><input type="text" name="member_name" id="member_name" class="regular-text" value="<?php echo esc_attr($member->post_title); ?>" required /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="member_email"><?php _e('Email', 'chamberboss'); ?></label></th>
                        <td><input type="email" name="member_email" id="member_email" class="regular-text" value="<?php echo esc_attr($email); ?>" required /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="member_phone"><?php _e('Phone', 'chamberboss'); ?></label></th>
                        <td><input type="text" name="member_phone" id="member_phone" class="regular-text" value="<?php echo esc_attr($phone); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="member_company"><?php _e('Company', 'chamberboss'); ?></label></th>
                        <td><input type="text" name="member_company" id="member_company" class="regular-text" value="<?php echo esc_attr($company); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="member_address"><?php _e('Address', 'chamberboss'); ?></label></th>
                        <td><textarea name="member_address" id="member_address" class="large-text code" rows="3"><?php echo esc_textarea($address); ?></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="member_website"><?php _e('Website', 'chamberboss'); ?></label></th>
                        <td><input type="url" name="member_website" id="member_website" class="regular-text" value="<?php echo esc_attr($website); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="member_notes"><?php _e('Notes', 'chamberboss'); ?></label></th>
                        <td><textarea name="member_notes" id="member_notes" class="large-text code" rows="5"><?php echo esc_textarea($notes); ?></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="subscription_status"><?php _e('Subscription Status', 'chamberboss'); ?></label></th>
                        <td>
                            <select name="subscription_status" id="subscription_status">
                                <option value="active" <?php selected($subscription_status, 'active'); ?>><?php _e('Active', 'chamberboss'); ?></option>
                                <option value="inactive" <?php selected($subscription_status, 'inactive'); ?>><?php _e('Inactive', 'chamberboss'); ?></option>
                                <option value="expired" <?php selected($subscription_status, 'expired'); ?>><?php _e('Expired', 'chamberboss'); ?></option>
                                <option value="cancelled" <?php selected($subscription_status, 'cancelled'); ?>><?php _e('Cancelled', 'chamberboss'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="subscription_start"><?php _e('Subscription Start Date', 'chamberboss'); ?></label></th>
                        <td><input type="date" name="subscription_start" id="subscription_start" value="<?php echo esc_attr(date('Y-m-d', strtotime($subscription_start))); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="subscription_end"><?php _e('Subscription End Date', 'chamberboss'); ?></label></th>
                        <td><input type="date" name="subscription_end" id="subscription_end" value="<?php echo esc_attr(date('Y-m-d', strtotime($subscription_end))); ?>" class="regular-text" /></td>
                    </tr>
                </table>
                
                <?php submit_button('Update Member'); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render member view page
     */
    private function render_view_member($member_id) {
        $member = get_post($member_id);
        
        if (!$member || $member->post_type !== 'chamberboss_member') {
            wp_die(__('Member not found.', 'chamberboss'));
        }
        
        $subscription = $this->database->get_member_subscription($member_id);
        $transactions = $this->database->get_member_transactions($member_id);
        
        // Get member meta
        $email = get_post_meta($member_id, '_chamberboss_member_email', true);
        $phone = get_post_meta($member_id, '_chamberboss_member_phone', true);
        $company = get_post_meta($member_id, '_chamberboss_member_company', true);
        $address = get_post_meta($member_id, '_chamberboss_member_address', true);
        $website = get_post_meta($member_id, '_chamberboss_member_website', true);
        $notes = get_post_meta($member_id, '_chamberboss_member_notes', true);
        $subscription_start = get_post_meta($member_id, '_chamberboss_subscription_start', true);
        $subscription_end = get_post_meta($member_id, '_chamberboss_subscription_end', true);
        
        // Get associated business listings
        $associated_listings = get_posts([
            'post_type' => 'chamberboss_listing',
            'author' => $member_id,
            'posts_per_page' => -1, // Get all listings for this member
            'post_status' => ['publish', 'pending', 'draft'],
            'orderby' => 'post_date',
            'order' => 'DESC'
        ]);
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html($member->post_title); ?></h1>
            
            <div class="chamberboss-member-view">
                <div class="member-info-grid">
                    <!-- Member Details -->
                    <div class="member-details-card">
                        <h2><?php _e('Member Details', 'chamberboss'); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th><?php _e('Name', 'chamberboss'); ?></th>
                                <td><?php echo esc_html($member->post_title); ?></td>
                            </tr>
                            <tr>
                                <th><?php _e('Email', 'chamberboss'); ?></th>
                                <td><a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a></td>
                            </tr>
                            <?php if ($phone): ?>
                            <tr>
                                <th><?php _e('Phone', 'chamberboss'); ?></th>
                                <td><?php echo esc_html($phone); ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if ($company): ?>
                            <tr>
                                <th><?php _e('Company', 'chamberboss'); ?></th>
                                <td><?php echo esc_html($company); ?></td>
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
                            <?php if ($notes): ?>
                            <tr>
                                <th><?php _e('Notes', 'chamberboss'); ?></th>
                                <td><?php echo nl2br(esc_html($notes)); ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <th><?php _e('Member Since', 'chamberboss'); ?></th>
                                <td><?php echo date('F j, Y', strtotime($member->post_date)); ?></td>
                            </tr>
                            <?php if ($subscription_start): ?>
                            <tr>
                                <th><?php _e('Subscription Start', 'chamberboss'); ?></th>
                                <td><?php echo date('F j, Y', strtotime($subscription_start)); ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if ($subscription_end): ?>
                            <tr>
                                <th><?php _e('Subscription End', 'chamberboss'); ?></th>
                                <td><?php echo date('F j, Y', strtotime($subscription_end)); ?></td>
                            </tr>
                            <?php endif; ?>
                        </table>
                        
                        <p>
                            <a href="<?php echo get_edit_post_link($member_id); ?>" class="button button-primary">
                                <?php _e('Edit Member', 'chamberboss'); ?>
                            </a>
                            <a href="<?php echo admin_url('admin.php?page=chamberboss-members'); ?>" class="button">
                                <?php _e('Back to Members', 'chamberboss'); ?>
                            </a>
                        </p>
                    </div>
                    
                    <!-- Subscription Details -->
                    <div class="subscription-details-card">
                        <h2><?php _e('Subscription Details', 'chamberboss'); ?></h2>
                        <?php if ($subscription): ?>
                            <table class="form-table">
                                <tr>
                                    <th><?php _e('Status', 'chamberboss'); ?></th>
                                    <td><?php echo $this->get_status_badge($subscription->status); ?></td>
                                </tr>
                                <tr>
                                    <th><?php _e('Plan', 'chamberboss'); ?></th>
                                    <td><?php echo esc_html(ucfirst($subscription->plan_name)); ?></td>
                                </tr>
                                <tr>
                                    <th><?php _e('Amount', 'chamberboss'); ?></th>
                                    <td><?php echo $this->format_currency($subscription->amount, $subscription->currency); ?></td>
                                </tr>
                                <tr>
                                    <th><?php _e('Billing Cycle', 'chamberboss'); ?></th>
                                    <td><?php echo esc_html(ucfirst($subscription->billing_cycle)); ?></td>
                                </tr>
                                <?php if ($subscription->start_date): ?>
                                <tr>
                                    <th><?php _e('Start Date', 'chamberboss'); ?></th>
                                    <td><?php echo date('F j, Y', strtotime($subscription->start_date)); ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if ($subscription->end_date): ?>
                                <tr>
                                    <th><?php _e('End Date', 'chamberboss'); ?></th>
                                    <td><?php echo date('F j, Y', strtotime($subscription->end_date)); ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if ($subscription->next_billing_date && $subscription->status === 'active'): ?>
                                <tr>
                                    <th><?php _e('Next Billing', 'chamberboss'); ?></th>
                                    <td><?php echo date('F j, Y', strtotime($subscription->next_billing_date)); ?></td>
                                </tr>
                                <?php endif; ?>
                            </table>
                        <?php else: ?>
                            <p><?php _e('No subscription found for this member.', 'chamberboss'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Associated Business Listings -->
                <div class="associated-listings-card">
                    <h2><?php _e('Associated Business Listings', 'chamberboss'); ?></h2>
                    <?php if (!empty($associated_listings)): ?>
                        <ul class="associated-listings-list">
                            <?php foreach ($associated_listings as $listing): ?>
                                <li>
                                    <a href="<?php echo admin_url('admin.php?page=chamberboss-listings&action=view&listing_id=' . $listing->ID); ?>">
                                        <?php echo esc_html($listing->post_title); ?> 
                                        (<?php echo $this->get_status_badge($listing->post_status); ?>)
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p><?php _e('No business listings associated with this member.', 'chamberboss'); ?></p>
                    <?php endif; ?>
                </div>
                
                <!-- Transaction History -->
                <div class="transaction-history-card">
                    <h2><?php _e('Transaction History', 'chamberboss'); ?></h2>
                    <?php if (!empty($transactions)): ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Date', 'chamberboss'); ?></th>
                                    <th><?php _e('Type', 'chamberboss'); ?></th>
                                    <th><?php _e('Amount', 'chamberboss'); ?></th>
                                    <th><?php _e('Status', 'chamberboss'); ?></th>
                                    <th><?php _e('Payment ID', 'chamberboss'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transactions as $transaction): ?>
                                    <tr>
                                        <td><?php echo date('M j, Y g:i A', strtotime($transaction->created_at)); ?></td>
                                        <td><?php echo esc_html(ucwords(str_replace('_', ' ', $transaction->transaction_type))); ?></td>
                                        <td><?php echo $this->format_currency($transaction->amount, $transaction->currency); ?></td>
                                        <td><?php echo $this->get_status_badge($transaction->status); ?></td>
                                        <td>
                                            <?php if ($transaction->stripe_payment_intent_id): ?>
                                                <code><?php echo esc_html($transaction->stripe_payment_intent_id); ?></code>
                                            <?php elseif ($transaction->stripe_subscription_id): ?>
                                                <code><?php echo esc_html($transaction->stripe_subscription_id); ?></code>
                                            <?php else: ?>
                                                —
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p><?php _e('No transactions found for this member.', 'chamberboss'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get members based on filter and search
     */
    private function get_members($filter = 'all', $search = '', $paged = 1, $per_page = 20) {
        $args = [
            'post_type' => 'chamberboss_member',
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'paged' => $paged,
            'orderby' => 'date',
            'order' => 'DESC'
        ];
        
        if ($search) {
            $args['s'] = $search;
        }
        
        if ($filter !== 'all') {
            $args['meta_query'] = [];
            
            switch ($filter) {
                case 'active':
                    $args['meta_query'][] = [
                        'key' => '_chamberboss_subscription_status',
                        'value' => 'active',
                        'compare' => '='
                    ];
                    break;
                    
                case 'expired':
                    $args['meta_query'][] = [
                        'key' => '_chamberboss_subscription_status',
                        'value' => 'expired',
                        'compare' => '='
                    ];
                    break;
                    
                case 'expiring':
                    $args['meta_query'][] = [
                        [
                            'key' => '_chamberboss_subscription_status',
                            'value' => 'active',
                            'compare' => '='
                        ],
                        [
                            'key' => '_chamberboss_subscription_end',
                            'value' => date('Y-m-d H:i:s', strtotime('+30 days')),
                            'compare' => '<='
                        ]
                    ];
                    break;
            }
        }
        
        return get_posts($args);
    }
    
    /**
     * Get members count
     */
    private function get_members_count($filter = 'all', $search = '') {
        global $wpdb;
        
        $sql = "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p";
        $where = ["p.post_type = 'chamberboss_member'", "p.post_status = 'publish'"];
        
        if ($search) {
            $where[] = $wpdb->prepare("p.post_title LIKE %s", '%' . $wpdb->esc_like($search) . '%');
        }
        
        if ($filter !== 'all') {
            $sql .= " LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id";
            
            switch ($filter) {
                case 'active':
                    $where[] = "pm.meta_key = '_chamberboss_subscription_status' AND pm.meta_value = 'active'";
                    break;
                case 'expired':
                    $where[] = "pm.meta_key = '_chamberboss_subscription_status' AND pm.meta_value = 'expired'";
                    break;
                case 'expiring':
                    $sql .= " LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id";
                    $where[] = "pm.meta_key = '_chamberboss_subscription_status' AND pm.meta_value = 'active'";
                    $where[] = $wpdb->prepare("pm2.meta_key = '_chamberboss_subscription_end' AND pm2.meta_value <= %s", date('Y-m-d H:i:s', strtotime('+30 days')));
                    break;
            }
        }
        
        $sql .= " WHERE " . implode(' AND ', $where);
        
        return intval($wpdb->get_var($sql));
    }
    
    /**
     * Handle member actions
     */
    public function process_add_member() {
        error_log('[Chamberboss Debug] process_add_member reached.');
        error_log('[Chamberboss Debug] $_POST data: ' . print_r($_POST, true));

        if (!isset($_POST['action']) || !isset($_POST['add_member_nonce'])) {
            error_log('[Chamberboss Debug] Missing action or nonce.');
            return;
        }

        if ($_POST['action'] === 'add_new_member') {
            if (!wp_verify_nonce($_POST['add_member_nonce'], 'chamberboss_add_member_action')) {
                error_log('[Chamberboss Debug] Nonce verification failed for add_new_member.');
                wp_die(__('Security check failed', 'chamberboss'));
            }
            
            $data = $this->sanitize_input($_POST);
            error_log('[Chamberboss Debug] Sanitized member data: ' . print_r($data, true));

            // Validate required fields
            if (empty($data['member_name']) || empty($data['member_email'])) {
                error_log('[Chamberboss Debug] Member name or email is empty.');
                // Display error message
                add_settings_error('chamberboss_members', 'add_member_error', __('Name and email are required.', 'chamberboss'), 'error');
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
                error_log('[Chamberboss Debug] Member with this email already exists.');
                add_settings_error('chamberboss_members', 'add_member_error', __('A member with this email already exists.', 'chamberboss'), 'error');
                return;
            }

            // Create member post
            error_log('[Chamberboss Debug] Before wp_insert_post. Data: ' . print_r($data, true));
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
                    '_chamberboss_member_notes' => $data['member_notes'] ?? '', // Add notes to meta
                    '_chamberboss_subscription_status' => 'active',
                    '_chamberboss_subscription_start' => current_time('mysql'),
                    '_chamberboss_subscription_end' => date('Y-m-d H:i:s', strtotime('+1 year'))
                ]
            ]);
            error_log('[Chamberboss Debug] wp_insert_post returned: ' . print_r($member_id, true));

            if (is_wp_error($member_id)) {
                add_settings_error('chamberboss_members', 'add_member_error', __('Failed to create member.', 'chamberboss'), 'error');
                return;
            }

            // Trigger member registration action
            do_action('chamberboss_member_registered', $member_id);

            add_settings_error('chamberboss_members', 'add_member_success', __('Member added successfully!', 'chamberboss'), 'updated');
            
            // Redirect to prevent form resubmission
            wp_redirect(admin_url('admin.php?page=chamberboss-members&settings-updated=true'));
            exit;
        }
    }

    /**
     * Process edit member submission
     */
    public function process_edit_member() {
        if (!current_user_can('edit_chamberboss_member')) {
            wp_die(__('You do not have permission to edit members.', 'chamberboss'));
        }
        if (!current_user_can('edit_chamberboss_member')) {
            wp_die(__('You do not have permission to edit members.', 'chamberboss'));
        }

        if (!isset($_POST['edit_member_nonce']) || !wp_verify_nonce($_POST['edit_member_nonce'], 'chamberboss_edit_member_action')) {
            wp_die(__('Security check failed.', 'chamberboss'));
        }

        $member_id = intval($_POST['member_id'] ?? 0);
        if (!$member_id) {
            wp_die(__('Invalid member ID.', 'chamberboss'));
        }

        $data = $this->sanitize_input($_POST);

        // Update member post
        wp_update_post([
            'ID' => $member_id,
            'post_title' => $data['member_name'],
        ]);

        // Update member meta
        update_post_meta($member_id, '_chamberboss_member_email', $data['member_email']);
        update_post_meta($member_id, '_chamberboss_member_phone', $data['member_phone'] ?? '');
        update_post_meta($member_id, '_chamberboss_member_company', $data['member_company'] ?? '');
        update_post_meta($member_id, '_chamberboss_member_address', $data['member_address'] ?? '');
        update_post_meta($member_id, '_chamberboss_member_website', $data['member_website'] ?? '');
        update_post_meta($member_id, '_chamberboss_member_notes', $data['member_notes'] ?? '');
        update_post_meta($member_id, '_chamberboss_subscription_status', $data['subscription_status'] ?? 'inactive');
        update_post_meta($member_id, '_chamberboss_subscription_start', $data['subscription_start'] ?? '');
        update_post_meta($member_id, '_chamberboss_subscription_end', $data['subscription_end'] ?? '');

        add_settings_error('chamberboss_members', 'edit_member_success', __('Member updated successfully!', 'chamberboss'), 'updated');
        wp_redirect(admin_url('admin.php?page=chamberboss-members&action=edit&member_id=' . $member_id . '&settings-updated=true'));
        exit;
    }
    
    /**
     * Format currency amount
     */
    private function format_currency($amount, $currency = 'USD') {
        $symbols = [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'CAD' => '$CAD',
            'AUD' => '$AUD'
        ];
        
        $symbol = $symbols[$currency] ?? $currency . ' ';
        
        return $symbol . number_format($amount, 2);
    }
}