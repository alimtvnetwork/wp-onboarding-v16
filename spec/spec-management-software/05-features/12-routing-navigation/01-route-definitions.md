# 12.1 Route Definitions

**Version:** 1.0.0  
**Status:** Planned  
**Last Updated:** 2026-01-28

---

## Overview

React Router v6 configuration for protected routes, lazy loading, and navigation guards. Imports route constants from the shared [Route Configuration](./02-route-config.md) module.

**Cross-References:**
- [Route Configuration](./02-route-config.md) - Route constants and path builders
- [Authentication](../01-authentication/01-authentication.md) - Auth guards
- [State Management](../16-state-management/00-overview.md) - Navigation state

---

## 12.1.1 Route Structure

```typescript
const routes = [
  // Public routes
  { path: '/login', element: <LoginPage />, public: true },
  { path: '/register', element: <RegisterPage />, public: true },
  
  // Protected routes (require auth)
  { 
    path: '/', 
    element: <DashboardLayout />,
    children: [
      { index: true, element: <ProjectDashboard /> },
      { path: 'projects', element: <ProjectList /> },
      { path: 'projects/:projectId', element: <ProjectView /> },
      { path: 'projects/:projectId/editor/:fileId', element: <SpecEditor /> },
      { path: 'projects/:projectId/history', element: <HistoryView /> },
      { path: 'projects/:projectId/consistency', element: <ConsistencyDashboard /> },
      { path: 'projects/:projectId/knowledge', element: <KnowledgeManagement /> },
      { path: 'settings', element: <UserSettings /> },
      { path: 'settings/theme', element: <ThemeSettings /> },
      { path: 'settings/ai', element: <AISettings /> },
    ]
  },
  
  // Catch-all
  { path: '*', element: <NotFound /> },
];
```

---

## 12.1.2 Route Table

| Path | Component | Auth | Description |
|------|-----------|------|-------------|
| `/` | ProjectDashboard | ✅ | Home dashboard |
| `/login` | LoginPage | ❌ | Authentication |
| `/register` | RegisterPage | ❌ | New user signup |
| `/projects` | ProjectList | ✅ | All projects |
| `/projects/:id` | ProjectView | ✅ | Single project |
| `/projects/:id/editor/:fileId` | SpecEditor | ✅ | File editor |
| `/projects/:id/history` | HistoryView | ✅ | Snapshots |
| `/projects/:id/consistency` | ConsistencyDashboard | ✅ | Health check |
| `/projects/:id/knowledge` | KnowledgeManagement | ✅ | RAG sources |
| `/settings` | UserSettings | ✅ | User preferences |

---

## 12.1.3 Navigation Guards

```typescript
// Auth guard
const ProtectedRoute = ({ children }: { children: React.ReactNode }) => {
  const { isAuthenticated, isLoading } = useAuth();
  const location = useLocation();

  if (isLoading) {
    return <LoadingSpinner />;
  }

  if (!isAuthenticated) {
    return <Navigate to="/login" state={{ from: location }} replace />;
  }

  return <>{children}</>;
};

// Project access guard
const ProjectGuard = ({ children }: { children: React.ReactNode }) => {
  const { projectId } = useParams();
  const { hasAccess, isLoading } = useProjectAccess(projectId);

  if (isLoading) return <LoadingSpinner />;
  if (!hasAccess) return <Navigate to="/" replace />;

  return <>{children}</>;
};
```

---

## 12.1.4 Lazy Loading

```typescript
// Code splitting for better performance
const SpecEditor = lazy(() => import('@/pages/SpecEditor'));
const ConsistencyDashboard = lazy(() => import('@/pages/ConsistencyDashboard'));
const KnowledgeManagement = lazy(() => import('@/pages/KnowledgeManagement'));

// Wrapped with Suspense
<Suspense fallback={<PageSkeleton />}>
  <SpecEditor />
</Suspense>
```

---

## 12.1.5 Breadcrumb Generation

```typescript
const breadcrumbConfig: Record<string, BreadcrumbItem> = {
  '/': { label: 'Dashboard', icon: Home },
  '/projects': { label: 'Projects', icon: Folder },
  '/projects/:id': { label: ':projectName', icon: FileText },
  '/projects/:id/editor/:fileId': { label: ':fileName', icon: Edit },
  '/settings': { label: 'Settings', icon: Settings },
};

// Auto-generate breadcrumbs from current path
function useBreadcrumbs(): BreadcrumbItem[] {
  const location = useLocation();
  const params = useParams();
  // ... generate breadcrumb trail
}
```

---

## 12.1.6 Navigation Events

```typescript
// Track navigation for analytics
const NavigationTracker = () => {
  const location = useLocation();
  
  useEffect(() => {
    analytics.pageView(location.pathname);
  }, [location]);
  
  return null;
};

// Prevent accidental navigation with unsaved changes
const useNavigationBlock = (hasUnsavedChanges: boolean) => {
  const blocker = useBlocker(hasUnsavedChanges);
  
  useEffect(() => {
    if (blocker.state === 'blocked') {
      // Show confirmation dialog
    }
  }, [blocker]);
};
```

---

## Related Specs

- [Authentication](../01-authentication/01-authentication.md)
- [Dashboard](../11-dashboard/01-project-dashboard.md)
- [State Management](../16-state-management/00-overview.md)
