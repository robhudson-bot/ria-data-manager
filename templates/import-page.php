<?php
/**
 * Import Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get available post types
$post_types = get_post_types(array('public' => true), 'objects');
?>

<div class="ria-dm-import-container">
    <div class="ria-dm-card">
        <h2><?php _e('Import Content from CSV', 'ria-data-manager'); ?></h2>
        
        <form id="ria-dm-import-form" method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('ria_dm_import', 'ria_dm_import_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="csv_file"><?php _e('CSV File', 'ria-data-manager'); ?></label>
                    </th>
                    <td>
                        <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
                        <p class="description">
                            <?php _e('Select a CSV file to import.', 'ria-data-manager'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="default_post_type"><?php _e('Default Post Type', 'ria-data-manager'); ?></label>
                    </th>
                    <td>
                        <select name="default_post_type" id="default_post_type" class="regular-text">
                            <option value=""><?php _e('— Use post_type column from CSV —', 'ria-data-manager'); ?></option>
                            <?php foreach ($post_types as $slug => $post_type) : ?>
                                <option value="<?php echo esc_attr($slug); ?>">
                                    <?php echo esc_html($post_type->label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php _e('Select a post type to apply to all rows, or leave blank to use the post_type column from CSV.', 'ria-data-manager'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label><?php _e('Import Options', 'ria-data-manager'); ?></label>
                    </th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="update_existing" value="1" checked>
                                <?php _e('Update existing posts (match by ID)', 'ria-data-manager'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="create_taxonomies" value="1" checked>
                                <?php _e('Create taxonomies if they don\'t exist', 'ria-data-manager'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="skip_on_error" value="1" checked>
                                <?php _e('Skip rows with errors and continue', 'ria-data-manager'); ?>
                            </label>
                        </fieldset>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="button" class="button button-secondary" id="ria-dm-preview-btn">
                    <span class="dashicons dashicons-visibility"></span>
                    <?php _e('Preview Import', 'ria-data-manager'); ?>
                </button>
                <button type="submit" class="button button-primary button-large" id="ria-dm-import-btn">
                    <span class="dashicons dashicons-upload"></span>
                    <?php _e('Import CSV', 'ria-data-manager'); ?>
                </button>
            </p>
        </form>
        
        <!-- Progress & Results -->
        <div id="ria-dm-import-progress" class="ria-dm-progress" style="display: none;">
            <div class="ria-dm-progress-bar">
                <div class="ria-dm-progress-fill"></div>
            </div>
            <p class="ria-dm-status-text"><?php _e('Importing...', 'ria-data-manager'); ?></p>
        </div>
        
        <div id="ria-dm-import-result" class="ria-dm-result" style="display: none;"></div>
        
        <!-- Preview Section -->
        <div id="ria-dm-preview-section" class="ria-dm-preview" style="display: none;">
            <h3><?php _e('Import Preview', 'ria-data-manager'); ?></h3>
            <div id="ria-dm-preview-content"></div>
        </div>
    </div>
    
    <!-- Import Info Box -->
    <div class="ria-dm-info-box">
        <h3><?php _e('About CSV Import', 'ria-data-manager'); ?></h3>
        <p>
            <?php _e('Import content from a CSV file into WordPress. The CSV file should follow the same structure as the exported files.', 'ria-data-manager'); ?>
        </p>
        <h4><?php _e('Required Fields:', 'ria-data-manager'); ?></h4>
        <ul>
            <li><code>post_title</code> - <?php _e('Post title (required)', 'ria-data-manager'); ?></li>
            <li><code>post_type</code> - <?php _e('Post type (required)', 'ria-data-manager'); ?></li>
        </ul>
        <h4><?php _e('Optional Fields:', 'ria-data-manager'); ?></h4>
        <ul>
            <li><code>ID</code> - <?php _e('Post ID (for updating existing posts)', 'ria-data-manager'); ?></li>
            <li><code>post_content</code> - <?php _e('Post content/body', 'ria-data-manager'); ?></li>
            <li><code>post_status</code> - <?php _e('publish, draft, pending, private', 'ria-data-manager'); ?></li>
            <li><code>post_date</code> - <?php _e('Publish date', 'ria-data-manager'); ?></li>
            <li><code>featured_image</code> - <?php _e('Image URL or attachment ID', 'ria-data-manager'); ?></li>
            <li><code>tax_category</code> - <?php _e('Categories (comma-separated)', 'ria-data-manager'); ?></li>
            <li><code>tax_post_tag</code> - <?php _e('Tags (comma-separated)', 'ria-data-manager'); ?></li>
            <li><code>acf_*</code> - <?php _e('ACF fields (prefix with acf_)', 'ria-data-manager'); ?></li>
        </ul>
        <h4><?php _e('Tips:', 'ria-data-manager'); ?></h4>
        <ul>
            <li><?php _e('Use Preview to check your data before importing', 'ria-data-manager'); ?></li>
            <li><?php _e('Large imports are processed in batches', 'ria-data-manager'); ?></li>
            <li><?php _e('Errors are logged and can be reviewed after import', 'ria-data-manager'); ?></li>
            <li><?php _e('Always backup your database before importing', 'ria-data-manager'); ?></li>
        </ul>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Preview Import - Shows before/after comparison
    $('#ria-dm-preview-btn').on('click', function(e) {
        e.preventDefault();

        var fileInput = $('#csv_file')[0];
        if (!fileInput.files.length) {
            alert('<?php _e('Please select a CSV file first', 'ria-data-manager'); ?>');
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true).text('Analyzing...');

        var formData = new FormData();
        formData.append('action', 'ria_dm_preview_import');
        formData.append('nonce', riaDM.nonce);
        formData.append('csv_file', fileInput.files[0]);

        $.ajax({
            url: riaDM.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    var data = response.data;
                    var html = '';

                    // Summary Box
                    html += '<div class="ria-dm-preview-summary">';
                    html += '<h4>Import Summary</h4>';
                    html += '<div class="ria-dm-summary-stats">';
                    html += '<div class="ria-dm-stat"><span class="ria-dm-stat-num">' + data.summary.total + '</span><span class="ria-dm-stat-label">Total Rows</span></div>';
                    html += '<div class="ria-dm-stat ria-dm-stat-update"><span class="ria-dm-stat-num">' + data.summary.updates + '</span><span class="ria-dm-stat-label">Updates</span></div>';
                    html += '<div class="ria-dm-stat ria-dm-stat-create"><span class="ria-dm-stat-num">' + data.summary.creates + '</span><span class="ria-dm-stat-label">New Posts</span></div>';
                    html += '<div class="ria-dm-stat"><span class="ria-dm-stat-num">' + data.summary.unchanged + '</span><span class="ria-dm-stat-label">Unchanged</span></div>';
                    if (data.summary.taxonomy_changes > 0) {
                        html += '<div class="ria-dm-stat ria-dm-stat-tax"><span class="ria-dm-stat-num">' + data.summary.taxonomy_changes + '</span><span class="ria-dm-stat-label">Taxonomy Changes</span></div>';
                    }
                    html += '</div></div>';

                    // Warnings
                    if (data.warnings && data.warnings.length > 0) {
                        html += '<div class="ria-dm-warnings">';
                        data.warnings.forEach(function(warning) {
                            html += '<p class="ria-dm-warning">⚠️ ' + $('<div>').text(warning).html() + '</p>';
                        });
                        html += '</div>';
                    }

                    // Detailed Changes
                    if (data.changes && data.changes.length > 0) {
                        html += '<h4>Pending Changes (First ' + data.changes.length + ')</h4>';
                        html += '<div class="ria-dm-changes-list">';

                        data.changes.forEach(function(item) {
                            var typeClass = item.type === 'create' ? 'ria-dm-change-create' : 'ria-dm-change-update';
                            var typeLabel = item.type === 'create' ? 'NEW' : 'UPDATE';

                            html += '<div class="ria-dm-change-item ' + typeClass + '">';
                            html += '<div class="ria-dm-change-header">';
                            html += '<span class="ria-dm-change-type">' + typeLabel + '</span>';
                            html += '<strong>' + $('<div>').text(item.title).html() + '</strong>';
                            if (item.id) {
                                html += ' <span class="ria-dm-change-id">(ID: ' + item.id + ')</span>';
                            }
                            if (item.edit_url) {
                                html += ' <a href="' + item.edit_url + '" target="_blank" class="ria-dm-edit-link">Edit</a>';
                            }
                            html += '</div>';

                            html += '<div class="ria-dm-change-fields">';
                            item.changes.forEach(function(change) {
                                html += '<div class="ria-dm-field-change">';
                                html += '<span class="ria-dm-field-name">' + $('<div>').text(change.field_label || change.field).html() + '</span>';
                                html += '<div class="ria-dm-field-values">';
                                html += '<span class="ria-dm-old-value">' + $('<div>').text(change.old).html() + '</span>';
                                html += '<span class="ria-dm-arrow">→</span>';
                                html += '<span class="ria-dm-new-value">' + $('<div>').text(change.new).html() + '</span>';
                                html += '</div></div>';
                            });
                            html += '</div></div>';
                        });

                        html += '</div>';

                        if (data.summary.updates > data.changes.length) {
                            html += '<p class="ria-dm-more-changes">... and ' + (data.summary.updates - data.changes.length) + ' more updates</p>';
                        }
                    } else if (data.summary.updates === 0 && data.summary.creates === 0) {
                        html += '<p class="ria-dm-no-changes">No changes detected. All rows match existing data.</p>';
                    }

                    $('#ria-dm-preview-content').html(html);
                    $('#ria-dm-preview-section').show();
                } else {
                    alert('<?php _e('Error:', 'ria-data-manager'); ?> ' + response.data.message);
                }
            },
            complete: function() {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-visibility"></span> Preview Import');
            }
        });
    });
    
    // Import CSV
    $('#ria-dm-import-form').on('submit', function(e) {
        e.preventDefault();
        
        var fileInput = $('#csv_file')[0];
        if (!fileInput.files.length) {
            alert('<?php _e('Please select a CSV file first', 'ria-data-manager'); ?>');
            return;
        }
        
        if (!confirm('<?php _e('Are you sure you want to import this file? This will create or update content in your database.', 'ria-data-manager'); ?>')) {
            return;
        }
        
        var formData = new FormData();
        formData.append('action', 'ria_dm_import');
        formData.append('nonce', riaDM.nonce);
        formData.append('csv_file', fileInput.files[0]);
        formData.append('update_existing', $('input[name="update_existing"]').is(':checked'));
        formData.append('create_taxonomies', $('input[name="create_taxonomies"]').is(':checked'));
        formData.append('skip_on_error', $('input[name="skip_on_error"]').is(':checked'));
        formData.append('default_post_type', $('#default_post_type').val());
        
        // Show progress
        $('#ria-dm-import-progress').show();
        $('#ria-dm-import-result').hide();
        $('#ria-dm-import-btn').prop('disabled', true);
        $('#ria-dm-preview-btn').prop('disabled', true);
        
        // AJAX request
        $.ajax({
            url: riaDM.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                $('#ria-dm-import-progress').hide();
                
                if (response.success) {
                    var results = response.data.results;
                    var html = '<p><strong>' + riaDM.strings.complete + '</strong></p>';
                    html += '<ul>';
                    html += '<li><?php _e('Successfully imported:', 'ria-data-manager'); ?> ' + results.success + '</li>';
                    html += '<li><?php _e('Created new:', 'ria-data-manager'); ?> ' + results.created + '</li>';
                    html += '<li><?php _e('Updated existing:', 'ria-data-manager'); ?> ' + results.updated + '</li>';
                    html += '<li><?php _e('Failed:', 'ria-data-manager'); ?> ' + results.failed + '</li>';
                    html += '</ul>';
                    
                    if (results.errors && results.errors.length > 0) {
                        html += '<h4><?php _e('Errors:', 'ria-data-manager'); ?></h4>';
                        html += '<ul class="ria-dm-errors">';
                        results.errors.slice(0, 10).forEach(function(error) {
                            html += '<li>';
                            if (error.row) {
                                html += '<?php _e('Row', 'ria-data-manager'); ?> ' + error.row + ': ';
                            }
                            html += error.message || error;
                            html += '</li>';
                        });
                        if (results.errors.length > 10) {
                            html += '<li><em><?php _e('... and', 'ria-data-manager'); ?> ' + (results.errors.length - 10) + ' <?php _e('more errors', 'ria-data-manager'); ?></em></li>';
                        }
                        html += '</ul>';
                    }
                    
                    $('#ria-dm-import-result')
                        .removeClass('notice-error')
                        .addClass('notice notice-success')
                        .html(html)
                        .show();
                } else {
                    $('#ria-dm-import-result')
                        .removeClass('notice-success')
                        .addClass('notice notice-error')
                        .html('<p><strong>' + riaDM.strings.error + ':</strong> ' + response.data.message + '</p>')
                        .show();
                }
            },
            error: function() {
                $('#ria-dm-import-progress').hide();
                $('#ria-dm-import-result')
                    .removeClass('notice-success')
                    .addClass('notice notice-error')
                    .html('<p><strong>' + riaDM.strings.error + ':</strong> An unexpected error occurred.</p>')
                    .show();
            },
            complete: function() {
                $('#ria-dm-import-btn').prop('disabled', false);
                $('#ria-dm-preview-btn').prop('disabled', false);
            }
        });
    });
});
</script>
