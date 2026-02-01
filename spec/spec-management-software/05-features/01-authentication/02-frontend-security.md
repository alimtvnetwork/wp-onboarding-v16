# 25. Frontend Security

## Overview

Security measures for the frontend application covering authentication flows, CSRF protection, XSS prevention, and Content Security Policies.

---

## 25.1 Authentication Architecture

### Token Management

```typescript
// lib/auth/tokenManager.ts
interface TokenPair {
  accessToken: string;
  refreshToken: string;
  expiresAt: number;
}

class TokenManager {
  private static readonly ACCESS_TOKEN_KEY = 'auth_access_token';
  private static readonly REFRESH_TOKEN_KEY = 'auth_refresh_token';
  private static readonly EXPIRY_KEY = 'auth_token_expiry';
  
  // Buffer before expiry to trigger refresh (5 minutes)
  private static readonly REFRESH_BUFFER_MS = 5 * 60 * 1000;

  static setTokens(tokens: TokenPair): void {
    // Store in memory for XSS protection
    sessionStorage.setItem(this.ACCESS_TOKEN_KEY, tokens.accessToken);
    
    // Refresh token in httpOnly cookie via API (preferred)
    // Fallback to secure storage if cookies unavailable
    if (!document.cookie.includes('has_refresh_token')) {
      localStorage.setItem(this.REFRESH_TOKEN_KEY, tokens.refreshToken);
    }
    
    localStorage.setItem(this.EXPIRY_KEY, tokens.expiresAt.toString());
  }

  static getAccessToken(): string | null {
    return sessionStorage.getItem(this.ACCESS_TOKEN_KEY);
  }

  static isExpired(): boolean {
    const expiry = localStorage.getItem(this.EXPIRY_KEY);
    if (!expiry) return true;
    
    return Date.now() >= parseInt(expiry) - this.REFRESH_BUFFER_MS;
  }

  static clearTokens(): void {
    sessionStorage.removeItem(this.ACCESS_TOKEN_KEY);
    localStorage.removeItem(this.REFRESH_TOKEN_KEY);
    localStorage.removeItem(this.EXPIRY_KEY);
  }
}
```

### Auth Context

```typescript
// contexts/AuthContext.tsx
interface AuthState {
  user: User | null;
  isAuthenticated: boolean;
  isLoading: boolean;
}

interface AuthContextValue extends AuthState {
  login: (credentials: LoginCredentials) => Promise<void>;
  logout: () => Promise<void>;
  refreshSession: () => Promise<void>;
}

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [state, dispatch] = useReducer(authReducer, initialState);
  const refreshTimeoutRef = useRef<NodeJS.Timeout>();

  // Silent token refresh
  const scheduleRefresh = useCallback((expiresAt: number) => {
    const refreshTime = expiresAt - Date.now() - (5 * 60 * 1000);
    
    if (refreshTime > 0) {
      refreshTimeoutRef.current = setTimeout(async () => {
        try {
          await refreshSession();
        } catch {
          dispatch({ type: 'LOGOUT' });
        }
      }, refreshTime);
    }
  }, []);

  const login = useCallback(async (credentials: LoginCredentials) => {
    dispatch({ type: 'AUTH_START' });
    
    try {
      const response = await authApi.login(credentials);
      TokenManager.setTokens(response.tokens);
      dispatch({ type: 'AUTH_SUCCESS', payload: response.user });
      scheduleRefresh(response.tokens.expiresAt);
    } catch (error) {
      dispatch({ type: 'AUTH_ERROR', payload: error });
      throw error;
    }
  }, [scheduleRefresh]);

  const logout = useCallback(async () => {
    try {
      await authApi.logout();
    } finally {
      TokenManager.clearTokens();
      clearTimeout(refreshTimeoutRef.current);
      dispatch({ type: 'LOGOUT' });
    }
  }, []);

  // Check session on mount
  useEffect(() => {
    const initAuth = async () => {
      const token = TokenManager.getAccessToken();
      if (token && !TokenManager.isExpired()) {
        try {
          const user = await authApi.getCurrentUser();
          dispatch({ type: 'AUTH_SUCCESS', payload: user });
        } catch {
          TokenManager.clearTokens();
          dispatch({ type: 'LOGOUT' });
        }
      } else {
        dispatch({ type: 'AUTH_CHECKED' });
      }
    };

    initAuth();
  }, []);

  return (
    <AuthContext.Provider value={{ ...state, login, logout, refreshSession }}>
      {children}
    </AuthContext.Provider>
  );
}
```

### Protected Routes

```typescript
// components/ProtectedRoute.tsx
interface ProtectedRouteProps {
  children: React.ReactNode;
  requiredRoles?: string[];
  fallback?: React.ReactNode;
}

export function ProtectedRoute({ 
  children, 
  requiredRoles,
  fallback 
}: ProtectedRouteProps) {
  const { isAuthenticated, isLoading, user } = useAuth();
  const location = useLocation();

  if (isLoading) {
    return fallback ?? <LoadingSpinner />;
  }

  if (!isAuthenticated) {
    return <Navigate to="/login" state={{ from: location }} replace />;
  }

  if (requiredRoles && !requiredRoles.some(role => user?.roles.includes(role))) {
    return <Navigate to="/unauthorized" replace />;
  }

  return <>{children}</>;
}
```

---

## 25.2 CSRF Protection

### CSRF Token Management

```typescript
// lib/security/csrf.ts
class CSRFManager {
  private static readonly TOKEN_HEADER = 'X-CSRF-Token';
  private static readonly TOKEN_KEY = 'csrf_token';

  static async getToken(): Promise<string> {
    // Check for existing valid token
    let token = sessionStorage.getItem(this.TOKEN_KEY);
    
    if (!token) {
      // Fetch new token from server
      const response = await fetch('/api/csrf-token', {
        credentials: 'include',
      });
      
      const data = await response.json();
      token = data.token;
      sessionStorage.setItem(this.TOKEN_KEY, token);
    }

    return token;
  }

  static clearToken(): void {
    sessionStorage.removeItem(this.TOKEN_KEY);
  }

  static async attachToRequest(headers: Headers): Promise<void> {
    const token = await this.getToken();
    headers.set(this.TOKEN_HEADER, token);
  }
}
```

### CSRF-Protected Fetch

```typescript
// lib/api/secureFetch.ts
export async function secureFetch(
  url: string,
  options: RequestInit = {}
): Promise<Response> {
  const headers = new Headers(options.headers);
  
  // Add CSRF token for state-changing requests
  if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(options.method || 'GET')) {
    await CSRFManager.attachToRequest(headers);
  }

  // Always include credentials for cookie-based auth
  return fetch(url, {
    ...options,
    headers,
    credentials: 'include',
  });
}
```

### Double Submit Cookie Pattern

```typescript
// Middleware on API client
const csrfMiddleware = async (config: RequestConfig): Promise<RequestConfig> => {
  // Read CSRF token from cookie
  const csrfCookie = document.cookie
    .split('; ')
    .find(row => row.startsWith('XSRF-TOKEN='));
  
  if (csrfCookie) {
    const token = decodeURIComponent(csrfCookie.split('=')[1]);
    config.headers = {
      ...config.headers,
      'X-XSRF-TOKEN': token,
    };
  }

  return config;
};
```

---

## 25.3 XSS Prevention

### Content Sanitization

```typescript
// lib/security/sanitize.ts
import DOMPurify from 'dompurify';

// Configure DOMPurify
DOMPurify.setConfig({
  ALLOWED_TAGS: [
    'p', 'br', 'strong', 'em', 'u', 's', 'code', 'pre',
    'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
    'ul', 'ol', 'li',
    'blockquote',
    'a', 'img',
    'table', 'thead', 'tbody', 'tr', 'th', 'td',
  ],
  ALLOWED_ATTR: [
    'href', 'src', 'alt', 'title', 'class',
    'target', 'rel',
  ],
  ALLOW_DATA_ATTR: false,
  ADD_ATTR: ['target'], // Allow target for links
});

// Hook to sanitize URLs
DOMPurify.addHook('afterSanitizeAttributes', (node) => {
  // Ensure links open in new tab safely
  if (node.tagName === 'A') {
    node.setAttribute('target', '_blank');
    node.setAttribute('rel', 'noopener noreferrer');
  }
  
  // Validate image sources
  if (node.tagName === 'IMG') {
    const src = node.getAttribute('src');
    if (src && !isValidImageUrl(src)) {
      node.removeAttribute('src');
    }
  }
});

export function sanitizeHtml(dirty: string): string {
  return DOMPurify.sanitize(dirty);
}

export function sanitizeMarkdown(markdown: string): string {
  // First convert markdown to HTML, then sanitize
  const html = markdownToHtml(markdown);
  return sanitizeHtml(html);
}

function isValidImageUrl(url: string): boolean {
  try {
    const parsed = new URL(url);
    return ['http:', 'https:', 'data:'].includes(parsed.protocol);
  } catch {
    return false;
  }
}
```

### Safe HTML Component

```typescript
// components/SafeHtml.tsx
interface SafeHtmlProps {
  html: string;
  className?: string;
  as?: keyof JSX.IntrinsicElements;
}

export function SafeHtml({ html, className, as: Component = 'div' }: SafeHtmlProps) {
  const sanitized = useMemo(() => sanitizeHtml(html), [html]);

  return (
    <Component
      className={className}
      dangerouslySetInnerHTML={{ __html: sanitized }}
    />
  );
}
```

### Input Validation

```typescript
// lib/security/validation.ts
import { z } from 'zod';

// Schemas with XSS protection
export const safeStringSchema = z.string()
  .transform(s => s.trim())
  .refine(s => !/<script/i.test(s), 'Invalid characters detected')
  .refine(s => !/javascript:/i.test(s), 'Invalid protocol detected')
  .refine(s => !/on\w+\s*=/i.test(s), 'Invalid event handler detected');

export const urlSchema = z.string().url().refine(url => {
  try {
    const parsed = new URL(url);
    return ['http:', 'https:'].includes(parsed.protocol);
  } catch {
    return false;
  }
}, 'Invalid URL protocol');

export const emailSchema = z.string()
  .email()
  .max(255)
  .transform(s => s.toLowerCase().trim());
```

### React Security Patterns

```typescript
// NEVER do this:
// <div dangerouslySetInnerHTML={{ __html: userInput }} />

// ALWAYS sanitize:
// <SafeHtml html={userInput} />

// For URLs, validate:
function SafeLink({ href, children }: { href: string; children: React.ReactNode }) {
  const isValid = useMemo(() => {
    try {
      const url = new URL(href, window.location.origin);
      return ['http:', 'https:', 'mailto:'].includes(url.protocol);
    } catch {
      return false;
    }
  }, [href]);

  if (!isValid) {
    console.warn('Blocked potentially malicious URL:', href);
    return <span>{children}</span>;
  }

  return (
    <a 
      href={href} 
      target="_blank" 
      rel="noopener noreferrer"
    >
      {children}
    </a>
  );
}
```

---

## 25.4 Content Security Policy

### CSP Configuration

```typescript
// CSP header configuration (set by backend/meta tag)
const contentSecurityPolicy = {
  'default-src': ["'self'"],
  'script-src': [
    "'self'",
    "'strict-dynamic'", // For script loaders
    // No 'unsafe-inline' - use nonces
  ],
  'style-src': [
    "'self'",
    "'unsafe-inline'", // Required for CSS-in-JS
  ],
  'img-src': [
    "'self'",
    'data:',
    'blob:',
    'https:', // Allow HTTPS images
  ],
  'font-src': [
    "'self'",
    'https://fonts.gstatic.com',
  ],
  'connect-src': [
    "'self'",
    'https://api.example.com', // API endpoint
    'wss://ws.example.com',    // WebSocket
  ],
  'frame-ancestors': ["'none'"], // Prevent clickjacking
  'base-uri': ["'self'"],
  'form-action': ["'self'"],
  'object-src': ["'none'"],
  'upgrade-insecure-requests': [],
};

// Generate CSP string
function generateCSP(policy: Record<string, string[]>): string {
  return Object.entries(policy)
    .map(([directive, values]) => {
      if (values.length === 0) return directive;
      return `${directive} ${values.join(' ')}`;
    })
    .join('; ');
}
```

### CSP Meta Tag

```html
<!-- index.html -->
<meta 
  http-equiv="Content-Security-Policy" 
  content="
    default-src 'self';
    script-src 'self';
    style-src 'self' 'unsafe-inline';
    img-src 'self' data: blob: https:;
    font-src 'self' https://fonts.gstatic.com;
    connect-src 'self' https://api.example.com wss://ws.example.com;
    frame-ancestors 'none';
    base-uri 'self';
    form-action 'self';
    object-src 'none';
    upgrade-insecure-requests;
  "
>
```

### CSP Violation Reporting

```typescript
// lib/security/cspReporter.ts
interface CSPViolation {
  documentURI: string;
  violatedDirective: string;
  effectiveDirective: string;
  originalPolicy: string;
  blockedURI: string;
  statusCode: number;
}

export function setupCSPReporting(): void {
  document.addEventListener('securitypolicyviolation', (event) => {
    const violation: CSPViolation = {
      documentURI: event.documentURI,
      violatedDirective: event.violatedDirective,
      effectiveDirective: event.effectiveDirective,
      originalPolicy: event.originalPolicy,
      blockedURI: event.blockedURI,
      statusCode: event.statusCode,
    };

    // Log to monitoring service
    console.error('CSP Violation:', violation);
    
    // Send to backend for tracking
    navigator.sendBeacon('/api/csp-report', JSON.stringify(violation));
  });
}
```

---

## 25.5 Additional Security Headers

### Security Headers (Backend Configuration)

```typescript
// Recommended response headers
const securityHeaders = {
  'Strict-Transport-Security': 'max-age=31536000; includeSubDomains; preload',
  'X-Content-Type-Options': 'nosniff',
  'X-Frame-Options': 'DENY',
  'X-XSS-Protection': '1; mode=block', // Legacy browsers
  'Referrer-Policy': 'strict-origin-when-cross-origin',
  'Permissions-Policy': 'camera=(), microphone=(), geolocation=()',
};
```

### Subresource Integrity

```html
<!-- For external scripts/styles -->
<script 
  src="https://cdn.example.com/lib.js"
  integrity="sha384-abc123..."
  crossorigin="anonymous"
></script>
```

---

## 25.6 Secure Data Handling

### Sensitive Data Masking

```typescript
// lib/security/masking.ts
export function maskEmail(email: string): string {
  const [local, domain] = email.split('@');
  if (!domain) return '***';
  
  const maskedLocal = local.length > 2
    ? `${local[0]}***${local[local.length - 1]}`
    : '***';
  
  return `${maskedLocal}@${domain}`;
}

export function maskToken(token: string): string {
  if (token.length < 8) return '***';
  return `${token.slice(0, 4)}...${token.slice(-4)}`;
}

// Prevent sensitive data in console
if (process.env.NODE_ENV === 'production') {
  const originalLog = console.log;
  console.log = (...args) => {
    const sanitized = args.map(arg => {
      if (typeof arg === 'string') {
        // Mask potential tokens/passwords
        return arg.replace(
          /("?(password|token|secret|key)"?\s*[:=]\s*)"[^"]+"/gi,
          '$1"[REDACTED]"'
        );
      }
      return arg;
    });
    originalLog.apply(console, sanitized);
  };
}
```

### Secure Storage

```typescript
// lib/security/secureStorage.ts
class SecureStorage {
  private static readonly ENCRYPTION_KEY = 'app_storage_key';

  // For non-sensitive data only
  static setItem(key: string, value: string): void {
    try {
      const encoded = btoa(encodeURIComponent(value));
      localStorage.setItem(key, encoded);
    } catch (error) {
      console.error('Storage error:', error);
    }
  }

  static getItem(key: string): string | null {
    try {
      const encoded = localStorage.getItem(key);
      if (!encoded) return null;
      return decodeURIComponent(atob(encoded));
    } catch {
      return null;
    }
  }

  // Clear all stored data on logout
  static clear(): void {
    localStorage.clear();
    sessionStorage.clear();
  }
}
```

---

## 25.7 Security Audit Hooks

```typescript
// hooks/useSecurityAudit.ts
interface SecurityEvent {
  type: 'auth_failure' | 'csrf_error' | 'xss_attempt' | 'suspicious_activity';
  details: Record<string, unknown>;
  timestamp: number;
}

export function useSecurityAudit() {
  const logSecurityEvent = useCallback((event: SecurityEvent) => {
    // Local logging
    console.warn('Security Event:', event);
    
    // Send to backend
    navigator.sendBeacon('/api/security-log', JSON.stringify({
      ...event,
      userAgent: navigator.userAgent,
      url: window.location.href,
    }));
  }, []);

  const checkForSuspiciousActivity = useCallback(() => {
    // Check for rapid failed attempts
    const failedAttempts = parseInt(
      sessionStorage.getItem('failed_auth_attempts') || '0'
    );
    
    if (failedAttempts >= 5) {
      logSecurityEvent({
        type: 'suspicious_activity',
        details: { reason: 'excessive_auth_failures', count: failedAttempts },
        timestamp: Date.now(),
      });
    }
  }, [logSecurityEvent]);

  return { logSecurityEvent, checkForSuspiciousActivity };
}
```

---

## 25.8 Security Testing

```typescript
// tests/security.spec.ts
import { test, expect } from '@playwright/test';

test.describe('Security Tests', () => {
  test('XSS prevention in user input', async ({ page }) => {
    await page.goto('/editor');
    
    const xssPayloads = [
      '<script>alert("xss")</script>',
      '<img src=x onerror=alert("xss")>',
      'javascript:alert("xss")',
      '<svg onload=alert("xss")>',
    ];

    for (const payload of xssPayloads) {
      await page.fill('[data-testid="content-input"]', payload);
      await page.click('[data-testid="save-button"]');
      
      // Verify script tags are not executed
      const alertTriggered = await page.evaluate(() => {
        return (window as any).__xssTriggered === true;
      });
      
      expect(alertTriggered).toBe(false);
    }
  });

  test('CSRF token present on mutations', async ({ page }) => {
    await page.goto('/');
    
    // Intercept POST requests
    const requests: Request[] = [];
    page.on('request', request => {
      if (request.method() === 'POST') {
        requests.push(request);
      }
    });

    // Trigger a mutation
    await page.click('[data-testid="create-button"]');
    await page.fill('[data-testid="name-input"]', 'Test');
    await page.click('[data-testid="submit-button"]');

    // Verify CSRF header present
    const postRequest = requests[0];
    expect(postRequest.headers()['x-csrf-token']).toBeDefined();
  });

  test('secure cookies configuration', async ({ page }) => {
    await page.goto('/login');
    await page.fill('[data-testid="email"]', 'test@example.com');
    await page.fill('[data-testid="password"]', 'password123');
    await page.click('[data-testid="login-button"]');

    const cookies = await page.context().cookies();
    const authCookie = cookies.find(c => c.name === 'auth_session');
    
    if (authCookie) {
      expect(authCookie.secure).toBe(true);
      expect(authCookie.sameSite).toBe('Strict');
    }
  });
});
```

---

## 25.9 Acceptance Criteria

- [ ] Access tokens stored in sessionStorage (XSS mitigation)
- [ ] Refresh tokens in httpOnly cookies when possible
- [ ] CSRF tokens attached to all state-changing requests
- [ ] All user HTML content sanitized with DOMPurify
- [ ] CSP headers configured to prevent inline scripts
- [ ] No sensitive data logged to console in production
- [ ] Security events reported to monitoring
- [ ] Protected routes enforce authentication
- [ ] Role-based access control implemented
- [ ] Input validation with Zod schemas
- [ ] External links use rel="noopener noreferrer"
- [ ] Security headers configured on backend
