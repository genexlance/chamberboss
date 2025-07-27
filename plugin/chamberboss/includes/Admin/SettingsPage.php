<?php
namespace Chamberboss\Admin;

use Chamberboss\Core\BaseClass;
use Chamberboss\Payments\StripeConfig;

/**
 * Settings Admin Page
 */
class SettingsPage extends BaseClass {
    
    /**
     * Stripe configuration instance
     * @var StripeConfig
     */
    private $stripe_config;
    
    /**
     * Initialize settings page
     */
    protected function init() {
        $this->stripe_config = new StripeConfig();
        
        // Handle form submissions
        add_action('admin_init', [$this, 'handle_settings_save']);
    }
    
    /**
     * Render settings page
     */
    public function render() {
        $active_tab = $_GET['tab'] ?? 'general';
        $message = $_GET['message'] ?? '';
        
        ?>
        <div class="wrap">
            <h1><?php _e('Chamberboss Settings', 'chamberboss'); ?></h1>
            
            <?php if ($message === 'saved'): ?>
                <div class="chamberboss-notice chamberboss-notice-success">
                    <p><?php _e('Settings saved successfully.', 'chamberboss'); ?></p>
                </div>
            <?php elseif ($message === 'error'): ?>
                <div class="chamberboss-notice chamberboss-notice-error">
                    <p><?php _e('An error occurred while saving settings.', 'chamberboss'); ?></p>
                </div>
            <?php endif; ?>
            
            <div class="chamberboss-settings">
                <!-- Navigation Tabs -->
                <nav class="chamberboss-settings-nav">
                    <a href="<?php echo admin_url('admin.php?page=chamberboss-settings&tab=general'); ?>" 
                       class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
                        <?php _e('General', 'chamberboss'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=chamberboss-settings&tab=stripe'); ?>" 
                       class="nav-tab <?php echo $active_tab === 'stripe' ? 'nav-tab-active' : ''; ?>">
                        <?php _e('Stripe', 'chamberboss'); ?>
                    </a>
                    
                    <a href="<?php echo admin_url('admin.php?page=chamberboss-settings&tab=mailpoet'); ?>" 
                       class="nav-tab <?php echo $active_tab === 'mailpoet' ? 'nav-tab-active' : ''; ?>">
                        <?php _e('MailPoet', 'chamberboss'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=chamberboss-settings&tab=email'); ?>" 
                       class="nav-tab <?php echo $active_tab === 'email' ? 'nav-tab-active' : ''; ?>">
                        <?php _e('Email', 'chamberboss'); ?>
                    </a>
                </nav>
                
                <!-- Tab Content -->
                <?php
                switch ($active_tab) {
                    case 'stripe':
                        $this->render_stripe_settings();
                        break;
                    case 'mailpoet':
                        $this->render_mailpoet_settings();
                        break;
                    case 'categories':
                        $this->render_categories_settings();
                        break;
                    case 'email':
                        $this->render_email_settings();
                        break;
                    default:
                        $this->render_general_settings();
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render general settings
     */
    private function render_general_settings() {
        $membership_price = $this->get_option('chamberboss_membership_price', '100.00');
        $currency = $this->get_option('chamberboss_currency', 'USD');
        $renewal_days = $this->get_option('chamberboss_renewal_days', '30');
        
        ?>
        <div class="chamberboss-settings-section">
            <h3><?php _e('General Settings', 'chamberboss'); ?></h3>
            
            <form method="post" action="">
                <?php wp_nonce_field('chamberboss_general_settings', 'chamberboss_general_nonce'); ?>
                <input type="hidden" name="action" value="save_general_settings">
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="membership_price"><?php _e('Membership Price', 'chamberboss'); ?></label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="membership_price" 
                                   name="membership_price" 
                                   value="<?php echo esc_attr($membership_price); ?>" 
                                   step="0.01" 
                                   min="0" 
                                   class="regular-text" 
                                   required>
                            <p class="description">
                                <?php _e('Annual membership price in your selected currency.', 'chamberboss'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="currency"><?php _e('Currency', 'chamberboss'); ?></label>
                        </th>
                        <td>
                            <select id="currency" name="currency">
                                <option value="USD" <?php selected($currency, 'USD'); ?>>USD - US Dollar</option>
                                <option value="EUR" <?php selected($currency, 'EUR'); ?>>EUR - Euro</option>
                                <option value="GBP" <?php selected($currency, 'GBP'); ?>>GBP - British Pound</option>
                                <option value="CAD" <?php selected($currency, 'CAD'); ?>>CAD - Canadian Dollar</option>
                                <option value="AUD" <?php selected($currency, 'AUD'); ?>>AUD - Australian Dollar</option>
                            </select>
                            <p class="description">
                                <?php _e('Currency for membership payments.', 'chamberboss'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="renewal_days"><?php _e('Renewal Notification Days', 'chamberboss'); ?></label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="renewal_days" 
                                   name="renewal_days" 
                                   value="<?php echo esc_attr($renewal_days); ?>" 
                                   min="1" 
                                   max="365" 
                                   class="small-text" 
                                   required>
                            <p class="description">
                                <?php _e('Number of days before membership expiry to send renewal notifications.', 'chamberboss'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(__('Save General Settings', 'chamberboss')); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render Stripe settings
     */
    private function render_stripe_settings() {
        $stripe_settings = $this->stripe_config->get_all_settings();
        
        ?>
        <div class="chamberboss-settings-section">
            <h3><?php _e('Stripe Configuration', 'chamberboss'); ?></h3>
            
            <form method="post" action="">
                <?php wp_nonce_field('chamberboss_stripe_settings', 'chamberboss_stripe_nonce'); ?>
                <input type="hidden" name="action" value="save_stripe_settings">
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="stripe_mode"><?php _e('Mode', 'chamberboss'); ?></label>
                        </th>
                        <td>
                            <select id="stripe_mode" name="stripe_mode">
                                <option value="test" <?php selected($stripe_settings['mode'], 'test'); ?>>
                                    <?php _e('Test Mode', 'chamberboss'); ?>
                                </option>
                                <option value="live" <?php selected($stripe_settings['mode'], 'live'); ?>>
                                    <?php _e('Live Mode', 'chamberboss'); ?>
                                </option>
                            </select>
                            <span class="stripe-mode-indicator stripe-mode-<?php echo esc_attr($stripe_settings['mode']); ?>">
                                <?php echo esc_html(strtoupper($stripe_settings['mode'])); ?>
                            </span>
                            <p class="description">
                                <?php _e('Use test mode for development and live mode for production.', 'chamberboss'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <h4><?php _e('Test Mode Keys', 'chamberboss'); ?></h4>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="test_publishable_key"><?php _e('Test Publishable Key', 'chamberboss'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="test_publishable_key" 
                                   name="test_publishable_key" 
                                   value="<?php echo esc_attr($stripe_settings['test_publishable_key']); ?>" 
                                   class="large-text" 
                                   placeholder="pk_test_...">
                            <p class="description">
                                <?php _e('Your Stripe test publishable key (starts with pk_test_). Find it in your Stripe Dashboard under ', 'chamberboss'); ?>
                                <a href="https://dashboard.stripe.com/test/apikeys" target="_blank"><?php _e('API keys', 'chamberboss'); ?></a>.
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="test_secret_key"><?php _e('Test Secret Key', 'chamberboss'); ?></label>
                        </th>
                        <td>
                            <input type="password" 
                                   id="test_secret_key" 
                                   name="test_secret_key" 
                                   value="<?php echo esc_attr($stripe_settings['test_secret_key']); ?>" 
                                   class="large-text" 
                                   placeholder="sk_test_...">
                            <?php if (!empty($stripe_settings['test_secret_key'])): ?>
                                <p class="description">
                                    <strong><?php _e('Current key:', 'chamberboss'); ?></strong> 
                                    <span class="stripe-key-display">
                                        <?php echo esc_html($this->stripe_config->get_masked_key($stripe_settings['test_secret_key'])); ?>
                                    </span>
                                </p>
                            <?php endif; ?>
                            <p class="description">
                                <?php _e('Your Stripe test secret key (starts with sk_test_). Reveal and copy it from your Stripe Dashboard under ', 'chamberboss'); ?>
                                <a href="https://dashboard.stripe.com/test/apikeys" target="_blank"><?php _e('API keys', 'chamberboss'); ?></a>.
                            </p>
                        </td>
                    </tr>
                </table>
                
                <h4><?php _e('Live Mode Keys', 'chamberboss'); ?></h4>
                <p class="description"><?php _e('Switch to Live Mode in your Stripe Dashboard to find these keys.', 'chamberboss'); ?></p>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="live_publishable_key"><?php _e('Live Publishable Key', 'chamberboss'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="live_publishable_key" 
                                   name="live_publishable_key" 
                                   value="<?php echo esc_attr($stripe_settings['live_publishable_key']); ?>" 
                                   class="large-text" 
                                   placeholder="pk_live_...">
                            <p class="description">
                                <?php _e('Your Stripe live publishable key (starts with pk_live_).', 'chamberboss'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="live_secret_key"><?php _e('Live Secret Key', 'chamberboss'); ?></label>
                        </th>
                        <td>
                            <input type="password" 
                                   id="live_secret_key" 
                                   name="live_secret_key" 
                                   value="<?php echo esc_attr($stripe_settings['live_secret_key']); ?>" 
                                   class="large-text" 
                                   placeholder="sk_live_...">
                            <?php if (!empty($stripe_settings['live_secret_key'])): ?>
                                <p class="description">
                                    <strong><?php _e('Current key:', 'chamberboss'); ?></strong> 
                                    <span class="stripe-key-display">
                                        <?php echo esc_html($this->stripe_config->get_masked_key($stripe_settings['live_secret_key'])); ?>
                                    </span>
                                </p>
                            <?php endif; ?>
                            <p class="description">
                                <?php _e('Your Stripe live secret key (starts with sk_live_). Reveal and copy it from your Stripe Dashboard under ', 'chamberboss'); ?>
                                <a href="https://dashboard.stripe.com/apikeys" target="_blank"><?php _e('API keys', 'chamberboss'); ?></a>.
                            </p>
                        </td>
                    </tr>
                </table>
                
                <h4><?php _e('Webhook Configuration', 'chamberboss'); ?></h4>
                <p class="description">
                    <?php _e('To receive real-time updates from Stripe (e.g., successful payments, subscription changes), you need to set up a webhook endpoint in your Stripe Dashboard.', 'chamberboss'); ?><br>
                    <?php _e('1. Go to ', 'chamberboss'); ?>
                    <a href="https://dashboard.stripe.com/webhooks" target="_blank"><?php _e('Stripe Dashboard > Developers > Webhooks', 'chamberboss'); ?></a>.<br>
                    <?php _e("2. Click 'Add endpoint' or select an existing one.", 'chamberboss'); ?><br>
                    <?php _e('3. Set the Endpoint URL to:', 'chamberboss'); ?> <code><?php echo home_url('/chamberboss/webhook/'); ?></code><br>
                    <?php _e('4. Select events to send. We recommend at least: ', 'chamberboss'); ?><code>customer.subscription.updated</code>, <code>checkout.session.completed</code>, <code>invoice.payment_succeeded</code>.<br>
                    <?php _e("5. Click 'Add endpoint'. After creation, click on the endpoint and find the 'Signing secret' (starts with 'whsec_'). Copy and paste it here.", 'chamberboss'); ?><br>
                </p>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="webhook_secret"><?php _e('Webhook Secret', 'chamberboss'); ?></label>
                        </th>
                        <td>
                            <input type="password" 
                                   id="webhook_secret" 
                                   name="webhook_secret" 
                                   value="<?php echo esc_attr($stripe_settings['webhook_secret']); ?>" 
                                   class="large-text" 
                                   placeholder="whsec_...">
                            <p class="description">
                                <?php _e('Your Stripe webhook endpoint secret (starts with whsec_).', 'chamberboss'); ?><br>
                                <strong><?php _e('Webhook URL:', 'chamberboss'); ?></strong> 
                                <code><?php echo home_url('/chamberboss/webhook/'); ?></code>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <?php if ($stripe_settings['is_configured']): ?>
                    <div class="chamberboss-notice chamberboss-notice-success">
                        <p><?php _e('Stripe is properly configured and ready to accept payments.', 'chamberboss'); ?></p>
                    </div>
                <?php else: ?>
                    <div class="chamberboss-notice chamberboss-notice-warning">
                        <p><?php _e('Stripe configuration is incomplete. Please add your API keys to enable payments.', 'chamberboss'); ?></p>
                    </div>
                <?php endif; ?>
                
                <?php submit_button(__('Save Stripe Settings', 'chamberboss')); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render MailPoet settings
     */
    private function render_mailpoet_settings() {
        $enabled = $this->get_option('chamberboss_mailpoet_enabled', '0');
        $list_id = $this->get_option('chamberboss_mailpoet_list_id', '');
        $auto_add = $this->get_option('chamberboss_mailpoet_auto_add', '1');
        
        // Check if MailPoet is active
        $mailpoet_active = class_exists('\\MailPoet\\API\\API');
        $mailpoet_lists = [];
        
        if ($mailpoet_active) {
            try {
                $mailpoet_api = \MailPoet\API\API::MP('v1');
                $mailpoet_lists = $mailpoet_api->getLists();
            } catch (Exception $e) {
                // Handle error silently
            }
        }
        
        ?>
        <div class="chamberboss-settings-section">
            <h3><?php _e('MailPoet Integration', 'chamberboss'); ?></h3>
            
            <?php if (!$mailpoet_active): ?>
                <div class="chamberboss-notice chamberboss-notice-warning">
                    <p>
                        <?php _e('MailPoet plugin is not active. Please install and activate MailPoet to use this integration.', 'chamberboss'); ?>
                        <a href="<?php echo admin_url('plugin-install.php?s=mailpoet&tab=search&type=term'); ?>" target="_blank">
                            <?php _e('Install MailPoet', 'chamberboss'); ?>
                        </a>
                    </p>
                </div>
            <?php endif; ?>
            
            <form method="post" action="">
                <?php wp_nonce_field('chamberboss_mailpoet_settings', 'chamberboss_mailpoet_nonce'); ?>
                <input type="hidden" name="action" value="save_mailpoet_settings">
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="mailpoet_enabled"><?php _e('Enable MailPoet Integration', 'chamberboss'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" 
                                   id="mailpoet_enabled" 
                                   name="mailpoet_enabled" 
                                   value="1" 
                                   <?php checked($enabled, '1'); ?>
                                   <?php disabled(!$mailpoet_active); ?>>
                            <p class="description">
                                <?php _e('Automatically manage member email subscriptions with MailPoet.', 'chamberboss'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <?php if ($mailpoet_active && !empty($mailpoet_lists)): ?>
                    <tr>
                        <th scope="row">
                            <label for="mailpoet_list_id"><?php _e('Default Email List', 'chamberboss'); ?></label>
                        </th>
                        <td>
                            <select id="mailpoet_list_id" name="mailpoet_list_id">
                                <option value=""><?php _e('Select a list...', 'chamberboss'); ?></option>
                                <?php foreach ($mailpoet_lists as $list): ?>
                                    <option value="<?php echo esc_attr($list['id']); ?>" <?php selected($list_id, $list['id']); ?>>
                                        <?php echo esc_html($list['name']); ?> (<?php echo intval($list['subscribers']); ?> subscribers)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php _e('New members will be automatically added to this email list.', 'chamberboss'); ?>
                            </p>
                        </td>
                    </tr>
                    <?php endif; ?>
                    
                    <tr>
                        <th scope="row">
                            <label for="mailpoet_auto_add"><?php _e('Auto-add New Members', 'chamberboss'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" 
                                   id="mailpoet_auto_add" 
                                   name="mailpoet_auto_add" 
                                   value="1" 
                                   <?php checked($auto_add, '1'); ?>>
                            <p class="description">
                                <?php _e('Automatically add new members to the selected email list when they join.', 'chamberboss'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(__('Save MailPoet Settings', 'chamberboss')); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render email settings
     */
    private function render_email_settings() {
        ?>
        <div class="chamberboss-settings-section">
            <h3><?php _e('Email Settings', 'chamberboss'); ?></h3>
            
            <form method="post" action="">
                <?php wp_nonce_field('chamberboss_email_settings', 'chamberboss_email_nonce'); ?>
                <input type="hidden" name="action" value="save_email_settings">
                
                <h4><?php _e('Email Sender', 'chamberboss'); ?></h4>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="chamberboss_email_from_name"><?php _e('From Name', 'chamberboss'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="chamberboss_email_from_name" 
                                   name="chamberboss_email_from_name" 
                                   value="<?php echo esc_attr(get_option('chamberboss_email_from_name', get_bloginfo('name'))); ?>" 
                                   class="regular-text" 
                                   required>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="chamberboss_email_from_address"><?php _e('From Email Address', 'chamberboss'); ?></label>
                        </th>
                        <td>
                            <input type="email" 
                                   id="chamberboss_email_from_address" 
                                   name="chamberboss_email_from_address" 
                                   value="<?php echo esc_attr(get_option('chamberboss_email_from_address', get_option('admin_email'))); ?>" 
                                   class="regular-text" 
                                   required>
                        </td>
                    </tr>
                </table>
                
                <h4><?php _e('Renewal Notification Email', 'chamberboss'); ?></h4>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="chamberboss_email_renewal_subject"><?php _e('Subject', 'chamberboss'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="chamberboss_email_renewal_subject" 
                                   name="chamberboss_email_renewal_subject" 
                                   value="<?php echo esc_attr(get_option('chamberboss_email_renewal_subject', 'Your membership is expiring soon')); ?>" 
                                   class="large-text" 
                                   required>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="chamberboss_email_renewal_message"><?php _e('Message', 'chamberboss'); ?></label>
                        </th>
                        <td>
                            <textarea id="chamberboss_email_renewal_message" 
                                      name="chamberboss_email_renewal_message" 
                                      rows="8" 
                                      class="large-text" 
                                      required><?php echo esc_textarea(get_option('chamberboss_email_renewal_message', $this->get_default_renewal_message())); ?></textarea>
                            <p class="description">
                                <?php _e('Available placeholders: {member_name}, {expiry_date}, {renewal_url}', 'chamberboss'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <h4><?php _e('Welcome Email', 'chamberboss'); ?></h4>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="chamberboss_email_welcome_subject"><?php _e('Subject', 'chamberboss'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="chamberboss_email_welcome_subject" 
                                   name="chamberboss_email_welcome_subject" 
                                   value="<?php echo esc_attr(get_option('chamberboss_email_welcome_subject', 'Welcome to our Chamber of Commerce!')); ?>" 
                                   class="large-text" 
                                   required>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="chamberboss_email_welcome_message"><?php _e('Message', 'chamberboss'); ?></label>
                        </th>
                        <td>
                            <textarea id="chamberboss_email_welcome_message" 
                                      name="chamberboss_email_welcome_message" 
                                      rows="8" 
                                      class="large-text" 
                                      required><?php echo esc_textarea(get_option('chamberboss_email_welcome_message', $this->get_default_welcome_message())); ?></textarea>
                            <p class="description">
                                <?php _e('Available placeholders: {member_name}, {login_url}, {directory_url}', 'chamberboss'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(__('Save Email Settings', 'chamberboss')); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render categories settings
     */
    private function render_categories_settings() {
        $categories = $this->get_option('chamberboss_business_categories', []);
        
        ?>
       
        <?php
    }
    
    /**
     * Handle settings save
     */
    public function handle_settings_save() {
        if (!isset($_POST['action'])) {
            return;
        }
        
        $action = $_POST['action'];
        
        switch ($action) {
            case 'save_general_settings':
                $this->save_general_settings();
                break;
            case 'save_stripe_settings':
                $this->save_stripe_settings();
                break;
            case 'save_mailpoet_settings':
                $this->save_mailpoet_settings();
                break;
            case 'save_email_settings':
                $this->save_email_settings();
                break;
            case 'save_categories_settings':
                $this->save_categories_settings();
                break;
        }
    }
    
    /**
     * Save general settings
     */
    private function save_general_settings() {
        if (!$this->verify_nonce($_POST['chamberboss_general_nonce'] ?? '', 'chamberboss_general_settings')) {
            return;
        }
        
        if (!$this->user_can('manage_options')) {
            return;
        }
        
        $membership_price = floatval($_POST['membership_price'] ?? 0);
        $currency = sanitize_text_field($_POST['currency'] ?? 'USD');
        $renewal_days = intval($_POST['renewal_days'] ?? 30);
        
        $this->update_option('chamberboss_membership_price', $membership_price);
        $this->update_option('chamberboss_currency', $currency);
        $this->update_option('chamberboss_renewal_days', $renewal_days);
        
        wp_redirect(admin_url('admin.php?page=chamberboss-settings&tab=general&message=saved'));
        exit;
    }
    
    /**
     * Save Stripe settings
     */
    private function save_stripe_settings() {
        if (!$this->verify_nonce($_POST['chamberboss_stripe_nonce'] ?? '', 'chamberboss_stripe_settings')) {
            return;
        }
        
        if (!$this->user_can('manage_options')) {
            return;
        }
        
        $settings = [
            'mode' => sanitize_text_field($_POST['stripe_mode'] ?? 'test'),
            'test_publishable_key' => sanitize_text_field($_POST['test_publishable_key'] ?? ''),
            'test_secret_key' => sanitize_text_field($_POST['test_secret_key'] ?? ''),
            'live_publishable_key' => sanitize_text_field($_POST['live_publishable_key'] ?? ''),
            'live_secret_key' => sanitize_text_field($_POST['live_secret_key'] ?? ''),
            'webhook_secret' => sanitize_text_field($_POST['webhook_secret'] ?? ''),
        ];
        
        $this->stripe_config->update_settings($settings);
        
        wp_redirect(admin_url('admin.php?page=chamberboss-settings&tab=stripe&message=saved'));
        exit;
    }
    
    /**
     * Save MailPoet settings
     */
    private function save_mailpoet_settings() {
        if (!$this->verify_nonce($_POST['chamberboss_mailpoet_nonce'] ?? '', 'chamberboss_mailpoet_settings')) {
            return;
        }
        
        if (!$this->user_can('manage_options')) {
            return;
        }
        
        $enabled = isset($_POST['mailpoet_enabled']) ? '1' : '0';
        $list_id = sanitize_text_field($_POST['mailpoet_list_id'] ?? '');
        $auto_add = isset($_POST['mailpoet_auto_add']) ? '1' : '0';
        
        $this->update_option('chamberboss_mailpoet_enabled', $enabled);
        $this->update_option('chamberboss_mailpoet_list_id', $list_id);
        $this->update_option('chamberboss_mailpoet_auto_add', $auto_add);
        
        wp_redirect(admin_url('admin.php?page=chamberboss-settings&tab=mailpoet&message=saved'));
        exit;
    }
    
    /**
     * Save email settings
     */
    private function save_email_settings() {
        if (!$this->verify_nonce($_POST['chamberboss_email_nonce'] ?? '', 'chamberboss_email_settings')) {
            return;
        }
        
        if (!$this->user_can('manage_options')) {
            return;
        }
        
        $from_name = sanitize_text_field($_POST['chamberboss_email_from_name'] ?? '');
        $from_address = sanitize_email($_POST['chamberboss_email_from_address'] ?? '');
        $renewal_subject = sanitize_text_field($_POST['chamberboss_email_renewal_subject'] ?? '');
        $renewal_message = sanitize_textarea_field($_POST['chamberboss_email_renewal_message'] ?? '');
        $welcome_subject = sanitize_text_field($_POST['chamberboss_email_welcome_subject'] ?? '');
        $welcome_message = sanitize_textarea_field($_POST['chamberboss_email_welcome_message'] ?? '');
        
        $this->update_option('chamberboss_email_from_name', $from_name);
        $this->update_option('chamberboss_email_from_address', $from_address);
        $this->update_option('chamberboss_email_renewal_subject', $renewal_subject);
        $this->update_option('chamberboss_email_renewal_message', $renewal_message);
        $this->update_option('chamberboss_email_welcome_subject', $welcome_subject);
        $this->update_option('chamberboss_email_welcome_message', $welcome_message);
        
        wp_redirect(admin_url('admin.php?page=chamberboss-settings&tab=email&message=saved'));
        exit;
    }
    
    /**
     * Save categories settings
     */
    private function save_categories_settings() {
        if (!$this->verify_nonce($_POST['chamberboss_categories_nonce'] ?? '', 'chamberboss_categories_settings')) {
            return;
        }
        
        if (!$this->user_can('manage_options')) {
            return;
        }
        
        $categories = array_map('trim', explode("\n", $_POST['business_categories'] ?? ''));
        $categories = array_filter($categories, 'strlen'); // Remove empty lines
        
        $this->update_option('chamberboss_business_categories', $categories);
        
        wp_redirect(admin_url('admin.php?page=chamberboss-settings&tab=categories&message=saved'));
        exit;
    }
    
    /**
     * Get default renewal message
     */
    private function get_default_renewal_message() {
        return "Dear {member_name},\n\nYour chamber membership is set to expire on {expiry_date}. To continue enjoying all the benefits of membership, please renew your subscription.\n\nRenew now: {renewal_url}\n\nThank you for being a valued member!\n\nBest regards,\nThe Chamber Team";
    }
    
    /**
     * Get default welcome message
     */
    private function get_default_welcome_message() {
        return "Dear {member_name},\n\nWelcome to our Chamber of Commerce! We're excited to have you as a new member.\n\nYou can now:\n- Submit business listings to our directory\n- Access member-only resources\n- Connect with other local businesses\n\nExplore our business directory: {directory_url}\nAccess your account: {login_url}\n\nIf you have any questions, please don't hesitate to contact us.\n\nWelcome aboard!\n\nBest regards,\nThe Chamber Team";
    }
}

