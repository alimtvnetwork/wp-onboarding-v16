# Category Generator for Area by Riseup Asia LLC

A powerful WordPress plugin to generate categories by cross-joining titles and areas with templated HTML descriptions.

## About

**Plugin Name:** Category Generator for Area  
**Developed by:** MD Alim Ul Karim  
**Company:** [Riseup Asia LLC](https://riseup-asia.com/)  
**Version:** 2.3.0

### About the Author

**MD Alim Ul Karim** is the founder of Riseup Asia LLC, a leading technology solutions company specializing in innovative web development, digital transformation, and enterprise software solutions. With years of experience in software architecture and development, Alim has helped numerous businesses streamline their operations through custom-built solutions.

### About Riseup Asia LLC

[Riseup Asia LLC](https://riseup-asia.com/) is a technology company dedicated to providing cutting-edge solutions for businesses across Asia and beyond. Our expertise includes:

- Custom WordPress Development
- Enterprise Software Solutions
- Digital Transformation Services
- Web Application Development
- SEO & Digital Marketing Tools

---

## Features

- **Cross-Join Generation**: Input multiple titles and areas to automatically create all combinations
- **HTML Templating**: Use sample HTML with placeholders ({title}, {area}, {category}) for consistent descriptions
- **Live Preview**: See all combinations before generating
- **Batch Processing**: Generate hundreds of categories efficiently via AJAX
- **Custom Taxonomy Support**: Works with default categories or any custom taxonomy
- **Parent Category Support**: Assign all generated categories under a parent

## Installation

### Method 1: Upload ZIP (Recommended)

1. Download the `category-generator` folder
2. **Create a ZIP file** of the `category-generator` folder
3. Go to WordPress Admin → **Plugins → Add New → Upload Plugin**
4. Upload the ZIP file and click **Install Now**
5. Click **Activate Plugin**

### Method 2: FTP Upload

1. Upload the `category-generator` folder to `/wp-content/plugins/`
2. Go to WordPress Admin → **Plugins**
3. Find "Category Generator for Area by Riseup Asia LLC" and click **Activate**

## Usage

1. Navigate to **Category Generator** in WordPress admin menu
2. Enter your titles (one per line)
3. Enter your areas (one per line)
4. (Optional) Set a custom name format using placeholders
5. (Optional) Provide a sample HTML template with placeholders:
   - `{title}` - Will be replaced with the title
   - `{area}` - Will be replaced with the area
   - `{category}` - Will be replaced with "Title Area"
6. Click **Preview Combinations** to see what will be generated
7. Click **Generate Categories** to create all categories

## Template Example

```html
<div class="service-area">
  <h2>{category} Services</h2>
  <p>Looking for professional {title} services in {area}? 
  We provide top-quality {title} solutions throughout the {area} region.</p>
</div>
```

## Available Placeholders

| Placeholder | Description |
|-------------|-------------|
| `{title}` | The title value |
| `{area}` | The area value |
| `{category}` | The full category name |
| `{TITLE}` | Title in UPPERCASE |
| `{AREA}` | Area in UPPERCASE |
| `{Title}` | Title in Title Case |
| `{Area}` | Area in Title Case |

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- `manage_categories` capability (Editors and Administrators)

## Support

For support, feature requests, or bug reports, please contact:

- **Website:** [https://riseup-asia.com/](https://riseup-asia.com/)
- **Email:** contact@riseup-asia.com

## License

GPL v2 or later

---

## Changelog

### Version 2.3.0 (2026-01-09)

**Fixes:**
- Store SQLite database in `wp-content/uploads/category-generator/db/` to avoid permission issues on locked-down hosts.

### Version 2.2.0 (2026-01-09)

**New Features:**
- **Category Snapshots System**: Full backup and restore of WordPress category tables (wp_terms, wp_term_taxonomy, wp_termmeta)
- **Snapshots Submenu Page**: Dedicated page to view, create, restore, and delete snapshots
- **Manual & Automatic Snapshots**: Create snapshots manually or automatically before each category generation
- **Snapshot Toolbar on Generate Page**: Quick snapshot creation and restore dropdown with recent snapshots
- **Auto-Snapshot Setting**: Toggle to automatically create a backup before any category changes
- **Configurable Snapshot Limit**: Set maximum number of auto-snapshots to keep (default: 20)
- **Merge Restore Mode**: Restores add/update categories without deleting existing ones
- **Snapshot Storage**: Snapshots stored securely in wp-content/category-generator-snapshots/

**Improvements:**
- Added snapshot statistics to Settings → General page
- Sortable filename format: YYYY-MM-DD_HHmmss_slug.db
- .htaccess protection for snapshot directory

### Version 2.1.0 (2026-01-09)

**New Features:**
- **Database Backup & Restore**: Download the SQLite database file directly or restore from a previous backup in Settings → Danger Zone
- **Template Categories (3-Level Hierarchy)**: Organize templates with Root → Category → Subcategory structure in the new Categories tab
- **Template Category Filtering**: Filter templates by category in HTML, Meta, and Schema template tabs
- **Category Column**: Added category column to all template tables for better organization
- **History Page Inject UI**: Inject inner templates into existing category descriptions with Start/End/Cursor position options
- **Reset Confirmation Dialog**: Type "RESET" confirmation with optional data export before resetting database

**Improvements:**
- Enhanced Danger Zone UI with separate backup and reset sections
- Better modal styling and user experience
- Improved file validation for database restore

### Version 2.0.0

**Major Release:**
- Complete rewrite with SQLite database backend
- Inner Templates system for reusable content blocks
- Variables system with dynamic placeholders
- Business Profile management with Local Business Schema
- AI Provider integration (OpenAI, Anthropic, Google, Groq)
- Remote Template APIs for importing from external servers
- Category history tracking with full audit trail
- Import/Export functionality (ZIP, CSV, SQLite)
- Yoast SEO deep integration
- Test Cases page for validation
- Comprehensive Settings panel

### Version 1.0.0

**Initial Release:**
- Cross-join category generation
- HTML templating with placeholders
- Live preview functionality
- Batch processing via AJAX
- Custom taxonomy support
- Parent category assignment

---

© 2024 Riseup Asia LLC. All rights reserved.
