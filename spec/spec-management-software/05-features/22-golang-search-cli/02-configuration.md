# Component: Configuration

**Parent:** [Golang Search CLI](./00-overview.md)  
**Version:** 1.2.0  
**Updated:** 2026-01-28  

---

## Summary

Configuration management using Viper with JSON file support and environment variable overrides. Includes proxy support with rotation strategies for avoiding IP-based blocking.

---

## Configuration File

**Path:** `./config.json` or `./config/config.json`

---

## Type Standards

### Weight Values (NORMALIZED)

All weight values use `float64` in range `0.0` to `1.0` and **MUST sum to 1.0** (±0.001 tolerance).

| Before | After | Meaning |
|--------|-------|---------|
| `40` (int, percentage) | `0.40` (float64) | 40% probability |
| `30` (int, percentage) | `0.30` (float64) | 30% probability |

### Duration Values (STANDARDIZED)

All duration values support both formats:
- **String format:** `"2s"`, `"500ms"`, `"30m"`, `"1h"`
- **Numeric format:** Milliseconds as integer (for backward compatibility)

| Field | String Example | Numeric Example | Internal Unit |
|-------|----------------|-----------------|---------------|
| `requestDelay` | `"2s"` | `2000` | milliseconds |
| `timeout` | `"30s"` | `30000` | milliseconds |
| `cooldown` | `"15m"` | `900000` | milliseconds |
| `cleanupInterval` | `"24h"` | `86400000` | milliseconds |

---

## Full Configuration Schema

```json
{
  "database": {
    "path": "./data/search.db.sqlite",
    "maxConnections": 10,
    "logQueries": false
  },
  "search": {
    "defaultEngine": "google",
    "defaultMethod": "html",
    "requestDelay": "2s",
    "maxConcurrent": 5,
    "timeout": "30s",
    "maxRetries": 3,
    "retryDelay": "5s",
    "userAgents": [
      "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
      "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
      "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
    ],
    "methodWeights": {
      "html": 0.40,
      "google_api": 0.30,
      "duckduckgo": 0.20,
      "bing": 0.10
    }
  },
  "proxy": {
    "enabled": false,
    "type": "http",
    "url": "${PROXY_URL}",
    "auth": {
      "enabled": false,
      "username": "${PROXY_USERNAME}",
      "password": "${PROXY_PASSWORD}"
    },
    "rotation": {
      "enabled": false,
      "strategy": "round-robin",
      "urls": [],
      "healthCheck": {
        "enabled": true,
        "interval": "5m",
        "timeout": "10s",
        "testUrl": "https://httpbin.org/ip"
      }
    },
    "timeout": "10s",
    "skipVerify": false,
    "perEngine": {}
  },
  "cache": {
    "enabled": true,
    "ttlDays": 5,
    "maxEntries": 10000,
    "autoCleanup": true,
    "cleanupInterval": "24h"
  },
  "nested": {
    "enabled": true,
    "maxDepth": 3,
    "keywordThreshold": 5,
    "minKeywordLength": 3,
    "excludeKeywords": ["the", "and", "or", "is", "a", "an"]
  },
  "output": {
    "defaultFormat": "json",
    "saveToDb": true,
    "prettyPrint": true,
    "includeMetadata": true
  },
  "pageFetch": {
    "enabled": false,
    "maxSize": 1048576,
    "timeout": "10s",
    "extractText": true,
    "extractKeywords": true
  },
  "apis": {
    "googleSearchConsole": {
      "enabled": false,
      "credentialsPath": "./credentials.json",
      "quotaLimit": 100
    },
    "bing": {
      "enabled": false,
      "apiKeyEnv": "BING_API_KEY",
      "endpoint": "https://api.bing.microsoft.com/v7.0/search"
    },
    "duckduckgo": {
      "enabled": true,
      "endpoint": "https://html.duckduckgo.com/html/"
    }
  },
  "blocking": {
    "detectPatterns": [
      "unusual traffic",
      "captcha",
      "blocked",
      "rate limit"
    ],
    "cooldown": "30m",
    "maxBlockedMethods": 2
  },
  "backoff": {
    "initialDelay": "1s",
    "maxDelay": "60s",
    "multiplier": 2.0,
    "jitter": 0.2,
    "jitterType": "bounded",
    "maxAttempts": 5,
    "resetAfterSuccess": true
  },
  "selectors": {
    "path": "./configs/selectors.json",
    "autoReload": false,
    "fallbackToEmbedded": true,
    "reloadInterval": "5m"
  },
  "shutdown": {
    "timeout": "30s",
    "progressInterval": "5s",
    "forceExitTimeout": "45s"
  },
  "resources": {
    "maxGoroutines": 100,
    "maxMemoryMB": 512,
    "acquisitionTimeout": "10s",
    "memoryCheckInterval": "30s"
  },
  "logging": {
    "level": "info",
    "file": "./logs/search.log",
    "maxSize": 10485760,
    "maxBackups": 5
  },
  "metrics": {
    "enabled": true,
    "endpoint": "/metrics",
    "port": 9090,
    "basicAuth": {
      "enabled": false,
      "username": "",
      "passwordHash": ""
    }
  },
  "health": {
    "enabled": true,
    "port": 8080,
    "timeout": "5s",
    "diskMinFreeMB": 100,
    "diskPaths": ["./data"]
  },
  "tracing": {
    "enabled": false,
    "serviceName": "gsearch",
    "environment": "development",
    "otlpEndpoint": "localhost:4317",
    "samplingRate": 1.0,
    "batchTimeout": "5s",
    "exportTimeout": "30s"
  }
}
```

---

## Proxy Configuration

### Proxy Types

| Type | Protocol | Port (Default) | Use Case |
|------|----------|----------------|----------|
| `http` | HTTP CONNECT | 8080 | General HTTP proxying |
| `https` | HTTPS CONNECT | 8443 | Encrypted proxy connection |
| `socks5` | SOCKS5 | 1080 | Full TCP proxying, more anonymous |
| `socks5h` | SOCKS5 + DNS | 1080 | DNS resolved by proxy server |

### Rotation Strategies

| Strategy | Description | Best For |
|----------|-------------|----------|
| `round-robin` | Cycles through proxies sequentially | Balanced distribution |
| `random` | Selects random proxy each request | Unpredictable patterns |
| `least-used` | Prefers proxies with fewer requests | Even load distribution |
| `failover` | Uses primary until failure | Reliable single proxy with backup |
| `weighted` | Selects based on configured weights | Prioritizing faster proxies |

### Per-Engine Proxy Override

```json
{
  "proxy": {
    "enabled": true,
    "url": "http://default-proxy:8080",
    "perEngine": {
      "google": {
        "url": "socks5://google-proxy:1080",
        "auth": {
          "enabled": true,
          "username": "google_user",
          "password": "${GOOGLE_PROXY_PASS}"
        }
      },
      "bing": {
        "url": "http://bing-proxy:8080"
      }
    }
  }
}
```

---

## Go Configuration Struct

```go
package config

import (
    "encoding/json"
    "fmt"
    "math"
    "time"

    "github.com/spf13/viper"
)

// Duration supports both string ("2s") and numeric (milliseconds) formats
type Duration struct {
    time.Duration
}

func (d *Duration) UnmarshalJSON(data []byte) error {
    var v interface{}
    if err := json.Unmarshal(data, &v); err != nil {
        return err
    }
    switch value := v.(type) {
    case float64:
        // Interpret as milliseconds for backward compatibility
        d.Duration = time.Duration(value) * time.Millisecond
    case string:
        parsed, err := time.ParseDuration(value)
        if err != nil {
            return fmt.Errorf("invalid duration string: %w", err)
        }
        d.Duration = parsed
    default:
        return fmt.Errorf("invalid duration type: %T", v)
    }
    return nil
}

func (d Duration) MarshalJSON() ([]byte, error) {
    return json.Marshal(d.Duration.String())
}

type Config struct {
    Database    DatabaseConfig    `mapstructure:"database"`
    Search      SearchConfig      `mapstructure:"search"`
    Proxy       ProxyConfig       `mapstructure:"proxy"`
    Cache       CacheConfig       `mapstructure:"cache"`
    Nested      NestedConfig      `mapstructure:"nested"`
    Output      OutputConfig      `mapstructure:"output"`
    PageFetch   PageFetchConfig   `mapstructure:"pageFetch"`
    APIs        APIsConfig        `mapstructure:"apis"`
    Blocking    BlockingConfig    `mapstructure:"blocking"`
    Backoff     BackoffConfig     `mapstructure:"backoff"`
    Selectors   SelectorConfig    `mapstructure:"selectors"`
    Shutdown    ShutdownConfig    `mapstructure:"shutdown"`
    Resources   ResourceConfig    `mapstructure:"resources"`
    Logging     LoggingConfig     `mapstructure:"logging"`
    Metrics     MetricsConfig     `mapstructure:"metrics"`     // Prometheus metrics
    Health      HealthConfig      `mapstructure:"health"`      // Health check endpoints
    Tracing     TracingConfig     `mapstructure:"tracing"`     // OpenTelemetry tracing
}

type DatabaseConfig struct {
    Path           string `mapstructure:"path"`
    MaxConnections int    `mapstructure:"maxConnections"`
    LogQueries     bool   `mapstructure:"logQueries"`
}

type SearchConfig struct {
    DefaultEngine  string             `mapstructure:"defaultEngine"`
    DefaultMethod  string             `mapstructure:"defaultMethod"`
    RequestDelay   Duration           `mapstructure:"requestDelay"`
    MaxConcurrent  int                `mapstructure:"maxConcurrent"`
    Timeout        Duration           `mapstructure:"timeout"`
    MaxRetries     int                `mapstructure:"maxRetries"`
    RetryDelay     Duration           `mapstructure:"retryDelay"`
    UserAgents     []string           `mapstructure:"userAgents"`
    MethodWeights  map[string]float64 `mapstructure:"methodWeights"` // 0.0-1.0, must sum to 1.0
}

// ProxyConfig configures HTTP/SOCKS proxy support
type ProxyConfig struct {
    Enabled   bool              `mapstructure:"enabled"`
    Type      string            `mapstructure:"type"`      // http, https, socks5, socks5h
    URL       string            `mapstructure:"url"`       // Primary proxy URL
    Auth      ProxyAuthConfig   `mapstructure:"auth"`
    Rotation  ProxyRotation     `mapstructure:"rotation"`
    Timeout   Duration          `mapstructure:"timeout"`
    SkipVerify bool             `mapstructure:"skipVerify"` // Skip TLS verification
    PerEngine map[string]ProxyOverride `mapstructure:"perEngine"`
}

// ProxyAuthConfig configures proxy authentication
type ProxyAuthConfig struct {
    Enabled  bool   `mapstructure:"enabled"`
    Username string `mapstructure:"username"`
    Password string `mapstructure:"password"`
}

// ProxyRotation configures multiple proxy rotation
type ProxyRotation struct {
    Enabled     bool               `mapstructure:"enabled"`
    Strategy    string             `mapstructure:"strategy"` // round-robin, random, least-used, failover, weighted
    URLs        []string           `mapstructure:"urls"`
    Weights     map[string]float64 `mapstructure:"weights,omitempty"` // For weighted strategy
    HealthCheck ProxyHealthCheck   `mapstructure:"healthCheck"`
}

// ProxyHealthCheck configures proxy health monitoring
type ProxyHealthCheck struct {
    Enabled  bool     `mapstructure:"enabled"`
    Interval Duration `mapstructure:"interval"`
    Timeout  Duration `mapstructure:"timeout"`
    TestURL  string   `mapstructure:"testUrl"`
}

// ProxyOverride allows per-engine proxy configuration
type ProxyOverride struct {
    URL  string          `mapstructure:"url"`
    Auth ProxyAuthConfig `mapstructure:"auth"`
}

type CacheConfig struct {
    Enabled         bool     `mapstructure:"enabled"`
    TTLDays         int      `mapstructure:"ttlDays"`
    MaxEntries      int      `mapstructure:"maxEntries"`
    AutoCleanup     bool     `mapstructure:"autoCleanup"`
    CleanupInterval Duration `mapstructure:"cleanupInterval"`
}

type NestedConfig struct {
    Enabled          bool     `mapstructure:"enabled"`
    MaxDepth         int      `mapstructure:"maxDepth"`
    KeywordThreshold int      `mapstructure:"keywordThreshold"`
    MinKeywordLength int      `mapstructure:"minKeywordLength"`
    ExcludeKeywords  []string `mapstructure:"excludeKeywords"`
}

type OutputConfig struct {
    DefaultFormat   string `mapstructure:"defaultFormat"`
    SaveToDb        bool   `mapstructure:"saveToDb"`
    PrettyPrint     bool   `mapstructure:"prettyPrint"`
    IncludeMetadata bool   `mapstructure:"includeMetadata"`
}

type PageFetchConfig struct {
    Enabled         bool     `mapstructure:"enabled"`
    MaxSize         int      `mapstructure:"maxSize"`
    Timeout         Duration `mapstructure:"timeout"`
    ExtractText     bool     `mapstructure:"extractText"`
    ExtractKeywords bool     `mapstructure:"extractKeywords"`
}

type APIsConfig struct {
    GoogleSearchConsole GoogleAPIConfig `mapstructure:"googleSearchConsole"`
    Bing                BingAPIConfig   `mapstructure:"bing"`
    DuckDuckGo          DDGConfig       `mapstructure:"duckduckgo"`
}

type GoogleAPIConfig struct {
    Enabled         bool   `mapstructure:"enabled"`
    CredentialsPath string `mapstructure:"credentialsPath"`
    QuotaLimit      int    `mapstructure:"quotaLimit"`
}

type BingAPIConfig struct {
    Enabled   bool   `mapstructure:"enabled"`
    APIKeyEnv string `mapstructure:"apiKeyEnv"`
    Endpoint  string `mapstructure:"endpoint"`
}

type DDGConfig struct {
    Enabled  bool   `mapstructure:"enabled"`
    Endpoint string `mapstructure:"endpoint"`
}

type BlockingConfig struct {
    DetectPatterns    []string `mapstructure:"detectPatterns"`
    Cooldown          Duration `mapstructure:"cooldown"`
    MaxBlockedMethods int      `mapstructure:"maxBlockedMethods"`
}

type BackoffConfig struct {
    InitialDelay      Duration   `mapstructure:"initialDelay"`
    MaxDelay          Duration   `mapstructure:"maxDelay"`
    Multiplier        float64    `mapstructure:"multiplier"`
    Jitter            float64    `mapstructure:"jitter"`            // 0.0-1.0
    JitterType        string     `mapstructure:"jitterType"`        // full, equal, decorrelated, bounded
    MaxAttempts       int        `mapstructure:"maxAttempts"`       // Max retry attempts
    ResetAfterSuccess bool       `mapstructure:"resetAfterSuccess"` // Reset backoff on success
}

type SelectorConfig struct {
    Path               string   `mapstructure:"path"`
    AutoReload         bool     `mapstructure:"autoReload"`
    FallbackToEmbedded bool     `mapstructure:"fallbackToEmbedded"`
    ReloadInterval     Duration `mapstructure:"reloadInterval"`
}

type ShutdownConfig struct {
    Timeout          Duration `mapstructure:"timeout"`
    ProgressInterval Duration `mapstructure:"progressInterval"`
    ForceExitTimeout Duration `mapstructure:"forceExitTimeout"`
}

type ResourceConfig struct {
    MaxGoroutines       int      `mapstructure:"maxGoroutines"`
    MaxMemoryMB         int      `mapstructure:"maxMemoryMB"`
    AcquisitionTimeout  Duration `mapstructure:"acquisitionTimeout"`
    MemoryCheckInterval Duration `mapstructure:"memoryCheckInterval"`
}

type LoggingConfig struct {
    Level      string `mapstructure:"level"`
    File       string `mapstructure:"file"`
    MaxSize    int    `mapstructure:"maxSize"`
    MaxBackups int    `mapstructure:"maxBackups"`
}

// MetricsConfig configures Prometheus metrics endpoint
type MetricsConfig struct {
    Enabled   bool            `mapstructure:"enabled"`
    Endpoint  string          `mapstructure:"endpoint"`  // Default: /metrics
    Port      int             `mapstructure:"port"`      // Default: 9090
    BasicAuth MetricsAuthConfig `mapstructure:"basicAuth"`
}

type MetricsAuthConfig struct {
    Enabled      bool   `mapstructure:"enabled"`
    Username     string `mapstructure:"username"`
    PasswordHash string `mapstructure:"passwordHash"` // Bcrypt hash
}

// HealthConfig configures health check endpoints
type HealthConfig struct {
    Enabled     bool     `mapstructure:"enabled"`
    Port        int      `mapstructure:"port"`        // Default: 8080
    Timeout     Duration `mapstructure:"timeout"`     // Default: 5s
    DiskMinFreeMB int64  `mapstructure:"diskMinFreeMB"` // Default: 100
    DiskPaths   []string `mapstructure:"diskPaths"`   // Default: ["./data"]
}

// TracingConfig configures OpenTelemetry distributed tracing
type TracingConfig struct {
    Enabled       bool    `mapstructure:"enabled"`
    ServiceName   string  `mapstructure:"serviceName"`   // Default: gsearch
    Environment   string  `mapstructure:"environment"`   // Default: development
    OTLPEndpoint  string  `mapstructure:"otlpEndpoint"`  // Default: localhost:4317
    SamplingRate  float64 `mapstructure:"samplingRate"`  // Default: 1.0 (100%)
    BatchTimeout  Duration `mapstructure:"batchTimeout"` // Default: 5s
    ExportTimeout Duration `mapstructure:"exportTimeout"` // Default: 30s
}
```

---

## Proxy Manager Implementation

```go
// pkg/proxy/manager.go

package proxy

import (
    "context"
    "crypto/tls"
    "fmt"
    "math/rand"
    "net"
    "net/http"
    "net/url"
    "sync"
    "sync/atomic"
    "time"
    
    "github.com/rs/zerolog/log"
    "golang.org/x/net/proxy"
    "gsearch/pkg/config"
    "gsearch/pkg/errors"
)

// ProxyManager manages proxy selection and rotation
type ProxyManager struct {
    config      config.ProxyConfig
    proxies     []*ProxyEntry
    current     atomic.Int64
    mu          sync.RWMutex
    healthStop  chan struct{}
}

// ProxyEntry represents a single proxy with metadata
type ProxyEntry struct {
    URL         *url.URL
    Auth        *url.Userinfo
    Healthy     bool
    LastCheck   time.Time
    RequestCount int64
    FailCount   int64
    Weight      float64
    mu          sync.Mutex
}

// NewProxyManager creates a new proxy manager
func NewProxyManager(cfg config.ProxyConfig) (*ProxyManager, error) {
    pm := &ProxyManager{
        config:     cfg,
        healthStop: make(chan struct{}),
    }
    
    if !cfg.Enabled {
        return pm, nil
    }
    
    // Initialize proxy list
    if cfg.Rotation.Enabled && len(cfg.Rotation.URLs) > 0 {
        for _, urlStr := range cfg.Rotation.URLs {
            entry, err := pm.parseProxyURL(urlStr, cfg.Auth)
            if err != nil {
                return nil, errors.WrapError(errors.ErrInvalidConfigFormat, 
                    "invalid proxy URL: "+urlStr, err)
            }
            
            // Set weight if using weighted strategy
            if w, ok := cfg.Rotation.Weights[urlStr]; ok {
                entry.Weight = w
            } else {
                entry.Weight = 1.0
            }
            
            pm.proxies = append(pm.proxies, entry)
        }
    } else if cfg.URL != "" {
        entry, err := pm.parseProxyURL(cfg.URL, cfg.Auth)
        if err != nil {
            return nil, errors.WrapError(errors.ErrInvalidConfigFormat,
                "invalid proxy URL", err)
        }
        pm.proxies = append(pm.proxies, entry)
    }
    
    // Start health check if enabled
    if cfg.Rotation.Enabled && cfg.Rotation.HealthCheck.Enabled {
        go pm.healthCheckLoop()
    }
    
    return pm, nil
}

// parseProxyURL parses a proxy URL string
func (pm *ProxyManager) parseProxyURL(urlStr string, auth config.ProxyAuthConfig) (*ProxyEntry, error) {
    parsed, err := url.Parse(urlStr)
    if err != nil {
        return nil, err
    }
    
    entry := &ProxyEntry{
        URL:     parsed,
        Healthy: true, // Assume healthy initially
        Weight:  1.0,
    }
    
    // Set auth if configured
    if auth.Enabled && auth.Username != "" {
        entry.Auth = url.UserPassword(auth.Username, auth.Password)
    } else if parsed.User != nil {
        entry.Auth = parsed.User
    }
    
    return entry, nil
}

// GetProxy returns the next proxy based on rotation strategy
func (pm *ProxyManager) GetProxy() (*url.URL, error) {
    if !pm.config.Enabled || len(pm.proxies) == 0 {
        return nil, nil // Direct connection
    }
    
    pm.mu.RLock()
    defer pm.mu.RUnlock()
    
    var entry *ProxyEntry
    
    switch pm.config.Rotation.Strategy {
    case "round-robin":
        entry = pm.selectRoundRobin()
    case "random":
        entry = pm.selectRandom()
    case "least-used":
        entry = pm.selectLeastUsed()
    case "failover":
        entry = pm.selectFailover()
    case "weighted":
        entry = pm.selectWeighted()
    default:
        entry = pm.selectRoundRobin()
    }
    
    if entry == nil {
        return nil, errors.NewError(errors.ErrProxyConnection, 
            "no healthy proxies available")
    }
    
    // Clone URL and add auth
    proxyURL := *entry.URL
    if entry.Auth != nil {
        proxyURL.User = entry.Auth
    }
    
    // Track usage
    entry.mu.Lock()
    entry.RequestCount++
    entry.mu.Unlock()
    
    return &proxyURL, nil
}

// selectRoundRobin cycles through proxies sequentially
func (pm *ProxyManager) selectRoundRobin() *ProxyEntry {
    for attempts := 0; attempts < len(pm.proxies); attempts++ {
        idx := pm.current.Add(1) % int64(len(pm.proxies))
        entry := pm.proxies[idx]
        if entry.Healthy {
            return entry
        }
    }
    return nil
}

// selectRandom selects a random healthy proxy
func (pm *ProxyManager) selectRandom() *ProxyEntry {
    healthy := pm.getHealthyProxies()
    if len(healthy) == 0 {
        return nil
    }
    return healthy[rand.Intn(len(healthy))]
}

// selectLeastUsed selects the proxy with fewest requests
func (pm *ProxyManager) selectLeastUsed() *ProxyEntry {
    var selected *ProxyEntry
    minCount := int64(^uint64(0) >> 1) // Max int64
    
    for _, entry := range pm.proxies {
        if entry.Healthy {
            entry.mu.Lock()
            count := entry.RequestCount
            entry.mu.Unlock()
            
            if count < minCount {
                minCount = count
                selected = entry
            }
        }
    }
    return selected
}

// selectFailover uses first healthy proxy
func (pm *ProxyManager) selectFailover() *ProxyEntry {
    for _, entry := range pm.proxies {
        if entry.Healthy {
            return entry
        }
    }
    return nil
}

// selectWeighted selects based on configured weights
func (pm *ProxyManager) selectWeighted() *ProxyEntry {
    healthy := pm.getHealthyProxies()
    if len(healthy) == 0 {
        return nil
    }
    
    // Calculate total weight
    var totalWeight float64
    for _, entry := range healthy {
        totalWeight += entry.Weight
    }
    
    // Random selection based on weight
    r := rand.Float64() * totalWeight
    var cumulative float64
    for _, entry := range healthy {
        cumulative += entry.Weight
        if r <= cumulative {
            return entry
        }
    }
    
    return healthy[len(healthy)-1]
}

// getHealthyProxies returns all healthy proxy entries
func (pm *ProxyManager) getHealthyProxies() []*ProxyEntry {
    var healthy []*ProxyEntry
    for _, entry := range pm.proxies {
        if entry.Healthy {
            healthy = append(healthy, entry)
        }
    }
    return healthy
}

// GetProxyForEngine returns proxy for specific engine (with override support)
func (pm *ProxyManager) GetProxyForEngine(engine string) (*url.URL, error) {
    if override, ok := pm.config.PerEngine[engine]; ok {
        parsed, err := url.Parse(override.URL)
        if err != nil {
            return nil, err
        }
        
        if override.Auth.Enabled {
            parsed.User = url.UserPassword(override.Auth.Username, override.Auth.Password)
        }
        
        return parsed, nil
    }
    
    return pm.GetProxy()
}

// CreateHTTPClient creates an HTTP client configured with proxy
func (pm *ProxyManager) CreateHTTPClient(timeout time.Duration) (*http.Client, error) {
    transport := &http.Transport{
        DialContext: (&net.Dialer{
            Timeout:   30 * time.Second,
            KeepAlive: 30 * time.Second,
        }).DialContext,
        MaxIdleConns:          100,
        IdleConnTimeout:       90 * time.Second,
        TLSHandshakeTimeout:   10 * time.Second,
        ExpectContinueTimeout: 1 * time.Second,
    }
    
    if pm.config.SkipVerify {
        transport.TLSClientConfig = &tls.Config{InsecureSkipVerify: true}
    }
    
    if pm.config.Enabled {
        transport.Proxy = pm.proxyFunc
    }
    
    return &http.Client{
        Transport: transport,
        Timeout:   timeout,
    }, nil
}

// proxyFunc is the proxy selector for http.Transport
func (pm *ProxyManager) proxyFunc(req *http.Request) (*url.URL, error) {
    // Check for per-engine override based on request URL
    engine := pm.detectEngine(req.URL.Host)
    if engine != "" {
        return pm.GetProxyForEngine(engine)
    }
    
    return pm.GetProxy()
}

// detectEngine identifies the search engine from host
func (pm *ProxyManager) detectEngine(host string) string {
    switch {
    case contains(host, "google"):
        return "google"
    case contains(host, "bing"):
        return "bing"
    case contains(host, "duckduckgo"):
        return "duckduckgo"
    default:
        return ""
    }
}

// CreateSOCKS5Client creates an HTTP client with SOCKS5 proxy
func (pm *ProxyManager) CreateSOCKS5Client(proxyURL *url.URL, timeout time.Duration) (*http.Client, error) {
    var auth *proxy.Auth
    if proxyURL.User != nil {
        pass, _ := proxyURL.User.Password()
        auth = &proxy.Auth{
            User:     proxyURL.User.Username(),
            Password: pass,
        }
    }
    
    dialer, err := proxy.SOCKS5("tcp", proxyURL.Host, auth, proxy.Direct)
    if err != nil {
        return nil, errors.WrapError(errors.ErrProxyConnection, 
            "failed to create SOCKS5 dialer", err)
    }
    
    transport := &http.Transport{
        DialContext: func(ctx context.Context, network, addr string) (net.Conn, error) {
            return dialer.Dial(network, addr)
        },
    }
    
    if pm.config.SkipVerify {
        transport.TLSClientConfig = &tls.Config{InsecureSkipVerify: true}
    }
    
    return &http.Client{
        Transport: transport,
        Timeout:   timeout,
    }, nil
}

// healthCheckLoop periodically checks proxy health
func (pm *ProxyManager) healthCheckLoop() {
    interval := pm.config.Rotation.HealthCheck.Interval.Duration
    ticker := time.NewTicker(interval)
    defer ticker.Stop()
    
    // Initial check
    pm.checkAllProxies()
    
    for {
        select {
        case <-ticker.C:
            pm.checkAllProxies()
        case <-pm.healthStop:
            return
        }
    }
}

// checkAllProxies checks health of all configured proxies
func (pm *ProxyManager) checkAllProxies() {
    testURL := pm.config.Rotation.HealthCheck.TestURL
    timeout := pm.config.Rotation.HealthCheck.Timeout.Duration
    
    var wg sync.WaitGroup
    for _, entry := range pm.proxies {
        wg.Add(1)
        go func(e *ProxyEntry) {
            defer wg.Done()
            pm.checkProxyHealth(e, testURL, timeout)
        }(entry)
    }
    wg.Wait()
}

// checkProxyHealth checks if a single proxy is working
func (pm *ProxyManager) checkProxyHealth(entry *ProxyEntry, testURL string, timeout time.Duration) {
    proxyURL := entry.URL
    if entry.Auth != nil {
        proxyURL.User = entry.Auth
    }
    
    client := &http.Client{
        Transport: &http.Transport{
            Proxy: http.ProxyURL(proxyURL),
        },
        Timeout: timeout,
    }
    
    resp, err := client.Get(testURL)
    
    entry.mu.Lock()
    defer entry.mu.Unlock()
    
    entry.LastCheck = time.Now()
    
    if err != nil {
        entry.FailCount++
        if entry.FailCount >= 3 {
            entry.Healthy = false
            log.Warn().
                Str("proxy", entry.URL.Host).
                Int64("failCount", entry.FailCount).
                Msg("Proxy marked unhealthy")
        }
    } else {
        resp.Body.Close()
        if resp.StatusCode == http.StatusOK {
            entry.Healthy = true
            entry.FailCount = 0
        }
    }
}

// MarkFailed marks a proxy as failed (for external failure reporting)
func (pm *ProxyManager) MarkFailed(proxyURL *url.URL) {
    pm.mu.Lock()
    defer pm.mu.Unlock()
    
    for _, entry := range pm.proxies {
        if entry.URL.Host == proxyURL.Host {
            entry.mu.Lock()
            entry.FailCount++
            if entry.FailCount >= 3 {
                entry.Healthy = false
            }
            entry.mu.Unlock()
            break
        }
    }
}

// Stop stops the proxy manager
func (pm *ProxyManager) Stop() {
    close(pm.healthStop)
}

// Stats returns proxy statistics
func (pm *ProxyManager) Stats() []ProxyStats {
    pm.mu.RLock()
    defer pm.mu.RUnlock()
    
    var stats []ProxyStats
    for _, entry := range pm.proxies {
        entry.mu.Lock()
        stats = append(stats, ProxyStats{
            URL:          entry.URL.String(),
            Healthy:      entry.Healthy,
            RequestCount: entry.RequestCount,
            FailCount:    entry.FailCount,
            LastCheck:    entry.LastCheck,
            Weight:       entry.Weight,
        })
        entry.mu.Unlock()
    }
    return stats
}

// ProxyStats contains proxy statistics
type ProxyStats struct {
    URL          string    `json:"url"`
    Healthy      bool      `json:"healthy"`
    RequestCount int64     `json:"requestCount"`
    FailCount    int64     `json:"failCount"`
    LastCheck    time.Time `json:"lastCheck"`
    Weight       float64   `json:"weight"`
}

func contains(s, substr string) bool {
    return len(s) >= len(substr) && (s == substr || 
        len(s) > 0 && containsIgnoreCase(s, substr))
}

func containsIgnoreCase(s, substr string) bool {
    // Simple case-insensitive contains
    return len(s) >= len(substr) && 
        (s[:len(substr)] == substr || containsIgnoreCase(s[1:], substr))
}
```

---

## Configuration Loading

```go
package config

import (
    "fmt"

    "github.com/spf13/viper"
)

func Load(configPath string) (*Config, error) {
    if configPath != "" {
        viper.SetConfigFile(configPath)
    } else {
        viper.SetConfigName("config")
        viper.SetConfigType("json")
        viper.AddConfigPath(".")
        viper.AddConfigPath("./config")
    }
    
    // Set defaults
    setDefaults()
    
    // Environment variable overrides
    viper.SetEnvPrefix("GSEARCH")
    viper.AutomaticEnv()
    
    // Read config file
    if err := viper.ReadInConfig(); err != nil {
        if _, ok := err.(viper.ConfigFileNotFoundError); !ok {
            return nil, fmt.Errorf("error reading config: %w", err)
        }
        // Config file not found, use defaults
    }
    
    var cfg Config
    if err := viper.Unmarshal(&cfg); err != nil {
        return nil, fmt.Errorf("error unmarshaling config: %w", err)
    }
    
    if err := cfg.Validate(); err != nil {
        return nil, fmt.Errorf("config validation failed: %w", err)
    }
    
    return &cfg, nil
}

func setDefaults() {
    viper.SetDefault("database.path", "./data/search.db.sqlite")
    viper.SetDefault("database.maxConnections", 10)
    
    viper.SetDefault("search.defaultEngine", "google")
    viper.SetDefault("search.defaultMethod", "html")
    viper.SetDefault("search.requestDelay", "2s")
    viper.SetDefault("search.maxConcurrent", 5)
    viper.SetDefault("search.timeout", "30s")
    viper.SetDefault("search.maxRetries", 3)
    viper.SetDefault("search.retryDelay", "5s")
    
    // Default weights (sum to 1.0)
    viper.SetDefault("search.methodWeights", map[string]float64{
        "html":       0.40,
        "google_api": 0.30,
        "duckduckgo": 0.20,
        "bing":       0.10,
    })
    
    // Proxy defaults
    viper.SetDefault("proxy.enabled", false)
    viper.SetDefault("proxy.type", "http")
    viper.SetDefault("proxy.timeout", "10s")
    viper.SetDefault("proxy.skipVerify", false)
    viper.SetDefault("proxy.rotation.strategy", "round-robin")
    viper.SetDefault("proxy.rotation.healthCheck.enabled", true)
    viper.SetDefault("proxy.rotation.healthCheck.interval", "5m")
    viper.SetDefault("proxy.rotation.healthCheck.timeout", "10s")
    viper.SetDefault("proxy.rotation.healthCheck.testUrl", "https://httpbin.org/ip")
    
    viper.SetDefault("cache.enabled", true)
    viper.SetDefault("cache.ttlDays", 5)
    viper.SetDefault("cache.maxEntries", 10000)
    viper.SetDefault("cache.cleanupInterval", "24h")
    
    viper.SetDefault("nested.enabled", true)
    viper.SetDefault("nested.maxDepth", 3)
    
    viper.SetDefault("output.defaultFormat", "json")
    viper.SetDefault("output.saveToDb", true)
    viper.SetDefault("output.prettyPrint", true)
    
    viper.SetDefault("blocking.cooldown", "30m")
    
    viper.SetDefault("backoff.initialDelay", "1s")
    viper.SetDefault("backoff.maxDelay", "60s")
    viper.SetDefault("backoff.multiplier", 2.0)
    viper.SetDefault("backoff.jitter", 0.2)
    
    viper.SetDefault("selectors.path", "./configs/selectors.json")
    viper.SetDefault("selectors.fallbackToEmbedded", true)
    
    viper.SetDefault("shutdown.timeout", "30s")
    viper.SetDefault("shutdown.progressInterval", "5s")
    viper.SetDefault("shutdown.forceExitTimeout", "45s")
    
    viper.SetDefault("resources.maxGoroutines", 100)
    viper.SetDefault("resources.maxMemoryMB", 512)
    viper.SetDefault("resources.acquisitionTimeout", "10s")
}
```

---

## Environment Variables

| Variable | Config Path | Description |
|----------|-------------|-------------|
| `GSEARCH_DATABASE_PATH` | `database.path` | Database file path |
| `GSEARCH_SEARCH_REQUESTDELAY` | `search.requestDelay` | Request delay (e.g., "2s") |
| `GSEARCH_SEARCH_TIMEOUT` | `search.timeout` | Request timeout |
| `GSEARCH_CACHE_TTLDAYS` | `cache.ttlDays` | Cache TTL in days |
| `GSEARCH_PROXY_ENABLED` | `proxy.enabled` | Enable proxy |
| `GSEARCH_PROXY_URL` | `proxy.url` | Proxy URL |
| `PROXY_URL` | (external) | Proxy URL (alternative) |
| `PROXY_USERNAME` | (external) | Proxy username |
| `PROXY_PASSWORD` | (external) | Proxy password |
| `BING_API_KEY` | (external) | Bing API key |
| `GOOGLE_APPLICATION_CREDENTIALS` | (external) | Google API credentials |
| `GSEARCH_TOKEN_KEY` | (external) | OAuth token encryption key |

---

## Validation

```go
func (c *Config) Validate() error {
    // Validate request delay
    if c.Search.RequestDelay.Duration < 500*time.Millisecond {
        return fmt.Errorf("search.requestDelay must be >= 500ms, got %v", c.Search.RequestDelay)
    }
    
    // Validate concurrency
    if c.Search.MaxConcurrent < 1 || c.Search.MaxConcurrent > 20 {
        return fmt.Errorf("search.maxConcurrent must be 1-20, got %d", c.Search.MaxConcurrent)
    }
    
    // Validate cache TTL
    if c.Cache.TTLDays < 1 {
        return fmt.Errorf("cache.ttlDays must be >= 1, got %d", c.Cache.TTLDays)
    }
    
    // Validate nested depth
    if c.Nested.MaxDepth < 1 || c.Nested.MaxDepth > 5 {
        return fmt.Errorf("nested.maxDepth must be 1-5, got %d", c.Nested.MaxDepth)
    }
    
    // Validate method weights
    if err := c.ValidateWeights(); err != nil {
        return err
    }
    
    // Validate backoff config
    if c.Backoff.Multiplier < 1.0 {
        return fmt.Errorf("backoff.multiplier must be >= 1.0, got %f", c.Backoff.Multiplier)
    }
    if c.Backoff.Jitter < 0.0 || c.Backoff.Jitter > 1.0 {
        return fmt.Errorf("backoff.jitter must be 0.0-1.0, got %f", c.Backoff.Jitter)
    }
    
    // Validate proxy config
    if err := c.ValidateProxy(); err != nil {
        return err
    }
    
    return nil
}

// ValidateWeights ensures all weights are 0.0-1.0 and sum to 1.0
func (c *Config) ValidateWeights() error {
    var total float64
    for method, weight := range c.Search.MethodWeights {
        if weight < 0.0 || weight > 1.0 {
            return fmt.Errorf("weight for %s must be 0.0-1.0, got %f", method, weight)
        }
        total += weight
    }
    
    // Allow small floating point tolerance
    if math.Abs(total-1.0) > 0.001 {
        return fmt.Errorf("search.methodWeights must sum to 1.0 (±0.001), got %f", total)
    }
    
    return nil
}

// ValidateProxy validates proxy configuration
func (c *Config) ValidateProxy() error {
    if !c.Proxy.Enabled {
        return nil
    }
    
    // Validate proxy type
    validTypes := map[string]bool{
        "http": true, "https": true, "socks5": true, "socks5h": true,
    }
    if !validTypes[c.Proxy.Type] {
        return fmt.Errorf("proxy.type must be http/https/socks5/socks5h, got %s", c.Proxy.Type)
    }
    
    // Validate rotation strategy
    if c.Proxy.Rotation.Enabled {
        validStrategies := map[string]bool{
            "round-robin": true, "random": true, "least-used": true,
            "failover": true, "weighted": true,
        }
        if !validStrategies[c.Proxy.Rotation.Strategy] {
            return fmt.Errorf("proxy.rotation.strategy must be one of: round-robin, random, least-used, failover, weighted")
        }
        
        if len(c.Proxy.Rotation.URLs) == 0 {
            return fmt.Errorf("proxy.rotation.urls must have at least one proxy when rotation is enabled")
        }
        
        // Validate weighted strategy has weights
        if c.Proxy.Rotation.Strategy == "weighted" {
            if len(c.Proxy.Rotation.Weights) == 0 {
                return fmt.Errorf("proxy.rotation.weights required for weighted strategy")
            }
        }
    } else if c.Proxy.URL == "" {
        return fmt.Errorf("proxy.url required when proxy is enabled without rotation")
    }
    
    return nil
}
```

---

## Usage Examples

### Single Proxy

```json
{
  "proxy": {
    "enabled": true,
    "type": "http",
    "url": "http://proxy.example.com:8080",
    "timeout": "10s"
  }
}
```

### Proxy with Authentication

```json
{
  "proxy": {
    "enabled": true,
    "type": "http",
    "url": "http://proxy.example.com:8080",
    "auth": {
      "enabled": true,
      "username": "${PROXY_USERNAME}",
      "password": "${PROXY_PASSWORD}"
    }
  }
}
```

### SOCKS5 Proxy

```json
{
  "proxy": {
    "enabled": true,
    "type": "socks5",
    "url": "socks5://127.0.0.1:1080"
  }
}
```

### Proxy Rotation

```json
{
  "proxy": {
    "enabled": true,
    "rotation": {
      "enabled": true,
      "strategy": "round-robin",
      "urls": [
        "http://proxy1.example.com:8080",
        "http://proxy2.example.com:8080",
        "http://proxy3.example.com:8080"
      ],
      "healthCheck": {
        "enabled": true,
        "interval": "5m",
        "timeout": "10s"
      }
    }
  }
}
```

### Weighted Proxy Rotation

```json
{
  "proxy": {
    "enabled": true,
    "rotation": {
      "enabled": true,
      "strategy": "weighted",
      "urls": [
        "http://fast-proxy.example.com:8080",
        "http://slow-proxy.example.com:8080"
      ],
      "weights": {
        "http://fast-proxy.example.com:8080": 0.8,
        "http://slow-proxy.example.com:8080": 0.2
      }
    }
  }
}
```

---

## Related Specs

- [CLI Framework](./01-cli-framework.md) — Command integration, shutdown config
- [Method Switching](./08-method-switching.md) — Weight-based selection
- [HTML Parser](./04-html-parser.md) — Selector config, proxy usage
- [Error Codes](./15-error-codes.md) — Proxy error codes (4009-4011)
- [Remediation Plan](./14-remediation-plan.md) — Phase 6 implementation

---

## Changelog

| Version | Date | Changes |
|---------|------|---------|
| 1.0.0 | 2026-01-28 | Initial configuration with Viper |
| 1.1.0 | 2026-01-28 | Normalized weights, standardized durations (Phase 1) |
| 1.2.0 | 2026-01-28 | Added proxy support with rotation strategies (Phase 6) |
