# 10 — WP REST API Client

> **Parent:** [00-overview.md](../00-overview.md)  
> **Status:** Draft

---

## Overview

The WordPress REST API Client handles all communication with remote WordPress sites, including authentication, plugin management, and file uploads.

---

## Interface

```go
// internal/wordpress/client.go
package wordpress

import (
    "context"
)

type Client interface {
    // Site information
    GetSiteInfo(ctx context.Context, url, username, password string) (*SiteInfo, error)
    
    // Plugin operations
    ListPlugins(ctx context.Context, url, username, password string) ([]Plugin, error)
    GetPlugin(ctx context.Context, url, username, password, slug string) (*Plugin, error)
    ActivatePlugin(ctx context.Context, url, username, password, slug string) error
    DeactivatePlugin(ctx context.Context, url, username, password, slug string) error
    DeletePlugin(ctx context.Context, url, username, password, slug string) error
    
    // Upload operations
    UploadPlugin(ctx context.Context, url, username, password string, zipPath string) (*UploadResult, error)
    
    // Plugin files (if supported by a companion plugin)
    GetPluginFiles(ctx context.Context, url, username, password, slug string) ([]RemoteFile, error)
    UploadPluginFile(ctx context.Context, url, username, password, slug, filePath string, content []byte) error
    
    // Health check
    Ping(ctx context.Context, url string) error
}
```

---

## Data Types

```go
// internal/wordpress/types.go
package wordpress

type SiteInfo struct {
    Name        string `json:"name"`
    Description string `json:"description"`
    URL         string `json:"url"`
    Home        string `json:"home"`
    GMTOffset   int    `json:"gmt_offset"`
    Timezone    string `json:"timezone_string"`
    Version     string `json:"version,omitempty"`  // From /wp-json
}

type Plugin struct {
    Slug        string            `json:"slug,omitempty"`  // Derived from plugin file
    Plugin      string            `json:"plugin"`          // e.g., "my-plugin/my-plugin.php"
    Status      string            `json:"status"`          // "active", "inactive"
    Name        string            `json:"name"`
    PluginURI   string            `json:"plugin_uri"`
    Author      string            `json:"author"`
    AuthorURI   string            `json:"author_uri"`
    Description Description       `json:"description"`
    Version     string            `json:"version"`
    NetworkOnly bool              `json:"network_only"`
    RequiresWP  string            `json:"requires_wp"`
    RequiresPHP string            `json:"requires_php"`
    TextDomain  string            `json:"text_domain"`
}

type Description struct {
    Raw      string `json:"raw"`
    Rendered string `json:"rendered"`
}

type UploadResult struct {
    Success     bool   `json:"success"`
    PluginSlug  string `json:"pluginSlug,omitempty"`
    Version     string `json:"version,omitempty"`
    WasUpdated  bool   `json:"wasUpdated"`
    Error       string `json:"error,omitempty"`
}

type RemoteFile struct {
    Path       string `json:"path"`
    Size       int64  `json:"size"`
    Hash       string `json:"hash"`
    ModifiedAt string `json:"modifiedAt"`
}
```

---

## Implementation

### HTTP Client

```go
// internal/wordpress/client.go
package wordpress

import (
    "bytes"
    "context"
    "encoding/base64"
    "encoding/json"
    "fmt"
    "io"
    "mime/multipart"
    "net/http"
    "os"
    "path/filepath"
    "strings"
    "time"
    
    "wp-plugin-publish/internal/logger"
    "wp-plugin-publish/pkg/apperror"
)

type clientImpl struct {
    httpClient *http.Client
    log        *logger.Logger
}

func NewClient(log *logger.Logger) Client {
    return &clientImpl{
        httpClient: &http.Client{
            Timeout: 60 * time.Second,
        },
        log: log,
    }
}

func (c *clientImpl) doRequest(ctx context.Context, method, url, username, password string, body io.Reader, contentType string) (*http.Response, error) {
    req, err := http.NewRequestWithContext(ctx, method, url, body)
    if err != nil {
        return nil, apperror.Wrap(err, apperror.ErrWPConnect, "failed to create request")
    }
    
    // Set Application Password authentication
    auth := base64.StdEncoding.EncodeToString([]byte(username + ":" + password))
    req.Header.Set("Authorization", "Basic "+auth)
    
    if contentType != "" {
        req.Header.Set("Content-Type", contentType)
    }
    
    req.Header.Set("Accept", "application/json")
    req.Header.Set("User-Agent", "WP-Plugin-Publish/1.0")
    
    c.log.Debug("Making WP API request",
        "method", method,
        "url", url,
    )
    
    resp, err := c.httpClient.Do(req)
    if err != nil {
        return nil, apperror.Wrap(err, apperror.ErrWPConnect, "request failed")
    }
    
    return resp, nil
}

func (c *clientImpl) parseResponse(resp *http.Response, target interface{}) error {
    defer resp.Body.Close()
    
    body, err := io.ReadAll(resp.Body)
    if err != nil {
        return apperror.Wrap(err, apperror.ErrWPAPI, "failed to read response body")
    }
    
    // Check for error status codes
    if resp.StatusCode == 401 {
        return apperror.New(apperror.ErrWPAuth, "authentication failed - check username and application password").
            WithContext("status", resp.StatusCode)
    }
    
    if resp.StatusCode == 403 {
        return apperror.New(apperror.ErrWPAuth, "access forbidden - user may lack required permissions").
            WithContext("status", resp.StatusCode)
    }
    
    if resp.StatusCode == 404 {
        return apperror.New(apperror.ErrNotFound, "endpoint not found").
            WithContext("status", resp.StatusCode)
    }
    
    if resp.StatusCode >= 400 {
        // Try to parse WP error response
        var wpErr struct {
            Code    string `json:"code"`
            Message string `json:"message"`
        }
        if json.Unmarshal(body, &wpErr) == nil && wpErr.Message != "" {
            return apperror.New(apperror.ErrWPAPI, wpErr.Message).
                WithContext("wp_code", wpErr.Code).
                WithContext("status", resp.StatusCode)
        }
        
        return apperror.New(apperror.ErrWPAPI, "WordPress API error").
            WithContext("status", resp.StatusCode).
            WithContext("body", string(body))
    }
    
    if target != nil {
        if err := json.Unmarshal(body, target); err != nil {
            return apperror.Wrap(err, apperror.ErrWPAPI, "failed to parse response")
        }
    }
    
    return nil
}
```

### Site Information

```go
// internal/wordpress/site.go
package wordpress

import (
    "context"
    "strings"
    
    "wp-plugin-publish/pkg/apperror"
)

func (c *clientImpl) GetSiteInfo(ctx context.Context, url, username, password string) (*SiteInfo, error) {
    c.log.Info("Getting site info", "url", url)
    
    // Normalize URL
    url = strings.TrimSuffix(url, "/")
    
    // First, get basic site info from /wp-json
    resp, err := c.doRequest(ctx, "GET", url+"/wp-json", username, password, nil, "")
    if err != nil {
        return nil, err
    }
    
    var indexResponse struct {
        Name        string `json:"name"`
        Description string `json:"description"`
        URL         string `json:"url"`
        Home        string `json:"home"`
        GMTOffset   int    `json:"gmt_offset"`
        Timezone    string `json:"timezone_string"`
        Namespaces  []string `json:"namespaces"`
    }
    
    if err := c.parseResponse(resp, &indexResponse); err != nil {
        return nil, err
    }
    
    // Check if wp/v2 namespace is available
    hasWPv2 := false
    for _, ns := range indexResponse.Namespaces {
        if ns == "wp/v2" {
            hasWPv2 = true
            break
        }
    }
    
    if !hasWPv2 {
        return nil, apperror.New(apperror.ErrWPVersion, 
            "WordPress REST API v2 not available - WordPress 4.7+ required")
    }
    
    // Get WordPress version from root namespace
    version := ""
    resp2, err := c.doRequest(ctx, "GET", url+"/wp-json/wp/v2", username, password, nil, "")
    if err == nil {
        var v2Info struct {
            Version string `json:"wp_version"`
        }
        if c.parseResponse(resp2, &v2Info) == nil {
            version = v2Info.Version
        }
    }
    
    return &SiteInfo{
        Name:        indexResponse.Name,
        Description: indexResponse.Description,
        URL:         indexResponse.URL,
        Home:        indexResponse.Home,
        GMTOffset:   indexResponse.GMTOffset,
        Timezone:    indexResponse.Timezone,
        Version:     version,
    }, nil
}

func (c *clientImpl) Ping(ctx context.Context, url string) error {
    url = strings.TrimSuffix(url, "/")
    
    resp, err := c.httpClient.Get(url + "/wp-json")
    if err != nil {
        return apperror.Wrap(err, apperror.ErrWPConnect, "failed to ping site")
    }
    defer resp.Body.Close()
    
    if resp.StatusCode >= 400 {
        return apperror.New(apperror.ErrWPConnect, "site not reachable").
            WithContext("status", resp.StatusCode)
    }
    
    return nil
}
```

### Plugin Operations

```go
// internal/wordpress/plugins.go
package wordpress

import (
    "bytes"
    "context"
    "encoding/json"
    "strings"
    
    "wp-plugin-publish/pkg/apperror"
)

func (c *clientImpl) ListPlugins(ctx context.Context, url, username, password string) ([]Plugin, error) {
    c.log.Debug("Listing plugins", "url", url)
    
    url = strings.TrimSuffix(url, "/")
    
    resp, err := c.doRequest(ctx, "GET", url+"/wp-json/wp/v2/plugins", username, password, nil, "")
    if err != nil {
        return nil, err
    }
    
    var plugins []Plugin
    if err := c.parseResponse(resp, &plugins); err != nil {
        return nil, err
    }
    
    // Extract slug from plugin file path
    for i := range plugins {
        if plugins[i].Plugin != "" {
            parts := strings.Split(plugins[i].Plugin, "/")
            if len(parts) > 0 {
                plugins[i].Slug = parts[0]
            }
        }
    }
    
    c.log.Info("Listed plugins", "url", url, "count", len(plugins))
    return plugins, nil
}

func (c *clientImpl) GetPlugin(ctx context.Context, url, username, password, slug string) (*Plugin, error) {
    c.log.Debug("Getting plugin", "url", url, "slug", slug)
    
    plugins, err := c.ListPlugins(ctx, url, username, password)
    if err != nil {
        return nil, err
    }
    
    for _, p := range plugins {
        if p.Slug == slug || strings.HasPrefix(p.Plugin, slug+"/") {
            return &p, nil
        }
    }
    
    return nil, apperror.New(apperror.ErrNotFound, "plugin not found on remote site").
        WithContext("slug", slug)
}

func (c *clientImpl) ActivatePlugin(ctx context.Context, url, username, password, slug string) error {
    c.log.Info("Activating plugin", "url", url, "slug", slug)
    
    plugin, err := c.GetPlugin(ctx, url, username, password, slug)
    if err != nil {
        return err
    }
    
    if plugin.Status == "active" {
        c.log.Debug("Plugin already active", "slug", slug)
        return nil
    }
    
    url = strings.TrimSuffix(url, "/")
    endpoint := url + "/wp-json/wp/v2/plugins/" + plugin.Plugin
    
    body, _ := json.Marshal(map[string]string{"status": "active"})
    
    resp, err := c.doRequest(ctx, "PUT", endpoint, username, password, bytes.NewReader(body), "application/json")
    if err != nil {
        return apperror.Wrap(err, apperror.ErrWPActivate, "failed to activate plugin")
    }
    
    if err := c.parseResponse(resp, nil); err != nil {
        return apperror.Wrap(err, apperror.ErrWPActivate, "plugin activation failed")
    }
    
    c.log.Info("Plugin activated", "slug", slug)
    return nil
}

func (c *clientImpl) DeactivatePlugin(ctx context.Context, url, username, password, slug string) error {
    c.log.Info("Deactivating plugin", "url", url, "slug", slug)
    
    plugin, err := c.GetPlugin(ctx, url, username, password, slug)
    if err != nil {
        return err
    }
    
    if plugin.Status == "inactive" {
        c.log.Debug("Plugin already inactive", "slug", slug)
        return nil
    }
    
    url = strings.TrimSuffix(url, "/")
    endpoint := url + "/wp-json/wp/v2/plugins/" + plugin.Plugin
    
    body, _ := json.Marshal(map[string]string{"status": "inactive"})
    
    resp, err := c.doRequest(ctx, "PUT", endpoint, username, password, bytes.NewReader(body), "application/json")
    if err != nil {
        return apperror.Wrap(err, apperror.ErrWPDeactivate, "failed to deactivate plugin")
    }
    
    if err := c.parseResponse(resp, nil); err != nil {
        return apperror.Wrap(err, apperror.ErrWPDeactivate, "plugin deactivation failed")
    }
    
    c.log.Info("Plugin deactivated", "slug", slug)
    return nil
}

func (c *clientImpl) DeletePlugin(ctx context.Context, url, username, password, slug string) error {
    c.log.Info("Deleting plugin", "url", url, "slug", slug)
    
    plugin, err := c.GetPlugin(ctx, url, username, password, slug)
    if err != nil {
        return err
    }
    
    // Must deactivate first
    if plugin.Status == "active" {
        if err := c.DeactivatePlugin(ctx, url, username, password, slug); err != nil {
            return err
        }
    }
    
    url = strings.TrimSuffix(url, "/")
    endpoint := url + "/wp-json/wp/v2/plugins/" + plugin.Plugin
    
    resp, err := c.doRequest(ctx, "DELETE", endpoint, username, password, nil, "")
    if err != nil {
        return apperror.Wrap(err, apperror.ErrWPPlugin, "failed to delete plugin")
    }
    
    if err := c.parseResponse(resp, nil); err != nil {
        return apperror.Wrap(err, apperror.ErrWPPlugin, "plugin deletion failed")
    }
    
    c.log.Info("Plugin deleted", "slug", slug)
    return nil
}
```

### Upload Operations

```go
// internal/wordpress/upload.go
package wordpress

import (
    "bytes"
    "context"
    "io"
    "mime/multipart"
    "os"
    "path/filepath"
    "strings"
    
    "wp-plugin-publish/pkg/apperror"
)

func (c *clientImpl) UploadPlugin(ctx context.Context, url, username, password string, zipPath string) (*UploadResult, error) {
    c.log.Info("Uploading plugin", "url", url, "zip", zipPath)
    
    // Open the zip file
    file, err := os.Open(zipPath)
    if err != nil {
        return nil, apperror.Wrap(err, apperror.ErrFileRead, "failed to open zip file")
    }
    defer file.Close()
    
    // Get file info for size
    stat, err := file.Stat()
    if err != nil {
        return nil, apperror.Wrap(err, apperror.ErrFileRead, "failed to stat zip file")
    }
    
    c.log.Debug("Uploading plugin zip",
        "size", stat.Size(),
        "filename", filepath.Base(zipPath),
    )
    
    // Create multipart form
    var body bytes.Buffer
    writer := multipart.NewWriter(&body)
    
    // Add the file
    part, err := writer.CreateFormFile("pluginzip", filepath.Base(zipPath))
    if err != nil {
        return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to create form file")
    }
    
    if _, err := io.Copy(part, file); err != nil {
        return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to copy file content")
    }
    
    // Add overwrite flag
    writer.WriteField("overwrite", "true")
    
    writer.Close()
    
    // Make the upload request
    url = strings.TrimSuffix(url, "/")
    endpoint := url + "/wp-json/wp/v2/plugins"
    
    resp, err := c.doRequest(ctx, "POST", endpoint, username, password, &body, writer.FormDataContentType())
    if err != nil {
        return nil, apperror.Wrap(err, apperror.ErrWPUpload, "failed to upload plugin")
    }
    
    var uploadResp struct {
        Plugin  string `json:"plugin"`
        Status  string `json:"status"`
        Name    string `json:"name"`
        Version string `json:"version"`
    }
    
    if err := c.parseResponse(resp, &uploadResp); err != nil {
        return &UploadResult{
            Success: false,
            Error:   err.Error(),
        }, nil
    }
    
    // Extract slug from plugin path
    slug := ""
    if uploadResp.Plugin != "" {
        parts := strings.Split(uploadResp.Plugin, "/")
        if len(parts) > 0 {
            slug = parts[0]
        }
    }
    
    c.log.Info("Plugin uploaded successfully",
        "slug", slug,
        "version", uploadResp.Version,
    )
    
    return &UploadResult{
        Success:    true,
        PluginSlug: slug,
        Version:    uploadResp.Version,
        WasUpdated: true,
    }, nil
}

// GetPluginFiles requires a companion WP plugin to expose file information
func (c *clientImpl) GetPluginFiles(ctx context.Context, url, username, password, slug string) ([]RemoteFile, error) {
    c.log.Debug("Getting plugin files", "url", url, "slug", slug)
    
    // This requires a custom endpoint - the standard WP REST API doesn't expose plugin files
    url = strings.TrimSuffix(url, "/")
    endpoint := url + "/wp-json/wp-plugin-publish/v1/plugins/" + slug + "/files"
    
    resp, err := c.doRequest(ctx, "GET", endpoint, username, password, nil, "")
    if err != nil {
        return nil, apperror.Wrap(err, apperror.ErrWPAPI, 
            "failed to get plugin files - wp-plugin-publish companion plugin may not be installed")
    }
    
    var files []RemoteFile
    if err := c.parseResponse(resp, &files); err != nil {
        return nil, err
    }
    
    return files, nil
}

// UploadPluginFile requires a companion WP plugin
func (c *clientImpl) UploadPluginFile(ctx context.Context, url, username, password, slug, filePath string, content []byte) error {
    c.log.Debug("Uploading single file", "url", url, "slug", slug, "file", filePath)
    
    // This requires a custom endpoint
    url = strings.TrimSuffix(url, "/")
    endpoint := url + "/wp-json/wp-plugin-publish/v1/plugins/" + slug + "/files"
    
    var body bytes.Buffer
    writer := multipart.NewWriter(&body)
    
    writer.WriteField("path", filePath)
    
    part, err := writer.CreateFormFile("file", filepath.Base(filePath))
    if err != nil {
        return apperror.Wrap(err, apperror.ErrInternal, "failed to create form file")
    }
    
    if _, err := part.Write(content); err != nil {
        return apperror.Wrap(err, apperror.ErrInternal, "failed to write file content")
    }
    
    writer.Close()
    
    resp, err := c.doRequest(ctx, "POST", endpoint, username, password, &body, writer.FormDataContentType())
    if err != nil {
        return apperror.Wrap(err, apperror.ErrWPUpload, 
            "failed to upload file - wp-plugin-publish companion plugin may not be installed")
    }
    
    return c.parseResponse(resp, nil)
}
```

---

## Error Handling

| HTTP Status | Error Code | Meaning |
|-------------|------------|---------|
| 401 | E3002 | Invalid credentials |
| 403 | E3002 | Insufficient permissions |
| 404 | E2005 | Plugin/endpoint not found |
| 500+ | E3003 | WordPress server error |

---

## Rate Limiting

The client respects WordPress rate limiting:

```go
// internal/wordpress/ratelimit.go
package wordpress

import (
    "sync"
    "time"
)

type RateLimiter struct {
    mu          sync.Mutex
    lastRequest map[string]time.Time
    minInterval time.Duration
}

func NewRateLimiter(minInterval time.Duration) *RateLimiter {
    return &RateLimiter{
        lastRequest: make(map[string]time.Time),
        minInterval: minInterval,
    }
}

func (rl *RateLimiter) Wait(host string) {
    rl.mu.Lock()
    defer rl.mu.Unlock()
    
    if last, ok := rl.lastRequest[host]; ok {
        elapsed := time.Since(last)
        if elapsed < rl.minInterval {
            time.Sleep(rl.minInterval - elapsed)
        }
    }
    
    rl.lastRequest[host] = time.Now()
}
```

---

## Next Document

See [11-rest-api-endpoints.md](./11-rest-api-endpoints.md) for backend HTTP API.
