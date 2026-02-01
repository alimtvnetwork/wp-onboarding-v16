# Security Patterns

> Version: 1.0.0 | Last Updated: 2025-01-26

## Overview

This document defines security patterns for protecting applications against common vulnerabilities. All implementations must follow defense-in-depth principles.

---

## 1. Input Validation

### 1.1 Validation Principles

```
RULE: Never trust user input
- Validate on both client AND server
- Use allowlists over denylists
- Validate type, length, format, and range
- Reject invalid input early
```

### 1.2 Schema Validation

**TypeScript (Zod)**
```typescript
import { z } from 'zod';

const userSchema = z.object({
  email: z.string()
    .trim()
    .email('Invalid email format')
    .max(255, 'Email too long'),
  password: z.string()
    .min(12, 'Password must be at least 12 characters')
    .max(128, 'Password too long')
    .regex(/[A-Z]/, 'Must contain uppercase')
    .regex(/[a-z]/, 'Must contain lowercase')
    .regex(/[0-9]/, 'Must contain number')
    .regex(/[^A-Za-z0-9]/, 'Must contain special character'),
  age: z.number()
    .int('Age must be whole number')
    .min(13, 'Must be 13 or older')
    .max(150, 'Invalid age'),
});

function validateUser(input: unknown): Result<User> {
  const result = userSchema.safeParse(input);
  if (!result.success) {
    return { success: false, error: result.error.flatten() };
  }
  return { success: true, data: result.data };
}
```

**PHP**
```php
class UserValidator {
    private const EMAIL_MAX_LENGTH = 255;
    private const PASSWORD_MIN_LENGTH = 12;
    private const PASSWORD_MAX_LENGTH = 128;
    
    public function validate(array $input): ValidationResult {
        $errors = [];
        
        // Email validation
        $email = trim($input['email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'][] = 'Invalid email format';
        }
        if (strlen($email) > self::EMAIL_MAX_LENGTH) {
            $errors['email'][] = 'Email too long';
        }
        
        // Password validation
        $password = $input['password'] ?? '';
        if (strlen($password) < self::PASSWORD_MIN_LENGTH) {
            $errors['password'][] = 'Password must be at least 12 characters';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors['password'][] = 'Must contain uppercase';
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errors['password'][] = 'Must contain lowercase';
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errors['password'][] = 'Must contain number';
        }
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors['password'][] = 'Must contain special character';
        }
        
        return new ValidationResult(empty($errors), $errors);
    }
}
```

**Python**
```python
from pydantic import BaseModel, EmailStr, validator, constr
from typing import Optional

class UserInput(BaseModel):
    email: EmailStr
    password: constr(min_length=12, max_length=128)
    age: int
    
    @validator('password')
    def password_complexity(cls, v):
        if not any(c.isupper() for c in v):
            raise ValueError('Must contain uppercase')
        if not any(c.islower() for c in v):
            raise ValueError('Must contain lowercase')
        if not any(c.isdigit() for c in v):
            raise ValueError('Must contain number')
        if not any(not c.isalnum() for c in v):
            raise ValueError('Must contain special character')
        return v
    
    @validator('age')
    def age_range(cls, v):
        if v < 13:
            raise ValueError('Must be 13 or older')
        if v > 150:
            raise ValueError('Invalid age')
        return v
```

---

## 2. Output Encoding

### 2.1 Context-Aware Encoding

```
RULE: Encode output based on context
- HTML context: HTML entity encoding
- JavaScript context: JavaScript encoding
- URL context: URL encoding
- CSS context: CSS encoding
- SQL context: Parameterized queries (never encode manually)
```

### 2.2 HTML Encoding

**TypeScript**
```typescript
const HTML_ENTITIES: Record<string, string> = {
  '&': '&amp;',
  '<': '&lt;',
  '>': '&gt;',
  '"': '&quot;',
  "'": '&#x27;',
  '/': '&#x2F;',
};

function encodeHtml(input: string): string {
  return input.replace(/[&<>"'/]/g, (char) => HTML_ENTITIES[char]);
}

function encodeForUrl(input: string): string {
  return encodeURIComponent(input);
}

function encodeForJavaScript(input: string): string {
  return JSON.stringify(input).slice(1, -1);
}
```

**PHP**
```php
class OutputEncoder {
    public static function html(string $input): string {
        return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    public static function url(string $input): string {
        return rawurlencode($input);
    }
    
    public static function javascript(string $input): string {
        return json_encode($input, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    }
    
    public static function css(string $input): string {
        // Only allow alphanumeric and safe characters
        return preg_replace('/[^a-zA-Z0-9_-]/', '', $input);
    }
}
```

---

## 3. Authentication Security

### 3.1 Password Hashing

```
RULE: Use modern password hashing algorithms
- Argon2id (preferred)
- bcrypt (acceptable, cost >= 12)
- NEVER: MD5, SHA1, SHA256 alone
```

**PHP**
```php
class PasswordService {
    private const ARGON2_OPTIONS = [
        'memory_cost' => 65536,  // 64MB
        'time_cost' => 4,
        'threads' => 3,
    ];
    
    public function hash(string $password): string {
        return password_hash($password, PASSWORD_ARGON2ID, self::ARGON2_OPTIONS);
    }
    
    public function verify(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }
    
    public function needsRehash(string $hash): bool {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID, self::ARGON2_OPTIONS);
    }
}
```

**Python**
```python
from argon2 import PasswordHasher
from argon2.exceptions import VerifyMismatchError

class PasswordService:
    def __init__(self):
        self.hasher = PasswordHasher(
            time_cost=4,
            memory_cost=65536,
            parallelism=3,
        )
    
    def hash(self, password: str) -> str:
        return self.hasher.hash(password)
    
    def verify(self, password: str, hash: str) -> bool:
        try:
            self.hasher.verify(hash, password)
            return True
        except VerifyMismatchError:
            return False
    
    def needs_rehash(self, hash: str) -> bool:
        return self.hasher.check_needs_rehash(hash)
```

### 3.2 Session Security

```
RULE: Secure session configuration
- Use secure, httpOnly, sameSite cookies
- Regenerate session ID on privilege change
- Implement session timeout
- Store minimal data in session
```

**TypeScript**
```typescript
interface SessionConfig {
  name: string;
  secret: string;
  maxAge: number;
  secure: boolean;
  httpOnly: boolean;
  sameSite: 'strict' | 'lax' | 'none';
}

const SESSION_CONFIG: SessionConfig = {
  name: '__Host-session',  // Host prefix for extra security
  secret: process.env.SESSION_SECRET!,
  maxAge: 3600,  // 1 hour
  secure: true,
  httpOnly: true,
  sameSite: 'strict',
};

class SessionManager {
  regenerateSession(session: Session): void {
    const oldData = { ...session.data };
    session.destroy();
    session.create();
    session.data = oldData;
  }
  
  validateSession(session: Session): boolean {
    const now = Date.now();
    const isExpired = session.createdAt + SESSION_CONFIG.maxAge * 1000 < now;
    const isInactive = session.lastActivity + 1800000 < now; // 30 min idle
    
    return !isExpired && !isInactive;
  }
}
```

---

## 4. Authorization Patterns

### 4.1 Role-Based Access Control (RBAC)

```
RULE: Roles stored in separate table
- NEVER store roles on user/profile table
- Use security definer functions to check roles
- Implement least privilege principle
```

**SQL (Supabase/PostgreSQL)**
```sql
-- Role enum
CREATE TYPE public.app_role AS ENUM ('admin', 'moderator', 'user');

-- Roles table
CREATE TABLE public.user_roles (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID REFERENCES auth.users(id) ON DELETE CASCADE NOT NULL,
    role app_role NOT NULL,
    granted_at TIMESTAMPTZ DEFAULT now(),
    granted_by UUID REFERENCES auth.users(id),
    UNIQUE (user_id, role)
);

-- Enable RLS
ALTER TABLE public.user_roles ENABLE ROW LEVEL SECURITY;

-- Security definer function (bypasses RLS safely)
CREATE OR REPLACE FUNCTION public.has_role(_user_id UUID, _role app_role)
RETURNS BOOLEAN
LANGUAGE sql
STABLE
SECURITY DEFINER
SET search_path = public
AS $$
  SELECT EXISTS (
    SELECT 1
    FROM public.user_roles
    WHERE user_id = _user_id
      AND role = _role
  )
$$;

-- RLS policy using function
CREATE POLICY "Admins can manage roles"
ON public.user_roles
FOR ALL
TO authenticated
USING (public.has_role(auth.uid(), 'admin'));
```

### 4.2 Permission Checking

**TypeScript**
```typescript
type Permission = 'read' | 'write' | 'delete' | 'admin';
type Resource = 'users' | 'posts' | 'settings';

interface PermissionMatrix {
  [role: string]: {
    [resource in Resource]?: Permission[];
  };
}

const PERMISSIONS: PermissionMatrix = {
  admin: {
    users: ['read', 'write', 'delete', 'admin'],
    posts: ['read', 'write', 'delete', 'admin'],
    settings: ['read', 'write', 'admin'],
  },
  moderator: {
    users: ['read'],
    posts: ['read', 'write', 'delete'],
  },
  user: {
    posts: ['read', 'write'],
  },
};

function hasPermission(
  userRole: string,
  resource: Resource,
  permission: Permission
): boolean {
  const rolePermissions = PERMISSIONS[userRole]?.[resource] ?? [];
  return rolePermissions.includes(permission);
}

function requirePermission(
  userRole: string,
  resource: Resource,
  permission: Permission
): void {
  if (!hasPermission(userRole, resource, permission)) {
    throw new ForbiddenError(
      `Permission denied: ${permission} on ${resource}`
    );
  }
}
```

---

## 5. SQL Injection Prevention

### 5.1 Parameterized Queries

```
RULE: NEVER concatenate user input into SQL
- Use parameterized queries / prepared statements
- Use ORM with proper escaping
- Validate identifiers separately
```

**TypeScript**
```typescript
// ✓ CORRECT: Parameterized query
async function getUserById(id: string): Promise<User | null> {
  const { data, error } = await supabase
    .from('users')
    .select('*')
    .eq('id', id)
    .single();
  
  return data;
}

// ✓ CORRECT: Raw query with parameters
async function searchUsers(term: string): Promise<User[]> {
  const { data } = await supabase.rpc('search_users', {
    search_term: term,
  });
  return data;
}

// ✗ WRONG: String concatenation
async function getUserUnsafe(id: string): Promise<User> {
  // NEVER DO THIS
  const query = `SELECT * FROM users WHERE id = '${id}'`;
}
```

**PHP**
```php
class UserRepository {
    // ✓ CORRECT: Prepared statement
    public function findById(string $id): ?User {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetchObject(User::class) ?: null;
    }
    
    // ✓ CORRECT: Query builder
    public function findByEmail(string $email): ?User {
        return $this->orm
            ->table('users')
            ->where('email', '=', $email)
            ->first();
    }
    
    // For dynamic column names (validate against allowlist)
    private const ALLOWED_COLUMNS = ['name', 'email', 'created_at'];
    
    public function findOrderedBy(string $column, string $direction): array {
        if (!in_array($column, self::ALLOWED_COLUMNS, true)) {
            throw new InvalidArgumentException('Invalid column');
        }
        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            throw new InvalidArgumentException('Invalid direction');
        }
        
        return $this->orm
            ->table('users')
            ->orderBy($column, $direction)
            ->get();
    }
}
```

---

## 6. Cross-Site Scripting (XSS) Prevention

### 6.1 React/TypeScript Patterns

```typescript
// ✓ SAFE: React auto-escapes
function UserName({ name }: { name: string }) {
  return <span>{name}</span>;
}

// ⚠ DANGEROUS: Only use with sanitized content
function RichContent({ html }: { html: string }) {
  // Must sanitize first!
  const sanitized = DOMPurify.sanitize(html, {
    ALLOWED_TAGS: ['b', 'i', 'em', 'strong', 'a', 'p'],
    ALLOWED_ATTR: ['href'],
  });
  
  return <div dangerouslySetInnerHTML={{ __html: sanitized }} />;
}

// ✓ SAFE: URL validation
function ExternalLink({ url, children }: { url: string; children: React.ReactNode }) {
  const isValidUrl = /^https?:\/\//.test(url);
  
  if (!isValidUrl) {
    return <span>{children}</span>;
  }
  
  return (
    <a 
      href={url} 
      rel="noopener noreferrer" 
      target="_blank"
    >
      {children}
    </a>
  );
}
```

---

## 7. CSRF Protection

### 7.1 Token-Based Protection

**TypeScript**
```typescript
import crypto from 'crypto';

class CSRFProtection {
  private tokenLength = 32;
  
  generateToken(): string {
    return crypto.randomBytes(this.tokenLength).toString('hex');
  }
  
  validateToken(sessionToken: string, requestToken: string): boolean {
    if (!sessionToken || !requestToken) {
      return false;
    }
    // Timing-safe comparison
    return crypto.timingSafeEqual(
      Buffer.from(sessionToken),
      Buffer.from(requestToken)
    );
  }
}

// Middleware
function csrfMiddleware(req: Request, res: Response, next: NextFunction) {
  // Skip for safe methods
  if (['GET', 'HEAD', 'OPTIONS'].includes(req.method)) {
    return next();
  }
  
  const sessionToken = req.session.csrfToken;
  const requestToken = req.headers['x-csrf-token'] || req.body._csrf;
  
  if (!csrf.validateToken(sessionToken, requestToken)) {
    return res.status(403).json({ error: 'Invalid CSRF token' });
  }
  
  next();
}
```

---

## 8. Rate Limiting

### 8.1 Sliding Window Rate Limiter

**TypeScript**
```typescript
interface RateLimitConfig {
  windowMs: number;
  maxRequests: number;
  keyPrefix: string;
}

class RateLimiter {
  constructor(
    private redis: Redis,
    private config: RateLimitConfig
  ) {}
  
  async isAllowed(key: string): Promise<{
    allowed: boolean;
    remaining: number;
    resetAt: number;
  }> {
    const fullKey = `${this.config.keyPrefix}:${key}`;
    const now = Date.now();
    const windowStart = now - this.config.windowMs;
    
    // Remove old entries and count current
    const pipeline = this.redis.pipeline();
    pipeline.zremrangebyscore(fullKey, 0, windowStart);
    pipeline.zcard(fullKey);
    pipeline.zadd(fullKey, now, `${now}:${crypto.randomUUID()}`);
    pipeline.expire(fullKey, Math.ceil(this.config.windowMs / 1000));
    
    const results = await pipeline.exec();
    const currentCount = results[1][1] as number;
    
    return {
      allowed: currentCount < this.config.maxRequests,
      remaining: Math.max(0, this.config.maxRequests - currentCount - 1),
      resetAt: now + this.config.windowMs,
    };
  }
}

// Usage
const authLimiter = new RateLimiter(redis, {
  windowMs: 15 * 60 * 1000,  // 15 minutes
  maxRequests: 5,
  keyPrefix: 'rl:auth',
});
```

---

## 9. Secrets Management

### 9.1 Environment Variables

```
RULE: Never commit secrets
- Use environment variables
- Rotate secrets regularly
- Use secret management services in production
- Different secrets per environment
```

**TypeScript**
```typescript
import { z } from 'zod';

const envSchema = z.object({
  NODE_ENV: z.enum(['development', 'staging', 'production']),
  DATABASE_URL: z.string().url(),
  JWT_SECRET: z.string().min(32),
  API_KEY: z.string().min(16),
  ENCRYPTION_KEY: z.string().length(64),  // 32 bytes hex
});

function loadEnv(): z.infer<typeof envSchema> {
  const result = envSchema.safeParse(process.env);
  
  if (!result.success) {
    console.error('Invalid environment variables:', result.error.flatten());
    process.exit(1);
  }
  
  return result.data;
}

export const env = loadEnv();
```

---

## 10. Security Headers

### 10.1 HTTP Security Headers

```typescript
const SECURITY_HEADERS = {
  'Strict-Transport-Security': 'max-age=31536000; includeSubDomains; preload',
  'X-Content-Type-Options': 'nosniff',
  'X-Frame-Options': 'DENY',
  'X-XSS-Protection': '1; mode=block',
  'Referrer-Policy': 'strict-origin-when-cross-origin',
  'Permissions-Policy': 'camera=(), microphone=(), geolocation=()',
  'Content-Security-Policy': [
    "default-src 'self'",
    "script-src 'self' 'unsafe-inline'",
    "style-src 'self' 'unsafe-inline'",
    "img-src 'self' data: https:",
    "font-src 'self'",
    "connect-src 'self' https://api.example.com",
    "frame-ancestors 'none'",
    "base-uri 'self'",
    "form-action 'self'",
  ].join('; '),
};

function securityHeadersMiddleware(
  req: Request, 
  res: Response, 
  next: NextFunction
) {
  Object.entries(SECURITY_HEADERS).forEach(([header, value]) => {
    res.setHeader(header, value);
  });
  next();
}
```

---

## 11. OWASP Top 10 (2021) Mapping

This section maps the OWASP Top 10 vulnerabilities to the mitigation patterns defined in this document.

### Quick Reference Matrix

| OWASP ID | Vulnerability | Section | Mitigation Strategy |
|----------|---------------|---------|---------------------|
| A01:2021 | Broken Access Control | §4 | RBAC with security definer functions, RLS policies |
| A02:2021 | Cryptographic Failures | §3.1, §9 | Argon2id hashing, AES-256-GCM, env-based secrets |
| A03:2021 | Injection | §1, §5 | Zod/Pydantic schemas, parameterized queries |
| A04:2021 | Insecure Design | §4.2 | Permission matrices, defense-in-depth |
| A05:2021 | Security Misconfiguration | §9, §10 | Env validation, security headers |
| A06:2021 | Vulnerable Components | — | Dependency scanning (npm audit, Snyk) |
| A07:2021 | Auth Failures | §3 | Secure sessions, password policies |
| A08:2021 | Data Integrity Failures | §7 | CSRF tokens, HMAC verification |
| A09:2021 | Logging Failures | §Cross-ref | Security event logging (see 03-logging-system.md) |
| A10:2021 | SSRF | §1.2, §6.1 | URL allowlist validation, protocol checks |

### Detailed Mitigations

#### A01:2021 - Broken Access Control
**Risk:** Users acting outside intended permissions, accessing other users' data.

**Mitigations:**
- ✅ RBAC with roles in separate table (§4.1)
- ✅ Security definer functions for role checks
- ✅ Row Level Security (RLS) on all tables
- ✅ Permission checks at API layer AND database layer

```sql
-- Example: Multi-layer access control
CREATE POLICY "Users can only view own data"
ON public.user_data
FOR SELECT
TO authenticated
USING (user_id = auth.uid() OR public.has_role(auth.uid(), 'admin'));
```

#### A02:2021 - Cryptographic Failures
**Risk:** Exposure of sensitive data due to weak or missing encryption.

**Mitigations:**
- ✅ Argon2id for password hashing (§3.1)
- ✅ AES-256-GCM for data encryption at rest
- ✅ TLS 1.3 for data in transit
- ✅ Secure random for tokens/keys (crypto.randomBytes)

```typescript
// Encryption example
import { createCipheriv, randomBytes } from 'crypto';

function encrypt(plaintext: string, key: Buffer): string {
  const iv = randomBytes(12);  // 96 bits for GCM
  const cipher = createCipheriv('aes-256-gcm', key, iv);
  const encrypted = Buffer.concat([cipher.update(plaintext, 'utf8'), cipher.final()]);
  const tag = cipher.getAuthTag();
  return Buffer.concat([iv, tag, encrypted]).toString('base64');
}
```

#### A03:2021 - Injection
**Risk:** SQL, NoSQL, OS, or LDAP injection through untrusted data.

**Mitigations:**
- ✅ Schema validation with Zod/Pydantic (§1.2)
- ✅ Parameterized queries only (§5.1)
- ✅ ORM usage with proper escaping
- ✅ Allowlist validation for dynamic identifiers

#### A04:2021 - Insecure Design
**Risk:** Missing or ineffective security controls in architecture.

**Mitigations:**
- ✅ Permission matrices with explicit deny-by-default (§4.2)
- ✅ Defense in depth (validate at every layer)
- ✅ Threat modeling during design phase
- ✅ Secure defaults in all configurations

#### A05:2021 - Security Misconfiguration
**Risk:** Insecure default configs, incomplete setups, verbose errors.

**Mitigations:**
- ✅ Environment variable validation with Zod (§9.1)
- ✅ Security headers configured (§10.1)
- ✅ Error messages sanitized (no stack traces in production)
- ✅ Debug modes disabled in production

```typescript
// Production error sanitization
function sanitizeError(error: Error, env: string): ApiError {
  if (env === 'production') {
    return { code: 'ERR_INTERNAL', message: 'An error occurred' };
  }
  return { code: 'ERR_INTERNAL', message: error.message, stack: error.stack };
}
```

#### A06:2021 - Vulnerable and Outdated Components
**Risk:** Using components with known vulnerabilities.

**Mitigations:**
- ✅ Regular dependency audits (`npm audit`, `pip-audit`)
- ✅ Automated vulnerability scanning (Snyk, Dependabot)
- ✅ Lock files committed (package-lock.json, Pipfile.lock)
- ✅ Quarterly dependency update reviews

```bash
# Audit commands
npm audit --audit-level=high
pip-audit --strict
```

#### A07:2021 - Identification and Authentication Failures
**Risk:** Session hijacking, credential stuffing, weak passwords.

**Mitigations:**
- ✅ Secure session configuration (§3.2)
- ✅ Password complexity requirements (§1.2)
- ✅ Rate limiting on auth endpoints (§8)
- ✅ Session regeneration on privilege change

#### A08:2021 - Software and Data Integrity Failures
**Risk:** Code/data that doesn't verify integrity, supply chain attacks.

**Mitigations:**
- ✅ CSRF tokens for state-changing requests (§7)
- ✅ HMAC verification for webhooks/callbacks
- ✅ Subresource Integrity (SRI) for external scripts
- ✅ Signed commits in version control

```html
<!-- SRI example -->
<script 
  src="https://cdn.example.com/lib.js"
  integrity="sha384-oqVuAfXRKap7fdgcCY5uykM6+R9GqQ8K/..."
  crossorigin="anonymous"
></script>
```

#### A09:2021 - Security Logging and Monitoring Failures
**Risk:** Insufficient logging, detection, and incident response.

**Mitigations:**
- ✅ Security event logging (see [03-logging-system.md](./03-logging-system.md))
- ✅ Failed auth attempts logged with context
- ✅ Audit trail for sensitive operations
- ✅ Real-time alerting for anomalies

```typescript
// Security event logging
logger.security('AUTH_FAILED', {
  email: maskedEmail,
  ip: request.ip,
  userAgent: request.headers['user-agent'],
  attempt: attemptCount,
});
```

#### A10:2021 - Server-Side Request Forgery (SSRF)
**Risk:** Server fetching attacker-controlled URLs to access internal services.

**Mitigations:**
- ✅ URL allowlist validation (§1.2, §6.1)
- ✅ Protocol validation (https only)
- ✅ Block private IP ranges
- ✅ Disable redirects or validate redirect targets

```typescript
const ALLOWED_DOMAINS = ['api.trusted.com', 'cdn.example.com'];
const BLOCKED_IP_RANGES = [
  /^10\./,
  /^172\.(1[6-9]|2[0-9]|3[01])\./,
  /^192\.168\./,
  /^127\./,
  /^169\.254\./,
];

function validateUrl(url: string): boolean {
  try {
    const parsed = new URL(url);
    
    // Protocol check
    if (parsed.protocol !== 'https:') return false;
    
    // Domain allowlist
    if (!ALLOWED_DOMAINS.includes(parsed.hostname)) return false;
    
    // IP range check (resolve DNS first in production)
    for (const range of BLOCKED_IP_RANGES) {
      if (range.test(parsed.hostname)) return false;
    }
    
    return true;
  } catch {
    return false;
  }
}
```

---

## Security Checklist

| Category | Requirement | Priority | OWASP |
|----------|-------------|----------|-------|
| Input | Schema validation on all inputs | Critical | A03 |
| Output | Context-aware encoding | Critical | A03 |
| Auth | Argon2id password hashing | Critical | A02 |
| Auth | Secure session configuration | Critical | A07 |
| AuthZ | RBAC with separate roles table | Critical | A01 |
| SQL | Parameterized queries only | Critical | A03 |
| XSS | React auto-escape, DOMPurify for HTML | Critical | A03 |
| CSRF | Token validation on mutations | High | A08 |
| Rate Limit | IP + user-based limiting | High | A07 |
| Secrets | Environment variables, no commits | Critical | A02 |
| Headers | Security headers configured | High | A05 |
| Logging | Security events logged | High | A09 |
| Dependencies | Regular vulnerability scanning | High | A06 |
| SSRF | URL allowlist validation | High | A10 |

---

## Cross-References

- [01-coding-standards-foundation.md](../01-foundation/01-coding-standards-foundation.md) - Code organization
- [02-error-management-foundation.md](../01-foundation/02-error-management-foundation.md) - Error handling
- [01-logging-system-systems.md](../02-systems/01-logging-system-systems.md) - Security event logging
- [03-api-conventions-quality.md](../03-quality/03-api-conventions-quality.md) - API authentication
