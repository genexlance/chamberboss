<?php
namespace Chamberboss\Email;

use Chamberboss\Core\BaseClass;
use Chamberboss\Core\Database;

/**
 * MailPoet Integration Handler
 */
class MailPoetIntegration extends BaseClass {
    
    /**
     * Database instance
     * @var Database
     */
    private $database;
    
    /**
     * MailPoet API instance
     * @var object|null
     */
    private $mailpoet_api;
    
    /**
     * Initialize MailPoet integration
     */
    protected function init() {
        $this->database = new Database();
        
        // Check if MailPoet is available
        if ($this->is_mailpoet_available()) {
            $this->mailpoet_api = \MailPoet\API\API::MP('v1');
            
            // Hook into membership events
            add_action('chamberboss_membership_activated', [$this, 'on_membership_activated']);
            add_action('chamberboss_membership_cancelled', [$this, 'on_membership_cancelled']);
            add_action('chamberboss_membership_expired', [$this, 'on_membership_expired']);
            
            // Hook into post save for manual member creation
            add_action('save_post', [$this, 'on_member_save'], 10, 2);
        }
    }
    
    /**
     * Check if MailPoet is available
     * @return bool
     */
    public function is_mailpoet_available() {
        return class_exists('\\MailPoet\\API\\API') && $this->is_integration_enabled();
    }
    
    /**
     * Check if integration is enabled in settings
     * @return bool
     */
    public function is_integration_enabled() {
        return $this->get_option('chamberboss_mailpoet_enabled', '0') === '1';
    }
    
    /**
     * Get configured list ID
     * @return string
     */
    public function get_list_id() {
        return $this->get_option('chamberboss_mailpoet_list_id', '');
    }
    
    /**
     * Check if auto-add is enabled
     * @return bool
     */
    public function is_auto_add_enabled() {
        return $this->get_option('chamberboss_mailpoet_auto_add', '1') === '1';
    }
    
    /**
     * Add member to MailPoet list
     * @param int $member_id
     * @param string $list_id Optional list ID, uses default if not provided
     * @return bool|array
     */
    public function add_member_to_list($member_id, $list_id = null) {
        if (!$this->is_mailpoet_available()) {
            return false;
        }
        
        if (!$list_id) {
            $list_id = $this->get_list_id();
        }
        
        if (!$list_id) {
            $this->log('No MailPoet list ID configured', 'warning');
            return false;
        }
        
        // Get member data
        $member = get_post($member_id);
        if (!$member || $member->post_type !== 'chamberboss_member') {
            return false;
        }
        
        $email = get_post_meta($member_id, '_chamberboss_member_email', true);
        if (!$email) {
            $this->log("No email found for member {$member_id}", 'warning');
            return false;
        }
        
        try {
            // Check if subscriber already exists
            $existing_subscriber = $this->get_subscriber_by_email($email);
            
            if ($existing_subscriber) {
                // Update existing subscriber
                $subscriber_data = [
                    'id' => $existing_subscriber['id'],
                    'email' => $email,
                    'first_name' => $this->extract_first_name($member->post_title),
                    'last_name' => $this->extract_last_name($member->post_title),
                ];
                
                $subscriber = $this->mailpoet_api->updateSubscriber($subscriber_data);
                $subscriber_id = $subscriber['id'];
            } else {
                // Create new subscriber
                $subscriber_data = [
                    'email' => $email,
                    'first_name' => $this->extract_first_name($member->post_title),
                    'last_name' => $this->extract_last_name($member->post_title),
                    'status' => 'subscribed'
                ];
                
                $subscriber = $this->mailpoet_api->addSubscriber($subscriber_data);
                $subscriber_id = $subscriber['id'];
            }
            
            // Subscribe to list
            $this->mailpoet_api->subscribeToList($subscriber_id, $list_id);
            
            // Log the action
            $this->database->log_mailpoet_action([
                'member_id' => $member_id,
                'mailpoet_subscriber_id' => $subscriber_id,
                'list_id' => $list_id,
                'action' => 'subscribed',
                'status' => 'success'
            ]);
            
            $this->log("Added member {$member_id} to MailPoet list {$list_id}");
            
            return [
                'success' => true,
                'subscriber_id' => $subscriber_id,
                'message' => 'Member added to email list successfully'
            ];
            
        } catch (\Exception $e) {
            $error_message = $e->getMessage();
            
            // Log the error
            $this->database->log_mailpoet_action([
                'member_id' => $member_id,
                'list_id' => $list_id,
                'action' => 'subscribe_failed',
                'status' => 'error',
                'error_message' => $error_message
            ]);
            
            $this->log("Failed to add member {$member_id} to MailPoet: {$error_message}", 'error');
            
            return [
                'success' => false,
                'error' => $error_message
            ];
        }
    }
    
    /**
     * Remove member from MailPoet list
     * @param int $member_id
     * @param string $list_id Optional list ID, uses default if not provided
     * @return bool|array
     */
    public function remove_member_from_list($member_id, $list_id = null) {
        if (!$this->is_mailpoet_available()) {
            return false;
        }
        
        if (!$list_id) {
            $list_id = $this->get_list_id();
        }
        
        if (!$list_id) {
            return false;
        }
        
        $email = get_post_meta($member_id, '_chamberboss_member_email', true);
        if (!$email) {
            return false;
        }
        
        try {
            $subscriber = $this->get_subscriber_by_email($email);
            
            if ($subscriber) {
                $this->mailpoet_api->unsubscribeFromList($subscriber['id'], $list_id);
                
                // Log the action
                $this->database->log_mailpoet_action([
                    'member_id' => $member_id,
                    'mailpoet_subscriber_id' => $subscriber['id'],
                    'list_id' => $list_id,
                    'action' => 'unsubscribed',
                    'status' => 'success'
                ]);
                
                $this->log("Removed member {$member_id} from MailPoet list {$list_id}");
                
                return [
                    'success' => true,
                    'message' => 'Member removed from email list successfully'
                ];
            }
            
            return [
                'success' => false,
                'error' => 'Subscriber not found'
            ];
            
        } catch (\Exception $e) {
            $error_message = $e->getMessage();
            
            // Log the error
            $this->database->log_mailpoet_action([
                'member_id' => $member_id,
                'list_id' => $list_id,
                'action' => 'unsubscribe_failed',
                'status' => 'error',
                'error_message' => $error_message
            ]);
            
            $this->log("Failed to remove member {$member_id} from MailPoet: {$error_message}", 'error');
            
            return [
                'success' => false,
                'error' => $error_message
            ];
        }
    }
    
    /**
     * Get subscriber by email
     * @param string $email
     * @return array|null
     */
    public function get_subscriber_by_email($email) {
        if (!$this->is_mailpoet_available()) {
            return null;
        }
        
        try {
            return $this->mailpoet_api->getSubscriber($email);
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Get all MailPoet lists
     * @return array
     */
    public function get_lists() {
        if (!$this->is_mailpoet_available()) {
            return [];
        }
        
        try {
            return $this->mailpoet_api->getLists();
        } catch (\Exception $e) {
            $this->log('Failed to get MailPoet lists: ' . $e->getMessage(), 'error');
            return [];
        }
    }
    
    /**
     * Handle membership activation
     * @param int $member_id
     */
    public function on_membership_activated($member_id) {
        if (!$this->is_auto_add_enabled()) {
            return;
        }
        
        $this->add_member_to_list($member_id);
    }
    
    /**
     * Handle membership cancellation
     * @param int $member_id
     */
    public function on_membership_cancelled($member_id) {
        // Optionally remove from list when membership is cancelled
        $remove_on_cancel = apply_filters('chamberboss_mailpoet_remove_on_cancel', false);
        
        if ($remove_on_cancel) {
            $this->remove_member_from_list($member_id);
        }
    }
    
    /**
     * Handle membership expiration
     * @param int $member_id
     */
    public function on_membership_expired($member_id) {
        // Optionally remove from list when membership expires
        $remove_on_expire = apply_filters('chamberboss_mailpoet_remove_on_expire', false);
        
        if ($remove_on_expire) {
            $this->remove_member_from_list($member_id);
        }
    }
    
    /**
     * Handle member post save
     * @param int $post_id
     * @param object $post
     */
    public function on_member_save($post_id, $post) {
        if ($post->post_type !== 'chamberboss_member') {
            return;
        }
        
        if (!$this->is_auto_add_enabled()) {
            return;
        }
        
        // Only add to list if member is active
        $status = get_post_meta($post_id, '_chamberboss_subscription_status', true);
        if ($status === 'active') {
            $this->add_member_to_list($post_id);
        }
    }
    
    /**
     * Sync all active members to MailPoet
     * @param string $list_id Optional list ID
     * @return array
     */
    public function sync_all_members($list_id = null) {
        if (!$this->is_mailpoet_available()) {
            return [
                'success' => false,
                'error' => 'MailPoet not available'
            ];
        }
        
        if (!$list_id) {
            $list_id = $this->get_list_id();
        }
        
        if (!$list_id) {
            return [
                'success' => false,
                'error' => 'No list ID configured'
            ];
        }
        
        // Get all active members
        $members = get_posts([
            'post_type' => 'chamberboss_member',
            'post_status' => 'publish',
            'numberposts' => -1,
            'meta_query' => [
                [
                    'key' => '_chamberboss_subscription_status',
                    'value' => 'active',
                    'compare' => '='
                ]
            ]
        ]);
        
        $results = [
            'success' => true,
            'total' => count($members),
            'added' => 0,
            'errors' => 0,
            'messages' => []
        ];
        
        foreach ($members as $member) {
            $result = $this->add_member_to_list($member->ID, $list_id);
            
            if ($result && $result['success']) {
                $results['added']++;
            } else {
                $results['errors']++;
                $results['messages'][] = "Failed to add {$member->post_title}: " . ($result['error'] ?? 'Unknown error');
            }
        }
        
        $this->log("MailPoet sync completed: {$results['added']} added, {$results['errors']} errors");
        
        return $results;
    }
    
    /**
     * Extract first name from full name
     * @param string $full_name
     * @return string
     */
    private function extract_first_name($full_name) {
        $parts = explode(' ', trim($full_name));
        return $parts[0] ?? '';
    }
    
    /**
     * Extract last name from full name
     * @param string $full_name
     * @return string
     */
    private function extract_last_name($full_name) {
        $parts = explode(' ', trim($full_name));
        if (count($parts) > 1) {
            array_shift($parts); // Remove first name
            return implode(' ', $parts);
        }
        return '';
    }
    
    /**
     * Get member's MailPoet subscription status
     * @param int $member_id
     * @return array|null
     */
    public function get_member_subscription_status($member_id) {
        if (!$this->is_mailpoet_available()) {
            return null;
        }
        
        $email = get_post_meta($member_id, '_chamberboss_member_email', true);
        if (!$email) {
            return null;
        }
        
        try {
            $subscriber = $this->get_subscriber_by_email($email);
            if (!$subscriber) {
                return null;
            }
            
            $list_id = $this->get_list_id();
            if (!$list_id) {
                return null;
            }
            
            $subscriptions = $this->mailpoet_api->getSubscriberLists($subscriber['id']);
            
            foreach ($subscriptions as $subscription) {
                if ($subscription['id'] == $list_id) {
                    return [
                        'subscriber_id' => $subscriber['id'],
                        'list_id' => $list_id,
                        'status' => $subscription['status'],
                        'subscribed_at' => $subscription['subscribed_at'] ?? null
                    ];
                }
            }
            
            return null;
            
        } catch (\Exception $e) {
            $this->log("Failed to get subscription status for member {$member_id}: " . $e->getMessage(), 'error');
            return null;
        }
    }
    
    /**
     * Bulk update member email list subscriptions
     * @param array $member_ids
     * @param string $action 'subscribe' or 'unsubscribe'
     * @param string $list_id Optional list ID
     * @return array
     */
    public function bulk_update_subscriptions($member_ids, $action, $list_id = null) {
        if (!$this->is_mailpoet_available()) {
            return [
                'success' => false,
                'error' => 'MailPoet not available'
            ];
        }
        
        if (!in_array($action, ['subscribe', 'unsubscribe'])) {
            return [
                'success' => false,
                'error' => 'Invalid action'
            ];
        }
        
        $results = [
            'success' => true,
            'total' => count($member_ids),
            'processed' => 0,
            'errors' => 0,
            'messages' => []
        ];
        
        foreach ($member_ids as $member_id) {
            if ($action === 'subscribe') {
                $result = $this->add_member_to_list($member_id, $list_id);
            } else {
                $result = $this->remove_member_from_list($member_id, $list_id);
            }
            
            if ($result && $result['success']) {
                $results['processed']++;
            } else {
                $results['errors']++;
                $member_title = get_the_title($member_id);
                $results['messages'][] = "Failed to {$action} {$member_title}: " . ($result['error'] ?? 'Unknown error');
            }
        }
        
        return $results;
    }
}

