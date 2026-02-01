# Consistency Checker - Go Implementation

**Version:** 1.0.0  
**Status:** Draft  
**Last Updated:** 2026-01-27

---

## 1. Overview

This document provides the complete Go implementation for the Consistency Checker backend service. It scans markdown files for:

- **Broken links** — Internal links pointing to non-existent files or sections
- **Duplicate definitions** — Same term/anchor defined multiple times
- **Naming convention violations** — Files/folders not following project standards
- **Orphaned files** — Files never referenced by any other file
- **Missing required sections** — Spec files missing mandatory headers

---

## 2. Package Structure

```
internal/
├── services/
│   └── consistency/
│       ├── checker.go          # Main service orchestrator
│       ├── scanner.go          # File scanning utilities
│       ├── link_validator.go   # Link validation logic
│       ├── naming_validator.go # Naming convention checks
│       ├── duplicate_finder.go # Duplicate definition detection
│       ├── completeness.go     # Required section checks
│       ├── health_scorer.go    # Score calculation
│       └── fixer.go            # Auto-fix generation
├── models/
│   └── consistency.go          # Domain models
└── repository/
    └── consistency_repo.go     # Database operations
```

---

## 3. Models

### `internal/models/consistency.go`

```go
package models

import (
	"time"
)

// ============================================================
// Consistency Checker - Domain Models
// ============================================================

// IssueSeverity represents the severity level of a finding
type IssueSeverity string

const (
	SeverityError   IssueSeverity = "error"
	SeverityWarning IssueSeverity = "warning"
	SeverityInfo    IssueSeverity = "info"
)

// IssueCategory represents the type of consistency issue
type IssueCategory string

const (
	CategoryBrokenLink          IssueCategory = "broken-link"
	CategoryMissingReference    IssueCategory = "missing-reference"
	CategoryDuplicateDefinition IssueCategory = "duplicate-definition"
	CategoryNamingConvention    IssueCategory = "naming-convention"
	CategoryStructureViolation  IssueCategory = "structure-violation"
	CategoryOrphanedFile        IssueCategory = "orphaned-file"
	CategoryCircularDependency  IssueCategory = "circular-dependency"
	CategorySchemaMismatch      IssueCategory = "schema-mismatch"
	CategoryVersionConflict     IssueCategory = "version-conflict"
)

// ReportStatus represents the current state of a consistency check
type ReportStatus string

const (
	StatusPending   ReportStatus = "pending"
	StatusRunning   ReportStatus = "running"
	StatusCompleted ReportStatus = "completed"
	StatusFailed    ReportStatus = "failed"
	StatusCancelled ReportStatus = "cancelled"
)

// ConsistencyIssue represents a single issue found during scanning
type ConsistencyIssue struct {
	Id            string        `json:"id"`
	Category      IssueCategory `json:"category"`
	Severity      IssueSeverity `json:"severity"`
	Title         string        `json:"title"`
	Description   string        `json:"description"`
	FilePath      string        `json:"filePath"`
	LineNumber    *int          `json:"lineNumber,omitempty"`
	ColumnNumber  *int          `json:"columnNumber,omitempty"`
	SourceText    *string       `json:"sourceText,omitempty"`
	SuggestedFix  *string       `json:"suggestedFix,omitempty"`
	RelatedFiles  []string      `json:"relatedFiles,omitempty"`
	IsResolved    bool          `json:"isResolved"`
	ResolvedAt    *time.Time    `json:"resolvedAt,omitempty"`
	ResolvedBy    *string       `json:"resolvedBy,omitempty"`
	AutoFixable   bool          `json:"autoFixable"`
}

// ReportSummary contains aggregate statistics for a report
type ReportSummary struct {
	TotalIssues    int                      `json:"totalIssues"`
	ErrorCount     int                      `json:"errorCount"`
	WarningCount   int                      `json:"warningCount"`
	InfoCount      int                      `json:"infoCount"`
	ResolvedCount  int                      `json:"resolvedCount"`
	FilesScanned   int                      `json:"filesScanned"`
	ScanDurationMs int64                    `json:"scanDurationMs"`
	CategoryCounts map[IssueCategory]int    `json:"categoryCounts"`
}

// ConsistencyReport represents a complete scan report
type ConsistencyReport struct {
	Id          string             `json:"id"`
	ProjectId   string             `json:"projectId"`
	Status      ReportStatus       `json:"status"`
	Summary     ReportSummary      `json:"summary"`
	Issues      []ConsistencyIssue `json:"issues"`
	CreatedAt   time.Time          `json:"createdAt"`
	CompletedAt *time.Time         `json:"completedAt,omitempty"`
	TriggeredBy string             `json:"triggeredBy"` // "manual", "auto", "commit-hook"
	CommitHash  *string            `json:"commitHash,omitempty"`
	Score       int                `json:"score"`       // 0-100
	Grade       string             `json:"grade"`       // A-F
}

// ScanProgress tracks real-time scan progress
type ScanProgress struct {
	Status          ReportStatus `json:"status"`
	CurrentFile     *string      `json:"currentFile,omitempty"`
	FilesScanned    int          `json:"filesScanned"`
	TotalFiles      int          `json:"totalFiles"`
	IssuesFound     int          `json:"issuesFound"`
	ProgressPercent float64      `json:"progressPercent"`
}

// AutoFix represents a proposed automatic fix
type AutoFix struct {
	Id          string  `json:"id"`
	FindingId   string  `json:"findingId"`
	FilePath    string  `json:"filePath"`
	LineNumber  *int    `json:"lineNumber,omitempty"`
	OldContent  string  `json:"oldContent"`
	NewContent  string  `json:"newContent"`
	Confidence  float64 `json:"confidence"` // 0-1
	Description string  `json:"description"`
}

// FixResult tracks the outcome of applying fixes
type FixResult struct {
	Applied []AutoFix `json:"applied"`
	Skipped []AutoFix `json:"skipped"`
	Failed  []AutoFix `json:"failed"`
}

// LinkInfo represents a parsed markdown link
type LinkInfo struct {
	FullMatch    string
	Text         string
	Target       string
	IsExternal   bool
	HasAnchor    bool
	FilePart     string
	AnchorPart   string
	LineNumber   int
	ColumnNumber int
}

// FileInfo represents scanned file metadata
type FileInfo struct {
	Path         string
	RelativePath string
	ModifiedAt   time.Time
	Size         int64
	Headings     []HeadingInfo
	Links        []LinkInfo
	Definitions  []DefinitionInfo
}

// HeadingInfo represents a markdown heading
type HeadingInfo struct {
	Level      int
	Text       string
	Anchor     string
	LineNumber int
}

// DefinitionInfo represents a term or anchor definition
type DefinitionInfo struct {
	Type       string // "heading", "anchor", "glossary-term"
	Name       string
	NormalizedName string
	FilePath   string
	LineNumber int
}

// NamingRule defines a naming convention check
type NamingRule struct {
	Pattern     string
	Description string
	Severity    IssueSeverity
	Applies     func(path string) bool
}

// ScanConfig configures the consistency checker behavior
type ScanConfig struct {
	ProjectPath           string
	IncludePatterns       []string
	ExcludePatterns       []string
	CheckBrokenLinks      bool
	CheckDuplicates       bool
	CheckNaming           bool
	CheckCompleteness     bool
	CheckOrphans          bool
	AutoFixEnabled        bool
	MinConfidenceForFix   float64
	MaxFilesToScan        int
	TimeoutSeconds        int
}
```

---

## 4. Main Checker Service

### `internal/services/consistency/checker.go`

```go
package consistency

import (
	"context"
	"fmt"
	"sync"
	"time"

	"github.com/google/uuid"
	"spec-manager/internal/models"
	"spec-manager/internal/repository"
	"spec-manager/internal/utils"
)

// ============================================================
// Consistency Checker Service - Main Orchestrator
// ============================================================

type CheckerService struct {
	repo             *repository.ConsistencyRepo
	scanner          *Scanner
	linkValidator    *LinkValidator
	namingValidator  *NamingValidator
	duplicateFinder  *DuplicateFinder
	completenessChecker *CompletenessChecker
	healthScorer     *HealthScorer
	fixer            *Fixer
	
	// Progress tracking
	progressMu       sync.RWMutex
	activeScans      map[string]*models.ScanProgress
	
	// Configuration
	config           *models.ScanConfig
}

func NewCheckerService(
	repo *repository.ConsistencyRepo,
	config *models.ScanConfig,
) *CheckerService {
	return &CheckerService{
		repo:                repo,
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
}

// RunCheck performs a full consistency check on the project
func (s *CheckerService) RunCheck(
	ctx context.Context,
	projectId string,
	projectPath string,
	triggeredBy string,
) (*models.ConsistencyReport, error) {
	startTime := time.Now()
	reportId := uuid.NewString()

	// Initialize report
	report := &models.ConsistencyReport{
		Id:          reportId,
		ProjectId:   projectId,
		Status:      models.StatusRunning,
		TriggeredBy: triggeredBy,
		CreatedAt:   startTime,
		Issues:      make([]models.ConsistencyIssue, 0),
		Summary: models.ReportSummary{
			CategoryCounts: make(map[models.IssueCategory]int),
		},
	}

	// Initialize progress tracking
	progress := &models.ScanProgress{
		Status: models.StatusRunning,
	}
	s.setProgress(reportId, progress)
	defer s.clearProgress(reportId)

	// Step 1: Scan all files
	utils.Info("Starting consistency check", map[string]any{
		"projectId": projectId,
		"reportId":  reportId,
	})

	files, err := s.scanner.ScanDirectory(ctx, projectPath, s.config)
	if err != nil {
		report.Status = models.StatusFailed
		return report, fmt.Errorf("scan directory: %w", err)
	}

	progress.TotalFiles = len(files)
	s.setProgress(reportId, progress)

	// Step 2: Run all validators in parallel
	var wg sync.WaitGroup
	issuesCh := make(chan []models.ConsistencyIssue, 5)
	errCh := make(chan error, 5)

	// 2a: Link validation
	if s.config.CheckBrokenLinks {
		wg.Add(1)
		go func() {
			defer wg.Done()
			issues, err := s.linkValidator.ValidateAll(ctx, files, projectPath)
			if err != nil {
				errCh <- fmt.Errorf("link validation: %w", err)
				return
			}
			issuesCh <- issues
		}()
	}

	// 2b: Duplicate detection
	if s.config.CheckDuplicates {
		wg.Add(1)
		go func() {
			defer wg.Done()
			issues, err := s.duplicateFinder.FindAll(ctx, files)
			if err != nil {
				errCh <- fmt.Errorf("duplicate detection: %w", err)
				return
			}
			issuesCh <- issues
		}()
	}

	// 2c: Naming convention checks
	if s.config.CheckNaming {
		wg.Add(1)
		go func() {
			defer wg.Done()
			issues, err := s.namingValidator.ValidateAll(ctx, files, projectPath)
			if err != nil {
				errCh <- fmt.Errorf("naming validation: %w", err)
				return
			}
			issuesCh <- issues
		}()
	}

	// 2d: Completeness checks
	if s.config.CheckCompleteness {
		wg.Add(1)
		go func() {
			defer wg.Done()
			issues, err := s.completenessChecker.CheckAll(ctx, files)
			if err != nil {
				errCh <- fmt.Errorf("completeness check: %w", err)
				return
			}
			issuesCh <- issues
		}()
	}

	// 2e: Orphan detection
	if s.config.CheckOrphans {
		wg.Add(1)
		go func() {
			defer wg.Done()
			issues, err := s.findOrphans(ctx, files)
			if err != nil {
				errCh <- fmt.Errorf("orphan detection: %w", err)
				return
			}
			issuesCh <- issues
		}()
	}

	// Wait for all validators
	go func() {
		wg.Wait()
		close(issuesCh)
		close(errCh)
	}()

	// Collect results
	var allIssues []models.ConsistencyIssue
	for issues := range issuesCh {
		allIssues = append(allIssues, issues...)
	}

	// Check for errors
	select {
	case err := <-errCh:
		if err != nil {
			utils.Error("Validation error", map[string]any{"error": err.Error()})
			// Continue with partial results
		}
	default:
	}

	// Step 3: Calculate summary and score
	report.Issues = allIssues
	report.Summary = s.calculateSummary(allIssues, len(files), time.Since(startTime))
	report.Score, report.Grade = s.healthScorer.Calculate(report.Summary)

	// Step 4: Generate auto-fixes if enabled
	if s.config.AutoFixEnabled {
		for i := range report.Issues {
			fix := s.fixer.GenerateFix(&report.Issues[i])
			if fix != nil && fix.Confidence >= s.config.MinConfidenceForFix {
				report.Issues[i].AutoFixable = true
				report.Issues[i].SuggestedFix = &fix.NewContent
			}
		}
	}

	// Step 5: Save report
	completedAt := time.Now()
	report.Status = models.StatusCompleted
	report.CompletedAt = &completedAt

	if err := s.repo.SaveReport(ctx, report); err != nil {
		return report, fmt.Errorf("save report: %w", err)
	}

	utils.Info("Consistency check completed", map[string]any{
		"reportId":     reportId,
		"score":        report.Score,
		"grade":        report.Grade,
		"totalIssues":  report.Summary.TotalIssues,
		"durationMs":   report.Summary.ScanDurationMs,
	})

	return report, nil
}

// GetProgress returns the current progress of an active scan
func (s *CheckerService) GetProgress(reportId string) *models.ScanProgress {
	s.progressMu.RLock()
	defer s.progressMu.RUnlock()
	return s.activeScans[reportId]
}

func (s *CheckerService) setProgress(reportId string, progress *models.ScanProgress) {
	s.progressMu.Lock()
	defer s.progressMu.Unlock()
	s.activeScans[reportId] = progress
}

func (s *CheckerService) clearProgress(reportId string) {
	s.progressMu.Lock()
	defer s.progressMu.Unlock()
	delete(s.activeScans, reportId)
}

func (s *CheckerService) calculateSummary(
	issues []models.ConsistencyIssue,
	filesScanned int,
	duration time.Duration,
) models.ReportSummary {
	summary := models.ReportSummary{
		TotalIssues:    len(issues),
		FilesScanned:   filesScanned,
		ScanDurationMs: duration.Milliseconds(),
		CategoryCounts: make(map[models.IssueCategory]int),
	}

	for _, issue := range issues {
		switch issue.Severity {
		case models.SeverityError:
			summary.ErrorCount++
		case models.SeverityWarning:
			summary.WarningCount++
		case models.SeverityInfo:
			summary.InfoCount++
		}

		if issue.IsResolved {
			summary.ResolvedCount++
		}

		summary.CategoryCounts[issue.Category]++
	}

	return summary
}

func (s *CheckerService) findOrphans(
	ctx context.Context,
	files []models.FileInfo,
) ([]models.ConsistencyIssue, error) {
	// Build a set of all referenced files
	referenced := make(map[string]bool)
	
	for _, file := range files {
		for _, link := range file.Links {
			if !link.IsExternal && link.FilePart != "" {
				referenced[link.FilePart] = true
			}
		}
	}

	// Find files that are never referenced
	var issues []models.ConsistencyIssue
	excludePatterns := []string{
		"00-overview", "README", "99-", "index",
	}

	for _, file := range files {
		// Skip exclusions
		skip := false
		for _, pattern := range excludePatterns {
			if containsPattern(file.RelativePath, pattern) {
				skip = true
				break
			}
		}
		if skip {
			continue
		}

		if !referenced[file.RelativePath] {
			issues = append(issues, models.ConsistencyIssue{
				Id:          uuid.NewString(),
				Category:    models.CategoryOrphanedFile,
				Severity:    models.SeverityWarning,
				Title:       "Orphaned file detected",
				Description: fmt.Sprintf("File '%s' is not referenced by any other file", file.RelativePath),
				FilePath:    file.RelativePath,
				AutoFixable: false,
			})
		}
	}

	return issues, nil
}

func containsPattern(s, pattern string) bool {
	return len(s) >= len(pattern) && 
		(s == pattern || 
		 len(s) > len(pattern) && 
		 (s[:len(pattern)] == pattern || s[len(s)-len(pattern):] == pattern))
}
```

---

## 5. File Scanner

### `internal/services/consistency/scanner.go`

```go
package consistency

import (
	"bufio"
	"context"
	"os"
	"path/filepath"
	"regexp"
	"strings"
	"time"

	"spec-manager/internal/models"
)

// ============================================================
// File Scanner - Markdown Parsing and Analysis
// ============================================================

type Scanner struct {
	// Regex patterns for parsing
	headingRegex   *regexp.Regexp
	linkRegex      *regexp.Regexp
	anchorRegex    *regexp.Regexp
	definitionRegex *regexp.Regexp
}

func NewScanner() *Scanner {
	return &Scanner{
		// Match markdown headings: ## Heading Text
		headingRegex: regexp.MustCompile(`^(#{1,6})\s+(.+)$`),
		
		// Match markdown links: [text](target)
		linkRegex: regexp.MustCompile(`\[([^\]]+)\]\(([^)]+)\)`),
		
		// Match explicit anchors: {#anchor-name}
		anchorRegex: regexp.MustCompile(`\{#([a-z0-9-]+)\}`),
		
		// Match term definitions (glossary style)
		definitionRegex: regexp.MustCompile(`^\*\*([^*]+)\*\*\s*[-:]`),
	}
}

// ScanDirectory scans all markdown files in a directory
func (s *Scanner) ScanDirectory(
	ctx context.Context,
	rootPath string,
	config *models.ScanConfig,
) ([]models.FileInfo, error) {
	var files []models.FileInfo

	err := filepath.Walk(rootPath, func(path string, info os.FileInfo, err error) error {
		if err != nil {
			return err
		}

		// Check context cancellation
		select {
		case <-ctx.Done():
			return ctx.Err()
		default:
		}

		// Skip directories
		if info.IsDir() {
			// Skip excluded directories
			for _, pattern := range config.ExcludePatterns {
				if matched, _ := filepath.Match(pattern, info.Name()); matched {
					return filepath.SkipDir
				}
			}
			return nil
		}

		// Only process markdown files
		if !strings.HasSuffix(strings.ToLower(path), ".md") {
			return nil
		}

		// Check include patterns
		if len(config.IncludePatterns) > 0 {
			matched := false
			for _, pattern := range config.IncludePatterns {
				if m, _ := filepath.Match(pattern, info.Name()); m {
					matched = true
					break
				}
			}
			if !matched {
				return nil
			}
		}

		// Check max files limit
		if config.MaxFilesToScan > 0 && len(files) >= config.MaxFilesToScan {
			return nil
		}

		// Scan the file
		relPath, _ := filepath.Rel(rootPath, path)
		fileInfo, err := s.ScanFile(ctx, path, relPath)
		if err != nil {
			// Log error but continue
			return nil
		}

		fileInfo.ModifiedAt = info.ModTime()
		fileInfo.Size = info.Size()
		files = append(files, *fileInfo)

		return nil
	})

	return files, err
}

// ScanFile parses a single markdown file
func (s *Scanner) ScanFile(
	ctx context.Context,
	filePath string,
	relativePath string,
) (*models.FileInfo, error) {
	file, err := os.Open(filePath)
	if err != nil {
		return nil, err
	}
	defer file.Close()

	fileInfo := &models.FileInfo{
		Path:         filePath,
		RelativePath: relativePath,
		Headings:     make([]models.HeadingInfo, 0),
		Links:        make([]models.LinkInfo, 0),
		Definitions:  make([]models.DefinitionInfo, 0),
	}

	scanner := bufio.NewScanner(file)
	lineNumber := 0

	for scanner.Scan() {
		lineNumber++
		line := scanner.Text()

		// Check context
		select {
		case <-ctx.Done():
			return nil, ctx.Err()
		default:
		}

		// Parse headings
		if matches := s.headingRegex.FindStringSubmatch(line); matches != nil {
			level := len(matches[1])
			text := strings.TrimSpace(matches[2])
			anchor := s.generateAnchor(text)

			fileInfo.Headings = append(fileInfo.Headings, models.HeadingInfo{
				Level:      level,
				Text:       text,
				Anchor:     anchor,
				LineNumber: lineNumber,
			})

			fileInfo.Definitions = append(fileInfo.Definitions, models.DefinitionInfo{
				Type:           "heading",
				Name:           text,
				NormalizedName: anchor,
				FilePath:       relativePath,
				LineNumber:     lineNumber,
			})
		}

		// Parse links
		linkMatches := s.linkRegex.FindAllStringSubmatchIndex(line, -1)
		for _, match := range linkMatches {
			if len(match) >= 6 {
				text := line[match[2]:match[3]]
				target := line[match[4]:match[5]]
				
				linkInfo := s.parseLink(text, target, lineNumber, match[4])
				fileInfo.Links = append(fileInfo.Links, linkInfo)
			}
		}

		// Parse explicit anchors
		if matches := s.anchorRegex.FindStringSubmatch(line); matches != nil {
			fileInfo.Definitions = append(fileInfo.Definitions, models.DefinitionInfo{
				Type:           "anchor",
				Name:           matches[1],
				NormalizedName: matches[1],
				FilePath:       relativePath,
				LineNumber:     lineNumber,
			})
		}

		// Parse term definitions (glossary entries)
		if matches := s.definitionRegex.FindStringSubmatch(line); matches != nil {
			term := strings.TrimSpace(matches[1])
			fileInfo.Definitions = append(fileInfo.Definitions, models.DefinitionInfo{
				Type:           "glossary-term",
				Name:           term,
				NormalizedName: s.generateAnchor(term),
				FilePath:       relativePath,
				LineNumber:     lineNumber,
			})
		}
	}

	return fileInfo, scanner.Err()
}

// parseLink extracts link components
func (s *Scanner) parseLink(text, target string, line, col int) models.LinkInfo {
	link := models.LinkInfo{
		FullMatch:    "[" + text + "](" + target + ")",
		Text:         text,
		Target:       target,
		LineNumber:   line,
		ColumnNumber: col,
	}

	// Check if external
	if strings.HasPrefix(target, "http://") || 
	   strings.HasPrefix(target, "https://") ||
	   strings.HasPrefix(target, "mailto:") {
		link.IsExternal = true
		return link
	}

	// Parse anchor
	if strings.Contains(target, "#") {
		parts := strings.SplitN(target, "#", 2)
		link.FilePart = parts[0]
		link.AnchorPart = parts[1]
		link.HasAnchor = true
	} else {
		link.FilePart = target
	}

	return link
}

// generateAnchor creates a URL-safe anchor from heading text
func (s *Scanner) generateAnchor(text string) string {
	// Remove markdown formatting
	text = regexp.MustCompile(`[\*_\[\]()]+`).ReplaceAllString(text, "")
	
	// Convert to lowercase
	text = strings.ToLower(text)
	
	// Replace spaces and special chars with hyphens
	text = regexp.MustCompile(`[^a-z0-9]+`).ReplaceAllString(text, "-")
	
	// Trim leading/trailing hyphens
	text = strings.Trim(text, "-")
	
	return text
}
```

---

## 6. Link Validator

### `internal/services/consistency/link_validator.go`

```go
package consistency

import (
	"context"
	"fmt"
	"os"
	"path/filepath"
	"strings"

	"github.com/google/uuid"
	"spec-manager/internal/models"
)

// ============================================================
// Link Validator - Broken Link Detection
// ============================================================

type LinkValidator struct {
	// Cache of file existence checks
	fileCache map[string]bool
	
	// Cache of file headings
	headingCache map[string]map[string]bool
}

func NewLinkValidator() *LinkValidator {
	return &LinkValidator{
		fileCache:    make(map[string]bool),
		headingCache: make(map[string]map[string]bool),
	}
}

// ValidateAll checks all links in all files
func (v *LinkValidator) ValidateAll(
	ctx context.Context,
	files []models.FileInfo,
	rootPath string,
) ([]models.ConsistencyIssue, error) {
	var issues []models.ConsistencyIssue

	// Pre-populate heading cache
	for _, file := range files {
		headings := make(map[string]bool)
		for _, h := range file.Headings {
			headings[h.Anchor] = true
		}
		v.headingCache[file.RelativePath] = headings
	}

	// Pre-populate file existence cache
	for _, file := range files {
		v.fileCache[file.RelativePath] = true
	}

	// Validate each file's links
	for _, file := range files {
		select {
		case <-ctx.Done():
			return issues, ctx.Err()
		default:
		}

		fileDir := filepath.Dir(file.RelativePath)

		for _, link := range file.Links {
			// Skip external links
			if link.IsExternal {
				continue
			}

			// Skip empty links
			if link.Target == "" {
				continue
			}

			issue := v.validateLink(link, file.RelativePath, fileDir, rootPath)
			if issue != nil {
				issues = append(issues, *issue)
			}
		}
	}

	return issues, nil
}

func (v *LinkValidator) validateLink(
	link models.LinkInfo,
	sourceFile string,
	sourceDir string,
	rootPath string,
) *models.ConsistencyIssue {
	// Handle same-file anchor links
	if link.FilePart == "" && link.HasAnchor {
		return v.validateAnchor(link, sourceFile, sourceFile)
	}

	// Resolve relative path
	targetPath := link.FilePart
	if !filepath.IsAbs(targetPath) {
		targetPath = filepath.Join(sourceDir, targetPath)
		targetPath = filepath.Clean(targetPath)
	}

	// Normalize path separators
	targetPath = strings.ReplaceAll(targetPath, "\\", "/")

	// Check file existence
	if !v.fileExists(targetPath, rootPath) {
		// Try common alternatives
		suggestion := v.findSimilarFile(targetPath, rootPath)
		
		line := link.LineNumber
		col := link.ColumnNumber
		sourceText := link.FullMatch

		return &models.ConsistencyIssue{
			Id:           uuid.NewString(),
			Category:     models.CategoryBrokenLink,
			Severity:     models.SeverityError,
			Title:        "Broken link: file not found",
			Description:  fmt.Sprintf("Link to '%s' points to non-existent file", link.Target),
			FilePath:     sourceFile,
			LineNumber:   &line,
			ColumnNumber: &col,
			SourceText:   &sourceText,
			SuggestedFix: suggestion,
			RelatedFiles: []string{targetPath},
			AutoFixable:  suggestion != nil,
		}
	}

	// If there's an anchor, validate it
	if link.HasAnchor {
		return v.validateAnchor(link, sourceFile, targetPath)
	}

	return nil
}

func (v *LinkValidator) validateAnchor(
	link models.LinkInfo,
	sourceFile string,
	targetFile string,
) *models.ConsistencyIssue {
	headings, ok := v.headingCache[targetFile]
	if !ok {
		return nil // File not in cache, skip
	}

	if !headings[link.AnchorPart] {
		// Anchor not found
		suggestion := v.findSimilarAnchor(link.AnchorPart, headings)
		line := link.LineNumber
		sourceText := link.FullMatch

		return &models.ConsistencyIssue{
			Id:          uuid.NewString(),
			Category:    models.CategoryBrokenLink,
			Severity:    models.SeverityError,
			Title:       "Broken link: anchor not found",
			Description: fmt.Sprintf("Anchor '#%s' does not exist in '%s'", link.AnchorPart, targetFile),
			FilePath:    sourceFile,
			LineNumber:  &line,
			SourceText:  &sourceText,
			SuggestedFix: suggestion,
			RelatedFiles: []string{targetFile},
			AutoFixable:  suggestion != nil,
		}
	}

	return nil
}

func (v *LinkValidator) fileExists(relativePath, rootPath string) bool {
	// Check cache first
	if exists, ok := v.fileCache[relativePath]; ok {
		return exists
	}

	// Check filesystem
	fullPath := filepath.Join(rootPath, relativePath)
	_, err := os.Stat(fullPath)
	exists := err == nil
	v.fileCache[relativePath] = exists
	return exists
}

func (v *LinkValidator) findSimilarFile(target, rootPath string) *string {
	// Try with/without .md extension
	alternatives := []string{
		target + ".md",
		strings.TrimSuffix(target, ".md"),
	}

	for _, alt := range alternatives {
		if v.fileExists(alt, rootPath) {
			return &alt
		}
	}

	// Could implement fuzzy matching here
	return nil
}

func (v *LinkValidator) findSimilarAnchor(target string, headings map[string]bool) *string {
	// Simple similarity check
	for anchor := range headings {
		// Check if they're similar (Levenshtein distance)
		if levenshteinDistance(target, anchor) <= 2 {
			return &anchor
		}
	}
	return nil
}

// levenshteinDistance calculates edit distance between two strings
func levenshteinDistance(a, b string) int {
	if len(a) == 0 {
		return len(b)
	}
	if len(b) == 0 {
		return len(a)
	}

	if a[0] == b[0] {
		return levenshteinDistance(a[1:], b[1:])
	}

	insert := levenshteinDistance(a, b[1:])
	delete := levenshteinDistance(a[1:], b)
	replace := levenshteinDistance(a[1:], b[1:])

	return 1 + min(insert, min(delete, replace))
}

func min(a, b int) int {
	if a < b {
		return a
	}
	return b
}
```

---

## 7. Naming Validator

### `internal/services/consistency/naming_validator.go`

```go
package consistency

import (
	"context"
	"fmt"
	"path/filepath"
	"regexp"
	"strings"

	"github.com/google/uuid"
	"spec-manager/internal/models"
)

// ============================================================
// Naming Validator - Convention Enforcement
// ============================================================

type NamingValidator struct {
	rules []models.NamingRule
}

func NewNamingValidator() *NamingValidator {
	v := &NamingValidator{
		rules: make([]models.NamingRule, 0),
	}
	v.registerDefaultRules()
	return v
}

func (v *NamingValidator) registerDefaultRules() {
	// Rule 1: Markdown files should be lowercase with hyphens
	v.rules = append(v.rules, models.NamingRule{
		Pattern:     `^[a-z0-9]+(-[a-z0-9]+)*\.md$`,
		Description: "Markdown files should be lowercase-hyphenated (e.g., my-file.md)",
		Severity:    models.SeverityWarning,
		Applies: func(path string) bool {
			return strings.HasSuffix(path, ".md")
		},
	})

	// Rule 2: Folders should have two-digit prefix for ordering
	v.rules = append(v.rules, models.NamingRule{
		Pattern:     `^(\d{2}-[a-z0-9-]+|ideas|diagrams|features)$`,
		Description: "Folders should have two-digit prefix (e.g., 01-backend/) or be named 'ideas', 'diagrams', or 'features'",
		Severity:    models.SeverityInfo,
		Applies: func(path string) bool {
			// Only check directories
			return !strings.Contains(filepath.Base(path), ".")
		},
	})

	// Rule 3: Spec files should have numbered prefix
	v.rules = append(v.rules, models.NamingRule{
		Pattern:     `^(\d{2}-)?.+\.md$`,
		Description: "Spec files should have numbered prefix for ordering (e.g., 01-overview.md)",
		Severity:    models.SeverityInfo,
		Applies: func(path string) bool {
			return strings.HasSuffix(path, ".md") && 
			       !strings.Contains(path, "README") &&
			       !strings.Contains(path, "ideas/")
		},
	})

	// Rule 4: No spaces in filenames
	v.rules = append(v.rules, models.NamingRule{
		Pattern:     `^[^\s]+$`,
		Description: "Filenames should not contain spaces",
		Severity:    models.SeverityError,
		Applies:     func(path string) bool { return true },
	})

	// Rule 5: No special characters except hyphen and underscore
	v.rules = append(v.rules, models.NamingRule{
		Pattern:     `^[a-zA-Z0-9._-]+$`,
		Description: "Filenames should only contain alphanumeric characters, hyphens, underscores, and dots",
		Severity:    models.SeverityWarning,
		Applies:     func(path string) bool { return true },
	})

	// Rule 6: Consistency report files
	v.rules = append(v.rules, models.NamingRule{
		Pattern:     `^99-consistency-report\.md$`,
		Description: "Consistency report should be named '99-consistency-report.md'",
		Severity:    models.SeverityInfo,
		Applies: func(path string) bool {
			base := filepath.Base(path)
			return strings.Contains(strings.ToLower(base), "consistency")
		},
	})
}

// ValidateAll checks naming conventions for all files
func (v *NamingValidator) ValidateAll(
	ctx context.Context,
	files []models.FileInfo,
	rootPath string,
) ([]models.ConsistencyIssue, error) {
	var issues []models.ConsistencyIssue
	checkedDirs := make(map[string]bool)

	for _, file := range files {
		select {
		case <-ctx.Done():
			return issues, ctx.Err()
		default:
		}

		// Check file name
		fileName := filepath.Base(file.RelativePath)
		fileIssues := v.validateName(fileName, file.RelativePath, true)
		issues = append(issues, fileIssues...)

		// Check parent directories (only once per directory)
		dir := filepath.Dir(file.RelativePath)
		for dir != "." && dir != "" {
			if !checkedDirs[dir] {
				checkedDirs[dir] = true
				dirName := filepath.Base(dir)
				dirIssues := v.validateName(dirName, dir, false)
				issues = append(issues, dirIssues...)
			}
			dir = filepath.Dir(dir)
		}
	}

	return issues, nil
}

func (v *NamingValidator) validateName(
	name string,
	fullPath string,
	isFile bool,
) []models.ConsistencyIssue {
	var issues []models.ConsistencyIssue

	for _, rule := range v.rules {
		if !rule.Applies(fullPath) {
			continue
		}

		regex := regexp.MustCompile(rule.Pattern)
		if !regex.MatchString(name) {
			suggestion := v.generateSuggestion(name, rule.Pattern)
			
			issues = append(issues, models.ConsistencyIssue{
				Id:          uuid.NewString(),
				Category:    models.CategoryNamingConvention,
				Severity:    rule.Severity,
				Title:       "Naming convention violation",
				Description: fmt.Sprintf("'%s' does not match convention: %s", name, rule.Description),
				FilePath:    fullPath,
				SuggestedFix: suggestion,
				AutoFixable:  suggestion != nil,
			})
		}
	}

	return issues
}

func (v *NamingValidator) generateSuggestion(name, pattern string) *string {
	// Generate a suggested fix based on common issues
	suggested := name

	// Replace spaces with hyphens
	suggested = strings.ReplaceAll(suggested, " ", "-")

	// Convert to lowercase
	suggested = strings.ToLower(suggested)

	// Remove special characters
	suggested = regexp.MustCompile(`[^a-z0-9._-]`).ReplaceAllString(suggested, "")

	// Clean up multiple hyphens
	suggested = regexp.MustCompile(`-+`).ReplaceAllString(suggested, "-")

	if suggested != name {
		return &suggested
	}
	return nil
}
```

---

## 8. Duplicate Finder

### `internal/services/consistency/duplicate_finder.go`

```go
package consistency

import (
	"context"
	"fmt"
	"strings"

	"github.com/google/uuid"
	"spec-manager/internal/models"
)

// ============================================================
// Duplicate Finder - Detect Duplicate Definitions
// ============================================================

type DuplicateFinder struct{}

func NewDuplicateFinder() *DuplicateFinder {
	return &DuplicateFinder{}
}

// FindAll detects duplicate definitions across all files
func (d *DuplicateFinder) FindAll(
	ctx context.Context,
	files []models.FileInfo,
) ([]models.ConsistencyIssue, error) {
	var issues []models.ConsistencyIssue

	// Collect all definitions
	definitions := make(map[string][]models.DefinitionInfo)

	for _, file := range files {
		for _, def := range file.Definitions {
			key := d.normalizeKey(def.Type, def.NormalizedName)
			definitions[key] = append(definitions[key], def)
		}
	}

	// Find duplicates
	for key, defs := range definitions {
		select {
		case <-ctx.Done():
			return issues, ctx.Err()
		default:
		}

		if len(defs) > 1 {
			// Check if they're in different files (cross-file duplicates are more serious)
			fileSet := make(map[string]bool)
			for _, def := range defs {
				fileSet[def.FilePath] = true
			}

			severity := models.SeverityWarning
			if len(fileSet) > 1 {
				severity = models.SeverityError
			}

			// Create issue for each duplicate
			relatedFiles := make([]string, 0, len(defs))
			locations := make([]string, 0, len(defs))
			
			for _, def := range defs {
				relatedFiles = append(relatedFiles, def.FilePath)
				locations = append(locations, fmt.Sprintf("%s:%d", def.FilePath, def.LineNumber))
			}

			// Report on the first occurrence
			firstDef := defs[0]
			line := firstDef.LineNumber

			issues = append(issues, models.ConsistencyIssue{
				Id:          uuid.NewString(),
				Category:    models.CategoryDuplicateDefinition,
				Severity:    severity,
				Title:       fmt.Sprintf("Duplicate %s definition", firstDef.Type),
				Description: fmt.Sprintf("'%s' is defined %d times: %s", 
					firstDef.Name, len(defs), strings.Join(locations, ", ")),
				FilePath:    firstDef.FilePath,
				LineNumber:  &line,
				RelatedFiles: relatedFiles[1:], // Exclude the first file
				AutoFixable:  false,
			})
		}
	}

	return issues, nil
}

func (d *DuplicateFinder) normalizeKey(defType, name string) string {
	// Create a unique key for grouping definitions
	return strings.ToLower(defType + ":" + name)
}
```

---

## 9. Completeness Checker

### `internal/services/consistency/completeness.go`

```go
package consistency

import (
	"context"
	"fmt"
	"regexp"
	"strings"

	"github.com/google/uuid"
	"spec-manager/internal/models"
)

// ============================================================
// Completeness Checker - Required Section Validation
// ============================================================

type CompletenessChecker struct {
	requiredPatterns []requiredPattern
}

type requiredPattern struct {
	Name        string
	Pattern     *regexp.Regexp
	Description string
	Severity    models.IssueSeverity
	AppliesTo   func(path string) bool
}

func NewCompletenessChecker() *CompletenessChecker {
	c := &CompletenessChecker{
		requiredPatterns: make([]requiredPattern, 0),
	}
	c.registerPatterns()
	return c
}

func (c *CompletenessChecker) registerPatterns() {
	// Version header
	c.requiredPatterns = append(c.requiredPatterns, requiredPattern{
		Name:        "Version header",
		Pattern:     regexp.MustCompile(`(?i)\*\*Version:\*\*\s*[\d.]+`),
		Description: "Spec file should include version header (e.g., **Version:** 1.0.0)",
		Severity:    models.SeverityWarning,
		AppliesTo: func(path string) bool {
			return strings.HasSuffix(path, ".md") &&
			       !strings.Contains(path, "README") &&
			       !strings.Contains(path, "ideas/")
		},
	})

	// Status indicator
	c.requiredPatterns = append(c.requiredPatterns, requiredPattern{
		Name:        "Status indicator",
		Pattern:     regexp.MustCompile(`(?i)\*\*Status:\*\*\s*(Draft|Review|Approved|Deprecated)`),
		Description: "Spec file should include status indicator (Draft/Review/Approved/Deprecated)",
		Severity:    models.SeverityWarning,
		AppliesTo: func(path string) bool {
			return strings.HasSuffix(path, ".md") &&
			       !strings.Contains(path, "README") &&
			       !strings.Contains(path, "ideas/")
		},
	})

	// Last Updated date
	c.requiredPatterns = append(c.requiredPatterns, requiredPattern{
		Name:        "Last Updated date",
		Pattern:     regexp.MustCompile(`(?i)\*\*Last Updated:\*\*\s*\d{4}-\d{2}-\d{2}`),
		Description: "Spec file should include Last Updated date (YYYY-MM-DD format)",
		Severity:    models.SeverityInfo,
		AppliesTo: func(path string) bool {
			return strings.HasSuffix(path, ".md") &&
			       !strings.Contains(path, "README")
		},
	})

	// Overview section for overview files
	c.requiredPatterns = append(c.requiredPatterns, requiredPattern{
		Name:        "Overview section",
		Pattern:     regexp.MustCompile(`(?i)^#{1,2}\s*(1\.\s*)?Overview`),
		Description: "Overview files should contain an Overview section",
		Severity:    models.SeverityWarning,
		AppliesTo: func(path string) bool {
			return strings.Contains(path, "overview") ||
			       strings.Contains(path, "00-")
		},
	})

	// Cross-references section
	c.requiredPatterns = append(c.requiredPatterns, requiredPattern{
		Name:        "Cross-references section",
		Pattern:     regexp.MustCompile(`(?i)^#{1,2}\s*(\d+\.\s*)?(Cross-[Rr]eferences?|Related|See Also)`),
		Description: "Spec file should include a Cross-References section",
		Severity:    models.SeverityInfo,
		AppliesTo: func(path string) bool {
			return strings.HasSuffix(path, ".md") &&
			       !strings.Contains(path, "README") &&
			       !strings.Contains(path, "ideas/") &&
			       !strings.Contains(path, "glossary")
		},
	})

	// At least one heading
	c.requiredPatterns = append(c.requiredPatterns, requiredPattern{
		Name:        "Main heading",
		Pattern:     regexp.MustCompile(`^#\s+.+`),
		Description: "Markdown file should have at least one main heading",
		Severity:    models.SeverityWarning,
		AppliesTo: func(path string) bool {
			return strings.HasSuffix(path, ".md")
		},
	})
}

// CheckAll validates completeness of all files
func (c *CompletenessChecker) CheckAll(
	ctx context.Context,
	files []models.FileInfo,
) ([]models.ConsistencyIssue, error) {
	var issues []models.ConsistencyIssue

	for _, file := range files {
		select {
		case <-ctx.Done():
			return issues, ctx.Err()
		default:
		}

		fileIssues := c.checkFile(file)
		issues = append(issues, fileIssues...)
	}

	return issues, nil
}

func (c *CompletenessChecker) checkFile(file models.FileInfo) []models.ConsistencyIssue {
	var issues []models.ConsistencyIssue

	// Read file content for pattern matching
	content := c.buildContentFromHeadings(file)

	for _, pattern := range c.requiredPatterns {
		if !pattern.AppliesTo(file.RelativePath) {
			continue
		}

		if !pattern.Pattern.MatchString(content) {
			suggestion := c.generateTemplate(pattern.Name)
			
			issues = append(issues, models.ConsistencyIssue{
				Id:          uuid.NewString(),
				Category:    models.CategoryStructureViolation,
				Severity:    pattern.Severity,
				Title:       fmt.Sprintf("Missing required element: %s", pattern.Name),
				Description: pattern.Description,
				FilePath:    file.RelativePath,
				SuggestedFix: suggestion,
				AutoFixable:  suggestion != nil,
			})
		}
	}

	return issues
}

func (c *CompletenessChecker) buildContentFromHeadings(file models.FileInfo) string {
	var sb strings.Builder
	
	for _, h := range file.Headings {
		for i := 0; i < h.Level; i++ {
			sb.WriteString("#")
		}
		sb.WriteString(" ")
		sb.WriteString(h.Text)
		sb.WriteString("\n")
	}

	return sb.String()
}

func (c *CompletenessChecker) generateTemplate(patternName string) *string {
	templates := map[string]string{
		"Version header":      "**Version:** 1.0.0",
		"Status indicator":    "**Status:** Draft",
		"Last Updated date":   "**Last Updated:** 2026-01-27",
		"Cross-references section": "\n---\n\n## Cross-References\n\n- Related docs here\n",
	}

	if template, ok := templates[patternName]; ok {
		return &template
	}
	return nil
}
```

---

## 10. Health Scorer

### `internal/services/consistency/health_scorer.go`

```go
package consistency

import (
	"spec-manager/internal/models"
)

// ============================================================
// Health Scorer - Calculate Overall Health Score
// ============================================================

type HealthScorer struct {
	weights map[models.IssueCategory]float64
}

func NewHealthScorer() *HealthScorer {
	return &HealthScorer{
		weights: map[models.IssueCategory]float64{
			models.CategoryBrokenLink:          3.0, // Most critical
			models.CategoryMissingReference:    2.0,
			models.CategoryDuplicateDefinition: 2.0,
			models.CategoryNamingConvention:    1.0,
			models.CategoryStructureViolation:  1.5,
			models.CategoryOrphanedFile:        0.5,
			models.CategoryCircularDependency:  2.5,
			models.CategorySchemaMismatch:      2.0,
			models.CategoryVersionConflict:     1.0,
		},
	}
}

// Calculate computes the health score and grade
func (s *HealthScorer) Calculate(summary models.ReportSummary) (int, string) {
	if summary.FilesScanned == 0 {
		return 100, "A"
	}

	// Start with perfect score
	score := 100.0

	// Deduct points based on severity
	score -= float64(summary.ErrorCount) * 5.0
	score -= float64(summary.WarningCount) * 2.0
	score -= float64(summary.InfoCount) * 0.5

	// Deduct based on category weights
	for category, count := range summary.CategoryCounts {
		weight := s.weights[category]
		if weight == 0 {
			weight = 1.0
		}
		score -= float64(count) * weight * 0.5
	}

	// Bonus for resolved issues
	if summary.TotalIssues > 0 {
		resolvedRatio := float64(summary.ResolvedCount) / float64(summary.TotalIssues)
		score += resolvedRatio * 5.0
	}

	// Clamp score
	if score < 0 {
		score = 0
	}
	if score > 100 {
		score = 100
	}

	finalScore := int(score)
	grade := s.scoreToGrade(finalScore)

	return finalScore, grade
}

func (s *HealthScorer) scoreToGrade(score int) string {
	switch {
	case score >= 90:
		return "A"
	case score >= 80:
		return "B"
	case score >= 70:
		return "C"
	case score >= 60:
		return "D"
	default:
		return "F"
	}
}
```

---

## 11. Auto-Fixer

### `internal/services/consistency/fixer.go`

```go
package consistency

import (
	"strings"

	"github.com/google/uuid"
	"spec-manager/internal/models"
)

// ============================================================
// Auto-Fixer - Generate Automatic Fixes
// ============================================================

type Fixer struct{}

func NewFixer() *Fixer {
	return &Fixer{}
}

// GenerateFix creates an auto-fix for an issue if possible
func (f *Fixer) GenerateFix(issue *models.ConsistencyIssue) *models.AutoFix {
	if issue.SuggestedFix == nil {
		return nil
	}

	switch issue.Category {
	case models.CategoryBrokenLink:
		return f.fixBrokenLink(issue)
	case models.CategoryNamingConvention:
		return f.fixNamingConvention(issue)
	case models.CategoryStructureViolation:
		return f.fixMissingSection(issue)
	default:
		return nil
	}
}

func (f *Fixer) fixBrokenLink(issue *models.ConsistencyIssue) *models.AutoFix {
	if issue.SourceText == nil || issue.SuggestedFix == nil {
		return nil
	}

	// Replace the old link target with the suggested one
	oldLink := *issue.SourceText
	newLink := oldLink

	// Extract the link target from [text](target)
	if strings.Contains(oldLink, "](") {
		parts := strings.SplitN(oldLink, "](", 2)
		if len(parts) == 2 {
			newLink = parts[0] + "](" + *issue.SuggestedFix + ")"
		}
	}

	return &models.AutoFix{
		Id:          uuid.NewString(),
		FindingId:   issue.Id,
		FilePath:    issue.FilePath,
		LineNumber:  issue.LineNumber,
		OldContent:  *issue.SourceText,
		NewContent:  newLink,
		Confidence:  0.9,
		Description: "Fix broken link target",
	}
}

func (f *Fixer) fixNamingConvention(issue *models.ConsistencyIssue) *models.AutoFix {
	if issue.SuggestedFix == nil {
		return nil
	}

	return &models.AutoFix{
		Id:          uuid.NewString(),
		FindingId:   issue.Id,
		FilePath:    issue.FilePath,
		OldContent:  issue.FilePath,
		NewContent:  *issue.SuggestedFix,
		Confidence:  0.85,
		Description: "Rename file to follow convention",
	}
}

func (f *Fixer) fixMissingSection(issue *models.ConsistencyIssue) *models.AutoFix {
	if issue.SuggestedFix == nil {
		return nil
	}

	return &models.AutoFix{
		Id:          uuid.NewString(),
		FindingId:   issue.Id,
		FilePath:    issue.FilePath,
		OldContent:  "",
		NewContent:  *issue.SuggestedFix,
		Confidence:  0.95,
		Description: "Add missing section/header",
	}
}

// ApplyFixes applies selected fixes to files
func (f *Fixer) ApplyFixes(
	fixes []models.AutoFix,
	dryRun bool,
) (*models.FixResult, error) {
	result := &models.FixResult{
		Applied: make([]models.AutoFix, 0),
		Skipped: make([]models.AutoFix, 0),
		Failed:  make([]models.AutoFix, 0),
	}

	for _, fix := range fixes {
		// Skip low-confidence fixes
		if fix.Confidence < 0.85 {
			result.Skipped = append(result.Skipped, fix)
			continue
		}

		if dryRun {
			result.Applied = append(result.Applied, fix)
			continue
		}

		// TODO: Implement actual file modification
		// This would:
		// 1. Read the file
		// 2. Find the line/content to replace
		// 3. Apply the change
		// 4. Write the file back
		// 5. Track in version control

		result.Applied = append(result.Applied, fix)
	}

	return result, nil
}
```

---

## 12. Repository

### `internal/repository/consistency_repo.go`

```go
package repository

import (
	"context"
	"database/sql"
	"encoding/json"
	"time"

	"spec-manager/internal/models"
)

// ============================================================
// Consistency Report Repository
// ============================================================

type ConsistencyRepo struct {
	db *sql.DB
}

func NewConsistencyRepo(db *sql.DB) *ConsistencyRepo {
	return &ConsistencyRepo{db: db}
}

// SaveReport persists a consistency report
func (r *ConsistencyRepo) SaveReport(
	ctx context.Context,
	report *models.ConsistencyReport,
) error {
	summaryJSON, err := json.Marshal(report.Summary)
	if err != nil {
		return err
	}

	issuesJSON, err := json.Marshal(report.Issues)
	if err != nil {
		return err
	}

	var completedAt *string
	if report.CompletedAt != nil {
		t := report.CompletedAt.UTC().Format(time.RFC3339)
		completedAt = &t
	}

	_, err = r.db.ExecContext(ctx, `
		INSERT INTO ConsistencyReport (
			Id, ProjectId, Status, Score, Grade, 
			SummaryJson, FindingsJson, DurationMs, 
			TriggeredBy, CommitHash, CreatedAt, CompletedAt
		) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
	`,
		report.Id,
		report.ProjectId,
		string(report.Status),
		report.Score,
		report.Grade,
		string(summaryJSON),
		string(issuesJSON),
		report.Summary.ScanDurationMs,
		report.TriggeredBy,
		report.CommitHash,
		report.CreatedAt.UTC().Format(time.RFC3339),
		completedAt,
	)

	return err
}

// GetLatestReport retrieves the most recent report for a project
func (r *ConsistencyRepo) GetLatestReport(
	ctx context.Context,
	projectId string,
) (*models.ConsistencyReport, error) {
	row := r.db.QueryRowContext(ctx, `
		SELECT Id, ProjectId, Status, Score, Grade, 
		       SummaryJson, FindingsJson, DurationMs,
		       TriggeredBy, CommitHash, CreatedAt, CompletedAt
		FROM ConsistencyReport
		WHERE ProjectId = ?
		ORDER BY CreatedAt DESC
		LIMIT 1
	`, projectId)

	return r.scanReport(row)
}

// GetReportHistory retrieves past reports for a project
func (r *ConsistencyRepo) GetReportHistory(
	ctx context.Context,
	projectId string,
	limit int,
) ([]models.ConsistencyReport, error) {
	rows, err := r.db.QueryContext(ctx, `
		SELECT Id, ProjectId, Status, Score, Grade, 
		       SummaryJson, FindingsJson, DurationMs,
		       TriggeredBy, CommitHash, CreatedAt, CompletedAt
		FROM ConsistencyReport
		WHERE ProjectId = ?
		ORDER BY CreatedAt DESC
		LIMIT ?
	`, projectId, limit)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var reports []models.ConsistencyReport
	for rows.Next() {
		report, err := r.scanReportRow(rows)
		if err != nil {
			return nil, err
		}
		reports = append(reports, *report)
	}

	return reports, rows.Err()
}

// GetReport retrieves a specific report by ID
func (r *ConsistencyRepo) GetReport(
	ctx context.Context,
	reportId string,
) (*models.ConsistencyReport, error) {
	row := r.db.QueryRowContext(ctx, `
		SELECT Id, ProjectId, Status, Score, Grade, 
		       SummaryJson, FindingsJson, DurationMs,
		       TriggeredBy, CommitHash, CreatedAt, CompletedAt
		FROM ConsistencyReport
		WHERE Id = ?
	`, reportId)

	return r.scanReport(row)
}

// DeleteOldReports removes reports older than retention period
func (r *ConsistencyRepo) DeleteOldReports(
	ctx context.Context,
	projectId string,
	keepCount int,
) (int64, error) {
	result, err := r.db.ExecContext(ctx, `
		DELETE FROM ConsistencyReport
		WHERE ProjectId = ?
		AND Id NOT IN (
			SELECT Id FROM ConsistencyReport
			WHERE ProjectId = ?
			ORDER BY CreatedAt DESC
			LIMIT ?
		)
	`, projectId, projectId, keepCount)
	if err != nil {
		return 0, err
	}
	return result.RowsAffected()
}

func (r *ConsistencyRepo) scanReport(row *sql.Row) (*models.ConsistencyReport, error) {
	var report models.ConsistencyReport
	var status, summaryJSON, findingsJSON string
	var createdAt string
	var completedAt *string
	var commitHash *string

	err := row.Scan(
		&report.Id,
		&report.ProjectId,
		&status,
		&report.Score,
		&report.Grade,
		&summaryJSON,
		&findingsJSON,
		&report.Summary.ScanDurationMs,
		&report.TriggeredBy,
		&commitHash,
		&createdAt,
		&completedAt,
	)
	if err != nil {
		if err == sql.ErrNoRows {
			return nil, nil
		}
		return nil, err
	}

	report.Status = models.ReportStatus(status)
	report.CommitHash = commitHash

	if err := json.Unmarshal([]byte(summaryJSON), &report.Summary); err != nil {
		return nil, err
	}

	if err := json.Unmarshal([]byte(findingsJSON), &report.Issues); err != nil {
		return nil, err
	}

	report.CreatedAt, _ = time.Parse(time.RFC3339, createdAt)
	if completedAt != nil {
		t, _ := time.Parse(time.RFC3339, *completedAt)
		report.CompletedAt = &t
	}

	return &report, nil
}

func (r *ConsistencyRepo) scanReportRow(rows *sql.Rows) (*models.ConsistencyReport, error) {
	var report models.ConsistencyReport
	var status, summaryJSON, findingsJSON string
	var createdAt string
	var completedAt *string
	var commitHash *string

	err := rows.Scan(
		&report.Id,
		&report.ProjectId,
		&status,
		&report.Score,
		&report.Grade,
		&summaryJSON,
		&findingsJSON,
		&report.Summary.ScanDurationMs,
		&report.TriggeredBy,
		&commitHash,
		&createdAt,
		&completedAt,
	)
	if err != nil {
		return nil, err
	}

	report.Status = models.ReportStatus(status)
	report.CommitHash = commitHash

	if err := json.Unmarshal([]byte(summaryJSON), &report.Summary); err != nil {
		return nil, err
	}

	if err := json.Unmarshal([]byte(findingsJSON), &report.Issues); err != nil {
		return nil, err
	}

	report.CreatedAt, _ = time.Parse(time.RFC3339, createdAt)
	if completedAt != nil {
		t, _ := time.Parse(time.RFC3339, *completedAt)
		report.CompletedAt = &t
	}

	return &report, nil
}
```

---

## 13. HTTP Handler

### `internal/handlers/consistency_handler.go`

```go
package handlers

import (
	"net/http"

	"github.com/gin-gonic/gin"
	"spec-manager/internal/services/consistency"
	"spec-manager/internal/handlers/dto"
)

// ============================================================
// Consistency Check HTTP Handler
// ============================================================

type ConsistencyHandler struct {
	checker *consistency.CheckerService
}

func NewConsistencyHandler(checker *consistency.CheckerService) *ConsistencyHandler {
	return &ConsistencyHandler{checker: checker}
}

// RunCheck handles POST /api/v1/projects/:id/consistency/run
func (h *ConsistencyHandler) RunCheck(c *gin.Context) {
	projectId := c.Param("id")
	
	var req struct {
		ReportType string `json:"reportType"`
	}
	if err := c.ShouldBindJSON(&req); err != nil {
		dto.Error(c, &dto.AppError{
			HTTPStatus: http.StatusBadRequest,
			Code:       1001,
			Message:    "Invalid request body",
		})
		return
	}

	// Get project path from context/database
	projectPath := c.GetString("projectPath") // Set by middleware

	report, err := h.checker.RunCheck(
		c.Request.Context(),
		projectId,
		projectPath,
		"manual",
	)
	if err != nil {
		dto.Error(c, &dto.AppError{
			HTTPStatus: http.StatusInternalServerError,
			Code:       5080,
			Message:    "Report generation failed",
			Details:    err.Error(),
		})
		return
	}

	dto.Success(c, report)
}

// GetLatestReport handles GET /api/v1/projects/:id/consistency/latest
func (h *ConsistencyHandler) GetLatestReport(c *gin.Context) {
	projectId := c.Param("id")

	report, err := h.checker.GetLatestReport(c.Request.Context(), projectId)
	if err != nil {
		dto.Error(c, &dto.AppError{
			HTTPStatus: http.StatusInternalServerError,
			Code:       5080,
			Message:    "Failed to retrieve report",
		})
		return
	}

	if report == nil {
		dto.Error(c, &dto.AppError{
			HTTPStatus: http.StatusNotFound,
			Code:       5085,
			Message:    "No reports found for this project",
		})
		return
	}

	dto.Success(c, report)
}

// GetProgress handles GET /api/v1/consistency/reports/:id/progress (SSE)
func (h *ConsistencyHandler) GetProgress(c *gin.Context) {
	reportId := c.Param("id")

	c.Header("Content-Type", "text/event-stream")
	c.Header("Cache-Control", "no-cache")
	c.Header("Connection", "keep-alive")

	// Stream progress updates
	for {
		select {
		case <-c.Request.Context().Done():
			return
		default:
			progress := h.checker.GetProgress(reportId)
			if progress == nil {
				c.SSEvent("complete", gin.H{"status": "completed"})
				return
			}

			c.SSEvent("progress", progress)
			c.Writer.Flush()
		}
	}
}

// RegisterRoutes registers consistency check routes
func (h *ConsistencyHandler) RegisterRoutes(r *gin.RouterGroup) {
	r.POST("/projects/:id/consistency/run", h.RunCheck)
	r.GET("/projects/:id/consistency/latest", h.GetLatestReport)
	r.GET("/consistency/reports/:id/progress", h.GetProgress)
}
```

---

## 14. Cross-References

- **Spec Definition:** [01-consistency-checker.md](./01-consistency-checker.md)
- **Dashboard UI:** [03-consistency-dashboard.md](./03-consistency-dashboard.md)
- **Database Schema:** [Database Schema](../../07-database-design/01-schema.md)
