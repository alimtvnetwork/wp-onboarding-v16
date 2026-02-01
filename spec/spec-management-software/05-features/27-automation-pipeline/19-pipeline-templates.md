# Pipeline Templates

**Version:** 1.0.0  
**Status:** Specified  
**Updated:** 2026-01-30  
**Parent:** [Automation Pipeline](./00-overview.md)

---

## Overview

Reusable pipeline template system enabling users to save, share, and instantiate pre-configured pipeline patterns. Templates capture block structures, stage configurations, variable mappings, and connection wiring for rapid pipeline creation.

---

## Database Schema

### PipelineTemplate Table

```sql
CREATE TABLE PipelineTemplate (
  Id              TEXT PRIMARY KEY,
  Name            TEXT NOT NULL,
  Description     TEXT,
  Category        TEXT NOT NULL,           -- 'CONTENT', 'DATA', 'CODE', 'INTEGRATION', 'CUSTOM'
  Tags            TEXT,                     -- JSON array of tags
  IconName        TEXT,                     -- Lucide icon identifier
  ColorTheme      TEXT,                     -- Hex color for template card
  
  -- Template Definition
  TemplateData    TEXT NOT NULL,           -- JSON: full pipeline structure
  InputSchema     TEXT,                     -- JSON: required inputs
  OutputSchema    TEXT,                     -- JSON: expected outputs
  
  -- Metadata
  Author          TEXT,
  Version         TEXT NOT NULL DEFAULT '1.0.0',
  IsBuiltIn       INTEGER NOT NULL DEFAULT 0,
  IsPublic        INTEGER NOT NULL DEFAULT 0,
  UsageCount      INTEGER NOT NULL DEFAULT 0,
  
  -- Timestamps
  CreatedAt       TEXT NOT NULL DEFAULT (datetime('now')),
  UpdatedAt       TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX idx_template_category ON PipelineTemplate(Category);
CREATE INDEX idx_template_public ON PipelineTemplate(IsPublic);
```

### TemplateParameter Table

```sql
CREATE TABLE TemplateParameter (
  Id              TEXT PRIMARY KEY,
  TemplateId      TEXT NOT NULL REFERENCES PipelineTemplate(Id) ON DELETE CASCADE,
  
  Name            TEXT NOT NULL,           -- Parameter identifier
  DisplayName     TEXT NOT NULL,           -- Human-readable label
  Description     TEXT,
  ParameterType   TEXT NOT NULL,           -- 'STRING', 'NUMBER', 'BOOLEAN', 'SELECT', 'MODEL', 'FILE'
  DefaultValue    TEXT,
  Options         TEXT,                     -- JSON array for SELECT type
  IsRequired      INTEGER NOT NULL DEFAULT 1,
  ValidationRule  TEXT,                     -- Regex or JSON schema
  
  SortOrder       INTEGER NOT NULL DEFAULT 0,
  CreatedAt       TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX idx_param_template ON TemplateParameter(TemplateId);
```

### TemplateInstance Table

```sql
CREATE TABLE TemplateInstance (
  Id              TEXT PRIMARY KEY,
  TemplateId      TEXT NOT NULL REFERENCES PipelineTemplate(Id),
  PipelineId      TEXT NOT NULL REFERENCES Pipeline(Id) ON DELETE CASCADE,
  
  ParameterValues TEXT,                     -- JSON: user-provided values
  InstantiatedAt  TEXT NOT NULL DEFAULT (datetime('now')),
  
  UNIQUE(PipelineId)
);
```

---

## TypeScript Interfaces

### Template Types

```typescript
enum TemplateCategory {
  CONTENT = 'CONTENT',       // Content generation pipelines
  DATA = 'DATA',             // Data processing/ETL
  CODE = 'CODE',             // Code generation workflows
  INTEGRATION = 'INTEGRATION', // External service integrations
  CUSTOM = 'CUSTOM'          // User-created templates
}

enum ParameterType {
  STRING = 'STRING',
  NUMBER = 'NUMBER',
  BOOLEAN = 'BOOLEAN',
  SELECT = 'SELECT',
  MODEL = 'MODEL',           // AI model selector
  FILE = 'FILE'              // File path input
}

interface PipelineTemplate {
  readonly id: string;
  readonly name: string;
  readonly description: string | null;
  readonly category: TemplateCategory;
  readonly tags: readonly string[];
  readonly iconName: string | null;
  readonly colorTheme: string | null;
  
  readonly templateData: TemplateData;
  readonly inputSchema: JSONSchema | null;
  readonly outputSchema: JSONSchema | null;
  
  readonly author: string | null;
  readonly version: string;
  readonly isBuiltIn: boolean;
  readonly isPublic: boolean;
  readonly usageCount: number;
  
  readonly createdAt: Date;
  readonly updatedAt: Date;
}

interface TemplateData {
  readonly blocks: readonly TemplateBlock[];
  readonly connections: readonly TemplateConnection[];
  readonly variables: readonly TemplateVariable[];
  readonly canvasLayout: CanvasLayout;
}

interface TemplateBlock {
  readonly id: string;                    // Template-local ID
  readonly name: string;
  readonly description: string | null;
  readonly executionMode: ExecutionMode;
  readonly stages: readonly TemplateStage[];
  readonly position: { x: number; y: number };
}

interface TemplateStage {
  readonly id: string;
  readonly name: string;
  readonly type: StageType;
  readonly config: StageConfig;
  readonly parameterBindings: readonly ParameterBinding[];
}

interface ParameterBinding {
  readonly configPath: string;            // JSONPath to config field
  readonly parameterId: string;           // Reference to TemplateParameter
}

interface TemplateParameter {
  readonly id: string;
  readonly templateId: string;
  readonly name: string;
  readonly displayName: string;
  readonly description: string | null;
  readonly parameterType: ParameterType;
  readonly defaultValue: string | null;
  readonly options: readonly SelectOption[] | null;
  readonly isRequired: boolean;
  readonly validationRule: string | null;
  readonly sortOrder: number;
}

interface SelectOption {
  readonly value: string;
  readonly label: string;
  readonly description?: string;
}
```

---

## Template Instantiation

### InstantiationEngine

```typescript
interface InstantiationEngine {
  // Validate parameters before instantiation
  validateParameters(
    template: PipelineTemplate,
    values: Record<string, unknown>
  ): ValidationResult;
  
  // Create pipeline from template
  instantiate(
    template: PipelineTemplate,
    values: Record<string, unknown>,
    options: InstantiationOptions
  ): Promise<Pipeline>;
  
  // Preview what will be created
  preview(
    template: PipelineTemplate,
    values: Record<string, unknown>
  ): PipelinePreview;
}

interface InstantiationOptions {
  readonly pipelineName: string;
  readonly projectId: string;
  readonly insertPosition?: { x: number; y: number };
  readonly prefixIds?: string;            // Prefix for generated IDs
}

interface PipelinePreview {
  readonly blocks: readonly BlockPreview[];
  readonly connections: readonly ConnectionPreview[];
  readonly estimatedComplexity: number;
  readonly requiredIntegrations: readonly string[];
}

interface ValidationResult {
  readonly isValid: boolean;
  readonly errors: readonly ParameterError[];
  readonly warnings: readonly ParameterWarning[];
}

interface ParameterError {
  readonly parameterId: string;
  readonly message: string;
  readonly code: ParameterErrorCode;
}

enum ParameterErrorCode {
  REQUIRED = 'REQUIRED',
  INVALID_FORMAT = 'INVALID_FORMAT',
  OUT_OF_RANGE = 'OUT_OF_RANGE',
  INVALID_OPTION = 'INVALID_OPTION'
}
```

### ID Generation Strategy

```typescript
interface IdGenerator {
  // Generate unique IDs during instantiation
  generateBlockId(templateBlockId: string, prefix: string): string;
  generateStageId(templateStageId: string, prefix: string): string;
  generateConnectionId(templateConnId: string, prefix: string): string;
  
  // Map template IDs to instance IDs
  buildIdMapping(template: TemplateData, prefix: string): IdMapping;
}

interface IdMapping {
  readonly blocks: ReadonlyMap<string, string>;      // template ID → instance ID
  readonly stages: ReadonlyMap<string, string>;
  readonly connections: ReadonlyMap<string, string>;
  readonly variables: ReadonlyMap<string, string>;
}
```

---

## Template Creation

### TemplateBuilder

```typescript
interface TemplateBuilder {
  // Create template from existing pipeline
  fromPipeline(
    pipeline: Pipeline,
    options: TemplateCreationOptions
  ): PipelineTemplate;
  
  // Extract parameterizable fields
  detectParameters(pipeline: Pipeline): readonly DetectedParameter[];
  
  // Validate template structure
  validateTemplate(template: PipelineTemplate): ValidationResult;
}

interface TemplateCreationOptions {
  readonly name: string;
  readonly description?: string;
  readonly category: TemplateCategory;
  readonly tags?: readonly string[];
  readonly parameterize: readonly ParameterizationRule[];
}

interface ParameterizationRule {
  readonly stageId: string;
  readonly configPath: string;
  readonly parameterName: string;
  readonly parameterType: ParameterType;
  readonly displayName: string;
}

interface DetectedParameter {
  readonly stageId: string;
  readonly stageName: string;
  readonly configPath: string;
  readonly currentValue: unknown;
  readonly suggestedType: ParameterType;
  readonly suggestedName: string;
  readonly confidence: number;            // 0-1 likelihood this should be parameterized
}
```

---

## Built-in Templates

### Template Categories

```typescript
const BUILTIN_TEMPLATES: readonly BuiltInTemplate[] = [
  // Content Generation
  {
    category: TemplateCategory.CONTENT,
    templates: [
      {
        name: 'Blog Post Generator',
        description: 'Research topic → Generate outline → Write sections → Review',
        stages: ['SEARCH', 'PROMPT', 'PROMPT', 'VALIDATION'],
        parameters: ['topic', 'wordCount', 'tone', 'model']
      },
      {
        name: 'Documentation Writer',
        description: 'Analyze code → Generate docs → Format → Validate links',
        stages: ['FILE_OP', 'PROMPT', 'TRANSFORM', 'VALIDATION'],
        parameters: ['sourcePath', 'docStyle', 'includeExamples']
      }
    ]
  },
  
  // Data Processing
  {
    category: TemplateCategory.DATA,
    templates: [
      {
        name: 'CSV Transformer',
        description: 'Load CSV → Transform → Validate → Export',
        stages: ['FILE_OP', 'TRANSFORM', 'VALIDATION', 'FILE_OP'],
        parameters: ['inputPath', 'outputPath', 'transformScript']
      },
      {
        name: 'API Data Sync',
        description: 'Fetch API → Transform → Validate → Store',
        stages: ['HTTP', 'TRANSFORM', 'VALIDATION', 'FILE_OP'],
        parameters: ['apiUrl', 'authHeader', 'outputFormat']
      }
    ]
  },
  
  // Code Generation
  {
    category: TemplateCategory.CODE,
    templates: [
      {
        name: 'Component Generator',
        description: 'Parse spec → Generate code → Lint → Test',
        stages: ['FILE_OP', 'CODE_GEN', 'VALIDATION', 'VALIDATION'],
        parameters: ['specPath', 'language', 'framework', 'outputDir']
      },
      {
        name: 'Refactoring Pipeline',
        description: 'Analyze → Plan changes → Apply → Verify',
        stages: ['FILE_OP', 'PROMPT', 'CODE_GEN', 'VALIDATION'],
        parameters: ['targetPath', 'refactorType', 'preserveTests']
      }
    ]
  },
  
  // Integration
  {
    category: TemplateCategory.INTEGRATION,
    templates: [
      {
        name: 'Webhook Handler',
        description: 'Receive → Validate → Process → Respond',
        stages: ['HTTP', 'VALIDATION', 'TRANSFORM', 'HTTP'],
        parameters: ['webhookSecret', 'processingScript', 'responseUrl']
      }
    ]
  }
];
```

---

## React Components

### TemplateGallery

```typescript
interface TemplateGalleryProps {
  readonly onSelect: (template: PipelineTemplate) => void;
  readonly filterCategory?: TemplateCategory;
  readonly showBuiltInOnly?: boolean;
}

const TemplateGallery: React.FC<TemplateGalleryProps> = ({
  onSelect,
  filterCategory,
  showBuiltInOnly
}) => {
  // State
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedCategory, setSelectedCategory] = useState<TemplateCategory | null>(
    filterCategory ?? null
  );
  
  // Query templates
  const { data: templates } = useQuery({
    queryKey: ['templates', selectedCategory, showBuiltInOnly],
    queryFn: () => fetchTemplates({ category: selectedCategory, builtInOnly: showBuiltInOnly })
  });
  
  // Filter by search
  const filteredTemplates = useMemo(() => {
    if (!searchQuery) return templates;
    const query = searchQuery.toLowerCase();
    return templates?.filter(t => 
      t.name.toLowerCase().includes(query) ||
      t.tags.some(tag => tag.toLowerCase().includes(query))
    );
  }, [templates, searchQuery]);
  
  return (
    <div className="flex flex-col gap-4">
      {/* Search and filters */}
      <div className="flex gap-2">
        <Input
          placeholder="Search templates..."
          value={searchQuery}
          onChange={(e) => setSearchQuery(e.target.value)}
          className="flex-1"
        />
        <CategoryFilter
          value={selectedCategory}
          onChange={setSelectedCategory}
        />
      </div>
      
      {/* Template grid */}
      <div className="grid grid-cols-2 lg:grid-cols-3 gap-4">
        {filteredTemplates?.map(template => (
          <TemplateCard
            key={template.id}
            template={template}
            onClick={() => onSelect(template)}
          />
        ))}
      </div>
    </div>
  );
};
```

### TemplateCard

```typescript
interface TemplateCardProps {
  readonly template: PipelineTemplate;
  readonly onClick: () => void;
}

const TemplateCard: React.FC<TemplateCardProps> = ({ template, onClick }) => {
  const Icon = getIconComponent(template.iconName);
  
  return (
    <Card
      className="cursor-pointer hover:border-primary transition-colors"
      onClick={onClick}
    >
      <CardHeader className="pb-2">
        <div className="flex items-center gap-2">
          <div
            className="p-2 rounded-md"
            style={{ backgroundColor: template.colorTheme ?? 'hsl(var(--muted))' }}
          >
            <Icon className="h-5 w-5 text-primary-foreground" />
          </div>
          <div>
            <CardTitle className="text-sm">{template.name}</CardTitle>
            <Badge variant="outline" className="text-xs">
              {template.category}
            </Badge>
          </div>
        </div>
      </CardHeader>
      
      <CardContent>
        <p className="text-xs text-muted-foreground line-clamp-2">
          {template.description}
        </p>
        
        <div className="flex flex-wrap gap-1 mt-2">
          {template.tags.slice(0, 3).map(tag => (
            <Badge key={tag} variant="secondary" className="text-xs">
              {tag}
            </Badge>
          ))}
        </div>
        
        <div className="flex items-center gap-2 mt-3 text-xs text-muted-foreground">
          <span>{template.templateData.blocks.length} blocks</span>
          <span>•</span>
          <span>{template.usageCount} uses</span>
        </div>
      </CardContent>
    </Card>
  );
};
```

### TemplateInstantiationDialog

```typescript
interface TemplateInstantiationDialogProps {
  readonly template: PipelineTemplate;
  readonly open: boolean;
  readonly onOpenChange: (open: boolean) => void;
  readonly onInstantiate: (pipeline: Pipeline) => void;
}

const TemplateInstantiationDialog: React.FC<TemplateInstantiationDialogProps> = ({
  template,
  open,
  onOpenChange,
  onInstantiate
}) => {
  const [parameterValues, setParameterValues] = useState<Record<string, unknown>>({});
  const [pipelineName, setPipelineName] = useState(`${template.name} - ${formatDate(new Date())}`);
  
  // Fetch parameters
  const { data: parameters } = useQuery({
    queryKey: ['template-parameters', template.id],
    queryFn: () => fetchTemplateParameters(template.id)
  });
  
  // Initialize defaults
  useEffect(() => {
    if (parameters) {
      const defaults: Record<string, unknown> = {};
      parameters.forEach(p => {
        if (p.defaultValue !== null) {
          defaults[p.id] = parseDefaultValue(p.defaultValue, p.parameterType);
        }
      });
      setParameterValues(defaults);
    }
  }, [parameters]);
  
  // Validation
  const validation = useMemo(() => {
    if (!parameters) return { isValid: false, errors: [] };
    return validateParameters(parameters, parameterValues);
  }, [parameters, parameterValues]);
  
  // Instantiate
  const instantiateMutation = useMutation({
    mutationFn: () => instantiateTemplate(template, parameterValues, { pipelineName }),
    onSuccess: (pipeline) => {
      onInstantiate(pipeline);
      onOpenChange(false);
    }
  });
  
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-2xl max-h-[80vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>Create from Template: {template.name}</DialogTitle>
          <DialogDescription>{template.description}</DialogDescription>
        </DialogHeader>
        
        <div className="space-y-4 py-4">
          {/* Pipeline name */}
          <div className="space-y-2">
            <Label htmlFor="pipeline-name">Pipeline Name</Label>
            <Input
              id="pipeline-name"
              value={pipelineName}
              onChange={(e) => setPipelineName(e.target.value)}
            />
          </div>
          
          {/* Template parameters */}
          {parameters?.map(param => (
            <ParameterInput
              key={param.id}
              parameter={param}
              value={parameterValues[param.id]}
              onChange={(value) => setParameterValues(prev => ({
                ...prev,
                [param.id]: value
              }))}
              error={validation.errors.find(e => e.parameterId === param.id)}
            />
          ))}
          
          {/* Preview */}
          <TemplatePreview
            template={template}
            parameterValues={parameterValues}
          />
        </div>
        
        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button
            onClick={() => instantiateMutation.mutate()}
            disabled={!validation.isValid || instantiateMutation.isPending}
          >
            {instantiateMutation.isPending ? 'Creating...' : 'Create Pipeline'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
};
```

---

## API Endpoints

```typescript
// Template CRUD
GET    /api/templates                    // List templates (with filters)
GET    /api/templates/:id                // Get template details
POST   /api/templates                    // Create template
PUT    /api/templates/:id                // Update template
DELETE /api/templates/:id                // Delete template

// Template parameters
GET    /api/templates/:id/parameters     // Get template parameters

// Instantiation
POST   /api/templates/:id/instantiate    // Create pipeline from template
POST   /api/templates/:id/preview        // Preview instantiation

// From pipeline
POST   /api/pipelines/:id/to-template    // Create template from pipeline
POST   /api/pipelines/:id/detect-params  // Detect parameterizable fields
```

---

## See Also

- [React Flow Canvas](./10-react-flow-canvas.md) — Visual canvas for instantiated pipelines
- [Import Export](./20-import-export.md) — Template import/export
- [Database Schema](./01-database-schema.md) — Core schema
