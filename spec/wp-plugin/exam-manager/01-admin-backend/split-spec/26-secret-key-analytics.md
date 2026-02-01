# 24 - Secret Key Analytics [OPTIONAL]

> **Phase:** Wiki & Keys  
> **Dependencies:** `21-secret-key-service.md`  
> **Estimated Time:** 4-6 hours  
> **Status:** ⚠️ OPTIONAL - Can be skipped without affecting core functionality

---

## 📋 Scope

Enhanced analytics tracking for secret key access:
- IP address tracking (hashed for privacy)
- GeoIP location lookup (country/city)
- Session duration tracking
- Referrer analysis
- Tracking cookies for return visitors
- Analytics dashboard with visualizations

**If this spec is skipped:**
- Basic view counting still works (from spec 21)
- Analytics columns remain NULL in database
- Admin UI hides analytics features

---

## 🔧 Analytics Service

**File:** `src/Services/SecretKeyAnalyticsService.php`

```php
<?php
namespace ExamQuestionsManager\Services;

use ExamQuestionsManager\ORM\Models\SecretKeyAccess;
use ExamQuestionsManager\ORM\Models\SecretKey;
use ExamQuestionsManager\Utils\Logger;

class SecretKeyAnalyticsService {
    /**
     * Track access with full analytics
     */
    public static function trackAccess(SecretKey $key): SecretKeyAccess {
        $ipAddress = self::getClientIp();
        $ipHash = hash('sha256', $ipAddress . 'secret_salt');
        
        // Check if unique visitor
        $isNewVisitor = !SecretKeyAccess::where('secretKeyId', $key->id)
            ->where('ipAddressHash', $ipHash)
            ->exists();
        
        // Get/set tracking cookie
        $cookieId = self::handleTrackingCookie($key->id);
        
        // GeoIP lookup (optional)
        $geoData = self::lookupGeoIp($ipAddress);
        
        // Create access record
        $access = SecretKeyAccess::create([
            'secretKeyId' => $key->id,
            'ipAddress' => self::partiallyMaskIp($ipAddress),
            'ipAddressHash' => $ipHash,
            'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'referrer' => $_SERVER['HTTP_REFERER'] ?? null,
            'cookieId' => $cookieId,
            'countryCode' => $geoData['country'] ?? null,
            'city' => $geoData['city'] ?? null,
            'accessedAt' => date('Y-m-d H:i:s'),
        ]);
        
        // Update counters
        $key->viewCount = ($key->viewCount ?? 0) + 1;
        if ($isNewVisitor) {
            $key->uniqueVisitorCount = ($key->uniqueVisitorCount ?? 0) + 1;
        }
        $key->save();
        
        Logger::info('Secret key access tracked', [
            'secretKeyId' => $key->id,
            'ipHash' => substr($ipHash, 0, 16) . '...',
            'isNewVisitor' => $isNewVisitor,
            'country' => $geoData['country'] ?? 'unknown'
        ]);
        
        return $access;
    }
    
    /**
     * Get client IP address
     */
    private static function getClientIp(): string {
        $headers = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR'
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // Handle comma-separated IPs
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }
    
    /**
     * Partially mask IP for storage (privacy)
     */
    private static function partiallyMaskIp(string $ip): string {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            // 192.168.1.100 → 192.168.x.x
            $parts = explode('.', $ip);
            return $parts[0] . '.' . $parts[1] . '.x.x';
        }
        
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // Mask last 64 bits
            return substr($ip, 0, strpos($ip, ':', 4) ?: 10) . '::x';
        }
        
        return 'x.x.x.x';
    }
    
    /**
     * Handle tracking cookie
     */
    private static function handleTrackingCookie(int $secretKeyId): string {
        $cookieName = 'eqm_track_' . $secretKeyId;
        
        if (isset($_COOKIE[$cookieName])) {
            return $_COOKIE[$cookieName];
        }
        
        $cookieId = 'track_' . bin2hex(random_bytes(16));
        
        setcookie(
            $cookieName,
            $cookieId,
            [
                'expires' => time() + (365 * 24 * 60 * 60), // 1 year
                'path' => '/',
                'secure' => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax'
            ]
        );
        
        return $cookieId;
    }
    
    /**
     * GeoIP lookup (requires external service or database)
     */
    private static function lookupGeoIp(string $ip): array {
        // Check if GeoIP is enabled in settings
        $settings = get_option('eqm_settings', []);
        if (empty($settings['enableGeoIp'])) {
            return [];
        }
        
        // Option 1: Use ip-api.com (free, rate-limited)
        // Option 2: Use local MaxMind database
        // Option 3: Use WordPress GeoIP plugin
        
        try {
            // Free API (limit: 45 requests/minute)
            $response = wp_remote_get(
                "http://ip-api.com/json/{$ip}?fields=country,countryCode,city",
                ['timeout' => 2]
            );
            
            if (is_wp_error($response)) {
                return [];
            }
            
            $body = json_decode(wp_remote_retrieve_body($response), true);
            
            if ($body && $body['status'] === 'success') {
                return [
                    'country' => $body['countryCode'] ?? null,
                    'city' => $body['city'] ?? null,
                ];
            }
        } catch (\Exception $e) {
            Logger::warning('GeoIP lookup failed', ['error' => $e->getMessage()]);
        }
        
        return [];
    }
    
    /**
     * Update session duration (called on page unload)
     */
    public static function updateSessionDuration(int $accessId, int $durationSeconds): void {
        $access = SecretKeyAccess::find($accessId);
        
        if ($access) {
            $access->sessionDuration = $durationSeconds;
            $access->save();
        }
    }
    
    /**
     * Get analytics summary for a secret key
     */
    public static function getAnalyticsSummary(int $secretKeyId): array {
        $accesses = SecretKeyAccess::where('secretKeyId', $secretKeyId)->get();
        
        if (empty($accesses)) {
            return [
                'totalViews' => 0,
                'uniqueVisitors' => 0,
                'avgDuration' => 0,
                'topReferrers' => [],
                'topCountries' => [],
                'viewsByDate' => [],
            ];
        }
        
        // Calculate metrics
        $uniqueIps = array_unique(array_column($accesses, 'ipAddressHash'));
        $durations = array_filter(array_column($accesses, 'sessionDuration'));
        $avgDuration = !empty($durations) ? array_sum($durations) / count($durations) : 0;
        
        // Top referrers
        $referrers = array_filter(array_column($accesses, 'referrer'));
        $referrerCounts = array_count_values(array_map(function($r) {
            $host = parse_url($r, PHP_URL_HOST);
            return $host ?: 'direct';
        }, $referrers));
        arsort($referrerCounts);
        
        // Top countries
        $countries = array_filter(array_column($accesses, 'countryCode'));
        $countryCounts = array_count_values($countries);
        arsort($countryCounts);
        
        // Views by date (last 30 days)
        $viewsByDate = [];
        $thirtyDaysAgo = date('Y-m-d', strtotime('-30 days'));
        
        foreach ($accesses as $access) {
            $date = substr($access->accessedAt, 0, 10);
            if ($date >= $thirtyDaysAgo) {
                $viewsByDate[$date] = ($viewsByDate[$date] ?? 0) + 1;
            }
        }
        ksort($viewsByDate);
        
        return [
            'totalViews' => count($accesses),
            'uniqueVisitors' => count($uniqueIps),
            'avgDuration' => round($avgDuration),
            'topReferrers' => array_slice($referrerCounts, 0, 5, true),
            'topCountries' => array_slice($countryCounts, 0, 5, true),
            'viewsByDate' => $viewsByDate,
        ];
    }
    
    /**
     * Get recent access log
     */
    public static function getRecentAccesses(int $secretKeyId, int $limit = 50): array {
        return SecretKeyAccess::where('secretKeyId', $secretKeyId)
            ->orderBy('accessedAt', 'DESC')
            ->limit($limit)
            ->get();
    }
    
    /**
     * Export analytics as CSV
     */
    public static function exportToCsv(int $secretKeyId): string {
        $accesses = SecretKeyAccess::where('secretKeyId', $secretKeyId)
            ->orderBy('accessedAt', 'DESC')
            ->get();
        
        $csv = "Timestamp,IP (Masked),Country,City,Referrer,User Agent,Duration (s)\n";
        
        foreach ($accesses as $access) {
            $csv .= sprintf(
                "%s,%s,%s,%s,%s,%s,%s\n",
                $access->accessedAt,
                $access->ipAddress ?? '',
                $access->countryCode ?? '',
                $access->city ?? '',
                str_replace(',', ';', $access->referrer ?? ''),
                str_replace(',', ';', substr($access->userAgent ?? '', 0, 100)),
                $access->sessionDuration ?? ''
            );
        }
        
        return $csv;
    }
    
    /**
     * Clear analytics data for a key
     */
    public static function clearAnalytics(int $secretKeyId): int {
        return SecretKeyAccess::where('secretKeyId', $secretKeyId)->delete();
    }
}
```

---

## 🔧 Analytics API Endpoint

**File:** `src/API/SecretKeyAnalyticsEndpoints.php`

```php
<?php
namespace ExamQuestionsManager\API;

use ExamQuestionsManager\Services\SecretKeyAnalyticsService;
use ExamQuestionsManager\ORM\Models\SecretKey;
use ExamQuestionsManager\API\Middleware\RoleMiddleware;
use WP_REST_Request;
use WP_REST_Response;

class SecretKeyAnalyticsEndpoints {
    public static function register(): void {
        register_rest_route('eqm/v1', '/secret-keys/(?P<id>\d+)/analytics', [
            'methods' => 'GET',
            'callback' => [self::class, 'getAnalytics'],
            'permission_callback' => RoleMiddleware::examManagement(),
        ]);
        
        register_rest_route('eqm/v1', '/secret-keys/(?P<id>\d+)/analytics/export', [
            'methods' => 'GET',
            'callback' => [self::class, 'exportAnalytics'],
            'permission_callback' => RoleMiddleware::examManagement(),
        ]);
        
        register_rest_route('eqm/v1', '/secret-keys/(?P<id>\d+)/analytics', [
            'methods' => 'DELETE',
            'callback' => [self::class, 'clearAnalytics'],
            'permission_callback' => RoleMiddleware::adminOnly(),
        ]);
        
        register_rest_route('eqm/v1', '/secret-keys/access/(?P<id>\d+)/duration', [
            'methods' => 'POST',
            'callback' => [self::class, 'updateDuration'],
            'permission_callback' => '__return_true',
        ]);
    }
    
    public static function getAnalytics(WP_REST_Request $request): WP_REST_Response {
        $keyId = (int) $request['id'];
        $key = SecretKey::find($keyId);
        
        if (!$key) {
            return new WP_REST_Response(['error' => 'Secret key not found'], 404);
        }
        
        $summary = SecretKeyAnalyticsService::getAnalyticsSummary($keyId);
        $recentAccesses = SecretKeyAnalyticsService::getRecentAccesses($keyId, 50);
        
        return new WP_REST_Response([
            'summary' => $summary,
            'recentAccesses' => array_map(fn($a) => $a->toArray(), $recentAccesses),
        ]);
    }
    
    public static function exportAnalytics(WP_REST_Request $request): WP_REST_Response {
        $keyId = (int) $request['id'];
        $key = SecretKey::find($keyId);
        
        if (!$key) {
            return new WP_REST_Response(['error' => 'Secret key not found'], 404);
        }
        
        $csv = SecretKeyAnalyticsService::exportToCsv($keyId);
        
        // Return as downloadable file
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="analytics-' . $keyId . '.csv"');
        echo $csv;
        exit;
    }
    
    public static function clearAnalytics(WP_REST_Request $request): WP_REST_Response {
        $keyId = (int) $request['id'];
        $deleted = SecretKeyAnalyticsService::clearAnalytics($keyId);
        
        return new WP_REST_Response([
            'success' => true,
            'deleted' => $deleted
        ]);
    }
    
    public static function updateDuration(WP_REST_Request $request): WP_REST_Response {
        $accessId = (int) $request['id'];
        $duration = (int) $request->get_param('duration');
        
        SecretKeyAnalyticsService::updateSessionDuration($accessId, $duration);
        
        return new WP_REST_Response(['success' => true]);
    }
}
```

---

## 📊 Admin UI: Analytics Dashboard

**React Component Structure:**

```tsx
// SecretKeyAnalytics.tsx
interface AnalyticsData {
  summary: {
    totalViews: number;
    uniqueVisitors: number;
    avgDuration: number;
    topReferrers: Record<string, number>;
    topCountries: Record<string, number>;
    viewsByDate: Record<string, number>;
  };
  recentAccesses: Array<{
    id: number;
    accessedAt: string;
    ipAddress: string;
    countryCode: string;
    city: string;
    referrer: string;
    userAgent: string;
    sessionDuration: number;
  }>;
}

// Display:
// - Overview cards (Total Views, Unique Visitors, Avg Duration)
// - Line chart for views over time
// - Bar chart for top referrers
// - Pie chart for geographic distribution
// - Table with recent access log
// - Export CSV button
// - Clear logs button (admin only)
```

---

## ✅ Acceptance Criteria

### Analytics Tracking
- [ ] IP addresses hashed before comparison
- [ ] Partial IP stored for display (privacy)
- [ ] Tracking cookie set correctly
- [ ] New visitors detected via IP hash
- [ ] View counts increment correctly

### GeoIP (Optional Feature Toggle)
- [ ] GeoIP can be enabled/disabled in settings
- [ ] Country code captured when enabled
- [ ] City captured when enabled
- [ ] Graceful fallback when lookup fails
- [ ] Rate limiting respected

### Analytics Summary
- [ ] Total views counted correctly
- [ ] Unique visitors calculated from IP hashes
- [ ] Average duration calculated
- [ ] Top 5 referrers returned
- [ ] Top 5 countries returned
- [ ] 30-day view chart data provided

### Export & Cleanup
- [ ] CSV export includes all access records
- [ ] CSV properly escapes commas
- [ ] Clear analytics removes all access records
- [ ] Clear is admin-only

### Graceful Degradation
- [ ] If analytics disabled, basic counting works
- [ ] Missing GeoIP doesn't break access logging
- [ ] UI hides analytics when not available

---

## 📝 Notes

### Privacy Considerations
- Store only partially masked IPs for display
- Use SHA-256 hash for unique visitor counting
- Cookie is httponly and secure
- GeoIP is opt-in feature
- Provide data export for GDPR compliance

### Skipping This Spec
If this optional spec is skipped:
1. Remove GeoIP enable toggle from settings
2. Hide analytics tab in secret key admin
3. Access logging still occurs with NULL analytics fields
4. Basic view/unique counts from spec 21 still work

---

*Next: `25-participant-service.md`*
