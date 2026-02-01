# 17 - REST API Endpoints

> **Status:** Complete  
> **Priority:** High  
> **Updated:** 2026-01-31

---

## Purpose

Consolidated reference for all Link Manager REST API endpoints. All endpoints use the `lm/v1` namespace and require WordPress admin authentication (`manage_options` capability).

---

## Authentication

All endpoints require:
- WordPress admin login (cookie auth)
- OR valid REST API nonce via `X-WP-Nonce` header

```javascript
// Example fetch with nonce
fetch('/wp-json/lm/v1/posts', {
  headers: {
    'X-WP-Nonce': wpApiSettings.nonce,
    'Content-Type': 'application/json'
  }
});
```

---

## Content Endpoints

### List Posts/Pages/Categories

```
GET /lm/v1/posts
GET /lm/v1/pages
GET /lm/v1/categories
```

**Query Parameters:**

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `page` | int | 1 | Page number |
| `per_page` | int | 20 | Items per page (max 100) |
| `search` | string | - | Search term |
| `search_type` | string | title | `title` or `slug` |
| `sort_by` | string | title | `title`, `links`, `broken`, `updated` |
| `sort_dir` | string | asc | `asc` or `desc` |
| `has_broken` | bool | - | Filter to items with broken links |

**Response:**

```json
{
  "items": [
    {
      "id": 123,
      "title": "Getting Started Guide",
      "slug": "getting-started",
      "url": "https://example.com/getting-started",
      "meta_description": "Learn how to...",
      "total_links": 12,
      "broken_links": 2,
      "working_links": 10,
      "history_count": 3,
      "last_scanned": "2026-01-31T10:30:00Z",
      "last_modified": "2026-01-30T15:00:00Z"
    }
  ],
  "total": 294,
  "page": 1,
  "per_page": 20,
  "total_pages": 15
}
```

---

### Get Content Details

```
GET /lm/v1/posts/{id}
GET /lm/v1/pages/{id}
GET /lm/v1/categories/{id}
```

**Response:**

```json
{
  "id": 123,
  "type": "post",
  "title": "Getting Started Guide",
  "slug": "getting-started",
  "url": "https://example.com/getting-started",
  "wp_edit_url": "https://example.com/wp-admin/post.php?post=123&action=edit",
  "meta_description": "Learn how to...",
  "last_scanned": "2026-01-31T10:30:00Z",
  "history_count": 3,
  "is_elementor": true,
  "link_stats": {
    "total": 12,
    "working": 10,
    "broken": 2,
    "json_ld": 3
  }
}
```

---

## Link Endpoints

### Get Links for Content

```
GET /lm/v1/posts/{id}/links
GET /lm/v1/pages/{id}/links
GET /lm/v1/categories/{id}/links
```

**Query Parameters:**

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `status` | string | all | `all`, `working`, `broken`, `unknown` |
| `source` | string | all | `all`, `html`, `json_ld` |
| `wrapper` | string | - | Filter by wrapper: `h1`-`h6`, `strong`, `em` |
| `word_count` | string | - | `1`, `2`, `3+` |
| `has_title` | bool | - | Has title attribute |

**Response:**

```json
{
  "links": [
    {
      "id": "link_abc123",
      "url": "https://example.com/page",
      "anchor_text": "click here",
      "word_count": 2,
      "title_attribute": "Visit page",
      "status": "broken",
      "status_code": 404,
      "wrapper_stack": ["h2", "strong"],
      "source_type": "html",
      "json_ld_path": null,
      "elementor_element_id": "abc123",
      "position": { "start": 1234, "end": 1290 },
      "outer_html": "<a href=\"...\" title=\"...\">click here</a>"
    }
  ],
  "total": 12,
  "stats": {
    "by_status": { "working": 10, "broken": 2 },
    "by_wrapper": { "h2": 3, "strong": 5, "none": 4 },
    "by_word_count": { "1": 2, "2": 6, "3+": 4 }
  }
}
```

---

### Modify Link

```
PUT /lm/v1/links/{id}
```

**Request Body:**

```json
{
  "url": "https://new-url.com/page",
  "title_attribute": "New title",
  "remove_title": false
}
```

**Response:**

```json
{
  "success": true,
  "link": { /* updated link object */ },
  "history_id": "hist_xyz789"
}
```

---

### Remove Link

```
DELETE /lm/v1/links/{id}
```

**Query Parameters:**

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `mode` | string | keep_text | `keep_text`, `keep_href_only`, `remove_all` |

**Response:**

```json
{
  "success": true,
  "history_id": "hist_xyz789"
}
```

---

### Bulk Link Operations

```
POST /lm/v1/links/bulk
```

**Request Body:**

```json
{
  "link_ids": ["link_abc", "link_def", "link_ghi"],
  "action": "remove_title",
  "options": {}
}
```

**Available Actions:**

| Action | Options | Description |
|--------|---------|-------------|
| `remove_title` | - | Remove title attribute |
| `remove_link` | `mode`: keep_text/keep_href_only | Remove anchor tag |
| `remove_wrapper` | `wrapper`: h1-h6/strong/em | Remove wrapper tag |
| `change_url` | `new_url`: string | Change href value |
| `set_title` | `title`: string | Set title attribute |
| `set_title_from_csv` | `mapping`: object | Bulk title from CSV data |

**Response:**

```json
{
  "success": true,
  "modified": 3,
  "failed": 0,
  "errors": [],
  "history_id": "hist_xyz789"
}
```

---

## Scan Endpoints

### Start Scan

```
POST /lm/v1/scan
```

**Request Body:**

```json
{
  "scan_type": "all",
  "content_type": null,
  "content_ids": null
}
```

| Field | Type | Description |
|-------|------|-------------|
| `scan_type` | string | `all`, `broken`, `selected` |
| `content_type` | string? | `posts`, `pages`, `categories` |
| `content_ids` | int[]? | Specific IDs to scan |

**Response:**

```json
{
  "job_id": "job_abc123",
  "status": "pending",
  "total_items": 294
}
```

---

### Get Scan Progress

```
GET /lm/v1/scan/{job_id}/progress
```

**Response:**

```json
{
  "id": "job_abc123",
  "status": "running",
  "progress": {
    "total": 294,
    "completed": 45,
    "percentage": 15.3,
    "current_item": "Getting Started Guide",
    "links_found": 234,
    "broken_found": 12,
    "eta_seconds": 180
  },
  "started_at": "2026-01-31T10:30:00Z",
  "errors": []
}
```

---

### Cancel Scan

```
POST /lm/v1/scan/{job_id}/cancel
```

**Response:**

```json
{
  "success": true,
  "status": "cancelled"
}
```

---

## Snapshot Endpoints

### List Snapshots

```
GET /lm/v1/snapshots
```

**Response:**

```json
{
  "snapshots": [
    {
      "id": 3,
      "sequence": 3,
      "name": "pre-cleanup",
      "file_name": "003-pre-cleanup-2026-01-31.db",
      "size_bytes": 2457600,
      "content_counts": {
        "posts": 294,
        "pages": 45,
        "categories": 12
      },
      "is_auto_snapshot": false,
      "created_at": "2026-01-31T10:00:00Z",
      "restored_at": null
    }
  ],
  "total": 7
}
```

---

### Create Snapshot

```
POST /lm/v1/snapshots
```

**Request Body:**

```json
{
  "name": "before-bulk-edit",
  "include_history": false,
  "content_types": ["posts", "pages", "categories"]
}
```

**Response:**

```json
{
  "success": true,
  "snapshot": { /* snapshot object */ }
}
```

---

### Restore Snapshot

```
POST /lm/v1/snapshots/{id}/restore
```

**Request Body:**

```json
{
  "content_types": ["posts"]
}
```

**Response:**

```json
{
  "success": true,
  "restored_counts": {
    "posts": 294,
    "pages": 0,
    "categories": 0,
    "links": 1847
  },
  "backup_path": "wp-content/uploads/link-manager/snapshots/004-pre-restore-backup-2026-01-31.db"
}
```

---

### Delete Snapshot

```
DELETE /lm/v1/snapshots/{id}
```

**Response:**

```json
{
  "success": true
}
```

---

## History Endpoints

### Get Content History

```
GET /lm/v1/posts/{id}/history
GET /lm/v1/pages/{id}/history
GET /lm/v1/categories/{id}/history
```

**Response:**

```json
{
  "versions": [
    {
      "id": "v3",
      "version_number": 3,
      "changes": [
        { "type": "remove_title", "count": 2 }
      ],
      "created_at": "2026-01-31T14:30:00Z",
      "created_by": "admin"
    }
  ],
  "total": 3
}
```

---

### Restore to Version

```
POST /lm/v1/posts/{id}/history/{version}/restore
POST /lm/v1/pages/{id}/history/{version}/restore
```

**Response:**

```json
{
  "success": true,
  "restored_version": 1,
  "new_version": 4
}
```

---

### Compare Versions

```
GET /lm/v1/posts/{id}/history/compare?from=1&to=3
```

**Response:**

```json
{
  "from_version": 1,
  "to_version": 3,
  "changes": [
    {
      "type": "link_removed",
      "url": "https://example.com/old",
      "anchor_text": "old link"
    },
    {
      "type": "url_changed",
      "old_url": "https://old.com",
      "new_url": "https://new.com"
    }
  ]
}
```

---

## Import Endpoints

### Upload CSV

```
POST /lm/v1/import/upload
Content-Type: multipart/form-data
```

**Response:**

```json
{
  "file_id": "tmp_abc123",
  "file_name": "broken-links.csv",
  "row_count": 150,
  "detected_columns": {
    "broken_url": "url",
    "source": "source_page",
    "status_code": "status",
    "unmapped": ["extra_column"]
  }
}
```

---

### Preview Import

```
POST /lm/v1/import/preview
```

**Request Body:**

```json
{
  "file_id": "tmp_abc123",
  "column_mapping": {
    "broken_url": "url",
    "source": "source_page"
  },
  "match_by": "url"
}
```

**Response:**

```json
{
  "total_rows": 150,
  "matched_rows": 142,
  "unmatched_rows": 8,
  "preview_rows": [
    {
      "row": 1,
      "broken_url": "https://example.com/old",
      "source": "/getting-started",
      "matched": true,
      "post_id": 123
    }
  ]
}
```

---

### Execute Import

```
POST /lm/v1/import/execute
```

**Request Body:**

```json
{
  "file_id": "tmp_abc123",
  "column_mapping": { /* ... */ },
  "match_by": "url",
  "skip_duplicates": true
}
```

**Response:**

```json
{
  "success": true,
  "imported": 142,
  "skipped": 8,
  "duplicates_skipped": 5,
  "errors": []
}
```

---

## Settings Endpoints

### Get Settings

```
GET /lm/v1/settings
```

**Response:**

```json
{
  "auto_snapshot_enabled": true,
  "snapshot_retention_limit": 50,
  "show_first_modification_warning": true,
  "default_items_per_page": 20,
  "default_tab": "posts",
  "validate_link_status": true,
  "follow_redirects": true,
  "request_timeout": 10,
  "concurrent_requests": 5,
  "batch_size": 20,
  "scan_post_content": true,
  "scan_elementor_data": true,
  "scan_json_ld": true,
  "enabled_post_types": ["post", "page"]
}
```

---

### Update Settings

```
PUT /lm/v1/settings
```

**Request Body:** Partial settings object

**Response:**

```json
{
  "success": true,
  "settings": { /* updated settings */ }
}
```

---

### Get Database Stats

```
GET /lm/v1/database/stats
```

**Response:**

```json
{
  "main_database": {
    "path": "wp-content/uploads/link-manager/link-manager.db",
    "size_bytes": 2457600,
    "posts": 294,
    "pages": 45,
    "categories": 12,
    "links": 1847
  },
  "history_databases": {
    "path": "wp-content/uploads/link-manager/history-manage/",
    "posts": { "count": 47, "size_bytes": 12902400 },
    "pages": { "count": 8, "size_bytes": 1258291 },
    "categories": { "count": 3, "size_bytes": 419430 }
  },
  "snapshots": {
    "path": "wp-content/uploads/link-manager/snapshots/",
    "count": 7,
    "total_size_bytes": 47185920
  }
}
```

---

## Error Response Format

All errors follow this format:

```json
{
  "code": 14404,
  "message": "Snapshot not found",
  "details": {
    "snapshot_id": 99
  }
}
```

HTTP status codes:
- `400` - Invalid request
- `401` - Not authenticated
- `403` - Not authorized
- `404` - Resource not found
- `500` - Server error

---

## Internal Linking Endpoints

### Link Targets

#### List Targets

```
GET /lm/v1/internal-linking/targets
```

**Query Parameters:**

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `page` | int | 1 | Page number |
| `per_page` | int | 20 | Items per page (max 100) |
| `search` | string | - | Search in URL or title |
| `category` | string | - | Filter by category |
| `is_active` | bool | true | Only active targets |

**Response:**

```json
{
  "targets": [
    {
      "id": 1,
      "url": "/carpet-cleaning-guide",
      "title": "Carpet Cleaning Guide",
      "category": "cleaning",
      "priority": 5,
      "times_linked": 23,
      "is_active": true,
      "source": "MANUAL_IMPORT",
      "created_at": "2026-01-31T10:00:00Z"
    }
  ],
  "total": 127,
  "page": 1,
  "per_page": 20
}
```

---

#### Add Target

```
POST /lm/v1/internal-linking/targets
```

**Request Body:**

```json
{
  "url": "/carpet-cleaning-guide",
  "title": "Carpet Cleaning Guide",
  "category": "cleaning",
  "priority": 5,
  "keywords": ["carpet", "cleaning tips"]
}
```

**Response:**

```json
{
  "success": true,
  "target": { /* target object */ }
}
```

---

#### Update Target

```
PUT /lm/v1/internal-linking/targets/{id}
```

**Request Body:** Partial target object

**Response:**

```json
{
  "success": true,
  "target": { /* updated target */ }
}
```

---

#### Delete Target

```
DELETE /lm/v1/internal-linking/targets/{id}
```

**Response:**

```json
{
  "success": true
}
```

---

#### Import Targets from CSV

```
POST /lm/v1/internal-linking/import/csv
Content-Type: multipart/form-data
```

**Form Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `file` | file | CSV file |
| `url_column` | string | Column name for URL |
| `title_column` | string | Column name for title |
| `category_column` | string? | Optional category column |
| `auto_detect` | bool | Auto-detect columns |

**Response:**

```json
{
  "success": true,
  "imported": 127,
  "skipped": 3,
  "failed": 0,
  "errors": []
}
```

---

#### Import Targets from JSON

```
POST /lm/v1/internal-linking/import/json
Content-Type: multipart/form-data
```

**Response:**

```json
{
  "success": true,
  "imported": 45,
  "variables_created": 3,
  "errors": []
}
```

---

### Templates

#### List Templates

```
GET /lm/v1/internal-linking/templates
```

**Query Parameters:**

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `active_only` | bool | true | Only active templates |

**Response:**

```json
{
  "templates": [
    {
      "id": 1,
      "name": "Basic Link",
      "template": "<a href=\"{{url}}\" title=\"{{title}}\">{{anchor_text}}</a>",
      "is_default": true,
      "is_active": true,
      "created_at": "2026-01-31T10:00:00Z"
    }
  ]
}
```

---

#### Create Template

```
POST /lm/v1/internal-linking/templates
```

**Request Body:**

```json
{
  "name": "Bold Heading Link",
  "template": "<{{heading_tag}}><strong><a href=\"{{url}}\" title=\"{{title_attr}}\">{{anchor_text}}</a></strong></{{heading_tag}}>",
  "is_default": false
}
```

**Response:**

```json
{
  "success": true,
  "template": { /* template object */ }
}
```

---

#### Update Template

```
PUT /lm/v1/internal-linking/templates/{id}
```

**Request Body:** Partial template object

---

#### Delete Template

```
DELETE /lm/v1/internal-linking/templates/{id}
```

---

#### Set Default Template

```
POST /lm/v1/internal-linking/templates/{id}/default
```

**Response:**

```json
{
  "success": true,
  "template": { /* updated template with is_default: true */ }
}
```

---

### Variables

#### List Variables

```
GET /lm/v1/internal-linking/variables
```

**Response:**

```json
{
  "variables": [
    {
      "id": 1,
      "name": "title_attr",
      "source_type": "csv",
      "source_file": "title-variations.csv",
      "values_count": 15,
      "selection_mode": "SEQUENTIAL",
      "current_index": 3,
      "last_refreshed_at": "2026-01-31T10:00:00Z"
    }
  ]
}
```

---

#### Create Variable

```
POST /lm/v1/internal-linking/variables
```

**Request Body (from file):**

```json
{
  "name": "title_attr",
  "source_type": "csv",
  "source_file": "title-variations.csv",
  "column_or_key": "title_text",
  "selection_mode": "SEQUENTIAL"
}
```

**Request Body (manual values):**

```json
{
  "name": "heading_tag",
  "source_type": "manual",
  "values": ["h2", "h3", "h4"],
  "selection_mode": "RANDOM"
}
```

---

#### Update Variable

```
PUT /lm/v1/internal-linking/variables/{id}
```

---

#### Delete Variable

```
DELETE /lm/v1/internal-linking/variables/{id}
```

---

#### Refresh Variable Values

```
POST /lm/v1/internal-linking/variables/{id}/refresh
```

Reloads values from source file.

**Response:**

```json
{
  "success": true,
  "values_count": 18,
  "previous_count": 15
}
```

---

#### Reset Variable Index

```
POST /lm/v1/internal-linking/variables/{id}/reset
```

Resets sequential index to 0.

---

### Auto-Linking

#### Find Orphan Content

```
GET /lm/v1/internal-linking/orphans
```

**Query Parameters:**

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `content_type` | string | all | `posts`, `pages`, `all` |
| `max_links` | int | 5 | Content with fewer than this |
| `category_id` | int | - | Filter by category |

**Response:**

```json
{
  "orphans": [
    {
      "id": 123,
      "type": "post",
      "title": "How to Clean Carpets",
      "internal_link_count": 2,
      "total_links": 8
    }
  ],
  "total": 47
}
```

---

#### Preview Links

```
POST /lm/v1/internal-linking/generate/preview
```

**Request Body:**

```json
{
  "content_type": "post",
  "content_id": 123,
  "link_count": 5,
  "template_id": 2
}
```

**Response:**

```json
{
  "proposed_links": [
    {
      "target_url": "/carpet-cleaning-guide",
      "anchor_text": "carpet cleaning tips",
      "phrase_context": "...professional carpet cleaning tips that will help...",
      "template_preview": "<h2><a href=\"/carpet-cleaning-guide\">carpet cleaning tips</a></h2>"
    }
  ],
  "total_matches": 5
}
```

---

#### Generate Links

```
POST /lm/v1/internal-linking/generate
```

**Request Body:**

```json
{
  "content_type": "post",
  "content_id": 123,
  "link_count": 5,
  "template_id": 2,
  "insertion_mode": "FIRST_MATCH"
}
```

**Response:**

```json
{
  "success": true,
  "links_created": 5,
  "history_id": "hist_abc123",
  "links": [
    {
      "target_url": "/carpet-cleaning-guide",
      "anchor_text": "carpet cleaning tips"
    }
  ]
}
```

---

#### Bulk Generate Links

```
POST /lm/v1/internal-linking/generate/bulk
```

**Request Body:**

```json
{
  "content_type": "post",
  "content_ids": [123, 456, 789],
  "links_per_content": 5,
  "template_id": null,
  "insertion_mode": "FIRST_MATCH"
}
```

**Response:**

```json
{
  "success": true,
  "total_processed": 3,
  "total_links_created": 12,
  "content_with_links": 3,
  "content_failed": 0,
  "results": [
    { "content_id": 123, "links_created": 5 },
    { "content_id": 456, "links_created": 4 },
    { "content_id": 789, "links_created": 3 }
  ]
}
```

---

#### Remove Internal Links

```
DELETE /lm/v1/internal-linking/links/{content_type}/{content_id}
```

**Query Parameters:**

| Param | Type | Description |
|-------|------|-------------|
| `link_ids` | string | Comma-separated IDs (optional, removes all if empty) |

**Response:**

```json
{
  "success": true,
  "links_removed": 5,
  "history_id": "hist_xyz789"
}
```

---

### Reports

#### Get Site Linking Report

```
GET /lm/v1/internal-linking/report
```

**Response:**

```json
{
  "total_content": 234,
  "content_with_links": 187,
  "orphan_content": 47,
  "total_internal_links": 589,
  "avg_links_per_content": 3.2,
  "distribution": {
    "0": 47,
    "1-2": 34,
    "3-5": 68,
    "6-10": 52,
    "10+": 33
  },
  "top_targets": [
    { "url": "/carpet-cleaning-guide", "times_linked": 23 }
  ],
  "history_summary": {
    "content_with_history": 89,
    "total_versions": 312
  }
}
```

---

#### Export Report

```
GET /lm/v1/internal-linking/report/export
```

Returns CSV file with full report data.

---

## Link Health Monitor Endpoints

### Health Summary

```
GET /lm/v1/health/summary
```

Get overall health statistics for all monitored links.

**Response:**

```json
{
  "total_links": 1847,
  "checked_links": 1620,
  "healthy": 1502,
  "broken": 45,
  "slow": 23,
  "redirects": 50,
  "excluded": 12,
  "unknown": 215,
  "average_response_ms": 342,
  "last_full_scan": "2026-01-31T08:00:00Z",
  "active_alerts": 68,
  "critical_alerts": 12
}
```

---

### List Health Checks

```
GET /lm/v1/health/checks
```

**Query Parameters:**

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `page` | int | 1 | Page number |
| `per_page` | int | 20 | Items per page (max 100) |
| `status` | string | all | `healthy`, `broken`, `slow`, `redirect`, `unknown`, `excluded` |
| `priority` | string | all | `high`, `normal`, `low` |
| `sort_by` | string | last_checked | `last_checked`, `response_time`, `failures` |
| `sort_dir` | string | desc | `asc` or `desc` |

**Response:**

```json
{
  "items": [
    {
      "id": 1,
      "link_id": 456,
      "url": "https://example.com/page",
      "status": "broken",
      "http_code": 404,
      "response_time_ms": null,
      "redirect_count": 0,
      "final_url": null,
      "error_message": "Not Found",
      "ssl_valid": true,
      "ssl_expiry": "2027-06-15T00:00:00Z",
      "priority": "high",
      "last_checked_at": "2026-01-31T10:30:00Z",
      "next_check_at": "2026-02-01T10:30:00Z",
      "check_count": 5,
      "consecutive_failures": 3
    }
  ],
  "total": 1847,
  "page": 1,
  "per_page": 20,
  "total_pages": 93
}
```

---

### Get Health Check Details

```
GET /lm/v1/health/checks/{id}
```

**Response:**

```json
{
  "id": 1,
  "link_id": 456,
  "url": "https://example.com/page",
  "status": "broken",
  "http_code": 404,
  "response_time_ms": null,
  "redirect_count": 0,
  "final_url": null,
  "redirect_chain": [],
  "error_message": "Not Found",
  "ssl_valid": true,
  "ssl_expiry": "2027-06-15T00:00:00Z",
  "priority": "high",
  "last_checked_at": "2026-01-31T10:30:00Z",
  "next_check_at": "2026-02-01T10:30:00Z",
  "check_count": 5,
  "consecutive_failures": 3,
  "content_references": [
    { "id": 123, "type": "post", "title": "Getting Started" },
    { "id": 45, "type": "page", "title": "About Us" }
  ],
  "check_history": [
    { "checked_at": "2026-01-31T10:30:00Z", "status": "broken", "http_code": 404 },
    { "checked_at": "2026-01-30T10:30:00Z", "status": "broken", "http_code": 404 }
  ]
}
```

---

### Check Single URL

```
POST /lm/v1/health/check
```

Immediately check a URL without scheduling.

**Request Body:**

```json
{
  "url": "https://example.com/page-to-check"
}
```

**Response:**

```json
{
  "url": "https://example.com/page-to-check",
  "status": "healthy",
  "http_code": 200,
  "response_time_ms": 234,
  "redirect_count": 1,
  "final_url": "https://example.com/page-to-check/",
  "redirect_chain": ["https://example.com/page-to-check/"],
  "ssl_valid": true,
  "ssl_expiry": "2027-06-15T00:00:00Z",
  "checked_at": "2026-01-31T14:30:00Z"
}
```

---

### Check Specific Link

```
POST /lm/v1/health/check/{linkId}
```

Check a specific link from the database immediately.

**Response:**

```json
{
  "success": true,
  "result": { /* HealthCheckResult object */ }
}
```

---

### Start Full Health Scan

```
POST /lm/v1/health/scan/start
```

Start a background health scan of all links.

**Request Body:**

```json
{
  "priority": "all",
  "force_recheck": false
}
```

| Field | Type | Description |
|-------|------|-------------|
| `priority` | string | `all`, `high`, `stale` (links not checked recently) |
| `force_recheck` | bool | Recheck even recently checked links |

**Response:**

```json
{
  "job_id": 1,
  "status": "pending",
  "total_links": 1847
}
```

---

### Get Scan Progress

```
GET /lm/v1/health/scan/{jobId}
```

**Response:**

```json
{
  "id": 1,
  "status": "running",
  "total_links": 1847,
  "processed_links": 450,
  "healthy_count": 412,
  "broken_count": 18,
  "slow_count": 8,
  "redirect_count": 12,
  "percentage": 24.4,
  "started_at": "2026-01-31T10:00:00Z",
  "eta_seconds": 320
}
```

---

### Cancel Health Scan

```
POST /lm/v1/health/scan/{jobId}/cancel
```

**Response:**

```json
{
  "success": true,
  "status": "cancelled"
}
```

---

### List Scan Jobs

```
GET /lm/v1/health/jobs
```

**Response:**

```json
{
  "jobs": [
    {
      "id": 1,
      "status": "completed",
      "total_links": 1847,
      "processed_links": 1847,
      "healthy_count": 1702,
      "broken_count": 45,
      "slow_count": 23,
      "redirect_count": 77,
      "started_at": "2026-01-31T08:00:00Z",
      "completed_at": "2026-01-31T08:45:00Z"
    }
  ],
  "total": 12
}
```

---

### List Active Alerts

```
GET /lm/v1/health/alerts
```

**Query Parameters:**

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `page` | int | 1 | Page number |
| `per_page` | int | 20 | Items per page (max 100) |
| `severity` | string | all | `info`, `warning`, `error`, `critical` |
| `type` | string | all | `broken_link`, `redirect_chain`, `slow_response`, `ssl_error`, `dns_error`, `timeout` |
| `acknowledged` | bool | false | Include acknowledged alerts |

**Response:**

```json
{
  "items": [
    {
      "id": 1,
      "health_check_id": 456,
      "alert_type": "broken_link",
      "severity": "error",
      "message": "Link returns 404 Not Found",
      "details": {
        "url": "https://example.com/missing-page",
        "http_code": 404
      },
      "content_id": 123,
      "content_type": "post",
      "acknowledged": false,
      "acknowledged_by": null,
      "acknowledged_at": null,
      "resolved_at": null,
      "created_at": "2026-01-31T10:30:00Z"
    }
  ],
  "total": 68,
  "page": 1,
  "per_page": 20,
  "total_pages": 4
}
```

---

### Get Alert Statistics

```
GET /lm/v1/health/alerts/stats
```

**Response:**

```json
{
  "total_active": 68,
  "by_severity": {
    "critical": 12,
    "error": 33,
    "warning": 18,
    "info": 5
  },
  "by_type": {
    "broken_link": 45,
    "redirect_chain": 8,
    "slow_response": 10,
    "ssl_error": 3,
    "dns_error": 2,
    "timeout": 0
  },
  "acknowledged": 15,
  "resolved_today": 23,
  "new_today": 8
}
```

---

### Acknowledge Alert

```
PUT /lm/v1/health/alerts/{id}/acknowledge
```

**Response:**

```json
{
  "success": true,
  "acknowledged_at": "2026-01-31T14:30:00Z",
  "acknowledged_by": "admin"
}
```

---

### Resolve Alert

```
PUT /lm/v1/health/alerts/{id}/resolve
```

**Response:**

```json
{
  "success": true,
  "resolved_at": "2026-01-31T14:30:00Z"
}
```

---

### Delete Alert

```
DELETE /lm/v1/health/alerts/{id}
```

**Response:**

```json
{
  "success": true
}
```

---

### List Broken Links Report

```
GET /lm/v1/health/broken
```

**Query Parameters:**

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `page` | int | 1 | Page number |
| `per_page` | int | 20 | Items per page |
| `consecutive_failures` | int | - | Min consecutive failures |

**Response:**

```json
{
  "items": [
    {
      "url": "https://example.com/missing",
      "http_code": 404,
      "error_message": "Not Found",
      "consecutive_failures": 5,
      "last_checked_at": "2026-01-31T10:30:00Z",
      "content_count": 3,
      "content_references": [
        { "id": 123, "type": "post", "title": "Article A" }
      ]
    }
  ],
  "total": 45,
  "page": 1,
  "per_page": 20,
  "total_pages": 3
}
```

---

### List Slow Links Report

```
GET /lm/v1/health/slow
```

**Query Parameters:**

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `page` | int | 1 | Page number |
| `per_page` | int | 20 | Items per page |
| `min_response_ms` | int | 2000 | Minimum response time |

**Response:**

```json
{
  "items": [
    {
      "url": "https://slow-server.com/page",
      "response_time_ms": 4500,
      "http_code": 200,
      "last_checked_at": "2026-01-31T10:30:00Z",
      "content_count": 2
    }
  ],
  "total": 23,
  "page": 1,
  "per_page": 20,
  "total_pages": 2
}
```

---

### List Redirect Chains

```
GET /lm/v1/health/redirects
```

**Query Parameters:**

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `min_depth` | int | 2 | Minimum redirect chain depth |

**Response:**

```json
{
  "items": [
    {
      "original_url": "http://example.com/old",
      "final_url": "https://example.com/new-page/",
      "redirect_count": 3,
      "redirect_chain": [
        "https://example.com/old",
        "https://example.com/old/",
        "https://example.com/new-page/"
      ],
      "total_time_ms": 890,
      "content_count": 5
    }
  ],
  "total": 8
}
```

---

### Export Health Report

```
GET /lm/v1/health/export
```

**Query Parameters:**

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `format` | string | csv | `csv` or `json` |
| `status` | string | all | Filter by status |

Returns downloadable file with health check data.

---

### List Exclusions

```
GET /lm/v1/health/exclusions
```

**Response:**

```json
{
  "items": [
    {
      "id": 1,
      "pattern": "localhost",
      "pattern_type": "domain",
      "reason": "Development URLs",
      "created_by": "admin",
      "created_at": "2026-01-15T10:00:00Z"
    }
  ],
  "total": 5
}
```

---

### Add Exclusion

```
POST /lm/v1/health/exclusions
```

**Request Body:**

```json
{
  "pattern": "internal.example.com",
  "pattern_type": "domain",
  "reason": "Internal network, not accessible externally"
}
```

| Field | Type | Description |
|-------|------|-------------|
| `pattern` | string | URL pattern, domain, or regex |
| `pattern_type` | string | `domain`, `url`, `regex` |
| `reason` | string | Reason for exclusion |

**Response:**

```json
{
  "success": true,
  "exclusion": { /* exclusion object */ }
}
```

---

### Delete Exclusion

```
DELETE /lm/v1/health/exclusions/{id}
```

**Response:**

```json
{
  "success": true
}
```

---

## Notification Endpoints

### List Notification Queue

```
GET /lm/v1/notifications
```

**Query Parameters:**

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `page` | int | 1 | Page number |
| `per_page` | int | 20 | Items per page (max 100) |
| `status` | string | all | `PENDING`, `SENT`, `FAILED`, `RETRYING`, `CANCELLED` |
| `channel` | string | all | `EMAIL`, `WEBHOOK`, `ADMIN_NOTICE`, `LOG` |
| `type` | string | - | Filter by notification type |

**Response:**

```json
{
  "items": [
    {
      "id": 1,
      "type": "BROKEN_LINK_DETECTED",
      "channel": "EMAIL",
      "priority": "HIGH",
      "status": "SENT",
      "recipient": "admin@example.com",
      "subject": "Broken Link Detected",
      "attempts": 1,
      "sent_at": "2026-01-31T10:30:00Z",
      "created_at": "2026-01-31T10:29:55Z"
    }
  ],
  "total": 156,
  "page": 1,
  "per_page": 20,
  "total_pages": 8
}
```

---

### Get Notification Details

```
GET /lm/v1/notifications/{id}
```

**Response:**

```json
{
  "id": 1,
  "type": "BROKEN_LINK_DETECTED",
  "channel": "EMAIL",
  "priority": "HIGH",
  "status": "SENT",
  "recipient": "admin@example.com",
  "subject": "Broken Link Detected",
  "payload": {
    "type": "BROKEN_LINK_DETECTED",
    "timestamp": "2026-01-31T10:29:55Z",
    "siteUrl": "https://example.com",
    "siteName": "Example Site",
    "alert": {
      "id": 42,
      "severity": "ERROR",
      "url": "https://broken-link.com/page",
      "httpCode": 404,
      "contentTitle": "Getting Started Guide",
      "contentUrl": "https://example.com/getting-started"
    }
  },
  "attempts": 1,
  "last_attempt_at": "2026-01-31T10:30:00Z",
  "last_error": null,
  "scheduled_for": null,
  "sent_at": "2026-01-31T10:30:00Z",
  "created_at": "2026-01-31T10:29:55Z"
}
```

---

### Retry Failed Notification

```
POST /lm/v1/notifications/{id}/retry
```

**Response:**

```json
{
  "success": true,
  "status": "RETRYING",
  "attempt": 2
}
```

---

### Cancel/Delete Notification

```
DELETE /lm/v1/notifications/{id}
```

**Response:**

```json
{
  "success": true
}
```

---

### List Recipients

```
GET /lm/v1/notifications/recipients
```

**Response:**

```json
{
  "items": [
    {
      "id": 1,
      "email": "admin@example.com",
      "name": "Site Admin",
      "is_active": true,
      "notification_types": ["BROKEN_LINK_DETECTED", "DAILY_HEALTH_DIGEST"],
      "channels": ["EMAIL"],
      "digest_preference": "DAILY",
      "created_at": "2026-01-15T09:00:00Z",
      "updated_at": "2026-01-31T10:00:00Z"
    }
  ],
  "total": 3
}
```

---

### Add Recipient

```
POST /lm/v1/notifications/recipients
```

**Request Body:**

```json
{
  "email": "editor@example.com",
  "name": "Content Editor",
  "notification_types": ["BROKEN_LINK_DETECTED", "SCAN_COMPLETE"],
  "channels": ["EMAIL"],
  "digest_preference": "WEEKLY"
}
```

**Response:**

```json
{
  "success": true,
  "recipient": { /* recipient object */ }
}
```

---

### Update Recipient

```
PUT /lm/v1/notifications/recipients/{id}
```

**Request Body:**

```json
{
  "name": "Senior Editor",
  "notification_types": ["BROKEN_LINK_DETECTED", "SSL_EXPIRY_WARNING"],
  "digest_preference": "DAILY",
  "is_active": true
}
```

**Response:**

```json
{
  "success": true,
  "recipient": { /* updated recipient object */ }
}
```

---

### Remove Recipient

```
DELETE /lm/v1/notifications/recipients/{id}
```

**Response:**

```json
{
  "success": true
}
```

---

### List Webhook Endpoints

```
GET /lm/v1/notifications/webhooks
```

**Response:**

```json
{
  "items": [
    {
      "id": 1,
      "name": "Slack Alerts",
      "url": "https://hooks.slack.com/services/xxx/yyy/zzz",
      "auth_type": "NONE",
      "is_active": true,
      "notification_types": ["BROKEN_LINK_DETECTED", "BROKEN_THRESHOLD_EXCEEDED"],
      "headers": {},
      "retry_enabled": true,
      "last_success_at": "2026-01-31T09:00:00Z",
      "last_failure_at": null,
      "consecutive_failures": 0,
      "created_at": "2026-01-15T10:00:00Z",
      "updated_at": "2026-01-31T09:00:00Z"
    }
  ],
  "total": 2
}
```

---

### Add Webhook Endpoint

```
POST /lm/v1/notifications/webhooks
```

**Request Body:**

```json
{
  "name": "Custom Integration",
  "url": "https://api.myapp.com/webhooks/link-manager",
  "auth_type": "HMAC_SHA256",
  "auth_secret": "my-webhook-secret-key",
  "notification_types": ["BROKEN_LINK_DETECTED", "HEALTH_SCAN_COMPLETE"],
  "headers": {
    "X-Custom-Header": "custom-value"
  },
  "retry_enabled": true
}
```

| Field | Type | Description |
|-------|------|-------------|
| `name` | string | Display name for endpoint |
| `url` | string | Webhook URL |
| `auth_type` | string | `NONE`, `HMAC_SHA256`, `BEARER_TOKEN`, `BASIC_AUTH` |
| `auth_secret` | string | Secret for authentication |
| `notification_types` | array | Subscribed notification types |
| `headers` | object | Custom HTTP headers |
| `retry_enabled` | bool | Enable automatic retries |

**Response:**

```json
{
  "success": true,
  "webhook": { /* webhook object (auth_secret redacted) */ }
}
```

---

### Update Webhook Endpoint

```
PUT /lm/v1/notifications/webhooks/{id}
```

**Request Body:**

```json
{
  "name": "Updated Integration",
  "notification_types": ["BROKEN_LINK_DETECTED"],
  "is_active": false
}
```

**Response:**

```json
{
  "success": true,
  "webhook": { /* updated webhook object */ }
}
```

---

### Remove Webhook Endpoint

```
DELETE /lm/v1/notifications/webhooks/{id}
```

**Response:**

```json
{
  "success": true
}
```

---

### Test Webhook Endpoint

```
POST /lm/v1/notifications/webhooks/{id}/test
```

**Response:**

```json
{
  "success": true,
  "response_code": 200,
  "response_time_ms": 245,
  "response_body": "{\"ok\":true}"
}
```

---

### Get Notification Settings

```
GET /lm/v1/notifications/settings
```

**Response:**

```json
{
  "email_enabled": true,
  "webhook_enabled": true,
  "admin_notice_enabled": true,
  "digest_enabled": true,
  "digest_time": "09:00",
  "broken_threshold": 5,
  "slow_threshold": 10,
  "ssl_warning_days": 30
}
```

---

### Update Notification Settings

```
PUT /lm/v1/notifications/settings
```

**Request Body:**

```json
{
  "email_enabled": true,
  "digest_time": "08:00",
  "broken_threshold": 10,
  "ssl_warning_days": 14
}
```

**Response:**

```json
{
  "success": true,
  "settings": { /* updated settings object */ }
}
```

---

### Get Notification Statistics

```
GET /lm/v1/notifications/stats
```

**Query Parameters:**

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `period` | string | 7d | Time period: `24h`, `7d`, `30d`, `all` |

**Response:**

```json
{
  "period": "7d",
  "total_sent": 156,
  "total_failed": 3,
  "by_channel": {
    "EMAIL": { "sent": 120, "failed": 2 },
    "WEBHOOK": { "sent": 35, "failed": 1 },
    "ADMIN_NOTICE": { "sent": 1, "failed": 0 }
  },
  "by_type": {
    "BROKEN_LINK_DETECTED": 45,
    "DAILY_HEALTH_DIGEST": 7,
    "HEALTH_SCAN_COMPLETE": 14
  },
  "avg_delivery_time_ms": 1250,
  "success_rate": 98.1
}
```

---

### Get Delivery Log

```
GET /lm/v1/notifications/log
```

**Query Parameters:**

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `page` | int | 1 | Page number |
| `per_page` | int | 50 | Items per page (max 100) |
| `channel` | string | all | Filter by channel |
| `status` | string | all | `SENT` or `FAILED` |
| `from` | string | - | ISO date start |
| `to` | string | - | ISO date end |

**Response:**

```json
{
  "items": [
    {
      "id": 1,
      "notification_id": 42,
      "channel": "EMAIL",
      "recipient": "admin@example.com",
      "type": "BROKEN_LINK_DETECTED",
      "status": "SENT",
      "response_code": null,
      "duration_ms": 1540,
      "error_message": null,
      "created_at": "2026-01-31T10:30:00Z"
    }
  ],
  "total": 2450,
  "page": 1,
  "per_page": 50,
  "total_pages": 49
}
```

---

### Clear Old Log Entries

```
DELETE /lm/v1/notifications/log
```

**Query Parameters:**

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `days` | int | 30 | Delete entries older than X days |

**Response:**

```json
{
  "success": true,
  "deleted_count": 1845
}
```

---

### Send Digest Now

```
POST /lm/v1/notifications/digest/send
```

**Request Body:**

```json
{
  "recipient_id": 1,
  "period": "daily"
}
```

| Field | Type | Description |
|-------|------|-------------|
| `recipient_id` | int? | Specific recipient (null = all) |
| `period` | string | `daily` or `weekly` |

**Response:**

```json
{
  "success": true,
  "sent_count": 3,
  "recipients": ["admin@example.com", "editor@example.com", "manager@example.com"]
}
```

---

### Preview Digest Content

```
GET /lm/v1/notifications/digest/preview
```

**Query Parameters:**

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `period` | string | daily | `daily` or `weekly` |

**Response:**

```json
{
  "period": "daily",
  "from": "2026-01-30T00:00:00Z",
  "to": "2026-01-31T00:00:00Z",
  "summary": {
    "total_links": 1847,
    "broken_links": 12,
    "slow_links": 8,
    "new_alerts": 5,
    "resolved_alerts": 3
  },
  "top_issues": [
    {
      "url": "https://broken-site.com/page",
      "http_code": 404,
      "affected_content_count": 3
    }
  ],
  "html_preview": "<!-- rendered email HTML -->"
}
```

---

### Update Digest Schedule

```
PUT /lm/v1/notifications/digest/schedule
```

**Request Body:**

```json
{
  "time": "08:00",
  "timezone": "America/New_York",
  "days": ["monday", "wednesday", "friday"]
}
```

**Response:**

```json
{
  "success": true,
  "next_scheduled": "2026-02-01T08:00:00-05:00"
}
```

---

## Yoast SEO Endpoints

### Check Yoast Status

```
GET /lm/v1/yoast/status
```

**Response:**

```json
{
  "installed": true,
  "active": true,
  "version": "23.1",
  "premium_installed": true,
  "premium_active": true,
  "premium_version": "23.1"
}
```

---

### Get Yoast Settings

```
GET /lm/v1/yoast/settings
```

**Response:**

```json
{
  "focus_keyword": {
    "auto_generate_enabled": true,
    "source": "title",
    "max_length": 60,
    "trim_mode": "word_boundary",
    "min_words": 1,
    "max_words": 5,
    "exclude_stop_words": true,
    "stop_words": ["a", "an", "the", "and", "or", "but", "in", "on", "at", "to", "for", "of", "with", "by"]
  },
  "multiple_keywords": {
    "enabled": true,
    "max_keywords": 5,
    "extraction_method": "title_words",
    "min_word_length": 3,
    "exclude_numbers": true
  },
  "meta_description": {
    "max_length": 140,
    "min_length": 50,
    "trim_enabled": true,
    "trim_mode": "remove_last_word",
    "add_ellipsis": true
  },
  "content_types": {
    "posts": true,
    "pages": true,
    "categories": true,
    "tags": false,
    "custom_post_types": []
  },
  "batch_processing": {
    "batch_size": 25,
    "delay_between_batches_ms": 500
  }
}
```

---

### Update Yoast Settings

```
PUT /lm/v1/yoast/settings
```

**Request Body:**

```json
{
  "focus_keyword.max_length": 60,
  "focus_keyword.trim_mode": "word_boundary",
  "focus_keyword.exclude_stop_words": true,
  "meta_description.max_length": 140,
  "meta_description.trim_mode": "remove_last_word",
  "meta_description.add_ellipsis": true,
  "batch_processing.batch_size": 25
}
```

**Response:**

```json
{
  "success": true,
  "updated_settings": ["focus_keyword.max_length", "meta_description.trim_mode"],
  "settings": { /* full settings object */ }
}
```

---

### Reset Settings to Defaults

```
POST /lm/v1/yoast/settings/reset
```

**Response:**

```json
{
  "success": true,
  "settings": { /* default settings object */ }
}
```

---

### List Content Missing Focus Keywords

```
GET /lm/v1/yoast/content/missing-keywords
```

**Query Parameters:**

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `page` | int | 1 | Page number |
| `per_page` | int | 20 | Items per page (max 100) |
| `post_types` | string | post,page | Comma-separated: `post`, `page`, `category` |
| `search` | string | - | Search by title |

**Response:**

```json
{
  "items": [
    {
      "id": 123,
      "type": "post",
      "title": "How to Optimize Your Website for Better Performance",
      "slug": "optimize-website-performance",
      "url": "https://example.com/optimize-website-performance",
      "edit_url": "https://example.com/wp-admin/post.php?post=123&action=edit",
      "published_at": "2026-01-15T10:00:00Z",
      "suggested_keyword": "optimize website",
      "suggested_multiple_keywords": ["optimize", "website", "performance", "better"]
    }
  ],
  "total": 67,
  "page": 1,
  "per_page": 20,
  "total_pages": 4,
  "stats": {
    "posts_missing": 47,
    "pages_missing": 12,
    "categories_missing": 8
  }
}
```

---

### List Oversized Meta Descriptions

```
GET /lm/v1/yoast/content/oversized-descriptions
```

**Query Parameters:**

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `page` | int | 1 | Page number |
| `per_page` | int | 20 | Items per page (max 100) |
| `max_length` | int | 140 | Description length threshold |
| `post_types` | string | post,page | Comma-separated: `post`, `page`, `category` |

**Response:**

```json
{
  "items": [
    {
      "id": 456,
      "type": "page",
      "title": "About Our Company",
      "meta_description": "This comprehensive page covers everything you need to know about our company, including our history, mission, values, team members, and what makes us unique in the industry.",
      "description_length": 187,
      "trimmed_preview": "This comprehensive page covers everything you need to know about our company, including our history, mission, values, team...",
      "chars_to_remove": 47
    }
  ],
  "total": 23,
  "page": 1,
  "per_page": 20,
  "total_pages": 2
}
```

---

### Get Content Statistics

```
GET /lm/v1/yoast/content/stats
```

**Response:**

```json
{
  "posts": {
    "total": 294,
    "missing_keyword": 47,
    "with_keyword": 247,
    "with_multiple_keywords": 89,
    "oversized_descriptions": 15
  },
  "pages": {
    "total": 45,
    "missing_keyword": 12,
    "with_keyword": 33,
    "with_multiple_keywords": 20,
    "oversized_descriptions": 5
  },
  "categories": {
    "total": 28,
    "missing_keyword": 8,
    "with_keyword": 20,
    "with_multiple_keywords": 0,
    "oversized_descriptions": 3
  },
  "overall_seo_score": 78
}
```

---

### Optimize Single Content Item

```
POST /lm/v1/yoast/content/{id}/optimize
```

**Request Body:**

```json
{
  "post_type": "post",
  "operations": ["focus_keyword", "multiple_keywords", "meta_description"],
  "custom_keyword": null,
  "custom_description": null
}
```

| Field | Type | Description |
|-------|------|-------------|
| `post_type` | string | `post`, `page`, or `category` |
| `operations` | string[] | Which optimizations to apply |
| `custom_keyword` | string? | Override auto-generated keyword |
| `custom_description` | string? | Override trimmed description |

**Response:**

```json
{
  "success": true,
  "content_id": 123,
  "applied": {
    "focus_keyword": {
      "old_value": null,
      "new_value": "optimize website performance",
      "source": "auto_generated"
    },
    "multiple_keywords": {
      "old_value": [],
      "new_value": ["optimize", "website", "performance"],
      "source": "auto_generated"
    },
    "meta_description": {
      "old_value": "This is a very long description that exceeds the configured limit...",
      "new_value": "This is a very long description that exceeds the configured...",
      "chars_removed": 47
    }
  },
  "audit_log_ids": [101, 102, 103]
}
```

---

### Batch Set Focus Keywords

```
POST /lm/v1/yoast/batch/focus-keywords
```

**Request Body:**

```json
{
  "content_ids": [123, 456, 789],
  "post_type": "post",
  "use_custom": false,
  "custom_keywords": null,
  "add_to_queue": true
}
```

| Field | Type | Description |
|-------|------|-------------|
| `content_ids` | int[] | Content IDs to optimize |
| `post_type` | string | `post`, `page`, or `category` |
| `use_custom` | bool | Use custom keywords mapping |
| `custom_keywords` | object? | Map of `{id: keyword}` |
| `add_to_queue` | bool | Process immediately or queue |

**Response (immediate):**

```json
{
  "success": true,
  "processed": 3,
  "results": [
    { "id": 123, "success": true, "keyword": "optimize website" },
    { "id": 456, "success": true, "keyword": "contact us" },
    { "id": 789, "success": false, "error": "Post not found", "error_code": 14953 }
  ],
  "failed": 1
}
```

**Response (queued):**

```json
{
  "success": true,
  "queued": 3,
  "queue_ids": [501, 502, 503],
  "estimated_completion": "2026-01-31T11:00:00Z"
}
```

---

### Batch Set Multiple Keywords (Premium)

```
POST /lm/v1/yoast/batch/multiple-keywords
```

**Request Body:**

```json
{
  "content_ids": [123, 456],
  "post_type": "post",
  "extraction_settings": {
    "max_keywords": 5,
    "min_word_length": 3,
    "exclude_numbers": true
  }
}
```

**Response:**

```json
{
  "success": true,
  "processed": 2,
  "results": [
    { "id": 123, "success": true, "keywords": ["optimize", "website", "performance"] },
    { "id": 456, "success": true, "keywords": ["contact", "support", "help"] }
  ],
  "failed": 0,
  "premium_required": false
}
```

**Error Response (no Premium):**

```json
{
  "success": false,
  "error": "Yoast Premium is required for multiple keywords",
  "error_code": 14951
}
```

---

### Batch Trim Meta Descriptions

```
POST /lm/v1/yoast/batch/trim-descriptions
```

**Request Body:**

```json
{
  "content_ids": [123, 456, 789],
  "post_type": "post",
  "max_length": 140,
  "trim_mode": "remove_last_word",
  "add_ellipsis": true,
  "add_to_queue": false
}
```

**Response:**

```json
{
  "success": true,
  "processed": 3,
  "results": [
    { 
      "id": 123, 
      "success": true, 
      "old_length": 187, 
      "new_length": 138,
      "trimmed_description": "This comprehensive page covers everything you need to know about our company, including our history, mission, values..."
    },
    { "id": 456, "success": true, "old_length": 156, "new_length": 139 },
    { "id": 789, "success": false, "error": "No meta description set", "skipped": true }
  ],
  "failed": 0,
  "skipped": 1
}
```

---

### Get Optimization Queue

```
GET /lm/v1/yoast/queue
```

**Query Parameters:**

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `status` | string | all | `all`, `pending`, `processing`, `completed`, `failed` |
| `type` | string | all | `all`, `focus_keyword`, `multiple_keywords`, `meta_description` |
| `page` | int | 1 | Page number |
| `per_page` | int | 50 | Items per page |

**Response:**

```json
{
  "items": [
    {
      "id": 501,
      "wp_post_id": 123,
      "post_type": "post",
      "post_title": "How to Optimize Your Website",
      "optimization_type": "FOCUS_KEYWORD",
      "status": "PENDING",
      "priority": 0,
      "scheduled_at": null,
      "processed_at": null,
      "error_message": null,
      "created_at": "2026-01-31T10:30:00Z"
    }
  ],
  "total": 47,
  "page": 1,
  "per_page": 50,
  "total_pages": 1,
  "stats": {
    "pending": 42,
    "processing": 1,
    "completed": 156,
    "failed": 4
  }
}
```

---

### Cancel Queued Optimization

```
DELETE /lm/v1/yoast/queue/{id}
```

**Response:**

```json
{
  "success": true,
  "queue_id": 501,
  "status": "CANCELLED"
}
```

---

### Get Audit Log

```
GET /lm/v1/yoast/audit-log
```

**Query Parameters:**

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `page` | int | 1 | Page number |
| `per_page` | int | 50 | Items per page |
| `action_type` | string | all | `focus_keyword`, `multiple_keywords`, `meta_description` |
| `post_type` | string | all | `post`, `page`, `category` |
| `from_date` | string | - | ISO 8601 date |
| `to_date` | string | - | ISO 8601 date |

**Response:**

```json
{
  "items": [
    {
      "id": 101,
      "wp_post_id": 123,
      "post_type": "post",
      "post_title": "How to Optimize Your Website",
      "action_type": "focus_keyword",
      "field_modified": "_yoast_wpseo_focuskw",
      "old_value": null,
      "new_value": "optimize website performance",
      "auto_generated": true,
      "created_at": "2026-01-31T10:35:00Z",
      "can_revert": true
    }
  ],
  "total": 234,
  "page": 1,
  "per_page": 50,
  "total_pages": 5
}
```

---

### Revert Audit Log Entry

```
POST /lm/v1/yoast/audit-log/{id}/revert
```

**Response:**

```json
{
  "success": true,
  "reverted": {
    "audit_id": 101,
    "wp_post_id": 123,
    "field": "_yoast_wpseo_focuskw",
    "from_value": "optimize website performance",
    "to_value": null
  },
  "new_audit_id": 235
}
```

**Error Response:**

```json
{
  "success": false,
  "error": "Cannot revert: field has been modified since this change",
  "error_code": 14956,
  "current_value": "different keyword"
}
```

---

## Rate Limiting

No explicit rate limiting, but scan, bulk linking, health check, notification, and Yoast optimization operations are naturally throttled via batch processing.

---

## Dependencies

- All backend service specs (09-16, 21-24, 27)
- WordPress REST API
- WordPress authentication
- Yoast SEO plugin (for Yoast endpoints)
