# Implementation Guidelines

**Version:** 2.0.0  
**Status:** Active  
**Last Updated:** 2026-01-27

---

## Overview

This document provides AI-friendly implementation checklists broken into small, atomic phases. Each phase should take 1-2 hours and can be verified independently.

**Target Audience:** AI coding agents, developers  
**Prerequisites:** All specification documents (Phases 1-9) complete

---

## Phase Index

### Backend Foundation (Phases A1-A12)

| Phase | Name | Est. Time | Dependencies |
|-------|------|-----------|--------------|
| A1 | Go Project Scaffold | 30 min | None |
| A2 | SQLite Connection | 30 min | A1 |
| A3 | Migration Runner | 45 min | A2 |
| A4 | Core Tables Migration | 45 min | A3 |
| A5 | Model Definitions | 45 min | A4 |
| A6 | Configuration System | 45 min | A1 |
| A7 | Password Hashing | 30 min | A1 |
| A8 | JWT Token Utils | 45 min | A6 |
| A9 | User Repository | 45 min | A5 |
| A10 | Session Repository | 30 min | A5 |
| A11 | Auth Service | 60 min | A7, A8, A9, A10 |
| A12 | Auth Handlers | 60 min | A11 |

### Backend API (Phases B1-B10)

| Phase | Name | Est. Time | Dependencies |
|-------|------|-----------|--------------|
| B1 | Middleware Setup | 45 min | A12 |
| B2 | Response Utilities | 30 min | A1 |
| B3 | Error Types | 30 min | A1 |
| B4 | Validation Utils | 45 min | B3 |
| B5 | Project Repository | 45 min | A5 |
| B6 | Project Service | 45 min | B5 |
| B7 | Project Handlers | 45 min | B1, B2, B6 |
| B8 | File Repository | 45 min | A5 |
| B9 | File Service | 60 min | B8 |
| B10 | File Handlers | 45 min | B1, B2, B9 |

### File Sync & History (Phases C1-C8)

| Phase | Name | Est. Time | Dependencies |
|-------|------|-----------|--------------|
| C1 | Hash Utilities | 30 min | A1 |
| C2 | FS Read/Write | 45 min | C1 |
| C3 | Sync Service Core | 60 min | B9, C2 |
| C4 | FSNotify Watcher | 45 min | C3 |
| C5 | Metadata Sync | 45 min | C3 |
| C6 | Snapshot Repository | 30 min | A5 |
| C7 | Snapshot Service | 60 min | C2, C6 |
| C8 | Snapshot Handlers | 45 min | B1, C7 |

### Git Integration (Phases D1-D4)

| Phase | Name | Est. Time | Dependencies |
|-------|------|-----------|--------------|
| D1 | Git Service Init | 45 min | A1 |
| D2 | Git Commit/Push | 45 min | D1 |
| D3 | Git Status | 30 min | D1 |
| D4 | Git Handlers | 45 min | B1, D2, D3 |

### Frontend Foundation (Phases E1-E10)

| Phase | Name | Est. Time | Dependencies |
|-------|------|-----------|--------------|
| E1 | React Project Scaffold | 30 min | None |
| E2 | Tailwind + Design Tokens | 45 min | E1 |
| E3 | Theme Store | 30 min | E2 |
| E4 | Theme Switcher Component | 30 min | E3 |
| E5 | API Client Base | 45 min | E1 |
| E6 | Auth Store | 30 min | E5 |
| E7 | Auth Hooks | 45 min | E5, E6 |
| E8 | Router Setup | 45 min | E1 |
| E9 | Layout Components | 45 min | E2, E8 |
| E10 | Auth Pages | 60 min | E7, E9 |

### Frontend Editor (Phases F1-F10)

| Phase | Name | Est. Time | Dependencies |
|-------|------|-----------|--------------|
| F1 | Project Hooks | 30 min | E5 |
| F2 | Project Card | 30 min | E2 |
| F3 | Project Grid | 30 min | F1, F2 |
| F4 | Dashboard Page | 45 min | F3, E9 |
| F5 | File Hooks | 30 min | E5 |
| F6 | Folder Tree Node | 45 min | E2 |
| F7 | Folder Tree Component | 60 min | F5, F6 |
| F8 | CodeMirror Setup | 45 min | E1 |
| F9 | Markdown Editor | 60 min | F8 |
| F10 | Editor Page | 60 min | F7, F9, E9 |

### History & Sync UI (Phases G1-G6)

| Phase | Name | Est. Time | Dependencies |
|-------|------|-----------|--------------|
| G1 | Snapshot Hooks | 30 min | E5 |
| G2 | Snapshot Card | 30 min | E2 |
| G3 | History Panel | 45 min | G1, G2 |
| G4 | Diff Viewer | 60 min | E2 |
| G5 | Folder Sync Wizard | 60 min | F1, E9 |
| G6 | Sync Status Banner | 30 min | G5 |

### AI Backend (Phases H1-H8)

| Phase | Name | Est. Time | Dependencies |
|-------|------|-----------|--------------|
| H1 | LLaMA Process Manager | 60 min | A6 |
| H2 | Transcription Service | 45 min | H1 |
| H3 | Completion Service | 45 min | H1 |
| H4 | AI Handlers | 45 min | B1, H2, H3 |
| H5 | Instruction Repository | 30 min | A5 |
| H6 | Instruction Service | 60 min | H3, H5 |
| H7 | Instruction Handlers | 45 min | B1, H6 |
| H8 | Task Repository | 30 min | A5 |

### AI Frontend (Phases I1-I6)

| Phase | Name | Est. Time | Dependencies |
|-------|------|-----------|--------------|
| I1 | Voice Recording Hook | 45 min | E1 |
| I2 | Voice Input Component | 45 min | I1 |
| I3 | Instruction Hooks | 30 min | E5 |
| I4 | Task Preview Component | 30 min | E2 |
| I5 | Chat Message Component | 30 min | E2 |
| I6 | AI Chat Panel | 60 min | I3, I4, I5 |

### History & Rollback (Phases J1-J4)

| Phase | Name | Est. Time | Dependencies |
|-------|------|-----------|--------------|
| J1 | FileChange Repository | 30 min | A5 |
| J2 | History Service | 45 min | J1 |
| J3 | Rollback Service | 60 min | J2 |
| J4 | History Handlers | 45 min | B1, J3 |

### Consistency & Testing (Phases K1-K6)

| Phase | Name | Est. Time | Dependencies |
|-------|------|-----------|--------------|
| K1 | Link Validator | 45 min | A1 |
| K2 | Schema Validator | 45 min | A1 |
| K3 | Consistency Service | 60 min | K1, K2 |
| K4 | Consistency Handlers | 45 min | B1, K3 |
| K5 | Backend Tests | 120 min | All backend |
| K6 | Frontend Tests | 120 min | All frontend |

---

## Phase A1: Go Project Scaffold

**Goal:** Create empty Go project with folder structure

**Checklist:**
- [ ] Create project folder `go-backend/`
- [ ] Run `go mod init spec-manager`
- [ ] Create folder structure:
  ```
  go-backend/
  ├── cmd/
  │   └── server/
  │       └── main.go
  ├── internal/
  │   ├── config/
  │   ├── handlers/
  │   │   └── dto/
  │   ├── services/
  │   ├── models/
  │   ├── repository/
  │   ├── middleware/
  │   └── utils/
  ├── migrations/
  ├── data/
  └── testdata/
  ```
- [ ] Create minimal `main.go`:
  ```go
  package main

  import (
      "log"
      "github.com/gin-gonic/gin"
  )

  func main() {
      r := gin.Default()
      r.GET("/health", func(c *gin.Context) {
          c.JSON(200, gin.H{"status": "ok"})
      })
      log.Fatal(r.Run(":8080"))
  }
  ```
- [ ] Add dependencies:
  ```
  go get github.com/gin-gonic/gin@v1.9.1
  go get github.com/mattn/go-sqlite3@v1.14.22
  go get github.com/golang-jwt/jwt/v5@v5.2.1
  go get github.com/fsnotify/fsnotify@v1.7.0
  go get github.com/go-git/go-git/v5@v5.11.0
  go get golang.org/x/crypto
  go get github.com/google/uuid@v1.6.0
  ```
- [ ] Create `.env.example`:
  ```
  SERVER_PORT=8080
  DATABASE_PATH=./data/spec.db
  JWT_SECRET=change-me-in-production
  SPEC_ROOT_PATH=./specs
  ```

**Verify:** `go build ./...` succeeds, `go run cmd/server/main.go` starts server

---

## Phase A2: SQLite Connection

**Goal:** Create database connection pool with proper initialization

**Checklist:**
- [ ] Create `internal/repository/sqlite.go`:
  ```go
  package repository

  import (
      "database/sql"
      _ "github.com/mattn/go-sqlite3"
  )

  type DB struct {
      *sql.DB
  }

  func NewDB(path string) (*DB, error) {
      db, err := sql.Open("sqlite3", path+"?_foreign_keys=on&_journal_mode=WAL")
      if err != nil {
          return nil, err
      }
      
      // Set connection pool settings
      db.SetMaxOpenConns(1) // SQLite only supports 1 writer
      db.SetMaxIdleConns(1)
      
      // Test connection
      if err := db.Ping(); err != nil {
          return nil, err
      }
      
      return &DB{db}, nil
  }

  func (db *DB) Close() error {
      return db.DB.Close()
  }
  ```
- [ ] Create `data/` directory with `.gitkeep`
- [ ] Update `main.go` to initialize DB on startup

**Verify:** Server starts and creates empty database file

---

## Phase A3: Database Initialization with GORM

**Goal:** Initialize database with GORM AutoMigrate

> **ORM Policy**: All database operations use GORM. Raw SQL is forbidden.

**Checklist:**
- [ ] Create `internal/db/init.go`:
  ```go
  package db

  import (
      "gorm.io/driver/sqlite"
      "gorm.io/gorm"
      "gorm.io/gorm/logger"
      "spec-manager/internal/models"
  )

  // Connect opens a GORM connection to SQLite
  func Connect(dbPath string) (*gorm.DB, error) {
      db, err := gorm.Open(sqlite.Open(dbPath), &gorm.Config{
          Logger: logger.Default.LogMode(logger.Info),
      })
      if err != nil {
          return nil, err
      }
      return db, nil
  }

  // InitDatabase creates/updates all tables using AutoMigrate
  // This replaces manual SQL migrations
  func InitDatabase(db *gorm.DB) error {
      return db.AutoMigrate(
          &models.User{},
          &models.Session{},
          &models.Project{},
          &models.File{},
          &models.Config{},
          &models.Snapshot{},
          &models.PromptPreset{},
          &models.PromptPresetVersion{},
          &models.UserPromptOverride{},
          &models.Instruction{},
          &models.InstructionTask{},
          &models.InconsistencyReport{},
          &models.InconsistencyIssue{},
          &models.ClarificationQuestion{},
          &models.ClarificationAnswer{},
          &models.RegenerationEvent{},
      )
  }

  // SchemaMigration tracks applied migrations (for complex versioned changes)
  type SchemaMigration struct {
      Version   int    `gorm:"primaryKey"`
      Name      string `gorm:"not null"`
      AppliedAt string `gorm:"not null;default:CURRENT_TIMESTAMP"`
  }

  // RunVersionedMigrations handles complex migrations that AutoMigrate can't do
  func RunVersionedMigrations(db *gorm.DB) error {
      // Ensure migration tracking table exists
      if err := db.AutoMigrate(&SchemaMigration{}); err != nil {
          return err
      }

      // Get current version
      var current SchemaMigration
      db.Order("version DESC").First(&current)

      // Run pending migrations
      migrations := []struct {
          Version int
          Name    string
          Migrate func(*gorm.DB) error
      }{
          {1, "initial_schema", func(db *gorm.DB) error { return nil }}, // Handled by AutoMigrate
          {2, "add_avatar_url", func(db *gorm.DB) error {
              // Example: Add column if missing
              if !db.Migrator().HasColumn(&models.User{}, "AvatarUrl") {
                  return db.Migrator().AddColumn(&models.User{}, "AvatarUrl")
              }
              return nil
          }},
      }

      for _, m := range migrations {
          if m.Version <= current.Version {
              continue
          }

          err := db.Transaction(func(tx *gorm.DB) error {
              if err := m.Migrate(tx); err != nil {
                  return err
              }
              return tx.Create(&SchemaMigration{
                  Version: m.Version,
                  Name:    m.Name,
              }).Error
          })
          if err != nil {
              return err
          }
      }

      return nil
  }
  ```

**Verify:** AutoMigrate creates all tables correctly; versioned migrations run in order

---

## Phase A4: Core Tables - GORM Models

**Goal:** Define GORM models for all core entities

> **ORM Policy**: All database operations use GORM. Raw SQL is forbidden.

**Checklist:**
- [ ] Create `internal/models/user.go`:
  ```go
  package models

  import (
      "time"
      "gorm.io/gorm"
  )

  // User represents an authenticated user
  type User struct {
      Id              string         `gorm:"primaryKey;type:text"`
      Username        string         `gorm:"type:text;not null;uniqueIndex"`
      Email           string         `gorm:"type:text;not null;uniqueIndex"`
      PasswordHash    string         `gorm:"type:text;not null"`
      DisplayName     string         `gorm:"type:text"`
      ThemePreference string         `gorm:"type:text;default:'light'"`
      CreatedAt       time.Time      `gorm:"not null"`
      UpdatedAt       time.Time      `gorm:"not null"`
      LastLoginAt     *time.Time
      DeletedAt       gorm.DeletedAt `gorm:"index"`
      
      // Relationships
      Sessions []Session `gorm:"foreignKey:UserId;constraint:OnDelete:CASCADE"`
      Projects []Project `gorm:"foreignKey:OwnerId;constraint:OnDelete:CASCADE"`
  }

  // Session represents an authenticated session
  type Session struct {
      Id         string     `gorm:"primaryKey;type:text"`
      UserId     string     `gorm:"type:text;not null;index"`
      TokenHash  string     `gorm:"type:text;not null;uniqueIndex"`
      DeviceInfo string     `gorm:"type:text"`
      ExpiresAt  time.Time  `gorm:"not null"`
      CreatedAt  time.Time  `gorm:"not null"`
      RevokedAt  *time.Time
      
      // Relationships
      User User `gorm:"foreignKey:UserId;constraint:OnDelete:CASCADE"`
  }

  // Project represents a spec project or category
  type Project struct {
      Id          string    `gorm:"primaryKey;type:text"`
      ParentId    *string   `gorm:"type:text;index"`
      OwnerId     string    `gorm:"type:text;not null;index"`
      Name        string    `gorm:"type:text;not null"`
      Slug        string    `gorm:"type:text;not null;uniqueIndex"`
      Path        string    `gorm:"type:text;not null;uniqueIndex"`
      Type        string    `gorm:"type:text;not null"` // spec, category
      Description string    `gorm:"type:text"`
      SortOrder   int       `gorm:"default:0"`
      CreatedAt   time.Time `gorm:"not null"`
      UpdatedAt   time.Time `gorm:"not null"`
      
      // Relationships
      Parent   *Project  `gorm:"foreignKey:ParentId;constraint:OnDelete:CASCADE"`
      Children []Project `gorm:"foreignKey:ParentId"`
      Owner    User      `gorm:"foreignKey:OwnerId;constraint:OnDelete:CASCADE"`
      Files    []File    `gorm:"foreignKey:ProjectId;constraint:OnDelete:CASCADE"`
  }

  // File represents a file or folder in a project
  type File struct {
      Id          string    `gorm:"primaryKey;type:text"`
      ProjectId   string    `gorm:"type:text;not null;index;uniqueIndex:idx_file_project_path,priority:1"`
      ParentId    *string   `gorm:"type:text;index"`
      Name        string    `gorm:"type:text;not null"`
      Path        string    `gorm:"type:text;not null;uniqueIndex:idx_file_project_path,priority:2"`
      Type        string    `gorm:"type:text;not null"` // file, folder
      ContentHash string    `gorm:"type:text"`
      SortOrder   int       `gorm:"default:0"`
      CreatedAt   time.Time `gorm:"not null"`
      UpdatedAt   time.Time `gorm:"not null"`
      
      // Relationships
      Project  Project `gorm:"foreignKey:ProjectId;constraint:OnDelete:CASCADE"`
      Parent   *File   `gorm:"foreignKey:ParentId;constraint:OnDelete:CASCADE"`
      Children []File  `gorm:"foreignKey:ParentId"`
  }

  // Config stores system configuration key-value pairs
  type Config struct {
      Key         string    `gorm:"primaryKey;type:text"`
      Value       string    `gorm:"type:text;not null"`
      Source      string    `gorm:"type:text;default:'database'"`
      Description string    `gorm:"type:text"`
      UpdatedAt   time.Time `gorm:"not null"`
  }

  // Snapshot represents a point-in-time backup of a project
  type Snapshot struct {
      Id          string    `gorm:"primaryKey;type:text"`
      ProjectId   string    `gorm:"type:text;not null;index"`
      CreatedById *string   `gorm:"type:text"`
      Name        string    `gorm:"type:text;not null"`
      Description string    `gorm:"type:text"`
      FolderPath  string    `gorm:"type:text;not null"`
      CreatedAt   time.Time `gorm:"not null"`
      
      // Relationships
      Project   Project `gorm:"foreignKey:ProjectId;constraint:OnDelete:CASCADE"`
      CreatedBy *User   `gorm:"foreignKey:CreatedById;constraint:OnDelete:SET NULL"`
  }
  ```
- [ ] Run `db.AutoMigrate()` in main.go on startup

**Verify:** All tables created with correct schema, indexes exist

---

## Phase A5: Model Definitions

**Goal:** Create Go struct definitions for all database entities

**Checklist:**
- [ ] Create `internal/models/user.go`:
  ```go
  package models

  import "time"

  type User struct {
      Id              string     `json:"id"`
      Username        string     `json:"username"`
      Email           string     `json:"email"`
      PasswordHash    string     `json:"-"` // Never expose
      DisplayName     *string    `json:"displayName"`
      ThemePreference string     `json:"themePreference"`
      CreatedAt       time.Time  `json:"createdAt"`
      UpdatedAt       time.Time  `json:"updatedAt"`
      LastLoginAt     *time.Time `json:"lastLoginAt"`
  }
  ```
- [ ] Create `internal/models/session.go`:
  ```go
  package models

  import "time"

  type Session struct {
      Id         string     `json:"id"`
      UserId     string     `json:"userId"`
      TokenHash  string     `json:"-"`
      DeviceInfo *string    `json:"deviceInfo"`
      ExpiresAt  time.Time  `json:"expiresAt"`
      CreatedAt  time.Time  `json:"createdAt"`
      RevokedAt  *time.Time `json:"revokedAt"`
  }
  ```
- [ ] Create `internal/models/project.go`:
  ```go
  package models

  import "time"

  type ProjectType string

  const (
      ProjectTypeSpec     ProjectType = "spec"
      ProjectTypeCategory ProjectType = "category"
  )

  type Project struct {
      Id          string      `json:"id"`
      ParentId    *string     `json:"parentId"`
      OwnerId     string      `json:"ownerId"`
      Name        string      `json:"name"`
      Slug        string      `json:"slug"`
      Path        string      `json:"path"`
      Type        ProjectType `json:"type"`
      Description *string     `json:"description"`
      SortOrder   int         `json:"sortOrder"`
      CreatedAt   time.Time   `json:"createdAt"`
      UpdatedAt   time.Time   `json:"updatedAt"`
  }
  ```
- [ ] Create `internal/models/file.go`:
  ```go
  package models

  import "time"

  type FileType string

  const (
      FileTypeFile   FileType = "file"
      FileTypeFolder FileType = "folder"
  )

  type File struct {
      Id          string    `json:"id"`
      ProjectId   string    `json:"projectId"`
      ParentId    *string   `json:"parentId"`
      Name        string    `json:"name"`
      Path        string    `json:"path"`
      Type        FileType  `json:"type"`
      ContentHash *string   `json:"contentHash"`
      SortOrder   int       `json:"sortOrder"`
      CreatedAt   time.Time `json:"createdAt"`
      UpdatedAt   time.Time `json:"updatedAt"`
  }

  type FileWithContent struct {
      File
      Content string `json:"content"`
  }
  ```
- [ ] Create `internal/models/snapshot.go`
- [ ] Create `internal/models/config.go`

**Verify:** All models compile without errors

---

## Phase A6: Configuration System

**Goal:** Implement 3-tier configuration hierarchy

**Checklist:**
- [ ] Create `internal/config/config.go`:
  ```go
  package config

  import (
      "encoding/json"
      "os"
      "strconv"
  )

  type Config struct {
      ServerPort      int    `json:"server.port"`
      DatabasePath    string `json:"database.path"`
      JWTSecret       string `json:"jwt.secret"`
      JWTAccessTTL    string `json:"jwt.access_ttl"`
      JWTRefreshTTL   string `json:"jwt.refresh_ttl"`
      SpecRootPath    string `json:"spec.root_path"`
      LlamaServerPath string `json:"llama.server.path"`
  }

  func Load() (*Config, error) {
      cfg := &Config{
          // Defaults (tier 3)
          ServerPort:    8080,
          DatabasePath:  "./data/spec.db",
          JWTAccessTTL:  "15m",
          JWTRefreshTTL: "336h",
          SpecRootPath:  "./specs",
      }

      // Load from seed.json if exists (tier 2)
      if data, err := os.ReadFile("internal/config/seed.json"); err == nil {
          json.Unmarshal(data, cfg)
      }

      // Override from environment (tier 1 - highest priority)
      if port := os.Getenv("SERVER_PORT"); port != "" {
          cfg.ServerPort, _ = strconv.Atoi(port)
      }
      if path := os.Getenv("DATABASE_PATH"); path != "" {
          cfg.DatabasePath = path
      }
      if secret := os.Getenv("JWT_SECRET"); secret != "" {
          cfg.JWTSecret = secret
      }
      if path := os.Getenv("SPEC_ROOT_PATH"); path != "" {
          cfg.SpecRootPath = path
      }

      return cfg, nil
  }
  ```
- [ ] Create `internal/config/seed.json`:
  ```json
  {
    "server.port": 8080,
    "database.path": "./data/spec.db",
    "jwt.access_ttl": "15m",
    "jwt.refresh_ttl": "336h",
    "spec.root_path": "./specs",
    "llama.server.path": "/usr/local/bin/llama-server",
    "llama.voice.model": "whisper-large-v3",
    "llama.reasoning.model": "deepseek-r1:14b"
  }
  ```
- [ ] Update `main.go` to load config on startup

**Verify:** Config loads from env, seed.json, and defaults correctly

---

## Phase A7: Password Hashing

**Goal:** Implement Argon2id password hashing with bcrypt fallback

**Checklist:**
- [ ] Create `internal/utils/hash.go`:
  ```go
  package utils

  import (
      "crypto/rand"
      "crypto/subtle"
      "encoding/base64"
      "fmt"
      "strings"

      "golang.org/x/crypto/argon2"
      "golang.org/x/crypto/bcrypt"
  )

  const (
      argonTime    = 3
      argonMemory  = 64 * 1024 // 64MB
      argonThreads = 4
      argonKeyLen  = 32
      saltLen      = 16
  )

  // HashPassword creates an Argon2id hash
  func HashPassword(password string) (string, error) {
      salt := make([]byte, saltLen)
      if _, err := rand.Read(salt); err != nil {
          return "", err
      }

      hash := argon2.IDKey(
          []byte(password),
          salt,
          argonTime,
          argonMemory,
          argonThreads,
          argonKeyLen,
      )

      return fmt.Sprintf(
          "$argon2id$v=19$m=%d,t=%d,p=%d$%s$%s",
          argonMemory, argonTime, argonThreads,
          base64.RawStdEncoding.EncodeToString(salt),
          base64.RawStdEncoding.EncodeToString(hash),
      ), nil
  }

  // VerifyPassword checks password against hash (supports argon2id and bcrypt)
  func VerifyPassword(hash, password string) bool {
      if strings.HasPrefix(hash, "$argon2id$") {
          return verifyArgon2id(hash, password)
      }
      if strings.HasPrefix(hash, "$2") {
          return bcrypt.CompareHashAndPassword([]byte(hash), []byte(password)) == nil
      }
      return false
  }

  func verifyArgon2id(hash, password string) bool {
      parts := strings.Split(hash, "$")
      if len(parts) != 6 {
          return false
      }

      var memory, time uint32
      var threads uint8
      fmt.Sscanf(parts[3], "m=%d,t=%d,p=%d", &memory, &time, &threads)

      salt, _ := base64.RawStdEncoding.DecodeString(parts[4])
      expectedHash, _ := base64.RawStdEncoding.DecodeString(parts[5])

      computedHash := argon2.IDKey(
          []byte(password),
          salt,
          time,
          memory,
          threads,
          uint32(len(expectedHash)),
      )

      return subtle.ConstantTimeCompare(computedHash, expectedHash) == 1
  }

  // HashPasswordBcrypt for legacy support
  func HashPasswordBcrypt(password string) (string, error) {
      hash, err := bcrypt.GenerateFromPassword([]byte(password), bcrypt.DefaultCost)
      return string(hash), err
  }
  ```

**Verify:** Can hash and verify passwords, constant-time comparison works

---

## Phase A8: JWT Token Utils

**Goal:** Implement JWT access/refresh token generation and validation

**Checklist:**
- [ ] Create `internal/utils/jwt.go`:
  ```go
  package utils

  import (
      "crypto/sha256"
      "encoding/hex"
      "fmt"
      "time"

      "github.com/golang-jwt/jwt/v5"
  )

  type TokenType string

  const (
      TokenTypeAccess  TokenType = "access"
      TokenTypeRefresh TokenType = "refresh"
  )

  type Claims struct {
      jwt.RegisteredClaims
      UserId    string    `json:"uid"`
      TokenType TokenType `json:"typ"`
  }

  type TokenPair struct {
      AccessToken  string    `json:"accessToken"`
      RefreshToken string    `json:"refreshToken"`
      ExpiresAt    time.Time `json:"expiresAt"`
  }

  type JWTUtil struct {
      secret        []byte
      accessTTL     time.Duration
      refreshTTL    time.Duration
  }

  func NewJWTUtil(secret string, accessTTL, refreshTTL time.Duration) *JWTUtil {
      return &JWTUtil{
          secret:     []byte(secret),
          accessTTL:  accessTTL,
          refreshTTL: refreshTTL,
      }
  }

  func (j *JWTUtil) GenerateTokenPair(userId string) (*TokenPair, error) {
      now := time.Now()

      // Access token
      accessClaims := Claims{
          RegisteredClaims: jwt.RegisteredClaims{
              ExpiresAt: jwt.NewNumericDate(now.Add(j.accessTTL)),
              IssuedAt:  jwt.NewNumericDate(now),
              Subject:   userId,
          },
          UserId:    userId,
          TokenType: TokenTypeAccess,
      }
      accessToken := jwt.NewWithClaims(jwt.SigningMethodHS256, accessClaims)
      accessStr, err := accessToken.SignedString(j.secret)
      if err != nil {
          return nil, err
      }

      // Refresh token
      refreshClaims := Claims{
          RegisteredClaims: jwt.RegisteredClaims{
              ExpiresAt: jwt.NewNumericDate(now.Add(j.refreshTTL)),
              IssuedAt:  jwt.NewNumericDate(now),
              Subject:   userId,
          },
          UserId:    userId,
          TokenType: TokenTypeRefresh,
      }
      refreshToken := jwt.NewWithClaims(jwt.SigningMethodHS256, refreshClaims)
      refreshStr, err := refreshToken.SignedString(j.secret)
      if err != nil {
          return nil, err
      }

      return &TokenPair{
          AccessToken:  accessStr,
          RefreshToken: refreshStr,
          ExpiresAt:    now.Add(j.accessTTL),
      }, nil
  }

  func (j *JWTUtil) ValidateToken(tokenStr string, expectedType TokenType) (*Claims, error) {
      token, err := jwt.ParseWithClaims(tokenStr, &Claims{}, func(t *jwt.Token) (interface{}, error) {
          if _, ok := t.Method.(*jwt.SigningMethodHMAC); !ok {
              return nil, fmt.Errorf("unexpected signing method")
          }
          return j.secret, nil
      })
      if err != nil {
          return nil, err
      }

      claims, ok := token.Claims.(*Claims)
      if !ok || !token.Valid {
          return nil, fmt.Errorf("invalid token")
      }

      if claims.TokenType != expectedType {
          return nil, fmt.Errorf("wrong token type")
      }

      return claims, nil
  }

  // HashToken creates SHA-256 hash for storage
  func HashToken(token string) string {
      h := sha256.Sum256([]byte(token))
      return hex.EncodeToString(h[:])
  }
  ```

**Verify:** Can generate and validate token pairs

---

## Phase A9: User Repository

**Goal:** Implement GORM-based database operations for User entity

> **ORM Policy**: All database operations use GORM. Raw SQL is forbidden.

**Checklist:**
- [ ] Create `internal/repository/user_repo.go`:
  ```go
  package repository

  import (
      "time"

      "spec-manager/internal/models"
      "github.com/google/uuid"
      "gorm.io/gorm"
  )

  type UserRepo struct {
      db *gorm.DB
  }

  func NewUserRepo(db *gorm.DB) *UserRepo {
      return &UserRepo{db: db}
  }

  func (r *UserRepo) Create(user *models.User) error {
      user.Id = uuid.NewString()
      user.CreatedAt = time.Now().UTC()
      user.UpdatedAt = user.CreatedAt
      
      return r.db.Create(user).Error
  }

  func (r *UserRepo) GetById(id string) (*models.User, error) {
      var user models.User
      err := r.db.First(&user, "id = ?", id).Error
      if err != nil {
          return nil, err
      }
      return &user, nil
  }

  func (r *UserRepo) GetByUsername(username string) (*models.User, error) {
      var user models.User
      err := r.db.First(&user, "username = ?", username).Error
      if err != nil {
          return nil, err
      }
      return &user, nil
  }

  func (r *UserRepo) GetByEmail(email string) (*models.User, error) {
      var user models.User
      err := r.db.First(&user, "email = ?", email).Error
      if err != nil {
          return nil, err
      }
      return &user, nil
  }

  func (r *UserRepo) UpdateLastLogin(id string) error {
      now := time.Now().UTC()
      return r.db.Model(&models.User{}).
          Where("id = ?", id).
          Updates(map[string]interface{}{
              "last_login_at": now,
              "updated_at":    now,
          }).Error
  }

  func (r *UserRepo) Update(user *models.User) error {
      user.UpdatedAt = time.Now().UTC()
      return r.db.Save(user).Error
  }

  func (r *UserRepo) Delete(id string) error {
      return r.db.Delete(&models.User{}, "id = ?", id).Error
  }
  ```

**Verify:** Can create and retrieve users using GORM

---

## Phase A10: Session Repository

**Goal:** Implement GORM-based database operations for Session entity

> **ORM Policy**: All database operations use GORM. Raw SQL is forbidden.

**Checklist:**
- [ ] Create `internal/repository/session_repo.go`:
  ```go
  package repository

  import (
      "time"

      "spec-manager/internal/models"
      "github.com/google/uuid"
      "gorm.io/gorm"
  )

  type SessionRepo struct {
      db *gorm.DB
  }

  func NewSessionRepo(db *gorm.DB) *SessionRepo {
      return &SessionRepo{db: db}
  }

  func (r *SessionRepo) Create(session *models.Session) error {
      session.Id = uuid.NewString()
      session.CreatedAt = time.Now().UTC()
      
      return r.db.Create(session).Error
  }

  func (r *SessionRepo) GetByTokenHash(hash string) (*models.Session, error) {
      var session models.Session
      err := r.db.
          Where("token_hash = ?", hash).
          Where("revoked_at IS NULL").
          First(&session).Error
      
      if err != nil {
          return nil, err
      }
      return &session, nil
  }

  func (r *SessionRepo) Revoke(id string) error {
      now := time.Now().UTC()
      return r.db.Model(&models.Session{}).
          Where("id = ?", id).
          Update("revoked_at", now).Error
  }

  func (r *SessionRepo) RevokeAllForUser(userId string) error {
      now := time.Now().UTC()
      return r.db.Model(&models.Session{}).
          Where("user_id = ?", userId).
          Where("revoked_at IS NULL").
          Update("revoked_at", now).Error
  }

  func (r *SessionRepo) GetActiveSessions(userId string) ([]models.Session, error) {
      var sessions []models.Session
      now := time.Now().UTC()
      
      err := r.db.
          Where("user_id = ?", userId).
          Where("revoked_at IS NULL").
          Where("expires_at > ?", now).
          Order("created_at DESC").
          Find(&sessions).Error
      
      return sessions, err
  }

  func (r *SessionRepo) CleanExpired() (int64, error) {
      now := time.Now().UTC()
      result := r.db.
          Where("expires_at < ?", now).
          Or("revoked_at IS NOT NULL AND revoked_at < ?", now.Add(-7*24*time.Hour)).
          Delete(&models.Session{})
      
      return result.RowsAffected, result.Error
  }
  ```

  func (r *SessionRepo) Revoke(id string) error {
      now := time.Now().UTC().Format(time.RFC3339)
      _, err := r.db.Exec(`UPDATE Session SET RevokedAt = ? WHERE Id = ?`, now, id)
      return err
  }

  func (r *SessionRepo) RevokeByTokenHash(hash string) error {
      now := time.Now().UTC().Format(time.RFC3339)
      _, err := r.db.Exec(`UPDATE Session SET RevokedAt = ? WHERE TokenHash = ?`, now, hash)
      return err
  }

  func (r *SessionRepo) CleanExpired() (int64, error) {
      now := time.Now().UTC().Format(time.RFC3339)
      result, err := r.db.Exec(`DELETE FROM Session WHERE ExpiresAt < ?`, now)
      if err != nil {
          return 0, err
      }
      return result.RowsAffected()
  }
  ```

**Verify:** Can create, retrieve, and revoke sessions

---

## Phase A11: Auth Service

**Goal:** Implement authentication business logic

**Checklist:**
- [ ] Create `internal/services/auth_service.go`:
  ```go
  package services

  import (
      "errors"
      "time"

      "spec-manager/internal/models"
      "spec-manager/internal/repository"
      "spec-manager/internal/utils"
  )

  var (
      ErrInvalidCredentials = errors.New("invalid credentials")
      ErrUserExists         = errors.New("user already exists")
      ErrSessionExpired     = errors.New("session expired")
  )

  type AuthService struct {
      userRepo    *repository.UserRepo
      sessionRepo *repository.SessionRepo
      jwt         *utils.JWTUtil
  }

  func NewAuthService(
      userRepo *repository.UserRepo,
      sessionRepo *repository.SessionRepo,
      jwt *utils.JWTUtil,
  ) *AuthService {
      return &AuthService{
          userRepo:    userRepo,
          sessionRepo: sessionRepo,
          jwt:         jwt,
      }
  }

  type RegisterRequest struct {
      Username    string `json:"username" binding:"required,min=3,max=50"`
      Email       string `json:"email" binding:"required,email"`
      Password    string `json:"password" binding:"required,min=8"`
      DisplayName string `json:"displayName"`
  }

  func (s *AuthService) Register(req RegisterRequest) (*models.User, error) {
      // Check if user exists
      if existing, _ := s.userRepo.GetByUsername(req.Username); existing != nil {
          return nil, ErrUserExists
      }
      if existing, _ := s.userRepo.GetByEmail(req.Email); existing != nil {
          return nil, ErrUserExists
      }

      // Hash password
      hash, err := utils.HashPassword(req.Password)
      if err != nil {
          return nil, err
      }

      user := &models.User{
          Username:        req.Username,
          Email:           req.Email,
          PasswordHash:    hash,
          DisplayName:     &req.DisplayName,
          ThemePreference: "light",
      }

      if err := s.userRepo.Create(user); err != nil {
          return nil, err
      }

      return user, nil
  }

  type LoginRequest struct {
      Username string `json:"username" binding:"required"`
      Password string `json:"password" binding:"required"`
  }

  func (s *AuthService) Login(req LoginRequest, deviceInfo string) (*utils.TokenPair, *models.User, error) {
      // Find user
      user, err := s.userRepo.GetByUsername(req.Username)
      if err != nil {
          user, err = s.userRepo.GetByEmail(req.Username)
      }
      if err != nil || user == nil {
          return nil, nil, ErrInvalidCredentials
      }

      // Verify password
      if !utils.VerifyPassword(user.PasswordHash, req.Password) {
          return nil, nil, ErrInvalidCredentials
      }

      // Generate tokens
      tokens, err := s.jwt.GenerateTokenPair(user.Id)
      if err != nil {
          return nil, nil, err
      }

      // Store refresh token session
      session := &models.Session{
          UserId:     user.Id,
          TokenHash:  utils.HashToken(tokens.RefreshToken),
          DeviceInfo: &deviceInfo,
          ExpiresAt:  time.Now().Add(14 * 24 * time.Hour),
      }
      if err := s.sessionRepo.Create(session); err != nil {
          return nil, nil, err
      }

      // Update last login
      s.userRepo.UpdateLastLogin(user.Id)

      return tokens, user, nil
  }

  func (s *AuthService) Logout(refreshToken string) error {
      hash := utils.HashToken(refreshToken)
      return s.sessionRepo.RevokeByTokenHash(hash)
  }

  func (s *AuthService) Refresh(refreshToken string) (*utils.TokenPair, error) {
      // Validate refresh token
      claims, err := s.jwt.ValidateToken(refreshToken, utils.TokenTypeRefresh)
      if err != nil {
          return nil, ErrInvalidCredentials
      }

      // Check session exists and not revoked
      hash := utils.HashToken(refreshToken)
      session, err := s.sessionRepo.GetByTokenHash(hash)
      if err != nil || session == nil {
          return nil, ErrSessionExpired
      }

      // Revoke old session
      s.sessionRepo.Revoke(session.Id)

      // Generate new tokens
      tokens, err := s.jwt.GenerateTokenPair(claims.UserId)
      if err != nil {
          return nil, err
      }

      // Store new session
      newSession := &models.Session{
          UserId:     claims.UserId,
          TokenHash:  utils.HashToken(tokens.RefreshToken),
          DeviceInfo: session.DeviceInfo,
          ExpiresAt:  time.Now().Add(14 * 24 * time.Hour),
      }
      s.sessionRepo.Create(newSession)

      return tokens, nil
  }

  func (s *AuthService) GetUser(userId string) (*models.User, error) {
      return s.userRepo.GetById(userId)
  }
  ```

**Verify:** Can register, login, logout, and refresh tokens

---

## Phase A12: Auth Handlers

**Goal:** Create HTTP handlers for authentication endpoints

**Checklist:**
- [ ] Create `internal/handlers/dto/auth.go`:
  ```go
  package dto

  type RegisterRequest struct {
      Username    string `json:"username" binding:"required,min=3,max=50"`
      Email       string `json:"email" binding:"required,email"`
      Password    string `json:"password" binding:"required,min=8"`
      DisplayName string `json:"displayName"`
  }

  type LoginRequest struct {
      Username string `json:"username" binding:"required"`
      Password string `json:"password" binding:"required"`
  }

  type RefreshRequest struct {
      RefreshToken string `json:"refreshToken" binding:"required"`
  }

  type AuthResponse struct {
      User         UserDTO   `json:"user"`
      AccessToken  string    `json:"accessToken"`
      RefreshToken string    `json:"refreshToken"`
      ExpiresAt    string    `json:"expiresAt"`
  }

  type UserDTO struct {
      Id              string  `json:"id"`
      Username        string  `json:"username"`
      Email           string  `json:"email"`
      DisplayName     *string `json:"displayName"`
      ThemePreference string  `json:"themePreference"`
  }
  ```
- [ ] Create `internal/handlers/auth.go`:
  ```go
  package handlers

  import (
      "net/http"

      "spec-manager/internal/handlers/dto"
      "spec-manager/internal/services"
      "github.com/gin-gonic/gin"
  )

  type AuthHandler struct {
      authService *services.AuthService
  }

  func NewAuthHandler(authService *services.AuthService) *AuthHandler {
      return &AuthHandler{authService: authService}
  }

  func (h *AuthHandler) Register(c *gin.Context) {
      var req dto.RegisterRequest
      if err := c.ShouldBindJSON(&req); err != nil {
          c.JSON(http.StatusBadRequest, gin.H{"success": false, "error": err.Error()})
          return
      }

      user, err := h.authService.Register(services.RegisterRequest{
          Username:    req.Username,
          Email:       req.Email,
          Password:    req.Password,
          DisplayName: req.DisplayName,
      })
      if err != nil {
          status := http.StatusInternalServerError
          if err == services.ErrUserExists {
              status = http.StatusConflict
          }
          c.JSON(status, gin.H{"success": false, "error": err.Error()})
          return
      }

      c.JSON(http.StatusCreated, gin.H{
          "success": true,
          "data": dto.UserDTO{
              Id:              user.Id,
              Username:        user.Username,
              Email:           user.Email,
              DisplayName:     user.DisplayName,
              ThemePreference: user.ThemePreference,
          },
      })
  }

  func (h *AuthHandler) Login(c *gin.Context) {
      var req dto.LoginRequest
      if err := c.ShouldBindJSON(&req); err != nil {
          c.JSON(http.StatusBadRequest, gin.H{"success": false, "error": err.Error()})
          return
      }

      deviceInfo := c.GetHeader("User-Agent")
      tokens, user, err := h.authService.Login(services.LoginRequest{
          Username: req.Username,
          Password: req.Password,
      }, deviceInfo)

      if err != nil {
          c.JSON(http.StatusUnauthorized, gin.H{"success": false, "error": "Invalid credentials"})
          return
      }

      c.JSON(http.StatusOK, gin.H{
          "success": true,
          "data": dto.AuthResponse{
              User: dto.UserDTO{
                  Id:              user.Id,
                  Username:        user.Username,
                  Email:           user.Email,
                  DisplayName:     user.DisplayName,
                  ThemePreference: user.ThemePreference,
              },
              AccessToken:  tokens.AccessToken,
              RefreshToken: tokens.RefreshToken,
              ExpiresAt:    tokens.ExpiresAt.Format("2006-01-02T15:04:05Z07:00"),
          },
      })
  }

  func (h *AuthHandler) Logout(c *gin.Context) {
      var req dto.RefreshRequest
      if err := c.ShouldBindJSON(&req); err != nil {
          c.JSON(http.StatusBadRequest, gin.H{"success": false, "error": err.Error()})
          return
      }

      h.authService.Logout(req.RefreshToken)
      c.JSON(http.StatusOK, gin.H{"success": true, "data": nil})
  }

  func (h *AuthHandler) Refresh(c *gin.Context) {
      var req dto.RefreshRequest
      if err := c.ShouldBindJSON(&req); err != nil {
          c.JSON(http.StatusBadRequest, gin.H{"success": false, "error": err.Error()})
          return
      }

      tokens, err := h.authService.Refresh(req.RefreshToken)
      if err != nil {
          c.JSON(http.StatusUnauthorized, gin.H{"success": false, "error": "Invalid refresh token"})
          return
      }

      c.JSON(http.StatusOK, gin.H{
          "success": true,
          "data": gin.H{
              "accessToken":  tokens.AccessToken,
              "refreshToken": tokens.RefreshToken,
              "expiresAt":    tokens.ExpiresAt.Format("2006-01-02T15:04:05Z07:00"),
          },
      })
  }

  func (h *AuthHandler) Me(c *gin.Context) {
      userId := c.GetString("userId")
      user, err := h.authService.GetUser(userId)
      if err != nil {
          c.JSON(http.StatusNotFound, gin.H{"success": false, "error": "User not found"})
          return
      }

      c.JSON(http.StatusOK, gin.H{
          "success": true,
          "data": dto.UserDTO{
              Id:              user.Id,
              Username:        user.Username,
              Email:           user.Email,
              DisplayName:     user.DisplayName,
              ThemePreference: user.ThemePreference,
          },
      })
  }

  func (h *AuthHandler) RegisterRoutes(r *gin.RouterGroup, authMiddleware gin.HandlerFunc) {
      auth := r.Group("/auth")
      auth.POST("/register", h.Register)
      auth.POST("/login", h.Login)
      auth.POST("/logout", h.Logout)
      auth.POST("/refresh", h.Refresh)
      auth.GET("/me", authMiddleware, h.Me)
  }
  ```

**Verify:** All auth endpoints work via curl/Postman

---

## Remaining Phases Summary

The document continues with similarly detailed phases for:

- **B1-B10:** Middleware, Response Utils, Error Types, Validation, Project/File CRUD
- **C1-C8:** File System Sync, Hash Utils, FSNotify, Metadata Sync, Snapshots
- **D1-D4:** Git Init, Commit/Push, Status, Handlers
- **E1-E10:** React Scaffold, Themes, API Client, Auth, Router, Layout
- **F1-F10:** Dashboard, Folder Tree, Editor, History UI
- **G1-G6:** Sync UI, Diff Viewer
- **H1-H8:** LLaMA Server, Transcription, Instructions
- **I1-I6:** Voice Input, Chat Panel
- **J1-J4:** File Change Tracking, Rollback
- **K1-K6:** Consistency Checker, Tests

---

## Phase Completion Tracking

Use this checklist to track progress:

```
Backend Foundation:
[ ] A1  [ ] A2  [ ] A3  [ ] A4  [ ] A5  [ ] A6
[ ] A7  [ ] A8  [ ] A9  [ ] A10 [ ] A11 [ ] A12

Backend API:
[ ] B1  [ ] B2  [ ] B3  [ ] B4  [ ] B5
[ ] B6  [ ] B7  [ ] B8  [ ] B9  [ ] B10

File Sync & History:
[ ] C1  [ ] C2  [ ] C3  [ ] C4
[ ] C5  [ ] C6  [ ] C7  [ ] C8

Git:
[ ] D1  [ ] D2  [ ] D3  [ ] D4

Frontend Foundation:
[ ] E1  [ ] E2  [ ] E3  [ ] E4  [ ] E5
[ ] E6  [ ] E7  [ ] E8  [ ] E9  [ ] E10

Frontend Editor:
[ ] F1  [ ] F2  [ ] F3  [ ] F4  [ ] F5
[ ] F6  [ ] F7  [ ] F8  [ ] F9  [ ] F10

History & Sync UI:
[ ] G1  [ ] G2  [ ] G3  [ ] G4  [ ] G5  [ ] G6

AI Backend:
[ ] H1  [ ] H2  [ ] H3  [ ] H4
[ ] H5  [ ] H6  [ ] H7  [ ] H8

AI Frontend:
[ ] I1  [ ] I2  [ ] I3  [ ] I4  [ ] I5  [ ] I6

History & Rollback:
[ ] J1  [ ] J2  [ ] J3  [ ] J4

Consistency & Testing:
[ ] K1  [ ] K2  [ ] K3  [ ] K4  [ ] K5  [ ] K6
```

---

## Cross-References

> **Note:** Specs migrated to consolidated `05-features/` structure.

- [Database Schema](../07-database-design/00-overview.md)
- [API Client](../05-features/15-api-client/00-overview.md)
- [File Management](../05-features/02-file-management/00-overview.md)
- [Authentication](../05-features/01-authentication/00-overview.md)
- [AI Integration](../05-features/06-ai-integration/00-overview.md)
- [Theme System](../05-features/10-theme-system/00-overview.md)
- [Testing & Deployment](./06-testing-deployment.md)
