# Issue: QUpload Admin UI Missing Version Header and Modern Design

> **ID:** 27-qupload-ui-uplift-version-header
> **Date:** 2026-03-13
> **Category:** WordPress/UI
> **Status:** Open

---

## Issue Summary

1. **What happened:** The QUpload admin interface lacks a version number in its header and does not match the modern UI design of the Riseup Asia Uploader plugin.
2. **Where it happened:** QUpload admin templates (`wp-plugins/qupload/templates/`)
3. **Symptoms and impact:** Inconsistent visual experience between the two plugins. No version visibility makes debugging harder. Users perceive QUpload as less polished.
4. **How it was discovered:** Visual comparison with Riseup Asia Uploader.

## Fix Description

1. Add version number to QUpload admin header (matching Riseup Asia Uploader pattern)
2. Uplift QUpload admin UI to match Riseup Asia Uploader's design system:
   - Inter + JetBrains Mono typography
   - Gradient buttons with ripple effects
   - Backdrop-blur modals
   - `quFadeInUp` and `quShimmer` animations
   - High-contrast color scheme
3. Both plugins already share `admin-shared.css` — leverage existing shared styles

## Reference

- Riseup Asia Uploader admin header with version: check `wp-plugins/riseup-asia-uploader/templates/` for header pattern
- Shared CSS: `admin-shared.css` (referenced in `.lovable/memory/style/unified-admin-ui-design`)

## TODO and Follow-Ups

1. Add version badge to QUpload admin header
2. Apply animation classes from shared CSS
3. Verify visual parity with Riseup Asia Uploader

## Done Checklist

- [ ] Spec updated
- [x] Issue write-up created
- [ ] Templates updated
- [ ] Visual QA completed
