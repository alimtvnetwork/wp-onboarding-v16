# 13. Exam Content Tab

## Overview
First tab in exam editor for managing the main exam content via Markdown.

---

## 13.1 Content Upload

### Upload Methods
- Drag-and-drop Markdown file
- File picker button
- Paste from clipboard
- URL import (fetch remote file)

### Supported Formats
- `.md` - Markdown
- `.txt` - Plain text (converted)
- `.html` - HTML (converted to Markdown)

### Acceptance Criteria:
- [ ] Drag zone clearly indicated
- [ ] File type validation before upload
- [ ] Large files show progress
- [ ] Conversion preserves formatting
- [ ] Upload replaces existing content (with backup)

---

## 13.2 Markdown Editor

### Editor Features
- Syntax highlighting
- Line numbers
- Code folding
- Search and replace
- Split view (edit/preview)

### Toolbar Actions
- Bold, Italic, Strikethrough
- Headings (H1-H6)
- Lists (ordered, unordered, task)
- Links, Images
- Code blocks
- Tables
- Wiki links

### Acceptance Criteria:
- [ ] Real-time preview updates
- [ ] Syntax highlighting accurate
- [ ] Toolbar inserts correct syntax
- [ ] Undo/redo works properly
- [ ] Large documents perform well

---

## 13.3 Image Management

### Image Upload
- Drag-drop into editor
- Paste from clipboard
- Insert via toolbar button
- Upload to exam's image folder

### Image Display
- Thumbnail gallery of uploaded images
- Click to insert at cursor
- Delete unused images
- Image optimization on upload

### Acceptance Criteria:
- [ ] Images stored in exam's folder
- [ ] Markdown syntax inserted correctly
- [ ] Gallery shows all exam images
- [ ] Delete confirms before removing
- [ ] Images resized for web

---

## 13.4 Structure Extraction

### Auto-Extracted Elements
- H1 heading (exam title suggestion)
- H2 headings (section list)
- Code blocks (language detection)
- Tables (count)
- Links (validation)

### Structure Panel
- Clickable table of contents
- Section word counts
- Reading time estimate
- Structure warnings

### Acceptance Criteria:
- [ ] TOC updates on content change
- [ ] Click jumps to section
- [ ] Invalid links highlighted
- [ ] Word count accurate
- [ ] Warnings are actionable

---

## 13.5 Wiki Link Integration

### Wiki Link Syntax
`[[Wiki Page Name]]` or `[[Page Name|Display Text]]`

### Linking Features
- Autocomplete wiki page names
- Create new page from link
- Broken link detection
- Backlink tracking

### Acceptance Criteria:
- [ ] Autocomplete shows matching pages
- [ ] Broken links highlighted
- [ ] Click creates new page if missing
- [ ] Preview shows linked page title
- [ ] Backlinks listed in wiki page

---

## 13.6 Content Validation

### Validation Rules
- No empty content for published exams
- At least one H2 section
- All images accessible
- All wiki links valid (warning)
- No malicious HTML

### Validation Display
- Errors block publish
- Warnings allow publish
- Fix suggestions provided
- Jump to issue location

### Acceptance Criteria:
- [ ] Validation runs on save
- [ ] Errors clearly distinguished
- [ ] Suggestions are specific
- [ ] Quick-fix actions where possible
- [ ] Validation can be re-run manually

---

## 13.7 Export Options

### Export Formats
- Markdown (original)
- HTML (rendered)
- PDF (formatted)
- DOCX (for editing)

### Export Settings
- Include/exclude images
- Table of contents option
- Custom header/footer
- Styling options

### Acceptance Criteria:
- [ ] All formats download correctly
- [ ] Images embedded or linked
- [ ] PDF respects page breaks
- [ ] Export includes metadata
