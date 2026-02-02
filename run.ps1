# WP Plugin Publish - PowerShell Build & Run Script
# Supports pnpm with PnP for disk-efficient Node.js dependency management
# All paths are relative to script location (working directory)

param(
    [Alias('b')][switch]$buildonly,
    [Alias('s')][switch]$skipbuild,
    [Alias('p')][switch]$skippull,
    [Alias('f')][switch]$force,
    [Alias('i')][switch]$install,
    [Alias('r')][switch]$rebuild,
    [Alias('fw')][switch]$openfirewall,
    [Alias('h')][switch]$help,
    [Alias('v')][switch]$verbose
)

# -rebuild is a convenience flag that combines -force and -install
if ($rebuild) {
    $force = $true
    $install = $true
}

$ErrorActionPreference = "Stop"

# ============================================================================
# PATH RESOLUTION: Script location is the working directory
# ============================================================================
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
if ([string]::IsNullOrWhiteSpace($ScriptDir)) {
    $ScriptDir = Get-Location
}

# ============================================================================
# CONFIGURATION LOADING
# ============================================================================
$ConfigPath = Join-Path $ScriptDir "powershell.json"

if (-not (Test-Path $ConfigPath)) {
    Write-Host "ERROR: powershell.json not found at: $ConfigPath" -ForegroundColor Red
    Write-Host "Create a powershell.json configuration file in the script directory." -ForegroundColor Yellow
    exit 1
}

try {
    $Config = Get-Content $ConfigPath -Raw | ConvertFrom-Json
} catch {
    Write-Host "ERROR: Failed to parse powershell.json: $_" -ForegroundColor Red
    exit 1
}

# Resolve paths relative to script directory
# Resolve paths - handles both relative and absolute paths
function Resolve-RelativePath($Path) {
    if ([string]::IsNullOrWhiteSpace($Path) -or $Path -eq ".") {
        return $ScriptDir
    }
    # Check if path is already absolute (starts with drive letter or UNC path)
    if ($Path -match '^[A-Za-z]:' -or $Path -match '^\\\\') {
        return $Path -replace '/', '\'
    }
    return Join-Path $ScriptDir $Path
}

# Configuration with defaults
$ProjectName = if ($Config.projectName) { $Config.projectName } else { "Project" }
$RootDir = Resolve-RelativePath $Config.rootDir
$BackendDir = Resolve-RelativePath $Config.backendDir
$FrontendDir = Resolve-RelativePath $Config.frontendDir
$DistDir = if ($Config.distDir) { $Config.distDir } else { "dist" }
$TargetDir = if ($Config.targetDir) { Resolve-RelativePath $Config.targetDir } else { $null }
$DataDir = if ($Config.dataDir) { Resolve-RelativePath $Config.dataDir } else { $null }
$Ports = if ($Config.ports) { $Config.ports } else { @(8080) }
$BuildCommand = if ($Config.buildCommand) { $Config.buildCommand } else { "pnpm run build" }
$InstallCommand = if ($Config.installCommand) { $Config.installCommand } else { "pnpm install" }
$RunCommand = if ($Config.runCommand) { $Config.runCommand } else { "go run cmd/server/main.go" }
$CleanPaths = if ($Config.cleanPaths) { $Config.cleanPaths } else { @("node_modules", "dist", ".vite") }
$ConfigFile = if ($Config.configFile) { $Config.configFile } else { "config.json" }
$ConfigExampleFile = if ($Config.configExampleFile) { $Config.configExampleFile } else { "config.example.json" }
$RequiredModules = if ($Config.requiredModules) { $Config.requiredModules } else { @() }

# pnpm configuration
$PnpmStorePath = if ($Config.pnpmStorePath) { Resolve-RelativePath $Config.pnpmStorePath } else { $null }
$UsePnp = if ($null -ne $Config.usePnp) { $Config.usePnp } else { $true }

# Prerequisites
$CheckGo = if ($null -ne $Config.prerequisites -and $null -ne $Config.prerequisites.go) { $Config.prerequisites.go } else { $true }
$CheckNode = if ($null -ne $Config.prerequisites -and $null -ne $Config.prerequisites.node) { $Config.prerequisites.node } else { $true }
$CheckPnpm = if ($null -ne $Config.prerequisites -and $null -ne $Config.prerequisites.pnpm) { $Config.prerequisites.pnpm } else { $true }

$TotalStopwatch = [System.Diagnostics.Stopwatch]::StartNew()

# ============================================================================
# HELP
# ============================================================================
if ($help) {
    Write-Host ""
    Write-Host "$ProjectName - Build & Run Script" -ForegroundColor Cyan
    Write-Host ("=" * ($ProjectName.Length + 22)) -ForegroundColor Cyan
    Write-Host ""
    Write-Host "USAGE:" -ForegroundColor Yellow
    Write-Host "  .\run.ps1 [flags]"
    Write-Host ""
    Write-Host "FLAGS:" -ForegroundColor Yellow
    Write-Host "  -h,  -help          Show this help message and exit"
    Write-Host "  -b,  -buildonly     Build frontend only, don't start the backend server"
    Write-Host "  -s,  -skipbuild     Skip frontend build, only run the backend server"
    Write-Host "  -p,  -skippull      Skip git pull step"
    Write-Host "  -f,  -force         Clean build: remove caches, dependencies, databases"
    Write-Host "  -i,  -install       Install/update dependencies (frontend + backend)"
    Write-Host "  -r,  -rebuild       Complete clean reinstall (combines -f + -i)"
    Write-Host "  -fw, -openfirewall  (Admin) Add Windows Firewall inbound rules"
    Write-Host "  -v,  -verbose       Show detailed debug output"
    Write-Host ""
    Write-Host "EXAMPLES:" -ForegroundColor Yellow
    Write-Host "  .\run.ps1              # Full build and run"
    Write-Host "  .\run.ps1 -i           # Install/update all dependencies"
    Write-Host "  .\run.ps1 -r           # Complete clean reinstall and build"
    Write-Host "  .\run.ps1 -f           # Clean rebuild everything"
    Write-Host "  .\run.ps1 -s           # Just start the backend (skip build)"
    Write-Host "  .\run.ps1 -b           # Build only, don't start server"
    Write-Host "  .\run.ps1 -p -f        # Clean build without git pull"
    Write-Host ""
    Write-Host "CONFIGURATION:" -ForegroundColor Yellow
    Write-Host "  Config file: $ConfigPath"
    Write-Host "  Project: $ProjectName"
    Write-Host "  Backend: $BackendDir"
    Write-Host "  Frontend: $FrontendDir"
    if ($PnpmStorePath) {
        Write-Host "  pnpm Store: $PnpmStorePath"
    }
    Write-Host ""
    Write-Host "STEPS:" -ForegroundColor Yellow
    Write-Host "  1. Git pull (unless -p)"
    Write-Host "  2. Check prerequisites (Go, Node, pnpm)"
    Write-Host "  3. Build React frontend (unless -s)"
    Write-Host "  4. Copy build to backend (if targetDir configured)"
    Write-Host "  5. Start Go backend (unless -b)"
    Write-Host ""
    exit 0
}

# ============================================================================
# BANNER
# ============================================================================
Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  $ProjectName - Build & Run Script" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

if ($verbose) {
    Write-Host "Configuration:" -ForegroundColor Gray
    Write-Host "  Script Dir: $ScriptDir" -ForegroundColor Gray
    Write-Host "  Root Dir: $RootDir" -ForegroundColor Gray
    Write-Host "  Backend Dir: $BackendDir" -ForegroundColor Gray
    Write-Host "  Frontend Dir: $FrontendDir" -ForegroundColor Gray
    Write-Host "  pnpm Store: $PnpmStorePath" -ForegroundColor Gray
    Write-Host ""
}

# ============================================================================
# UTILITY FUNCTIONS
# ============================================================================

function Format-ElapsedTime($Stopwatch) {
    $elapsed = $Stopwatch.Elapsed
    if ($elapsed.TotalMinutes -ge 1) {
        return "{0:N0}m {1:N1}s" -f [Math]::Floor($elapsed.TotalMinutes), $elapsed.Seconds
    } else {
        return "{0:N1}s" -f $elapsed.TotalSeconds
    }
}

function Test-Command($Command) {
    $oldPreference = $ErrorActionPreference
    $ErrorActionPreference = 'SilentlyContinue'
    try { 
        $result = Get-Command $Command -ErrorAction SilentlyContinue
        return $null -ne $result
    }
    catch { return $false }
    finally { $ErrorActionPreference = $oldPreference }
}

function Test-IsAdmin {
    try {
        $currentUser = [Security.Principal.WindowsIdentity]::GetCurrent()
        $principal = New-Object Security.Principal.WindowsPrincipal($currentUser)
        return $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
    } catch {
        return $false
    }
}

function Refresh-Path {
    $env:Path = [System.Environment]::GetEnvironmentVariable("Path", "Machine") + ";" + 
                [System.Environment]::GetEnvironmentVariable("Path", "User")
}

# ============================================================================
# INSTALLATION FUNCTIONS
# ============================================================================

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
        Refresh-Path
        Write-Host "  ✓ Node.js installed successfully" -ForegroundColor Green
        Write-Host "  NOTE: You may need to restart PowerShell for PATH changes" -ForegroundColor Yellow
    }
    catch {
        Write-Host "ERROR: Failed to install Node.js. Please install manually:" -ForegroundColor Red
        Write-Host "  Download from: https://nodejs.org/" -ForegroundColor Yellow
        exit 1
    }
}

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
        Refresh-Path
        Write-Host "  ✓ Go installed successfully" -ForegroundColor Green
        Write-Host "  NOTE: You may need to restart PowerShell for PATH changes" -ForegroundColor Yellow
    }
    catch {
        Write-Host "ERROR: Failed to install Go. Please install manually:" -ForegroundColor Red
        Write-Host "  Download from: https://go.dev/dl/" -ForegroundColor Yellow
        exit 1
    }
}

function Install-Pnpm {
    Write-Host "  Installing pnpm globally..." -ForegroundColor Yellow
    
    try {
        npm install -g pnpm
        if ($LASTEXITCODE -ne 0) { throw "pnpm install failed" }
        Refresh-Path
        Write-Host "  ✓ pnpm installed successfully" -ForegroundColor Green
    }
    catch {
        Write-Host "ERROR: Failed to install pnpm. Please install manually:" -ForegroundColor Red
        Write-Host "  Run: npm install -g pnpm" -ForegroundColor Yellow
        exit 1
    }
}

function Configure-PnpmStore {
    if ($PnpmStorePath) {
        Write-Host "  Configuring pnpm store path: $PnpmStorePath" -ForegroundColor Gray
        
        # Ensure store directory exists
        if (-not (Test-Path $PnpmStorePath)) {
            New-Item -ItemType Directory -Path $PnpmStorePath -Force | Out-Null
        }
        
        # Set pnpm store directory globally
        pnpm config set store-dir $PnpmStorePath --global
        if ($LASTEXITCODE -ne 0) {
            Write-Host "  WARNING: Failed to set pnpm store path" -ForegroundColor Yellow
        } else {
            Write-Host "  ✓ pnpm store: $PnpmStorePath" -ForegroundColor Green
        }
        
        # Use virtual store inside the shared store for minimal local footprint
        $virtualStorePath = Join-Path $PnpmStorePath "v3"
        pnpm config set virtual-store-dir $virtualStorePath --global 2>$null
    }
    
    # Configure node linker based on usePnp setting
    if ($UsePnp) {
        Write-Host "  Enabling Plug'n'Play (PnP) mode - no node_modules folder" -ForegroundColor Gray
        pnpm config set node-linker pnp --global 2>$null
        pnpm config set symlink false --global 2>$null
    } else {
        # Use hoisted mode but with hard links to store (still efficient)
        pnpm config set node-linker hoisted --global 2>$null
    }
    
    # Always use hard links for maximum efficiency
    pnpm config set package-import-method hardlink --global 2>$null
}

function Ensure-FirewallRules {
    param([int[]]$PortList = @(8080))

    if (-not (Test-IsAdmin)) {
        Write-Host "  WARNING: -OpenFirewall requires Administrator. Re-run PowerShell as Admin." -ForegroundColor Yellow
        Write-Host "  TIP: Right-click PowerShell → Run as Administrator" -ForegroundColor Gray
        return
    }

    if (-not (Test-Command "New-NetFirewallRule")) {
        Write-Host "  WARNING: New-NetFirewallRule not available. Skipping firewall setup." -ForegroundColor Yellow
        return
    }

    foreach ($p in $PortList) {
        $ruleName = "$ProjectName (Backend) TCP $p"
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

# ============================================================================
# STEP TRACKING
# ============================================================================
$StepTimes = @{}

# ============================================================================
# STEP 1: GIT PULL
# ============================================================================
$stepWatch = [System.Diagnostics.Stopwatch]::StartNew()
if (-not $skippull) {
    Write-Host "[1/5] Pulling latest changes from git..." -ForegroundColor Yellow
    
    Push-Location $RootDir
    try {
        if (Test-Path ".git") {
            git pull 2>&1 | Out-Host
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
    Write-Host "[1/5] Skipping git pull (-p)" -ForegroundColor Gray
}
$stepWatch.Stop()
$StepTimes["Git Pull"] = $stepWatch.Elapsed
Write-Host "  ⏱ $(Format-ElapsedTime $stepWatch)" -ForegroundColor DarkGray
Write-Host ""

# ============================================================================
# STEP 2: PREREQUISITES
# ============================================================================
$stepWatch = [System.Diagnostics.Stopwatch]::StartNew()
Write-Host "[2/5] Checking prerequisites..." -ForegroundColor Yellow

# Check Go
if ($CheckGo) {
    if (-not (Test-Command "go")) {
        Write-Host "  Go is not installed or not in PATH" -ForegroundColor Yellow
        Install-Go
    }
    $goVersion = (go version 2>&1) -replace 'go version ', ''
    Write-Host "  ✓ Go found: $goVersion" -ForegroundColor Green
}

# Check Node
if ($CheckNode) {
    if (-not (Test-Command "node")) {
        Write-Host "  Node.js is not installed or not in PATH" -ForegroundColor Yellow
        Install-NodeJS
    }
    $nodeVersion = node --version 2>&1
    Write-Host "  ✓ Node.js found: $nodeVersion" -ForegroundColor Green
}

# Check pnpm
if ($CheckPnpm) {
    if (-not (Test-Command "pnpm")) {
        Write-Host "  pnpm is not installed" -ForegroundColor Yellow
        Install-Pnpm
    }
    $pnpmVersion = pnpm --version 2>&1
    Write-Host "  ✓ pnpm found: $pnpmVersion" -ForegroundColor Green
    
    # Configure pnpm store
    Configure-PnpmStore
}

$stepWatch.Stop()
$StepTimes["Prerequisites"] = $stepWatch.Elapsed
Write-Host "  ⏱ $(Format-ElapsedTime $stepWatch)" -ForegroundColor DarkGray
Write-Host ""

# ============================================================================
# INSTALL MODE: Install dependencies for both frontend and backend
# ============================================================================
if ($install) {
    $stepWatch = [System.Diagnostics.Stopwatch]::StartNew()
    Write-Host "[INSTALL] Installing/updating all dependencies..." -ForegroundColor Cyan
    Write-Host ""
    
    # Frontend: pnpm install
    Write-Host "  [Frontend] Running pnpm install..." -ForegroundColor Yellow
    Push-Location $FrontendDir
    try {
        # Configure pnpm store first
        Configure-PnpmStore
        
        Invoke-Expression $InstallCommand
        if ($LASTEXITCODE -ne 0) { throw "pnpm install failed" }
        Write-Host "  ✓ Frontend dependencies installed" -ForegroundColor Green
    }
    finally {
        Pop-Location
    }
    
    # Backend: go mod tidy + go mod download
    Write-Host ""
    Write-Host "  [Backend] Running go mod tidy && go mod download..." -ForegroundColor Yellow
    Push-Location $BackendDir
    try {
        go mod tidy
        if ($LASTEXITCODE -ne 0) { throw "go mod tidy failed" }
        
        go mod download
        if ($LASTEXITCODE -ne 0) { throw "go mod download failed" }
        
        Write-Host "  ✓ Backend dependencies installed" -ForegroundColor Green
    }
    finally {
        Pop-Location
    }
    
    $stepWatch.Stop()
    $StepTimes["Install Dependencies"] = $stepWatch.Elapsed
    
    Write-Host ""
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "  Dependencies installed successfully!" -ForegroundColor Cyan
    Write-Host "  Time: $(Format-ElapsedTime $stepWatch)" -ForegroundColor Cyan
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "Next steps:" -ForegroundColor Yellow
    Write-Host "  .\run.ps1        # Build and run the application" -ForegroundColor Gray
    Write-Host "  .\run.ps1 -f     # Clean rebuild if needed" -ForegroundColor Gray
    Write-Host ""
    exit 0
}

# ============================================================================
# STEP 3: FRONTEND BUILD
# ============================================================================
$stepWatch = [System.Diagnostics.Stopwatch]::StartNew()
if (-not $skipbuild) {
    Write-Host "[3/5] Building React frontend..." -ForegroundColor Yellow
    
    Push-Location $FrontendDir
    try {
        # Force clean build
        if ($force) {
            Write-Host "  FORCE MODE: Cleaning build artifacts..." -ForegroundColor Magenta
            
            foreach ($cleanPath in $CleanPaths) {
                # Handle wildcards
                if ($cleanPath -match '\*') {
                    $resolvedPath = Resolve-RelativePath ($cleanPath -replace '\*.*$', '')
                    $pattern = $cleanPath -replace '^.*[\\/]', ''
                    if (Test-Path $resolvedPath) {
                        $items = Get-ChildItem -Path $resolvedPath -Filter $pattern -ErrorAction SilentlyContinue
                        foreach ($item in $items) {
                            Write-Host "  Removing: $($item.Name)..." -ForegroundColor Gray
                            Remove-Item -Force -Recurse $item.FullName -ErrorAction SilentlyContinue
                        }
                    }
                } else {
                    $resolvedPath = Resolve-RelativePath $cleanPath
                    if (Test-Path $resolvedPath) {
                        Write-Host "  Removing: $cleanPath..." -ForegroundColor Gray
                        Remove-Item -Recurse -Force $resolvedPath -ErrorAction SilentlyContinue
                    }
                }
            }
            
            # Clear pnpm cache if force mode
            if ($CheckPnpm) {
                Write-Host "  Clearing pnpm cache..." -ForegroundColor Gray
                pnpm store prune 2>&1 | Out-Null
            }
            
            Write-Host "  ✓ Clean complete" -ForegroundColor Magenta
        }
        
        # Check if install needed
        $NeedsInstall = -not (Test-Path "node_modules") -and -not (Test-Path ".pnp.cjs")
        
        # Check for required modules (catches new deps after git pull)
        if (-not $NeedsInstall -and $RequiredModules.Count -gt 0) {
            foreach ($m in $RequiredModules) {
                $modulePath = Join-Path "node_modules" $m
                if (-not (Test-Path $modulePath)) {
                    $NeedsInstall = $true
                    Write-Host "  Missing module: $m - will reinstall" -ForegroundColor Gray
                    break
                }
            }
        }

        if ($NeedsInstall -or $force) {
            Write-Host "  Installing dependencies with pnpm..." -ForegroundColor Gray
            Invoke-Expression $InstallCommand
            if ($LASTEXITCODE -ne 0) { throw "pnpm install failed" }
        }
        
        # Build
        Write-Host "  Running build command: $BuildCommand" -ForegroundColor Gray
        Invoke-Expression $BuildCommand
        if ($LASTEXITCODE -ne 0) { throw "Build failed" }
        
        Write-Host "  ✓ Frontend built successfully" -ForegroundColor Green
    }
    finally {
        Pop-Location
    }
    $stepWatch.Stop()
    $StepTimes["Frontend Build"] = $stepWatch.Elapsed
    Write-Host "  ⏱ $(Format-ElapsedTime $stepWatch)" -ForegroundColor DarkGray
    Write-Host ""
    
    # ============================================================================
    # STEP 4: COPY BUILD TO BACKEND
    # ============================================================================
    $stepWatch = [System.Diagnostics.Stopwatch]::StartNew()
    
    if ($TargetDir) {
        Write-Host "[4/5] Copying build to Go backend..." -ForegroundColor Yellow
        
        $SourceDist = Join-Path $FrontendDir $DistDir
        
        if (-not (Test-Path $SourceDist)) {
            Write-Host "  WARNING: Build output not found at: $SourceDist" -ForegroundColor Yellow
        } else {
            # Ensure target parent exists
            $TargetParent = Split-Path -Parent $TargetDir
            if (-not (Test-Path $TargetParent)) {
                New-Item -ItemType Directory -Path $TargetParent -Force | Out-Null
            }
            
            # Remove old and copy new
            if (Test-Path $TargetDir) {
                Remove-Item -Recurse -Force $TargetDir
            }
            Copy-Item -Recurse $SourceDist $TargetDir
            Write-Host "  ✓ Build files copied to: $TargetDir" -ForegroundColor Green
        }
    } else {
        Write-Host "[4/5] Skipping copy (no targetDir configured)" -ForegroundColor Gray
    }
    
    $stepWatch.Stop()
    $StepTimes["Copy Build"] = $stepWatch.Elapsed
    Write-Host "  ⏱ $(Format-ElapsedTime $stepWatch)" -ForegroundColor DarkGray
} else {
    Write-Host "[3/5] Skipping frontend build (-s)" -ForegroundColor Gray
    Write-Host "[4/5] Skipping copy step" -ForegroundColor Gray
    $stepWatch.Stop()
    $StepTimes["Frontend Build"] = [TimeSpan]::Zero
    $StepTimes["Copy Build"] = [TimeSpan]::Zero
}
Write-Host ""

# ============================================================================
# BUILD ONLY EXIT
# ============================================================================
if ($buildonly) {
    $TotalStopwatch.Stop()
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "  Build complete! (-b mode)" -ForegroundColor Cyan
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

# ============================================================================
# STEP 5: START BACKEND
# ============================================================================
Write-Host "[5/5] Starting Go backend..." -ForegroundColor Yellow

Push-Location $BackendDir
try {
    # Check for config file
    $BackendConfigPath = Join-Path $BackendDir $ConfigFile
    $BackendConfigExample = Join-Path $BackendDir $ConfigExampleFile
    
    if (-not (Test-Path $BackendConfigPath)) {
        if (Test-Path $BackendConfigExample) {
            Write-Host "  Creating $ConfigFile from $ConfigExampleFile..." -ForegroundColor Gray
            Copy-Item $BackendConfigExample $BackendConfigPath
            Write-Host "  Please edit $ConfigFile with your settings" -ForegroundColor Yellow
        } else {
            Write-Host "  WARNING: No $ConfigFile or $ConfigExampleFile found" -ForegroundColor Yellow
        }
    }
    
    # Create data directory if configured
    if ($DataDir -and -not (Test-Path $DataDir)) {
        New-Item -ItemType Directory -Path $DataDir -Force | Out-Null
        Write-Host "  Created data directory: $DataDir" -ForegroundColor Gray
    }

    # Firewall rules
    if ($openfirewall) {
        Write-Host "  Configuring Windows Firewall rules..." -ForegroundColor Yellow
        Ensure-FirewallRules -PortList $Ports
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
    
    # Run the backend
    Invoke-Expression $RunCommand
}
finally {
    Pop-Location
}
