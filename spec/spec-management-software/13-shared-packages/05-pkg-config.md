# pkg/config Specification

**Version:** 1.0.0  
**Status:** Active  
**Updated:** 2026-01-30  
**Priority:** P1  

---

## Overview

The `pkg/config` package provides configuration management using Viper. It supports multiple config sources (files, environment variables), validation, and type-safe access to configuration values.

**Cross-References:**
- [pkg/errors Specification](./02-pkg-errors.md)
- [pkg/types Specification](./03-pkg-types.md)

---

## File Structure

```
pkg/config/
├── loader.go      # Config file loading
├── validation.go  # Config validation
├── types.go       # Config struct definitions
├── env.go         # Environment variable handling
├── defaults.go    # Default values
└── config_test.go
```

---

## Design Principles

1. **Fail fast** - Invalid config prevents startup
2. **Environment-aware** - Different defaults per environment
3. **Type-safe** - Strongly typed config structs
4. **Validated** - All values validated on load
5. **Documented** - Self-documenting config structure

---

## types.go

```go
package config

import (
    "time"
    
    "github.com/specbuilder/pkg/logging"
)

// Config is the root configuration structure
type Config struct {
    Environment Environment    `mapstructure:"environment"`
    Server      ServerConfig   `mapstructure:"server"`
    Database    DatabaseConfig `mapstructure:"database"`
    Logging     LoggingConfig  `mapstructure:"logging"`
    Services    ServicesConfig `mapstructure:"services"`
    Security    SecurityConfig `mapstructure:"security"`
    AI          AIConfig       `mapstructure:"ai"`
}

// Environment represents deployment environment
type Environment string

const (
    EnvDevelopment Environment = "development"
    EnvStaging     Environment = "staging"
    EnvProduction  Environment = "production"
)

// IsDevelopment returns true for development environment
func (e Environment) IsDevelopment() bool {
    return e == EnvDevelopment
}

// IsProduction returns true for production environment
func (e Environment) IsProduction() bool {
    return e == EnvProduction
}

// ServerConfig holds HTTP server settings
type ServerConfig struct {
    Host            string        `mapstructure:"host"`
    Port            int           `mapstructure:"port"`
    ReadTimeout     time.Duration `mapstructure:"read_timeout"`
    WriteTimeout    time.Duration `mapstructure:"write_timeout"`
    IdleTimeout     time.Duration `mapstructure:"idle_timeout"`
    ShutdownTimeout time.Duration `mapstructure:"shutdown_timeout"`
    MaxRequestSize  int64         `mapstructure:"max_request_size"`
    TrustedProxies  []string      `mapstructure:"trusted_proxies"`
}

// Address returns the full server address
func (s ServerConfig) Address() string {
    return fmt.Sprintf("%s:%d", s.Host, s.Port)
}

// DatabaseConfig holds SQLite settings
type DatabaseConfig struct {
    // Path configurations
    SettingsPath   string `mapstructure:"settings_path"`
    ProjectsPath   string `mapstructure:"projects_path"`
    ProjectDataDir string `mapstructure:"project_data_dir"`
    ConvDataDir    string `mapstructure:"conv_data_dir"`
    
    // Connection settings
    MaxOpenConns    int           `mapstructure:"max_open_conns"`
    MaxIdleConns    int           `mapstructure:"max_idle_conns"`
    ConnMaxLifetime time.Duration `mapstructure:"conn_max_lifetime"`
    ConnMaxIdleTime time.Duration `mapstructure:"conn_max_idle_time"`
    
    // SQLite-specific
    BusyTimeout     time.Duration `mapstructure:"busy_timeout"`
    JournalMode     string        `mapstructure:"journal_mode"`
    SynchronousMode string        `mapstructure:"synchronous_mode"`
    CacheSize       int           `mapstructure:"cache_size"`
    
    // Migrations
    MigrationsPath string `mapstructure:"migrations_path"`
    AutoMigrate    bool   `mapstructure:"auto_migrate"`
}

// LoggingConfig holds logging settings
type LoggingConfig struct {
    Level     logging.Level  `mapstructure:"level"`
    Format    logging.Format `mapstructure:"format"`
    AddSource bool           `mapstructure:"add_source"`
    Output    string         `mapstructure:"output"` // stdout, stderr, file path
}

// ServicesConfig holds microservice URLs
type ServicesConfig struct {
    Gateway    ServiceEndpoint `mapstructure:"gateway"`
    SpecMgr    ServiceEndpoint `mapstructure:"specmgr"`
    AIBridge   ServiceEndpoint `mapstructure:"aibridge"`
    Chronicle  ServiceEndpoint `mapstructure:"chronicle"`
    Scout      ServiceEndpoint `mapstructure:"scout"`
    NexusFlow  ServiceEndpoint `mapstructure:"nexusflow"`
}

// ServiceEndpoint holds a single service's connection info
type ServiceEndpoint struct {
    Host    string        `mapstructure:"host"`
    Port    int           `mapstructure:"port"`
    Timeout time.Duration `mapstructure:"timeout"`
    Retries int           `mapstructure:"retries"`
}

// URL returns the service URL
func (s ServiceEndpoint) URL() string {
    return fmt.Sprintf("http://%s:%d", s.Host, s.Port)
}

// SecurityConfig holds security settings
type SecurityConfig struct {
    // CORS
    AllowedOrigins   []string `mapstructure:"allowed_origins"`
    AllowedMethods   []string `mapstructure:"allowed_methods"`
    AllowedHeaders   []string `mapstructure:"allowed_headers"`
    AllowCredentials bool     `mapstructure:"allow_credentials"`
    MaxAge           int      `mapstructure:"max_age"`
    
    // Rate Limiting
    RateLimitEnabled bool          `mapstructure:"rate_limit_enabled"`
    RateLimitWindow  time.Duration `mapstructure:"rate_limit_window"`
    RateLimitMax     int           `mapstructure:"rate_limit_max"`
    
    // SSRF Prevention
    AllowedHosts     []string `mapstructure:"allowed_hosts"`
    BlockedNetworks  []string `mapstructure:"blocked_networks"`
    
    // API Keys (loaded from env)
    APIKeyHeader     string `mapstructure:"api_key_header"`
}

// AIConfig holds AI/LLM settings
type AIConfig struct {
    // Server settings
    Provider    string `mapstructure:"provider"` // ollama, llamacpp, openai
    BaseURL     string `mapstructure:"base_url"`
    APIKey      string `mapstructure:"api_key"` // From environment
    
    // Model settings
    DefaultModel    string  `mapstructure:"default_model"`
    EmbeddingModel  string  `mapstructure:"embedding_model"`
    Temperature     float64 `mapstructure:"temperature"`
    MaxTokens       int     `mapstructure:"max_tokens"`
    
    // Context settings
    ContextWindow   int `mapstructure:"context_window"`
    OverlapTokens   int `mapstructure:"overlap_tokens"`
    
    // Timeouts
    InferenceTimeout time.Duration `mapstructure:"inference_timeout"`
    StreamTimeout    time.Duration `mapstructure:"stream_timeout"`
    
    // llama-swap settings
    LlamaSwapEnabled bool   `mapstructure:"llama_swap_enabled"`
    LlamaSwapURL     string `mapstructure:"llama_swap_url"`
}

// NexusFlowConfig holds Nexus-Flow specific settings
type NexusFlowConfig struct {
    // WebSocket
    WSHost           string        `mapstructure:"ws_host"`
    WSPort           int           `mapstructure:"ws_port"`
    WSPingInterval   time.Duration `mapstructure:"ws_ping_interval"`
    WSPongTimeout    time.Duration `mapstructure:"ws_pong_timeout"`
    WSMaxMessageSize int64         `mapstructure:"ws_max_message_size"`
    
    // Execution
    MaxConcurrentExecs int           `mapstructure:"max_concurrent_execs"`
    DefaultTimeout     time.Duration `mapstructure:"default_timeout"`
    RetryAttempts      int           `mapstructure:"retry_attempts"`
    RetryBackoff       time.Duration `mapstructure:"retry_backoff"`
    
    // Block settings
    MaxBlocksPerFlow   int `mapstructure:"max_blocks_per_flow"`
    MaxConditionDepth  int `mapstructure:"max_condition_depth"`
    MaxParallelBranches int `mapstructure:"max_parallel_branches"`
}
```

---

## loader.go

```go
package config

import (
    "fmt"
    "os"
    "strings"
    
    "github.com/spf13/viper"
    
    "github.com/specbuilder/pkg/errors"
)

// LoadOptions configures the loader
type LoadOptions struct {
    ConfigPath    string
    ConfigName    string
    ConfigType    string
    EnvPrefix     string
    AllowMissing  bool
}

// DefaultLoadOptions returns sensible defaults
func DefaultLoadOptions() LoadOptions {
    return LoadOptions{
        ConfigPath:   ".",
        ConfigName:   "config",
        ConfigType:   "yaml",
        EnvPrefix:    "SPEC",
        AllowMissing: false,
    }
}

// Load loads configuration from file and environment
func Load(opts LoadOptions) (*Config, error) {
    v := viper.New()
    
    // Set config file settings
    v.SetConfigName(opts.ConfigName)
    v.SetConfigType(opts.ConfigType)
    v.AddConfigPath(opts.ConfigPath)
    v.AddConfigPath("./config")
    v.AddConfigPath("/etc/specbuilder")
    
    // Environment variable settings
    v.SetEnvPrefix(opts.EnvPrefix)
    v.SetEnvKeyReplacer(strings.NewReplacer(".", "_", "-", "_"))
    v.AutomaticEnv()
    
    // Set defaults
    setDefaults(v)
    
    // Read config file
    if err := v.ReadInConfig(); err != nil {
        if _, ok := err.(viper.ConfigFileNotFoundError); ok {
            if !opts.AllowMissing {
                return nil, errors.NewWithDetails(
                    errors.ErrConfigNotFound,
                    "configuration file not found",
                    map[string]any{
                        "name": opts.ConfigName,
                        "path": opts.ConfigPath,
                    },
                )
            }
            // Continue with defaults + env vars
        } else {
            return nil, errors.NewWithDetails(
                errors.ErrConfigParse,
                "failed to parse configuration file",
                map[string]any{"error": err.Error()},
            )
        }
    }
    
    // Unmarshal into struct
    var cfg Config
    if err := v.Unmarshal(&cfg); err != nil {
        return nil, errors.NewWithDetails(
            errors.ErrConfigParse,
            "failed to unmarshal configuration",
            map[string]any{"error": err.Error()},
        )
    }
    
    // Validate configuration
    if err := Validate(&cfg); err != nil {
        return nil, err
    }
    
    // Load secrets from environment
    loadSecrets(&cfg)
    
    return &cfg, nil
}

// LoadFromFile loads from a specific file path
func LoadFromFile(path string) (*Config, error) {
    v := viper.New()
    v.SetConfigFile(path)
    
    if err := v.ReadInConfig(); err != nil {
        return nil, errors.NewWithDetails(
            errors.ErrConfigParse,
            "failed to read configuration file",
            map[string]any{"path": path, "error": err.Error()},
        )
    }
    
    var cfg Config
    if err := v.Unmarshal(&cfg); err != nil {
        return nil, errors.NewWithDetails(
            errors.ErrConfigParse,
            "failed to unmarshal configuration",
            map[string]any{"error": err.Error()},
        )
    }
    
    if err := Validate(&cfg); err != nil {
        return nil, err
    }
    
    loadSecrets(&cfg)
    
    return &cfg, nil
}

// loadSecrets loads sensitive values from environment
func loadSecrets(cfg *Config) {
    // AI API Key
    if key := os.Getenv("SPEC_AI_API_KEY"); key != "" {
        cfg.AI.APIKey = key
    }
    
    // Additional secrets can be added here
}

// MustLoad loads config or panics
func MustLoad(opts LoadOptions) *Config {
    cfg, err := Load(opts)
    if err != nil {
        panic(fmt.Sprintf("failed to load config: %v", err))
    }
    return cfg
}
```

---

## defaults.go

```go
package config

import (
    "time"
    
    "github.com/spf13/viper"
    
    "github.com/specbuilder/pkg/logging"
)

func setDefaults(v *viper.Viper) {
    // Environment
    v.SetDefault("environment", "development")
    
    // Server defaults
    v.SetDefault("server.host", "0.0.0.0")
    v.SetDefault("server.port", 8080)
    v.SetDefault("server.read_timeout", 30*time.Second)
    v.SetDefault("server.write_timeout", 30*time.Second)
    v.SetDefault("server.idle_timeout", 120*time.Second)
    v.SetDefault("server.shutdown_timeout", 10*time.Second)
    v.SetDefault("server.max_request_size", 10*1024*1024) // 10MB
    
    // Database defaults
    v.SetDefault("database.settings_path", "./data/settings.db")
    v.SetDefault("database.projects_path", "./data/projects.db")
    v.SetDefault("database.project_data_dir", "./data/projects")
    v.SetDefault("database.conv_data_dir", "./data/conversations")
    v.SetDefault("database.max_open_conns", 10)
    v.SetDefault("database.max_idle_conns", 5)
    v.SetDefault("database.conn_max_lifetime", time.Hour)
    v.SetDefault("database.conn_max_idle_time", 30*time.Minute)
    v.SetDefault("database.busy_timeout", 5*time.Second)
    v.SetDefault("database.journal_mode", "WAL")
    v.SetDefault("database.synchronous_mode", "NORMAL")
    v.SetDefault("database.cache_size", -64000) // 64MB
    v.SetDefault("database.migrations_path", "./migrations")
    v.SetDefault("database.auto_migrate", true)
    
    // Logging defaults
    v.SetDefault("logging.level", logging.LevelInfo)
    v.SetDefault("logging.format", logging.FormatJSON)
    v.SetDefault("logging.add_source", false)
    v.SetDefault("logging.output", "stdout")
    
    // Service defaults
    setServiceDefaults(v, "services.gateway", 8080)
    setServiceDefaults(v, "services.specmgr", 8081)
    setServiceDefaults(v, "services.aibridge", 8082)
    setServiceDefaults(v, "services.chronicle", 8083)
    setServiceDefaults(v, "services.scout", 8084)
    setServiceDefaults(v, "services.nexusflow", 9000)
    
    // Security defaults
    v.SetDefault("security.allowed_origins", []string{"*"})
    v.SetDefault("security.allowed_methods", []string{"GET", "POST", "PUT", "DELETE", "OPTIONS"})
    v.SetDefault("security.allowed_headers", []string{"Content-Type", "Authorization", "X-Request-ID"})
    v.SetDefault("security.allow_credentials", true)
    v.SetDefault("security.max_age", 86400)
    v.SetDefault("security.rate_limit_enabled", true)
    v.SetDefault("security.rate_limit_window", time.Minute)
    v.SetDefault("security.rate_limit_max", 100)
    v.SetDefault("security.api_key_header", "X-API-Key")
    v.SetDefault("security.blocked_networks", []string{
        "10.0.0.0/8",
        "172.16.0.0/12",
        "192.168.0.0/16",
        "127.0.0.0/8",
        "169.254.0.0/16",
    })
    
    // AI defaults
    v.SetDefault("ai.provider", "ollama")
    v.SetDefault("ai.base_url", "http://localhost:11434")
    v.SetDefault("ai.default_model", "llama3.2")
    v.SetDefault("ai.embedding_model", "nomic-embed-text")
    v.SetDefault("ai.temperature", 0.7)
    v.SetDefault("ai.max_tokens", 4096)
    v.SetDefault("ai.context_window", 8192)
    v.SetDefault("ai.overlap_tokens", 200)
    v.SetDefault("ai.inference_timeout", 60*time.Second)
    v.SetDefault("ai.stream_timeout", 5*time.Minute)
    v.SetDefault("ai.llama_swap_enabled", false)
    v.SetDefault("ai.llama_swap_url", "http://localhost:8000")
}

func setServiceDefaults(v *viper.Viper, prefix string, port int) {
    v.SetDefault(prefix+".host", "localhost")
    v.SetDefault(prefix+".port", port)
    v.SetDefault(prefix+".timeout", 30*time.Second)
    v.SetDefault(prefix+".retries", 3)
}
```

---

## validation.go

```go
package config

import (
    "net"
    "os"
    "path/filepath"
    
    "github.com/specbuilder/pkg/errors"
)

// Validate checks all configuration values
func Validate(cfg *Config) error {
    validators := []func(*Config) error{
        validateEnvironment,
        validateServer,
        validateDatabase,
        validateLogging,
        validateServices,
        validateSecurity,
        validateAI,
    }
    
    for _, validate := range validators {
        if err := validate(cfg); err != nil {
            return err
        }
    }
    
    return nil
}

func validateEnvironment(cfg *Config) error {
    switch cfg.Environment {
    case EnvDevelopment, EnvStaging, EnvProduction:
        return nil
    default:
        return errors.NewWithDetails(
            errors.ErrConfigValidation,
            "invalid environment",
            map[string]any{
                "value":   cfg.Environment,
                "allowed": []string{"development", "staging", "production"},
            },
        )
    }
}

func validateServer(cfg *Config) error {
    if cfg.Server.Port < 1 || cfg.Server.Port > 65535 {
        return errors.NewWithDetails(
            errors.ErrConfigValidation,
            "invalid server port",
            map[string]any{"port": cfg.Server.Port},
        )
    }
    
    if cfg.Server.ReadTimeout < time.Second {
        return errors.NewWithDetails(
            errors.ErrConfigValidation,
            "read_timeout must be at least 1 second",
            map[string]any{"value": cfg.Server.ReadTimeout},
        )
    }
    
    if cfg.Server.MaxRequestSize < 1024 {
        return errors.NewWithDetails(
            errors.ErrConfigValidation,
            "max_request_size must be at least 1KB",
            map[string]any{"value": cfg.Server.MaxRequestSize},
        )
    }
    
    return nil
}

func validateDatabase(cfg *Config) error {
    // Validate paths are writable
    paths := []string{
        filepath.Dir(cfg.Database.SettingsPath),
        filepath.Dir(cfg.Database.ProjectsPath),
        cfg.Database.ProjectDataDir,
        cfg.Database.ConvDataDir,
    }
    
    for _, path := range paths {
        if err := ensureDir(path); err != nil {
            return errors.NewWithDetails(
                errors.ErrConfigValidation,
                "database path not writable",
                map[string]any{"path": path, "error": err.Error()},
            )
        }
    }
    
    // Validate journal mode
    validModes := map[string]bool{
        "DELETE": true, "TRUNCATE": true, "PERSIST": true,
        "MEMORY": true, "WAL": true, "OFF": true,
    }
    if !validModes[cfg.Database.JournalMode] {
        return errors.NewWithDetails(
            errors.ErrConfigValidation,
            "invalid journal_mode",
            map[string]any{"value": cfg.Database.JournalMode},
        )
    }
    
    return nil
}

func validateLogging(cfg *Config) error {
    switch cfg.Logging.Format {
    case "json", "text":
        // Valid
    default:
        return errors.NewWithDetails(
            errors.ErrConfigValidation,
            "invalid logging format",
            map[string]any{"value": cfg.Logging.Format},
        )
    }
    
    return nil
}

func validateServices(cfg *Config) error {
    services := map[string]ServiceEndpoint{
        "gateway":   cfg.Services.Gateway,
        "specmgr":   cfg.Services.SpecMgr,
        "aibridge":  cfg.Services.AIBridge,
        "chronicle": cfg.Services.Chronicle,
        "scout":     cfg.Services.Scout,
        "nexusflow": cfg.Services.NexusFlow,
    }
    
    for name, svc := range services {
        if svc.Port < 1 || svc.Port > 65535 {
            return errors.NewWithDetails(
                errors.ErrConfigValidation,
                "invalid service port",
                map[string]any{"service": name, "port": svc.Port},
            )
        }
        
        if svc.Timeout < time.Second {
            return errors.NewWithDetails(
                errors.ErrConfigValidation,
                "service timeout must be at least 1 second",
                map[string]any{"service": name, "timeout": svc.Timeout},
            )
        }
    }
    
    return nil
}

func validateSecurity(cfg *Config) error {
    // Validate blocked networks are valid CIDR
    for _, cidr := range cfg.Security.BlockedNetworks {
        if _, _, err := net.ParseCIDR(cidr); err != nil {
            return errors.NewWithDetails(
                errors.ErrConfigValidation,
                "invalid CIDR in blocked_networks",
                map[string]any{"cidr": cidr, "error": err.Error()},
            )
        }
    }
    
    return nil
}

func validateAI(cfg *Config) error {
    validProviders := map[string]bool{
        "ollama": true, "llamacpp": true, "openai": true,
    }
    if !validProviders[cfg.AI.Provider] {
        return errors.NewWithDetails(
            errors.ErrConfigValidation,
            "invalid AI provider",
            map[string]any{
                "value":   cfg.AI.Provider,
                "allowed": []string{"ollama", "llamacpp", "openai"},
            },
        )
    }
    
    if cfg.AI.Temperature < 0 || cfg.AI.Temperature > 2 {
        return errors.NewWithDetails(
            errors.ErrConfigValidation,
            "temperature must be between 0 and 2",
            map[string]any{"value": cfg.AI.Temperature},
        )
    }
    
    return nil
}

func ensureDir(path string) error {
    return os.MkdirAll(path, 0755)
}
```

---

## Sample Configuration File

```yaml
# config.yaml
environment: development

server:
  host: "0.0.0.0"
  port: 8080
  read_timeout: 30s
  write_timeout: 30s
  max_request_size: 10485760  # 10MB

database:
  settings_path: "./data/settings.db"
  projects_path: "./data/projects.db"
  project_data_dir: "./data/projects"
  conv_data_dir: "./data/conversations"
  journal_mode: "WAL"
  auto_migrate: true

logging:
  level: debug
  format: text
  add_source: true

services:
  gateway:
    host: localhost
    port: 8080
  specmgr:
    host: localhost
    port: 8081
  nexusflow:
    host: localhost
    port: 9000

ai:
  provider: ollama
  base_url: "http://localhost:11434"
  default_model: "llama3.2"
  temperature: 0.7
  max_tokens: 4096
```

---

## Usage Examples

```go
// Load with defaults
cfg, err := config.Load(config.DefaultLoadOptions())

// Load from specific file
cfg, err := config.LoadFromFile("/etc/specbuilder/config.yaml")

// Must load (panics on error)
cfg := config.MustLoad(config.LoadOptions{
    ConfigPath: "./config",
    EnvPrefix:  "SPEC",
})

// Access values
fmt.Println("Server:", cfg.Server.Address())
fmt.Println("Environment:", cfg.Environment)
fmt.Println("AI Provider:", cfg.AI.Provider)
```
