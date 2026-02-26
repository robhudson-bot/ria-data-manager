<?php
/**
 * Diagnostic Script for ACF and Taxonomy Detection
 *
 * Run this on the live site to debug why fields aren't appearing in export
 *
 * Usage: Add this to the plugin, then visit:
 * https://example.com/wp-admin/admin.php?page=quarry-diagnostic
 */

if (!defined('ABSPATH')) {
    exit;
}

// Hook to admin menu
add_action('admin_menu', function() {
    add_submenu_page(
        null, // Hidden from menu
        'Quarry Diagnostic',
        'Quarry Diagnostic',
        'manage_options',
        'quarry-diagnostic',
        'qry_diagnostic_page'
    );
});

function qry_diagnostic_page() {
    ?>
    <div class="wrap">
        <h1>Quarry - ACF & Taxonomy Diagnostic</h1>

        <?php
        echo '<div style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccc;">';

        // Test 1: ACF Active?
        echo '<h2>1. ACF Detection</h2>';
        $acf_active = class_exists('ACF');
        if ($acf_active) {
            echo '<p style="color: green;">✅ ACF is ACTIVE</p>';
            echo '<p>ACF Version: ' . (defined('ACF_VERSION') ? ACF_VERSION : 'Unknown') . '</p>';
        } else {
            echo '<p style="color: red;">❌ ACF is NOT ACTIVE</p>';
        }

        // Test 2: ACF Field Groups for Pages
        echo '<h2>2. ACF Field Groups for "page" Post Type</h2>';
        if ($acf_active && function_exists('acf_get_field_groups')) {
            $field_groups = acf_get_field_groups(array('post_type' => 'page'));
            if ($field_groups) {
                echo '<p style="color: green;">✅ Found ' . count($field_groups) . ' field group(s)</p>';
                echo '<ul>';
                foreach ($field_groups as $group) {
                    echo '<li><strong>' . esc_html($group['title']) . '</strong> (key: ' . esc_html($group['key']) . ')';

                    // Get fields in this group
                    $fields = acf_get_fields($group['key']);
                    if ($fields) {
                        echo '<ul>';
                        foreach ($fields as $field) {
                            echo '<li>' . esc_html($field['label']) . ' (name: ' . esc_html($field['name']) . ', type: ' . esc_html($field['type']) . ')</li>';
                        }
                        echo '</ul>';
                    }
                    echo '</li>';
                }
                echo '</ul>';
            } else {
                echo '<p style="color: orange;">⚠️ No field groups found with location rules for "page" post type</p>';
            }
        }

        // Test 3: Sample actual page ACF fields
        echo '<h2>3. ACF Fields from Sample Pages</h2>';
        $sample_pages = get_posts(array(
            'post_type' => 'page',
            'posts_per_page' => 5,
            'post_status' => 'publish',
        ));

        if ($sample_pages && $acf_active && function_exists('get_field_objects')) {
            $all_field_names = array();
            echo '<p>Checking first 5 published pages...</p>';
            foreach ($sample_pages as $page) {
                $fields = get_field_objects($page->ID);
                if ($fields) {
                    echo '<p><strong>' . esc_html($page->post_title) . '</strong> (ID: ' . $page->ID . '):</p>';
                    echo '<ul>';
                    foreach ($fields as $field_name => $field) {
                        echo '<li>' . esc_html($field['label']) . ' (name: ' . esc_html($field_name) . ', type: ' . esc_html($field['type']) . ')</li>';
                        $all_field_names[$field_name] = true;
                    }
                    echo '</ul>';
                } else {
                    echo '<p>' . esc_html($page->post_title) . ' (ID: ' . $page->ID . '): No ACF fields</p>';
                }
            }

            if (!empty($all_field_names)) {
                echo '<p style="color: green;">✅ Found ' . count($all_field_names) . ' unique ACF field(s) across sample pages:</p>';
                echo '<pre>' . implode(', ', array_keys($all_field_names)) . '</pre>';
            } else {
                echo '<p style="color: red;">❌ No ACF fields found on any sample pages</p>';
            }
        } else {
            echo '<p style="color: orange;">⚠️ Could not check sample pages (ACF may not be active)</p>';
        }

        // Test 4: Taxonomies for Pages
        echo '<h2>4. Taxonomies for "page" Post Type</h2>';
        $taxonomies = get_object_taxonomies('page', 'objects');
        if ($taxonomies) {
            echo '<p style="color: green;">✅ Found ' . count($taxonomies) . ' taxonom(ies)</p>';
            echo '<ul>';
            foreach ($taxonomies as $tax) {
                echo '<li><strong>' . esc_html($tax->label) . '</strong> (name: ' . esc_html($tax->name) . ')</li>';
            }
            echo '</ul>';
        } else {
            echo '<p style="color: orange;">⚠️ No taxonomies registered for "page" post type</p>';
            echo '<p><small>Note: By default, WordPress pages don\'t have categories or tags. This is normal unless custom taxonomies were added.</small></p>';
        }

        // Test 5: Check what our exporter would detect
        echo '<h2>5. What Our Exporter Would Find</h2>';
        if (class_exists('QRY_Metadata_Exporter')) {
            echo '<p>Testing the metadata exporter\'s ACF detection method...</p>';

            // Simulate what the exporter does
            $field_names = array();

            // Method 1: Location-based
            if ($acf_active && class_exists('QRY_ACF_Handler')) {
                $fields_by_location = QRY_ACF_Handler::get_fields_for_post_type('page');
                foreach ($fields_by_location as $field_name => $field_data) {
                    $field_names[$field_name] = 'location-based';
                }
            }

            // Method 2: Post sampling
            $query = new WP_Query(array(
                'post_type' => 'page',
                'posts_per_page' => 5,
                'post_status' => 'any',
                'fields' => 'ids',
            ));

            if ($query->have_posts() && function_exists('get_field_objects')) {
                foreach ($query->posts as $post_id) {
                    $post_fields = get_field_objects($post_id);
                    if ($post_fields) {
                        foreach ($post_fields as $field_name => $field) {
                            if (!isset($field_names[$field_name])) {
                                $field_names[$field_name] = 'post-sampling';
                            }
                        }
                    }
                }
            }

            if (!empty($field_names)) {
                echo '<p style="color: green;">✅ Exporter would find ' . count($field_names) . ' ACF field(s):</p>';
                echo '<table border="1" cellpadding="5" style="border-collapse: collapse;">';
                echo '<tr><th>Field Name</th><th>Detection Method</th></tr>';
                foreach ($field_names as $name => $method) {
                    echo '<tr><td>acf_' . esc_html($name) . '</td><td>' . esc_html($method) . '</td></tr>';
                }
                echo '</table>';
            } else {
                echo '<p style="color: red;">❌ Exporter would find NO ACF fields</p>';
            }

            wp_reset_postdata();
        } else {
            echo '<p style="color: red;">❌ QRY_Metadata_Exporter class not found!</p>';
        }

        // Test 6: Check plugin version
        echo '<h2>6. Plugin Version Check</h2>';
        if (defined('QRY_VERSION')) {
            echo '<p>Plugin Version: <strong>' . QRY_VERSION . '</strong></p>';
            if (QRY_VERSION === '1.2.0') {
                echo '<p style="color: green;">✅ Running v1.2.0 (latest with improved ACF detection)</p>';
            } else {
                echo '<p style="color: orange;">⚠️ Not running v1.2.0 - may need to update</p>';
            }
        } else {
            echo '<p style="color: red;">❌ Version constant not defined</p>';
        }

        echo '</div>';
        ?>
    </div>
    <?php
}
