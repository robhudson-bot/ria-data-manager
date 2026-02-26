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

<div class="qry-import-container">
    <div class="qry-card">
        <h2><?php _e('Import Content from CSV', 'quarry'); ?></h2>
        
        <form id="qry-import-form" method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('qry_import', 'qry_import_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="csv_file"><?php _e('CSV File', 'quarry'); ?></label>
                    </th>
                    <td>
                        <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
                        <p class="description">
                            <?php _e('Select a CSV file to import.', 'quarry'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="default_post_type"><?php _e('Default Post Type', 'quarry'); ?></label>
                    </th>
                    <td>
                        <select name="default_post_type" id="default_post_type" class="regular-text">
                            <option value=""><?php _e('— Use post_type column from CSV —', 'quarry'); ?></option>
                            <?php foreach ($post_types as $slug => $post_type) : ?>
                                <option value="<?php echo esc_attr($slug); ?>">
                                    <?php echo esc_html($post_type->label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php _e('Select a post type to apply to all rows, or leave blank to use the post_type column from CSV.', 'quarry'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label><?php _e('Import Options', 'quarry'); ?></label>
                    </th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="update_existing" value="1" checked>
                                <?php _e('Update existing posts (match by ID)', 'quarry'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="create_taxonomies" value="1" checked>
                                <?php _e('Create taxonomies if they don\'t exist', 'quarry'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="skip_on_error" value="1" checked>
                                <?php _e('Skip rows with errors and continue', 'quarry'); ?>
                            </label>
                        </fieldset>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="button" class="button button-secondary" id="qry-preview-btn">
                    <?php echo qry_icon( 'eye', 16 ); ?>
                    <?php _e('Preview Import', 'quarry'); ?>
                </button>
                <button type="submit" class="button button-primary button-large" id="qry-import-btn">
                    <?php echo qry_icon( 'upload', 16 ); ?>
                    <?php _e('Import CSV', 'quarry'); ?>
                </button>
            </p>
        </form>
        
        <!-- Progress & Results -->
        <div id="qry-import-progress" class="qry-progress" style="display: none;">
            <div class="qry-progress-bar">
                <div class="qry-progress-fill"></div>
            </div>
            <p class="qry-status-text"><?php _e('Importing...', 'quarry'); ?></p>
        </div>
        
        <div id="qry-import-result" class="qry-result" style="display: none;"></div>
        
        <!-- Preview Section -->
        <div id="qry-preview-section" class="qry-preview" style="display: none;">
            <h3><?php _e('Import Preview', 'quarry'); ?></h3>
            <div id="qry-preview-content"></div>
        </div>
    </div>
    
    <!-- Import Info Box -->
    <div class="qry-info-box">
        <h3><?php _e('About CSV Import', 'quarry'); ?></h3>
        <p>
            <?php _e('Import content from a CSV file into WordPress. The CSV file should follow the same structure as the exported files.', 'quarry'); ?>
        </p>
        <h4><?php _e('Required Fields:', 'quarry'); ?></h4>
        <ul>
            <li><code>post_title</code> - <?php _e('Post title (required)', 'quarry'); ?></li>
            <li><code>post_type</code> - <?php _e('Post type (required)', 'quarry'); ?></li>
        </ul>
        <h4><?php _e('Optional Fields:', 'quarry'); ?></h4>
        <ul>
            <li><code>ID</code> - <?php _e('Post ID (for updating existing posts)', 'quarry'); ?></li>
            <li><code>post_content</code> - <?php _e('Post content/body', 'quarry'); ?></li>
            <li><code>post_status</code> - <?php _e('publish, draft, pending, private', 'quarry'); ?></li>
            <li><code>post_date</code> - <?php _e('Publish date', 'quarry'); ?></li>
            <li><code>featured_image</code> - <?php _e('Image URL or attachment ID', 'quarry'); ?></li>
            <li><code>tax_category</code> - <?php _e('Categories (comma-separated)', 'quarry'); ?></li>
            <li><code>tax_post_tag</code> - <?php _e('Tags (comma-separated)', 'quarry'); ?></li>
            <li><code>acf_*</code> - <?php _e('ACF fields (prefix with acf_)', 'quarry'); ?></li>
        </ul>
        <h4><?php _e('Tips:', 'quarry'); ?></h4>
        <ul>
            <li><?php _e('Use Preview to check your data before importing', 'quarry'); ?></li>
            <li><?php _e('Large imports are processed in batches', 'quarry'); ?></li>
            <li><?php _e('Errors are logged and can be reviewed after import', 'quarry'); ?></li>
            <li><?php _e('Always backup your database before importing', 'quarry'); ?></li>
        </ul>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Preview Import - Shows before/after comparison
    $('#qry-preview-btn').on('click', function(e) {
        e.preventDefault();

        var fileInput = $('#csv_file')[0];
        if (!fileInput.files.length) {
            alert('<?php _e('Please select a CSV file first', 'quarry'); ?>');
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true).text('Analyzing...');

        var formData = new FormData();
        formData.append('action', 'qry_preview_import');
        formData.append('nonce', quarry.nonce);
        formData.append('csv_file', fileInput.files[0]);

        $.ajax({
            url: quarry.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    var data = response.data;
                    var html = '';

                    // Summary Box
                    html += '<div class="qry-preview-summary">';
                    html += '<h4>Import Summary</h4>';
                    html += '<div class="qry-summary-stats">';
                    html += '<div class="qry-stat"><span class="qry-stat-num">' + data.summary.total + '</span><span class="qry-stat-label">Total Rows</span></div>';
                    html += '<div class="qry-stat qry-stat-update"><span class="qry-stat-num">' + data.summary.updates + '</span><span class="qry-stat-label">Updates</span></div>';
                    html += '<div class="qry-stat qry-stat-create"><span class="qry-stat-num">' + data.summary.creates + '</span><span class="qry-stat-label">New Posts</span></div>';
                    html += '<div class="qry-stat"><span class="qry-stat-num">' + data.summary.unchanged + '</span><span class="qry-stat-label">Unchanged</span></div>';
                    if (data.summary.taxonomy_changes > 0) {
                        html += '<div class="qry-stat qry-stat-tax"><span class="qry-stat-num">' + data.summary.taxonomy_changes + '</span><span class="qry-stat-label">Taxonomy Changes</span></div>';
                    }
                    html += '</div></div>';

                    // Warnings
                    if (data.warnings && data.warnings.length > 0) {
                        html += '<div class="qry-warnings">';
                        data.warnings.forEach(function(warning) {
                            html += '<p class="qry-warning">⚠️ ' + $('<div>').text(warning).html() + '</p>';
                        });
                        html += '</div>';
                    }

                    // Detailed Changes
                    if (data.changes && data.changes.length > 0) {
                        html += '<h4>Pending Changes (First ' + data.changes.length + ')</h4>';
                        html += '<div class="qry-changes-list">';

                        data.changes.forEach(function(item) {
                            var typeClass = item.type === 'create' ? 'qry-change-create' : 'qry-change-update';
                            var typeLabel = item.type === 'create' ? 'NEW' : 'UPDATE';

                            html += '<div class="qry-change-item ' + typeClass + '">';
                            html += '<div class="qry-change-header">';
                            html += '<span class="qry-change-type">' + typeLabel + '</span>';
                            html += '<strong>' + $('<div>').text(item.title).html() + '</strong>';
                            if (item.id) {
                                html += ' <span class="qry-change-id">(ID: ' + item.id + ')</span>';
                            }
                            if (item.edit_url) {
                                html += ' <a href="' + item.edit_url + '" target="_blank" class="qry-edit-link">Edit</a>';
                            }
                            html += '</div>';

                            html += '<div class="qry-change-fields">';
                            item.changes.forEach(function(change) {
                                html += '<div class="qry-field-change">';
                                html += '<span class="qry-field-name">' + $('<div>').text(change.field_label || change.field).html() + '</span>';
                                html += '<div class="qry-field-values">';
                                html += '<span class="qry-old-value">' + $('<div>').text(change.old).html() + '</span>';
                                html += '<span class="qry-arrow">→</span>';
                                html += '<span class="qry-new-value">' + $('<div>').text(change.new).html() + '</span>';
                                html += '</div></div>';
                            });
                            html += '</div></div>';
                        });

                        html += '</div>';

                        if (data.summary.updates > data.changes.length) {
                            html += '<p class="qry-more-changes">... and ' + (data.summary.updates - data.changes.length) + ' more updates</p>';
                        }
                    } else if (data.summary.updates === 0 && data.summary.creates === 0) {
                        html += '<p class="qry-no-changes">No changes detected. All rows match existing data.</p>';
                    }

                    $('#qry-preview-content').html(html);
                    $('#qry-preview-section').show();
                } else {
                    alert('<?php _e('Error:', 'quarry'); ?> ' + response.data.message);
                }
            },
            complete: function() {
                $btn.prop('disabled', false).html('<svg class="qry-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg> Preview Import');
            }
        });
    });
    
    // Import CSV
    $('#qry-import-form').on('submit', function(e) {
        e.preventDefault();
        
        var fileInput = $('#csv_file')[0];
        if (!fileInput.files.length) {
            alert('<?php _e('Please select a CSV file first', 'quarry'); ?>');
            return;
        }
        
        if (!confirm('<?php _e('Are you sure you want to import this file? This will create or update content in your database.', 'quarry'); ?>')) {
            return;
        }
        
        var formData = new FormData();
        formData.append('action', 'qry_import');
        formData.append('nonce', quarry.nonce);
        formData.append('csv_file', fileInput.files[0]);
        formData.append('update_existing', $('input[name="update_existing"]').is(':checked'));
        formData.append('create_taxonomies', $('input[name="create_taxonomies"]').is(':checked'));
        formData.append('skip_on_error', $('input[name="skip_on_error"]').is(':checked'));
        formData.append('default_post_type', $('#default_post_type').val());
        
        // Show progress
        $('#qry-import-progress').show();
        $('#qry-import-result').hide();
        $('#qry-import-btn').prop('disabled', true);
        $('#qry-preview-btn').prop('disabled', true);
        
        // AJAX request
        $.ajax({
            url: quarry.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                $('#qry-import-progress').hide();
                
                if (response.success) {
                    var results = response.data.results;
                    var html = '<p><strong>' + quarry.strings.complete + '</strong></p>';
                    html += '<ul>';
                    html += '<li><?php _e('Created new:', 'quarry'); ?> ' + results.created + '</li>';
                    html += '<li><?php _e('Updated:', 'quarry'); ?> ' + results.updated + '</li>';
                    if (results.skipped > 0) {
                        html += '<li><?php _e('Skipped (unchanged):', 'quarry'); ?> ' + results.skipped + '</li>';
                    }
                    html += '<li><?php _e('Failed:', 'quarry'); ?> ' + results.failed + '</li>';
                    html += '</ul>';
                    
                    if (results.errors && results.errors.length > 0) {
                        html += '<h4><?php _e('Errors:', 'quarry'); ?></h4>';
                        html += '<ul class="qry-errors">';
                        results.errors.slice(0, 10).forEach(function(error) {
                            html += '<li>';
                            if (error.row) {
                                html += '<?php _e('Row', 'quarry'); ?> ' + error.row + ': ';
                            }
                            html += error.message || error;
                            html += '</li>';
                        });
                        if (results.errors.length > 10) {
                            html += '<li><em><?php _e('... and', 'quarry'); ?> ' + (results.errors.length - 10) + ' <?php _e('more errors', 'quarry'); ?></em></li>';
                        }
                        html += '</ul>';
                    }
                    
                    $('#qry-import-result')
                        .removeClass('notice-error')
                        .addClass('notice notice-success')
                        .html(html)
                        .show();
                } else {
                    $('#qry-import-result')
                        .removeClass('notice-success')
                        .addClass('notice notice-error')
                        .html('<p><strong>' + quarry.strings.error + ':</strong> ' + response.data.message + '</p>')
                        .show();
                }
            },
            error: function() {
                $('#qry-import-progress').hide();
                $('#qry-import-result')
                    .removeClass('notice-success')
                    .addClass('notice notice-error')
                    .html('<p><strong>' + quarry.strings.error + ':</strong> An unexpected error occurred.</p>')
                    .show();
            },
            complete: function() {
                $('#qry-import-btn').prop('disabled', false);
                $('#qry-preview-btn').prop('disabled', false);
            }
        });
    });
});
</script>
