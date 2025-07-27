<?php
/**
 * Stripe Configuration Debug Script
 */

// WordPress bootstrap
$wp_path = '/Users/lancesmithcc/Local Sites/lancedev/app/public';
require_once($wp_path . '/wp-config.php');

echo "<h1>🔧 Stripe Configuration Debug</h1>";

try {
    $stripe_config = new \Chamberboss\Payments\StripeConfig();
    
    echo "<h2>Raw Configuration Values:</h2>";
    echo "<pre>";
    echo "Mode: " . $stripe_config->get_mode() . "\n";
    echo "Publishable Key: " . ($stripe_config->get_publishable_key() ?: 'NOT SET') . "\n";
    echo "Secret Key: " . ($stripe_config->get_secret_key() ? 'SET (length: ' . strlen($stripe_config->get_secret_key()) . ')' : 'NOT SET') . "\n";
    echo "Is Configured: " . ($stripe_config->is_configured() ? 'YES' : 'NO') . "\n";
    echo "</pre>";
    
    echo "<h2>WordPress Options:</h2>";
    echo "<pre>";
    echo "chamberboss_stripe_mode: " . get_option('chamberboss_stripe_mode', 'NOT SET') . "\n";
    echo "chamberboss_stripe_test_publishable_key: " . get_option('chamberboss_stripe_test_publishable_key', 'NOT SET') . "\n";
    echo "chamberboss_stripe_test_secret_key: " . (get_option('chamberboss_stripe_test_secret_key') ? 'SET' : 'NOT SET') . "\n";
    echo "chamberboss_stripe_live_publishable_key: " . get_option('chamberboss_stripe_live_publishable_key', 'NOT SET') . "\n";
    echo "chamberboss_stripe_live_secret_key: " . (get_option('chamberboss_stripe_live_secret_key') ? 'SET' : 'NOT SET') . "\n";
    echo "</pre>";
    
    echo "<h2>Frontend Form Test:</h2>";
    wp_set_current_user(0); // Ensure not logged in
    $shortcode_output = do_shortcode('[chamberboss_member_registration]');
    
    if (strpos($shortcode_output, 'payment-element') !== false) {
        echo "<span style='color:green;'>✅ Payment fields present in form</span><br>";
    } else if (strpos($shortcode_output, 'Registration is currently free') !== false) {
        echo "<span style='color:orange;'>⚠️  Free registration mode (Stripe not configured)</span><br>";
    } else {
        echo "<span style='color:red;'>❌ No payment section found</span><br>";
    }
    
    echo "<h2>Form Output Sample:</h2>";
    echo "<textarea style='width:100%;height:200px;'>" . htmlspecialchars($shortcode_output) . "</textarea>";
    
} catch (Exception $e) {
    echo "<span style='color:red;'>❌ ERROR: " . $e->getMessage() . "</span>";
}
?> 