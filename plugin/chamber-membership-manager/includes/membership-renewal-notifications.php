<?php
/**
 * Membership renewal notifications for Chamber Boss
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class CB_Membership_Renewal_Notifications {
    
    /**
     * Initialize the renewal notification system
     */
    public static function init() {
        // Schedule daily check for expiring memberships
        if (!wp_next_scheduled('cb_check_membership_renewals')) {
            wp_schedule_event(time(), 'daily', 'cb_check_membership_renewals');
        }
        
        // Hook into the scheduled event
        add_action('cb_check_membership_renewals', array(__CLASS__, 'check_membership_renewals'));
    }
    
    /**
     * Check for memberships that need renewal notifications
     */
    public static function check_membership_renewals() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cb_memberships';
        
        // Get memberships expiring in the next 7 days
        $expiring_memberships = $wpdb->get_results(
            "SELECT * FROM $table_name 
             WHERE status = 'active' 
             AND end_date <= DATE_ADD(NOW(), INTERVAL 7 DAY) 
             AND end_date >= NOW()"
        );
        
        foreach ($expiring_memberships as $membership) {
            self::send_renewal_notification($membership);
        }
        
        // Get memberships that have already expired
        $expired_memberships = $wpdb->get_results(
            "SELECT * FROM $table_name 
             WHERE status = 'active' 
             AND end_date < NOW()"
        );
        
        foreach ($expired_memberships as $membership) {
            self::send_expiration_notification($membership);
            
            // Update membership status to expired
            $wpdb->update(
                $table_name,
                array('status' => 'expired'),
                array('id' => $membership->id)
            );
            
            // Remove user from MailPoet members list
            self::remove_user_from_mailpoet_members_list($membership->user_id);
        }
    }
    
    /**
     * Send renewal notification to member
     */
    private static function send_renewal_notification($membership) {
        $user = get_user_by('ID', $membership->user_id);
        if (!$user) {
            return;
        }
        
        $subject = 'Your Chamber Membership Expires Soon';
        $message = "Hello {$user->display_name},\n\n";
        $message .= "Your chamber membership is set to expire on " . date('F j, Y', strtotime($membership->end_date)) . ".\n\n";
        $message .= "Please renew your membership to continue enjoying all the benefits.\n\n";
        $message .= "Thank you for your continued support!";
        
        wp_mail($user->user_email, $subject, $message);
    }
    
    /**
     * Send expiration notification to member
     */
    private static function send_expiration_notification($membership) {
        $user = get_user_by('ID', $membership->user_id);
        if (!$user) {
            return;
        }
        
        $subject = 'Your Chamber Membership Has Expired';
        $message = "Hello {$user->display_name},\n\n";
        $message .= "Your chamber membership expired on " . date('F j, Y', strtotime($membership->end_date)) . ".\n\n";
        $message .= "Please renew your membership to continue enjoying all the benefits.\n\n";
        $message .= "Thank you for your continued support!";
        
        wp_mail($user->user_email, $subject, $message);
    }
    
    /**
     * Remove user from MailPoet members list
     */
    private static function remove_user_from_mailpoet_members_list($user_id) {
        // Check if MailPoet is active
        if (!class_exists(\MailPoet\API\API::class)) {
            return;
        }
        
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            return;
        }
        
        try {
            $mailpoet_api = \MailPoet\API\API::MP('v1');
            $members_list_id = get_option('cb_mailpoet_members_list_id');
            $nonmembers_list_id = get_option('cb_mailpoet_nonmembers_list_id');
            
            if ($members_list_id) {
                $mailpoet_api->unsubscribe($user->user_email, array($members_list_id));
            }
            
            if ($nonmembers_list_id) {
                $mailpoet_api->subscribe($user->user_email, array($nonmembers_list_id));
            }
        } catch (Exception $e) {
            // Log error
            error_log('Chamber Boss MailPoet integration error: ' . $e->getMessage());
        }
    }
}

// Initialize the renewal notification system
CB_Membership_Renewal_Notifications::init();