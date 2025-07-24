<?php
/**
 * Stripe Webhook Handler for Chamber Boss
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class CB_Stripe_Webhook_Handler {
    
    /**
     * Handle Stripe webhook events
     */
    public static function handle_webhook() {
        // Get the webhook secret from settings
        $webhook_secret = get_option('cb_stripe_webhook_secret');
        
        // Get the payload and signature
        $payload = @file_get_contents('php://input');
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
        
        try {
            // Verify the webhook signature
            $event = \Stripe\Webhook::constructEvent(
                $payload, 
                $sig_header, 
                $webhook_secret
            );
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            // Invalid signature
            http_response_code(400);
            exit();
        } catch (Exception $e) {
            // Other errors
            http_response_code(400);
            exit();
        }
        
        // Handle the event
        switch ($event->type) {
            case 'checkout.session.completed':
                self::handle_checkout_session_completed($event->data->object);
                break;
            case 'invoice.payment_succeeded':
                self::handle_invoice_payment_succeeded($event->data->object);
                break;
            case 'invoice.payment_failed':
                self::handle_invoice_payment_failed($event->data->object);
                break;
            default:
                // Unexpected event type
                http_response_code(400);
                exit();
        }
        
        http_response_code(200);
    }
    
    /**
     * Handle checkout session completed event
     */
    private static function handle_checkout_session_completed($session) {
        // Get the user ID from the session metadata
        $user_id = $session->metadata->user_id;
        
        if (!$user_id) {
            return;
        }
        
        // Get the user
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            return;
        }
        
        // Create or update membership
        self::create_or_update_membership($user_id, $session);
        
        // Add user to MailPoet members list
        self::add_user_to_mailpoet_members_list($user->user_email);
    }
    
    /**
     * Handle invoice payment succeeded event
     */
    private static function handle_invoice_payment_succeeded($invoice) {
        // Get the customer ID
        $customer_id = $invoice->customer;
        
        // Get the user with this Stripe customer ID
        $user = self::get_user_by_stripe_customer_id($customer_id);
        if (!$user) {
            return;
        }
        
        // Update membership
        self::update_membership($user->ID, $invoice);
    }
    
    /**
     * Handle invoice payment failed event
     */
    private static function handle_invoice_payment_failed($invoice) {
        // Get the customer ID
        $customer_id = $invoice->customer;
        
        // Get the user with this Stripe customer ID
        $user = self::get_user_by_stripe_customer_id($customer_id);
        if (!$user) {
            return;
        }
        
        // Send notification to user
        self::send_payment_failed_notification($user, $invoice);
    }
    
    /**
     * Create or update membership
     */
    private static function create_or_update_membership($user_id, $session) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cb_memberships';
        
        // Check if membership already exists
        $membership = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d", 
            $user_id
        ));
        
        $data = array(
            'user_id' => $user_id,
            'membership_type' => 'premium', // Default to premium, can be customized
            'start_date' => current_time('mysql'),
            'status' => 'active',
            'stripe_customer_id' => $session->customer,
            'stripe_subscription_id' => isset($session->subscription) ? $session->subscription : null,
        );
        
        if ($membership) {
            // Update existing membership
            $wpdb->update(
                $table_name,
                $data,
                array('id' => $membership->id)
            );
        } else {
            // Create new membership
            $wpdb->insert($table_name, $data);
        }
    }
    
    /**
     * Update membership
     */
    private static function update_membership($user_id, $invoice) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cb_memberships';
        
        // Update membership end date
        $wpdb->update(
            $table_name,
            array(
                'end_date' => date('Y-m-d H:i:s', $invoice->period_end),
                'status' => 'active'
            ),
            array('user_id' => $user_id)
        );
    }
    
    /**
     * Get user by Stripe customer ID
     */
    private static function get_user_by_stripe_customer_id($customer_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cb_memberships';
        
        $user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM $table_name WHERE stripe_customer_id = %s",
            $customer_id
        ));
        
        if ($user_id) {
            return get_user_by('ID', $user_id);
        }
        
        return false;
    }
    
    /**
     * Add user to MailPoet members list
     */
    private static function add_user_to_mailpoet_members_list($email) {
        // Check if MailPoet is active
        if (!class_exists(\MailPoet\API\API::class)) {
            return;
        }
        
        try {
            $mailpoet_api = \MailPoet\API\API::MP('v1');
            $members_list_id = get_option('cb_mailpoet_members_list_id');
            
            if ($members_list_id) {
                $mailpoet_api->subscribe($email, array($members_list_id));
            }
        } catch (Exception $e) {
            // Log error
            error_log('Chamber Boss MailPoet integration error: ' . $e->getMessage());
        }
    }
    
    /**
     * Send payment failed notification
     */
    private static function send_payment_failed_notification($user, $invoice) {
        $subject = 'Payment Failed for Your Chamber Membership';
        $message = "Hello {$user->display_name},\n\n";
        $message .= "We were unable to process your membership payment. Please update your payment method to continue enjoying your membership benefits.\n\n";
        $message .= "If you have any questions, please contact us.\n\n";
        $message .= "Thank you!";
        
        wp_mail($user->user_email, $subject, $message);
    }
}

// Handle the webhook when the endpoint is called
if (isset($_GET['cb-stripe-webhook'])) {
    CB_Stripe_Webhook_Handler::handle_webhook();
}