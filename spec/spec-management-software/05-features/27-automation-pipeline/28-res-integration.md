# RES Integration Specification

**Version:** 1.0.0  
**Status:** Specified  
**Updated:** 2026-01-30  
**Parent:** [Automation Pipeline](./00-overview.md)

---

## Overview

Integration layer connecting the Automation Pipeline to the Resilient Execution System (RES) for enterprise-grade fault tolerance. This specification defines how pipeline stages leverage RES mechanisms including Self-Correction, Multi-Model Consensus, Checkpoint & Rollback, Adaptive Retry, and Human Escalation.

**Cross-References:**
- [Resilient Execution System](../06-ai-integration/12-resilient-execution-system.md)
- [Error Handlers](./15-error-handlers.md)
- [Telemetry Integration](./18-telemetry-integration.md)
- [Escalation Notifications](../../05-features/26-escalation-notifications/00-overview.md)

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                      Automation Pipeline                             │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐                  │
│  │   Stage 1   │──│   Stage 2   │──│   Stage N   │                  │
│  └──────┬──────┘  └──────┬──────┘  └──────┬──────┘                  │
│         │                │                │                          │
│         └────────────────┼────────────────┘                          │
│                          ▼                                           │
│  ┌───────────────────────────────────────────────────────────────┐  │
│  │                   RES Integration Layer                        │  │
│  │  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐         │  │
│  │  │   Failure    │  │  Recovery    │  │  Escalation  │         │  │
│  │  │   Detector   │  │   Router     │  │   Gateway    │         │  │
│  │  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘         │  │
│  └─────────┼─────────────────┼─────────────────┼─────────────────┘  │
│            │                 │                 │                     │
└────────────┼─────────────────┼─────────────────┼─────────────────────┘
             │                 │                 │
             ▼                 ▼                 ▼
┌─────────────────────────────────────────────────────────────────────┐
│                   Resilient Execution System                         │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐  ┌────────────┐ │
│  │    Self     │  │   Multi     │  │ Checkpoint  │  │  Adaptive  │ │
│  │ Correction  │  │  Consensus  │  │ & Rollback  │  │   Retry    │ │
│  └─────────────┘  └─────────────┘  └─────────────┘  └────────────┘ │
│                           │                                          │
│                           ▼                                          │
│                    ┌─────────────┐                                   │
│                    │   Human     │                                   │
│                    │ Escalation  │                                   │
│                    └─────────────┘                                   │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Database Schema

### RESIntegrationConfig Table

```sql
CREATE TABLE RESIntegrationConfig (
  Id                    TEXT PRIMARY KEY,
  PipelineId            TEXT NOT NULL REFERENCES Pipeline(Id) ON DELETE CASCADE,
  
  -- Feature toggles
  EnableSelfCorrection  INTEGER DEFAULT 1,
  EnableConsensus       INTEGER DEFAULT 0,
  EnableCheckpoints     INTEGER DEFAULT 1,
  EnableAdaptiveRetry   INTEGER DEFAULT 1,
  EnableEscalation      INTEGER DEFAULT 1,
  
  -- Thresholds
  ConsensusThreshold    REAL DEFAULT 0.7,      -- Agreement threshold for consensus
  ConfidenceMinimum     REAL DEFAULT 0.6,      -- Min confidence before escalation
  RiskThreshold         TEXT DEFAULT 'MEDIUM', -- Risk level triggering consensus
  
  -- Retry configuration
  MaxGlobalRetries      INTEGER DEFAULT 5,
  RetryBudgetSeconds    INTEGER DEFAULT 300,
  
  -- Model preferences
  PrimaryModel          TEXT DEFAULT 'gemini-3-flash-preview',
  FallbackModels        TEXT,                  -- JSON array of fallback models
  ConsensusModels       TEXT,                  -- JSON array for voting
  
  CreatedAt             TEXT NOT NULL DEFAULT (datetime('now')),
  UpdatedAt             TEXT NOT NULL DEFAULT (datetime('now')),
  
  UNIQUE(PipelineId)
);
```

### StageRESConfig Table

```sql
CREATE TABLE StageRESConfig (
  Id                    TEXT PRIMARY KEY,
  StageId               TEXT NOT NULL REFERENCES Stage(Id) ON DELETE CASCADE,
  
  -- Override pipeline defaults
  EnableSelfCorrection  INTEGER,               -- NULL = inherit from pipeline
  EnableConsensus       INTEGER,
  RequireConsensus      INTEGER DEFAULT 0,     -- Force consensus for this stage
  
  -- Stage-specific settings
  CriticalityLevel      TEXT DEFAULT 'NORMAL', -- 'LOW', 'NORMAL', 'HIGH', 'CRITICAL'
  MaxSelfCorrectionAttempts INTEGER DEFAULT 3,
  ConsensusModelCount   INTEGER DEFAULT 3,
  
  -- Checkpoint behavior
  CreateCheckpointBefore INTEGER DEFAULT 0,
  CreateCheckpointAfter  INTEGER DEFAULT 1,
  
  -- Rollback scope
  RollbackScope         TEXT DEFAULT 'STAGE',  -- 'STAGE', 'BLOCK', 'PIPELINE'
  
  CreatedAt             TEXT NOT NULL DEFAULT (datetime('now')),
  
  UNIQUE(StageId)
);
```

### RESExecutionLog Table

```sql
CREATE TABLE RESExecutionLog (
  Id                    TEXT PRIMARY KEY,
  StageExecutionId      TEXT NOT NULL REFERENCES StageExecution(Id),
  
  -- RES mechanism used
  Mechanism             TEXT NOT NULL,         -- 'SELF_CORRECTION', 'CONSENSUS', 'CHECKPOINT', 'ADAPTIVE_RETRY', 'ESCALATION'
  
  -- Execution details
  AttemptNumber         INTEGER NOT NULL,
  ModelUsed             TEXT,
  PromptVariation       TEXT,                  -- For self-correction
  Temperature           REAL,
  
  -- Results
  Success               INTEGER NOT NULL,
  ConfidenceScore       REAL,
  ErrorCategory         TEXT,
  
  -- Consensus specific
  VotingResults         TEXT,                  -- JSON: model votes
  AgreementScore        REAL,
  
  -- Timing
  StartedAt             TEXT NOT NULL,
  CompletedAt           TEXT,
  DurationMs            INTEGER,
  
  CreatedAt             TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX idx_res_log_stage ON RESExecutionLog(StageExecutionId);
CREATE INDEX idx_res_log_mechanism ON RESExecutionLog(Mechanism);
```

---

## TypeScript Interfaces

### Configuration Types

```typescript
enum RESMechanism {
  SELF_CORRECTION = 'SELF_CORRECTION',
  MULTI_MODEL_CONSENSUS = 'MULTI_MODEL_CONSENSUS',
  CHECKPOINT_ROLLBACK = 'CHECKPOINT_ROLLBACK',
  ADAPTIVE_RETRY = 'ADAPTIVE_RETRY',
  HUMAN_ESCALATION = 'HUMAN_ESCALATION'
}

enum CriticalityLevel {
  LOW = 'LOW',
  NORMAL = 'NORMAL',
  HIGH = 'HIGH',
  CRITICAL = 'CRITICAL'
}

enum RiskLevel {
  LOW = 'LOW',
  MEDIUM = 'MEDIUM',
  HIGH = 'HIGH'
}

enum RollbackScope {
  STAGE = 'STAGE',
  BLOCK = 'BLOCK',
  PIPELINE = 'PIPELINE'
}

interface RESIntegrationConfig {
  readonly id: string;
  readonly pipelineId: string;
  
  // Feature toggles
  readonly enableSelfCorrection: boolean;
  readonly enableConsensus: boolean;
  readonly enableCheckpoints: boolean;
  readonly enableAdaptiveRetry: boolean;
  readonly enableEscalation: boolean;
  
  // Thresholds
  readonly consensusThreshold: number;
  readonly confidenceMinimum: number;
  readonly riskThreshold: RiskLevel;
  
  // Retry config
  readonly maxGlobalRetries: number;
  readonly retryBudgetSeconds: number;
  
  // Models
  readonly primaryModel: string;
  readonly fallbackModels: readonly string[];
  readonly consensusModels: readonly string[];
}

interface StageRESConfig {
  readonly id: string;
  readonly stageId: string;
  
  readonly enableSelfCorrection: boolean | null;
  readonly enableConsensus: boolean | null;
  readonly requireConsensus: boolean;
  
  readonly criticalityLevel: CriticalityLevel;
  readonly maxSelfCorrectionAttempts: number;
  readonly consensusModelCount: number;
  
  readonly createCheckpointBefore: boolean;
  readonly createCheckpointAfter: boolean;
  readonly rollbackScope: RollbackScope;
}
```

### Execution Types

```typescript
interface RESExecutionContext {
  readonly pipelineExecutionId: string;
  readonly stageExecutionId: string;
  readonly stageId: string;
  readonly blockId: string;
  
  readonly config: RESIntegrationConfig;
  readonly stageConfig: StageRESConfig | null;
  
  readonly attemptHistory: readonly RESAttempt[];
  readonly currentCheckpoint: ExecutionCheckpoint | null;
  
  readonly retryBudgetRemaining: number;
  readonly globalAttemptCount: number;
}

interface RESAttempt {
  readonly mechanism: RESMechanism;
  readonly attemptNumber: number;
  readonly modelUsed: string;
  readonly temperature: number;
  readonly promptVariation: string | null;
  
  readonly success: boolean;
  readonly confidenceScore: number | null;
  readonly error: RESError | null;
  
  readonly durationMs: number;
  readonly tokensUsed: number;
}

interface RESError {
  readonly category: ErrorCategory;
  readonly message: string;
  readonly isRetryable: boolean;
  readonly suggestedMechanism: RESMechanism | null;
}

enum ErrorCategory {
  MODEL_FAILURE = 'MODEL_FAILURE',
  VALIDATION_FAILURE = 'VALIDATION_FAILURE',
  TIMEOUT = 'TIMEOUT',
  RATE_LIMIT = 'RATE_LIMIT',
  CONTEXT_OVERFLOW = 'CONTEXT_OVERFLOW',
  PARSE_ERROR = 'PARSE_ERROR',
  NETWORK_ERROR = 'NETWORK_ERROR',
  UNKNOWN = 'UNKNOWN'
}
```

---

## RES Integration Service

### Core Interface

```typescript
interface RESIntegrationService {
  // Configuration
  getConfig(pipelineId: string): Promise<RESIntegrationConfig>;
  updateConfig(pipelineId: string, updates: Partial<RESIntegrationConfig>): Promise<RESIntegrationConfig>;
  getStageConfig(stageId: string): Promise<StageRESConfig | null>;
  updateStageConfig(stageId: string, updates: Partial<StageRESConfig>): Promise<StageRESConfig>;
  
  // Execution
  executeWithResilience(context: RESExecutionContext, operation: StageOperation): Promise<RESExecutionResult>;
  
  // Individual mechanisms
  attemptSelfCorrection(context: RESExecutionContext, error: RESError): Promise<SelfCorrectionResult>;
  requestConsensus(context: RESExecutionContext, operation: StageOperation): Promise<ConsensusResult>;
  createCheckpoint(context: RESExecutionContext): Promise<ExecutionCheckpoint>;
  rollback(context: RESExecutionContext, checkpointId: string): Promise<RollbackResult>;
  escalateToHuman(context: RESExecutionContext, reason: EscalationReason): Promise<EscalationResult>;
  
  // Retry management
  selectRetryStrategy(context: RESExecutionContext, error: RESError): RetryStrategy;
  calculateBackoff(attemptNumber: number, strategy: RetryStrategy): number;
}

interface StageOperation {
  readonly stageType: StageType;
  readonly config: StageConfig;
  readonly resolvedInputs: Record<string, unknown>;
  readonly execute: () => Promise<StageOutput>;
}

interface RESExecutionResult {
  readonly success: boolean;
  readonly output: StageOutput | null;
  readonly mechanismsUsed: readonly RESMechanism[];
  readonly totalAttempts: number;
  readonly totalDurationMs: number;
  readonly finalConfidence: number | null;
  readonly wasEscalated: boolean;
  readonly escalationResolution: EscalationResolution | null;
}
```

### Self-Correction Implementation

```typescript
interface SelfCorrectionAgent {
  // Analyze failure and suggest corrections
  analyzeError(
    error: RESError,
    originalPrompt: string,
    previousAttempts: readonly RESAttempt[]
  ): Promise<CorrectionAnalysis>;
  
  // Generate alternative prompt
  generateCorrectedPrompt(
    analysis: CorrectionAnalysis,
    originalPrompt: string
  ): Promise<string>;
  
  // Re-execute with correction
  executeWithCorrection(
    context: RESExecutionContext,
    correctedPrompt: string,
    adjustedParams: ModelParams
  ): Promise<SelfCorrectionResult>;
}

interface CorrectionAnalysis {
  readonly errorType: ErrorCategory;
  readonly rootCause: string;
  readonly suggestedFixes: readonly SuggestedFix[];
  readonly recommendedModelChange: string | null;
  readonly recommendedTemperatureChange: number | null;
  readonly confidence: number;
}

interface SuggestedFix {
  readonly type: FixType;
  readonly description: string;
  readonly promptModification: string | null;
}

enum FixType {
  PROMPT_CLARIFICATION = 'PROMPT_CLARIFICATION',
  CONTEXT_REDUCTION = 'CONTEXT_REDUCTION',
  OUTPUT_FORMAT_CHANGE = 'OUTPUT_FORMAT_CHANGE',
  TEMPERATURE_ADJUSTMENT = 'TEMPERATURE_ADJUSTMENT',
  MODEL_SWITCH = 'MODEL_SWITCH',
  TASK_DECOMPOSITION = 'TASK_DECOMPOSITION'
}

interface SelfCorrectionResult {
  readonly success: boolean;
  readonly correctionApplied: SuggestedFix;
  readonly output: StageOutput | null;
  readonly newConfidence: number;
  readonly shouldContinueCorrection: boolean;
}
```

### Multi-Model Consensus

```typescript
interface ConsensusEngine {
  // Execute across multiple models
  executeWithConsensus(
    context: RESExecutionContext,
    operation: StageOperation,
    models: readonly string[]
  ): Promise<ConsensusResult>;
  
  // Compare outputs semantically
  compareOutputs(
    outputs: readonly ModelOutput[],
    stageType: StageType
  ): Promise<SemanticComparison>;
  
  // Vote and resolve
  resolveConsensus(
    comparison: SemanticComparison,
    threshold: number
  ): ConsensusResolution;
}

interface ModelOutput {
  readonly model: string;
  readonly output: StageOutput;
  readonly confidence: number;
  readonly durationMs: number;
  readonly tokensUsed: number;
}

interface SemanticComparison {
  readonly outputs: readonly ModelOutput[];
  readonly agreementMatrix: readonly readonly number[][];  // Pairwise similarity
  readonly clusters: readonly OutputCluster[];
  readonly overallAgreement: number;
}

interface OutputCluster {
  readonly outputs: readonly ModelOutput[];
  readonly representativeOutput: ModelOutput;
  readonly internalAgreement: number;
  readonly clusterWeight: number;  // Based on model reliability
}

interface ConsensusResult {
  readonly reached: boolean;
  readonly agreementScore: number;
  readonly selectedOutput: StageOutput | null;
  readonly votingDetails: readonly ModelVote[];
  readonly dissent: readonly DissentRecord[];
}

interface ModelVote {
  readonly model: string;
  readonly output: StageOutput;
  readonly confidence: number;
  readonly agreedWithConsensus: boolean;
}

interface DissentRecord {
  readonly model: string;
  readonly output: StageOutput;
  readonly divergenceReason: string;
  readonly severity: 'MINOR' | 'MAJOR' | 'CRITICAL';
}
```

### Checkpoint & Rollback

```typescript
interface CheckpointManager {
  // Create checkpoint
  createCheckpoint(
    context: RESExecutionContext,
    type: CheckpointType
  ): Promise<ExecutionCheckpoint>;
  
  // List checkpoints for execution
  listCheckpoints(
    pipelineExecutionId: string
  ): Promise<readonly ExecutionCheckpoint[]>;
  
  // Rollback to checkpoint
  rollback(
    checkpointId: string,
    scope: RollbackScope
  ): Promise<RollbackResult>;
  
  // Cleanup old checkpoints
  pruneCheckpoints(
    pipelineExecutionId: string,
    retentionPolicy: RetentionPolicy
  ): Promise<number>;
}

enum CheckpointType {
  STAGE_BEFORE = 'STAGE_BEFORE',
  STAGE_AFTER = 'STAGE_AFTER',
  BLOCK_BEFORE = 'BLOCK_BEFORE',
  BLOCK_AFTER = 'BLOCK_AFTER',
  MANUAL = 'MANUAL'
}

interface ExecutionCheckpoint {
  readonly id: string;
  readonly pipelineExecutionId: string;
  readonly blockId: string;
  readonly stageId: string | null;
  readonly type: CheckpointType;
  
  readonly variableSnapshot: Record<string, unknown>;
  readonly fileManifest: readonly FileRecord[];
  readonly databaseChanges: readonly DBChange[];
  
  readonly createdAt: Date;
  readonly canRollback: boolean;
  readonly sizeBytes: number;
}

interface RollbackResult {
  readonly success: boolean;
  readonly checkpoint: ExecutionCheckpoint;
  readonly restoredVariables: number;
  readonly revertedFiles: number;
  readonly revertedDBChanges: number;
  readonly warnings: readonly string[];
}

interface FileRecord {
  readonly path: string;
  readonly operation: 'CREATE' | 'MODIFY' | 'DELETE';
  readonly previousContent: string | null;
  readonly previousHash: string | null;
}

interface DBChange {
  readonly table: string;
  readonly operation: 'INSERT' | 'UPDATE' | 'DELETE';
  readonly rowId: string;
  readonly previousData: Record<string, unknown> | null;
}
```

### Adaptive Retry

```typescript
interface AdaptiveRetryEngine {
  // Select strategy based on error
  selectStrategy(
    error: RESError,
    attemptHistory: readonly RESAttempt[]
  ): RetryStrategy;
  
  // Calculate next attempt parameters
  calculateNextAttempt(
    strategy: RetryStrategy,
    attemptNumber: number,
    error: RESError
  ): NextAttemptParams;
  
  // Check if should continue retrying
  shouldRetry(
    context: RESExecutionContext,
    error: RESError
  ): RetryDecision;
}

interface RetryStrategy {
  readonly type: RetryStrategyType;
  readonly maxAttempts: number;
  readonly baseDelayMs: number;
  readonly maxDelayMs: number;
  readonly jitterFactor: number;
  readonly modelRotation: boolean;
  readonly temperatureEscalation: boolean;
}

enum RetryStrategyType {
  IMMEDIATE = 'IMMEDIATE',
  LINEAR_BACKOFF = 'LINEAR_BACKOFF',
  EXPONENTIAL_BACKOFF = 'EXPONENTIAL_BACKOFF',
  FIBONACCI_BACKOFF = 'FIBONACCI_BACKOFF',
  ADAPTIVE = 'ADAPTIVE'
}

interface NextAttemptParams {
  readonly delayMs: number;
  readonly model: string;
  readonly temperature: number;
  readonly promptVariation: string | null;
  readonly reduceContext: boolean;
}

interface RetryDecision {
  readonly shouldRetry: boolean;
  readonly reason: string;
  readonly suggestedMechanism: RESMechanism | null;
  readonly escalate: boolean;
}
```

### Human Escalation Gateway

```typescript
interface EscalationGateway {
  // Trigger escalation
  escalate(
    context: RESExecutionContext,
    reason: EscalationReason
  ): Promise<EscalationRequest>;
  
  // Check for resolution
  checkResolution(
    escalationId: string
  ): Promise<EscalationStatus>;
  
  // Apply human decision
  applyResolution(
    escalationId: string,
    resolution: HumanResolution
  ): Promise<EscalationResult>;
}

interface EscalationReason {
  readonly type: EscalationType;
  readonly description: string;
  readonly context: EscalationContext;
  readonly suggestedActions: readonly SuggestedAction[];
  readonly riskLevel: RiskLevel;
  readonly confidenceScore: number;
}

enum EscalationType {
  LOW_CONFIDENCE = 'LOW_CONFIDENCE',
  CONSENSUS_FAILURE = 'CONSENSUS_FAILURE',
  CRITICAL_STAGE = 'CRITICAL_STAGE',
  RETRY_EXHAUSTED = 'RETRY_EXHAUSTED',
  DESTRUCTIVE_ACTION = 'DESTRUCTIVE_ACTION',
  POLICY_VIOLATION = 'POLICY_VIOLATION',
  ANOMALY_DETECTED = 'ANOMALY_DETECTED'
}

interface EscalationContext {
  readonly pipelineName: string;
  readonly stageName: string;
  readonly stageType: StageType;
  readonly attemptsSoFar: number;
  readonly lastError: RESError | null;
  readonly outputPreview: string | null;
  readonly affectedResources: readonly string[];
}

interface SuggestedAction {
  readonly action: ActionType;
  readonly description: string;
  readonly confidence: number;
  readonly risk: RiskLevel;
}

enum ActionType {
  APPROVE_OUTPUT = 'APPROVE_OUTPUT',
  MODIFY_OUTPUT = 'MODIFY_OUTPUT',
  RETRY_WITH_GUIDANCE = 'RETRY_WITH_GUIDANCE',
  SKIP_STAGE = 'SKIP_STAGE',
  ABORT_PIPELINE = 'ABORT_PIPELINE',
  ROLLBACK = 'ROLLBACK',
  MANUAL_OVERRIDE = 'MANUAL_OVERRIDE'
}

interface HumanResolution {
  readonly action: ActionType;
  readonly modifiedOutput: StageOutput | null;
  readonly guidance: string | null;
  readonly resolvedBy: string;
  readonly resolvedAt: Date;
  readonly notes: string | null;
}

interface EscalationResult {
  readonly success: boolean;
  readonly resolution: HumanResolution;
  readonly resumeExecution: boolean;
  readonly outputToUse: StageOutput | null;
}
```

---

## Integration Flow

### Stage Execution with RES

```typescript
const executeStageWithRES = async (
  stage: Stage,
  context: RESExecutionContext
): Promise<RESExecutionResult> => {
  const config = context.config;
  const stageConfig = context.stageConfig;
  const mechanismsUsed: RESMechanism[] = [];
  
  // 1. Create checkpoint if configured
  if (stageConfig?.createCheckpointBefore) {
    await checkpointManager.createCheckpoint(context, CheckpointType.STAGE_BEFORE);
    mechanismsUsed.push(RESMechanism.CHECKPOINT_ROLLBACK);
  }
  
  // 2. Determine if consensus required
  const requireConsensus = 
    stageConfig?.requireConsensus ||
    (stageConfig?.criticalityLevel === CriticalityLevel.CRITICAL && config.enableConsensus);
  
  let result: StageOutput | null = null;
  let attempts = 0;
  let lastError: RESError | null = null;
  
  while (attempts < config.maxGlobalRetries && context.retryBudgetRemaining > 0) {
    attempts++;
    
    try {
      // 3. Execute with or without consensus
      if (requireConsensus) {
        const consensusResult = await consensusEngine.executeWithConsensus(
          context,
          createOperation(stage),
          config.consensusModels
        );
        mechanismsUsed.push(RESMechanism.MULTI_MODEL_CONSENSUS);
        
        if (consensusResult.reached) {
          result = consensusResult.selectedOutput;
          break;
        } else {
          // Escalate if consensus fails
          if (config.enableEscalation) {
            return await handleEscalation(context, {
              type: EscalationType.CONSENSUS_FAILURE,
              description: 'Models failed to reach consensus',
              // ... context details
            });
          }
        }
      } else {
        // Standard execution
        result = await executeStage(stage, context);
        break;
      }
    } catch (error) {
      lastError = categorizeError(error);
      
      // 4. Attempt self-correction
      if (config.enableSelfCorrection && lastError.isRetryable) {
        const correction = await selfCorrectionAgent.attemptSelfCorrection(context, lastError);
        mechanismsUsed.push(RESMechanism.SELF_CORRECTION);
        
        if (correction.success) {
          result = correction.output;
          break;
        }
      }
      
      // 5. Adaptive retry
      if (config.enableAdaptiveRetry) {
        const retryDecision = adaptiveRetry.shouldRetry(context, lastError);
        
        if (retryDecision.shouldRetry) {
          mechanismsUsed.push(RESMechanism.ADAPTIVE_RETRY);
          const nextParams = adaptiveRetry.calculateNextAttempt(
            adaptiveRetry.selectStrategy(lastError, context.attemptHistory),
            attempts,
            lastError
          );
          await delay(nextParams.delayMs);
          continue;
        }
        
        if (retryDecision.escalate) {
          break; // Fall through to escalation
        }
      }
    }
  }
  
  // 6. Escalate if all else fails
  if (!result && config.enableEscalation) {
    const escalation = await escalationGateway.escalate(context, {
      type: EscalationType.RETRY_EXHAUSTED,
      description: `Stage failed after ${attempts} attempts`,
      riskLevel: stageConfig?.criticalityLevel === CriticalityLevel.CRITICAL 
        ? RiskLevel.HIGH 
        : RiskLevel.MEDIUM,
      // ... context
    });
    mechanismsUsed.push(RESMechanism.HUMAN_ESCALATION);
    
    // Wait for resolution
    const resolution = await waitForResolution(escalation.id);
    result = resolution.outputToUse;
  }
  
  // 7. Create checkpoint after success
  if (result && stageConfig?.createCheckpointAfter) {
    await checkpointManager.createCheckpoint(context, CheckpointType.STAGE_AFTER);
  }
  
  return {
    success: result !== null,
    output: result,
    mechanismsUsed,
    totalAttempts: attempts,
    totalDurationMs: Date.now() - context.startTime,
    finalConfidence: result?.confidence ?? null,
    wasEscalated: mechanismsUsed.includes(RESMechanism.HUMAN_ESCALATION),
    escalationResolution: null
  };
};
```

---

## React Components

### RESConfigPanel

```typescript
interface RESConfigPanelProps {
  readonly pipelineId: string;
  readonly stageId?: string;
}

const RESConfigPanel: React.FC<RESConfigPanelProps> = ({
  pipelineId,
  stageId
}) => {
  const { data: config } = useRESConfig(pipelineId);
  const { data: stageConfig } = useStageRESConfig(stageId);
  
  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <Shield className="h-5 w-5" />
          Resilience Settings
        </CardTitle>
        <CardDescription>
          Configure fault tolerance for {stageId ? 'this stage' : 'the pipeline'}
        </CardDescription>
      </CardHeader>
      
      <CardContent className="space-y-6">
        {/* Feature Toggles */}
        <div className="space-y-4">
          <h4 className="text-sm font-medium">Enabled Mechanisms</h4>
          
          <div className="grid grid-cols-2 gap-4">
            <FeatureToggle
              label="Self-Correction"
              description="AI analyzes and fixes its own errors"
              checked={config?.enableSelfCorrection}
              icon={<RefreshCcw className="h-4 w-4" />}
            />
            
            <FeatureToggle
              label="Multi-Model Consensus"
              description="Run critical tasks across multiple models"
              checked={config?.enableConsensus}
              icon={<Users className="h-4 w-4" />}
            />
            
            <FeatureToggle
              label="Checkpoints"
              description="Save state for rollback capability"
              checked={config?.enableCheckpoints}
              icon={<Save className="h-4 w-4" />}
            />
            
            <FeatureToggle
              label="Adaptive Retry"
              description="Smart retry with model/temp variation"
              checked={config?.enableAdaptiveRetry}
              icon={<RotateCcw className="h-4 w-4" />}
            />
            
            <FeatureToggle
              label="Human Escalation"
              description="Route to humans when AI uncertain"
              checked={config?.enableEscalation}
              icon={<UserCheck className="h-4 w-4" />}
            />
          </div>
        </div>
        
        <Separator />
        
        {/* Thresholds */}
        <div className="space-y-4">
          <h4 className="text-sm font-medium">Thresholds</h4>
          
          <div className="space-y-2">
            <Label>Consensus Agreement Threshold</Label>
            <Slider
              value={[config?.consensusThreshold ?? 0.7]}
              min={0.5}
              max={1.0}
              step={0.05}
              onValueChange={(v) => updateConfig({ consensusThreshold: v[0] })}
            />
            <p className="text-xs text-muted-foreground">
              Minimum agreement between models: {(config?.consensusThreshold ?? 0.7) * 100}%
            </p>
          </div>
          
          <div className="space-y-2">
            <Label>Confidence Threshold for Escalation</Label>
            <Slider
              value={[config?.confidenceMinimum ?? 0.6]}
              min={0.3}
              max={0.9}
              step={0.05}
              onValueChange={(v) => updateConfig({ confidenceMinimum: v[0] })}
            />
            <p className="text-xs text-muted-foreground">
              Escalate to human if confidence below: {(config?.confidenceMinimum ?? 0.6) * 100}%
            </p>
          </div>
        </div>
        
        {/* Model Configuration */}
        <div className="space-y-4">
          <h4 className="text-sm font-medium">Model Configuration</h4>
          
          <ModelSelector
            label="Primary Model"
            value={config?.primaryModel}
            onChange={(m) => updateConfig({ primaryModel: m })}
          />
          
          <ModelMultiSelector
            label="Fallback Models"
            value={config?.fallbackModels ?? []}
            onChange={(m) => updateConfig({ fallbackModels: m })}
          />
          
          <ModelMultiSelector
            label="Consensus Models"
            value={config?.consensusModels ?? []}
            onChange={(m) => updateConfig({ consensusModels: m })}
            description="Models used for voting on critical stages"
          />
        </div>
      </CardContent>
    </Card>
  );
};
```

### RESExecutionMonitor

```typescript
interface RESExecutionMonitorProps {
  readonly executionId: string;
}

const RESExecutionMonitor: React.FC<RESExecutionMonitorProps> = ({
  executionId
}) => {
  const { data: logs } = useRESLogs(executionId);
  
  // Group by mechanism
  const stats = useMemo(() => {
    const grouped = groupBy(logs, 'mechanism');
    return {
      selfCorrections: grouped[RESMechanism.SELF_CORRECTION]?.length ?? 0,
      consensusVotes: grouped[RESMechanism.MULTI_MODEL_CONSENSUS]?.length ?? 0,
      checkpoints: grouped[RESMechanism.CHECKPOINT_ROLLBACK]?.length ?? 0,
      retries: grouped[RESMechanism.ADAPTIVE_RETRY]?.length ?? 0,
      escalations: grouped[RESMechanism.HUMAN_ESCALATION]?.length ?? 0,
      successRate: calculateSuccessRate(logs)
    };
  }, [logs]);
  
  return (
    <div className="space-y-4">
      {/* Summary Stats */}
      <div className="grid grid-cols-5 gap-2">
        <StatCard
          icon={<RefreshCcw />}
          label="Self-Corrections"
          value={stats.selfCorrections}
        />
        <StatCard
          icon={<Users />}
          label="Consensus Votes"
          value={stats.consensusVotes}
        />
        <StatCard
          icon={<Save />}
          label="Checkpoints"
          value={stats.checkpoints}
        />
        <StatCard
          icon={<RotateCcw />}
          label="Retries"
          value={stats.retries}
        />
        <StatCard
          icon={<UserCheck />}
          label="Escalations"
          value={stats.escalations}
          variant={stats.escalations > 0 ? 'warning' : 'default'}
        />
      </div>
      
      {/* Timeline */}
      <RESTimeline logs={logs} />
      
      {/* Success Rate */}
      <Card>
        <CardContent className="pt-4">
          <div className="flex items-center justify-between">
            <span className="text-sm font-medium">Overall Success Rate</span>
            <Badge variant={stats.successRate >= 0.98 ? 'success' : 'warning'}>
              {(stats.successRate * 100).toFixed(1)}%
            </Badge>
          </div>
          <Progress value={stats.successRate * 100} className="mt-2" />
        </CardContent>
      </Card>
    </div>
  );
};
```

---

## Success Metrics

| Metric | Target | Measurement |
|--------|--------|-------------|
| Stage Success Rate | ≥98% | After all RES mechanisms |
| Self-Correction Success | ≥70% | Errors fixed without retry |
| Consensus Agreement | ≥85% | First-round agreement |
| Escalation Rate | ≤2% | Of total stage executions |
| Rollback Success | 100% | Complete state restoration |
| Mean Time to Recovery | <30s | From error to successful retry |

---

## Related Specifications

- [Resilient Execution System](../06-ai-integration/12-resilient-execution-system.md)
- [Error Handlers](./15-error-handlers.md)
- [Telemetry Integration](./18-telemetry-integration.md)
- [Live Execution View](./16-live-execution-view.md)
- [Debug Inspector](./17-debug-inspector.md)
