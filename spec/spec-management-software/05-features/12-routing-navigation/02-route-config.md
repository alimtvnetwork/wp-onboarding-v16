# 12.2 Route Configuration

**Version:** 1.0.0  
**Status:** Planned  
**Last Updated:** 2026-01-28

---

## Overview

Centralized route constants and path utilities extracted to break the circular dependency between Dashboard and Routing specs. This module contains no component dependencies—only pure data and helper functions.

**Cross-References:**
- [Route Definitions](./01-route-definitions.md) - Consumes route config
- [Dashboard](../11-dashboard/00-overview.md) - Consumes route config
- [Breadcrumb System](./01-route-definitions.md#1215-breadcrumb-generation) - Uses path utilities

---

## 12.2.1 Design Rationale

```
BEFORE (Circular):
┌─────────────┐       ┌─────────────┐
│  Dashboard  │──────▶│   Routing   │
│             │◀──────│             │
└─────────────┘       └─────────────┘

AFTER (Resolved):
┌─────────────┐       ┌─────────────┐
│  Dashboard  │       │   Routing   │
└──────┬──────┘       └──────┬──────┘
       │                     │
       └────────┬────────────┘
                ▼
        ┌─────────────┐
        │ Route Config│  (No dependencies)
        └─────────────┘
```

---

## 12.2.2 Route Path Constants

```typescript
// routes/config.ts - Pure constants, no imports

export const ROUTES = {
  // Public routes
  LOGIN: '/login',
  REGISTER: '/register',
  FORGOT_PASSWORD: '/forgot-password',
  
  // Dashboard
  HOME: '/',
  
  // Projects
  PROJECTS: '/projects',
  PROJECT_VIEW: '/projects/:projectId',
  PROJECT_EDITOR: '/projects/:projectId/editor/:fileId',
  PROJECT_HISTORY: '/projects/:projectId/history',
  PROJECT_CONSISTENCY: '/projects/:projectId/consistency',
  PROJECT_KNOWLEDGE: '/projects/:projectId/knowledge',
  
  // Settings
  SETTINGS: '/settings',
  SETTINGS_THEME: '/settings/theme',
  SETTINGS_AI: '/settings/ai',
  SETTINGS_SHORTCUTS: '/settings/shortcuts',
  
  // Error pages
  NOT_FOUND: '/404',
  SERVER_ERROR: '/500',
} as const;

export type RoutePath = typeof ROUTES[keyof typeof ROUTES];
```

---

## 12.2.3 Route Metadata

```typescript
// routes/metadata.ts

import { Home, Folder, FileText, Settings, History, Shield, Brain } from 'lucide-react';

export interface RouteMetadata {
  path: string;
  title: string;
  icon?: LucideIcon;
  requiresAuth: boolean;
  breadcrumbLabel: string | ((params: Record<string, string>) => string);
}

export const ROUTE_METADATA: Record<string, RouteMetadata> = {
  [ROUTES.HOME]: {
    path: ROUTES.HOME,
    title: 'Dashboard',
    icon: Home,
    requiresAuth: true,
    breadcrumbLabel: 'Home',
  },
  [ROUTES.PROJECTS]: {
    path: ROUTES.PROJECTS,
    title: 'Projects',
    icon: Folder,
    requiresAuth: true,
    breadcrumbLabel: 'Projects',
  },
  [ROUTES.PROJECT_VIEW]: {
    path: ROUTES.PROJECT_VIEW,
    title: 'Project',
    icon: FileText,
    requiresAuth: true,
    breadcrumbLabel: (params) => params.projectName || 'Project',
  },
  [ROUTES.PROJECT_HISTORY]: {
    path: ROUTES.PROJECT_HISTORY,
    title: 'History',
    icon: History,
    requiresAuth: true,
    breadcrumbLabel: 'History',
  },
  [ROUTES.PROJECT_CONSISTENCY]: {
    path: ROUTES.PROJECT_CONSISTENCY,
    title: 'Consistency Check',
    icon: Shield,
    requiresAuth: true,
    breadcrumbLabel: 'Consistency',
  },
  [ROUTES.PROJECT_KNOWLEDGE]: {
    path: ROUTES.PROJECT_KNOWLEDGE,
    title: 'Knowledge Base',
    icon: Brain,
    requiresAuth: true,
    breadcrumbLabel: 'Knowledge',
  },
  [ROUTES.SETTINGS]: {
    path: ROUTES.SETTINGS,
    title: 'Settings',
    icon: Settings,
    requiresAuth: true,
    breadcrumbLabel: 'Settings',
  },
};
```

---

## 12.2.4 Path Builder Utilities

```typescript
// routes/pathBuilder.ts - Pure functions, no side effects

/**
 * Build a route path with parameters
 */
export const buildPath = (
  route: string,
  params: Record<string, string>
): string => {
  let path = route;
  for (const [key, value] of Object.entries(params)) {
    path = path.replace(`:${key}`, encodeURIComponent(value));
  }
  return path;
};

// Pre-built path builders for common routes
export const paths = {
  project: (projectId: string) => 
    buildPath(ROUTES.PROJECT_VIEW, { projectId }),
  
  editor: (projectId: string, fileId: string) => 
    buildPath(ROUTES.PROJECT_EDITOR, { projectId, fileId }),
  
  history: (projectId: string) => 
    buildPath(ROUTES.PROJECT_HISTORY, { projectId }),
  
  consistency: (projectId: string) => 
    buildPath(ROUTES.PROJECT_CONSISTENCY, { projectId }),
  
  knowledge: (projectId: string) => 
    buildPath(ROUTES.PROJECT_KNOWLEDGE, { projectId }),
};

// Usage examples:
// paths.project('abc123')           → '/projects/abc123'
// paths.editor('abc123', 'file1')   → '/projects/abc123/editor/file1'
```

---

## 12.2.5 Route Matching Utilities

```typescript
// routes/matching.ts

/**
 * Check if current path matches a route pattern
 */
export const matchRoute = (
  currentPath: string,
  routePattern: string
): boolean => {
  const pattern = routePattern
    .replace(/:[^/]+/g, '[^/]+')
    .replace(/\//g, '\\/');
  return new RegExp(`^${pattern}$`).test(currentPath);
};

/**
 * Extract parameters from a path based on route pattern
 */
export const extractParams = (
  currentPath: string,
  routePattern: string
): Record<string, string> => {
  const paramNames = routePattern.match(/:[^/]+/g)?.map(p => p.slice(1)) || [];
  const pattern = routePattern.replace(/:[^/]+/g, '([^/]+)');
  const match = currentPath.match(new RegExp(`^${pattern}$`));
  
  if (!match) return {};
  
  return paramNames.reduce((acc, name, index) => {
    acc[name] = decodeURIComponent(match[index + 1]);
    return acc;
  }, {} as Record<string, string>);
};

/**
 * Get route metadata for current path
 */
export const getRouteMetadata = (currentPath: string): RouteMetadata | null => {
  for (const [pattern, metadata] of Object.entries(ROUTE_METADATA)) {
    if (matchRoute(currentPath, pattern)) {
      return metadata;
    }
  }
  return null;
};
```

---

## 12.2.6 Public vs Protected Routes

```typescript
// routes/access.ts

export const PUBLIC_ROUTES = [
  ROUTES.LOGIN,
  ROUTES.REGISTER,
  ROUTES.FORGOT_PASSWORD,
] as const;

export const isPublicRoute = (path: string): boolean => {
  return PUBLIC_ROUTES.some(route => matchRoute(path, route));
};

export const isProtectedRoute = (path: string): boolean => {
  return !isPublicRoute(path);
};
```

---

## 12.2.7 Module Exports

```typescript
// routes/index.ts - Barrel export

export { ROUTES, type RoutePath } from './config';
export { ROUTE_METADATA, type RouteMetadata } from './metadata';
export { buildPath, paths } from './pathBuilder';
export { matchRoute, extractParams, getRouteMetadata } from './matching';
export { PUBLIC_ROUTES, isPublicRoute, isProtectedRoute } from './access';
```

---

## 12.2.8 Usage Examples

```typescript
// In Dashboard component
import { ROUTES, paths, ROUTE_METADATA } from '@/routes';

// Navigate to project
navigate(paths.project(project.id));

// Get page title
const metadata = ROUTE_METADATA[ROUTES.HOME];
document.title = metadata.title;

// In Routing component
import { ROUTES, isPublicRoute } from '@/routes';

const ProtectedRoute = ({ children }) => {
  const location = useLocation();
  
  if (isPublicRoute(location.pathname)) {
    return children;
  }
  
  // Auth check...
};
```

---

## Related Specs

- [Route Definitions](./01-route-definitions.md)
- [Dashboard](../11-dashboard/01-project-dashboard.md)
- [State Management](../16-state-management/01-state-architecture.md)
