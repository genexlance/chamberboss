<?php
namespace Chamberboss\Core;

/**
 * Base class for all Chamberboss classes
 */
abstract class BaseClass {
    
    /**
     * Plugin version
     * @var string
     */
    protected $version;
    
    /**
     * Plugin text domain
     * @var string
     */
    protected $text_domain = 'chamberboss';
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->version = CHAMBERBOSS_VERSION;
        $this->init();
    }
    
    /**
     * Initialize the class
     */
    abstract protected function init();
    
    /**
     * Get option with default value
     * @param string $option_name
     * @param mixed $default
     * @return mixed
     */
    protected function get_option($option_name, $default = '') {
        return get_option($option_name, $default);
    }
    
    /**
     * Update option
     * @param string $option_name
     * @param mixed $value
     * @return bool
     */
    protected function update_option($option_name, $value) {
        $result = update_option($option_name, $value);
        
        // WordPress update_option returns false if the value didn't change or if saving empty value to non-existent option
        // Consider it successful if the current value matches what we wanted to save
        if (!$result) {
            $current_value = get_option($option_name, '__NOT_SET__');
            $result = ($current_value === $value);
        }
        
        return $result;
    }
    
    /**
     * Log message for debugging
     * @param string $message
     * @param string $level
     */
    protected function log($message, $level = 'info') {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[Chamberboss {$level}] {$message}");
        }
    }
    
    /**
     * Check if user has required capability
     * @param string $capability
     * @return bool
     */
    protected function user_can($capability) {
        return current_user_can($capability);
    }
    
    /**
     * Sanitize input data
     * @param mixed $data
     * @return mixed
     */
    protected function sanitize_input($data) {
        if (is_array($data)) {
            return array_map([$this, 'sanitize_input'], $data);
        }
        
        if (is_string($data)) {
            return sanitize_text_field($data);
        }
        
        return $data;
    }
    
    /**
     * Verify nonce
     * @param string $nonce
     * @param string $action
     * @return bool
     */
    protected function verify_nonce($nonce, $action) {
        return wp_verify_nonce($nonce, $action);
    }
    
    /**
     * Create nonce
     * @param string $action
     * @return string
     */
    protected function create_nonce($action) {
        return wp_create_nonce($action);
    }
    
    /**
     * Get current user ID
     * @return int
     */
    protected function get_current_user_id() {
        return get_current_user_id();
    }
    
    /**
     * Check if current request is AJAX
     * @return bool
     */
    protected function is_ajax() {
        return wp_doing_ajax();
    }
    
    /**
     * Send JSON response
     * @param array $data
     * @param bool $success
     */
    protected function send_json_response($data, $success = true) {
        if ($success) {
            wp_send_json_success($data);
        } else {
            wp_send_json_error($data);
        }
    }

    /**
     * Get status badge HTML
     */
    protected function get_status_badge($status) {
        $badges = [
            'active' => '<span class="chamberboss-badge chamberboss-badge-success">Active</span>',
            'inactive' => '<span class="chamberboss-badge chamberboss-badge-secondary">Inactive</span>',
            'expired' => '<span class="chamberboss-badge chamberboss-badge-warning">Expired</span>',
            'cancelled' => '<span class="chamberboss-badge chamberboss-badge-danger">Cancelled</span>',
            'completed' => '<span class="chamberboss-badge chamberboss-badge-success">Completed</span>',
            'pending' => '<span class="chamberboss-badge chamberboss-badge-warning">Pending</span>',
            'failed' => '<span class="chamberboss-badge chamberboss-badge-danger">Failed</span>',
            'publish' => '<span class="chamberboss-badge chamberboss-badge-success">Published</span>',
            'draft' => '<span class="chamberboss-badge chamberboss-badge-secondary">Draft</span>',
            'trash' => '<span class="chamberboss-badge chamberboss-badge-danger">Trash</span>'
        ];
        
        return $badges[$status] ?? '<span class="chamberboss-badge chamberboss-badge-secondary">' . ucfirst($status) . '</span>';
    }
}

