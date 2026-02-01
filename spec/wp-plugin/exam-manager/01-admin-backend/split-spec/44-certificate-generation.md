# 42. Certificate Generation [OPTIONAL]

## Overview
Automated certificate generation for participants who complete exams.

---

## 42.1 Certificate Configuration

### Per-Exam Settings
- Enable/disable certificates
- Certificate template selection
- Minimum completion percentage required
- Custom fields to include

### Global Settings
- Default template
- Signature image upload
- Organization logo
- Issuer name and title

### Acceptance Criteria:
- [ ] Certificate toggle per exam
- [ ] Template preview available
- [ ] Logo/signature upload with cropping
- [ ] Settings inherited from global if not set

---

## 42.2 Certificate Templates

### Template Elements
- Background design/color
- Header with logo
- Participant name (dynamic)
- Exam title (dynamic)
- Completion date (dynamic)
- Unique certificate ID
- Digital signature/stamp
- QR code for verification (optional)

### Template Variations
- Classic (formal, serif fonts)
- Modern (clean, sans-serif)
- Minimal (simple, text-focused)
- Custom (admin-uploaded design)

### Acceptance Criteria:
- [ ] Multiple built-in templates
- [ ] Template customization options
- [ ] Preview with sample data
- [ ] Custom template upload support

---

## 42.3 Generation Trigger

### Automatic Generation
- Triggered when participant reaches 100% progress
- Triggered when admin marks as completed
- Queued for background processing

### Manual Generation
- Admin can generate for any completed participant
- Regenerate with updated information
- Batch generation for multiple participants

### Acceptance Criteria:
- [ ] Auto-generation happens within 5 minutes
- [ ] Email notification with download link
- [ ] Manual trigger available in admin
- [ ] Regeneration creates new version, keeps old

---

## 42.4 Certificate Storage

### Storage Details
- PDF file generated and stored
- Unique filename with certificate ID
- Stored in uploads directory
- Database record links participant to certificate

### Retention
- Certificates kept indefinitely by default
- Configurable retention period
- Deleted certificates logged

### Acceptance Criteria:
- [ ] PDF stored securely in uploads folder
- [ ] .htaccess prevents direct access
- [ ] Download requires valid token
- [ ] Storage usage tracked

---

## 42.5 Verification System

### Verification URL
`/verify/{certificate-id}`

### Verification Page
- Shows certificate validity status
- Displays participant name (partial)
- Shows exam title
- Shows completion date
- Indicates if certificate is revoked

### QR Code
- Embedded in certificate
- Links directly to verification page
- Scannable from printed certificate

### Acceptance Criteria:
- [ ] Verification works without login
- [ ] Invalid IDs show clear error
- [ ] Revoked certificates clearly marked
- [ ] Rate limiting on verification endpoint

---

## 42.6 Certificate Download

### Download Options
- PDF download (print quality)
- PNG download (web sharing)
- LinkedIn share button
- Email certificate to self

### Access Control
- Participant can download own certificate
- Admin can download any certificate
- Secure download token expires after 24 hours

### Acceptance Criteria:
- [ ] Multiple format options
- [ ] High resolution for printing
- [ ] Social sharing optimized image
- [ ] Download tracked in analytics

---

## 42.7 Revocation

### Revocation Reasons
- Exam completed fraudulently
- Participant request
- Administrative correction
- Exam invalidated

### Revocation Process
1. Admin initiates revocation
2. Reason required
3. Confirmation dialog
4. Certificate marked as revoked
5. Optional: notify participant

### Acceptance Criteria:
- [ ] Revoked certificates not downloadable
- [ ] Verification page shows revoked status
- [ ] Revocation logged with reason
- [ ] Participant notification optional
