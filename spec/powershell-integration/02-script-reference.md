# PowerShell Script Reference

> **Version:** 1.0.0  
> **Created:** 2026-01-31  
> **Status:** Active

---

## Command-Line Flags

| Flag | Type | Description |
|------|------|-------------|
| `-Help` | Switch | Show help message and exit |
| `-BuildOnly` | Switch | Build frontend only, don't start backend |
| `-SkipBuild` | Switch | Skip frontend build, only run backend |
| `-SkipPull` | Switch | Skip git pull step |
| `-Force` | Switch | Clean build: remove caches, node_modules, databases |
| `-OpenFirewall` | Switch | Add Windows Firewall rules (requires Admin) |
| `-DevMode` | Switch | Reserved for future use |

---

## Usage Examples

```powershell
# Show help
.\run.ps1 -Help

# Full build and run (default)
.\run.ps1

# Clean rebuild everything
.\run.ps1 -Force

# Quick start (skip build)
.\run.ps1 -SkipBuild

# Build only for CI/CD
.\run.ps1 -BuildOnly

# Skip git, clean build
.\run.ps1 -SkipPull -Force

# First-time setup with firewall
.\run.ps1 -OpenFirewall
```

---

## Functions Reference

### Format-ElapsedTime

Formats a Stopwatch elapsed time for display.

```powershell
function Format-ElapsedTime($Stopwatch) {
    $elapsed = $Stopwatch.Elapsed
    if ($elapsed.TotalMinutes -ge 1) {
        return "{0:N0}m {1:N1}s" -f [Math]::Floor($elapsed.TotalMinutes), $elapsed.Seconds
    } else {
        return "{0:N1}s" -f $elapsed.TotalSeconds
    }
}
```

**Output Examples:**
- `2.3s` - Short duration
- `1m 45.2s` - Longer duration

---

### Test-Command

Checks if a command exists in PATH.

```powershell
function Test-Command($Command) {
    try { 
        if (Get-Command $Command) { return $true } 
    }
    catch { return $false }
}
```

**Usage:**
```powershell
if (-not (Test-Command "go")) {
    Install-Go
}
```

---

### Test-IsAdmin

Checks if running with Administrator privileges.

```powershell
function Test-IsAdmin {
    $currentUser = [Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = New-Object Security.Principal.WindowsPrincipal($currentUser)
    return $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
}
```

---

### Install-NodeJS

Installs Node.js LTS via winget.

```powershell
function Install-NodeJS {
    winget install OpenJS.NodeJS.LTS --accept-package-agreements --accept-source-agreements
    # Refresh PATH
    $env:Path = [System.Environment]::GetEnvironmentVariable("Path","Machine") + ";" + 
                [System.Environment]::GetEnvironmentVariable("Path","User")
}
```

---

### Install-Go

Installs Go via winget.

```powershell
function Install-Go {
    winget install GoLang.Go --accept-package-agreements --accept-source-agreements
    # Refresh PATH
    $env:Path = [System.Environment]::GetEnvironmentVariable("Path","Machine") + ";" + 
                [System.Environment]::GetEnvironmentVariable("Path","User")
}
```

---

### Ensure-FirewallRules

Creates Windows Firewall inbound rules.

```powershell
function Ensure-FirewallRules {
    param([int[]]$Ports = @(8080, 8081))
    
    foreach ($p in $Ports) {
        $ruleName = "LLM Runner (Go Backend) TCP $p"
        $existing = Get-NetFirewallRule -DisplayName $ruleName -ErrorAction SilentlyContinue
        
        if ($null -eq $existing) {
            New-NetFirewallRule `
                -DisplayName $ruleName `
                -Direction Inbound `
                -Action Allow `
                -Protocol TCP `
                -LocalPort $p `
                -Profile Private,Domain
        }
    }
}
```

**Requirements:**
- Must run as Administrator
- Windows PowerShell 5.1+ or PowerShell 7+

---

## Pipeline Steps Detail

### Step 1: Git Pull

```powershell
if (-not $SkipPull) {
    Push-Location $RootDir
    if (Test-Path ".git") {
        git pull
    }
    Pop-Location
}
```

**Behavior:**
- Skipped if `-SkipPull` flag
- Warns but continues if git pull fails
- Skips if not a git repository

---

### Step 2: Prerequisites Check

```powershell
# Check Go
if (-not (Test-Command "go")) {
    Install-Go
}

# Check npm
if (-not (Test-Command "npm")) {
    Install-NodeJS
}
```

**Auto-Install:**
- Uses winget for Windows package management
- Refreshes PATH after install
- Warns if restart needed

---

### Step 3: Frontend Build

```powershell
Push-Location $FrontendDir

# Force clean
if ($Force) {
    Remove-Item -Recurse -Force "node_modules" -ErrorAction SilentlyContinue
    Remove-Item -Recurse -Force "dist" -ErrorAction SilentlyContinue
    Remove-Item -Recurse -Force ".vite" -ErrorAction SilentlyContinue
    # Also clean databases
}

# Install dependencies if needed
if (-not (Test-Path "node_modules")) {
    npm install
}

# Build
npm run build

Pop-Location
```

---

### Step 4: Copy Build

```powershell
$SourceDist = Join-Path $RootDir $DistDir
$TargetDist = Join-Path $RootDir $TargetDir

# Remove old
if (Test-Path $TargetDist) {
    Remove-Item -Recurse -Force $TargetDist
}

# Copy new
Copy-Item -Recurse $SourceDist $TargetDist
```

---

### Step 5: Start Backend

```powershell
Push-Location $BackendDir

# Create config if missing
if (-not (Test-Path "config.json")) {
    Copy-Item "config.example.json" "config.json"
}

# Create data directories
New-Item -ItemType Directory -Path "data" -ErrorAction SilentlyContinue
New-Item -ItemType Directory -Path "data/conversations" -ErrorAction SilentlyContinue
New-Item -ItemType Directory -Path "data/seeding" -ErrorAction SilentlyContinue

# Run
go run main.go

Pop-Location
```

---

## Timing Output

The script tracks time for each step:

```
[1/5] Pulling latest changes from git...
  ✓ Git pull complete
  ⏱ 1.2s

[2/5] Checking prerequisites...
  ✓ Go found: go version go1.21.0 windows/amd64
  ✓ npm found: 10.2.0
  ⏱ 0.3s

[3/5] Building React frontend...
  Running npm build...
  ✓ Frontend built successfully
  ⏱ 12.5s

[4/5] Copying build to Go backend...
  ✓ Build files copied to go-backend/frontend/dist
  ⏱ 0.1s

[5/5] Starting Go backend...
========================================
  LLM Runner starting...
  Open: http://localhost:8080
  Press Ctrl+C to stop
  Build time: 14.1s
========================================
```

---

## Exit Codes

| Code | Meaning |
|------|---------|
| 0 | Success |
| 1 | Prerequisites installation failed |
| 2 | npm install failed |
| 3 | npm build failed |
| 4 | Go run failed |

---

## Cross-References

- [Configuration Schema](./01-configuration-schema.md) - JSON config format
- [Error Codes](./04-error-codes.md) - Detailed error handling
