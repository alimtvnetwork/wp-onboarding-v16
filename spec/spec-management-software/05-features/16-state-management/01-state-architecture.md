# 16.1 State Architecture

**Version:** 2.0.0  
**Status:** Complete  
**Last Updated:** 2026-01-31

---

## Overview

Hybrid state management combining React Query for server state, Zustand for client state, and React Context for component-scoped state.

**Cross-References:**
- [API Client](../15-api-client/00-overview.md) - Server state fetching
- [Theme System](../10-theme-system/00-overview.md) - Theme state
- [Routing](../12-routing-navigation/00-overview.md) - Navigation state

---

## 16.1.1 State Categories

| Category | Tool | Use Case |
|----------|------|----------|
| Server State | React Query | API data, caching |
| Client State | Zustand | UI state, preferences |
| Component State | useState/useReducer | Local component logic |
| Shared Context | React Context | Theme, auth, i18n |

---

## 16.1.2 Zustand Stores

### UI Store
```typescript
interface UIState {
  sidebarOpen: boolean;
  sidebarWidth: number;
  editorSplitRatio: number;
  activePanel: 'editor' | 'preview' | 'split';
  
  // Actions
  toggleSidebar: () => void;
  setSidebarWidth: (width: number) => void;
  setEditorSplitRatio: (ratio: number) => void;
  setActivePanel: (panel: UIState['activePanel']) => void;
}

const useUIStore = create<UIState>()(
  persist(
    (set) => ({
      sidebarOpen: true,
      sidebarWidth: 280,
      editorSplitRatio: 0.5,
      activePanel: 'split',
      
      toggleSidebar: () => set((s) => ({ sidebarOpen: !s.sidebarOpen })),
      setSidebarWidth: (width) => set({ sidebarWidth: width }),
      setEditorSplitRatio: (ratio) => set({ editorSplitRatio: ratio }),
      setActivePanel: (panel) => set({ activePanel: panel }),
    }),
    { name: 'ui-preferences' }
  )
);
```

### Editor Store
```typescript
interface EditorState {
  openFiles: Map<string, FileTab>;
  activeFileId: string | null;
  unsavedChanges: Set<string>;
  
  // Actions
  openFile: (file: FileTab) => void;
  closeFile: (fileId: string) => void;
  setActiveFile: (fileId: string) => void;
  markUnsaved: (fileId: string) => void;
  markSaved: (fileId: string) => void;
}
```

---

## 16.1.3 React Query Patterns

```typescript
// Query keys factory
const queryKeys = {
  projects: {
    all: ['projects'] as const,
    detail: (id: string) => ['projects', id] as const,
    files: (projectId: string) => ['projects', projectId, 'files'] as const,
  },
  files: {
    detail: (id: string) => ['files', id] as const,
    content: (id: string) => ['files', id, 'content'] as const,
  },
  consistency: {
    report: (projectId: string) => ['consistency', projectId] as const,
  },
};

// Optimistic updates
const useUpdateFile = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: updateFileApi,
    onMutate: async (newFile) => {
      await queryClient.cancelQueries({ queryKey: queryKeys.files.detail(newFile.id) });
      
      const previous = queryClient.getQueryData(queryKeys.files.detail(newFile.id));
      queryClient.setQueryData(queryKeys.files.detail(newFile.id), newFile);
      
      return { previous };
    },
    onError: (err, newFile, context) => {
      queryClient.setQueryData(queryKeys.files.detail(newFile.id), context?.previous);
    },
    onSettled: (_, __, newFile) => {
      queryClient.invalidateQueries({ queryKey: queryKeys.files.detail(newFile.id) });
    },
  });
};
```

---

## 16.1.4 Context Providers

```typescript
// Provider hierarchy
const AppProviders = ({ children }: { children: React.ReactNode }) => (
  <QueryClientProvider client={queryClient}>
    <ThemeProvider>
      <AuthProvider>
        <I18nProvider>
          <ToastProvider>
            {children}
          </ToastProvider>
        </I18nProvider>
      </AuthProvider>
    </ThemeProvider>
  </QueryClientProvider>
);
```

---

## 16.1.5 State Synchronization

```typescript
// Sync Zustand with server
const useSyncPreferences = () => {
  const { preferences, setPreferences } = usePreferencesStore();
  const { mutate: savePrefs } = useSavePreferences();
  
  // Debounced sync to server
  const syncToServer = useDebouncedCallback(
    (prefs: Preferences) => savePrefs(prefs),
    2000
  );
  
  useEffect(() => {
    syncToServer(preferences);
  }, [preferences]);
};
```

---

## 16.1.6 DevTools Integration

```typescript
// Enable devtools in development
if (import.meta.env.DEV) {
  // Zustand devtools
  mountStoreDevtool('UIStore', useUIStore);
  
  // React Query devtools (auto-included via ReactQueryDevtools)
}
```

---

## Related Specs

- [API Client](../15-api-client/01-http-client.md)
- [Theme Provider](../10-theme-system/01-theme-provider.md)
- [Authentication](../01-authentication/01-authentication.md)
