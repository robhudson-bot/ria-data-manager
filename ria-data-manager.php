<?php
/**
 * Plugin Name: RIA Data Manager
 * Plugin URI: https://github.com/robhudson-bot/ria-data-manager
 * Description: Export and import WordPress metadata (posts, pages, custom post types) with ACF fields for collaborative editing in Google Sheets. Optimized for team workflows.
 * Version: 1.2.0
 * Author: Rob Hudson
 * Author URI: https://the-ria.ca
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ria-data-manager
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('RIA_DM_VERSION', '1.2.0');
define('RIA_DM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RIA_DM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('RIA_DM_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Load optional site-specific configuration
$ria_dm_config_file = RIA_DM_PLUGIN_DIR . 'config/config.php';
if (file_exists($ria_dm_config_file)) {
    $ria_dm_config = include $ria_dm_config_file;
    define('RIA_DM_CONFIG', $ria_dm_config);
} else {
    define('RIA_DM_CONFIG', array());
}

/**
 * Main RIA Data Manager Class
 */
class RIA_Data_Manager {
    
    /**
     * Single instance of the class
     */
    private static $instance = null;
    
    /**
     * Get single instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    /**
     * Load required files
     */
    private function load_dependencies() {
        // Core utilities
        require_once RIA_DM_PLUGIN_DIR . 'includes/class-csv-processor.php';
        require_once RIA_DM_PLUGIN_DIR . 'includes/class-acf-handler.php';
        require_once RIA_DM_PLUGIN_DIR . 'includes/class-taxonomy-handler.php';

        // New metadata-focused exporters (v1.2.0+)
        require_once RIA_DM_PLUGIN_DIR . 'includes/class-metadata-exporter.php';
        require_once RIA_DM_PLUGIN_DIR . 'includes/class-google-sheets.php';

        // Importer
        require_once RIA_DM_PLUGIN_DIR . 'includes/class-importer.php';

        // Admin interface
        require_once RIA_DM_PLUGIN_DIR . 'includes/class-admin.php';

        // Legacy exporters (deprecated - kept for reference only)
        // These are no longer loaded by default as they produce files too large for Google Sheets
        // require_once RIA_DM_PLUGIN_DIR . 'includes/class-exporter.php';
        // require_once RIA_DM_PLUGIN_DIR . 'includes/class-rest-exporter.php';
        // require_once RIA_DM_PLUGIN_DIR . 'includes/class-exporter-improved.php';
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('plugins_loaded', array($this, 'check_dependencies'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        
        // Initialize admin interface
        if (is_admin()) {
            RIA_DM_Admin::get_instance();
        }
    }
    
    /**
     * Check plugin dependencies
     */
    public function check_dependencies() {
        // Check if ACF Pro is active
        if (!class_exists('ACF')) {
            add_action('admin_notices', array($this, 'acf_missing_notice'));
        }
    }
    
    /**
     * ACF missing notice
     */
    public function acf_missing_notice() {
        ?>
        <div class="notice notice-warning">
            <p>
                <strong>RIA Data Manager:</strong> 
                Advanced Custom Fields (ACF) is recommended for full functionality. 
                Some features may be limited without ACF.
            </p>
        </div>
        <?php
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_assets($hook) {
        // Only load on our plugin pages
        if (strpos($hook, 'ria-data-manager') === false) {
            return;
        }
        
        wp_enqueue_style(
            'ria-dm-admin',
            RIA_DM_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            RIA_DM_VERSION
        );
        
        wp_enqueue_script(
            'ria-dm-admin',
            RIA_DM_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            RIA_DM_VERSION,
            true
        );
        
        // Localize script for AJAX
        wp_localize_script('ria-dm-admin', 'riaDM', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ria_dm_nonce'),
            'strings' => array(
                'processing' => __('Processing...', 'ria-data-manager'),
                'complete' => __('Complete!', 'ria-data-manager'),
                'error' => __('Error occurred', 'ria-data-manager'),
            )
        ));
    }
}

/**
 * Initialize the plugin
 */
function ria_dm_init() {
    return RIA_Data_Manager::get_instance();
}

// Start the plugin
add_action('plugins_loaded', 'ria_dm_init');

/**
 * Get configuration value
 * 
 * @param string $key Configuration key (supports dot notation: 'export_defaults.include_acf')
 * @param mixed $default Default value if key not found
 * @return mixed Configuration value or default
 */
function ria_dm_get_config($key, $default = null) {
    $config = RIA_DM_CONFIG;
    
    // Support dot notation for nested values
    if (strpos($key, '.') !== false) {
        $keys = explode('.', $key);
        foreach ($keys as $k) {
            if (isset($config[$k])) {
                $config = $config[$k];
            } else {
                return $default;
            }
        }
        return $config;
    }
    
    return isset($config[$key]) ? $config[$key] : $default;
}

/**
 * Activation hook
 */
register_activation_hook(__FILE__, function() {
    // Create upload directory if it doesn't exist
    $upload_dir = wp_upload_dir();
    $ria_dm_dir = $upload_dir['basedir'] . '/ria-data-manager';
    
    if (!file_exists($ria_dm_dir)) {
        wp_mkdir_p($ria_dm_dir);
    }
    
    // Add index.php to prevent directory listing
    $index_file = $ria_dm_dir . '/index.php';
    if (!file_exists($index_file)) {
        file_put_contents($index_file, '<?php // Silence is golden');
    }
});

/**
 * Deactivation hook
 */
register_deactivation_hook(__FILE__, function() {
    // Clean up temporary files older than 24 hours
    $upload_dir = wp_upload_dir();
    $ria_dm_dir = $upload_dir['basedir'] . '/ria-data-manager';
    
    if (file_exists($ria_dm_dir)) {
        $files = glob($ria_dm_dir . '/*');
        $now = time();
        
        foreach ($files as $file) {
            if (is_file($file) && $now - filemtime($file) >= 86400) {
                @unlink($file);
            }
        }
    }
});
