# Google Sheets Collaborative Editing Workflow

**Version 1.2.0 - NEW FEATURE**

## Overview

The RIA Data Manager now supports **metadata-only exports** designed specifically for collaborative editing in Google Sheets. Teams can edit page metadata without dealing with massive HTML content fields.

## Why This Matters

### The Problem
- Full content exports create **3.7MB+ files** per page (Divi Builder content)
- Google Sheets limit: 50,000 characters per cell
- Excel can't handle large exports
- Teams don't need to edit HTML shortcodes

### The Solution
- Export **metadata only** (titles, excerpts, ACF fields)
- Small, Google Sheets-compatible CSV files
- Teams edit readable data, not HTML
- Sync changes back to WordPress

---

## Complete Workflow

### Step 1: Export Metadata from WordPress

**Option A: Via Plugin UI** (Coming soon)
1. Go to **Tools → RIA Data Manager**
2. Select **"Metadata-Only Export"**
3. Choose post type (e.g., Pages)
4. Click **Export**
5. Download CSV file

**Option B: Via Code** (Available now)
```php
// Export metadata only
$args = array(
    'post_type' => 'page',
    'post_status' => array('publish', 'draft'),
    'include_acf' => true,
    'include_content_preview' => true,  // First 500 chars only
    'preview_length' => 500,
);

$result = RIA_DM_Metadata_Exporter::export($args);

if (!is_wp_error($result)) {
    $download_url = RIA_DM_CSV_Processor::get_download_url($result);
    echo "Download: " . $download_url;
}
```

### Step 2: Upload to Google Sheets

1. Download the exported CSV file
2. Go to [Google Sheets](https://sheets.google.com)
3. Create new spreadsheet (or open existing)
4. **File → Import → Upload**
5. Select the CSV file
6. Choose **"Replace spreadsheet"** or **"Insert new sheet"**
7. Click **Import data**

**Important:** Keep the **ID column**! This is used to match records when syncing back.

### Step 3: Team Editing

**Editable Fields:**
- ✅ post_title (Page title)
- ✅ post_excerpt (Description/summary)
- ✅ post_status (publish, draft, pending)
- ✅ post_name (URL slug)
- ✅ post_author (Author username)
- ✅ post_parent (Parent page ID)
- ✅ menu_order (Display order)
- ✅ featured_image (Image URL)
- ✅ ACF custom fields (if any)
- ✅ Taxonomies/categories

**Read-Only Fields:**
- 🔒 ID (Don't change! Needed for sync)
- 🔒 post_date (Created date)
- 🔒 post_modified (Last modified date)
- 🔒 content_preview (Just for reference)

**Tips:**
- Share the sheet with your team
- Use comments/notes for collaboration
- Track changes with version history
- Filter/sort as needed

### Step 4: Download Updated CSV

1. **File → Download → Comma Separated Values (.csv)**
2. Save to your computer

### Step 5: Import Back to WordPress

**Option A: Via Plugin UI** (Coming soon)
1. Go to **Tools → RIA Data Manager**
2. Select **"Import from Google Sheets"**
3. Upload the CSV file
4. Preview changes
5. Click **Import**

**Option B: Via Code** (Available now)
```php
// Import from Google Sheets CSV
$file_path = '/path/to/downloaded.csv';

$args = array(
    'dry_run' => true,  // Preview changes first
    'allowed_fields' => array(
        'post_title',
        'post_excerpt',
        'post_status',
        // ... other fields
    ),
);

$result = RIA_DM_Google_Sheets::import_from_sheets($file_path, $args);

// Review changes
print_r($result['changes']);

// If looks good, apply updates
$args['dry_run'] = false;
$result = RIA_DM_Google_Sheets::import_from_sheets($file_path, $args);

echo "Updated: " . $result['updated'] . " posts";
```

---

## What Gets Exported

### Standard Export (Full Content) ❌
```
ID, post_title, post_content (3.7MB!), post_excerpt, ...
```
- **Problem:** Too large for Google Sheets
- **File size:** 50MB+ for 65 pages
- **Result:** Unworkable in Excel/Sheets

### Metadata-Only Export ✅
```
ID, post_title, post_excerpt, content_preview (500 chars), post_status, ...
```
- **Benefit:** Google Sheets compatible
- **File size:** <5MB for 65 pages
- **Result:** Easy team editing

---

## Excluded Pages

**These 5 pages are too large even without full content:**
1. Impact Report 2024-2025 (3.74MB Divi content)
2. Impact Report 2023-24 (132KB)
3. Walk With Me 2024 (76KB)
4. Impact Report 2022-23 (76KB)
5. Trailblazers Awards (60KB)

**Options:**
- Export them separately
- Exclude from team editing
- Edit directly in WordPress
- Strip all HTML (plain text only)

---

## Safety Features

### Dry Run Mode
Always test imports first:
```php
$args = array('dry_run' => true);
$result = RIA_DM_Google_Sheets::import_from_sheets($file_path, $args);
```

This shows:
- What will change
- Old vs new values
- Any errors/warnings

### Change Detection
Only updates fields that actually changed:
```php
Array (
    [post_id] => 123
    [title] => "Home"
    [changes] => Array (
        [post_excerpt] => Array (
            [old] => "Old description"
            [new] => "New description"
        )
    )
)
```

### Validation
- Checks if post exists
- Validates field values
- Prevents accidental data loss
- Logs all changes

---

## Advanced Usage

### Custom Field Selection

Only allow specific fields to be edited:
```php
$args = array(
    'allowed_fields' => array(
        'post_title',      // Allow title edits
        'post_excerpt',    // Allow excerpt edits
        // 'post_status' is not in list, won't be updated
    ),
);
```

### Batch Processing

Export/import in batches:
```php
// Export 10-25 pages at a time
$args = array(
    'post_type' => 'page',
    'posts_per_page' => 25,
    'offset' => 0,  // Start at first page
);
```

### ACF Field Handling

ACF fields are automatically included if ACF Pro is active:
- Simple values: Exported as-is
- Arrays: Pipe-separated (`value1 | value2 | value3`)
- Complex: JSON format

---

## Troubleshooting

### Import shows "Post not found"
**Problem:** ID in CSV doesn't match WordPress
**Solution:** Don't edit the ID column in Google Sheets

### Changes not applying
**Problem:** Field not in `allowed_fields` list
**Solution:** Add the field to your import args

### File too large for Google Sheets
**Problem:** Page has massive content
**Solution:** Use metadata-only export (no full content)

### Featured image not updating
**Problem:** Image URL handling not yet implemented
**Solution:** Coming in future update

---

## Roadmap

### Version 1.2.0 (Current)
- ✅ Metadata-only export
- ✅ Google Sheets CSV workflow
- ✅ Import with change detection
- ✅ Dry-run mode

### Version 1.3.0 (Planned)
- 🔜 Admin UI for exports
- 🔜 One-click import
- 🔜 Featured image URL handling
- 🔜 Batch export UI

### Version 1.4.0 (Planned)
- 🔜 Google Sheets API integration
- 🔜 Direct push to Sheets
- 🔜 Direct pull from Sheets
- 🔜 Live sync (polling)

### Version 2.0.0 (Future)
- 🔮 Real-time collaboration
- 🔮 Conflict resolution
- 🔮 User permissions
- 🔮 Audit trail

---

## Support

**Questions?** Contact rob.hudson@the-ria.ca

**Issues?** [GitHub Issues](https://github.com/robhudson-bot/ria-data-manager/issues)

**Documentation:** See [README.md](README.md) for full plugin docs
