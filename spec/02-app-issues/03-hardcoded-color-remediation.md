# Hardcoded Color Remediation Plan

> **Created:** 2026-03-04  
> **Status:** Active  
> **Rule:** [Forbidden Patterns §11](../spec/06-php-standards/forbidden-patterns.md)

---

## Summary

After introducing `ColorConfig` (JSON-driven color loader) and `data/colors.json`, a codebase scan found **hardcoded hex color codes** across 6 template files. This plan tracks their migration to `ColorConfig` calls.

---

## ✅ Completed

| File | Pattern | Fix Applied |
|------|---------|-------------|
| `templates/admin-errors.php` | `$levelColors = array('#dc3545', ...)` | `ColorConfig::getGroup('logLevel')` |

---

## 🔲 Remaining — PHP Logic Colors (Priority 1)

These are hex codes used in **PHP logic** (inline `style=` attributes with PHP conditionals). They should use `ColorConfig`.

| # | File | Line(s) | Hardcoded Value | Replacement |
|---|------|---------|-----------------|-------------|
| 1 | `templates/admin-settings.php` | 180-181 | `#46b450` (success color for "Update available") | `ColorConfig::status('success')` |
| 2 | `templates/admin-settings.php` | 421-427 | `#2271b1`, `#dcdcde`, `#f0f6fc` (storage mode card borders) | `ColorConfig::wpAdmin('primary')`, `wpAdmin('border')`, `wpAdmin('primaryBg')` |
| 3 | `templates/admin-settings.php` | 423, 429 | `#2271b1` (dashicons color) | `ColorConfig::wpAdmin('primary')` |
| 4 | `templates/admin-settings.php` | 446-447 | `#2271b1` (slider accent, value color) | `ColorConfig::wpAdmin('primary')` |
| 5 | `templates/admin-settings.php` | 622 | `#dc3232` / `#46b450` (JS showStatus) | PHP-injected `STATUS_COLORS` JS constant |
| 6 | `templates/admin-settings.php` | 738 | `#dc3232` / `#46b450` (JS showSnapStatus) | PHP-injected `STATUS_COLORS` JS constant |
| 7 | `templates/admin-settings.php` | 761-762 | `#2271b1`, `#dcdcde`, `#f0f6fc` (JS storage mode toggle) | PHP-injected `THEME_COLORS` JS constant |
| 8 | `templates/admin-snapshots.php` | 142 | `#2271b1` (progress badge bg) | `ColorConfig::wpAdmin('primary')` |
| 9 | `templates/admin-snapshots.php` | 233-234 | `#2271b1`, `#7b1fa2` (chart legend dots) | `ColorConfig::wpAdmin('primary')`, `wpAdmin('snapshotIncremental')` |
| 10 | `templates/admin-snapshots.php` | 752-754 | `#6c3483`, `#2271b1` (JS snapshot mode icons) | PHP-injected `SNAP_COLORS` JS constant |
| 11 | `templates/admin-agents.php` | 532 | `#dc3232` (JS delete button color) | PHP-injected `STATUS_COLORS` JS constant |

---

## 🔲 Remaining — CSS `<style>` Block Colors (Priority 2 — Exempt but Trackable)

Per the spec exception, single-use CSS colors in `<style>` blocks are **exempt**. However, colors that repeat across multiple templates should be consolidated into CSS custom properties fed by `ColorConfig`.

| # | File | Pattern | Count | Notes |
|---|------|---------|-------|-------|
| 1 | `templates/admin-snapshots.php` | `#2271b1` in CSS classes | ~8 | `.riseup-progress-bar`, `.riseup-bar-full`, `.riseup-cal-today`, etc. |
| 2 | `templates/admin-snapshots.php` | `#646970` in CSS | ~4 | `.riseup-storage-desc`, `.riseup-slider-value`, legend text |
| 3 | `templates/admin-snapshots.php` | `#dcdcde` in CSS | ~2 | `.riseup-storage-card` border |
| 4 | `templates/admin-agents.php` | `#dc3232` in CSS | ~3 | `.required`, `.error`, `.riseup-modal-close:hover` |
| 5 | `templates/admin-agents.php` | `#46b450` in CSS | ~1 | `.success` |
| 6 | `templates/admin-license.php` | `#46b450`, `#dc3232` in CSS | ~2 | `.success`, `.error` |
| 7 | `templates/admin-logs.php` | `#667eea`, `#dcdcde` in CSS | ~2 | Header gradient, border |
| 8 | `templates/partials/admin-errors-styles.php` | `#46b450` in CSS | ~2 | Success icon, live dot |

### Suggested CSS Consolidation (Future)

```php
<!-- In admin template headers -->
<style>
:root {
    --riseup-primary: <?php echo esc_attr(ColorConfig::wpAdmin('primary')); ?>;
    --riseup-success: <?php echo esc_attr(ColorConfig::status('success')); ?>;
    --riseup-error: <?php echo esc_attr(ColorConfig::status('error')); ?>;
    --riseup-border: <?php echo esc_attr(ColorConfig::wpAdmin('border')); ?>;
    --riseup-text-muted: <?php echo esc_attr(ColorConfig::wpAdmin('textMuted')); ?>;
}
</style>
```

Then replace all CSS hex codes with `var(--riseup-primary)`, etc.

---

## Implementation Order

1. **Phase 1**: Fix Priority 1 items in `admin-settings.php` (items 1-7) — highest concentration
2. **Phase 2**: Fix Priority 1 items in `admin-snapshots.php` (items 8-10)
3. **Phase 3**: Fix Priority 1 item in `admin-agents.php` (item 11)
4. **Phase 4** (optional): Introduce CSS custom properties for Priority 2 consolidation

---

*Plan created 2026-03-04*
