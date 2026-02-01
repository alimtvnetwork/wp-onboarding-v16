# Build Profiles

**Version:** 1.0.0  
**Status:** Draft  
**Updated:** 2026-01-29  

---

## Overview

Saved build configurations that can be referenced by name. Stored in config.json and managed via CLI.

**Cross-References:**
- [Configuration](./03-configuration.md)
- [Asset Operations](./08-asset-operations.md)
- [CLI Interface](./02-cli-interface.md)

---

## Build Profile Schema

```go
type BuildProfile struct {
    Name         string            `json:"name"`
    Description  string            `json:"description,omitempty"`
    Runtime      RuntimeType       `json:"runtime"` // powershell, nodejs, golang
    Source       string            `json:"source"`  // Source path or script
    Output       string            `json:"output,omitempty"`
    Command      string            `json:"command,omitempty"` // For Node.js: build, dev, etc.
    Args         []string          `json:"args,omitempty"`
    Env          map[string]string `json:"env,omitempty"`
    WorkDir      string            `json:"workdir,omitempty"`
    Timeout      string            `json:"timeout,omitempty"`
    PreCommands  []string          `json:"preCommands,omitempty"`
    PostCommands []string          `json:"postCommands,omitempty"`
    Assets       *AssetConfig      `json:"assets,omitempty"`
    Port         int               `json:"port,omitempty"`
}
```

---

## Profile Examples

### Go Backend API

```json
{
  "name": "backend-api",
  "description": "Build the Go backend API server",
  "runtime": "golang",
  "source": "./cmd/api",
  "output": "./bin/api",
  "preCommands": ["go mod tidy"],
  "env": {
    "CGO_ENABLED": "1",
    "GOOS": "linux",
    "GOARCH": "amd64"
  },
  "args": ["-ldflags", "-s -w"],
  "port": 8080
}
```

### React Frontend

```json
{
  "name": "frontend",
  "description": "Build React frontend with Vite",
  "runtime": "nodejs",
  "source": "./frontend",
  "command": "build",
  "env": {
    "NODE_ENV": "production"
  },
  "assets": {
    "enabled": true,
    "operations": [
      {
        "source": "./frontend/dist",
        "destination": "./public",
        "mode": "clear-copy"
      }
    ]
  }
}
```

### PowerShell Deployment

```json
{
  "name": "deploy-prod",
  "description": "Run production deployment script",
  "runtime": "powershell",
  "source": "./scripts/deploy.ps1",
  "args": ["-Environment", "production", "-Force"],
  "timeout": "30m"
}
```

### Development Server

```json
{
  "name": "dev-server",
  "description": "Run development server with hot reload",
  "runtime": "golang",
  "source": "./cmd/server",
  "preCommands": ["go mod tidy"],
  "env": {
    "APP_ENV": "development",
    "DEBUG": "true"
  },
  "port": 3000
}
```

---

## Profile Manager

```go
type ProfileManager struct {
    config   *Config
    profiles map[string]*BuildProfile
}

func (pm *ProfileManager) Get(name string) (*BuildProfile, error) {
    profile, exists := pm.profiles[name]
    if !exists {
        return nil, fmt.Errorf("profile not found: %s", name)
    }
    return profile, nil
}

func (pm *ProfileManager) Add(profile *BuildProfile) error {
    if _, exists := pm.profiles[profile.Name]; exists {
        return fmt.Errorf("profile already exists: %s", profile.Name)
    }
    
    if err := pm.validate(profile); err != nil {
        return err
    }
    
    pm.profiles[profile.Name] = profile
    return pm.save()
}

func (pm *ProfileManager) Remove(name string) error {
    if _, exists := pm.profiles[name]; !exists {
        return fmt.Errorf("profile not found: %s", name)
    }
    
    delete(pm.profiles, name)
    return pm.save()
}

func (pm *ProfileManager) List() []*BuildProfile {
    profiles := make([]*BuildProfile, 0, len(pm.profiles))
    for _, p := range pm.profiles {
        profiles = append(profiles, p)
    }
    return profiles
}

func (pm *ProfileManager) validate(profile *BuildProfile) error {
    if profile.Name == "" {
        return errors.New("profile name is required")
    }
    
    if profile.Runtime == "" {
        return errors.New("runtime is required")
    }
    
    if profile.Source == "" && profile.Command == "" {
        return errors.New("source or command is required")
    }
    
    // Validate runtime
    validRuntimes := []RuntimeType{RuntimePowerShell, RuntimeNodeJS, RuntimeGolang}
    valid := false
    for _, r := range validRuntimes {
        if profile.Runtime == r {
            valid = true
            break
        }
    }
    if !valid {
        return fmt.Errorf("invalid runtime: %s", profile.Runtime)
    }
    
    return nil
}
```

---

## Profile Execution

```go
func (e *ExecutionEngine) ExecuteProfile(ctx context.Context, profileName string) (*ExecutionResult, error) {
    // Get profile
    profile, err := e.profileManager.Get(profileName)
    if err != nil {
        return nil, err
    }
    
    // Build command from profile
    cmd := &Command{
        Type:        profile.Runtime,
        Script:      profile.Source,
        Args:        profile.Args,
        WorkDir:     profile.WorkDir,
        Env:         profile.Env,
        PreCommands: profile.PreCommands,
    }
    
    // Parse timeout
    if profile.Timeout != "" {
        cmd.Timeout, _ = time.ParseDuration(profile.Timeout)
    } else {
        cmd.Timeout = e.config.Execution.Timeout
    }
    
    // Execute pre-commands
    for _, preCmd := range cmd.PreCommands {
        result, err := e.executeShellCommand(ctx, preCmd, cmd.WorkDir)
        if err != nil {
            return result, fmt.Errorf("pre-command failed: %w", err)
        }
    }
    
    // Get executor for runtime
    executor, err := e.factory.Create(profile.Runtime)
    if err != nil {
        return nil, err
    }
    
    // Handle port if specified
    if profile.Port > 0 {
        return e.executeWithPort(ctx, cmd, profile.Port)
    }
    
    // Execute main command
    result, err := executor.Execute(ctx, cmd)
    if err != nil {
        return result, err
    }
    
    // Execute post-commands if build succeeded
    if result.Success && len(profile.PostCommands) > 0 {
        for _, postCmd := range profile.PostCommands {
            _, err := e.executeShellCommand(ctx, postCmd, cmd.WorkDir)
            if err != nil {
                e.logger.Warn("Post-command failed", "command", postCmd, "error", err)
            }
        }
    }
    
    // Handle assets
    if profile.Assets != nil && profile.Assets.Enabled && result.Success {
        if err := e.assetCopier.Execute(profile.Assets); err != nil {
            result.Warnings = append(result.Warnings, BuildError{
                Message:  fmt.Sprintf("Asset copy failed: %s", err),
                Severity: "warning",
            })
        }
    }
    
    return result, nil
}
```

---

## CLI Commands

### Add Profile

```bash
# Add simple Go profile
brun config add-profile \
  --name backend-api \
  --runtime golang \
  --source ./cmd/api \
  --output ./bin/api

# Add with full options
brun config add-profile \
  --name frontend \
  --runtime nodejs \
  --source ./frontend \
  --command build \
  --env "NODE_ENV=production"
```

### List Profiles

```bash
brun config show --profiles

# Output:
# Profiles:
#   backend-api    golang      Build the Go backend API
#   frontend       nodejs      Build React frontend
#   deploy-prod    powershell  Run production deployment
```

### Remove Profile

```bash
brun config remove-profile --name old-profile
```

### Execute Profile

```bash
# Run by name
brun build --profile backend-api

# Run with overrides
brun build --profile backend-api --timeout 10m --clean
```

---

## Profile Inheritance (Future)

```json
{
  "name": "backend-prod",
  "extends": "backend-api",
  "env": {
    "GOOS": "linux",
    "GOARCH": "amd64"
  },
  "args": ["-ldflags", "-s -w -X main.version=1.0.0"]
}
```

---

## See Also

- [Configuration](./03-configuration.md)
- [Asset Operations](./08-asset-operations.md)
- [CLI Interface](./02-cli-interface.md)
