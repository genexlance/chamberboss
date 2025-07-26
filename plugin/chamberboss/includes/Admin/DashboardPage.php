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
            'CAD' => 'C$',
            'AUD' => 'A$'
        ];
        
        $symbol = $symbols[$currency] ?? $currency . ' ';
        
        return $symbol . number_format($amount, 2);
    }
    
    }

