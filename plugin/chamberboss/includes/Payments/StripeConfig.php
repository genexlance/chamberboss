<?php
namespace Chamberboss\Payments;

use Chamberboss\Core\BaseClass;

/**
 * Stripe Configuration Handler
 */
class StripeConfig extends BaseClass {
    
    /**
     * Option names for Stripe settings
     */
    const OPTION_STRIPE_MODE = 'chamberboss_stripe_mode';
    const OPTION_STRIPE_TEST_PUBLISHABLE_KEY = 'chamberboss_stripe_test_publishable_key';
    const OPTION_STRIPE_TEST_SECRET_KEY = 'chamberboss_stripe_test_secret_key';
    const OPTION_STRIPE_LIVE_PUBLISHABLE_KEY = 'chamberboss_stripe_live_publishable_key';
    const OPTION_STRIPE_LIVE_SECRET_KEY = 'chamberboss_stripe_live_secret_key';
    const OPTION_STRIPE_WEBHOOK_SECRET = 'chamberboss_stripe_webhook_secret';
    
    /**
     * Initialize configuration
     */
    protected function init() {
        // Configuration is handled through admin interface
    }
    
    /**
     * Get Stripe mode (test or live)
     * @return string
     */
    public function get_mode() {
        return $this->get_option(self::OPTION_STRIPE_MODE, 'test');
    }
    
    /**
     * Set Stripe mode
     * @param string $mode
     * @return bool
     */
    public function set_mode($mode) {
        if (!in_array($mode, ['test', 'live'])) {
            return false;
        }
        
        return $this->update_option(self::OPTION_STRIPE_MODE, $mode);
    }
    
    /**
     * Get publishable key for current mode
     * @return string
     */
    public function get_publishable_key() {
        $mode = $this->get_mode();
        $option = $mode === 'live' ? self::OPTION_STRIPE_LIVE_PUBLISHABLE_KEY : self::OPTION_STRIPE_TEST_PUBLISHABLE_KEY;
        
        return $this->get_option($option, '');
    }
    
    /**
     * Get secret key for current mode
     * @return string
     */
    public function get_secret_key() {
        $mode = $this->get_mode();
        $option = $mode === 'live' ? self::OPTION_STRIPE_LIVE_SECRET_KEY : self::OPTION_STRIPE_TEST_SECRET_KEY;
        
        return $this->get_option($option, '');
    }
    
    /**
     * Set publishable key for specific mode
     * @param string $key
     * @param string $mode
     * @return bool
     */
    public function set_publishable_key($key, $mode = null) {
        if ($mode === null) {
            $mode = $this->get_mode();
        }
        
        $option = $mode === 'live' ? self::OPTION_STRIPE_LIVE_PUBLISHABLE_KEY : self::OPTION_STRIPE_TEST_PUBLISHABLE_KEY;
        
        return $this->update_option($option, sanitize_text_field($key));
    }
    
    /**
     * Set secret key for specific mode
     * @param string $key
     * @param string $mode
     * @return bool
     */
    public function set_secret_key($key, $mode = null) {
        if ($mode === null) {
            $mode = $this->get_mode();
        }
        
        $option = $mode === 'live' ? self::OPTION_STRIPE_LIVE_SECRET_KEY : self::OPTION_STRIPE_TEST_SECRET_KEY;
        
        error_log('[Chamberboss Debug] set_secret_key: mode=' . $mode . ', option=' . $option . ', key length=' . strlen($key));
        
        $result = $this->update_option($option, sanitize_text_field($key));
        
        error_log('[Chamberboss Debug] update_option result for ' . $option . ': ' . ($result ? 'success' : 'failed'));
        
        // Verify it was saved
        $saved_value = $this->get_option($option, '');
        error_log('[Chamberboss Debug] Verification - saved value length: ' . strlen($saved_value));
        
        return $result;
    }
    
    /**
     * Get webhook secret
     * @return string
     */
    public function get_webhook_secret() {
        return $this->get_option(self::OPTION_STRIPE_WEBHOOK_SECRET, '');
    }
    
    /**
     * Set webhook secret
     * @param string $secret
     * @return bool
     */
    public function set_webhook_secret($secret) {
        return $this->update_option(self::OPTION_STRIPE_WEBHOOK_SECRET, sanitize_text_field($secret));
    }
    
    /**
     * Check if Stripe is properly configured
     * @return bool
     */
    public function is_configured() {
        $publishable_key = $this->get_publishable_key();
        $secret_key = $this->get_secret_key();
        
        return !empty($publishable_key) && !empty($secret_key);
    }
    
    /**
     * Get all Stripe settings for admin display
     * @return array
     */
    public function get_all_settings() {
        return [
            'mode' => $this->get_mode(),
            'test_publishable_key' => $this->get_option(self::OPTION_STRIPE_TEST_PUBLISHABLE_KEY, ''),
            'test_secret_key' => $this->get_option(self::OPTION_STRIPE_TEST_SECRET_KEY, ''),
            'live_publishable_key' => $this->get_option(self::OPTION_STRIPE_LIVE_PUBLISHABLE_KEY, ''),
            'live_secret_key' => $this->get_option(self::OPTION_STRIPE_LIVE_SECRET_KEY, ''),
            'webhook_secret' => $this->get_webhook_secret(),
            'is_configured' => $this->is_configured(),
        ];
    }
    
    /**
     * Update multiple settings at once
     * @param array $settings
     * @return bool
     */
    public function update_settings($settings) {
        $success = true;
        
        // Log what we're trying to save
        error_log('[Chamberboss Debug] StripeConfig::update_settings called with: ' . print_r($settings, true));
        
        if (isset($settings['mode'])) {
            $result = $this->set_mode($settings['mode']);
            error_log('[Chamberboss Debug] set_mode result: ' . ($result ? 'success' : 'failed'));
            $success = $success && $result;
        }
        
        if (isset($settings['test_publishable_key'])) {
            $result = $this->set_publishable_key($settings['test_publishable_key'], 'test');
            error_log('[Chamberboss Debug] set_publishable_key (test) result: ' . ($result ? 'success' : 'failed'));
            $success = $success && $result;
        }
        
        if (isset($settings['test_secret_key'])) {
            $result = $this->set_secret_key($settings['test_secret_key'], 'test');
            error_log('[Chamberboss Debug] set_secret_key (test) result: ' . ($result ? 'success' : 'failed'));
            $success = $success && $result;
        }
        
        if (isset($settings['live_publishable_key'])) {
            $result = $this->set_publishable_key($settings['live_publishable_key'], 'live');
            error_log('[Chamberboss Debug] set_publishable_key (live) result: ' . ($result ? 'success' : 'failed'));
            $success = $success && $result;
        }
        
        if (isset($settings['live_secret_key'])) {
            $result = $this->set_secret_key($settings['live_secret_key'], 'live');
            error_log('[Chamberboss Debug] set_secret_key (live) result: ' . ($result ? 'success' : 'failed'));
            $success = $success && $result;
        }
        
        if (isset($settings['webhook_secret'])) {
            $result = $this->set_webhook_secret($settings['webhook_secret']);
            error_log('[Chamberboss Debug] set_webhook_secret result: ' . ($result ? 'success' : 'failed'));
            $success = $success && $result;
        }
        
        error_log('[Chamberboss Debug] StripeConfig::update_settings final result: ' . ($success ? 'success' : 'failed'));
        
        return $success;
    }
    
    /**
     * Validate API keys format
     * @param string $key
     * @param string $type (publishable or secret)
     * @return bool
     */
    public function validate_key_format($key, $type) {
        if (empty($key)) {
            return false;
        }
        
        if ($type === 'publishable') {
            return strpos($key, 'pk_') === 0;
        } elseif ($type === 'secret') {
            return strpos($key, 'sk_') === 0;
        }
        
        return false;
    }
    
    /**
     * Get masked key for display (shows only last 4 characters)
     * @param string $key
     * @return string
     */
    public function get_masked_key($key) {
        if (empty($key) || strlen($key) < 8) {
            return '';
        }
        
        return str_repeat('*', strlen($key) - 4) . substr($key, -4);
    }
}

