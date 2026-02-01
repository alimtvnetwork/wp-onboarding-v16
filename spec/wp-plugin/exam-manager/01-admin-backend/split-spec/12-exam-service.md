# 10. Exam Service

## Overview
Core business logic service for exam CRUD operations and content management.

---

## 10.1 ExamService Class

### Responsibilities
- Create, read, update, delete exams
- Manage exam content files
- Parse Markdown for structure extraction
- Handle exam status transitions

### Acceptance Criteria:
- [ ] Service registered in dependency container
- [ ] All methods use ORM (no raw SQL)
- [ ] Transactions for multi-step operations
- [ ] Proper error handling with exceptions

---

## 10.2 Create Exam

### Input Requirements
- Title (required, max 255 chars)
- Description (optional, max 1000 chars)
- Status (default: DRAFT)
- Content file (optional Markdown)

### Process Flow
1. Validate input data
2. Generate unique slug from title
3. Create exam record in database
4. Store content file if provided
5. Return created exam entity

### Acceptance Criteria:
- [ ] Slug is URL-safe and unique
- [ ] Content file stored in uploads directory
- [ ] Created exam has all default values
- [ ] Audit log entry created
- [ ] Returns complete exam entity

---

## 10.3 Update Exam

### Updateable Fields
- Title (regenerates slug if changed)
- Description
- Status
- Content (replaces file)
- Settings (JSON field)

### Validation Rules
- Cannot change status to ARCHIVED if active participants
- Title changes require unique slug check
- Content changes create backup of previous

### Acceptance Criteria:
- [ ] Partial updates supported
- [ ] Previous content backed up
- [ ] Status transition rules enforced
- [ ] Updated timestamp set
- [ ] Audit log entry created

---

## 10.4 Delete Exam

### Soft Delete
- Set `deletedAt` timestamp
- Keep all related data
- Exclude from normal queries

### Hard Delete (Admin Only)
- Remove exam record
- Delete content files
- Cascade delete related records
- Requires confirmation flag

### Acceptance Criteria:
- [ ] Soft delete is default
- [ ] Hard delete requires explicit flag
- [ ] Related participants handled appropriately
- [ ] Content files removed on hard delete
- [ ] Audit log entry created

---

## 10.5 Markdown Parsing

### Structure Extraction
- Extract H1 as exam title (if not set)
- Extract H2 headings as sections
- Count total sections for progress
- Identify code blocks and tables

### Content Processing
- Sanitize HTML if present
- Convert relative image paths to absolute
- Parse wiki links `[[Page Name]]`
- Extract metadata from frontmatter (optional)

### Acceptance Criteria:
- [ ] H1/H2 extraction accurate
- [ ] Section count stored in exam record
- [ ] Wiki links converted to actual URLs
- [ ] Images uploaded and paths updated
- [ ] Frontmatter parsed if present

---

## 10.6 H2 Section Extraction Algorithm

### Overview
Extract H2 headers from exam Markdown content to create progress-trackable sections. Each H2 becomes a checklist item in the `IN_EXAM` phase.

### Regex Patterns (from Consts.php)

All regex patterns MUST be defined in `Consts.php` for consistency and maintainability.

```php
<?php
// File: src/Consts.php

class Consts {
    // ═══════════════════════════════════════════════════════════════════
    // MARKDOWN SECTION EXTRACTION REGEX PATTERNS
    // ═══════════════════════════════════════════════════════════════════
    
    /**
     * REGEX_H2_HEADER - Extract H2 headers from Markdown
     * 
     * Pattern: /^## (.+)$/m
     * 
     * Explanation:
     * - ^      : Start of line (with 'm' modifier, works on each line)
     * - ##     : Literal two hash characters (H2 in Markdown)
     * - (space): Single space after ##
     * - (.+)   : Capture group - one or more characters (the title)
     * - $      : End of line
     * - m      : Multiline modifier (^ and $ match line boundaries)
     * 
     * Examples that MATCH:
     * - "## Introduction"           → captures "Introduction"
     * - "## Getting Started Guide"  → captures "Getting Started Guide"
     * - "## Step 1: Setup"          → captures "Step 1: Setup"
     * - "## **Bold Title**"         → captures "**Bold Title**" (formatting preserved)
     * 
     * Examples that DO NOT MATCH:
     * - "# H1 Header"               → H1, not H2
     * - "### H3 Header"             → H3, not H2
     * - "##NoSpace"                 → Missing space after ##
     * - "  ## Indented"             → Leading whitespace
     * - "Some ## inline"            → Not at line start
     */
    public const REGEX_H2_HEADER = '/^## (.+)$/m';
    
    /**
     * REGEX_H1_HEADER - Extract H1 header (for exam title fallback)
     * 
     * Pattern: /^# (.+)$/m
     * 
     * Same logic as H2 but with single #
     * Only first match is used (exam should have one H1)
     */
    public const REGEX_H1_HEADER = '/^# (.+)$/m';
    
    /**
     * REGEX_BOLD_MARKDOWN - Remove bold formatting from titles
     * 
     * Pattern: /\*\*(.+?)\*\*/
     * 
     * Explanation:
     * - \*\*   : Literal ** (escaped asterisks)
     * - (.+?)  : Non-greedy capture of content
     * - \*\*   : Closing **
     * 
     * Examples:
     * - "**Bold Text**"     → "Bold Text"
     * - "Some **bold** here" → "Some bold here"
     */
    public const REGEX_BOLD_MARKDOWN = '/\*\*(.+?)\*\*/';
    
    /**
     * REGEX_UNDERLINE_MARKDOWN - Remove underline formatting from titles
     * 
     * Pattern: /__(.+?)__/
     * 
     * Examples:
     * - "__Underlined__"    → "Underlined"
     */
    public const REGEX_UNDERLINE_MARKDOWN = '/__(.+?)__/';
    
    /**
     * REGEX_LINK_MARKDOWN - Extract link text from Markdown links
     * 
     * Pattern: /\[(.+?)\]\(.+?\)/
     * 
     * Explanation:
     * - \[     : Literal [
     * - (.+?)  : Non-greedy capture of link text
     * - \]     : Literal ]
     * - \(     : Literal (
     * - .+?    : Non-greedy match of URL
     * - \)     : Literal )
     * 
     * Examples:
     * - "[Click Here](https://example.com)" → "Click Here"
     * - "[Docs](/docs)"                     → "Docs"
     */
    public const REGEX_LINK_MARKDOWN = '/\[(.+?)\]\(.+?\)/';
    
    /**
     * REGEX_WIKI_LINK - Extract wiki-style links
     * 
     * Pattern: /\[\[(.+?)\]\]/
     * 
     * Examples:
     * - "[[Page Name]]"        → "Page Name"
     * - "[[Category/Article]]" → "Category/Article"
     */
    public const REGEX_WIKI_LINK = '/\[\[(.+?)\]\]/';
    
    /**
     * REGEX_CODE_BLOCK - Match fenced code blocks (to skip during extraction)
     * 
     * Pattern: /```[\s\S]*?```/
     * 
     * Purpose: Identify code blocks so H2-like content inside isn't extracted
     */
    public const REGEX_CODE_BLOCK = '/```[\s\S]*?```/';
    
    /**
     * REGEX_SLUG_UNSAFE_CHARS - Characters to remove for URL-safe slugs
     * 
     * Pattern: /[^a-z0-9\-]/
     */
    public const REGEX_SLUG_UNSAFE_CHARS = '/[^a-z0-9\-]/';
    
    /**
     * REGEX_MULTIPLE_DASHES - Collapse multiple dashes in slugs
     * 
     * Pattern: /-+/
     */
    public const REGEX_MULTIPLE_DASHES = '/-+/';
}
```

### Extraction Algorithm

---

## ⚠️ IMPLEMENTATION WARNING - MEDIUM RISK AREA: H2 Code Block Detection

> **AI IMPLEMENTATION ALERT**: H2 headers inside code blocks must NOT be extracted as sections.
> 
> **THE WRONG WAY**:
> ```php
> // ❌ WRONG: Matches ALL H2 patterns including those in code blocks
> preg_match_all('/^## (.+)$/m', $markdown, $matches);
> // This will extract "## Fake Section" from inside ```code blocks```!
> ```
> 
> **THE CORRECT WAY**:
> ```php
> // ✅ CORRECT: Two-step approach
> // Step 1: Match H2 with position tracking
> preg_match_all(Consts::REGEX_H2_HEADER, $markdown, $matches, PREG_OFFSET_CAPTURE);
> 
> // Step 2: For each match, check if it's inside a code block
> foreach ($matches[1] as $match) {
>     $offset = $match[1];
>     if (isInsideCodeBlock($markdown, $offset)) {
>         continue; // Skip this false positive
>     }
>     // Process real section...
> }
> ```
> 
> **WHY THIS MATTERS**: False positives create phantom checklist items that users cannot complete.

---

### Pre-Implementation Checklist (MANDATORY)

Before implementing H2 extraction, verify:

- [ ] ✅ You use `PREG_OFFSET_CAPTURE` to get byte positions
- [ ] ✅ You check `isInsideCodeBlock()` for EACH match
- [ ] ✅ Section numbers are 1-indexed (start at 1, not 0)
- [ ] ✅ Empty titles (after normalization) are skipped
- [ ] ✅ You update `exam.sectionCount` after extraction
- [ ] ❌ You do NOT simply regex match without code block detection
- [ ] ❌ You do NOT use 0-indexed section numbers

---

```pseudocode
function extractSections(markdownContent: string): Section[]
    // ============================================================
    // CRITICAL: Must detect and skip H2 inside code blocks
    // CRITICAL: Section numbers are 1-indexed (NOT 0-indexed)
    // ============================================================
    
    // Step 1: Find all H2 matches WITH position tracking
    matches = []
    preg_match_all(Consts::REGEX_H2_HEADER, markdownContent, matches, PREG_OFFSET_CAPTURE)
    
    // Step 2: Process each match
    sections = []
    sectionNumber = 1  // ← MUST be 1-indexed (NOT 0-indexed!)
    
    FOR EACH match IN matches[1]:  // matches[1] contains captured groups
        rawTitle = match[0]        // The captured title text
        byteOffset = match[1]      // Position in string
        
        // Calculate line number from byte offset
        lineNumber = countLineBreaks(markdownContent, 0, byteOffset) + 1
        
        // ============================================================
        // CRITICAL CHECK: Skip if inside a code block
        // ============================================================
        IF isInsideCodeBlock(markdownContent, byteOffset):
            CONTINUE  // Skip this match - it's a false positive
        
        // Skip empty titles
        IF trim(rawTitle) = '':
            CONTINUE
        
        // Normalize title (remove formatting)
        normalizedTitle = normalizeTitle(rawTitle)
        
        // Skip if normalized title is empty
        IF normalizedTitle = '':
            CONTINUE
        
        // Create section object
        section = {
            sectionNumber: sectionNumber,      // 1-indexed integer
            rawTitle: rawTitle,                // Original with formatting
            normalizedTitle: normalizedTitle,  // Cleaned for display
            lineNumber: lineNumber,            // Line in source file
            byteOffset: byteOffset,            // For precise location
            slug: slugify(normalizedTitle)     // URL-safe identifier
        }
        
        sections.push(section)
        sectionNumber++  // Increment for next section
    
    RETURN sections
```

### Title Normalization Algorithm

```pseudocode
function normalizeTitle(rawTitle: string): string
    normalized = rawTitle
    
    // Step 1: Remove bold formatting: **text** → text
    normalized = preg_replace(Consts::REGEX_BOLD_MARKDOWN, '$1', normalized)
    
    // Step 2: Remove underline formatting: __text__ → text
    normalized = preg_replace(Consts::REGEX_UNDERLINE_MARKDOWN, '$1', normalized)
    
    // Step 3: Extract link text: [text](url) → text
    normalized = preg_replace(Consts::REGEX_LINK_MARKDOWN, '$1', normalized)
    
    // Step 4: Extract wiki link text: [[Page]] → Page
    normalized = preg_replace(Consts::REGEX_WIKI_LINK, '$1', normalized)
    
    // Step 5: Trim whitespace
    normalized = trim(normalized)
    
    RETURN normalized
```

### Slug Generation Algorithm

```pseudocode
function slugify(title: string): string
    slug = title
    
    // Step 1: Convert to lowercase
    slug = strtolower(slug)
    
    // Step 2: Replace spaces with dashes
    slug = str_replace(' ', '-', slug)
    
    // Step 3: Remove unsafe characters
    slug = preg_replace(Consts::REGEX_SLUG_UNSAFE_CHARS, '', slug)
    
    // Step 4: Collapse multiple dashes
    slug = preg_replace(Consts::REGEX_MULTIPLE_DASHES, '-', slug)
    
    // Step 5: Trim leading/trailing dashes
    slug = trim(slug, '-')
    
    // Step 6: Limit length
    IF strlen(slug) > 50:
        slug = substr(slug, 0, 50)
        slug = rtrim(slug, '-')  // Don't end with dash
    
    RETURN slug
```

### Code Block Detection

```pseudocode
function isInsideCodeBlock(content: string, offset: int): bool
    // Find all code block ranges
    matches = []
    preg_match_all(Consts::REGEX_CODE_BLOCK, content, matches, PREG_OFFSET_CAPTURE)
    
    FOR EACH match IN matches[0]:
        blockStart = match[1]
        blockEnd = blockStart + strlen(match[0])
        
        IF offset >= blockStart AND offset < blockEnd:
            RETURN true
    
    RETURN false
```

### Mapping to Checklist Items

```pseudocode
function createChecklistFromSections(examId: int, sections: Section[]): void
    // Clear existing auto-generated checklist items for this exam
    db.delete(
        "DELETE FROM checklist_items 
         WHERE examId = ? 
         AND phase = 'IN_EXAM' 
         AND isAutoGenerated = true",
        [examId]
    )
    
    FOR EACH section IN sections:
        checklistItem = {
            examId: examId,
            phase: ChecklistPhase::IN_EXAM,
            type: ChecklistItemType::SECTION_CHECKPOINT,
            title: section.normalizedTitle,
            sortOrder: section.sectionNumber,
            isAutoGenerated: true,  // Flag for regeneration
            isRequired: true,       // Sections count toward completion
            metadata: json_encode({
                sectionNumber: section.sectionNumber,
                lineNumber: section.lineNumber,
                byteOffset: section.byteOffset,
                slug: section.slug,
                rawTitle: section.rawTitle
            })
        }
        
        db.insert('checklist_items', checklistItem)
    
    // Update exam with section count
    db.update('exams', examId, { sectionCount: count(sections) })
```

### Section Number Mapping for API

```pseudocode
// REST Endpoint: POST /api/participants/{id}/sections/{sectionNumber}/complete

function markSectionComplete(participantId: int, sectionNumber: int): Response
    // Find checklist item by section number
    item = db.query(
        "SELECT * FROM checklist_items 
         WHERE examId = (SELECT examId FROM participants WHERE id = ?)
         AND phase = 'IN_EXAM'
         AND JSON_EXTRACT(metadata, '$.sectionNumber') = ?",
        [participantId, sectionNumber]
    )
    
    IF item IS NULL:
        THROW NotFoundException("Section {$sectionNumber} not found")
    
    // Mark as complete
    markChecklistItemComplete(participantId, item.id)
    
    RETURN { success: true, sectionNumber: sectionNumber }
```

### Edge Cases

| Case | Handling | Example |
|------|----------|---------|
| Empty H2 (`## `) | Skip, do not create section | `## ` → skipped |
| H2 with only formatting | Skip after normalization | `## **  **` → skipped |
| Duplicate H2 titles | Both created, unique `sectionNumber` | Two `## Setup` → sections 1 and 2 |
| H2 inside code block | Skipped by code block detection | ` ```\n## Not a section\n``` ` |
| H2 with special chars | Preserved in `rawTitle`, cleaned in `normalizedTitle` | `## Step 1: "Setup"` → `Step 1: Setup` |
| Very long title | Slug truncated to 50 chars | 100-char title → 50-char slug |
| H2 with wiki link | Link text extracted | `## See [[Guide]]` → `See Guide` |
| H2 with inline code | Backticks preserved in raw | `## Using \`npm\`` → raw preserved |
| Nested bold/links | Processed in order | `## **[Bold Link](url)**` → `Bold Link` |

### Test Cases

```php
// Test Case 1: Basic H2 extraction
$content = "# Exam Title\n\n## Introduction\n\nContent here.\n\n## Getting Started\n\nMore content.";
$sections = extractSections($content);
assert(count($sections) === 2);
assert($sections[0]['normalizedTitle'] === 'Introduction');
assert($sections[0]['sectionNumber'] === 1);
assert($sections[1]['normalizedTitle'] === 'Getting Started');
assert($sections[1]['sectionNumber'] === 2);

// Test Case 2: H2 with formatting
$content = "## **Bold Section**\n\n## __Underlined__\n\n## [Link Text](url)";
$sections = extractSections($content);
assert($sections[0]['normalizedTitle'] === 'Bold Section');
assert($sections[1]['normalizedTitle'] === 'Underlined');
assert($sections[2]['normalizedTitle'] === 'Link Text');

// Test Case 3: H2 inside code block (should be skipped)
$content = "## Real Section\n\n```\n## Fake Section in Code\n```\n\n## Another Real Section";
$sections = extractSections($content);
assert(count($sections) === 2);
assert($sections[0]['normalizedTitle'] === 'Real Section');
assert($sections[1]['normalizedTitle'] === 'Another Real Section');

// Test Case 4: Empty and whitespace-only H2 (should be skipped)
$content = "## Valid Section\n\n## \n\n##    \n\n## Another Valid";
$sections = extractSections($content);
assert(count($sections) === 2);

// Test Case 5: Duplicate titles
$content = "## Setup\n\nFirst setup.\n\n## Configuration\n\n## Setup\n\nSecond setup.";
$sections = extractSections($content);
assert(count($sections) === 3);
assert($sections[0]['sectionNumber'] === 1);
assert($sections[2]['sectionNumber'] === 3);
assert($sections[0]['slug'] === 'setup');
assert($sections[2]['slug'] === 'setup');  // Same slug, different sectionNumber
```

### Acceptance Criteria:
- [ ] All regex patterns defined in `Consts.php` with documentation
- [ ] Section numbering is 1-indexed (starts at 1)
- [ ] H2 inside code blocks are skipped
- [ ] Empty H2 headers are skipped
- [ ] Formatting (bold, links) removed from normalized titles
- [ ] Slug generated for URL-safe section identification
- [ ] Line number and byte offset preserved for source mapping
- [ ] Existing auto-generated items cleared before regeneration
- [ ] Exam `sectionCount` field updated after extraction
- [ ] All test cases pass

---

## 10.7 Content File Management

### Storage Location
`{EQM_UPLOADS_DIR}/questions/{exam_id}/`

### File Structure
- `content.md` - Main exam content
- `content.backup.md` - Previous version
- `images/` - Uploaded images subfolder

### Acceptance Criteria:
- [ ] Directory created on first upload
- [ ] Files named consistently
- [ ] Backup created on update
- [ ] Old backups cleaned up (keep 5)
- [ ] .htaccess prevents direct access

---

## 10.8 Status Transitions

### Valid Transitions
| From | To | Condition |
|------|-----|-----------|
| DRAFT | PUBLISHED | Content exists |
| PUBLISHED | DRAFT | No active participants |
| PUBLISHED | ARCHIVED | Admin only |
| ARCHIVED | DRAFT | Admin only |

### Acceptance Criteria:
- [ ] Invalid transitions throw exception
- [ ] Conditions checked before transition
- [ ] Status change triggers notifications
- [ ] Transition logged with actor

---

## Related Specifications

| Topic | Spec | Relationship |
|-------|------|--------------|
| **Exam Hierarchy** | [13-exam-hierarchy](13-exam-hierarchy.md) | Parent/child exam relationships |
| **Exam Editor UI** | [14-exam-editor-ui](14-exam-editor-ui.md) | Admin interface for exam CRUD |
| **Content Tab** | [15-exam-content-tab](15-exam-content-tab.md) | Markdown editor with H2 extraction preview |
| **Metadata Tab** | [16-exam-metadata-tab](16-exam-metadata-tab.md) | Slug, visibility, deadline settings |
| **Sub-Exams Tab** | [17-exam-subexams-tab](17-exam-subexams-tab.md) | Child exam management |
| **Prerequisites Tab** | [18-exam-prerequisites-tab](18-exam-prerequisites-tab.md) | Pre-exam requirements |
| **Checklists Tab** | [19-exam-checklists-tab](19-exam-checklists-tab.md) | Phase-based checklist items |
| **Progress Tracking** | [28-participant-progress](28-participant-progress.md) | Uses H2 sections for progress calculation |
| **Database Schema** | [04-database-schema](04-database-schema.md) | `exam` table definition |
| **Enums** | [06-enums-constants](06-enums-constants.md) | Regex patterns in `Consts.php` |
| **Audit Logging** | [46-audit-logging](46-audit-logging.md) | Exam CRUD audit events |

### Key Algorithm References
- **H2 Section Extraction**: Section 10.6 of this spec
- **Slug Generation**: Section 10.6 `slugify()` pseudocode
- **Regex Patterns**: Defined in `06-enums-constants.md` → `Consts.php`

---

*Next: `13-exam-hierarchy.md`*
