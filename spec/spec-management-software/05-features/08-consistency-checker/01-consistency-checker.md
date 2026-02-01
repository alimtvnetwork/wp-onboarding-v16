# Consistency Checker System

**Version:** 1.0.0  
**Status:** Draft  
**Last Updated:** 2026-01-27

---

## 1. Overview

The Consistency Checker System performs **automated validation** across all specification documents to ensure:

- **Cross-reference integrity** — All links point to existing files/sections
- **Schema alignment** — Database tables match API contracts
- **Terminology consistency** — Same terms used throughout
- **Completeness checks** — Required sections present in each spec
- **Health scoring** — Quantified spec quality metrics

This differs from the static `99-consistency-report.md` audit by providing **real-time, programmatic checks** that run on-demand or via scheduled jobs.

---

## 2. Report Types

### 2.1 Report Categories

| Category | Description | Frequency |
|----------|-------------|-----------|
| `cross-reference` | Validates internal links | On-demand / Pre-commit |
| `schema-api` | Matches DB schema to API endpoints | On-demand |
| `terminology` | Checks glossary term usage | Weekly |
| `completeness` | Validates required sections | On save |
| `full-health` | All checks combined | Daily / Manual |

### 2.2 Report Output Format

```typescript
interface ConsistencyReport {
  reportId: string;
  projectId: string;
  reportType: 'cross-reference' | 'schema-api' | 'terminology' | 'completeness' | 'full-health';
  generatedAt: string;           // ISO8601
  durationMs: number;
  score: number;                 // 0-100
  grade: 'A' | 'B' | 'C' | 'D' | 'F';
  summary: ReportSummary;
  findings: Finding[];
  recommendations: Recommendation[];
}

interface ReportSummary {
  totalFilesScanned: number;
  totalLinksChecked: number;
  validLinks: number;
  brokenLinks: number;
  warningsCount: number;
  errorsCount: number;
}

interface Finding {
  id: string;
  severity: 'error' | 'warning' | 'info';
  category: string;
  filePath: string;
  line?: number;
  message: string;
  suggestion?: string;
  autoFixable: boolean;
}

interface Recommendation {
  priority: 'high' | 'medium' | 'low';
  category: string;
  description: string;
  affectedFiles: string[];
  estimatedEffort: string;  // "5 min", "1 hour", etc.
}
```

---

## 3. Validation Rules

### 3.1 Cross-Reference Validation

**Rule CR-001: Internal Link Validity**
```
For each markdown link [text](path):
  1. If path starts with "http" → Skip (external link)
  2. If path starts with "#" → Validate section exists in current file
  3. If path contains "#" → Split into file path + section
  4. Resolve relative path from current file location
  5. Check target file exists
  6. If section specified, check heading exists in target
```

**Rule CR-002: Anchor Format**
```
Anchors must be lowercase, hyphenated versions of headings:
  "## Database Schema" → "#database-schema"
  "### 3.1 Core Entities" → "#31-core-entities"
```

**Rule CR-003: Orphan Detection**
```
Flag files that are never referenced by any other file.
Exclude: 00-overview.md, README.md, 99-*.md
```

### 3.2 Schema-API Alignment

**Rule SA-001: Table Coverage**
```
Each database table must have:
  - At least one GET endpoint
  - Corresponding TypeScript interface
  - Entry in glossary
```

**Rule SA-002: Field Consistency**
```
For each API response field:
  - Must match a database column name
  - Type must be compatible (TEXT→string, INTEGER→number)
  - Nullable fields must be marked optional in TypeScript
```

**Rule SA-003: Error Code Registry**
```
All error codes used in API specs must exist in error code registry.
Each code must have: constant name, numeric code, description.
```

### 3.3 Terminology Consistency

**Rule TC-001: Glossary Terms**
```
Terms defined in 05-glossary.md must be used consistently:
  - First occurrence in each file should match glossary definition
  - Alternate spellings flagged as warnings
  - Case sensitivity enforced for technical terms
```

**Rule TC-002: Naming Conventions**
```
Validate naming matches project conventions:
  - Database: PascalCase tables/columns
  - API: camelCase JSON fields
  - Files: lowercase-hyphenated.md
  - Folders: two-digit-prefix-name/
```

### 3.4 Completeness Checks

**Rule CC-001: Required Sections**
```
Each spec file must contain:
  - Version header
  - Status indicator
  - Last Updated date
  - At least one heading
  - Cross-references section (if applicable)
```

**Rule CC-002: Overview Files**
```
00-overview.md files must contain:
  - Summary section
  - List of related specs
  - Architecture diagram (if backend/frontend overview)
```

**Rule CC-003: API Endpoint Specs**
```
Each endpoint must document:
  - HTTP method + path
  - Request schema (if applicable)
  - Response schema
  - Error codes
  - Example request/response
```

### 3.5 RAG Format Validation

> **Cross-Reference:** See `18-rag-spec-guidelines.md` for complete RAG artifact formatting rules.

RAG format validation ensures that idea and instruction files conform to the chunking and indexing requirements of the RAG system.

**Rule RAG-001: Artifact File Naming**
```
Ideas: ideas/{nn}-idea-{slug}.md
Instructions: instructions/{nn}-instruction-{slug}.md
Where:
  - {nn} is a two-digit numeric prefix (01, 02, ...)
  - {slug} is lowercase-hyphenated descriptor
```

**Rule RAG-002: Required Frontmatter**
```
Each RAG artifact must include YAML frontmatter with:
  - title: Required string
  - status: draft | active | archived
  - created_at: ISO8601 date
  - tags: Array of classification tags
```

**Rule RAG-003: Chunk Boundaries**
```
Validate markdown structure for optimal chunking:
  - Each H2 section should be 100-500 words (ideal chunk size)
  - No orphan H3/H4 without parent H2
  - Code blocks must not exceed 50 lines
  - Tables must have header row
```

**Rule RAG-004: Cross-Reference Format**
```
RAG artifacts must reference related specs using anchor links:
  - Format: [Spec Name](../path/to/spec.md#section)
  - All internal links must resolve to existing files
  - Section anchors must match actual heading anchors
```

**Rule RAG-005: Embedding Readiness**
```
Content must be embedding-friendly:
  - Avoid excessive inline code in prose
  - Include descriptive headings (not just numbers)
  - Use complete sentences, not fragments
  - Avoid abbreviations without definitions
```

#### RAG Validator Implementation

```go
type RAGValidator interface {
    // Validate single artifact
    ValidateArtifact(ctx context.Context, filePath string) ([]Finding, error)
    
    // Validate all artifacts in project
    ValidateAllArtifacts(ctx context.Context, projectPath string) ([]Finding, error)
    
    // Check frontmatter completeness
    ValidateFrontmatter(content []byte) ([]Finding, error)
    
    // Analyze chunk boundaries
    AnalyzeChunkBoundaries(content []byte) (*ChunkAnalysis, error)
}

type ChunkAnalysis struct {
    TotalChunks       int      `json:"totalChunks"`
    AverageChunkSize  int      `json:"averageChunkSize"`  // words
    OversizedChunks   []string `json:"oversizedChunks"`   // section IDs
    UndersizedChunks  []string `json:"undersizedChunks"`  // section IDs
    OrphanedHeadings  []string `json:"orphanedHeadings"`  // H3/H4 without H2
    RecommendedSplits []string `json:"recommendedSplits"` // where to add H2
}
```

#### RAG Scoring Integration

RAG format validation contributes to the overall health score:

```go
type HealthScore struct {
    CrossReference   float64  // 0-100, weight: 25%
    SchemaAlignment  float64  // 0-100, weight: 20%
    Terminology      float64  // 0-100, weight: 15%
    Completeness     float64  // 0-100, weight: 15%
    Freshness        float64  // 0-100, weight: 10%
    RAGFormat        float64  // 0-100, weight: 15%  // NEW
}

func CalculateOverallScore(h HealthScore) float64 {
    return h.CrossReference*0.25 +
           h.SchemaAlignment*0.20 +
           h.Terminology*0.15 +
           h.Completeness*0.15 +
           h.Freshness*0.10 +
           h.RAGFormat*0.15
}
```

**RAG Format Score Calculation:**
```
score = 100 - deductions
Deductions:
  - Missing frontmatter field: -5 points per field
  - Invalid file naming: -3 points per file
  - Oversized chunk (>500 words): -2 points per chunk
  - Undersized chunk (<50 words): -1 point per chunk
  - Orphaned heading: -2 points per heading
  - Broken internal link: -3 points per link
```

---

## 4. Health Scoring

### 4.1 Score Calculation

```go
type HealthScore struct {
    CrossReference   float64  // 0-100, weight: 30%
    SchemaAlignment  float64  // 0-100, weight: 25%
    Terminology      float64  // 0-100, weight: 15%
    Completeness     float64  // 0-100, weight: 20%
    Freshness        float64  // 0-100, weight: 10%
}

func CalculateOverallScore(h HealthScore) float64 {
    return h.CrossReference*0.30 +
           h.SchemaAlignment*0.25 +
           h.Terminology*0.15 +
           h.Completeness*0.20 +
           h.Freshness*0.10
}

func ScoreToGrade(score float64) string {
    switch {
    case score >= 90: return "A"
    case score >= 80: return "B"
    case score >= 70: return "C"
    case score >= 60: return "D"
    default:          return "F"
    }
}
```

### 4.2 Category Scoring

**Cross-Reference Score:**
```
score = (validLinks / totalLinks) * 100
Deductions:
  - Each broken link: -2 points
  - Each orphan file: -1 point
```

**Schema-API Alignment Score:**
```
score = 100 - (mismatches * 5)
Deductions:
  - Missing endpoint for table: -5 points
  - Type mismatch: -3 points
  - Missing error code: -2 points
```

**Terminology Score:**
```
score = (consistentTerms / totalTerms) * 100
Deductions:
  - Undefined term usage: -2 points
  - Inconsistent capitalization: -1 point
```

**Completeness Score:**
```
score = (presentSections / requiredSections) * 100
Deductions:
  - Missing version header: -5 points
  - Missing cross-references: -3 points
  - Outdated (>30 days): -2 points
```

**Freshness Score:**
```
Based on LastUpdated dates across all files:
  - All files updated within 7 days: 100
  - Average age > 30 days: 70
  - Average age > 90 days: 50
  - Files older than 180 days: additional -5 per file
```

---

## 5. Auto-Fix Capabilities

### 5.1 Fixable Issues

| Finding Type | Auto-Fix Action |
|--------------|-----------------|
| Broken relative path | Suggest correct path |
| Missing anchor | Add anchor to heading |
| Incorrect anchor format | Reformat anchor |
| Missing version header | Insert template header |
| Outdated date | Update to current date |
| Case mismatch in term | Correct to glossary case |

### 5.2 Fix Application

```go
type AutoFix struct {
    FindingId    string
    FilePath     string
    LineNumber   int
    OldContent   string
    NewContent   string
    Confidence   float64  // 0-1, only apply if > 0.9
}

type FixResult struct {
    Applied      []AutoFix
    Skipped      []AutoFix  // Low confidence
    Failed       []AutoFix  // Could not apply
}

func ApplyFixes(fixes []AutoFix, dryRun bool) (*FixResult, error)
```

---

## 6. API Endpoints

### 6.1 Report Generation

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/projects/{id}/consistency/run` | Generate new report |
| GET | `/api/v1/projects/{id}/consistency/latest` | Get most recent report |
| GET | `/api/v1/projects/{id}/consistency/history` | List past reports |
| GET | `/api/v1/consistency/reports/{reportId}` | Get specific report |

### 6.2 Auto-Fix Operations

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/consistency/reports/{id}/preview-fixes` | Preview auto-fixes |
| POST | `/api/v1/consistency/reports/{id}/apply-fixes` | Apply selected fixes |

### 6.3 Request/Response Examples

#### POST `/api/v1/projects/{id}/consistency/run`

**Request:**
```json
{
  "reportType": "full-health",
  "includeAutoFixes": true
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "reportId": "rpt_abc123",
    "projectId": "prj_xyz",
    "reportType": "full-health",
    "generatedAt": "2026-01-27T15:00:00Z",
    "durationMs": 1250,
    "score": 87,
    "grade": "B",
    "summary": {
      "totalFilesScanned": 42,
      "totalLinksChecked": 156,
      "validLinks": 152,
      "brokenLinks": 4,
      "warningsCount": 8,
      "errorsCount": 4
    },
    "findings": [
      {
        "id": "f_001",
        "severity": "error",
        "category": "cross-reference",
        "filePath": "01-backend/03-api-endpoints.md",
        "line": 45,
        "message": "Broken link: ./02-database-schema.md#user-table",
        "suggestion": "Change to: ./02-database-schema.md#user",
        "autoFixable": true
      }
    ],
    "recommendations": [
      {
        "priority": "high",
        "category": "completeness",
        "description": "Add error code documentation to 5 API endpoints",
        "affectedFiles": [
          "01-backend/03-api-endpoints.md"
        ],
        "estimatedEffort": "30 min"
      }
    ]
  }
}
```

---

## 7. Database Schema

### 7.1 ConsistencyReport Table

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| Id | TEXT | PK | UUID primary key |
| ProjectId | TEXT | FK → Project.Id, NOT NULL | Target project |
| ReportType | TEXT | NOT NULL | Report category |
| Score | INTEGER | NOT NULL | 0-100 score |
| Grade | TEXT | NOT NULL | A-F grade |
| SummaryJson | TEXT | NOT NULL | JSON summary object |
| FindingsJson | TEXT | NOT NULL | JSON array of findings |
| RecommendationsJson | TEXT | NOT NULL | JSON array |
| DurationMs | INTEGER | NOT NULL | Generation time |
| CreatedAt | TEXT | NOT NULL | ISO8601 timestamp |

**Indexes:**
- `IX_ConsistencyReport_ProjectId` — on ProjectId
- `IX_ConsistencyReport_CreatedAt` — on CreatedAt (DESC)

**SQL:**
```sql
CREATE TABLE ConsistencyReport (
    Id TEXT PRIMARY KEY,
    ProjectId TEXT NOT NULL,
    ReportType TEXT NOT NULL CHECK (ReportType IN (
        'cross-reference', 'schema-api', 'terminology', 'completeness', 'full-health'
    )),
    Score INTEGER NOT NULL CHECK (Score >= 0 AND Score <= 100),
    Grade TEXT NOT NULL CHECK (Grade IN ('A', 'B', 'C', 'D', 'F')),
    SummaryJson TEXT NOT NULL,
    FindingsJson TEXT NOT NULL,
    RecommendationsJson TEXT NOT NULL,
    DurationMs INTEGER NOT NULL,
    CreatedAt TEXT NOT NULL,
    FOREIGN KEY (ProjectId) REFERENCES Project(Id) ON DELETE CASCADE
);

CREATE INDEX IX_ConsistencyReport_ProjectId ON ConsistencyReport(ProjectId);
CREATE INDEX IX_ConsistencyReport_CreatedAt ON ConsistencyReport(CreatedAt DESC);
```

### 7.2 ConsistencyFix Table

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| Id | TEXT | PK | UUID primary key |
| ReportId | TEXT | FK → ConsistencyReport.Id | Source report |
| FindingId | TEXT | NOT NULL | Reference to finding |
| FilePath | TEXT | NOT NULL | Target file |
| LineNumber | INTEGER | NULL | Target line |
| OldContent | TEXT | NOT NULL | Original content |
| NewContent | TEXT | NOT NULL | Fixed content |
| Confidence | REAL | NOT NULL | 0-1 confidence score |
| Status | TEXT | NOT NULL | 'pending', 'applied', 'skipped', 'failed' |
| AppliedAt | TEXT | NULL | When applied |
| AppliedById | TEXT | FK → User.Id | Who applied |

**SQL:**
```sql
CREATE TABLE ConsistencyFix (
    Id TEXT PRIMARY KEY,
    ReportId TEXT NOT NULL,
    FindingId TEXT NOT NULL,
    FilePath TEXT NOT NULL,
    LineNumber INTEGER,
    OldContent TEXT NOT NULL,
    NewContent TEXT NOT NULL,
    Confidence REAL NOT NULL CHECK (Confidence >= 0 AND Confidence <= 1),
    Status TEXT NOT NULL CHECK (Status IN ('pending', 'applied', 'skipped', 'failed')),
    AppliedAt TEXT,
    AppliedById TEXT,
    FOREIGN KEY (ReportId) REFERENCES ConsistencyReport(Id) ON DELETE CASCADE,
    FOREIGN KEY (AppliedById) REFERENCES User(Id) ON DELETE SET NULL
);

CREATE INDEX IX_ConsistencyFix_ReportId ON ConsistencyFix(ReportId);
CREATE INDEX IX_ConsistencyFix_Status ON ConsistencyFix(Status);
```

---

## 8. Scheduling & Triggers

### 8.1 Automatic Triggers

| Trigger | Report Type | Condition |
|---------|-------------|-----------|
| Pre-commit hook | cross-reference | Any .md file modified |
| Daily cron | full-health | 2:00 AM local time |
| Weekly cron | terminology | Sunday 3:00 AM |
| On file save | completeness | Single file check |

### 8.2 Cron Configuration

```go
type ConsistencySchedule struct {
    ProjectId       string
    ReportType      string
    CronExpression  string    // "0 2 * * *" for daily at 2 AM
    Enabled         bool
    LastRunAt       time.Time
    NextRunAt       time.Time
}
```

---

## 9. Service Interface

```go
type ConsistencyService interface {
    // Run a consistency check
    RunCheck(ctx context.Context, projectId string, reportType string) (*ConsistencyReport, error)
    
    // Get latest report for a project
    GetLatestReport(ctx context.Context, projectId string) (*ConsistencyReport, error)
    
    // Get report history
    GetReportHistory(ctx context.Context, projectId string, limit int) ([]ConsistencyReport, error)
    
    // Preview auto-fixes for a report
    PreviewFixes(ctx context.Context, reportId string) ([]AutoFix, error)
    
    // Apply selected fixes
    ApplyFixes(ctx context.Context, reportId string, fixIds []string, dryRun bool) (*FixResult, error)
    
    // Schedule periodic checks
    ScheduleCheck(ctx context.Context, schedule ConsistencySchedule) error
}

type LinkValidator interface {
    ValidateLink(ctx context.Context, sourceFile, targetPath string) (*LinkValidation, error)
    ValidateAllLinks(ctx context.Context, projectPath string) ([]LinkValidation, error)
}

type SchemaValidator interface {
    ValidateSchemaAlignment(ctx context.Context, schemaPath, apiPath string) ([]Finding, error)
}

type TerminologyValidator interface {
    ValidateTerms(ctx context.Context, glossaryPath, targetPath string) ([]Finding, error)
}
```

---

## 10. Frontend Integration

### 10.1 Dashboard Widget

```typescript
interface ConsistencyWidget {
  latestScore: number;
  grade: string;
  trend: 'up' | 'down' | 'stable';
  lastChecked: string;
  topIssues: Finding[];  // Max 3
}
```

### 10.2 React Query Hooks

```typescript
// Hook for consistency reports
function useConsistencyReport(projectId: string) {
  return useQuery({
    queryKey: ['consistency', projectId],
    queryFn: () => api.get(`/projects/${projectId}/consistency/latest`),
    staleTime: 5 * 60 * 1000,  // 5 minutes
  });
}

// Hook for running new report
function useRunConsistencyCheck() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (params: { projectId: string; reportType: string }) =>
      api.post(`/projects/${params.projectId}/consistency/run`, {
        reportType: params.reportType,
      }),
    onSuccess: (_, { projectId }) => {
      queryClient.invalidateQueries(['consistency', projectId]);
    },
  });
}

// Hook for applying fixes
function useApplyFixes() {
  return useMutation({
    mutationFn: (params: { reportId: string; fixIds: string[] }) =>
      api.post(`/consistency/reports/${params.reportId}/apply-fixes`, {
        fixIds: params.fixIds,
      }),
  });
}
```

### 10.3 Finding Display

```typescript
interface FindingCardProps {
  finding: Finding;
  onApplyFix?: () => void;
  onDismiss?: () => void;
}

// Severity colors (using design tokens)
const severityStyles = {
  error: 'border-destructive bg-destructive/10 text-destructive',
  warning: 'border-warning bg-warning/10 text-warning-foreground',
  info: 'border-muted bg-muted/50 text-muted-foreground',
};
```

---

## 11. Error Codes

| Code | Constant | Description |
|------|----------|-------------|
| 5080 | `ERR_REPORT_GENERATION_FAILED` | Report generation error |
| 5081 | `ERR_PROJECT_NOT_FOUND` | Project ID doesn't exist |
| 5082 | `ERR_INVALID_REPORT_TYPE` | Unknown report type |
| 5083 | `ERR_FIX_CONFLICT` | File changed since fix generated |
| 5084 | `ERR_FIX_ALREADY_APPLIED` | Fix was already applied |
| 5085 | `ERR_REPORT_NOT_FOUND` | Report ID doesn't exist |
| 5086 | `ERR_LOW_CONFIDENCE_FIX` | Fix confidence below threshold |

---

## 12. Configuration

### 12.1 Config Keys

| Key | Default | Description |
|-----|---------|-------------|
| `consistency.auto_run_enabled` | `true` | Enable scheduled checks |
| `consistency.daily_cron` | `0 2 * * *` | Daily check schedule |
| `consistency.min_score_threshold` | `70` | Alert if below |
| `consistency.max_reports_retained` | `30` | Reports to keep |
| `consistency.auto_fix_confidence` | `0.9` | Min confidence for auto-fix |
| `consistency.freshness_warning_days` | `30` | Days before freshness warning |

---

## 14. Iterative Consistency Loop (Loop-to-99%)

### 14.1 Overview

The Iterative Consistency Loop is a **self-improving validation system** that continuously checks specifications and generates fixes until the health score reaches **99% or higher**. This ensures specs remain production-ready at all times.

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                        ITERATIVE CONSISTENCY LOOP                               │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                 │
│  ┌──────────┐    ┌──────────┐    ┌──────────┐    ┌──────────┐                  │
│  │  START   │───▶│  SCAN    │───▶│  SCORE   │───▶│ SCORE    │                  │
│  │  CHECK   │    │  ALL     │    │  CALC    │    │ >= 99%?  │                  │
│  └──────────┘    │  SPECS   │    └──────────┘    └──────────┘                  │
│                  └──────────┘           │              │                        │
│                                         │         YES  │  NO                    │
│                                         │              ▼                        │
│  ┌──────────┐    ┌──────────┐    ┌──────────┐    ┌──────────┐                  │
│  │ COMPLETE │◀───│  FINAL   │◀───│  LOOP    │◀───│ GENERATE │                  │
│  │  REPORT  │    │  REPORT  │    │  AGAIN   │    │  FIXES   │                  │
│  └──────────┘    └──────────┘    └──────────┘    └──────────┘                  │
│                                         ▲              │                        │
│                                         │              ▼                        │
│                                         │        ┌──────────┐                   │
│                                         └────────│  APPLY   │                   │
│                                                  │  FIXES   │                   │
│                                                  └──────────┘                   │
│                                                                                 │
└─────────────────────────────────────────────────────────────────────────────────┘
```

### 14.2 Loop Configuration

| Key | Default | Description |
|-----|---------|-------------|
| `consistency.loop.target_score` | `99` | Target percentage to reach |
| `consistency.loop.max_iterations` | `10` | Maximum loop iterations |
| `consistency.loop.auto_apply_fixes` | `false` | Auto-apply high-confidence fixes |
| `consistency.loop.min_improvement_per_iter` | `2` | Min % improvement required |
| `consistency.loop.stall_threshold` | `3` | Consecutive no-improvement iterations before stopping |
| `consistency.loop.report_each_iteration` | `true` | Generate report per iteration |

### 14.3 Loop Execution Model

```go
type IterativeLoopConfig struct {
    TargetScore           int  `json:"targetScore"`           // Default: 99
    MaxIterations         int  `json:"maxIterations"`         // Default: 10
    AutoApplyFixes        bool `json:"autoApplyFixes"`        // Default: false
    MinImprovementPerIter int  `json:"minImprovementPerIter"` // Default: 2
    StallThreshold        int  `json:"stallThreshold"`        // Default: 3
    ReportEachIteration   bool `json:"reportEachIteration"`   // Default: true
}

type IterationResult struct {
    Iteration       int                `json:"iteration"`
    Score           int                `json:"score"`
    ScoreDelta      int                `json:"scoreDelta"`
    FindingsCount   int                `json:"findingsCount"`
    FixesGenerated  int                `json:"fixesGenerated"`
    FixesApplied    int                `json:"fixesApplied"`
    DurationMs      int                `json:"durationMs"`
    Report          *ConsistencyReport `json:"report,omitempty"`
}

type LoopResult struct {
    LoopId           string            `json:"loopId"`
    ProjectId        string            `json:"projectId"`
    StartedAt        time.Time         `json:"startedAt"`
    CompletedAt      time.Time         `json:"completedAt"`
    TotalIterations  int               `json:"totalIterations"`
    InitialScore     int               `json:"initialScore"`
    FinalScore       int               `json:"finalScore"`
    TargetReached    bool              `json:"targetReached"`
    StopReason       LoopStopReason    `json:"stopReason"`
    Iterations       []IterationResult `json:"iterations"`
    FinalReport      *ConsistencyReport `json:"finalReport"`
    TotalFixesApplied int              `json:"totalFixesApplied"`
}

type LoopStopReason string

const (
    StopReasonTargetReached LoopStopReason = "target_reached"
    StopReasonMaxIterations LoopStopReason = "max_iterations"
    StopReasonStalled       LoopStopReason = "stalled"
    StopReasonManualStop    LoopStopReason = "manual_stop"
    StopReasonError         LoopStopReason = "error"
)
```

### 14.4 Loop Service Implementation

```go
func (s *ConsistencyService) RunIterativeLoop(
    ctx context.Context,
    projectId string,
    config IterativeLoopConfig,
) (*LoopResult, error) {
    loopId := uuid.New().String()
    startTime := time.Now()
    
    result := &LoopResult{
        LoopId:      loopId,
        ProjectId:   projectId,
        StartedAt:   startTime,
        Iterations:  make([]IterationResult, 0),
    }
    
    // Initial scan
    report, err := s.RunFullHealthCheck(ctx, projectId)
    if isNotEmpty(err) {
        return nil, err
    }
    
    result.InitialScore = report.Score
    currentScore := report.Score
    stallCount := 0
    
    for i := 1; i <= config.MaxIterations; i++ {
        iterStart := time.Now()
        
        // Check if target reached
        if currentScore >= config.TargetScore {
            result.StopReason = StopReasonTargetReached
            result.TargetReached = true
            break
        }
        
        // Generate fixes for current findings
        fixes, err := s.GenerateAutoFixes(ctx, report)
        if isNotEmpty(err) {
            s.logger.Error("Fix generation failed", "iteration", i, "error", err)
        }
        
        appliedCount := 0
        if config.AutoApplyFixes && len(fixes) > 0 {
            appliedCount, err = s.ApplyHighConfidenceFixes(ctx, fixes)
            if isNotEmpty(err) {
                s.logger.Warn("Some fixes failed", "error", err)
            }
        }
        
        // Re-scan after fixes
        report, err = s.RunFullHealthCheck(ctx, projectId)
        if isNotEmpty(err) {
            result.StopReason = StopReasonError
            break
        }
        
        scoreDelta := report.Score - currentScore
        
        iterResult := IterationResult{
            Iteration:      i,
            Score:          report.Score,
            ScoreDelta:     scoreDelta,
            FindingsCount:  len(report.Findings),
            FixesGenerated: len(fixes),
            FixesApplied:   appliedCount,
            DurationMs:     int(time.Since(iterStart).Milliseconds()),
        }
        
        if config.ReportEachIteration {
            iterResult.Report = report
        }
        
        result.Iterations = append(result.Iterations, iterResult)
        result.TotalFixesApplied += appliedCount
        
        // Check for stall
        if scoreDelta < config.MinImprovementPerIter {
            stallCount++
            if stallCount >= config.StallThreshold {
                result.StopReason = StopReasonStalled
                break
            }
        } else {
            stallCount = 0
        }
        
        currentScore = report.Score
        
        // Emit progress event via SSE
        s.emitLoopProgress(ctx, loopId, iterResult)
    }
    
    if result.StopReason == "" {
        result.StopReason = StopReasonMaxIterations
    }
    
    result.CompletedAt = time.Now()
    result.TotalIterations = len(result.Iterations)
    result.FinalScore = currentScore
    result.FinalReport = report
    
    // Persist loop result
    s.loopRepo.SaveLoopResult(ctx, result)
    
    return result, nil
}
```

### 14.5 API Endpoints

#### Start Iterative Loop

```
POST /api/v1/projects/:projectId/consistency/loop
```

**Request:**
```json
{
  "targetScore": 99,
  "maxIterations": 10,
  "autoApplyFixes": false,
  "reportEachIteration": true
}
```

**Response (202 Accepted):**
```json
{
  "success": true,
  "data": {
    "loopId": "loop-uuid",
    "status": "running",
    "initialScore": 82,
    "targetScore": 99,
    "estimatedIterations": 5
  }
}
```

#### Get Loop Status

```
GET /api/v1/projects/:projectId/consistency/loop/:loopId
```

**Response:**
```json
{
  "success": true,
  "data": {
    "loopId": "loop-uuid",
    "status": "running",
    "currentIteration": 3,
    "currentScore": 91,
    "targetScore": 99,
    "iterations": [
      { "iteration": 1, "score": 82, "scoreDelta": 0, "fixesApplied": 0 },
      { "iteration": 2, "score": 87, "scoreDelta": 5, "fixesApplied": 3 },
      { "iteration": 3, "score": 91, "scoreDelta": 4, "fixesApplied": 2 }
    ]
  }
}
```

#### Stream Loop Progress (SSE)

```
GET /api/v1/projects/:projectId/consistency/loop/:loopId/stream
```

**SSE Events:**
```
event: iteration_complete
data: {"iteration": 4, "score": 94, "scoreDelta": 3, "fixesApplied": 2}

event: loop_complete
data: {"finalScore": 99, "targetReached": true, "totalIterations": 5}
```

#### Stop Running Loop

```
POST /api/v1/projects/:projectId/consistency/loop/:loopId/stop
```

### 14.6 Detailed Report Generation

Each iteration generates a detailed report with actionable items:

```typescript
interface IterationDetailedReport extends ConsistencyReport {
  iteration: number;
  progressFromInitial: number;  // Percentage improvement from start
  remainingToTarget: number;    // Points needed to reach target
  blockers: BlockerAnalysis[];  // Issues preventing 99%
  fixSuggestions: FixSuggestion[];
  estimatedIterationsRemaining: number;
}

interface BlockerAnalysis {
  category: string;
  impact: number;  // Points deducted
  description: string;
  affectedFiles: string[];
  isAutoFixable: boolean;
  manualSteps?: string[];
}

interface FixSuggestion {
  findingId: string;
  confidence: number;  // 0.0 - 1.0
  fixType: 'add_reference' | 'fix_link' | 'add_section' | 'rename' | 'update_term';
  description: string;
  beforePreview: string;
  afterPreview: string;
  filePath: string;
  lineNumber?: number;
}
```

### 14.7 Database Schema

```go
// ConsistencyLoop tracks iterative loop executions
type ConsistencyLoop struct {
    BaseModel
    ProjectId         string         `gorm:"type:text;not null;index" json:"projectId"`
    InitialScore      int            `gorm:"not null" json:"initialScore"`
    TargetScore       int            `gorm:"not null" json:"targetScore"`
    FinalScore        int            `gorm:"default:0" json:"finalScore"`
    TargetReached     bool           `gorm:"default:false" json:"targetReached"`
    TotalIterations   int            `gorm:"default:0" json:"totalIterations"`
    TotalFixesApplied int            `gorm:"default:0" json:"totalFixesApplied"`
    StopReason        string         `gorm:"type:text" json:"stopReason"`
    Config            datatypes.JSON `gorm:"type:text" json:"config"`
    StartedAt         time.Time      `gorm:"not null" json:"startedAt"`
    CompletedAt       *time.Time     `gorm:"type:text" json:"completedAt"`
    
    // Relations
    Project    Project              `gorm:"foreignKey:ProjectId;constraint:OnDelete:CASCADE"`
    Iterations []ConsistencyLoopIteration `gorm:"foreignKey:LoopId;constraint:OnDelete:CASCADE"`
}

// ConsistencyLoopIteration tracks each iteration
type ConsistencyLoopIteration struct {
    BaseModel
    LoopId         string         `gorm:"type:text;not null;index" json:"loopId"`
    Iteration      int            `gorm:"not null" json:"iteration"`
    Score          int            `gorm:"not null" json:"score"`
    ScoreDelta     int            `gorm:"default:0" json:"scoreDelta"`
    FindingsCount  int            `gorm:"default:0" json:"findingsCount"`
    FixesGenerated int            `gorm:"default:0" json:"fixesGenerated"`
    FixesApplied   int            `gorm:"default:0" json:"fixesApplied"`
    DurationMs     int            `gorm:"default:0" json:"durationMs"`
    ReportJson     datatypes.JSON `gorm:"type:text" json:"reportJson"`
    
    // Relations
    Loop ConsistencyLoop `gorm:"foreignKey:LoopId;constraint:OnDelete:CASCADE"`
}

func (ConsistencyLoop) TableName() string          { return "ConsistencyLoop" }
func (ConsistencyLoopIteration) TableName() string { return "ConsistencyLoopIteration" }
```

### 14.8 Acceptance Criteria

### Cross-Reference Validation (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| CR-001 | Internal markdown links validated for existence | Critical | Link scan test |
| CR-002 | Section anchors validated against target headings | Critical | Anchor test |
| CR-003 | Relative paths resolved from current file location | Critical | Path resolution test |
| CR-004 | External links (http/https) skipped | High | Skip external test |
| CR-005 | Orphan files (never referenced) flagged | High | Orphan detection test |
| CR-006 | Broken link count accurate in report | High | Count accuracy test |

### Schema-API Alignment (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| SA-001 | Each database table has at least one GET endpoint | Critical | Coverage test |
| SA-002 | API field types match database column types | Critical | Type mapping test |
| SA-003 | Nullable columns marked optional in TypeScript | High | Nullable test |
| SA-004 | Error codes in API specs exist in registry | High | Error code test |
| SA-005 | Missing endpoint flagged as finding | High | Missing endpoint test |

### Terminology Validation (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| TV-001 | Glossary terms used consistently across specs | Critical | Term scan test |
| TV-002 | Case sensitivity enforced for technical terms | High | Case test |
| TV-003 | Naming conventions enforced (PascalCase tables, camelCase JSON) | High | Convention test |
| TV-004 | Alternate spellings flagged as warnings | Medium | Spelling test |

### Completeness Checks (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| CC-001 | Version header present in all specs | Critical | Header test |
| CC-002 | Status indicator present | Critical | Status test |
| CC-003 | Last Updated date present | Critical | Date test |
| CC-004 | At least one heading present | High | Heading test |
| CC-005 | 00-overview.md contains required sections | High | Overview test |
| CC-006 | API specs document all required fields | High | API doc test |

### RAG Format Validation (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| RF-001 | Idea files follow `{nn}-idea-{slug}.md` pattern | Critical | Naming test |
| RF-002 | Instruction files follow `{nn}-instruction-{slug}.md` pattern | Critical | Naming test |
| RF-003 | YAML frontmatter contains required fields | High | Frontmatter test |
| RF-004 | Chunk boundaries validated (100-500 words per H2) | High | Chunk size test |
| RF-005 | Code blocks ≤50 lines | Medium | Code block test |
| RF-006 | No orphan H3/H4 without parent H2 | Medium | Heading structure test |

### Health Scoring (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| HS-001 | Score calculated from weighted components | Critical | Score calc test |
| HS-002 | Grade derived from score (A≥90, B≥80, C≥70, D≥60, F<60) | Critical | Grade test |
| HS-003 | Cross-reference weight: 25% | High | Weight test |
| HS-004 | Schema-API weight: 20% | High | Weight test |
| HS-005 | RAG format weight: 15% | High | Weight test |
| HS-006 | Score deductions applied per finding type | High | Deduction test |

### Auto-Fix (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| AF-001 | Broken relative paths suggest corrections | Critical | Path fix test |
| AF-002 | Incorrect anchor format auto-fixed | High | Anchor fix test |
| AF-003 | Missing version header inserted from template | High | Header fix test |
| AF-004 | Outdated date updated to current | Medium | Date fix test |
| AF-005 | Fixes with confidence <0.9 skipped | High | Confidence gate test |
| AF-006 | Preview-fixes endpoint shows proposed changes | High | Preview test |
| AF-007 | Apply-fixes endpoint executes selected fixes | High | Apply test |

### Consistency Loop (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| CL-001 | Loop starts with baseline health check | Critical | Baseline test |
| CL-002 | Each iteration generates and applies fixes | Critical | Iteration test |
| CL-003 | High-confidence fixes auto-applied when enabled | High | Auto-apply test |
| CL-004 | Score recalculated after each iteration | High | Score refresh test |
| CL-005 | Loop stops when target score (99%) reached | Critical | Target stop test |
| CL-006 | Loop stops after max iterations (default 10) | Critical | Max iter test |
| CL-007 | Loop stops if stalled (no improvement for 3 iterations) | High | Stall detection test |
| CL-008 | SSE stream emits progress events | High | SSE test |
| CL-009 | Manual stop endpoint available | Medium | Stop test |
| CL-010 | Blocker analysis identifies issues preventing 99% | High | Blocker test |

### API Endpoints (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| AE-001 | POST /consistency/run generates new report | Critical | Run test |
| AE-002 | GET /consistency/latest returns most recent report | Critical | Latest test |
| AE-003 | GET /consistency/history lists past reports | High | History test |
| AE-004 | GET /reports/{id} returns specific report | High | Get report test |
| AE-005 | Report stored in ConsistencyReport table | High | Persistence test |

### Error Handling (99% Required)

| ID | Criterion | Priority | Validation Method |
|----|-----------|----------|-------------------|
| EH-001 | ERR_REPORT_NOT_FOUND (9001) for missing report | Critical | Error code test |
| EH-002 | ERR_FIX_FAILED (9002) when auto-fix fails | Critical | Error code test |
| EH-003 | ERR_LOOP_TIMEOUT (9003) when loop exceeds time limit | High | Timeout test |
| EH-004 | All errors include reportId for debugging | High | Error context test |

---

## 13. Cross-References

- **Database Schema:** [01-schema.md](../../07-database-design/01-schema.md)
- **Consistency Dashboard:** [03-consistency-dashboard.md](./03-consistency-dashboard.md)
- **Implementation:** [02-consistency-checker-implementation.md](./02-consistency-checker-implementation.md)
- **Static Audit Report:** [99-consistency-report.md](../../99-consistency-report.md)
