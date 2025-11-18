<?php
/**
 * ACF Handler Class
 * Handles Advanced Custom Fields integration
 */

if (!defined('ABSPATH')) {
    exit;
}

class RIA_DM_ACF_Handler {
    
    /**
     * Check if ACF is active
     *
     * @return bool
     */
    public static function is_acf_active() {
        return class_exists('ACF');
    }
    
    /**
     * Get all ACF field groups for a post type
     *
     * @param string $post_type Post type
     * @return array ACF field groups
     */
    public static function get_field_groups($post_type) {
        if (!self::is_acf_active()) {
            return array();
        }
        
        $field_groups = acf_get_field_groups(array(
            'post_type' => $post_type
        ));
        
        return $field_groups ?: array();
    }
    
    /**
     * Get all ACF fields for a post type
     *
     * @param string $post_type Post type
     * @return array ACF fields with metadata
     */
    public static function get_fields_for_post_type($post_type) {
        if (!self::is_acf_active()) {
            return array();
        }
        
        $all_fields = array();
        $field_groups = self::get_field_groups($post_type);
        
        foreach ($field_groups as $group) {
            $fields = acf_get_fields($group['key']);
            
            if ($fields) {
                foreach ($fields as $field) {
                    $all_fields[$field['name']] = array(
                        'label' => $field['label'],
                        'name' => $field['name'],
                        'type' => $field['type'],
                        'key' => $field['key'],
                        'group' => $group['title']
                    );
                }
            }
        }
        
        return $all_fields;
    }
    
    /**
     * Export ACF fields for a post
     *
     * @param int $post_id Post ID
     * @param array $field_list Optional specific fields to export
     * @return array Field values
     */
    public static function export_fields($post_id, $field_list = null) {
        if (!self::is_acf_active()) {
            return array();
        }
        
        $exported_fields = array();
        
        // Get all ACF fields for this post
        $fields = get_field_objects($post_id);
        
        if (!$fields) {
            return array();
        }
        
        foreach ($fields as $field_name => $field) {
            // Skip if field_list is provided and field not in list
            if ($field_list !== null && !in_array($field_name, $field_list)) {
                continue;
            }
            
            $value = $field['value'];
            
            // Format value based on field type
            $formatted_value = self::format_field_value($value, $field['type']);
            
            $exported_fields['acf_' . $field_name] = $formatted_value;
        }
        
        return $exported_fields;
    }
    
    /**
     * Format ACF field value for CSV export
     *
     * @param mixed $value Field value
     * @param string $type Field type
     * @return string Formatted value
     */
    private static function format_field_value($value, $type) {
        switch ($type) {
            case 'repeater':
                // Repeater fields: JSON encode
                return json_encode($value);
                
            case 'relationship':
            case 'post_object':
                // Post relationships: export IDs
                if (is_array($value)) {
                    $ids = array_map(function($post) {
                        return is_object($post) ? $post->ID : $post;
                    }, $value);
                    return implode(',', $ids);
                } elseif (is_object($value)) {
                    return $value->ID;
                }
                return $value;
                
            case 'taxonomy':
                // Taxonomies: export term IDs or slugs
                if (is_array($value)) {
                    $terms = array_map(function($term) {
                        return is_object($term) ? $term->slug : $term;
                    }, $value);
                    return implode(',', $terms);
                } elseif (is_object($value)) {
                    return $value->slug;
                }
                return $value;
                
            case 'image':
            case 'file':
                // Media: export URL or ID
                if (is_array($value)) {
                    return isset($value['url']) ? $value['url'] : $value['ID'];
                }
                return $value;
                
            case 'gallery':
                // Gallery: export image URLs
                if (is_array($value)) {
                    $urls = array_map(function($img) {
                        return is_array($img) ? $img['url'] : $img;
                    }, $value);
                    return implode('|', $urls);
                }
                return $value;
                
            case 'wysiwyg':
                // WYSIWYG: keep HTML but clean up
                return wp_kses_post($value);
                
            case 'true_false':
                // Boolean: convert to 1/0
                return $value ? '1' : '0';
                
            case 'date_picker':
            case 'date_time_picker':
                // Dates: format consistently
                return $value ? date('Y-m-d H:i:s', strtotime($value)) : '';
                
            case 'group':
                // Group fields: JSON encode
                return json_encode($value);
                
            default:
                // Default: return as string
                if (is_array($value)) {
                    return json_encode($value);
                }
                return (string) $value;
        }
    }
    
    /**
     * Import ACF fields for a post
     *
     * @param int $post_id Post ID
     * @param array $field_data Field data from CSV
     * @return bool Success status
     */
    public static function import_fields($post_id, $field_data) {
        if (!self::is_acf_active()) {
            return false;
        }
        
        $success = true;
        
        foreach ($field_data as $field_name => $value) {
            // Skip if not an ACF field (doesn't start with acf_)
            if (strpos($field_name, 'acf_') !== 0) {
                continue;
            }
            
            // Remove acf_ prefix
            $clean_field_name = substr($field_name, 4);
            
            // Get field object to determine type
            $field_object = get_field_object($clean_field_name, $post_id);
            
            if (!$field_object) {
                // Try to get field by key
                $field_object = acf_get_field($clean_field_name);
            }
            
            if ($field_object) {
                $parsed_value = self::parse_field_value($value, $field_object['type']);
                
                // Update field
                $result = update_field($clean_field_name, $parsed_value, $post_id);
                
                if (!$result) {
                    $success = false;
                }
            }
        }
        
        return $success;
    }
    
    /**
     * Parse CSV value back to ACF field format
     *
     * @param string $value CSV value
     * @param string $type Field type
     * @return mixed Parsed value
     */
    private static function parse_field_value($value, $type) {
        if (empty($value)) {
            return null;
        }
        
        switch ($type) {
            case 'repeater':
            case 'group':
                // JSON decode
                $decoded = json_decode($value, true);
                return $decoded ?: $value;
                
            case 'relationship':
            case 'post_object':
                // Convert IDs back to array
                if (strpos($value, ',') !== false) {
                    return array_map('intval', explode(',', $value));
                }
                return intval($value);
                
            case 'taxonomy':
                // Convert to array of term IDs or slugs
                if (strpos($value, ',') !== false) {
                    return array_map('trim', explode(',', $value));
                }
                return trim($value);
                
            case 'image':
            case 'file':
                // Try to get attachment ID from URL
                if (filter_var($value, FILTER_VALIDATE_URL)) {
                    $attachment_id = attachment_url_to_postid($value);
                    return $attachment_id ?: $value;
                }
                return intval($value);
                
            case 'gallery':
                // Parse pipe-separated URLs
                if (strpos($value, '|') !== false) {
                    $urls = explode('|', $value);
                    return array_map(function($url) {
                        $id = attachment_url_to_postid($url);
                        return $id ?: $url;
                    }, $urls);
                }
                return array($value);
                
            case 'true_false':
                // Convert to boolean
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
                
            case 'number':
                return is_numeric($value) ? floatval($value) : 0;
                
            case 'date_picker':
            case 'date_time_picker':
                // Parse date
                return strtotime($value) ? date('Y-m-d', strtotime($value)) : $value;
                
            default:
                return $value;
        }
    }
    
    /**
     * Get ACF field column headers for export
     *
     * @param string $post_type Post type
     * @return array Field names with acf_ prefix
     */
    public static function get_field_headers($post_type) {
        $fields = self::get_fields_for_post_type($post_type);
        $headers = array();
        
        foreach ($fields as $field_name => $field_data) {
            $headers[] = 'acf_' . $field_name;
        }
        
        return $headers;
    }
}
