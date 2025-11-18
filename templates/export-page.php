<?php
/**
 * Export Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$post_types = RIA_DM_Exporter::get_available_post_types();
?>

<div class="ria-dm-export-container">
    <div class="ria-dm-card">
        <h2><?php _e('Export Content to CSV', 'ria-data-manager'); ?></h2>
        
        <form id="ria-dm-export-form" method="post">
            <?php wp_nonce_field('ria_dm_export', 'ria_dm_export_nonce'); ?>
            
            <!-- Post Type Selection -->
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="post_type"><?php _e('Content Type', 'ria-data-manager'); ?></label>
                    </th>
                    <td>
                        <select name="post_type" id="post_type" class="regular-text">
                            <?php foreach ($post_types as $slug => $post_type) : ?>
                                <option value="<?php echo esc_attr($slug); ?>">
                                    <?php echo esc_html($post_type->label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php _e('Select the type of content to export', 'ria-data-manager'); ?>
                        </p>
                    </td>
                </tr>
                
                <!-- Post Status -->
                <tr>
                    <th scope="row">
                        <label><?php _e('Post Status', 'ria-data-manager'); ?></label>
                    </th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="post_status[]" value="publish" checked>
                                <?php _e('Published', 'ria-data-manager'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="post_status[]" value="draft">
                                <?php _e('Draft', 'ria-data-manager'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="post_status[]" value="pending">
                                <?php _e('Pending', 'ria-data-manager'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="post_status[]" value="private">
                                <?php _e('Private', 'ria-data-manager'); ?>
                            </label>
                        </fieldset>
                        <p class="description">
                            <?php _e('Select which post statuses to include', 'ria-data-manager'); ?>
                        </p>
                    </td>
                </tr>
                
                <!-- Date Range -->
                <tr>
                    <th scope="row">
                        <label><?php _e('Date Range', 'ria-data-manager'); ?></label>
                    </th>
                    <td>
                        <label for="date_from"><?php _e('From:', 'ria-data-manager'); ?></label>
                        <input type="date" name="date_from" id="date_from" class="regular-text">
                        <br><br>
                        <label for="date_to"><?php _e('To:', 'ria-data-manager'); ?></label>
                        <input type="date" name="date_to" id="date_to" class="regular-text">
                        <p class="description">
                            <?php _e('Optional: Filter posts by publish date', 'ria-data-manager'); ?>
                        </p>
                    </td>
                </tr>
                
                <!-- Include Options -->
                <tr>
                    <th scope="row">
                        <label><?php _e('Include Fields', 'ria-data-manager'); ?></label>
                    </th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="include_featured_image" value="1" checked>
                                <?php _e('Featured Images', 'ria-data-manager'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="include_taxonomies" value="1" checked>
                                <?php _e('Categories & Tags', 'ria-data-manager'); ?>
                            </label><br>
                            <?php if (class_exists('ACF')) : ?>
                                <label>
                                    <input type="checkbox" name="include_acf" value="1" checked>
                                    <?php _e('ACF Fields', 'ria-data-manager'); ?>
                                </label><br>
                            <?php endif; ?>
                        </fieldset>
                        <p class="description">
                            <?php _e('Select which additional fields to include in the export', 'ria-data-manager'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="submit" class="button button-primary button-large" id="ria-dm-export-btn">
                    <span class="dashicons dashicons-download"></span>
                    <?php _e('Export to CSV', 'ria-data-manager'); ?>
                </button>
            </p>
        </form>
        
        <!-- Progress & Results -->
        <div id="ria-dm-export-progress" class="ria-dm-progress" style="display: none;">
            <div class="ria-dm-progress-bar">
                <div class="ria-dm-progress-fill"></div>
            </div>
            <p class="ria-dm-status-text"><?php _e('Exporting...', 'ria-data-manager'); ?></p>
        </div>
        
        <div id="ria-dm-export-result" class="ria-dm-result" style="display: none;"></div>
    </div>
    
    <!-- Export Info Box -->
    <div class="ria-dm-info-box">
        <h3><?php _e('About CSV Export', 'ria-data-manager'); ?></h3>
        <p>
            <?php _e('This tool exports your WordPress content to a CSV file that can be opened in Excel, Google Sheets, or imported back into WordPress.', 'ria-data-manager'); ?>
        </p>
        <h4><?php _e('Exported Fields:', 'ria-data-manager'); ?></h4>
        <ul>
            <li><?php _e('Standard WordPress fields (title, content, excerpt, status, etc.)', 'ria-data-manager'); ?></li>
            <li><?php _e('Categories and tags', 'ria-data-manager'); ?></li>
            <li><?php _e('Featured images (as URLs)', 'ria-data-manager'); ?></li>
            <?php if (class_exists('ACF')) : ?>
                <li><?php _e('Advanced Custom Fields (ACF)', 'ria-data-manager'); ?></li>
            <?php endif; ?>
            <li><?php _e('Custom post types and taxonomies', 'ria-data-manager'); ?></li>
        </ul>
        <h4><?php _e('Tips:', 'ria-data-manager'); ?></h4>
        <ul>
            <li><?php _e('Large exports may take a few moments to complete', 'ria-data-manager'); ?></li>
            <li><?php _e('CSV files are saved with UTF-8 encoding', 'ria-data-manager'); ?></li>
            <li><?php _e('Exported files are automatically deleted after 24 hours', 'ria-data-manager'); ?></li>
        </ul>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#ria-dm-export-form').on('submit', function(e) {
        e.preventDefault();
        
        // Collect form data
        var formData = {
            action: 'ria_dm_export',
            nonce: riaDM.nonce,
            post_type: $('#post_type').val(),
            post_status: [],
            include_acf: $('input[name="include_acf"]').is(':checked'),
            include_taxonomies: $('input[name="include_taxonomies"]').is(':checked'),
            include_featured_image: $('input[name="include_featured_image"]').is(':checked'),
            date_from: $('#date_from').val(),
            date_to: $('#date_to').val()
        };
        
        $('input[name="post_status[]"]:checked').each(function() {
            formData.post_status.push($(this).val());
        });
        
        // Show progress
        $('#ria-dm-export-progress').show();
        $('#ria-dm-export-result').hide();
        $('#ria-dm-export-btn').prop('disabled', true);
        
        // AJAX request
        $.ajax({
            url: riaDM.ajaxurl,
            type: 'POST',
            data: formData,
            success: function(response) {
                $('#ria-dm-export-progress').hide();
                
                if (response.success) {
                    $('#ria-dm-export-result')
                        .removeClass('notice-error')
                        .addClass('notice notice-success')
                        .html('<p><strong>' + riaDM.strings.complete + '</strong></p>' +
                              '<p><a href="' + response.data.download_url + '" class="button button-primary" download>' +
                              '<span class="dashicons dashicons-download"></span> Download ' + response.data.filename + '</a></p>')
                        .show();
                } else {
                    $('#ria-dm-export-result')
                        .removeClass('notice-success')
                        .addClass('notice notice-error')
                        .html('<p><strong>' + riaDM.strings.error + ':</strong> ' + response.data.message + '</p>')
                        .show();
                }
            },
            error: function() {
                $('#ria-dm-export-progress').hide();
                $('#ria-dm-export-result')
                    .removeClass('notice-success')
                    .addClass('notice notice-error')
                    .html('<p><strong>' + riaDM.strings.error + ':</strong> An unexpected error occurred.</p>')
                    .show();
            },
            complete: function() {
                $('#ria-dm-export-btn').prop('disabled', false);
            }
        });
    });
});
</script>
