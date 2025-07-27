<?php
/**
 * Complete Registration Flow Test
 * 
 * This script tests:
 * 1. Stripe configuration detection
 * 2. Registration form rendering
 * 3. Payment field visibility
 * 4. AJAX endpoints
 */

// WordPress bootstrap
$wp_path = '/Users/lancesmithcc/Local Sites/lancedev/app/public';
require_once($wp_path . '/wp-config.php');

// Force debug mode
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

echo "<!DOCTYPE html>";
echo "<html><head>";
echo "<title>ChumberBoss Registration Test</title>";
echo "<style>";
echo "body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }";
echo ".test-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }";
echo ".pass { color: green; } .fail { color: red; } .warning { color: orange; }";
echo ".debug-box { background: #f0f0f0; padding: 10px; margin: 10px 0; font-family: monospace; }";
echo "</style>";
echo "</head><body>";

echo "<h1>🔧 ChumberBoss Registration Test Suite</h1>";

// Test 1: Stripe Configuration
echo "<div class='test-section'>";
echo "<h2>Test 1: Stripe Configuration</h2>";

try {
    $stripe_config = new \Chamberboss\Payments\StripeConfig();
    $is_configured = $stripe_config->is_configured();
    $publishable_key = $stripe_config->get_publishable_key();
    $secret_key = $stripe_config->get_secret_key();
    $mode = $stripe_config->get_mode();
    
    echo "<div class='debug-box'>";
    echo "Mode: " . $mode . "<br>";
    echo "Is Configured: " . ($is_configured ? 'YES' : 'NO') . "<br>";
    echo "Publishable Key: " . ($publishable_key ? substr($publishable_key, 0, 15) . '...' : 'NOT SET') . "<br>";
    echo "Secret Key: " . ($secret_key ? substr($secret_key, 0, 15) . '...' : 'NOT SET') . "<br>";
    echo "</div>";
    
    if ($is_configured) {
        echo "<span class='pass'>✅ PASS: Stripe is configured</span>";
    } else {
        echo "<span class='fail'>❌ FAIL: Stripe not configured</span>";
        echo "<div style='margin-top: 10px; padding: 10px; background: #fff3cd; border: 1px solid #ffeaa7;'>";
        echo "<strong>Solution:</strong> Add your Stripe test keys in WP Admin → ChumberBoss → Settings → Stripe:<br>";
        echo "Get test keys from: <a href='https://docs.stripe.com/keys' target='_blank'>Stripe Documentation</a><br>";
        echo "Or create a free account at: <a href='https://dashboard.stripe.com' target='_blank'>Stripe Dashboard</a>";
        echo "</div>";
    }
} catch (Exception $e) {
    echo "<span class='fail'>❌ ERROR: " . $e->getMessage() . "</span>";
}

echo "</div>";

// Test 2: Registration Form Rendering
echo "<div class='test-section'>";
echo "<h2>Test 2: Registration Form</h2>";

try {
    // Force user logout for testing
    wp_set_current_user(0);
    
    // Test shortcode
    $shortcode_output = do_shortcode('[chamberboss_member_registration]');
    
    if (strpos($shortcode_output, 'chamberboss-member-registration') !== false) {
        echo "<span class='pass'>✅ PASS: Registration form renders</span><br>";
    } else {
        echo "<span class='fail'>❌ FAIL: Registration form not rendering</span><br>";
    }
    
    // Check for payment fields
    if (strpos($shortcode_output, 'payment-element') !== false) {
        echo "<span class='pass'>✅ PASS: Payment fields present</span><br>";
    } else if (strpos($shortcode_output, 'Registration is currently free') !== false) {
        echo "<span class='warning'>⚠️ WARNING: Free registration mode (Stripe not configured)</span><br>";
    } else {
        echo "<span class='fail'>❌ FAIL: No payment section found</span><br>";
    }
    
    // Check for debug info
    if (strpos($shortcode_output, 'DEBUG INFO') !== false) {
        echo "<span class='pass'>✅ PASS: Debug information present</span><br>";
    } else {
        echo "<span class='warning'>⚠️ WARNING: Debug info not showing</span><br>";
    }
    
} catch (Exception $e) {
    echo "<span class='fail'>❌ ERROR: " . $e->getMessage() . "</span>";
}

echo "</div>";

// Test 3: AJAX Endpoints
echo "<div class='test-section'>";
echo "<h2>Test 3: AJAX Endpoints</h2>";

// Test if hooks are registered
$wp_ajax_hooks = [
    'wp_ajax_chamberboss_register_member',
    'wp_ajax_nopriv_chamberboss_register_member',
    'wp_ajax_chamberboss_create_registration_payment_intent',
    'wp_ajax_nopriv_chamberboss_create_registration_payment_intent',
    'wp_ajax_chamberboss_test_ajax',
    'wp_ajax_nopriv_chamberboss_test_ajax'
];

foreach ($wp_ajax_hooks as $hook) {
    if (has_action($hook)) {
        echo "<span class='pass'>✅ $hook registered</span><br>";
    } else {
        echo "<span class='fail'>❌ $hook NOT registered</span><br>";
    }
}

echo "</div>";

// Test 4: JavaScript Dependencies
echo "<div class='test-section'>";
echo "<h2>Test 4: JavaScript & Styles</h2>";

// Check if script is enqueued
global $wp_scripts, $wp_styles;

if (wp_script_is('chamberboss-frontend', 'registered')) {
    echo "<span class='pass'>✅ Frontend JavaScript registered</span><br>";
} else {
    echo "<span class='fail'>❌ Frontend JavaScript NOT registered</span><br>";
}

if (wp_style_is('chamberboss-frontend', 'registered')) {
    echo "<span class='pass'>✅ Frontend CSS registered</span><br>";
} else {
    echo "<span class='warning'>⚠️ Frontend CSS not registered</span><br>";
}

echo "</div>";

// Test 5: Database Tables
echo "<div class='test-section'>";
echo "<h2>Test 5: Database Tables</h2>";

global $wpdb;

$tables_to_check = [
    $wpdb->prefix . 'chamberboss_members',
    $wpdb->prefix . 'chamberboss_transactions',
    $wpdb->prefix . 'chamberboss_categories'
];

foreach ($tables_to_check as $table) {
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
    if ($exists) {
        echo "<span class='pass'>✅ Table $table exists</span><br>";
    } else {
        echo "<span class='warning'>⚠️ Table $table missing (may be normal)</span><br>";
    }
}

echo "</div>";

// Show actual registration form
echo "<div class='test-section'>";
echo "<h2>Test 6: Live Registration Form</h2>";
echo "<p>This is the actual registration form output:</p>";
echo "<div style='border: 2px solid #007cba; padding: 20px; background: #f9f9f9;'>";
echo do_shortcode('[chamberboss_member_registration]');
echo "</div>";
echo "</div>";

// Final recommendations
echo "<div class='test-section'>";
echo "<h2>🎯 Next Steps</h2>";

$stripe_config = new \Chamberboss\Payments\StripeConfig();
if (!$stripe_config->is_configured()) {
    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px;'>";
    echo "<h3>Quick Fix Required:</h3>";
    echo "<ol>";
    echo "<li>Go to <a href='/wp-admin/admin.php?page=chamberboss-settings&tab=stripe' target='_blank'>WP Admin → ChumberBoss → Settings → Stripe</a></li>";
    echo "<li>Set Mode to 'Test Mode'</li>";
    echo "<li>Add your Stripe test keys (get from <a href='https://docs.stripe.com/keys' target='_blank'>Stripe docs</a>):</li>";
    echo "<ul>";
    echo "<li>Test Publishable Key: <code>pk_test_...</code></li>";
    echo "<li>Test Secret Key: <code>sk_test_...</code></li>";
    echo "</ul>";
    echo "<li>Save settings and refresh this page</li>";
    echo "</ol>";
    echo "</div>";
} else {
    echo "<div style='background: #d1ecf1; border: 1px solid #b3d7e0; padding: 15px; border-radius: 5px;'>";
    echo "<h3>Ready to Test:</h3>";
    echo "<ol>";
    echo "<li>Use test card: <code>4242 4242 4242 4242</code></li>";
    echo "<li>Any future expiry date and any 3-digit CVC</li>";
    echo "<li>Fill out the form above and submit</li>";
    echo "<li>Check for successful user creation and welcome email</li>";
    echo "</ol>";
    echo "</div>";
}

echo "</div>";

echo "<div style='margin-top: 30px; padding: 20px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 5px;'>";
echo "<h3>📊 Test Summary</h3>";
echo "<p><strong>Version:</strong> ChumberBoss v1.0.1</p>";
echo "<p><strong>Test Time:</strong> " . current_time('mysql') . "</p>";
echo "<p><strong>WordPress User:</strong> " . (is_user_logged_in() ? wp_get_current_user()->user_login : 'Not logged in') . "</p>";
echo "<p><strong>Plugin Active:</strong> " . (is_plugin_active('chamberboss/chamberboss.php') ? 'Yes' : 'No') . "</p>";
echo "</div>";

echo "</body></html>";
?> 