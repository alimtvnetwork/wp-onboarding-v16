# 23. Secret Key Admin UI

## Overview
React interface for creating, managing, and monitoring secret keys that provide anonymous exam access.

---

## 23.1 Key List View

### Table Columns
| Column | Sortable | Description |
|--------|----------|-------------|
| Key (masked) | No | Shows first 4 + last 4 characters |
| Label | Yes | Admin-defined identifier |
| Exam | Yes | Associated exam title |
| Status | Yes | Active/Expired/Revoked badge |
| Uses | Yes | Current / Max (or ∞) |
| Created | Yes | Creation timestamp |
| Expires | Yes | Expiration date or "Never" |
| Actions | No | Quick action buttons |

### Acceptance Criteria:
- [ ] Keys masked by default (show on hover/click)
- [ ] Copy-to-clipboard button for full key
- [ ] Filter by exam, status, date range
- [ ] Bulk revoke functionality
- [ ] Export keys as CSV

---

## 23.2 Create Key Form

### Fields
- **Exam** (required): Dropdown selector
- **Label** (required): Descriptive name (e.g., "LinkedIn Campaign Q1")
- **Max Uses** (optional): Integer limit or unlimited
- **Expires At** (optional): Date/time picker
- **Notes** (optional): Internal notes textarea

### Acceptance Criteria:
- [ ] Key generated on server, never client-side
- [ ] Generated key shown once with copy button
- [ ] Warning that key cannot be retrieved later
- [ ] Immediate redirect to key detail view
- [ ] Success toast with quick-copy action

---

## 23.3 Key Detail View

### Sections
1. **Header**: Label, masked key, status badge, copy button
2. **Configuration**: Exam link, max uses, expiration
3. **Usage Statistics**: Total uses, unique visitors, last used
4. **Access URL**: Full shareable URL with copy button
5. **Activity Log**: Recent access attempts with timestamps

### Acceptance Criteria:
- [ ] Full key revealable with confirmation
- [ ] Edit label and notes inline
- [ ] Extend expiration date option
- [ ] Increase max uses option
- [ ] Cannot decrease limits below current usage

---

## 23.4 Key Actions

### Available Actions
- **Copy URL**: Full access URL to clipboard
- **Reveal Key**: Show full key with re-mask timeout
- **Extend**: Add time to expiration
- **Revoke**: Immediately disable (with confirmation)
- **Delete**: Permanent removal (with double confirmation)

### Acceptance Criteria:
- [ ] Revoked keys show strikethrough styling
- [ ] Revocation is immediate, no grace period
- [ ] Delete requires typing "DELETE" to confirm
- [ ] Audit log entry for all actions

---

## 23.5 Batch Key Generation

### Use Case
Generate multiple keys at once for distribution campaigns.

### Form Fields
- **Exam** (required): Single exam selection
- **Quantity** (required): 1-100 keys
- **Label Prefix** (required): e.g., "Campaign-" → "Campaign-001"
- **Max Uses Each** (optional): Per-key limit
- **Expires At** (optional): Shared expiration

### Acceptance Criteria:
- [ ] Preview shows sample labels before generation
- [ ] Progress indicator during generation
- [ ] Download all keys as CSV on completion
- [ ] Each key is independent (revoking one doesn't affect others)

---

## 23.6 Key Analytics Preview

### Quick Stats Panel
- Total keys for exam
- Active vs expired/revoked counts
- Total accesses this period
- Unique visitors estimate

### Acceptance Criteria:
- [ ] Stats update in real-time
- [ ] Click-through to full analytics (Spec 24)
- [ ] Date range selector for period stats
- [ ] Visual chart for access trends
