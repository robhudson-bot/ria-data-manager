<?php
/**
 * Exporter Class
 * Handles exporting WordPress content to CSV
 */

if (!defined('ABSPATH')) {
    exit;
}

class RIA_DM_Exporter {
    
    /**
     * Export posts to CSV
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
            'meta_keys' => array(), // Specific meta keys to export
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Build query args
        $query_args = array(
            'post_type' => $args['post_type'],
            'post_status' => $args['post_status'],
            'posts_per_page' => $args['posts_per_page'],
            'orderby' => 'date',
            'order' => 'DESC',
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
            
            $row = self::build_row($post_id, $args, $headers);
            $data[] = $row;
        }
        
        wp_reset_postdata();
        
        // Generate filename
        $filename = self::generate_filename($args['post_type']);
        
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
            'post_name', // slug
            'post_parent',
            'menu_order',
        );
        
        // Featured image
        if ($args['include_featured_image']) {
            $headers[] = 'featured_image';
        }
        
        // Taxonomies
        if ($args['include_taxonomies']) {
            $taxonomy_headers = RIA_DM_Taxonomy_Handler::get_taxonomy_headers($args['post_type']);
            $headers = array_merge($headers, $taxonomy_headers);
        }
        
        // ACF fields
        if ($args['include_acf'] && RIA_DM_ACF_Handler::is_acf_active()) {
            $acf_headers = RIA_DM_ACF_Handler::get_field_headers($args['post_type']);
            $headers = array_merge($headers, $acf_headers);
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
     * Build data row for a post
     *
     * @param int $post_id Post ID
     * @param array $args Export arguments
     * @param array $headers CSV headers
     * @return array Row data
     */
    private static function build_row($post_id, $args, $headers) {
        $post = get_post($post_id);
        
        // Initialize row with empty values for all headers
        $row = array_fill_keys($headers, '');
        
        // Standard WordPress fields
        $row['ID'] = $post->ID;
        $row['post_title'] = RIA_DM_CSV_Processor::sanitize_csv_value($post->post_title);
        $row['post_content'] = RIA_DM_CSV_Processor::sanitize_csv_value($post->post_content);
        $row['post_excerpt'] = RIA_DM_CSV_Processor::sanitize_csv_value($post->post_excerpt);
        $row['post_status'] = $post->post_status;
        $row['post_type'] = $post->post_type;
        $row['post_date'] = $post->post_date;
        $row['post_modified'] = $post->post_modified;
        $row['post_author'] = get_the_author_meta('user_login', $post->post_author);
        $row['post_name'] = $post->post_name;
        $row['post_parent'] = $post->post_parent;
        $row['menu_order'] = $post->menu_order;
        
        // Featured image
        if ($args['include_featured_image']) {
            $thumbnail_id = get_post_thumbnail_id($post_id);
            if ($thumbnail_id) {
                $row['featured_image'] = wp_get_attachment_url($thumbnail_id);
            }
        }
        
        // Taxonomies
        if ($args['include_taxonomies']) {
            $taxonomy_data = RIA_DM_Taxonomy_Handler::export_terms($post_id, $post->post_type);
            foreach ($taxonomy_data as $key => $value) {
                if (isset($row[$key])) {
                    $row[$key] = RIA_DM_CSV_Processor::sanitize_csv_value($value);
                }
            }
        }
        
        // ACF fields
        if ($args['include_acf'] && RIA_DM_ACF_Handler::is_acf_active()) {
            $acf_data = RIA_DM_ACF_Handler::export_fields($post_id);
            foreach ($acf_data as $key => $value) {
                if (isset($row[$key])) {
                    $row[$key] = RIA_DM_CSV_Processor::sanitize_csv_value($value);
                }
            }
        }
        
        // Custom meta fields
        if ($args['include_meta'] && !empty($args['meta_keys'])) {
            foreach ($args['meta_keys'] as $meta_key) {
                $meta_value = get_post_meta($post_id, $meta_key, true);
                $header_key = 'meta_' . $meta_key;
                if (isset($row[$header_key])) {
                    $row[$header_key] = RIA_DM_CSV_Processor::sanitize_csv_value($meta_value);
                }
            }
        }
        
        // Return values in exact header order to prevent misalignment
        $ordered_values = array();
        foreach ($headers as $header) {
            $ordered_values[] = isset($row[$header]) ? $row[$header] : '';
        }
        
        return $ordered_values;
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
     * Get available post types for export
     *
     * @return array Post types
     */
    public static function get_available_post_types() {
        $post_types = get_post_types(array(
            'public' => true,
        ), 'objects');
        
        // Add custom post types
        $custom_types = get_post_types(array(
            'public' => false,
            '_builtin' => false,
        ), 'objects');
        
        $post_types = array_merge($post_types, $custom_types);
        
        // Remove some internal types
        $exclude = array('attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset');
        
        foreach ($exclude as $type) {
            unset($post_types[$type]);
        }
        
        return $post_types;
    }
    
    /**
     * Get export statistics
     *
     * @param array $args Query arguments
     * @return array Statistics
     */
    public static function get_export_stats($args) {
        $query_args = array(
            'post_type' => $args['post_type'],
            'post_status' => $args['post_status'],
            'posts_per_page' => -1,
            'fields' => 'ids',
        );
        
        $query = new WP_Query($query_args);
        
        return array(
            'total_posts' => $query->found_posts,
            'post_type' => $args['post_type'],
            'estimated_size' => self::estimate_file_size($query->found_posts),
        );
    }
    
    /**
     * Estimate CSV file size
     *
     * @param int $post_count Number of posts
     * @return string Readable file size
     */
    private static function estimate_file_size($post_count) {
        // Rough estimate: ~5KB per post
        $bytes = $post_count * 5 * 1024;
        
        $units = array('B', 'KB', 'MB', 'GB');
        $i = 0;
        
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
    
    /**
     * Export multiple post types
     *
     * @param array $post_types Array of post type slugs
     * @param array $args Additional export arguments
     * @return array Array of file paths or errors
     */
    public static function export_multiple($post_types, $args = array()) {
        $results = array();
        
        foreach ($post_types as $post_type) {
            $export_args = array_merge($args, array('post_type' => $post_type));
            $result = self::export($export_args);
            
            $results[$post_type] = $result;
        }
        
        return $results;
    }
}
