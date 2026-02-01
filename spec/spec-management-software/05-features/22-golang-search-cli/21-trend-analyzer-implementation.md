# Trend Analyzer Implementation Specification

**Version:** 1.0.0  
**Status:** Draft  
**Last Updated:** 2026-01-29

---

## Overview

This specification details the Golang implementation of the TrendAnalyzer component for the `gsearch` CLI, including full settings integration via the Seedable Configuration Pattern and data source collectors for market signal aggregation.

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                     TrendAnalyzer Service                        │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐       │
│  │   Settings   │───▶│  Collectors  │───▶│   Scoring    │       │
│  │   Service    │    │   Manager    │    │   Engine     │       │
│  └──────────────┘    └──────────────┘    └──────────────┘       │
│         │                   │                   │                │
│         ▼                   ▼                   ▼                │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐       │
│  │  Seed JSON   │    │  Data Source │    │  Composite   │       │
│  │  Configs     │    │  Adapters    │    │  Calculator  │       │
│  └──────────────┘    └──────────────┘    └──────────────┘       │
│                             │                   │                │
│                             ▼                   ▼                │
│                      ┌──────────────┐    ┌──────────────┐       │
│                      │   SQLite     │    │ Visualizer   │       │
│                      │   Storage    │    │   Engine     │       │
│                      └──────────────┘    └──────────────┘       │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## Module Structure

```
internal/
├── trends/
│   ├── analyzer.go           # Main TrendAnalyzer struct
│   ├── config.go             # Settings integration
│   ├── scoring.go            # Composite score calculation
│   ├── growth.go             # Growth rate computation
│   └── visualization.go      # Chart generation
├── collectors/
│   ├── manager.go            # Collector orchestration
│   ├── github.go             # GitHub API collector
│   ├── stackoverflow.go      # StackOverflow collector
│   ├── jobs.go               # Job posting aggregator
│   ├── npm.go                # NPM registry collector
│   └── pypi.go               # PyPI registry collector
├── settings/
│   ├── service.go            # SettingsService implementation
│   ├── seeder.go             # JSON seed file processor
│   └── models.go             # Setting data models
└── storage/
    ├── trends.go             # TrendSignal/TrendHistory repos
    └── settings.go           # Settings repository
```

---

## Core Interfaces

### TrendAnalyzer Interface

```go
// TrendAnalyzer orchestrates trend data collection, scoring, and visualization
type TrendAnalyzer interface {
    // Analyze performs full trend analysis for a given type
    Analyze(ctx context.Context, opts AnalyzeOptions) (*TrendReport, error)
    
    // ComputeCompositeScore calculates weighted composite score
    ComputeCompositeScore(signal TrendSignal) float64
    
    // ComputeGrowthRate calculates growth metrics over time periods
    ComputeGrowthRate(history []TrendHistory) GrowthMetrics
    
    // GenerateVisualization creates charts and reports
    GenerateVisualization(report *TrendReport, opts VisualizeOptions) error
    
    // GetTopN returns top N items by composite score
    GetTopN(signals []TrendSignal, n int) []TrendSignal
}
```

### AnalyzeOptions

```go
type AnalyzeOptions struct {
    Type           TrendType      // language, framework, library, tool
    Category       string         // Optional: web, mobile, data, etc.
    TimeRange      TimeRange      // Time period for analysis
    IncludeHistory bool           // Include historical data
    RefreshData    bool           // Force refresh from sources
}

type TrendType string

const (
    TrendTypeLanguage  TrendType = "language"
    TrendTypeFramework TrendType = "framework"
    TrendTypeLibrary   TrendType = "library"
    TrendTypeTool      TrendType = "tool"
)
```

### TrendReport

```go
type TrendReport struct {
    Type           TrendType
    GeneratedAt    time.Time
    TopItems       []TrendSignal
    GrowthLeaders  []GrowthLeader
    Visualizations []Visualization
    Metadata       ReportMetadata
}

type GrowthLeader struct {
    Name         string
    GrowthRate   float64
    GrowthPeriod string
    Direction    string // "rising", "stable", "declining"
}

type Visualization struct {
    Type     string // "bar", "line", "heatmap"
    FilePath string
    Title    string
}

type ReportMetadata struct {
    SourceCount     int
    DataFreshness   time.Time
    ConfidenceScore float64
}
```

---

## Settings Integration

### SettingsService Interface

```go
// SettingsService provides access to seedable configuration values
type SettingsService interface {
    // Get retrieves a string setting value
    Get(category, key string) (string, error)
    
    // GetFloat retrieves a float64 setting value
    GetFloat(category, key string) (float64, error)
    
    // GetInt retrieves an int setting value
    GetInt(category, key string) (int, error)
    
    // GetMap retrieves a map[string]interface{} setting value
    GetMap(category, key string) (map[string]interface{}, error)
    
    // GetWeights retrieves a typed weights map
    GetWeights(category, key string) (map[string]float64, error)
    
    // Update modifies a setting value (marks as user-modified)
    Update(category, key string, value interface{}) error
    
    // ResetToDefault resets a setting to its seed value
    ResetToDefault(category, key string) error
    
    // GetCategory retrieves all settings in a category
    GetCategory(category string) ([]Setting, error)
    
    // SeedFromFile processes a seed JSON file
    SeedFromFile(filePath string) error
}
```

### TrendAnalyzer Config Loading

```go
// TrendConfig holds all trend analysis configuration from settings
type TrendConfig struct {
    CompositeWeights     CompositeWeights
    SignalNormalization  SignalNormalization
    GrowthRateWeights    GrowthRateWeights
    VisualizationConfig  VisualizationConfig
    DataSourceWeights    DataSourceWeights
    FreshnessRequirements FreshnessRequirements
}

type CompositeWeights struct {
    GitHubStars            float64 `json:"github_stars"`
    JobPostings            float64 `json:"job_postings"`
    StackOverflowQuestions float64 `json:"stackoverflow_questions"`
    PackageDownloads       float64 `json:"package_downloads"`
}

type SignalNormalization struct {
    GitHubStarsMax            int64 `json:"github_stars_max"`
    JobPostingsMax            int64 `json:"job_postings_max"`
    StackOverflowQuestionsMax int64 `json:"stackoverflow_questions_max"`
    PackageDownloadsMax       int64 `json:"package_downloads_max"`
}

type GrowthRateWeights struct {
    WeekOverWeek    float64 `json:"week_over_week"`
    MonthOverMonth  float64 `json:"month_over_month"`
    QuarterOverQuarter float64 `json:"quarter_over_quarter"`
    YearOverYear    float64 `json:"year_over_year"`
}

type VisualizationConfig struct {
    TopNResults  int `json:"top_n_results"`
    ChartDPI     int `json:"chart_dpi"`
    FigureWidth  int `json:"figure_width"`
    FigureHeight int `json:"figure_height"`
}

type DataSourceWeights struct {
    GitHubAPI         float64 `json:"github_api_weight"`
    StackOverflowAPI  float64 `json:"stackoverflow_api_weight"`
    IndeedScraper     float64 `json:"indeed_scraper_weight"`
    NPMRegistry       float64 `json:"npm_registry_weight"`
    PyPIRegistry      float64 `json:"pypi_registry_weight"`
}

type FreshnessRequirements struct {
    MaxDataAgeHours int     `json:"max_data_age_hours"`
    StalePenalty    float64 `json:"stale_penalty"`
    CacheTTLMinutes int     `json:"cache_ttl_minutes"`
}
```

### Config Loader

```go
// LoadTrendConfig loads trend configuration from SettingsService
func LoadTrendConfig(settings SettingsService) (*TrendConfig, error) {
    config := &TrendConfig{}
    
    // Load composite weights
    weights, err := settings.GetMap("trend_analysis", "composite_score_weights")
    if err != nil {
        return nil, fmt.Errorf("load composite weights: %w", err)
    }
    config.CompositeWeights = parseCompositeWeights(weights)
    
    // Load normalization limits
    normalization, err := settings.GetMap("trend_analysis", "signal_normalization")
    if err != nil {
        return nil, fmt.Errorf("load normalization: %w", err)
    }
    config.SignalNormalization = parseNormalization(normalization)
    
    // ... load remaining config sections
    
    return config, nil
}
```

---

## Data Source Collectors

### Collector Interface

```go
// Collector defines the interface for data source adapters
type Collector interface {
    // Name returns the collector identifier
    Name() string
    
    // Collect fetches trend data for a given query
    Collect(ctx context.Context, query CollectorQuery) (*CollectorResult, error)
    
    // HealthCheck verifies the data source is accessible
    HealthCheck(ctx context.Context) error
    
    // RateLimit returns current rate limit status
    RateLimit() RateLimitStatus
}

type CollectorQuery struct {
    Type     TrendType
    Keywords []string
    Limit    int
    Since    time.Time
}

type CollectorResult struct {
    Source    string
    Signals   []RawSignal
    FetchedAt time.Time
    Metadata  map[string]interface{}
}

type RawSignal struct {
    Name       string
    Value      int64
    SignalType string // "stars", "questions", "downloads", etc.
    Timestamp  time.Time
}

type RateLimitStatus struct {
    Remaining int
    ResetAt   time.Time
    Limited   bool
}
```

### Collector Manager

```go
// CollectorManager orchestrates multiple data source collectors
type CollectorManager struct {
    collectors map[string]Collector
    settings   SettingsService
    cache      Cache
    logger     Logger
}

// CollectAll fetches data from all registered collectors
func (m *CollectorManager) CollectAll(ctx context.Context, query CollectorQuery) (*AggregatedResult, error) {
    results := make(chan CollectorResult, len(m.collectors))
    errors := make(chan error, len(m.collectors))
    
    // Parallel collection with timeout
    for name, collector := range m.collectors {
        go func(n string, c Collector) {
            result, err := c.Collect(ctx, query)
            if err != nil {
                errors <- fmt.Errorf("%s: %w", n, err)
                return
            }
            results <- *result
        }(name, collector)
    }
    
    // Aggregate results with source weighting
    return m.aggregateResults(results, errors)
}
```

### GitHub Collector

```go
// GitHubCollector fetches repository and star data from GitHub API
type GitHubCollector struct {
    client      *github.Client
    settings    SettingsService
    rateLimiter *rate.Limiter
}

func (c *GitHubCollector) Collect(ctx context.Context, query CollectorQuery) (*CollectorResult, error) {
    // Get source weight from settings
    weight, _ := c.settings.GetFloat("trend_analysis", "github_api_weight")
    
    signals := []RawSignal{}
    
    for _, keyword := range query.Keywords {
        // Search repositories
        repos, _, err := c.client.Search.Repositories(ctx, keyword, &github.SearchOptions{
            Sort:  "stars",
            Order: "desc",
        })
        if err != nil {
            return nil, err
        }
        
        for _, repo := range repos.Repositories {
            signals = append(signals, RawSignal{
                Name:       *repo.FullName,
                Value:      int64(*repo.StargazersCount),
                SignalType: "github_stars",
                Timestamp:  time.Now(),
            })
        }
    }
    
    return &CollectorResult{
        Source:    "github",
        Signals:   signals,
        FetchedAt: time.Now(),
        Metadata: map[string]interface{}{
            "weight": weight,
        },
    }, nil
}
```

### StackOverflow Collector

```go
// StackOverflowCollector fetches question/tag data from StackExchange API
type StackOverflowCollector struct {
    client   *http.Client
    settings SettingsService
    apiKey   string
}

func (c *StackOverflowCollector) Collect(ctx context.Context, query CollectorQuery) (*CollectorResult, error) {
    weight, _ := c.settings.GetFloat("trend_analysis", "stackoverflow_api_weight")
    
    signals := []RawSignal{}
    
    for _, keyword := range query.Keywords {
        // Fetch tag statistics
        tagStats, err := c.fetchTagStats(ctx, keyword)
        if err != nil {
            continue // Log and continue with other keywords
        }
        
        signals = append(signals, RawSignal{
            Name:       keyword,
            Value:      tagStats.QuestionCount,
            SignalType: "stackoverflow_questions",
            Timestamp:  time.Now(),
        })
    }
    
    return &CollectorResult{
        Source:    "stackoverflow",
        Signals:   signals,
        FetchedAt: time.Now(),
        Metadata: map[string]interface{}{
            "weight": weight,
        },
    }, nil
}
```

### Job Posting Collector

```go
// JobCollector aggregates job posting data from multiple sources
type JobCollector struct {
    scrapers []JobScraper
    settings SettingsService
}

type JobScraper interface {
    Scrape(ctx context.Context, query string) ([]JobPosting, error)
    Source() string
}

func (c *JobCollector) Collect(ctx context.Context, query CollectorQuery) (*CollectorResult, error) {
    weight, _ := c.settings.GetFloat("trend_analysis", "indeed_scraper_weight")
    
    signals := []RawSignal{}
    
    for _, keyword := range query.Keywords {
        totalCount := int64(0)
        
        for _, scraper := range c.scrapers {
            postings, err := scraper.Scrape(ctx, keyword)
            if err != nil {
                continue
            }
            totalCount += int64(len(postings))
        }
        
        signals = append(signals, RawSignal{
            Name:       keyword,
            Value:      totalCount,
            SignalType: "job_postings",
            Timestamp:  time.Now(),
        })
    }
    
    return &CollectorResult{
        Source:    "jobs",
        Signals:   signals,
        FetchedAt: time.Now(),
        Metadata: map[string]interface{}{
            "weight": weight,
        },
    }, nil
}
```

### Package Registry Collectors

```go
// NPMCollector fetches download statistics from NPM registry
type NPMCollector struct {
    client   *http.Client
    settings SettingsService
}

func (c *NPMCollector) Collect(ctx context.Context, query CollectorQuery) (*CollectorResult, error) {
    weight, _ := c.settings.GetFloat("trend_analysis", "npm_registry_weight")
    
    signals := []RawSignal{}
    
    for _, pkg := range query.Keywords {
        downloads, err := c.fetchDownloads(ctx, pkg, "last-month")
        if err != nil {
            continue
        }
        
        signals = append(signals, RawSignal{
            Name:       pkg,
            Value:      downloads,
            SignalType: "package_downloads",
            Timestamp:  time.Now(),
        })
    }
    
    return &CollectorResult{
        Source:    "npm",
        Signals:   signals,
        FetchedAt: time.Now(),
        Metadata: map[string]interface{}{
            "weight": weight,
        },
    }, nil
}

// PyPICollector fetches download statistics from PyPI
type PyPICollector struct {
    client   *http.Client
    settings SettingsService
}

// Similar implementation to NPMCollector...
```

---

## Scoring Engine

### Composite Score Calculator

```go
// CompositeScorer calculates weighted composite scores from raw signals
type CompositeScorer struct {
    config *TrendConfig
}

// ComputeScore calculates the normalized composite score
func (s *CompositeScorer) ComputeScore(signal TrendSignal) float64 {
    weights := s.config.CompositeWeights
    norms := s.config.SignalNormalization
    
    // Normalize each signal to 0-1 range
    normalizedStars := normalize(signal.GitHubStars, norms.GitHubStarsMax)
    normalizedJobs := normalize(signal.JobPostings, norms.JobPostingsMax)
    normalizedQuestions := normalize(signal.StackOverflowQuestions, norms.StackOverflowQuestionsMax)
    normalizedDownloads := normalize(signal.PackageDownloads, norms.PackageDownloadsMax)
    
    // Apply weighted composite formula
    composite := (
        weights.GitHubStars * normalizedStars +
        weights.JobPostings * normalizedJobs +
        weights.StackOverflowQuestions * normalizedQuestions +
        weights.PackageDownloads * normalizedDownloads
    )
    
    return composite
}

func normalize(value int64, max int64) float64 {
    if max <= 0 {
        return 0
    }
    normalized := float64(value) / float64(max)
    if normalized > 1.0 {
        return 1.0
    }
    return normalized
}
```

### Growth Rate Calculator

```go
// GrowthCalculator computes growth rates across time periods
type GrowthCalculator struct {
    config *TrendConfig
}

type GrowthMetrics struct {
    WeekOverWeek       float64
    MonthOverMonth     float64
    QuarterOverQuarter float64
    YearOverYear       float64
    WeightedGrowth     float64
    Direction          string
}

func (c *GrowthCalculator) Calculate(history []TrendHistory) GrowthMetrics {
    weights := c.config.GrowthRateWeights
    
    metrics := GrowthMetrics{
        WeekOverWeek:       c.computePeriodGrowth(history, 7*24*time.Hour),
        MonthOverMonth:     c.computePeriodGrowth(history, 30*24*time.Hour),
        QuarterOverQuarter: c.computePeriodGrowth(history, 90*24*time.Hour),
        YearOverYear:       c.computePeriodGrowth(history, 365*24*time.Hour),
    }
    
    // Compute weighted growth
    metrics.WeightedGrowth = (
        weights.WeekOverWeek * metrics.WeekOverWeek +
        weights.MonthOverMonth * metrics.MonthOverMonth +
        weights.QuarterOverQuarter * metrics.QuarterOverQuarter +
        weights.YearOverYear * metrics.YearOverYear
    )
    
    // Determine direction
    switch {
    case metrics.WeightedGrowth > 0.1:
        metrics.Direction = "rising"
    case metrics.WeightedGrowth < -0.1:
        metrics.Direction = "declining"
    default:
        metrics.Direction = "stable"
    }
    
    return metrics
}

func (c *GrowthCalculator) computePeriodGrowth(history []TrendHistory, period time.Duration) float64 {
    // Find data points at period boundaries
    // Calculate percentage change
    // Return growth rate
}
```

---

## Visualization Engine

### Visualizer Interface

```go
// Visualizer generates charts and reports from trend data
type Visualizer interface {
    // GenerateBarChart creates a bar chart of top items
    GenerateBarChart(data []TrendSignal, opts ChartOptions) (string, error)
    
    // GenerateLineChart creates a line chart of trends over time
    GenerateLineChart(history []TrendHistory, opts ChartOptions) (string, error)
    
    // GenerateHeatmap creates a heatmap of signal correlations
    GenerateHeatmap(signals []TrendSignal, opts ChartOptions) (string, error)
    
    // GenerateReport creates a comprehensive PDF/HTML report
    GenerateReport(report *TrendReport, opts ReportOptions) (string, error)
}

type ChartOptions struct {
    Title      string
    Width      int
    Height     int
    DPI        int
    OutputPath string
    Format     string // "png", "svg", "pdf"
}

type ReportOptions struct {
    Title       string
    OutputPath  string
    Format      string // "html", "pdf", "markdown"
    IncludeRaw  bool
}
```

### Go Chart Implementation

```go
// GoChartVisualizer uses go-chart library for visualization
type GoChartVisualizer struct {
    config *VisualizationConfig
}

func (v *GoChartVisualizer) GenerateBarChart(data []TrendSignal, opts ChartOptions) (string, error) {
    // Apply config defaults
    if opts.Width == 0 {
        opts.Width = v.config.FigureWidth * 100
    }
    if opts.Height == 0 {
        opts.Height = v.config.FigureHeight * 100
    }
    if opts.DPI == 0 {
        opts.DPI = v.config.ChartDPI
    }
    
    // Build bar chart values
    bars := make([]chart.Value, len(data))
    for i, signal := range data {
        bars[i] = chart.Value{
            Label: signal.Name,
            Value: signal.CompositeScore,
        }
    }
    
    // Create chart
    barChart := chart.BarChart{
        Title:      opts.Title,
        Width:      opts.Width,
        Height:     opts.Height,
        Bars:       bars,
        // ... styling options
    }
    
    // Render to file
    f, _ := os.Create(opts.OutputPath)
    defer f.Close()
    barChart.Render(chart.PNG, f)
    
    return opts.OutputPath, nil
}
```

---

## CLI Integration

### Command Registration

```go
// RegisterTrendCommands adds trend analysis commands to the CLI
func RegisterTrendCommands(root *cobra.Command, analyzer TrendAnalyzer) {
    trendsCmd := &cobra.Command{
        Use:   "trends",
        Short: "Analyze technology trends and market signals",
    }
    
    // gsearch trends analyze
    analyzeCmd := &cobra.Command{
        Use:   "analyze",
        Short: "Run trend analysis",
        RunE:  runTrendAnalysis(analyzer),
    }
    analyzeCmd.Flags().StringP("type", "t", "language", "Trend type: language, framework, library, tool")
    analyzeCmd.Flags().StringP("category", "c", "", "Category filter: web, mobile, data, etc.")
    analyzeCmd.Flags().BoolP("refresh", "r", false, "Force refresh from data sources")
    analyzeCmd.Flags().IntP("top", "n", 10, "Number of top results")
    analyzeCmd.Flags().StringP("output", "o", "", "Output file path")
    analyzeCmd.Flags().StringP("format", "f", "table", "Output format: table, json, csv, chart")
    
    // gsearch trends history
    historyCmd := &cobra.Command{
        Use:   "history [name]",
        Short: "View historical trend data",
        RunE:  runTrendHistory(analyzer),
    }
    historyCmd.Flags().StringP("period", "p", "month", "Time period: week, month, quarter, year")
    
    // gsearch trends compare
    compareCmd := &cobra.Command{
        Use:   "compare [items...]",
        Short: "Compare trend data between items",
        RunE:  runTrendCompare(analyzer),
    }
    
    trendsCmd.AddCommand(analyzeCmd, historyCmd, compareCmd)
    root.AddCommand(trendsCmd)
}
```

### Output Formatting

```go
// TrendOutput handles multiple output formats
type TrendOutput struct {
    format string
    writer io.Writer
}

func (o *TrendOutput) Render(report *TrendReport) error {
    switch o.format {
    case "table":
        return o.renderTable(report)
    case "json":
        return o.renderJSON(report)
    case "csv":
        return o.renderCSV(report)
    case "chart":
        return o.renderChart(report)
    default:
        return fmt.Errorf("unsupported format: %s", o.format)
    }
}

func (o *TrendOutput) renderTable(report *TrendReport) error {
    tw := tabwriter.NewWriter(o.writer, 0, 0, 2, ' ', 0)
    
    fmt.Fprintf(tw, "RANK\tNAME\tSCORE\tGROWTH\tDIRECTION\n")
    fmt.Fprintf(tw, "----\t----\t-----\t------\t---------\n")
    
    for i, item := range report.TopItems {
        fmt.Fprintf(tw, "%d\t%s\t%.3f\t%.1f%%\t%s\n",
            i+1,
            item.Name,
            item.CompositeScore,
            item.GrowthRate*100,
            item.Direction,
        )
    }
    
    return tw.Flush()
}
```

---

## Error Handling

### Error Types

```go
var (
    ErrCollectorTimeout   = errors.New("collector timeout exceeded")
    ErrRateLimited        = errors.New("rate limit exceeded")
    ErrInvalidConfig      = errors.New("invalid configuration")
    ErrNoDataAvailable    = errors.New("no trend data available")
    ErrStaleData          = errors.New("data exceeds freshness threshold")
)

// CollectorError wraps collector-specific errors
type CollectorError struct {
    Collector string
    Err       error
}

func (e *CollectorError) Error() string {
    return fmt.Sprintf("collector %s: %v", e.Collector, e.Err)
}
```

### Graceful Degradation

```go
// CollectWithFallback attempts collection with fallback on failure
func (m *CollectorManager) CollectWithFallback(ctx context.Context, query CollectorQuery) (*AggregatedResult, error) {
    result, err := m.CollectAll(ctx, query)
    
    if err != nil {
        // Check for cached data
        cached, cacheErr := m.cache.Get(query.CacheKey())
        if cacheErr == nil {
            // Apply stale penalty from settings
            penalty, _ := m.settings.GetFloat("trend_analysis", "stale_penalty")
            cached.ApplyPenalty(penalty)
            return cached, nil
        }
    }
    
    // Partial results acceptable
    if result != nil && result.SuccessCount > 0 {
        return result, nil
    }
    
    return nil, ErrNoDataAvailable
}
```

---

## Testing Strategy

### Unit Tests

```go
func TestCompositeScorer_ComputeScore(t *testing.T) {
    config := &TrendConfig{
        CompositeWeights: CompositeWeights{
            GitHubStars:            0.30,
            JobPostings:            0.40,
            StackOverflowQuestions: 0.20,
            PackageDownloads:       0.10,
        },
        SignalNormalization: SignalNormalization{
            GitHubStarsMax:            500000,
            JobPostingsMax:            100000,
            StackOverflowQuestionsMax: 50000,
            PackageDownloadsMax:       1000000000,
        },
    }
    
    scorer := NewCompositeScorer(config)
    
    tests := []struct {
        name     string
        signal   TrendSignal
        expected float64
    }{
        {
            name: "balanced signal",
            signal: TrendSignal{
                GitHubStars:            100000,
                JobPostings:            20000,
                StackOverflowQuestions: 10000,
                PackageDownloads:       200000000,
            },
            expected: 0.30*0.2 + 0.40*0.2 + 0.20*0.2 + 0.10*0.2, // 0.20
        },
        // ... more test cases
    }
    
    for _, tt := range tests {
        t.Run(tt.name, func(t *testing.T) {
            score := scorer.ComputeScore(tt.signal)
            assert.InDelta(t, tt.expected, score, 0.001)
        })
    }
}
```

### Integration Tests

```go
func TestTrendAnalyzer_FullPipeline(t *testing.T) {
    // Setup test database
    db := setupTestDB(t)
    
    // Seed configuration
    seeder := NewSeeder(db)
    seeder.SeedFromFile("testdata/seeding-trend-analysis.json")
    
    // Create analyzer with mock collectors
    analyzer := NewTrendAnalyzer(db, []Collector{
        NewMockGitHubCollector(testGitHubData),
        NewMockStackOverflowCollector(testSOData),
    })
    
    // Run analysis
    report, err := analyzer.Analyze(context.Background(), AnalyzeOptions{
        Type: TrendTypeLanguage,
    })
    
    require.NoError(t, err)
    assert.Len(t, report.TopItems, 10)
    assert.True(t, report.TopItems[0].CompositeScore > report.TopItems[1].CompositeScore)
}
```

---

## Related Specifications

- [Seedable Configuration Pattern](../../04-coding-guidelines/05-seedable-config-pattern.md)
- [Trend Analysis Engine](./20-trend-analysis-engine.md)
- [Authority & Credibility Scoring](./19-authority-credibility-scoring.md)
- [Settings UI](../../05-features/25-settings-ui/)
