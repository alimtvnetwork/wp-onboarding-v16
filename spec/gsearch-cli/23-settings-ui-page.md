# 23. Settings UI Page Specification

**Version:** 1.0.0  
**Status:** Planned  
**Updated:** 2026-01-29  
**Parent:** [GSearch CLI Overview](./00-overview.md)

---

## Purpose

Define the Settings UI page for editing all seedable configuration categories. This specification covers the React/TypeScript frontend that interfaces with the Golang SettingsService API to provide runtime configuration management.

---

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           SETTINGS UI ARCHITECTURE                           │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  ┌────────────────────────────────────────────────────────────────────────┐ │
│  │                         SETTINGS PAGE                                   │ │
│  │                                                                         │ │
│  │  ┌─────────────┐  ┌─────────────────────────────────────────────────┐  │ │
│  │  │  Category   │  │            Settings Panel                        │  │ │
│  │  │  Sidebar    │  │                                                  │  │ │
│  │  │             │  │  ┌─────────────────────────────────────────────┐ │  │ │
│  │  │ □ Model     │  │  │  Category Header + Version                  │ │  │ │
│  │  │   Routing   │  │  │  ──────────────────────────────────────────  │ │  │ │
│  │  │             │  │  │                                              │ │  │ │
│  │  │ □ Authority │  │  │  Setting Key 1            [Input Field]     │ │  │ │
│  │  │   Scores    │  │  │  Description text...      [Reset] [Save]    │ │  │ │
│  │  │             │  │  │                                              │ │  │ │
│  │  │ □ Source    │  │  │  ──────────────────────────────────────────  │ │  │ │
│  │  │   Weights   │  │  │                                              │ │  │ │
│  │  │             │  │  │  Setting Key 2            [Slider]          │ │  │ │
│  │  │ □ Credib.   │  │  │  Description text...      [Reset] [Save]    │ │  │ │
│  │  │   Thresholds│  │  │                                              │ │  │ │
│  │  │             │  │  │  ──────────────────────────────────────────  │ │  │ │
│  │  │ □ Confidence│  │  │                                              │ │  │ │
│  │  │   Metrics   │  │  │  Setting Key 3 (Object)   [Expand]          │ │  │ │
│  │  │             │  │  │  ├─ sub_key_1             [0.40]            │ │  │ │
│  │  │ □ Trend     │  │  │  ├─ sub_key_2             [0.25]            │ │  │ │
│  │  │   Analysis  │  │  │  └─ sub_key_3             [0.20]            │ │  │ │
│  │  │             │  │  │                           [Reset] [Save]    │ │  │ │
│  │  └─────────────┘  │  │                                              │ │  │ │
│  │                    │  └─────────────────────────────────────────────┘ │  │ │
│  │                    │                                                  │  │ │
│  │                    │  ┌─────────────────────────────────────────────┐ │  │ │
│  │                    │  │  Category Actions                           │ │  │ │
│  │                    │  │  [Reset All to Defaults] [Export JSON]      │ │  │ │
│  │                    │  └─────────────────────────────────────────────┘ │  │ │
│  │                    └─────────────────────────────────────────────────┘  │ │
│  │                                                                         │ │
│  └────────────────────────────────────────────────────────────────────────┘ │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## Component Structure

```
src/
├── pages/
│   └── Settings/
│       └── index.tsx                    # Main settings page
├── components/
│   └── settings/
│       ├── CategorySidebar.tsx          # Category navigation
│       ├── SettingsPanel.tsx            # Main settings display
│       ├── SettingCard.tsx              # Individual setting card
│       ├── SettingEditors/
│       │   ├── StringEditor.tsx         # Text input
│       │   ├── NumberEditor.tsx         # Numeric input with slider
│       │   ├── BooleanEditor.tsx        # Toggle switch
│       │   ├── ArrayEditor.tsx          # List editor
│       │   ├── ObjectEditor.tsx         # Nested key-value editor
│       │   └── WeightsEditor.tsx        # Specialized weights editor
│       ├── CategoryHeader.tsx           # Category title + version
│       ├── CategoryActions.tsx          # Reset/Export buttons
│       ├── SettingModifiedBadge.tsx     # User-modified indicator
│       └── SettingsSearchBar.tsx        # Search across settings
├── hooks/
│   └── useSettings.ts                   # Settings data hook
├── types/
│   └── settings.ts                      # TypeScript interfaces
└── api/
    └── settings.ts                      # API client
```

---

## TypeScript Interfaces

### Core Types

```typescript
enum ConfigCategory {
  ModelRouting = "model_routing",
  AuthorityScores = "authority_scores",
  SourceWeights = "source_weights",
  CredibilityThresholds = "credibility_thresholds",
  ConfidenceMetrics = "confidence_metrics",
  TrendAnalysis = "trend_analysis",
  SearchSettings = "search_settings",
  CacheSettings = "cache_settings",
}

enum ValueType {
  String = "string",
  Number = "number",
  Boolean = "boolean",
  Array = "array",
  Object = "object",
}

interface Setting {
  readonly id: string;
  readonly key: string;
  value: SettingValue;
  readonly category: ConfigCategory;
  readonly version: string;
  readonly valueType: ValueType;
  readonly description: string;
  readonly isUserModified: boolean;
  readonly defaultValue: SettingValue;
  readonly createdAt: string;
  readonly updatedAt: string;
}

type SettingValue =
  | string
  | number
  | boolean
  | readonly string[]
  | Record<string, string | number | boolean>;

interface CategoryMetadata {
  readonly category: ConfigCategory;
  readonly displayName: string;
  readonly description: string;
  readonly icon: React.ComponentType;
  readonly version: string;
  readonly settingCount: number;
  readonly modifiedCount: number;
}
```

### API Response Types

```typescript
interface SettingsResponse {
  readonly settings: readonly Setting[];
  readonly category: ConfigCategory;
  readonly version: string;
}

interface UpdateSettingRequest {
  readonly category: ConfigCategory;
  readonly key: string;
  readonly value: SettingValue;
}

interface UpdateSettingResponse {
  readonly success: boolean;
  readonly setting: Setting;
  readonly error?: string;
}

interface ResetSettingRequest {
  readonly category: ConfigCategory;
  readonly key: string;
}

interface ExportCategoryResponse {
  readonly seedFile: SeedFile;
}

interface SeedFile {
  readonly version: string;
  readonly category: ConfigCategory;
  readonly description: string;
  readonly values: Record<string, SettingValue>;
}
```

---

## Category Configuration

```typescript
const CATEGORY_CONFIG: Record<ConfigCategory, CategoryConfig> = {
  [ConfigCategory.ModelRouting]: {
    displayName: "Model Routing",
    description: "LLM complexity thresholds and model pool configuration",
    icon: CpuIcon,
    color: "blue",
  },
  [ConfigCategory.AuthorityScores]: {
    displayName: "Authority Scores",
    description: "Domain authority values for source credibility",
    icon: ShieldCheckIcon,
    color: "green",
  },
  [ConfigCategory.SourceWeights]: {
    displayName: "Source Weights",
    description: "Weight formula coefficients for scoring",
    icon: ScaleIcon,
    color: "purple",
  },
  [ConfigCategory.CredibilityThresholds]: {
    displayName: "Credibility Thresholds",
    description: "Classification thresholds for source credibility",
    icon: AlertTriangleIcon,
    color: "orange",
  },
  [ConfigCategory.ConfidenceMetrics]: {
    displayName: "Confidence Metrics",
    description: "Confidence analysis weights and thresholds",
    icon: TargetIcon,
    color: "cyan",
  },
  [ConfigCategory.TrendAnalysis]: {
    displayName: "Trend Analysis",
    description: "Composite scoring and growth rate weights",
    icon: TrendingUpIcon,
    color: "pink",
  },
  [ConfigCategory.SearchSettings]: {
    displayName: "Search Settings",
    description: "Search behavior and output configuration",
    icon: SearchIcon,
    color: "gray",
  },
  [ConfigCategory.CacheSettings]: {
    displayName: "Cache Settings",
    description: "Cache TTL and storage limits",
    icon: DatabaseIcon,
    color: "yellow",
  },
};
```

---

## API Client

```typescript
// api/settings.ts

const API_BASE = "/api/settings";

export const settingsApi = {
  // Get all settings for a category
  async getByCategory(category: ConfigCategory): Promise<SettingsResponse> {
    const response = await fetch(`${API_BASE}/category/${category}`);
    if (!response.ok) throw new Error("Failed to fetch settings");
    return response.json();
  },

  // Get all categories with metadata
  async getAllCategories(): Promise<readonly CategoryMetadata[]> {
    const response = await fetch(`${API_BASE}/categories`);
    if (!response.ok) throw new Error("Failed to fetch categories");
    return response.json();
  },

  // Update a single setting
  async updateSetting(
    category: ConfigCategory,
    key: string,
    value: SettingValue
  ): Promise<UpdateSettingResponse> {
    const response = await fetch(`${API_BASE}/update`, {
      method: "PUT",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ category, key, value }),
    });
    if (!response.ok) throw new Error("Failed to update setting");
    return response.json();
  },

  // Reset a single setting to default
  async resetSetting(
    category: ConfigCategory,
    key: string
  ): Promise<UpdateSettingResponse> {
    const response = await fetch(`${API_BASE}/reset`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ category, key }),
    });
    if (!response.ok) throw new Error("Failed to reset setting");
    return response.json();
  },

  // Reset entire category to defaults
  async resetCategory(category: ConfigCategory): Promise<{ success: boolean }> {
    const response = await fetch(`${API_BASE}/reset-category/${category}`, {
      method: "POST",
    });
    if (!response.ok) throw new Error("Failed to reset category");
    return response.json();
  },

  // Export category as seed file JSON
  async exportCategory(category: ConfigCategory): Promise<ExportCategoryResponse> {
    const response = await fetch(`${API_BASE}/export/${category}`);
    if (!response.ok) throw new Error("Failed to export category");
    return response.json();
  },

  // Search settings across all categories
  async searchSettings(query: string): Promise<readonly Setting[]> {
    const response = await fetch(`${API_BASE}/search?q=${encodeURIComponent(query)}`);
    if (!response.ok) throw new Error("Failed to search settings");
    return response.json();
  },
};
```

---

## React Hook

```typescript
// hooks/useSettings.ts

import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { settingsApi } from "@/api/settings";
import { ConfigCategory, Setting, SettingValue } from "@/types/settings";

export function useSettings(category: ConfigCategory) {
  const queryClient = useQueryClient();

  const settingsQuery = useQuery({
    queryKey: ["settings", category],
    queryFn: () => settingsApi.getByCategory(category),
    staleTime: 30000, // 30 seconds
  });

  const updateMutation = useMutation({
    mutationFn: ({ key, value }: { key: string; value: SettingValue }) =>
      settingsApi.updateSetting(category, key, value),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["settings", category] });
      queryClient.invalidateQueries({ queryKey: ["categories"] });
    },
  });

  const resetMutation = useMutation({
    mutationFn: (key: string) => settingsApi.resetSetting(category, key),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["settings", category] });
      queryClient.invalidateQueries({ queryKey: ["categories"] });
    },
  });

  const resetCategoryMutation = useMutation({
    mutationFn: () => settingsApi.resetCategory(category),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["settings", category] });
      queryClient.invalidateQueries({ queryKey: ["categories"] });
    },
  });

  return {
    settings: settingsQuery.data?.settings ?? [],
    version: settingsQuery.data?.version ?? "",
    isLoading: settingsQuery.isLoading,
    isError: settingsQuery.isError,
    error: settingsQuery.error,
    updateSetting: updateMutation.mutate,
    resetSetting: resetMutation.mutate,
    resetCategory: resetCategoryMutation.mutate,
    isUpdating: updateMutation.isPending,
    isResetting: resetMutation.isPending || resetCategoryMutation.isPending,
  };
}

export function useCategories() {
  return useQuery({
    queryKey: ["categories"],
    queryFn: () => settingsApi.getAllCategories(),
    staleTime: 60000, // 1 minute
  });
}

export function useSettingsSearch(query: string) {
  return useQuery({
    queryKey: ["settings-search", query],
    queryFn: () => settingsApi.searchSettings(query),
    enabled: query.length >= 2,
    staleTime: 10000,
  });
}
```

---

## Component Specifications

### SettingsPage Component

```typescript
// pages/Settings/index.tsx

interface SettingsPageProps {}

export function SettingsPage({}: SettingsPageProps): JSX.Element {
  // State
  const [selectedCategory, setSelectedCategory] = useState<ConfigCategory>(
    ConfigCategory.ModelRouting
  );
  const [searchQuery, setSearchQuery] = useState<string>("");

  // Data
  const { categories, isLoading: categoriesLoading } = useCategories();
  const { settings, version, isLoading, updateSetting, resetSetting, resetCategory } =
    useSettings(selectedCategory);

  // Render
  return (
    <div className="flex h-full">
      {/* Sidebar */}
      <CategorySidebar
        categories={categories}
        selectedCategory={selectedCategory}
        onSelectCategory={setSelectedCategory}
      />

      {/* Main Panel */}
      <div className="flex-1 flex flex-col">
        {/* Search Bar */}
        <SettingsSearchBar value={searchQuery} onChange={setSearchQuery} />

        {/* Category Header */}
        <CategoryHeader category={selectedCategory} version={version} />

        {/* Settings List */}
        <SettingsPanel
          settings={settings}
          isLoading={isLoading}
          onUpdate={updateSetting}
          onReset={resetSetting}
        />

        {/* Category Actions */}
        <CategoryActions
          category={selectedCategory}
          onResetAll={resetCategory}
          onExport={() => exportCategory(selectedCategory)}
        />
      </div>
    </div>
  );
}
```

### CategorySidebar Component

```typescript
// components/settings/CategorySidebar.tsx

interface CategorySidebarProps {
  readonly categories: readonly CategoryMetadata[];
  readonly selectedCategory: ConfigCategory;
  readonly onSelectCategory: (category: ConfigCategory) => void;
}

export function CategorySidebar({
  categories,
  selectedCategory,
  onSelectCategory,
}: CategorySidebarProps): JSX.Element {
  return (
    <aside className="w-64 border-r bg-muted/30">
      <div className="p-4">
        <h2 className="text-lg font-semibold">Settings</h2>
      </div>
      <nav className="space-y-1 px-2">
        {categories.map((cat) => {
          const config = CATEGORY_CONFIG[cat.category];
          const Icon = config.icon;
          const isSelected = cat.category === selectedCategory;

          return (
            <button
              key={cat.category}
              onClick={() => onSelectCategory(cat.category)}
              className={cn(
                "w-full flex items-center gap-3 px-3 py-2 rounded-md text-sm",
                isSelected
                  ? "bg-primary text-primary-foreground"
                  : "hover:bg-muted"
              )}
            >
              <Icon className="h-4 w-4" />
              <span className="flex-1 text-left">{config.displayName}</span>
              {cat.modifiedCount > 0 && (
                <Badge variant="secondary" className="text-xs">
                  {cat.modifiedCount}
                </Badge>
              )}
            </button>
          );
        })}
      </nav>
    </aside>
  );
}
```

### SettingCard Component

```typescript
// components/settings/SettingCard.tsx

interface SettingCardProps {
  readonly setting: Setting;
  readonly onUpdate: (value: SettingValue) => void;
  readonly onReset: () => void;
  readonly isUpdating: boolean;
}

export function SettingCard({
  setting,
  onUpdate,
  onReset,
  isUpdating,
}: SettingCardProps): JSX.Element {
  const [localValue, setLocalValue] = useState<SettingValue>(setting.value);
  const [isDirty, setIsDirty] = useState(false);

  // Reset local value when setting changes
  useEffect(() => {
    setLocalValue(setting.value);
    setIsDirty(false);
  }, [setting.value]);

  const handleChange = (newValue: SettingValue) => {
    setLocalValue(newValue);
    setIsDirty(true);
  };

  const handleSave = () => {
    onUpdate(localValue);
    setIsDirty(false);
  };

  const handleReset = () => {
    onReset();
    setLocalValue(setting.defaultValue);
    setIsDirty(false);
  };

  return (
    <Card className="p-4">
      <div className="flex items-start justify-between gap-4">
        <div className="flex-1">
          {/* Header */}
          <div className="flex items-center gap-2">
            <h3 className="font-mono text-sm font-medium">{setting.key}</h3>
            {setting.isUserModified && <SettingModifiedBadge />}
          </div>

          {/* Description */}
          {setting.description && (
            <p className="text-sm text-muted-foreground mt-1">
              {setting.description}
            </p>
          )}

          {/* Editor */}
          <div className="mt-3">
            <SettingEditor
              valueType={setting.valueType}
              value={localValue}
              onChange={handleChange}
            />
          </div>
        </div>

        {/* Actions */}
        <div className="flex gap-2">
          {setting.isUserModified && (
            <Button
              variant="ghost"
              size="sm"
              onClick={handleReset}
              disabled={isUpdating}
            >
              <RotateCcwIcon className="h-4 w-4" />
            </Button>
          )}
          <Button
            variant="default"
            size="sm"
            onClick={handleSave}
            disabled={!isDirty || isUpdating}
          >
            {isUpdating ? <Loader2 className="h-4 w-4 animate-spin" /> : "Save"}
          </Button>
        </div>
      </div>
    </Card>
  );
}
```

### Setting Editors

#### NumberEditor

```typescript
// components/settings/SettingEditors/NumberEditor.tsx

interface NumberEditorProps {
  readonly value: number;
  readonly onChange: (value: number) => void;
  readonly min?: number;
  readonly max?: number;
  readonly step?: number;
  readonly showSlider?: boolean;
}

export function NumberEditor({
  value,
  onChange,
  min = 0,
  max = 1,
  step = 0.01,
  showSlider = true,
}: NumberEditorProps): JSX.Element {
  return (
    <div className="flex items-center gap-4">
      {showSlider && (
        <Slider
          value={[value]}
          onValueChange={([v]) => onChange(v)}
          min={min}
          max={max}
          step={step}
          className="flex-1"
        />
      )}
      <Input
        type="number"
        value={value}
        onChange={(e) => onChange(parseFloat(e.target.value))}
        min={min}
        max={max}
        step={step}
        className="w-24"
      />
    </div>
  );
}
```

#### ObjectEditor (Weights)

```typescript
// components/settings/SettingEditors/ObjectEditor.tsx

interface ObjectEditorProps {
  readonly value: Record<string, number | string | boolean>;
  readonly onChange: (value: Record<string, number | string | boolean>) => void;
}

export function ObjectEditor({
  value,
  onChange,
}: ObjectEditorProps): JSX.Element {
  const [isExpanded, setIsExpanded] = useState(false);

  const handleFieldChange = (key: string, newValue: number | string | boolean) => {
    onChange({ ...value, [key]: newValue });
  };

  return (
    <Collapsible open={isExpanded} onOpenChange={setIsExpanded}>
      <CollapsibleTrigger asChild>
        <Button variant="ghost" className="w-full justify-between">
          <span>{Object.keys(value).length} properties</span>
          <ChevronDownIcon
            className={cn("h-4 w-4 transition-transform", isExpanded && "rotate-180")}
          />
        </Button>
      </CollapsibleTrigger>
      <CollapsibleContent className="space-y-2 pt-2">
        {Object.entries(value).map(([key, val]) => (
          <div key={key} className="flex items-center gap-2 pl-4">
            <span className="font-mono text-xs text-muted-foreground w-32 truncate">
              {key}
            </span>
            {typeof val === "number" ? (
              <NumberEditor
                value={val}
                onChange={(v) => handleFieldChange(key, v)}
                showSlider={val >= 0 && val <= 1}
              />
            ) : typeof val === "boolean" ? (
              <Switch
                checked={val}
                onCheckedChange={(v) => handleFieldChange(key, v)}
              />
            ) : (
              <Input
                value={val as string}
                onChange={(e) => handleFieldChange(key, e.target.value)}
              />
            )}
          </div>
        ))}
      </CollapsibleContent>
    </Collapsible>
  );
}
```

#### WeightsEditor (Specialized)

```typescript
// components/settings/SettingEditors/WeightsEditor.tsx

interface WeightsEditorProps {
  readonly value: Record<string, number>;
  readonly onChange: (value: Record<string, number>) => void;
  readonly mustSumToOne?: boolean;
}

export function WeightsEditor({
  value,
  onChange,
  mustSumToOne = true,
}: WeightsEditorProps): JSX.Element {
  const total = Object.values(value).reduce((sum, v) => sum + v, 0);
  const isValid = !mustSumToOne || Math.abs(total - 1.0) < 0.01;

  const handleWeightChange = (key: string, newValue: number) => {
    onChange({ ...value, [key]: newValue });
  };

  return (
    <div className="space-y-3">
      {/* Weights */}
      {Object.entries(value).map(([key, weight]) => (
        <div key={key} className="flex items-center gap-3">
          <span className="font-mono text-xs w-40 truncate">{key}</span>
          <Slider
            value={[weight]}
            onValueChange={([v]) => handleWeightChange(key, v)}
            min={0}
            max={1}
            step={0.01}
            className="flex-1"
          />
          <span className="text-xs font-mono w-12 text-right">
            {(weight * 100).toFixed(0)}%
          </span>
        </div>
      ))}

      {/* Total indicator */}
      {mustSumToOne && (
        <div
          className={cn(
            "flex items-center justify-between text-xs pt-2 border-t",
            isValid ? "text-green-600" : "text-destructive"
          )}
        >
          <span>Total:</span>
          <span className="font-mono">{(total * 100).toFixed(0)}%</span>
          {!isValid && <span className="text-destructive">Must equal 100%</span>}
        </div>
      )}
    </div>
  );
}
```

---

## Category-Specific Configurations

### Confidence Metrics Category

```typescript
// Renders specialized UI for confidence_metrics

const CONFIDENCE_METRICS_KEYS: readonly SettingKeyConfig[] = [
  {
    key: "weights",
    displayName: "Confidence Weights",
    description: "Weights for overall confidence calculation (must sum to 1.0)",
    editor: "weights",
    validation: { mustSumToOne: true },
  },
  {
    key: "thresholds",
    displayName: "Thresholds",
    description: "Threshold values for confidence classification",
    editor: "object",
  },
  {
    key: "expert_domains",
    displayName: "Expert Domains",
    description: "Domains considered expert/authoritative sources",
    editor: "array",
  },
  {
    key: "warnings",
    displayName: "Warning Messages",
    description: "Warning messages displayed for low confidence scenarios",
    editor: "object",
  },
  {
    key: "freshness_decay",
    displayName: "Freshness Decay",
    description: "Time decay parameters for data freshness scoring",
    editor: "object",
  },
  {
    key: "agreement_detection",
    displayName: "Agreement Detection",
    description: "Parameters for detecting source agreement/contradiction",
    editor: "object",
  },
];
```

### Trend Analysis Category

```typescript
// Renders specialized UI for trend_analysis

const TREND_ANALYSIS_KEYS: readonly SettingKeyConfig[] = [
  {
    key: "composite_score_weights",
    displayName: "Composite Score Weights",
    description: "Weights for trend composite scoring (GitHub, Jobs, SO, Downloads)",
    editor: "weights",
    validation: { mustSumToOne: true },
  },
  {
    key: "signal_normalization",
    displayName: "Signal Normalization",
    description: "Maximum values for normalizing each signal type",
    editor: "object",
  },
  {
    key: "growth_rate_weights",
    displayName: "Growth Rate Weights",
    description: "Weights for different growth periods",
    editor: "weights",
    validation: { mustSumToOne: true },
  },
  {
    key: "visualization",
    displayName: "Visualization Settings",
    description: "Chart generation parameters",
    editor: "object",
  },
  {
    key: "data_sources",
    displayName: "Data Source Weights",
    description: "Reliability weights for each data source API",
    editor: "object",
  },
  {
    key: "freshness_requirements",
    displayName: "Freshness Requirements",
    description: "Data staleness thresholds and penalties",
    editor: "object",
  },
];
```

---

## Export Functionality

```typescript
// CategoryActions.tsx - Export handler

const handleExport = async (category: ConfigCategory) => {
  try {
    const { seedFile } = await settingsApi.exportCategory(category);
    
    // Generate filename
    const filename = `seeding-${category.replace(/_/g, "-")}.json`;
    
    // Format JSON with indentation
    const jsonContent = JSON.stringify(seedFile, null, 2);
    
    // Create download
    const blob = new Blob([jsonContent], { type: "application/json" });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = filename;
    link.click();
    URL.revokeObjectURL(url);
    
    toast.success(`Exported ${CATEGORY_CONFIG[category].displayName}`);
  } catch (error) {
    toast.error("Failed to export settings");
  }
};
```

---

## Search Functionality

```typescript
// SettingsSearchBar.tsx

interface SettingsSearchBarProps {
  readonly value: string;
  readonly onChange: (value: string) => void;
}

export function SettingsSearchBar({
  value,
  onChange,
}: SettingsSearchBarProps): JSX.Element {
  const { data: searchResults, isLoading } = useSettingsSearch(value);

  return (
    <div className="relative">
      <div className="flex items-center gap-2 px-4 py-2 border-b">
        <SearchIcon className="h-4 w-4 text-muted-foreground" />
        <Input
          type="text"
          placeholder="Search settings..."
          value={value}
          onChange={(e) => onChange(e.target.value)}
          className="border-0 focus-visible:ring-0"
        />
        {value && (
          <Button variant="ghost" size="icon" onClick={() => onChange("")}>
            <XIcon className="h-4 w-4" />
          </Button>
        )}
      </div>

      {/* Search Results Dropdown */}
      {value.length >= 2 && (
        <div className="absolute top-full left-0 right-0 bg-background border rounded-b-md shadow-lg z-50 max-h-64 overflow-auto">
          {isLoading ? (
            <div className="p-4 text-center text-muted-foreground">
              Searching...
            </div>
          ) : searchResults && searchResults.length > 0 ? (
            <div className="py-2">
              {searchResults.map((setting) => (
                <SearchResultItem key={setting.id} setting={setting} />
              ))}
            </div>
          ) : (
            <div className="p-4 text-center text-muted-foreground">
              No settings found
            </div>
          )}
        </div>
      )}
    </div>
  );
}
```

---

## Validation Rules

```typescript
interface ValidationRule {
  readonly type: "range" | "sum" | "pattern" | "custom";
  readonly params?: Record<string, unknown>;
  readonly message: string;
}

const VALIDATION_RULES: Record<string, readonly ValidationRule[]> = {
  // Weights must sum to 1.0
  "confidence_metrics:weights": [
    {
      type: "sum",
      params: { target: 1.0, tolerance: 0.01 },
      message: "Weights must sum to 100%",
    },
  ],
  
  // Thresholds must be between 0 and 1
  "credibility_thresholds:thresholds": [
    {
      type: "range",
      params: { min: 0, max: 1 },
      message: "Thresholds must be between 0 and 1",
    },
  ],
  
  // Growth rate weights must sum to 1.0
  "trend_analysis:growth_rate_weights": [
    {
      type: "sum",
      params: { target: 1.0, tolerance: 0.01 },
      message: "Growth rate weights must sum to 100%",
    },
  ],
};
```

---

## Related Specifications

- [SettingsService Implementation](./22-settings-service-implementation.md)
- [Seedable Config Pattern](../../04-coding-guidelines/05-seedable-config-pattern.md)
- [Trend Analysis Engine](./20-trend-analysis-engine.md)
- [Authority & Credibility Scoring](./19-authority-credibility-scoring.md)
