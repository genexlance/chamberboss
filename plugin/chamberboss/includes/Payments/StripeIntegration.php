<?php
namespace Chamberboss\Payments;

use Chamberboss\Core\BaseClass;
use Chamberboss\Core\Database;

/**
 * Stripe Integration Handler
 */
class StripeIntegration extends BaseClass {
    
    /**
     * Stripe configuration instance
     * @var StripeConfig
     */
    private $config;
    
    /**
     * Database instance
     * @var Database
     */
    private $database;
    
    /**
     * Initialize Stripe integration
     */
    protected function init() {
        $this->config = new StripeConfig();
        $this->database = new Database();
        
        // Initialize Stripe SDK if configured
        if ($this->config->is_configured()) {
            $this->init_stripe_sdk();
        }
        
        // Hook into WordPress actions
        add_action('wp_ajax_chamberboss_create_payment_intent', [$this, 'ajax_create_payment_intent']);
        add_action('wp_ajax_nopriv_chamberboss_create_payment_intent', [$this, 'ajax_create_payment_intent']);
        add_action('wp_ajax_chamberboss_confirm_payment', [$this, 'ajax_confirm_payment']);
        add_action('wp_ajax_nopriv_chamberboss_confirm_payment', [$this, 'ajax_confirm_payment']);
        
        // Webhook handler
        add_action('wp_ajax_chamberboss_stripe_webhook', [$this, 'handle_webhook']);
        add_action('wp_ajax_nopriv_chamberboss_stripe_webhook', [$this, 'handle_webhook']);
        
        // Custom endpoint for webhooks
        add_action('init', [$this, 'add_webhook_endpoint']);
        add_action('parse_request', [$this, 'handle_webhook_request']);
        
        // Enqueue scripts
        add_action('wp_enqueue_scripts', [$this, 'enqueue_stripe_scripts']);
    }
    
    /**
     * Initialize Stripe SDK
     */
    private function init_stripe_sdk() {
        if (!class_exists('\\Stripe\\Stripe')) {
            // Include Stripe SDK (would need to be installed via Composer)
            $this->log('Stripe SDK not found. Please install via Composer: composer require stripe/stripe-php', 'error');
            return;
        }
        
        try {
            \Stripe\Stripe::setApiKey($this->config->get_secret_key());
            \Stripe\Stripe::setApiVersion('2023-10-16');
        } catch (Exception $e) {
            $this->log('Failed to initialize Stripe SDK: ' . $e->getMessage(), 'error');
        }
    }
    
    /**
     * Create Stripe customer
     * @param int $member_id
     * @param array $customer_data
     * @return string|false Customer ID or false on failure
     */
    public function create_customer($member_id, $customer_data) {
        if (!$this->config->is_configured()) {
            return false;
        }
        
        try {
            $customer = \Stripe\Customer::create([
                'email' => $customer_data['email'],
                'name' => $customer_data['name'],
                'phone' => $customer_data['phone'] ?? null,
                'address' => $customer_data['address'] ?? null,
                'metadata' => [
                    'member_id' => $member_id,
                    'source' => 'chamberboss'
                ]
            ]);
            
            // Store customer ID in member meta
            update_post_meta($member_id, '_chamberboss_stripe_customer_id', $customer->id);
            
            $this->log("Created Stripe customer {$customer->id} for member {$member_id}");
            
            return $customer->id;
            
        } catch (\Stripe\Exception\ApiErrorException $e) {
            $this->log('Stripe customer creation failed: ' . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Create payment intent for membership
     * @param int $member_id
     * @param float $amount
     * @param string $currency
     * @return array|false
     */
    public function create_payment_intent($member_id, $amount, $currency = 'USD') {
        if (!$this->config->is_configured()) {
            return false;
        }
        
        try {
            $customer_id = get_post_meta($member_id, '_chamberboss_stripe_customer_id', true);
            
            $intent_data = [
                'amount' => intval($amount * 100), // Convert to cents
                'currency' => strtolower($currency),
                'automatic_payment_methods' => ['enabled' => true],
                'metadata' => [
                    'member_id' => $member_id,
                    'type' => 'membership_payment'
                ]
            ];
            
            if ($customer_id) {
                $intent_data['customer'] = $customer_id;
            }
            
            $intent = \Stripe\PaymentIntent::create($intent_data);
            
            // Record transaction in database
            $this->database->add_transaction([
                'member_id' => $member_id,
                'stripe_payment_intent_id' => $intent->id,
                'amount' => $amount,
                'currency' => $currency,
                'status' => 'pending',
                'transaction_type' => 'membership_payment'
            ]);
            
            return [
                'client_secret' => $intent->client_secret,
                'payment_intent_id' => $intent->id
            ];
            
        } catch (\Stripe\Exception\ApiErrorException $e) {
            $this->log('Payment intent creation failed: ' . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Create subscription for member
     * @param int $member_id
     * @param string $price_id
     * @return array|false
     */
    public function create_subscription($member_id, $price_id) {
        if (!$this->config->is_configured()) {
            return false;
        }
        
        try {
            $customer_id = get_post_meta($member_id, '_chamberboss_stripe_customer_id', true);
            
            if (!$customer_id) {
                $this->log("No Stripe customer found for member {$member_id}", 'error');
                return false;
            }
            
            $subscription = \Stripe\Subscription::create([
                'customer' => $customer_id,
                'items' => [['price' => $price_id]],
                'payment_behavior' => 'default_incomplete',
                'payment_settings' => ['save_default_payment_method' => 'on_subscription'],
                'expand' => ['latest_invoice.payment_intent'],
                'metadata' => [
                    'member_id' => $member_id,
                    'source' => 'chamberboss'
                ]
            ]);
            
            // Store subscription ID
            update_post_meta($member_id, '_chamberboss_stripe_subscription_id', $subscription->id);
            
            // Update database
            $this->database->upsert_member_subscription($member_id, [
                'stripe_customer_id' => $customer_id,
                'stripe_subscription_id' => $subscription->id,
                'status' => $subscription->status,
                'start_date' => date('Y-m-d H:i:s', $subscription->current_period_start),
                'end_date' => date('Y-m-d H:i:s', $subscription->current_period_end),
                'next_billing_date' => date('Y-m-d H:i:s', $subscription->current_period_end)
            ]);
            
            return [
                'subscription_id' => $subscription->id,
                'client_secret' => $subscription->latest_invoice->payment_intent->client_secret
            ];
            
        } catch (\Stripe\Exception\ApiErrorException $e) {
            $this->log('Subscription creation failed: ' . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Cancel subscription
     * @param int $member_id
     * @return bool
     */
    public function cancel_subscription($member_id) {
        if (!$this->config->is_configured()) {
            return false;
        }
        
        try {
            $subscription_id = get_post_meta($member_id, '_chamberboss_stripe_subscription_id', true);
            
            if (!$subscription_id) {
                return false;
            }
            
            $subscription = \Stripe\Subscription::retrieve($subscription_id);
            $subscription->cancel();
            
            // Update database
            $this->database->upsert_member_subscription($member_id, [
                'status' => 'cancelled',
                'cancelled_at' => current_time('mysql')
            ]);
            
            // Update post meta
            update_post_meta($member_id, '_chamberboss_subscription_status', 'cancelled');
            
            $this->log("Cancelled subscription {$subscription_id} for member {$member_id}");
            
            return true;
            
        } catch (\Stripe\Exception\ApiErrorException $e) {
            $this->log('Subscription cancellation failed: ' . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * AJAX handler for creating payment intent
     */
    public function ajax_create_payment_intent() {
        if (!$this->verify_nonce($_POST['nonce'] ?? '', 'chamberboss_payment')) {
            $this->send_json_response(['message' => 'Invalid nonce'], false);
            return;
        }
        
        $member_id = intval($_POST['member_id'] ?? 0);
        $amount = floatval($_POST['amount'] ?? 0);
        
        if (!$member_id || !$amount) {
            $this->send_json_response(['message' => 'Invalid parameters'], false);
            return;
        }
        
        $result = $this->create_payment_intent($member_id, $amount);
        
        if ($result) {
            $this->send_json_response($result);
        } else {
            $this->send_json_response(['message' => 'Failed to create payment intent'], false);
        }
    }
    
    /**
     * AJAX handler for confirming payment
     */
    public function ajax_confirm_payment() {
        if (!$this->verify_nonce($_POST['nonce'] ?? '', 'chamberboss_payment')) {
            $this->send_json_response(['message' => 'Invalid nonce'], false);
            return;
        }
        
        $payment_intent_id = sanitize_text_field($_POST['payment_intent_id'] ?? '');
        
        if (!$payment_intent_id) {
            $this->send_json_response(['message' => 'Invalid payment intent ID'], false);
            return;
        }
        
        try {
            $intent = \Stripe\PaymentIntent::retrieve($payment_intent_id);
            
            if ($intent->status === 'succeeded') {
                $member_id = intval($intent->metadata->member_id ?? 0);
                
                if ($member_id) {
                    $this->process_successful_payment($member_id, $intent);
                }
                
                $this->send_json_response(['status' => 'succeeded']);
            } else {
                $this->send_json_response(['status' => $intent->status]);
            }
            
        } catch (\Stripe\Exception\ApiErrorException $e) {
            $this->log('Payment confirmation failed: ' . $e->getMessage(), 'error');
            $this->send_json_response(['message' => 'Payment confirmation failed'], false);
        }
    }
    
    /**
     * Process successful payment
     * @param int $member_id
     * @param object $payment_intent
     */
    private function process_successful_payment($member_id, $payment_intent) {
        // Update transaction status
        global $wpdb;
        $table = $wpdb->prefix . 'chamberboss_transactions';
        
        $wpdb->update(
            $table,
            ['status' => 'completed', 'updated_at' => current_time('mysql')],
            ['stripe_payment_intent_id' => $payment_intent->id]
        );
        
        // Activate membership
        $end_date = date('Y-m-d H:i:s', strtotime('+1 year'));
        
        update_post_meta($member_id, '_chamberboss_subscription_status', 'active');
        update_post_meta($member_id, '_chamberboss_subscription_start', current_time('mysql'));
        update_post_meta($member_id, '_chamberboss_subscription_end', $end_date);
        
        // Update database subscription
        $this->database->upsert_member_subscription($member_id, [
            'status' => 'active',
            'start_date' => current_time('mysql'),
            'end_date' => $end_date,
            'next_billing_date' => $end_date
        ]);
        
        // Trigger membership activation actions
        do_action('chamberboss_membership_activated', $member_id);
        
        $this->log("Membership activated for member {$member_id}");
    }
    
    /**
     * Add webhook endpoint
     */
    public function add_webhook_endpoint() {
        add_rewrite_rule(
            '^chamberboss/webhook/?$',
            'index.php?chamberboss_webhook=1',
            'top'
        );
        
        add_filter('query_vars', function($vars) {
            $vars[] = 'chamberboss_webhook';
            return $vars;
        });
    }
    
    /**
     * Handle webhook request
     */
    public function handle_webhook_request() {
        if (get_query_var('chamberboss_webhook')) {
            $this->handle_webhook();
            exit;
        }
    }
    
    /**
     * Handle Stripe webhook
     */
    public function handle_webhook() {
        $payload = @file_get_contents('php://input');
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        $webhook_secret = $this->config->get_webhook_secret();
        
        if (empty($webhook_secret)) {
            http_response_code(400);
            exit('Webhook secret not configured');
        }
        
        try {
            $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $webhook_secret);
        } catch (\UnexpectedValueException $e) {
            http_response_code(400);
            exit('Invalid payload');
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            http_response_code(400);
            exit('Invalid signature');
        }
        
        $this->process_webhook_event($event);
        
        http_response_code(200);
        exit('OK');
    }
    
    /**
     * Process webhook event
     * @param object $event
     */
    private function process_webhook_event($event) {
        switch ($event->type) {
            case 'payment_intent.succeeded':
                $this->handle_payment_succeeded($event->data->object);
                break;
                
            case 'payment_intent.payment_failed':
                $this->handle_payment_failed($event->data->object);
                break;
                
            case 'invoice.payment_succeeded':
                $this->handle_invoice_payment_succeeded($event->data->object);
                break;
                
            case 'customer.subscription.updated':
                $this->handle_subscription_updated($event->data->object);
                break;
                
            case 'customer.subscription.deleted':
                $this->handle_subscription_deleted($event->data->object);
                break;
                
            default:
                $this->log("Unhandled webhook event: {$event->type}");
        }
    }
    
    /**
     * Handle payment succeeded webhook
     * @param object $payment_intent
     */
    private function handle_payment_succeeded($payment_intent) {
        $member_id = intval($payment_intent->metadata->member_id ?? 0);
        
        if ($member_id) {
            $this->process_successful_payment($member_id, $payment_intent);
        }
    }
    
    /**
     * Handle payment failed webhook
     * @param object $payment_intent
     */
    private function handle_payment_failed($payment_intent) {
        $member_id = intval($payment_intent->metadata->member_id ?? 0);
        
        if ($member_id) {
            // Update transaction status
            global $wpdb;
            $table = $wpdb->prefix . 'chamberboss_transactions';
            
            $wpdb->update(
                $table,
                ['status' => 'failed', 'updated_at' => current_time('mysql')],
                ['stripe_payment_intent_id' => $payment_intent->id]
            );
            
            // Trigger payment failed actions
            do_action('chamberboss_payment_failed', $member_id, $payment_intent);
            
            $this->log("Payment failed for member {$member_id}");
        }
    }
    
    /**
     * Handle invoice payment succeeded webhook
     * @param object $invoice
     */
    private function handle_invoice_payment_succeeded($invoice) {
        if ($invoice->subscription) {
            $subscription = \Stripe\Subscription::retrieve($invoice->subscription);
            $member_id = intval($subscription->metadata->member_id ?? 0);
            
            if ($member_id) {
                // Update subscription in database
                $this->database->upsert_member_subscription($member_id, [
                    'status' => $subscription->status,
                    'next_billing_date' => date('Y-m-d H:i:s', $subscription->current_period_end)
                ]);
                
                // Add transaction record
                $this->database->add_transaction([
                    'member_id' => $member_id,
                    'stripe_subscription_id' => $subscription->id,
                    'amount' => $invoice->amount_paid / 100,
                    'currency' => strtoupper($invoice->currency),
                    'status' => 'completed',
                    'transaction_type' => 'subscription_renewal'
                ]);
                
                $this->log("Subscription renewed for member {$member_id}");
            }
        }
    }
    
    /**
     * Handle subscription updated webhook
     * @param object $subscription
     */
    private function handle_subscription_updated($subscription) {
        $member_id = intval($subscription->metadata->member_id ?? 0);
        
        if ($member_id) {
            $this->database->upsert_member_subscription($member_id, [
                'status' => $subscription->status,
                'start_date' => date('Y-m-d H:i:s', $subscription->current_period_start),
                'end_date' => date('Y-m-d H:i:s', $subscription->current_period_end),
                'next_billing_date' => date('Y-m-d H:i:s', $subscription->current_period_end)
            ]);
            
            update_post_meta($member_id, '_chamberboss_subscription_status', $subscription->status);
        }
    }
    
    /**
     * Handle subscription deleted webhook
     * @param object $subscription
     */
    private function handle_subscription_deleted($subscription) {
        $member_id = intval($subscription->metadata->member_id ?? 0);
        
        if ($member_id) {
            $this->database->upsert_member_subscription($member_id, [
                'status' => 'cancelled',
                'cancelled_at' => current_time('mysql')
            ]);
            
            update_post_meta($member_id, '_chamberboss_subscription_status', 'cancelled');
            
            $this->log("Subscription cancelled for member {$member_id}");
        }
    }
    
    /**
     * Enqueue Stripe scripts
     */
    public function enqueue_stripe_scripts() {
        if ($this->config->is_configured()) {
            wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', [], null, true);
            
            wp_localize_script('stripe-js', 'chamberboss_stripe', [
                'publishable_key' => $this->config->get_publishable_key(),
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('chamberboss_payment')
            ]);
        }
    }
}

