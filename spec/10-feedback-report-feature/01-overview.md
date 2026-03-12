# Feature Spec: Report / Feedback System

**Version:** 1.0.0  
**Since:** 2.6.0  
**Date:** 2026-03-12

## 1. Purpose

Allow WordPress admins to submit bug reports and feature feedback directly from the Riseup Asia Uploader admin panel. Reports are sent via `wp_mail()` to a configurable support email address.

## 2. UI Location

- **Dedicated submenu:** "Report / Feedback" under the Riseup Uploader main menu.
- **Quick button:** On the Error Log page header — a "Report Issue" button that opens the same modal.

## 3. Feedback Modal

A modal overlay (using the existing `modal-wrapper.php` partial) with:

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| Subject | Text input | Yes | Max 200 chars |
| Description | Textarea | Yes | Min 20 chars |
| Screenshots | File input (multiple) | No | Up to 3 images, max 2 MB each. Accepted: jpg, jpeg, png, gif, webp |
| Include system info | Checkbox | No | Default: checked. Appends PHP version, WP version, plugin version, active theme, site URL |

## 4. Email Delivery

- Uses `wp_mail()` which respects any SMTP plugin (GoSMTP, WP Mail SMTP, etc.).
- From: WordPress configured sender.
- To: Support email from plugin settings (`OptionNameType::SupportSettings`).
- Attachments: Temporary uploaded files passed to `wp_mail()`, cleaned up after send.
- Body: Plain text with system info footer (if checkbox selected).

### Email Readiness Check

Before showing the modal, an AJAX call checks if:
1. A support email is configured in settings.
2. If no support email is configured, show an inline notice with:
   - A link to the settings page to configure the support email.
   - If a fallback URL is configured, show a link: "Or submit a ticket manually at [URL]".

## 5. Settings (Admin Settings Page)

New settings section "Support & Feedback" added to the existing Settings page:

| Setting | Type | Default | Stored In |
|---------|------|---------|-----------|
| Support Email | email input | (empty) | `OptionNameType::SupportSettings` → `support_email` |
| Fallback Ticket URL | url input | (empty) | `OptionNameType::SupportSettings` → `fallback_url` |

## 6. Enums & Keys

### OptionNameType
- `SupportSettings = 'RiseupSupportSettings'`

### AjaxActionType
- `SendFeedback = 'riseup_send_feedback'`
- `CheckFeedbackReady = 'riseup_check_feedback_ready'`

### AdminPageType
- `Feedback = 'riseup-asia-feedback'`

### NonceType
- `Feedback = 'riseup_feedback_nonce'`

## 7. Files Created/Modified

### New Files
- `includes/Admin/Traits/AdminFeedbackAjaxTrait.php` — AJAX handlers
- `templates/admin-feedback.php` — Feedback page template
- `templates/partials/shared/modal-feedback.php` — Modal partial
- `assets/js/admin-feedback.js` — Form submission logic
- `assets/css/admin-feedback.css` — Modal + form styles

### Modified Files
- `includes/Admin/Admin.php` — Use new trait, register AJAX hooks
- `includes/Admin/Traits/AdminMenuTrait.php` — Add submenu, enqueue assets
- `includes/Admin/Traits/AdminPagesTrait.php` — Add render method
- `includes/Admin/Traits/AdminSettingsTrait.php` — Register + sanitize support settings
- `includes/Enums/OptionNameType.php` — Add `SupportSettings`
- `includes/Enums/AjaxActionType.php` — Add `SendFeedback`, `CheckFeedbackReady`
- `includes/Enums/AdminPageType.php` — Add `Feedback`
- `includes/Enums/NonceType.php` — Add `Feedback`
- `templates/admin-settings.php` — Add support settings section
- `templates/admin-errors.php` — Add "Report Issue" button

## 8. Security

- Nonce verification on all AJAX calls.
- `manage_options` capability check.
- File type validation (MIME + extension whitelist).
- File size validation (max 2 MB per file).
- All user input sanitized (`sanitize_text_field`, `sanitize_textarea_field`, `sanitize_email`, `esc_url_raw`).
- Temp files cleaned up after `wp_mail()`.

## 9. Error Handling

- All AJAX handlers wrapped in try-catch(Throwable).
- If `wp_mail()` fails, return error JSON with diagnostic info.
- If no SMTP plugin detected and wp_mail fails, suggest configuring SMTP.
- FileLogger captures all feedback send attempts (success + failure).
