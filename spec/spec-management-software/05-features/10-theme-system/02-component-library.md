# 10.2 Component Library

**Version:** 1.0.0  
**Status:** Planned  
**Last Updated:** 2026-01-28

---

## Overview

Shadcn/UI-based component library with custom variants, theme-aware styling, and consistent design tokens across all UI elements.

**Cross-References:**
- [Theme Provider](./01-theme-provider.md) - Theme context and CSS variables
- [Coding Guidelines](../../04-coding-guidelines/00-overview.md) - Component standards

---

## 10.2.1 Component Categories

### Core Components

| Component | Description | Variants |
|-----------|-------------|----------|
| Button | Primary action trigger | default, destructive, outline, secondary, ghost, link |
| Input | Text input field | default, error, disabled |
| Select | Dropdown selection | default, multi |
| Checkbox | Boolean toggle | default, indeterminate |
| Radio | Single selection from group | default |
| Switch | Toggle switch | default |
| Textarea | Multi-line text input | default, error |

### Layout Components

| Component | Description | Usage |
|-----------|-------------|-------|
| Card | Container with border | Content grouping |
| Dialog | Modal overlay | Confirmations, forms |
| Sheet | Side panel drawer | Navigation, details |
| Tabs | Tabbed content | Section organization |
| Accordion | Collapsible sections | FAQ, settings |
| Separator | Visual divider | Content separation |

### Navigation Components

| Component | Description | Usage |
|-----------|-------------|-------|
| NavigationMenu | Top-level navigation | Header nav |
| Breadcrumb | Path navigation | Page hierarchy |
| Pagination | Page controls | List navigation |
| Command | Command palette | Quick actions |

### Feedback Components

| Component | Description | Usage |
|-----------|-------------|-------|
| Toast | Notification popup | Status messages |
| Alert | Inline alert | Warnings, info |
| Progress | Progress indicator | Loading, upload |
| Skeleton | Loading placeholder | Content loading |
| Badge | Status indicator | Tags, counts |

---

## 10.2.2 Custom Variants

### Button Variants

```typescript
const buttonVariants = cva(
  "inline-flex items-center justify-center rounded-md text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50",
  {
    variants: {
      variant: {
        default: "bg-primary text-primary-foreground hover:bg-primary/90",
        destructive: "bg-destructive text-destructive-foreground hover:bg-destructive/90",
        outline: "border border-input bg-background hover:bg-accent hover:text-accent-foreground",
        secondary: "bg-secondary text-secondary-foreground hover:bg-secondary/80",
        ghost: "hover:bg-accent hover:text-accent-foreground",
        link: "text-primary underline-offset-4 hover:underline",
        // Custom variants
        premium: "bg-gradient-to-r from-primary to-accent text-white shadow-lg",
        success: "bg-green-600 text-white hover:bg-green-700",
      },
      size: {
        default: "h-10 px-4 py-2",
        sm: "h-9 rounded-md px-3",
        lg: "h-11 rounded-md px-8",
        icon: "h-10 w-10",
      },
    },
    defaultVariants: {
      variant: "default",
      size: "default",
    },
  }
);
```

---

## 10.2.3 Form Components

### FormField Pattern

```typescript
interface FormFieldProps {
  label: string;
  name: string;
  description?: string;
  error?: string;
  required?: boolean;
  children: React.ReactNode;
}

// Usage with react-hook-form
<FormField
  control={form.control}
  name="email"
  render={({ field }) => (
    <FormItem>
      <FormLabel>Email</FormLabel>
      <FormControl>
        <Input placeholder="email@example.com" {...field} />
      </FormControl>
      <FormDescription>Your work email address</FormDescription>
      <FormMessage />
    </FormItem>
  )}
/>
```

---

## 10.2.4 Data Display Components

### DataTable

```typescript
interface DataTableProps<T> {
  columns: ColumnDef<T>[];
  data: T[];
  pagination?: boolean;
  sorting?: boolean;
  filtering?: boolean;
  selection?: boolean;
  onRowClick?: (row: T) => void;
}
```

### TreeView

```typescript
interface TreeNode {
  id: string;
  label: string;
  icon?: React.ReactNode;
  children?: TreeNode[];
  isExpanded?: boolean;
  isSelected?: boolean;
}

interface TreeViewProps {
  nodes: TreeNode[];
  onSelect: (node: TreeNode) => void;
  onExpand: (node: TreeNode) => void;
  renderNode?: (node: TreeNode) => React.ReactNode;
}
```

---

## 10.2.5 Icon System

```typescript
// Using Lucide React icons
import { 
  File, Folder, Settings, User, 
  Play, Pause, Check, X, 
  ChevronRight, ChevronDown,
  // ... 
} from 'lucide-react';

// Icon button pattern
<Button variant="ghost" size="icon">
  <Settings className="h-4 w-4" />
</Button>
```

---

## 10.2.6 Accessibility Standards

| Requirement | Implementation |
|-------------|----------------|
| Keyboard navigation | All interactive elements focusable |
| Screen reader | ARIA labels and roles |
| Color contrast | WCAG AA minimum (4.5:1) |
| Focus indicators | Visible focus rings |
| Motion | Respects `prefers-reduced-motion` |

---

## Related Specs

- [Theme Provider](./01-theme-provider.md)
- [Error UI](../13-error-ui/00-overview.md)
- [Mobile Responsive](../14-mobile-responsive/00-overview.md)
