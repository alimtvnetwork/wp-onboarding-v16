# 26 - Email Templates

> **Status:** Complete  
> **Priority:** Medium  
> **Updated:** 2026-01-31

---

## Purpose

Defines email templates for Link Manager notifications including HTML and plain text versions with variable placeholders for broken link alerts, health reports, and digest summaries.

---

## Template Architecture

```
EmailTemplateEngine
├── TemplateRegistry (template definitions)
├── VariableResolver (placeholder replacement)
├── HtmlRenderer (HTML output)
└── PlainTextRenderer (text fallback)
```

---

## Template Categories

| Category | Templates | Trigger |
|----------|-----------|---------|
| Alerts | Broken Links, SSL Error, Timeout | Immediate on detection |
| Health | Health Check Failed, Recovery | Health monitor events |
| Digests | Daily Summary, Weekly Report | Scheduled via cron |
| System | Test Notification, Welcome | Manual/setup |

---

## Variable Syntax

```
{{variable_name}}           → Simple replacement
{{variable_name|default}}   → With fallback value
{{#if condition}}...{{/if}} → Conditional blocks
{{#each items}}...{{/each}} → Iteration blocks
{{count|number}}            → Number formatting
{{date|date:Y-m-d}}         → Date formatting
```

---

## Global Variables

Available in all templates:

| Variable | Type | Description |
|----------|------|-------------|
| `{{site_name}}` | string | WordPress site title |
| `{{site_url}}` | string | Site home URL |
| `{{admin_url}}` | string | WP admin URL |
| `{{plugin_url}}` | string | Link Manager dashboard URL |
| `{{recipient_name}}` | string | Recipient display name |
| `{{recipient_email}}` | string | Recipient email address |
| `{{generated_at}}` | datetime | Email generation timestamp |
| `{{unsubscribe_url}}` | string | Preference management URL |

---

## Template: Broken Links Alert

### Trigger
Sent when broken link count exceeds configured threshold.

### Variables

| Variable | Type | Description |
|----------|------|-------------|
| `{{broken_count}}` | int | Number of broken links |
| `{{threshold}}` | int | Configured alert threshold |
| `{{links}}` | array | List of broken link objects |
| `{{links.*.url}}` | string | The broken URL |
| `{{links.*.source_title}}` | string | Content containing the link |
| `{{links.*.source_url}}` | string | Edit URL for content |
| `{{links.*.http_code}}` | int | HTTP response code |
| `{{links.*.error_message}}` | string | Error description |
| `{{links.*.first_detected}}` | datetime | When first found broken |
| `{{scan_id}}` | int | Related scan ID |
| `{{scan_completed_at}}` | datetime | Scan completion time |

### HTML Template

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Broken Links Alert - {{site_name}}</title>
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #1d2327; margin: 0; padding: 0; background-color: #f0f0f1; }
    .container { max-width: 600px; margin: 0 auto; background: #ffffff; }
    .header { background: #d63638; color: #ffffff; padding: 24px; text-align: center; }
    .header h1 { margin: 0; font-size: 24px; font-weight: 600; }
    .content { padding: 24px; }
    .alert-box { background: #fcf0f1; border-left: 4px solid #d63638; padding: 16px; margin-bottom: 24px; }
    .alert-box strong { color: #d63638; }
    .link-table { width: 100%; border-collapse: collapse; margin: 16px 0; }
    .link-table th { background: #f0f0f1; text-align: left; padding: 12px; font-size: 12px; text-transform: uppercase; color: #50575e; }
    .link-table td { padding: 12px; border-bottom: 1px solid #c3c4c7; font-size: 14px; }
    .link-table tr:last-child td { border-bottom: none; }
    .url { word-break: break-all; color: #2271b1; }
    .error-code { display: inline-block; background: #d63638; color: #fff; padding: 2px 8px; border-radius: 3px; font-size: 12px; font-weight: 600; }
    .btn { display: inline-block; background: #2271b1; color: #ffffff; text-decoration: none; padding: 12px 24px; border-radius: 4px; font-weight: 600; }
    .btn:hover { background: #135e96; }
    .footer { background: #f0f0f1; padding: 16px 24px; text-align: center; font-size: 12px; color: #50575e; }
    .footer a { color: #2271b1; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1>⚠️ Broken Links Detected</h1>
    </div>
    <div class="content">
      <p>Hi {{recipient_name|there}},</p>
      
      <div class="alert-box">
        <strong>{{broken_count}} broken link{{#if broken_count > 1}}s{{/if}}</strong> detected on <strong>{{site_name}}</strong>
      </div>
      
      <p>The following links are returning errors and may affect your visitors' experience:</p>
      
      <table class="link-table">
        <thead>
          <tr>
            <th>Broken URL</th>
            <th>Found In</th>
            <th>Error</th>
          </tr>
        </thead>
        <tbody>
          {{#each links}}
          <tr>
            <td><span class="url">{{url}}</span></td>
            <td><a href="{{source_url}}">{{source_title}}</a></td>
            <td><span class="error-code">{{http_code}}</span> {{error_message}}</td>
          </tr>
          {{/each}}
        </tbody>
      </table>
      
      <p style="text-align: center; margin-top: 24px;">
        <a href="{{plugin_url}}" class="btn">Review in Link Manager</a>
      </p>
      
      <p style="font-size: 13px; color: #50575e; margin-top: 24px;">
        Scan completed: {{scan_completed_at|date:F j, Y \a\t g:i A}}
      </p>
    </div>
    <div class="footer">
      <p>This alert was sent because broken links exceeded your threshold of {{threshold}}.</p>
      <p><a href="{{unsubscribe_url}}">Manage notification preferences</a> | <a href="{{site_url}}">{{site_name}}</a></p>
    </div>
  </div>
</body>
</html>
```

### Plain Text Template

```
BROKEN LINKS ALERT
==================

Hi {{recipient_name|there}},

{{broken_count}} broken link{{#if broken_count > 1}}s{{/if}} detected on {{site_name}}.

BROKEN LINKS:
{{#each links}}
- URL: {{url}}
  Found in: {{source_title}}
  Error: {{http_code}} - {{error_message}}
  Edit: {{source_url}}
  
{{/each}}

Review all broken links:
{{plugin_url}}

---
Scan completed: {{scan_completed_at|date:F j, Y \a\t g:i A}}
This alert was sent because broken links exceeded your threshold of {{threshold}}.

Manage preferences: {{unsubscribe_url}}
{{site_name}} - {{site_url}}
```

---

## Template: SSL Certificate Error

### Trigger
Sent when SSL certificate issues are detected.

### Variables

| Variable | Type | Description |
|----------|------|-------------|
| `{{domain}}` | string | Affected domain |
| `{{error_type}}` | string | SSL error type |
| `{{error_details}}` | string | Detailed error message |
| `{{expires_at}}` | datetime | Certificate expiry (if applicable) |
| `{{days_until_expiry}}` | int | Days until expiration |
| `{{affected_links_count}}` | int | Number of links affected |
| `{{affected_links}}` | array | Sample of affected links |

### HTML Template

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SSL Certificate Alert - {{site_name}}</title>
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #1d2327; margin: 0; padding: 0; background-color: #f0f0f1; }
    .container { max-width: 600px; margin: 0 auto; background: #ffffff; }
    .header { background: #dba617; color: #1d2327; padding: 24px; text-align: center; }
    .header h1 { margin: 0; font-size: 24px; font-weight: 600; }
    .content { padding: 24px; }
    .warning-box { background: #fcf9e8; border-left: 4px solid #dba617; padding: 16px; margin-bottom: 24px; }
    .detail-row { display: flex; border-bottom: 1px solid #c3c4c7; padding: 12px 0; }
    .detail-label { font-weight: 600; width: 140px; color: #50575e; }
    .detail-value { flex: 1; }
    .btn { display: inline-block; background: #2271b1; color: #ffffff; text-decoration: none; padding: 12px 24px; border-radius: 4px; font-weight: 600; }
    .footer { background: #f0f0f1; padding: 16px 24px; text-align: center; font-size: 12px; color: #50575e; }
    .footer a { color: #2271b1; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1>🔒 SSL Certificate Issue</h1>
    </div>
    <div class="content">
      <p>Hi {{recipient_name|there}},</p>
      
      <div class="warning-box">
        An SSL certificate issue was detected for <strong>{{domain}}</strong>
      </div>
      
      <div class="detail-row">
        <span class="detail-label">Error Type:</span>
        <span class="detail-value">{{error_type}}</span>
      </div>
      <div class="detail-row">
        <span class="detail-label">Details:</span>
        <span class="detail-value">{{error_details}}</span>
      </div>
      {{#if expires_at}}
      <div class="detail-row">
        <span class="detail-label">Expires:</span>
        <span class="detail-value">{{expires_at|date:F j, Y}} ({{days_until_expiry}} days)</span>
      </div>
      {{/if}}
      <div class="detail-row">
        <span class="detail-label">Affected Links:</span>
        <span class="detail-value">{{affected_links_count}} links on your site</span>
      </div>
      
      <p style="text-align: center; margin-top: 24px;">
        <a href="{{plugin_url}}" class="btn">View Affected Links</a>
      </p>
    </div>
    <div class="footer">
      <p><a href="{{unsubscribe_url}}">Manage notification preferences</a> | <a href="{{site_url}}">{{site_name}}</a></p>
    </div>
  </div>
</body>
</html>
```

### Plain Text Template

```
SSL CERTIFICATE ALERT
=====================

Hi {{recipient_name|there}},

An SSL certificate issue was detected for {{domain}}.

ERROR DETAILS:
- Type: {{error_type}}
- Details: {{error_details}}
{{#if expires_at}}
- Expires: {{expires_at|date:F j, Y}} ({{days_until_expiry}} days)
{{/if}}
- Affected Links: {{affected_links_count}}

View affected links:
{{plugin_url}}

---
Manage preferences: {{unsubscribe_url}}
{{site_name}} - {{site_url}}
```

---

## Template: Daily Digest

### Trigger
Sent daily at configured time.

### Variables

| Variable | Type | Description |
|----------|------|-------------|
| `{{report_date}}` | date | Report date |
| `{{summary.total_links}}` | int | Total links tracked |
| `{{summary.healthy}}` | int | Healthy link count |
| `{{summary.broken}}` | int | Broken link count |
| `{{summary.warnings}}` | int | Warning count |
| `{{summary.redirects}}` | int | Redirect count |
| `{{summary.unchecked}}` | int | Unchecked count |
| `{{new_broken}}` | array | Newly broken links |
| `{{new_broken_count}}` | int | New broken count |
| `{{recovered}}` | array | Recovered links |
| `{{recovered_count}}` | int | Recovered count |
| `{{top_issues}}` | array | Top issues by type |
| `{{health_score}}` | int | Overall health percentage |
| `{{scans_run}}` | int | Scans run in period |
| `{{last_scan_at}}` | datetime | Last scan timestamp |

### HTML Template

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Daily Link Report - {{site_name}}</title>
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #1d2327; margin: 0; padding: 0; background-color: #f0f0f1; }
    .container { max-width: 600px; margin: 0 auto; background: #ffffff; }
    .header { background: #2271b1; color: #ffffff; padding: 24px; text-align: center; }
    .header h1 { margin: 0; font-size: 24px; font-weight: 600; }
    .header p { margin: 8px 0 0; opacity: 0.9; }
    .content { padding: 24px; }
    .health-score { text-align: center; padding: 24px; background: #f0f0f1; border-radius: 8px; margin-bottom: 24px; }
    .health-score .score { font-size: 48px; font-weight: 700; color: {{#if health_score >= 90}}#00a32a{{else if health_score >= 70}}#dba617{{else}}#d63638{{/if}}; }
    .health-score .label { font-size: 14px; color: #50575e; text-transform: uppercase; }
    .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px; }
    .stat-box { text-align: center; padding: 16px; background: #f0f0f1; border-radius: 4px; }
    .stat-box .number { font-size: 24px; font-weight: 700; }
    .stat-box .label { font-size: 12px; color: #50575e; text-transform: uppercase; }
    .stat-box.healthy .number { color: #00a32a; }
    .stat-box.broken .number { color: #d63638; }
    .stat-box.warning .number { color: #dba617; }
    .section { margin: 24px 0; }
    .section h2 { font-size: 16px; margin: 0 0 12px; padding-bottom: 8px; border-bottom: 2px solid #c3c4c7; }
    .link-item { padding: 12px 0; border-bottom: 1px solid #f0f0f1; }
    .link-item:last-child { border-bottom: none; }
    .link-url { color: #2271b1; word-break: break-all; }
    .link-meta { font-size: 12px; color: #50575e; margin-top: 4px; }
    .status-badge { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; }
    .status-broken { background: #fcf0f1; color: #d63638; }
    .status-recovered { background: #edfaef; color: #00a32a; }
    .btn { display: inline-block; background: #2271b1; color: #ffffff; text-decoration: none; padding: 12px 24px; border-radius: 4px; font-weight: 600; }
    .footer { background: #f0f0f1; padding: 16px 24px; text-align: center; font-size: 12px; color: #50575e; }
    .footer a { color: #2271b1; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1>📊 Daily Link Report</h1>
      <p>{{report_date|date:l, F j, Y}}</p>
    </div>
    <div class="content">
      <p>Hi {{recipient_name|there}},</p>
      <p>Here's your daily link health summary for <strong>{{site_name}}</strong>:</p>
      
      <div class="health-score">
        <div class="score">{{health_score}}%</div>
        <div class="label">Link Health Score</div>
      </div>
      
      <div class="stats-grid">
        <div class="stat-box healthy">
          <div class="number">{{summary.healthy}}</div>
          <div class="label">Healthy</div>
        </div>
        <div class="stat-box broken">
          <div class="number">{{summary.broken}}</div>
          <div class="label">Broken</div>
        </div>
        <div class="stat-box warning">
          <div class="number">{{summary.warnings}}</div>
          <div class="label">Warnings</div>
        </div>
      </div>
      
      {{#if new_broken_count > 0}}
      <div class="section">
        <h2>🔴 New Broken Links ({{new_broken_count}})</h2>
        {{#each new_broken}}
        <div class="link-item">
          <span class="status-badge status-broken">{{http_code}}</span>
          <div class="link-url">{{url}}</div>
          <div class="link-meta">Found in: {{source_title}}</div>
        </div>
        {{/each}}
      </div>
      {{/if}}
      
      {{#if recovered_count > 0}}
      <div class="section">
        <h2>🟢 Recovered Links ({{recovered_count}})</h2>
        {{#each recovered}}
        <div class="link-item">
          <span class="status-badge status-recovered">Fixed</span>
          <div class="link-url">{{url}}</div>
          <div class="link-meta">Was broken since: {{first_detected|date:M j}}</div>
        </div>
        {{/each}}
      </div>
      {{/if}}
      
      <p style="text-align: center; margin-top: 24px;">
        <a href="{{plugin_url}}" class="btn">View Full Report</a>
      </p>
      
      <p style="font-size: 13px; color: #50575e; margin-top: 24px; text-align: center;">
        {{scans_run}} scan{{#if scans_run > 1}}s{{/if}} run | Last scan: {{last_scan_at|date:g:i A}}
      </p>
    </div>
    <div class="footer">
      <p><a href="{{unsubscribe_url}}">Manage notification preferences</a> | <a href="{{site_url}}">{{site_name}}</a></p>
    </div>
  </div>
</body>
</html>
```

### Plain Text Template

```
DAILY LINK REPORT
=================
{{report_date|date:l, F j, Y}}

Hi {{recipient_name|there}},

Here's your daily link health summary for {{site_name}}:

HEALTH SCORE: {{health_score}}%

SUMMARY:
- Healthy: {{summary.healthy}}
- Broken: {{summary.broken}}
- Warnings: {{summary.warnings}}
- Redirects: {{summary.redirects}}
- Total: {{summary.total_links}}

{{#if new_broken_count > 0}}
NEW BROKEN LINKS ({{new_broken_count}}):
{{#each new_broken}}
- {{url}}
  Error: {{http_code}} - {{error_message}}
  Found in: {{source_title}}
{{/each}}

{{/if}}
{{#if recovered_count > 0}}
RECOVERED LINKS ({{recovered_count}}):
{{#each recovered}}
- {{url}} (was broken since {{first_detected|date:M j}})
{{/each}}

{{/if}}

View full report:
{{plugin_url}}

---
{{scans_run}} scan{{#if scans_run > 1}}s{{/if}} run | Last scan: {{last_scan_at|date:g:i A}}

Manage preferences: {{unsubscribe_url}}
{{site_name}} - {{site_url}}
```

---

## Template: Weekly Summary

### Trigger
Sent weekly at configured day/time.

### Variables

Includes all Daily Digest variables plus:

| Variable | Type | Description |
|----------|------|-------------|
| `{{week_start}}` | date | Week start date |
| `{{week_end}}` | date | Week end date |
| `{{trend.broken_change}}` | int | Change in broken count |
| `{{trend.health_change}}` | int | Health score change |
| `{{trend.direction}}` | string | up/down/stable |
| `{{top_broken_domains}}` | array | Most problematic domains |
| `{{auto_fixes_applied}}` | int | Auto-fixes count |
| `{{manual_fixes}}` | int | Manual fixes count |

### HTML Template

*(Similar structure to Daily Digest with weekly metrics and trend indicators)*

### Plain Text Template

```
WEEKLY LINK SUMMARY
===================
{{week_start|date:M j}} - {{week_end|date:M j, Y}}

Hi {{recipient_name|there}},

Weekly summary for {{site_name}}:

HEALTH SCORE: {{health_score}}% ({{trend.direction}} {{trend.health_change|abs}}%)

THIS WEEK:
- New broken: {{new_broken_count}}
- Recovered: {{recovered_count}}
- Auto-fixed: {{auto_fixes_applied}}
- Manual fixes: {{manual_fixes}}

CURRENT STATUS:
- Total Links: {{summary.total_links}}
- Healthy: {{summary.healthy}}
- Broken: {{summary.broken}}
- Warnings: {{summary.warnings}}

{{#if top_broken_domains}}
TOP PROBLEMATIC DOMAINS:
{{#each top_broken_domains}}
- {{domain}}: {{count}} broken links
{{/each}}

{{/if}}

View full report:
{{plugin_url}}

---
Manage preferences: {{unsubscribe_url}}
{{site_name}} - {{site_url}}
```

---

## Template: Health Check Failed

### Trigger
Sent when scheduled health check fails.

### Variables

| Variable | Type | Description |
|----------|------|-------------|
| `{{check_type}}` | string | Type of health check |
| `{{failure_reason}}` | string | Why it failed |
| `{{last_success_at}}` | datetime | Last successful check |
| `{{consecutive_failures}}` | int | Failure streak count |
| `{{affected_urls}}` | array | URLs that failed |

### HTML/Plain Text

*(Similar structure to Broken Links Alert, focused on health check specifics)*

---

## Template: Test Notification

### Trigger
Sent via "Send Test" button in settings.

### Variables

| Variable | Type | Description |
|----------|------|-------------|
| `{{test_type}}` | string | email/webhook |
| `{{sent_by}}` | string | Admin who sent test |
| `{{sent_at}}` | datetime | When test was sent |

### HTML Template

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Test Notification - {{site_name}}</title>
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #1d2327; margin: 0; padding: 0; background-color: #f0f0f1; }
    .container { max-width: 600px; margin: 0 auto; background: #ffffff; }
    .header { background: #00a32a; color: #ffffff; padding: 24px; text-align: center; }
    .header h1 { margin: 0; font-size: 24px; font-weight: 600; }
    .content { padding: 24px; text-align: center; }
    .success-icon { font-size: 48px; margin-bottom: 16px; }
    .footer { background: #f0f0f1; padding: 16px 24px; text-align: center; font-size: 12px; color: #50575e; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1>✅ Test Notification</h1>
    </div>
    <div class="content">
      <div class="success-icon">🎉</div>
      <h2>It works!</h2>
      <p>This test notification was sent from Link Manager on <strong>{{site_name}}</strong>.</p>
      <p style="font-size: 13px; color: #50575e;">
        Sent by: {{sent_by}}<br>
        Sent at: {{sent_at|date:F j, Y \a\t g:i A}}
      </p>
    </div>
    <div class="footer">
      <p><a href="{{plugin_url}}">Link Manager</a> | <a href="{{site_url}}">{{site_name}}</a></p>
    </div>
  </div>
</body>
</html>
```

### Plain Text Template

```
TEST NOTIFICATION
=================

It works!

This test notification was sent from Link Manager on {{site_name}}.

Sent by: {{sent_by}}
Sent at: {{sent_at|date:F j, Y \a\t g:i A}}

---
Link Manager: {{plugin_url}}
{{site_name}} - {{site_url}}
```

---

## PHP Implementation

### TemplateEngine Interface

```php
interface TemplateEngineInterface
{
    public function render(string $templateName, array $variables): RenderedEmail;
    public function registerTemplate(string $name, EmailTemplate $template): void;
    public function getAvailableTemplates(): array;
    public function validateVariables(string $templateName, array $variables): ValidationResult;
}
```

### RenderedEmail Class

```php
class RenderedEmail
{
    public function __construct(
        public readonly string $subject,
        public readonly string $html,
        public readonly string $plainText,
        public readonly array $headers = []
    ) {}
}
```

### EmailTemplate Class

```php
class EmailTemplate
{
    public function __construct(
        public readonly string $name,
        public readonly string $subject,
        public readonly string $htmlTemplate,
        public readonly string $plainTextTemplate,
        public readonly array $requiredVariables = [],
        public readonly array $optionalVariables = []
    ) {}
}
```

### Variable Resolver

```php
class VariableResolver
{
    public function resolve(string $template, array $variables): string
    {
        // Handle simple replacements: {{var}}
        // Handle defaults: {{var|default}}
        // Handle conditionals: {{#if}}...{{/if}}
        // Handle loops: {{#each}}...{{/each}}
        // Handle formatters: {{var|date:format}}
    }
    
    public function formatValue(mixed $value, string $formatter, string $format = ''): string
    {
        return match($formatter) {
            'date' => (new DateTimeImmutable($value))->format($format),
            'number' => number_format($value),
            'abs' => abs($value),
            default => (string) $value
        };
    }
}
```

---

## Template Storage

Templates stored in: `wp-content/plugins/link-manager/templates/email/`

```
templates/email/
├── broken-links-alert.html
├── broken-links-alert.txt
├── ssl-certificate-error.html
├── ssl-certificate-error.txt
├── daily-digest.html
├── daily-digest.txt
├── weekly-summary.html
├── weekly-summary.txt
├── health-check-failed.html
├── health-check-failed.txt
├── test-notification.html
└── test-notification.txt
```

### Template Override

Users can override templates by copying to:
`wp-content/themes/{theme}/link-manager/email/`

---

## Email Headers

```php
$headers = [
    'Content-Type: text/html; charset=UTF-8',
    'From: {{site_name}} <noreply@{{site_domain}}>',
    'Reply-To: {{admin_email}}',
    'X-Mailer: Link-Manager/{{plugin_version}}',
    'List-Unsubscribe: <{{unsubscribe_url}}>'
];
```

---

## Accessibility Requirements

| Requirement | Implementation |
|-------------|----------------|
| Color contrast | Min 4.5:1 ratio for text |
| Alt text | All images have alt attributes |
| Semantic HTML | Proper heading hierarchy |
| Link text | Descriptive, not "click here" |
| Plain text | Always provide text alternative |
| Font size | Min 14px body text |

---

## Testing

### Template Preview API

```
GET /lm/v1/notifications/templates/{name}/preview
POST /lm/v1/notifications/templates/{name}/preview
     Body: { variables: {...} }
```

### Validation Checks

- All required variables present
- Variable types match expected
- HTML validates
- Plain text renders correctly
- Links are properly encoded
- No XSS vulnerabilities

---

## Related Specs

- `24-notification-service.md` - Notification delivery service
- `25-notification-settings-page.md` - UI for managing notifications
- `66-shared-constants.md` - Notification type enums

---

## Acceptance Criteria

- [ ] All templates render HTML and plain text versions
- [ ] Variables are properly escaped to prevent XSS
- [ ] Conditional blocks work correctly
- [ ] Loop blocks iterate properly
- [ ] Date/number formatters work
- [ ] Templates are responsive on mobile
- [ ] Plain text is readable and well-formatted
- [ ] Unsubscribe links included in all templates
- [ ] Templates can be overridden by themes
- [ ] Preview API returns rendered templates
