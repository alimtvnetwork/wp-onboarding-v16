# Import/Export UI

> **Version:** 1.0.0  
> **Last Updated:** 2026-01-28  
> **Status:** Draft  

---

## 30.1 Overview

Frontend components for importing and exporting spec projects. Provides intuitive interfaces for ZIP/Markdown file uploads, import previews, export configuration, and progress tracking.

**Cross-References:**
- [Import/Export System](./01-import-export-system.md) - Backend API specification
- [Project Management Overview](./00-overview.md) - Integration point

---

## 30.2 Import Flow

### 30.2.1 Import Entry Points

Users can trigger import from multiple locations:

1. **Dashboard Header** - "Import Project" button
2. **Empty State** - "Import from File" action
3. **Command Palette** - `Ctrl+Shift+I` → "Import Project"
4. **Drag & Drop** - Drop ZIP/MD file on dashboard

### 30.2.2 Import Modal Layout

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  Import Project                                                        [×]  │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  ┌────────────────────────────────────────────────────────────────────────┐ │
│  │                                                                        │ │
│  │            ┌─────────────────────────────────────────┐                 │ │
│  │            │  📁                                     │                 │ │
│  │            │                                         │                 │ │
│  │            │  Drag & drop your file here             │                 │ │
│  │            │  or click to browse                     │                 │ │
│  │            │                                         │                 │ │
│  │            │  Supported: .zip, .md                   │                 │ │
│  │            └─────────────────────────────────────────┘                 │ │
│  │                                                                        │ │
│  └────────────────────────────────────────────────────────────────────────┘ │
│                                                                              │
│  ────────────────────────────────────────────────────────────────────────── │
│                                                                              │
│  Recent Imports                                                              │
│  ┌────────────────────────────────────────────────────────────────────────┐ │
│  │  📦 exam-manager-export-2026-01-27.zip    45 files    2 days ago       │ │
│  │  📄 api-design-prd.md                     1 file     5 days ago       │ │
│  └────────────────────────────────────────────────────────────────────────┘ │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 30.2.3 Import Preview Step

After file selection, show preview before executing:

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  Import Project                                              Step 2 of 3    │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  📦 exam-manager-export.zip                                                  │
│  ───────────────────────────────────────────────────────────────────────    │
│                                                                              │
│  ┌────────────────────────────┐  ┌──────────────────────────────────────┐  │
│  │  Import Summary            │  │  File Preview                        │  │
│  ├────────────────────────────┤  ├──────────────────────────────────────┤  │
│  │  📁 Project: Exam Manager  │  │  📁 spec/                            │  │
│  │  📂 Files: 45              │  │    ├─ 📄 00-overview.md              │  │
│  │  📂 Folders: 8             │  │    ├─ 📁 01-backend/                 │  │
│  │  💾 Size: 250 KB           │  │    │   ├─ 📄 01-database.md         │  │
│  │  ✓ Has metadata            │  │    │   ├─ 📄 02-api.md              │  │
│  │  ✓ Has overview            │  │    │   └─ 📄 03-auth.md             │  │
│  │                            │  │    ├─ 📁 02-frontend/                │  │
│  │  Category: WordPress       │  │    │   └─ 📄 01-components.md       │  │
│  └────────────────────────────┘  │    └─ 📁 ideas/                      │  │
│                                  │        └─ 📄 README.md               │  │
│  ⚠️ Warnings                      └──────────────────────────────────────┘  │
│  ┌────────────────────────────────────────────────────────────────────────┐ │
│  │  • Project with slug "exam-manager" already exists                     │ │
│  └────────────────────────────────────────────────────────────────────────┘ │
│                                                                              │
│                                              [← Back]  [Next: Options →]    │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 30.2.4 Import Options Step

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  Import Project                                              Step 3 of 3    │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  Project Settings                                                            │
│  ───────────────────────────────────────────────────────────────────────    │
│                                                                              │
│  Project Name *                                                              │
│  ┌────────────────────────────────────────────────────────────────────────┐ │
│  │  Exam Manager                                                          │ │
│  └────────────────────────────────────────────────────────────────────────┘ │
│                                                                              │
│  Category                                                                    │
│  ┌────────────────────────────────────────┐                                 │
│  │  WordPress Plugins                   ▼ │                                 │
│  └────────────────────────────────────────┘                                 │
│                                                                              │
│  Visibility                                                                  │
│  ┌────────────────────────────────────────────────────────────────────────┐ │
│  │  ○  User Only - Only you can see this project                          │ │
│  │  ○  Global - All users can view this project (read-only for others)    │ │
│  └────────────────────────────────────────────────────────────────────────┘ │
│                                                                              │
│  Conflict Handling                                                           │
│  ┌────────────────────────────────────────────────────────────────────────┐ │
│  │  ○  Skip - Don't import if project exists                              │ │
│  │  ●  Rename - Add suffix to project name                                │ │
│  │  ○  Overwrite - Replace existing project                               │ │
│  └────────────────────────────────────────────────────────────────────────┘ │
│                                                                              │
│  PRD Processing (Markdown files only)                                        │
│  ┌────────────────────────────────────────────────────────────────────────┐ │
│  │  ☑  Split PRD sections into separate spec files                        │ │
│  └────────────────────────────────────────────────────────────────────────┘ │
│                                                                              │
│                                              [← Back]  [Import Project]     │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 30.2.5 Import Component

```typescript
// components/import/ImportModal.tsx
import { useState, useCallback } from 'react';
import { Upload, FileArchive, FileText, AlertTriangle, Check } from 'lucide-react';
import { useDropzone } from 'react-dropzone';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Checkbox } from '@/components/ui/checkbox';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Progress } from '@/components/ui/progress';
import { useImport } from '@/hooks/useImport';
import { ImportPreview, ImportOptions, ConflictStrategy, Visibility } from '@/types/import';

type ImportStep = 'upload' | 'preview' | 'options' | 'importing' | 'complete';

interface ImportModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess: (projectId: string) => void;
  categories: string[];
}

export function ImportModal({ isOpen, onClose, onSuccess, categories }: ImportModalProps) {
  const [step, setStep] = useState<ImportStep>('upload');
  const [file, setFile] = useState<File | null>(null);
  const [preview, setPreview] = useState<ImportPreview | null>(null);
  const [options, setOptions] = useState<ImportOptions>({
    projectName: '',
    category: null,
    visibility: 'user',
    conflictStrategy: 'rename',
    generateMetadata: true,
    parsePrdSections: true,
  });
  
  const { previewImport, executeImport, isLoading, progress, error } = useImport();

  const onDrop = useCallback(async (acceptedFiles: File[]) => {
    const file = acceptedFiles[0];
    if (!file) return;
    
    setFile(file);
    
    // Get preview from backend
    const previewResult = await previewImport(file);
    if (previewResult) {
      setPreview(previewResult);
      setOptions(prev => ({
        ...prev,
        projectName: previewResult.projectName || file.name.replace(/\.(zip|md)$/i, ''),
        category: previewResult.detectedCategory || null,
      }));
      setStep('preview');
    }
  }, [previewImport]);

  const { getRootProps, getInputProps, isDragActive } = useDropzone({
    onDrop,
    accept: {
      'application/zip': ['.zip'],
      'text/markdown': ['.md'],
    },
    maxFiles: 1,
    maxSize: 50 * 1024 * 1024, // 50MB
  });

  const handleImport = async () => {
    if (!file) return;
    
    setStep('importing');
    const result = await executeImport(file, options);
    
    if (result) {
      setStep('complete');
      setTimeout(() => {
        onSuccess(result.projectId);
        onClose();
      }, 1500);
    }
  };

  const renderUploadStep = () => (
    <div className="space-y-6">
      <div
        {...getRootProps()}
        className={cn(
          'border-2 border-dashed rounded-lg p-12 text-center cursor-pointer transition-colors',
          isDragActive 
            ? 'border-primary bg-primary/5' 
            : 'border-border hover:border-primary/50'
        )}
      >
        <input {...getInputProps()} />
        <Upload className="h-12 w-12 mx-auto mb-4 text-muted-foreground" />
        <p className="text-lg font-medium">
          {isDragActive ? 'Drop your file here' : 'Drag & drop your file here'}
        </p>
        <p className="text-sm text-muted-foreground mt-1">
          or click to browse
        </p>
        <p className="text-xs text-muted-foreground mt-4">
          Supported: .zip, .md (max 50MB)
        </p>
      </div>
    </div>
  );

  const renderPreviewStep = () => (
    <div className="space-y-6">
      <div className="flex items-center gap-2 text-lg font-medium">
        {preview?.detectedType === 'zip' ? (
          <FileArchive className="h-5 w-5" />
        ) : (
          <FileText className="h-5 w-5" />
        )}
        {file?.name}
      </div>

      <div className="grid grid-cols-2 gap-4">
        {/* Summary */}
        <div className="border rounded-lg p-4 space-y-2">
          <h4 className="font-medium">Import Summary</h4>
          <div className="text-sm space-y-1">
            <p>📁 Project: {preview?.projectName || 'Unknown'}</p>
            <p>📂 Files: {preview?.fileCount}</p>
            <p>📂 Folders: {preview?.folderCount}</p>
            <p>💾 Size: {formatBytes(preview?.totalSizeBytes || 0)}</p>
            <p>{preview?.hasProjectJson ? '✓' : '✗'} Has metadata</p>
            <p>{preview?.hasOverview ? '✓' : '✗'} Has overview</p>
            {preview?.detectedCategory && (
              <p>Category: {preview.detectedCategory}</p>
            )}
          </div>
        </div>

        {/* File Tree */}
        <div className="border rounded-lg p-4">
          <h4 className="font-medium mb-2">File Preview</h4>
          <ScrollArea className="h-48">
            <FileTreePreview nodes={preview?.fileTree || []} />
          </ScrollArea>
        </div>
      </div>

      {/* Warnings */}
      {preview?.warnings && preview.warnings.length > 0 && (
        <Alert variant="warning">
          <AlertTriangle className="h-4 w-4" />
          <AlertDescription>
            <ul className="list-disc pl-4">
              {preview.warnings.map((warning, i) => (
                <li key={i}>{warning}</li>
              ))}
            </ul>
          </AlertDescription>
        </Alert>
      )}

      <div className="flex justify-end gap-2">
        <Button variant="outline" onClick={() => setStep('upload')}>
          ← Back
        </Button>
        <Button onClick={() => setStep('options')}>
          Next: Options →
        </Button>
      </div>
    </div>
  );

  const renderOptionsStep = () => (
    <div className="space-y-6">
      {/* Project Name */}
      <div className="space-y-2">
        <Label htmlFor="projectName">Project Name *</Label>
        <Input
          id="projectName"
          value={options.projectName}
          onChange={(e) => setOptions(prev => ({ ...prev, projectName: e.target.value }))}
          placeholder="Enter project name"
        />
      </div>

      {/* Category */}
      <div className="space-y-2">
        <Label>Category</Label>
        <Select
          value={options.category || 'none'}
          onValueChange={(v) => setOptions(prev => ({ 
            ...prev, 
            category: v === 'none' ? null : v 
          }))}
        >
          <SelectTrigger>
            <SelectValue placeholder="Select category" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="none">No Category</SelectItem>
            {categories.map((cat) => (
              <SelectItem key={cat} value={cat}>{cat}</SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>

      {/* Visibility */}
      <div className="space-y-2">
        <Label>Visibility</Label>
        <RadioGroup
          value={options.visibility}
          onValueChange={(v) => setOptions(prev => ({ ...prev, visibility: v as Visibility }))}
        >
          <div className="flex items-start gap-2">
            <RadioGroupItem value="user" id="vis-user" />
            <Label htmlFor="vis-user" className="font-normal">
              <span className="font-medium">User Only</span>
              <span className="block text-sm text-muted-foreground">
                Only you can see this project
              </span>
            </Label>
          </div>
          <div className="flex items-start gap-2">
            <RadioGroupItem value="global" id="vis-global" />
            <Label htmlFor="vis-global" className="font-normal">
              <span className="font-medium">Global</span>
              <span className="block text-sm text-muted-foreground">
                All users can view this project (read-only for others)
              </span>
            </Label>
          </div>
        </RadioGroup>
      </div>

      {/* Conflict Handling */}
      <div className="space-y-2">
        <Label>Conflict Handling</Label>
        <RadioGroup
          value={options.conflictStrategy}
          onValueChange={(v) => setOptions(prev => ({ 
            ...prev, 
            conflictStrategy: v as ConflictStrategy 
          }))}
        >
          <div className="flex items-center gap-2">
            <RadioGroupItem value="skip" id="conflict-skip" />
            <Label htmlFor="conflict-skip" className="font-normal">
              Skip - Don't import if project exists
            </Label>
          </div>
          <div className="flex items-center gap-2">
            <RadioGroupItem value="rename" id="conflict-rename" />
            <Label htmlFor="conflict-rename" className="font-normal">
              Rename - Add suffix to project name
            </Label>
          </div>
          <div className="flex items-center gap-2">
            <RadioGroupItem value="overwrite" id="conflict-overwrite" />
            <Label htmlFor="conflict-overwrite" className="font-normal">
              Overwrite - Replace existing project
            </Label>
          </div>
        </RadioGroup>
      </div>

      {/* PRD Processing */}
      {preview?.detectedType === 'markdown' || preview?.detectedType === 'prd' && (
        <div className="flex items-center gap-2">
          <Checkbox
            id="parsePrd"
            checked={options.parsePrdSections}
            onCheckedChange={(checked) => setOptions(prev => ({ 
              ...prev, 
              parsePrdSections: !!checked 
            }))}
          />
          <Label htmlFor="parsePrd" className="font-normal">
            Split PRD sections into separate spec files
          </Label>
        </div>
      )}

      <div className="flex justify-end gap-2">
        <Button variant="outline" onClick={() => setStep('preview')}>
          ← Back
        </Button>
        <Button onClick={handleImport} disabled={!options.projectName}>
          Import Project
        </Button>
      </div>
    </div>
  );

  const renderImportingStep = () => (
    <div className="space-y-6 py-8">
      <div className="text-center">
        <div className="animate-spin h-12 w-12 border-4 border-primary border-t-transparent rounded-full mx-auto mb-4" />
        <p className="text-lg font-medium">Importing project...</p>
        <p className="text-sm text-muted-foreground">{file?.name}</p>
      </div>
      <Progress value={progress} className="w-full" />
    </div>
  );

  const renderCompleteStep = () => (
    <div className="space-y-6 py-8 text-center">
      <div className="h-12 w-12 rounded-full bg-green-100 dark:bg-green-900/20 flex items-center justify-center mx-auto">
        <Check className="h-6 w-6 text-green-600 dark:text-green-400" />
      </div>
      <div>
        <p className="text-lg font-medium">Import Complete!</p>
        <p className="text-sm text-muted-foreground">
          Redirecting to your project...
        </p>
      </div>
    </div>
  );

  return (
    <Dialog open={isOpen} onOpenChange={onClose}>
      <DialogContent className="max-w-2xl">
        <DialogHeader>
          <DialogTitle>
            Import Project
            {step !== 'upload' && step !== 'importing' && step !== 'complete' && (
              <span className="text-sm font-normal text-muted-foreground ml-2">
                Step {step === 'preview' ? '2' : '3'} of 3
              </span>
            )}
          </DialogTitle>
        </DialogHeader>

        {error && (
          <Alert variant="destructive">
            <AlertDescription>{error}</AlertDescription>
          </Alert>
        )}

        {step === 'upload' && renderUploadStep()}
        {step === 'preview' && renderPreviewStep()}
        {step === 'options' && renderOptionsStep()}
        {step === 'importing' && renderImportingStep()}
        {step === 'complete' && renderCompleteStep()}
      </DialogContent>
    </Dialog>
  );
}
```

---

## 30.3 Export Flow

### 30.3.1 Export Entry Points

1. **Project Card Menu** - "Export" option
2. **Project Settings** - "Export Project" button
3. **Command Palette** - `Ctrl+Shift+E` → "Export Project"

### 30.3.2 Export Modal Layout

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  Export Project: Exam Manager                                          [×]  │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  Export Options                                                              │
│  ───────────────────────────────────────────────────────────────────────    │
│                                                                              │
│  What to Include                                                             │
│  ┌────────────────────────────────────────────────────────────────────────┐ │
│  │  ☑  Spec Files (45 files, 230 KB)                                      │ │
│  │  ☑  Ideas Folder (12 files, 25 KB)                                     │ │
│  │  ☑  Instructions Folder (8 files, 18 KB)                               │ │
│  │  ☐  History Snapshots (15 snapshots, 1.2 MB)                           │ │
│  └────────────────────────────────────────────────────────────────────────┘ │
│                                                                              │
│  ───────────────────────────────────────────────────────────────────────    │
│                                                                              │
│  Estimated Export Size: 273 KB                                               │
│  Filename: exam-manager-export-2026-01-28.zip                                │
│                                                                              │
│                                                       [Cancel]  [Export]    │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 30.3.3 Export Component

```typescript
// components/export/ExportModal.tsx
import { useState, useEffect } from 'react';
import { Download, FileArchive, Loader2 } from 'lucide-react';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Progress } from '@/components/ui/progress';
import { useExport } from '@/hooks/useExport';
import { Project, ExportOptions } from '@/types/project';

interface ExportModalProps {
  isOpen: boolean;
  onClose: () => void;
  project: Project;
}

export function ExportModal({ isOpen, onClose, project }: ExportModalProps) {
  const [options, setOptions] = useState<ExportOptions>({
    includeHistory: false,
    includeIdeas: true,
    includeInstructions: true,
    selectedFiles: [],
  });
  
  const { 
    startExport, 
    downloadExport, 
    exportStatus, 
    isExporting, 
    progress,
    estimatedSize 
  } = useExport();

  const handleExport = async () => {
    const exportId = await startExport(project.id, options);
    if (exportId) {
      // Poll for completion, then download
      // This is handled by useExport hook
    }
  };

  const formatDate = () => {
    return new Date().toISOString().split('T')[0];
  };

  return (
    <Dialog open={isOpen} onOpenChange={onClose}>
      <DialogContent className="max-w-md">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <FileArchive className="h-5 w-5" />
            Export Project: {project.name}
          </DialogTitle>
        </DialogHeader>

        {!isExporting && exportStatus !== 'completed' && (
          <div className="space-y-6">
            <div className="space-y-4">
              <Label className="text-sm font-medium">What to Include</Label>
              
              <div className="space-y-3">
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <Checkbox
                      id="spec"
                      checked={true}
                      disabled
                    />
                    <Label htmlFor="spec" className="font-normal">
                      Spec Files
                    </Label>
                  </div>
                  <span className="text-xs text-muted-foreground">
                    {project.fileCount} files
                  </span>
                </div>

                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <Checkbox
                      id="ideas"
                      checked={options.includeIdeas}
                      onCheckedChange={(checked) => 
                        setOptions(prev => ({ ...prev, includeIdeas: !!checked }))
                      }
                    />
                    <Label htmlFor="ideas" className="font-normal">
                      Ideas Folder
                    </Label>
                  </div>
                  <span className="text-xs text-muted-foreground">
                    {project.ideaCount || 0} files
                  </span>
                </div>

                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <Checkbox
                      id="instructions"
                      checked={options.includeInstructions}
                      onCheckedChange={(checked) => 
                        setOptions(prev => ({ ...prev, includeInstructions: !!checked }))
                      }
                    />
                    <Label htmlFor="instructions" className="font-normal">
                      Instructions Folder
                    </Label>
                  </div>
                  <span className="text-xs text-muted-foreground">
                    {project.instructionCount || 0} files
                  </span>
                </div>

                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <Checkbox
                      id="history"
                      checked={options.includeHistory}
                      onCheckedChange={(checked) => 
                        setOptions(prev => ({ ...prev, includeHistory: !!checked }))
                      }
                    />
                    <Label htmlFor="history" className="font-normal">
                      History Snapshots
                    </Label>
                  </div>
                  <span className="text-xs text-muted-foreground">
                    {project.snapshotCount || 0} snapshots
                  </span>
                </div>
              </div>
            </div>

            <div className="border-t pt-4 space-y-2">
              <div className="flex justify-between text-sm">
                <span className="text-muted-foreground">Estimated Size:</span>
                <span className="font-medium">{formatBytes(estimatedSize)}</span>
              </div>
              <div className="flex justify-between text-sm">
                <span className="text-muted-foreground">Filename:</span>
                <span className="font-mono text-xs">
                  {project.slug}-export-{formatDate()}.zip
                </span>
              </div>
            </div>
          </div>
        )}

        {isExporting && (
          <div className="py-8 space-y-4">
            <div className="text-center">
              <Loader2 className="h-8 w-8 animate-spin mx-auto mb-2 text-primary" />
              <p className="text-sm text-muted-foreground">
                Preparing export...
              </p>
            </div>
            <Progress value={progress} />
          </div>
        )}

        {exportStatus === 'completed' && (
          <div className="py-8 text-center space-y-4">
            <Download className="h-12 w-12 mx-auto text-green-500" />
            <p className="font-medium">Export Ready!</p>
            <Button onClick={downloadExport} className="w-full">
              <Download className="h-4 w-4 mr-2" />
              Download ZIP
            </Button>
          </div>
        )}

        <DialogFooter>
          {!isExporting && exportStatus !== 'completed' && (
            <>
              <Button variant="outline" onClick={onClose}>
                Cancel
              </Button>
              <Button onClick={handleExport}>
                Export
              </Button>
            </>
          )}
          {exportStatus === 'completed' && (
            <Button variant="outline" onClick={onClose}>
              Close
            </Button>
          )}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
```

---

## 30.4 Import/Export Hooks

```typescript
// hooks/useImport.ts
import { useState, useCallback } from 'react';
import { useMutation } from '@tanstack/react-query';
import { apiClient } from '@/lib/api-client';
import { ImportPreview, ImportOptions, ImportResult } from '@/types/import';

export function useImport() {
  const [progress, setProgress] = useState(0);
  const [error, setError] = useState<string | null>(null);

  const previewMutation = useMutation({
    mutationFn: async (file: File): Promise<ImportPreview> => {
      const formData = new FormData();
      formData.append('file', file);
      
      const response = await apiClient.post('/import/preview', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      
      return response.data.data;
    },
    onError: (err: Error) => {
      setError(err.message);
    },
  });

  const executeMutation = useMutation({
    mutationFn: async ({ 
      file, 
      options 
    }: { 
      file: File; 
      options: ImportOptions;
    }): Promise<ImportResult> => {
      const formData = new FormData();
      formData.append('file', file);
      formData.append('options', JSON.stringify(options));
      
      const response = await apiClient.post('/import/execute', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
        onUploadProgress: (progressEvent) => {
          const percentCompleted = Math.round(
            (progressEvent.loaded * 100) / (progressEvent.total || 1)
          );
          setProgress(percentCompleted);
        },
      });
      
      return response.data.data;
    },
    onError: (err: Error) => {
      setError(err.message);
    },
  });

  const previewImport = useCallback(async (file: File) => {
    setError(null);
    return previewMutation.mutateAsync(file);
  }, [previewMutation]);

  const executeImport = useCallback(async (file: File, options: ImportOptions) => {
    setError(null);
    setProgress(0);
    return executeMutation.mutateAsync({ file, options });
  }, [executeMutation]);

  return {
    previewImport,
    executeImport,
    isLoading: previewMutation.isPending || executeMutation.isPending,
    progress,
    error,
  };
}
```

```typescript
// hooks/useExport.ts
import { useState, useCallback, useEffect } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';
import { apiClient } from '@/lib/api-client';
import { ExportOptions, ExportStatus } from '@/types/export';

export function useExport() {
  const [exportId, setExportId] = useState<string | null>(null);
  const [progress, setProgress] = useState(0);

  const startMutation = useMutation({
    mutationFn: async ({ 
      projectId, 
      options 
    }: { 
      projectId: string; 
      options: ExportOptions;
    }): Promise<{ exportId: string }> => {
      const response = await apiClient.post(`/projects/${projectId}/export`, { options });
      return response.data.data;
    },
    onSuccess: (data) => {
      setExportId(data.exportId);
    },
  });

  // Poll for export status
  const { data: statusData } = useQuery({
    queryKey: ['export-status', exportId],
    queryFn: async () => {
      const response = await apiClient.get(`/exports/${exportId}/status`);
      return response.data.data;
    },
    enabled: !!exportId,
    refetchInterval: (data) => {
      if (data?.status === 'completed' || data?.status === 'failed') {
        return false;
      }
      return 1000; // Poll every second
    },
  });

  useEffect(() => {
    if (statusData?.progress) {
      setProgress(statusData.progress);
    }
  }, [statusData]);

  const startExport = useCallback(async (projectId: string, options: ExportOptions) => {
    setProgress(0);
    return startMutation.mutateAsync({ projectId, options });
  }, [startMutation]);

  const downloadExport = useCallback(() => {
    if (exportId && statusData?.status === 'completed') {
      window.location.href = `/api/v1/exports/${exportId}/download`;
    }
  }, [exportId, statusData]);

  return {
    startExport,
    downloadExport,
    exportStatus: statusData?.status as ExportStatus | undefined,
    isExporting: startMutation.isPending || statusData?.status === 'processing',
    progress,
    estimatedSize: statusData?.estimatedSize || 0,
  };
}
```

---

## 30.5 Drag & Drop Integration

```typescript
// components/project/DashboardDropZone.tsx
import { useCallback } from 'react';
import { useDropzone } from 'react-dropzone';
import { cn } from '@/lib/utils';

interface DashboardDropZoneProps {
  children: React.ReactNode;
  onFileDrop: (file: File) => void;
}

export function DashboardDropZone({ children, onFileDrop }: DashboardDropZoneProps) {
  const onDrop = useCallback((acceptedFiles: File[]) => {
    if (acceptedFiles.length > 0) {
      onFileDrop(acceptedFiles[0]);
    }
  }, [onFileDrop]);

  const { getRootProps, getInputProps, isDragActive } = useDropzone({
    onDrop,
    accept: {
      'application/zip': ['.zip'],
      'text/markdown': ['.md'],
    },
    noClick: true, // Don't trigger on click, only drag
    maxFiles: 1,
  });

  return (
    <div {...getRootProps()} className="relative min-h-screen">
      <input {...getInputProps()} />
      
      {/* Drag overlay */}
      {isDragActive && (
        <div className="fixed inset-0 z-50 bg-background/80 backdrop-blur-sm flex items-center justify-center">
          <div className="border-2 border-dashed border-primary rounded-xl p-12 text-center">
            <FileArchive className="h-16 w-16 mx-auto mb-4 text-primary" />
            <p className="text-xl font-medium">Drop to import project</p>
            <p className="text-sm text-muted-foreground">
              Supported: .zip, .md files
            </p>
          </div>
        </div>
      )}
      
      {children}
    </div>
  );
}
```

---

## 30.6 Keyboard Shortcuts

| Shortcut | Action |
|----------|--------|
| `Ctrl+Shift+I` | Open Import Modal |
| `Ctrl+Shift+E` | Open Export Modal (when project selected) |
| `Escape` | Close modal |

---

## 30.7 Acceptance Criteria

### Import UI Criteria

- [ ] Drag & drop zone accepts .zip and .md files
- [ ] Preview shows accurate file tree and metadata
- [ ] Warnings displayed for missing metadata
- [ ] All import options are configurable
- [ ] Progress indicator during import
- [ ] Success message with redirect to project
- [ ] Error messages are clear and actionable

### Export UI Criteria

- [ ] Export options clearly show what will be included
- [ ] Estimated size updates based on selections
- [ ] Progress indicator during export generation
- [ ] Download triggers automatically when ready
- [ ] Can cancel export in progress

### Integration Criteria

- [ ] Dashboard header has Import button
- [ ] Project card menu has Export option
- [ ] Command palette supports both actions
- [ ] Drag & drop works on dashboard
- [ ] Keyboard shortcuts functional
