# 04 — Site Service

> **Parent:** [00-overview.md](../00-overview.md)  
> **Status:** Draft

---

## Overview

The Site Service manages WordPress site connections, including CRUD operations, credential encryption, and connection testing.

---

## Interface

```go
// internal/services/site/service.go
package site

import (
    "context"
    
    "wp-plugin-publish/internal/models"
)

type Service interface {
    // CRUD operations
    List(ctx context.Context) ([]models.Site, error)
    GetByID(ctx context.Context, id int64) (*models.Site, error)
    GetByURL(ctx context.Context, url string) (*models.Site, error)
    Create(ctx context.Context, input CreateInput) (*models.Site, error)
    Update(ctx context.Context, id int64, input UpdateInput) (*models.Site, error)
    Delete(ctx context.Context, id int64) error
    
    // Connection management
    TestConnection(ctx context.Context, id int64) (*ConnectionResult, error)
    TestCredentials(ctx context.Context, url, username, password string) (*ConnectionResult, error)
    
    // Status updates
    UpdateLastSync(ctx context.Context, id int64) error
    SetActive(ctx context.Context, id int64, active bool) error
}
```

---

## Data Types

### Site Model

```go
// internal/models/site.go
package models

import "time"

type Site struct {
    ID           int64      `json:"id"`
    Name         string     `json:"name"`
    URL          string     `json:"url"`
    Username     string     `json:"username"`
    AppPassword  string     `json:"-"`  // Never exposed in JSON
    IsActive     bool       `json:"isActive"`
    LastSyncAt   *time.Time `json:"lastSyncAt,omitempty"`
    CreatedAt    time.Time  `json:"createdAt"`
    UpdatedAt    time.Time  `json:"updatedAt"`
}

// SiteWithStatus includes connection status for UI display
type SiteWithStatus struct {
    Site
    IsConnected   bool   `json:"isConnected"`
    WPVersion     string `json:"wpVersion,omitempty"`
    PluginCount   int    `json:"pluginCount"`
    LastError     string `json:"lastError,omitempty"`
}
```

### Input Types

```go
// internal/services/site/types.go
package site

type CreateInput struct {
    Name        string `json:"name" validate:"required,max=255"`
    URL         string `json:"url" validate:"required,url,max=2048"`
    Username    string `json:"username" validate:"required,max=255"`
    AppPassword string `json:"appPassword" validate:"required"`
}

type UpdateInput struct {
    Name        *string `json:"name,omitempty" validate:"omitempty,max=255"`
    URL         *string `json:"url,omitempty" validate:"omitempty,url,max=2048"`
    Username    *string `json:"username,omitempty" validate:"omitempty,max=255"`
    AppPassword *string `json:"appPassword,omitempty"`
    IsActive    *bool   `json:"isActive,omitempty"`
}

type ConnectionResult struct {
    Success     bool   `json:"success"`
    WPVersion   string `json:"wpVersion,omitempty"`
    SiteName    string `json:"siteName,omitempty"`
    PluginCount int    `json:"pluginCount,omitempty"`
    Error       string `json:"error,omitempty"`
    ErrorCode   string `json:"errorCode,omitempty"`
}
```

---

## Implementation

### Service Constructor

```go
// internal/services/site/service.go
package site

import (
    "database/sql"
    
    "wp-plugin-publish/internal/logger"
    "wp-plugin-publish/internal/wordpress"
)

type serviceImpl struct {
    db        *sql.DB
    wpClient  *wordpress.Client
    log       *logger.Logger
    encKey    []byte  // AES-256 encryption key
}

func New(db *sql.DB, wpClient *wordpress.Client, log *logger.Logger, encKey []byte) Service {
    return &serviceImpl{
        db:       db,
        wpClient: wpClient,
        log:      log,
        encKey:   encKey,
    }
}
```

### CRUD Operations

```go
// internal/services/site/crud.go
package site

import (
    "context"
    "database/sql"
    "strings"
    "time"
    
    "wp-plugin-publish/internal/models"
    "wp-plugin-publish/pkg/apperror"
)

func (s *serviceImpl) List(ctx context.Context) ([]models.Site, error) {
    s.log.Debug("Listing all sites")
    
    rows, err := s.db.QueryContext(ctx, `
        SELECT Id, Name, Url, Username, AppPassword, IsActive, LastSyncAt, CreatedAt, UpdatedAt
        FROM Sites
        ORDER BY Name ASC
    `)
    if err != nil {
        return nil, apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to list sites")
    }
    defer rows.Close()
    
    var sites []models.Site
    for rows.Next() {
        var site models.Site
        var encryptedPassword string
        var lastSyncAt sql.NullString
        
        if err := rows.Scan(
            &site.ID, &site.Name, &site.URL, &site.Username,
            &encryptedPassword, &site.IsActive, &lastSyncAt,
            &site.CreatedAt, &site.UpdatedAt,
        ); err != nil {
            return nil, apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to scan site row")
        }
        
        // Decrypt password
        site.AppPassword, err = DecryptPassword(encryptedPassword, s.encKey)
        if err != nil {
            s.log.Warn("Failed to decrypt password for site", "site_id", site.ID)
        }
        
        if lastSyncAt.Valid {
            t, _ := time.Parse(time.RFC3339, lastSyncAt.String)
            site.LastSyncAt = &t
        }
        
        sites = append(sites, site)
    }
    
    s.log.Info("Listed sites", "count", len(sites))
    return sites, nil
}

func (s *serviceImpl) GetByID(ctx context.Context, id int64) (*models.Site, error) {
    s.log.Debug("Getting site by ID", "site_id", id)
    
    var site models.Site
    var encryptedPassword string
    var lastSyncAt sql.NullString
    
    err := s.db.QueryRowContext(ctx, `
        SELECT Id, Name, Url, Username, AppPassword, IsActive, LastSyncAt, CreatedAt, UpdatedAt
        FROM Sites WHERE Id = ?
    `, id).Scan(
        &site.ID, &site.Name, &site.URL, &site.Username,
        &encryptedPassword, &site.IsActive, &lastSyncAt,
        &site.CreatedAt, &site.UpdatedAt,
    )
    
    if err == sql.ErrNoRows {
        return nil, apperror.New(apperror.ErrNotFound, "site not found").
            WithContext("site_id", id)
    }
    if err != nil {
        return nil, apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to get site")
    }
    
    site.AppPassword, _ = DecryptPassword(encryptedPassword, s.encKey)
    
    if lastSyncAt.Valid {
        t, _ := time.Parse(time.RFC3339, lastSyncAt.String)
        site.LastSyncAt = &t
    }
    
    return &site, nil
}

func (s *serviceImpl) Create(ctx context.Context, input CreateInput) (*models.Site, error) {
    s.log.Info("Creating site", "name", input.Name, "url", input.URL)
    
    // Validate input
    if err := s.validateCreateInput(input); err != nil {
        return nil, err
    }
    
    // Normalize URL (remove trailing slash)
    url := strings.TrimSuffix(input.URL, "/")
    
    // Check for duplicate URL
    existing, _ := s.GetByURL(ctx, url)
    if existing != nil {
        return nil, apperror.New(apperror.ErrDuplicate, "site with this URL already exists").
            WithContext("url", url)
    }
    
    // Encrypt password
    encryptedPassword, err := EncryptPassword(input.AppPassword, s.encKey)
    if err != nil {
        return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to encrypt password")
    }
    
    // Insert site
    result, err := s.db.ExecContext(ctx, `
        INSERT INTO Sites (Name, Url, Username, AppPassword, IsActive, CreatedAt, UpdatedAt)
        VALUES (?, ?, ?, ?, 1, datetime('now'), datetime('now'))
    `, input.Name, url, input.Username, encryptedPassword)
    
    if err != nil {
        return nil, apperror.Wrap(err, apperror.ErrDatabaseExec, "failed to create site")
    }
    
    id, _ := result.LastInsertId()
    s.log.Info("Site created", "site_id", id, "name", input.Name)
    
    return s.GetByID(ctx, id)
}

func (s *serviceImpl) Update(ctx context.Context, id int64, input UpdateInput) (*models.Site, error) {
    s.log.Info("Updating site", "site_id", id)
    
    // Verify site exists
    existing, err := s.GetByID(ctx, id)
    if err != nil {
        return nil, err
    }
    
    // Build update query dynamically
    var updates []string
    var args []interface{}
    
    if input.Name != nil {
        updates = append(updates, "Name = ?")
        args = append(args, *input.Name)
    }
    if input.URL != nil {
        url := strings.TrimSuffix(*input.URL, "/")
        updates = append(updates, "Url = ?")
        args = append(args, url)
    }
    if input.Username != nil {
        updates = append(updates, "Username = ?")
        args = append(args, *input.Username)
    }
    if input.AppPassword != nil {
        encrypted, err := EncryptPassword(*input.AppPassword, s.encKey)
        if err != nil {
            return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to encrypt password")
        }
        updates = append(updates, "AppPassword = ?")
        args = append(args, encrypted)
    }
    if input.IsActive != nil {
        active := 0
        if *input.IsActive {
            active = 1
        }
        updates = append(updates, "IsActive = ?")
        args = append(args, active)
    }
    
    if len(updates) == 0 {
        return existing, nil
    }
    
    updates = append(updates, "UpdatedAt = datetime('now')")
    args = append(args, id)
    
    query := "UPDATE Sites SET " + strings.Join(updates, ", ") + " WHERE Id = ?"
    
    _, err = s.db.ExecContext(ctx, query, args...)
    if err != nil {
        return nil, apperror.Wrap(err, apperror.ErrDatabaseExec, "failed to update site")
    }
    
    s.log.Info("Site updated", "site_id", id)
    return s.GetByID(ctx, id)
}

func (s *serviceImpl) Delete(ctx context.Context, id int64) error {
    s.log.Info("Deleting site", "site_id", id)
    
    // Verify site exists
    if _, err := s.GetByID(ctx, id); err != nil {
        return err
    }
    
    // Delete (cascade will handle plugins)
    _, err := s.db.ExecContext(ctx, "DELETE FROM Sites WHERE Id = ?", id)
    if err != nil {
        return apperror.Wrap(err, apperror.ErrDatabaseExec, "failed to delete site")
    }
    
    s.log.Info("Site deleted", "site_id", id)
    return nil
}
```

### Connection Testing

```go
// internal/services/site/connection.go
package site

import (
    "context"
    
    "wp-plugin-publish/pkg/apperror"
)

func (s *serviceImpl) TestConnection(ctx context.Context, id int64) (*ConnectionResult, error) {
    s.log.Info("Testing connection", "site_id", id)
    
    site, err := s.GetByID(ctx, id)
    if err != nil {
        return nil, err
    }
    
    return s.TestCredentials(ctx, site.URL, site.Username, site.AppPassword)
}

func (s *serviceImpl) TestCredentials(ctx context.Context, url, username, password string) (*ConnectionResult, error) {
    s.log.Debug("Testing credentials", "url", url, "username", username)
    
    // Test connection via WP REST API
    info, err := s.wpClient.GetSiteInfo(ctx, url, username, password)
    if err != nil {
        appErr, ok := err.(*apperror.AppError)
        if ok {
            return &ConnectionResult{
                Success:   false,
                Error:     appErr.Message,
                ErrorCode: appErr.Code,
            }, nil
        }
        return &ConnectionResult{
            Success:   false,
            Error:     err.Error(),
            ErrorCode: apperror.ErrWPConnect,
        }, nil
    }
    
    // Get plugin count
    plugins, err := s.wpClient.ListPlugins(ctx, url, username, password)
    pluginCount := 0
    if err == nil {
        pluginCount = len(plugins)
    }
    
    s.log.Info("Connection test successful",
        "url", url,
        "wp_version", info.Version,
        "site_name", info.Name,
    )
    
    return &ConnectionResult{
        Success:     true,
        WPVersion:   info.Version,
        SiteName:    info.Name,
        PluginCount: pluginCount,
    }, nil
}

func (s *serviceImpl) UpdateLastSync(ctx context.Context, id int64) error {
    _, err := s.db.ExecContext(ctx,
        "UPDATE Sites SET LastSyncAt = datetime('now'), UpdatedAt = datetime('now') WHERE Id = ?",
        id,
    )
    if err != nil {
        return apperror.Wrap(err, apperror.ErrDatabaseExec, "failed to update last sync")
    }
    return nil
}

func (s *serviceImpl) SetActive(ctx context.Context, id int64, active bool) error {
    activeInt := 0
    if active {
        activeInt = 1
    }
    
    _, err := s.db.ExecContext(ctx,
        "UPDATE Sites SET IsActive = ?, UpdatedAt = datetime('now') WHERE Id = ?",
        activeInt, id,
    )
    if err != nil {
        return apperror.Wrap(err, apperror.ErrDatabaseExec, "failed to update site active status")
    }
    return nil
}
```

### Validation

```go
// internal/services/site/validator.go
package site

import (
    "net/url"
    "strings"
    
    "wp-plugin-publish/pkg/apperror"
)

func (s *serviceImpl) validateCreateInput(input CreateInput) error {
    if strings.TrimSpace(input.Name) == "" {
        return apperror.New(apperror.ErrValidationEmpty, "site name is required")
    }
    
    if len(input.Name) > 255 {
        return apperror.New(apperror.ErrValidationLength, "site name must be 255 characters or less")
    }
    
    if strings.TrimSpace(input.URL) == "" {
        return apperror.New(apperror.ErrValidationEmpty, "site URL is required")
    }
    
    parsedURL, err := url.Parse(input.URL)
    if err != nil || (parsedURL.Scheme != "http" && parsedURL.Scheme != "https") {
        return apperror.New(apperror.ErrValidationURL, "invalid site URL format")
    }
    
    if len(input.URL) > 2048 {
        return apperror.New(apperror.ErrValidationLength, "site URL must be 2048 characters or less")
    }
    
    if strings.TrimSpace(input.Username) == "" {
        return apperror.New(apperror.ErrValidationEmpty, "username is required")
    }
    
    if strings.TrimSpace(input.AppPassword) == "" {
        return apperror.New(apperror.ErrValidationEmpty, "application password is required")
    }
    
    // Normalize app password (remove spaces for validation)
    normalized := strings.ReplaceAll(input.AppPassword, " ", "")
    if len(normalized) != 24 {
        return apperror.New(apperror.ErrValidationFormat, 
            "application password must be 24 characters (format: xxxx xxxx xxxx xxxx xxxx xxxx)")
    }
    
    return nil
}
```

### Encryption

```go
// internal/services/site/encryption.go
package site

import (
    "crypto/aes"
    "crypto/cipher"
    "crypto/rand"
    "encoding/base64"
    "io"
    
    "wp-plugin-publish/pkg/apperror"
)

func EncryptPassword(plaintext string, key []byte) (string, error) {
    block, err := aes.NewCipher(key)
    if err != nil {
        return "", apperror.Wrap(err, apperror.ErrInternal, "failed to create cipher")
    }
    
    gcm, err := cipher.NewGCM(block)
    if err != nil {
        return "", apperror.Wrap(err, apperror.ErrInternal, "failed to create GCM")
    }
    
    nonce := make([]byte, gcm.NonceSize())
    if _, err := io.ReadFull(rand.Reader, nonce); err != nil {
        return "", apperror.Wrap(err, apperror.ErrInternal, "failed to generate nonce")
    }
    
    ciphertext := gcm.Seal(nonce, nonce, []byte(plaintext), nil)
    return base64.StdEncoding.EncodeToString(ciphertext), nil
}

func DecryptPassword(ciphertext string, key []byte) (string, error) {
    data, err := base64.StdEncoding.DecodeString(ciphertext)
    if err != nil {
        return "", apperror.Wrap(err, apperror.ErrInternal, "failed to decode ciphertext")
    }
    
    block, err := aes.NewCipher(key)
    if err != nil {
        return "", apperror.Wrap(err, apperror.ErrInternal, "failed to create cipher")
    }
    
    gcm, err := cipher.NewGCM(block)
    if err != nil {
        return "", apperror.Wrap(err, apperror.ErrInternal, "failed to create GCM")
    }
    
    if len(data) < gcm.NonceSize() {
        return "", apperror.New(apperror.ErrInternal, "ciphertext too short")
    }
    
    nonce := data[:gcm.NonceSize()]
    ciphertextBytes := data[gcm.NonceSize():]
    
    plaintext, err := gcm.Open(nil, nonce, ciphertextBytes, nil)
    if err != nil {
        return "", apperror.Wrap(err, apperror.ErrInternal, "failed to decrypt")
    }
    
    return string(plaintext), nil
}
```

---

## Error Scenarios

| Scenario | Error Code | HTTP Status |
|----------|------------|-------------|
| Site not found | E2005 | 404 |
| Duplicate URL | E2006 | 409 |
| Invalid URL format | E6002 | 400 |
| Empty required field | E6004 | 400 |
| Connection failed | E3001 | 502 |
| Auth failed | E3002 | 401 |

---

## Next Document

See [05-plugin-service.md](./05-plugin-service.md) for plugin management.
