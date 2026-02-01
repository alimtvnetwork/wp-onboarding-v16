# 19. Authority & Credibility Scoring

**Version:** 1.0.0  
**Status:** Planned  
**Updated:** 2026-01-29  
**Parent:** [GSearch Overview](./00-overview.md)

---

## Purpose

Define the authority scoring, source weighting, and credibility classification system for evaluating search results. All configuration values follow the [Seedable Configuration Pattern](../../04-coding-guidelines/05-seedable-config-pattern.md).

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│              AUTHORITY & CREDIBILITY SCORING                     │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │                 AUTHORITY SCORES                          │   │
│  │  • Domain-based scoring                                   │   │
│  │  • Academic sources: 0.88-0.95                            │   │
│  │  • News sources: 0.80-0.85                                │   │
│  │  • Technical: 0.75-0.95                                   │   │
│  │  • Default: 0.50                                          │   │
│  └──────────────────────────────────────────────────────────┘   │
│                           │                                      │
│                           ▼                                      │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │                 SOURCE WEIGHT FORMULA                     │   │
│  │                                                           │   │
│  │  weight = (0.5 × authority) +                             │   │
│  │           (0.3 × recency) +                               │   │
│  │           (0.2 × citations)                               │   │
│  │                                                           │   │
│  └──────────────────────────────────────────────────────────┘   │
│                           │                                      │
│                           ▼                                      │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │                 CREDIBILITY CLASSIFICATION                │   │
│  │                                                           │   │
│  │  score < 0.4  → LOW                                       │   │
│  │  score < 0.7  → MEDIUM                                    │   │
│  │  score >= 0.7 → HIGH                                      │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## Authority Scores

### Domain Authority Lookup

| Category | Domain | Score |
|----------|--------|-------|
| Academic | scholar.google.com | 0.95 |
| Academic | arxiv.org | 0.92 |
| Academic | researchgate.net | 0.88 |
| Academic | *.edu | 0.90 |
| News | reuters.com | 0.85 |
| News | bbc.com | 0.82 |
| News | theguardian.com | 0.80 |
| Technical | github.com | 0.88 |
| Technical | stackoverflow.com | 0.75 |
| Technical | official docs | 0.95 |
| Low Trust | medium.com | 0.40 |
| Default | (any other) | 0.50 |

### Seed File: `config/seeding-authority-scores.json`

```json
{
  "version": "1.0.0",
  "category": "authority_scores",
  "values": {
    "domain_scores": {
      "scholar.google.com": 0.95,
      "arxiv.org": 0.92,
      "researchgate.net": 0.88,
      ".edu": 0.90,
      "reuters.com": 0.85,
      "bbc.com": 0.82,
      "theguardian.com": 0.80,
      "github.com": 0.88,
      "stackoverflow.com": 0.75,
      "medium.com": 0.40,
      "default": 0.50
    },
    "official_docs_patterns": [
      "docs.*",
      "documentation.*",
      "developer.*",
      "api.*"
    ],
    "official_docs_score": 0.95,
    "tld_bonuses": {
      ".gov": 0.10,
      ".edu": 0.08,
      ".org": 0.02
    }
  }
}
```

### Golang Implementation

```go
type AuthorityScorer struct {
    settings *SettingsService
}

func (as *AuthorityScorer) LookupAuthority(domain string) float64 {
    // Get domain scores from settings (Seedable Config)
    scores, err := as.settings.GetMap("authority_scores", "domain_scores")
    if err != nil {
        return 0.5 // Default
    }
    
    // Exact match
    if score, ok := scores[domain]; ok {
        return score.(float64)
    }
    
    // TLD patterns (.edu, .gov)
    tldBonuses, _ := as.settings.GetMap("authority_scores", "tld_bonuses")
    for tld, bonus := range tldBonuses {
        if strings.HasSuffix(domain, tld) {
            defaultScore := scores["default"].(float64)
            return defaultScore + bonus.(float64)
        }
    }
    
    // Check official docs patterns
    patterns, _ := as.settings.Get("authority_scores", "official_docs_patterns")
    officialScore, _ := as.settings.GetFloat("authority_scores", "official_docs_score")
    
    for _, pattern := range patterns.([]interface{}) {
        if matchPattern(domain, pattern.(string)) {
            return officialScore
        }
    }
    
    // Subdomain check (e.g., docs.example.com)
    if strings.HasPrefix(domain, "docs.") || strings.HasPrefix(domain, "developer.") {
        return officialScore
    }
    
    // Default
    return scores["default"].(float64)
}
```

---

## Source Weight Formula

### Weight Calculation

```
weight = (authority_weight × authority) +
         (recency_weight × recency) +
         (citations_weight × citations)

Default weights:
- authority_weight: 0.5
- recency_weight:   0.3
- citations_weight: 0.2
```

### Seed File: `config/seeding-source-weights.json`

```json
{
  "version": "1.0.0",
  "category": "source_weights",
  "values": {
    "weight_formula": {
      "authority_weight": 0.5,
      "recency_weight": 0.3,
      "citations_weight": 0.2
    },
    "recency_decay": {
      "days_half_life": 180,
      "min_score": 0.1
    },
    "citation_normalization": {
      "max_citations": 100,
      "cap_at": 1.0
    }
  }
}
```

### Golang Implementation

```go
type SourceWeightCalculator struct {
    settings       *SettingsService
    authorityScorer *AuthorityScorer
}

type Source struct {
    Domain       string    `json:"domain"`
    Url          string    `json:"url"`
    PublishDate  time.Time `json:"publishDate"`
    CitationCount int      `json:"citationCount"`
    Weight       float64   `json:"weight"`
}

func (swc *SourceWeightCalculator) CalculateWeights(sources []Source) []Source {
    // Get weight formula from settings
    formula, _ := swc.settings.GetMap("source_weights", "weight_formula")
    recencyConfig, _ := swc.settings.GetMap("source_weights", "recency_decay")
    citationConfig, _ := swc.settings.GetMap("source_weights", "citation_normalization")
    
    authorityWeight := formula["authority_weight"].(float64)
    recencyWeight := formula["recency_weight"].(float64)
    citationsWeight := formula["citations_weight"].(float64)
    
    halfLifeDays := recencyConfig["days_half_life"].(float64)
    minRecencyScore := recencyConfig["min_score"].(float64)
    
    maxCitations := citationConfig["max_citations"].(float64)
    citationCap := citationConfig["cap_at"].(float64)
    
    for i := range sources {
        // Authority score
        authority := swc.authorityScorer.LookupAuthority(sources[i].Domain)
        
        // Recency score (time decay)
        recency := swc.timeDecay(sources[i].PublishDate, halfLifeDays, minRecencyScore)
        
        // Citation score (normalized, capped)
        citations := math.Min(float64(sources[i].CitationCount)/maxCitations, citationCap)
        
        // Combined weight
        sources[i].Weight = (authorityWeight * authority) +
                           (recencyWeight * recency) +
                           (citationsWeight * citations)
    }
    
    // Sort by weight descending
    sort.Slice(sources, func(i, j int) bool {
        return sources[i].Weight > sources[j].Weight
    })
    
    return sources
}

func (swc *SourceWeightCalculator) timeDecay(publishDate time.Time, halfLifeDays float64, minScore float64) float64 {
    daysSince := time.Since(publishDate).Hours() / 24
    
    // Exponential decay: score = e^(-λt) where λ = ln(2)/half_life
    lambda := math.Log(2) / halfLifeDays
    score := math.Exp(-lambda * daysSince)
    
    // Apply minimum score floor
    if score < minScore {
        score = minScore
    }
    
    return score
}
```

---

## Credibility Classification

### Thresholds

| Classification | Score Range | Description |
|----------------|-------------|-------------|
| LOW | < 0.4 | Unverified, low authority, outdated |
| MEDIUM | 0.4 - 0.7 | Some verification, moderate authority |
| HIGH | >= 0.7 | Well-verified, high authority, recent |

### Seed File: `config/seeding-credibility.json`

```json
{
  "version": "1.0.0",
  "category": "credibility_thresholds",
  "values": {
    "thresholds": {
      "low_max": 0.4,
      "medium_max": 0.7,
      "high_min": 0.7
    },
    "check_weights": {
      "https_enabled": 0.15,
      "domain_authority": 0.25,
      "content_quality": 0.20,
      "citation_count": 0.15,
      "recency": 0.15,
      "author_verified": 0.10
    }
  }
}
```

### Golang Implementation

```go
type CredibilityLevel string

const (
    CredibilityLow    CredibilityLevel = "LOW"
    CredibilityMedium CredibilityLevel = "MEDIUM"
    CredibilityHigh   CredibilityLevel = "HIGH"
)

type CredibilityClassifier struct {
    settings *SettingsService
}

type CredibilityChecks struct {
    HttpsEnabled   float64 `json:"httpsEnabled"`
    DomainAuthority float64 `json:"domainAuthority"`
    ContentQuality float64 `json:"contentQuality"`
    CitationCount  float64 `json:"citationCount"`
    Recency        float64 `json:"recency"`
    AuthorVerified float64 `json:"authorVerified"`
}

type CredibilityResult struct {
    Level      CredibilityLevel `json:"level"`
    Score      float64          `json:"score"`
    Checks     CredibilityChecks `json:"checks"`
    Confidence float64          `json:"confidence"`
}

func (cc *CredibilityClassifier) Classify(source Source, checks CredibilityChecks) (*CredibilityResult, error) {
    // Get thresholds from settings
    thresholds, err := cc.settings.GetMap("credibility_thresholds", "thresholds")
    if err != nil {
        return nil, err
    }
    
    // Get check weights from settings
    weights, err := cc.settings.GetMap("credibility_thresholds", "check_weights")
    if err != nil {
        return nil, err
    }
    
    // Calculate weighted score
    checksMap := map[string]float64{
        "https_enabled":    checks.HttpsEnabled,
        "domain_authority": checks.DomainAuthority,
        "content_quality":  checks.ContentQuality,
        "citation_count":   checks.CitationCount,
        "recency":          checks.Recency,
        "author_verified":  checks.AuthorVerified,
    }
    
    totalScore := 0.0
    for key, value := range checksMap {
        weight := weights[key].(float64)
        totalScore += weight * value
    }
    
    // Classify based on thresholds
    lowMax := thresholds["low_max"].(float64)
    mediumMax := thresholds["medium_max"].(float64)
    
    var level CredibilityLevel
    if totalScore < lowMax {
        level = CredibilityLow
    } else if totalScore < mediumMax {
        level = CredibilityMedium
    } else {
        level = CredibilityHigh
    }
    
    return &CredibilityResult{
        Level:      level,
        Score:      totalScore,
        Checks:     checks,
        Confidence: calculateConfidence(checksMap),
    }, nil
}

func calculateConfidence(checks map[string]float64) float64 {
    // Confidence based on how many checks were performed
    nonZero := 0
    for _, v := range checks {
        if v > 0 {
            nonZero++
        }
    }
    return float64(nonZero) / float64(len(checks))
}
```

---

## Confidence Analysis

Comprehensive confidence scoring that measures how reliable an answer is based on multiple factors.

### Confidence Metrics

| Metric | Description | Range |
|--------|-------------|-------|
| source_agreement | Consensus among sources | 0-1 |
| data_freshness | Recency of information (time decay) | 0-1 |
| source_count_confidence | Quantity of sources (capped at 10) | 0-1 |
| authority_diversity | Domain diversity of sources | 0-1 |
| expert_consensus | Agreement among expert sources | 0-1 |
| contradiction_presence | Conflicting claims detected | 0/1 |

### Seed File: `config/seeding-confidence-metrics.json`

```json
{
  "version": "1.0.0",
  "category": "confidence_metrics",
  "values": {
    "weight_formula": {
      "source_agreement_weight": 0.40,
      "data_freshness_weight": 0.25,
      "source_count_weight": 0.20,
      "authority_diversity_weight": 0.15
    },
    "thresholds": {
      "min_sources_for_high_confidence": 10,
      "contradiction_penalty_multiplier": 0.7,
      "freshness_half_life_days": 90
    },
    "expert_sources": [
      "scholar.google.com",
      "arxiv.org",
      ".edu",
      "nature.com",
      "ieee.org"
    ],
    "warnings": {
      "low_confidence_threshold": 0.4,
      "low_confidence_message": "⚠️ Findings contradictory—use caution",
      "moderate_confidence_threshold": 0.6,
      "moderate_confidence_message": "ℹ️ Limited sources—verify independently"
    }
  }
}
```

### Golang Implementation

```go
type ConfidenceAnalyzer struct {
    settings *SettingsService
}

type ConfidenceMetrics struct {
    Score               float64            `json:"score"`
    Details             ConfidenceDetails  `json:"details"`
    Warning             string             `json:"warning,omitempty"`
    ContradictionsFound bool               `json:"contradictionsFound"`
}

type ConfidenceDetails struct {
    SourceAgreement      float64 `json:"sourceAgreement"`
    DataFreshness        float64 `json:"dataFreshness"`
    SourceCountConfidence float64 `json:"sourceCountConfidence"`
    AuthorityDiversity   float64 `json:"authorityDiversity"`
    ExpertConsensus      float64 `json:"expertConsensus"`
    ContradictionPresence float64 `json:"contradictionPresence"`
}

func (ca *ConfidenceAnalyzer) AnalyzeConfidence(sources []Source) (*ConfidenceMetrics, error) {
    // Get weight formula from settings (Seedable Config)
    weights, err := ca.settings.GetMap("confidence_metrics", "weight_formula")
    if err != nil {
        return nil, err
    }
    
    thresholds, err := ca.settings.GetMap("confidence_metrics", "thresholds")
    if err != nil {
        return nil, err
    }
    
    warnings, err := ca.settings.GetMap("confidence_metrics", "warnings")
    if err != nil {
        return nil, err
    }
    
    // Calculate individual metrics
    details := ConfidenceDetails{
        SourceAgreement:       ca.calculateAgreement(sources),
        DataFreshness:         ca.calculateFreshness(sources, thresholds),
        SourceCountConfidence: ca.calculateSourceCountConfidence(sources, thresholds),
        AuthorityDiversity:    ca.measureDomainDiversity(sources),
        ExpertConsensus:       ca.calculateExpertAgreement(sources),
        ContradictionPresence: ca.detectContradictions(sources),
    }
    
    // Get weights
    sourceAgreementWeight := weights["source_agreement_weight"].(float64)
    dataFreshnessWeight := weights["data_freshness_weight"].(float64)
    sourceCountWeight := weights["source_count_weight"].(float64)
    authorityDiversityWeight := weights["authority_diversity_weight"].(float64)
    
    // Calculate overall confidence
    overallConfidence := (sourceAgreementWeight * details.SourceAgreement) +
                        (dataFreshnessWeight * details.DataFreshness) +
                        (sourceCountWeight * details.SourceCountConfidence) +
                        (authorityDiversityWeight * details.AuthorityDiversity)
    
    // Reduce confidence if contradictions found
    contradictionsFound := details.ContradictionPresence > 0.5
    if contradictionsFound {
        penaltyMultiplier := thresholds["contradiction_penalty_multiplier"].(float64)
        overallConfidence *= penaltyMultiplier
    }
    
    // Determine warning message
    warning := ""
    lowThreshold := warnings["low_confidence_threshold"].(float64)
    moderateThreshold := warnings["moderate_confidence_threshold"].(float64)
    
    if contradictionsFound || overallConfidence < lowThreshold {
        warning = warnings["low_confidence_message"].(string)
    } else if overallConfidence < moderateThreshold {
        warning = warnings["moderate_confidence_message"].(string)
    }
    
    return &ConfidenceMetrics{
        Score:               overallConfidence,
        Details:             details,
        Warning:             warning,
        ContradictionsFound: contradictionsFound,
    }, nil
}

func (ca *ConfidenceAnalyzer) calculateAgreement(sources []Source) float64 {
    if len(sources) < 2 {
        return 0.5 // Neutral if insufficient sources
    }
    
    // Compare content similarity across sources
    agreements := 0
    comparisons := 0
    
    for i := 0; i < len(sources); i++ {
        for j := i + 1; j < len(sources); j++ {
            similarity := cosineSimilarity(sources[i].Content, sources[j].Content)
            if similarity > 0.7 {
                agreements++
            }
            comparisons++
        }
    }
    
    if comparisons == 0 {
        return 0.5
    }
    
    return float64(agreements) / float64(comparisons)
}

func (ca *ConfidenceAnalyzer) calculateFreshness(sources []Source, thresholds map[string]interface{}) float64 {
    if len(sources) == 0 {
        return 0
    }
    
    // Find most recent source
    var minDate time.Time
    for i, s := range sources {
        if i == 0 || s.PublishDate.After(minDate) {
            minDate = s.PublishDate
        }
    }
    
    // Time decay using half-life
    halfLifeDays := thresholds["freshness_half_life_days"].(float64)
    daysSince := time.Since(minDate).Hours() / 24
    
    lambda := math.Log(2) / halfLifeDays
    return math.Exp(-lambda * daysSince)
}

func (ca *ConfidenceAnalyzer) calculateSourceCountConfidence(sources []Source, thresholds map[string]interface{}) float64 {
    minSourcesForHigh := thresholds["min_sources_for_high_confidence"].(float64)
    return math.Min(float64(len(sources))/minSourcesForHigh, 1.0)
}

func (ca *ConfidenceAnalyzer) measureDomainDiversity(sources []Source) float64 {
    if len(sources) == 0 {
        return 0
    }
    
    uniqueDomains := make(map[string]bool)
    for _, s := range sources {
        uniqueDomains[s.Domain] = true
    }
    
    // Diversity = unique domains / total sources (capped at 1)
    return math.Min(float64(len(uniqueDomains))/float64(len(sources)), 1.0)
}

func (ca *ConfidenceAnalyzer) calculateExpertAgreement(sources []Source) float64 {
    // Get expert sources from settings
    expertSources, _ := ca.settings.Get("confidence_metrics", "expert_sources")
    expertPatterns := expertSources.([]interface{})
    
    expertCount := 0
    agreementScore := 0.0
    
    for _, s := range sources {
        isExpert := false
        for _, pattern := range expertPatterns {
            if matchPattern(s.Domain, pattern.(string)) {
                isExpert = true
                break
            }
        }
        
        if isExpert {
            expertCount++
            agreementScore += s.Weight // Use weight as proxy for agreement
        }
    }
    
    if expertCount == 0 {
        return 0.5 // Neutral if no expert sources
    }
    
    return agreementScore / float64(expertCount)
}

func (ca *ConfidenceAnalyzer) detectContradictions(sources []Source) float64 {
    if len(sources) < 2 {
        return 0
    }
    
    contradictions := 0
    comparisons := 0
    
    for i := 0; i < len(sources); i++ {
        for j := i + 1; j < len(sources); j++ {
            // Check for semantic contradiction
            if ca.areContradictory(sources[i].Content, sources[j].Content) {
                contradictions++
            }
            comparisons++
        }
    }
    
    if comparisons == 0 {
        return 0
    }
    
    return float64(contradictions) / float64(comparisons)
}

func (ca *ConfidenceAnalyzer) areContradictory(content1, content2 string) bool {
    // Simplified contradiction detection
    // In production, use NLI model (e.g., DeBERTa)
    negationPatterns := []string{"not", "never", "false", "incorrect", "wrong"}
    
    for _, pattern := range negationPatterns {
        if strings.Contains(content1, pattern) != strings.Contains(content2, pattern) {
            return true
        }
    }
    
    return false
}
```

---

## Database Schema

### Table: SourceAuthority

```sql
CREATE TABLE SourceAuthority (
    Id              TEXT PRIMARY KEY,
    Domain          TEXT NOT NULL UNIQUE,
    AuthorityScore  REAL NOT NULL,
    Category        TEXT,
    IsManualOverride INTEGER DEFAULT 0,
    LastUpdated     TEXT NOT NULL,
    CreatedAt       TEXT NOT NULL
);

CREATE INDEX idx_source_authority_domain ON SourceAuthority(Domain);
```

### Table: SourceCredibility

```sql
CREATE TABLE SourceCredibility (
    Id              TEXT PRIMARY KEY,
    Url             TEXT NOT NULL,
    Domain          TEXT NOT NULL,
    CredibilityLevel TEXT NOT NULL,
    Score           REAL NOT NULL,
    ChecksJson      TEXT NOT NULL,
    EvaluatedAt     TEXT NOT NULL,
    
    FOREIGN KEY (Domain) REFERENCES SourceAuthority(Domain)
);

CREATE INDEX idx_source_credibility_url ON SourceCredibility(Url);
CREATE INDEX idx_source_credibility_level ON SourceCredibility(CredibilityLevel);
```

### Table: SourceConfidence

```sql
CREATE TABLE SourceConfidence (
    Id                      TEXT PRIMARY KEY,
    SearchRequestId         TEXT NOT NULL,
    OverallScore            REAL NOT NULL,
    SourceAgreement         REAL NOT NULL,
    DataFreshness           REAL NOT NULL,
    SourceCountConfidence   REAL NOT NULL,
    AuthorityDiversity      REAL NOT NULL,
    ExpertConsensus         REAL NOT NULL,
    ContradictionPresence   REAL NOT NULL,
    ContradictionsFound     INTEGER DEFAULT 0,
    Warning                 TEXT,
    EvaluatedAt             TEXT NOT NULL,
    
    FOREIGN KEY (SearchRequestId) REFERENCES SearchRequest(Id)
);

CREATE INDEX idx_source_confidence_search ON SourceConfidence(SearchRequestId);
CREATE INDEX idx_source_confidence_score ON SourceConfidence(OverallScore);
```

### Golang Models

```go
type SourceAuthorityRecord struct {
    Id              string    `gorm:"primaryKey"`
    Domain          string    `gorm:"unique;not null"`
    AuthorityScore  float64   `gorm:"not null"`
    Category        string
    IsManualOverride bool     `gorm:"default:false"`
    LastUpdated     time.Time
    CreatedAt       time.Time
}

func (SourceAuthorityRecord) TableName() string {
    return "SourceAuthority"
}

type SourceCredibilityRecord struct {
    Id               string    `gorm:"primaryKey"`
    Url              string    `gorm:"not null"`
    Domain           string    `gorm:"not null"`
    CredibilityLevel string    `gorm:"not null"`
    Score            float64   `gorm:"not null"`
    ChecksJson       string    `gorm:"not null"`
    EvaluatedAt      time.Time
}

func (SourceCredibilityRecord) TableName() string {
    return "SourceCredibility"
}

type SourceConfidenceRecord struct {
    Id                    string    `gorm:"primaryKey"`
    SearchRequestId       string    `gorm:"not null"`
    OverallScore          float64   `gorm:"not null"`
    SourceAgreement       float64   `gorm:"not null"`
    DataFreshness         float64   `gorm:"not null"`
    SourceCountConfidence float64   `gorm:"not null"`
    AuthorityDiversity    float64   `gorm:"not null"`
    ExpertConsensus       float64   `gorm:"not null"`
    ContradictionPresence float64   `gorm:"not null"`
    ContradictionsFound   bool      `gorm:"default:false"`
    Warning               string
    EvaluatedAt           time.Time
}

func (SourceConfidenceRecord) TableName() string {
    return "SourceConfidence"
}
```

---

## TypeScript Types

```typescript
enum CredibilityLevel {
  Low = "LOW",
  Medium = "MEDIUM",
  High = "HIGH",
}

interface AuthorityScoreConfig {
  readonly domain_scores: Record<string, number>;
  readonly official_docs_patterns: readonly string[];
  readonly official_docs_score: number;
  readonly tld_bonuses: Record<string, number>;
}

interface WeightFormula {
  readonly authority_weight: number;
  readonly recency_weight: number;
  readonly citations_weight: number;
}

interface RecencyDecayConfig {
  readonly days_half_life: number;
  readonly min_score: number;
}

interface CitationNormalizationConfig {
  readonly max_citations: number;
  readonly cap_at: number;
}

interface SourceWeightsConfig {
  readonly weight_formula: WeightFormula;
  readonly recency_decay: RecencyDecayConfig;
  readonly citation_normalization: CitationNormalizationConfig;
}

interface CredibilityThresholds {
  readonly low_max: number;
  readonly medium_max: number;
  readonly high_min: number;
}

interface CredibilityCheckWeights {
  readonly https_enabled: number;
  readonly domain_authority: number;
  readonly content_quality: number;
  readonly citation_count: number;
  readonly recency: number;
  readonly author_verified: number;
}

interface CredibilityConfig {
  readonly thresholds: CredibilityThresholds;
  readonly check_weights: CredibilityCheckWeights;
}

interface Source {
  readonly domain: string;
  readonly url: string;
  readonly publishDate: string;
  readonly citationCount: number;
  readonly weight: number;
}

interface CredibilityChecks {
  readonly httpsEnabled: number;
  readonly domainAuthority: number;
  readonly contentQuality: number;
  readonly citationCount: number;
  readonly recency: number;
  readonly authorVerified: number;
}

interface CredibilityResult {
  readonly level: CredibilityLevel;
  readonly score: number;
  readonly checks: CredibilityChecks;
  readonly confidence: number;
}
// Confidence Types

interface ConfidenceWeightFormula {
  readonly source_agreement_weight: number;
  readonly data_freshness_weight: number;
  readonly source_count_weight: number;
  readonly authority_diversity_weight: number;
}

interface ConfidenceThresholds {
  readonly min_sources_for_high_confidence: number;
  readonly contradiction_penalty_multiplier: number;
  readonly freshness_half_life_days: number;
}

interface ConfidenceWarnings {
  readonly low_confidence_threshold: number;
  readonly low_confidence_message: string;
  readonly moderate_confidence_threshold: number;
  readonly moderate_confidence_message: string;
}

interface ConfidenceMetricsConfig {
  readonly weight_formula: ConfidenceWeightFormula;
  readonly thresholds: ConfidenceThresholds;
  readonly expert_sources: readonly string[];
  readonly warnings: ConfidenceWarnings;
}

interface ConfidenceDetails {
  readonly sourceAgreement: number;
  readonly dataFreshness: number;
  readonly sourceCountConfidence: number;
  readonly authorityDiversity: number;
  readonly expertConsensus: number;
  readonly contradictionPresence: number;
}

interface ConfidenceMetrics {
  readonly score: number;
  readonly details: ConfidenceDetails;
  readonly warning: string | null;
  readonly contradictionsFound: boolean;
}
```

---

## Integration with gsearch CLI

### Command Examples

```bash
# Search with authority scoring
gsearch search "machine learning" --show-authority

# Filter by credibility level
gsearch search "AI news" --min-credibility HIGH

# Export with scores and confidence
gsearch search "golang best practices" --output json --include-scores --include-confidence

# Filter by confidence score
gsearch search "AI research" --min-confidence 0.7

# Update authority scores for domain
gsearch authority set github.com 0.90

# View current authority config
gsearch authority list

# View confidence metrics config
gsearch config show confidence_metrics
```

### Output with Scores and Confidence

```json
{
  "results": [
    {
      "title": "Machine Learning - arXiv",
      "url": "https://arxiv.org/abs/2301.12345",
      "domain": "arxiv.org",
      "authority_score": 0.92,
      "credibility": {
        "level": "HIGH",
        "score": 0.85
      },
      "weight": 0.78
    }
  ],
  "confidence": {
    "score": 0.82,
    "details": {
      "sourceAgreement": 0.85,
      "dataFreshness": 0.90,
      "sourceCountConfidence": 0.70,
      "authorityDiversity": 0.80,
      "expertConsensus": 0.88,
      "contradictionPresence": 0.0
    },
    "warning": null,
    "contradictionsFound": false
  }
}
```

### Output with Low Confidence Warning

```json
{
  "results": [...],
  "confidence": {
    "score": 0.35,
    "details": {
      "sourceAgreement": 0.30,
      "dataFreshness": 0.50,
      "sourceCountConfidence": 0.20,
      "authorityDiversity": 0.40,
      "expertConsensus": 0.25,
      "contradictionPresence": 0.60
    },
    "warning": "⚠️ Findings contradictory—use caution",
    "contradictionsFound": true
  }
}
```

---

## Related

- [GSearch Overview](./00-overview.md) - CLI overview
- [Seedable Config Pattern](../../04-coding-guidelines/05-seedable-config-pattern.md) - Configuration pattern
- [Agentic Search](../26-ai-code-generation/10-agentic-search.md) - Search pipeline integration
