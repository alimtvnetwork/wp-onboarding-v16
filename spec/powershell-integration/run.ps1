# LLM Runner - PowerShell Build & Run Script
# This script builds the React frontend and runs the Go backend

param(
    [switch]$BuildOnly,
    [switch]$DevMode,
    [switch]$SkipBuild,
    [switch]$SkipPull,
    [switch]$Force,
    [switch]$OpenFirewall,
    [switch]$Help
)

$ErrorActionPreference = "Stop"
$RootDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$BackendDir = Join-Path $RootDir "go-backend"
$TotalStopwatch = [System.Diagnostics.Stopwatch]::StartNew()

# Show help and exit
if ($Help) {
    Write-Host ""
    Write-Host "LLM Runner - Build & Run Script" -ForegroundColor Cyan
    Write-Host "================================" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "USAGE:" -ForegroundColor Yellow
    Write-Host "  .\run.ps1 [flags]"
    Write-Host ""
    Write-Host "FLAGS:" -ForegroundColor Yellow
    Write-Host "  -Help        Show this help message and exit"
    Write-Host "  -BuildOnly   Build frontend only, don't start the backend server"
    Write-Host "  -SkipBuild   Skip frontend build, only run the backend server"
    Write-Host "  -SkipPull    Skip git pull step"
    Write-Host "  -Force       Clean build: remove node_modules, dist, .vite cache, and SQLite databases"
    Write-Host "  -OpenFirewall  (Admin) Add Windows Firewall inbound rules for TCP 8080/8081"
    Write-Host "  -DevMode     (Reserved for future use)"
    Write-Host ""
    Write-Host "EXAMPLES:" -ForegroundColor Yellow
    Write-Host "  .\run.ps1                  # Full build and run"
    Write-Host "  .\run.ps1 -Force           # Clean rebuild everything"
    Write-Host "  .\run.ps1 -SkipBuild       # Just start the backend"
    Write-Host "  .\run.ps1 -BuildOnly       # Build only, don't start server"
    Write-Host "  .\run.ps1 -SkipPull -Force # Clean build without git pull"
    Write-Host ""
    Write-Host "STEPS:" -ForegroundColor Yellow
    Write-Host "  1. Git pull (unless -SkipPull)"
    Write-Host "  2. Check prerequisites (Go, npm)"
    Write-Host "  3. Build React frontend (unless -SkipBuild)"
    Write-Host "  4. Copy build to go-backend/frontend"
    Write-Host "  5. Start Go backend (unless -BuildOnly)"
    Write-Host ""
    exit 0
}

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  LLM Runner - Build & Run Script" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Function to format elapsed time
function Format-ElapsedTime($Stopwatch) {
    $elapsed = $Stopwatch.Elapsed
    if ($elapsed.TotalMinutes -ge 1) {
        return "{0:N0}m {1:N1}s" -f [Math]::Floor($elapsed.TotalMinutes), $elapsed.Seconds
    } else {
        return "{0:N1}s" -f $elapsed.TotalSeconds
    }
}

# Function to check if command exists
function Test-Command($Command) {
    $oldPreference = $ErrorActionPreference
    $ErrorActionPreference = 'stop'
    try { if (Get-Command $Command) { return $true } }
    catch { return $false }
    finally { $ErrorActionPreference = $oldPreference }
}

# Check if the current PowerShell session is running as Administrator
function Test-IsAdmin {
    try {
        $currentUser = [Security.Principal.WindowsIdentity]::GetCurrent()
        $principal = New-Object Security.Principal.WindowsPrincipal($currentUser)
        return $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
    } catch {
        return $false
    }
}

# Ensure Windows Firewall inbound rules exist for LLM Runner ports
function Ensure-FirewallRules {
    param(
        [int[]]$Ports = @(8080, 8081)
    )

    if (-not (Test-IsAdmin)) {
        Write-Host "  WARNING: -OpenFirewall requires Administrator. Re-run PowerShell as Admin to apply firewall rules." -ForegroundColor Yellow
        Write-Host "  TIP: Right-click PowerShell → Run as Administrator" -ForegroundColor Gray
        return
    }

    if (-not (Test-Command "New-NetFirewallRule")) {
        Write-Host "  WARNING: New-NetFirewallRule not available. Skipping automatic firewall setup." -ForegroundColor Yellow
        return
    }

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
                -Profile Private,Domain `
                | Out-Null
            Write-Host "  ✓ Firewall rule added: $ruleName" -ForegroundColor Green
        } else {
            Write-Host "  ✓ Firewall rule exists: $ruleName" -ForegroundColor Green
        }
    }

    Write-Host "  Verify: Get-NetFirewallRule -DisplayName 'LLM Runner*'" -ForegroundColor Gray
}

# Function to install Node.js using winget
function Install-NodeJS {
    Write-Host "  Attempting to install Node.js via winget..." -ForegroundColor Yellow
    
    if (-not (Test-Command "winget")) {
        Write-Host "ERROR: winget is not available. Please install Node.js manually:" -ForegroundColor Red
        Write-Host "  Download from: https://nodejs.org/" -ForegroundColor Yellow
        exit 1
    }
    
    try {
        winget install OpenJS.NodeJS.LTS --accept-package-agreements --accept-source-agreements
        if ($LASTEXITCODE -ne 0) { throw "winget install failed" }
        
        # Refresh PATH
        $env:Path = [System.Environment]::GetEnvironmentVariable("Path","Machine") + ";" + [System.Environment]::GetEnvironmentVariable("Path","User")
        
        Write-Host "  ✓ Node.js installed successfully" -ForegroundColor Green
        Write-Host "  NOTE: You may need to restart PowerShell for PATH changes" -ForegroundColor Yellow
    }
    catch {
        Write-Host "ERROR: Failed to install Node.js. Please install manually:" -ForegroundColor Red
        Write-Host "  Download from: https://nodejs.org/" -ForegroundColor Yellow
        exit 1
    }
}

# Function to install Go using winget
function Install-Go {
    Write-Host "  Attempting to install Go via winget..." -ForegroundColor Yellow
    
    if (-not (Test-Command "winget")) {
        Write-Host "ERROR: winget is not available. Please install Go manually:" -ForegroundColor Red
        Write-Host "  Download from: https://go.dev/dl/" -ForegroundColor Yellow
        exit 1
    }
    
    try {
        winget install GoLang.Go --accept-package-agreements --accept-source-agreements
        if ($LASTEXITCODE -ne 0) { throw "winget install failed" }
        
        # Refresh PATH
        $env:Path = [System.Environment]::GetEnvironmentVariable("Path","Machine") + ";" + [System.Environment]::GetEnvironmentVariable("Path","User")
        
        Write-Host "  ✓ Go installed successfully" -ForegroundColor Green
        Write-Host "  NOTE: You may need to restart PowerShell for PATH changes" -ForegroundColor Yellow
    }
    catch {
        Write-Host "ERROR: Failed to install Go. Please install manually:" -ForegroundColor Red
        Write-Host "  Download from: https://go.dev/dl/" -ForegroundColor Yellow
        exit 1
    }
}

# Step timers
$StepTimes = @{}

# Git pull to sync latest changes
$stepWatch = [System.Diagnostics.Stopwatch]::StartNew()
if (-not $SkipPull) {
    Write-Host "[1/5] Pulling latest changes from git..." -ForegroundColor Yellow
    
    Push-Location $RootDir
    try {
        if (Test-Path ".git") {
            git pull
            if ($LASTEXITCODE -ne 0) {
                Write-Host "  WARNING: git pull failed, continuing anyway..." -ForegroundColor Yellow
            } else {
                Write-Host "  ✓ Git pull complete" -ForegroundColor Green
            }
        } else {
            Write-Host "  Skipping git pull (not a git repository)" -ForegroundColor Gray
        }
    }
    finally {
        Pop-Location
    }
} else {
    Write-Host "[1/5] Skipping git pull (--SkipPull)" -ForegroundColor Gray
}
$stepWatch.Stop()
$StepTimes["Git Pull"] = $stepWatch.Elapsed
Write-Host "  ⏱ $(Format-ElapsedTime $stepWatch)" -ForegroundColor DarkGray
Write-Host ""

# Check prerequisites
$stepWatch = [System.Diagnostics.Stopwatch]::StartNew()
Write-Host "[2/5] Checking prerequisites..." -ForegroundColor Yellow

if (-not (Test-Command "go")) {
    Write-Host "  Go is not installed or not in PATH" -ForegroundColor Yellow
    Install-Go
}
Write-Host "  ✓ Go found: $(go version)" -ForegroundColor Green

if (-not (Test-Command "npm")) {
    Write-Host "  npm is not installed or not in PATH" -ForegroundColor Yellow
    Install-NodeJS
}
Write-Host "  ✓ npm found: $(npm --version)" -ForegroundColor Green
$stepWatch.Stop()
$StepTimes["Prerequisites"] = $stepWatch.Elapsed
Write-Host "  ⏱ $(Format-ElapsedTime $stepWatch)" -ForegroundColor DarkGray

# Build React frontend
$stepWatch = [System.Diagnostics.Stopwatch]::StartNew()
if (-not $SkipBuild) {
    Write-Host ""
    Write-Host "[3/5] Building React frontend..." -ForegroundColor Yellow
    
    Push-Location $RootDir
    try {
        # Force clean build - remove node_modules, dist, and optionally databases
        if ($Force) {
            Write-Host "  FORCE MODE: Cleaning build artifacts..." -ForegroundColor Magenta
            if (Test-Path "node_modules") {
                Write-Host "  Removing node_modules..." -ForegroundColor Gray
                Remove-Item -Recurse -Force "node_modules"
            }
            if (Test-Path "dist") {
                Write-Host "  Removing dist..." -ForegroundColor Gray
                Remove-Item -Recurse -Force "dist"
            }
            if (Test-Path ".vite") {
                Write-Host "  Removing .vite cache..." -ForegroundColor Gray
                Remove-Item -Recurse -Force ".vite"
            }
            
            # Remove SQLite databases in go-backend/data
            $DataDbPath = Join-Path $BackendDir "data"
            if (Test-Path $DataDbPath) {
                $DbFiles = Get-ChildItem -Path $DataDbPath -Filter "*.db" -Recurse -ErrorAction SilentlyContinue
                foreach ($db in $DbFiles) {
                    Write-Host "  Removing database: $($db.Name)..." -ForegroundColor Gray
                    Remove-Item -Force $db.FullName
                }
                # Also remove any .db-shm and .db-wal files (WAL mode artifacts)
                $WalFiles = Get-ChildItem -Path $DataDbPath -Include "*.db-shm","*.db-wal" -Recurse -ErrorAction SilentlyContinue
                foreach ($wal in $WalFiles) {
                    Remove-Item -Force $wal.FullName
                }
            }
            
            Write-Host "  ✓ Clean complete (including databases)" -ForegroundColor Magenta
        }
        
        # Install/update dependencies when missing OR when new deps were added since last install
        $NeedsInstall = -not (Test-Path "node_modules")
        if (-not $NeedsInstall) {
            # If a required module folder is missing, force an install (common after pulling new commits)
            $RequiredModules = @(
                "react-syntax-highlighter"
            )
            foreach ($m in $RequiredModules) {
                if (-not (Test-Path (Join-Path "node_modules" $m))) {
                    $NeedsInstall = $true
                    break
                }
            }
        }

        if ($NeedsInstall) {
            Write-Host "  Installing npm dependencies..." -ForegroundColor Gray
            npm install
            if ($LASTEXITCODE -ne 0) { throw "npm install failed" }
        }
        
        # Build the frontend
        Write-Host "  Running npm build..." -ForegroundColor Gray
        npm run build
        if ($LASTEXITCODE -ne 0) { throw "npm build failed" }
        
        Write-Host "  ✓ Frontend built successfully" -ForegroundColor Green
    }
    finally {
        Pop-Location
    }
    $stepWatch.Stop()
    $StepTimes["Frontend Build"] = $stepWatch.Elapsed
    Write-Host "  ⏱ $(Format-ElapsedTime $stepWatch)" -ForegroundColor DarkGray
    
    # Copy dist to go-backend/frontend
    $stepWatch = [System.Diagnostics.Stopwatch]::StartNew()
    Write-Host ""
    Write-Host "[4/5] Copying build to Go backend..." -ForegroundColor Yellow
    
    $SourceDist = Join-Path $RootDir "dist"
    $TargetDir = Join-Path $BackendDir "frontend"
    $TargetDist = Join-Path $TargetDir "dist"
    
    # Create frontend directory if it doesn't exist
    if (-not (Test-Path $TargetDir)) {
        New-Item -ItemType Directory -Path $TargetDir | Out-Null
    }
    
    # Remove old dist if exists
    if (Test-Path $TargetDist) {
        Remove-Item -Recurse -Force $TargetDist
    }
    
    # Copy new dist
    Copy-Item -Recurse $SourceDist $TargetDist
    Write-Host "  ✓ Build files copied to go-backend/frontend/dist" -ForegroundColor Green
    $stepWatch.Stop()
    $StepTimes["Copy Build"] = $stepWatch.Elapsed
    Write-Host "  ⏱ $(Format-ElapsedTime $stepWatch)" -ForegroundColor DarkGray
} else {
    Write-Host ""
    Write-Host "[3/5] Skipping frontend build (--SkipBuild)" -ForegroundColor Gray
    Write-Host "[4/5] Skipping copy step" -ForegroundColor Gray
    $stepWatch.Stop()
    $StepTimes["Frontend Build"] = $stepWatch.Elapsed
    $StepTimes["Copy Build"] = [TimeSpan]::Zero
}

if ($BuildOnly) {
    $TotalStopwatch.Stop()
    Write-Host ""
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "  Build complete! (--BuildOnly mode)" -ForegroundColor Cyan
    Write-Host "  Total time: $(Format-ElapsedTime $TotalStopwatch)" -ForegroundColor Cyan
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "Build Summary:" -ForegroundColor Yellow
    foreach ($step in $StepTimes.GetEnumerator()) {
        $time = "{0:N1}s" -f $step.Value.TotalSeconds
        Write-Host "  $($step.Key): $time" -ForegroundColor Gray
    }
    exit 0
}

# Run Go backend
Write-Host ""
Write-Host "[5/5] Starting Go backend..." -ForegroundColor Yellow

Push-Location $BackendDir
try {
    # Check for config.json
    if (-not (Test-Path "config.json")) {
        Write-Host "  WARNING: config.json not found!" -ForegroundColor Yellow
        Write-Host "  Copying from config.example.json..." -ForegroundColor Gray
        Copy-Item "config.example.json" "config.json"
        Write-Host "  Please edit config.json with your paths" -ForegroundColor Yellow
    }
    
    # Create data directory structure
    $DataDir = Join-Path $BackendDir "data"
    if (-not (Test-Path $DataDir)) {
        New-Item -ItemType Directory -Path $DataDir | Out-Null
    }
    if (-not (Test-Path (Join-Path $DataDir "conversations"))) {
        New-Item -ItemType Directory -Path (Join-Path $DataDir "conversations") | Out-Null
    }
    
    # Create seeding directory if it doesn't exist (seed files now live in backend)
    $SeedingDir = Join-Path $DataDir "seeding"
    if (-not (Test-Path $SeedingDir)) {
        New-Item -ItemType Directory -Path $SeedingDir | Out-Null
        Write-Host "  Created seeding directory" -ForegroundColor Gray
    }

    if ($OpenFirewall) {
        Write-Host "  Configuring Windows Firewall rules (-OpenFirewall)..." -ForegroundColor Yellow
        Ensure-FirewallRules -Ports @(8080, 8081)
    }
    
    $TotalStopwatch.Stop()
    Write-Host ""
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "  LLM Runner starting..." -ForegroundColor Cyan
    Write-Host "  Open: http://localhost:8080" -ForegroundColor Cyan
    Write-Host "  Press Ctrl+C to stop" -ForegroundColor Cyan
    Write-Host "  Build time: $(Format-ElapsedTime $TotalStopwatch)" -ForegroundColor Cyan
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host ""
    
    go run main.go
}
finally {
    Pop-Location
}
