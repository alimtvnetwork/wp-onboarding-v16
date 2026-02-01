# Component: Stage Node Components

**Parent:** [Automation Pipeline](./00-overview.md)  
**Version:** 1.0.0  
**Status:** Planned  
**Phase:** 4 - Node Canvas UI  

---

## Summary

Custom React Flow node components for representing pipeline stages on the canvas. Each stage type has a specialized visual representation with configuration panels, execution state indicators, and connection handles.

---

## User Stories

- As a user, I want to see what type each stage is at a glance
- As a user, I want to configure stages by clicking on them
- As a user, I want to see execution progress on each stage
- As a user, I want to expand/collapse stages to see their details
- As a user, I want to quickly add stages to blocks

---

## Node Types

### Base Node Structure

```typescript
interface StageNodeData {
  stage: Stage;
  block: ExecutionBlock;
  isSelected: boolean;
  isExecuting: boolean;
  executionState?: StageExecutionState;
  isCollapsed: boolean;
}

interface StageExecutionState {
  status: StageExecutionStatus;
  progress: number;
  currentStep?: string;
  startedAt?: string;
  completedAt?: string;
  output?: unknown;
  error?: StageError;
}
```

### Node Component Architecture

```
┌─────────────────────────────────────────────────────────┐
│                     StageNode                            │
├─────────────────────────────────────────────────────────┤
│  ┌─────────────────────────────────────────────────────┐│
│  │                   Header                             ││
│  │  [Icon] Stage Name                    [Menu] [⋮]    ││
│  └─────────────────────────────────────────────────────┘│
│  ┌─────────────────────────────────────────────────────┐│
│  │                   Body                               ││
│  │  Stage-specific content (config preview)             ││
│  │  • Prompt: Template name, model                      ││
│  │  • Search: Query preview                             ││
│  │  • Validation: Script name, language                 ││
│  └─────────────────────────────────────────────────────┘│
│  ┌─────────────────────────────────────────────────────┐│
│  │                   Footer                             ││
│  │  Status indicator | Duration | Output type           ││
│  └─────────────────────────────────────────────────────┘│
│                                                          │
│  ○ Input Handle (top)                                   │
│                                        Output Handle ○   │
└─────────────────────────────────────────────────────────┘
```

---

## Base Stage Node

```tsx
import { Handle, Position, NodeProps } from 'reactflow';
import { cn } from '@/lib/utils';

interface BaseStageNodeProps extends NodeProps<StageNodeData> {
  icon: LucideIcon;
  color: string;
  children: React.ReactNode;
}

export function BaseStageNode({
  id,
  data,
  selected,
  icon: Icon,
  color,
  children,
}: BaseStageNodeProps) {
  const { stage, executionState, isCollapsed } = data;
  
  const statusColors = {
    [StageExecutionStatus.PENDING]: 'border-muted',
    [StageExecutionStatus.RUNNING]: 'border-primary animate-pulse',
    [StageExecutionStatus.SUCCESS]: 'border-green-500',
    [StageExecutionStatus.FAILED]: 'border-destructive',
    [StageExecutionStatus.SKIPPED]: 'border-muted opacity-50',
  };
  
  const status = executionState?.status ?? StageExecutionStatus.PENDING;
  
  return (
    <div
      className={cn(
        \"bg-card rounded-lg border-2 shadow-md min-w-[200px] max-w-[300px]\",
        \"transition-all duration-200\",
        statusColors[status],
        selected && \"ring-2 ring-primary ring-offset-2\"
      )}
    >
      {/* Input Handle */}
      <Handle
        type=\"target\"
        position={Position.Top}
        className={cn(
          \"w-3 h-3 rounded-full border-2 border-background\",
          color
        )}
      />
      
      {/* Header */}
      <div className={cn(\"flex items-center gap-2 p-2 border-b\", color)}>
        <Icon className=\"h-4 w-4 text-white\" />
        <span className=\"flex-1 font-medium text-white text-sm truncate\">
          {stage.Name}
        </span>
        
        {/* Status indicator */}
        {executionState?.status === StageExecutionStatus.RUNNING && (
          <Loader2 className=\"h-4 w-4 text-white animate-spin\" />
        )}
        {executionState?.status === StageExecutionStatus.SUCCESS && (
          <CheckCircle className=\"h-4 w-4 text-white\" />
        )}
        {executionState?.status === StageExecutionStatus.FAILED && (
          <XCircle className=\"h-4 w-4 text-white\" />
        )}
        
        {/* Menu */}
        <StageNodeMenu stageId={stage.Id} />
      </div>
      
      {/* Body - Stage-specific content */}
      {!isCollapsed && (
        <div className=\"p-2 text-sm\">
          {children}
        </div>
      )}
      
      {/* Footer */}
      <div className=\"flex items-center justify-between p-2 border-t bg-muted/50 text-xs text-muted-foreground\">
        <span>{stage.OutputVariable || 'No output'}</span>
        {executionState?.durationMs && (
          <span>{formatDuration(executionState.durationMs)}</span>
        )}
      </div>
      
      {/* Output Handle */}
      <Handle
        type=\"source\"
        position={Position.Bottom}
        className={cn(
          \"w-3 h-3 rounded-full border-2 border-background\",
          color
        )}
      />
      
      {/* Progress bar for running state */}
      {executionState?.status === StageExecutionStatus.RUNNING && (
        <div className=\"absolute bottom-0 left-0 right-0 h-1 bg-muted overflow-hidden rounded-b-lg\">
          <div 
            className=\"h-full bg-primary transition-all duration-300\"
            style={{ width: `${executionState.progress}%` }}
          />
        </div>
      )}
    </div>
  );
}
```

---

## Stage-Specific Nodes

### Prompt Stage Node

```tsx
export function PromptStageNode(props: NodeProps<StageNodeData>) {
  const config = JSON.parse(props.data.stage.Config) as PromptStageConfig;
  const { data: template } = useQuery({
    queryKey: ['prompt-template', config.promptTemplateId],
    queryFn: () => fetchPromptTemplate(config.promptTemplateId),
    enabled: !!config.promptTemplateId,
  });
  
  return (
    <BaseStageNode {...props} icon={MessageSquare} color=\"bg-blue-500\">
      <div className=\"space-y-1\">
        {template ? (
          <div className=\"flex items-center gap-1\">
            <FileText className=\"h-3 w-3\" />
            <span className=\"truncate\">{template.FileName}</span>
          </div>
        ) : (
          <span className=\"text-muted-foreground italic\">No template selected</span>
        )}
        
        {config.model && (
          <div className=\"flex items-center gap-1 text-muted-foreground\">
            <Cpu className=\"h-3 w-3\" />
            <span>{config.model}</span>
          </div>
        )}
        
        {config.additionalContext && (
          <div className=\"text-muted-foreground truncate\">
            +{config.additionalContext.length} chars context
          </div>
        )}
      </div>
    </BaseStageNode>
  );
}
```

### Search Stage Node

```tsx
export function SearchStageNode(props: NodeProps<StageNodeData>) {
  const config = JSON.parse(props.data.stage.Config) as SearchStageConfig;
  
  return (
    <BaseStageNode {...props} icon={Search} color=\"bg-green-500\">
      <div className=\"space-y-1\">
        <div className=\"bg-muted rounded px-2 py-1 font-mono text-xs truncate\">
          {config.query || 'No query set'}
        </div>
        
        <div className=\"flex items-center gap-2 text-muted-foreground\">
          <span>Max: {config.maxResults}</span>
          {config.minConfidence && (
            <span>Min: {(config.minConfidence * 100).toFixed(0)}%</span>
          )}
        </div>
        
        {config.sources && config.sources.length > 0 && (
          <div className=\"flex flex-wrap gap-1\">
            {config.sources.slice(0, 2).map((source) => (
              <Badge key={source} variant=\"secondary\" className=\"text-xs\">
                {source}
              </Badge>
            ))}
            {config.sources.length > 2 && (
              <Badge variant=\"secondary\" className=\"text-xs\">
                +{config.sources.length - 2}
              </Badge>
            )}
          </div>
        )}
      </div>
    </BaseStageNode>
  );
}
```

### Code Generation Stage Node

```tsx
export function CodeGenStageNode(props: NodeProps<StageNodeData>) {
  const config = JSON.parse(props.data.stage.Config) as CodeGenStageConfig;
  
  const languageIcons: Record<CodeLanguage, LucideIcon> = {
    [CodeLanguage.GOLANG]: FileCode,
    [CodeLanguage.PYTHON]: FileCode,
    [CodeLanguage.TYPESCRIPT]: FileCode,
    [CodeLanguage.JAVASCRIPT]: FileCode,
    [CodeLanguage.HTML]: Code,
    [CodeLanguage.CSS]: Palette,
    [CodeLanguage.SQL]: Database,
  };
  
  const LangIcon = languageIcons[config.language] || FileCode;
  
  return (
    <BaseStageNode {...props} icon={Code} color=\"bg-purple-500\">
      <div className=\"space-y-1\">
        <div className=\"flex items-center gap-1\">
          <LangIcon className=\"h-3 w-3\" />
          <span>{config.language}</span>
        </div>
        
        {config.outputPath && (
          <div className=\"font-mono text-xs text-muted-foreground truncate\">
            → {config.outputPath}
          </div>
        )}
        
        {config.executeAfterGeneration && (
          <Badge variant=\"outline\" className=\"text-xs\">
            <Play className=\"h-2 w-2 mr-1\" />
            Auto-execute
          </Badge>
        )}
      </div>
    </BaseStageNode>
  );
}
```

### Validation Stage Node

```tsx
export function ValidationStageNode(props: NodeProps<StageNodeData>) {
  const config = JSON.parse(props.data.stage.Config) as ValidationStageConfig;
  const { data: script } = useQuery({
    queryKey: ['validation-script', config.scriptId],
    queryFn: () => fetchValidationScript(config.scriptId),
    enabled: !!config.scriptId,
  });
  
  const languageColors = {
    [ValidationLanguage.GOLANG]: 'text-cyan-500',
    [ValidationLanguage.PYTHON]: 'text-yellow-500',
    [ValidationLanguage.TYPESCRIPT]: 'text-blue-500',
  };
  
  return (
    <BaseStageNode {...props} icon={CheckCircle} color=\"bg-orange-500\">
      <div className=\"space-y-1\">
        {script ? (
          <>
            <div className=\"flex items-center gap-1\">
              <FileCode className={cn(\"h-3 w-3\", languageColors[script.Language])} />
              <span className=\"truncate\">{script.Name}</span>
            </div>
            <div className=\"text-xs text-muted-foreground\">
              {script.Language}
            </div>
          </>
        ) : (
          <span className=\"text-muted-foreground italic\">No script selected</span>
        )}
        
        <div className=\"flex items-center gap-1\">
          <span className=\"text-xs text-muted-foreground\">On fail:</span>
          <Badge variant=\"outline\" className=\"text-xs\">
            {config.onFailure}
          </Badge>
        </div>
      </div>
    </BaseStageNode>
  );
}
```

### HTTP Stage Node

```tsx
export function HttpStageNode(props: NodeProps<StageNodeData>) {
  const config = JSON.parse(props.data.stage.Config) as HttpStageConfig;
  
  const methodColors = {
    GET: 'text-green-500',
    POST: 'text-blue-500',
    PUT: 'text-yellow-500',
    PATCH: 'text-orange-500',
    DELETE: 'text-red-500',
  };
  
  return (
    <BaseStageNode {...props} icon={Globe} color=\"bg-cyan-500\">
      <div className=\"space-y-1\">
        <div className=\"flex items-center gap-1\">
          <span className={cn(\"font-bold text-xs\", methodColors[config.method])}>
            {config.method}
          </span>
        </div>
        
        <div className=\"font-mono text-xs truncate bg-muted rounded px-1 py-0.5\">
          {config.url}
        </div>
        
        {config.headers && Object.keys(config.headers).length > 0 && (
          <div className=\"text-xs text-muted-foreground\">
            {Object.keys(config.headers).length} headers
          </div>
        )}
      </div>
    </BaseStageNode>
  );
}
```

### Transform Stage Node

```tsx
export function TransformStageNode(props: NodeProps<StageNodeData>) {
  const config = JSON.parse(props.data.stage.Config) as TransformStageConfig;
  
  const transformIcons: Record<TransformType, LucideIcon> = {
    [TransformType.JSON_PARSE]: Braces,
    [TransformType.JSON_STRINGIFY]: Braces,
    [TransformType.REGEX_EXTRACT]: Regex,
    [TransformType.TEMPLATE]: FileText,
    [TransformType.JMESPATH]: Filter,
    [TransformType.MAP]: List,
    [TransformType.FILTER]: Filter,
    // ... other transforms
  };
  
  const TransformIcon = transformIcons[config.transformType] || Shuffle;
  
  return (
    <BaseStageNode {...props} icon={Shuffle} color=\"bg-yellow-500\">
      <div className=\"space-y-1\">
        <div className=\"flex items-center gap-1\">
          <TransformIcon className=\"h-3 w-3\" />
          <span>{formatTransformType(config.transformType)}</span>
        </div>
        
        {config.expression && (
          <div className=\"font-mono text-xs truncate bg-muted rounded px-1 py-0.5\">
            {config.expression}
          </div>
        )}
        
        <div className=\"text-xs text-muted-foreground\">
          {config.inputVariable} → output
        </div>
      </div>
    </BaseStageNode>
  );
}
```

### File Operation Stage Node

```tsx
export function FileOpStageNode(props: NodeProps<StageNodeData>) {
  const config = JSON.parse(props.data.stage.Config) as FileOpStageConfig;
  
  const operationIcons: Record<FileOperation, LucideIcon> = {
    [FileOperation.READ]: FileInput,
    [FileOperation.WRITE]: FileOutput,
    [FileOperation.APPEND]: FilePlus,
    [FileOperation.DELETE]: FileX,
    [FileOperation.COPY]: Copy,
    [FileOperation.MOVE]: Move,
    [FileOperation.EXISTS]: FileSearch,
    [FileOperation.LIST]: FolderOpen,
    [FileOperation.MKDIR]: FolderPlus,
  };
  
  const OpIcon = operationIcons[config.operation] || FileText;
  
  return (
    <BaseStageNode {...props} icon={FileText} color=\"bg-gray-500\">
      <div className=\"space-y-1\">
        <div className=\"flex items-center gap-1\">
          <OpIcon className=\"h-3 w-3\" />
          <span>{config.operation}</span>
        </div>
        
        {config.sourcePath && (
          <div className=\"font-mono text-xs truncate text-muted-foreground\">
            {config.sourcePath}
          </div>
        )}
        
        {config.destinationPath && (
          <div className=\"flex items-center gap-1 font-mono text-xs text-muted-foreground\">
            <ArrowRight className=\"h-3 w-3\" />
            <span className=\"truncate\">{config.destinationPath}</span>
          </div>
        )}
      </div>
    </BaseStageNode>
  );
}
```

---

## Block Node (Container)

Container node that groups stages.

```tsx
interface BlockNodeData {
  block: ExecutionBlock;
  stages: Stage[];
  isCollapsed: boolean;
  executionState?: BlockExecutionState;
}

export function BlockNode({ id, data, selected }: NodeProps<BlockNodeData>) {
  const { block, stages, isCollapsed, executionState } = data;
  const [isHovered, setIsHovered] = useState(false);
  
  return (
    <div
      className={cn(
        \"bg-card/50 rounded-xl border-2 border-dashed\",
        \"min-w-[250px] transition-all duration-200\",
        selected && \"border-primary\",
        executionState?.status === BlockExecutionStatus.RUNNING && \"border-primary animate-pulse\"
      )}
      onMouseEnter={() => setIsHovered(true)}
      onMouseLeave={() => setIsHovered(false)}
    >
      {/* Block Header */}
      <div className=\"flex items-center justify-between p-3 border-b\">
        <div className=\"flex items-center gap-2\">
          <Layers className=\"h-4 w-4 text-muted-foreground\" />
          <span className=\"font-medium\">{block.Name}</span>
          <Badge variant=\"secondary\" className=\"text-xs\">
            {stages.length} stages
          </Badge>
        </div>
        
        <div className=\"flex items-center gap-1\">
          <Button
            size=\"icon\"
            variant=\"ghost\"
            className=\"h-6 w-6\"
            onClick={() => toggleCollapse(id)}
          >
            {isCollapsed ? (
              <ChevronDown className=\"h-4 w-4\" />
            ) : (
              <ChevronUp className=\"h-4 w-4\" />
            )}
          </Button>
          
          <BlockNodeMenu blockId={block.Id} />
        </div>
      </div>
      
      {/* Stages container */}
      {!isCollapsed && (
        <div className=\"p-3 space-y-2\">
          {stages.length === 0 ? (
            <div className=\"flex flex-col items-center justify-center py-8 text-muted-foreground\">
              <Plus className=\"h-8 w-8 mb-2 opacity-50\" />
              <span className=\"text-sm\">Drop stages here</span>
            </div>
          ) : (
            stages.map((stage, index) => (
              <MiniStageCard
                key={stage.Id}
                stage={stage}
                index={index}
                executionState={executionState?.stageStates?.get(stage.Id)}
              />
            ))
          )}
          
          {/* Add stage button */}
          {isHovered && (
            <Button
              variant=\"outline\"
              size=\"sm\"
              className=\"w-full border-dashed\"
              onClick={() => openAddStageDialog(block.Id)}
            >
              <Plus className=\"h-4 w-4 mr-1\" />
              Add Stage
            </Button>
          )}
        </div>
      )}
      
      {/* Collapsed preview */}
      {isCollapsed && stages.length > 0 && (
        <div className=\"p-2 flex gap-1\">
          {stages.slice(0, 5).map((stage) => (
            <StageTypeIcon key={stage.Id} type={stage.StageType} size=\"sm\" />
          ))}
          {stages.length > 5 && (
            <span className=\"text-xs text-muted-foreground\">
              +{stages.length - 5}
            </span>
          )}
        </div>
      )}
      
      {/* Handles */}
      <Handle type=\"target\" position={Position.Top} />
      <Handle type=\"source\" position={Position.Bottom} />
    </div>
  );
}
```

---

## Mini Stage Card

Compact stage representation within blocks.

```tsx
interface MiniStageCardProps {
  stage: Stage;
  index: number;
  executionState?: StageExecutionState;
  onEdit?: () => void;
  onDelete?: () => void;
}

export function MiniStageCard({
  stage,
  index,
  executionState,
  onEdit,
  onDelete,
}: MiniStageCardProps) {
  const [isHovered, setIsHovered] = useState(false);
  
  const StageIcon = getStageIcon(stage.StageType);
  const stageColor = getStageColor(stage.StageType);
  
  return (
    <div
      className={cn(
        \"flex items-center gap-2 p-2 rounded-lg border bg-background\",
        \"transition-all duration-150\",
        executionState?.status === StageExecutionStatus.RUNNING && \"ring-2 ring-primary\",
        executionState?.status === StageExecutionStatus.SUCCESS && \"border-green-500\",
        executionState?.status === StageExecutionStatus.FAILED && \"border-destructive\"
      )}
      onMouseEnter={() => setIsHovered(true)}
      onMouseLeave={() => setIsHovered(false)}
    >
      {/* Drag handle */}
      <GripVertical className=\"h-4 w-4 text-muted-foreground cursor-grab\" />
      
      {/* Order number */}
      <span className=\"text-xs text-muted-foreground w-4\">{index + 1}</span>
      
      {/* Type icon */}
      <div className={cn(\"p-1 rounded\", stageColor)}>
        <StageIcon className=\"h-3 w-3 text-white\" />
      </div>
      
      {/* Name */}
      <span className=\"flex-1 text-sm truncate\">{stage.Name}</span>
      
      {/* Status */}
      {executionState && (
        <StageStatusIndicator status={executionState.status} size=\"sm\" />
      )}
      
      {/* Actions */}
      {isHovered && (
        <div className=\"flex items-center gap-1\">
          <Button size=\"icon\" variant=\"ghost\" className=\"h-6 w-6\" onClick={onEdit}>
            <Edit className=\"h-3 w-3\" />
          </Button>
          <Button size=\"icon\" variant=\"ghost\" className=\"h-6 w-6\" onClick={onDelete}>
            <Trash2 className=\"h-3 w-3\" />
          </Button>
        </div>
      )}
    </div>
  );
}
```

---

## Stage Configuration Panel

Slide-out panel for editing stage configuration.

```tsx
interface StageConfigPanelProps {
  stage: Stage;
  open: boolean;
  onClose: () => void;
  onSave: (updates: Partial<Stage>) => void;
}

export function StageConfigPanel({
  stage,
  open,
  onClose,
  onSave,
}: StageConfigPanelProps) {
  const [config, setConfig] = useState(() => JSON.parse(stage.Config));
  
  // Get the appropriate config form based on stage type
  const ConfigForm = useMemo(() => {
    switch (stage.StageType) {
      case StageType.PROMPT:
        return PromptConfigForm;
      case StageType.SEARCH:
        return SearchConfigForm;
      case StageType.CODE_GEN:
        return CodeGenConfigForm;
      case StageType.VALIDATION:
        return ValidationConfigForm;
      case StageType.HTTP:
        return HttpConfigForm;
      case StageType.TRANSFORM:
        return TransformConfigForm;
      case StageType.FILE_OP:
        return FileOpConfigForm;
      default:
        return GenericConfigForm;
    }
  }, [stage.StageType]);
  
  const handleSave = () => {
    onSave({
      Config: JSON.stringify(config),
    });
    onClose();
  };
  
  return (
    <Sheet open={open} onOpenChange={(o) => !o && onClose()}>
      <SheetContent className=\"w-[400px] sm:w-[540px]\">
        <SheetHeader>
          <SheetTitle className=\"flex items-center gap-2\">
            <StageTypeIcon type={stage.StageType} />
            Configure {stage.Name}
          </SheetTitle>
          <SheetDescription>
            {getStageTypeDescription(stage.StageType)}
          </SheetDescription>
        </SheetHeader>
        
        <div className=\"mt-6 space-y-6\">
          {/* Common settings */}
          <div className=\"space-y-4\">
            <div className=\"space-y-2\">
              <Label htmlFor=\"name\">Stage Name</Label>
              <Input
                id=\"name\"
                value={stage.Name}
                onChange={(e) => onSave({ Name: e.target.value })}
              />
            </div>
            
            <div className=\"space-y-2\">
              <Label htmlFor=\"output\">Output Variable</Label>
              <Input
                id=\"output\"
                value={stage.OutputVariable || ''}
                onChange={(e) => onSave({ OutputVariable: e.target.value })}
                placeholder=\"e.g., searchResults\"
              />
            </div>
          </div>
          
          <Separator />
          
          {/* Type-specific config */}
          <ConfigForm
            config={config}
            onChange={setConfig}
          />
          
          <Separator />
          
          {/* Advanced settings */}
          <Collapsible>
            <CollapsibleTrigger className=\"flex items-center gap-2 text-sm\">
              <Settings className=\"h-4 w-4\" />
              Advanced Settings
              <ChevronDown className=\"h-4 w-4\" />
            </CollapsibleTrigger>
            <CollapsibleContent className=\"pt-4 space-y-4\">
              <div className=\"space-y-2\">
                <Label>Timeout (seconds)</Label>
                <Input
                  type=\"number\"
                  value={stage.TimeoutSeconds}
                  onChange={(e) => onSave({ TimeoutSeconds: parseInt(e.target.value) })}
                />
              </div>
              
              <div className=\"flex items-center justify-between\">
                <Label>Enabled</Label>
                <Switch
                  checked={stage.IsEnabled}
                  onCheckedChange={(checked) => onSave({ IsEnabled: checked ? 1 : 0 })}
                />
              </div>
            </CollapsibleContent>
          </Collapsible>
        </div>
        
        <SheetFooter className=\"mt-6\">
          <Button variant=\"outline\" onClick={onClose}>
            Cancel
          </Button>
          <Button onClick={handleSave}>
            Save Changes
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  );
}
```

---

## Stage Type Icons and Colors

```typescript
const stageTypeConfig: Record<StageType, { icon: LucideIcon; color: string; label: string }> = {
  [StageType.PROMPT]: {
    icon: MessageSquare,
    color: 'bg-blue-500',
    label: 'Prompt',
  },
  [StageType.SEARCH]: {
    icon: Search,
    color: 'bg-green-500',
    label: 'Search',
  },
  [StageType.CODE_GEN]: {
    icon: Code,
    color: 'bg-purple-500',
    label: 'Code Generation',
  },
  [StageType.VALIDATION]: {
    icon: CheckCircle,
    color: 'bg-orange-500',
    label: 'Validation',
  },
  [StageType.HTTP]: {
    icon: Globe,
    color: 'bg-cyan-500',
    label: 'HTTP Request',
  },
  [StageType.TRANSFORM]: {
    icon: Shuffle,
    color: 'bg-yellow-500',
    label: 'Transform',
  },
  [StageType.FILE_OP]: {
    icon: FileText,
    color: 'bg-gray-500',
    label: 'File Operation',
  },
};

export function getStageIcon(type: StageType): LucideIcon {
  return stageTypeConfig[type]?.icon ?? Box;
}

export function getStageColor(type: StageType): string {
  return stageTypeConfig[type]?.color ?? 'bg-gray-500';
}

export function getStageLabel(type: StageType): string {
  return stageTypeConfig[type]?.label ?? type;
}
```

---

## Status Indicators

```tsx
interface StageStatusIndicatorProps {
  status: StageExecutionStatus;
  size?: 'sm' | 'md' | 'lg';
  showLabel?: boolean;
}

export function StageStatusIndicator({
  status,
  size = 'md',
  showLabel = false,
}: StageStatusIndicatorProps) {
  const config = {
    [StageExecutionStatus.PENDING]: {
      icon: Clock,
      color: 'text-muted-foreground',
      label: 'Pending',
    },
    [StageExecutionStatus.RUNNING]: {
      icon: Loader2,
      color: 'text-primary',
      label: 'Running',
      animate: true,
    },
    [StageExecutionStatus.SUCCESS]: {
      icon: CheckCircle,
      color: 'text-green-500',
      label: 'Success',
    },
    [StageExecutionStatus.FAILED]: {
      icon: XCircle,
      color: 'text-destructive',
      label: 'Failed',
    },
    [StageExecutionStatus.SKIPPED]: {
      icon: MinusCircle,
      color: 'text-muted-foreground',
      label: 'Skipped',
    },
    [StageExecutionStatus.RETRYING]: {
      icon: RefreshCw,
      color: 'text-yellow-500',
      label: 'Retrying',
      animate: true,
    },
  };
  
  const { icon: Icon, color, label, animate } = config[status];
  
  const sizeClasses = {
    sm: 'h-3 w-3',
    md: 'h-4 w-4',
    lg: 'h-5 w-5',
  };
  
  return (
    <div className=\"flex items-center gap-1\">
      <Icon className={cn(sizeClasses[size], color, animate && 'animate-spin')} />
      {showLabel && <span className={cn('text-xs', color)}>{label}</span>}
    </div>
  );
}
```

---

## Performance Targets

| Metric | Target |
|--------|--------|
| Node render | < 16ms |
| Config panel open | < 100ms |
| Status update | < 10ms |
| Drag start | < 16ms |
| Hover state change | < 8ms |

---

## Related Specs

- [React Flow Canvas](./10-react-flow-canvas.md)
- [Connection Wiring](./12-connection-wiring.md)
- [Stage Executor](./04-stage-executor.md)
