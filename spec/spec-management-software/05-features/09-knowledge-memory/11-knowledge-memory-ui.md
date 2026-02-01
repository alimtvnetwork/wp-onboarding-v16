# 31. Knowledge Memory UI

## 31.1 Overview

The Knowledge Memory UI enables users to manage AI knowledge sources from spec projects and URLs. It provides intuitive interfaces for adding sources, configuring crawl options, monitoring ingestion progress, and managing the knowledge base.

### 31.1.1 Key Features

| Feature | Description |
|---------|-------------|
| **Source Dashboard** | Overview of all knowledge sources with status indicators |
| **Spec Source Wizard** | Multi-step form for adding spec project knowledge |
| **URL Source Wizard** | Configurable URL crawler setup with pattern matching |
| **Progress Tracker** | Real-time job monitoring with detailed status |
| **Source Manager** | View, refresh, and delete knowledge sources |

### 31.1.2 Navigation Structure

```
/projects/{projectId}/knowledge
├── /                          # Knowledge Dashboard
├── /add/spec                  # Add Spec Source Wizard
├── /add/url                   # Add URL Source Wizard
├── /sources/{sourceId}        # Source Detail View
└── /jobs/{jobId}              # Job Progress View
```

---

## 31.2 Knowledge Dashboard

### 31.2.1 Layout

```
┌─────────────────────────────────────────────────────────────────────────┐
│ Knowledge Memory                                        [+ Add Source ▼]│
├─────────────────────────────────────────────────────────────────────────┤
│ ┌─────────────────────────────────────────────────────────────────────┐ │
│ │ 📊 Knowledge Overview                                               │ │
│ │ ┌───────────────┐ ┌───────────────┐ ┌───────────────┐              │ │
│ │ │ 3 Sources     │ │ 4,250 Chunks  │ │ 1.2M Tokens   │              │ │
│ │ │ Active        │ │ Indexed       │ │ Total         │              │ │
│ │ └───────────────┘ └───────────────┘ └───────────────┘              │ │
│ └─────────────────────────────────────────────────────────────────────┘ │
│                                                                         │
│ ┌─────────────────────────────────────────────────────────────────────┐ │
│ │ 🔍 Filter: [All Types ▼] [All Status ▼]              [Search...]   │ │
│ └─────────────────────────────────────────────────────────────────────┘ │
│                                                                         │
│ ┌─────────────────────────────────────────────────────────────────────┐ │
│ │ ┌─────────────────────────────────────────────────────────────────┐ │ │
│ │ │ 📁 WordPress Plugin Spec              ● Ready      [⋮]         │ │ │
│ │ │ Spec Project • 1,250 chunks • 450K tokens                       │ │ │
│ │ │ Last synced: 2 hours ago                                        │ │ │
│ │ └─────────────────────────────────────────────────────────────────┘ │ │
│ │ ┌─────────────────────────────────────────────────────────────────┐ │ │
│ │ │ 🌐 Lovable Documentation              ◐ Processing  [⋮]        │ │ │
│ │ │ URL • 320 pages crawled • 45% complete                          │ │ │
│ │ │ Currently: /features/authentication                             │ │ │
│ │ │ ████████████████░░░░░░░░░░░░░░░░░░░░░ 45%                       │ │ │
│ │ └─────────────────────────────────────────────────────────────────┘ │ │
│ │ ┌─────────────────────────────────────────────────────────────────┐ │ │
│ │ │ 🌐 React Documentation                ● Ready      [⋮]         │ │ │
│ │ │ URL • 892 pages • 380K tokens                                   │ │ │
│ │ │ Last crawled: 1 day ago                                         │ │ │
│ │ └─────────────────────────────────────────────────────────────────┘ │ │
│ └─────────────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────────────┘
```

### 31.2.2 Component Structure

```typescript
// src/features/knowledge/pages/KnowledgeDashboard.tsx
import { useState } from 'react';
import { useParams } from 'react-router-dom';
import { useKnowledgeSources } from '../hooks/useKnowledgeSources';
import { KnowledgeOverviewCards } from '../components/KnowledgeOverviewCards';
import { KnowledgeSourceList } from '../components/KnowledgeSourceList';
import { KnowledgeFilters } from '../components/KnowledgeFilters';
import { AddSourceDropdown } from '../components/AddSourceDropdown';
import { Button } from '@/components/ui/button';
import { Plus } from 'lucide-react';

export function KnowledgeDashboard() {
  const { projectId } = useParams<{ projectId: string }>();
  const [filters, setFilters] = useState<KnowledgeFilters>({
    type: 'all',
    status: 'all',
    search: '',
  });
  
  const { data: sources, isLoading} = useKnowledgeSources(projectId!, filters);
  
  return (
    <div className="container py-6 space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-semibold">Knowledge Memory</h1>
          <p className="text-muted-foreground">
            Manage AI knowledge sources for enhanced spec generation
          </p>
        </div>
        <AddSourceDropdown projectId={projectId!} />
      </div>
      
      <KnowledgeOverviewCards sources={sources} />
      
      <KnowledgeFilters filters={filters} onChange={setFilters} />
      
      <KnowledgeSourceList 
        sources={sources} 
        isLoading={isLoading}
        projectId={projectId!}
      />
    </div>
  );
}
```

### 31.2.3 Source Card Component

```typescript
// src/features/knowledge/components/KnowledgeSourceCard.tsx
import { formatDistanceToNow } from 'date-fns';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import { 
  DropdownMenu, 
  DropdownMenuContent, 
  DropdownMenuItem, 
  DropdownMenuTrigger 
} from '@/components/ui/dropdown-menu';
import { Button } from '@/components/ui/button';
import { MoreVertical, RefreshCw, Trash2, Eye, FolderOpen, Globe } from 'lucide-react';
import { KnowledgeSource } from '../types';

interface KnowledgeSourceCardProps {
  source: KnowledgeSource;
  onView: (id: string) => void;
  onRefresh: (id: string) => void;
  onDelete: (id: string) => void;
}

export function KnowledgeSourceCard({ 
  source, 
  onView, 
  onRefresh, 
  onDelete 
}: KnowledgeSourceCardProps) {
  const isProcessing = source.status === 'processing';
  const Icon = source.sourceType === 'spec' ? FolderOpen : Globe;
  
  return (
    <Card className="hover:border-primary/50 transition-colors">
      <CardContent className="p-4">
        <div className="flex items-start justify-between">
          <div className="flex items-start gap-3 flex-1">
            <div className="p-2 rounded-lg bg-muted">
              <Icon className="h-5 w-5 text-muted-foreground" />
            </div>
            
            <div className="flex-1 min-w-0">
              <div className="flex items-center gap-2">
                <h3 className="font-medium truncate">{source.name}</h3>
                <StatusBadge status={source.status} />
              </div>
              
              <p className="text-sm text-muted-foreground mt-1">
                {source.sourceType === 'spec' ? 'Spec Project' : 'URL'} 
                {' • '}
                {source.totalChunks.toLocaleString()} chunks
                {' • '}
                {formatTokens(source.totalTokens)}
              </p>
              
              {isProcessing && source.job && (
                <div className="mt-3 space-y-1">
                  <div className="flex items-center justify-between text-xs">
                    <span className="text-muted-foreground truncate max-w-[300px]">
                      {source.job.currentItem}
                    </span>
                    <span className="font-medium">{source.job.progress}%</span>
                  </div>
                  <Progress value={source.job.progress} className="h-1.5" />
                </div>
              )}
              
              {!isProcessing && (
                <p className="text-xs text-muted-foreground mt-2">
                  Last synced: {formatDistanceToNow(new Date(source.updatedAt), { addSuffix: true })}
                </p>
              )}
            </div>
          </div>
          
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="ghost" size="icon" className="h-8 w-8">
                <MoreVertical className="h-4 w-4" />
                <span className="sr-only">Source actions</span>
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuItem onClick={() => onView(source.id)}>
                <Eye className="mr-2 h-4 w-4" />
                View Details
              </DropdownMenuItem>
              <DropdownMenuItem 
                onClick={() => onRefresh(source.id)}
                disabled={isProcessing}
              >
                <RefreshCw className="mr-2 h-4 w-4" />
                Refresh
              </DropdownMenuItem>
              <DropdownMenuItem 
                onClick={() => onDelete(source.id)}
                className="text-destructive focus:text-destructive"
              >
                <Trash2 className="mr-2 h-4 w-4" />
                Delete
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </CardContent>
    </Card>
  );
}

function StatusBadge({ status }: { status: KnowledgeSource['status'] }) {
  const variants: Record<string, { variant: 'default' | 'secondary' | 'destructive' | 'outline'; label: string }> = {
    pending: { variant: 'secondary', label: 'Pending' },
    processing: { variant: 'default', label: 'Processing' },
    ready: { variant: 'outline', label: 'Ready' },
    error: { variant: 'destructive', label: 'Error' },
    removing: { variant: 'secondary', label: 'Removing' },
  };
  
  const config = variants[status] || variants.pending;
  
  return (
    <Badge variant={config.variant} className="text-xs">
      {config.label}
    </Badge>
  );
}

function formatTokens(tokens: number): string {
  if (tokens >= 1_000_000) return `${(tokens / 1_000_000).toFixed(1)}M tokens`;
  if (tokens >= 1_000) return `${(tokens / 1_000).toFixed(0)}K tokens`;
  return `${tokens} tokens`;
}
```

---

## 31.3 Add Source Dropdown

### 31.3.1 Component

```typescript
// src/features/knowledge/components/AddSourceDropdown.tsx
import { useNavigate } from 'react-router-dom';
import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Plus, FolderOpen, Globe } from 'lucide-react';

interface AddSourceDropdownProps {
  projectId: string;
}

export function AddSourceDropdown({ projectId }: AddSourceDropdownProps) {
  const navigate = useNavigate();
  
  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button>
          <Plus className="mr-2 h-4 w-4" />
          Add Source
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-56">
        <DropdownMenuItem 
          onClick={() => navigate(`/projects/${projectId}/knowledge/add/spec`)}
        >
          <FolderOpen className="mr-2 h-4 w-4" />
          <div>
            <div className="font-medium">Spec Project</div>
            <div className="text-xs text-muted-foreground">
              Learn from existing specifications
            </div>
          </div>
        </DropdownMenuItem>
        <DropdownMenuItem 
          onClick={() => navigate(`/projects/${projectId}/knowledge/add/url`)}
        >
          <Globe className="mr-2 h-4 w-4" />
          <div>
            <div className="font-medium">Website URL</div>
            <div className="text-xs text-muted-foreground">
              Crawl and index web documentation
            </div>
          </div>
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
```

---

## 31.4 Add Spec Source Wizard

### 31.4.1 Wizard Layout

```
┌─────────────────────────────────────────────────────────────────────────┐
│ ← Back to Knowledge                                                     │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│ Add Spec Knowledge Source                                               │
│ Learn from existing specification projects                              │
│                                                                         │
│ ┌───────────────────────────────────────────────────────────────────┐   │
│ │ Step 1          Step 2           Step 3                            │   │
│ │ ● Source ────── ○ Folders ────── ○ Review                         │   │
│ └───────────────────────────────────────────────────────────────────┘   │
│                                                                         │
│ ┌───────────────────────────────────────────────────────────────────┐   │
│ │                                                                    │   │
│ │ Source Name *                                                      │   │
│ │ ┌────────────────────────────────────────────────────────────────┐│   │
│ │ │ WordPress Plugin Spec                                          ││   │
│ │ └────────────────────────────────────────────────────────────────┘│   │
│ │                                                                    │   │
│ │ Description                                                        │   │
│ │ ┌────────────────────────────────────────────────────────────────┐│   │
│ │ │ Learning coding patterns and documentation style from the      ││   │
│ │ │ exam-manager WordPress plugin specification.                   ││   │
│ │ └────────────────────────────────────────────────────────────────┘│   │
│ │                                                                    │   │
│ │ ─────────────────────────────────────────────────────────────────  │   │
│ │                                                                    │   │
│ │ Source Type                                                        │   │
│ │ ┌─────────────────────────┐ ┌─────────────────────────┐           │   │
│ │ │ ○ Internal Project      │ │ ◉ External Path         │           │   │
│ │ │   Select from existing  │ │   Browse local folder   │           │   │
│ │ │   projects              │ │                         │           │   │
│ │ └─────────────────────────┘ └─────────────────────────┘           │   │
│ │                                                                    │   │
│ │ External Spec Path *                                               │   │
│ │ ┌────────────────────────────────────────────────────────┐[Browse]│   │
│ │ │ /Users/dev/projects/exam-manager/spec                  │        │   │
│ │ └────────────────────────────────────────────────────────┘        │   │
│ │                                                                    │   │
│ └───────────────────────────────────────────────────────────────────┘   │
│                                                                         │
│                                              [Cancel]  [Next: Folders →]│
└─────────────────────────────────────────────────────────────────────────┘
```

### 31.4.2 Step 2: Folder Selection

```
┌───────────────────────────────────────────────────────────────────┐
│                                                                    │
│ Select Folders to Include                                          │
│ Choose which folders to learn from. Unchecked folders will be      │
│ excluded from indexing.                                            │
│                                                                    │
│ File Extensions                                                    │
│ ┌────────────────────────────────────────────────────────────────┐│
│ │ .md, .json, .yaml                                              ││
│ └────────────────────────────────────────────────────────────────┘│
│                                                                    │
│ ─────────────────────────────────────────────────────────────────  │
│                                                                    │
│ ☑ Select All    ☐ Expand All                                      │
│                                                                    │
│ ┌────────────────────────────────────────────────────────────────┐│
│ │ ▼ ☑ 📁 01-admin-backend (42 files)                             ││
│ │     ☑ 📁 features (38 files)                                 ││
│ │     ☑ 📄 exam-questions-manager-full-spec.md                   ││
│ │                                                                 ││
│ │ ▶ ☑ 📁 02-frontend (24 files)                                  ││
│ │                                                                 ││
│ │ ▶ ☐ 📁 diagrams (6 files)                                      ││
│ │                                                                 ││
│ │ ▶ ☐ 📁 ideas (3 files)                                         ││
│ └────────────────────────────────────────────────────────────────┘│
│                                                                    │
│ Selected: 66 files • ~2.1 MB                                       │
│                                                                    │
└───────────────────────────────────────────────────────────────────┘
                                     [← Back]  [Next: Review →]
```

### 31.4.3 Step 3: Review & Confirm

```
┌───────────────────────────────────────────────────────────────────┐
│                                                                    │
│ Review Configuration                                               │
│                                                                    │
│ ┌────────────────────────────────────────────────────────────────┐│
│ │ Source Name         WordPress Plugin Spec                      ││
│ │ Description         Learning coding patterns and documentation ││
│ │                     style from the exam-manager plugin spec.   ││
│ │ Source Path         /Users/dev/projects/exam-manager/spec      ││
│ │ File Extensions     .md, .json, .yaml                          ││
│ │ Included Folders    01-admin-backend, 02-frontend              ││
│ │ Excluded Folders    diagrams, ideas                            ││
│ │ Total Files         66 files (~2.1 MB)                         ││
│ └────────────────────────────────────────────────────────────────┘│
│                                                                    │
│ ⓘ Indexing will run in the background. You can close this page    │
│   and check progress from the Knowledge Dashboard.                 │
│                                                                    │
└───────────────────────────────────────────────────────────────────┘
                                     [← Back]  [Start Indexing]
```

### 31.4.4 Wizard Component

```typescript
// src/features/knowledge/pages/AddSpecSourceWizard.tsx
import { useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useCreateSpecSource } from '../hooks/useCreateSpecSource';
import { WizardStepper } from '../components/WizardStepper';
import { SpecSourceStep } from '../components/wizard/SpecSourceStep';
import { SpecFoldersStep } from '../components/wizard/SpecFoldersStep';
import { SpecReviewStep } from '../components/wizard/SpecReviewStep';
import { Button } from '@/components/ui/button';
import { ArrowLeft } from 'lucide-react';
import { toast } from '@/hooks/use-toast';

const STEPS = [
  { id: 'source', label: 'Source' },
  { id: 'folders', label: 'Folders' },
  { id: 'review', label: 'Review' },
];

export function AddSpecSourceWizard() {
  const { projectId } = useParams<{ projectId: string }>();
  const navigate = useNavigate();
  const [currentStep, setCurrentStep] = useState(0);
  const [formData, setFormData] = useState<SpecSourceFormData>({
    name: '',
    description: '',
    sourceType: 'external',
    sourceProjectId: null,
    externalPath: '',
    fileExtensions: ['md', 'json', 'yaml'],
    includeFolders: [],
    excludeFolders: [],
  });
  
  const createSource = useCreateSpecSource();
  
  const handleNext = () => {
    if (currentStep < STEPS.length - 1) {
      setCurrentStep(currentStep + 1);
    }
  };
  
  const handleBack = () => {
    if (currentStep > 0) {
      setCurrentStep(currentStep - 1);
    }
  };
  
  const handleSubmit = async () => {
    try {
      await createSource.mutateAsync({
        projectId: projectId!,
        ...formData,
      });
      toast({
        title: 'Knowledge source created',
        description: 'Indexing has started in the background.',
      });
      navigate(`/projects/${projectId}/knowledge`);
    } catch (error) {
      toast({
        title: 'Failed to create source',
        description: error instanceof Error ? error.message : 'Unknown error',
        variant: 'destructive',
      });
    }
  };
  
  return (
    <div className="container max-w-3xl py-6">
      <Button
        variant="ghost"
        className="mb-4"
        onClick={() => navigate(`/projects/${projectId}/knowledge`)}
      >
        <ArrowLeft className="mr-2 h-4 w-4" />
        Back to Knowledge
      </Button>
      
      <div className="space-y-2 mb-8">
        <h1 className="text-2xl font-semibold">Add Spec Knowledge Source</h1>
        <p className="text-muted-foreground">
          Learn from existing specification projects
        </p>
      </div>
      
      <WizardStepper steps={STEPS} currentStep={currentStep} />
      
      <div className="mt-8">
        {currentStep === 0 && (
          <SpecSourceStep 
            data={formData} 
            onChange={setFormData}
            onNext={handleNext}
          />
        )}
        {currentStep === 1 && (
          <SpecFoldersStep 
            data={formData} 
            onChange={setFormData}
            onNext={handleNext}
            onBack={handleBack}
          />
        )}
        {currentStep === 2 && (
          <SpecReviewStep 
            data={formData}
            onBack={handleBack}
            onSubmit={handleSubmit}
            isSubmitting={createSource.isPending}
          />
        )}
      </div>
    </div>
  );
}
```

---

## 31.5 Add URL Source Wizard

### 31.5.1 URL Configuration Form

```
┌─────────────────────────────────────────────────────────────────────────┐
│ ← Back to Knowledge                                                     │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│ Add URL Knowledge Source                                                │
│ Crawl and index web documentation for AI learning                       │
│                                                                         │
│ ┌───────────────────────────────────────────────────────────────────┐   │
│ │ Step 1           Step 2            Step 3                          │   │
│ │ ● Basic ──────── ○ Crawl ──────── ○ Review                        │   │
│ └───────────────────────────────────────────────────────────────────┘   │
│                                                                         │
│ ┌───────────────────────────────────────────────────────────────────┐   │
│ │                                                                    │   │
│ │ Source Name *                                                      │   │
│ │ ┌────────────────────────────────────────────────────────────────┐│   │
│ │ │ React Documentation                                            ││   │
│ │ └────────────────────────────────────────────────────────────────┘│   │
│ │                                                                    │   │
│ │ Description                                                        │   │
│ │ ┌────────────────────────────────────────────────────────────────┐│   │
│ │ │ Official React documentation for component patterns            ││   │
│ │ └────────────────────────────────────────────────────────────────┘│   │
│ │                                                                    │   │
│ │ Base URL *                                                         │   │
│ │ ┌────────────────────────────────────────────────────────────────┐│   │
│ │ │ https://react.dev                                              ││   │
│ │ └────────────────────────────────────────────────────────────────┘│   │
│ │ ℹ️ Enter the starting URL for the crawler                         │   │
│ │                                                                    │   │
│ └───────────────────────────────────────────────────────────────────┘   │
│                                                                         │
│                                       [Cancel]  [Next: Crawl Settings →]│
└─────────────────────────────────────────────────────────────────────────┘
```

### 31.5.2 Step 2: Crawl Settings

```
┌───────────────────────────────────────────────────────────────────┐
│                                                                    │
│ Crawl Settings                                                     │
│                                                                    │
│ ☑ Crawl sub-pages                                                 │
│   Follow links to discover and index additional pages              │
│                                                                    │
│ ─────────────────────────────────────────────────────────────────  │
│                                                                    │
│ Depth & Limits                                                     │
│                                                                    │
│ Maximum Depth                    Maximum Pages                     │
│ ┌──────────────────────────┐    ┌──────────────────────────┐      │
│ │ 3                    [▼] │    │ 500                  [▼] │      │
│ └──────────────────────────┘    └──────────────────────────┘      │
│ How many links deep            Total pages to crawl                │
│                                                                    │
│ ☑ Stay within domain                                              │
│   Only crawl pages on the same domain (react.dev)                  │
│                                                                    │
│ ─────────────────────────────────────────────────────────────────  │
│                                                                    │
│ URL Patterns (Optional)                                            │
│                                                                    │
│ Include patterns (regex)                                           │
│ ┌────────────────────────────────────────────────────────────────┐│
│ │ /learn/.*                                                      ││
│ │ /reference/.*                                                  ││
│ │                                                         [+ Add]││
│ └────────────────────────────────────────────────────────────────┘│
│ Only crawl URLs matching these patterns                            │
│                                                                    │
│ Exclude patterns (regex)                                           │
│ ┌────────────────────────────────────────────────────────────────┐│
│ │ /blog/.*                                                       ││
│ │ /community/.*                                                  ││
│ │                                                         [+ Add]││
│ └────────────────────────────────────────────────────────────────┘│
│ Skip URLs matching these patterns                                  │
│                                                                    │
│ ─────────────────────────────────────────────────────────────────  │
│                                                                    │
│ Advanced Options                                                   │ ▼
│                                                                    │
│ Crawl Delay (ms)                ☑ Respect robots.txt              │
│ ┌──────────────────────────┐                                       │
│ │ 1000                     │                                       │
│ └──────────────────────────┘                                       │
│ Delay between requests                                             │
│                                                                    │
└───────────────────────────────────────────────────────────────────┘
                                        [← Back]  [Next: Review →]
```

### 31.5.3 URL Wizard Component

```typescript
// src/features/knowledge/components/wizard/UrlCrawlSettingsStep.tsx
import { Card, CardContent } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { PatternInput } from '../PatternInput';
import { ChevronDown, Plus, X } from 'lucide-react';

interface UrlCrawlSettingsStepProps {
  data: UrlSourceFormData;
  onChange: (data: UrlSourceFormData) => void;
  onNext: () => void;
  onBack: () => void;
}

export function UrlCrawlSettingsStep({ 
  data, 
  onChange, 
  onNext, 
  onBack 
}: UrlCrawlSettingsStepProps) {
  const [advancedOpen, setAdvancedOpen] = useState(false);
  
  return (
    <Card>
      <CardContent className="pt-6 space-y-6">
        {/* Crawl Sub-pages Toggle */}
        <div className="flex items-center justify-between">
          <div className="space-y-0.5">
            <Label htmlFor="crawl-subpages">Crawl sub-pages</Label>
            <p className="text-sm text-muted-foreground">
              Follow links to discover and index additional pages
            </p>
          </div>
          <Switch
            id="crawl-subpages"
            checked={data.crawlSubPages}
            onCheckedChange={(checked) => 
              onChange({ ...data, crawlSubPages: checked })
            }
          />
        </div>
        
        {data.crawlSubPages && (
          <>
            {/* Depth & Limits */}
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label>Maximum Depth</Label>
                <Select 
                  value={data.maxDepth.toString()}
                  onValueChange={(v) => onChange({ ...data, maxDepth: parseInt(v) })}
                >
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {[1, 2, 3, 4, 5].map(n => (
                      <SelectItem key={n} value={n.toString()}>
                        {n} {n === 1 ? 'level' : 'levels'}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <p className="text-xs text-muted-foreground">
                  How many links deep to follow
                </p>
              </div>
              
              <div className="space-y-2">
                <Label>Maximum Pages</Label>
                <Select 
                  value={data.maxPages.toString()}
                  onValueChange={(v) => onChange({ ...data, maxPages: parseInt(v) })}
                >
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {[50, 100, 250, 500, 1000].map(n => (
                      <SelectItem key={n} value={n.toString()}>
                        {n} pages
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <p className="text-xs text-muted-foreground">
                  Total pages to crawl
                </p>
              </div>
            </div>
            
            {/* Stay Within Domain */}
            <div className="flex items-center justify-between">
              <div className="space-y-0.5">
                <Label htmlFor="stay-domain">Stay within domain</Label>
                <p className="text-sm text-muted-foreground">
                  Only crawl pages on {new URL(data.baseUrl || 'https://example.com').hostname}
                </p>
              </div>
              <Switch
                id="stay-domain"
                checked={data.stayWithinDomain}
                onCheckedChange={(checked) => 
                  onChange({ ...data, stayWithinDomain: checked })
                }
              />
            </div>
            
            {/* URL Patterns */}
            <div className="space-y-4">
              <div className="space-y-2">
                <Label>Include patterns (regex)</Label>
                <PatternInput
                  patterns={data.includePatterns}
                  onChange={(patterns) => 
                    onChange({ ...data, includePatterns: patterns })
                  }
                  placeholder="/docs/.*"
                />
                <p className="text-xs text-muted-foreground">
                  Only crawl URLs matching these patterns
                </p>
              </div>
              
              <div className="space-y-2">
                <Label>Exclude patterns (regex)</Label>
                <PatternInput
                  patterns={data.excludePatterns}
                  onChange={(patterns) => 
                    onChange({ ...data, excludePatterns: patterns })
                  }
                  placeholder="/blog/.*"
                />
                <p className="text-xs text-muted-foreground">
                  Skip URLs matching these patterns
                </p>
              </div>
            </div>
            
            {/* Advanced Options */}
            <Collapsible open={advancedOpen} onOpenChange={setAdvancedOpen}>
              <CollapsibleTrigger asChild>
                <Button variant="ghost" className="w-full justify-between">
                  Advanced Options
                  <ChevronDown className={cn(
                    "h-4 w-4 transition-transform",
                    advancedOpen && "rotate-180"
                  )} />
                </Button>
              </CollapsibleTrigger>
              <CollapsibleContent className="pt-4 space-y-4">
                <div className="grid grid-cols-2 gap-4">
                  <div className="space-y-2">
                    <Label>Crawl Delay (ms)</Label>
                    <Input
                      type="number"
                      min={500}
                      max={5000}
                      step={100}
                      value={data.crawlDelayMs}
                      onChange={(e) => 
                        onChange({ ...data, crawlDelayMs: parseInt(e.target.value) })
                      }
                    />
                    <p className="text-xs text-muted-foreground">
                      Delay between requests
                    </p>
                  </div>
                  
                  <div className="space-y-2 pt-8">
                    <div className="flex items-center space-x-2">
                      <Switch
                        id="robots"
                        checked={data.respectRobotsTxt}
                        onCheckedChange={(checked) => 
                          onChange({ ...data, respectRobotsTxt: checked })
                        }
                      />
                      <Label htmlFor="robots">Respect robots.txt</Label>
                    </div>
                  </div>
                </div>
              </CollapsibleContent>
            </Collapsible>
          </>
        )}
        
        {/* Navigation */}
        <div className="flex justify-end gap-2 pt-4">
          <Button variant="outline" onClick={onBack}>
            Back
          </Button>
          <Button onClick={onNext}>
            Next: Review
          </Button>
        </div>
      </CardContent>
    </Card>
  );
}
```

### 31.5.4 Pattern Input Component

```typescript
// src/features/knowledge/components/PatternInput.tsx
import { useState } from 'react';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Plus, X } from 'lucide-react';

interface PatternInputProps {
  patterns: string[];
  onChange: (patterns: string[]) => void;
  placeholder?: string;
}

export function PatternInput({ patterns, onChange, placeholder }: PatternInputProps) {
  const [input, setInput] = useState('');
  const [error, setError] = useState<string | null>(null);
  
  const addPattern = () => {
    if (!input.trim()) return;
    
    // Validate regex
    try {
      new RegExp(input);
      setError(null);
    } catch {
      setError('Invalid regex pattern');
      return;
    }
    
    if (!patterns.includes(input)) {
      onChange([...patterns, input]);
    }
    setInput('');
  };
  
  const removePattern = (pattern: string) => {
    onChange(patterns.filter(p => p !== pattern));
  };
  
  return (
    <div className="space-y-2">
      <div className="flex gap-2">
        <Input
          value={input}
          onChange={(e) => setInput(e.target.value)}
          placeholder={placeholder}
          onKeyDown={(e) => {
            if (e.key === 'Enter') {
              e.preventDefault();
              addPattern();
            }
          }}
          className={error ? 'border-destructive' : ''}
        />
        <Button type="button" variant="outline" size="icon" onClick={addPattern}>
          <Plus className="h-4 w-4" />
        </Button>
      </div>
      
      {error && (
        <p className="text-xs text-destructive">{error}</p>
      )}
      
      {patterns.length > 0 && (
        <div className="flex flex-wrap gap-1">
          {patterns.map((pattern) => (
            <Badge key={pattern} variant="secondary" className="gap-1">
              <code className="text-xs">{pattern}</code>
              <button
                type="button"
                onClick={() => removePattern(pattern)}
                className="hover:text-destructive"
              >
                <X className="h-3 w-3" />
              </button>
            </Badge>
          ))}
        </div>
      )}
    </div>
  );
}
```

---

## 31.6 Progress Tracking

### 31.6.1 Job Progress Modal

```
┌─────────────────────────────────────────────────────────────────────────┐
│ Indexing Progress                                               [Close] │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│ ┌─────────────────────────────────────────────────────────────────────┐ │
│ │ 🌐 React Documentation                                              │ │
│ │                                                                      │ │
│ │ Status: Crawling pages...                                           │ │
│ │                                                                      │ │
│ │ ████████████████████████████░░░░░░░░░░░░░░░░░░░░░░░░░░░░ 65%        │ │
│ │                                                                      │ │
│ │ Progress Details                                                     │ │
│ │ ┌──────────────────────────────────────────────────────────────────┐│ │
│ │ │ Pages discovered     267                                         ││ │
│ │ │ Pages crawled        174 / 267                                   ││ │
│ │ │ Chunks created       1,840                                       ││ │
│ │ │ Tokens indexed       412,500                                     ││ │
│ │ │ Errors               3                                           ││ │
│ │ └──────────────────────────────────────────────────────────────────┘│ │
│ │                                                                      │ │
│ │ Currently processing:                                                │ │
│ │ https://react.dev/reference/react/hooks/useState                    │ │
│ │                                                                      │ │
│ │ ⓘ You can close this modal. Indexing will continue in background.   │ │
│ └─────────────────────────────────────────────────────────────────────┘ │
│                                                                         │
│ Recent Activity                                                         │
│ ┌─────────────────────────────────────────────────────────────────────┐ │
│ │ 10:45:23  ✓ Indexed /reference/react/hooks/useEffect               │ │
│ │ 10:45:22  ✓ Indexed /reference/react/hooks/useContext              │ │
│ │ 10:45:21  ⚠ Skipped /blog/2024 (excluded by pattern)               │ │
│ │ 10:45:20  ✓ Indexed /learn/state-management                        │ │
│ │ 10:45:19  ✓ Indexed /learn/thinking-in-react                       │ │
│ └─────────────────────────────────────────────────────────────────────┘ │
│                                                                         │
│                                                        [Cancel Indexing]│
└─────────────────────────────────────────────────────────────────────────┘
```

### 31.6.2 Progress Component

```typescript
// src/features/knowledge/components/JobProgressModal.tsx
import { useEffect } from 'react';
import { useKnowledgeJob } from '../hooks/useKnowledgeJob';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Progress } from '@/components/ui/progress';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { ScrollArea } from '@/components/ui/scroll-area';
import { AlertCircle, CheckCircle2, Clock, Loader2 } from 'lucide-react';
import { formatDistanceToNow } from 'date-fns';

interface JobProgressModalProps {
  jobId: string;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onCancel?: () => void;
}

export function JobProgressModal({ 
  jobId, 
  open, 
  onOpenChange,
  onCancel 
}: JobProgressModalProps) {
  const { data: job, isLoading } = useKnowledgeJob(jobId, {
    refetchInterval: (data) => {
      // Poll every 2s while running, stop when done
      if (data?.status === 'running') return 2000;
      return false;
    },
  });
  
  if (isLoading || !job) {
    return (
      <Dialog open={open} onOpenChange={onOpenChange}>
        <DialogContent>
          <div className="flex items-center justify-center py-8">
            <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
          </div>
        </DialogContent>
      </Dialog>
    );
  }
  
  const isRunning = job.status === 'running';
  const isCompleted = job.status === 'completed';
  const isFailed = job.status === 'failed';
  
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-lg">
        <DialogHeader>
          <DialogTitle>Indexing Progress</DialogTitle>
        </DialogHeader>
        
        <div className="space-y-6">
          {/* Status Badge */}
          <div className="flex items-center gap-2">
            <StatusIcon status={job.status} />
            <span className="font-medium">{job.sourceName}</span>
            <JobStatusBadge status={job.status} />
          </div>
          
          {/* Progress Bar */}
          <div className="space-y-2">
            <div className="flex justify-between text-sm">
              <span className="text-muted-foreground">
                {isRunning ? 'Processing...' : isCompleted ? 'Complete' : 'Status'}
              </span>
              <span className="font-medium">{job.progress}%</span>
            </div>
            <Progress value={job.progress} />
          </div>
          
          {/* Stats Grid */}
          <div className="grid grid-cols-2 gap-4 p-4 bg-muted/50 rounded-lg">
            <StatItem label="Items Processed" value={`${job.processedItems} / ${job.totalItems}`} />
            <StatItem label="Chunks Created" value={job.stats?.chunks?.toLocaleString() || '—'} />
            <StatItem label="Tokens Indexed" value={formatTokens(job.stats?.tokens || 0)} />
            <StatItem label="Errors" value={job.stats?.errors || 0} />
          </div>
          
          {/* Current Item */}
          {isRunning && job.currentItem && (
            <div className="space-y-1">
              <p className="text-sm text-muted-foreground">Currently processing:</p>
              <p className="text-sm font-mono truncate bg-muted px-2 py-1 rounded">
                {job.currentItem}
              </p>
            </div>
          )}
          
          {/* Error Message */}
          {isFailed && job.errorMessage && (
            <div className="p-3 bg-destructive/10 text-destructive rounded-lg text-sm">
              <p className="font-medium">Error:</p>
              <p>{job.errorMessage}</p>
            </div>
          )}
          
          {/* Activity Log */}
          {job.activityLog && job.activityLog.length > 0 && (
            <div className="space-y-2">
              <p className="text-sm font-medium">Recent Activity</p>
              <ScrollArea className="h-32 rounded border">
                <div className="p-2 space-y-1">
                  {job.activityLog.map((entry, i) => (
                    <div key={i} className="flex gap-2 text-xs font-mono">
                      <span className="text-muted-foreground">
                        {new Date(entry.timestamp).toLocaleTimeString()}
                      </span>
                      <span className={entry.type === 'error' ? 'text-destructive' : ''}>
                        {entry.type === 'success' ? '✓' : entry.type === 'skip' ? '⚠' : '✗'}
                      </span>
                      <span className="truncate">{entry.message}</span>
                    </div>
                  ))}
                </div>
              </ScrollArea>
            </div>
          )}
          
          {/* Info */}
          {isRunning && (
            <p className="text-xs text-muted-foreground">
              ℹ️ You can close this modal. Indexing will continue in the background.
            </p>
          )}
          
          {/* Actions */}
          <div className="flex justify-end gap-2">
            {isRunning && onCancel && (
              <Button variant="destructive" size="sm" onClick={onCancel}>
                Cancel Indexing
              </Button>
            )}
            <Button variant="outline" onClick={() => onOpenChange(false)}>
              Close
            </Button>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  );
}

function StatusIcon({ status }: { status: string }) {
  switch (status) {
    case 'running':
      return <Loader2 className="h-5 w-5 animate-spin text-primary" />;
    case 'completed':
      return <CheckCircle2 className="h-5 w-5 text-green-500" />;
    case 'failed':
      return <AlertCircle className="h-5 w-5 text-destructive" />;
    default:
      return <Clock className="h-5 w-5 text-muted-foreground" />;
  }
}

function StatItem({ label, value }: { label: string; value: string | number }) {
  return (
    <div>
      <p className="text-xs text-muted-foreground">{label}</p>
      <p className="font-medium">{value}</p>
    </div>
  );
}
```

---

## 31.7 Source Detail View

### 31.7.1 Layout

```
┌─────────────────────────────────────────────────────────────────────────┐
│ ← Back to Knowledge                                                     │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│ 📁 WordPress Plugin Spec                              ● Ready           │
│ Learning from exam-manager WordPress plugin specification               │
│                                                                         │
│ ┌───────────────┬───────────────┬───────────────┬───────────────┐       │
│ │ 1,250         │ 450K          │ 66            │ 2 hours ago   │       │
│ │ Chunks        │ Tokens        │ Files         │ Last Sync     │       │
│ └───────────────┴───────────────┴───────────────┴───────────────┘       │
│                                                                         │
│ [Refresh]  [Delete Source]                                              │
│                                                                         │
│ ┌─────────────────────────────────────────────────────────────────────┐ │
│ │ Tabs: [Configuration] [Indexed Files] [Search Preview]             │ │
│ ├─────────────────────────────────────────────────────────────────────┤ │
│ │                                                                      │ │
│ │ Configuration                                                        │ │
│ │                                                                      │ │
│ │ ┌──────────────────────────────────────────────────────────────────┐│ │
│ │ │ Source Path     /Users/dev/projects/exam-manager/spec            ││ │
│ │ │ File Extensions .md, .json, .yaml                                ││ │
│ │ │ Include Folders 01-admin-backend, 02-frontend                    ││ │
│ │ │ Exclude Folders diagrams, ideas                                  ││ │
│ │ │ Created         Jan 27, 2025 at 10:00 AM                         ││ │
│ │ └──────────────────────────────────────────────────────────────────┘│ │
│ │                                                                      │ │
│ │ Job History                                                          │ │
│ │ ┌──────────────────────────────────────────────────────────────────┐│ │
│ │ │ Jan 28 10:00  Refresh      ✓ Completed    1,250 chunks  2m 34s  ││ │
│ │ │ Jan 27 10:00  Initial      ✓ Completed    1,248 chunks  3m 12s  ││ │
│ │ └──────────────────────────────────────────────────────────────────┘│ │
│ └─────────────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────────────┘
```

### 31.7.2 Search Preview Tab

```
┌─────────────────────────────────────────────────────────────────────────┐
│ Search Preview                                                          │
│ Test semantic search against this knowledge source                      │
│                                                                         │
│ ┌─────────────────────────────────────────────────────────────────────┐ │
│ │ How do I implement error handling in WordPress plugins?     [Search]│ │
│ └─────────────────────────────────────────────────────────────────────┘ │
│                                                                         │
│ Results (5 matches)                                                     │
│                                                                         │
│ ┌─────────────────────────────────────────────────────────────────────┐ │
│ │ 📄 02-error-management.md                           Score: 0.92    │ │
│ │ Section: Error Handling Patterns                                    │ │
│ │ ────────────────────────────────────────────────────────────────── │ │
│ │ All exceptions must extend `PluginException` base class. Use       │ │
│ │ error codes in the 1xxx-9xxx range according to the error...       │ │
│ │                                                    [View Full →]    │ │
│ └─────────────────────────────────────────────────────────────────────┘ │
│ ┌─────────────────────────────────────────────────────────────────────┐ │
│ │ 📄 07-logging-system.md                             Score: 0.87    │ │
│ │ Section: Exception Logging                                          │ │
│ │ ────────────────────────────────────────────────────────────────── │ │
│ │ When exceptions occur, the logging system captures full context     │ │
│ │ including stack traces, user information, and request data...      │ │
│ │                                                    [View Full →]    │ │
│ └─────────────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 31.8 Delete Confirmation

### 31.8.1 Delete Dialog

```typescript
// src/features/knowledge/components/DeleteSourceDialog.tsx
import { useState } from 'react';
import { useDeleteKnowledgeSource } from '../hooks/useDeleteKnowledgeSource';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { AlertTriangle, Loader2 } from 'lucide-react';
import { toast } from '@/hooks/use-toast';

interface DeleteSourceDialogProps {
  source: { id: string; name: string; totalChunks: number };
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onDeleted?: () => void;
}

export function DeleteSourceDialog({
  source,
  open,
  onOpenChange,
  onDeleted,
}: DeleteSourceDialogProps) {
  const [confirmText, setConfirmText] = useState('');
  const deleteSource = useDeleteKnowledgeSource();
  
  const canDelete = confirmText === source.name;
  
  const handleDelete = async () => {
    try {
      await deleteSource.mutateAsync(source.id);
      toast({
        title: 'Knowledge source deleted',
        description: 'All associated data has been removed.',
      });
      onOpenChange(false);
      onDeleted?.();
    } catch (error) {
      toast({
        title: 'Failed to delete',
        description: error instanceof Error ? error.message : 'Unknown error',
        variant: 'destructive',
      });
    }
  };
  
  return (
    <AlertDialog open={open} onOpenChange={onOpenChange}>
      <AlertDialogContent>
        <AlertDialogHeader>
          <div className="flex items-center gap-2">
            <div className="p-2 rounded-full bg-destructive/10">
              <AlertTriangle className="h-5 w-5 text-destructive" />
            </div>
            <AlertDialogTitle>Delete Knowledge Source</AlertDialogTitle>
          </div>
          <AlertDialogDescription className="space-y-3">
            <p>
              This will permanently delete <strong>{source.name}</strong> and all 
              associated data:
            </p>
            <ul className="list-disc list-inside text-sm space-y-1">
              <li>{source.totalChunks.toLocaleString()} indexed chunks</li>
              <li>All vector embeddings</li>
              <li>Crawl history and metadata</li>
            </ul>
            <p className="font-medium">This action cannot be undone.</p>
          </AlertDialogDescription>
        </AlertDialogHeader>
        
        <div className="space-y-2 py-4">
          <Label htmlFor="confirm-delete">
            Type <strong>{source.name}</strong> to confirm:
          </Label>
          <Input
            id="confirm-delete"
            value={confirmText}
            onChange={(e) => setConfirmText(e.target.value)}
            placeholder={source.name}
          />
        </div>
        
        <AlertDialogFooter>
          <AlertDialogCancel disabled={deleteSource.isPending}>
            Cancel
          </AlertDialogCancel>
          <AlertDialogAction
            onClick={handleDelete}
            disabled={!canDelete || deleteSource.isPending}
            className="bg-destructive hover:bg-destructive/90"
          >
            {deleteSource.isPending ? (
              <>
                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                Deleting...
              </>
            ) : (
              'Delete Source'
            )}
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  );
}
```

---

## 31.9 React Query Hooks

### 31.9.1 Query Keys

```typescript
// src/features/knowledge/api/queryKeys.ts
export const knowledgeKeys = {
  all: ['knowledge'] as const,
  sources: (projectId: string) => 
    [...knowledgeKeys.all, 'sources', projectId] as const,
  source: (sourceId: string) => 
    [...knowledgeKeys.all, 'source', sourceId] as const,
  jobs: (projectId: string) => 
    [...knowledgeKeys.all, 'jobs', projectId] as const,
  job: (jobId: string) => 
    [...knowledgeKeys.all, 'job', jobId] as const,
  search: (sourceId: string, query: string) => 
    [...knowledgeKeys.all, 'search', sourceId, query] as const,
};
```

### 31.9.2 Hooks Implementation

```typescript
// src/features/knowledge/hooks/useKnowledgeSources.ts
import { useQuery } from '@tanstack/react-query';
import { knowledgeKeys } from '../api/queryKeys';
import { knowledgeApi } from '../api/knowledgeApi';

export function useKnowledgeSources(projectId: string, filters?: KnowledgeFilters) {
  return useQuery({
    queryKey: knowledgeKeys.sources(projectId),
    queryFn: () => knowledgeApi.getSources(projectId, filters),
    staleTime: 30_000, // 30 seconds
  });
}

// src/features/knowledge/hooks/useKnowledgeJob.ts
import { useQuery } from '@tanstack/react-query';
import { knowledgeKeys } from '../api/queryKeys';
import { knowledgeApi } from '../api/knowledgeApi';

interface UseKnowledgeJobOptions {
  refetchInterval?: number | false | ((data: KnowledgeJob | undefined) => number | false);
}

export function useKnowledgeJob(jobId: string, options?: UseKnowledgeJobOptions) {
  return useQuery({
    queryKey: knowledgeKeys.job(jobId),
    queryFn: () => knowledgeApi.getJob(jobId),
    refetchInterval: options?.refetchInterval,
  });
}

// src/features/knowledge/hooks/useCreateSpecSource.ts
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { knowledgeKeys } from '../api/queryKeys';
import { knowledgeApi } from '../api/knowledgeApi';

export function useCreateSpecSource() {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (data: CreateSpecSourceRequest) => 
      knowledgeApi.createSpecSource(data),
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({
        queryKey: knowledgeKeys.sources(variables.projectId),
      });
    },
  });
}

// src/features/knowledge/hooks/useDeleteKnowledgeSource.ts
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { knowledgeKeys } from '../api/queryKeys';
import { knowledgeApi } from '../api/knowledgeApi';

export function useDeleteKnowledgeSource() {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (sourceId: string) => knowledgeApi.deleteSource(sourceId),
    onSuccess: () => {
      // Invalidate all knowledge queries since we don't know the projectId here
      queryClient.invalidateQueries({
        queryKey: knowledgeKeys.all,
      });
    },
  });
}
```

---

## 31.10 Types

```typescript
// src/features/knowledge/types.ts
export interface KnowledgeSource {
  id: string;
  projectId: string;
  sourceType: 'spec' | 'url';
  name: string;
  description?: string;
  status: 'pending' | 'processing' | 'ready' | 'error' | 'removing';
  totalChunks: number;
  totalTokens: number;
  createdAt: string;
  updatedAt: string;
  
  // Type-specific fields
  spec?: SpecSourceDetails;
  url?: UrlSourceDetails;
  
  // Active job if processing
  job?: JobProgress;
}

export interface SpecSourceDetails {
  sourceProjectId?: string;
  externalPath?: string;
  includeFolders: string[];
  excludeFolders: string[];
  fileExtensions: string[];
  lastSyncAt?: string;
}

export interface UrlSourceDetails {
  baseUrl: string;
  crawlSubPages: boolean;
  maxDepth: number;
  maxPages: number;
  stayWithinDomain: boolean;
  includePatterns: string[];
  excludePatterns: string[];
  crawlDelayMs: number;
  respectRobotsTxt: boolean;
  crawledPages?: number;
  lastCrawlAt?: string;
}

export interface KnowledgeJob {
  id: string;
  projectId: string;
  knowledgeSourceId: string;
  sourceName: string;
  jobType: 'ingest_spec' | 'crawl_url' | 'remove';
  status: 'queued' | 'running' | 'completed' | 'failed' | 'cancelled';
  progress: number;
  totalItems: number;
  processedItems: number;
  currentItem?: string;
  errorMessage?: string;
  startedAt?: string;
  completedAt?: string;
  stats?: {
    chunks?: number;
    tokens?: number;
    errors?: number;
  };
  activityLog?: ActivityLogEntry[];
}

export interface ActivityLogEntry {
  timestamp: string;
  type: 'success' | 'skip' | 'error';
  message: string;
}

export interface KnowledgeFilters {
  type: 'all' | 'spec' | 'url';
  status: 'all' | 'ready' | 'processing' | 'error';
  search: string;
}

export interface SpecSourceFormData {
  name: string;
  description: string;
  sourceType: 'internal' | 'external';
  sourceProjectId: string | null;
  externalPath: string;
  fileExtensions: string[];
  includeFolders: string[];
  excludeFolders: string[];
}

export interface UrlSourceFormData {
  name: string;
  description: string;
  baseUrl: string;
  crawlSubPages: boolean;
  maxDepth: number;
  maxPages: number;
  stayWithinDomain: boolean;
  includePatterns: string[];
  excludePatterns: string[];
  crawlDelayMs: number;
  respectRobotsTxt: boolean;
}
```

---

## 31.11 Accessibility

### 31.11.1 WCAG 2.1 AA Compliance

| Requirement | Implementation |
|-------------|----------------|
| **Keyboard Navigation** | All interactive elements focusable via Tab |
| **Focus Indicators** | Visible focus rings on cards, buttons, inputs |
| **Screen Reader** | ARIA labels on status badges, progress bars |
| **Color Contrast** | Status colors meet 4.5:1 ratio |
| **Motion** | Progress animations respect `prefers-reduced-motion` |

### 31.11.2 ARIA Annotations

```typescript
// Progress bar with live region
<Progress 
  value={job.progress} 
  aria-label={`Indexing progress: ${job.progress}%`}
  aria-live="polite"
/>

// Status badge
<Badge 
  aria-label={`Status: ${status}`}
  role="status"
>
  {status}
</Badge>

// Source card
<Card 
  role="article"
  aria-labelledby={`source-title-${source.id}`}
>
  <h3 id={`source-title-${source.id}`}>{source.name}</h3>
</Card>
```

---

## 31.12 Responsive Design

### 31.12.1 Breakpoint Behavior

| Breakpoint | Layout Changes |
|------------|----------------|
| **Mobile (<640px)** | Single column, stacked cards, collapsible filters |
| **Tablet (640-1024px)** | Two-column stats grid, cards full width |
| **Desktop (>1024px)** | Full layout with side-by-side elements |

### 31.12.2 Mobile Adaptations

- Wizard steps become vertical
- Pattern inputs expand to full width
- Progress modal becomes full screen sheet
- Dropdown menus use bottom sheets

---

## 31.13 Cross-References

| Specification | Relationship |
|---------------|--------------|
| 31-knowledge-memory-system.md (Backend) | API contracts, data models |
| 18-state-management.md | React Query configuration |
| 19-component-library.md | Shared UI components |
| 15-error-loading-states.md | Error handling patterns |
| 24-mobile-responsiveness.md | Responsive breakpoints |

---

## 31.14 Summary

The Knowledge Memory UI provides a complete interface for managing AI knowledge sources with:

1. **Dashboard**: Overview cards, filtering, and source list with status indicators
2. **Spec Wizard**: Three-step form for adding spec projects with folder selection
3. **URL Wizard**: Configurable crawl settings with pattern matching
4. **Progress Tracking**: Real-time job monitoring with activity logs
5. **Source Management**: Detail views, refresh, and safe deletion with confirmation
6. **Accessibility**: WCAG 2.1 AA compliant with keyboard navigation
