# 15.1 HTTP Client

**Version:** 1.0.0  
**Status:** Planned  
**Last Updated:** 2026-01-28

---

## Overview

Centralized HTTP client configuration with interceptors, error handling, authentication token management, and request/response transformation.

**Cross-References:**
- [Authentication](../01-authentication/01-authentication.md) - Token management
- [Error UI](../13-error-ui/00-overview.md) - Error display
- [State Management](../16-state-management/00-overview.md) - Cache integration

---

## 15.1.1 Client Architecture

```typescript
// Base client configuration
const apiClient = axios.create({
  baseURL: import.meta.env.VITE_API_URL,
  timeout: 30000,
  headers: {
    'Content-Type': 'application/json',
  },
});
```

---

## 15.1.2 Request Interceptors

```typescript
// Auth token injection
apiClient.interceptors.request.use((config) => {
  const token = getAuthToken();
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Request logging (development)
apiClient.interceptors.request.use((config) => {
  if (import.meta.env.DEV) {
    console.log(`[API] ${config.method?.toUpperCase()} ${config.url}`);
  }
  return config;
});
```

---

## 15.1.3 Response Interceptors

```typescript
// Error transformation
apiClient.interceptors.response.use(
  (response) => response,
  async (error) => {
    const { response, config } = error;
    
    // Handle 401 - Token expired
    if (response?.status === 401) {
      const refreshed = await refreshToken();
      if (refreshed) {
        return apiClient(config); // Retry request
      }
      // Redirect to login
      window.location.href = '/login';
    }
    
    // Transform to AppError
    throw new AppError({
      code: response?.data?.code || 'UNKNOWN_ERROR',
      message: response?.data?.message || 'An error occurred',
      status: response?.status || 500,
      details: response?.data?.details,
    });
  }
);
```

---

## 15.1.4 React Query Integration

```typescript
// Query client configuration
const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 5 * 60 * 1000,     // 5 minutes
      cacheTime: 30 * 60 * 1000,    // 30 minutes
      retry: 3,
      retryDelay: (attemptIndex) => Math.min(1000 * 2 ** attemptIndex, 30000),
      refetchOnWindowFocus: false,
    },
    mutations: {
      retry: 1,
    },
  },
});

// Custom hooks pattern
export const useProjects = () => {
  return useQuery({
    queryKey: ['projects'],
    queryFn: () => apiClient.get('/projects').then(r => r.data),
  });
};

export const useCreateProject = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (data: CreateProjectDto) => 
      apiClient.post('/projects', data).then(r => r.data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['projects'] });
    },
  });
};
```

---

## 15.1.5 API Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/auth/login` | POST | User authentication |
| `/auth/refresh` | POST | Token refresh |
| `/projects` | GET | List projects |
| `/projects` | POST | Create project |
| `/projects/:id` | GET | Get project |
| `/projects/:id` | PUT | Update project |
| `/projects/:id` | DELETE | Delete project |
| `/projects/:id/files` | GET | List files |
| `/projects/:id/files/:fileId` | GET | Get file |
| `/projects/:id/files/:fileId` | PUT | Update file |
| `/projects/:id/snapshots` | GET | List snapshots |
| `/projects/:id/consistency` | GET | Get health report |

---

## 15.1.6 Request Utilities

```typescript
// Cancellable request
const useApiRequest = <T>(queryFn: () => Promise<T>) => {
  const abortController = useRef<AbortController>();
  
  const execute = useCallback(async () => {
    abortController.current?.abort();
    abortController.current = new AbortController();
    
    return queryFn();
  }, [queryFn]);
  
  useEffect(() => {
    return () => abortController.current?.abort();
  }, []);
  
  return { execute };
};

// Retry with backoff
const retryWithBackoff = async <T>(
  fn: () => Promise<T>,
  maxRetries = 3
): Promise<T> => {
  for (let i = 0; i < maxRetries; i++) {
    try {
      return await fn();
    } catch (error) {
      if (i === maxRetries - 1) throw error;
      await sleep(Math.pow(2, i) * 1000);
    }
  }
  throw new Error('Max retries exceeded');
};
```

---

## 15.1.7 File Upload Handling

```typescript
const uploadFile = async (
  projectId: string,
  file: File,
  onProgress?: (progress: number) => void
) => {
  const formData = new FormData();
  formData.append('file', file);
  
  return apiClient.post(`/projects/${projectId}/files`, formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
    onUploadProgress: (event) => {
      if (event.total) {
        onProgress?.(Math.round((event.loaded * 100) / event.total));
      }
    },
  });
};
```

---

## Related Specs

- [Authentication](../01-authentication/01-authentication.md)
- [State Management](../16-state-management/00-overview.md)
- [Error Management](../../06-error-management/00-overview.md)
