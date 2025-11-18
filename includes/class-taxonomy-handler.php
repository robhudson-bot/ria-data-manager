<?php
/**
 * Taxonomy Handler Class
 * Handles taxonomy operations for export/import
 */

if (!defined('ABSPATH')) {
    exit;
}

class RIA_DM_Taxonomy_Handler {
    
    /**
     * Get all taxonomies for a post type
     *
     * @param string $post_type Post type
     * @return array Taxonomies
     */
    public static function get_taxonomies_for_post_type($post_type) {
        return get_object_taxonomies($post_type, 'objects');
    }
    
    /**
     * Export taxonomy terms for a post
     *
     * @param int $post_id Post ID
     * @param string $post_type Post type
     * @return array Taxonomy terms
     */
    public static function export_terms($post_id, $post_type) {
        $taxonomies = self::get_taxonomies_for_post_type($post_type);
        $exported_terms = array();
        
        foreach ($taxonomies as $taxonomy) {
            $terms = wp_get_post_terms($post_id, $taxonomy->name, array('fields' => 'names'));
            
            if (!is_wp_error($terms) && !empty($terms)) {
                $exported_terms['tax_' . $taxonomy->name] = implode(',', $terms);
            } else {
                $exported_terms['tax_' . $taxonomy->name] = '';
            }
        }
        
        return $exported_terms;
    }
    
    /**
     * Import taxonomy terms for a post
     *
     * @param int $post_id Post ID
     * @param array $terms_data Terms data from CSV
     * @param bool $create_missing Create terms if they don't exist
     * @return bool Success status
     */
    public static function import_terms($post_id, $terms_data, $create_missing = true) {
        $success = true;
        
        foreach ($terms_data as $taxonomy_key => $terms_string) {
            // Skip if not a taxonomy field (doesn't start with tax_)
            if (strpos($taxonomy_key, 'tax_') !== 0) {
                continue;
            }
            
            // Remove tax_ prefix
            $taxonomy = substr($taxonomy_key, 4);
            
            // Check if taxonomy exists
            if (!taxonomy_exists($taxonomy)) {
                continue;
            }
            
            // Parse terms
            if (empty($terms_string)) {
                // Clear existing terms
                wp_set_object_terms($post_id, array(), $taxonomy);
                continue;
            }
            
            $term_names = array_map('trim', explode(',', $terms_string));
            $term_ids = array();
            
            foreach ($term_names as $term_name) {
                if (empty($term_name)) {
                    continue;
                }
                
                // Check if term exists
                $term = get_term_by('name', $term_name, $taxonomy);
                
                if (!$term) {
                    // Try by slug
                    $term = get_term_by('slug', sanitize_title($term_name), $taxonomy);
                }
                
                if (!$term && $create_missing) {
                    // Create the term
                    $new_term = wp_insert_term($term_name, $taxonomy);
                    
                    if (!is_wp_error($new_term)) {
                        $term_ids[] = $new_term['term_id'];
                    }
                } elseif ($term) {
                    $term_ids[] = $term->term_id;
                }
            }
            
            // Set terms for post
            if (!empty($term_ids)) {
                $result = wp_set_object_terms($post_id, $term_ids, $taxonomy);
                
                if (is_wp_error($result)) {
                    $success = false;
                }
            }
        }
        
        return $success;
    }
    
    /**
     * Get taxonomy column headers for export
     *
     * @param string $post_type Post type
     * @return array Taxonomy names with tax_ prefix
     */
    public static function get_taxonomy_headers($post_type) {
        $taxonomies = self::get_taxonomies_for_post_type($post_type);
        $headers = array();
        
        foreach ($taxonomies as $taxonomy) {
            $headers[] = 'tax_' . $taxonomy->name;
        }
        
        return $headers;
    }
    
    /**
     * Get hierarchical terms as a path
     *
     * @param int $post_id Post ID
     * @param string $taxonomy Taxonomy name
     * @return string Term path (e.g., "Parent > Child")
     */
    public static function get_hierarchical_term_path($post_id, $taxonomy) {
        if (!is_taxonomy_hierarchical($taxonomy)) {
            $terms = wp_get_post_terms($post_id, $taxonomy, array('fields' => 'names'));
            return is_wp_error($terms) ? '' : implode(',', $terms);
        }
        
        $terms = wp_get_post_terms($post_id, $taxonomy);
        if (is_wp_error($terms) || empty($terms)) {
            return '';
        }
        
        $paths = array();
        
        foreach ($terms as $term) {
            $ancestors = get_ancestors($term->term_id, $taxonomy, 'taxonomy');
            $ancestors = array_reverse($ancestors);
            
            $path_parts = array();
            foreach ($ancestors as $ancestor_id) {
                $ancestor = get_term($ancestor_id, $taxonomy);
                if ($ancestor && !is_wp_error($ancestor)) {
                    $path_parts[] = $ancestor->name;
                }
            }
            $path_parts[] = $term->name;
            
            $paths[] = implode(' > ', $path_parts);
        }
        
        return implode(',', $paths);
    }
    
    /**
     * Parse hierarchical term path back to term IDs
     *
     * @param string $path Term path (e.g., "Parent > Child")
     * @param string $taxonomy Taxonomy name
     * @param bool $create_missing Create terms if they don't exist
     * @return array Term IDs
     */
    public static function parse_hierarchical_path($path, $taxonomy, $create_missing = true) {
        if (!is_taxonomy_hierarchical($taxonomy)) {
            // Non-hierarchical: just split by comma
            $term_names = array_map('trim', explode(',', $path));
            return self::get_or_create_terms($term_names, $taxonomy, $create_missing);
        }
        
        // Split multiple paths
        $paths = array_map('trim', explode(',', $path));
        $all_term_ids = array();
        
        foreach ($paths as $single_path) {
            // Split path by separator
            $path_parts = array_map('trim', explode('>', $single_path));
            
            $parent_id = 0;
            $term_id = null;
            
            foreach ($path_parts as $term_name) {
                if (empty($term_name)) {
                    continue;
                }
                
                // Try to find term
                $term = get_term_by('name', $term_name, $taxonomy);
                
                // Check if it has correct parent
                if ($term && $term->parent !== $parent_id) {
                    $term = false;
                }
                
                if (!$term && $create_missing) {
                    // Create term with parent
                    $new_term = wp_insert_term($term_name, $taxonomy, array(
                        'parent' => $parent_id
                    ));
                    
                    if (!is_wp_error($new_term)) {
                        $term_id = $new_term['term_id'];
                    }
                } elseif ($term) {
                    $term_id = $term->term_id;
                }
                
                $parent_id = $term_id;
            }
            
            if ($term_id) {
                $all_term_ids[] = $term_id;
            }
        }
        
        return $all_term_ids;
    }
    
    /**
     * Get or create terms by name
     *
     * @param array $term_names Term names
     * @param string $taxonomy Taxonomy name
     * @param bool $create_missing Create if don't exist
     * @return array Term IDs
     */
    private static function get_or_create_terms($term_names, $taxonomy, $create_missing = true) {
        $term_ids = array();
        
        foreach ($term_names as $term_name) {
            if (empty($term_name)) {
                continue;
            }
            
            $term = get_term_by('name', $term_name, $taxonomy);
            
            if (!$term && $create_missing) {
                $new_term = wp_insert_term($term_name, $taxonomy);
                if (!is_wp_error($new_term)) {
                    $term_ids[] = $new_term['term_id'];
                }
            } elseif ($term) {
                $term_ids[] = $term->term_id;
            }
        }
        
        return $term_ids;
    }
    
    /**
     * Clean taxonomy cache for a post
     *
     * @param int $post_id Post ID
     * @param string $post_type Post type
     */
    public static function clean_cache($post_id, $post_type) {
        $taxonomies = self::get_taxonomies_for_post_type($post_type);
        
        foreach ($taxonomies as $taxonomy) {
            clean_object_term_cache($post_id, $taxonomy->name);
        }
    }
}
