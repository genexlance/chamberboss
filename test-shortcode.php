<?php
/**
 * Test Chamberboss Shortcode Registration
 * Place this file in your WordPress root and visit /test-shortcode.php
 */

// Load WordPress
require_once('wp-load.php');

echo "<h1>Chamberboss Shortcode Test</h1>";

// Check if shortcode exists
if (shortcode_exists('chamberboss_member_registration')) {
    echo "<p style='color:green;'><strong>✅ Shortcode EXISTS</strong></p>";
    
    // Test the shortcode
    echo "<h2>Shortcode Output:</h2>";
    echo "<div style='border:2px solid blue; padding:20px;'>";
    echo do_shortcode('[chamberboss_member_registration]');
    echo "</div>";
    
} else {
    echo "<p style='color:red;'><strong>❌ Shortcode NOT FOUND</strong></p>";
    
    // List all registered shortcodes
    global $shortcode_tags;
    echo "<h2>All Registered Shortcodes:</h2>";
    echo "<pre>";
    print_r(array_keys($shortcode_tags));
    echo "</pre>";
}

// Check if Directory class exists
if (class_exists('Chamberboss\Public\Directory')) {
    echo "<p style='color:green;'><strong>✅ Directory Class EXISTS</strong></p>";
} else {
    echo "<p style='color:red;'><strong>❌ Directory Class NOT FOUND</strong></p>";
} 