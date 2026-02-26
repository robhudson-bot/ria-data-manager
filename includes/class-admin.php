<?php
/**
 * Admin Class
 * Handles WordPress admin interface
 */

if (!defined('ABSPATH')) {
    exit;
}

class QRY_Admin {
    
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
        add_action('admin_init', array($this, 'handle_rescan'));
        add_action('wp_ajax_qry_export', array($this, 'ajax_export'));
        add_action('wp_ajax_qry_import', array($this, 'ajax_import'));
        add_action('wp_ajax_qry_preview_import', array($this, 'ajax_preview_import'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_management_page(
            __('Quarry', 'quarry'),
            __('Quarry', 'quarry'),
            'manage_options',
            'quarry',
            array($this, 'render_admin_page')
        );
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'dashboard';

        ?>
        <div class="wrap qry-admin">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <h2 class="nav-tab-wrapper">
                <a href="?page=quarry&tab=dashboard"
                   class="nav-tab <?php echo $active_tab === 'dashboard' ? 'nav-tab-active' : ''; ?>">
                    <?php echo qry_icon( 'layout-dashboard', 16 ); ?>
                    <?php _e('Dashboard', 'quarry'); ?>
                </a>
                <a href="?page=quarry&tab=export"
                   class="nav-tab <?php echo $active_tab === 'export' ? 'nav-tab-active' : ''; ?>">
                    <?php echo qry_icon( 'download', 16 ); ?>
                    <?php _e('Export', 'quarry'); ?>
                </a>
                <a href="?page=quarry&tab=import"
                   class="nav-tab <?php echo $active_tab === 'import' ? 'nav-tab-active' : ''; ?>">
                    <?php echo qry_icon( 'upload', 16 ); ?>
                    <?php _e('Import', 'quarry'); ?>
                </a>
            </h2>

            <div class="qry-tab-content">
                <?php
                switch ($active_tab) {
                    case 'export':
                        $this->render_export_tab();
                        break;
                    case 'import':
                        $this->render_import_tab();
                        break;
                    case 'dashboard':
                    default:
                        $this->render_dashboard_tab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render dashboard tab
     */
    private function render_dashboard_tab() {
        include QRY_PLUGIN_DIR . 'templates/dashboard-page.php';
    }

    /**
     * Render export tab
     */
    private function render_export_tab() {
        include QRY_PLUGIN_DIR . 'templates/export-page.php';
    }

    /**
     * Render import tab
     */
    private function render_import_tab() {
        include QRY_PLUGIN_DIR . 'templates/import-page.php';
    }

    /**
     * Handle rescan request from dashboard
     */
    public function handle_rescan() {
        if ( ! isset( $_GET['qry_rescan'] ) ) {
            return;
        }

        if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'qry_rescan' ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        QRY_Site_Scanner::clear_cache();
        QRY_Site_Scanner::scan( true );

        wp_safe_redirect( admin_url( 'tools.php?page=quarry&tab=dashboard&qry_rescanned=1' ) );
        exit;
    }
    
    /**
     * AJAX export handler - Uses metadata-only export for Google Sheets
     *
     * @since 1.2.0 - Simplified to only use metadata exporter
     */
    public function ajax_export() {
        check_ajax_referer('qry_nonce', 'nonce');

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
        $result = QRY_Metadata_Exporter::export($args);

        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message(),
            ));
        }

        // Get download URL and stats
        $download_url = QRY_CSV_Processor::get_download_url($result);
        $stats = QRY_Metadata_Exporter::get_export_stats($result);

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
        check_ajax_referer('qry_nonce', 'nonce');
        
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
        $target_dir = $upload_dir['basedir'] . '/quarry/';
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
        $default_post_type = isset($_POST['default_post_type']) ? sanitize_text_field($_POST['default_post_type']) : '';

        // Prepare import arguments
        $args = array(
            'update_existing' => $update_existing,
            'create_taxonomies' => $create_taxonomies,
            'skip_on_error' => $skip_on_error,
            'default_post_type' => $default_post_type,
        );
        
        // Perform import
        $result = QRY_Importer::import($target_file, $args);
        
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
     * AJAX preview import handler - Shows before/after comparison
     */
    public function ajax_preview_import() {
        check_ajax_referer('qry_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        // Check if file was uploaded
        if (empty($_FILES['csv_file'])) {
            wp_send_json_error(array('message' => 'No file uploaded'));
        }

        $file = $_FILES['csv_file'];

        // Read CSV
        $csv_data = QRY_CSV_Processor::read_csv($file['tmp_name']);

        if (is_wp_error($csv_data)) {
            wp_send_json_error(array('message' => $csv_data->get_error_message()));
        }

        // Analyze changes by comparing CSV data to current WordPress data
        $changes = $this->analyze_import_changes($csv_data['data'], $csv_data['headers']);

        wp_send_json_success(array(
            'total_rows' => $csv_data['total_rows'],
            'summary' => $changes['summary'],
            'changes' => $changes['details'],
            'warnings' => $changes['warnings'],
        ));
    }

    /**
     * Analyze import changes - compare CSV data to current WordPress data
     *
     * @param array $csv_rows CSV data rows
     * @param array $headers CSV headers
     * @return array Analysis results with summary and detailed changes
     */
    private function analyze_import_changes($csv_rows, $headers) {
        $summary = array(
            'total' => count($csv_rows),
            'updates' => 0,
            'creates' => 0,
            'unchanged' => 0,
            'taxonomy_changes' => 0,
        );

        $details = array();
        $warnings = array();
        $max_preview = 10; // Show details for first 10 rows

        foreach ($csv_rows as $index => $row) {
            $post_id = isset($row['ID']) ? intval($row['ID']) : 0;
            $post_title = isset($row['post_title']) ? $row['post_title'] : 'Untitled';

            // Check if post exists
            $existing_post = $post_id ? get_post($post_id) : null;

            if (!$existing_post) {
                $summary['creates']++;
                if ($index < $max_preview) {
                    $details[] = array(
                        'type' => 'create',
                        'title' => $post_title,
                        'id' => null,
                        'changes' => array(
                            array(
                                'field' => 'New Post',
                                'old' => '-',
                                'new' => $post_title,
                            )
                        ),
                    );
                }
                continue;
            }

            // Compare existing post to CSV data
            $field_changes = array();

            // Check taxonomy fields specifically
            foreach ($row as $field => $new_value) {
                if (strpos($field, 'tax_') === 0 && !empty($new_value)) {
                    $taxonomy = substr($field, 4);

                    if (taxonomy_exists($taxonomy)) {
                        // Get current terms
                        $current_terms = wp_get_post_terms($post_id, $taxonomy, array('fields' => 'names'));
                        $current_value = is_array($current_terms) ? implode(', ', $current_terms) : '';

                        // Normalize new value (handle line breaks, extra spaces)
                        $new_terms = array_map('trim', explode(',', $new_value));
                        $new_terms = array_filter($new_terms);
                        $normalized_new = implode(', ', $new_terms);

                        // Compare
                        if ($current_value !== $normalized_new) {
                            $field_changes[] = array(
                                'field' => $field,
                                'field_label' => 'Taxonomy: ' . $taxonomy,
                                'old' => $current_value ?: '(empty)',
                                'new' => $normalized_new,
                            );
                            $summary['taxonomy_changes']++;
                        }
                    }
                }
            }

            // Check other key fields
            $check_fields = array(
                'post_title' => 'Title',
                'post_status' => 'Status',
                'post_excerpt' => 'Excerpt',
            );

            foreach ($check_fields as $field => $label) {
                if (isset($row[$field])) {
                    $current_value = $existing_post->$field ?? '';
                    $new_value = $row[$field];

                    if ($current_value !== $new_value && !empty($new_value)) {
                        $field_changes[] = array(
                            'field' => $field,
                            'field_label' => $label,
                            'old' => $this->truncate_value($current_value, 50),
                            'new' => $this->truncate_value($new_value, 50),
                        );
                    }
                }
            }

            if (!empty($field_changes)) {
                $summary['updates']++;
                if ($index < $max_preview) {
                    $details[] = array(
                        'type' => 'update',
                        'title' => $existing_post->post_title,
                        'id' => $post_id,
                        'edit_url' => get_edit_post_link($post_id, 'raw'),
                        'changes' => $field_changes,
                    );
                }
            } else {
                $summary['unchanged']++;
            }
        }

        // Add warning if many rows will be affected
        if ($summary['updates'] > 50) {
            $warnings[] = 'Large import: ' . $summary['updates'] . ' posts will be updated. Consider testing with a smaller batch first.';
        }

        return array(
            'summary' => $summary,
            'details' => $details,
            'warnings' => $warnings,
        );
    }

    /**
     * Truncate value for display
     */
    private function truncate_value($value, $length = 50) {
        if (strlen($value) > $length) {
            return substr($value, 0, $length) . '...';
        }
        return $value ?: '(empty)';
    }
}
