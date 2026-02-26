<?php
/**
 * Plugin Name: Quarry
 * Plugin URI: https://github.com/robhudson-bot/quarry
 * Description: WordPress site scanner and data manager. Discover your site structure, export/import metadata with ACF fields, and collaborate via Google Sheets.
 * Version: 2.1.0
 * Author: Rob Hudson
 * Author URI: https://robhudson.ca
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: quarry
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('QRY_VERSION', '2.1.0');
define('QRY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('QRY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('QRY_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('QRY_MIN_WP_VERSION', '5.8');

// GitHub token for checking plugin updates.
// Read-only fine-grained PAT scoped to robhudson-bot/quarry.
// Supports both QUARRY_UPDATE_TOKEN (new) and RIA_DM_UPDATE_TOKEN (legacy).
if ( ! defined( 'QUARRY_UPDATE_TOKEN' ) ) {
    if ( defined( 'RIA_DM_UPDATE_TOKEN' ) && RIA_DM_UPDATE_TOKEN ) {
        define( 'QUARRY_UPDATE_TOKEN', RIA_DM_UPDATE_TOKEN );
    } else {
        define( 'QUARRY_UPDATE_TOKEN', '' );
    }
}

// Load optional site-specific configuration
$qry_config_file = QRY_PLUGIN_DIR . 'config/config.php';
if (file_exists($qry_config_file)) {
    $qry_config = include $qry_config_file;
    define('QRY_CONFIG', $qry_config);
} else {
    define('QRY_CONFIG', array());
}

/**
 * Main Quarry Plugin Class
 */
class Quarry {
    
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
        // Lucide icon helper
        require_once QRY_PLUGIN_DIR . 'includes/icons.php';

        // Core utilities
        require_once QRY_PLUGIN_DIR . 'includes/class-csv-processor.php';
        require_once QRY_PLUGIN_DIR . 'includes/class-acf-handler.php';
        require_once QRY_PLUGIN_DIR . 'includes/class-taxonomy-handler.php';

        // New metadata-focused exporters (v1.2.0+)
        require_once QRY_PLUGIN_DIR . 'includes/class-metadata-exporter.php';
        require_once QRY_PLUGIN_DIR . 'includes/class-google-sheets.php';

        // Importer
        require_once QRY_PLUGIN_DIR . 'includes/class-importer.php';

        // Site scanner (v2.0.0+)
        require_once QRY_PLUGIN_DIR . 'includes/class-site-scanner.php';

        // Admin interface
        require_once QRY_PLUGIN_DIR . 'includes/class-admin.php';

        // GitHub auto-updater
        require_once QRY_PLUGIN_DIR . 'includes/class-github-updater.php';

        // Diagnostic tools (can be accessed via URL parameter)
        if (is_admin() && isset($_GET['page']) && $_GET['page'] === 'quarry-diagnostic') {
            require_once QRY_PLUGIN_DIR . 'includes/diagnostic-acf-taxonomies.php';
        }

        // Legacy exporters (deprecated - kept for reference only)
        // These are no longer loaded by default as they produce files too large for Google Sheets
        // require_once QRY_PLUGIN_DIR . 'includes/class-exporter.php';
        // require_once QRY_PLUGIN_DIR . 'includes/class-rest-exporter.php';
        // require_once QRY_PLUGIN_DIR . 'includes/class-exporter-improved.php';
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('plugins_loaded', array($this, 'check_dependencies'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        
        // Initialize admin interface
        if (is_admin()) {
            QRY_Admin::get_instance();
        }

        // Initialize GitHub updater
        $updater = new QRY_GitHub_Updater();
        $updater->init();
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
                <strong>Quarry:</strong> 
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
        if (strpos($hook, 'quarry') === false) {
            return;
        }
        
        wp_enqueue_style(
            'qry-admin',
            QRY_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            QRY_VERSION
        );
        
        wp_enqueue_script(
            'qry-admin',
            QRY_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            QRY_VERSION,
            true
        );
        
        // Localize script for AJAX
        wp_localize_script('qry-admin', 'quarry', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('qry_nonce'),
            'strings' => array(
                'processing' => __('Processing...', 'quarry'),
                'complete' => __('Complete!', 'quarry'),
                'error' => __('Error occurred', 'quarry'),
            )
        ));
    }
}

/**
 * Initialize the plugin
 */
function qry_init() {
    return Quarry::get_instance();
}

// Start the plugin
add_action('plugins_loaded', 'qry_init');

/**
 * Get configuration value
 * 
 * @param string $key Configuration key (supports dot notation: 'export_defaults.include_acf')
 * @param mixed $default Default value if key not found
 * @return mixed Configuration value or default
 */
function qry_get_config($key, $default = null) {
    $config = QRY_CONFIG;
    
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
    $qry_dir = $upload_dir['basedir'] . '/quarry';
    
    if (!file_exists($qry_dir)) {
        wp_mkdir_p($qry_dir);
    }
    
    // Add index.php to prevent directory listing
    $index_file = $qry_dir . '/index.php';
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
    $qry_dir = $upload_dir['basedir'] . '/quarry';
    
    if (file_exists($qry_dir)) {
        $files = glob($qry_dir . '/*');
        $now = time();
        
        foreach ($files as $file) {
            if (is_file($file) && $now - filemtime($file) >= 86400) {
                @unlink($file);
            }
        }
    }
});
