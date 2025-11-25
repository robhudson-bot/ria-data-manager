<?php
/**
 * Metadata-Only Exporter Class
 *
 * Exports only metadata fields for collaborative editing in Google Sheets.
 * Excludes large content fields that exceed Google Sheets limits.
 *
 * @package RIA_Data_Manager
 * @since 1.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RIA_DM_Metadata_Exporter {

    /**
     * Export metadata only (no post_content)
     *
     * Perfect for Google Sheets editing where teams need to update:
     * - Titles, excerpts, slugs
     * - Featured images
     * - ACF custom fields
     * - Post status, author, dates
     *
     * @param array $args Export arguments
     * @return string|WP_Error File path or error
     */
    public static function export($args = array()) {
        $defaults = array(
            'post_type' => 'page',
            'post_status' => array('publish', 'draft'),
            'posts_per_page' => -1,
            'include_acf' => true,
            'include_taxonomies' => true,
            'include_featured_image' => true,
            'include_content_preview' => true, // First 500 chars only
            'preview_length' => 500,
        );

        $args = wp_parse_args($args, $defaults);

        // Build query
        $query_args = array(
            'post_type' => $args['post_type'],
            'post_status' => $args['post_status'],
            'posts_per_page' => $args['posts_per_page'],
            'orderby' => 'ID',
            'order' => 'ASC',
        );

        $query = new WP_Query($query_args);

        if (!$query->have_posts()) {
            return new WP_Error('no_posts', 'No posts found matching criteria');
        }

        // Build headers
        $headers = self::build_metadata_headers($args);

        // Build data rows
        $data = array();

        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();

            $row = self::build_metadata_row($post_id, $args, $headers);
            $data[] = $row;
        }

        wp_reset_postdata();

        // Generate filename
        $filename = sprintf('metadata_%s_%s.csv',
            sanitize_title($args['post_type']),
            date('Y-m-d_His')
        );

        // Write CSV
        $file_path = self::write_metadata_csv($filename, $headers, $data);

        if (is_wp_error($file_path)) {
            return $file_path;
        }

        // Clean old files
        RIA_DM_CSV_Processor::clean_old_files(1);

        return $file_path;
    }

    /**
     * Build metadata-only headers
     *
     * @param array $args Export arguments
     * @return array Headers
     */
    private static function build_metadata_headers($args) {
        // Core WordPress fields (NO post_content!)
        $headers = array(
            'ID',
            'post_title',
            'post_excerpt',
        );

        // Add content preview if requested
        if ($args['include_content_preview']) {
            $headers[] = 'content_preview';
        }

        // Continue with other fields
        $headers = array_merge($headers, array(
            'post_status',
            'post_name',
            'post_date',
            'post_modified',
            'post_author',
            'last_modified_by',
            'post_parent',
            'post_parent_title',
            'menu_order',
            'page_template',
            'comment_status',
            'post_url',
            'edit_url',
        ));

        // Featured image with extra info
        if ($args['include_featured_image']) {
            $headers[] = 'featured_image';
            $headers[] = 'featured_image_alt';
            $headers[] = 'has_featured_image';
        }

        // Content statistics
        $headers[] = 'word_count';

        // Post-specific fields (only for 'post' type)
        if ($args['post_type'] === 'post') {
            $headers[] = 'is_sticky';
            $headers[] = 'comment_count';
            $headers[] = 'author_display_name';
            $headers[] = 'author_email';
        }

        // Yoast SEO fields
        if (self::is_yoast_active()) {
            $headers[] = 'yoast_focus_keyword';
            $headers[] = 'yoast_meta_description';
            $headers[] = 'yoast_seo_score';
            $headers[] = 'yoast_readability_score';
            $headers[] = 'yoast_reading_time';
            $headers[] = 'yoast_primary_category';
            $headers[] = 'yoast_og_image';
            $headers[] = 'yoast_og_description';
            $headers[] = 'yoast_twitter_image';
            $headers[] = 'yoast_twitter_title';
        }

        // Taxonomies
        if ($args['include_taxonomies']) {
            $taxonomies = get_object_taxonomies($args['post_type'], 'names');
            foreach ($taxonomies as $taxonomy) {
                $headers[] = 'taxonomy_' . $taxonomy;
            }
        }

        // ACF fields - improved detection
        if ($args['include_acf'] && RIA_DM_ACF_Handler::is_acf_active()) {
            $acf_fields = self::get_all_acf_fields_for_export($args['post_type']);
            foreach ($acf_fields as $field_name) {
                $headers[] = 'acf_' . $field_name;
            }
        }

        return $headers;
    }

    /**
     * Check if Yoast SEO is active
     *
     * @return bool
     */
    private static function is_yoast_active() {
        return defined('WPSEO_VERSION') || class_exists('WPSEO_Options');
    }

    /**
     * Get all ACF fields that should be exported
     * Uses multiple detection methods for reliability
     *
     * @param string $post_type Post type
     * @return array Field names
     */
    private static function get_all_acf_fields_for_export($post_type) {
        $field_names = array();

        // Method 1: Try standard location-based detection
        $fields_by_location = RIA_DM_ACF_Handler::get_fields_for_post_type($post_type);
        foreach ($fields_by_location as $field_name => $field_data) {
            $field_names[$field_name] = true;
        }

        // Method 2: Get fields from actual posts (more reliable)
        $query = new WP_Query(array(
            'post_type' => $post_type,
            'posts_per_page' => 5, // Sample first 5 posts
            'post_status' => 'any',
            'fields' => 'ids',
        ));

        if ($query->have_posts()) {
            foreach ($query->posts as $post_id) {
                $post_fields = get_field_objects($post_id);
                if ($post_fields) {
                    foreach ($post_fields as $field_name => $field) {
                        $field_names[$field_name] = true;
                    }
                }
            }
        }

        wp_reset_postdata();

        return array_keys($field_names);
    }

    /**
     * Build metadata-only row
     *
     * @param int $post_id Post ID
     * @param array $args Export arguments
     * @param array $headers CSV headers
     * @return array Row data
     */
    private static function build_metadata_row($post_id, $args, $headers) {
        $post = get_post($post_id);
        $row = array();

        foreach ($headers as $header) {
            $value = '';

            switch ($header) {
                case 'ID':
                    $value = $post->ID;
                    break;

                case 'post_title':
                    $value = $post->post_title;
                    break;

                case 'post_excerpt':
                    $value = $post->post_excerpt;
                    break;

                case 'content_preview':
                    // Just first N characters for preview
                    $content = strip_tags($post->post_content);
                    $content = trim(preg_replace('/\s+/', ' ', $content));
                    $length = $args['preview_length'];
                    $value = mb_substr($content, 0, $length);
                    if (mb_strlen($content) > $length) {
                        $value .= '...';
                    }
                    break;

                case 'post_status':
                    $value = $post->post_status;
                    break;

                case 'post_name':
                    $value = $post->post_name;
                    break;

                case 'post_date':
                    // Format as YYYY-MM-DD for Google Sheets compatibility
                    $value = !empty($post->post_date) && $post->post_date !== '0000-00-00 00:00:00'
                        ? date('Y-m-d', strtotime($post->post_date))
                        : '';
                    break;

                case 'post_modified':
                    // Format as YYYY-MM-DD for Google Sheets compatibility
                    $value = !empty($post->post_modified) && $post->post_modified !== '0000-00-00 00:00:00'
                        ? date('Y-m-d', strtotime($post->post_modified))
                        : '';
                    break;

                case 'post_author':
                    $author = get_userdata($post->post_author);
                    $value = $author ? $author->user_login : '';
                    break;

                case 'last_modified_by':
                    // Get last editor from revisions
                    $last_revision = wp_get_post_revisions($post_id, array('posts_per_page' => 1));
                    if ($last_revision) {
                        $revision = reset($last_revision);
                        $editor = get_userdata($revision->post_author);
                        $value = $editor ? $editor->user_login : '';
                    } else {
                        // Fall back to post author
                        $author = get_userdata($post->post_author);
                        $value = $author ? $author->user_login : '';
                    }
                    break;

                case 'post_parent':
                    $value = $post->post_parent;
                    break;

                case 'post_parent_title':
                    if ($post->post_parent) {
                        $parent = get_post($post->post_parent);
                        $value = $parent ? $parent->post_title : '';
                    }
                    break;

                case 'menu_order':
                    $value = $post->menu_order;
                    break;

                case 'page_template':
                    $template = get_page_template_slug($post_id);
                    $value = $template ? $template : 'default';
                    break;

                case 'comment_status':
                    $value = $post->comment_status;
                    break;

                case 'post_url':
                    $value = get_permalink($post_id);
                    break;

                case 'edit_url':
                    $value = get_edit_post_link($post_id);
                    break;

                case 'featured_image':
                    if ($args['include_featured_image']) {
                        $thumbnail_id = get_post_thumbnail_id($post_id);
                        if ($thumbnail_id) {
                            $value = wp_get_attachment_url($thumbnail_id);
                        }
                    }
                    break;

                case 'featured_image_alt':
                    if ($args['include_featured_image']) {
                        $thumbnail_id = get_post_thumbnail_id($post_id);
                        if ($thumbnail_id) {
                            $value = get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true);
                        }
                    }
                    break;

                case 'has_featured_image':
                    if ($args['include_featured_image']) {
                        $value = has_post_thumbnail($post_id) ? 'Yes' : 'No';
                    }
                    break;

                case 'word_count':
                    $content = strip_tags($post->post_content);
                    $value = str_word_count($content);
                    break;

                // Post-specific fields
                case 'is_sticky':
                    $value = is_sticky($post_id) ? 'Yes' : 'No';
                    break;

                case 'comment_count':
                    $value = $post->comment_count;
                    break;

                case 'author_display_name':
                    $author = get_userdata($post->post_author);
                    $value = $author ? $author->display_name : '';
                    break;

                case 'author_email':
                    $author = get_userdata($post->post_author);
                    $value = $author ? $author->user_email : '';
                    break;

                // Yoast SEO fields
                case 'yoast_focus_keyword':
                    $value = get_post_meta($post_id, '_yoast_wpseo_focuskw', true);
                    break;

                case 'yoast_meta_description':
                    $value = get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
                    break;

                case 'yoast_seo_score':
                    $value = get_post_meta($post_id, '_yoast_wpseo_linkdex', true);
                    break;

                case 'yoast_readability_score':
                    $value = get_post_meta($post_id, '_yoast_wpseo_content_score', true);
                    break;

                case 'yoast_reading_time':
                    $value = get_post_meta($post_id, '_yoast_wpseo_estimated-reading-time-minutes', true);
                    break;

                case 'yoast_primary_category':
                    $cat_id = get_post_meta($post_id, '_yoast_wpseo_primary_category', true);
                    if ($cat_id) {
                        $category = get_category($cat_id);
                        $value = $category ? $category->name : $cat_id;
                    }
                    break;

                case 'yoast_og_image':
                    $value = get_post_meta($post_id, '_yoast_wpseo_opengraph-image', true);
                    break;

                case 'yoast_og_description':
                    $value = get_post_meta($post_id, '_yoast_wpseo_opengraph-description', true);
                    break;

                case 'yoast_twitter_image':
                    $value = get_post_meta($post_id, '_yoast_wpseo_twitter-image', true);
                    break;

                case 'yoast_twitter_title':
                    $value = get_post_meta($post_id, '_yoast_wpseo_twitter-title', true);
                    break;

                default:
                    // Taxonomy fields
                    if (strpos($header, 'taxonomy_') === 0 && $args['include_taxonomies']) {
                        $taxonomy = str_replace('taxonomy_', '', $header);
                        $terms = wp_get_post_terms($post_id, $taxonomy, array('fields' => 'names'));
                        if (!is_wp_error($terms)) {
                            $value = implode(', ', $terms);
                        }
                    }
                    // ACF fields
                    elseif (strpos($header, 'acf_') === 0 && $args['include_acf']) {
                        $field_name = str_replace('acf_', '', $header);
                        if (function_exists('get_field')) {
                            $field_value = get_field($field_name, $post_id);
                            $value = self::format_acf_value($field_value);
                        }
                    }
                    break;
            }

            // Clean value for CSV
            $row[] = self::sanitize_for_csv($value);
        }

        return $row;
    }

    /**
     * Format ACF value for CSV export
     *
     * @param mixed $value ACF field value
     * @return string Formatted value
     */
    private static function format_acf_value($value) {
        if (is_array($value)) {
            // Simple arrays: join with pipe
            if (self::is_simple_array($value)) {
                return implode(' | ', $value);
            }
            // Complex arrays: JSON
            return json_encode($value);
        } elseif (is_object($value)) {
            // Post objects: use ID
            if (isset($value->ID)) {
                return $value->ID;
            }
            // Other objects: JSON
            return json_encode($value);
        }

        return (string) $value;
    }

    /**
     * Check if array contains only simple values
     *
     * @param array $array Array to check
     * @return bool True if simple
     */
    private static function is_simple_array($array) {
        if (!is_array($array)) {
            return false;
        }

        foreach ($array as $value) {
            if (is_array($value) || is_object($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Sanitize value for CSV
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

        $value = (string) $value;

        // Remove null bytes
        $value = str_replace(array("\0", "\x0B"), '', $value);

        // Normalize line endings
        $value = str_replace(array("\r\n", "\r"), "\n", $value);

        return $value;
    }

    /**
     * Write metadata CSV file
     *
     * @param string $filename Filename
     * @param array $headers Headers
     * @param array $data Data rows
     * @return string|WP_Error File path or error
     */
    private static function write_metadata_csv($filename, $headers, $data) {
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . '/ria-data-manager/' . sanitize_file_name($filename);

        // Ensure directory exists
        $dir = dirname($file_path);
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }

        // Open file
        $handle = fopen($file_path, 'w');
        if (!$handle) {
            return new WP_Error('file_error', 'Could not create CSV file');
        }

        // Write UTF-8 BOM
        fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

        // Write headers
        if (fputcsv($handle, $headers) === false) {
            fclose($handle);
            return new WP_Error('write_error', 'Failed to write CSV headers');
        }

        // Write data
        foreach ($data as $row) {
            if (fputcsv($handle, $row) === false) {
                fclose($handle);
                return new WP_Error('write_error', 'Failed to write CSV row');
            }
        }

        fclose($handle);

        // Verify file
        if (!file_exists($file_path) || filesize($file_path) === 0) {
            return new WP_Error('file_error', 'CSV file was not created properly');
        }

        return $file_path;
    }

    /**
     * Get export statistics
     *
     * @param string $file_path CSV file path
     * @return array Statistics
     */
    public static function get_export_stats($file_path) {
        if (!file_exists($file_path)) {
            return array();
        }

        $stats = array(
            'filesize' => filesize($file_path),
            'filesize_formatted' => size_format(filesize($file_path)),
            'row_count' => 0,
            'google_sheets_compatible' => false,
        );

        // Count rows
        $handle = fopen($file_path, 'r');
        if ($handle) {
            $row_count = 0;
            while (fgetcsv($handle)) {
                $row_count++;
            }
            fclose($handle);
            $stats['row_count'] = $row_count - 1; // Subtract header
        }

        // Check Google Sheets limits
        // Max 10 million cells, 50,000 chars per cell
        $stats['google_sheets_compatible'] = ($stats['filesize'] < 50 * 1024 * 1024); // 50MB limit

        return $stats;
    }
}
