# AI Integration Backend

**Version:** 0.1.0  
**Status:** Draft  
**Updated:** 2026-01-27  

---

## Overview

The AI integration backend manages the local LLaMA server configuration, model selection, and provides API endpoints for the multi-stage AI chain: Voice Transcription → RAG Context Retrieval → Reasoning → Idea/Spec Generation.

**Cross-References:**
- [RAG System](../09-knowledge-memory/01-rag-system.md) - Retrieval-Augmented Generation for context injection
- [Instruction System](./03-instruction-system.md) - Idea promotion and instruction lifecycle
- [Database Schema](../../07-database-design/01-schema.md) - ModelRegistry, ModelSlot entities

---

## 7.1 Architecture

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         AI Integration Flow                              │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  ┌─────────┐    ┌─────────────┐    ┌─────────────┐    ┌─────────────┐  │
│  │  Voice  │───▶│  Voice      │───▶│  Reasoning  │───▶│  Generate   │  │
│  │  Input  │    │  Model      │    │    Model    │    │  Idea/Spec  │  │
│  └─────────┘    └─────────────┘    └─────────────┘    └─────────────┘  │
│       │               │                   │                  │          │
│       │               ▼                   ▼                  ▼          │
│       │         Transcription      Questions/           Markdown       │
│       │            Text            Validation           Output          │
│       │                                  │                              │
│       │                                  ▼                              │
│       │                           User Answers                          │
│       │                                  │                              │
│       └──────────────────────────────────┘                              │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 7.2 LLaMA Server Configuration

### Database Schema (Config Table Entries)

```sql
-- Seeded configuration for LLaMA server
INSERT INTO Config (Key, Value, Description) VALUES
('llama.server.path', '/usr/local/bin/llama-server', 'Path to llama.cpp server executable'),
('llama.server.host', '127.0.0.1', 'LLaMA server bind host'),
('llama.server.port', '8080', 'LLaMA server port'),
('llama.models.dir', '/models', 'Directory containing model files'),
('llama.voice.model', 'whisper-large-v3.gguf', 'Model for voice transcription'),
('llama.reasoning.model', 'mixtral-8x7b-instruct.gguf', 'Model for reasoning/generation'),
('llama.context.size', '8192', 'Context window size'),
('llama.gpu.layers', '35', 'Number of layers to offload to GPU');
```

### Configuration Service

```go
// internal/services/config_service.go
package services

type LLaMAConfig struct {
    ServerPath     string `json:"serverPath"`
    Host           string `json:"host"`
    Port           int    `json:"port"`
    ModelsDir      string `json:"modelsDir"`
    VoiceModel     string `json:"voiceModel"`
    ReasoningModel string `json:"reasoningModel"`
    ContextSize    int    `json:"contextSize"`
    GPULayers      int    `json:"gpuLayers"`
}

type ConfigService struct {
    db *sql.DB
}

func (s *ConfigService) GetLLaMAConfig(ctx context.Context) (*LLaMAConfig, error) {
    config := &LLaMAConfig{}
    
    rows, err := s.db.QueryContext(ctx, `
        SELECT Key, Value FROM Config WHERE Key LIKE 'llama.%'
    `)
    if err != nil {
        return nil, err
    }
    defer rows.Close()
    
    for rows.Next() {
        var key, value string
        if err := rows.Scan(&key, &value); err != nil {
            continue
        }
        
        switch key {
        case "llama.server.path":
            config.ServerPath = value
        case "llama.server.host":
            config.Host = value
        case "llama.server.port":
            config.Port, _ = strconv.Atoi(value)
        case "llama.models.dir":
            config.ModelsDir = value
        case "llama.voice.model":
            config.VoiceModel = value
        case "llama.reasoning.model":
            config.ReasoningModel = value
        case "llama.context.size":
            config.ContextSize, _ = strconv.Atoi(value)
        case "llama.gpu.layers":
            config.GPULayers, _ = strconv.Atoi(value)
        }
    }
    
    return config, nil
}

func (s *ConfigService) UpdateLLaMAConfig(ctx context.Context, updates map[string]string) error {
    tx, err := s.db.BeginTx(ctx, nil)
    if err != nil {
        return err
    }
    defer tx.Rollback()
    
    stmt, err := tx.PrepareContext(ctx, `
        INSERT INTO Config (Key, Value, UpdatedAt)
        VALUES (?, ?, datetime('now'))
        ON CONFLICT(Key) DO UPDATE SET Value = excluded.Value, UpdatedAt = excluded.UpdatedAt
    `)
    if err != nil {
        return err
    }
    defer stmt.Close()
    
    for key, value := range updates {
        if _, err := stmt.ExecContext(ctx, "llama."+key, value); err != nil {
            return err
        }
    }
    
    return tx.Commit()
}

func (s *ConfigService) ListAvailableModels(ctx context.Context) ([]ModelInfo, error) {
    config, err := s.GetLLaMAConfig(ctx)
    if err != nil {
        return nil, err
    }
    
    entries, err := os.ReadDir(config.ModelsDir)
    if err != nil {
        return nil, err
    }
    
    var models []ModelInfo
    for _, entry := range entries {
        if !entry.IsDir() && strings.HasSuffix(entry.Name(), ".gguf") {
            info, _ := entry.Info()
            models = append(models, ModelInfo{
                Name:     entry.Name(),
                Size:     info.Size(),
                Modified: info.ModTime(),
            })
        }
    }
    
    return models, nil
}

type ModelInfo struct {
    Name     string    `json:"name"`
    Size     int64     `json:"size"`
    Modified time.Time `json:"modified"`
}
```

---

## 7.3 Model Registry Service

The Model Registry manages discovery, registration, and selection of AI models.

### Model Discovery

```go
// internal/services/model_registry_service.go
package services

import (
    "context"
    "os"
    "path/filepath"
    "strings"
)

type ModelRegistryService struct {
    db            *sql.DB
    configService *ConfigService
}

func NewModelRegistryService(db *sql.DB, configService *ConfigService) *ModelRegistryService {
    return &ModelRegistryService{db: db, configService: configService}
}

// ScanModels discovers models from configured root paths
func (s *ModelRegistryService) ScanModels(ctx context.Context) ([]ModelInfo, error) {
    // Get model root paths from config
    rootPaths, err := s.configService.GetConfigAsArray(ctx, "llama.models.rootPaths")
    if err != nil {
        return nil, err
    }
    
    var discovered []ModelInfo
    
    for _, rootPath := range rootPaths {
        entries, err := os.ReadDir(rootPath)
        if err != nil {
            continue // Skip inaccessible paths
        }
        
        for _, entry := range entries {
            if entry.IsDir() || !strings.HasSuffix(entry.Name(), ".gguf") {
                continue
            }
            
            info, _ := entry.Info()
            modelPath := filepath.Join(rootPath, entry.Name())
            
            // Infer model type from filename
            modelType := inferModelType(entry.Name())
            
            discovered = append(discovered, ModelInfo{
                FileName:      entry.Name(),
                DisplayName:   generateDisplayName(entry.Name()),
                ModelType:     modelType,
                ModelPath:     modelPath,
                FileSizeBytes: info.Size(),
            })
        }
    }
    
    return discovered, nil
}

// ModelCategory defines the 4 primary categories for model selection
type ModelCategory string

const (
    ModelCategoryThinking ModelCategory = "thinking"  // Long-chain reasoning, planning
    ModelCategoryWriting  ModelCategory = "writing"   // Content generation, drafting
    ModelCategoryVoice    ModelCategory = "voice"     // Speech-to-text transcription
    ModelCategoryCoding   ModelCategory = "coding"    // Code generation, refactoring
)

var AllModelCategories = []ModelCategory{
    ModelCategoryThinking, ModelCategoryWriting, ModelCategoryVoice, ModelCategoryCoding,
}

// inferModelCategory determines category based on filename patterns
func inferModelCategory(filename string) ModelCategory {
    lowerName := strings.ToLower(filename)
    
    // Voice detection
    if containsAny(lowerName, "whisper", "speech", "voice", "transcribe", "audio") {
        return ModelCategoryVoice
    }
    
    // Coding detection
    if containsAny(lowerName, "code", "coder", "starcoder", "codellama", "deepseek-coder", "qwen-coder") {
        return ModelCategoryCoding
    }
    
    // Thinking/Reasoning detection
    if containsAny(lowerName, "reasoning", "think", "o1", "r1", "qwq", "deepseek-r", "reflection") {
        return ModelCategoryThinking
    }
    
    // Default to writing for general-purpose models (llama, mistral, etc.)
    return ModelCategoryWriting
}

func containsAny(s string, substrs ...string) bool {
    for _, sub := range substrs {
        if strings.Contains(s, sub) {
            return true
        }
    }
    return false
}

// SyncRegistry updates database with discovered models
func (s *ModelRegistryService) SyncRegistry(ctx context.Context) error {
    discovered, err := s.ScanModels(ctx)
    if err != nil {
        return err
    }
    
    tx, _ := s.db.BeginTx(ctx, nil)
    defer tx.Rollback()
    
    for _, model := range discovered {
        _, err := tx.ExecContext(ctx, `
            INSERT INTO ModelRegistry (Id, DisplayName, FileName, ModelCategory, ModelPath, FileSizeBytes, LastScannedAt, CreatedAt, UpdatedAt)
            VALUES (?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'), datetime('now'))
            ON CONFLICT(FileName) DO UPDATE SET
                ModelPath = excluded.ModelPath,
                FileSizeBytes = excluded.FileSizeBytes,
                ModelCategory = excluded.ModelCategory,
                LastScannedAt = datetime('now'),
                UpdatedAt = datetime('now')
        `, uuid.NewString(), model.DisplayName, model.FileName, model.Category, model.ModelPath, model.FileSizeBytes)
        
        if err != nil {
            return err
        }
    }
    
    return tx.Commit()
}
```

### Model Selection Hierarchy

Model selection follows a priority hierarchy for each of the 4 categories:

```
┌─────────────────────────────────────────────────────────────────┐
│              Model Selection Hierarchy (Per Category)            │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│   Categories: thinking | writing | voice | coding               │
│                                                                  │
│   Priority 1: Per-Instruction Override                          │
│   ─────────────────────────────────                             │
│   When launching an AI task, user can explicitly select a       │
│   model to use for that specific instruction and category.      │
│                                                                  │
│   Priority 2: Per-Project Default                                │
│   ────────────────────────                                      │
│   Each project can specify default models per category:         │
│   ProjectSettings.DefaultThinkingModelId                        │
│   ProjectSettings.DefaultWritingModelId                         │
│   ProjectSettings.DefaultVoiceModelId                           │
│   ProjectSettings.DefaultCodingModelId                          │
│                                                                  │
│   Priority 3: Per-User Default                                   │
│   ───────────────────────                                       │
│   User preferences stored per category:                         │
│   User.DefaultThinkingModelId                                   │
│   User.DefaultWritingModelId                                    │
│   User.DefaultVoiceModelId                                      │
│   User.DefaultCodingModelId                                     │
│                                                                  │
│   Priority 4: System Default                                     │
│   ─────────────────────                                         │
│   Config keys:                                                   │
│   llm.defaults.thinkingModelId                                  │
│   llm.defaults.writingModelId                                   │
│   llm.defaults.voiceModelId                                     │
│   llm.defaults.codingModelId                                    │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### Model Resolution Logic

```go
// CategoryModelOverrides holds per-category model overrides
type CategoryModelOverrides struct {
    ThinkingModelId *string `json:"thinkingModelId,omitempty"`
    WritingModelId  *string `json:"writingModelId,omitempty"`
    VoiceModelId    *string `json:"voiceModelId,omitempty"`
    CodingModelId   *string `json:"codingModelId,omitempty"`
}

// ResolveModelByCategory returns the model to use based on hierarchy and category
func (s *ModelRegistryService) ResolveModelByCategory(
    ctx context.Context,
    category ModelCategory,
    instructionOverrides *CategoryModelOverrides,
    projectId *string,
    userId string,
) (*ModelInfo, error) {
    
    // Priority 1: Per-instruction override
    if instructionOverrides != nil {
        modelId := s.getOverrideForCategory(instructionOverrides, category)
        if modelId != nil && *modelId != "" {
            return s.GetModelById(ctx, *modelId)
        }
    }
    
    // Priority 2: Per-project default
    if projectId != nil {
        projectSettings, _ := s.getProjectSettings(ctx, *projectId)
        if projectSettings != nil {
            modelId := s.getProjectDefaultForCategory(projectSettings, category)
            if modelId != nil && *modelId != "" {
                return s.GetModelById(ctx, *modelId)
            }
        }
    }
    
    // Priority 3: Per-user default
    user, _ := s.getUserWithPreferences(ctx, userId)
    if user != nil {
        modelId := s.getUserDefaultForCategory(user, category)
        if modelId != nil && *modelId != "" {
            return s.GetModelById(ctx, *modelId)
        }
    }
    
    // Priority 4: System default
    configKey := fmt.Sprintf("llm.defaults.%sModelId", category)
    defaultModelId, _ := s.configService.GetConfig(ctx, configKey)
    if defaultModelId != "" {
        return s.GetModelById(ctx, defaultModelId)
    }
    
    // Fallback: First enabled model of requested category
    return s.GetFirstEnabledModelByCategory(ctx, category)
}

func (s *ModelRegistryService) getOverrideForCategory(overrides *CategoryModelOverrides, category ModelCategory) *string {
    switch category {
    case ModelCategoryThinking:
        return overrides.ThinkingModelId
    case ModelCategoryWriting:
        return overrides.WritingModelId
    case ModelCategoryVoice:
        return overrides.VoiceModelId
    case ModelCategoryCoding:
        return overrides.CodingModelId
    }
    return nil
}

func (s *ModelRegistryService) getProjectDefaultForCategory(settings *ProjectSettings, category ModelCategory) *string {
    switch category {
    case ModelCategoryThinking:
        return settings.DefaultThinkingModelId
    case ModelCategoryWriting:
        return settings.DefaultWritingModelId
    case ModelCategoryVoice:
        return settings.DefaultVoiceModelId
    case ModelCategoryCoding:
        return settings.DefaultCodingModelId
    }
    return nil
}

func (s *ModelRegistryService) getUserDefaultForCategory(user *User, category ModelCategory) *string {
    switch category {
    case ModelCategoryThinking:
        return user.DefaultThinkingModelId
    case ModelCategoryWriting:
        return user.DefaultWritingModelId
    case ModelCategoryVoice:
        return user.DefaultVoiceModelId
    case ModelCategoryCoding:
        return user.DefaultCodingModelId
    }
    return nil
}

// GetFirstEnabledModelByCategory returns the first available model for a category
func (s *ModelRegistryService) GetFirstEnabledModelByCategory(ctx context.Context, category ModelCategory) (*ModelInfo, error) {
    row := s.db.QueryRowContext(ctx, `
        SELECT Id, DisplayName, FileName, ModelCategory, ModelPath, FileSizeBytes
        FROM ModelRegistry 
        WHERE ModelCategory = ? AND IsEnabled = 1
        ORDER BY Priority ASC, DisplayName ASC
        LIMIT 1
    `, string(category))
    
    var model ModelInfo
    err := row.Scan(&model.Id, &model.DisplayName, &model.FileName, &model.Category, &model.ModelPath, &model.FileSizeBytes)
    if err != nil {
        return nil, err
    }
    return &model, nil
}
```

---

## 7.4 Multi-Model Slot Manager

The Slot Manager handles running multiple models concurrently on different ports.

### Slot Manager Architecture

```
┌─────────────────────────────────────────────────────────────────────────┐
│                     Multi-Model Slot Manager                             │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│   ┌──────────────────────────────────────────────────────────────────┐  │
│   │                       Slot Pool                                   │  │
│   ├─────────┬─────────┬─────────┬─────────┬─────────┬───────────────┤  │
│   │ Slot 0  │ Slot 1  │ Slot 2  │ Slot 3  │ ...     │ Slot N        │  │
│   │ :8080   │ :8081   │ :8082   │ :8083   │         │ :808N         │  │
│   │ whisper │ llama3  │ (idle)  │ (idle)  │         │ (idle)        │  │
│   │ active  │ active  │ idle    │ idle    │         │ idle          │  │
│   └─────────┴─────────┴─────────┴─────────┴─────────┴───────────────┘  │
│                                                                          │
│   Max Concurrent Models: llama.server.maxConcurrentModels (default: 3)  │
│   LRU Eviction: Oldest lastAccessedAt slot evicted when full            │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

### Slot Manager Service

```go
// internal/services/slot_manager.go
package services

import (
    "context"
    "fmt"
    "os/exec"
    "sync"
    "time"
)

type SlotManager struct {
    db             *sql.DB
    configService  *ConfigService
    registryService *ModelRegistryService
    mutex          sync.RWMutex
    processes      map[int]*exec.Cmd  // slotIndex -> process
}

func NewSlotManager(db *sql.DB, configService *ConfigService, registryService *ModelRegistryService) *SlotManager {
    return &SlotManager{
        db:              db,
        configService:   configService,
        registryService: registryService,
        processes:       make(map[int]*exec.Cmd),
    }
}

// InitializeSlots creates slot records on startup
func (sm *SlotManager) InitializeSlots(ctx context.Context) error {
    basePort, _ := sm.configService.GetConfigAsInt(ctx, "llama.server.basePort")
    portRangeStart, _ := sm.configService.GetConfigAsInt(ctx, "llama.server.portRangeStart")
    portRangeEnd, _ := sm.configService.GetConfigAsInt(ctx, "llama.server.portRangeEnd")
    
    tx, _ := sm.db.BeginTx(ctx, nil)
    defer tx.Rollback()
    
    // Create base slot (index 0)
    tx.ExecContext(ctx, `
        INSERT OR IGNORE INTO ModelSlot (Id, SlotIndex, Port, Status, CreatedAt, UpdatedAt)
        VALUES (?, 0, ?, 'idle', datetime('now'), datetime('now'))
    `, uuid.NewString(), basePort)
    
    // Create additional slots
    slotIndex := 1
    for port := portRangeStart; port <= portRangeEnd; port++ {
        tx.ExecContext(ctx, `
            INSERT OR IGNORE INTO ModelSlot (Id, SlotIndex, Port, Status, CreatedAt, UpdatedAt)
            VALUES (?, ?, ?, 'idle', datetime('now'), datetime('now'))
        `, uuid.NewString(), slotIndex, port)
        slotIndex++
    }
    
    return tx.Commit()
}

// RequestModel ensures a model is loaded and returns its port
func (sm *SlotManager) RequestModel(ctx context.Context, modelId string) (int, error) {
    sm.mutex.Lock()
    defer sm.mutex.Unlock()
    
    // Check if model already loaded
    var existingSlot struct {
        Port   int
        Status string
    }
    err := sm.db.QueryRowContext(ctx, `
        SELECT Port, Status FROM ModelSlot WHERE ModelId = ?
    `, modelId).Scan(&existingSlot.Port, &existingSlot.Status)
    
    if err == nil && existingSlot.Status == "active" {
        // Update last accessed time
        sm.db.ExecContext(ctx, `
            UPDATE ModelSlot SET LastAccessedAt = datetime('now') WHERE ModelId = ?
        `, modelId)
        return existingSlot.Port, nil
    }
    
    // Find available slot or evict LRU
    slot, err := sm.findOrEvictSlot(ctx)
    if err != nil {
        return 0, err
    }
    
    // Load model into slot
    err = sm.loadModelIntoSlot(ctx, modelId, slot)
    if err != nil {
        return 0, err
    }
    
    return slot.Port, nil
}

// findOrEvictSlot finds an idle slot or evicts the least recently used
func (sm *SlotManager) findOrEvictSlot(ctx context.Context) (*ModelSlot, error) {
    maxConcurrent, _ := sm.configService.GetConfigAsInt(ctx, "llama.server.maxConcurrentModels")
    
    // Count active slots
    var activeCount int
    sm.db.QueryRowContext(ctx, `
        SELECT COUNT(*) FROM ModelSlot WHERE Status = 'active'
    `).Scan(&activeCount)
    
    // Find idle slot
    var idleSlot ModelSlot
    err := sm.db.QueryRowContext(ctx, `
        SELECT Id, SlotIndex, Port FROM ModelSlot WHERE Status = 'idle' LIMIT 1
    `).Scan(&idleSlot.Id, &idleSlot.SlotIndex, &idleSlot.Port)
    
    if err == nil {
        return &idleSlot, nil
    }
    
    // No idle slots - check if we can evict
    if activeCount >= maxConcurrent {
        // Find LRU slot
        var lruSlot ModelSlot
        err := sm.db.QueryRowContext(ctx, `
            SELECT Id, SlotIndex, Port, ModelId, ProcessId
            FROM ModelSlot
            WHERE Status = 'active'
            ORDER BY LastAccessedAt ASC
            LIMIT 1
        `).Scan(&lruSlot.Id, &lruSlot.SlotIndex, &lruSlot.Port, &lruSlot.ModelId, &lruSlot.ProcessId)
        
        if err != nil {
            return nil, fmt.Errorf("no available slots: %w", err)
        }
        
        // Evict the LRU model
        if err := sm.unloadSlot(ctx, &lruSlot); err != nil {
            return nil, err
        }
        
        return &lruSlot, nil
    }
    
    return nil, fmt.Errorf("no available slots")
}

// loadModelIntoSlot starts a model process and updates slot status
func (sm *SlotManager) loadModelIntoSlot(ctx context.Context, modelId string, slot *ModelSlot) error {
    // Update slot status to loading
    sm.db.ExecContext(ctx, `
        UPDATE ModelSlot SET Status = 'loading', ModelId = ?, UpdatedAt = datetime('now')
        WHERE Id = ?
    `, modelId, slot.Id)
    
    // Get model info
    model, err := sm.registryService.GetModelById(ctx, modelId)
    if err != nil {
        sm.db.ExecContext(ctx, `
            UPDATE ModelSlot SET Status = 'error', ErrorMessage = ?, UpdatedAt = datetime('now')
            WHERE Id = ?
        `, err.Error(), slot.Id)
        return err
    }
    
    // Build shell command from template
    cmd, err := sm.buildStartCommand(ctx, model, slot.Port)
    if err != nil {
        return err
    }
    
    // Start process
    if err := cmd.Start(); err != nil {
        sm.db.ExecContext(ctx, `
            UPDATE ModelSlot SET Status = 'error', ErrorMessage = ?, UpdatedAt = datetime('now')
            WHERE Id = ?
        `, err.Error(), slot.Id)
        return err
    }
    
    sm.processes[slot.SlotIndex] = cmd
    
    // Wait for health check
    if err := sm.waitForHealth(ctx, slot.Port); err != nil {
        cmd.Process.Kill()
        sm.db.ExecContext(ctx, `
            UPDATE ModelSlot SET Status = 'error', ErrorMessage = ?, UpdatedAt = datetime('now')
            WHERE Id = ?
        `, err.Error(), slot.Id)
        return err
    }
    
    // Update slot to active
    sm.db.ExecContext(ctx, `
        UPDATE ModelSlot 
        SET Status = 'active', 
            ProcessId = ?, 
            StartedAt = datetime('now'),
            LastAccessedAt = datetime('now'),
            ErrorMessage = NULL,
            UpdatedAt = datetime('now')
        WHERE Id = ?
    `, cmd.Process.Pid, slot.Id)
    
    return nil
}

// buildStartCommand constructs the shell command from template
func (sm *SlotManager) buildStartCommand(ctx context.Context, model *ModelInfo, port int) (*exec.Cmd, error) {
    template, _ := sm.configService.GetConfig(ctx, "llama.server.shellCommandTemplate")
    executable, _ := sm.configService.GetConfig(ctx, "llama.server.executablePath")
    bindAddress, _ := sm.configService.GetConfig(ctx, "llama.server.bindAddress")
    contextSize, _ := sm.configService.GetConfigAsInt(ctx, "llama.models.contextSize")
    gpuLayers, _ := sm.configService.GetConfigAsInt(ctx, "llama.models.gpuLayers")
    
    // Use model-specific overrides if set
    if model.ContextSize != nil {
        contextSize = *model.ContextSize
    }
    if model.GpuLayers != nil {
        gpuLayers = *model.GpuLayers
    }
    
    // Replace placeholders in template
    cmdStr := strings.ReplaceAll(template, "{executable}", executable)
    cmdStr = strings.ReplaceAll(cmdStr, "{modelPath}", model.ModelPath)
    cmdStr = strings.ReplaceAll(cmdStr, "{host}", bindAddress)
    cmdStr = strings.ReplaceAll(cmdStr, "{port}", fmt.Sprintf("%d", port))
    cmdStr = strings.ReplaceAll(cmdStr, "{contextSize}", fmt.Sprintf("%d", contextSize))
    cmdStr = strings.ReplaceAll(cmdStr, "{gpuLayers}", fmt.Sprintf("%d", gpuLayers))
    
    return exec.CommandContext(ctx, "sh", "-c", cmdStr), nil
}

// unloadSlot gracefully stops a model and frees the slot
func (sm *SlotManager) unloadSlot(ctx context.Context, slot *ModelSlot) error {
    sm.db.ExecContext(ctx, `
        UPDATE ModelSlot SET Status = 'unloading', UpdatedAt = datetime('now')
        WHERE Id = ?
    `, slot.Id)
    
    if proc, ok := sm.processes[slot.SlotIndex]; ok && proc.Process != nil {
        proc.Process.Kill()
        delete(sm.processes, slot.SlotIndex)
    }
    
    sm.db.ExecContext(ctx, `
        UPDATE ModelSlot 
        SET Status = 'idle', 
            ModelId = NULL, 
            ProcessId = NULL,
            StartedAt = NULL,
            LastAccessedAt = NULL,
            UpdatedAt = datetime('now')
        WHERE Id = ?
    `, slot.Id)
    
    return nil
}
```

---

## 7.5 LLaMA Server Manager

```go
// internal/services/llama_manager.go
package services

import (
    "context"
    "os/exec"
    "sync"
)

type LLaMAManager struct {
    configService *ConfigService
    mutex         sync.Mutex
    process       *exec.Cmd
    isRunning     bool
}

func NewLLaMAManager(configService *ConfigService) *LLaMAManager {
    return &LLaMAManager{
        configService: configService,
    }
}

func (m *LLaMAManager) Start(ctx context.Context, modelType string) error {
    m.mutex.Lock()
    defer m.mutex.Unlock()
    
    if m.isRunning {
        return nil // Already running
    }
    
    config, err := m.configService.GetLLaMAConfig(ctx)
    if err != nil {
        return err
    }
    
    // Select model based on type
    var modelPath string
    switch modelType {
    case "voice":
        modelPath = filepath.Join(config.ModelsDir, config.VoiceModel)
    case "reasoning":
        modelPath = filepath.Join(config.ModelsDir, config.ReasoningModel)
    default:
        return fmt.Errorf("unknown model type: %s", modelType)
    }
    
    // Build command arguments
    args := []string{
        "--model", modelPath,
        "--host", config.Host,
        "--port", strconv.Itoa(config.Port),
        "--ctx-size", strconv.Itoa(config.ContextSize),
        "--n-gpu-layers", strconv.Itoa(config.GPULayers),
    }
    
    m.process = exec.CommandContext(ctx, config.ServerPath, args...)
    
    if err := m.process.Start(); err != nil {
        return fmt.Errorf("failed to start llama server: %w", err)
    }
    
    m.isRunning = true
    
    // Wait for server to be ready
    return m.waitForReady(ctx, config.Host, config.Port)
}

func (m *LLaMAManager) Stop() error {
    m.mutex.Lock()
    defer m.mutex.Unlock()
    
    if !m.isRunning || m.process == nil {
        return nil
    }
    
    if err := m.process.Process.Kill(); err != nil {
        return err
    }
    
    m.isRunning = false
    m.process = nil
    return nil
}

func (m *LLaMAManager) IsRunning() bool {
    m.mutex.Lock()
    defer m.mutex.Unlock()
    return m.isRunning
}

func (m *LLaMAManager) waitForReady(ctx context.Context, host string, port int) error {
    url := fmt.Sprintf("http://%s:%d/health", host, port)
    
    for i := 0; i < 30; i++ { // Wait up to 30 seconds
        select {
        case <-ctx.Done():
            return ctx.Err()
        case <-time.After(time.Second):
            resp, err := http.Get(url)
            if err == nil && resp.StatusCode == 200 {
                resp.Body.Close()
                return nil
            }
        }
    }
    
    return fmt.Errorf("llama server failed to start within timeout")
}
```

---

## 7.4 AI Chain Service (with RAG Integration)

The AI Chain Service orchestrates the multi-stage pipeline with RAG context injection at key stages.

```go
// internal/services/ai_chain_service.go
package services

import (
    "context"
)

type AIChainService struct {
    llamaManager    *LLaMAManager
    configService   *ConfigService
    ragService      *RAGService      // RAG integration
    artifactService *ArtifactService // Artifact management
    db              *gorm.DB
}

func NewAIChainService(
    llamaManager *LLaMAManager,
    configService *ConfigService,
    ragService *RAGService,
    artifactService *ArtifactService,
    db *gorm.DB,
) *AIChainService {
    return &AIChainService{
        llamaManager:    llamaManager,
        configService:   configService,
        ragService:      ragService,
        artifactService: artifactService,
        db:              db,
    }
}

// Stage 1: Voice Transcription
func (s *AIChainService) TranscribeAudio(ctx context.Context, audioData []byte) (*TranscriptionResult, error) {
    config, _ := s.configService.GetLLaMAConfig(ctx)
    
    // Send to Whisper endpoint
    url := fmt.Sprintf("http://%s:%d/inference", config.Host, config.Port)
    
    req, err := http.NewRequestWithContext(ctx, "POST", url, bytes.NewReader(audioData))
    if err != nil {
        return nil, err
    }
    req.Header.Set("Content-Type", "audio/wav")
    
    resp, err := http.DefaultClient.Do(req)
    if err != nil {
        return nil, err
    }
    defer resp.Body.Close()
    
    var result TranscriptionResult
    if err := json.NewDecoder(resp.Body).Decode(&result); err != nil {
        return nil, err
    }
    
    return &result, nil
}

// Stage 2: RAG Context Retrieval (NEW)
// See: 16-rag-system.md for full retrieval pipeline
func (s *AIChainService) RetrieveRAGContext(ctx context.Context, projectId, queryText string) (*RAGContextResult, error) {
    // Retrieve relevant chunks from indexed artifacts
    session, err := s.ragService.Retrieve(ctx, &RetrieveRequest{
        ProjectId:      projectId,
        QueryText:      queryText,
        TopK:           10,
        IncludePinned:  true,
        IncludeRecent:  true,
    })
    if err != nil {
        return nil, err
    }
    
    // Format chunks for prompt injection
    var contextParts []string
    for _, chunk := range session.Chunks {
        contextParts = append(contextParts, fmt.Sprintf(
            "### From: %s (section: %s)\n%s",
            chunk.Artifact.RelativePath,
            chunk.SectionAnchor,
            chunk.Content,
        ))
    }
    
    return &RAGContextResult{
        SessionId:    session.Id,
        ContextText:  strings.Join(contextParts, "\n\n---\n\n"),
        ChunkCount:   len(session.Chunks),
        SourcePaths:  extractUniquePaths(session.Chunks),
    }, nil
}

// Stage 3: Reasoning - Analyze and Generate Questions (with RAG context)
func (s *AIChainService) AnalyzeIntent(ctx context.Context, req *AnalyzeRequest) (*AnalyzeResult, error) {
    config, _ := s.configService.GetLLaMAConfig(ctx)
    
    // Retrieve RAG context for grounded analysis
    ragCtx, err := s.RetrieveRAGContext(ctx, req.ProjectId, req.Text)
    if err != nil {
        // Continue without RAG context (graceful degradation)
        ragCtx = &RAGContextResult{ContextText: ""}
    }
    
    prompt := buildAnalysisPromptWithRAG(req.Text, req.ExistingSpecs, ragCtx.ContextText)
    
    response, err := s.callLLM(ctx, config, prompt)
    if err != nil {
        return nil, err
    }
    
    result, err := parseAnalysisResponse(response)
    if err != nil {
        return nil, err
    }
    
    result.RAGSessionId = ragCtx.SessionId
    result.RAGChunkCount = ragCtx.ChunkCount
    
    return result, nil
}

// Stage 4: Generate Idea/Spec (with RAG context)
func (s *AIChainService) GenerateSpec(ctx context.Context, req *GenerateRequest) (*GenerateResult, error) {
    config, _ := s.configService.GetLLaMAConfig(ctx)
    
    // Retrieve RAG context for grounded generation
    ragCtx, err := s.RetrieveRAGContext(ctx, req.ProjectId, req.Intent)
    if err != nil {
        ragCtx = &RAGContextResult{ContextText: ""}
    }
    
    var prompt string
    switch req.OutputType {
    case "idea":
        prompt = buildIdeaPromptWithRAG(req.Intent, req.Answers, ragCtx.ContextText)
    case "spec":
        prompt = buildSpecPromptWithRAG(req.Intent, req.Answers, req.ProjectContext, ragCtx.ContextText)
    default:
        return nil, fmt.Errorf("unknown output type: %s", req.OutputType)
    }
    
    response, err := s.callLLM(ctx, config, prompt)
    if err != nil {
        return nil, err
    }
    
    // Save as artifact and trigger reindex
    // See: 11-instruction-system.md for artifact lifecycle
    artifact, err := s.artifactService.SaveArtifact(ctx, &SaveArtifactRequest{
        ProjectId:    req.ProjectId,
        ArtifactType: req.OutputType,
        Content:      response,
        UserId:       req.UserId,
    })
    if err != nil {
        return nil, err
    }
    
    // Trigger RAG reindex for new artifact
    go s.ragService.TriggerReindex(context.Background(), artifact.Id)
    
    return &GenerateResult{
        Content:      response,
        OutputType:   req.OutputType,
        ArtifactId:   artifact.Id,
        ArtifactPath: artifact.RelativePath,
        RAGSessionId: ragCtx.SessionId,
    }, nil
}

// buildAnalysisPromptWithRAG injects RAG context into the analysis prompt
func buildAnalysisPromptWithRAG(text string, existingSpecs []string, ragContext string) string {
    var sb strings.Builder
    
    sb.WriteString("[INST] You are an expert specification analyst.\n\n")
    
    // Inject RAG context if available
    if ragContext != "" {
        sb.WriteString("## Relevant Context from Existing Specifications\n\n")
        sb.WriteString(ragContext)
        sb.WriteString("\n\n---\n\n")
    }
    
    sb.WriteString("## User Request\n\n")
    sb.WriteString(text)
    sb.WriteString("\n\n")
    
    sb.WriteString("## Task\n\n")
    sb.WriteString("1. Identify the user's intent\n")
    sb.WriteString("2. List any ambiguities that need clarification\n")
    sb.WriteString("3. Generate clarifying questions if needed\n")
    sb.WriteString("4. Reference relevant context from existing specs where applicable\n\n")
    
    sb.WriteString("Respond in JSON format. [/INST]")
    
    return sb.String()
}

// buildIdeaPromptWithRAG injects RAG context into idea generation
func buildIdeaPromptWithRAG(intent string, answers map[string]string, ragContext string) string {
    var sb strings.Builder
    
    sb.WriteString("[INST] You are a technical writer creating an idea document.\n\n")
    
    if ragContext != "" {
        sb.WriteString("## Related Context\n\n")
        sb.WriteString(ragContext)
        sb.WriteString("\n\n---\n\n")
    }
    
    sb.WriteString("## Intent\n\n")
    sb.WriteString(intent)
    sb.WriteString("\n\n")
    
    if len(answers) > 0 {
        sb.WriteString("## Clarifications\n\n")
        for q, a := range answers {
            sb.WriteString(fmt.Sprintf("- **%s**: %s\n", q, a))
        }
        sb.WriteString("\n")
    }
    
    sb.WriteString("Generate a well-structured idea document in Markdown format.\n")
    sb.WriteString("Include cross-references to related specs where appropriate. [/INST]")
    
    return sb.String()
}

// buildSpecPromptWithRAG injects RAG context into spec generation
func buildSpecPromptWithRAG(intent string, answers map[string]string, projectContext string, ragContext string) string {
    var sb strings.Builder
    
    sb.WriteString("[INST] You are a technical specification writer.\n\n")
    
    if ragContext != "" {
        sb.WriteString("## Relevant Existing Specifications\n\n")
        sb.WriteString(ragContext)
        sb.WriteString("\n\n---\n\n")
    }
    
    if projectContext != "" {
        sb.WriteString("## Project Context\n\n")
        sb.WriteString(projectContext)
        sb.WriteString("\n\n")
    }
    
    sb.WriteString("## Requirement\n\n")
    sb.WriteString(intent)
    sb.WriteString("\n\n")
    
    if len(answers) > 0 {
        sb.WriteString("## Clarifications\n\n")
        for q, a := range answers {
            sb.WriteString(fmt.Sprintf("- **%s**: %s\n", q, a))
        }
        sb.WriteString("\n")
    }
    
    sb.WriteString("Generate a complete technical specification in Markdown format.\n")
    sb.WriteString("Include:\n")
    sb.WriteString("- Version header\n")
    sb.WriteString("- Overview section\n")
    sb.WriteString("- Detailed requirements\n")
    sb.WriteString("- Cross-references to related specs (use [[path]] links)\n")
    sb.WriteString("- Acceptance criteria [/INST]")
    
    return sb.String()
}

func (s *AIChainService) callLLM(ctx context.Context, config *LLaMAConfig, prompt string) (string, error) {
    url := fmt.Sprintf("http://%s:%d/completion", config.Host, config.Port)
    
    payload := map[string]interface{}{
        "prompt":      prompt,
        "n_predict":   2048,
        "temperature": 0.7,
        "stop":        []string{"</s>", "[INST]"},
    }
    
    body, _ := json.Marshal(payload)
    req, err := http.NewRequestWithContext(ctx, "POST", url, bytes.NewReader(body))
    if err != nil {
        return "", err
    }
    req.Header.Set("Content-Type", "application/json")
    
    resp, err := http.DefaultClient.Do(req)
    if err != nil {
        return "", err
    }
    defer resp.Body.Close()
    
    var result struct {
        Content string `json:"content"`
    }
    if err := json.NewDecoder(resp.Body).Decode(&result); err != nil {
        return "", err
    }
    
    return result.Content, nil
}

// Types
type TranscriptionResult struct {
    Text       string  `json:"text"`
    Language   string  `json:"language"`
    Confidence float64 `json:"confidence"`
}

type RAGContextResult struct {
    SessionId   string   `json:"sessionId"`
    ContextText string   `json:"contextText"`
    ChunkCount  int      `json:"chunkCount"`
    SourcePaths []string `json:"sourcePaths"`
}

type AnalyzeRequest struct {
    Text          string   `json:"text"`
    ProjectId     string   `json:"projectId"`
    ExistingSpecs []string `json:"existingSpecs"`
}

type AnalyzeResult struct {
    Intent       string     `json:"intent"`
    Ambiguities  []string   `json:"ambiguities"`
    Questions   []Question `json:"questions"`
    Validated   bool       `json:"validated"`
}

type Question struct {
    ID       string   `json:"id"`
    Text     string   `json:"text"`
    Type     string   `json:"type"` // "text", "choice", "confirm"
    Options  []string `json:"options,omitempty"`
    Required bool     `json:"required"`
}

type GenerateRequest struct {
    Intent         string            `json:"intent"`
    Answers        map[string]string `json:"answers"`
    OutputType     string            `json:"outputType"` // "idea" or "spec"
    ProjectContext string            `json:"projectContext"`
}

type GenerateResult struct {
    Content    string `json:"content"`
    OutputType string `json:"outputType"`
}
```

---

## 7.5 Prompt Templates

```go
// internal/prompts/templates.go
package prompts

const AnalysisPromptTemplate = `[INST] You are an expert software architect analyzing a user's request to create a specification document.

User Request:
{{.Text}}

Existing Specifications (for context):
{{range .ExistingSpecs}}
- {{.}}
{{end}}

Your task:
1. Identify the core intent of the request
2. List any ambiguities or missing information
3. Generate clarifying questions to resolve ambiguities
4. Check if the request conflicts with existing specs

Respond in JSON format:
{
  "intent": "concise description of what the user wants",
  "ambiguities": ["list of unclear points"],
  "questions": [
    {
      "id": "q1",
      "text": "question text",
      "type": "text|choice|confirm",
      "options": ["only for choice type"],
      "required": true
    }
  ],
  "validated": true/false
}
[/INST]`

const IdeaPromptTemplate = `[INST] You are an expert software architect creating an idea document.

User Intent: {{.Intent}}

User Answers to Clarifying Questions:
{{range $id, $answer := .Answers}}
- {{$id}}: {{$answer}}
{{end}}

Create a concise idea document in Markdown format following this structure:

# [Descriptive Title]

## Problem Statement
What problem does this solve?

## Proposed Solution
High-level description of the solution.

## Key Features
- Feature 1
- Feature 2
- ...

## Open Questions
Questions to resolve before detailed specification.

## Next Steps
What needs to happen next.
[/INST]`

const SpecPromptTemplate = `[INST] You are an expert software architect creating a detailed specification document.

User Intent: {{.Intent}}

User Answers:
{{range $id, $answer := .Answers}}
- {{$id}}: {{$answer}}
{{end}}

Project Context:
{{.ProjectContext}}

Create a detailed specification document in Markdown following the project's conventions:

1. Use numbered sections (## 1.1, ## 1.2, etc.)
2. Include code examples where relevant
3. Define data structures and interfaces
4. List acceptance criteria
5. Note dependencies on other specs

Be thorough but concise. Include implementation details.
[/INST]`
```

---

## 7.6 API Endpoints

### Model Registry Endpoints

```go
// GET /api/v1/ai/models
// List all models in registry
{
    "success": true,
    "data": {
        "items": [
            {
                "id": "uuid-1",
                "displayName": "Whisper Large V3",
                "fileName": "whisper-large-v3.gguf",
                "modelType": "voice",
                "modelPath": "/models/whisper-large-v3.gguf",
                "fileSizeBytes": 3094000000,
                "tags": ["speech", "multilingual"],
                "isEnabled": true,
                "contextSize": null,
                "gpuLayers": null,
                "lastScannedAt": "2026-01-27T10:00:00Z"
            },
            {
                "id": "uuid-2",
                "displayName": "Mixtral 8x7B Instruct",
                "fileName": "mixtral-8x7b-instruct.gguf",
                "modelType": "reasoning",
                "modelPath": "/models/mixtral-8x7b-instruct.gguf",
                "fileSizeBytes": 26000000000,
                "tags": ["instruct", "large"],
                "isEnabled": true
            }
        ],
        "count": 2
    },
    "error": null,
    "meta": {}
}

// GET /api/v1/ai/models/{id}
// Get single model details
{
    "success": true,
    "data": {
        "id": "uuid-1",
        "displayName": "Whisper Large V3",
        "fileName": "whisper-large-v3.gguf",
        "modelType": "voice",
        ...
    }
}

// PUT /api/v1/ai/models/{id}
// Update model settings (display name, tags, enabled, overrides)
Request:
{
    "displayName": "Whisper Large V3 (Optimized)",
    "tags": ["speech", "multilingual", "optimized"],
    "isEnabled": true,
    "contextSize": 4096,
    "gpuLayers": 20
}

// POST /api/v1/ai/models/scan
// Trigger model directory rescan
{
    "success": true,
    "data": {
        "discovered": 5,
        "added": 2,
        "updated": 3,
        "removed": 0
    }
}
```

### Model Slot Endpoints

```go
// GET /api/v1/ai/slots
// List all model slots and their status
{
    "success": true,
    "data": {
        "items": [
            {
                "id": "slot-uuid-1",
                "slotIndex": 0,
                "port": 8080,
                "modelId": "uuid-1",
                "modelName": "Whisper Large V3",
                "status": "active",
                "startedAt": "2026-01-27T08:00:00Z",
                "lastAccessedAt": "2026-01-27T12:30:00Z"
            },
            {
                "id": "slot-uuid-2",
                "slotIndex": 1,
                "port": 8081,
                "modelId": "uuid-2",
                "modelName": "Mixtral 8x7B",
                "status": "active",
                "startedAt": "2026-01-27T09:15:00Z",
                "lastAccessedAt": "2026-01-27T12:28:00Z"
            },
            {
                "id": "slot-uuid-3",
                "slotIndex": 2,
                "port": 8082,
                "modelId": null,
                "modelName": null,
                "status": "idle"
            }
        ],
        "maxConcurrentModels": 3,
        "activeCount": 2
    }
}

// POST /api/v1/ai/slots/request
// Request a model to be loaded, returns port when ready
Request:
{
    "modelId": "uuid-2",
    "projectId": "project-uuid",  // optional: for project default resolution
    "instructionOverride": false  // optional: if true, uses modelId directly
}

Response:
{
    "success": true,
    "data": {
        "slotIndex": 1,
        "port": 8081,
        "status": "active",
        "wasAlreadyLoaded": true
    }
}

// POST /api/v1/ai/slots/{slotIndex}/unload
// Manually unload a model from a slot
{
    "success": true,
    "data": {
        "slotIndex": 1,
        "previousModelId": "uuid-2",
        "status": "idle"
    }
}

// GET /api/v1/ai/slots/{slotIndex}/health
// Health check for a specific slot
{
    "success": true,
    "data": {
        "slotIndex": 1,
        "port": 8081,
        "healthy": true,
        "responseTimeMs": 45,
        "lastCheckedAt": "2026-01-27T12:35:00Z"
    }
}
```

### Model Selection Defaults Endpoints

```go
// GET /api/v1/ai/defaults
// Get current user's model defaults
{
    "success": true,
    "data": {
        "systemDefaults": {
            "reasoningModelId": "uuid-2",
            "voiceModelId": "uuid-1"
        },
        "userDefaults": {
            "reasoningModelId": "uuid-3",
            "voiceModelId": null
        },
        "resolved": {
            "reasoningModelId": "uuid-3",
            "reasoningModelName": "Llama 3 70B",
            "voiceModelId": "uuid-1",
            "voiceModelName": "Whisper Large V3"
        }
    }
}

// PUT /api/v1/ai/defaults/user
// Set user's default models
Request:
{
    "reasoningModelId": "uuid-3",
    "voiceModelId": "uuid-1"
}

// GET /api/v1/projects/{projectId}/ai-settings
// Get project-specific AI settings
{
    "success": true,
    "data": {
        "projectId": "project-uuid",
        "defaultReasoningModelId": "uuid-4",
        "defaultVoiceModelId": null,
        "instructionApprovalRequired": true
    }
}

// PUT /api/v1/projects/{projectId}/ai-settings
// Update project-specific AI settings
Request:
{
    "defaultReasoningModelId": "uuid-4",
    "defaultVoiceModelId": null,
    "instructionApprovalRequired": true
}
```

### Configuration Endpoints

```go
// GET /api/v1/ai/config
// Returns current LLaMA configuration
{
    "success": true,
    "data": {
        "serverPath": "/usr/local/bin/llama-server",
        "bindAddress": "127.0.0.1",
        "basePort": 8080,
        "portRangeStart": 8081,
        "portRangeEnd": 8089,
        "maxConcurrentModels": 3,
        "shellCommandTemplate": "{executable} --model {modelPath} --host {host} --port {port} ...",
        "modelRootPaths": ["/models", "/models-extra"],
        "contextSize": 8192,
        "gpuLayers": 35
    }
}

// PUT /api/v1/ai/config
// Update LLaMA configuration
Request:
{
    "maxConcurrentModels": 4,
    "contextSize": 16384
}
```

### AI Chain Endpoints

```go
// POST /api/v1/ai/transcribe
// Transcribe audio to text
Request: multipart/form-data with "audio" file
Response:
{
    "success": true,
    "data": {
        "text": "I want to create a new feature for user authentication...",
        "language": "en",
        "confidence": 0.95
    }
}

// POST /api/v1/ai/analyze
// Analyze intent and generate questions
Request:
{
    "text": "I want to add OAuth support to the authentication system",
    "existingSpecs": ["spec/auth/01-overview.md", "spec/auth/02-login.md"]
}

Response:
{
    "success": true,
    "data": {
        "intent": "Add OAuth 2.0 authentication providers",
        "ambiguities": [
            "Which OAuth providers to support?",
            "Should existing password auth be kept?"
        ],
        "questions": [
            {
                "id": "q1",
                "text": "Which OAuth providers should be supported?",
                "type": "choice",
                "options": ["Google", "GitHub", "Microsoft", "All of the above"],
                "required": true
            },
            {
                "id": "q2",
                "text": "Should password authentication remain as a fallback?",
                "type": "confirm",
                "required": true
            }
        ],
        "validated": true
    }
}

// POST /api/v1/ai/generate
// Generate idea or spec document
Request:
{
    "intent": "Add OAuth 2.0 authentication providers",
    "answers": {
        "q1": "All of the above",
        "q2": "true"
    },
    "outputType": "spec",
    "projectContext": "spec/auth"
}

Response:
{
    "success": true,
    "data": {
        "content": "# OAuth Integration\n\n## 1.1 Overview\n...",
        "outputType": "spec"
    }
}

// POST /api/v1/ai/generate/stream
// Stream generation for real-time display
// Returns Server-Sent Events
```

---

## 7.7 Error Codes

| Code | Constant | HTTP | Description |
|------|----------|------|-------------|
| 7001 | ERR_LLAMA_NOT_RUNNING | 500 | LLaMA server not running |
| 7002 | ERR_MODEL_FILE_NOT_FOUND | 404 | Model file not found on disk |
| 7003 | ERR_AUDIO_INVALID_FORMAT | 400 | Invalid audio format for transcription |
| 7004 | ERR_TRANSCRIPTION_FAILED | 500 | Voice transcription failed |
| 7005 | ERR_GENERATION_FAILED | 500 | Text generation failed |
| 7006 | ERR_SERVER_START_TIMEOUT | 500 | Server start exceeded timeout |
| 7007 | ERR_INVALID_MODEL_TYPE | 400 | Invalid model type (not 'reasoning' or 'voice') |
| 7008 | ERR_SERVER_BUSY | 503 | Server busy (model loading) |
| 7009 | ERR_ALL_SLOTS_FULL | 503 | All model slots occupied |
| 7010 | ERR_MODEL_LOAD_FAILED | 500 | Failed to load model into slot |
| 7011 | ERR_MODEL_NOT_IN_REGISTRY | 404 | Model ID not found in registry |
| 7012 | ERR_SLOT_NOT_FOUND | 404 | Slot index not found |
| 7013 | ERR_HEALTH_CHECK_FAILED | 500 | Model health check failed |
| 7014 | ERR_MODEL_SCAN_FAILED | 500 | Failed to scan model directories |

---

## 7.8 Acceptance Criteria

### Model Registry
- [ ] Models discovered from configured root paths
- [ ] Model type inferred from filename patterns
- [ ] Registry syncs on startup (when mode = onStartup)
- [ ] Manual scan triggers rescan of all paths
- [ ] Model enable/disable prevents selection
- [ ] Model-specific context/GPU overrides work

### Model Selection
- [ ] Per-instruction override has highest priority
- [ ] Per-project default used when no override
- [ ] Per-user default used when no project default
- [ ] System default used as final fallback
- [ ] Resolution returns first enabled model if no defaults set

### Multi-Model Slots
- [ ] Slots initialized on server startup
- [ ] Request model loads into available slot
- [ ] Already-loaded model returns port immediately
- [ ] LRU eviction when slots full
- [ ] Health checks detect crashed models
- [ ] Slot unload gracefully stops process

### API Endpoints
- [ ] All model registry CRUD operations work
- [ ] Slot status reflects actual process state
- [ ] Model request blocks until ready
- [ ] Default model settings persist correctly
- [ ] Project AI settings stored and retrieved

### AI Chain
- [ ] Voice transcription works with voice model
- [ ] Analysis generates clarifying questions
- [ ] Generation produces valid Markdown
- [ ] Streaming generation works
- [ ] Model selection hierarchy applied to chain requests

---

## 7.15 Transport Format Service

The Transport Format Service handles serialization/deserialization of AI request/response payloads in configurable formats.

### Supported Formats

| Format | Extension | Use Case |
|--------|-----------|----------|
| `json` | `.json` | Default, structured API communication |
| `yaml` | `.yaml` | Human-readable, good for prompts with multiline content |
| `toml` | `.toml` | Configuration-style, clear key-value separation |
| `markdown` | `.md` | Documentation-friendly, rendered prompts/responses |
| `file` | varies | Large payloads, audit trail, batch processing |

### Transport Envelope

All formats use a common envelope structure:

```go
// internal/models/transport_envelope.go
package models

import "time"

type TransportEnvelope struct {
    RequestId    string                 `json:"requestId" yaml:"requestId" toml:"request_id"`
    Timestamp    time.Time              `json:"timestamp" yaml:"timestamp" toml:"timestamp"`
    ModelId      string                 `json:"modelId" yaml:"modelId" toml:"model_id"`
    SlotPort     int                    `json:"slotPort,omitempty" yaml:"slotPort,omitempty" toml:"slot_port,omitempty"`
    Format       string                 `json:"format" yaml:"format" toml:"format"`
    Direction    string                 `json:"direction" yaml:"direction" toml:"direction"` // "request" or "response"
    ContentType  string                 `json:"contentType" yaml:"contentType" toml:"content_type"`
    Payload      interface{}            `json:"payload" yaml:"payload" toml:"payload"`
    Metadata     map[string]interface{} `json:"metadata,omitempty" yaml:"metadata,omitempty" toml:"metadata,omitempty"`
}

type AIRequest struct {
    Prompt       string                 `json:"prompt" yaml:"prompt" toml:"prompt"`
    SystemPrompt string                 `json:"systemPrompt,omitempty" yaml:"systemPrompt,omitempty" toml:"system_prompt,omitempty"`
    MaxTokens    int                    `json:"maxTokens,omitempty" yaml:"maxTokens,omitempty" toml:"max_tokens,omitempty"`
    Temperature  float64                `json:"temperature,omitempty" yaml:"temperature,omitempty" toml:"temperature,omitempty"`
    Context      []ContextChunk         `json:"context,omitempty" yaml:"context,omitempty" toml:"context,omitempty"`
}

type AIResponse struct {
    Content      string                 `json:"content" yaml:"content" toml:"content"`
    TokensUsed   TokenUsage             `json:"tokensUsed" yaml:"tokensUsed" toml:"tokens_used"`
    FinishReason string                 `json:"finishReason" yaml:"finishReason" toml:"finish_reason"`
    Duration     int64                  `json:"durationMs" yaml:"durationMs" toml:"duration_ms"`
}

type TokenUsage struct {
    Prompt     int `json:"prompt" yaml:"prompt" toml:"prompt"`
    Completion int `json:"completion" yaml:"completion" toml:"completion"`
    Total      int `json:"total" yaml:"total" toml:"total"`
}
```

### Transport Format Service Implementation

```go
// internal/services/transport_format_service.go
package services

import (
    "bytes"
    "encoding/json"
    "fmt"
    "os"
    "path/filepath"
    "strings"
    "text/template"
    "time"

    "github.com/BurntSushi/toml"
    "gopkg.in/yaml.v3"
)

type TransportFormat string

const (
    FormatJSON     TransportFormat = "json"
    FormatYAML     TransportFormat = "yaml"
    FormatTOML     TransportFormat = "toml"
    FormatMarkdown TransportFormat = "markdown"
    FormatFile     TransportFormat = "file"
)

type TransportFormatService struct {
    configService *ConfigService
    format        TransportFormat
    outputDir     string
    prettyPrint   bool
    mdTemplates   map[string]*template.Template
}

func NewTransportFormatService(configService *ConfigService) (*TransportFormatService, error) {
    ctx := context.Background()
    
    format, _ := configService.GetConfig(ctx, "ai.transport.format")
    outputDir, _ := configService.GetConfig(ctx, "ai.transport.fileOutputDir")
    prettyPrint, _ := configService.GetConfigAsBool(ctx, "ai.transport.prettyPrint")
    
    svc := &TransportFormatService{
        configService: configService,
        format:        TransportFormat(format),
        outputDir:     outputDir,
        prettyPrint:   prettyPrint,
        mdTemplates:   make(map[string]*template.Template),
    }
    
    // Load Markdown templates
    if err := svc.loadMarkdownTemplates(); err != nil {
        return nil, err
    }
    
    return svc, nil
}

// Encode serializes data to configured format
func (s *TransportFormatService) Encode(envelope *TransportEnvelope) ([]byte, error) {
    switch s.format {
    case FormatJSON:
        return s.encodeJSON(envelope)
    case FormatYAML:
        return s.encodeYAML(envelope)
    case FormatTOML:
        return s.encodeTOML(envelope)
    case FormatMarkdown:
        return s.encodeMarkdown(envelope)
    case FormatFile:
        return s.encodeToFile(envelope)
    default:
        return s.encodeJSON(envelope)
    }
}

// Decode deserializes data from configured format
func (s *TransportFormatService) Decode(data []byte, envelope *TransportEnvelope) error {
    switch s.format {
    case FormatJSON:
        return json.Unmarshal(data, envelope)
    case FormatYAML:
        return yaml.Unmarshal(data, envelope)
    case FormatTOML:
        return toml.Unmarshal(data, envelope)
    case FormatMarkdown:
        return s.decodeMarkdown(data, envelope)
    case FormatFile:
        return s.decodeFromFile(data, envelope)
    default:
        return json.Unmarshal(data, envelope)
    }
}

func (s *TransportFormatService) encodeJSON(envelope *TransportEnvelope) ([]byte, error) {
    if s.prettyPrint {
        return json.MarshalIndent(envelope, "", "  ")
    }
    return json.Marshal(envelope)
}

func (s *TransportFormatService) encodeYAML(envelope *TransportEnvelope) ([]byte, error) {
    return yaml.Marshal(envelope)
}

func (s *TransportFormatService) encodeTOML(envelope *TransportEnvelope) ([]byte, error) {
    var buf bytes.Buffer
    encoder := toml.NewEncoder(&buf)
    if err := encoder.Encode(envelope); err != nil {
        return nil, err
    }
    return buf.Bytes(), nil
}

func (s *TransportFormatService) encodeMarkdown(envelope *TransportEnvelope) ([]byte, error) {
    templateName, _ := s.configService.GetConfig(context.Background(), "ai.transport.markdownTemplate")
    if templateName == "" {
        templateName = "default"
    }
    
    tmpl, ok := s.mdTemplates[templateName]
    if !ok {
        tmpl = s.mdTemplates["default"]
    }
    
    var buf bytes.Buffer
    if err := tmpl.Execute(&buf, envelope); err != nil {
        return nil, err
    }
    return buf.Bytes(), nil
}

func (s *TransportFormatService) encodeToFile(envelope *TransportEnvelope) ([]byte, error) {
    // Ensure output directory exists
    if err := os.MkdirAll(s.outputDir, 0755); err != nil {
        return nil, err
    }
    
    // Generate filename: {requestId}_{direction}_{timestamp}.json
    filename := fmt.Sprintf("%s_%s_%d.json",
        envelope.RequestId,
        envelope.Direction,
        envelope.Timestamp.UnixMilli(),
    )
    filepath := filepath.Join(s.outputDir, filename)
    
    // Write JSON content to file
    content, err := json.MarshalIndent(envelope, "", "  ")
    if err != nil {
        return nil, err
    }
    
    if err := os.WriteFile(filepath, content, 0644); err != nil {
        return nil, err
    }
    
    // Return file path reference
    return []byte(filepath), nil
}

func (s *TransportFormatService) decodeFromFile(data []byte, envelope *TransportEnvelope) error {
    filepath := strings.TrimSpace(string(data))
    content, err := os.ReadFile(filepath)
    if err != nil {
        return err
    }
    return json.Unmarshal(content, envelope)
}

func (s *TransportFormatService) decodeMarkdown(data []byte, envelope *TransportEnvelope) error {
    // Parse Markdown with YAML frontmatter
    content := string(data)
    
    if strings.HasPrefix(content, "---") {
        parts := strings.SplitN(content, "---", 3)
        if len(parts) >= 3 {
            // Parse YAML frontmatter
            if err := yaml.Unmarshal([]byte(parts[1]), envelope); err != nil {
                return err
            }
            // Body becomes payload content
            if req, ok := envelope.Payload.(*AIRequest); ok {
                req.Prompt = strings.TrimSpace(parts[2])
            }
        }
    }
    return nil
}

func (s *TransportFormatService) loadMarkdownTemplates() error {
    s.mdTemplates["default"] = template.Must(template.New("default").Parse(`---
requestId: {{.RequestId}}
timestamp: {{.Timestamp.Format "2006-01-02T15:04:05Z07:00"}}
modelId: {{.ModelId}}
direction: {{.Direction}}
---

# AI {{if eq .Direction "request"}}Request{{else}}Response{{end}}

{{if eq .Direction "request"}}
## System Prompt
{{with .Payload}}{{.SystemPrompt}}{{end}}

## User Prompt
{{with .Payload}}{{.Prompt}}{{end}}

{{if .Payload.Context}}
## Context
{{range .Payload.Context}}
- **{{.Source}}**: {{.Content | truncate 200}}
{{end}}
{{end}}
{{else}}
## Response Content
{{with .Payload}}{{.Content}}{{end}}

## Token Usage
- Prompt: {{with .Payload}}{{.TokensUsed.Prompt}}{{end}}
- Completion: {{with .Payload}}{{.TokensUsed.Completion}}{{end}}
- Total: {{with .Payload}}{{.TokensUsed.Total}}{{end}}
{{end}}
`))

    s.mdTemplates["minimal"] = template.Must(template.New("minimal").Parse(`# {{.Direction}} | {{.RequestId}}
{{with .Payload}}{{if eq $.Direction "request"}}{{.Prompt}}{{else}}{{.Content}}{{end}}{{end}}
`))

    s.mdTemplates["verbose"] = template.Must(template.New("verbose").Parse(`---
requestId: {{.RequestId}}
timestamp: {{.Timestamp.Format "2006-01-02T15:04:05Z07:00"}}
modelId: {{.ModelId}}
slotPort: {{.SlotPort}}
format: {{.Format}}
direction: {{.Direction}}
contentType: {{.ContentType}}
metadata: {{.Metadata | toJSON}}
---

# AI {{.Direction | title}} Details

## Envelope Metadata
| Field | Value |
|-------|-------|
| Request ID | {{.RequestId}} |
| Timestamp | {{.Timestamp}} |
| Model ID | {{.ModelId}} |
| Slot Port | {{.SlotPort}} |
| Format | {{.Format}} |

{{if eq .Direction "request"}}
## System Prompt
\`\`\`
{{with .Payload}}{{.SystemPrompt}}{{end}}
\`\`\`

## User Prompt
\`\`\`
{{with .Payload}}{{.Prompt}}{{end}}
\`\`\`

## Parameters
- Max Tokens: {{with .Payload}}{{.MaxTokens}}{{end}}
- Temperature: {{with .Payload}}{{.Temperature}}{{end}}

{{if .Payload.Context}}
## RAG Context Chunks
{{range $i, $chunk := .Payload.Context}}
### Chunk {{$i}}
- Source: {{$chunk.Source}}
- Score: {{$chunk.Score}}

\`\`\`
{{$chunk.Content}}
\`\`\`
{{end}}
{{end}}
{{else}}
## Response Content
\`\`\`
{{with .Payload}}{{.Content}}{{end}}
\`\`\`

## Execution Details
| Metric | Value |
|--------|-------|
| Duration | {{with .Payload}}{{.Duration}}ms{{end}} |
| Finish Reason | {{with .Payload}}{{.FinishReason}}{{end}} |
| Prompt Tokens | {{with .Payload}}{{.TokensUsed.Prompt}}{{end}} |
| Completion Tokens | {{with .Payload}}{{.TokensUsed.Completion}}{{end}} |
| Total Tokens | {{with .Payload}}{{.TokensUsed.Total}}{{end}} |
{{end}}
`))

    return nil
}

// GetFormat returns current configured format
func (s *TransportFormatService) GetFormat() TransportFormat {
    return s.format
}

// SetFormat updates format at runtime (persists to DB)
func (s *TransportFormatService) SetFormat(ctx context.Context, format TransportFormat) error {
    if err := s.configService.SetConfig(ctx, "ai.transport.format", string(format)); err != nil {
        return err
    }
    s.format = format
    return nil
}

// CleanupOldFiles removes transport files older than retention period
func (s *TransportFormatService) CleanupOldFiles(ctx context.Context) (int, error) {
    retentionHours, _ := s.configService.GetConfigAsInt(ctx, "ai.transport.fileRetentionHours")
    if retentionHours == 0 {
        retentionHours = 24
    }
    
    cutoff := time.Now().Add(-time.Duration(retentionHours) * time.Hour)
    deleted := 0
    
    entries, err := os.ReadDir(s.outputDir)
    if err != nil {
        return 0, err
    }
    
    for _, entry := range entries {
        info, _ := entry.Info()
        if info.ModTime().Before(cutoff) {
            os.Remove(filepath.Join(s.outputDir, entry.Name()))
            deleted++
        }
    }
    
    return deleted, nil
}
```

### Format Selection in AI Chain

```go
// Example: Using TransportFormatService in AI requests
func (s *AIService) GenerateWithTransport(ctx context.Context, req *AIRequest) (*AIResponse, error) {
    transportSvc := s.transportFormatService
    
    // Create request envelope
    requestEnvelope := &TransportEnvelope{
        RequestId:   generateRequestId(),
        Timestamp:   time.Now(),
        ModelId:     s.currentModel.Id,
        SlotPort:    s.currentSlot.Port,
        Format:      string(transportSvc.GetFormat()),
        Direction:   "request",
        ContentType: "ai/chat",
        Payload:     req,
        Metadata: map[string]interface{}{
            "userId":    ctx.Value("userId"),
            "projectId": ctx.Value("projectId"),
        },
    }
    
    // Encode for logging/audit
    encoded, err := transportSvc.Encode(requestEnvelope)
    if err != nil {
        return nil, err
    }
    s.logger.Debug("AI Request", map[string]interface{}{
        "format":  transportSvc.GetFormat(),
        "encoded": string(encoded),
    })
    
    // Execute AI call...
    response, err := s.executeAICall(ctx, req)
    if err != nil {
        return nil, err
    }
    
    // Create response envelope
    responseEnvelope := &TransportEnvelope{
        RequestId:   requestEnvelope.RequestId,
        Timestamp:   time.Now(),
        ModelId:     s.currentModel.Id,
        SlotPort:    s.currentSlot.Port,
        Format:      string(transportSvc.GetFormat()),
        Direction:   "response",
        ContentType: "ai/chat",
        Payload:     response,
    }
    
    // Encode for logging/audit
    encoded, _ = transportSvc.Encode(responseEnvelope)
    s.logger.Debug("AI Response", map[string]interface{}{
        "format":  transportSvc.GetFormat(),
        "encoded": string(encoded),
    })
    
    return response, nil
}
```

---

## 7.16 Acceptance Criteria (Transport Format)

- [ ] JSON format encodes/decodes correctly with optional pretty-print
- [ ] YAML format preserves multiline strings properly
- [ ] TOML format uses snake_case keys as per TOML convention
- [ ] Markdown format renders human-readable documents with frontmatter
- [ ] File format writes to configured directory with proper naming
- [ ] File cleanup job removes files older than retention period
- [ ] Format can be changed at runtime via API
- [ ] All formats include metadata when `includeMetadata` is enabled
- [ ] Markdown templates (default, minimal, verbose) render correctly

---

## Related Specs

- [Backend Overview](./01-overview.md)
- [RAG System](../09-knowledge-memory/01-rag-system.md) - Context retrieval for grounded generation
- [Instruction System](./03-instruction-system.md) - Idea promotion and artifact lifecycle
- [Database Schema](../../07-database-design/01-schema.md)
- [Path Manager](../02-file-management/02-path-manager.md) - Artifact path handling
- [Voice Input](../05-voice-input/00-overview.md)
- [AI Chat UI](./08-ai-chat-ui.md)
