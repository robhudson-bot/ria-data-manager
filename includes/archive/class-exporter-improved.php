<?php
/**
 * Improved Exporter Class
 * Fixes issues with Page content export and adds REST API option
 */

if (!defined('ABSPATH')) {
    exit;
}

class RIA_DM_Exporter_Improved {
    
    /**
     * Export posts to CSV with improved content handling
     *
     * @param array $args Export arguments
     * @return string|WP_Error File path or error
     */
    public static function export($args = array()) {
        $defaults = array(
            'post_type' => 'post',
            'post_status' => array('publish', 'draft'),
            'posts_per_page' => -1,
            'include_acf' => true,
            'include_taxonomies' => true,
            'include_featured_image' => true,
            'include_meta' => false,
            'date_from' => '',
            'date_to' => '',
            'meta_keys' => array(),
            'use_rest_api' => false, // New option to use REST API
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Use REST API export if requested
        if ($args['use_rest_api']) {
            return self::export_via_rest_api($args);
        }
        
        // Build query args
        $query_args = array(
            'post_type' => $args['post_type'],
            'post_status' => $args['post_status'],
            'posts_per_page' => $args['posts_per_page'],
            'orderby' => 'ID',
            'order' => 'ASC', // Use ascending order for consistency
        );
        
        // Add date filtering if specified
        if (!empty($args['date_from']) || !empty($args['date_to'])) {
            $query_args['date_query'] = array();
            
            if (!empty($args['date_from'])) {
                $query_args['date_query']['after'] = $args['date_from'];
            }
            
            if (!empty($args['date_to'])) {
                $query_args['date_query']['before'] = $args['date_to'];
            }
        }
        
        // Get posts
        $query = new WP_Query($query_args);
        
        if (!$query->have_posts()) {
            return new WP_Error('no_posts', 'No posts found matching criteria');
        }
        
        // Build headers
        $headers = self::build_headers($args);
        
        // Build data rows
        $data = array();
        
        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();
            
            $row = self::build_row_improved($post_id, $args, $headers);
            $data[] = $row;
        }
        
        wp_reset_postdata();
        
        // Generate filename
        $filename = self::generate_filename($args['post_type']);
        
        // Write CSV with improved method
        $file_path = self::write_csv_improved($filename, $headers, $data);
        
        if (is_wp_error($file_path)) {
            return $file_path;
        }
        
        // Clean old files
        QRY_CSV_Processor::clean_old_files(1);
        
        return $file_path;
    }
    
    /**
     * Export via REST API (more reliable for complex content)
     *
     * @param array $args Export arguments
     * @return string|WP_Error File path or error
     */
    private static function export_via_rest_api($args) {
        // Get REST base for post type
        $rest_base = self::get_rest_base($args['post_type']);
        
        // Build REST URL
        $rest_url = rest_url('wp/v2/' . $rest_base);
        
        // Fetch all posts via REST
        $all_posts = array();
        $page = 1;
        $per_page = 100;
        
        do {
            $params = array(
                'page' => $page,
                'per_page' => $per_page,
                'status' => implode(',', (array)$args['post_status']),
                '_embed' => true,
            );
            
            $request_url = add_query_arg($params, $rest_url);
            
            $response = wp_remote_get($request_url, array(
                'timeout' => 30,
            ));

            if (is_wp_error($response)) {
                return $response;
            }

            $body = wp_remote_retrieve_body($response);

            // Check if response is HTML instead of JSON
            if (preg_match('/^\s*</', $body)) {
                $preview = substr(strip_tags($body), 0, 300);
                return new WP_Error(
                    'rest_api_html_response',
                    sprintf('REST API returned HTML instead of JSON (likely a PHP error or plugin conflict). Response preview: %s', $preview)
                );
            }

            $posts = json_decode($body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $preview = substr($body, 0, 300);
                return new WP_Error(
                    'rest_api_json_error',
                    sprintf('Failed to parse REST API response. Error: %s. Response: %s', json_last_error_msg(), $preview)
                );
            }

            if (empty($posts)) {
                break;
            }
            
            $all_posts = array_merge($all_posts, $posts);
            
            // Check if there are more pages
            $headers = wp_remote_retrieve_headers($response);
            $total_pages = isset($headers['X-WP-TotalPages']) ? intval($headers['X-WP-TotalPages']) : 1;
            
            $page++;
            
        } while ($page <= $total_pages);
        
        if (empty($all_posts)) {
            return new WP_Error('no_posts', 'No posts found via REST API');
        }
        
        // Build headers
        $headers = self::build_headers($args);
        
        // Build data rows from REST data
        $data = array();
        foreach ($all_posts as $post_data) {
            $row = self::build_row_from_rest($post_data, $args, $headers);
            $data[] = $row;
        }
        
        // Generate filename
        $filename = 'rest-' . self::generate_filename($args['post_type']);
        
        // Write CSV
        $file_path = self::write_csv_improved($filename, $headers, $data);
        
        if (is_wp_error($file_path)) {
            return $file_path;
        }
        
        return $file_path;
    }
    
    /**
     * Build improved row with better content sanitization
     *
     * @param int $post_id Post ID
     * @param array $args Export arguments
     * @param array $headers CSV headers
     * @return array Row data (indexed array in exact header order)
     */
    private static function build_row_improved($post_id, $args, $headers) {
        $post = get_post($post_id);
        
        // Build row as indexed array in exact header order
        $row = array();
        
        foreach ($headers as $header) {
            $value = '';
            
            // Standard WordPress fields
            switch ($header) {
                case 'ID':
                    $value = $post->ID;
                    break;
                    
                case 'post_title':
                    $value = $post->post_title;
                    break;
                    
                case 'post_content':
                    // Clean content for CSV - remove excessive whitespace but preserve structure
                    $content = $post->post_content;
                    // Normalize line breaks
                    $content = str_replace(array("\r\n", "\r"), "\n", $content);
                    // Remove excessive blank lines (more than 2 in a row)
                    $content = preg_replace("/\n{3,}/", "\n\n", $content);
                    $value = $content;
                    break;
                    
                case 'post_excerpt':
                    $value = $post->post_excerpt;
                    break;
                    
                case 'post_status':
                    $value = $post->post_status;
                    break;
                    
                case 'post_type':
                    $value = $post->post_type;
                    break;
                    
                case 'post_date':
                    $value = $post->post_date;
                    break;
                    
                case 'post_modified':
                    $value = $post->post_modified;
                    break;
                    
                case 'post_author':
                    $author = get_userdata($post->post_author);
                    $value = $author ? $author->user_login : '';
                    break;
                    
                case 'post_name':
                    $value = $post->post_name;
                    break;
                    
                case 'post_parent':
                    $value = $post->post_parent;
                    break;
                    
                case 'menu_order':
                    $value = $post->menu_order;
                    break;
                    
                case 'featured_image':
                    if ($args['include_featured_image']) {
                        $thumbnail_id = get_post_thumbnail_id($post_id);
                        if ($thumbnail_id) {
                            $value = wp_get_attachment_url($thumbnail_id);
                        }
                    }
                    break;
                    
                default:
                    // Check if it's a taxonomy field
                    if (strpos($header, 'taxonomy_') === 0 && $args['include_taxonomies']) {
                        $taxonomy = str_replace('taxonomy_', '', $header);
                        $terms = wp_get_post_terms($post_id, $taxonomy, array('fields' => 'names'));
                        if (!is_wp_error($terms)) {
                            $value = implode(',', $terms);
                        }
                    }
                    // Check if it's an ACF field
                    elseif (strpos($header, 'acf_') === 0 && $args['include_acf']) {
                        $field_name = str_replace('acf_', '', $header);
                        $field_value = get_field($field_name, $post_id);
                        $value = self::format_acf_value($field_value);
                    }
                    // Check if it's a meta field
                    elseif (strpos($header, 'meta_') === 0 && $args['include_meta']) {
                        $meta_key = str_replace('meta_', '', $header);
                        $value = get_post_meta($post_id, $meta_key, true);
                    }
                    break;
            }
            
            // Sanitize the value for CSV
            $row[] = self::sanitize_for_csv($value);
        }
        
        return $row;
    }
    
    /**
     * Build row from REST API data
     *
     * @param array $post_data Post data from REST API
     * @param array $args Export arguments
     * @param array $headers CSV headers
     * @return array Row data
     */
    private static function build_row_from_rest($post_data, $args, $headers) {
        $post_id = $post_data['id'];
        $row = array();
        
        foreach ($headers as $header) {
            $value = '';
            
            switch ($header) {
                case 'ID':
                    $value = $post_id;
                    break;
                    
                case 'post_title':
                    $value = isset($post_data['title']['rendered']) ? 
                        wp_strip_all_tags($post_data['title']['rendered']) : '';
                    break;
                    
                case 'post_content':
                    // Get raw content, not rendered
                    $value = isset($post_data['content']['raw']) ? 
                        $post_data['content']['raw'] : 
                        (isset($post_data['content']['rendered']) ? $post_data['content']['rendered'] : '');
                    break;
                    
                case 'post_excerpt':
                    $value = isset($post_data['excerpt']['raw']) ? 
                        $post_data['excerpt']['raw'] : 
                        (isset($post_data['excerpt']['rendered']) ? wp_strip_all_tags($post_data['excerpt']['rendered']) : '');
                    break;
                    
                case 'post_status':
                    $value = isset($post_data['status']) ? $post_data['status'] : '';
                    break;
                    
                case 'post_type':
                    $value = isset($post_data['type']) ? $post_data['type'] : '';
                    break;
                    
                case 'post_date':
                    $value = isset($post_data['date']) ? $post_data['date'] : '';
                    break;
                    
                case 'post_modified':
                    $value = isset($post_data['modified']) ? $post_data['modified'] : '';
                    break;
                    
                case 'post_author':
                    if (isset($post_data['_embedded']['author'][0]['slug'])) {
                        $value = $post_data['_embedded']['author'][0]['slug'];
                    } elseif (isset($post_data['author'])) {
                        $author = get_userdata($post_data['author']);
                        $value = $author ? $author->user_login : '';
                    }
                    break;
                    
                case 'post_name':
                    $value = isset($post_data['slug']) ? $post_data['slug'] : '';
                    break;
                    
                case 'post_parent':
                    $value = isset($post_data['parent']) ? $post_data['parent'] : 0;
                    break;
                    
                case 'menu_order':
                    $value = isset($post_data['menu_order']) ? $post_data['menu_order'] : 0;
                    break;
                    
                case 'featured_image':
                    if ($args['include_featured_image'] && isset($post_data['_embedded']['wp:featuredmedia'][0]['source_url'])) {
                        $value = $post_data['_embedded']['wp:featuredmedia'][0]['source_url'];
                    }
                    break;
                    
                default:
                    // Handle taxonomies, ACF, and meta fields
                    if (strpos($header, 'taxonomy_') === 0) {
                        $taxonomy = str_replace('taxonomy_', '', $header);
                        $terms = wp_get_post_terms($post_id, $taxonomy, array('fields' => 'names'));
                        if (!is_wp_error($terms)) {
                            $value = implode(',', $terms);
                        }
                    } elseif (strpos($header, 'acf_') === 0) {
                        $field_name = str_replace('acf_', '', $header);
                        $field_value = get_field($field_name, $post_id);
                        $value = self::format_acf_value($field_value);
                    }
                    break;
            }
            
            $row[] = self::sanitize_for_csv($value);
        }
        
        return $row;
    }
    
    /**
     * Improved CSV sanitization
     *
     * @param mixed $value Value to sanitize
     * @return string Sanitized value
     */
    private static function sanitize_for_csv($value) {
        if (is_array($value)) {
            $value = json_encode($value);
        } elseif (is_object($value)) {
            $value = json_encode($value);
        }
        
        // Convert to string
        $value = (string) $value;
        
        // Remove null bytes and other problematic characters
        $value = str_replace(array("\0", "\x0B"), '', $value);
        
        // Normalize line endings to \n
        $value = str_replace(array("\r\n", "\r"), "\n", $value);
        
        return $value;
    }
    
    /**
     * Format ACF value for export
     *
     * @param mixed $value ACF field value
     * @return string Formatted value
     */
    private static function format_acf_value($value) {
        if (is_array($value)) {
            // For arrays, check if it's a simple list or complex structure
            if (self::is_simple_array($value)) {
                return implode('|', $value);
            } else {
                return json_encode($value);
            }
        } elseif (is_object($value)) {
            // Convert objects to array first
            if (isset($value->ID)) {
                return $value->ID; // For post objects
            }
            return json_encode($value);
        }
        
        return (string) $value;
    }
    
    /**
     * Check if array is a simple list
     *
     * @param array $array Array to check
     * @return bool True if simple list
     */
    private static function is_simple_array($array) {
        if (!is_array($array)) {
            return false;
        }
        
        // Check if all values are scalars
        foreach ($array as $value) {
            if (is_array($value) || is_object($value)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Write CSV with improved handling
     *
     * @param string $filename Filename
     * @param array $headers Headers
     * @param array $data Data rows
     * @return string|WP_Error File path or error
     */
    private static function write_csv_improved($filename, $headers, $data) {
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . '/quarry/' . sanitize_file_name($filename);
        
        // Ensure directory exists
        $dir = dirname($file_path);
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }
        
        // Open file for writing
        $handle = fopen($file_path, 'w');
        if (!$handle) {
            return new WP_Error('file_error', 'Could not create CSV file');
        }
        
        // Write BOM for UTF-8 to ensure proper encoding in Excel
        fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Write headers
        if (fputcsv($handle, $headers) === false) {
            fclose($handle);
            return new WP_Error('write_error', 'Failed to write CSV headers');
        }
        
        // Write data rows
        foreach ($data as $row) {
            if (fputcsv($handle, $row) === false) {
                fclose($handle);
                return new WP_Error('write_error', 'Failed to write CSV row');
            }
        }
        
        fclose($handle);
        
        // Verify file was created and has content
        if (!file_exists($file_path) || filesize($file_path) === 0) {
            return new WP_Error('file_error', 'CSV file was not created properly');
        }
        
        return $file_path;
    }
    
    /**
     * Build CSV headers
     *
     * @param array $args Export arguments
     * @return array Headers
     */
    private static function build_headers($args) {
        // Standard WordPress fields
        $headers = array(
            'ID',
            'post_title',
            'post_content',
            'post_excerpt',
            'post_status',
            'post_type',
            'post_date',
            'post_modified',
            'post_author',
            'post_name',
            'post_parent',
            'menu_order',
        );
        
        // Featured image
        if ($args['include_featured_image']) {
            $headers[] = 'featured_image';
        }
        
        // Taxonomies
        if ($args['include_taxonomies']) {
            $taxonomies = get_object_taxonomies($args['post_type'], 'names');
            foreach ($taxonomies as $taxonomy) {
                $headers[] = 'taxonomy_' . $taxonomy;
            }
        }
        
        // ACF fields
        if ($args['include_acf'] && class_exists('ACF')) {
            $acf_fields = QRY_ACF_Handler::get_fields_for_post_type($args['post_type']);
            foreach ($acf_fields as $field_name => $field_data) {
                $headers[] = 'acf_' . $field_name;
            }
        }
        
        // Custom meta fields
        if ($args['include_meta'] && !empty($args['meta_keys'])) {
            foreach ($args['meta_keys'] as $meta_key) {
                $headers[] = 'meta_' . $meta_key;
            }
        }
        
        return $headers;
    }
    
    /**
     * Generate filename for export
     *
     * @param string $post_type Post type
     * @return string Filename
     */
    private static function generate_filename($post_type) {
        $timestamp = date('Y-m-d_His');
        return sprintf('export_%s_%s.csv', sanitize_title($post_type), $timestamp);
    }
    
    /**
     * Get REST base for post type
     *
     * @param string $post_type Post type
     * @return string REST base
     */
    private static function get_rest_base($post_type) {
        $post_type_obj = get_post_type_object($post_type);
        
        if ($post_type_obj && isset($post_type_obj->rest_base)) {
            return $post_type_obj->rest_base;
        }
        
        // Default mappings
        $defaults = array(
            'post' => 'posts',
            'page' => 'pages',
            'attachment' => 'media',
        );
        
        return isset($defaults[$post_type]) ? $defaults[$post_type] : $post_type;
    }
}
