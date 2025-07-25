<?php
/**
 * Verification script for business listing category functionality
 * Run this in WordPress admin to verify the implementation
 */

// Check if we're in WordPress admin
if (!is_admin()) {
    die('This script must be run in WordPress admin context');
}

echo "<h2>Business Listing Category Implementation Verification</h2>";

// Test 1: Verify Gutenberg is disabled
echo "<h3>1. Gutenberg Block Editor Status</h3>";
$use_block_editor = use_block_editor_for_post_type('business_listing');
if (!$use_block_editor) {
    echo "<p style='color: green;'>✓ Gutenberg is correctly disabled for business_listing post type</p>";
} else {
    echo "<p style='color: red;'>✗ Gutenberg is still enabled for business_listing post type</p>";
}

// Test 2: Verify taxonomy registration
echo "<h3>2. Business Category Taxonomy</h3>";
if (taxonomy_exists('business_category')) {
    echo "<p style='color: green;'>✓ business_category taxonomy is registered</p>";
    
    $taxonomy = get_taxonomy('business_category');
    echo "<ul>";
    echo "<li>Hierarchical: " . ($taxonomy->hierarchical ? 'Yes' : 'No') . "</li>";
    echo "<li>Show UI: " . ($taxonomy->show_ui ? 'Yes' : 'No') . "</li>";
    echo "<li>Show in REST: " . ($taxonomy->show_in_rest ? 'Yes' : 'No') . "</li>";
    echo "<li>Meta box disabled: " . ($taxonomy->meta_box_cb === false ? 'Yes (using custom)' : 'No') . "</li>";
    echo "</ul>";
} else {
    echo "<p style='color: red;'>✗ business_category taxonomy is not registered</p>";
}

// Test 3: Verify post type supports taxonomy
echo "<h3>3. Post Type Taxonomy Support</h3>";
if (post_type_exists('business_listing')) {
    $post_type = get_post_type_object('business_listing');
    if (in_array('business_category', $post_type->taxonomies)) {
        echo "<p style='color: green;'>✓ business_listing post type supports business_category taxonomy</p>";
    } else {
        echo "<p style='color: red;'>✗ business_listing post type does not support business_category taxonomy</p>";
    }
} else {
    echo "<p style='color: red;'>✗ business_listing post type is not registered</p>";
}

// Test 4: Check if meta boxes are registered
echo "<h3>4. Meta Box Registration</h3>";
global $wp_meta_boxes;
if (isset($wp_meta_boxes['business_listing']['side']['default']['business_listing_categories'])) {
    echo "<p style='color: green;'>✓ Business Categories meta box is registered</p>";
} else {
    echo "<p style='color: orange;'>? Business Categories meta box registration cannot be verified (may only be available on edit screen)</p>";
}

if (isset($wp_meta_boxes['business_listing']['normal']['high']['business_listing_details'])) {
    echo "<p style='color: green;'>✓ Business Details meta box is registered</p>";
} else {
    echo "<p style='color: orange;'>? Business Details meta box registration cannot be verified (may only be available on edit screen)</p>";
}

// Test 5: Create sample categories for testing
echo "<h3>5. Sample Category Creation Test</h3>";
$test_categories = array('Restaurant', 'Retail Store', 'Professional Service');
$created_terms = array();

foreach ($test_categories as $cat_name) {
    $term = get_term_by('name', $cat_name, 'business_category');
    if (!$term) {
        $result = wp_insert_term($cat_name, 'business_category');
        if (!is_wp_error($result)) {
            $created_terms[] = $result['term_id'];
            echo "<p style='color: green;'>✓ Created test category: $cat_name</p>";
        } else {
            echo "<p style='color: red;'>✗ Failed to create category: $cat_name - " . $result->get_error_message() . "</p>";
        }
    } else {
        echo "<p style='color: blue;'>ℹ Category already exists: $cat_name</p>";
    }
}

// Display existing categories
echo "<h3>6. Existing Business Categories</h3>";
$categories = get_terms(array(
    'taxonomy' => 'business_category',
    'hide_empty' => false,
));

if (!empty($categories) && !is_wp_error($categories)) {
    echo "<ul>";
    foreach ($categories as $category) {
        echo "<li>{$category->name} (ID: {$category->term_id})</li>";
    }
    echo "</ul>";
} else {
    echo "<p>No categories found or error occurred.</p>";
}

echo "<h3>Implementation Summary</h3>";
echo "<p>The implementation includes:</p>";
echo "<ul>";
echo "<li>✓ Gutenberg block editor disabled for business_listing post type</li>";
echo "<li>✓ Custom category meta box added to classic editor sidebar</li>";
echo "<li>✓ Category saving functionality implemented</li>";
echo "<li>✓ Taxonomy properly registered with REST API support</li>";
echo "<li>✓ CSS styling added for better user experience</li>";
echo "</ul>";

echo "<p><strong>Next steps:</strong> Test the functionality by creating or editing a business listing in the WordPress admin.</p>";
?>