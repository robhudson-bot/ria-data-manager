<?php
/**
 * CSV Processor Class
 * Handles CSV file reading and writing operations
 */

if (!defined('ABSPATH')) {
    exit;
}

class RIA_DM_CSV_Processor {
    
    /**
     * Maximum rows per batch
     */
    const BATCH_SIZE = 100;
    
    /**
     * Write data to CSV file
     *
     * @param string $filename Filename to write
     * @param array $headers Column headers
     * @param array $data Data rows
     * @return string|WP_Error File path or error
     */
    public static function write_csv($filename, $headers, $data) {
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . '/ria-data-manager/' . sanitize_file_name($filename);
        
        // Open file for writing
        $handle = fopen($file_path, 'w');
        if (!$handle) {
            return new WP_Error('file_error', 'Could not create CSV file');
        }
        
        // Write BOM for UTF-8
        fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Write headers
        fputcsv($handle, $headers);
        
        // Write data rows
        foreach ($data as $row) {
            fputcsv($handle, $row);
        }
        
        fclose($handle);
        
        return $file_path;
    }
    
    /**
     * Read CSV file
     *
     * @param string $file_path Path to CSV file
     * @return array|WP_Error Array with headers and data, or error
     */
    public static function read_csv($file_path) {
        if (!file_exists($file_path)) {
            return new WP_Error('file_not_found', 'CSV file not found');
        }
        
        $handle = fopen($file_path, 'r');
        if (!$handle) {
            return new WP_Error('file_error', 'Could not read CSV file');
        }
        
        // Skip BOM if present
        $bom = fread($handle, 3);
        if ($bom !== chr(0xEF).chr(0xBB).chr(0xBF)) {
            rewind($handle);
        }
        
        // Read headers
        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            return new WP_Error('invalid_csv', 'Invalid CSV file - no headers found');
        }
        
        // Read data rows
        $data = array();
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) === count($headers)) {
                $data[] = array_combine($headers, $row);
            }
        }
        
        fclose($handle);
        
        return array(
            'headers' => $headers,
            'data' => $data,
            'total_rows' => count($data)
        );
    }
    
    /**
     * Sanitize value for CSV output
     *
     * @param mixed $value Value to sanitize
     * @return string Sanitized value
     */
    public static function sanitize_csv_value($value) {
        if (is_array($value)) {
            return implode(',', array_map('trim', $value));
        }
        
        if (is_object($value)) {
            return '';
        }
        
        // Remove any control characters except newlines and tabs
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
        
        return (string) $value;
    }
    
    /**
     * Parse CSV value
     *
     * @param string $value Value to parse
     * @param string $type Field type (array, string, etc.)
     * @return mixed Parsed value
     */
    public static function parse_csv_value($value, $type = 'string') {
        $value = trim($value);
        
        if (empty($value)) {
            return '';
        }
        
        switch ($type) {
            case 'array':
                // Handle comma-separated values
                return array_map('trim', explode(',', $value));
                
            case 'number':
                return is_numeric($value) ? floatval($value) : 0;
                
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
                
            case 'date':
                return strtotime($value) ?: $value;
                
            default:
                return $value;
        }
    }
    
    /**
     * Get download URL for a file
     *
     * @param string $file_path Full file path
     * @return string Download URL
     */
    public static function get_download_url($file_path) {
        $upload_dir = wp_upload_dir();
        $file_url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $file_path);
        
        // Add nonce for security
        $nonce = wp_create_nonce('ria_dm_download_' . basename($file_path));
        return add_query_arg('nonce', $nonce, $file_url);
    }
    
    /**
     * Clean old CSV files
     *
     * @param int $days Days old to delete (default 1)
     */
    public static function clean_old_files($days = 1) {
        $upload_dir = wp_upload_dir();
        $ria_dm_dir = $upload_dir['basedir'] . '/ria-data-manager';
        
        if (!file_exists($ria_dm_dir)) {
            return;
        }
        
        $files = glob($ria_dm_dir . '/*.csv');
        $cutoff_time = time() - ($days * 86400);
        
        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $cutoff_time) {
                @unlink($file);
            }
        }
    }
    
    /**
     * Validate CSV structure
     *
     * @param array $headers CSV headers
     * @return bool|WP_Error True if valid, error otherwise
     */
    public static function validate_csv_structure($headers) {
        // Required WordPress fields
        $required_fields = array('post_title', 'post_type');
        
        foreach ($required_fields as $field) {
            if (!in_array($field, $headers)) {
                return new WP_Error(
                    'missing_field',
                    sprintf('Required field "%s" is missing from CSV', $field)
                );
            }
        }
        
        return true;
    }
    
    /**
     * Get field mapping suggestions
     *
     * @param array $headers CSV headers
     * @return array Suggested field mappings
     */
    public static function suggest_field_mapping($headers) {
        $mapping = array();
        
        // Common field name variations
        $field_aliases = array(
            'post_title' => array('title', 'post_title', 'name', 'heading'),
            'post_content' => array('content', 'post_content', 'body', 'description'),
            'post_excerpt' => array('excerpt', 'post_excerpt', 'summary'),
            'post_status' => array('status', 'post_status', 'state'),
            'post_type' => array('type', 'post_type', 'content_type'),
            'post_date' => array('date', 'post_date', 'published', 'publish_date'),
        );
        
        foreach ($headers as $header) {
            $header_lower = strtolower(trim($header));
            
            foreach ($field_aliases as $wp_field => $aliases) {
                if (in_array($header_lower, $aliases)) {
                    $mapping[$header] = $wp_field;
                    break;
                }
            }
            
            // If no match found, keep original
            if (!isset($mapping[$header])) {
                $mapping[$header] = $header;
            }
        }
        
        return $mapping;
    }
}
