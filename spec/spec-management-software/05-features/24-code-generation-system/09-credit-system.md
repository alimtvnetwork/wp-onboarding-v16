# Credit System

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-29  

---

## Overview

The Credit System tracks and manages usage consumption for AI-powered code generation. Credits are consumed based on AI requests, files generated, and build verification cycles, providing a transparent billing model similar to Lovable's credit system.

**Cross-References:**
- [Architecture](./01-architecture.md)
- [Parallel Code Generation](./03-parallel-code-generation.md)
- [User Management](../02-project-management/01-user-management.md)

---

## Credit Types

### Consumption Triggers

| Type | Trigger | Default Rate | Description |
|------|---------|--------------|-------------|
| `ai_request` | Per LLM API call | 0.01 per 1K tokens | Token-based consumption |
| `file_generated` | Per file created/modified | 0.05 per file | Fixed per-file cost |
| `build_cycle` | Per brun verification | 0.10 per cycle | Build verification cost |

### Consumption Formula

```
Total Credits = Σ(AI Token Credits) + Σ(File Credits) + Σ(Build Credits)

Where:
- AI Token Credits = (input_tokens + output_tokens) / 1000 * ai_request_rate
- File Credits = files_generated * file_rate
- Build Credits = build_cycles * build_rate
```

---

## Data Models

### UserCredits

```go
type UserCredits struct {
    ID            string    `gorm:"primaryKey;type:text"`
    UserID        string    `gorm:"type:text;not null;uniqueIndex"`
    Balance       float64   `gorm:"type:real;default:0"`
    TotalPurchased float64  `gorm:"type:real;default:0"`
    TotalConsumed float64   `gorm:"type:real;default:0"`
    FreeCredits   float64   `gorm:"type:real;default:0"`     // Monthly free allocation
    FreeResetAt   time.Time                                   // When free credits reset
    CreatedAt     time.Time
    UpdatedAt     time.Time
    
    // Relationships
    User          User `gorm:"foreignKey:UserID"`
}
```

### CreditTransaction

```go
type CreditTransaction struct {
    ID              string          `gorm:"primaryKey;type:text"`
    UserID          string          `gorm:"type:text;not null;index"`
    ProjectID       string          `gorm:"type:text;index"`
    Type            TransactionType `gorm:"type:text;not null"`
    Amount          float64         `gorm:"type:real;not null"`
    BalanceAfter    float64         `gorm:"type:real;not null"`
    CreditType      CreditType      `gorm:"type:text"`            // For consumption
    Description     string          `gorm:"type:text"`
    Metadata        string          `gorm:"type:text"`            // JSON
    GenerationRunID string          `gorm:"type:text;index"`
    CreatedAt       time.Time       `gorm:"index"`
}

type TransactionType string

const (
    TransactionTypePurchase    TransactionType = "purchase"
    TransactionTypeConsumption TransactionType = "consumption"
    TransactionTypeRefund      TransactionType = "refund"
    TransactionTypeFreeGrant   TransactionType = "free_grant"
    TransactionTypeExpiry      TransactionType = "expiry"
)

type CreditType string

const (
    CreditTypeAIRequest     CreditType = "ai_request"
    CreditTypeFileGenerated CreditType = "file_generated"
    CreditTypeBuildCycle    CreditType = "build_cycle"
)
```

### CreditPlan

```go
type CreditPlan struct {
    ID              string    `gorm:"primaryKey;type:text"`
    Name            string    `gorm:"type:text;not null"`
    Description     string    `gorm:"type:text"`
    CreditsIncluded float64   `gorm:"type:real;not null"`
    PriceUSD        float64   `gorm:"type:real;not null"`
    MonthlyFree     float64   `gorm:"type:real;default:0"`
    IsActive        bool      `gorm:"type:boolean;default:true"`
    Features        string    `gorm:"type:text"`                // JSON array
    CreatedAt       time.Time
}
```

---

## Credit Tracker Service

### Core Operations

```go
type CreditTracker struct {
    db           *gorm.DB
    rates        *CreditRates
    auditLogger  *AuditLogger
}

type CreditRates struct {
    AIRequestPerKTokens float64 // Default: 0.01
    FileGenerated       float64 // Default: 0.05
    BuildCycle          float64 // Default: 0.10
}

func NewCreditTracker(db *gorm.DB, rates *CreditRates) *CreditTracker {
    return &CreditTracker{
        db:    db,
        rates: rates,
    }
}

// Check if user has sufficient credits for an operation
func (t *CreditTracker) HasSufficientCredits(userID string, estimated float64) (bool, error) {
    balance, err := t.GetBalance(userID)
    if err != nil {
        return false, err
    }
    return balance >= estimated, nil
}

// Get current balance
func (t *CreditTracker) GetBalance(userID string) (float64, error) {
    var credits UserCredits
    if err := t.db.Where("user_id = ?", userID).First(&credits).Error; err != nil {
        if errors.Is(err, gorm.ErrRecordNotFound) {
            return 0, nil
        }
        return 0, err
    }
    return credits.Balance, nil
}

// Consume credits atomically
func (t *CreditTracker) Consume(consumption CreditConsumption) error {
    return t.db.Transaction(func(tx *gorm.DB) error {
        // Lock user credits row
        var credits UserCredits
        if err := tx.Clauses(clause.Locking{Strength: "UPDATE"}).
            Where("user_id = ?", consumption.UserID).
            First(&credits).Error; err != nil {
            return err
        }
        
        // Check balance
        if credits.Balance < consumption.Amount {
            return ErrInsufficientCredits
        }
        
        // Deduct credits
        credits.Balance -= consumption.Amount
        credits.TotalConsumed += consumption.Amount
        credits.UpdatedAt = time.Now()
        
        if err := tx.Save(&credits).Error; err != nil {
            return err
        }
        
        // Record transaction
        transaction := CreditTransaction{
            ID:              uuid.New().String(),
            UserID:          consumption.UserID,
            ProjectID:       consumption.ProjectID,
            Type:            TransactionTypeConsumption,
            Amount:          -consumption.Amount,
            BalanceAfter:    credits.Balance,
            CreditType:      consumption.Type,
            Description:     consumption.Description,
            Metadata:        toJSON(consumption.Metadata),
            GenerationRunID: consumption.GenerationRunID,
            CreatedAt:       time.Now(),
        }
        
        return tx.Create(&transaction).Error
    })
}
```

### Credit Estimation

```go
type CreditEstimator struct {
    rates *CreditRates
}

type EstimationInput struct {
    Plan              *GenerationPlan
    EstimatedTokens   int    // Total estimated tokens
}

type CreditEstimate struct {
    AIRequestCredits    float64
    FileCredits         float64
    BuildCredits        float64
    TotalEstimate       float64
    Breakdown           []EstimateBreakdown
}

type EstimateBreakdown struct {
    Category    string
    Count       int
    Rate        float64
    Subtotal    float64
}

func (e *CreditEstimator) Estimate(input EstimationInput) *CreditEstimate {
    estimate := &CreditEstimate{}
    
    // AI Request credits
    aiCredits := float64(input.EstimatedTokens) / 1000.0 * e.rates.AIRequestPerKTokens
    estimate.AIRequestCredits = aiCredits
    estimate.Breakdown = append(estimate.Breakdown, EstimateBreakdown{
        Category: "AI Tokens",
        Count:    input.EstimatedTokens,
        Rate:     e.rates.AIRequestPerKTokens,
        Subtotal: aiCredits,
    })
    
    // File credits
    fileCount := len(input.Plan.Files)
    fileCredits := float64(fileCount) * e.rates.FileGenerated
    estimate.FileCredits = fileCredits
    estimate.Breakdown = append(estimate.Breakdown, EstimateBreakdown{
        Category: "Files Generated",
        Count:    fileCount,
        Rate:     e.rates.FileGenerated,
        Subtotal: fileCredits,
    })
    
    // Build credits (estimate 1-2 cycles)
    buildCredits := e.rates.BuildCycle * 2 // Assume 2 build cycles (initial + fix)
    estimate.BuildCredits = buildCredits
    estimate.Breakdown = append(estimate.Breakdown, EstimateBreakdown{
        Category: "Build Cycles",
        Count:    2,
        Rate:     e.rates.BuildCycle,
        Subtotal: buildCredits,
    })
    
    estimate.TotalEstimate = aiCredits + fileCredits + buildCredits
    
    return estimate
}
```

### Pre-Generation Credit Check

```go
type CreditGuard struct {
    tracker   *CreditTracker
    estimator *CreditEstimator
}

type CreditCheckResult struct {
    Allowed         bool
    CurrentBalance  float64
    EstimatedCost   float64
    Shortage        float64
    RequiresPurchase bool
}

func (g *CreditGuard) CheckBeforeGeneration(
    userID string,
    plan *GenerationPlan,
) (*CreditCheckResult, error) {
    
    balance, err := g.tracker.GetBalance(userID)
    if err != nil {
        return nil, err
    }
    
    estimate := g.estimator.Estimate(EstimationInput{
        Plan:            plan,
        EstimatedTokens: calculateEstimatedTokens(plan),
    })
    
    result := &CreditCheckResult{
        CurrentBalance: balance,
        EstimatedCost:  estimate.TotalEstimate,
    }
    
    if balance >= estimate.TotalEstimate {
        result.Allowed = true
    } else {
        result.Allowed = false
        result.Shortage = estimate.TotalEstimate - balance
        result.RequiresPurchase = true
    }
    
    return result, nil
}
```

---

## Credit Plans

### Default Plans (Seeded)

| Plan | Credits | Price | Monthly Free | Features |
|------|---------|-------|--------------|----------|
| Free | 0 | $0 | 100 | Basic code generation |
| Starter | 500 | $9 | 100 | Priority support |
| Professional | 2000 | $29 | 200 | Advanced models, faster generation |
| Enterprise | 10000 | $99 | 500 | Custom models, dedicated resources |

### Seeding Configuration

```go
var defaultPlans = []CreditPlan{
    {
        ID:              "plan_free",
        Name:            "Free",
        Description:     "Basic access with monthly free credits",
        CreditsIncluded: 0,
        PriceUSD:        0,
        MonthlyFree:     100,
        Features:        `["Basic code generation", "Standard models", "Community support"]`,
    },
    {
        ID:              "plan_starter",
        Name:            "Starter",
        Description:     "For individual developers",
        CreditsIncluded: 500,
        PriceUSD:        9,
        MonthlyFree:     100,
        Features:        `["Everything in Free", "Priority support", "Advanced analytics"]`,
    },
    {
        ID:              "plan_professional",
        Name:            "Professional",
        Description:     "For professional teams",
        CreditsIncluded: 2000,
        PriceUSD:        29,
        MonthlyFree:     200,
        Features:        `["Everything in Starter", "Advanced models", "Faster generation", "API access"]`,
    },
    {
        ID:              "plan_enterprise",
        Name:            "Enterprise",
        Description:     "For large organizations",
        CreditsIncluded: 10000,
        PriceUSD:        99,
        MonthlyFree:     500,
        Features:        `["Everything in Professional", "Custom models", "Dedicated resources", "SLA"]`,
    },
}
```

---

## Monthly Free Credits

### Free Credit Reset

```go
func (t *CreditTracker) ProcessMonthlyFreeCredits() error {
    // Find users whose free credits need reset
    now := time.Now()
    
    var users []UserCredits
    if err := t.db.Where("free_reset_at < ?", now).Find(&users).Error; err != nil {
        return err
    }
    
    for _, user := range users {
        // Get user's plan
        plan, err := t.getUserPlan(user.UserID)
        if err != nil {
            continue
        }
        
        // Reset free credits
        if err := t.db.Transaction(func(tx *gorm.DB) error {
            // Expire remaining free credits
            if user.FreeCredits > 0 {
                transaction := CreditTransaction{
                    ID:           uuid.New().String(),
                    UserID:       user.UserID,
                    Type:         TransactionTypeExpiry,
                    Amount:       -user.FreeCredits,
                    BalanceAfter: user.Balance - user.FreeCredits,
                    Description:  "Monthly free credits expired",
                    CreatedAt:    now,
                }
                if err := tx.Create(&transaction).Error; err != nil {
                    return err
                }
            }
            
            // Grant new free credits
            user.Balance = user.Balance - user.FreeCredits + plan.MonthlyFree
            user.FreeCredits = plan.MonthlyFree
            user.FreeResetAt = now.AddDate(0, 1, 0)  // Next month
            
            if err := tx.Save(&user).Error; err != nil {
                return err
            }
            
            // Record grant
            transaction := CreditTransaction{
                ID:           uuid.New().String(),
                UserID:       user.UserID,
                Type:         TransactionTypeFreeGrant,
                Amount:       plan.MonthlyFree,
                BalanceAfter: user.Balance,
                Description:  fmt.Sprintf("Monthly free credits grant (%s plan)", plan.Name),
                CreatedAt:    now,
            }
            return tx.Create(&transaction).Error
        }); err != nil {
            log.Printf("Failed to reset free credits for user %s: %v", user.UserID, err)
        }
    }
    
    return nil
}
```

---

## Usage Dashboard

### API Endpoints

```
GET /api/v1/credits/balance
Response: { balance: float, free_credits: float, free_reset_at: datetime }

GET /api/v1/credits/transactions
Query: ?page=1&limit=20&type=consumption
Response: { transactions: CreditTransaction[], total: int }

GET /api/v1/credits/usage
Query: ?period=month
Response: { 
    ai_requests: { count: int, credits: float },
    files_generated: { count: int, credits: float },
    build_cycles: { count: int, credits: float },
    total: float
}

POST /api/v1/credits/estimate
Body: { plan_id: string }
Response: { estimate: CreditEstimate }
```

### Frontend Component

```typescript
interface CreditDashboardProps {
    userId: string;
}

interface CreditUsage {
    aiRequests: { count: number; credits: number };
    filesGenerated: { count: number; credits: number };
    buildCycles: { count: number; credits: number };
    total: number;
}

// Features:
// - Current balance display with progress bar
// - Usage breakdown chart (pie/bar)
// - Transaction history table
// - Credit purchase options
// - Monthly free credits countdown
```

---

## Configuration

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `credits.rate.aiRequestPerKTokens` | float | 0.01 | Credits per 1K tokens |
| `credits.rate.fileGenerated` | float | 0.05 | Credits per file |
| `credits.rate.buildCycle` | float | 0.10 | Credits per build cycle |
| `credits.free.defaultMonthly` | float | 100 | Default monthly free credits |
| `credits.free.resetDay` | int | 1 | Day of month to reset free credits |
| `credits.warning.threshold` | float | 20 | Warn when balance below this |

---

## Error Codes

| Code | Constant | Description |
|------|----------|-------------|
| 8600 | ERR_CREDITS_INSUFFICIENT | Insufficient credits for operation |
| 8601 | ERR_CREDITS_ESTIMATION_FAILED | Failed to estimate credit cost |
| 8602 | ERR_CREDITS_TRANSACTION_FAILED | Failed to record transaction |
| 8603 | ERR_CREDITS_PLAN_NOT_FOUND | Credit plan not found |
| 8604 | ERR_CREDITS_PURCHASE_FAILED | Credit purchase failed |

---

## Related Specs

- [Architecture](./01-architecture.md)
- [Parallel Code Generation](./03-parallel-code-generation.md)
- [User Management](../02-project-management/01-user-management.md)
