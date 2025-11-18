# RIA Data Manager 1.2

A powerful WordPress plugin for exporting and importing content (posts, pages, custom post types) with Advanced Custom Fields (ACF) to/from CSV files.

## Features

### Export
- ✅ Export Posts, Pages, and Custom Post Types
- ✅ Include ACF Fields with proper formatting
- ✅ Export Categories, Tags, and Custom Taxonomies
- ✅ Featured Images (as URLs)
- ✅ Filter by Post Status (Published, Draft, Pending, Private)
- ✅ Date Range Filtering
- ✅ UTF-8 Encoded CSV files
- ✅ Automatic file cleanup after 24 hours

### Import
- ✅ Import from CSV files
- ✅ Create new posts or update existing ones
- ✅ Auto-detect and map CSV columns
- ✅ Import ACF fields
- ✅ Create taxonomies if they don't exist
- ✅ Featured image support (URL or Attachment ID)
- ✅ Batch processing for large datasets
- ✅ Preview import before processing
- ✅ Skip errors and continue import
- ✅ Detailed error logging

## Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher
- Advanced Custom Fields (ACF) Pro (recommended, not required)

## Installation

### Method 1: Upload via WordPress Admin

1. Download the plugin ZIP file
2. Go to WordPress Admin → Plugins → Add New
3. Click "Upload Plugin"
4. Choose the `ria-data-manager.zip` file
5. Click "Install Now"
6. Activate the plugin

### Method 2: Manual Installation

1. Download and extract the plugin files
2. Upload the `ria-data-manager` folder to `/wp-content/plugins/`
3. Activate the plugin through the 'Plugins' menu in WordPress

### Method 3: Via FTP (SiteGround)

```bash
# From your local machine
cd C:\Users\RHudson\Desktop\GitHub\ria-data-manager

# Create ZIP file
# Upload to SiteGround via FTP or cPanel File Manager

# Or use SSH
ssh u2430-nrax5dropioa@giow1119.siteground.us -p 18765 -i ~/.ssh/ria_siteground

# Navigate to plugins directory
cd public_html/wp-content/plugins/

# Upload plugin folder
# Activate via WordPress admin
```

## Usage

### Accessing the Plugin

After activation, go to **Tools → RIA Data Manager** in your WordPress admin.

### Exporting Content

1. Navigate to the **Export** tab
2. Select the content type (Posts, Pages, or Custom Post Type)
3. Choose post statuses to include (Published, Draft, etc.)
4. Optionally set a date range
5. Select which fields to include:
   - Featured Images
   - Categories & Tags
   - ACF Fields (if ACF is installed)
6. Click "Export to CSV"
7. Download the generated CSV file

### Importing Content

1. Navigate to the **Import** tab
2. Click "Choose File" and select your CSV file
3. Configure import options:
   - **Update existing posts**: Match by ID and update
   - **Create taxonomies**: Auto-create missing categories/tags
   - **Skip on error**: Continue import if a row fails
4. (Optional) Click "Preview Import" to see what will be imported
5. Click "Import CSV" to start the import
6. Review the results summary

## CSV Structure

### Required Columns

- `post_title` - Post title (required)
- `post_type` - Post type (required: post, page, or custom post type slug)

### Standard WordPress Columns

- `ID` - Post ID (for updating existing posts)
- `post_content` - Post content/body
- `post_excerpt` - Post excerpt
- `post_status` - Post status (publish, draft, pending, private)
- `post_date` - Publish date (YYYY-MM-DD or any valid date format)
- `post_author` - Author username or email
- `post_name` - Post slug
- `post_parent` - Parent post ID (for hierarchical types)
- `menu_order` - Menu order number

### Additional Columns

- `featured_image` - Featured image URL or attachment ID
- `tax_category` - Categories (comma-separated names)
- `tax_post_tag` - Tags (comma-separated names)
- `tax_{taxonomy}` - Custom taxonomy terms (comma-separated)
- `acf_{field_name}` - ACF field values (prefix with `acf_`)
- `meta_{key}` - Custom post meta (prefix with `meta_`)

### Example CSV Structure

```csv
ID,post_title,post_content,post_status,post_type,post_date,featured_image,tax_category,acf_event_date,acf_location
,New Event,Event description,publish,events,2024-01-15,https://example.com/image.jpg,"Events,News",2024-02-01,Toronto
123,Update Event,Updated content,publish,events,2024-01-15,,News,2024-02-15,Montreal
```

## Custom Post Types Supported

The plugin automatically detects and supports all registered custom post types on your site, including:

- Events
- Conferences
- Resources
- Researcher
- Research Project
- Programs
- Theme
- Audience
- Setting
- Researcher Type
- Tax Receipts

## ACF Field Support

### Supported ACF Field Types

- Text, Textarea, WYSIWYG
- Number, Email, URL
- Select, Checkbox, Radio
- True/False
- Date Picker, Date Time Picker
- Image, File, Gallery
- Relationship, Post Object
- Taxonomy
- Repeater Fields (JSON encoded)
- Group Fields (JSON encoded)

### ACF Export Format

ACF fields are exported with proper formatting:
- **Relationships/Post Objects**: Exported as post IDs (comma-separated for multiple)
- **Taxonomies**: Exported as term slugs (comma-separated)
- **Images/Files**: Exported as URLs
- **Gallery**: Exported as pipe-separated URLs (url1|url2|url3)
- **Repeater/Group**: Exported as JSON
- **True/False**: Exported as 1 or 0
- **Dates**: Exported in YYYY-MM-DD format

## File Management

- CSV files are stored in `wp-content/uploads/ria-data-manager/`
- Protected by `.htaccess` (deny from all)
- Files older than 24 hours are automatically deleted
- Download links include security nonces

## Troubleshooting

### Import Issues

**Problem**: Import fails with "Invalid CSV file"
- **Solution**: Ensure your CSV is UTF-8 encoded with proper headers

**Problem**: ACF fields not importing
- **Solution**: 
  - Verify ACF Pro is installed and activated
  - Check field names are prefixed with `acf_`
  - Ensure field groups are assigned to the post type

**Problem**: Images not importing
- **Solution**: 
  - Use full image URLs (https://...)
  - Or use existing attachment IDs from media library
  - Ensure URLs are accessible

### Export Issues

**Problem**: Export button does nothing
- **Solution**: Check browser console for JavaScript errors
- Verify file permissions on uploads directory

**Problem**: Missing fields in export
- **Solution**: Ensure ACF fields are assigned to the post type
- Check if fields have values in the posts

### Performance

For large datasets (1000+ posts):
- Import is processed in batches of 100
- Large exports may take time - be patient
- Consider breaking large imports into smaller files
- Increase PHP memory limit if needed: `define('WP_MEMORY_LIMIT', '256M');`

## Developer Notes

### Hooks & Filters

The plugin provides several hooks for customization:

```php
// Modify export arguments
add_filter('ria_dm_export_args', function($args) {
    $args['posts_per_page'] = 500;
    return $args;
});

// Modify import arguments
add_filter('ria_dm_import_args', function($args) {
    $args['batch_size'] = 50;
    return $args;
});

// Customize CSV headers
add_filter('ria_dm_csv_headers', function($headers, $post_type) {
    $headers[] = 'custom_field';
    return $headers;
}, 10, 2);
```

### File Structure

```
ria-data-manager/
├── ria-data-manager.php          # Main plugin file
├── includes/
│   ├── class-admin.php            # Admin interface
│   ├── class-exporter.php         # Export functionality
│   ├── class-importer.php         # Import functionality
│   ├── class-acf-handler.php      # ACF integration
│   ├── class-taxonomy-handler.php # Taxonomy management
│   └── class-csv-processor.php    # CSV utilities
├── assets/
│   ├── css/admin.css              # Admin styles
│   └── js/admin.js                # Admin scripts
├── templates/
│   ├── export-page.php            # Export page template
│   └── import-page.php            # Import page template
└── README.md                      # This file
```

## Security

- All AJAX requests are nonce-protected
- User capability checks (requires `manage_options`)
- File upload validation (CSV only)
- Sanitized inputs and outputs
- Protected upload directory
- SQL injection prevention via WP functions

## Changelog

### Version 1.0.0 (2024)
- Initial release
- Export functionality for all post types
- Import functionality with preview
- ACF integration
- Taxonomy support
- Featured image handling
- Batch processing
- Error logging
- Auto cleanup

## Support

For issues, questions, or feature requests:
1. Check the Troubleshooting section above
2. Review WordPress and PHP error logs
3. Verify ACF Pro is up to date
4. Check file permissions on wp-content/uploads/

## Credits

- **Author**: Rob Hudson
- **Website**: https://the-ria.ca
- **Built for**: Rural Innovation Analysis (RIA)

## License

GPL v2 or later
https://www.gnu.org/licenses/gpl-2.0.html

## Roadmap

Future enhancements under consideration:
- [ ] Excel (XLSX) format support
- [ ] Scheduled automated exports
- [ ] Import field mapping UI
- [ ] Export profiles/templates
- [ ] WP-CLI support
- [ ] XML export/import
- [ ] Meta box field support
- [ ] Multisite compatibility
