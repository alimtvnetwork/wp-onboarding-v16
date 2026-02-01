# 46. Rate Limiting

## Overview
Protect API endpoints from abuse using sliding window rate limiting with configurable thresholds per endpoint category.

> **Last Updated:** 2026-01-26  
> **Database Naming:** PascalCase (e.g., `IpAddressHash`, `CreatedAt`)

---

## 46.1 Rate Limit Categories

### Endpoint Categories
| Category | Window | Max Requests | Lockout Duration |
|----------|--------|--------------|------------------|
| auth_login | 15 min | 5 | 30 min |
| auth_signup | 1 hour | 10 | 1 hour |
| auth_password_reset | 1 hour | 3 | 2 hours |
| extension_request | 24 hours | 5 | 24 hours |
| file_upload | 1 hour | 20 | 1 hour |
| api_general | 1 min | 60 | 5 min |
| secret_key_validate | 15 min | 10 | 30 min |

### Acceptance Criteria:
- [ ] All categories configurable via settings
- [ ] Lockout duration enforced
- [ ] Categories independently tracked

---

## 46.2 Sliding Window Algorithm

### Overview
Uses a sliding window counter to track requests within a time window. More accurate than fixed windows, prevents burst abuse at window boundaries.

### Database Schema

```sql
CREATE TABLE RateLimit (
    Id INTEGER PRIMARY KEY AUTOINCREMENT,
    IpAddressHash VARCHAR(64) NOT NULL,     -- SHA-256 of IP address
    Category VARCHAR(50) NOT NULL,           -- e.g., 'auth_login'
    Endpoint VARCHAR(100) DEFAULT NULL,      -- Specific endpoint (optional)
    RequestCount INTEGER NOT NULL DEFAULT 1,
    WindowStart DATETIME NOT NULL,           -- Window start time
    WindowEnd DATETIME NOT NULL,             -- Window end time
    LastRequestAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE(IpAddressHash, Category, WindowStart)
);

CREATE TABLE RateLockout (
    Id INTEGER PRIMARY KEY AUTOINCREMENT,
    IpAddressHash VARCHAR(64) NOT NULL,
    Category VARCHAR(50) NOT NULL,
    LockedUntil DATETIME NOT NULL,
    Reason VARCHAR(255) NOT NULL,
    ViolationCount INTEGER NOT NULL DEFAULT 1,
    CreatedAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE(IpAddressHash, Category)
);

-- Indexes for fast lookups
CREATE INDEX IX_RateLimit_Lookup ON RateLimit(IpAddressHash, Category, WindowEnd);
CREATE INDEX IX_RateLimit_Cleanup ON RateLimit(WindowEnd);
CREATE INDEX IX_RateLockout_Check ON RateLockout(IpAddressHash, Category, LockedUntil);
```

### Rate Limit Service Class

```php
<?php
// File: src/Services/RateLimitService.php

namespace ExamQuestionsManager\Services;

use ExamQuestionsManager\Consts;

class RateLimitService {
    
    /**
     * Check if request is allowed and record it
     * 
     * @param string $ip Raw IP address (will be hashed)
     * @param string $category Category from config
     * @param string|null $examSlug For exam-scoped limits
     * @return RateLimitResult
     */
    public function checkAndRecord(
        string $ip, 
        string $category, 
        ?string $examSlug = null
    ): RateLimitResult {
        $ipHash = hash('sha256', $ip . Consts::RATE_LIMIT_SALT);
        
        // Check if currently locked out
        if ($this->isLockedOut($ipHash, $category)) {
            $lockout = $this->getLockout($ipHash, $category);
            return RateLimitResult::blocked(
                retryAfter: $lockout->LockedUntil->getTimestamp() - time(),
                reason: 'Too many requests. Please try again later.'
            );
        }
        
        $config = $this->getConfig($category);
        $now = new DateTime();
        $windowStart = $this->getWindowStart($now, $config['window']);
        
        // Count requests in current window
        $count = $this->countRequests($ipHash, $category, $windowStart);
        
        if ($count >= $config['maxRequests']) {
            // Create lockout
            $this->createLockout($ipHash, $category, $config['lockoutDuration']);
            
            return RateLimitResult::blocked(
                retryAfter: $config['lockoutDuration'],
                reason: 'Rate limit exceeded. Please try again later.'
            );
        }
        
        // Record this request
        $this->recordRequest($ipHash, $category, $windowStart, $config['window']);
        
        return RateLimitResult::allowed(
            remaining: $config['maxRequests'] - $count - 1,
            resetAt: $windowStart->getTimestamp() + $config['window']
        );
    }
    
    /**
     * Get rate limit configuration for a category
     */
    private function getConfig(string $category): array {
        $configs = [
            'auth_login' => [
                'window' => 900,        // 15 minutes
                'maxRequests' => 5,
                'lockoutDuration' => 1800, // 30 minutes
            ],
            'auth_signup' => [
                'window' => 3600,       // 1 hour
                'maxRequests' => 10,
                'lockoutDuration' => 3600,
            ],
            'extension_request' => [
                'window' => 86400,      // 24 hours
                'maxRequests' => 5,
                'lockoutDuration' => 86400,
            ],
            'api_general' => [
                'window' => 60,         // 1 minute
                'maxRequests' => 60,
                'lockoutDuration' => 300, // 5 minutes
            ],
        ];
        
        return $configs[$category] ?? $configs['api_general'];
    }
    
    /**
     * Check if IP is currently locked out for category
     */
    private function isLockedOut(string $ipHash, string $category): bool {
        $lockout = $this->rateLockoutRepo->findByIpAndCategory($ipHash, $category);
        
        if ($lockout === null) {
            return false;
        }
        
        $isExpired = $lockout->LockedUntil < new DateTime();
        
        if ($isExpired) {
            $this->rateLockoutRepo->delete($lockout->Id);
            return false;
        }
        
        return true;
    }
    
    /**
     * Create a lockout record
     */
    private function createLockout(
        string $ipHash, 
        string $category, 
        int $duration
    ): void {
        $lockedUntil = new DateTime();
        $lockedUntil->modify("+{$duration} seconds");
        
        $this->rateLockoutRepo->upsert([
            'IpAddressHash' => $ipHash,
            'Category' => $category,
            'LockedUntil' => $lockedUntil,
            'Reason' => 'Rate limit exceeded',
            'ViolationCount' => 1, // Increment on repeat
        ]);
        
        Logger::warning("Rate limit lockout created", [
            'ipHash' => substr($ipHash, 0, 8) . '...',
            'category' => $category,
            'duration' => $duration,
        ]);
    }
    
    /**
     * Clean up expired records (call via cron)
     */
    public function cleanup(): int {
        $deleted = 0;
        
        // Delete expired rate limit records
        $deleted += $this->rateLimitRepo->deleteExpired();
        
        // Delete expired lockouts
        $deleted += $this->rateLockoutRepo->deleteExpired();
        
        return $deleted;
    }
}
```

### Acceptance Criteria:
- [ ] Sliding window prevents burst abuse
- [ ] IP addresses hashed for privacy
- [ ] Lockouts enforced correctly
- [ ] Cleanup removes expired records

---

## 46.3 Response Headers

### Rate Limit Headers
```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 45
X-RateLimit-Reset: 1706299200
Retry-After: 300  (only on 429)
```

### 429 Response
```json
{
    "success": false,
    "error": {
        "code": "ERR_RATE_LIMITED",
        "message": "Too many requests. Please try again later.",
        "retryAfter": 300
    }
}
```

### Acceptance Criteria:
- [ ] Headers on all rate-limited endpoints
- [ ] Retry-After only on 429
- [ ] Reset timestamp in UTC

---

## 46.4 Middleware Implementation

```php
<?php
// File: src/Middleware/RateLimitMiddleware.php

class RateLimitMiddleware {
    
    public function handle(Request $request, string $category): ?Response {
        $rateLimitService = new RateLimitService();
        
        $result = $rateLimitService->checkAndRecord(
            $request->getClientIp(),
            $category,
            $request->get('examSlug')
        );
        
        if ($result->isBlocked()) {
            return Response::json([
                'success' => false,
                'error' => [
                    'code' => 'ERR_RATE_LIMITED',
                    'message' => $result->getReason(),
                    'retryAfter' => $result->getRetryAfter(),
                ]
            ], 429)->withHeaders([
                'Retry-After' => $result->getRetryAfter(),
                'X-RateLimit-Limit' => $result->getLimit(),
                'X-RateLimit-Remaining' => 0,
                'X-RateLimit-Reset' => $result->getResetAt(),
            ]);
        }
        
        // Add headers to successful responses
        $request->attributes->set('rateLimit', [
            'remaining' => $result->getRemaining(),
            'resetAt' => $result->getResetAt(),
        ]);
        
        return null; // Continue to controller
    }
}
```

### Acceptance Criteria:
- [ ] Middleware applied before controller
- [ ] Headers added to all responses
- [ ] Category configurable per route

---

## 46.5 Admin Override

### Whitelist Configuration
```php
// config/rate-limits.php
return [
    'whitelisted_ips' => [
        '127.0.0.1',
        '::1',
        // Add admin IPs here
    ],
    
    'whitelisted_users' => [
        // User IDs exempt from rate limiting
    ],
];
```

### Admin Dashboard
- View current rate limit status per IP
- Clear lockouts manually
- Adjust limits temporarily

### Acceptance Criteria:
- [ ] Whitelisted IPs bypass limits
- [ ] Admin can clear lockouts
- [ ] Audit log for manual overrides

---

## 46.6 Monitoring & Alerts

### Metrics to Track
| Metric | Alert Threshold |
|--------|-----------------|
| Lockouts per hour | > 100 |
| 429 responses per minute | > 50 |
| Unique IPs locked | > 20 |
| Average requests per IP | > 30/min |

### Alert Actions
- Log to error.txt
- Send admin notification
- Optionally block IP at firewall level

### Acceptance Criteria:
- [ ] Metrics logged for analysis
- [ ] Alerts triggered on thresholds
- [ ] Admin notified of anomalies

---

## 46.7 Testing

### Test Cases

```php
// Test: Rate limit enforced
function testRateLimitEnforced(): void {
    $service = new RateLimitService();
    $ip = '192.168.1.100';
    
    // Make 5 requests (limit for auth_login)
    for ($i = 0; $i < 5; $i++) {
        $result = $service->checkAndRecord($ip, 'auth_login');
        $this->assertTrue($result->isAllowed());
    }
    
    // 6th request should be blocked
    $result = $service->checkAndRecord($ip, 'auth_login');
    $this->assertTrue($result->isBlocked());
    $this->assertGreaterThan(0, $result->getRetryAfter());
}

// Test: Lockout expires
function testLockoutExpires(): void {
    $service = new RateLimitService();
    $ip = '192.168.1.101';
    
    // Trigger lockout
    for ($i = 0; $i < 10; $i++) {
        $service->checkAndRecord($ip, 'auth_login');
    }
    
    // Should be locked
    $result = $service->checkAndRecord($ip, 'auth_login');
    $this->assertTrue($result->isBlocked());
    
    // Simulate time passing (mock or database manipulation)
    $this->timeTravel('+31 minutes');
    
    // Should be allowed again
    $result = $service->checkAndRecord($ip, 'auth_login');
    $this->assertTrue($result->isAllowed());
}

// Test: Different categories independent
function testCategoriesIndependent(): void {
    $service = new RateLimitService();
    $ip = '192.168.1.102';
    
    // Hit auth_login limit
    for ($i = 0; $i < 5; $i++) {
        $service->checkAndRecord($ip, 'auth_login');
    }
    
    // auth_login blocked
    $result = $service->checkAndRecord($ip, 'auth_login');
    $this->assertTrue($result->isBlocked());
    
    // api_general should still work
    $result = $service->checkAndRecord($ip, 'api_general');
    $this->assertTrue($result->isAllowed());
}
```

### Acceptance Criteria:
- [ ] Limits enforced per category
- [ ] Lockouts expire correctly
- [ ] Categories independent
- [ ] Cleanup tested
