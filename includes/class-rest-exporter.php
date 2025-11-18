<?php
/**
 * REST API Exporter Class
 * Alternative export method using WordPress REST API
 */

if (!defined('ABSPATH')) {
    exit;
}

class RIA_DM_REST_Exporter {
    
    /**
     * Export posts using REST API
     *
     * @param array $args Export arguments
     * @return string|WP_Error File path or error
     */
    public static function export_via_rest($args = array()) {
        $defaults = array(
            'post_type' => 'post',
            'post_status' => 'publish,draft',
            'include_acf' => true,
            'include_taxonomies' => true,
            'include_featured_image' => true,
            'per_page' => 100,
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Build REST API endpoint URL
        $rest_url = rest_url('wp/v2/' . self::get_rest_base($args['post_type']));
        
        // Build query parameters
        $params = array(
            'status' => $args['post_status'],
            'per_page' => $args['per_page'],
            '_embed' => 1, // Embed related data
            '_fields' => 'id,date,modified,slug,status,type,title,content,excerpt,author,featured_media,template,meta,parent,menu_order',
        );
        
        // Add taxonomy embedding if needed
        if ($args['include_taxonomies']) {
            $taxonomies = get_object_taxonomies($args['post_type'], 'names');
            foreach ($taxonomies as $taxonomy) {
                $params[$taxonomy] = 'all';
            }
        }
        
        // Fetch all posts via REST API
        $all_posts = array();
        $page = 1;
        $total_pages = 1;
        
        do {
            $params['page'] = $page;
            $response = self::fetch_rest_data($rest_url, $params);
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            $all_posts = array_merge($all_posts, $response['data']);
            $total_pages = $response['total_pages'];
            $page++;
            
        } while ($page <= $total_pages);
        
        if (empty($all_posts)) {
            return new WP_Error('no_posts', 'No posts found matching criteria');
        }
        
        // Build headers
        $headers = self::build_rest_headers($args);
        
        // Build data rows
        $data = array();
        foreach ($all_posts as $post) {
            $row = self::build_rest_row($post, $args, $headers);
            $data[] = $row;
        }
        
        // Generate filename
        $filename = sprintf('rest-export_%s_%s.csv', 
            sanitize_title($args['post_type']), 
            date('Y-m-d_His')
        );
        
        // Write CSV
        $file_path = RIA_DM_CSV_Processor::write_csv($filename, $headers, $data);
        
        if (is_wp_error($file_path)) {
            return $file_path;
        }
        
        // Clean old files
        RIA_DM_CSV_Processor::clean_old_files(1);
        
        return $file_path;
    }
    
    /**
     * Fetch data from REST API
     *
     * @param string $url REST endpoint URL
     * @param array $params Query parameters
     * @return array|WP_Error Response data or error
     */
    private static function fetch_rest_data($url, $params = array()) {
        $request_url = add_query_arg($params, $url);
        
        $response = wp_remote_get($request_url, array(
            'timeout' => 30,
            'headers' => array(
                'X-WP-Nonce' => wp_create_nonce('wp_rest'),
            ),
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            return new WP_Error('rest_error', 'REST API request failed with status ' . $status_code);
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_error', 'Failed to parse REST API response');
        }
        
        // Get pagination info from headers
        $headers = wp_remote_retrieve_headers($response);
        $total_pages = isset($headers['X-WP-TotalPages']) ? intval($headers['X-WP-TotalPages']) : 1;
        
        return array(
            'data' => $data,
            'total_pages' => $total_pages,
        );
    }
    
    /**
     * Build CSV headers for REST export
     *
     * @param array $args Export arguments
     * @return array Headers
     */
    private static function build_rest_headers($args) {
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
        
        if ($args['include_featured_image']) {
            $headers[] = 'featured_image';
        }
        
        if ($args['include_taxonomies']) {
            $taxonomy_headers = RIA_DM_Taxonomy_Handler::get_taxonomy_headers($args['post_type']);
            $headers = array_merge($headers, $taxonomy_headers);
        }
        
        if ($args['include_acf'] && RIA_DM_ACF_Handler::is_acf_active()) {
            $acf_headers = RIA_DM_ACF_Handler::get_field_headers($args['post_type']);
            $headers = array_merge($headers, $acf_headers);
        }
        
        return $headers;
    }
    
    /**
     * Build data row from REST API response
     *
     * @param array $post Post data from REST API
     * @param array $args Export arguments
     * @param array $headers CSV headers
     * @return array Row data
     */
    private static function build_rest_row($post, $args, $headers) {
        // Initialize row with empty values
        $row = array_fill_keys($headers, '');
        
        // Extract post ID
        $post_id = $post['id'];
        
        // Standard WordPress fields from REST response
        $row['ID'] = $post_id;
        $row['post_title'] = isset($post['title']['rendered']) ? 
            RIA_DM_CSV_Processor::sanitize_csv_value($post['title']['rendered']) : '';
        $row['post_content'] = isset($post['content']['rendered']) ? 
            RIA_DM_CSV_Processor::sanitize_csv_value($post['content']['rendered']) : '';
        $row['post_excerpt'] = isset($post['excerpt']['rendered']) ? 
            RIA_DM_CSV_Processor::sanitize_csv_value($post['excerpt']['rendered']) : '';
        $row['post_status'] = isset($post['status']) ? $post['status'] : '';
        $row['post_type'] = isset($post['type']) ? $post['type'] : '';
        $row['post_date'] = isset($post['date']) ? $post['date'] : '';
        $row['post_modified'] = isset($post['modified']) ? $post['modified'] : '';
        $row['post_name'] = isset($post['slug']) ? $post['slug'] : '';
        
        // Get author username from REST API
        if (isset($post['_embedded']['author'][0]['slug'])) {
            $row['post_author'] = $post['_embedded']['author'][0]['slug'];
        } elseif (isset($post['author'])) {
            $author = get_userdata($post['author']);
            $row['post_author'] = $author ? $author->user_login : '';
        }
        
        // Parent post
        if (isset($post['parent'])) {
            $row['post_parent'] = $post['parent'];
        }
        
        // Menu order (for pages)
        if (isset($post['menu_order'])) {
            $row['menu_order'] = $post['menu_order'];
        }
        
        // Featured image
        if ($args['include_featured_image'] && isset($post['_embedded']['wp:featuredmedia'][0]['source_url'])) {
            $row['featured_image'] = $post['_embedded']['wp:featuredmedia'][0]['source_url'];
        }
        
        // Taxonomies from embedded data
        if ($args['include_taxonomies']) {
            $taxonomy_data = RIA_DM_Taxonomy_Handler::export_terms($post_id, $post['type']);
            foreach ($taxonomy_data as $key => $value) {
                if (isset($row[$key])) {
                    $row[$key] = RIA_DM_CSV_Processor::sanitize_csv_value($value);
                }
            }
        }
        
        // ACF fields (still need to query directly as REST may not expose all ACF fields)
        if ($args['include_acf'] && RIA_DM_ACF_Handler::is_acf_active()) {
            $acf_data = RIA_DM_ACF_Handler::export_fields($post_id);
            foreach ($acf_data as $key => $value) {
                if (isset($row[$key])) {
                    $row[$key] = RIA_DM_CSV_Processor::sanitize_csv_value($value);
                }
            }
        }
        
        // Return values in exact header order
        $ordered_values = array();
        foreach ($headers as $header) {
            $ordered_values[] = isset($row[$header]) ? $row[$header] : '';
        }
        
        return $ordered_values;
    }
    
    /**
     * Get REST API base for post type
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
    
    /**
     * Compare standard vs REST export
     *
     * @param string $post_type Post type
     * @return array Comparison data
     */
    public static function compare_export_methods($post_type) {
        // Standard export
        $start_standard = microtime(true);
        $standard_result = RIA_DM_Exporter::export(array('post_type' => $post_type));
        $time_standard = microtime(true) - $start_standard;
        
        // REST export
        $start_rest = microtime(true);
        $rest_result = self::export_via_rest(array('post_type' => $post_type));
        $time_rest = microtime(true) - $start_rest;
        
        return array(
            'standard' => array(
                'file' => $standard_result,
                'time' => round($time_standard, 3),
            ),
            'rest' => array(
                'file' => $rest_result,
                'time' => round($time_rest, 3),
            ),
        );
    }
}
