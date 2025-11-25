<?php
/**
 * Quick Test Script - Export Pages via REST API
 * 
 * This is a standalone script you can run to immediately test
 * exporting Pages via the REST API method without modifying any code.
 * 
 * USAGE:
 * 1. Upload this file to: wp-content/plugins/ria-data-manager/
 * 2. Go to: yoursite.com/wp-content/plugins/ria-data-manager/quick-test-export.php
 * 3. OR add to functions.php and visit: yoursite.com/?ria_test_export=1
 * 
 * This will export all published Pages and show you the download link.
 */

// Method 1: Direct access (uncomment if accessing directly)
/*
require_once('../../../wp-load.php');

if (!current_user_can('manage_options')) {
    die('Insufficient permissions');
}

// Load required files
require_once('includes/class-csv-processor.php');
require_once('includes/class-acf-handler.php');
require_once('includes/class-taxonomy-handler.php');
require_once('includes/class-exporter-improved.php');

echo '<h1>RIA Data Manager - Quick Test Export</h1>';
echo '<p>Testing Page export via REST API...</p>';

$args = array(
    'post_type' => 'page',
    'post_status' => array('publish'),
    'include_acf' => true,
    'include_taxonomies' => true,
    'include_featured_image' => true,
    'use_rest_api' => true,  // Use REST API method
);

$start = microtime(true);
$result = RIA_DM_Exporter_Improved::export($args);
$time = microtime(true) - $start;

if (is_wp_error($result)) {
    echo '<div style="color: red; padding: 20px; background: #fee;">';
    echo '<h2>Export Failed</h2>';
    echo '<p>' . esc_html($result->get_error_message()) . '</p>';
    echo '</div>';
} else {
    $download_url = RIA_DM_CSV_Processor::get_download_url($result);
    $filesize = filesize($result);
    
    echo '<div style="color: green; padding: 20px; background: #efe;">';
    echo '<h2>Export Successful!</h2>';
    echo '<p>File: ' . basename($result) . '</p>';
    echo '<p>Size: ' . size_format($filesize) . '</p>';
    echo '<p>Time: ' . round($time, 3) . ' seconds</p>';
    echo '<p><a href="' . esc_url($download_url) . '" style="display:inline-block; padding:10px 20px; background:#0073aa; color:white; text-decoration:none; border-radius:3px;">Download CSV File</a></p>';
    echo '</div>';
    
    echo '<h2>Next Steps:</h2>';
    echo '<ol>';
    echo '<li>Download the CSV file</li>';
    echo '<li>Open in a text editor (not Excel) to verify formatting</li>';
    echo '<li>Check that content is properly aligned</li>';
    echo '<li>If it looks good, implement the fix in your admin class</li>';
    echo '</ol>';
}

echo '<hr>';
echo '<p><a href="' . admin_url('tools.php?page=ria-data-manager') . '">Back to RIA Data Manager</a></p>';
*/

// Method 2: Add to functions.php (uncomment to use)
/*
add_action('init', 'ria_dm_quick_test');
function ria_dm_quick_test() {
    if (!isset($_GET['ria_test_export']) || !current_user_can('manage_options')) {
        return;
    }
    
    // Load improved exporter if not already loaded
    if (!class_exists('RIA_DM_Exporter_Improved')) {
        require_once(WP_PLUGIN_DIR . '/ria-data-manager/includes/class-exporter-improved.php');
    }
    
    $args = array(
        'post_type' => 'page',
        'post_status' => array('publish'),
        'include_acf' => true,
        'include_taxonomies' => true,
        'include_featured_image' => true,
        'use_rest_api' => true,
    );
    
    $result = RIA_DM_Exporter_Improved::export($args);
    
    if (!is_wp_error($result)) {
        $download_url = RIA_DM_CSV_Processor::get_download_url($result);
        wp_redirect($download_url);
        exit;
    } else {
        wp_die('Export failed: ' . $result->get_error_message());
    }
}
*/

// Method 3: WordPress Admin AJAX (most integrated)
add_action('wp_ajax_ria_quick_test_export', 'ria_dm_quick_test_ajax');
function ria_dm_quick_test_ajax() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    $post_type = isset($_GET['post_type']) ? sanitize_text_field($_GET['post_type']) : 'page';
    $method = isset($_GET['method']) ? sanitize_text_field($_GET['method']) : 'improved';
    
    $args = array(
        'post_type' => $post_type,
        'post_status' => array('publish', 'draft'),
        'include_acf' => true,
        'include_taxonomies' => true,
        'include_featured_image' => true,
        'use_rest_api' => false, // Default to direct method (more reliable)
    );

    // Only use REST API if explicitly requested
    if ($method === 'rest') {
        $args['use_rest_api'] = true;
    }
    
    $start = microtime(true);
    
    if ($method === 'original') {
        $result = RIA_DM_Exporter::export($args);
    } else {
        $result = RIA_DM_Exporter_Improved::export($args);
    }
    
    $time = microtime(true) - $start;
    
    if (is_wp_error($result)) {
        wp_send_json_error(array(
            'message' => $result->get_error_message(),
            'method' => $method,
        ));
    }
    
    wp_send_json_success(array(
        'message' => 'Export completed',
        'method' => $method,
        'download_url' => RIA_DM_CSV_Processor::get_download_url($result),
        'filename' => basename($result),
        'filesize' => size_format(filesize($result)),
        'time' => round($time, 3) . 's',
    ));
}

// Add quick test button to admin bar
add_action('admin_bar_menu', 'ria_dm_quick_test_admin_bar', 100);
function ria_dm_quick_test_admin_bar($wp_admin_bar) {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $wp_admin_bar->add_menu(array(
        'id' => 'ria-quick-export',
        'title' => 'Quick Export Pages',
        'href' => '#',
        'meta' => array(
            'title' => 'Quick test export for Pages',
            'onclick' => 'riaQuickExport(); return false;',
        ),
    ));
}

// Add JavaScript for quick export
add_action('admin_footer', 'ria_dm_quick_test_js');
add_action('wp_footer', 'ria_dm_quick_test_js');
function ria_dm_quick_test_js() {
    if (!current_user_can('manage_options')) {
        return;
    }
    ?>
    <script>
    function riaQuickExport() {
        if (!confirm('Export all Pages using Improved method?')) {
            return;
        }
        
        var adminUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
        var exportUrl = adminUrl + '?action=ria_quick_test_export&post_type=page&method=improved';
        
        // Show loading
        var loadingDiv = document.createElement('div');
        loadingDiv.id = 'ria-loading';
        loadingDiv.style = 'position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:white;padding:30px;border:2px solid #0073aa;border-radius:5px;z-index:999999;box-shadow:0 0 20px rgba(0,0,0,0.3);';
        loadingDiv.innerHTML = '<h2 style="margin:0 0 10px 0;">Exporting Pages...</h2><p style="margin:0;">Please wait, this may take a moment.</p>';
        document.body.appendChild(loadingDiv);
        
        fetch(exportUrl)
            .then(response => response.json())
            .then(data => {
                document.body.removeChild(loadingDiv);
                
                if (data.success) {
                    var resultDiv = document.createElement('div');
                    resultDiv.style = 'position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:white;padding:30px;border:2px solid #46b450;border-radius:5px;z-index:999999;box-shadow:0 0 20px rgba(0,0,0,0.3);max-width:500px;';
                    resultDiv.innerHTML = 
                        '<h2 style="margin:0 0 15px 0;color:#46b450;">Export Successful!</h2>' +
                        '<p><strong>Method:</strong> ' + data.data.method + '</p>' +
                        '<p><strong>File:</strong> ' + data.data.filename + '</p>' +
                        '<p><strong>Size:</strong> ' + data.data.filesize + '</p>' +
                        '<p><strong>Time:</strong> ' + data.data.time + '</p>' +
                        '<p style="margin-top:20px;">' +
                        '<a href="' + data.data.download_url + '" style="display:inline-block;padding:10px 20px;background:#0073aa;color:white;text-decoration:none;border-radius:3px;margin-right:10px;">Download CSV</a>' +
                        '<button onclick="this.parentElement.parentElement.remove()" style="padding:10px 20px;background:#ddd;border:none;border-radius:3px;cursor:pointer;">Close</button>' +
                        '</p>';
                    document.body.appendChild(resultDiv);
                } else {
                    alert('Export failed: ' + data.data.message);
                }
            })
            .catch(error => {
                document.body.removeChild(loadingDiv);
                alert('Export error: ' + error.message);
            });
    }
    </script>
    <?php
}
