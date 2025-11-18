<?php
/**
 * Admin Class - Updated AJAX Export Handler
 * 
 * Replace the ajax_export method in class-admin.php with this version
 * to use the improved exporter by default.
 * 
 * This version:
 * 1. Uses RIA_DM_Exporter_Improved by default
 * 2. Allows users to choose export method via a new parameter
 * 3. Falls back to REST API for Pages by default (most reliable)
 */

/**
 * AJAX export handler - IMPROVED VERSION
 */
public function ajax_export() {
    check_ajax_referer('ria_dm_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Insufficient permissions'));
    }
    
    // Get export parameters
    $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : 'post';
    $post_status = isset($_POST['post_status']) ? array_map('sanitize_text_field', $_POST['post_status']) : array('publish');
    $include_acf = isset($_POST['include_acf']) && $_POST['include_acf'] === 'true';
    $include_taxonomies = isset($_POST['include_taxonomies']) && $_POST['include_taxonomies'] === 'true';
    $include_featured_image = isset($_POST['include_featured_image']) && $_POST['include_featured_image'] === 'true';
    $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '';
    $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '';
    
    // New: Get export method (defaults to 'improved', use 'rest' for Pages)
    $export_method = isset($_POST['export_method']) ? sanitize_text_field($_POST['export_method']) : 'auto';
    
    // Auto-detect: use REST API for Pages, improved for everything else
    if ($export_method === 'auto') {
        $export_method = ($post_type === 'page') ? 'rest' : 'improved';
    }
    
    // Prepare export arguments
    $args = array(
        'post_type' => $post_type,
        'post_status' => $post_status,
        'include_acf' => $include_acf,
        'include_taxonomies' => $include_taxonomies,
        'include_featured_image' => $include_featured_image,
        'date_from' => $date_from,
        'date_to' => $date_to,
    );
    
    // Perform export based on method
    switch ($export_method) {
        case 'rest':
            // Use REST API method (best for Pages with complex content)
            $args['use_rest_api'] = true;
            $result = RIA_DM_Exporter_Improved::export($args);
            $method_label = 'REST API';
            break;
            
        case 'original':
            // Use original exporter (for compatibility)
            $result = RIA_DM_Exporter::export($args);
            $method_label = 'Original';
            break;
            
        case 'improved':
        default:
            // Use improved exporter (default)
            $result = RIA_DM_Exporter_Improved::export($args);
            $method_label = 'Improved';
            break;
    }
    
    if (is_wp_error($result)) {
        wp_send_json_error(array(
            'message' => $result->get_error_message(),
            'method' => $method_label,
        ));
    }
    
    // Get download URL
    $download_url = RIA_DM_CSV_Processor::get_download_url($result);
    
    wp_send_json_success(array(
        'message' => sprintf('Export completed successfully using %s method', $method_label),
        'download_url' => $download_url,
        'filename' => basename($result),
        'method' => $method_label,
        'filesize' => size_format(filesize($result)),
    ));
}
