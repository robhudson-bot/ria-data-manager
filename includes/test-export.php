<?php
/**
 * Test and Compare Export Methods
 * 
 * This script allows you to:
 * 1. Test the improved exporter
 * 2. Compare standard vs improved exports
 * 3. Export via REST API for maximum reliability
 * 
 * Usage: Place in wp-content/plugins/ria-data-manager/ and access via admin menu
 */

if (!defined('ABSPATH')) {
    exit;
}

// Add test page to admin menu
add_action('admin_menu', 'ria_dm_add_test_page');
function ria_dm_add_test_page() {
    add_submenu_page(
        'ria-data-manager',
        'Export Tester',
        'Export Tester',
        'manage_options',
        'ria-dm-test-export',
        'ria_dm_render_test_page'
    );
}

function ria_dm_render_test_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }
    
    // Handle form submission
    $results = array();
    if (isset($_POST['test_export']) && check_admin_referer('ria_dm_test_export')) {
        $post_type = sanitize_text_field($_POST['post_type']);
        $export_method = sanitize_text_field($_POST['export_method']);
        
        require_once RIA_DM_PLUGIN_DIR . 'includes/class-exporter-improved.php';
        
        $args = array(
            'post_type' => $post_type,
            'post_status' => array('publish', 'draft'),
            'include_acf' => true,
            'include_taxonomies' => true,
            'include_featured_image' => true,
        );
        
        switch ($export_method) {
            case 'original':
                $start = microtime(true);
                $file_path = RIA_DM_Exporter::export($args);
                $time = microtime(true) - $start;
                $method_label = 'Original Exporter';
                break;
                
            case 'improved':
                $start = microtime(true);
                $file_path = RIA_DM_Exporter_Improved::export($args);
                $time = microtime(true) - $start;
                $method_label = 'Improved Exporter';
                break;
                
            case 'rest':
                $args['use_rest_api'] = true;
                $start = microtime(true);
                $file_path = RIA_DM_Exporter_Improved::export($args);
                $time = microtime(true) - $start;
                $method_label = 'REST API Exporter';
                break;
                
            case 'compare':
                // Compare all methods
                $start = microtime(true);
                $file1 = RIA_DM_Exporter::export($args);
                $time1 = microtime(true) - $start;
                
                $start = microtime(true);
                $file2 = RIA_DM_Exporter_Improved::export($args);
                $time2 = microtime(true) - $start;
                
                $args['use_rest_api'] = true;
                $start = microtime(true);
                $file3 = RIA_DM_Exporter_Improved::export($args);
                $time3 = microtime(true) - $start;
                
                $results = array(
                    'method' => 'Comparison',
                    'files' => array(
                        'Original' => array(
                            'file' => $file1,
                            'time' => $time1,
                            'size' => !is_wp_error($file1) ? filesize($file1) : 0,
                            'download' => !is_wp_error($file1) ? RIA_DM_CSV_Processor::get_download_url($file1) : null,
                        ),
                        'Improved' => array(
                            'file' => $file2,
                            'time' => $time2,
                            'size' => !is_wp_error($file2) ? filesize($file2) : 0,
                            'download' => !is_wp_error($file2) ? RIA_DM_CSV_Processor::get_download_url($file2) : null,
                        ),
                        'REST API' => array(
                            'file' => $file3,
                            'time' => $time3,
                            'size' => !is_wp_error($file3) ? filesize($file3) : 0,
                            'download' => !is_wp_error($file3) ? RIA_DM_CSV_Processor::get_download_url($file3) : null,
                        ),
                    ),
                );
                break;
        }
        
        if (!isset($results['method'])) {
            if (is_wp_error($file_path)) {
                $results = array(
                    'error' => $file_path->get_error_message(),
                    'method' => $method_label,
                );
            } else {
                $results = array(
                    'success' => true,
                    'method' => $method_label,
                    'file' => $file_path,
                    'time' => round($time, 3),
                    'size' => filesize($file_path),
                    'download_url' => RIA_DM_CSV_Processor::get_download_url($file_path),
                );
            }
        }
    }
    
    // Get available post types
    $post_types = RIA_DM_Exporter::get_available_post_types();
    
    ?>
    <div class="wrap">
        <h1>Export Method Tester</h1>
        
        <div class="card">
            <h2>Test Different Export Methods</h2>
            <p>This tool helps you test and compare different export methods to find the best one for your Pages.</p>
            
            <form method="post">
                <?php wp_nonce_field('ria_dm_test_export'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="post_type">Post Type</label>
                        </th>
                        <td>
                            <select name="post_type" id="post_type" class="regular-text">
                                <?php foreach ($post_types as $slug => $post_type) : ?>
                                    <option value="<?php echo esc_attr($slug); ?>" <?php selected($slug, 'page'); ?>>
                                        <?php echo esc_html($post_type->labels->name . ' (' . $slug . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Select the post type to export</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="export_method">Export Method</label>
                        </th>
                        <td>
                            <select name="export_method" id="export_method" class="regular-text">
                                <option value="original">Original Exporter (Current)</option>
                                <option value="improved" selected>Improved Exporter (Fixed)</option>
                                <option value="rest">REST API Exporter (Most Reliable)</option>
                                <option value="compare">Compare All Methods</option>
                            </select>
                            <p class="description">
                                <strong>Original:</strong> Uses current export logic<br>
                                <strong>Improved:</strong> Better content sanitization and field alignment<br>
                                <strong>REST API:</strong> Uses WordPress REST API for maximum reliability<br>
                                <strong>Compare:</strong> Runs all three and shows results
                            </p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" name="test_export" class="button button-primary">
                        Run Export Test
                    </button>
                </p>
            </form>
        </div>
        
        <?php if (!empty($results)) : ?>
            <div class="card" style="margin-top: 20px;">
                <h2>Export Results</h2>
                
                <?php if (isset($results['error'])) : ?>
                    <div class="notice notice-error inline">
                        <p><strong>Error with <?php echo esc_html($results['method']); ?>:</strong></p>
                        <p><?php echo esc_html($results['error']); ?></p>
                    </div>
                    
                <?php elseif (isset($results['files'])) : ?>
                    <p><strong>Comparison Results:</strong></p>
                    
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Method</th>
                                <th>Time</th>
                                <th>File Size</th>
                                <th>Status</th>
                                <th>Download</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results['files'] as $method => $data) : ?>
                                <tr>
                                    <td><strong><?php echo esc_html($method); ?></strong></td>
                                    <td><?php echo esc_html(round($data['time'], 3)); ?>s</td>
                                    <td><?php echo esc_html(size_format($data['size'])); ?></td>
                                    <td>
                                        <?php if (is_wp_error($data['file'])) : ?>
                                            <span style="color: red;">❌ Error</span>
                                        <?php else : ?>
                                            <span style="color: green;">✅ Success</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!is_wp_error($data['file']) && $data['download']) : ?>
                                            <a href="<?php echo esc_url($data['download']); ?>" class="button button-small">
                                                Download CSV
                                            </a>
                                        <?php else : ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <div style="margin-top: 15px;">
                        <h3>Recommendations:</h3>
                        <ul style="list-style: disc; margin-left: 20px;">
                            <li>If all methods work, <strong>Improved Exporter</strong> offers the best balance of speed and reliability</li>
                            <li>If you're experiencing data misalignment, use <strong>REST API Exporter</strong> for maximum accuracy</li>
                            <li>Compare file sizes - they should be similar if content is being exported correctly</li>
                            <li>Open the CSV files to verify content is properly aligned in columns</li>
                        </ul>
                    </div>
                    
                <?php else : ?>
                    <div class="notice notice-success inline">
                        <p><strong>Success with <?php echo esc_html($results['method']); ?>!</strong></p>
                        <p>
                            Export completed in <?php echo esc_html($results['time']); ?> seconds<br>
                            File size: <?php echo esc_html(size_format($results['size'])); ?><br>
                            <a href="<?php echo esc_url($results['download_url']); ?>" class="button button-primary">
                                Download CSV File
                            </a>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div class="card" style="margin-top: 20px;">
            <h2>Troubleshooting Tips</h2>
            <ol>
                <li><strong>Content Misalignment:</strong> This usually happens when content contains unescaped quotes or newlines. The improved exporter handles this better.</li>
                <li><strong>Check Your CSV:</strong> Open the exported file in a text editor (not Excel) to see if quotes and commas are properly escaped.</li>
                <li><strong>Use REST API:</strong> For Pages with complex HTML content, the REST API method often gives the cleanest results.</li>
                <li><strong>ACF Fields:</strong> Complex ACF fields (repeaters, groups) are JSON-encoded for reliable export/import.</li>
                <li><strong>Character Encoding:</strong> All exports use UTF-8 with BOM for Excel compatibility.</li>
            </ol>
        </div>
    </div>
    
    <style>
        .card { 
            background: #fff; 
            border: 1px solid #ccd0d4; 
            box-shadow: 0 1px 1px rgba(0,0,0,.04); 
            padding: 20px; 
            margin-bottom: 20px; 
        }
        .card h2 { 
            margin-top: 0; 
            font-size: 18px; 
        }
    </style>
    <?php
}
