# 09. RBAC Admin UI

## Overview
React-based admin interface for managing user roles and permissions.

---

## 09.1 User List View

### Display Columns
| Column | Description |
|--------|-------------|
| Avatar | User profile image or initials |
| Name | Full name (linked to edit) |
| Email | Email address |
| Role | Current role badge |
| Status | Active/Inactive indicator |
| Last Login | Relative timestamp |
| Actions | Edit, Delete buttons |

### Acceptance Criteria:
- [ ] Users listed with pagination (20 per page)
- [ ] Search by name or email
- [ ] Filter by role dropdown
- [ ] Sort by any column
- [ ] Bulk role assignment available

---

## 09.2 Role Assignment

### Assignment Methods
- Single user: Dropdown in user edit modal
- Bulk: Select multiple users, choose role from toolbar

### Available Roles
- **Admin**: Full system access
- **Editor**: Modify exams and participants
- **Examinee**: Participate in assigned exams

### Acceptance Criteria:
- [ ] Role dropdown shows all available roles
- [ ] Role change takes effect immediately
- [ ] Cannot demote last admin
- [ ] Role change logged in audit trail
- [ ] Confirmation dialog for role changes

---

## 09.3 Add User Form

### Form Fields
- **Email** (required): Valid email format
- **Name** (required): Full name
- **Role** (required): Select from available roles
- **Send Invite** (checkbox): Email login credentials

### Validation
- Email must be unique in system
- Email format validation
- Name minimum 2 characters

### Acceptance Criteria:
- [ ] Form validates before submission
- [ ] Duplicate email shows error
- [ ] Success creates WordPress user if needed
- [ ] Invite email sent if checkbox selected
- [ ] New user appears in list immediately

---

## 09.4 Edit User Modal

### Editable Fields
- Name
- Email (with re-verification if changed)
- Role
- Status (Active/Inactive)

### Read-Only Display
- User ID
- Created date
- Last login date

### Acceptance Criteria:
- [ ] Modal opens with current values
- [ ] Save validates all fields
- [ ] Cancel discards changes
- [ ] Email change requires confirmation
- [ ] Status toggle disables user access

---

## 09.5 Permission Matrix Display

### Matrix View
- Rows: Each permission (view_exams, edit_exams, etc.)
- Columns: Each role (Admin, Editor, Examinee)
- Cells: Checkmark or X indicator

### Acceptance Criteria:
- [ ] Matrix shows all permissions
- [ ] Permissions grouped by category
- [ ] Read-only display (not editable)
- [ ] Tooltip explains each permission
- [ ] Export as PDF available

---

## 09.6 Role Statistics

### Dashboard Widgets
- Total users by role (pie chart)
- Recent role changes (list)
- Users without login in 30 days

### Acceptance Criteria:
- [ ] Stats update on page load
- [ ] Click role segment filters user list
- [ ] Inactive users highlighted
- [ ] Export user list available
