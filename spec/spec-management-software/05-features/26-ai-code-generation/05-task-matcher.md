# 05. Task Matcher

**Version:** 1.0.0  
**Status:** Planned  
**Updated:** 2026-01-29  
**Parent:** [Overview](./00-overview.md)

---

## Purpose

Define the reusability matching algorithm that searches for existing generated code when a new task is requested. This reduces redundant code generation and ensures consistency across similar operations.

---

## Matching Strategy

```
New Task Request
       │
       ▼
┌──────────────────────┐
│ 1. Extract Tags      │
│ From task description│
└──────────┬───────────┘
           │
           ▼
┌──────────────────────┐
│ 2. Query Database    │
│ Search by tag overlap│
└──────────┬───────────┘
           │
           ▼
┌──────────────────────┐
│ 3. Score Matches     │
│ Rank by similarity   │
└──────────┬───────────┘
           │
           ▼
┌──────────────────────┐
│ 4. Evaluate Best     │
│ Can it be adapted?   │
└──────────┬───────────┘
           │
     ┌─────┴─────┐
     │           │
    YES         NO
     │           │
     ▼           ▼
┌─────────┐  ┌─────────┐
│  Reuse  │  │Generate │
│ Existing│  │   New   │
└─────────┘  └─────────┘
```

---

## Tag Extraction

### Automatic Tag Generation

The AI extracts tags from task descriptions using pattern matching:

```go
type TagExtractor struct {
    patterns map[string][]string
}

func NewTagExtractor() *TagExtractor {
    return &TagExtractor{
        patterns: map[string][]string{
            // Operation types
            "rename|renaming":     {"rename", "filesystem"},
            "delete|remove":       {"delete", "filesystem"},
            "copy|duplicate":      {"copy", "filesystem"},
            "move":                {"move", "filesystem"},
            "create|new":          {"create", "filesystem"},
            "update|modify|change":{"update", "filesystem"},
            
            // Scope indicators
            "all files|every file":    {"batch-operation", "multi-file"},
            "directory|folder":        {"directory", "recursive"},
            "pattern|matching|regex":  {"pattern-matching"},
            
            // Data operations
            "parse|parsing":       {"parse", "data-processing"},
            "transform|convert":   {"transform", "data-processing"},
            "validate|check":      {"validate", "data-processing"},
            
            // Specific domains
            "index|overview":      {"index-management"},
            "markdown|.md":        {"markdown"},
            "json|.json":          {"json"},
            "lowercase|uppercase": {"case-transform"},
            "cross-reference":     {"cross-reference", "index-management"},
        },
    }
}

func (te *TagExtractor) Extract(description string) []string {
    tags := make(map[string]bool)
    desc := strings.ToLower(description)
    
    for pattern, extractedTags := range te.patterns {
        re := regexp.MustCompile(pattern)
        if re.MatchString(desc) {
            for _, tag := range extractedTags {
                tags[tag] = true
            }
        }
    }
    
    result := make([]string, 0, len(tags))
    for tag := range tags {
        result = append(result, tag)
    }
    
    sort.Strings(result)
    return result
}
```

### Tag Taxonomy

| Category | Tags |
|----------|------|
| Operation Type | `create`, `read`, `update`, `delete`, `rename`, `move`, `copy` |
| Scope | `single-file`, `multi-file`, `directory`, `recursive` |
| Complexity | `simple`, `moderate`, `complex`, `batch-operation` |
| Data Type | `markdown`, `json`, `yaml`, `text`, `binary` |
| Domain | `index-management`, `cross-reference`, `dependency-analysis` |
| Transform | `case-transform`, `pattern-matching`, `content-replace` |

---

## Database Query

### Tag Overlap Query

```sql
-- Find reusable tasks with matching tags
SELECT 
    t.Id,
    t.TaskName,
    t.GolangCode,
    t.FilePath,
    t.ComplexityScore,
    t.ExecutionCount,
    t.SuccessCount,
    COUNT(tt.TagName) AS TagOverlap,
    GROUP_CONCAT(tt.TagName) AS MatchedTags
FROM TempCodingTasks t
JOIN TaskTags tt ON t.Id = tt.TaskId
WHERE tt.TagName IN (?, ?, ?, ?)  -- Input tags
  AND t.IsReusable = 1
GROUP BY t.Id
HAVING TagOverlap >= 2  -- Minimum overlap threshold
ORDER BY 
    TagOverlap DESC,
    t.SuccessCount DESC,
    t.ExecutionCount DESC
LIMIT 5;
```

### GORM Implementation

```go
type TaskMatcher struct {
    db *gorm.DB
}

type MatchResult struct {
    Task        TempCodingTask
    TagOverlap  int
    MatchedTags []string
    Score       float64
}

func (tm *TaskMatcher) FindReusableCode(requestTags []string, minOverlap int) ([]MatchResult, error) {
    if len(requestTags) == 0 {
        return nil, nil
    }
    
    var results []struct {
        TempCodingTask
        TagOverlap  int
        MatchedTags string
    }
    
    err := tm.db.Model(&TempCodingTask{}).
        Select(`
            temp_coding_tasks.*,
            COUNT(task_tags.tag_name) as tag_overlap,
            GROUP_CONCAT(task_tags.tag_name) as matched_tags
        `).
        Joins("JOIN task_tags ON temp_coding_tasks.id = task_tags.task_id").
        Where("task_tags.tag_name IN ?", requestTags).
        Where("temp_coding_tasks.is_reusable = ?", true).
        Group("temp_coding_tasks.id").
        Having("tag_overlap >= ?", minOverlap).
        Order("tag_overlap DESC, success_count DESC").
        Limit(5).
        Find(&results).Error
    
    if err != nil {
        return nil, err
    }
    
    matches := make([]MatchResult, len(results))
    for i, r := range results {
        matches[i] = MatchResult{
            Task:        r.TempCodingTask,
            TagOverlap:  r.TagOverlap,
            MatchedTags: strings.Split(r.MatchedTags, ","),
            Score:       calculateMatchScore(r.TempCodingTask, r.TagOverlap, len(requestTags)),
        }
    }
    
    return matches, nil
}
```

---

## Similarity Scoring

### Score Calculation

```go
func calculateMatchScore(task TempCodingTask, overlap int, totalRequestTags int) float64 {
    // Base score from tag overlap (0-50 points)
    overlapRatio := float64(overlap) / float64(totalRequestTags)
    overlapScore := overlapRatio * 50.0
    
    // Success rate bonus (0-25 points)
    var successRate float64
    if task.ExecutionCount > 0 {
        successRate = float64(task.SuccessCount) / float64(task.ExecutionCount)
    }
    successScore := successRate * 25.0
    
    // Usage frequency bonus (0-15 points)
    // More executions = more reliable
    frequencyScore := math.Min(float64(task.ExecutionCount)/10.0, 1.0) * 15.0
    
    // Recency bonus (0-10 points)
    // Prefer recently used code
    var recencyScore float64
    if task.LastExecutedAt != nil {
        daysSinceExecution := time.Since(*task.LastExecutedAt).Hours() / 24
        recencyScore = math.Max(0, 10.0-(daysSinceExecution/30.0)*10.0)
    }
    
    return overlapScore + successScore + frequencyScore + recencyScore
}
```

### Score Thresholds

| Score Range | Action |
|-------------|--------|
| 80-100 | Direct reuse (high confidence) |
| 60-79 | Reuse with minor adaptation |
| 40-59 | Consider reuse, may need modification |
| 0-39 | Generate new code |

---

## Adaptation Logic

When a match is found but parameters differ:

```go
type AdaptationResult struct {
    CanAdapt       bool
    Modifications  []string
    OriginalCode   string
    AdaptedCode    string
    Confidence     float64
}

func (tm *TaskMatcher) AdaptCode(original TempCodingTask, newRequest TaskRequest) (*AdaptationResult, error) {
    result := &AdaptationResult{
        OriginalCode: original.GolangCode,
    }
    
    // Analyze differences
    modifications := []string{}
    
    // Check if target directory differs
    if original.Metadata != nil {
        var meta map[string]interface{}
        json.Unmarshal([]byte(*original.Metadata), &meta)
        
        if origDir, ok := meta["targetDir"].(string); ok {
            if origDir != newRequest.TargetDirectory {
                modifications = append(modifications, 
                    fmt.Sprintf("Update target directory: %s -> %s", origDir, newRequest.TargetDirectory))
            }
        }
    }
    
    // Check if pattern differs
    // ... additional adaptation checks
    
    if len(modifications) <= 3 {
        result.CanAdapt = true
        result.Modifications = modifications
        result.Confidence = 1.0 - (float64(len(modifications)) * 0.2)
        
        // Generate adapted code using LLM
        result.AdaptedCode = tm.generateAdaptation(original.GolangCode, modifications)
    } else {
        result.CanAdapt = false
        result.Confidence = 0.0
    }
    
    return result, nil
}
```

---

## Matching Pipeline

```go
type MatchingPipeline struct {
    extractor *TagExtractor
    matcher   *TaskMatcher
    threshold float64
}

func (mp *MatchingPipeline) Process(request TaskRequest) (*PipelineResult, error) {
    result := &PipelineResult{}
    
    // Step 1: Extract tags from request
    tags := mp.extractor.Extract(request.Description)
    result.ExtractedTags = tags
    
    if len(tags) == 0 {
        result.Action = ActionGenerateNew
        result.Reason = "No tags extracted from description"
        return result, nil
    }
    
    // Step 2: Find matches
    matches, err := mp.matcher.FindReusableCode(tags, 2)
    if err != nil {
        return nil, err
    }
    
    if len(matches) == 0 {
        result.Action = ActionGenerateNew
        result.Reason = "No matching tasks found"
        return result, nil
    }
    
    // Step 3: Evaluate best match
    bestMatch := matches[0]
    result.BestMatch = &bestMatch
    
    if bestMatch.Score >= mp.threshold {
        // Try to adapt
        adaptation, err := mp.matcher.AdaptCode(bestMatch.Task, request)
        if err != nil {
            return nil, err
        }
        
        if adaptation.CanAdapt {
            result.Action = ActionReuseWithAdaptation
            result.Adaptation = adaptation
        } else {
            result.Action = ActionGenerateNew
            result.Reason = "Code requires too many modifications"
        }
    } else {
        result.Action = ActionGenerateNew
        result.Reason = fmt.Sprintf("Best match score %.1f below threshold %.1f", 
            bestMatch.Score, mp.threshold)
    }
    
    return result, nil
}

type MatchAction string

const (
    ActionReuseDirect        MatchAction = "reuse_direct"
    ActionReuseWithAdaptation MatchAction = "reuse_with_adaptation"
    ActionGenerateNew        MatchAction = "generate_new"
)

type PipelineResult struct {
    ExtractedTags []string
    BestMatch     *MatchResult
    Action        MatchAction
    Reason        string
    Adaptation    *AdaptationResult
}
```

---

## TypeScript Types

```typescript
interface MatchResult {
  readonly task: TempCodingTask;
  readonly tagOverlap: number;
  readonly matchedTags: readonly string[];
  readonly score: number;
}

enum MatchAction {
  ReuseDirect = "reuse_direct",
  ReuseWithAdaptation = "reuse_with_adaptation",
  GenerateNew = "generate_new",
}

interface PipelineResult {
  readonly extractedTags: readonly string[];
  readonly bestMatch: MatchResult | null;
  readonly action: MatchAction;
  readonly reason: string;
  readonly adaptation: AdaptationResult | null;
}

interface AdaptationResult {
  readonly canAdapt: boolean;
  readonly modifications: readonly string[];
  readonly originalCode: string;
  readonly adaptedCode: string;
  readonly confidence: number;
}

interface MatcherConfig {
  readonly minTagOverlap: number;
  readonly scoreThreshold: number;
  readonly maxResults: number;
  readonly enableSemanticSearch: boolean;
}
```

---

## Configuration

```json
{
  "taskMatcher": {
    "minTagOverlap": 2,
    "scoreThreshold": 60.0,
    "maxResults": 5,
    "enableSemanticSearch": false,
    "weights": {
      "tagOverlap": 50,
      "successRate": 25,
      "frequency": 15,
      "recency": 10
    }
  }
}
```

---

## Related Specs

- [03-code-generator.md](./03-code-generator.md) — Generation fallback
- [11-database-schema.md](./11-database-schema.md) — Query schema
- [12-tag-system.md](./12-tag-system.md) — Tag taxonomy
