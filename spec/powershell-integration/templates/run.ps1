# PowerShell Build & Run Script (pnpm PnP Edition)
# This script builds the React frontend and runs the Go backend
# Uses pnpm with Plug'n'Play for disk-efficient package management

param(
    [switch]$BuildOnly,
    [switch]$SkipBuild,
    [switch]$SkipPull,
    [switch]$Force,
    [switch]$OpenFirewall,
    [switch]$Help
)

$ErrorActionPreference = "Stop"
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path

# Load configuration
$ConfigPath = Join-Path $ScriptDir "powershell.json"
if (-not (Test-Path $ConfigPath)) {
    Write-Host "ERROR: powershell.json not found at $ConfigPath" -ForegroundColor Red
    Write-Host "Create a powershell.json config file. See spec/powershell-integration/01-configuration-schema.md" -ForegroundColor Yellow
    exit 5
}

$config = Get-Content $ConfigPath | ConvertFrom-Json

# Resolve paths relative to script location
$RootDir = if ($config.rootDir -and $config.rootDir -ne ".") { 
    Join-Path $ScriptDir $config.rootDir 
} else { 
    $ScriptDir 
}
$BackendDir = Join-Path $RootDir $config.backendDir
$FrontendDir = if ($config.frontendDir -and $config.frontendDir -ne ".") {
    Join-Path $RootDir $config.frontendDir
} else {
    $RootDir
}
$DistDir = if ($config.distDir) { $config.distDir } else { "dist" }
$TargetDir = if ($config.targetDir) { Join-Path $RootDir $config.targetDir } else { Join-Path $BackendDir "frontend/dist" }
$DataDir = if ($config.dataDir) { Join-Path $RootDir $config.dataDir } else { Join-Path $BackendDir "data" }
$ProjectName = if ($config.projectName) { $config.projectName } else { "Project" }
$Ports = if ($config.ports) { $config.ports } else { @(8080) }
$BuildCommand = if ($config.buildCommand) { $config.buildCommand } else { "pnpm run build" }
$InstallCommand = if ($config.installCommand) { $config.installCommand } else { "pnpm install" }
$RunCommand = if ($config.runCommand) { $config.runCommand } else { "go run main.go" }
$ConfigFile = if ($config.configFile) { $config.configFile } else { "config.json" }
$ConfigExampleFile = if ($config.configExampleFile) { $config.configExampleFile } else { "config.example.json" }
$UsePnp = if ($null -ne $config.usePnp) { $config.usePnp } else { $true }
$PnpmStorePath = if ($config.pnpmStorePath) { $config.pnpmStorePath } else { ".pnpm-store" }
$RequiredModules = if ($config.requiredModules) { $config.requiredModules } else { @() }
$CleanPaths = if ($config.cleanPaths) { $config.cleanPaths } else { @("node_modules", "dist", ".vite", ".pnp.cjs", ".pnp.loader.mjs") }

$TotalStopwatch = [System.Diagnostics.Stopwatch]::StartNew()

# Show help and exit
if ($Help) {
    Write-Host ""
    Write-Host "$ProjectName - Build & Run Script (pnpm PnP)" -ForegroundColor Cyan
    Write-Host "================================================" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "USAGE:" -ForegroundColor Yellow
    Write-Host "  .\run.ps1 [flags]"
    Write-Host ""
    Write-Host "FLAGS:" -ForegroundColor Yellow
    Write-Host "  -Help          Show this help message and exit"
    Write-Host "  -BuildOnly     Build frontend only, don't start the backend server"
    Write-Host "  -SkipBuild     Skip frontend build, only run the backend server"
    Write-Host "  -SkipPull      Skip git pull step"
    Write-Host "  -Force         Clean build: remove caches, PnP files, prune pnpm store"
    Write-Host "  -OpenFirewall  (Admin) Add Windows Firewall inbound rules for ports"
    Write-Host ""
    Write-Host "EXAMPLES:" -ForegroundColor Yellow
    Write-Host "  .\run.ps1                  # Full build and run"
    Write-Host "  .\run.ps1 -Force           # Clean rebuild everything"
    Write-Host "  .\run.ps1 -SkipBuild       # Just start the backend"
    Write-Host "  .\run.ps1 -BuildOnly       # Build only, don't start server"
    Write-Host "  .\run.ps1 -SkipPull -Force # Clean build without git pull"
    Write-Host ""
    Write-Host "CONFIGURATION:" -ForegroundColor Yellow
    Write-Host "  Config file: $ConfigPath"
    Write-Host "  Project: $ProjectName"
    Write-Host "  Backend: $BackendDir"
    Write-Host "  Frontend: $FrontendDir"
    Write-Host "  pnpm Store: $PnpmStorePath"
    Write-Host "  Ports: $($Ports -join ', ')"
    Write-Host ""
    Write-Host "STEPS:" -ForegroundColor Yellow
    Write-Host "  1. Git pull (unless -SkipPull)"
    Write-Host "  2. Check prerequisites (Go, Node.js, pnpm)"
    Write-Host "  3. Install dependencies (pnpm PnP)"
    Write-Host "  4. Build React frontend (unless -SkipBuild)"
    Write-Host "  5. Copy build to backend, start server (unless -BuildOnly)"
    Write-Host ""
    exit 0
}

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  $ProjectName - Build & Run Script" -ForegroundColor Cyan
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

# Ensure Windows Firewall inbound rules exist
function Ensure-FirewallRules {
    param([int[]]$Ports = @(8080))

    if (-not (Test-IsAdmin)) {
        Write-Host "  WARNING: -OpenFirewall requires Administrator. Re-run PowerShell as Admin." -ForegroundColor Yellow
        return
    }

    if (-not (Test-Command "New-NetFirewallRule")) {
        Write-Host "  WARNING: New-NetFirewallRule not available. Skipping." -ForegroundColor Yellow
        return
    }

    foreach ($p in $Ports) {
        $ruleName = "$ProjectName (Go Backend) TCP $p"
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
}

# Function to install Node.js using winget
function Install-NodeJS {
    Write-Host "  Attempting to install Node.js via winget..." -ForegroundColor Yellow
    
    if (-not (Test-Command "winget")) {
        Write-Host "ERROR: winget not available. Install Node.js manually: https://nodejs.org/" -ForegroundColor Red
        exit 1
    }
    
    try {
        winget install OpenJS.NodeJS.LTS --accept-package-agreements --accept-source-agreements
        if ($LASTEXITCODE -ne 0) { throw "winget install failed" }
        $env:Path = [System.Environment]::GetEnvironmentVariable("Path","Machine") + ";" + [System.Environment]::GetEnvironmentVariable("Path","User")
        Write-Host "  ✓ Node.js installed successfully" -ForegroundColor Green
    }
    catch {
        Write-Host "ERROR: Failed to install Node.js. Install manually: https://nodejs.org/" -ForegroundColor Red
        exit 1
    }
}

# Function to install Go using winget
function Install-Go {
    Write-Host "  Attempting to install Go via winget..." -ForegroundColor Yellow
    
    if (-not (Test-Command "winget")) {
        Write-Host "ERROR: winget not available. Install Go manually: https://go.dev/dl/" -ForegroundColor Red
        exit 1
    }
    
    try {
        winget install GoLang.Go --accept-package-agreements --accept-source-agreements
        if ($LASTEXITCODE -ne 0) { throw "winget install failed" }
        $env:Path = [System.Environment]::GetEnvironmentVariable("Path","Machine") + ";" + [System.Environment]::GetEnvironmentVariable("Path","User")
        Write-Host "  ✓ Go installed successfully" -ForegroundColor Green
    }
    catch {
        Write-Host "ERROR: Failed to install Go. Install manually: https://go.dev/dl/" -ForegroundColor Red
        exit 1
    }
}

# Function to install pnpm
function Install-Pnpm {
    Write-Host "  Installing pnpm globally..." -ForegroundColor Yellow
    npm install -g pnpm
    if ($LASTEXITCODE -ne 0) { 
        Write-Host "ERROR: Failed to install pnpm" -ForegroundColor Red
        exit 1 
    }
    $env:Path = [System.Environment]::GetEnvironmentVariable("Path","Machine") + ";" + [System.Environment]::GetEnvironmentVariable("Path","User")
    Write-Host "  ✓ pnpm installed successfully" -ForegroundColor Green
}

# Set environment variables from config
if ($config.env) {
    foreach ($key in $config.env.PSObject.Properties.Name) {
        [System.Environment]::SetEnvironmentVariable($key, $config.env.$key, "Process")
    }
}

# Step timers
$StepTimes = @{}

# Step 1: Git pull
$stepWatch = [System.Diagnostics.Stopwatch]::StartNew()
if (-not $SkipPull) {
    Write-Host "[1/5] Pulling latest changes from git..." -ForegroundColor Yellow
    
    Push-Location $RootDir
    try {
        if (Test-Path ".git") {
            git pull
            if ($LASTEXITCODE -ne 0) {
                Write-Host "  WARNING: git pull failed, continuing..." -ForegroundColor Yellow
            } else {
                Write-Host "  ✓ Git pull complete" -ForegroundColor Green
            }
        } else {
            Write-Host "  Skipping (not a git repository)" -ForegroundColor Gray
        }
    }
    finally { Pop-Location }
} else {
    Write-Host "[1/5] Skipping git pull (-SkipPull)" -ForegroundColor Gray
}
$stepWatch.Stop()
$StepTimes["Git Pull"] = $stepWatch.Elapsed
Write-Host "  ⏱ $(Format-ElapsedTime $stepWatch)" -ForegroundColor DarkGray
Write-Host ""

# Step 2: Check prerequisites
$stepWatch = [System.Diagnostics.Stopwatch]::StartNew()
Write-Host "[2/5] Checking prerequisites..." -ForegroundColor Yellow

$prereqs = $config.prerequisites
if ($null -eq $prereqs -or $prereqs.go) {
    if (-not (Test-Command "go")) {
        Install-Go
    }
    Write-Host "  ✓ Go found: $(go version)" -ForegroundColor Green
}

if ($null -eq $prereqs -or $prereqs.node) {
    if (-not (Test-Command "node")) {
        Install-NodeJS
    }
    Write-Host "  ✓ Node.js found: $(node --version)" -ForegroundColor Green
}

if ($null -eq $prereqs -or $prereqs.pnpm) {
    if (-not (Test-Command "pnpm")) {
        Install-Pnpm
    }
    Write-Host "  ✓ pnpm found: $(pnpm --version)" -ForegroundColor Green
}

$stepWatch.Stop()
$StepTimes["Prerequisites"] = $stepWatch.Elapsed
Write-Host "  ⏱ $(Format-ElapsedTime $stepWatch)" -ForegroundColor DarkGray

# Step 3 & 4: Install & Build
$stepWatch = [System.Diagnostics.Stopwatch]::StartNew()
if (-not $SkipBuild) {
    Write-Host ""
    Write-Host "[3/5] Installing dependencies (pnpm PnP)..." -ForegroundColor Yellow
    
    Push-Location $FrontendDir
    try {
        # Configure pnpm store path
        if ($PnpmStorePath) {
            $storePath = if ([System.IO.Path]::IsPathRooted($PnpmStorePath)) {
                $PnpmStorePath
            } else {
                Join-Path $RootDir $PnpmStorePath
            }
            pnpm config set store-dir $storePath
            Write-Host "  Store path: $storePath" -ForegroundColor Gray
        }

        # Force clean
        if ($Force) {
            Write-Host "  FORCE MODE: Cleaning build artifacts..." -ForegroundColor Magenta
            foreach ($cleanPath in $CleanPaths) {
                $fullPath = Join-Path $RootDir $cleanPath
                if ($cleanPath -match '\*') {
                    # Handle glob patterns
                    $parentDir = Split-Path $fullPath -Parent
                    $pattern = Split-Path $fullPath -Leaf
                    if (Test-Path $parentDir) {
                        Get-ChildItem -Path $parentDir -Filter $pattern -Recurse -ErrorAction SilentlyContinue | ForEach-Object {
                            Write-Host "  Removing: $($_.Name)..." -ForegroundColor Gray
                            Remove-Item -Force $_.FullName -ErrorAction SilentlyContinue
                        }
                    }
                } elseif (Test-Path $fullPath) {
                    Write-Host "  Removing: $cleanPath..." -ForegroundColor Gray
                    Remove-Item -Recurse -Force $fullPath -ErrorAction SilentlyContinue
                }
            }
            
            # Prune pnpm store
            Write-Host "  Pruning pnpm store..." -ForegroundColor Gray
            pnpm store prune 2>$null
            
            Write-Host "  ✓ Clean complete" -ForegroundColor Magenta
        }

        # Check if install needed
        $NeedsInstall = -not (Test-Path "pnpm-lock.yaml") -or $Force
        if (-not $NeedsInstall -and $RequiredModules.Count -gt 0) {
            foreach ($m in $RequiredModules) {
                $modulePath = Join-Path "node_modules" $m
                if (-not (Test-Path $modulePath)) {
                    $NeedsInstall = $true
                    break
                }
            }
        }

        if ($NeedsInstall) {
            Write-Host "  Installing dependencies..." -ForegroundColor Gray
            Invoke-Expression $InstallCommand
            if ($LASTEXITCODE -ne 0) { throw "pnpm install failed" }
        }
        
        Write-Host "  ✓ Dependencies installed" -ForegroundColor Green
        
        # Build
        Write-Host ""
        Write-Host "[4/5] Building React frontend..." -ForegroundColor Yellow
        Write-Host "  Running: $BuildCommand" -ForegroundColor Gray
        Invoke-Expression $BuildCommand
        if ($LASTEXITCODE -ne 0) { throw "Build failed" }
        
        Write-Host "  ✓ Frontend built successfully" -ForegroundColor Green
    }
    finally { Pop-Location }
    
    $stepWatch.Stop()
    $StepTimes["Install & Build"] = $stepWatch.Elapsed
    Write-Host "  ⏱ $(Format-ElapsedTime $stepWatch)" -ForegroundColor DarkGray
    
    # Copy dist to backend
    $copyWatch = [System.Diagnostics.Stopwatch]::StartNew()
    Write-Host ""
    Write-Host "  Copying build to backend..." -ForegroundColor Yellow
    
    $SourceDist = Join-Path $FrontendDir $DistDir
    
    # Create target directory if needed
    $TargetParent = Split-Path $TargetDir -Parent
    if (-not (Test-Path $TargetParent)) {
        New-Item -ItemType Directory -Path $TargetParent | Out-Null
    }
    
    # Remove old dist if exists
    if (Test-Path $TargetDir) {
        Remove-Item -Recurse -Force $TargetDir
    }
    
    # Copy new dist
    Copy-Item -Recurse $SourceDist $TargetDir
    Write-Host "  ✓ Build files copied to $TargetDir" -ForegroundColor Green
    $copyWatch.Stop()
    $StepTimes["Copy Build"] = $copyWatch.Elapsed
} else {
    Write-Host ""
    Write-Host "[3/5] Skipping install (-SkipBuild)" -ForegroundColor Gray
    Write-Host "[4/5] Skipping build (-SkipBuild)" -ForegroundColor Gray
    $stepWatch.Stop()
    $StepTimes["Install & Build"] = $stepWatch.Elapsed
    $StepTimes["Copy Build"] = [TimeSpan]::Zero
}

if ($BuildOnly) {
    $TotalStopwatch.Stop()
    Write-Host ""
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "  Build complete! (-BuildOnly mode)" -ForegroundColor Cyan
    Write-Host "  Total time: $(Format-ElapsedTime $TotalStopwatch)" -ForegroundColor Cyan
    Write-Host "========================================" -ForegroundColor Cyan
    exit 0
}

# Step 5: Run Go backend
Write-Host ""
Write-Host "[5/5] Starting Go backend..." -ForegroundColor Yellow

Push-Location $BackendDir
try {
    # Check for config.json
    $backendConfig = Join-Path $BackendDir $ConfigFile
    $backendConfigExample = Join-Path $BackendDir $ConfigExampleFile
    if (-not (Test-Path $backendConfig)) {
        if (Test-Path $backendConfigExample) {
            Write-Host "  Copying $ConfigExampleFile to $ConfigFile..." -ForegroundColor Gray
            Copy-Item $backendConfigExample $backendConfig
        } else {
            Write-Host "  WARNING: $ConfigFile not found!" -ForegroundColor Yellow
        }
    }
    
    # Create data directory
    if (-not (Test-Path $DataDir)) {
        New-Item -ItemType Directory -Path $DataDir | Out-Null
    }

    if ($OpenFirewall) {
        Write-Host "  Configuring Windows Firewall rules..." -ForegroundColor Yellow
        Ensure-FirewallRules -Ports $Ports
    }
    
    $TotalStopwatch.Stop()
    Write-Host ""
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "  $ProjectName starting..." -ForegroundColor Cyan
    Write-Host "  Open: http://localhost:$($Ports[0])" -ForegroundColor Cyan
    Write-Host "  Press Ctrl+C to stop" -ForegroundColor Cyan
    Write-Host "  Build time: $(Format-ElapsedTime $TotalStopwatch)" -ForegroundColor Cyan
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host ""
    
    Invoke-Expression $RunCommand
}
finally { Pop-Location }
