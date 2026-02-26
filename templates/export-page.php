<?php
/**
 * Export Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get available post types using WordPress function
$post_types = get_post_types(array('public' => true), 'objects');
?>

<div class="qry-export-container">
    <div class="qry-card">
        <h2><?php _e('Export Content to CSV', 'quarry'); ?></h2>
        
        <form id="qry-export-form" method="post">
            <?php wp_nonce_field('qry_export', 'qry_export_nonce'); ?>
            
            <!-- Post Type Selection -->
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="post_type"><?php _e('Content Type', 'quarry'); ?></label>
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
                            <?php _e('Select the type of content to export', 'quarry'); ?>
                        </p>
                    </td>
                </tr>
                
                <!-- Post Status -->
                <tr>
                    <th scope="row">
                        <label><?php _e('Post Status', 'quarry'); ?></label>
                    </th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="post_status[]" value="publish" checked>
                                <?php _e('Published', 'quarry'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="post_status[]" value="draft">
                                <?php _e('Draft', 'quarry'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="post_status[]" value="pending">
                                <?php _e('Pending', 'quarry'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="post_status[]" value="private">
                                <?php _e('Private', 'quarry'); ?>
                            </label>
                        </fieldset>
                        <p class="description">
                            <?php _e('Select which post statuses to include', 'quarry'); ?>
                        </p>
                    </td>
                </tr>
                
                <!-- Date Range -->
                <tr>
                    <th scope="row">
                        <label><?php _e('Date Range', 'quarry'); ?></label>
                    </th>
                    <td>
                        <label for="date_from"><?php _e('From:', 'quarry'); ?></label>
                        <input type="date" name="date_from" id="date_from" class="regular-text">
                        <br><br>
                        <label for="date_to"><?php _e('To:', 'quarry'); ?></label>
                        <input type="date" name="date_to" id="date_to" class="regular-text">
                        <p class="description">
                            <?php _e('Optional: Filter posts by publish date', 'quarry'); ?>
                        </p>
                    </td>
                </tr>
                
                <!-- Include Options -->
                <tr>
                    <th scope="row">
                        <label><?php _e('Include Fields', 'quarry'); ?></label>
                    </th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="include_featured_image" value="1" checked>
                                <?php _e('Featured Images', 'quarry'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="include_taxonomies" value="1" checked>
                                <?php _e('Categories & Tags', 'quarry'); ?>
                            </label><br>
                            <?php if (class_exists('ACF')) : ?>
                                <label>
                                    <input type="checkbox" name="include_acf" value="1" checked>
                                    <?php _e('ACF Fields', 'quarry'); ?>
                                </label><br>
                            <?php endif; ?>
                        </fieldset>
                        <p class="description">
                            <?php _e('Select which additional fields to include in the export', 'quarry'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="submit" class="button button-primary button-large" id="qry-export-btn">
                    <?php echo qry_icon( 'download', 16 ); ?>
                    <?php _e('Export to CSV', 'quarry'); ?>
                </button>
            </p>
        </form>
        
        <!-- Progress & Results -->
        <div id="qry-export-progress" class="qry-progress" style="display: none;">
            <div class="qry-progress-bar">
                <div class="qry-progress-fill"></div>
            </div>
            <p class="qry-status-text"><?php _e('Exporting...', 'quarry'); ?></p>
        </div>
        
        <div id="qry-export-result" class="qry-result" style="display: none;"></div>
    </div>
    
    <!-- Export Info Box -->
    <div class="qry-info-box">
        <h3><?php _e('About CSV Export', 'quarry'); ?></h3>
        <p>
            <?php _e('This tool exports your WordPress content to a CSV file that can be opened in Excel, Google Sheets, or imported back into WordPress.', 'quarry'); ?>
        </p>
        <h4><?php _e('Exported Fields:', 'quarry'); ?></h4>
        <ul>
            <li><?php _e('Standard WordPress fields (title, content, excerpt, status, etc.)', 'quarry'); ?></li>
            <li><?php _e('Categories and tags', 'quarry'); ?></li>
            <li><?php _e('Featured images (as URLs)', 'quarry'); ?></li>
            <?php if (class_exists('ACF')) : ?>
                <li><?php _e('Advanced Custom Fields (ACF)', 'quarry'); ?></li>
            <?php endif; ?>
            <li><?php _e('Custom post types and taxonomies', 'quarry'); ?></li>
        </ul>
        <h4><?php _e('Tips:', 'quarry'); ?></h4>
        <ul>
            <li><?php _e('Large exports may take a few moments to complete', 'quarry'); ?></li>
            <li><?php _e('CSV files are saved with UTF-8 encoding', 'quarry'); ?></li>
            <li><?php _e('Exported files are automatically deleted after 24 hours', 'quarry'); ?></li>
        </ul>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#qry-export-form').on('submit', function(e) {
        e.preventDefault();
        
        // Collect form data
        var formData = {
            action: 'qry_export',
            nonce: quarry.nonce,
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
        $('#qry-export-progress').show();
        $('#qry-export-result').hide();
        $('#qry-export-btn').prop('disabled', true);
        
        // AJAX request
        $.ajax({
            url: quarry.ajaxurl,
            type: 'POST',
            data: formData,
            success: function(response) {
                $('#qry-export-progress').hide();
                
                if (response.success) {
                    $('#qry-export-result')
                        .removeClass('notice-error')
                        .addClass('notice notice-success')
                        .html('<p><strong>' + quarry.strings.complete + '</strong></p>' +
                              '<p><a href="' + response.data.download_url + '" class="qry-download-link" download>' +
                              '<svg class="qry-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg> ' +
                              'Download ' + response.data.filename + '</a></p>')
                        .show();
                } else {
                    $('#qry-export-result')
                        .removeClass('notice-success')
                        .addClass('notice notice-error')
                        .html('<p><strong>' + quarry.strings.error + ':</strong> ' + response.data.message + '</p>')
                        .show();
                }
            },
            error: function() {
                $('#qry-export-progress').hide();
                $('#qry-export-result')
                    .removeClass('notice-success')
                    .addClass('notice notice-error')
                    .html('<p><strong>' + quarry.strings.error + ':</strong> An unexpected error occurred.</p>')
                    .show();
            },
            complete: function() {
                $('#qry-export-btn').prop('disabled', false);
            }
        });
    });
});
</script>
