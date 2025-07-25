<?php
namespace Chamberboss\Notifications;

use Chamberboss\Core\BaseClass;
use Chamberboss\Core\Database;

/**
 * Notification System Handler
 */
class NotificationSystem extends BaseClass {
    
    /**
     * Database instance
     * @var Database
     */
    private $database;
    
    /**
     * Initialize notification system
     */
    protected function init() {
        $this->database = new Database();
        
        // Schedule daily cron job for notifications
        add_action('wp', [$this, 'schedule_notifications_cron']);
        add_action('chamberboss_daily_notifications', [$this, 'process_daily_notifications']);
        
        // Hook into membership events
        add_action('chamberboss_membership_activated', [$this, 'send_welcome_email']);
        add_action('chamberboss_payment_succeeded', [$this, 'send_payment_confirmation']);
        add_action('chamberboss_payment_failed', [$this, 'send_payment_failed_notification']);
        add_action('chamberboss_member_registered', [$this, 'send_registration_confirmation']);
        
        // Admin notifications
        add_action('chamberboss_listing_submitted', [$this, 'notify_admin_new_listing']);
        
        // Process pending notifications
        add_action('init', [$this, 'process_pending_notifications']);
    }
    
    /**
     * Schedule notifications cron job
     */
    public function schedule_notifications_cron() {
        if (!wp_next_scheduled('chamberboss_daily_notifications')) {
            wp_schedule_event(time(), 'daily', 'chamberboss_daily_notifications');
        }
    }
    
    /**
     * Process daily notifications
     */
    public function process_daily_notifications() {
        $this->send_renewal_notifications();
        $this->process_expired_memberships();
        $this->cleanup_old_notifications();
    }
    
    /**
     * Send renewal notifications
     */
    public function send_renewal_notifications() {
        $renewal_days = intval($this->get_option('chamberboss_renewal_days', '30'));
        $expiring_subscriptions = $this->database->get_expiring_subscriptions($renewal_days);
        
        foreach ($expiring_subscriptions as $subscription) {
            // Check if we've already sent a renewal notification recently
            if ($this->has_recent_notification($subscription->member_id, 'renewal_reminder', 7)) {
                continue;
            }
            
            $member = get_post($subscription->member_id);
            if (!$member) {
                continue;
            }
            
            $email = get_post_meta($subscription->member_id, '_chamberboss_member_email', true);
            if (!$email) {
                continue;
            }
            
            $this->queue_notification([
                'member_id' => $subscription->member_id,
                'notification_type' => 'renewal_reminder',
                'subject' => $this->get_renewal_subject($member, $subscription),
                'message' => $this->get_renewal_message($member, $subscription),
                'scheduled_at' => current_time('mysql')
            ]);
        }
        
        $this->log('Queued renewal notifications for ' . count($expiring_subscriptions) . ' members');
    }
    
    /**
     * Process expired memberships
     */
    public function process_expired_memberships() {
        $expired_subscriptions = $this->database->get_expired_subscriptions();
        
        foreach ($expired_subscriptions as $subscription) {
            // Update subscription status
            $this->database->upsert_member_subscription($subscription->member_id, [
                'status' => 'expired'
            ]);
            
            // Update post meta
            update_post_meta($subscription->member_id, '_chamberboss_subscription_status', 'expired');
            
            // Send expiration notification
            $member = get_post($subscription->member_id);
            if ($member) {
                $this->queue_notification([
                    'member_id' => $subscription->member_id,
                    'notification_type' => 'membership_expired',
                    'subject' => $this->get_expiration_subject($member),
                    'message' => $this->get_expiration_message($member),
                    'scheduled_at' => current_time('mysql')
                ]);
            }
            
            // Trigger expired action
            do_action('chamberboss_membership_expired', $subscription->member_id);
        }
        
        if (!empty($expired_subscriptions)) {
            $this->log('Processed ' . count($expired_subscriptions) . ' expired memberships');
        }
    }
    
    /**
     * Send welcome email to new member
     * @param int $member_id
     */
    public function send_welcome_email($member_id) {
        $member = get_post($member_id);
        if (!$member) {
            return;
        }
        
        $email = get_post_meta($member_id, '_chamberboss_member_email', true);
        if (!$email) {
            return;
        }
        
        $this->queue_notification([
            'member_id' => $member_id,
            'notification_type' => 'welcome_email',
            'subject' => $this->get_welcome_subject($member),
            'message' => $this->get_welcome_message($member),
            'scheduled_at' => current_time('mysql')
        ]);
    }
    
    /**
     * Send payment confirmation
     * @param int $member_id
     * @param object $payment_data
     */
    public function send_payment_confirmation($member_id, $payment_data = null) {
        $member = get_post($member_id);
        if (!$member) {
            return;
        }
        
        $email = get_post_meta($member_id, '_chamberboss_member_email', true);
        if (!$email) {
            return;
        }
        
        $this->queue_notification([
            'member_id' => $member_id,
            'notification_type' => 'payment_confirmation',
            'subject' => $this->get_payment_confirmation_subject($member),
            'message' => $this->get_payment_confirmation_message($member, $payment_data),
            'scheduled_at' => current_time('mysql')
        ]);
    }
    
    /**
     * Send payment failed notification
     * @param int $member_id
     * @param object $payment_data
     */
    public function send_payment_failed_notification($member_id, $payment_data = null) {
        $member = get_post($member_id);
        if (!$member) {
            return;
        }
        
        $email = get_post_meta($member_id, '_chamberboss_member_email', true);
        if (!$email) {
            return;
        }
        
        $this->queue_notification([
            'member_id' => $member_id,
            'notification_type' => 'payment_failed',
            'subject' => 'Payment Failed - Action Required',
            'message' => $this->get_payment_failed_message($member, $payment_data),
            'scheduled_at' => current_time('mysql')
        ]);
    }
    
    /**
     * Send registration confirmation
     * @param int $member_id
     */
    public function send_registration_confirmation($member_id) {
        $member = get_post($member_id);
        if (!$member) {
            return;
        }
        
        $email = get_post_meta($member_id, '_chamberboss_member_email', true);
        if (!$email) {
            return;
        }
        
        $this->queue_notification([
            'member_id' => $member_id,
            'notification_type' => 'registration_confirmation',
            'subject' => 'Registration Received - Next Steps',
            'message' => $this->get_registration_confirmation_message($member),
            'scheduled_at' => current_time('mysql')
        ]);
    }
    
    /**
     * Notify admin of new listing submission
     * @param int $listing_id
     */
    public function notify_admin_new_listing($listing_id) {
        $listing = get_post($listing_id);
        if (!$listing) {
            return;
        }
        
        $admin_email = get_option('admin_email');
        $subject = 'New Business Listing Submitted - ' . $listing->post_title;
        $message = $this->get_admin_listing_notification_message($listing);
        
        $this->send_email_immediately($admin_email, $subject, $message);
    }
    
    /**
     * Queue notification for later sending
     * @param array $notification_data
     * @return bool|int
     */
    public function queue_notification($notification_data) {
        return $this->database->add_notification($notification_data);
    }
    
    /**
     * Process pending notifications
     */
    public function process_pending_notifications() {
        // Only process on admin requests to avoid performance issues
        if (!is_admin()) {
            return;
        }
        
        $notifications = $this->database->get_pending_notifications(10);
        
        foreach ($notifications as $notification) {
            $member = get_post($notification->member_id);
            if (!$member) {
                $this->database->update_notification_status($notification->id, 'failed');
                continue;
            }
            
            $email = get_post_meta($notification->member_id, '_chamberboss_member_email', true);
            if (!$email) {
                $this->database->update_notification_status($notification->id, 'failed');
                continue;
            }
            
            $sent = $this->send_email_immediately($email, $notification->subject, $notification->message);
            
            if ($sent) {
                $this->database->update_notification_status($notification->id, 'sent');
            } else {
                $this->database->update_notification_status($notification->id, 'failed');
            }
        }
    }
    
    /**
     * Send email immediately
     * @param string $to
     * @param string $subject
     * @param string $message
     * @return bool
     */
    public function send_email_immediately($to, $subject, $message) {
        $from_name = $this->get_option('chamberboss_email_from_name', get_bloginfo('name'));
        $from_email = $this->get_option('chamberboss_email_from_address', get_option('admin_email'));
        
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>'
        ];
        
        $html_message = $this->format_email_html($message, $subject);
        
        return wp_mail($to, $subject, $html_message, $headers);
    }
    
    /**
     * Format email as HTML
     * @param string $message
     * @param string $subject
     * @return string
     */
    private function format_email_html($message, $subject) {
        $site_name = get_bloginfo('name');
        $site_url = home_url();
        
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>' . esc_html($subject) . '</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4; }
                .email-container { max-width: 600px; margin: 0 auto; background-color: #ffffff; }
                .email-header { background-color: #2271b1; color: #ffffff; padding: 20px; text-align: center; }
                .email-body { padding: 30px; }
                .email-footer { background-color: #f8f9fa; padding: 20px; text-align: center; font-size: 14px; color: #6c757d; }
                .button { display: inline-block; padding: 12px 24px; background-color: #2271b1; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: bold; margin: 10px 0; }
                .button:hover { background-color: #135e96; }
            </style>
        </head>
        <body>
            <div class="email-container">
                <div class="email-header">
                    <h1>' . esc_html($site_name) . '</h1>
                </div>
                <div class="email-body">
                    ' . wpautop($message) . '
                </div>
                <div class="email-footer">
                    <p>This email was sent from <a href="' . esc_url($site_url) . '">' . esc_html($site_name) . '</a></p>
                    <p>If you have any questions, please contact us.</p>
                </div>
            </div>
        </body>
        </html>';
        
        return $html;
    }
    
    /**
     * Check if member has received a recent notification of specific type
     * @param int $member_id
     * @param string $notification_type
     * @param int $days
     * @return bool
     */
    private function has_recent_notification($member_id, $notification_type, $days = 7) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'chamberboss_notifications';
        $since_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} 
             WHERE member_id = %d 
             AND notification_type = %s 
             AND created_at >= %s",
            $member_id,
            $notification_type,
            $since_date
        ));
        
        return intval($count) > 0;
    }
    
    /**
     * Get renewal email subject
     * @param object $member
     * @param object $subscription
     * @return string
     */
    private function get_renewal_subject($member, $subscription) {
        $subject = $this->get_option('chamberboss_email_renewal_subject', 'Your membership is expiring soon');
        
        return $this->replace_placeholders($subject, $member, $subscription);
    }
    
    /**
     * Get renewal email message
     * @param object $member
     * @param object $subscription
     * @return string
     */
    private function get_renewal_message($member, $subscription) {
        $message = $this->get_option('chamberboss_email_renewal_message', $this->get_default_renewal_message());
        
        return $this->replace_placeholders($message, $member, $subscription);
    }
    
    /**
     * Get welcome email subject
     * @param object $member
     * @return string
     */
    private function get_welcome_subject($member) {
        $subject = $this->get_option('chamberboss_email_welcome_subject', 'Welcome to our Chamber of Commerce!');
        
        return $this->replace_placeholders($subject, $member);
    }
    
    /**
     * Get welcome email message
     * @param object $member
     * @return string
     */
    private function get_welcome_message($member) {
        $message = $this->get_option('chamberboss_email_welcome_message', $this->get_default_welcome_message());
        
        return $this->replace_placeholders($message, $member);
    }
    
    /**
     * Get payment confirmation subject
     * @param object $member
     * @return string
     */
    private function get_payment_confirmation_subject($member) {
        return 'Payment Received - Membership Activated';
    }
    
    /**
     * Get payment confirmation message
     * @param object $member
     * @param object $payment_data
     * @return string
     */
    private function get_payment_confirmation_message($member, $payment_data = null) {
        $membership_price = $this->get_option('chamberboss_membership_price', '100.00');
        $currency = $this->get_option('chamberboss_currency', 'USD');
        
        $message = "Dear {member_name},\n\n";
        $message .= "Thank you for your payment! Your chamber membership has been activated.\n\n";
        $message .= "Payment Details:\n";
        $message .= "Amount: " . $this->format_currency($membership_price, $currency) . "\n";
        $message .= "Date: " . date('F j, Y') . "\n\n";
        $message .= "You can now:\n";
        $message .= "- Submit business listings to our directory\n";
        $message .= "- Access member-only resources\n";
        $message .= "- Participate in networking events\n\n";
        $message .= "Explore our business directory: {directory_url}\n\n";
        $message .= "Welcome to the chamber!\n\n";
        $message .= "Best regards,\nThe Chamber Team";
        
        return $this->replace_placeholders($message, $member);
    }
    
    /**
     * Get payment failed message
     * @param object $member
     * @param object $payment_data
     * @return string
     */
    private function get_payment_failed_message($member, $payment_data = null) {
        $message = "Dear {member_name},\n\n";
        $message .= "We were unable to process your membership payment. Please check your payment method and try again.\n\n";
        $message .= "If you continue to experience issues, please contact us for assistance.\n\n";
        $message .= "Retry payment: {renewal_url}\n\n";
        $message .= "Best regards,\nThe Chamber Team";
        
        return $this->replace_placeholders($message, $member);
    }
    
    /**
     * Get registration confirmation message
     * @param object $member
     * @return string
     */
    private function get_registration_confirmation_message($member) {
        $message = "Dear {member_name},\n\n";
        $message .= "Thank you for registering with our Chamber of Commerce!\n\n";
        $message .= "Your registration has been received and is being processed. You will receive a welcome email once your membership is activated.\n\n";
        $message .= "If you have any questions, please don't hesitate to contact us.\n\n";
        $message .= "Best regards,\nThe Chamber Team";
        
        return $this->replace_placeholders($message, $member);
    }
    
    /**
     * Get expiration subject
     * @param object $member
     * @return string
     */
    private function get_expiration_subject($member) {
        return 'Your membership has expired';
    }
    
    /**
     * Get expiration message
     * @param object $member
     * @return string
     */
    private function get_expiration_message($member) {
        $message = "Dear {member_name},\n\n";
        $message .= "Your chamber membership has expired. To continue enjoying member benefits, please renew your membership.\n\n";
        $message .= "Renew now: {renewal_url}\n\n";
        $message .= "Thank you for your past membership!\n\n";
        $message .= "Best regards,\nThe Chamber Team";
        
        return $this->replace_placeholders($message, $member);
    }
    
    /**
     * Get admin listing notification message
     * @param object $listing
     * @return string
     */
    private function get_admin_listing_notification_message($listing) {
        $message = "A new business listing has been submitted and is pending approval.\n\n";
        $message .= "Business Name: " . $listing->post_title . "\n";
        $message .= "Submitted by: " . get_the_author_meta('display_name', $listing->post_author) . "\n";
        $message .= "Submission Date: " . date('F j, Y g:i A', strtotime($listing->post_date)) . "\n\n";
        $message .= "Review and approve: " . admin_url('post.php?post=' . $listing->ID . '&action=edit') . "\n\n";
        $message .= "View all pending listings: " . admin_url('edit.php?post_type=chamberboss_listing&post_status=pending');
        
        return $message;
    }
    
    /**
     * Replace placeholders in message
     * @param string $message
     * @param object $member
     * @param object $subscription
     * @return string
     */
    private function replace_placeholders($message, $member, $subscription = null) {
        $placeholders = [
            '{member_name}' => $member->post_title,
            '{site_name}' => get_bloginfo('name'),
            '{site_url}' => home_url(),
            '{directory_url}' => home_url('/business-directory/'),
            '{login_url}' => wp_login_url(),
            '{renewal_url}' => home_url('/member-registration/'),
        ];
        
        if ($subscription) {
            $placeholders['{expiry_date}'] = date('F j, Y', strtotime($subscription->end_date));
        }
        
        return str_replace(array_keys($placeholders), array_values($placeholders), $message);
    }
    
    /**
     * Get default renewal message
     * @return string
     */
    private function get_default_renewal_message() {
        return "Dear {member_name},\n\nYour chamber membership is set to expire on {expiry_date}. To continue enjoying all the benefits of membership, please renew your subscription.\n\nRenew now: {renewal_url}\n\nThank you for being a valued member!\n\nBest regards,\nThe Chamber Team";
    }
    
    /**
     * Get default welcome message
     * @return string
     */
    private function get_default_welcome_message() {
        return "Dear {member_name},\n\nWelcome to our Chamber of Commerce! We're excited to have you as a new member.\n\nYou can now:\n- Submit business listings to our directory\n- Access member-only resources\n- Connect with other local businesses\n\nExplore our business directory: {directory_url}\nAccess your account: {login_url}\n\nIf you have any questions, please don't hesitate to contact us.\n\nWelcome aboard!\n\nBest regards,\nThe Chamber Team";
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
     * Cleanup old notifications
     */
    private function cleanup_old_notifications() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'chamberboss_notifications';
        $cleanup_date = date('Y-m-d H:i:s', strtotime('-90 days'));
        
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE created_at < %s AND status = 'sent'",
            $cleanup_date
        ));
        
        if ($deleted > 0) {
            $this->log("Cleaned up {$deleted} old notifications");
        }
    }
}

