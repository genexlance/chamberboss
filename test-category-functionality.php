<?php
/**
 * Test script to verify business listing category functionality
 * This script should be run in a WordPress environment
 */

// Test 1: Check if business_listing post type is registered
echo "=== Testing Business Listing Post Type Registration ===\n";
if (post_type_exists('business_listing')) {
    echo "✓ business_listing post type is registered\n";
    
    $post_type_object = get_post_type_object('business_listing');
    echo "✓ Post type supports: " . implode(', ', $post_type_object->supports) . "\n";
    echo "✓ Post type taxonomies: " . implode(', ', $post_type_object->taxonomies) . "\n";
} else {
    echo "✗ business_listing post type is NOT registered\n";
}

// Test 2: Check if business_category taxonomy is registered
echo "\n=== Testing Business Category Taxonomy Registration ===\n";
if (taxonomy_exists('business_category')) {
    echo "✓ business_category taxonomy is registered\n";
    
    $taxonomy_object = get_taxonomy('business_category');
    echo "✓ Taxonomy is hierarchical: " . ($taxonomy_object->hierarchical ? 'Yes' : 'No') . "\n";
    echo "✓ Taxonomy shows UI: " . ($taxonomy_object->show_ui ? 'Yes' : 'No') . "\n";
    echo "✓ Taxonomy shows in REST: " . ($taxonomy_object->show_in_rest ? 'Yes' : 'No') . "\n";
    echo "✓ Taxonomy object types: " . implode(', ', $taxonomy_object->object_type) . "\n";
} else {
    echo "✗ business_category taxonomy is NOT registered\n";
}

// Test 3: Check if Gutenberg is disabled for business_listing
echo "\n=== Testing Gutenberg Disable Filter ===\n";
$use_block_editor = use_block_editor_for_post_type('business_listing');
if (!$use_block_editor) {
    echo "✓ Gutenberg is disabled for business_listing post type\n";
} else {
    echo "✗ Gutenberg is still enabled for business_listing post type\n";
}

// Test 4: Create test categories and business listing
echo "\n=== Testing Category Creation and Assignment ===\n";

// Create test categories
$test_categories = array('Restaurant', 'Retail', 'Professional Services');
$created_category_ids = array();

foreach ($test_categories as $category_name) {
    $term = wp_insert_term($category_name, 'business_category');
    if (!is_wp_error($term)) {
        $created_category_ids[] = $term['term_id'];
        echo "✓ Created category: $category_name (ID: {$term['term_id']})\n";
    } else {
        echo "✗ Failed to create category: $category_name - " . $term->get_error_message() . "\n";
    }
}

// Create test business listing
$test_post = wp_insert_post(array(
    'post_title' => 'Test Business Listing',
    'post_content' => 'This is a test business listing.',
    'post_status' => 'publish',
    'post_type' => 'business_listing'
));

if (!is_wp_error($test_post)) {
    echo "✓ Created test business listing (ID: $test_post)\n";
    
    // Assign categories to the business listing
    if (!empty($created_category_ids)) {
        $result = wp_set_post_terms($test_post, $created_category_ids, 'business_category');
        if (!is_wp_error($result)) {
            echo "✓ Assigned categories to business listing\n";
            
            // Verify category assignment
            $assigned_terms = wp_get_post_terms($test_post, 'business_category');
            if (!is_wp_error($assigned_terms) && !empty($assigned_terms)) {
                echo "✓ Categories successfully assigned: ";
                foreach ($assigned_terms as $term) {
                    echo $term->name . " ";
                }
                echo "\n";
            } else {
                echo "✗ Failed to retrieve assigned categories\n";
            }
        } else {
            echo "✗ Failed to assign categories: " . $result->get_error_message() . "\n";
        }
    }
    
    // Clean up test data
    wp_delete_post($test_post, true);
    echo "✓ Cleaned up test business listing\n";
} else {
    echo "✗ Failed to create test business listing: " . $test_post->get_error_message() . "\n";
}

// Clean up test categories
foreach ($created_category_ids as $term_id) {
    wp_delete_term($term_id, 'business_category');
}
echo "✓ Cleaned up test categories\n";

echo "\n=== Test Complete ===\n";