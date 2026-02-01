# Memory: ui/project-editor

**Updated:** 2026-01-29  
**Spec Location:** `spec/spec-management-software/05-features/`

---

## Overview

Multi-tab VS Code-style project editor with integrated AI capabilities.

---

## Layout

| Component | Description |
|-----------|-------------|
| File Tree | Hierarchical project navigation |
| Editor Tabs | Multi-file editing |
| AI Panel | Voice, Upload, Text input modes |
| Split Preview | Live Markdown rendering |

---

## Editor Features

| Feature | Implementation |
|---------|----------------|
| JSON Editing | Monaco Editor |
| Markdown Editing | CodeMirror 6 |
| Auto-save | 30-second periodic to `.temp` files |
| Split View | Side-by-side preview |

---

## AI Chat Modes

| Mode | Context | Output |
|------|---------|--------|
| Spec Mode | Markdown files | Specifications |
| Coding Mode | Source code | Implementation |

---

## Validation Controls

- **Validate Spec:** Trigger consistency check
- **Loop Build Verify:** Automated build validation
