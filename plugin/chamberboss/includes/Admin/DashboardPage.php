<?php
namespace Chamberboss\Admin;

use Chamberboss\Core\BaseClass;
use Chamberboss\Core\Database;

/**
 * Dashboard Admin Page
 */
class DashboardPage extends BaseClass {
    
    /**
     * Database instance
     * @var Database
     */
    private $database;
    
    /**
     * Initialize dashboard
     */
    protected function init() {
        $this->database = new Database();
        add_action('admin_post_chamberboss_approve_listing', [$this, 'approve_listing']);
    }
    
    /**
     * Render dashboard page
     */
    public function render() {
        $stats = $this->get_dashboard_stats();
        $recent_members = $this->get_recent_members();
        $recent_transactions = $this->get_recent_transactions();
        $expiring_memberships = $this->get_expiring_memberships();
        
        ?>
        <div class="wrap">
            <h1><?php _e('Chamberboss Dashboard', 'chamberboss'); ?></h1>
            
            <div class="chamberboss-dashboard">
                <!-- Stats Cards -->
                <div class="chamberboss-stats-grid">
                    <div class="chamberboss-stat-card">
                        <div class="stat-icon">
                            <span class="dashicons dashicons-groups"></span>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['total_members']); ?></h3>
                            <p><?php _e('Total Members', 'chamberboss'); ?></p>
                        </div>
                    </div>
                    
                    <div class="chamberboss-stat-card">
                        <div class="stat-icon">
                            <span class="dashicons dashicons-yes-alt"></span>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['active_members']); ?></h3>
                            <p><?php _e('Active Members', 'chamberboss'); ?></p>
                        </div>
                    </div>
                    
                    <div class="chamberboss-stat-card">
                        <div class="stat-icon">
                            <span class="dashicons dashicons-building"></span>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['total_listings']); ?></h3>
                            <p><?php _e('Business Listings', 'chamberboss'); ?></p>
                        </div>
                    </div>
                    
                    <div class="chamberboss-stat-card">
                        <div class="stat-icon">
                            <span class="dashicons dashicons-money-alt"></span>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $this->format_currency($stats['monthly_revenue']); ?></h3>
                            <p><?php _e('Monthly Revenue', 'chamberboss'); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="chamberboss-dashboard-grid">
                    <!-- Recent Members -->
                    <div class="chamberboss-dashboard-widget">
                        <div class="widget-header">
                            <h2><?php _e('Recent Members', 'chamberboss'); ?></h2>
                            <a href="<?php echo admin_url('admin.php?page=chamberboss-members'); ?>" class="button button-secondary">
                                <?php _e('View All', 'chamberboss'); ?>
                            </a>
                        </div>
                        <div class="widget-content">
                            <?php if (!empty($recent_members)): ?>
                                <table class="wp-list-table widefat fixed striped">
                                    <thead>
                                        <tr>
                                            <th><?php _e('Name', 'chamberboss'); ?></th>
                                            <th><?php _e('Email', 'chamberboss'); ?></th>
                                            <th><?php _e('Status', 'chamberboss'); ?></th>
                                            <th><?php _e('Joined', 'chamberboss'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_members as $member): ?>
                                            <tr>
                                                <td>
                                                    <strong>
                                                        <a href="<?php echo get_edit_post_link($member->ID); ?>">
                                                            <?php echo esc_html($member->post_title); ?>
                                                        </a>
                                                    </strong>
                                                </td>
                                                <td><?php echo esc_html(get_post_meta($member->ID, '_chamberboss_member_email', true)); ?></td>
                                                <td>
                                                    <?php 
                                                    $status = get_post_meta($member->ID, '_chamberboss_subscription_status', true) ?: 'inactive';
                                                    echo $this->get_status_badge($status);
                                                    ?>
                                                </td>
                                                <td><?php echo date('M j, Y', strtotime($member->post_date)); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p><?php _e('No members found.', 'chamberboss'); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Recent Transactions -->
                    <div class="chamberboss-dashboard-widget">
                        <div class="widget-header">
                            <h2><?php _e('Recent Transactions', 'chamberboss'); ?></h2>
                            <a href="<?php echo admin_url('admin.php?page=chamberboss-transactions'); ?>" class="button button-secondary">
                                <?php _e('View All', 'chamberboss'); ?>
                            </a>
                        </div>
                        <div class="widget-content">
                            <?php if (!empty($recent_transactions)): ?>
                                <table class="wp-list-table widefat fixed striped">
                                    <thead>
                                        <tr>
                                            <th><?php _e('Member', 'chamberboss'); ?></th>
                                            <th><?php _e('Amount', 'chamberboss'); ?></th>
                                            <th><?php _e('Status', 'chamberboss'); ?></th>
                                            <th><?php _e('Date', 'chamberboss'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_transactions as $transaction): ?>
                                            <tr>
                                                <td>
                                                    <?php 
                                                    $member_title = get_the_title($transaction->member_id);
                                                    echo esc_html($member_title ?: 'Unknown Member');
                                                    ?>
                                                </td>
                                                <td><?php echo $this->format_currency($transaction->amount, $transaction->currency); ?></td>
                                                <td><?php echo $this->get_status_badge($transaction->status); ?></td>
                                                <td><?php echo date('M j, Y', strtotime($transaction->created_at)); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p><?php _e('No transactions found.', 'chamberboss'); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Expiring Memberships Alert -->
                <?php if (!empty($expiring_memberships)): ?>
                <div class="chamberboss-alert chamberboss-alert-warning">
                    <div class="alert-icon">
                        <span class="dashicons dashicons-warning"></span>
                    </div>
                    <div class="alert-content">
                        <h3><?php _e('Expiring Memberships', 'chamberboss'); ?></h3>
                        <p>
                            <?php 
                            printf(
                                _n(
                                    '%d membership is expiring within 30 days.',
                                    '%d memberships are expiring within 30 days.',
                                    count($expiring_memberships),
                                    'chamberboss'
                                ),
                                count($expiring_memberships)
                            );
                            ?>
                        </p>
                        <a href="<?php echo admin_url('admin.php?page=chamberboss-members&filter=expiring'); ?>" class="button button-primary">
                            <?php _e('View Expiring Memberships', 'chamberboss'); ?>
                        </a>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Pending Listings for Approval -->
                <div class="chamberboss-dashboard-widget">
                    <div class="widget-header">
                        <h2><?php _e('Pending Business Listings', 'chamberboss'); ?></h2>
                        <a href="<?php echo admin_url('admin.php?page=chamberboss-listings&post_status=pending'); ?>" class="button button-secondary">
                            <?php _e('View All Pending', 'chamberboss'); ?>
                        </a>
                    </div>
                    <div class="widget-content">
                        <?php
                        $pending_listings = get_posts([
                            'post_type' => 'chamberboss_listing',
                            'post_status' => 'pending',
                            'numberposts' => 5,
                            'orderby' => 'date',
                            'order' => 'ASC'
                        ]);
                        
                        if (!empty($pending_listings)):
                        ?>
                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th><?php _e('Business Name', 'chamberboss'); ?></th>
                                        <th><?php _e('Submitted By', 'chamberboss'); ?></th>
                                        <th><?php _e('Date', 'chamberboss'); ?></th>
                                        <th><?php _e('Action', 'chamberboss'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_listings as $listing):
                                        $author = get_user_by('id', $listing->post_author);
                                    ?>
                                        <tr>
                                            <td>
                                                <strong>
                                                    <a href="<?php echo admin_url('admin.php?page=chamberboss-listings&action=view&listing_id=' . $listing->ID); ?>">
                                                        <?php echo esc_html($listing->post_title); ?>
                                                    </a>
                                                </strong>
                                            </td>
                                            <td><?php echo esc_html($author ? $author->display_name : 'Unknown'); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($listing->post_date)); ?></td>
                                            <td>
                                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                                    <?php wp_nonce_field('chamberboss_approve_listing', 'approve_listing_nonce'); ?>
                                                    <input type="hidden" name="action" value="chamberboss_approve_listing">
                                                    <input type="hidden" name="listing_id" value="<?php echo $listing->ID; ?>">
                                                    <button type="submit" class="button button-primary button-small">
                                                        <?php _e('Approve', 'chamberboss'); ?>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p><?php _e('No pending business listings.', 'chamberboss'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get dashboard statistics
     * @return array
     */
    private function get_dashboard_stats() {
        global $wpdb;
        
        // Total members
        $total_members = wp_count_posts('chamberboss_member')->publish;
        
        // Active members
        $active_members = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_chamberboss_subscription_status' 
            AND meta_value = 'active'
        ");
        
        // Total listings
        $total_listings = wp_count_posts('chamberboss_listing')->publish;
        
        // Monthly revenue
        $transactions_table = $wpdb->prefix . 'chamberboss_transactions';
        $monthly_revenue = $wpdb->get_var($wpdb->prepare("
            SELECT COALESCE(SUM(amount), 0)
            FROM {$transactions_table}
            WHERE status = 'completed'
            AND created_at >= %s
        ", date('Y-m-01')));
        
        return [
            'total_members' => intval($total_members),
            'active_members' => intval($active_members),
            'total_listings' => intval($total_listings),
            'monthly_revenue' => floatval($monthly_revenue)
        ];
    }
    
    /**
     * Get recent members
     * @param int $limit
     * @return array
     */
    private function get_recent_members($limit = 5) {
        return get_posts([
            'post_type' => 'chamberboss_member',
            'post_status' => 'publish',
            'numberposts' => $limit,
            'orderby' => 'date',
            'order' => 'DESC'
        ]);
    }
    
    /**
     * Get recent transactions
     * @param int $limit
     * @return array
     */
    private function get_recent_transactions($limit = 5) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'chamberboss_transactions';
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$table}
            ORDER BY created_at DESC
            LIMIT %d
        ", $limit));
    }
    
    /**
     * Get expiring memberships
     * @return array
     */
    private function get_expiring_memberships() {
        return $this->database->get_expiring_subscriptions(30);
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
            'CAD' => 'CAD$',
            'AUD' => 'AUD$'
        ];
        
        $symbol = $symbols[$currency] ?? $currency . ' ';
        
        return $symbol . number_format($amount, 2);
    }

    /**
     * Approve a business listing.
     */
    public function approve_listing() {
        // DEBUG: Log approval attempt
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("CHAMBERBOSS DEBUG: approve_listing called");
            error_log("CHAMBERBOSS DEBUG: Current user ID: " . get_current_user_id());
            error_log("CHAMBERBOSS DEBUG: User roles: " . implode(', ', wp_get_current_user()->roles ?? []));
            error_log("CHAMBERBOSS DEBUG: Can manage listings: " . (current_user_can('manage_chamberboss_listings') ? 'true' : 'false'));
            error_log("CHAMBERBOSS DEBUG: Is administrator: " . (current_user_can('administrator') ? 'true' : 'false'));
        }
        
        // Check permission - allow administrators even if custom capability isn't set yet
        $can_manage = current_user_can('manage_chamberboss_listings') || current_user_can('administrator');
        
        if (!$can_manage) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("CHAMBERBOSS DEBUG: Permission denied - user cannot manage listings and is not administrator");
            }
            wp_die(__('You do not have permission to approve listings.', 'chamberboss'));
        }
        
        // Ensure the admin has the capability for future requests
        if (current_user_can('administrator')) {
            $admin_role = get_role('administrator');
            if ($admin_role && !$admin_role->has_cap('manage_chamberboss_listings')) {
                $admin_role->add_cap('manage_chamberboss_listings');
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("CHAMBERBOSS DEBUG: Added manage_chamberboss_listings capability to administrator role");
                }
            }
        }

        $listing_id = intval($_POST['listing_id'] ?? 0);
        $nonce = sanitize_text_field($_POST['approve_listing_nonce'] ?? '');

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("CHAMBERBOSS DEBUG: Listing ID: $listing_id");
            error_log("CHAMBERBOSS DEBUG: Nonce: $nonce");
            error_log("CHAMBERBOSS DEBUG: Nonce valid: " . (wp_verify_nonce($nonce, 'chamberboss_approve_listing') ? 'true' : 'false'));
        }

        if (!$listing_id || !wp_verify_nonce($nonce, 'chamberboss_approve_listing')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("CHAMBERBOSS DEBUG: Invalid request or security check failed");
            }
            wp_die(__('Invalid request or security check failed.', 'chamberboss'));
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("CHAMBERBOSS DEBUG: Updating post $listing_id to publish status");
        }

        $result = wp_update_post([
            'ID' => $listing_id,
            'post_status' => 'publish',
        ]);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("CHAMBERBOSS DEBUG: Update result: " . ($result ? 'success' : 'failed'));
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("CHAMBERBOSS DEBUG: Redirecting to admin page");
        }

        wp_redirect(admin_url('admin.php?page=chamberboss&message=listing_approved'));
        exit;
    }
}
