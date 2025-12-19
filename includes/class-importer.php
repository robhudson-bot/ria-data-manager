<?php
/**
 * Importer Class
 * Handles importing WordPress content from CSV
 */

if (!defined('ABSPATH')) {
    exit;
}

class RIA_DM_Importer {

    /**
     * Log file path
     */
    private static $log_file = null;

    /**
     * Get log file path
     */
    private static function get_log_file() {
        if (self::$log_file === null) {
            $upload_dir = wp_upload_dir();
            $log_dir = $upload_dir['basedir'] . '/ria-data-manager/logs/';
            if (!file_exists($log_dir)) {
                wp_mkdir_p($log_dir);
            }
            self::$log_file = $log_dir . 'import_' . date('Y-m-d_His') . '.log';
        }
        return self::$log_file;
    }

    /**
     * Write to import log
     */
    private static function log($message) {
        $log_file = self::get_log_file();
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
    }

    /**
     * Import posts from CSV
     *
     * @param string $file_path Path to CSV file
     * @param array $args Import arguments
     * @return array|WP_Error Import results or error
     */
    public static function import($file_path, $args = array()) {
        // Reset log file for new import
        self::$log_file = null;

        self::log('=== IMPORT STARTED ===');
        self::log('File: ' . basename($file_path));

        $defaults = array(
            'update_existing' => true,
            'create_taxonomies' => true,
            'skip_on_error' => true,
            'batch_size' => 100,
            'field_mapping' => array(), // Custom field mapping
            'default_post_status' => 'draft',
            'default_post_author' => get_current_user_id(),
            'default_post_type' => '', // Override post_type for all rows
        );

        $args = wp_parse_args($args, $defaults);
        self::log('Options: update_existing=' . ($args['update_existing'] ? 'yes' : 'no') .
                  ', create_taxonomies=' . ($args['create_taxonomies'] ? 'yes' : 'no') .
                  ', default_post_type=' . ($args['default_post_type'] ?: 'from CSV'));

        // Read CSV file
        $csv_data = RIA_DM_CSV_Processor::read_csv($file_path);

        if (is_wp_error($csv_data)) {
            self::log('ERROR: Failed to read CSV - ' . $csv_data->get_error_message());
            return $csv_data;
        }

        self::log('CSV loaded: ' . count($csv_data['data']) . ' rows, ' . count($csv_data['headers']) . ' columns');

        // Validate CSV structure
        $validation = RIA_DM_CSV_Processor::validate_csv_structure($csv_data['headers']);
        if (is_wp_error($validation)) {
            self::log('ERROR: CSV validation failed - ' . $validation->get_error_message());
            return $validation;
        }

        // Apply field mapping
        if (!empty($args['field_mapping'])) {
            $csv_data['data'] = self::apply_field_mapping($csv_data['data'], $args['field_mapping']);
        }

        // Process imports
        $results = array(
            'success' => 0,
            'updated' => 0,
            'created' => 0,
            'failed' => 0,
            'errors' => array(),
            'log_file' => basename(self::get_log_file()),
        );

        self::log('--- Processing rows ---');

        foreach ($csv_data['data'] as $index => $row) {
            $row_num = $index + 2; // +2 for header row and 0-index
            $post_id = isset($row['ID']) ? $row['ID'] : 'new';
            $post_title = isset($row['post_title']) ? substr($row['post_title'], 0, 50) : 'untitled';

            try {
                $result = self::import_row($row, $args);

                if (is_wp_error($result)) {
                    $results['failed']++;
                    $error_msg = $result->get_error_message();
                    $results['errors'][] = array(
                        'row' => $row_num,
                        'post_id' => $post_id,
                        'title' => $post_title,
                        'message' => $error_msg,
                    );
                    self::log("Row $row_num FAILED (ID:$post_id \"$post_title\"): $error_msg");

                    if (!$args['skip_on_error']) {
                        self::log('Stopping import due to error (skip_on_error=false)');
                        break;
                    }
                } else {
                    $results['success']++;

                    if ($result['action'] === 'updated') {
                        $results['updated']++;
                        self::log("Row $row_num UPDATED post ID {$result['post_id']} \"$post_title\"");
                    } else {
                        $results['created']++;
                        self::log("Row $row_num CREATED post ID {$result['post_id']} \"$post_title\"");
                    }
                }
            } catch (Exception $e) {
                $results['failed']++;
                $error_msg = 'Exception: ' . $e->getMessage();
                $results['errors'][] = array(
                    'row' => $row_num,
                    'post_id' => $post_id,
                    'title' => $post_title,
                    'message' => $error_msg,
                );
                self::log("Row $row_num EXCEPTION (ID:$post_id): $error_msg");
            }
        }

        self::log('--- Import complete ---');
        self::log("Results: {$results['success']} success ({$results['updated']} updated, {$results['created']} created), {$results['failed']} failed");
        self::log('=== IMPORT FINISHED ===');

        return $results;
    }
    
    /**
     * Import a single row
     *
     * @param array $row CSV row data
     * @param array $args Import arguments
     * @return array|WP_Error Result or error
     */
    private static function import_row($row, $args) {
        // Validate required fields
        if (empty($row['post_title']) && empty($row['ID'])) {
            return new WP_Error('missing_data', 'Row missing required post_title or ID');
        }
        
        // Check if updating existing post
        $post_id = null;
        $action = 'created';
        
        if (!empty($row['ID']) && $args['update_existing']) {
            $existing_post = get_post($row['ID']);
            if ($existing_post) {
                $post_id = $row['ID'];
                $action = 'updated';
            }
        }
        
        // Prepare post data
        $post_data = self::prepare_post_data($row, $args);
        
        if (is_wp_error($post_data)) {
            return $post_data;
        }
        
        // Insert or update post
        if ($post_id) {
            $post_data['ID'] = $post_id;
            $result = wp_update_post($post_data, true);
        } else {
            $result = wp_insert_post($post_data, true);
        }
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        $post_id = $result;
        
        // Import featured image
        if (!empty($row['featured_image'])) {
            self::import_featured_image($post_id, $row['featured_image']);
        }
        
        // Import taxonomies
        $taxonomy_fields = self::extract_taxonomy_fields($row);
        if (!empty($taxonomy_fields)) {
            RIA_DM_Taxonomy_Handler::import_terms(
                $post_id,
                $taxonomy_fields,
                $args['create_taxonomies']
            );
        }
        
        // Import ACF fields
        $acf_fields = self::extract_acf_fields($row);
        if (!empty($acf_fields) && RIA_DM_ACF_Handler::is_acf_active()) {
            RIA_DM_ACF_Handler::import_fields($post_id, $acf_fields);
        }
        
        // Import custom meta
        $meta_fields = self::extract_meta_fields($row);
        if (!empty($meta_fields)) {
            self::import_meta_fields($post_id, $meta_fields);
        }
        
        return array(
            'post_id' => $post_id,
            'action' => $action,
        );
    }
    
    /**
     * Prepare WordPress post data from CSV row
     *
     * @param array $row CSV row
     * @param array $args Import arguments
     * @return array|WP_Error Post data or error
     */
    private static function prepare_post_data($row, $args) {
        $post_data = array();
        
        // Post title (required)
        $post_data['post_title'] = !empty($row['post_title']) 
            ? $row['post_title'] 
            : 'Untitled';
        
        // Post content
        if (!empty($row['post_content'])) {
            $post_data['post_content'] = $row['post_content'];
        }
        
        // Post excerpt
        if (!empty($row['post_excerpt'])) {
            $post_data['post_excerpt'] = $row['post_excerpt'];
        }
        
        // Post status
        $post_data['post_status'] = !empty($row['post_status']) 
            ? $row['post_status'] 
            : $args['default_post_status'];
        
        // Post type - use default_post_type if set, then CSV column, then fallback to 'post'
        if (!empty($args['default_post_type'])) {
            $post_data['post_type'] = $args['default_post_type'];
        } elseif (!empty($row['post_type'])) {
            $post_data['post_type'] = $row['post_type'];
        } else {
            return new WP_Error('missing_post_type', 'Post type is required. Either include a post_type column in your CSV or select a Default Post Type.');
        }

        // Validate post type exists
        if (!post_type_exists($post_data['post_type'])) {
            return new WP_Error('invalid_post_type', 'Post type does not exist: ' . $post_data['post_type']);
        }
        
        // Post date
        if (!empty($row['post_date'])) {
            $timestamp = strtotime($row['post_date']);
            if ($timestamp) {
                $post_data['post_date'] = date('Y-m-d H:i:s', $timestamp);
            }
        }
        
        // Post author
        if (!empty($row['post_author'])) {
            $user = get_user_by('login', $row['post_author']);
            if (!$user) {
                $user = get_user_by('email', $row['post_author']);
            }
            $post_data['post_author'] = $user ? $user->ID : $args['default_post_author'];
        } else {
            $post_data['post_author'] = $args['default_post_author'];
        }
        
        // Post slug
        if (!empty($row['post_name'])) {
            $post_data['post_name'] = sanitize_title($row['post_name']);
        }
        
        // Post parent
        if (!empty($row['post_parent'])) {
            $post_data['post_parent'] = intval($row['post_parent']);
        }
        
        // Menu order
        if (!empty($row['menu_order'])) {
            $post_data['menu_order'] = intval($row['menu_order']);
        }
        
        return $post_data;
    }
    
    /**
     * Extract taxonomy fields from CSV row
     *
     * @param array $row CSV row
     * @return array Taxonomy fields
     */
    private static function extract_taxonomy_fields($row) {
        $taxonomy_fields = array();
        
        foreach ($row as $key => $value) {
            if (strpos($key, 'tax_') === 0 && !empty($value)) {
                $taxonomy_fields[$key] = $value;
            }
        }
        
        return $taxonomy_fields;
    }
    
    /**
     * Extract ACF fields from CSV row
     *
     * @param array $row CSV row
     * @return array ACF fields
     */
    private static function extract_acf_fields($row) {
        $acf_fields = array();
        
        foreach ($row as $key => $value) {
            if (strpos($key, 'acf_') === 0 && !empty($value)) {
                $acf_fields[$key] = $value;
            }
        }
        
        return $acf_fields;
    }
    
    /**
     * Extract meta fields from CSV row
     *
     * @param array $row CSV row
     * @return array Meta fields
     */
    private static function extract_meta_fields($row) {
        $meta_fields = array();
        
        foreach ($row as $key => $value) {
            if (strpos($key, 'meta_') === 0 && !empty($value)) {
                $meta_fields[$key] = $value;
            }
        }
        
        return $meta_fields;
    }
    
    /**
     * Import featured image from URL or attachment ID
     *
     * @param int $post_id Post ID
     * @param string $image_data Image URL or ID
     * @return bool Success status
     */
    private static function import_featured_image($post_id, $image_data) {
        // Check if it's an ID
        if (is_numeric($image_data)) {
            $attachment_id = intval($image_data);
            if (get_post_type($attachment_id) === 'attachment') {
                return set_post_thumbnail($post_id, $attachment_id);
            }
        }
        
        // Try to get attachment ID from URL
        if (filter_var($image_data, FILTER_VALIDATE_URL)) {
            $attachment_id = attachment_url_to_postid($image_data);
            
            if ($attachment_id) {
                return set_post_thumbnail($post_id, $attachment_id);
            }
            
            // Download and attach image
            return self::download_and_attach_image($post_id, $image_data);
        }
        
        return false;
    }
    
    /**
     * Download image from URL and attach to post
     *
     * @param int $post_id Post ID
     * @param string $image_url Image URL
     * @return bool Success status
     */
    private static function download_and_attach_image($post_id, $image_url) {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        $attachment_id = media_sideload_image($image_url, $post_id, null, 'id');
        
        if (is_wp_error($attachment_id)) {
            return false;
        }
        
        return set_post_thumbnail($post_id, $attachment_id);
    }
    
    /**
     * Import custom meta fields
     *
     * @param int $post_id Post ID
     * @param array $meta_fields Meta fields
     */
    private static function import_meta_fields($post_id, $meta_fields) {
        foreach ($meta_fields as $key => $value) {
            // Remove meta_ prefix
            $meta_key = substr($key, 5);
            update_post_meta($post_id, $meta_key, $value);
        }
    }
    
    /**
     * Apply field mapping to data
     *
     * @param array $data CSV data rows
     * @param array $mapping Field mapping
     * @return array Mapped data
     */
    private static function apply_field_mapping($data, $mapping) {
        $mapped_data = array();
        
        foreach ($data as $row) {
            $mapped_row = array();
            
            foreach ($row as $csv_field => $value) {
                $wp_field = isset($mapping[$csv_field]) ? $mapping[$csv_field] : $csv_field;
                $mapped_row[$wp_field] = $value;
            }
            
            $mapped_data[] = $mapped_row;
        }
        
        return $mapped_data;
    }
    
    /**
     * Batch import with progress tracking
     *
     * @param string $file_path CSV file path
     * @param array $args Import arguments
     * @param callable $progress_callback Progress callback function
     * @return array Import results
     */
    public static function batch_import($file_path, $args, $progress_callback = null) {
        $csv_data = RIA_DM_CSV_Processor::read_csv($file_path);
        
        if (is_wp_error($csv_data)) {
            return $csv_data;
        }
        
        $batch_size = isset($args['batch_size']) ? $args['batch_size'] : 100;
        $total_rows = count($csv_data['data']);
        $batches = array_chunk($csv_data['data'], $batch_size);
        
        $results = array(
            'success' => 0,
            'updated' => 0,
            'created' => 0,
            'failed' => 0,
            'errors' => array(),
        );
        
        foreach ($batches as $batch_index => $batch) {
            foreach ($batch as $row_index => $row) {
                $result = self::import_row($row, $args);
                
                if (is_wp_error($result)) {
                    $results['failed']++;
                    $results['errors'][] = $result->get_error_message();
                } else {
                    $results['success']++;
                    if ($result['action'] === 'updated') {
                        $results['updated']++;
                    } else {
                        $results['created']++;
                    }
                }
            }
            
            // Progress callback
            if (is_callable($progress_callback)) {
                $progress = (($batch_index + 1) / count($batches)) * 100;
                call_user_func($progress_callback, $progress, $results);
            }
            
            // Clear object cache to prevent memory issues
            wp_cache_flush();
        }
        
        return $results;
    }
}
