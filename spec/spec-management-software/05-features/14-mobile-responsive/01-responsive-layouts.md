# 14.1 Responsive Layouts

**Version:** 1.0.0  
**Status:** Planned  
**Last Updated:** 2026-01-28

---

## Overview

Mobile-first responsive design system ensuring optimal user experience across all device sizes, from mobile phones to large desktop monitors.

**Cross-References:**
- [Theme System](../10-theme-system/00-overview.md) - Responsive tokens
- [Dashboard](../11-dashboard/00-overview.md) - Responsive dashboard
- [Component Library](../10-theme-system/02-component-library.md) - Responsive components

---

## 14.1.1 Breakpoint System

| Breakpoint | Min Width | Target Devices |
|------------|-----------|----------------|
| `xs` | 0px | Small phones |
| `sm` | 640px | Large phones |
| `md` | 768px | Tablets |
| `lg` | 1024px | Small laptops |
| `xl` | 1280px | Desktops |
| `2xl` | 1536px | Large monitors |

```css
/* Tailwind breakpoint usage */
.sidebar {
  @apply hidden md:block;  /* Hidden on mobile, visible on tablet+ */
}

.grid {
  @apply grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4;
}
```

---

## 14.1.2 Layout Patterns

### Mobile Layout
```
┌─────────────────────┐
│  Header + Hamburger │
├─────────────────────┤
│                     │
│   Main Content      │
│   (Full Width)      │
│                     │
├─────────────────────┤
│  Bottom Navigation  │
└─────────────────────┘
```

### Tablet Layout
```
┌─────────────────────────────┐
│  Header                     │
├──────────┬──────────────────┤
│  Sidebar │                  │
│  (Mini)  │  Main Content    │
│          │                  │
└──────────┴──────────────────┘
```

### Desktop Layout
```
┌────────────────────────────────────────┐
│  Header                                │
├────────────┬───────────────────────────┤
│  Sidebar   │  Main Content             │
│  (Full)    │  ┌─────────┬─────────┐   │
│            │  │ Panel 1 │ Panel 2 │   │
│            │  └─────────┴─────────┘   │
└────────────┴───────────────────────────┘
```

---

## 14.1.3 Responsive Sidebar

```typescript
interface ResponsiveSidebar {
  // Mobile: Sheet/Drawer (slides in)
  // Tablet: Collapsed icons only
  // Desktop: Full sidebar with labels
  
  mode: 'mobile' | 'collapsed' | 'expanded';
  isOpen: boolean;
  toggle: () => void;
}

const useSidebar = () => {
  const { width } = useWindowSize();
  
  const mode = useMemo(() => {
    if (width < 768) return 'mobile';
    if (width < 1024) return 'collapsed';
    return 'expanded';
  }, [width]);
  
  // ...
};
```

---

## 14.1.4 Touch Interactions

| Desktop Action | Mobile Equivalent |
|----------------|-------------------|
| Hover tooltip | Long press |
| Right-click menu | Long press menu |
| Drag & drop | Touch drag with haptic |
| Double-click | Double tap |
| Scroll | Swipe |

---

## 14.1.5 Mobile Navigation

```typescript
// Bottom navigation for mobile
const MobileNav = () => (
  <nav className="fixed bottom-0 left-0 right-0 md:hidden">
    <div className="flex justify-around bg-background border-t p-2">
      <NavItem icon={Home} label="Home" href="/" />
      <NavItem icon={Folder} label="Projects" href="/projects" />
      <NavItem icon={Plus} label="New" onClick={openNewDialog} />
      <NavItem icon={Search} label="Search" onClick={openSearch} />
      <NavItem icon={User} label="Profile" href="/settings" />
    </div>
  </nav>
);
```

---

## 14.1.6 Responsive Tables

```typescript
// Desktop: Full table
// Mobile: Card-based list

const ResponsiveTable = <T,>({ data, columns }: Props<T>) => {
  const isMobile = useMediaQuery('(max-width: 768px)');
  
  if (isMobile) {
    return (
      <div className="space-y-4">
        {data.map((row) => (
          <Card key={row.id}>
            {columns.map((col) => (
              <div key={col.key} className="flex justify-between py-1">
                <span className="text-muted-foreground">{col.header}</span>
                <span>{col.render(row)}</span>
              </div>
            ))}
          </Card>
        ))}
      </div>
    );
  }
  
  return <DataTable data={data} columns={columns} />;
};
```

---

## 14.1.7 Editor Responsiveness

| Screen Size | Editor Mode |
|-------------|-------------|
| Mobile (<640px) | Single pane, toggle preview |
| Tablet (640-1024px) | Vertical split (stacked) |
| Desktop (>1024px) | Horizontal split (side-by-side) |

---

## 14.1.8 Performance Considerations

| Optimization | Mobile Benefit |
|--------------|----------------|
| Lazy loading images | Reduced data usage |
| Virtual scrolling | Smooth long lists |
| Debounced search | Reduced API calls |
| Service worker | Offline support |
| Compressed assets | Faster load times |

---

## Related Specs

- [Theme System](../10-theme-system/00-overview.md)
- [Dashboard](../11-dashboard/01-project-dashboard.md)
- [Performance](../19-performance/00-overview.md)
