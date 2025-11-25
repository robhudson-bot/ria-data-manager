<?php
/**
 * Google Sheets Integration Class
 *
 * Handles exporting to and importing from Google Sheets for collaborative editing
 *
 * @package RIA_Data_Manager
 * @since 1.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RIA_DM_Google_Sheets {

    /**
     * Export to Google Sheets
     *
     * @param array $args Export arguments
     * @return array|WP_Error Result with sheets URL or error
     */
    public static function export_to_sheets($args = array()) {
        // First, create metadata-only export
        $csv_path = RIA_DM_Metadata_Exporter::export($args);

        if (is_wp_error($csv_path)) {
            return $csv_path;
        }

        // Check if Google Sheets API is configured
        if (!self::is_configured()) {
            return new WP_Error(
                'not_configured',
                'Google Sheets API is not configured. Please set up API credentials in settings.'
            );
        }

        // TODO: Implement actual Google Sheets API push
        // For now, return instructions for manual upload

        $result = array(
            'success' => true,
            'csv_path' => $csv_path,
            'download_url' => RIA_DM_CSV_Processor::get_download_url($csv_path),
            'instructions' => self::get_manual_upload_instructions(),
            'sheets_compatible' => true,
        );

        return $result;
    }

    /**
     * Get manual upload instructions
     *
     * @return string HTML instructions
     */
    private static function get_manual_upload_instructions() {
        return '
        <div class="ria-dm-instructions">
            <h3>Upload to Google Sheets</h3>
            <ol>
                <li>Download the CSV file using the link above</li>
                <li>Go to <a href="https://sheets.google.com" target="_blank">Google Sheets</a></li>
                <li>Create a new spreadsheet or open an existing one</li>
                <li>Go to <strong>File → Import → Upload</strong></li>
                <li>Choose the downloaded CSV file</li>
                <li>Select <strong>"Replace spreadsheet"</strong> or <strong>"Insert new sheet"</strong></li>
                <li>Click <strong>Import data</strong></li>
                <li>Share the Google Sheet with your team</li>
            </ol>
            <p><strong>Tip:</strong> Keep the ID column! You\'ll need it to sync changes back to WordPress.</p>
        </div>';
    }

    /**
     * Import from Google Sheets CSV
     *
     * @param string $file_path Path to downloaded CSV from Google Sheets
     * @param array $args Import arguments
     * @return array|WP_Error Import results or error
     */
    public static function import_from_sheets($file_path, $args = array()) {
        $defaults = array(
            'dry_run' => false,
            'update_content' => false, // Don't update content by default
            'allowed_fields' => array(
                'post_title',
                'post_excerpt',
                'post_status',
                'post_name',
                'post_author',
                'post_parent',
                'menu_order',
                'featured_image',
            ),
        );

        $args = wp_parse_args($args, $defaults);

        // Read CSV
        $data = self::read_csv($file_path);

        if (is_wp_error($data)) {
            return $data;
        }

        // Process updates
        $results = array(
            'total' => count($data),
            'updated' => 0,
            'skipped' => 0,
            'errors' => array(),
            'changes' => array(),
        );

        foreach ($data as $row) {
            $post_id = isset($row['ID']) ? intval($row['ID']) : 0;

            if (!$post_id) {
                $results['skipped']++;
                $results['errors'][] = 'Row missing ID: ' . json_encode($row);
                continue;
            }

            // Check if post exists
            $post = get_post($post_id);
            if (!$post) {
                $results['skipped']++;
                $results['errors'][] = "Post ID {$post_id} not found";
                continue;
            }

            // Build update data
            $update_data = self::build_update_data($row, $args['allowed_fields']);

            if (empty($update_data)) {
                $results['skipped']++;
                continue;
            }

            // Track changes
            $changes = self::detect_changes($post_id, $update_data);

            if (empty($changes)) {
                $results['skipped']++;
                continue;
            }

            // Apply updates (if not dry run)
            if (!$args['dry_run']) {
                $updated = self::apply_updates($post_id, $update_data);

                if (is_wp_error($updated)) {
                    $results['errors'][] = "Post ID {$post_id}: " . $updated->get_error_message();
                    continue;
                }

                $results['updated']++;
            }

            $results['changes'][] = array(
                'post_id' => $post_id,
                'title' => $post->post_title,
                'changes' => $changes,
            );
        }

        return $results;
    }

    /**
     * Read CSV file
     *
     * @param string $file_path File path
     * @return array|WP_Error Data or error
     */
    private static function read_csv($file_path) {
        if (!file_exists($file_path)) {
            return new WP_Error('file_not_found', 'CSV file not found');
        }

        $handle = fopen($file_path, 'r');
        if (!$handle) {
            return new WP_Error('file_error', 'Could not open CSV file');
        }

        // Read headers
        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            return new WP_Error('invalid_csv', 'Invalid CSV format');
        }

        // Read data
        $data = array();
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) === count($headers)) {
                $data[] = array_combine($headers, $row);
            }
        }

        fclose($handle);

        return $data;
    }

    /**
     * Build update data from CSV row
     *
     * @param array $row CSV row data
     * @param array $allowed_fields Allowed fields to update
     * @return array Update data
     */
    private static function build_update_data($row, $allowed_fields) {
        $update_data = array();

        foreach ($allowed_fields as $field) {
            if (isset($row[$field]) && $row[$field] !== '') {
                $update_data[$field] = $row[$field];
            }
        }

        // Handle ACF fields
        foreach ($row as $key => $value) {
            if (strpos($key, 'acf_') === 0 && $value !== '') {
                $field_name = str_replace('acf_', '', $key);
                $update_data['acf'][$field_name] = $value;
            }
        }

        // Handle taxonomies
        foreach ($row as $key => $value) {
            if (strpos($key, 'taxonomy_') === 0 && $value !== '') {
                $taxonomy = str_replace('taxonomy_', '', $key);
                $terms = array_map('trim', explode(',', $value));
                $update_data['taxonomies'][$taxonomy] = $terms;
            }
        }

        return $update_data;
    }

    /**
     * Detect changes between current post and update data
     *
     * @param int $post_id Post ID
     * @param array $update_data Update data
     * @return array Changes
     */
    private static function detect_changes($post_id, $update_data) {
        $post = get_post($post_id);
        $changes = array();

        // Check core fields
        foreach ($update_data as $key => $new_value) {
            if (in_array($key, array('acf', 'taxonomies'))) {
                continue;
            }

            $old_value = isset($post->$key) ? $post->$key : '';

            if ($old_value != $new_value) {
                $changes[$key] = array(
                    'old' => $old_value,
                    'new' => $new_value,
                );
            }
        }

        // Check ACF fields
        if (isset($update_data['acf']) && function_exists('get_field')) {
            foreach ($update_data['acf'] as $field_name => $new_value) {
                $old_value = get_field($field_name, $post_id);
                if ($old_value != $new_value) {
                    $changes["acf_{$field_name}"] = array(
                        'old' => $old_value,
                        'new' => $new_value,
                    );
                }
            }
        }

        return $changes;
    }

    /**
     * Apply updates to post
     *
     * @param int $post_id Post ID
     * @param array $update_data Update data
     * @return int|WP_Error Post ID or error
     */
    private static function apply_updates($post_id, $update_data) {
        // Update core fields
        $post_update = array('ID' => $post_id);

        foreach ($update_data as $key => $value) {
            if (!in_array($key, array('acf', 'taxonomies', 'featured_image'))) {
                $post_update[$key] = $value;
            }
        }

        if (count($post_update) > 1) {
            $result = wp_update_post($post_update, true);
            if (is_wp_error($result)) {
                return $result;
            }
        }

        // Update ACF fields
        if (isset($update_data['acf']) && function_exists('update_field')) {
            foreach ($update_data['acf'] as $field_name => $value) {
                update_field($field_name, $value, $post_id);
            }
        }

        // Update taxonomies
        if (isset($update_data['taxonomies'])) {
            foreach ($update_data['taxonomies'] as $taxonomy => $terms) {
                wp_set_object_terms($post_id, $terms, $taxonomy);
            }
        }

        // Update featured image
        if (isset($update_data['featured_image'])) {
            $image_url = $update_data['featured_image'];
            // TODO: Handle featured image URL to attachment ID conversion
        }

        return $post_id;
    }

    /**
     * Check if Google Sheets API is configured
     *
     * @return bool True if configured
     */
    public static function is_configured() {
        // Check for API credentials
        $credentials = get_option('ria_dm_google_sheets_credentials');
        return !empty($credentials);
    }

    /**
     * Get configuration status
     *
     * @return array Status information
     */
    public static function get_config_status() {
        return array(
            'configured' => self::is_configured(),
            'api_enabled' => false, // TODO: Check if API is accessible
            'credentials_type' => 'none', // TODO: service_account or oauth
        );
    }
}
