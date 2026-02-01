# Exam Questions Manager - WordPress Plugin Specification

> **Version:** 2.0.0 FINAL MERGED  
> **Status:** Backend & Admin Panel Requirements (COMPLETE)  
> **Target Platform:** WordPress 6.0+ with PHP 8.0+  
> **Database:** SQLite via PHP PDO  
> **Priority Iteration:** ITERATION-2-FULLY-REVISED  
> **Date Compiled:** January 24, 2026

---

## 📋 Table of Contents

1. [Executive Summary](#executive-summary)
2. [Original Requirements (Verbatim)](#original-requirements-verbatim)
3. [Technology Stack](#technology-stack)
4. [Core Philosophy](#core-philosophy)
5. [Role-Based Access Control (RBAC)](#role-based-access-control-rbac)
6. [Wiki Markdown System](#wiki-markdown-system)
7. [Secret Key Access System](#secret-key-access-system)
8. [Hierarchical Exam Structure](#hierarchical-exam-structure)
9. [Prerequisite System](#prerequisite-system)
10. [Checklist & Rubric System](#checklist--rubric-system)
11. [Two-Tier Deadline System](#two-tier-deadline-system)
12. [Email Template System](#email-template-system)
13. [Database Schema (Complete)](#database-schema-complete)
14. [Admin UI Screens](#admin-ui-screens)
15. [Backend API Endpoints](#backend-api-endpoints)
16. [Participant Management](#participant-management)
17. [Extension Request System](#extension-request-system)
18. [WP-Cron Jobs](#wp-cron-jobs)
19. [Logging System (Dual-File)](#logging-system-dual-file)
20. [Code Standards & Guidelines](#code-standards--guidelines)
21. [Enums & Constants](#enums--constants)
22. [File Structure](#file-structure)
23. [Workflows & Sequences](#workflows--sequences)
24. [Diagrams](#diagrams)
25. [Acceptance Criteria](#acceptance-criteria)
26. [Implementation Phases](#implementation-phases)
27. [Security Considerations](#security-considerations)
28. [Assumptions & Open Questions](#assumptions--open-questions)
29. [Sample Email Templates](#sample-email-templates)

---

## Executive Summary

The **Exam Questions Manager** is a comprehensive WordPress plugin designed to manage hierarchical, markdown-based examinations with participant tracking, two-tier deadline management, wiki documentation, and advanced secret key analytics. The plugin features a robust role-based access control system, customizable email notifications, and comprehensive progress tracking.

### Key Capabilities

| Feature | Description |
|---------|-------------|
| **Hierarchical Exams** | Unlimited parent-child nesting with independent tracking per level |
| **Wiki System** | Markdown-based documentation with granular visibility controls (public/role/private) |
| **RBAC System** | Three-tier role system (Admin, Exam Editor, Examinee) integrated with WordPress users |
| **Secret Key Access** | Multiple trackable secret keys per exam with IP/cookie analytics and geo-tracking |
| **Two-Tier Deadlines** | Soft (encouragement) and hard (blocking) deadlines with extension support |
| **Email Automation** | 11 templated emails for all lifecycle events with variable substitution |
| **Prerequisite Management** | Videos, links, and checklist items with sequencing and requirements |
| **Progress Tracking** | Section-by-section completion with timestamps and status tracking |
| **Dual Logging** | Separate general log and error log with stack traces |
| **ORM-Based Database** | No raw SQL, camelCase field names, prepared statements |

---

## Original Requirements (Verbatim)

> "Okay. So, uh, for a question exam, a user can s- can actually f- from the admin panel, admin can allow to, uh, let's say, she has a prerequisite, some videos. And, um, there could be multiple videos that, uh, that admin can set, uh, one after another to onboard with that, uh, assign- I mean, assignment. So, the first thing in the front end when user logs in, they could sh- see some checklist. Okay? And that would be, uh, the videos, prerequisite videos, prerequisite links to read, and then also read the question itself. Uh, that would be by default there. And pre and post, there could be some pre and post-checklist. And also, inside the assignment, there could be some, uh, marked Rubik cube as well. And, uh, uh, exam question can actually have a sub markdown question. That means, one exam can be, uh, m- can be, uh, let's say, a- a- a- a exam can have a parent. Just think about that. U- using the, uh, SQLite, uh, that's a relationship. So, in the backend- backend, we can have the relationship, uh, flexibility. So, that means when we are inside a question, uh, or markdown question, we should be able to add- add new sub-question or add new- add new exam criterias. Okay? So, things like that- Okay. ... we should be able to do. Um, and- and each one of the, uh, sub-question is itself a exam itself. So, it can have, again, the checklist, uh, the videos and things like that. So, if a exam contains multiple sub-exams, then this will show as a checklist. Okay? So, in the front-end part, it should nicely display like this. So, based on this, please modify your, uh, specs with detail, including the previous one. Do not remove it. And for the soft deadline and hard deadline, please be very clear with it, uh, that that should be, uh, very much detailed with, uh, examples so that it's easy to understand. And also, make one more guideline in the coding that, uh, there should be no strings. So, try to use, in the coding time, try to use constant or enum. So, if a term has multiple options, try to use enum as the best practice. And- and for the logs, uh, there is a log guideline. So, uh, for logs, every time have two files. One is the general logs and another is, uh, error.log file. Uh, sorry, error.txt file. So, error.txt file will contain all the error logs with the stack trace. And when the naming the function, if it has, uh, let's say Boolean as a return, so it should have is or has as a prefix. Same will go for the, uh, variable names as well. So, if a variable is a Boolean, so it should have is or has as a prefix. Try to use the best naming convention in the coding, uh, for that language. But in the database try to use the camelCase. And, um, every data that we modify should be saved into the SQLite databases and, um, try to have the ORM use. Create a checklist in your spec, uh, very carefully so that they can complete it. For the, uh, templates, email templates, I believe creating the, uh, HTML- uh, HTML body part only is enough rather than using the graph, I believe. So... Oh, okay. Graph can be used. I think that's not a problem. Um, so, th- these are, uh, on top of my head. Uh, try to update the whole requirement. Uh, rewrite the whole thing. And keep the existing stuff, do not remove. Just write this, don't do that. So, write the whole thing as a rewritten stuff so that I can re-share. Um, if you have any questions and confusion, please, uh, let me know."

---

## Technology Stack

| Component | Technology | Notes |
|-----------|------------|-------|
| **Platform** | WordPress 6.0+ | Minimum version requirement |
| **Backend Language** | PHP 8.0+ | Required for enum support |
| **Database** | SQLite via PHP PDO | Stored in uploads folder |
| **ORM** | Custom lightweight ORM | No raw SQL queries allowed |
| **Frontend (Admin)** | React.js | Via WordPress Script API |
| **Styling** | Tailwind CSS | Utility-first CSS |
| **Markdown Parsing** | Parsedown PHP library | CommonMark + GFM support |
| **Password Hashing** | bcrypt | Via `password_hash()` |
| **Scheduling** | WP-Cron | Daily execution at configured time |
| **Email** | WordPress `wp_mail()` | HTML templates with variables |
| **Logging** | Dual-file system | plugin.log + error.txt |

---

## Core Philosophy

This plugin enables educators/trainers to:

1. **Upload markdown exam questions** with prerequisite videos/links/checklists
2. **Organize exams hierarchically** (parent-child relationships, unlimited nesting)
3. **Track participant progress** with two-tier deadlines (soft = encouragement, hard = absolute cutoff)
4. **Manage extension requests** with admin approval/rejection workflow
5. **Send automated, customizable email notifications** via WP-Cron
6. **Maintain wiki documentation** with role-based visibility controls
7. **Share exams via secret keys** with comprehensive analytics tracking
8. **Use industry-standard code practices**: enums/constants (no magic strings), ORM for database, proper logging (dual-file system), camelCase database fields, boolean naming (is/has prefix)

### Key Requirements (Non-Negotiable)

- **Hierarchical Exams**: Exams can have parent-child relationships; each child is a full exam with own metadata, participants, progress
- **Two-Tier Deadlines**: Soft deadline (encouragement) vs. hard deadline (absolute cutoff); extension extends from hard deadline
- **Prerequisite System**: Videos, links, and checklist items (sequenced, reorderable)
- **Checklists & Rubrics**: Pre-checklist (prerequisites), in-exam checklist (checkpoints/rubric), post-exam checklist
- **Email Template Seeding**: Default templates in `/wp-content/uploads/exam-questions-manager/seeding/email-templates/` (HTML files); seeded to SQLite on activation; admin can edit, import, export
- **Dual Logging**: `plugin.log` (all events) + `error.txt` (errors with stack traces)
- **Code Standards**: Enums for all constants, `is`/`has` prefix for booleans, camelCase database fields, ORM usage, no raw SQL
- **File Naming**: Hyphens in markdown filenames (not underscores), e.g., `my-exam.md`
- **Wiki System**: Separate markdown documentation with visibility controls
- **Secret Keys**: Multiple keys per exam with analytics tracking

---

## Role-Based Access Control (RBAC)

### Role Definitions

The plugin implements a three-tier role system that integrates with WordPress users:

#### 1. Admin Role
```
Capabilities:
- Full access to all plugin features
- Manage all exams and participants
- Create, edit, delete wiki pages (any visibility)
- Assign and revoke user roles
- Configure plugin settings
- Manage email templates
- View all logs and analytics
- Create and manage secret keys
- Access database maintenance tools
- Reset/backup database
```

#### 2. Exam Editor Role
```
Capabilities:
- Create, edit, delete exams (own and assigned)
- Manage exam prerequisites and checklists
- View and manage participants
- Approve/reject extension requests
- Create and manage wiki pages (role-restricted)
- Create and manage secret keys
- View exam analytics
- CANNOT: Manage roles, plugin settings, or templates
```

#### 3. Examinee Role
```
Capabilities:
- Sign up for exams
- View exam content and complete sections
- Submit extension requests
- View own progress
- Access wiki pages (based on visibility settings)
- CANNOT: Create/edit exams, manage participants, admin actions
```

### Role Assignment Flow

```
┌─────────────────────────────────────────────────────────────┐
│                    WordPress User                            │
│                         │                                    │
│                         ▼                                    │
│              ┌──────────────────┐                            │
│              │   Plugin Login   │                            │
│              └────────┬─────────┘                            │
│                       │                                      │
│         ┌─────────────┼─────────────┐                        │
│         ▼             ▼             ▼                        │
│    ┌─────────┐  ┌───────────┐  ┌──────────┐                 │
│    │  Admin  │  │   Editor  │  │ Examinee │                 │
│    └─────────┘  └───────────┘  └──────────┘                 │
│                                                              │
│    Database: userRole table                                  │
│    - userId (FK → wp_users.ID)                              │
│    - role (ENUM: ADMIN, EXAM_EDITOR, EXAMINEE)              │
│    - assignedAt (DATETIME)                                   │
│    - assignedBy (FK → wp_users.ID)                          │
└─────────────────────────────────────────────────────────────┘
```

### Feature Permission Matrix

| Feature | Admin | Editor | Examinee | Public |
|---------|-------|--------|----------|--------|
| Manage Exams | ✅ | ✅ | ❌ | ❌ |
| Manage Participants | ✅ | ✅ | ❌ | ❌ |
| Manage Roles | ✅ | ❌ | ❌ | ❌ |
| Manage Wiki | ✅ | ✅ (limited) | ❌ | ❌ |
| View Public Wiki | ✅ | ✅ | ✅ | ✅ |
| Take Exams | ✅ | ✅ | ✅ | ❌ |
| Manage Settings | ✅ | ❌ | ❌ | ❌ |
| Manage Secret Keys | ✅ | ✅ | ❌ | ❌ |
| View Analytics | ✅ | ✅ | ❌ | ❌ |
| View Logs | ✅ | ❌ | ❌ | ❌ |

### Seeding Default Roles

On plugin activation, seed the first WordPress administrator as plugin Admin:

```php
function seedDefaultRoles() {
    $wpAdmins = get_users(['role' => 'administrator', 'number' => 1]);
    
    if (!empty($wpAdmins)) {
        $admin = $wpAdmins[0];
        
        // Check if already exists
        $existing = UserRole::findByUserId($admin->ID);
        if (!$existing) {
            UserRole::create([
                'userId' => $admin->ID,
                'role' => UserRoleType::ADMIN,
                'assignedAt' => current_time('mysql'),
                'assignedBy' => null // System assigned
            ]);
            
            Logger::info("Default admin role seeded for userId={$admin->ID}");
        }
    }
}
```

---

## Wiki Markdown System

### Overview

The Wiki system provides a separate content management area for documentation, guides, tutorials, and reference materials. Wiki pages are stored as Markdown and rendered to HTML.

### Wiki Visibility Levels

| Level | Code | Description | Who Can View |
|-------|------|-------------|--------------|
| **Public** | `PUBLIC` | Visible to everyone, no login required | Anyone (including anonymous) |
| **Authenticated** | `AUTHENTICATED` | Visible to logged-in users only | Any logged-in WordPress user |
| **Role-Based** | `ROLE` | Visible to specific plugin roles | Specified role(s) only |
| **Private** | `PRIVATE` | Only admins and creator can view | Admin role + Creator |

### Wiki Categories

Wiki pages can be organized into categories:

```
├── Getting Started
│   ├── Installation Guide (PUBLIC)
│   ├── Quick Start Tutorial (PUBLIC)
│   └── Admin Setup (ROLE:ADMIN)
├── Exam Guides
│   ├── How to Take an Exam (AUTHENTICATED)
│   ├── Extension Request Guide (ROLE:EXAMINEE)
│   └── Grading Rubric Explanation (ROLE:EXAM_EDITOR)
├── Technical Documentation
│   ├── API Reference (ROLE:ADMIN)
│   └── Database Schema (ROLE:ADMIN)
└── FAQs
    └── Common Questions (PUBLIC)
```

### Wiki Page Structure (Frontmatter)

```markdown
---
title: "How to Take an Exam"
slug: "how-to-take-exam"
category: "Exam Guides"
visibility: "AUTHENTICATED"
visibilityRoles: []
author: 1
createdAt: "2026-01-24T12:00:00Z"
updatedAt: "2026-01-24T12:00:00Z"
---

# How to Take an Exam

Content goes here in standard Markdown...

## Section 1
- Bullet points
- Links to [[Other Wiki Page]]

## Section 2
Code blocks with syntax highlighting:
```javascript
const example = true;
```
```

### Wiki Features

1. **Full Markdown Support**: CommonMark + GitHub Flavored Markdown (GFM)
2. **Syntax Highlighting**: Code blocks with language-specific highlighting
3. **Auto Table of Contents**: Generated from H2/H3 headings
4. **Full-Text Search**: Search across all accessible wiki pages
5. **Revision History**: Track changes with timestamps and author
6. **Cross-Linking**: Link between wiki pages using `[[Page Title]]` syntax
7. **Embedding**: Embed exam checklists or prerequisites in wiki pages
8. **Category Management**: Create, rename, delete categories
9. **Export/Import**: Export wiki pages as Markdown files

### Wiki Backend Logic

**Visibility Check Function:**
```php
function canViewWikiPage($userId, $wikiPage): bool {
    // Public pages: anyone can view
    if ($wikiPage->visibility === WikiVisibility::PUBLIC) {
        return true;
    }
    
    // Not logged in: only public pages
    if (!$userId) {
        return false;
    }
    
    // Authenticated: any logged-in user
    if ($wikiPage->visibility === WikiVisibility::AUTHENTICATED) {
        return true;
    }
    
    // Private: only admin or creator
    if ($wikiPage->visibility === WikiVisibility::PRIVATE) {
        $userRole = getUserRole($userId);
        return $userRole === UserRoleType::ADMIN || $wikiPage->authorId === $userId;
    }
    
    // Role-based: check if user has required role
    if ($wikiPage->visibility === WikiVisibility::ROLE) {
        $userRole = getUserRole($userId);
        return in_array($userRole, $wikiPage->visibilityRoles);
    }
    
    return false;
}
```

---

## Secret Key Access System

### Overview

Secret keys provide a way to share exam access without requiring signup. Each key is trackable and can be revoked at any time. This enables sharing exams with partners, guests, or for demo purposes.

### URL Pattern

```
Base Pattern: /{exam-slug}/{secret-key}

Examples:
- /javascript-fundamentals/abc123def456
- /react-advanced/key_2025_01_24_xyz
- /python-basics/guest_access_key_1
- /exam-demo/partner_preview_2026
```

### Secret Key Features

#### 1. Multiple Keys Per Exam
Each exam can have unlimited secret keys, each with:

| Property | Type | Description |
|----------|------|-------------|
| `keyValue` | VARCHAR(64) | Unique key string (min 8 characters) |
| `label` | VARCHAR(100) | Admin-friendly label (e.g., "Partner Share") |
| `description` | TEXT | Notes about key purpose |
| `expiresAt` | DATETIME | Expiration date (NULL = never) |
| `usageLimit` | INTEGER | Max usage count (NULL = unlimited) |
| `enabled` | BOOLEAN | Enable/disable toggle |
| `viewCount` | INTEGER | Total views counter |
| `uniqueVisitorCount` | INTEGER | Unique IP counter |

#### 2. Analytics Tracking

For each key access, track:

| Data Point | Description | Storage |
|------------|-------------|---------|
| `ipAddress` | Visitor's IP address (hashed for privacy) | secretKeyAccess.ipAddress |
| `ipAddressHash` | SHA-256 hash for unique visitor counting | secretKeyAccess.ipAddressHash |
| `userAgent` | Browser/device information | secretKeyAccess.userAgent |
| `referrer` | HTTP referrer URL | secretKeyAccess.referrer |
| `cookieId` | Tracking cookie ID (for return visitors) | secretKeyAccess.cookieId |
| `accessedAt` | Timestamp of access | secretKeyAccess.accessedAt |
| `sessionDuration` | Time spent on page (if tracked) | secretKeyAccess.sessionDuration |
| `country` | GeoIP country code (optional) | secretKeyAccess.countryCode |
| `city` | GeoIP city (optional) | secretKeyAccess.city |

#### 3. Key Access Log Entry Example
```json
{
  "id": 1234,
  "secretKeyId": 5,
  "ipAddress": "192.168.x.x",
  "ipAddressHash": "sha256:abc123...",
  "userAgent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36...",
  "referrer": "https://google.com/search?q=javascript+exam",
  "cookieId": "track_abc123def456",
  "countryCode": "US",
  "city": "New York",
  "accessedAt": "2026-01-24T14:30:00Z",
  "sessionDuration": 245
}
```

### Secret Key Validation Flow

```
┌─────────────────────────────────────────────────────────────┐
│                  SECRET KEY ACCESS FLOW                      │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  1. Visitor accesses: /exam-slug/secret-key                 │
│                          │                                   │
│                          ▼                                   │
│  2. Backend validates:                                       │
│     ├── Does exam exist with this slug?                     │
│     ├── Is exam enabled?                                    │
│     ├── Does secret key exist for this exam?                │
│     ├── Is secret key enabled?                              │
│     ├── Is secret key expired? (expiresAt < NOW)            │
│     └── Has usage limit been reached?                       │
│                          │                                   │
│              ┌───────────┴───────────┐                      │
│              ▼                       ▼                       │
│         [VALID]                 [INVALID]                   │
│              │                       │                       │
│              ▼                       ▼                       │
│  3. Log access:              Show error page:               │
│     - IP address              - "Invalid access key"        │
│     - User agent              - "Key expired"               │
│     - Timestamp               - "Usage limit reached"       │
│     - Referrer                                              │
│     - Set/read tracking cookie                              │
│              │                                              │
│              ▼                                              │
│  4. Increment counters:                                     │
│     - viewCount++                                           │
│     - Check if new unique IP (via hash)                     │
│     - If new: uniqueVisitorCount++                          │
│              │                                              │
│              ▼                                              │
│  5. Render exam (READ-ONLY mode):                           │
│     - Show all exam content                                 │
│     - Show prerequisites (read-only)                        │
│     - Hide signup/login form                                │
│     - Hide progress marking                                 │
│     - Display "Viewing with access key" banner              │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

### Secret Key Admin Interface

#### Key Management Table

| Key | Label | Exam | Status | Views | Unique | Created | Expires | Actions |
|-----|-------|------|--------|-------|--------|---------|---------|---------|
| `abc123...` | Partner Share | JS Basics | ✅ Active | 156 | 89 | Jan 20 | Feb 20 | 📊 ✎ 🗑 |
| `xyz789...` | Guest Preview | React 101 | ⏸️ Disabled | 23 | 15 | Jan 15 | Never | 📊 ✎ 🗑 |
| `guest_2025...` | Public Demo | Python | ✅ Active | 1,204 | 567 | Jan 1 | Jan 31 | 📊 ✎ 🗑 |

#### Key Analytics Dashboard

```
┌─────────────────────────────────────────────────────────────┐
│  Secret Key Analytics: abc123def456                         │
│  Exam: JavaScript Fundamentals                              │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  Overview                                                    │
│  ┌──────────────┬──────────────┬──────────────┬───────────┐ │
│  │ Total Views  │ Unique IPs   │ Avg Duration │ Referrers │ │
│  │     156      │      89      │    4:32      │    12     │ │
│  └──────────────┴──────────────┴──────────────┴───────────┘ │
│                                                              │
│  Views Over Time (Last 30 Days)                             │
│  ┌─────────────────────────────────────────────────────────┐│
│  │  📊 [Line chart showing daily views]                    ││
│  └─────────────────────────────────────────────────────────┘│
│                                                              │
│  Top Referrers                                              │
│  1. google.com (45 views)                                   │
│  2. linkedin.com (32 views)                                 │
│  3. twitter.com (18 views)                                  │
│  4. direct (61 views)                                       │
│                                                              │
│  Geographic Distribution                                    │
│  🇺🇸 United States: 45%                                    │
│  🇬🇧 United Kingdom: 22%                                   │
│  🇮🇳 India: 15%                                            │
│  🇩🇪 Germany: 8%                                           │
│  Other: 10%                                                 │
│                                                              │
│  Recent Access Log                                          │
│  ┌─────────────────────────────────────────────────────────┐│
│  │ Time       │ IP          │ Country │ Referrer          ││
│  │ 2 min ago  │ 192.168.1.x │ 🇺🇸 US   │ google.com       ││
│  │ 15 min ago │ 10.0.0.x    │ 🇬🇧 UK   │ linkedin.com     ││
│  │ 1 hr ago   │ 172.16.x.x  │ 🇮🇳 IN   │ direct           ││
│  └─────────────────────────────────────────────────────────┘│
│                                                              │
│  [Export CSV] [Export JSON] [Clear Logs]                    │
└─────────────────────────────────────────────────────────────┘
```

### Tracking Cookie Implementation

```php
function setTrackingCookie(int $secretKeyId): string {
    $cookieName = 'eqm_track_' . $secretKeyId;
    
    if (!isset($_COOKIE[$cookieName])) {
        $cookieId = 'track_' . bin2hex(random_bytes(16));
        setcookie(
            $cookieName,
            $cookieId,
            [
                'expires' => time() + (365 * 24 * 60 * 60), // 1 year
                'path' => '/',
                'secure' => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax'
            ]
        );
        return $cookieId;
    }
    
    return $_COOKIE[$cookieName];
}
```

---

## Hierarchical Exam Structure

### Parent-Child Relationships

Exams support unlimited nesting depth:

```
"Advanced JavaScript" (parent)
├── "ES6 Fundamentals" (child - level 1)
│   ├── "Arrow Functions" (grandchild - level 2)
│   ├── "Destructuring" (grandchild - level 2)
│   └── "Template Literals" (grandchild - level 2)
├── "Async Programming" (child - level 1)
│   ├── "Callbacks" (grandchild - level 2)
│   ├── "Promises" (grandchild - level 2)
│   │   └── "Promise.all & Promise.race" (great-grandchild - level 3)
│   └── "Async/Await" (grandchild - level 2)
└── "Modules" (child - level 1)
```

### Sub-Exam Features

Each sub-exam functions as a **complete, independent exam** with:
- Independent markdown content
- Own soft and hard deadlines (not inherited)
- Separate participant tracking
- Independent progress tracking
- Own extension requests
- Own prerequisite videos/links/checklists
- Own secret keys

### Cascade Behaviors

| Action | Behavior |
|--------|----------|
| **Delete Parent** | All children deleted recursively (cascade) |
| **Disable Parent** | Children remain in current state (no cascade) |
| **Participant Signup (Parent)** | Does NOT auto-enroll in children |
| **Complete Parent** | Children can still be incomplete |
| **Export Parent** | Includes all children in ZIP |

### Creating Sub-Exam Flow

1. Admin in Edit Exam screen, clicks "Add Sub-Exam" button
2. Opens Upload screen with `parentExamId = currentExamId` pre-filled
3. Admin uploads markdown, confirms details
4. On save:
   - Create exam record with `parentExamId = {parentId}`
   - Validate parent exam exists
   - Child inherits nothing from parent (completely independent exam)
   - Log: `[TIMESTAMP] INFO Sub-exam created: examId={childId} parentExamId={parentId} title="{title}"`

### Dashboard Listing Behavior

- Main dashboard shows **only parent exams** (`parentExamId IS NULL`)
- Child exams accessible through parent's "Sub-Exams" tab
- Breadcrumb navigation shows full hierarchy

---

## Prerequisite System

### Prerequisite Types

| Type | Enum Value | Description | Required Fields |
|------|------------|-------------|-----------------|
| **VIDEO** | `VIDEO` | YouTube/Vimeo URLs | title, url, description (opt) |
| **LINK** | `LINK` | External resources | title, url, description (opt) |
| **CHECKLIST_ITEM** | `CHECKLIST_ITEM` | Manual check-off items | title, description (opt) |

### Data Model

Table: `examPrerequisite`

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| id | INTEGER | yes | Primary Key |
| examId | INTEGER | yes | FK → exam.id |
| type | VARCHAR(20) | yes | Enum: VIDEO, LINK, CHECKLIST_ITEM |
| title | VARCHAR(255) | yes | Display title |
| url | VARCHAR(500) | no | For VIDEO and LINK types |
| description | TEXT | no | Optional description |
| displayOrder | INTEGER | yes | Order for rendering (0-based) |
| isRequired | BOOLEAN | yes | Must complete before proceeding |
| createdAt | DATETIME | yes | |
| updatedAt | DATETIME | yes | |

### Prerequisite Display Order

Prerequisites are displayed in the Pre-Checklist in order:
1. Required items first (sorted by displayOrder)
2. Optional items second (sorted by displayOrder)

### Prerequisite Completion Tracking

```
┌─────────────────────────────────────────────────────────────┐
│  Pre-Exam Checklist                                         │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  Required Prerequisites (3/3 completed) ✅                   │
│  ☑ Watch: Introduction to JavaScript (15 min)              │
│  ☑ Read: ES6 Feature Overview                              │
│  ☑ Check: I have Node.js installed                         │
│                                                              │
│  Optional Prerequisites (1/2 completed)                     │
│  ☑ Watch: Advanced Debugging Techniques                    │
│  ☐ Read: TypeScript Migration Guide                        │
│                                                              │
│  [Start Exam] ← Enabled when all required items complete    │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

### Admin Actions

- **Add prerequisite**: Admin fills form (title, url, description, isRequired), clicks Save
- **Edit prerequisite**: Admin modifies fields, clicks Save
- **Delete prerequisite**: Admin clicks Delete, confirms
- **Reorder**: Admin drags item to new position, displayOrder auto-updated on drop

**All changes persisted immediately to DB with logging:**
```
[TIMESTAMP] INFO Prerequisite created: examId={id} type={type} title="{title}" by adminId={adminId}
[TIMESTAMP] INFO Prerequisite updated: examId={id} type={type} title="{title}" by adminId={adminId}
[TIMESTAMP] INFO Prerequisite deleted: examId={id} prerequisiteId={pid} by adminId={adminId}
```

---

## Checklist & Rubric System

### Checklist Types

| Type | Enum Value | When Shown | Purpose |
|------|------------|------------|---------|
| **PRE** | `PRE` | Before exam starts | Prerequisites + auto-populated items |
| **IN_EXAM** | `IN_EXAM` | During exam | Section checkpoints, rubric items |
| **POST** | `POST` | After exam | Stretch goals, reflection, submission |

### Checklist Item Types

| Item Type | Enum Value | Description | Used In |
|-----------|------------|-------------|---------|
| **VIDEO** | `VIDEO` | Video prerequisite | PRE |
| **LINK** | `LINK` | Resource link | PRE, POST |
| **TEXT** | `TEXT` | Text-based item | PRE, IN_EXAM, POST |
| **SECTION_CHECKPOINT** | `SECTION_CHECKPOINT` | Auto-generated from H2 headers | IN_EXAM |
| **RUBRIC_ITEM** | `RUBRIC_ITEM` | Grading criteria | IN_EXAM |
| **CUSTOM** | `CUSTOM` | Custom admin-defined item | IN_EXAM, POST |

### Pre-Checklist (Auto-Generated)

- **Read-only in UI** - cannot be edited directly
- Backend concatenates:
  1. Prerequisites from `examPrerequisite` table
  2. Default "Read exam instructions" item
- No DB insert; computed on-the-fly when rendering

### In-Exam & Post-Exam Checklists (Admin-Editable)

- Admin can add, edit, delete, reorder items
- Each item persisted to `examChecklist` table
- Supports drag-and-drop reordering

### Rubric Items (Optional)

- Admin can add, edit, delete, reorder rubric criteria
- Each item persisted to `examRubric` table
- Displayed as guidance in frontend (for participant or admin grading reference)

### Data Models

**Table: examChecklist**

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| id | INTEGER | yes | Primary Key |
| examId | INTEGER | yes | FK → exam.id |
| checklistType | VARCHAR(20) | yes | Enum: PRE, IN_EXAM, POST |
| itemType | VARCHAR(30) | yes | Enum: VIDEO, LINK, TEXT, SECTION_CHECKPOINT, RUBRIC_ITEM, CUSTOM |
| title | VARCHAR(255) | yes | Item title |
| description | TEXT | no | Optional description |
| isRequired | BOOLEAN | yes | Must complete |
| displayOrder | INTEGER | yes | Order for rendering |
| createdAt | DATETIME | yes | |
| updatedAt | DATETIME | yes | |

**Table: examRubric**

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| id | INTEGER | yes | Primary Key |
| examId | INTEGER | yes | FK → exam.id |
| criterionTitle | VARCHAR(255) | yes | Rubric criterion name |
| description | TEXT | no | Expectations description |
| isRequired | BOOLEAN | yes | Must complete |
| displayOrder | INTEGER | yes | Order for rendering |
| createdAt | DATETIME | yes | |
| updatedAt | DATETIME | yes | |

---

## Two-Tier Deadline System

### Deadline Types

| Type | Enum Value | Behavior | Can Mark Sections? |
|------|------------|----------|-------------------|
| **Soft Deadline** | `SOFT` | Warning/encouragement only | ✅ Yes |
| **Hard Deadline** | `HARD` | Absolute blocking cutoff | ❌ No |
| **Extension Deadline** | `EXTENSION` | Custom extended deadline | ✅ Yes (until expiry) |

### Storage

Database fields on `participant` table:
- `softDeadlineDate` (DATETIME) - calculated at signup
- `hardDeadlineDate` (DATETIME) - calculated at signup
- `extensionDeadlineDate` (DATETIME, nullable) - only set if extension approved

### Calculation Algorithm

When participant signs up:
- `softDeadlineDate = signupDate + (exam.softDeadlineDays * 24 * 60 * 60)` (in seconds)
- `hardDeadlineDate = signupDate + (exam.hardDeadlineDays * 24 * 60 * 60)` (in seconds)
- `extensionDeadlineDate = NULL` (only set when extension approved)
- Both stored in DB for accurate tracking even if admin changes exam defaults later

#### Extension Calculation (CRITICAL)

```
When admin approves extension:
  baseDeadline = participant.originalHardDeadline ?? participant.hardDeadlineDate
  extensionDeadlineDate = baseDeadline + (approvedDays * 24 * 60 * 60)
  
IMPORTANT: Extensions are calculated from ORIGINAL hard deadline, NOT current date.
IMPORTANT: approvedDays is INTEGER representing calendar days.
```

#### Multiple Extensions

```
For subsequent extensions:
  baseDeadline = participant.extensionDeadlineDate (current extension)
  extensionDeadlineDate = baseDeadline + (additionalDays * 24 * 60 * 60)
```

#### Effective Deadline Priority (for display)

```
1. deadlineOverride        (if NOT NULL) → Admin manual override
2. extensionDeadlineDate   (if NOT NULL and > now()) → Extension granted  
3. hardDeadlineDate        (if NOT NULL) → Exam default
4. NULL                    → No deadline set
```

#### Additional Database Fields

| Field | Type | Purpose |
|-------|------|---------|
| `originalSoftDeadline` | DATETIME | Preserved original before any override |
| `originalHardDeadline` | DATETIME | Preserved original before any extension/override |
| `deadlineOverride` | DATETIME | Admin manual override (highest priority) |
| `deadlineOverrideReason` | VARCHAR(500) | Required reason for audit trail |

### Complete Timeline Example

```
Exam Configuration:
  - softDeadlineDays = 3
  - hardDeadlineDays = 7

Participant signs up: January 24, 2026 @ 1:00 PM
  - softDeadlineDate = January 27, 2026 @ 1:00 PM (signup + 3 days)
  - hardDeadlineDate = January 31, 2026 @ 1:00 PM (signup + 7 days)
  - extensionDeadlineDate = NULL

Timeline:
  ┌─────────────────────────────────────────────────────────────┐
  │ Jan 24-26 (Days 1-3)                                        │
  │ Status: ACTIVE                                              │
  │ Frontend: "You have 3 days to soft deadline"                │
  │ Can mark sections: YES                                      │
  │ Email: "Welcome! Exam started."                             │
  └─────────────────────────────────────────────────────────────┘
  
  ┌─────────────────────────────────────────────────────────────┐
  │ Jan 26 @ 1:00 PM (24h before soft deadline)                 │
  │ Cron job triggers: Send SOFT_DEADLINE_APPROACHING email     │
  │ Email: "Soft deadline approaching - 24 hours left"          │
  │ Status: Still ACTIVE                                        │
  └─────────────────────────────────────────────────────────────┘
  
  ┌─────────────────────────────────────────────────────────────┐
  │ Jan 27 @ 1:00 PM (Soft deadline reached)                    │
  │ Cron job triggers: Status → SOFT_DEADLINE_REACHED           │
  │ Email sent: "Soft deadline reached. Hard deadline: 4 days"  │
  │ Frontend: "Past soft deadline. Hard deadline: 4d 0h"        │
  │ Can mark sections: YES (still allowed)                      │
  └─────────────────────────────────────────────────────────────┘
  
  ┌─────────────────────────────────────────────────────────────┐
  │ Jan 27-30 (Status SOFT_DEADLINE_REACHED)                    │
  │ Frontend: "Soft deadline passed. Hard deadline: Xd Yh left" │
  │ Can mark sections: YES                                      │
  └─────────────────────────────────────────────────────────────┘
  
  ┌─────────────────────────────────────────────────────────────┐
  │ Jan 30 @ 1:00 PM (24h before hard deadline)                 │
  │ Cron job triggers: Send HARD_DEADLINE_APPROACHING email     │
  │ Email: "Hard deadline approaching - 24 hours left!"         │
  │ Status: Still SOFT_DEADLINE_REACHED                         │
  └─────────────────────────────────────────────────────────────┘
  
  ┌─────────────────────────────────────────────────────────────┐
  │ Jan 31 @ 1:00 PM (Hard deadline expires)                    │
  │ Cron job triggers: Status → LOCKED                          │
  │ Email sent: "Hard deadline passed. Exam locked."            │
  │ Frontend: "Hard deadline reached. Exam locked."             │
  │ Can mark sections: NO (BLOCKED)                             │
  │ Participant can: Request extension                          │
  └─────────────────────────────────────────────────────────────┘
  
  ┌─────────────────────────────────────────────────────────────┐
  │ Jan 31 @ 3:00 PM (Participant requests extension)           │
  │ extensionRequest record created with:                       │
  │   - reason: "Unexpected hospital visit"                     │
  │   - requestedDays: 3                                        │
  │   - attachedFile: [optional file]                           │
  │ Admin notified via email                                    │
  │ Frontend: Status badge "Locked - Extension requested"       │
  └─────────────────────────────────────────────────────────────┘
  
  ┌─────────────────────────────────────────────────────────────┐
  │ Feb 1 @ 10:00 AM (Admin approves extension)                 │
  │ Admin sets: approvedDays = 2 (less than requested 3)        │
  │ Updates participant:                                        │
  │   - extensionDeadlineDate = Jan 31 + 2 days = Feb 2 @ 1 PM │
  │   - status = EXTENDED                                       │
  │ Email sent: "Extension approved. New deadline: Feb 2 1 PM"  │
  │ Frontend: "Extended (1d 3h remaining)"                      │
  │ Can mark sections: YES (until Feb 2 @ 1 PM)                │
  └─────────────────────────────────────────────────────────────┘
  
  ┌─────────────────────────────────────────────────────────────┐
  │ Feb 1-2 (Status EXTENDED)                                   │
  │ Frontend: "Extension deadline: Feb 2 @ 1 PM (Xh left)"      │
  │ Can mark sections: YES                                      │
  └─────────────────────────────────────────────────────────────┘
  
  ┌─────────────────────────────────────────────────────────────┐
  │ Feb 2 @ 1:00 PM (Extension deadline expires)                │
  │ Cron job triggers: Status → LOCKED (re-locked)              │
  │ Email sent: "Extension deadline passed. Exam re-locked."    │
  │ Frontend: "Extension expired. Exam re-locked."              │
  │ Can mark sections: NO (BLOCKED again)                       │
  │ Participant can: Request another extension (if allowed)     │
  └─────────────────────────────────────────────────────────────┘
```

### Key Behaviors Summary

1. **Soft deadline is NOT a hard block** → participant can mark sections after soft deadline
2. **Hard deadline IS a hard block** → participant CANNOT mark sections after hard deadline (backend validates)
3. **Extension extends from hard deadline** → not from soft deadline
4. **Status transitions are automatic via WP-Cron** → admin doesn't manually trigger
5. **Countdown display shows actual date/time** → e.g., "Jan 27 2:00 PM (2d 3h)" not just "2 days"
6. **Deadlines are per-participant** → changing exam defaults doesn't affect existing participants

---

## Email Template System

### Default Templates (11 Total)

| Template Key | Trigger | Description |
|--------------|---------|-------------|
| `SIGNUP_CONFIRMATION` | Participant signs up | Welcome email with deadlines |
| `DAILY_DIGEST` | Daily cron (configured time) | Progress summary |
| `SOFT_DEADLINE_APPROACHING` | 24h before soft deadline | Reminder |
| `SOFT_DEADLINE_PASSED` | Soft deadline reached | Encouragement to continue |
| `HARD_DEADLINE_APPROACHING` | 24h before hard deadline | Urgent warning |
| `EXAM_LOCKED` | Hard deadline reached | Locked notification |
| `EXTENSION_REQUESTED` | Participant submits request | Admin notification |
| `EXTENSION_APPROVED` | Admin approves | Confirmation with new deadline |
| `EXTENSION_REJECTED` | Admin rejects | Rejection with reason |
| `EXTENSION_EXPIRED` | Extension deadline passed | Re-locked notification |
| `EXAM_COMPLETED` | All sections completed | Congratulations |

### Template Variables

| Variable | Description | Example |
|----------|-------------|---------|
| `{{participantName}}` | Participant's display name | "John Doe" |
| `{{participantEmail}}` | Participant's email | "john@example.com" |
| `{{examTitle}}` | Exam title | "JavaScript Fundamentals" |
| `{{examSlug}}` | Exam URL slug | "javascript-fundamentals" |
| `{{examUrl}}` | Full exam URL | "https://site.com/exam/javascript-fundamentals" |
| `{{softDeadlineDate}}` | Formatted soft deadline | "January 27, 2026 at 1:00 PM" |
| `{{hardDeadlineDate}}` | Formatted hard deadline | "January 31, 2026 at 1:00 PM" |
| `{{newDeadlineDate}}` | Extension deadline | "February 2, 2026 at 1:00 PM" |
| `{{daysRemaining}}` | Days until deadline | "3" |
| `{{hoursRemaining}}` | Hours until deadline | "24" |
| `{{sectionsCompleted}}` | Completed section count | "5" |
| `{{sectionsTotal}}` | Total section count | "10" |
| `{{progressPercent}}` | Completion percentage | "50%" |
| `{{approvedDays}}` | Admin-approved extension days | "2" |
| `{{requestedDays}}` | Requested extension days | "3" |
| `{{reason}}` | Extension request reason | "Family emergency..." |
| `{{adminNote}}` | Admin note (approval/rejection) | "Approved due to..." |
| `{{rejectionReason}}` | Rejection reason | "Insufficient justification" |
| `{{currentDate}}` | Current date | "January 24, 2026" |
| `{{siteUrl}}` | WordPress site URL | "https://example.com" |
| `{{siteName}}` | WordPress site name | "My Learning Platform" |
| `{{slug}}` | Exam slug | "javascript-basics" |

### Template Storage & Seeding

**Seeding Folder Structure:**
```
/wp-content/uploads/exam-questions-manager/
└── seeding/
    └── email-templates/
        ├── signup-confirmation.html
        ├── daily-digest.html
        ├── soft-deadline-approaching.html
        ├── soft-deadline-passed.html
        ├── hard-deadline-approaching.html
        ├── exam-locked.html
        ├── extension-requested.html
        ├── extension-approved.html
        ├── extension-rejected.html
        ├── extension-expired.html
        └── exam-completed.html
```

**Plugin Activation (Seeding Logic):**
1. Check if seeding folder exists; create if not
2. Copy default template files from plugin package to seeding folder
3. Read each template file (HTML format)
4. For each file:
   - Extract templateKey from filename (e.g., `signup-confirmation` from `signup-confirmation.html`)
   - Query DB: `SELECT * FROM emailTemplate WHERE templateKey = ?`
   - If NOT found: INSERT new record with content from file
   - If found: SKIP (preserve admin edits)
5. Log: `[TIMESTAMP] INFO Email templates seeded: {count} templates loaded from /seeding/email-templates/`

**Admin Edit Template:**
- Update `emailTemplate` record in DB
- Do NOT modify seeding files
- Update `updatedAt` timestamp
- Log: `[TIMESTAMP] INFO Email template updated: templateKey={key} by adminId={adminId}`

**Admin Reset to Default:**
- Read seeding file for this template
- Overwrite DB record with seeding file content
- Confirm action: "This will discard your edits. Continue?"
- Log: `[TIMESTAMP] INFO Email template reset to default: templateKey={key} by adminId={adminId}`

**Export/Import:**
- Export: Download template as `.html` file with metadata comment
- Import: Parse uploaded file, create/update DB record
- Log: `[TIMESTAMP] INFO Email template imported: templateKey={key} by adminId={adminId}`

---

## Database Schema (Complete)

### Entity Relationship Diagram

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           DATABASE SCHEMA                                    │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  ┌──────────────┐         ┌──────────────┐         ┌──────────────┐         │
│  │   wp_users   │         │  userRole    │         │     exam     │         │
│  │   (WP Core)  │◄────────│              │         │              │         │
│  └──────────────┘         └──────────────┘         └──────┬───────┘         │
│         │                                                  │                 │
│         │                                           ┌──────┴───────┐        │
│         │                                           │ (self-ref)   │        │
│         │                                           │ parentExamId │        │
│         │                                           └──────────────┘        │
│         │                                                  │                 │
│         │                    ┌─────────────────────────────┼────────────┐   │
│         │                    │                             │            │   │
│         │              ┌─────▼─────┐              ┌────────▼────────┐   │   │
│         │              │ examPre-  │              │  examChecklist  │   │   │
│         │              │ requisite │              │                 │   │   │
│         │              └───────────┘              └─────────────────┘   │   │
│         │                                                               │   │
│         │              ┌───────────┐              ┌─────────────────┐   │   │
│         │              │ examRubric│              │   secretKey     │   │   │
│         │              └───────────┘              └────────┬────────┘   │   │
│         │                                                  │            │   │
│         │                                         ┌────────▼────────┐   │   │
│         │                                         │ secretKeyAccess │   │   │
│         │                                         └─────────────────┘   │   │
│         │                                                               │   │
│         │              ┌─────────────┐        ┌──────────────┐         │   │
│         │              │ participant │◄───────│   progress   │         │   │
│         │              └──────┬──────┘        └──────────────┘         │   │
│         │                     │                                        │   │
│         │                     │               ┌──────────────┐         │   │
│         │                     └───────────────│ extension-   │         │   │
│         │                                     │   Request    │         │   │
│         │                                     └──────────────┘         │   │
│         │                                                               │   │
│         │              ┌─────────────┐                                 │   │
│         └──────────────│    wiki     │◄────────┐                       │   │
│                        └─────────────┘         │                       │   │
│                                         ┌──────┴───────┐               │   │
│                                         │ wikiRevision │               │   │
│                                         └──────────────┘               │   │
│                                                                         │   │
│         ┌──────────────┐        ┌──────────────────────┐               │   │
│         │emailTemplate │        │ participantChecklist │◄──────────────┘   │
│         └──────────────┘        └──────────────────────┘                   │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Table Definitions

#### Table: userRole

| Field | Type | Required | Default | Notes |
|-------|------|----------|---------|-------|
| id | INTEGER | yes | AUTO_INCREMENT | Primary Key |
| userId | INTEGER | yes | | FK → wp_users.ID |
| role | VARCHAR(20) | yes | | Enum: ADMIN, EXAM_EDITOR, EXAMINEE |
| assignedAt | DATETIME | yes | CURRENT_TIMESTAMP | |
| assignedBy | INTEGER | no | NULL | FK → wp_users.ID (NULL for system) |

**Indexes:** UNIQUE(userId)

---

#### Table: exam

| Field | Type | Required | Default | Notes |
|-------|------|----------|---------|-------|
| id | INTEGER | yes | AUTO_INCREMENT | Primary Key |
| parentExamId | INTEGER | no | NULL | FK → exam.id (self-referential) |
| title | VARCHAR(255) | yes | | Extracted from H1, editable by admin |
| description | TEXT | yes | | Extracted from first paragraph, editable |
| slug | VARCHAR(100) | yes | | Unique, indexed, hyphens only |
| markdownFilePath | VARCHAR(255) | yes | | Relative path: `questions/{slug}.md` |
| softDeadlineDays | INTEGER | yes | 7 | Default soft deadline in days |
| hardDeadlineDays | INTEGER | yes | 14 | Must be >= softDeadlineDays |
| secretKeyEnabled | BOOLEAN | yes | false | Enable secret key feature |
| enabled | BOOLEAN | yes | true | Enable/disable exam access |
| createdAt | DATETIME | yes | CURRENT_TIMESTAMP | |
| updatedAt | DATETIME | yes | CURRENT_TIMESTAMP | |
| createdBy | INTEGER | no | NULL | FK → wp_users.ID |

**Indexes:** `(parentExamId)`, `(slug)` UNIQUE, `(enabled)`  
**Foreign Key:** `parentExamId` → `exam.id` (self-referential, ON DELETE CASCADE)

---

#### Table: participant

| Field | Type | Required | Default | Notes |
|-------|------|----------|---------|-------|
| id | INTEGER | yes | AUTO_INCREMENT | Primary Key |
| examId | INTEGER | yes | | FK → exam.id |
| email | VARCHAR(255) | yes | | Unique per exam |
| whatsapp | VARCHAR(20) | yes | | Phone number |
| linkedin | VARCHAR(255) | yes | | LinkedIn URL |
| passwordHash | VARCHAR(255) | yes | | bcrypt hash |
| status | VARCHAR(30) | yes | 'ACTIVE' | Enum value |
| signupDate | DATETIME | yes | CURRENT_TIMESTAMP | |
| softDeadlineDate | DATETIME | yes | | Calculated at signup |
| hardDeadlineDate | DATETIME | yes | | Calculated at signup |
| extensionDeadlineDate | DATETIME | no | NULL | Set when extension approved |
| createdAt | DATETIME | yes | CURRENT_TIMESTAMP | |
| updatedAt | DATETIME | yes | CURRENT_TIMESTAMP | |

**Indexes:** `(examId)`, `(examId, email)` UNIQUE  
**Foreign Key:** `examId` → `exam.id` (ON DELETE CASCADE)  
**Status Values:** `ACTIVE`, `SOFT_DEADLINE_REACHED`, `HARD_DEADLINE_REACHED`, `LOCKED`, `COMPLETED`, `EXTENDED`

---

#### Table: progress

| Field | Type | Required | Default | Notes |
|-------|------|----------|---------|-------|
| id | INTEGER | yes | AUTO_INCREMENT | Primary Key |
| participantId | INTEGER | yes | | FK → participant.id |
| sectionNumber | INTEGER | yes | | H2 header index (1-based) |
| sectionTitle | VARCHAR(255) | yes | | H2 header text |
| isMarkedDone | BOOLEAN | yes | false | is/has prefix |
| completedAt | DATETIME | no | NULL | Timestamp when marked done |

**Indexes:** `(participantId)`, `(participantId, sectionNumber)` UNIQUE  
**Foreign Key:** `participantId` → `participant.id` (ON DELETE CASCADE)

---

#### Table: extensionRequest

| Field | Type | Required | Default | Notes |
|-------|------|----------|---------|-------|
| id | INTEGER | yes | AUTO_INCREMENT | Primary Key |
| participantId | INTEGER | yes | | FK → participant.id |
| reason | TEXT | yes | | Min 10, max 1000 chars |
| requestedDays | INTEGER | yes | | Days requested (1-365) |
| attachedFilePath | VARCHAR(255) | no | NULL | Relative path if file uploaded |
| isAdminApproved | BOOLEAN | no | NULL | NULL=pending, true=approved, false=rejected |
| adminApprovedDays | INTEGER | no | NULL | Days approved by admin |
| adminNote | TEXT | no | NULL | Admin's note |
| requestedAt | DATETIME | yes | CURRENT_TIMESTAMP | |
| processedAt | DATETIME | no | NULL | When admin processed |

**Indexes:** `(participantId)`, `(isAdminApproved)`  
**Foreign Key:** `participantId` → `participant.id` (ON DELETE CASCADE)

---

#### Table: secretKey

| Field | Type | Required | Default | Notes |
|-------|------|----------|---------|-------|
| id | INTEGER | yes | AUTO_INCREMENT | Primary Key |
| examId | INTEGER | yes | | FK → exam.id |
| keyValue | VARCHAR(64) | yes | | Unique, min 8 chars |
| label | VARCHAR(100) | no | NULL | Admin reference |
| description | TEXT | no | NULL | Notes |
| isEnabled | BOOLEAN | yes | true | Enable/disable |
| expiresAt | DATETIME | no | NULL | NULL = never expires |
| usageLimit | INTEGER | no | NULL | NULL = unlimited |
| viewCount | INTEGER | yes | 0 | Total views |
| uniqueVisitorCount | INTEGER | yes | 0 | Unique IPs |
| createdAt | DATETIME | yes | CURRENT_TIMESTAMP | |
| createdBy | INTEGER | no | NULL | FK → wp_users.ID |

**Indexes:** `(examId)`, `(keyValue)` UNIQUE  
**Foreign Key:** `examId` → `exam.id` (ON DELETE CASCADE)

---

#### Table: secretKeyAccess

| Field | Type | Required | Default | Notes |
|-------|------|----------|---------|-------|
| id | INTEGER | yes | AUTO_INCREMENT | Primary Key |
| secretKeyId | INTEGER | yes | | FK → secretKey.id |
| ipAddress | VARCHAR(45) | yes | | IPv4 or IPv6 |
| ipAddressHash | VARCHAR(64) | yes | | SHA-256 for unique counting |
| userAgent | TEXT | no | NULL | Browser info |
| referrer | VARCHAR(500) | no | NULL | HTTP referrer |
| cookieId | VARCHAR(50) | no | NULL | Tracking cookie |
| countryCode | VARCHAR(2) | no | NULL | GeoIP country |
| city | VARCHAR(100) | no | NULL | GeoIP city |
| accessedAt | DATETIME | yes | CURRENT_TIMESTAMP | |
| sessionDuration | INTEGER | no | NULL | Seconds |

**Indexes:** `(secretKeyId)`, `(secretKeyId, ipAddressHash)`  
**Foreign Key:** `secretKeyId` → `secretKey.id` (ON DELETE CASCADE)

---

#### Table: wiki

| Field | Type | Required | Default | Notes |
|-------|------|----------|---------|-------|
| id | INTEGER | yes | AUTO_INCREMENT | Primary Key |
| title | VARCHAR(255) | yes | | Page title |
| slug | VARCHAR(100) | yes | | Unique URL slug |
| category | VARCHAR(100) | no | NULL | Category name |
| markdownContent | TEXT | yes | | Page content |
| visibility | VARCHAR(20) | yes | 'PUBLIC' | Enum |
| visibilityRoles | TEXT | no | NULL | JSON array of roles |
| authorId | INTEGER | yes | | FK → wp_users.ID |
| createdAt | DATETIME | yes | CURRENT_TIMESTAMP | |
| updatedAt | DATETIME | yes | CURRENT_TIMESTAMP | |

**Indexes:** `(slug)` UNIQUE, `(visibility)`, `(category)`  
**Visibility Values:** `PUBLIC`, `AUTHENTICATED`, `ROLE`, `PRIVATE`

---

#### Table: wikiRevision

| Field | Type | Required | Default | Notes |
|-------|------|----------|---------|-------|
| id | INTEGER | yes | AUTO_INCREMENT | Primary Key |
| wikiId | INTEGER | yes | | FK → wiki.id |
| markdownContent | TEXT | yes | | Content at revision |
| revisionNumber | INTEGER | yes | | Sequential |
| authorId | INTEGER | yes | | FK → wp_users.ID |
| createdAt | DATETIME | yes | CURRENT_TIMESTAMP | |
| changeNote | VARCHAR(255) | no | NULL | Optional note |

**Indexes:** `(wikiId)`, `(wikiId, revisionNumber)` UNIQUE  
**Foreign Key:** `wikiId` → `wiki.id` (ON DELETE CASCADE)

---

#### Table: emailTemplate

| Field | Type | Required | Default | Notes |
|-------|------|----------|---------|-------|
| id | INTEGER | yes | AUTO_INCREMENT | Primary Key |
| templateKey | VARCHAR(50) | yes | | Unique enum key |
| subject | VARCHAR(255) | yes | | Email subject line |
| body | TEXT | yes | | HTML body with {{variables}} |
| createdAt | DATETIME | yes | CURRENT_TIMESTAMP | |
| updatedAt | DATETIME | yes | CURRENT_TIMESTAMP | |

**Indexes:** `(templateKey)` UNIQUE

---

#### Table: examPrerequisite

| Field | Type | Required | Default | Notes |
|-------|------|----------|---------|-------|
| id | INTEGER | yes | AUTO_INCREMENT | Primary Key |
| examId | INTEGER | yes | | FK → exam.id |
| type | VARCHAR(20) | yes | | Enum: VIDEO, LINK, CHECKLIST_ITEM |
| title | VARCHAR(255) | yes | | Display title |
| url | VARCHAR(500) | no | NULL | For VIDEO/LINK |
| description | TEXT | no | NULL | Optional |
| displayOrder | INTEGER | yes | 0 | Order (0-based) |
| isRequired | BOOLEAN | yes | true | Must complete |
| createdAt | DATETIME | yes | CURRENT_TIMESTAMP | |
| updatedAt | DATETIME | yes | CURRENT_TIMESTAMP | |

**Indexes:** `(examId)`, `(examId, displayOrder)`  
**Foreign Key:** `examId` → `exam.id` (ON DELETE CASCADE)

---

#### Table: examChecklist

| Field | Type | Required | Default | Notes |
|-------|------|----------|---------|-------|
| id | INTEGER | yes | AUTO_INCREMENT | Primary Key |
| examId | INTEGER | yes | | FK → exam.id |
| checklistType | VARCHAR(20) | yes | | Enum: PRE, IN_EXAM, POST |
| itemType | VARCHAR(30) | yes | | Enum: VIDEO, LINK, TEXT, etc. |
| title | VARCHAR(255) | yes | | Item title |
| description | TEXT | no | NULL | Optional |
| isRequired | BOOLEAN | yes | true | Must complete |
| displayOrder | INTEGER | yes | 0 | Order |
| createdAt | DATETIME | yes | CURRENT_TIMESTAMP | |
| updatedAt | DATETIME | yes | CURRENT_TIMESTAMP | |

**Indexes:** `(examId)`, `(examId, checklistType, displayOrder)`  
**Foreign Key:** `examId` → `exam.id` (ON DELETE CASCADE)

---

#### Table: examRubric

| Field | Type | Required | Default | Notes |
|-------|------|----------|---------|-------|
| id | INTEGER | yes | AUTO_INCREMENT | Primary Key |
| examId | INTEGER | yes | | FK → exam.id |
| criterionTitle | VARCHAR(255) | yes | | Rubric criterion |
| description | TEXT | no | NULL | Expectations |
| isRequired | BOOLEAN | yes | true | Must complete |
| displayOrder | INTEGER | yes | 0 | Order |
| createdAt | DATETIME | yes | CURRENT_TIMESTAMP | |
| updatedAt | DATETIME | yes | CURRENT_TIMESTAMP | |

**Indexes:** `(examId)`, `(examId, displayOrder)`  
**Foreign Key:** `examId` → `exam.id` (ON DELETE CASCADE)

---

#### Table: participantChecklist

| Field | Type | Required | Default | Notes |
|-------|------|----------|---------|-------|
| id | INTEGER | yes | AUTO_INCREMENT | Primary Key |
| participantId | INTEGER | yes | | FK → participant.id |
| checklistId | INTEGER | yes | | FK → examChecklist.id |
| isCompleted | BOOLEAN | yes | false | |
| completedAt | DATETIME | no | NULL | |

**Indexes:** `(participantId)`, `(participantId, checklistId)` UNIQUE  
**Foreign Keys:** ON DELETE CASCADE for both

---

## Admin UI Screens

### Screen 1: Main Dashboard

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  Exam Questions Manager                                      [Admin ▼]      │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐    │
│  │    Exams     │  │ Participants │  │  Extensions  │  │    Wiki      │    │
│  │      12      │  │     156      │  │   3 pending  │  │   24 pages   │    │
│  └──────────────┘  └──────────────┘  └──────────────┘  └──────────────┘    │
│                                                                              │
│  Quick Actions                                                              │
│  [+ New Exam] [+ New Wiki Page] [View Extensions] [Email Templates]         │
│                                                                              │
│  Recent Activity                                                            │
│  • John Doe signed up for "JavaScript Basics" - 2 hours ago                 │
│  • Extension approved for jane@example.com - 5 hours ago                    │
│  • New wiki page "Getting Started" created - Yesterday                      │
│                                                                              │
│  Exams Overview                               [Search] [Filter ▼] [+ Add]   │
│  ┌───────────────────────────────────────────────────────────────────────┐  │
│  │ Title          │ Status  │ Children │ Participants │ Pending │ Actions│  │
│  ├───────────────────────────────────────────────────────────────────────┤  │
│  │ JS Basics      │ ✅ On   │ 3        │ 45           │ 2       │ ⚙ ✎ 🗑 │  │
│  │ React 101      │ ✅ On   │ 0        │ 28           │ 0       │ ⚙ ✎ 🗑 │  │
│  │ Python Intro   │ ⏸ Off  │ 5        │ 83           │ 1       │ ⚙ ✎ 🗑 │  │
│  └───────────────────────────────────────────────────────────────────────┘  │
│                                                                              │
│  * Shows only parent exams (parentExamId IS NULL)                           │
│  * Participant count = direct participants only (not recursive)             │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Screen 2: Create/Upload Exam

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  Create New Exam                                                    [Back]  │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  Upload Exam File                                                           │
│  ┌───────────────────────────────────────────────────────────────────────┐  │
│  │                                                                        │  │
│  │     📁 Drag and drop .md or .zip file here                            │  │
│  │                                                                        │  │
│  │                    or [Browse Files]                                   │  │
│  │                                                                        │  │
│  │     Max: 10 MB per file, 50 MB for zip                                │  │
│  └───────────────────────────────────────────────────────────────────────┘  │
│                                                                              │
│  ─────────────────────────── OR ───────────────────────────                 │
│                                                                              │
│  Create Manually                                                            │
│                                                                              │
│  Title *                                                                    │
│  ┌───────────────────────────────────────────────────────────────────────┐  │
│  │ JavaScript Fundamentals                                                │  │
│  └───────────────────────────────────────────────────────────────────────┘  │
│                                                                              │
│  Description *                                                              │
│  ┌───────────────────────────────────────────────────────────────────────┐  │
│  │ Learn the core concepts of JavaScript including variables, functions, │  │
│  │ and control flow.                                                      │  │
│  └───────────────────────────────────────────────────────────────────────┘  │
│                                                                              │
│  Slug *                          Parent Exam                                │
│  ┌─────────────────────────┐    ┌─────────────────────────┐                │
│  │ javascript-fundamentals │    │ None (Top-level)      ▼ │                │
│  └─────────────────────────┘    └─────────────────────────┘                │
│                                                                              │
│  Soft Deadline (days) *          Hard Deadline (days) *                     │
│  ┌─────────────────────────┐    ┌─────────────────────────┐                │
│  │ 7                       │    │ 14                      │                │
│  └─────────────────────────┘    └─────────────────────────┘                │
│                                                                              │
│  ☑ Enable exam immediately                                                 │
│  ☐ Enable secret key access                                                │
│                                                                              │
│  [Cancel]                                                    [Create Exam]  │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Screen 3: Edit Exam (6-Tab Interface)

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  ◄ Exams / JavaScript Basics                                        [Save] │
│  Breadcrumb: Dashboard > JavaScript Advanced > ES6 Fundamentals > Edit     │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  [Content] [Metadata] [Sub-Exams] [Prerequisites] [Checklists] [Secret Keys]│
│  ═══════════════════════════════════════════════════════════════════════════│
│                                                                              │
│  ═══ TAB 1: CONTENT ═══                                                     │
│  ┌─────────────────────────────────┬─────────────────────────────────────┐  │
│  │ Markdown Editor                 │ Live Preview                        │  │
│  │                                 │                                     │  │
│  │ # JavaScript Basics             │ JavaScript Basics                   │  │
│  │                                 │ ═══════════════════                 │  │
│  │ Learn fundamental concepts.     │                                     │  │
│  │                                 │ Learn fundamental concepts.         │  │
│  │ ## Section 1: Variables         │                                     │  │
│  │                                 │ Section 1: Variables                │  │
│  │ Variables store data...         │ ─────────────────────               │  │
│  │                                 │                                     │  │
│  └─────────────────────────────────┴─────────────────────────────────────┘  │
│                                                                              │
│  ═══ TAB 2: METADATA ═══                                                    │
│  Title: [JavaScript Basics                    ]                             │
│  Description: [Learn fundamental concepts...  ]                             │
│  Slug: [javascript-basics                     ]                             │
│  Soft Deadline Days: [7 ]  Hard Deadline Days: [14]                         │
│  Parent Exam: [None (Top-level) ▼]                                          │
│  ☑ Enabled                                                                  │
│  Created: Jan 20, 2026  |  Modified: Jan 24, 2026                           │
│                                                                              │
│  ═══ TAB 3: SUB-EXAMS ═══                                                   │
│  ┌───────────────────────────────────────────────────────────────────────┐  │
│  │ Title            │ Status  │ Participants │ Actions                   │  │
│  ├───────────────────────────────────────────────────────────────────────┤  │
│  │ ES6 Features     │ ✅ On   │ 12           │ ✎ View Participants 🗑   │  │
│  │ Async/Await      │ ✅ On   │ 8            │ ✎ View Participants 🗑   │  │
│  └───────────────────────────────────────────────────────────────────────┘  │
│  [+ Add Sub-Exam]                                                           │
│                                                                              │
│  ═══ TAB 4: PREREQUISITES ═══                                               │
│  Prerequisite Videos                           [+ Add Video]                │
│  ┌───────────────────────────────────────────────────────────────────────┐  │
│  │ ≡ │ Introduction to JS (YouTube) │ Required │ ✎ 🗑                    │  │
│  │ ≡ │ Setup Your Environment       │ Optional │ ✎ 🗑                    │  │
│  └───────────────────────────────────────────────────────────────────────┘  │
│                                                                              │
│  Prerequisite Links                            [+ Add Link]                 │
│  ┌───────────────────────────────────────────────────────────────────────┐  │
│  │ ≡ │ MDN JavaScript Guide        │ Required │ ✎ 🗑                     │  │
│  └───────────────────────────────────────────────────────────────────────┘  │
│                                                                              │
│  Prerequisite Checklist Items                  [+ Add Item]                 │
│  ┌───────────────────────────────────────────────────────────────────────┐  │
│  │ ≡ │ Node.js installed           │ Required │ ✎ 🗑                     │  │
│  │ ≡ │ VS Code installed           │ Optional │ ✎ 🗑                     │  │
│  └───────────────────────────────────────────────────────────────────────┘  │
│                                                                              │
│  ═══ TAB 5: CHECKLISTS & RUBRICS ═══                                        │
│  Pre-Checklist (auto-generated from prerequisites - READ ONLY)              │
│  In-Exam Checklist                             [+ Add Item]                 │
│  Post-Exam Checklist                           [+ Add Item]                 │
│  Rubric Items                                  [+ Add Criterion]            │
│                                                                              │
│  ═══ TAB 6: SECRET KEYS ═══                                                 │
│  ☑ Enable secret key access for this exam                                  │
│                                                                              │
│  Secret Keys                                              [+ Generate Key]  │
│  ┌───────────────────────────────────────────────────────────────────────┐  │
│  │ Key           │ Label        │ Views │ Unique │ Expires   │ Actions   │  │
│  ├───────────────────────────────────────────────────────────────────────┤  │
│  │ abc123...     │ Partner      │ 156   │ 89     │ Feb 20    │ 📊 ✎ 🗑  │  │
│  │ xyz789...     │ Guest Demo   │ 23    │ 15     │ Never     │ 📊 ✎ 🗑  │  │
│  └───────────────────────────────────────────────────────────────────────┘  │
│                                                                              │
│  Full URL: https://example.com/exam/javascript-basics/{key}                 │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Screen 4: Participants (per exam)

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  ◄ Exams / JavaScript Basics / Participants                                │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  [Search by email...        ] [Status: All ▼] [Export CSV]                  │
│                                                                              │
│  ┌───────────────────────────────────────────────────────────────────────┐  │
│  │ Email          │ Status        │ Soft DL      │ Hard DL     │ Progress│  │
│  ├───────────────────────────────────────────────────────────────────────┤  │
│  │ john@email.com │ 🟢 ACTIVE     │ Jan 27 (2d)  │ Jan 31 (6d) │ 3/10    │  │
│  │ jane@email.com │ 🟡 SOFT_DL    │ Jan 25 ✓     │ Jan 29 (2d) │ 7/10    │  │
│  │ bob@email.com  │ 🔴 LOCKED     │ Jan 20 ✓     │ Jan 24 ✓    │ 5/10    │  │
│  │ alice@mail.com │ 🟣 EXTENDED   │ Jan 18 ✓     │ Jan 22 ✓    │ 8/10    │  │
│  │                │               │ Ext: Feb 2   │             │         │  │
│  │ mike@email.com │ ✅ COMPLETED  │ Jan 20 ✓     │ Jan 24 -    │ 10/10   │  │
│  └───────────────────────────────────────────────────────────────────────┘  │
│                                                                              │
│  Actions per row: [View Details] [Extend Manually] [Remove]                 │
│                                                                              │
│  Status Legend:                                                             │
│  🟢 ACTIVE - Within soft deadline                                          │
│  🟡 SOFT_DEADLINE_REACHED - Past soft, before hard                         │
│  🔴 LOCKED - Past hard deadline, cannot mark sections                      │
│  🟣 EXTENDED - Has active extension                                        │
│  ✅ COMPLETED - All sections marked done                                   │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Screen 5: Extension Requests (per exam)

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  ◄ Exams / JavaScript Basics / Extension Requests                          │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  [Search...] [Status: Pending ▼]                                            │
│                                                                              │
│  ┌───────────────────────────────────────────────────────────────────────┐  │
│  │ Email          │ Requested │ Reason (truncated)    │ Status  │ Actions│  │
│  ├───────────────────────────────────────────────────────────────────────┤  │
│  │ bob@email.com  │ 3 days    │ Hospital visit...     │ ⏳ Pending│ ✓ ✗ 👁│  │
│  │ sue@email.com  │ 5 days    │ Family emergency...   │ ⏳ Pending│ ✓ ✗ 👁│  │
│  │ jim@email.com  │ 2 days    │ Work deadline...      │ ✅ Approved│    👁│  │
│  │ amy@email.com  │ 7 days    │ No justification...   │ ❌ Rejected│    👁│  │
│  └───────────────────────────────────────────────────────────────────────┘  │
│                                                                              │
│  ✓ = Approve   ✗ = Reject   👁 = View Full Details                          │
│                                                                              │
│  ═══ APPROVE DIALOG ═══                                                     │
│  ┌─────────────────────────────────────────────────────────────────┐       │
│  │ Approve Extension Request                                       │       │
│  │                                                                  │       │
│  │ Participant: bob@email.com                                      │       │
│  │ Requested Days: 3                                               │       │
│  │ Reason: [Full text displayed here...]                          │       │
│  │                                                                  │       │
│  │ Approved Days: [3    ] ← Can override to approve fewer days    │       │
│  │ Admin Note: [                                          ]        │       │
│  │                                                                  │       │
│  │ [Cancel]                                          [Approve]     │       │
│  └─────────────────────────────────────────────────────────────────┘       │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Screen 6: Wiki Management

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  Wiki Pages                                                  [+ New Page]   │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  Categories           │  Pages                                              │
│  ┌──────────────────┐ │  ┌─────────────────────────────────────────────────┐│
│  │ All Pages    (24)│ │  │ Title        │ Category    │ Visibility│ Actions││
│  │ Getting Started  │ │  ├─────────────────────────────────────────────────┤│
│  │ Exam Guides      │ │  │ Welcome      │ Getting..   │ 🌐 Public │ ✎ 👁 🗑││
│  │ Technical Docs   │ │  │ Installation │ Getting..   │ 🌐 Public │ ✎ 👁 🗑││
│  │ FAQs             │ │  │ Admin Setup  │ Getting..   │ 🔒 Admin  │ ✎ 👁 🗑││
│  │ Uncategorized    │ │  │ How to Exam  │ Exam Guides │ 👤 Auth   │ ✎ 👁 🗑││
│  └──────────────────┘ │  │ API Docs     │ Technical   │ 🔒 Admin  │ ✎ 👁 🗑││
│                       │  └─────────────────────────────────────────────────┘│
│  [+ New Category]     │                                                     │
│                       │  Visibility Legend:                                 │
│                       │  🌐 PUBLIC  👤 AUTHENTICATED  🔒 ROLE-based        │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Screen 7: Email Templates

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  Email Templates                                      [Import] [Reseed All] │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  [Signup] [Daily] [Soft DL] [Hard DL] [Locked] [Ext.Req] [Approved] [More ▼]│
│  ═══════════════════════════════════════════════════════════════════════════│
│                                                                              │
│  Template: SIGNUP_CONFIRMATION                                              │
│                                                                              │
│  Subject:                                                                   │
│  ┌───────────────────────────────────────────────────────────────────────┐  │
│  │ Welcome to {{examTitle}} - Your Exam Awaits!                          │  │
│  └───────────────────────────────────────────────────────────────────────┘  │
│                                                                              │
│  Body (HTML):                                                               │
│  ┌───────────────────────────────────────────────────────────────────────┐  │
│  │ <!DOCTYPE html>                                                        │  │
│  │ <html>                                                                 │  │
│  │ <body style="font-family: Arial, sans-serif;">                        │  │
│  │   <h1>Welcome, {{participantName}}!</h1>                              │  │
│  │   <p>You have signed up for: <strong>{{examTitle}}</strong></p>       │  │
│  │   <p>Soft Deadline: {{softDeadlineDate}}</p>                          │  │
│  │   <p>Hard Deadline: {{hardDeadlineDate}}</p>                          │  │
│  │ </body>                                                                │  │
│  │ </html>                                                                │  │
│  └───────────────────────────────────────────────────────────────────────┘  │
│                                                                              │
│  Available Variables:                                                       │
│  {{participantName}}, {{examTitle}}, {{softDeadlineDate}},                  │
│  {{hardDeadlineDate}}, {{slug}}, {{examUrl}}, {{siteName}}                  │
│                                                                              │
│  [Preview] [Reset to Default] [Export]                            [Save]    │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Screen 8: Plugin Settings

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  Plugin Settings                                                            │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  General Settings                                                           │
│  ─────────────────                                                          │
│  Admin Email Address: [admin@example.com                          ]         │
│  Default Soft Deadline Days: [7  ]                                          │
│  Default Hard Deadline Days: [14 ]                                          │
│                                                                              │
│  WP-Cron Settings                                                           │
│  ─────────────────                                                          │
│  ☑ Enable WP-Cron Jobs                                                     │
│  Execution Time: [09:00 ▼] (24-hour format)                                 │
│                                                                              │
│  Feature Toggles                                                            │
│  ───────────────                                                            │
│  ☐ Enable Secret Key Feature (globally)                                    │
│  ☑ Enable GeoIP Tracking for Secret Keys                                   │
│                                                                              │
│  Email Settings                                                             │
│  ─────────────                                                              │
│  [Reseed Email Templates] - Re-import from seeding folder                   │
│                                                                              │
│  Database Maintenance                                                       │
│  ────────────────────                                                       │
│  [Backup Database] - Download SQLite file                                   │
│  [Reset All Data] - ⚠️ Deletes all data (requires confirmation)            │
│                                                                              │
│  Logs                                                                       │
│  ────                                                                       │
│  [View General Logs] [View Error Logs] [Clear All Logs]                     │
│                                                                              │
│                                                               [Save Settings]│
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Screen 9: Role Management (Admin Only)

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  Role Management                                          [+ Assign Role]   │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  Current Role Assignments                                                   │
│  ┌───────────────────────────────────────────────────────────────────────┐  │
│  │ WordPress User    │ Plugin Role    │ Assigned      │ By      │ Actions│  │
│  ├───────────────────────────────────────────────────────────────────────┤  │
│  │ admin@site.com    │ 👑 Admin       │ Jan 1, 2026   │ System  │    -   │  │
│  │ editor@site.com   │ ✏️ Exam Editor │ Jan 15, 2026  │ Admin   │ ✎ 🗑  │  │
│  │ user@site.com     │ 📝 Examinee    │ Jan 20, 2026  │ Admin   │ ✎ 🗑  │  │
│  └───────────────────────────────────────────────────────────────────────┘  │
│                                                                              │
│  Assign New Role Dialog                                                     │
│  ┌─────────────────────────────────────────────────────────────────┐       │
│  │ Select WordPress User: [Search users...               ▼]        │       │
│  │ Assign Role: [EXAM_EDITOR ▼]                                    │       │
│  │                                                                  │       │
│  │ [Cancel]                                            [Assign]    │       │
│  └─────────────────────────────────────────────────────────────────┘       │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## Backend API Endpoints

### REST API Routes

All endpoints prefixed with: `/wp-json/eqm/v1/`

#### Exam Endpoints

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| GET | `/exams` | List parent exams | Admin, Editor |
| GET | `/exams/{id}` | Get exam details | Admin, Editor |
| POST | `/exams` | Create exam | Admin, Editor |
| PUT | `/exams/{id}` | Update exam | Admin, Editor |
| DELETE | `/exams/{id}` | Delete exam (cascade) | Admin, Editor |
| GET | `/exams/{id}/children` | List sub-exams | Admin, Editor |
| GET | `/exams/{id}/participants` | List participants | Admin, Editor |
| GET | `/exams/{id}/extensions` | List extension requests | Admin, Editor |

#### Participant Endpoints

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| POST | `/signup` | Participant signup | Public |
| POST | `/login` | Participant login | Public |
| GET | `/participants/{id}` | Get participant | Admin, Editor, Self |
| PUT | `/participants/{id}/extend` | Manual extension | Admin, Editor |
| DELETE | `/participants/{id}` | Remove participant | Admin, Editor |
| POST | `/participants/{id}/mark-section` | Mark section done | Self |

#### Extension Endpoints

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| POST | `/extension-requests` | Submit request | Participant |
| GET | `/extension-requests/{id}` | Get request | Admin, Editor |
| PUT | `/extension-requests/{id}/approve` | Approve | Admin, Editor |
| PUT | `/extension-requests/{id}/reject` | Reject | Admin, Editor |

#### Wiki Endpoints

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| GET | `/wiki` | List accessible pages | Visibility-based |
| GET | `/wiki/{slug}` | Get page | Visibility-based |
| POST | `/wiki` | Create page | Admin, Editor |
| PUT | `/wiki/{id}` | Update page | Admin, Editor |
| DELETE | `/wiki/{id}` | Delete page | Admin, Editor |

#### Secret Key Endpoints

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| GET | `/exams/{id}/secret-keys` | List keys | Admin, Editor |
| POST | `/exams/{id}/secret-keys` | Generate key | Admin, Editor |
| PUT | `/secret-keys/{id}` | Update key | Admin, Editor |
| DELETE | `/secret-keys/{id}` | Delete key | Admin, Editor |
| GET | `/secret-keys/{id}/analytics` | Get analytics | Admin, Editor |

#### Public Access

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| GET | `/exam/{slug}` | Get exam (public) | Public |
| GET | `/exam/{slug}/{key}` | Access via secret key | Secret Key |

---

## Participant Management

### Signup Flow

**Input**: `{ examId, email, whatsapp, linkedin, password }`

**Validations**:
- Email format valid
- Email not already registered for this exam (composite key: examId + email)
- All fields not empty
- WhatsApp format valid
- LinkedIn URL format valid
- Password min 8 characters

**On Success**:
1. Hash password using bcrypt
2. Calculate deadlines:
   - `softDeadlineDate = NOW() + exam.softDeadlineDays`
   - `hardDeadlineDate = NOW() + exam.hardDeadlineDays`
3. Create participant record with status = `ACTIVE`
4. Parse markdown, count H2 headers
5. Create progress records (one per H2 section)
6. Send `SIGNUP_CONFIRMATION` email
7. Return 200 with success message
8. Log: `[TIMESTAMP] INFO Participant signup: email={email} examId={examId} softDeadline={date} hardDeadline={date}`

**On Error**:
- Duplicate email: Return 409 "Email already registered for this exam"
- Validation failure: Return 400 "Invalid {fieldName}: {reason}"

### Mark Section Done

**Input**: `{ participantId, sectionNumber }`

**Validations**:
- Participant exists
- Section number valid for this exam
- Participant status not `LOCKED`
- Current time < applicable deadline (extension or hard)
- Section not already marked done

**On Success**:
1. Update progress: `isMarkedDone = true, completedAt = NOW()`
2. Check if all sections complete → update status to `COMPLETED`
3. Return 200
4. Log: `[TIMESTAMP] INFO Section marked done: participantId={id} sectionNumber={num} examId={examId}`

**On Error**:
- Deadline passed: Return 403 "Deadline passed. Cannot mark sections."
- Already marked: Return 400 "Section already marked done."

---

## Extension Request System

### Submit Request

**Input**: `{ participantId, reason, requestedDays, attachedFile (optional) }`

**Validations**:
- Participant exists
- Status is `LOCKED` or `HARD_DEADLINE_REACHED`
- Reason: 10-1000 characters
- requestedDays: 1-365
- File: pdf, doc, docx, txt, jpg, png (max 5 MB)

**On Success**:
1. Create extensionRequest record with `isAdminApproved = NULL`
2. If file: save to `/extensions/{requestId}/`
3. Send email to plugin admin
4. Return 200
5. Log: `[TIMESTAMP] INFO Extension request submitted: requestId={id} participantId={pid} email={email} requestedDays={days}`

### Approve Request

**Input**: `{ requestId, approvedDays, adminNote }`

**On Success**:
1. Update extensionRequest: `isAdminApproved = true`, `adminApprovedDays`, `adminNote`, `processedAt = NOW()`
2. Update participant: `extensionDeadlineDate = hardDeadlineDate + approvedDays`, `status = EXTENDED`
3. Send `EXTENSION_APPROVED` email
4. Return 200
5. Log: `[TIMESTAMP] INFO Extension approved: requestId={id} participantId={pid} adminApprovedDays={days} by adminId={adminId}`

### Reject Request

**Input**: `{ requestId, adminNote }`

**On Success**:
1. Update extensionRequest: `isAdminApproved = false`, `adminNote`, `processedAt = NOW()`
2. Participant status remains `LOCKED`
3. Send `EXTENSION_REJECTED` email
4. Return 200
5. Log: `[TIMESTAMP] INFO Extension rejected: requestId={id} participantId={pid} reason="{note}" by adminId={adminId}`

---

## WP-Cron Jobs

All jobs run daily at configured time (default 9:00 AM).

### Job 1: Daily Digest

```php
Query: SELECT * FROM participant 
WHERE status IN ('ACTIVE', 'SOFT_DEADLINE_REACHED', 'EXTENDED') 
AND exam.enabled = true

For each participant:
  - Fetch exam details
  - Count completed sections
  - Calculate daysRemaining
  - Render DAILY_DIGEST template
  - Send email
  
Log: [TIMESTAMP] INFO Daily digest sent: {count} emails
```

### Job 2: Soft Deadline Approaching

```php
Query: SELECT * FROM participant 
WHERE softDeadlineDate BETWEEN NOW() AND NOW() + 24 hours
AND status = 'ACTIVE'
AND exam.enabled = true

For each participant:
  - Render SOFT_DEADLINE_APPROACHING template
  - Send email
  
Log: [TIMESTAMP] INFO Soft deadline approach: {count} emails sent
```

### Job 3: Soft Deadline Reached

```php
Query: SELECT * FROM participant 
WHERE softDeadlineDate < NOW()
AND status = 'ACTIVE'
AND exam.enabled = true

For each participant:
  - Update status = SOFT_DEADLINE_REACHED
  - Render SOFT_DEADLINE_PASSED template
  - Send email
  
Log: [TIMESTAMP] INFO Soft deadline reached: {count} status updated
```

### Job 4: Hard Deadline Approaching

```php
Query: SELECT * FROM participant 
WHERE hardDeadlineDate BETWEEN NOW() AND NOW() + 24 hours
AND status IN ('ACTIVE', 'SOFT_DEADLINE_REACHED')
AND exam.enabled = true

For each participant:
  - Render HARD_DEADLINE_APPROACHING template
  - Send email
  
Log: [TIMESTAMP] INFO Hard deadline approach: {count} emails sent
```

### Job 5: Hard Deadline Expiration

```php
Query: SELECT * FROM participant 
WHERE hardDeadlineDate < NOW()
AND status IN ('ACTIVE', 'SOFT_DEADLINE_REACHED')
AND exam.enabled = true

For each participant:
  - Update status = LOCKED
  - Render EXAM_LOCKED template
  - Send email
  
Log: [TIMESTAMP] INFO Hard deadline expiration: {count} participants locked
```

### Job 6: Extension Deadline Expiration

```php
Query: SELECT * FROM participant 
WHERE extensionDeadlineDate IS NOT NULL
AND extensionDeadlineDate < NOW()
AND status = 'EXTENDED'
AND exam.enabled = true

For each participant:
  - Update status = LOCKED
  - Render EXTENSION_EXPIRED template
  - Send email
  
Log: [TIMESTAMP] INFO Extension deadline expiration: {count} participants re-locked
```

### Job Safety

- All jobs check `exam.enabled = true`
- Jobs are idempotent (can run multiple times without duplicate effects)
- Errors logged to error.txt with stack trace
- If job fails partway, state preserved for next run

---

## Logging System (Dual-File)

### Log Files

**General Log**: `/wp-content/uploads/exam-questions-manager/logs/plugin.log`
- All events: plugin activation, exam CRUD, participant actions, cron jobs
- Retained indefinitely (admin can manually clear)

**Error Log**: `/wp-content/uploads/exam-questions-manager/logs/error.txt`
- ONLY errors and exceptions
- Includes full stack trace
- Rotated daily: `error-YYYYMMDD.txt`

### Log Format

**General Log**:
```
[YYYY-MM-DD HH:MM:SS] {LEVEL} {ACTION} {DETAILS}

Examples:
[2026-01-24 13:00:00] INFO Plugin activated
[2026-01-24 13:05:30] INFO Exam created: "JavaScript Basics" examId=5 slug="javascript-basics" by adminId=1
[2026-01-24 13:10:45] INFO Participant signup: email="john@example.com" examId=5 softDeadline="2026-01-27 13:05:30" hardDeadline="2026-01-31 13:05:30"
[2026-01-24 14:20:00] INFO Section marked done: participantId=12 sectionNumber=3 examId=5
[2026-01-24 15:30:15] INFO Extension approved: requestId=8 participantId=12 email="john@example.com" adminApprovedDays=2 by adminId=1
[2026-01-24 16:00:00] INFO Daily digest sent: 25 emails
```

**Error Log**:
```
[YYYY-MM-DD HH:MM:SS] {ERROR_TYPE} {MESSAGE}
{STACK_TRACE}
---

Example:
[2026-01-24 13:15:00] InvalidDataException Email validation failed for "invalid-email"
Stack Trace:
  File: /wp-content/plugins/exam-questions-manager/src/Services/ParticipantService.php
  Line: 42
  Message: Invalid email format
  Called by: ParticipantService::validateEmail()
  Called by: ParticipantService::signup()
---
```

### Logging Best Practices

**DO**:
- Log all successful major actions with relevant IDs
- Log all errors with full context and stack trace
- Include timestamps and user/actor context
- Use consistent levels (INFO, WARNING, ERROR, DEBUG)

**DO NOT**:
- Log passwords or sensitive data
- Log raw SQL queries
- Use generic messages ("Error occurred")
- Forget to log context IDs

---

## Code Standards & Guidelines

### 1. Enums & Constants (No Magic Strings)

**Rule**: All multi-option values use enums or constants. Never use string literals in code.

```php
// ✅ Good
$participant->setStatus(ParticipantStatus::ACTIVE);
if ($participant->getStatus() === ParticipantStatus::LOCKED) { ... }

// ❌ Bad (magic string)
$participant->setStatus('ACTIVE');
if ($participant->getStatus() === 'LOCKED') { ... }
```

### 2. Boolean Naming Convention

**Rule**: All boolean variables and functions use `is` or `has` prefix.

```php
// ✅ Good
$isDeadlineReached = true;
$hasExtension = false;
$isRequired = true;
function isDeadlineReached($participant) { ... }
function hasExtensionApproved($request) { ... }

// ❌ Bad
$deadlineReached = true;
$extension = false;
function deadlineReached($participant) { ... }
```

### 3. Database Field Naming (PascalCase)

**Rule**: All database field names use PascalCase (not snake_case or camelCase).  
ORM properties use camelCase to distinguish code from database.

```
❌ Bad: soft_deadline_days, extension_deadline_date, marked_done
❌ Bad: softDeadlineDays, extensionDeadlineDate, isMarkedDone (camelCase - use in ORM only)
✅ Good: SoftDeadlineDays, ExtensionDeadlineDate, IsMarkedDone (Database columns)
```

### 4. Code Naming Conventions (PHP)

- **Classes**: PascalCase (`ParticipantManager`, `ExamRepository`)
- **Functions/Methods**: camelCase (`getParticipant()`, `markSectionDone()`)
- **Constants**: UPPER_SNAKE_CASE (via enums)
- **Variables**: camelCase (`$participantId`, `$softDeadlineDate`)

### 5. ORM Usage Requirement

**Rule**: All database operations use ORM. Never use raw SQL queries.

```php
// ✅ Good (ORM)
$participant = Participant::findByEmail($email, $examId);
$participant->setStatus(ParticipantStatus::EXTENDED);
$participant->save();

// ❌ Bad (Raw SQL)
$wpdb->get_results("SELECT * FROM participants WHERE email = '$email'");
```

---

## Enums & Constants

### PHP Enum Definitions

```php
<?php
// src/Enums/ParticipantStatus.php
enum ParticipantStatus: string {
    case ACTIVE = 'ACTIVE';
    case SOFT_DEADLINE_REACHED = 'SOFT_DEADLINE_REACHED';
    case HARD_DEADLINE_REACHED = 'HARD_DEADLINE_REACHED';
    case LOCKED = 'LOCKED';
    case COMPLETED = 'COMPLETED';
    case EXTENDED = 'EXTENDED';
}

// src/Enums/UserRoleType.php
enum UserRoleType: string {
    case ADMIN = 'ADMIN';
    case EXAM_EDITOR = 'EXAM_EDITOR';
    case EXAMINEE = 'EXAMINEE';
}

// src/Enums/WikiVisibility.php
enum WikiVisibility: string {
    case PUBLIC = 'PUBLIC';
    case AUTHENTICATED = 'AUTHENTICATED';
    case ROLE = 'ROLE';
    case PRIVATE = 'PRIVATE';
}

// src/Enums/ChecklistType.php
enum ChecklistType: string {
    case PRE = 'PRE';
    case IN_EXAM = 'IN_EXAM';
    case POST = 'POST';
}

// src/Enums/ChecklistItemType.php
enum ChecklistItemType: string {
    case VIDEO = 'VIDEO';
    case LINK = 'LINK';
    case TEXT = 'TEXT';
    case SECTION_CHECKPOINT = 'SECTION_CHECKPOINT';
    case RUBRIC_ITEM = 'RUBRIC_ITEM';
    case CUSTOM = 'CUSTOM';
}

// src/Enums/PrerequisiteType.php
enum PrerequisiteType: string {
    case VIDEO = 'VIDEO';
    case LINK = 'LINK';
    case CHECKLIST_ITEM = 'CHECKLIST_ITEM';
}

// src/Enums/ExtensionStatus.php
enum ExtensionStatus: string {
    case PENDING = 'PENDING';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';
}

// src/Enums/EmailTemplateKey.php
enum EmailTemplateKey: string {
    case SIGNUP_CONFIRMATION = 'SIGNUP_CONFIRMATION';
    case DAILY_DIGEST = 'DAILY_DIGEST';
    case SOFT_DEADLINE_APPROACHING = 'SOFT_DEADLINE_APPROACHING';
    case SOFT_DEADLINE_PASSED = 'SOFT_DEADLINE_PASSED';
    case HARD_DEADLINE_APPROACHING = 'HARD_DEADLINE_APPROACHING';
    case EXAM_LOCKED = 'EXAM_LOCKED';
    case EXTENSION_REQUESTED = 'EXTENSION_REQUESTED';
    case EXTENSION_APPROVED = 'EXTENSION_APPROVED';
    case EXTENSION_REJECTED = 'EXTENSION_REJECTED';
    case EXTENSION_EXPIRED = 'EXTENSION_EXPIRED';
    case EXAM_COMPLETED = 'EXAM_COMPLETED';
}

// src/Enums/LogLevel.php
enum LogLevel: string {
    case INFO = 'INFO';
    case WARNING = 'WARNING';
    case ERROR = 'ERROR';
    case DEBUG = 'DEBUG';
    case CRITICAL = 'CRITICAL';
}

// src/Enums/DeadlineType.php
enum DeadlineType: string {
    case SOFT = 'SOFT';
    case HARD = 'HARD';
    case EXTENSION = 'EXTENSION';
}
```

---

## File Structure

```
/wp-content/plugins/exam-questions-manager/
├── exam-questions-manager.php          # Main plugin file
├── uninstall.php                        # Cleanup on uninstall
├── composer.json                        # PHP dependencies
├── package.json                         # JS dependencies
│
├── /src/
│   ├── /Admin/
│   │   ├── AdminMenu.php               # WordPress admin menu
│   │   ├── AdminAssets.php             # Enqueue scripts/styles
│   │   └── AdminController.php         # Admin AJAX handlers
│   │
│   ├── /API/
│   │   ├── RestController.php          # REST API base
│   │   ├── ExamEndpoints.php           # Exam CRUD
│   │   ├── WikiEndpoints.php           # Wiki CRUD
│   │   ├── ParticipantEndpoints.php    # Participant endpoints
│   │   ├── SecretKeyEndpoints.php      # Secret key endpoints
│   │   └── PublicEndpoints.php         # Public access
│   │
│   ├── /Database/
│   │   ├── Schema.php                  # SQLite schema
│   │   ├── Migrations.php              # Schema migrations
│   │   ├── Connection.php              # PDO wrapper
│   │   └── Seeder.php                  # Default data seeding
│   │
│   ├── /ORM/
│   │   ├── Model.php                   # Base model
│   │   ├── Repository.php              # Base repository
│   │   └── /Models/
│   │       ├── Exam.php
│   │       ├── Participant.php
│   │       ├── Progress.php
│   │       ├── ExtensionRequest.php
│   │       ├── SecretKey.php
│   │       ├── SecretKeyAccess.php
│   │       ├── Wiki.php
│   │       ├── WikiRevision.php
│   │       ├── UserRole.php
│   │       ├── EmailTemplate.php
│   │       ├── ExamPrerequisite.php
│   │       ├── ExamChecklist.php
│   │       ├── ExamRubric.php
│   │       └── ParticipantChecklist.php
│   │
│   ├── /Services/
│   │   ├── ExamService.php             # Exam business logic
│   │   ├── ParticipantService.php      # Participant management
│   │   ├── DeadlineService.php         # Deadline calculations
│   │   ├── ExtensionService.php        # Extension handling
│   │   ├── EmailService.php            # Email sending
│   │   ├── SecretKeyService.php        # Key generation/validation
│   │   ├── WikiService.php             # Wiki management
│   │   ├── RoleService.php             # Role management
│   │   └── MarkdownService.php         # Markdown parsing
│   │
│   ├── /Cron/
│   │   ├── CronManager.php             # Cron registration
│   │   ├── DailyDigestJob.php
│   │   ├── DeadlineCheckJob.php
│   │   └── CleanupJob.php
│   │
│   ├── /Enums/
│   │   ├── ParticipantStatus.php
│   │   ├── ChecklistType.php
│   │   ├── ChecklistItemType.php
│   │   ├── PrerequisiteType.php
│   │   ├── WikiVisibility.php
│   │   ├── UserRoleType.php
│   │   ├── ExtensionStatus.php
│   │   ├── EmailTemplateKey.php
│   │   ├── DeadlineType.php
│   │   └── LogLevel.php
│   │
│   └── /Utils/
│       ├── Logger.php                  # Logging utility
│       ├── Validator.php               # Input validation
│       ├── Sanitizer.php               # Data sanitization
│       └── FileHandler.php             # File operations
│
├── /admin/                             # React admin app
│   ├── /src/
│   │   ├── App.tsx
│   │   ├── /components/
│   │   ├── /pages/
│   │   ├── /hooks/
│   │   └── /services/
│   └── /build/
│
├── /public/                            # Frontend participant interface
│   ├── /src/
│   └── /build/
│
└── /assets/
    ├── /css/
    └── /images/

/wp-content/uploads/exam-questions-manager/
├── /questions/                         # Markdown files
│   ├── javascript-basics.md
│   └── react-fundamentals.md
├── /extensions/                        # Extension request attachments
│   └── /{requestId}/
├── /seeding/
│   └── /email-templates/               # Default email templates
│       ├── signup-confirmation.html
│       └── ...
├── /db/
│   └── exam-questions.sqlite           # SQLite database
└── /logs/
    ├── plugin.log                      # General logs
    └── error.txt                       # Error logs
```

---

## Workflows & Sequences

### Workflow 1: Plugin Activation

1. **Trigger**: Admin activates plugin
2. **Actions**:
   - Create directory structure
   - Copy default email templates to seeding folder
   - Create SQLite database
   - Initialize all tables
   - Seed email templates to DB (if not exists)
   - Seed default admin role
3. **Log**: `[TIMESTAMP] INFO Plugin activated. Email templates seeded: {count} templates loaded.`

### Workflow 2: Exam Creation via Upload

1. Admin uploads `.md` file
2. Parse markdown: extract H1 → title, first paragraph → description
3. Auto-generate slug (lowercase, hyphens, timestamp if duplicate)
4. Show modal with editable fields
5. On confirm: save file to filesystem, create DB record
6. Redirect to Edit screen
7. **Log**: `[TIMESTAMP] INFO Exam created: "{title}" examId={id} by adminId={adminId}`

### Workflow 3: Participant Signup

1. POST to signup endpoint with credentials
2. Validate all fields
3. Hash password, calculate deadlines
4. Create participant + progress records
5. Send `SIGNUP_CONFIRMATION` email
6. **Log**: `[TIMESTAMP] INFO Participant signup: email={email} examId={examId}`

### Workflow 4: Extension Request Approval

1. Admin clicks "Approve" on pending request
2. Dialog shows with override option
3. Admin confirms with days and note
4. Update extensionRequest, participant records
5. Send `EXTENSION_APPROVED` email
6. **Log**: `[TIMESTAMP] INFO Extension approved: requestId={id} adminApprovedDays={days} by adminId={adminId}`

### Workflow 5: Secret Key Access

1. Visitor accesses `/exam-slug/secret-key`
2. Validate: exam exists, key valid, not expired, under limit
3. Log access with IP, user agent, referrer
4. Set/read tracking cookie
5. Increment counters
6. Render exam in read-only mode

### Workflow 6: Daily Cron Execution

1. WP-Cron triggers at configured time
2. Run all deadline check jobs
3. Run daily digest job
4. Log results for each job
5. Handle errors gracefully, continue with next job

---

## Diagrams

### Diagram 1: Participant Lifecycle & Deadline Flow

```
                                      Participant Signs Up
                                              |
                                              v
                                      Status: ACTIVE
                                    (Days 1-N, before soft)
                                              |
                              ┌───────────────┼───────────────┐
                              |               |               |
                       (Complete all)  (24h before soft) (Soft deadline)
                              |               |               |
                              v               v               v
                         COMPLETED      Send email      SOFT_DEADLINE_REACHED
                                              |               |
                                              |    (Continue working)
                                              |               |
                                              |    ┌──────────┼──────────┐
                                              |    |          |          |
                                              | (Complete) (24h before) (Hard DL)
                                              |    |          |          |
                                              |    v          v          v
                                              | COMPLETED  Send email  LOCKED
                                              |                           |
                                              |              ┌────────────┼────────────┐
                                              |              |            |            |
                                              |        (No action)  (Request ext)  (Complete)
                                              |              |            |            |
                                              |              v            v            v
                                              |           LOCKED    Pending Review  COMPLETED
                                              |                           |
                                              |              ┌────────────┼────────────┐
                                              |              |            |            |
                                              |          (Approve)    (Reject)    (No action)
                                              |              |            |            |
                                              |              v            v            v
                                              |          EXTENDED      LOCKED       LOCKED
                                              |              |
                                              |    (Extension expires)
                                              |              |
                                              |              v
                                              └──────────> LOCKED (re-locked)
```

### Diagram 2: Email Template Seeding Flow

```
Plugin Activation
        |
        v
Check seeding folder exists
        |
   ┌────┴────┐
   |         |
   NO        YES
   |         |
   v         v
Create    Use existing
folder    folder
   |         |
   └────┬────┘
        |
        v
Copy templates from plugin → seeding folder
        |
        v
For each template file:
   Extract templateKey
   Check DB: exists?
        |
   ┌────┴────┐
   |         |
   NO        YES
   |         |
   v         v
INSERT    SKIP (preserve edits)
   |         |
   └────┬────┘
        |
        v
Log: "Email templates seeded"
```

### Diagram 3: Secret Key Access Flow

```
Request: /exam-slug/secret-key
              |
              v
        Exam exists?
              |
         ┌────┴────┐
         |         |
        YES        NO
         |         |
         v         v
   Key valid?    404 Error
         |
    ┌────┴────┐
    |         |
   YES        NO
    |         |
    v         v
Expired?   403 Error
    |
  ┌─┴─┐
  |   |
 YES  NO
  |   |
  v   v
403  Usage limit?
      |
   ┌──┴──┐
   |     |
  YES    NO
   |     |
   v     v
403   Log access
       Set cookie
       Increment counters
       Render exam (read-only)
```

---

## Acceptance Criteria

### Feature: File Upload & Markdown Parsing

- [ ] Admin can drag-drop or select `.md` file
- [ ] Admin can drag-drop or select `.zip` with multiple `.md` files
- [ ] System extracts H1 → title (or filename if no H1)
- [ ] System extracts first paragraph → description
- [ ] Auto-generated slug: lowercase, hyphens, timestamp if duplicate
- [ ] Modal displays with editable fields before confirm
- [ ] On confirm: save to filesystem + DB, redirect to Edit screen
- [ ] Log: `INFO Exam created: "{title}" examId={id} by adminId={adminId}`

### Feature: Two-Tier Deadline Management

- [ ] softDeadlineDays < hardDeadlineDays enforced
- [ ] Deadlines calculated at signup, stored on participant
- [ ] Soft deadline does NOT block section marking
- [ ] Hard deadline DOES block section marking
- [ ] WP-Cron updates statuses automatically
- [ ] Extension extends from hard deadline
- [ ] Countdown displays actual date/time

### Feature: Hierarchical Exams

- [ ] Exam can have optional parentExamId
- [ ] Child exam is fully independent
- [ ] Dashboard lists only parent exams
- [ ] Edit screen shows breadcrumb navigation
- [ ] Delete parent cascades all children
- [ ] Sub-Exams tab shows all children

### Feature: Extension Request Workflow

- [ ] Participant can request after hard deadline
- [ ] Request includes reason, days, optional file
- [ ] Admin receives notification email
- [ ] Admin can approve with day override
- [ ] Admin can reject with reason
- [ ] Approval updates extensionDeadlineDate
- [ ] Status changes to EXTENDED

### Feature: Wiki System

- [ ] Create wiki pages with Markdown
- [ ] Four visibility levels work correctly
- [ ] Categories can be managed
- [ ] Revision history tracked
- [ ] Cross-linking with [[Page Title]] syntax
- [ ] Full-text search works
- [ ] Role-based access enforced

### Feature: Secret Key System

- [ ] Multiple keys per exam
- [ ] Key validation (enabled, not expired, under limit)
- [ ] Access logging (IP, user agent, referrer, cookie)
- [ ] Analytics dashboard shows views, unique IPs, referrers
- [ ] GeoIP tracking (optional)
- [ ] Key can be disabled/enabled/deleted

### Feature: RBAC System

- [ ] Three roles: Admin, Exam Editor, Examinee
- [ ] Admin can assign/revoke roles
- [ ] Permissions enforced on all endpoints
- [ ] Default admin seeded on activation

### Feature: Code Standards

- [ ] No magic strings (enums used)
- [ ] Boolean variables use is/has prefix
- [ ] Database fields use camelCase
- [ ] ORM used for all DB operations
- [ ] Dual logging (plugin.log + error.txt)

---

## Implementation Phases

### Phase 1: Foundation (Week 1-2)
- [ ] Set up plugin structure and autoloading
- [ ] Create SQLite database connection and schema
- [ ] Implement base ORM classes
- [ ] Define all enums
- [ ] Create all entity models
- [ ] Implement Logger utility
- [ ] Set up WordPress admin menu

### Phase 2: Role System (Week 2-3)
- [ ] Create userRole table
- [ ] Implement RoleService
- [ ] Add role checking middleware
- [ ] Create role management admin UI
- [ ] Seed default admin role

### Phase 3: Exam Management (Week 3-4)
- [ ] Implement ExamService
- [ ] Create exam CRUD endpoints
- [ ] Build admin exam list page
- [ ] Build create/upload exam page
- [ ] Build edit exam page with tabs
- [ ] Implement hierarchical exam support

### Phase 4: Wiki System (Week 5-6)
- [ ] Implement WikiService
- [ ] Create wiki CRUD endpoints
- [ ] Build wiki list admin page
- [ ] Build wiki editor page
- [ ] Implement visibility checking
- [ ] Add revision history

### Phase 5: Secret Key System (Week 6-7)
- [ ] Implement SecretKeyService
- [ ] Create key generation logic
- [ ] Build key management UI
- [ ] Implement access tracking
- [ ] Create analytics dashboard
- [ ] Add GeoIP lookup (optional)

### Phase 6: Prerequisites & Checklists (Week 7-8)
- [ ] Build prerequisite management UI
- [ ] Build checklist management UI
- [ ] Implement rubric management
- [ ] Create participant checklist tracking

### Phase 7: Participant System (Week 8-9)
- [ ] Implement ParticipantService
- [ ] Create signup/login endpoints
- [ ] Build participant list admin page
- [ ] Implement progress tracking
- [ ] Build extension request system

### Phase 8: Deadline System (Week 9-10)
- [ ] Implement DeadlineService
- [ ] Create deadline calculation logic
- [ ] Implement status transitions
- [ ] Add deadline validation

### Phase 9: Email System (Week 10-11)
- [ ] Implement EmailService
- [ ] Create email template seeding
- [ ] Build template editor UI
- [ ] Implement variable substitution

### Phase 10: Cron Jobs (Week 11)
- [ ] Register all cron jobs
- [ ] Implement all deadline check jobs
- [ ] Implement daily digest job
- [ ] Add cron management in settings

### Phase 11: Frontend (Week 12-13)
- [ ] Build exam viewing interface
- [ ] Build progress marking UI
- [ ] Build extension request form
- [ ] Implement public wiki viewing
- [ ] Add secret key access flow

### Phase 12: Testing & Polish (Week 14)
- [ ] Write unit tests
- [ ] Write integration tests
- [ ] Security audit
- [ ] Performance optimization
- [ ] Documentation

---

## Security Considerations

### Input Validation

```php
class Validator {
    public static function email(string $email): bool {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    public static function slug(string $slug): bool {
        return preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) === 1;
    }
    
    public static function isPositiveInt($value): bool {
        return is_numeric($value) && intval($value) > 0;
    }
}
```

### SQL Injection Prevention

All operations use prepared statements via ORM:
```php
// ✅ Always use ORM
$exam = Exam::findBySlug($slug);

// ❌ Never use raw SQL
$wpdb->get_results("SELECT * FROM exam WHERE slug = '{$slug}'");
```

### XSS Prevention

```php
// In PHP templates
echo esc_html($title);
echo esc_attr($value);
echo esc_url($url);

// React escapes by default
```

### CSRF Protection

```php
// Generate nonce
wp_nonce_field('eqm_admin_action', 'eqm_nonce');

// Verify nonce
check_ajax_referer('eqm_admin_action', 'nonce');
```

### Password Security

```php
// Hash (signup)
$hash = password_hash($password, PASSWORD_BCRYPT);

// Verify (login)
$isValid = password_verify($inputPassword, $storedHash);
```

---

## Assumptions & Open Questions

### Assumptions Made

1. **Password Hashing**: Using bcrypt via `password_hash()`
2. **Deadline Changes**: Changing exam defaults doesn't affect existing participants
3. **Extension Limit**: No limit on extension requests per participant
4. **Cron Frequency**: Daily at configured time only
5. **Nested Depth**: Unlimited nesting allowed

### Open Questions

1. Should participants see their own progress dashboard?
2. Should admin receive email when participant completes?
3. Should there be a grace period after hard deadline?
4. Should extension deadline be soft or hard?
5. Should template variables support nested objects?

---

## Sample Email Templates

### signup-confirmation.html

```html
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Welcome to {{examTitle}}</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h1 style="color: #2563eb;">Welcome, {{participantName}}!</h1>
        
        <p>You have successfully signed up for:</p>
        
        <div style="background: #f3f4f6; padding: 20px; border-radius: 8px; margin: 20px 0;">
            <h2 style="margin-top: 0;">{{examTitle}}</h2>
            <p><strong>Soft Deadline:</strong> {{softDeadlineDate}}</p>
            <p><strong>Hard Deadline:</strong> {{hardDeadlineDate}}</p>
        </div>
        
        <p>You can access your exam at:</p>
        <p><a href="{{examUrl}}" style="color: #2563eb;">{{examUrl}}</a></p>
        
        <p>Good luck!</p>
        
        <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 30px 0;">
        <p style="font-size: 12px; color: #6b7280;">
            This email was sent from {{siteName}}. 
        </p>
    </div>
</body>
</html>
```

---

## Document History

| Version | Date | Changes |
|---------|------|---------|
| 1.0.0 | Jan 2025 | Initial specification |
| 2.0.0 | Jan 2026 | Full merge: RBAC, Wiki, Secret Keys, detailed workflows, acceptance criteria |

---

**END OF SPECIFICATION**

*This document is ready for implementation by a WordPress/PHP developer.*
