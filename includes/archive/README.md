# Archived Export Classes

**Date Archived:** 2025-11-25
**Reason:** Replaced by metadata-only export system in v1.2.0

## Why These Were Archived

These exporters produced full-content CSV files that were:
- **Too large** for Google Sheets (4.4MB+ files)
- **Uneditable** by teams (massive HTML/shortcodes)
- **Impractical** for collaborative workflows

### Problem Scale
- Single pages: 25KB - 3.7MB of HTML content
- Total exports: 50MB+ for 65 pages
- Google Sheets limit: 50,000 chars per cell
- Result: Unable to import or work with exports

## Archived Files

1. **class-exporter.php** - Original exporter
   - Basic CSV export with full content
   - No content sanitization

2. **class-rest-exporter.php** - REST API-based exporter
   - Attempted to use WordPress REST API
   - Had compatibility issues on live site

3. **class-exporter-improved.php** - Improved direct exporter
   - Better content handling
   - Still exported full HTML (too large)

## Replacement System

**v1.2.0: Metadata-Only Export**

New file: `class-metadata-exporter.php`

Key differences:
- ✅ Exports **metadata only** (no full content)
- ✅ Adds **content_preview** (500 chars for reference)
- ✅ File size: **<1MB** vs 50MB+
- ✅ Google Sheets compatible
- ✅ Team-editable metadata

### What Gets Exported Now

**Included:**
- post_title, post_excerpt, post_status
- post_name, post_author, dates
- ACF fields, taxonomies, featured_image
- content_preview (500 chars)

**Excluded:**
- post_content (stays in WordPress)

## If You Need Full Content Export

If you absolutely need full content exports for backup/migration:

1. **Option A:** Use native WordPress export (Tools → Export)
2. **Option B:** Use WP-CLI: `wp export`
3. **Option C:** Restore old exporters from this archive

To restore:
```bash
# Copy back to includes/
cp archive/class-exporter.php ../
cp archive/class-rest-exporter.php ../
cp archive/class-exporter-improved.php ../

# Update ria-data-manager.php to load them
```

## Purpose of This Plugin

**Primary Goal:** Enable collaborative metadata editing in Google Sheets

This plugin is **optimized for team workflows**, not full-site backups.

Use the right tool for your use case:
- **Team metadata editing** → RIA Data Manager (this plugin)
- **Full site backup** → WordPress Export or backup plugins
- **Database migration** → WP-CLI or phpMyAdmin

---

**See:** [GOOGLE_SHEETS_WORKFLOW.md](../../GOOGLE_SHEETS_WORKFLOW.md) for complete usage guide
