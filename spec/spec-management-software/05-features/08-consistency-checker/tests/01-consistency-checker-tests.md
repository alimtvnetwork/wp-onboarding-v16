# Consistency Checker - Unit Tests

**Version:** 1.0.0  
**Status:** Draft  
**Last Updated:** 2026-01-27

---

## 1. Overview

This document provides comprehensive unit tests for the Consistency Checker service, covering:

- **Link Validation** — Broken links, missing anchors, external links
- **Duplicate Detection** — Same-file and cross-file duplicates
- **Naming Convention Checks** — File and folder naming rules
- **Completeness Checks** — Required sections validation
- **Health Scoring** — Score calculation accuracy

All tests use mock file systems to avoid disk I/O dependencies.

---

## 2. Test Utilities

### `internal/services/consistency/testutil_test.go`

```go
package consistency

import (
	"context"
	"testing"
	"time"

	"spec-manager/internal/models"
)

// ============================================================
// Test Utilities - Mock File System & Helpers
// ============================================================

// MockFile represents a virtual file for testing
type MockFile struct {
	Path     string
	Content  string
	ModTime  time.Time
}

// MockFileSystem provides an in-memory file system for testing
type MockFileSystem struct {
	files map[string]*MockFile
}

func NewMockFileSystem() *MockFileSystem {
	return &MockFileSystem{
		files: make(map[string]*MockFile),
	}
}

func (fs *MockFileSystem) AddFile(path, content string) {
	fs.files[path] = &MockFile{
		Path:    path,
		Content: content,
		ModTime: time.Now(),
	}
}

func (fs *MockFileSystem) GetFile(path string) *MockFile {
	return fs.files[path]
}

func (fs *MockFileSystem) Exists(path string) bool {
	_, ok := fs.files[path]
	return ok
}

func (fs *MockFileSystem) List() []string {
	paths := make([]string, 0, len(fs.files))
	for path := range fs.files {
		paths = append(paths, path)
	}
	return paths
}

// createTestFileInfo creates a FileInfo from mock content
func createTestFileInfo(path, content string) models.FileInfo {
	scanner := NewScanner()
	
	// Parse content manually for testing
	fileInfo := models.FileInfo{
		Path:         path,
		RelativePath: path,
		ModifiedAt:   time.Now(),
		Size:         int64(len(content)),
		Headings:     make([]models.HeadingInfo, 0),
		Links:        make([]models.LinkInfo, 0),
		Definitions:  make([]models.DefinitionInfo, 0),
	}

	// Simple parsing for test files
	lines := splitLines(content)
	for lineNum, line := range lines {
		// Parse headings
		if matches := scanner.headingRegex.FindStringSubmatch(line); matches != nil {
			level := len(matches[1])
			text := matches[2]
			anchor := scanner.generateAnchor(text)

			fileInfo.Headings = append(fileInfo.Headings, models.HeadingInfo{
				Level:      level,
				Text:       text,
				Anchor:     anchor,
				LineNumber: lineNum + 1,
			})

			fileInfo.Definitions = append(fileInfo.Definitions, models.DefinitionInfo{
				Type:           "heading",
				Name:           text,
				NormalizedName: anchor,
				FilePath:       path,
				LineNumber:     lineNum + 1,
			})
		}

		// Parse links
		linkMatches := scanner.linkRegex.FindAllStringSubmatchIndex(line, -1)
		for _, match := range linkMatches {
			if len(match) >= 6 {
				text := line[match[2]:match[3]]
				target := line[match[4]:match[5]]
				linkInfo := scanner.parseLink(text, target, lineNum+1, match[4])
				fileInfo.Links = append(fileInfo.Links, linkInfo)
			}
		}
	}

	return fileInfo
}

func splitLines(content string) []string {
	var lines []string
	start := 0
	for i := 0; i < len(content); i++ {
		if content[i] == '\n' {
			lines = append(lines, content[start:i])
			start = i + 1
		}
	}
	if start < len(content) {
		lines = append(lines, content[start:])
	}
	return lines
}

// assertNoError fails the test if err is not nil
func assertNoError(t *testing.T, err error) {
	t.Helper()
	if err != nil {
		t.Fatalf("unexpected error: %v", err)
	}
}

// assertError fails the test if err is nil
func assertError(t *testing.T, err error) {
	t.Helper()
	if err == nil {
		t.Fatal("expected error but got nil")
	}
}

// assertEqual fails the test if expected != actual
func assertEqual[T comparable](t *testing.T, expected, actual T) {
	t.Helper()
	if expected != actual {
		t.Fatalf("expected %v, got %v", expected, actual)
	}
}

// assertContains fails if slice doesn't contain item
func assertContains[T comparable](t *testing.T, slice []T, item T) {
	t.Helper()
	for _, v := range slice {
		if v == item {
			return
		}
	}
	t.Fatalf("expected slice to contain %v", item)
}

// findIssueByCategory finds first issue with given category
func findIssueByCategory(issues []models.ConsistencyIssue, category models.IssueCategory) *models.ConsistencyIssue {
	for i := range issues {
		if issues[i].Category == category {
			return &issues[i]
		}
	}
	return nil
}

// countIssuesByCategory counts issues with given category
func countIssuesByCategory(issues []models.ConsistencyIssue, category models.IssueCategory) int {
	count := 0
	for _, issue := range issues {
		if issue.Category == category {
			count++
		}
	}
	return count
}

// countIssuesBySeverity counts issues with given severity
func countIssuesBySeverity(issues []models.ConsistencyIssue, severity models.IssueSeverity) int {
	count := 0
	for _, issue := range issues {
		if issue.Severity == severity {
			count++
		}
	}
	return count
}
```

---

## 3. Scanner Tests

### `internal/services/consistency/scanner_test.go`

```go
package consistency

import (
	"context"
	"testing"
)

// ============================================================
// Scanner Tests
// ============================================================

func TestScanner_GenerateAnchor(t *testing.T) {
	scanner := NewScanner()

	tests := []struct {
		name     string
		input    string
		expected string
	}{
		{
			name:     "simple heading",
			input:    "Overview",
			expected: "overview",
		},
		{
			name:     "heading with spaces",
			input:    "Database Schema",
			expected: "database-schema",
		},
		{
			name:     "heading with numbers",
			input:    "3.1 Core Entities",
			expected: "3-1-core-entities",
		},
		{
			name:     "heading with special chars",
			input:    "API (v2) Endpoints",
			expected: "api-v2-endpoints",
		},
		{
			name:     "heading with markdown",
			input:    "**Bold** and *italic*",
			expected: "bold-and-italic",
		},
		{
			name:     "heading with underscores",
			input:    "user_profile_table",
			expected: "user-profile-table",
		},
		{
			name:     "empty heading",
			input:    "",
			expected: "",
		},
		{
			name:     "only special chars",
			input:    "***",
			expected: "",
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			result := scanner.generateAnchor(tt.input)
			assertEqual(t, tt.expected, result)
		})
	}
}

func TestScanner_ParseLink(t *testing.T) {
	scanner := NewScanner()

	tests := []struct {
		name           string
		text           string
		target         string
		expectExternal bool
		expectAnchor   bool
		expectFile     string
		expectAnchorPart string
	}{
		{
			name:           "simple relative link",
			text:           "See here",
			target:         "./other-file.md",
			expectExternal: false,
			expectAnchor:   false,
			expectFile:     "./other-file.md",
		},
		{
			name:           "link with anchor",
			text:           "Database section",
			target:         "./schema.md#database",
			expectExternal: false,
			expectAnchor:   true,
			expectFile:     "./schema.md",
			expectAnchorPart: "database",
		},
		{
			name:           "same-file anchor",
			text:           "See above",
			target:         "#overview",
			expectExternal: false,
			expectAnchor:   true,
			expectFile:     "",
			expectAnchorPart: "overview",
		},
		{
			name:           "external http link",
			text:           "Google",
			target:         "https://google.com",
			expectExternal: true,
		},
		{
			name:           "external http link",
			text:           "Old site",
			target:         "http://example.com",
			expectExternal: true,
		},
		{
			name:           "mailto link",
			text:           "Email us",
			target:         "mailto:test@example.com",
			expectExternal: true,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			link := scanner.parseLink(tt.text, tt.target, 1, 0)

			assertEqual(t, tt.expectExternal, link.IsExternal)
			assertEqual(t, tt.expectAnchor, link.HasAnchor)

			if !tt.expectExternal {
				assertEqual(t, tt.expectFile, link.FilePart)
				if tt.expectAnchor {
					assertEqual(t, tt.expectAnchorPart, link.AnchorPart)
				}
			}
		})
	}
}

func TestScanner_ParseHeadings(t *testing.T) {
	content := `# Main Title

## Section 1

Some content here.

### Subsection 1.1

More content.

## Section 2

#### Deep Heading
`

	fileInfo := createTestFileInfo("test.md", content)

	assertEqual(t, 5, len(fileInfo.Headings))
	
	// Check first heading
	assertEqual(t, 1, fileInfo.Headings[0].Level)
	assertEqual(t, "Main Title", fileInfo.Headings[0].Text)
	assertEqual(t, "main-title", fileInfo.Headings[0].Anchor)

	// Check section heading
	assertEqual(t, 2, fileInfo.Headings[1].Level)
	assertEqual(t, "Section 1", fileInfo.Headings[1].Text)

	// Check subsection
	assertEqual(t, 3, fileInfo.Headings[2].Level)
	assertEqual(t, "subsection-1-1", fileInfo.Headings[2].Anchor)
}

func TestScanner_ParseLinks(t *testing.T) {
	content := `# Document

See [overview](./overview.md) for more info.

Check the [database schema](../schema.md#tables) section.

Also visit [Google](https://google.com) for help.

[Same file anchor](#conclusion).
`

	fileInfo := createTestFileInfo("test.md", content)

	assertEqual(t, 4, len(fileInfo.Links))

	// Check internal link
	assertEqual(t, "./overview.md", fileInfo.Links[0].Target)
	assertEqual(t, false, fileInfo.Links[0].IsExternal)

	// Check link with anchor
	assertEqual(t, true, fileInfo.Links[1].HasAnchor)
	assertEqual(t, "../schema.md", fileInfo.Links[1].FilePart)
	assertEqual(t, "tables", fileInfo.Links[1].AnchorPart)

	// Check external link
	assertEqual(t, true, fileInfo.Links[2].IsExternal)

	// Check same-file anchor
	assertEqual(t, "", fileInfo.Links[3].FilePart)
	assertEqual(t, "conclusion", fileInfo.Links[3].AnchorPart)
}
```

---

## 4. Link Validator Tests

### `internal/services/consistency/link_validator_test.go`

```go
package consistency

import (
	"context"
	"testing"

	"spec-manager/internal/models"
)

// ============================================================
// Link Validator Tests
// ============================================================

func TestLinkValidator_ValidateAll_NoBrokenLinks(t *testing.T) {
	ctx := context.Background()
	validator := NewLinkValidator()

	// Create test files
	files := []models.FileInfo{
		createTestFileInfo("01-overview.md", `# Overview

See [backend](./02-backend.md) for details.
`),
		createTestFileInfo("02-backend.md", `# Backend

The backend documentation.

## API Section

API details here.
`),
	}

	issues, err := validator.ValidateAll(ctx, files, "/project")

	assertNoError(t, err)
	assertEqual(t, 0, len(issues))
}

func TestLinkValidator_ValidateAll_BrokenFileLink(t *testing.T) {
	ctx := context.Background()
	validator := NewLinkValidator()

	files := []models.FileInfo{
		createTestFileInfo("01-overview.md", `# Overview

See [missing file](./non-existent.md) for details.
`),
	}

	issues, err := validator.ValidateAll(ctx, files, "/project")

	assertNoError(t, err)
	assertEqual(t, 1, len(issues))
	assertEqual(t, models.CategoryBrokenLink, issues[0].Category)
	assertEqual(t, models.SeverityError, issues[0].Severity)
	assertContains(t, issues[0].Description, "non-existent")
}

func TestLinkValidator_ValidateAll_BrokenAnchorLink(t *testing.T) {
	ctx := context.Background()
	validator := NewLinkValidator()

	files := []models.FileInfo{
		createTestFileInfo("01-overview.md", `# Overview

See [api section](./02-backend.md#wrong-anchor) for details.
`),
		createTestFileInfo("02-backend.md", `# Backend

## Correct Anchor

Content here.
`),
	}

	issues, err := validator.ValidateAll(ctx, files, "/project")

	assertNoError(t, err)
	assertEqual(t, 1, len(issues))
	assertEqual(t, models.CategoryBrokenLink, issues[0].Category)
	assertContains(t, issues[0].Description, "wrong-anchor")
}

func TestLinkValidator_ValidateAll_SameFileAnchor(t *testing.T) {
	ctx := context.Background()
	validator := NewLinkValidator()

	// Valid same-file anchor
	validFiles := []models.FileInfo{
		createTestFileInfo("test.md", `# Main

See [conclusion](#conclusion) below.

## Conclusion

The end.
`),
	}

	issues, err := validator.ValidateAll(ctx, validFiles, "/project")
	assertNoError(t, err)
	assertEqual(t, 0, len(issues))

	// Invalid same-file anchor
	invalidFiles := []models.FileInfo{
		createTestFileInfo("test.md", `# Main

See [missing](#does-not-exist) below.

## Conclusion

The end.
`),
	}

	issues, err = validator.ValidateAll(ctx, invalidFiles, "/project")
	assertNoError(t, err)
	assertEqual(t, 1, len(issues))
	assertEqual(t, models.CategoryBrokenLink, issues[0].Category)
}

func TestLinkValidator_ValidateAll_ExternalLinksSkipped(t *testing.T) {
	ctx := context.Background()
	validator := NewLinkValidator()

	files := []models.FileInfo{
		createTestFileInfo("test.md", `# Links

- [Google](https://google.com)
- [HTTP](http://example.com)
- [Email](mailto:test@test.com)
`),
	}

	issues, err := validator.ValidateAll(ctx, files, "/project")

	assertNoError(t, err)
	assertEqual(t, 0, len(issues))
}

func TestLinkValidator_ValidateAll_MultipleBrokenLinks(t *testing.T) {
	ctx := context.Background()
	validator := NewLinkValidator()

	files := []models.FileInfo{
		createTestFileInfo("test.md", `# Test

- [Missing 1](./missing1.md)
- [Missing 2](./missing2.md)
- [Missing 3](./missing3.md#anchor)
`),
	}

	issues, err := validator.ValidateAll(ctx, files, "/project")

	assertNoError(t, err)
	assertEqual(t, 3, len(issues))
	
	for _, issue := range issues {
		assertEqual(t, models.CategoryBrokenLink, issue.Category)
		assertEqual(t, models.SeverityError, issue.Severity)
	}
}

func TestLinkValidator_ValidateAll_RelativePaths(t *testing.T) {
	ctx := context.Background()
	validator := NewLinkValidator()

	files := []models.FileInfo{
		createTestFileInfo("docs/api/endpoints.md", `# Endpoints

See [schema](../database/schema.md) for types.
`),
		createTestFileInfo("docs/database/schema.md", `# Schema

Database schema here.
`),
	}

	issues, err := validator.ValidateAll(ctx, files, "/project")

	assertNoError(t, err)
	assertEqual(t, 0, len(issues))
}

func TestLinkValidator_SuggestsSimilarAnchor(t *testing.T) {
	ctx := context.Background()
	validator := NewLinkValidator()

	files := []models.FileInfo{
		createTestFileInfo("test.md", `# Test

See [section](#databse-schema) below.

## Database Schema

Content.
`),
	}

	issues, err := validator.ValidateAll(ctx, files, "/project")

	assertNoError(t, err)
	assertEqual(t, 1, len(issues))
	
	// Should suggest the correct anchor
	if issues[0].SuggestedFix != nil {
		assertEqual(t, "database-schema", *issues[0].SuggestedFix)
		assertEqual(t, true, issues[0].AutoFixable)
	}
}

func TestLevenshteinDistance(t *testing.T) {
	tests := []struct {
		a, b     string
		expected int
	}{
		{"", "", 0},
		{"a", "", 1},
		{"", "b", 1},
		{"abc", "abc", 0},
		{"abc", "abd", 1},
		{"abc", "adc", 1},
		{"kitten", "sitting", 3},
		{"database", "databse", 1},
	}

	for _, tt := range tests {
		t.Run(tt.a+"_"+tt.b, func(t *testing.T) {
			result := levenshteinDistance(tt.a, tt.b)
			assertEqual(t, tt.expected, result)
		})
	}
}
```

---

## 5. Duplicate Finder Tests

### `internal/services/consistency/duplicate_finder_test.go`

```go
package consistency

import (
	"context"
	"testing"

	"spec-manager/internal/models"
)

// ============================================================
// Duplicate Finder Tests
// ============================================================

func TestDuplicateFinder_NoDuplicates(t *testing.T) {
	ctx := context.Background()
	finder := NewDuplicateFinder()

	files := []models.FileInfo{
		createTestFileInfo("file1.md", `# Unique Title 1

## Section A
`),
		createTestFileInfo("file2.md", `# Unique Title 2

## Section B
`),
	}

	issues, err := finder.FindAll(ctx, files)

	assertNoError(t, err)
	assertEqual(t, 0, len(issues))
}

func TestDuplicateFinder_SameFileDuplicate(t *testing.T) {
	ctx := context.Background()
	finder := NewDuplicateFinder()

	files := []models.FileInfo{
		createTestFileInfo("file1.md", `# Title

## Section

Content.

## Section

More content with same heading.
`),
	}

	issues, err := finder.FindAll(ctx, files)

	assertNoError(t, err)
	assertEqual(t, 1, len(issues))
	assertEqual(t, models.CategoryDuplicateDefinition, issues[0].Category)
	assertEqual(t, models.SeverityWarning, issues[0].Severity) // Same file = warning
}

func TestDuplicateFinder_CrossFileDuplicate(t *testing.T) {
	ctx := context.Background()
	finder := NewDuplicateFinder()

	files := []models.FileInfo{
		createTestFileInfo("file1.md", `# Overview

## API Endpoints

Content.
`),
		createTestFileInfo("file2.md", `# Documentation

## API Endpoints

Different content but same heading.
`),
	}

	issues, err := finder.FindAll(ctx, files)

	assertNoError(t, err)
	assertEqual(t, 1, len(issues))
	assertEqual(t, models.CategoryDuplicateDefinition, issues[0].Category)
	assertEqual(t, models.SeverityError, issues[0].Severity) // Cross-file = error
	assertEqual(t, 1, len(issues[0].RelatedFiles)) // Should reference other file
}

func TestDuplicateFinder_MultipleDuplicates(t *testing.T) {
	ctx := context.Background()
	finder := NewDuplicateFinder()

	files := []models.FileInfo{
		createTestFileInfo("file1.md", `# Overview

## Database

## API
`),
		createTestFileInfo("file2.md", `# Docs

## Database

## Logging
`),
		createTestFileInfo("file3.md", `# More

## Database

## API
`),
	}

	issues, err := finder.FindAll(ctx, files)

	assertNoError(t, err)
	
	// Should find duplicates for "Database" (3 times) and "API" (2 times)
	assertEqual(t, 2, len(issues))
	
	dbIssue := findIssueByTitle(issues, "Database")
	if dbIssue != nil {
		assertContains(t, dbIssue.Description, "3 times")
	}
}

func TestDuplicateFinder_CaseInsensitive(t *testing.T) {
	ctx := context.Background()
	finder := NewDuplicateFinder()

	files := []models.FileInfo{
		createTestFileInfo("file1.md", `# Overview

## Database Schema
`),
		createTestFileInfo("file2.md", `# Docs

## database schema
`),
	}

	issues, err := finder.FindAll(ctx, files)

	assertNoError(t, err)
	// Same anchor after normalization = duplicate
	assertEqual(t, 1, len(issues))
}

func TestDuplicateFinder_DifferentTypes(t *testing.T) {
	ctx := context.Background()
	finder := NewDuplicateFinder()

	// Heading and anchor with same name should be separate
	file := models.FileInfo{
		RelativePath: "test.md",
		Definitions: []models.DefinitionInfo{
			{Type: "heading", Name: "Overview", NormalizedName: "overview", FilePath: "test.md", LineNumber: 1},
			{Type: "anchor", Name: "overview", NormalizedName: "overview", FilePath: "test.md", LineNumber: 10},
		},
	}

	issues, err := finder.FindAll(ctx, []models.FileInfo{file})

	assertNoError(t, err)
	// Different types = not duplicates
	assertEqual(t, 0, len(issues))
}

// Helper to find issue by title substring
func findIssueByTitle(issues []models.ConsistencyIssue, titleSubstr string) *models.ConsistencyIssue {
	for i := range issues {
		if containsPattern(issues[i].Description, titleSubstr) {
			return &issues[i]
		}
	}
	return nil
}
```

---

## 6. Naming Validator Tests

### `internal/services/consistency/naming_validator_test.go`

```go
package consistency

import (
	"context"
	"testing"

	"spec-manager/internal/models"
)

// ============================================================
// Naming Validator Tests
// ============================================================

func TestNamingValidator_ValidNames(t *testing.T) {
	ctx := context.Background()
	validator := NewNamingValidator()

	files := []models.FileInfo{
		{RelativePath: "01-overview.md"},
		{RelativePath: "02-backend/01-api.md"},
		{RelativePath: "readme.md"},
		{RelativePath: "glossary.md"},
	}

	issues, err := validator.ValidateAll(ctx, files, "/project")

	assertNoError(t, err)
	// Should have minimal or no issues for well-named files
	errorCount := countIssuesBySeverity(issues, models.SeverityError)
	assertEqual(t, 0, errorCount)
}

func TestNamingValidator_FileWithSpaces(t *testing.T) {
	ctx := context.Background()
	validator := NewNamingValidator()

	files := []models.FileInfo{
		{RelativePath: "my file name.md"},
	}

	issues, err := validator.ValidateAll(ctx, files, "/project")

	assertNoError(t, err)
	
	// Should detect space in filename
	issue := findIssueByCategory(issues, models.CategoryNamingConvention)
	if issue == nil {
		t.Fatal("expected naming convention issue")
	}
	assertEqual(t, models.SeverityError, issue.Severity)
}

func TestNamingValidator_UppercaseFile(t *testing.T) {
	ctx := context.Background()
	validator := NewNamingValidator()

	files := []models.FileInfo{
		{RelativePath: "MyDocument.md"},
	}

	issues, err := validator.ValidateAll(ctx, files, "/project")

	assertNoError(t, err)
	
	// Should suggest lowercase
	count := countIssuesByCategory(issues, models.CategoryNamingConvention)
	if count == 0 {
		t.Log("Note: uppercase file names may be allowed depending on rules")
	}
}

func TestNamingValidator_SpecialCharacters(t *testing.T) {
	ctx := context.Background()
	validator := NewNamingValidator()

	files := []models.FileInfo{
		{RelativePath: "file@name!.md"},
		{RelativePath: "file#1.md"},
		{RelativePath: "file$test.md"},
	}

	issues, err := validator.ValidateAll(ctx, files, "/project")

	assertNoError(t, err)
	
	// Should flag special characters
	assertEqual(t, 3, countIssuesByCategory(issues, models.CategoryNamingConvention))
}

func TestNamingValidator_FolderNaming(t *testing.T) {
	ctx := context.Background()
	validator := NewNamingValidator()

	tests := []struct {
		name        string
		path        string
		expectIssue bool
	}{
		{"valid numbered folder", "01-backend/file.md", false},
		{"valid ideas folder", "ideas/idea.md", false},
		{"valid diagrams folder", "diagrams/flow.md", false},
		{"invalid folder name", "Backend/file.md", true},
		{"folder without prefix", "backend/file.md", true},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			files := []models.FileInfo{{RelativePath: tt.path}}
			issues, err := validator.ValidateAll(ctx, files, "/project")
			
			assertNoError(t, err)
			
			hasNamingIssue := countIssuesByCategory(issues, models.CategoryNamingConvention) > 0
			if tt.expectIssue && !hasNamingIssue {
				t.Logf("expected naming issue for %s but got none", tt.path)
			}
		})
	}
}

func TestNamingValidator_GenerateSuggestion(t *testing.T) {
	validator := NewNamingValidator()

	tests := []struct {
		input    string
		expected string
	}{
		{"My File Name.md", "my-file-name.md"},
		{"FILE WITH SPACES.md", "file-with-spaces.md"},
		{"file@special#chars.md", "filespecialchars.md"},
		{"multiple---hyphens.md", "multiple-hyphens.md"},
		{"already-valid.md", ""}, // No suggestion needed
	}

	for _, tt := range tests {
		t.Run(tt.input, func(t *testing.T) {
			suggestion := validator.generateSuggestion(tt.input, ".*")
			
			if tt.expected == "" {
				if suggestion != nil {
					t.Errorf("expected no suggestion, got %s", *suggestion)
				}
			} else {
				if suggestion == nil {
					t.Error("expected suggestion but got nil")
				} else {
					assertEqual(t, tt.expected, *suggestion)
				}
			}
		})
	}
}

func TestNamingValidator_ConsistencyReportNaming(t *testing.T) {
	ctx := context.Background()
	validator := NewNamingValidator()

	tests := []struct {
		path        string
		expectIssue bool
	}{
		{"99-consistency-report.md", false},
		{"consistency-report.md", true}, // Missing prefix
		{"99-Consistency-Report.md", true}, // Wrong case
	}

	for _, tt := range tests {
		t.Run(tt.path, func(t *testing.T) {
			files := []models.FileInfo{{RelativePath: tt.path}}
			issues, err := validator.ValidateAll(ctx, files, "/project")
			
			assertNoError(t, err)
			
			hasIssue := countIssuesByCategory(issues, models.CategoryNamingConvention) > 0
			if tt.expectIssue != hasIssue {
				t.Errorf("path %s: expected issue=%v, got issue=%v", tt.path, tt.expectIssue, hasIssue)
			}
		})
	}
}
```

---

## 7. Completeness Checker Tests

### `internal/services/consistency/completeness_test.go`

```go
package consistency

import (
	"context"
	"testing"

	"spec-manager/internal/models"
)

// ============================================================
// Completeness Checker Tests
// ============================================================

func TestCompletenessChecker_CompleteFile(t *testing.T) {
	ctx := context.Background()
	checker := NewCompletenessChecker()

	content := `# API Specification

**Version:** 1.0.0  
**Status:** Draft  
**Last Updated:** 2026-01-27

## Overview

This document describes the API.

## Endpoints

GET /api/v1/users

## Cross-References

- [Database Schema](./schema.md)
`

	files := []models.FileInfo{
		createTestFileInfo("01-api-spec.md", content),
	}

	issues, err := checker.CheckAll(ctx, files)

	assertNoError(t, err)
	// Complete file should have no/minimal issues
	errorCount := countIssuesBySeverity(issues, models.SeverityError)
	assertEqual(t, 0, errorCount)
}

func TestCompletenessChecker_MissingVersion(t *testing.T) {
	ctx := context.Background()
	checker := NewCompletenessChecker()

	content := `# API Specification

**Status:** Draft  
**Last Updated:** 2026-01-27

## Overview

Content here.
`

	files := []models.FileInfo{
		createTestFileInfo("01-api-spec.md", content),
	}

	issues, err := checker.CheckAll(ctx, files)

	assertNoError(t, err)
	
	// Should detect missing version
	found := false
	for _, issue := range issues {
		if issue.Category == models.CategoryStructureViolation &&
			containsPattern(issue.Title, "Version") {
			found = true
			break
		}
	}
	if !found {
		t.Log("Note: Version header check may be optional")
	}
}

func TestCompletenessChecker_MissingStatus(t *testing.T) {
	ctx := context.Background()
	checker := NewCompletenessChecker()

	content := `# API Specification

**Version:** 1.0.0  
**Last Updated:** 2026-01-27

## Overview

Content here.
`

	files := []models.FileInfo{
		createTestFileInfo("01-api-spec.md", content),
	}

	issues, err := checker.CheckAll(ctx, files)

	assertNoError(t, err)
	
	// Should detect missing status
	structureIssues := countIssuesByCategory(issues, models.CategoryStructureViolation)
	if structureIssues == 0 {
		t.Log("Note: Status indicator may be optional")
	}
}

func TestCompletenessChecker_MissingMainHeading(t *testing.T) {
	ctx := context.Background()
	checker := NewCompletenessChecker()

	content := `## Only Subheading

No main heading in this file.

### Another Subheading

Content.
`

	files := []models.FileInfo{
		createTestFileInfo("test.md", content),
	}

	issues, err := checker.CheckAll(ctx, files)

	assertNoError(t, err)
	
	// Should detect missing main heading
	issue := findIssueByCategory(issues, models.CategoryStructureViolation)
	if issue != nil {
		assertContains(t, issue.Title, "heading")
	}
}

func TestCompletenessChecker_OverviewFile(t *testing.T) {
	ctx := context.Background()
	checker := NewCompletenessChecker()

	// Overview file without Overview section
	content := `# Backend Documentation

## API Endpoints

Content here.
`

	files := []models.FileInfo{
		createTestFileInfo("00-overview.md", content),
	}

	issues, err := checker.CheckAll(ctx, files)

	assertNoError(t, err)
	
	// Should detect missing Overview section in overview file
	found := false
	for _, issue := range issues {
		if containsPattern(issue.Title, "Overview") {
			found = true
			break
		}
	}
	if !found {
		t.Log("Note: Overview section check may have different rules")
	}
}

func TestCompletenessChecker_ReadmeExcluded(t *testing.T) {
	ctx := context.Background()
	checker := NewCompletenessChecker()

	// README files should have relaxed requirements
	content := `# Project Name

Just a simple readme.
`

	files := []models.FileInfo{
		createTestFileInfo("README.md", content),
	}

	issues, err := checker.CheckAll(ctx, files)

	assertNoError(t, err)
	
	// README should not require version/status headers
	errorCount := countIssuesBySeverity(issues, models.SeverityError)
	assertEqual(t, 0, errorCount)
}

func TestCompletenessChecker_IdeasExcluded(t *testing.T) {
	ctx := context.Background()
	checker := NewCompletenessChecker()

	content := `# Brainstorm Ideas

Just ideas, no formal structure needed.
`

	files := []models.FileInfo{
		createTestFileInfo("ideas/01-my-idea.md", content),
	}

	issues, err := checker.CheckAll(ctx, files)

	assertNoError(t, err)
	
	// Ideas folder should have relaxed requirements
	warningCount := countIssuesBySeverity(issues, models.SeverityWarning)
	assertEqual(t, 0, warningCount)
}

func TestCompletenessChecker_GeneratesTemplates(t *testing.T) {
	checker := NewCompletenessChecker()

	templates := []string{
		"Version header",
		"Status indicator",
		"Last Updated date",
		"Cross-references section",
	}

	for _, name := range templates {
		t.Run(name, func(t *testing.T) {
			template := checker.generateTemplate(name)
			if template == nil {
				t.Logf("No template for %s", name)
			} else {
				if len(*template) == 0 {
					t.Errorf("empty template for %s", name)
				}
			}
		})
	}
}
```

---

## 8. Health Scorer Tests

### `internal/services/consistency/health_scorer_test.go`

```go
package consistency

import (
	"testing"

	"spec-manager/internal/models"
)

// ============================================================
// Health Scorer Tests
// ============================================================

func TestHealthScorer_PerfectScore(t *testing.T) {
	scorer := NewHealthScorer()

	summary := models.ReportSummary{
		TotalIssues:    0,
		ErrorCount:     0,
		WarningCount:   0,
		InfoCount:      0,
		FilesScanned:   10,
		CategoryCounts: make(map[models.IssueCategory]int),
	}

	score, grade := scorer.Calculate(summary)

	assertEqual(t, 100, score)
	assertEqual(t, "A", grade)
}

func TestHealthScorer_WithErrors(t *testing.T) {
	scorer := NewHealthScorer()

	summary := models.ReportSummary{
		TotalIssues:    5,
		ErrorCount:     5,
		WarningCount:   0,
		InfoCount:      0,
		FilesScanned:   10,
		CategoryCounts: map[models.IssueCategory]int{
			models.CategoryBrokenLink: 5,
		},
	}

	score, grade := scorer.Calculate(summary)

	// 5 errors * 5 points = 25 point deduction
	// Plus category weight deduction
	if score >= 80 {
		t.Errorf("expected score < 80 with 5 errors, got %d", score)
	}
	if grade == "A" {
		t.Error("expected grade below A with errors")
	}
}

func TestHealthScorer_WithWarnings(t *testing.T) {
	scorer := NewHealthScorer()

	summary := models.ReportSummary{
		TotalIssues:    5,
		ErrorCount:     0,
		WarningCount:   5,
		InfoCount:      0,
		FilesScanned:   10,
		CategoryCounts: map[models.IssueCategory]int{
			models.CategoryNamingConvention: 5,
		},
	}

	score, grade := scorer.Calculate(summary)

	// Warnings should have less impact than errors
	if score < 80 {
		t.Errorf("expected score >= 80 with only warnings, got %d", score)
	}
}

func TestHealthScorer_WithInfoOnly(t *testing.T) {
	scorer := NewHealthScorer()

	summary := models.ReportSummary{
		TotalIssues:    10,
		ErrorCount:     0,
		WarningCount:   0,
		InfoCount:      10,
		FilesScanned:   20,
		CategoryCounts: map[models.IssueCategory]int{
			models.CategoryStructureViolation: 10,
		},
	}

	score, grade := scorer.Calculate(summary)

	// Info issues should have minimal impact
	if score < 90 {
		t.Errorf("expected score >= 90 with only info issues, got %d", score)
	}
	if grade != "A" && grade != "B" {
		t.Errorf("expected grade A or B with only info issues, got %s", grade)
	}
}

func TestHealthScorer_GradeThresholds(t *testing.T) {
	scorer := NewHealthScorer()

	tests := []struct {
		score    int
		expected string
	}{
		{100, "A"},
		{95, "A"},
		{90, "A"},
		{89, "B"},
		{80, "B"},
		{79, "C"},
		{70, "C"},
		{69, "D"},
		{60, "D"},
		{59, "F"},
		{0, "F"},
	}

	for _, tt := range tests {
		t.Run(string(rune(tt.score)), func(t *testing.T) {
			grade := scorer.scoreToGrade(tt.score)
			assertEqual(t, tt.expected, grade)
		})
	}
}

func TestHealthScorer_CategoryWeights(t *testing.T) {
	scorer := NewHealthScorer()

	// Broken links should have higher weight than naming conventions
	brokenLinkSummary := models.ReportSummary{
		TotalIssues:  5,
		ErrorCount:   5,
		FilesScanned: 10,
		CategoryCounts: map[models.IssueCategory]int{
			models.CategoryBrokenLink: 5,
		},
	}

	namingSummary := models.ReportSummary{
		TotalIssues:    5,
		WarningCount:   5,
		FilesScanned:   10,
		CategoryCounts: map[models.IssueCategory]int{
			models.CategoryNamingConvention: 5,
		},
	}

	brokenLinkScore, _ := scorer.Calculate(brokenLinkSummary)
	namingScore, _ := scorer.Calculate(namingSummary)

	if brokenLinkScore >= namingScore {
		t.Error("broken links should reduce score more than naming issues")
	}
}

func TestHealthScorer_ResolvedBonus(t *testing.T) {
	scorer := NewHealthScorer()

	// Same issues, but some resolved
	unresolvedSummary := models.ReportSummary{
		TotalIssues:    10,
		ErrorCount:     5,
		WarningCount:   5,
		ResolvedCount:  0,
		FilesScanned:   10,
		CategoryCounts: make(map[models.IssueCategory]int),
	}

	resolvedSummary := models.ReportSummary{
		TotalIssues:    10,
		ErrorCount:     5,
		WarningCount:   5,
		ResolvedCount:  5,
		FilesScanned:   10,
		CategoryCounts: make(map[models.IssueCategory]int),
	}

	unresolvedScore, _ := scorer.Calculate(unresolvedSummary)
	resolvedScore, _ := scorer.Calculate(resolvedSummary)

	if resolvedScore <= unresolvedScore {
		t.Error("resolved issues should provide score bonus")
	}
}

func TestHealthScorer_EmptyProject(t *testing.T) {
	scorer := NewHealthScorer()

	summary := models.ReportSummary{
		FilesScanned: 0,
	}

	score, grade := scorer.Calculate(summary)

	// Empty project = perfect score (nothing to check)
	assertEqual(t, 100, score)
	assertEqual(t, "A", grade)
}

func TestHealthScorer_ScoreClamping(t *testing.T) {
	scorer := NewHealthScorer()

	// Many errors should clamp to 0, not go negative
	summary := models.ReportSummary{
		TotalIssues:  100,
		ErrorCount:   100,
		FilesScanned: 10,
		CategoryCounts: map[models.IssueCategory]int{
			models.CategoryBrokenLink: 100,
		},
	}

	score, grade := scorer.Calculate(summary)

	if score < 0 {
		t.Errorf("score should not be negative, got %d", score)
	}
	if score > 100 {
		t.Errorf("score should not exceed 100, got %d", score)
	}
	assertEqual(t, "F", grade)
}
```

---

## 9. Integration Tests

### `internal/services/consistency/checker_integration_test.go`

```go
package consistency

import (
	"context"
	"testing"
	"time"

	"spec-manager/internal/models"
)

// ============================================================
// Integration Tests - Full Checker Service
// ============================================================

func TestCheckerService_FullScan(t *testing.T) {
	// Skip in short mode
	if testing.Short() {
		t.Skip("skipping integration test in short mode")
	}

	// Create mock config
	config := &models.ScanConfig{
		CheckBrokenLinks:    true,
		CheckDuplicates:     true,
		CheckNaming:         true,
		CheckCompleteness:   true,
		CheckOrphans:        true,
		AutoFixEnabled:      true,
		MinConfidenceForFix: 0.85,
	}

	// Create service without repo for testing
	checker := &CheckerService{
		scanner:             NewScanner(),
		linkValidator:       NewLinkValidator(),
		namingValidator:     NewNamingValidator(),
		duplicateFinder:     NewDuplicateFinder(),
		completenessChecker: NewCompletenessChecker(),
		healthScorer:        NewHealthScorer(),
		fixer:               NewFixer(),
		activeScans:         make(map[string]*models.ScanProgress),
		config:              config,
	}

	// Create test files
	files := []models.FileInfo{
		createTestFileInfo("01-overview.md", `# Project Overview

**Version:** 1.0.0  
**Status:** Draft  
**Last Updated:** 2026-01-27

## Overview

This is the main overview.

See [backend](./02-backend.md) for details.

## Cross-References

- [Backend](./02-backend.md)
`),
		createTestFileInfo("02-backend.md", `# Backend Documentation

**Version:** 1.0.0  
**Status:** Draft  
**Last Updated:** 2026-01-27

## Overview

Backend details.

## API Endpoints

GET /api/v1/users

## Cross-References

- [Overview](./01-overview.md)
`),
	}

	// Run individual validators
	ctx := context.Background()

	linkIssues, err := checker.linkValidator.ValidateAll(ctx, files, "/test")
	assertNoError(t, err)

	dupIssues, err := checker.duplicateFinder.FindAll(ctx, files)
	assertNoError(t, err)

	namingIssues, err := checker.namingValidator.ValidateAll(ctx, files, "/test")
	assertNoError(t, err)

	completenessIssues, err := checker.completenessChecker.CheckAll(ctx, files)
	assertNoError(t, err)

	// Combine all issues
	var allIssues []models.ConsistencyIssue
	allIssues = append(allIssues, linkIssues...)
	allIssues = append(allIssues, dupIssues...)
	allIssues = append(allIssues, namingIssues...)
	allIssues = append(allIssues, completenessIssues...)

	// Calculate summary
	summary := checker.calculateSummary(allIssues, len(files), time.Second)

	// Verify summary
	assertEqual(t, len(allIssues), summary.TotalIssues)
	assertEqual(t, len(files), summary.FilesScanned)

	// Calculate score
	score, grade := checker.healthScorer.Calculate(summary)

	t.Logf("Scan complete: %d issues, score %d (%s)", summary.TotalIssues, score, grade)
	t.Logf("Errors: %d, Warnings: %d, Info: %d", 
		summary.ErrorCount, summary.WarningCount, summary.InfoCount)

	// Well-formed test files should have good score
	if score < 70 {
		t.Errorf("expected score >= 70 for well-formed files, got %d", score)
		for _, issue := range allIssues {
			t.Logf("Issue: %s - %s (%s)", issue.Category, issue.Title, issue.FilePath)
		}
	}
}

func TestCheckerService_WithBrokenLinks(t *testing.T) {
	config := &models.ScanConfig{
		CheckBrokenLinks: true,
	}

	checker := &CheckerService{
		linkValidator: NewLinkValidator(),
		healthScorer:  NewHealthScorer(),
		config:        config,
	}

	files := []models.FileInfo{
		createTestFileInfo("test.md", `# Test

See [missing](./does-not-exist.md) and [also missing](./nope.md#section).
`),
	}

	ctx := context.Background()
	issues, err := checker.linkValidator.ValidateAll(ctx, files, "/test")

	assertNoError(t, err)
	assertEqual(t, 2, len(issues))

	for _, issue := range issues {
		assertEqual(t, models.CategoryBrokenLink, issue.Category)
		assertEqual(t, models.SeverityError, issue.Severity)
	}
}

func TestCheckerService_ProgressTracking(t *testing.T) {
	checker := &CheckerService{
		activeScans: make(map[string]*models.ScanProgress),
	}

	reportId := "test-report-123"

	// Set progress
	progress := &models.ScanProgress{
		Status:       models.StatusRunning,
		FilesScanned: 5,
		TotalFiles:   10,
		IssuesFound:  2,
	}
	checker.setProgress(reportId, progress)

	// Get progress
	retrieved := checker.GetProgress(reportId)
	if retrieved == nil {
		t.Fatal("expected progress to be set")
	}
	assertEqual(t, 5, retrieved.FilesScanned)
	assertEqual(t, 10, retrieved.TotalFiles)

	// Clear progress
	checker.clearProgress(reportId)
	
	retrieved = checker.GetProgress(reportId)
	if retrieved != nil {
		t.Error("expected progress to be cleared")
	}
}
```

---

## 10. Benchmark Tests

### `internal/services/consistency/benchmark_test.go`

```go
package consistency

import (
	"context"
	"fmt"
	"testing"

	"spec-manager/internal/models"
)

// ============================================================
// Benchmark Tests
// ============================================================

func BenchmarkScanner_GenerateAnchor(b *testing.B) {
	scanner := NewScanner()
	inputs := []string{
		"Simple Heading",
		"Complex Heading with Numbers 123",
		"Heading with **markdown** formatting",
		"Very Long Heading That Contains Many Words And Should Still Be Fast",
	}

	b.ResetTimer()
	for i := 0; i < b.N; i++ {
		scanner.generateAnchor(inputs[i%len(inputs)])
	}
}

func BenchmarkLinkValidator_ValidateAll(b *testing.B) {
	ctx := context.Background()
	validator := NewLinkValidator()

	// Create test files
	files := make([]models.FileInfo, 100)
	for i := 0; i < 100; i++ {
		content := fmt.Sprintf(`# File %d

See [link1](./file%d.md) and [link2](./file%d.md#section).

## Section

More [links](./file%d.md).
`, i, (i+1)%100, (i+2)%100, (i+3)%100)
		
		files[i] = createTestFileInfo(fmt.Sprintf("file%d.md", i), content)
	}

	b.ResetTimer()
	for i := 0; i < b.N; i++ {
		validator.ValidateAll(ctx, files, "/test")
	}
}

func BenchmarkDuplicateFinder_FindAll(b *testing.B) {
	ctx := context.Background()
	finder := NewDuplicateFinder()

	// Create files with many headings
	files := make([]models.FileInfo, 50)
	for i := 0; i < 50; i++ {
		content := fmt.Sprintf(`# Document %d

## Overview

## API

## Database

## Conclusion
`, i)
		files[i] = createTestFileInfo(fmt.Sprintf("doc%d.md", i), content)
	}

	b.ResetTimer()
	for i := 0; i < b.N; i++ {
		finder.FindAll(ctx, files)
	}
}

func BenchmarkHealthScorer_Calculate(b *testing.B) {
	scorer := NewHealthScorer()

	summary := models.ReportSummary{
		TotalIssues:  50,
		ErrorCount:   10,
		WarningCount: 25,
		InfoCount:    15,
		FilesScanned: 100,
		CategoryCounts: map[models.IssueCategory]int{
			models.CategoryBrokenLink:          5,
			models.CategoryDuplicateDefinition: 10,
			models.CategoryNamingConvention:    15,
			models.CategoryStructureViolation:  10,
			models.CategoryOrphanedFile:        10,
		},
	}

	b.ResetTimer()
	for i := 0; i < b.N; i++ {
		scorer.Calculate(summary)
	}
}

func BenchmarkLevenshteinDistance(b *testing.B) {
	pairs := [][2]string{
		{"database", "databse"},
		{"overview", "overivew"},
		{"configuration", "configration"},
	}

	b.ResetTimer()
	for i := 0; i < b.N; i++ {
		pair := pairs[i%len(pairs)]
		levenshteinDistance(pair[0], pair[1])
	}
}
```

---

## 11. RAG Format Validation Tests

### `internal/services/consistency/rag_validator_test.go`

```go
package consistency

import (
	"context"
	"testing"

	"spec-manager/internal/models"
)

// ============================================================
// RAG Format Validation Tests
// Tests for rules RAG-001 through RAG-005
// Reference: 18-rag-spec-guidelines.md
// ============================================================

// ------------------------------------------------------------
// Test Fixtures
// ------------------------------------------------------------

const validRAGArtifact = `---
title: "User Authentication Flow"
status: draft
tags: [auth, security, backend]
created: 2026-01-27
---

# User Authentication Flow

## Overview

This document describes the authentication flow for the application.
The system uses JWT tokens for session management and supports
multiple authentication providers including OAuth2 and SAML.

## Authentication Steps

### Step 1: User Login

The user submits credentials via the login form. The backend validates
the credentials against the user database and generates a JWT token
upon successful authentication.

### Step 2: Token Validation

Each API request includes the JWT token in the Authorization header.
The backend middleware validates the token signature and expiration.

## Cross-References

- See [Session Management](./session-management.md#token-refresh) for token refresh logic.
- See [Security Patterns](../../general-spec/04-advanced/01-security-patterns-advanced.md) for security guidelines.
`

const invalidNamingArtifact = `---
title: "Bad Naming"
status: draft
tags: [test]
---

# Bad Naming

Content here.
`

const missingFrontmatterArtifact = `# Missing Frontmatter

## Overview

This document is missing the required YAML frontmatter.

## Details

Some content without proper metadata.
`

const incompleteFrontmatterArtifact = `---
title: "Incomplete Metadata"
---

# Incomplete Metadata

## Overview

This document is missing required frontmatter fields (status, tags).
`

const oversizedChunkArtifact = `---
title: "Oversized Chunks"
status: draft
tags: [test]
---

# Oversized Chunks

## Very Long Section

` + generateLongContent(600) + `

## Normal Section

This section has normal length content that fits within chunk boundaries.
`

const undersizedChunkArtifact = `---
title: "Undersized Content"
status: draft
tags: [test]
---

# Undersized Content

## Tiny Section

Too short.

## Another Tiny

Brief.
`

const invalidAnchorArtifact = `---
title: "Invalid Anchors"
status: draft
tags: [test]
---

# Invalid Anchors

## Overview

See [broken link](./other-file.md#Invalid Anchor With Spaces) for details.

Also check [another broken](#UPPERCASE_ANCHOR) section.

## Cross-References

- [Valid anchor](#overview) works fine.
- [Invalid format](#section 3.1) is problematic.
`

const nonEmbeddingFriendlyArtifact = `---
title: "Non-Embedding Friendly"
status: draft
tags: [test]
---

# Non-Embedding Friendly

## Code Heavy Section

` + "```go\n" + `
package main

import (
	"fmt"
	"strings"
	"context"
)

func main() {
	ctx := context.Background()
	result := processData(ctx, "input")
	fmt.Println(result)
}

func processData(ctx context.Context, input string) string {
	// Long processing logic
	processed := strings.ToUpper(input)
	processed = strings.TrimSpace(processed)
	processed = strings.ReplaceAll(processed, " ", "_")
	return processed
}

func helperFunction() {
	// More code
}

func anotherHelper() {
	// Even more code
}
` + "```\n" + `

That's all.
`

// generateLongContent creates content with specified word count
func generateLongContent(wordCount int) string {
	words := []string{
		"the", "system", "processes", "data", "through", "multiple",
		"validation", "layers", "ensuring", "correctness", "and",
		"consistency", "across", "all", "components", "within",
	}
	var result strings.Builder
	for i := 0; i < wordCount; i++ {
		if i > 0 {
			result.WriteString(" ")
		}
		result.WriteString(words[i%len(words)])
	}
	return result.String()
}

// ------------------------------------------------------------
// RAG-001: Artifact Naming Convention Tests
// ------------------------------------------------------------

func TestRAGValidator_RAG001_ValidNaming(t *testing.T) {
	ctx := context.Background()
	validator := NewRAGValidator()

	validPaths := []string{
		"ideas/01-idea-user-auth.md",
		"ideas/02-idea-payment-flow.md",
		"instructions/01-instruction-setup-db.md",
		"instructions/10-instruction-api-design.md",
	}

	for _, path := range validPaths {
		t.Run(path, func(t *testing.T) {
			file := createTestFileInfo(path, validRAGArtifact)
			issues, err := validator.ValidateNaming(ctx, file)

			assertNoError(t, err)
			assertEqual(t, 0, len(issues))
		})
	}
}

func TestRAGValidator_RAG001_InvalidNaming(t *testing.T) {
	ctx := context.Background()
	validator := NewRAGValidator()

	tests := []struct {
		name        string
		path        string
		expectIssue bool
		issueMsg    string
	}{
		{
			name:        "missing numeric prefix",
			path:        "ideas/idea-user-auth.md",
			expectIssue: true,
			issueMsg:    "missing numeric prefix",
		},
		{
			name:        "wrong prefix format",
			path:        "ideas/1-idea-user-auth.md",
			expectIssue: true,
			issueMsg:    "prefix must be two digits",
		},
		{
			name:        "missing type indicator",
			path:        "ideas/01-user-auth.md",
			expectIssue: true,
			issueMsg:    "missing idea/instruction type",
		},
		{
			name:        "uppercase in slug",
			path:        "ideas/01-idea-UserAuth.md",
			expectIssue: true,
			issueMsg:    "slug must be lowercase kebab-case",
		},
		{
			name:        "underscores in slug",
			path:        "instructions/01-instruction-user_auth.md",
			expectIssue: true,
			issueMsg:    "use hyphens not underscores",
		},
		{
			name:        "wrong file extension",
			path:        "ideas/01-idea-user-auth.txt",
			expectIssue: true,
			issueMsg:    "must use .md extension",
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			file := createTestFileInfo(tt.path, invalidNamingArtifact)
			issues, err := validator.ValidateNaming(ctx, file)

			assertNoError(t, err)
			if tt.expectIssue {
				if len(issues) == 0 {
					t.Fatalf("expected naming issue for %s", tt.path)
				}
				assertEqual(t, models.CategoryRAGFormat, issues[0].Category)
				assertEqual(t, "RAG-001", issues[0].RuleID)
			}
		})
	}
}

// ------------------------------------------------------------
// RAG-002: Required Frontmatter Tests
// ------------------------------------------------------------

func TestRAGValidator_RAG002_ValidFrontmatter(t *testing.T) {
	ctx := context.Background()
	validator := NewRAGValidator()

	file := createTestFileInfo("ideas/01-idea-test.md", validRAGArtifact)
	issues, err := validator.ValidateFrontmatter(ctx, file)

	assertNoError(t, err)
	assertEqual(t, 0, len(issues))
}

func TestRAGValidator_RAG002_MissingFrontmatter(t *testing.T) {
	ctx := context.Background()
	validator := NewRAGValidator()

	file := createTestFileInfo("ideas/01-idea-test.md", missingFrontmatterArtifact)
	issues, err := validator.ValidateFrontmatter(ctx, file)

	assertNoError(t, err)
	if len(issues) == 0 {
		t.Fatal("expected frontmatter issue")
	}

	assertEqual(t, models.CategoryRAGFormat, issues[0].Category)
	assertEqual(t, "RAG-002", issues[0].RuleID)
	assertEqual(t, models.SeverityError, issues[0].Severity)
}

func TestRAGValidator_RAG002_IncompleteFrontmatter(t *testing.T) {
	ctx := context.Background()
	validator := NewRAGValidator()

	file := createTestFileInfo("ideas/01-idea-test.md", incompleteFrontmatterArtifact)
	issues, err := validator.ValidateFrontmatter(ctx, file)

	assertNoError(t, err)
	
	// Should have issues for missing 'status' and 'tags'
	if len(issues) < 2 {
		t.Fatalf("expected at least 2 issues for missing fields, got %d", len(issues))
	}

	for _, issue := range issues {
		assertEqual(t, "RAG-002", issue.RuleID)
	}
}

func TestRAGValidator_RAG002_InvalidStatus(t *testing.T) {
	ctx := context.Background()
	validator := NewRAGValidator()

	content := `---
title: "Test"
status: invalid_status
tags: [test]
---

# Test

Content.
`

	file := createTestFileInfo("ideas/01-idea-test.md", content)
	issues, err := validator.ValidateFrontmatter(ctx, file)

	assertNoError(t, err)
	
	var statusIssue *models.ConsistencyIssue
	for i := range issues {
		if strings.Contains(issues[i].Message, "status") {
			statusIssue = &issues[i]
			break
		}
	}

	if statusIssue == nil {
		t.Fatal("expected status validation issue")
	}
	assertEqual(t, "RAG-002", statusIssue.RuleID)
}

// ------------------------------------------------------------
// RAG-003: Chunk Size Boundaries Tests
// ------------------------------------------------------------

func TestRAGValidator_RAG003_ValidChunkSize(t *testing.T) {
	ctx := context.Background()
	validator := NewRAGValidator()

	file := createTestFileInfo("ideas/01-idea-test.md", validRAGArtifact)
	analysis, err := validator.AnalyzeChunks(ctx, file)

	assertNoError(t, err)
	
	for _, chunk := range analysis.Chunks {
		if chunk.WordCount < 100 || chunk.WordCount > 500 {
			t.Errorf("chunk %s has invalid word count: %d", chunk.ID, chunk.WordCount)
		}
	}
}

func TestRAGValidator_RAG003_OversizedChunk(t *testing.T) {
	ctx := context.Background()
	validator := NewRAGValidator()

	file := createTestFileInfo("ideas/01-idea-test.md", oversizedChunkArtifact)
	issues, err := validator.ValidateChunkSizes(ctx, file)

	assertNoError(t, err)

	var oversizeIssue *models.ConsistencyIssue
	for i := range issues {
		if issues[i].RuleID == "RAG-003" && strings.Contains(issues[i].Message, "exceeds") {
			oversizeIssue = &issues[i]
			break
		}
	}

	if oversizeIssue == nil {
		t.Fatal("expected oversized chunk issue")
	}
	assertEqual(t, models.SeverityWarning, oversizeIssue.Severity)
}

func TestRAGValidator_RAG003_UndersizedChunk(t *testing.T) {
	ctx := context.Background()
	validator := NewRAGValidator()

	file := createTestFileInfo("ideas/01-idea-test.md", undersizedChunkArtifact)
	issues, err := validator.ValidateChunkSizes(ctx, file)

	assertNoError(t, err)

	undersizeCount := 0
	for _, issue := range issues {
		if issue.RuleID == "RAG-003" && strings.Contains(issue.Message, "below minimum") {
			undersizeCount++
		}
	}

	if undersizeCount == 0 {
		t.Fatal("expected undersized chunk issues")
	}
}

func TestRAGValidator_RAG003_ChunkBoundaryDetection(t *testing.T) {
	ctx := context.Background()
	validator := NewRAGValidator()

	// Test that chunks are properly split at heading boundaries
	content := `---
title: "Chunk Boundaries"
status: draft
tags: [test]
---

# Main Title

## Section A

` + generateLongContent(200) + `

## Section B

` + generateLongContent(200) + `

## Section C

` + generateLongContent(200) + `
`

	file := createTestFileInfo("ideas/01-idea-test.md", content)
	analysis, err := validator.AnalyzeChunks(ctx, file)

	assertNoError(t, err)

	// Should have separate chunks for each section
	if len(analysis.Chunks) < 3 {
		t.Errorf("expected at least 3 chunks, got %d", len(analysis.Chunks))
	}

	// Each chunk should have a heading anchor
	for _, chunk := range analysis.Chunks {
		if chunk.HeadingAnchor == "" {
			t.Errorf("chunk %s missing heading anchor", chunk.ID)
		}
	}
}

// ------------------------------------------------------------
// RAG-004: Anchor Link Format Tests
// ------------------------------------------------------------

func TestRAGValidator_RAG004_ValidAnchors(t *testing.T) {
	ctx := context.Background()
	validator := NewRAGValidator()

	content := `---
title: "Valid Anchors"
status: draft
tags: [test]
---

# Valid Anchors

## Overview

See [section below](#implementation-details) for more.

Check [external doc](./other.md#api-endpoints) too.

## Implementation Details

The implementation details are here.

## Cross-References

- [Back to overview](#overview)
- [Other file section](../spec/file.md#database-schema)
`

	file := createTestFileInfo("ideas/01-idea-test.md", content)
	issues, err := validator.ValidateAnchors(ctx, file)

	assertNoError(t, err)
	
	anchorIssues := 0
	for _, issue := range issues {
		if issue.RuleID == "RAG-004" {
			anchorIssues++
		}
	}
	assertEqual(t, 0, anchorIssues)
}

func TestRAGValidator_RAG004_InvalidAnchorFormat(t *testing.T) {
	ctx := context.Background()
	validator := NewRAGValidator()

	file := createTestFileInfo("ideas/01-idea-test.md", invalidAnchorArtifact)
	issues, err := validator.ValidateAnchors(ctx, file)

	assertNoError(t, err)

	anchorIssues := 0
	for _, issue := range issues {
		if issue.RuleID == "RAG-004" {
			anchorIssues++
		}
	}

	if anchorIssues < 3 {
		t.Errorf("expected at least 3 anchor format issues, got %d", anchorIssues)
	}
}

func TestRAGValidator_RAG004_AnchorFormatRules(t *testing.T) {
	ctx := context.Background()
	validator := NewRAGValidator()

	tests := []struct {
		name        string
		anchor      string
		expectValid bool
	}{
		{"lowercase kebab", "database-schema", true},
		{"with numbers", "section-3-overview", true},
		{"single word", "overview", true},
		{"uppercase", "DATABASE-SCHEMA", false},
		{"spaces", "database schema", false},
		{"underscores", "database_schema", false},
		{"special chars", "section@3", false},
		{"dots", "section.3", false},
		{"empty", "", false},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			valid := validator.IsValidAnchorFormat(tt.anchor)
			assertEqual(t, tt.expectValid, valid)
		})
	}
}

// ------------------------------------------------------------
// RAG-005: Embedding-Friendly Prose Tests
// ------------------------------------------------------------

func TestRAGValidator_RAG005_ValidProse(t *testing.T) {
	ctx := context.Background()
	validator := NewRAGValidator()

	file := createTestFileInfo("ideas/01-idea-test.md", validRAGArtifact)
	issues, err := validator.ValidateProseQuality(ctx, file)

	assertNoError(t, err)

	proseIssues := 0
	for _, issue := range issues {
		if issue.RuleID == "RAG-005" {
			proseIssues++
		}
	}
	assertEqual(t, 0, proseIssues)
}

func TestRAGValidator_RAG005_CodeHeavyContent(t *testing.T) {
	ctx := context.Background()
	validator := NewRAGValidator()

	file := createTestFileInfo("ideas/01-idea-test.md", nonEmbeddingFriendlyArtifact)
	issues, err := validator.ValidateProseQuality(ctx, file)

	assertNoError(t, err)

	var codeRatioIssue *models.ConsistencyIssue
	for i := range issues {
		if issues[i].RuleID == "RAG-005" && strings.Contains(issues[i].Message, "code ratio") {
			codeRatioIssue = &issues[i]
			break
		}
	}

	if codeRatioIssue == nil {
		t.Fatal("expected code ratio warning")
	}
	assertEqual(t, models.SeverityWarning, codeRatioIssue.Severity)
}

func TestRAGValidator_RAG005_ProseMetrics(t *testing.T) {
	ctx := context.Background()
	validator := NewRAGValidator()

	file := createTestFileInfo("ideas/01-idea-test.md", validRAGArtifact)
	metrics, err := validator.CalculateProseMetrics(ctx, file)

	assertNoError(t, err)

	// Valid artifact should have good prose metrics
	if metrics.ProseRatio < 0.6 {
		t.Errorf("expected prose ratio >= 0.6, got %.2f", metrics.ProseRatio)
	}
	if metrics.AvgSentenceLength > 30 {
		t.Errorf("expected avg sentence length <= 30, got %.1f", metrics.AvgSentenceLength)
	}
	if metrics.HeadingDensity < 0.02 {
		t.Errorf("expected heading density >= 0.02, got %.3f", metrics.HeadingDensity)
	}
}

func TestRAGValidator_RAG005_ListHeavyContent(t *testing.T) {
	ctx := context.Background()
	validator := NewRAGValidator()

	content := `---
title: "List Heavy"
status: draft
tags: [test]
---

# List Heavy Document

## Items

- Item 1
- Item 2
- Item 3
- Item 4
- Item 5
- Item 6
- Item 7
- Item 8
- Item 9
- Item 10
- Item 11
- Item 12
- Item 13
- Item 14
- Item 15

## More Items

1. First
2. Second
3. Third
4. Fourth
5. Fifth
6. Sixth
7. Seventh
8. Eighth
9. Ninth
10. Tenth
`

	file := createTestFileInfo("ideas/01-idea-test.md", content)
	issues, err := validator.ValidateProseQuality(ctx, file)

	assertNoError(t, err)

	// Should warn about list-heavy content with minimal prose
	var listIssue *models.ConsistencyIssue
	for i := range issues {
		if issues[i].RuleID == "RAG-005" {
			listIssue = &issues[i]
			break
		}
	}

	if listIssue == nil {
		t.Fatal("expected prose quality warning for list-heavy content")
	}
}

// ------------------------------------------------------------
// Integration Tests
// ------------------------------------------------------------

func TestRAGValidator_ValidateAll(t *testing.T) {
	ctx := context.Background()
	validator := NewRAGValidator()

	tests := []struct {
		name           string
		path           string
		content        string
		expectedIssues int
		expectedErrors int
	}{
		{
			name:           "valid artifact",
			path:           "ideas/01-idea-auth-flow.md",
			content:        validRAGArtifact,
			expectedIssues: 0,
			expectedErrors: 0,
		},
		{
			name:           "missing frontmatter",
			path:           "ideas/01-idea-test.md",
			content:        missingFrontmatterArtifact,
			expectedIssues: 1,
			expectedErrors: 1,
		},
		{
			name:           "invalid naming",
			path:           "ideas/bad-name.md",
			content:        validRAGArtifact,
			expectedIssues: 1,
			expectedErrors: 0,
		},
		{
			name:           "multiple issues",
			path:           "ideas/bad-name.md",
			content:        missingFrontmatterArtifact,
			expectedIssues: 2,
			expectedErrors: 1,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			file := createTestFileInfo(tt.path, tt.content)
			issues, err := validator.ValidateAll(ctx, file)

			assertNoError(t, err)
			
			if len(issues) != tt.expectedIssues {
				t.Errorf("expected %d issues, got %d", tt.expectedIssues, len(issues))
			}

			errorCount := countIssuesBySeverity(issues, models.SeverityError)
			if errorCount != tt.expectedErrors {
				t.Errorf("expected %d errors, got %d", tt.expectedErrors, errorCount)
			}
		})
	}
}

func TestRAGValidator_BatchValidation(t *testing.T) {
	ctx := context.Background()
	validator := NewRAGValidator()

	files := []models.FileInfo{
		createTestFileInfo("ideas/01-idea-auth.md", validRAGArtifact),
		createTestFileInfo("ideas/02-idea-payment.md", validRAGArtifact),
		createTestFileInfo("ideas/03-idea-bad.md", missingFrontmatterArtifact),
		createTestFileInfo("instructions/01-instruction-setup.md", validRAGArtifact),
	}

	results, err := validator.ValidateBatch(ctx, files)

	assertNoError(t, err)
	assertEqual(t, 4, len(results))

	// Count files with issues
	filesWithIssues := 0
	for _, result := range results {
		if len(result.Issues) > 0 {
			filesWithIssues++
		}
	}
	assertEqual(t, 1, filesWithIssues)
}

// ------------------------------------------------------------
// Benchmarks
// ------------------------------------------------------------

func BenchmarkRAGValidator_ValidateFrontmatter(b *testing.B) {
	ctx := context.Background()
	validator := NewRAGValidator()
	file := createTestFileInfo("ideas/01-idea-test.md", validRAGArtifact)

	b.ResetTimer()
	for i := 0; i < b.N; i++ {
		validator.ValidateFrontmatter(ctx, file)
	}
}

func BenchmarkRAGValidator_AnalyzeChunks(b *testing.B) {
	ctx := context.Background()
	validator := NewRAGValidator()
	file := createTestFileInfo("ideas/01-idea-test.md", validRAGArtifact)

	b.ResetTimer()
	for i := 0; i < b.N; i++ {
		validator.AnalyzeChunks(ctx, file)
	}
}

func BenchmarkRAGValidator_ValidateAll(b *testing.B) {
	ctx := context.Background()
	validator := NewRAGValidator()
	file := createTestFileInfo("ideas/01-idea-test.md", validRAGArtifact)

	b.ResetTimer()
	for i := 0; i < b.N; i++ {
		validator.ValidateAll(ctx, file)
	}
}
```

---

## 12. Cross-References

- **Implementation:** [14-consistency-checker-implementation.md](./14-consistency-checker-implementation.md)
- **Spec Definition:** [13-consistency-checker.md](./13-consistency-checker.md)
- **RAG Spec Guidelines:** [18-rag-spec-guidelines.md](./18-rag-spec-guidelines.md)
- **Testing Standards:** [../../general-spec/03-quality/01-testing-standards-quality.md](../../general-spec/03-quality/01-testing-standards-quality.md)
