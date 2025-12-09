<?php
/**
 * Admin Class
 * Handles WordPress admin interface
 */

if (!defined('ABSPATH')) {
    exit;
}

class RIA_DM_Admin {
    
    /**
     * Single instance
     */
    private static $instance = null;
    
    /**
     * Get instance
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
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_ria_dm_export', array($this, 'ajax_export'));
        add_action('wp_ajax_ria_dm_import', array($this, 'ajax_import'));
        add_action('wp_ajax_ria_dm_preview_import', array($this, 'ajax_preview_import'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_management_page(
            __('RIA Data Manager', 'ria-data-manager'),
            __('RIA Data Manager', 'ria-data-manager'),
            'manage_options',
            'ria-data-manager',
            array($this, 'render_admin_page')
        );
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'export';
        
        ?>
        <div class="wrap ria-dm-admin">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <h2 class="nav-tab-wrapper">
                <a href="?page=ria-data-manager&tab=export" 
                   class="nav-tab <?php echo $active_tab === 'export' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Export', 'ria-data-manager'); ?>
                </a>
                <a href="?page=ria-data-manager&tab=import" 
                   class="nav-tab <?php echo $active_tab === 'import' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Import', 'ria-data-manager'); ?>
                </a>
            </h2>
            
            <div class="ria-dm-tab-content">
                <?php
                if ($active_tab === 'export') {
                    $this->render_export_tab();
                } else {
                    $this->render_import_tab();
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render export tab
     */
    private function render_export_tab() {
        include RIA_DM_PLUGIN_DIR . 'templates/export-page.php';
    }
    
    /**
     * Render import tab
     */
    private function render_import_tab() {
        include RIA_DM_PLUGIN_DIR . 'templates/import-page.php';
    }
    
    /**
     * AJAX export handler - Uses metadata-only export for Google Sheets
     *
     * @since 1.2.0 - Simplified to only use metadata exporter
     */
    public function ajax_export() {
        check_ajax_referer('ria_dm_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        // Get export parameters
        $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : 'post';
        $post_status = isset($_POST['post_status']) ? array_map('sanitize_text_field', $_POST['post_status']) : array('publish');
        $include_acf = isset($_POST['include_acf']) && $_POST['include_acf'] === 'true';
        $include_taxonomies = isset($_POST['include_taxonomies']) && $_POST['include_taxonomies'] === 'true';
        $include_featured_image = isset($_POST['include_featured_image']) && $_POST['include_featured_image'] === 'true';
        $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '';
        $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '';

        // Prepare export arguments
        $args = array(
            'post_type' => $post_type,
            'post_status' => $post_status,
            'include_acf' => $include_acf,
            'include_taxonomies' => $include_taxonomies,
            'include_featured_image' => $include_featured_image,
            'include_content_preview' => true,  // Add 500-char preview
            'preview_length' => 500,
            'date_from' => $date_from,
            'date_to' => $date_to,
        );

        // Use metadata-only export (optimized for Google Sheets)
        $result = RIA_DM_Metadata_Exporter::export($args);

        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message(),
            ));
        }

        // Get download URL and stats
        $download_url = RIA_DM_CSV_Processor::get_download_url($result);
        $stats = RIA_DM_Metadata_Exporter::get_export_stats($result);

        wp_send_json_success(array(
            'message' => 'Metadata export completed - ready for Google Sheets',
            'download_url' => $download_url,
            'filename' => basename($result),
            'filesize' => $stats['filesize_formatted'],
            'row_count' => $stats['row_count'],
            'google_sheets_compatible' => $stats['google_sheets_compatible'],
            'info' => 'This export excludes full post_content for Google Sheets compatibility. Content preview (500 chars) included for reference.',
        ));
    }
    
    /**
     * AJAX import handler
     */
    public function ajax_import() {
        check_ajax_referer('ria_dm_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        // Check if file was uploaded
        if (empty($_FILES['csv_file'])) {
            wp_send_json_error(array('message' => 'No file uploaded'));
        }
        
        $file = $_FILES['csv_file'];
        
        // Validate file type
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($file_ext !== 'csv') {
            wp_send_json_error(array('message' => 'File must be a CSV'));
        }
        
        // Move uploaded file
        $upload_dir = wp_upload_dir();
        $target_dir = $upload_dir['basedir'] . '/ria-data-manager/';
        $target_file = $target_dir . basename($file['name']);
        
        if (!file_exists($target_dir)) {
            wp_mkdir_p($target_dir);
        }
        
        if (!move_uploaded_file($file['tmp_name'], $target_file)) {
            wp_send_json_error(array('message' => 'Failed to upload file'));
        }
        
        // Get import parameters
        $update_existing = isset($_POST['update_existing']) && $_POST['update_existing'] === 'true';
        $create_taxonomies = isset($_POST['create_taxonomies']) && $_POST['create_taxonomies'] === 'true';
        $skip_on_error = isset($_POST['skip_on_error']) && $_POST['skip_on_error'] === 'true';
        
        // Prepare import arguments
        $args = array(
            'update_existing' => $update_existing,
            'create_taxonomies' => $create_taxonomies,
            'skip_on_error' => $skip_on_error,
        );
        
        // Perform import
        $result = RIA_DM_Importer::import($target_file, $args);
        
        // Clean up file
        @unlink($target_file);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success(array(
            'message' => 'Import completed',
            'results' => $result,
        ));
    }
    
    /**
     * AJAX preview import handler
     */
    public function ajax_preview_import() {
        check_ajax_referer('ria_dm_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        // Check if file was uploaded
        if (empty($_FILES['csv_file'])) {
            wp_send_json_error(array('message' => 'No file uploaded'));
        }
        
        $file = $_FILES['csv_file'];
        
        // Read CSV
        $csv_data = RIA_DM_CSV_Processor::read_csv($file['tmp_name']);
        
        if (is_wp_error($csv_data)) {
            wp_send_json_error(array('message' => $csv_data->get_error_message()));
        }
        
        // Get field mapping suggestions
        $suggested_mapping = RIA_DM_CSV_Processor::suggest_field_mapping($csv_data['headers']);
        
        // Get first 5 rows for preview
        $preview_data = array_slice($csv_data['data'], 0, 5);
        
        wp_send_json_success(array(
            'headers' => $csv_data['headers'],
            'suggested_mapping' => $suggested_mapping,
            'preview_data' => $preview_data,
            'total_rows' => $csv_data['total_rows'],
        ));
    }
}
