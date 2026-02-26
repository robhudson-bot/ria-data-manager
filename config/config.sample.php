<?php
/**
 * Sample Configuration for Quarry
 * 
 * Copy this file to config.php and customize for your site.
 * config.php is ignored by Git, so each site can have unique settings.
 * 
 * All settings are optional - the plugin works without configuration
 * using smart detection of post types, ACF fields, and taxonomies.
 * 
 * @package Quarry
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

return array(
    
    /**
     * Site Identification
     * 
     * Helps identify which site this configuration is for.
     * Used in logs and error reporting.
     */
    'site_name' => 'Your Site Name',
    'site_id' => 'unique-site-identifier',
    'environment' => 'production', // production, staging, development
    
    /**
     * Default Export Settings
     * 
     * These will be pre-selected when user opens the export page.
     * User can still change them in the UI.
     */
    'export_defaults' => array(
        'include_acf' => true,              // Include ACF fields
        'include_taxonomies' => true,       // Include categories, tags, etc.
        'include_featured_image' => true,   // Include featured images
        'include_meta' => false,            // Include custom meta fields
        'post_status' => array('publish'),  // Default post statuses
    ),
    
    /**
     * Default Import Settings
     * 
     * These will be pre-selected when user opens the import page.
     */
    'import_defaults' => array(
        'update_existing' => true,      // Update posts if ID exists
        'create_taxonomies' => true,    // Create missing taxonomies
        'skip_on_error' => true,        // Continue if row fails
        'batch_size' => 100,            // Posts per batch
        'default_post_status' => 'draft', // Status for new posts
    ),
    
    /**
     * Priority Post Types
     * 
     * These post types will appear first in the dropdown.
     * Use post type slugs, not labels.
     */
    'priority_post_types' => array(
        'post',
        'page',
        'events',
        'conferences',
        'resources',
    ),
    
    /**
     * Hidden Post Types
     * 
     * These post types won't appear in export options.
     * Useful for hiding internal/system post types.
     */
    'hidden_post_types' => array(
        'acf-field-group',
        'acf-field',
        'custom_css',
        'customize_changeset',
        'oembed_cache',
        'user_request',
        'wp_block',
    ),
    
    /**
     * Export Profiles (Future Feature)
     * 
     * Pre-configured export settings for common use cases.
     * Coming in v1.1.0
     */
    'export_profiles' => array(
        'all-published' => array(
            'name' => 'All Published Content',
            'description' => 'Export all published posts and pages',
            'post_type' => array('post', 'page'),
            'post_status' => array('publish'),
            'include_acf' => true,
            'include_taxonomies' => true,
        ),
        'events-upcoming' => array(
            'name' => 'Upcoming Events',
            'description' => 'Export future events with ACF fields',
            'post_type' => 'events',
            'post_status' => array('publish'),
            'date_from' => 'now',
            'include_acf' => true,
        ),
    ),
    
    /**
     * Feature Flags
     * 
     * Enable or disable specific plugin features.
     */
    'features' => array(
        'enable_export' => true,            // Enable export functionality
        'enable_import' => true,            // Enable import functionality
        'enable_preview' => true,           // Enable import preview
        'enable_batch_import' => true,      // Enable batch processing
        'enable_auto_cleanup' => true,      // Auto-delete old CSV files
        'enable_download_links' => true,    // Show download links
    ),
    
    /**
     * File Management
     * 
     * Control how exported CSV files are handled.
     */
    'file_management' => array(
        'cleanup_days' => 1,                // Days before auto-delete (0 = disabled)
        'max_file_size' => 50,              // Max upload size in MB
        'allowed_extensions' => array('csv'), // Allowed import file types
    ),
    
    /**
     * Performance Settings
     * 
     * Adjust for server capabilities.
     */
    'performance' => array(
        'batch_size' => 100,                // Posts per batch
        'memory_limit' => '256M',           // PHP memory limit
        'execution_time' => 300,            // Max execution time in seconds
    ),
    
    /**
     * CSV Formatting
     * 
     * Control CSV output format.
     */
    'csv_settings' => array(
        'delimiter' => ',',                 // CSV delimiter
        'enclosure' => '"',                 // Text enclosure
        'escape' => '\\',                   // Escape character
        'encoding' => 'UTF-8',              // File encoding
        'include_bom' => true,              // UTF-8 BOM for Excel compatibility
    ),
    
    /**
     * Logging & Debugging
     * 
     * Control error logging and debugging output.
     */
    'logging' => array(
        'enable_logging' => true,           // Enable error logging
        'log_level' => 'error',             // error, warning, info, debug
        'log_file' => 'qry-debug.log',   // Log file name
        'log_imports' => true,              // Log all import operations
        'log_exports' => true,              // Log all export operations
    ),
    
    /**
     * Admin UI Customization
     * 
     * Customize the admin interface appearance.
     */
    'admin_ui' => array(
        'menu_position' => null,            // Menu position (null = default under Tools)
        'menu_icon' => 'dashicons-database-export', // Menu icon
        'show_info_box' => true,            // Show info boxes on pages
        'show_statistics' => true,          // Show export/import statistics
    ),
    
    /**
     * Email Notifications (Future Feature)
     * 
     * Send notifications for completed imports/exports.
     * Coming in v1.2.0
     */
    'notifications' => array(
        'enable_email' => false,            // Send email notifications
        'admin_email' => get_option('admin_email'), // Notification email
        'notify_on_export' => false,        // Notify on export completion
        'notify_on_import' => false,        // Notify on import completion
        'notify_on_error' => true,          // Notify on errors
    ),
    
    /**
     * Advanced Options
     * 
     * For advanced users and developers.
     */
    'advanced' => array(
        'disable_nonce_check' => false,     // Disable nonce verification (NOT recommended)
        'allow_all_users' => false,         // Allow non-admin users (NOT recommended)
        'custom_upload_dir' => '',          // Custom upload directory path
        'debug_mode' => false,              // Enable debug mode
    ),
);
