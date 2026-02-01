# Import/Export System

**Version:** 1.0.0  
**Status:** Specified  
**Updated:** 2026-01-30  
**Parent:** [Automation Pipeline](./00-overview.md)

---

## Overview

Comprehensive import/export system for pipelines, templates, and automation artifacts. Supports multiple formats (JSON, YAML, ZIP bundles) with dependency resolution, conflict handling, and selective import capabilities.

---

## Export Formats

### Format Types

```typescript
enum ExportFormat {
  JSON = 'JSON',           // Single JSON file
  YAML = 'YAML',           // Single YAML file
  BUNDLE = 'BUNDLE'        // ZIP with assets
}

enum ExportScope {
  PIPELINE = 'PIPELINE',           // Single pipeline
  TEMPLATE = 'TEMPLATE',           // Single template
  WORKSPACE = 'WORKSPACE',         // All pipelines in project
  SELECTION = 'SELECTION'          // Selected items only
}

interface ExportOptions {
  readonly format: ExportFormat;
  readonly scope: ExportScope;
  readonly itemIds: readonly string[];
  readonly includeHistory?: boolean;           // Include execution history
  readonly includeValidationScripts?: boolean; // Include script files
  readonly includePromptLibrary?: boolean;     // Include referenced prompts
  readonly minify?: boolean;                   // Minify output
  readonly encryptSecrets?: boolean;           // Encrypt sensitive values
}
```

### Export Manifest

```typescript
interface ExportManifest {
  readonly version: string;                    // Export format version
  readonly exportedAt: string;                 // ISO timestamp
  readonly exportedBy: string;                 // User identifier
  readonly sourceProject: string;              // Project ID
  
  readonly contents: ExportContents;
  readonly dependencies: ExportDependencies;
  readonly checksums: Record<string, string>;  // File → SHA256
}

interface ExportContents {
  readonly pipelines: readonly PipelineExport[];
  readonly templates: readonly TemplateExport[];
  readonly prompts: readonly PromptExport[];
  readonly scripts: readonly ScriptExport[];
  readonly variables: readonly VariableExport[];
}

interface ExportDependencies {
  readonly requiredIntegrations: readonly string[];  // External services
  readonly requiredModels: readonly string[];        // AI models
  readonly externalUrls: readonly string[];          // Referenced URLs
}

interface PipelineExport {
  readonly id: string;
  readonly name: string;
  readonly description: string | null;
  readonly version: string;
  readonly blocks: readonly BlockExport[];
  readonly connections: readonly ConnectionExport[];
  readonly variables: readonly VariableExport[];
  readonly canvasLayout: CanvasLayout;
  readonly metadata: PipelineMetadata;
}

interface BlockExport {
  readonly id: string;
  readonly name: string;
  readonly executionMode: ExecutionMode;
  readonly stages: readonly StageExport[];
  readonly position: Position;
}

interface StageExport {
  readonly id: string;
  readonly name: string;
  readonly type: StageType;
  readonly config: StageConfig;
  readonly validationScriptRef?: string;  // Reference to script in bundle
  readonly promptTemplateRef?: string;    // Reference to prompt in bundle
}
```

---

## Bundle Structure

### ZIP Bundle Layout

```
pipeline-export.zip
├── manifest.json                    # Export manifest
├── pipelines/
│   ├── pipeline-001.json           # Pipeline definitions
│   └── pipeline-002.json
├── templates/
│   └── template-001.json           # Template definitions
├── prompts/
│   ├── prompt-001.md               # Prompt templates
│   └── prompt-002.md
├── scripts/
│   ├── validation-001.go           # Golang scripts
│   ├── validation-002.py           # Python scripts
│   └── validation-003.ts           # TypeScript scripts
├── assets/
│   └── ...                         # Additional assets
└── checksums.sha256                # Integrity verification
```

### Manifest Schema

```json
{
  "$schema": "https://spec-mgmt.local/schemas/export-manifest-v1.json",
  "version": "1.0.0",
  "exportedAt": "2026-01-30T10:30:00Z",
  "exportedBy": "user@example.com",
  "sourceProject": "proj_abc123",
  "contents": {
    "pipelines": [
      {
        "file": "pipelines/pipeline-001.json",
        "id": "pipe_xyz789",
        "name": "Content Generator"
      }
    ],
    "templates": [],
    "prompts": [
      {
        "file": "prompts/prompt-001.md",
        "id": "prompt_def456",
        "name": "Blog Outline Generator"
      }
    ],
    "scripts": [
      {
        "file": "scripts/validation-001.go",
        "id": "script_ghi012",
        "language": "golang"
      }
    ]
  },
  "dependencies": {
    "requiredIntegrations": ["google-search"],
    "requiredModels": ["claude-3-sonnet", "gpt-4o"],
    "externalUrls": []
  },
  "checksums": {
    "pipelines/pipeline-001.json": "sha256:abc123...",
    "prompts/prompt-001.md": "sha256:def456..."
  }
}
```

---

## Export Engine

### ExportEngine Interface

```typescript
interface ExportEngine {
  // Generate export
  export(options: ExportOptions): Promise<ExportResult>;
  
  // Validate exportability
  validateExport(options: ExportOptions): Promise<ExportValidation>;
  
  // Estimate export size
  estimateSize(options: ExportOptions): Promise<SizeEstimate>;
}

interface ExportResult {
  readonly success: boolean;
  readonly format: ExportFormat;
  readonly data: Blob | string;          // Blob for BUNDLE, string for JSON/YAML
  readonly filename: string;
  readonly manifest: ExportManifest;
  readonly warnings: readonly ExportWarning[];
}

interface ExportValidation {
  readonly canExport: boolean;
  readonly issues: readonly ExportIssue[];
  readonly recommendations: readonly string[];
}

interface ExportIssue {
  readonly severity: 'ERROR' | 'WARNING';
  readonly code: ExportIssueCode;
  readonly message: string;
  readonly itemId?: string;
  readonly itemType?: string;
}

enum ExportIssueCode {
  MISSING_SCRIPT = 'MISSING_SCRIPT',
  MISSING_PROMPT = 'MISSING_PROMPT',
  CIRCULAR_REFERENCE = 'CIRCULAR_REFERENCE',
  SENSITIVE_DATA = 'SENSITIVE_DATA',
  EXTERNAL_DEPENDENCY = 'EXTERNAL_DEPENDENCY',
  UNSUPPORTED_FEATURE = 'UNSUPPORTED_FEATURE'
}

interface ExportWarning {
  readonly code: string;
  readonly message: string;
  readonly suggestion?: string;
}

interface SizeEstimate {
  readonly totalBytes: number;
  readonly breakdown: Record<string, number>;  // Category → bytes
  readonly fileCount: number;
}
```

### Dependency Resolver

```typescript
interface DependencyResolver {
  // Resolve all dependencies for export items
  resolve(itemIds: readonly string[], scope: ExportScope): Promise<DependencyGraph>;
  
  // Check for circular dependencies
  detectCircular(graph: DependencyGraph): readonly CircularDependency[];
  
  // Get ordered export sequence
  getExportOrder(graph: DependencyGraph): readonly string[];
}

interface DependencyGraph {
  readonly nodes: ReadonlyMap<string, DependencyNode>;
  readonly edges: readonly DependencyEdge[];
}

interface DependencyNode {
  readonly id: string;
  readonly type: 'PIPELINE' | 'TEMPLATE' | 'PROMPT' | 'SCRIPT' | 'VARIABLE';
  readonly name: string;
  readonly required: boolean;
}

interface DependencyEdge {
  readonly from: string;
  readonly to: string;
  readonly type: 'USES' | 'REFERENCES' | 'EXTENDS';
}

interface CircularDependency {
  readonly path: readonly string[];
  readonly description: string;
}
```

---

## Import Engine

### ImportEngine Interface

```typescript
interface ImportEngine {
  // Parse import file/bundle
  parse(data: File | string, format: ExportFormat): Promise<ParsedImport>;
  
  // Analyze conflicts with existing data
  analyzeConflicts(parsed: ParsedImport): Promise<ConflictAnalysis>;
  
  // Execute import with resolution strategy
  import(
    parsed: ParsedImport,
    resolutions: readonly ConflictResolution[]
  ): Promise<ImportResult>;
  
  // Dry run import (no changes)
  dryRun(
    parsed: ParsedImport,
    resolutions: readonly ConflictResolution[]
  ): Promise<DryRunResult>;
}

interface ParsedImport {
  readonly manifest: ExportManifest;
  readonly pipelines: readonly PipelineExport[];
  readonly templates: readonly TemplateExport[];
  readonly prompts: readonly PromptExport[];
  readonly scripts: readonly ScriptExport[];
  readonly isValid: boolean;
  readonly validationErrors: readonly ValidationError[];
}

interface ConflictAnalysis {
  readonly hasConflicts: boolean;
  readonly conflicts: readonly ImportConflict[];
  readonly newItems: readonly NewItemSummary[];
  readonly unchangedItems: readonly string[];
}

interface ImportConflict {
  readonly id: string;
  readonly importedItem: ImportItem;
  readonly existingItem: ExistingItem;
  readonly conflictType: ConflictType;
  readonly suggestedResolution: ConflictResolutionStrategy;
  readonly canAutoResolve: boolean;
}

enum ConflictType {
  NAME_COLLISION = 'NAME_COLLISION',       // Same name, different ID
  ID_COLLISION = 'ID_COLLISION',           // Same ID, different content
  VERSION_CONFLICT = 'VERSION_CONFLICT',   // Older version exists
  DEPENDENCY_MISSING = 'DEPENDENCY_MISSING' // Required dependency not found
}

enum ConflictResolutionStrategy {
  SKIP = 'SKIP',                           // Don't import
  REPLACE = 'REPLACE',                     // Overwrite existing
  RENAME = 'RENAME',                       // Import with new name
  MERGE = 'MERGE',                         // Attempt to merge
  CREATE_NEW = 'CREATE_NEW'                // Create with new ID
}

interface ConflictResolution {
  readonly conflictId: string;
  readonly strategy: ConflictResolutionStrategy;
  readonly newName?: string;               // For RENAME strategy
  readonly mergeOptions?: MergeOptions;    // For MERGE strategy
}

interface ImportResult {
  readonly success: boolean;
  readonly imported: readonly ImportedItem[];
  readonly skipped: readonly SkippedItem[];
  readonly errors: readonly ImportError[];
  readonly rollbackAvailable: boolean;
}

interface ImportedItem {
  readonly originalId: string;
  readonly newId: string;
  readonly type: string;
  readonly name: string;
  readonly action: 'CREATED' | 'REPLACED' | 'MERGED';
}
```

---

## Database Schema

### ImportHistory Table

```sql
CREATE TABLE ImportHistory (
  Id              TEXT PRIMARY KEY,
  
  -- Import metadata
  Filename        TEXT NOT NULL,
  Format          TEXT NOT NULL,              -- 'JSON', 'YAML', 'BUNDLE'
  ManifestVersion TEXT NOT NULL,
  SourceProject   TEXT,
  
  -- Status
  Status          TEXT NOT NULL,              -- 'PENDING', 'IN_PROGRESS', 'COMPLETED', 'FAILED', 'ROLLED_BACK'
  
  -- Results
  ItemsImported   INTEGER NOT NULL DEFAULT 0,
  ItemsSkipped    INTEGER NOT NULL DEFAULT 0,
  ItemsFailed     INTEGER NOT NULL DEFAULT 0,
  
  -- Rollback data
  RollbackData    TEXT,                       -- JSON: data needed to undo
  CanRollback     INTEGER NOT NULL DEFAULT 1,
  
  -- Timestamps
  StartedAt       TEXT NOT NULL DEFAULT (datetime('now')),
  CompletedAt     TEXT,
  
  -- Error details
  ErrorMessage    TEXT,
  ErrorDetails    TEXT                        -- JSON: full error info
);

CREATE INDEX idx_import_status ON ImportHistory(Status);
CREATE INDEX idx_import_started ON ImportHistory(StartedAt);
```

### ImportItem Table

```sql
CREATE TABLE ImportItem (
  Id              TEXT PRIMARY KEY,
  ImportId        TEXT NOT NULL REFERENCES ImportHistory(Id) ON DELETE CASCADE,
  
  OriginalId      TEXT NOT NULL,
  NewId           TEXT,
  ItemType        TEXT NOT NULL,              -- 'PIPELINE', 'TEMPLATE', 'PROMPT', 'SCRIPT'
  ItemName        TEXT NOT NULL,
  
  Action          TEXT NOT NULL,              -- 'CREATED', 'REPLACED', 'MERGED', 'SKIPPED', 'FAILED'
  ConflictType    TEXT,                       -- If there was a conflict
  Resolution      TEXT,                       -- How it was resolved
  
  ErrorMessage    TEXT,
  
  CreatedAt       TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX idx_import_item_import ON ImportItem(ImportId);
CREATE INDEX idx_import_item_action ON ImportItem(Action);
```

---

## React Components

### ExportDialog

```typescript
interface ExportDialogProps {
  readonly open: boolean;
  readonly onOpenChange: (open: boolean) => void;
  readonly initialScope: ExportScope;
  readonly selectedIds?: readonly string[];
}

const ExportDialog: React.FC<ExportDialogProps> = ({
  open,
  onOpenChange,
  initialScope,
  selectedIds
}) => {
  const [options, setOptions] = useState<ExportOptions>({
    format: ExportFormat.BUNDLE,
    scope: initialScope,
    itemIds: selectedIds ?? [],
    includeValidationScripts: true,
    includePromptLibrary: true,
    includeHistory: false,
    minify: false
  });
  
  // Validation query
  const { data: validation } = useQuery({
    queryKey: ['export-validation', options],
    queryFn: () => validateExport(options),
    enabled: options.itemIds.length > 0
  });
  
  // Size estimate query
  const { data: sizeEstimate } = useQuery({
    queryKey: ['export-size', options],
    queryFn: () => estimateExportSize(options),
    enabled: validation?.canExport
  });
  
  // Export mutation
  const exportMutation = useMutation({
    mutationFn: () => performExport(options),
    onSuccess: (result) => {
      downloadExport(result);
      onOpenChange(false);
    }
  });
  
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-lg">
        <DialogHeader>
          <DialogTitle>Export Pipeline</DialogTitle>
        </DialogHeader>
        
        <div className="space-y-4 py-4">
          {/* Format selection */}
          <div className="space-y-2">
            <Label>Export Format</Label>
            <RadioGroup
              value={options.format}
              onValueChange={(v) => setOptions(prev => ({ ...prev, format: v as ExportFormat }))}
            >
              <div className="flex items-center space-x-2">
                <RadioGroupItem value="BUNDLE" id="bundle" />
                <Label htmlFor="bundle">ZIP Bundle (recommended)</Label>
              </div>
              <div className="flex items-center space-x-2">
                <RadioGroupItem value="JSON" id="json" />
                <Label htmlFor="json">JSON File</Label>
              </div>
              <div className="flex items-center space-x-2">
                <RadioGroupItem value="YAML" id="yaml" />
                <Label htmlFor="yaml">YAML File</Label>
              </div>
            </RadioGroup>
          </div>
          
          {/* Include options */}
          <div className="space-y-2">
            <Label>Include</Label>
            <div className="space-y-2">
              <div className="flex items-center space-x-2">
                <Checkbox
                  id="scripts"
                  checked={options.includeValidationScripts}
                  onCheckedChange={(c) => setOptions(prev => ({
                    ...prev,
                    includeValidationScripts: !!c
                  }))}
                />
                <Label htmlFor="scripts">Validation Scripts</Label>
              </div>
              <div className="flex items-center space-x-2">
                <Checkbox
                  id="prompts"
                  checked={options.includePromptLibrary}
                  onCheckedChange={(c) => setOptions(prev => ({
                    ...prev,
                    includePromptLibrary: !!c
                  }))}
                />
                <Label htmlFor="prompts">Prompt Templates</Label>
              </div>
              <div className="flex items-center space-x-2">
                <Checkbox
                  id="history"
                  checked={options.includeHistory}
                  onCheckedChange={(c) => setOptions(prev => ({
                    ...prev,
                    includeHistory: !!c
                  }))}
                />
                <Label htmlFor="history">Execution History</Label>
              </div>
            </div>
          </div>
          
          {/* Validation issues */}
          {validation && !validation.canExport && (
            <Alert variant="destructive">
              <AlertCircle className="h-4 w-4" />
              <AlertDescription>
                {validation.issues.filter(i => i.severity === 'ERROR').map(i => (
                  <div key={i.code}>{i.message}</div>
                ))}
              </AlertDescription>
            </Alert>
          )}
          
          {/* Size estimate */}
          {sizeEstimate && (
            <div className="text-sm text-muted-foreground">
              Estimated size: {formatBytes(sizeEstimate.totalBytes)} ({sizeEstimate.fileCount} files)
            </div>
          )}
        </div>
        
        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button
            onClick={() => exportMutation.mutate()}
            disabled={!validation?.canExport || exportMutation.isPending}
          >
            {exportMutation.isPending ? 'Exporting...' : 'Export'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
};
```

### ImportWizard

```typescript
interface ImportWizardProps {
  readonly open: boolean;
  readonly onOpenChange: (open: boolean) => void;
  readonly onComplete: (result: ImportResult) => void;
}

enum ImportStep {
  UPLOAD = 'UPLOAD',
  ANALYZE = 'ANALYZE',
  RESOLVE = 'RESOLVE',
  CONFIRM = 'CONFIRM',
  IMPORTING = 'IMPORTING',
  COMPLETE = 'COMPLETE'
}

const ImportWizard: React.FC<ImportWizardProps> = ({
  open,
  onOpenChange,
  onComplete
}) => {
  const [step, setStep] = useState<ImportStep>(ImportStep.UPLOAD);
  const [file, setFile] = useState<File | null>(null);
  const [parsed, setParsed] = useState<ParsedImport | null>(null);
  const [conflicts, setConflicts] = useState<ConflictAnalysis | null>(null);
  const [resolutions, setResolutions] = useState<ConflictResolution[]>([]);
  
  // Parse file
  const parseMutation = useMutation({
    mutationFn: (file: File) => parseImportFile(file),
    onSuccess: (result) => {
      setParsed(result);
      setStep(ImportStep.ANALYZE);
    }
  });
  
  // Analyze conflicts
  const analyzeMutation = useMutation({
    mutationFn: () => analyzeConflicts(parsed!),
    onSuccess: (result) => {
      setConflicts(result);
      if (result.hasConflicts) {
        // Initialize with suggested resolutions
        setResolutions(result.conflicts.map(c => ({
          conflictId: c.id,
          strategy: c.suggestedResolution
        })));
        setStep(ImportStep.RESOLVE);
      } else {
        setStep(ImportStep.CONFIRM);
      }
    }
  });
  
  // Execute import
  const importMutation = useMutation({
    mutationFn: () => executeImport(parsed!, resolutions),
    onSuccess: (result) => {
      setStep(ImportStep.COMPLETE);
      onComplete(result);
    }
  });
  
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-2xl">
        <DialogHeader>
          <DialogTitle>Import Pipeline</DialogTitle>
        </DialogHeader>
        
        {/* Step indicator */}
        <ImportStepIndicator currentStep={step} />
        
        {/* Step content */}
        <div className="py-4">
          {step === ImportStep.UPLOAD && (
            <UploadStep
              onFileSelect={(f) => {
                setFile(f);
                parseMutation.mutate(f);
              }}
              isParsing={parseMutation.isPending}
            />
          )}
          
          {step === ImportStep.ANALYZE && (
            <AnalyzeStep
              parsed={parsed!}
              onAnalyze={() => analyzeMutation.mutate()}
              isAnalyzing={analyzeMutation.isPending}
            />
          )}
          
          {step === ImportStep.RESOLVE && (
            <ResolveStep
              conflicts={conflicts!.conflicts}
              resolutions={resolutions}
              onUpdateResolution={(id, strategy) => {
                setResolutions(prev => prev.map(r =>
                  r.conflictId === id ? { ...r, strategy } : r
                ));
              }}
              onContinue={() => setStep(ImportStep.CONFIRM)}
            />
          )}
          
          {step === ImportStep.CONFIRM && (
            <ConfirmStep
              parsed={parsed!}
              conflicts={conflicts}
              resolutions={resolutions}
              onConfirm={() => {
                setStep(ImportStep.IMPORTING);
                importMutation.mutate();
              }}
            />
          )}
          
          {step === ImportStep.IMPORTING && (
            <ImportingStep progress={importMutation} />
          )}
          
          {step === ImportStep.COMPLETE && (
            <CompleteStep
              result={importMutation.data!}
              onClose={() => onOpenChange(false)}
            />
          )}
        </div>
      </DialogContent>
    </Dialog>
  );
};
```

### ConflictResolver

```typescript
interface ConflictResolverProps {
  readonly conflict: ImportConflict;
  readonly resolution: ConflictResolution;
  readonly onUpdate: (strategy: ConflictResolutionStrategy, options?: Partial<ConflictResolution>) => void;
}

const ConflictResolver: React.FC<ConflictResolverProps> = ({
  conflict,
  resolution,
  onUpdate
}) => {
  const [showDiff, setShowDiff] = useState(false);
  
  return (
    <Card className="border-warning">
      <CardHeader className="pb-2">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-2">
            <AlertTriangle className="h-4 w-4 text-warning" />
            <CardTitle className="text-sm">{conflict.importedItem.name}</CardTitle>
            <Badge variant="outline">{conflict.itemType}</Badge>
          </div>
          <Button
            variant="ghost"
            size="sm"
            onClick={() => setShowDiff(!showDiff)}
          >
            {showDiff ? 'Hide Diff' : 'Show Diff'}
          </Button>
        </div>
        <CardDescription>
          {getConflictDescription(conflict.conflictType)}
        </CardDescription>
      </CardHeader>
      
      <CardContent>
        {/* Diff view */}
        {showDiff && (
          <ConflictDiffView
            imported={conflict.importedItem}
            existing={conflict.existingItem}
          />
        )}
        
        {/* Resolution options */}
        <RadioGroup
          value={resolution.strategy}
          onValueChange={(v) => onUpdate(v as ConflictResolutionStrategy)}
          className="mt-4"
        >
          <div className="flex items-center space-x-2">
            <RadioGroupItem value="SKIP" id={`${conflict.id}-skip`} />
            <Label htmlFor={`${conflict.id}-skip`}>
              Skip (keep existing)
            </Label>
          </div>
          <div className="flex items-center space-x-2">
            <RadioGroupItem value="REPLACE" id={`${conflict.id}-replace`} />
            <Label htmlFor={`${conflict.id}-replace`}>
              Replace existing
            </Label>
          </div>
          <div className="flex items-center space-x-2">
            <RadioGroupItem value="RENAME" id={`${conflict.id}-rename`} />
            <Label htmlFor={`${conflict.id}-rename`}>
              Import with new name
            </Label>
          </div>
          {conflict.conflictType === ConflictType.VERSION_CONFLICT && (
            <div className="flex items-center space-x-2">
              <RadioGroupItem value="MERGE" id={`${conflict.id}-merge`} />
              <Label htmlFor={`${conflict.id}-merge`}>
                Attempt merge
              </Label>
            </div>
          )}
        </RadioGroup>
        
        {/* Rename input */}
        {resolution.strategy === 'RENAME' && (
          <Input
            className="mt-2"
            placeholder="New name..."
            value={resolution.newName ?? ''}
            onChange={(e) => onUpdate('RENAME', { newName: e.target.value })}
          />
        )}
      </CardContent>
    </Card>
  );
};
```

---

## API Endpoints

```typescript
// Export
POST   /api/export                       // Generate export
POST   /api/export/validate              // Validate export options
POST   /api/export/estimate              // Estimate export size

// Import
POST   /api/import/parse                 // Parse import file
POST   /api/import/analyze               // Analyze conflicts
POST   /api/import/execute               // Execute import
POST   /api/import/dry-run               // Dry run import
POST   /api/import/:id/rollback          // Rollback import

// History
GET    /api/import/history               // List import history
GET    /api/import/history/:id           // Get import details
```

---

## See Also

- [Pipeline Templates](./19-pipeline-templates.md) — Template system
- [Version Control](./21-version-control.md) — Versioning for pipelines
- [Prompt Import System](./02-prompt-import-system.md) — Prompt-specific import
