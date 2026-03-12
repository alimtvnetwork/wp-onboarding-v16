# WP Plugin Publish - PowerShell Build & Run Script
# Version: 2.0.3
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
    [Alias('u')][switch]$upload,
    [Alias('q')][switch]$qupload,
    [switch]$za,
    [Alias('zq')][switch]$zipqupload,
    [Alias('z')][switch]$zip,
    [Alias('t')][switch]$test,
    [Alias('h')][switch]$help,
    [Alias('v')][switch]$verbose,
    [Alias('d')][switch]$debug,
    [Alias('c')][switch]$clear,
    [Alias('pp')][string]$pluginpath = ""
)

# -rebuild is a convenience flag that combines -force and -install
if ($rebuild) {
    $force = $true
    $install = $true
}

$ErrorActionPreference = "Stop"

# ============================================================================
# SELF-LINT CHECK: Validate script syntax before execution
# ============================================================================
$scriptFile = $MyInvocation.MyCommand.Path
if ($scriptFile -and (Test-Path $scriptFile)) {
    $lintErrors = $null
    [void][System.Management.Automation.Language.Parser]::ParseFile(
        $scriptFile, [ref]$null, [ref]$lintErrors
    )
    if ($lintErrors -and $lintErrors.Count -gt 0) {
        Write-Host "SCRIPT LINT FAILED: run.ps1 has parse errors" -ForegroundColor Red
        foreach ($e in $lintErrors) {
            Write-Host "  Line $($e.Extent.StartLineNumber): $($e.Message)" -ForegroundColor Yellow
        }
        Write-Host ""
        Write-Host "Common fix: Ensure the file is saved as UTF-8 (no BOM) with straight ASCII quotes." -ForegroundColor Cyan
        exit 1
    }
}

# ============================================================================
# TEST MODE: Run Go tests and exit early
# ============================================================================
if ($test) {
    $ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
    if ([string]::IsNullOrWhiteSpace($ScriptDir)) {
        $ScriptDir = Get-Location
    }

    $ConfigPath = Join-Path $ScriptDir "powershell.json"
    $Config = Get-Content $ConfigPath -Raw | ConvertFrom-Json

    function Resolve-RelativePathForTest($Path) {
        if ([string]::IsNullOrWhiteSpace($Path) -or $Path -eq ".") { return $ScriptDir }
        if ($Path -match '^[A-Za-z]:' -or $Path -match '^\\\\') { return $Path -replace '/', '\' }
        return Join-Path $ScriptDir $Path
    }

    $BackendDirTest = Resolve-RelativePathForTest $Config.backendDir
    $DataDirTest = if ($Config.dataDir) { Resolve-RelativePathForTest $Config.dataDir } else { Join-Path $BackendDirTest "data" }

    if (-not (Test-Path $DataDirTest)) {
        New-Item -ItemType Directory -Path $DataDirTest -Force | Out-Null
    }

    $TestLogFile = Join-Path $DataDirTest "tests.log.txt"
    $ErrorLogFile = Join-Path $DataDirTest "error.txt"

    Write-Host ""
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "  Running Go Tests..." -ForegroundColor Cyan
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host ""

    Push-Location $BackendDirTest
    try {
        $testOutput = go test -v -count=1 ./... 2>&1 | Out-String
        $testExitCode = $LASTEXITCODE

        # Write full test log
        $testOutput | Out-File -FilePath $TestLogFile -Encoding UTF8

        # Extract failures for error log
        $failLines = ($testOutput -split "`n") | Where-Object { $_ -match '--- FAIL|FAIL\s' }

        if ($testExitCode -ne 0) {
            $failLines -join "`n" | Out-File -FilePath $ErrorLogFile -Encoding UTF8

            Write-Host $testOutput
            Write-Host ""
            Write-Host "  TESTS FAILED" -ForegroundColor Red
            Write-Host "  Full log:  $TestLogFile" -ForegroundColor Yellow
            Write-Host "  Errors:    $ErrorLogFile" -ForegroundColor Yellow
        } else {
            # Clear error file on success
            if (Test-Path $ErrorLogFile) { Remove-Item $ErrorLogFile -Force }

            Write-Host $testOutput
            Write-Host ""
            Write-Host "  ALL TESTS PASSED" -ForegroundColor Green
            Write-Host "  Full log:  $TestLogFile" -ForegroundColor DarkGray
        }
    }
    finally {
        Pop-Location
    }

    Write-Host ""
    exit $testExitCode
}

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
$RunCommand = if ($Config.runCommand) { $Config.runCommand } else { "go run ./cmd/server" }
if ($RunCommand -match 'go\s+run\s+\.?/?cmd/server/main\.go') {
    $RunCommand = "go run ./cmd/server"
}
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

# pnpm version-aware install behavior (pnpm v10+ blocks dependency build scripts by default)
$PnpmMajor = 0
$NodeMajor = 0
$EffectiveInstallCommand = $InstallCommand
$DidFrontendInstall = $false

# pnpm linker used for this run (computed by Configure-PnpmStore)
$EffectiveNodeLinker = if ($UsePnp) { "pnp" } else { "isolated" }

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
    Write-Host "  -u,  -upload        Upload default plugin to WordPress via upload-plugin-v2"
    Write-Host "  -u -q               Upload Riseup Asia Uploader via QUpload API (shorthand)"
    Write-Host "  -q,  -qupload       Upload plugin to WordPress via QUpload API"
    Write-Host "  -d,  -debug         Enable debug logging for upload (shows all endpoints, paths, responses)"
    Write-Host "  -z,  -zip           ZIP Riseup Asia plugin (default uploader). With -pp: specific plugin"
    Write-Host "  -za                 ZIP ALL plugins in wp-plugins/ with version numbers"
    Write-Host "  -zq, -zipqupload    ZIP QUpload plugin"
    Write-Host "  -c,  -clear         Remove all existing ZIP files from wp-plugins/ before zipping"
    Write-Host "  -pp, -pluginpath    Override plugin folder path or name (use with -u, -q, -zq, or -z)"
    Write-Host "  -t,  -test          Run Go backend tests and exit"
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
    Write-Host "  .\run.ps1 -u           # Upload default plugin to WordPress"
    Write-Host "  .\run.ps1 -u -q        # Upload Riseup Asia Uploader via QUpload"
    Write-Host "  .\run.ps1 -u -d        # Upload with debug logging (shows all endpoints & responses)"
    Write-Host "  .\run.ps1 -u -pp 'C:\path\to\plugin'  # Upload a specific plugin"
    Write-Host "  .\run.ps1 -q           # Upload default plugin via QUpload API"
    Write-Host "  .\run.ps1 -q -pp 'wp-plugins/qupload' # Upload specific plugin via QUpload"
    Write-Host "  .\run.ps1 -z           # ZIP Riseup Asia plugin (default uploader)"
    Write-Host "  .\run.ps1 -z -pp 'wp-plugins/qupload' # ZIP a specific plugin"
    Write-Host "  .\run.ps1 -za          # ZIP ALL plugins in wp-plugins/"
    Write-Host "  .\run.ps1 -zq          # ZIP QUpload plugin"
    Write-Host "  .\run.ps1 -t           # Run Go backend tests"
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

function Get-PnpmMajorVersion([string]$Version) {
    try {
        $major = ($Version -split '\.')[0]
        return [int]$major
    } catch {
        return 0
    }
}

function Get-NodeMajorVersion([string]$Version) {
    try {
        $v = $Version.Trim()
        if ($v.StartsWith('v')) { $v = $v.Substring(1) }
        $major = ($v -split '\.')[0]
        return [int]$major
    } catch {
        return 0
    }
}

function Get-DriveRoot([string]$Path) {
    try {
        if ([string]::IsNullOrWhiteSpace($Path)) { return $null }
        $full = [System.IO.Path]::GetFullPath($Path)
        if ($full -match '^[A-Za-z]:') { return $full.Substring(0, 2).ToUpper() }
        return $null
    } catch {
        return $null
    }
}

function Get-EffectivePnpmInstallCommand([string]$BaseCommand, [int]$Major) {
    $cmd = $BaseCommand
    if ($Major -ge 10 -and $cmd -match '(^|\s)pnpm\s+install(\s|$)' -and $cmd -notmatch 'dangerously-allow-all-builds') {
        # Non-interactive equivalent to pnpm approve-builds
        $cmd = "$cmd --dangerously-allow-all-builds"
    }
    return $cmd
}

function Enable-PnpmPnpNodeOptions([string]$ProjectDir) {
    # In pnpm PnP mode, ESM resolution needs the PnP loader.
    $pnpCjs = Join-Path $ProjectDir ".pnp.cjs"
    $pnpLoader = Join-Path $ProjectDir ".pnp.loader.mjs"

    $additions = @()

    if (Test-Path $pnpCjs) {
        if ([string]::IsNullOrWhiteSpace($env:NODE_OPTIONS) -or ($env:NODE_OPTIONS -notmatch [regex]::Escape($pnpCjs))) {
            $additions += "--require `"$pnpCjs`""
        }
    }

    if (Test-Path $pnpLoader) {
        if ([string]::IsNullOrWhiteSpace($env:NODE_OPTIONS) -or ($env:NODE_OPTIONS -notmatch [regex]::Escape($pnpLoader))) {
            $additions += "--experimental-loader `"$pnpLoader`""
        }
    }

    if ($additions.Count -gt 0) {
        $env:NODE_OPTIONS = (($env:NODE_OPTIONS + " " + ($additions -join " ")).Trim())
    }
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
    # IMPORTANT:
    # - virtual-store-dir should NOT be shared between projects.
    # - writing these settings per-project avoids global config side effects.
    # - pnpm PnP + Node ESM (Vite) can be fragile on Windows/Node 24, so we fall back to isolated when needed.

    $projectDrive = Get-DriveRoot $FrontendDir
    $storeDrive = Get-DriveRoot $PnpmStorePath
    $crossDrive = $false
    if ($projectDrive -and $storeDrive -and ($projectDrive -ne $storeDrive)) {
        $crossDrive = $true
    }

    # Decide linker
    $nodeLinker = "isolated"
    if ($UsePnp -and (-not $crossDrive) -and ($NodeMajor -lt 24)) {
        $nodeLinker = "pnp"
    }

    # expose for later steps (build uses this)
    $script:EffectiveNodeLinker = $nodeLinker

    if ($UsePnp -and $nodeLinker -ne "pnp") {
        Write-Host "  NOTE: Falling back to node-linker=isolated for compatibility (Node v$NodeMajor / cross-drive store)." -ForegroundColor Yellow
    }

    # Ensure store directory exists
    if ($PnpmStorePath) {
        Write-Host "  Configuring pnpm store path: $PnpmStorePath" -ForegroundColor Gray
        if (-not (Test-Path $PnpmStorePath)) {
            New-Item -ItemType Directory -Path $PnpmStorePath -Force | Out-Null
        }
        pnpm config set --location=project store-dir $PnpmStorePath 2>$null
    }

    # Keep virtual store per-project (shorter paths on Windows, no sharing)
    pnpm config set --location=project virtual-store-dir .pnpm 2>$null

    # Set linker per-project
    pnpm config set --location=project node-linker $nodeLinker 2>$null

    # symlink handling
    if ($nodeLinker -eq "pnp") {
        pnpm config set --location=project symlink false 2>$null
    } else {
        pnpm config set --location=project symlink true 2>$null
    }

    # Let pnpm pick best method (hardlinks only work same-disk; otherwise pnpm copies)
    pnpm config set --location=project package-import-method auto 2>$null
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
    $NodeMajor = Get-NodeMajorVersion $nodeVersion
}

# Check pnpm
if ($CheckPnpm) {
    if (-not (Test-Command "pnpm")) {
        Write-Host "  pnpm is not installed" -ForegroundColor Yellow
        Install-Pnpm
    }
    $pnpmVersion = pnpm --version 2>&1
    Write-Host "  ✓ pnpm found: $pnpmVersion" -ForegroundColor Green

    $PnpmMajor = Get-PnpmMajorVersion $pnpmVersion
    $EffectiveInstallCommand = Get-EffectivePnpmInstallCommand $InstallCommand $PnpmMajor
    if ($verbose -and $EffectiveInstallCommand -ne $InstallCommand) {
        Write-Host "  pnpm v$PnpmMajor detected: enabling dependency build scripts during install" -ForegroundColor Gray
    }
    
    # Configure pnpm store
    Configure-PnpmStore
}

$stepWatch.Stop()
$StepTimes["Prerequisites"] = $stepWatch.Elapsed
Write-Host "  ⏱ $(Format-ElapsedTime $stepWatch)" -ForegroundColor DarkGray
Write-Host ""

# ============================================================================
# ZIP HELPERS: Shared functions for all ZIP modes
# ============================================================================

Add-Type -AssemblyName System.IO.Compression.FileSystem

# ── Helper: Extract version from PHP plugin header ──────────────────────
function Get-PluginVersion($PluginDir) {
    $phpFiles = Get-ChildItem $PluginDir -Filter "*.php" -File | Where-Object {
        (Get-Content $_.FullName -Head 5 -ErrorAction SilentlyContinue) -match "Plugin Name:"
    } | Select-Object -First 1

    if ($phpFiles) {
        $content = Get-Content $phpFiles.FullName -Raw -ErrorAction SilentlyContinue
        $match = [regex]::Match($content, "\*?\s*Version:\s*(\d+\.\d+\.\d+)")
        if ($match.Success) { return $match.Groups[1].Value }
    }

    return "unknown"
}

# ── Helper: Create a single versioned ZIP ───────────────────────────────
function New-PluginZip($PluginDir) {
    $pluginName = Split-Path $PluginDir -Leaf
    $version = Get-PluginVersion $PluginDir
    $zipFileName = "$pluginName-v$version.zip"
    $zipOutputPath = Join-Path (Split-Path $PluginDir -Parent) $zipFileName

    Write-Host "  Plugin:  $pluginName" -ForegroundColor Yellow
    Write-Host "  Version: v$version" -ForegroundColor Yellow
    Write-Host "  Source:  $PluginDir" -ForegroundColor Gray
    Write-Host "  Output:  $zipOutputPath" -ForegroundColor Gray

    # Remove existing ZIP if present
    if (Test-Path $zipOutputPath) {
        Remove-Item $zipOutputPath -Force
        Write-Host "  Replaced existing ZIP" -ForegroundColor DarkGray
    }

    # Create ZIP with best compression (SmallestSize)
    try {
        $tempDir = Join-Path $env:TEMP "wp-zip-$(Get-Random)"
        $pluginTempDir = Join-Path $tempDir $pluginName
        New-Item -ItemType Directory -Path $pluginTempDir -Force | Out-Null
        Copy-Item -Path "$PluginDir\*" -Destination $pluginTempDir -Recurse

        [System.IO.Compression.ZipFile]::CreateFromDirectory(
            $pluginTempDir,
            $zipOutputPath,
            [System.IO.Compression.CompressionLevel]::SmallestSize,
            $true
        )

        Remove-Item $tempDir -Recurse -Force

        if (Test-Path $zipOutputPath) {
            $zipSize = (Get-Item $zipOutputPath).Length
            $zipSizeKB = [math]::Round($zipSize / 1024, 1)
            $zipSizeMB = [math]::Round($zipSize / 1048576, 2)
            $sizeLabel = if ($zipSizeMB -ge 1) { "$zipSizeMB MB" } else { "$zipSizeKB KB" }
            Write-Host "  Created: $zipFileName ($sizeLabel)" -ForegroundColor Green
        } else {
            Write-Host "  ERROR: ZIP file was not created for $pluginName" -ForegroundColor Red
        }
    } catch {
        Write-Host "  ERROR: Failed to create ZIP for $pluginName`: $_" -ForegroundColor Red
    }

    Write-Host ""
}

# ── Helper: Resolve default uploader plugin path ───────────────────────
function Get-DefaultUploaderPath {
    $defaultUploader = $null
    if ($Config.wpPlugins -and $Config.wpPlugins.defaultUploader) {
        $defaultUploader = $Config.wpPlugins.defaultUploader
    }
    if (-not $defaultUploader -or -not $Config.wpPlugins.plugins.$defaultUploader) {
        Write-Host "ERROR: No default uploader configured in powershell.json (wpPlugins.defaultUploader)" -ForegroundColor Red
        exit 1
    }
    $pluginCfg = $Config.wpPlugins.plugins.$defaultUploader
    $resolved = Resolve-RelativePath $pluginCfg.path
    if (-not (Test-Path $resolved)) {
        Write-Host "ERROR: Plugin folder not found: $resolved" -ForegroundColor Red
        exit 1
    }
    Write-Host "  Plugin: $defaultUploader" -ForegroundColor Yellow
    return $resolved
}

# ── Helper: Resolve default QUploader plugin path ──────────────────────
function Get-DefaultQUploaderPath {
    $defaultQUploader = $null
    if ($Config.wpPlugins -and $Config.wpPlugins.defaultQUploader) {
        $defaultQUploader = $Config.wpPlugins.defaultQUploader
    }
    if (-not $defaultQUploader -and $Config.wpPlugins -and $Config.wpPlugins.defaultUploader) {
        $defaultQUploader = $Config.wpPlugins.defaultUploader
    }
    if (-not $defaultQUploader -or -not $Config.wpPlugins.plugins.$defaultQUploader) {
        Write-Host "ERROR: No default QUploader configured in powershell.json (wpPlugins.defaultQUploader)" -ForegroundColor Red
        exit 1
    }
    $pluginCfg = $Config.wpPlugins.plugins.$defaultQUploader
    $resolved = Resolve-RelativePath $pluginCfg.path
    if (-not (Test-Path $resolved)) {
        Write-Host "ERROR: Plugin folder not found: $resolved" -ForegroundColor Red
        exit 1
    }
    Write-Host "  Plugin: $defaultQUploader" -ForegroundColor Yellow
    return $resolved
}

# ============================================================================
# ZIP MODE: ZIP default Riseup Asia plugin (-z / -zip)
#   -z              -> ZIP default uploader (Riseup Asia)
#   -z -pp <path>   -> ZIP a specific plugin
# ============================================================================
if ($zip) {
    Write-Host ""
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "  ZIP Mode (-z)" -ForegroundColor Cyan
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host ""

    if ($clear) { Clear-PluginZips }

    if ($pluginpath -ne "") {
        $zipPluginPath = $pluginpath
        if (-not [System.IO.Path]::IsPathRooted($zipPluginPath)) {
            $zipPluginPath = Join-Path $ScriptDir $zipPluginPath
        }
        if (-not (Test-Path $zipPluginPath)) {
            Write-Host "ERROR: Plugin folder not found: $zipPluginPath" -ForegroundColor Red
            exit 1
        }
        Write-Host "  Using custom plugin path: $zipPluginPath" -ForegroundColor Cyan
        New-PluginZip $zipPluginPath
    } else {
        $defaultPath = Get-DefaultUploaderPath
        New-PluginZip $defaultPath
    }

    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "  ZIP complete!" -ForegroundColor Green
    Write-Host "========================================" -ForegroundColor Cyan

    exit 0
}

# ============================================================================
# ZIP ALL MODE: ZIP all plugins in wp-plugins/ (-za)
# ============================================================================
if ($za) {
    Write-Host ""
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "  ZIP All Mode (-za)" -ForegroundColor Cyan
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host ""

    if ($clear) { Clear-PluginZips }

    $wpPluginsDir = Join-Path $ScriptDir "wp-plugins"
    if (-not (Test-Path $wpPluginsDir)) {
        Write-Host "ERROR: wp-plugins/ directory not found" -ForegroundColor Red
        exit 1
    }

    $pluginFolders = Get-ChildItem $wpPluginsDir -Directory | Where-Object {
        $phpFiles = Get-ChildItem $_.FullName -Filter "*.php" -File -ErrorAction SilentlyContinue
        $hasPluginHeader = $false
        foreach ($f in $phpFiles) {
            $head = Get-Content $f.FullName -Head 5 -ErrorAction SilentlyContinue
            if ($head -match "Plugin Name:") { $hasPluginHeader = $true; break }
        }
        $hasPluginHeader
    }

    if ($pluginFolders.Count -eq 0) {
        Write-Host "No WordPress plugins found in wp-plugins/" -ForegroundColor Yellow
        exit 0
    }

    Write-Host "  Found $($pluginFolders.Count) plugin(s) to ZIP:" -ForegroundColor Cyan
    foreach ($folder in $pluginFolders) {
        Write-Host "    - $($folder.Name)" -ForegroundColor Gray
    }
    Write-Host ""

    foreach ($folder in $pluginFolders) {
        New-PluginZip $folder.FullName
    }

    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "  ZIP All complete!" -ForegroundColor Green
    Write-Host "========================================" -ForegroundColor Cyan

    exit 0
}

# ============================================================================
# ZIP QUPLOAD MODE: ZIP QUpload plugin (-zq / -zipqupload)
# ============================================================================
if ($zipqupload) {
    Write-Host ""
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "  ZIP QUpload Mode (-zq)" -ForegroundColor Cyan
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host ""

    $qPath = Get-DefaultQUploaderPath
    New-PluginZip $qPath

    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "  ZIP complete!" -ForegroundColor Green
    Write-Host "========================================" -ForegroundColor Cyan

    exit 0
}

# ============================================================================
# UPLOAD+QUPLOAD COMBO: -u -q = Upload Riseup Asia Uploader via QUpload API
# ============================================================================
if ($upload -and $qupload) {
    Write-Host ""
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "  Upload via QUpload Mode (-u -q)" -ForegroundColor Cyan
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host ""

    # Resolve Riseup Asia Uploader path from config
    $defaultUploader = $null
    if ($Config.wpPlugins -and $Config.wpPlugins.defaultUploader) {
        $defaultUploader = $Config.wpPlugins.defaultUploader
    }
    if (-not $defaultUploader -or -not $Config.wpPlugins.plugins.$defaultUploader) {
        Write-Host "ERROR: No default uploader configured in powershell.json (wpPlugins.defaultUploader)" -ForegroundColor Red
        exit 1
    }

    $pluginConfig = $Config.wpPlugins.plugins.$defaultUploader
    $riseupPath = Resolve-RelativePath $pluginConfig.path

    if (-not (Test-Path $riseupPath)) {
        Write-Host "ERROR: Plugin folder not found: $riseupPath" -ForegroundColor Red
        exit 1
    }

    Write-Host "  Plugin: $defaultUploader (via QUpload)" -ForegroundColor Yellow

    # Use QUpload script with Riseup Asia Uploader path
    $quploadScript = Join-Path $ScriptDir "wp-plugins/scripts/upload-plugin-U-Q.ps1"
    if (-not (Test-Path $quploadScript)) {
        Write-Host "ERROR: upload-plugin-U-Q.ps1 not found at: $quploadScript" -ForegroundColor Red
        exit 1
    }

    $qConfigPath = Join-Path $ScriptDir "wp-plugins/scripts/qupload-config.json"
    if (Test-Path $qConfigPath) {
        $qConfig = Get-Content $qConfigPath -Raw | ConvertFrom-Json
        $qConfig.pluginFolderPath = $riseupPath
        $jsonConfigStr = ($qConfig | ConvertTo-Json -Compress)
        Write-Host "  Path:   $riseupPath" -ForegroundColor Gray
        Write-Host "  Site:   $($qConfig.wordPressSiteURL)" -ForegroundColor Gray
        Write-Host ""
        & $quploadScript -jc $jsonConfigStr -a
    } else {
        Write-Host "ERROR: qupload-config.json not found at: $qConfigPath" -ForegroundColor Red
        exit 1
    }

    exit 0
}

# ============================================================================
# UPLOAD MODE: Upload default plugin to WordPress (early exit - no build/backend)
# ============================================================================
if ($upload) {
    Write-Host ""
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "  Upload Mode (-u)" -ForegroundColor Cyan
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host ""

    # Determine plugin path: use -pp override or default from config
    if ($pluginpath -ne "") {
        # Custom plugin path provided via -pp flag
        $pluginPath = $pluginpath
        if (-not [System.IO.Path]::IsPathRooted($pluginPath)) {
            $pluginPath = Join-Path $ScriptDir $pluginPath
        }
        if (-not (Test-Path $pluginPath)) {
            Write-Host "ERROR: Plugin folder not found: $pluginPath" -ForegroundColor Red
            exit 1
        }
        Write-Host "  Using custom plugin path: $pluginPath" -ForegroundColor Cyan
    } else {
        # Resolve default uploader from powershell.json
        $defaultUploader = $null
        if ($Config.wpPlugins -and $Config.wpPlugins.defaultUploader) {
            $defaultUploader = $Config.wpPlugins.defaultUploader
        }

        if (-not $defaultUploader -or -not $Config.wpPlugins.plugins.$defaultUploader) {
            Write-Host "ERROR: No default uploader configured in powershell.json (wpPlugins.defaultUploader)" -ForegroundColor Red
            exit 1
        }

        $pluginConfig = $Config.wpPlugins.plugins.$defaultUploader
        $pluginPath = Resolve-RelativePath $pluginConfig.path

        if (-not (Test-Path $pluginPath)) {
            Write-Host "ERROR: Plugin folder not found: $pluginPath" -ForegroundColor Red
            exit 1
        }
        Write-Host "  Plugin: $defaultUploader" -ForegroundColor Yellow
    }

    # Find the upload-plugin-v2.ps1 script
    $uploadScript = Join-Path $ScriptDir "wp-plugins/scripts/upload-plugin-v2.ps1"
    if (-not (Test-Path $uploadScript)) {
        Write-Host "ERROR: upload-plugin-v2.ps1 not found at: $uploadScript" -ForegroundColor Red
        exit 1
    }

    # Build config JSON from wp-plugin-config.json if it exists
    $wpConfigPath = Join-Path $ScriptDir "wp-plugins/scripts/wp-plugin-config.json"
    if (Test-Path $wpConfigPath) {
        $wpConfig = Get-Content $wpConfigPath -Raw | ConvertFrom-Json
        $wpConfig.pluginFolderPath = $pluginPath
        $jsonConfigStr = ($wpConfig | ConvertTo-Json -Compress)
        Write-Host "  Path:   $pluginPath" -ForegroundColor Gray
        Write-Host "  Site:   $($wpConfig.wordPressSiteURL)" -ForegroundColor Gray
        Write-Host ""
        $debugArgs = @()
        if ($debug) { $debugArgs += "-DebugMode" }
        & $uploadScript -JsonConfig $jsonConfigStr -Activate @debugArgs
    } else {
        Write-Host "ERROR: wp-plugin-config.json not found at: $wpConfigPath" -ForegroundColor Red
        Write-Host "Create it with site URL, username, and app password." -ForegroundColor Yellow
        exit 1
    }

    exit 0
}

# ── Helper: Clear existing ZIP files from wp-plugins/ ───────────────────
function Clear-PluginZips {
    $wpPluginsDir = Join-Path $ScriptDir "wp-plugins"
    if (-not (Test-Path $wpPluginsDir)) { return }

    $zips = Get-ChildItem $wpPluginsDir -Filter "*.zip" -File -ErrorAction SilentlyContinue
    if ($zips.Count -eq 0) {
        Write-Host "  No existing ZIP files found" -ForegroundColor DarkGray
        return
    }

    Write-Host "  Clearing $($zips.Count) existing ZIP file(s):" -ForegroundColor Yellow
    foreach ($z in $zips) {
        Remove-Item $z.FullName -Force
        Write-Host "    Removed: $($z.Name)" -ForegroundColor DarkGray
    }
    Write-Host ""
}




# ============================================================================
# QUPLOAD MODE: Upload plugin via QUpload API (-q / -qupload)
#   Uses upload-plugin-U-Q.ps1 with qupload-config.json credentials.
#   -q              -> Upload default plugin via QUpload API
#   -q -pp <path>   -> Upload specific plugin via QUpload API
# ============================================================================
if ($qupload) {
    Write-Host ""
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "  QUpload Mode (-q)" -ForegroundColor Cyan
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host ""

    # Determine plugin path: use -pp override or default from config
    $qPluginPath = ""
    if ($pluginpath -ne "") {
        $qPluginPath = $pluginpath
        if (-not [System.IO.Path]::IsPathRooted($qPluginPath)) {
            $qPluginPath = Join-Path $ScriptDir $qPluginPath
        }
        if (-not (Test-Path $qPluginPath)) {
            Write-Host "ERROR: Plugin folder not found: $qPluginPath" -ForegroundColor Red
            exit 1
        }
        Write-Host "  Using custom plugin path: $qPluginPath" -ForegroundColor Cyan
    } else {
        # Use defaultQUploader first, fall back to defaultUploader
        $defaultQUploader = $null
        if ($Config.wpPlugins -and $Config.wpPlugins.defaultQUploader) {
            $defaultQUploader = $Config.wpPlugins.defaultQUploader
        }
        if (-not $defaultQUploader -and $Config.wpPlugins -and $Config.wpPlugins.defaultUploader) {
            $defaultQUploader = $Config.wpPlugins.defaultUploader
        }
        if (-not $defaultQUploader -or -not $Config.wpPlugins.plugins.$defaultQUploader) {
            Write-Host "ERROR: No default QUploader configured in powershell.json (wpPlugins.defaultQUploader)" -ForegroundColor Red
            exit 1
        }

        $pluginCfg = $Config.wpPlugins.plugins.$defaultQUploader
        $qPluginPath = Resolve-RelativePath $pluginCfg.path

        if (-not (Test-Path $qPluginPath)) {
            Write-Host "ERROR: Plugin folder not found: $qPluginPath" -ForegroundColor Red
            exit 1
        }
        Write-Host "  Plugin: $defaultQUploader" -ForegroundColor Yellow
    }

    # Find the QUpload script
    $quploadScript = Join-Path $ScriptDir "wp-plugins/scripts/upload-plugin-U-Q.ps1"
    if (-not (Test-Path $quploadScript)) {
        Write-Host "ERROR: upload-plugin-U-Q.ps1 not found at: $quploadScript" -ForegroundColor Red
        exit 1
    }

    # Build config from qupload-config.json
    $qConfigPath = Join-Path $ScriptDir "wp-plugins/scripts/qupload-config.json"
    if (Test-Path $qConfigPath) {
        $qConfig = Get-Content $qConfigPath -Raw | ConvertFrom-Json
        $qConfig.pluginFolderPath = $qPluginPath
        $jsonConfigStr = ($qConfig | ConvertTo-Json -Compress)
        Write-Host "  Path:   $qPluginPath" -ForegroundColor Gray
        Write-Host "  Site:   $($qConfig.wordPressSiteURL)" -ForegroundColor Gray
        Write-Host ""
        & $quploadScript -jc $jsonConfigStr -a
    } else {
        Write-Host "ERROR: qupload-config.json not found at: $qConfigPath" -ForegroundColor Red
        Write-Host "Create it with pluginFolderPath, wordPressSiteURL, username, and appPassword." -ForegroundColor Yellow
        exit 1
    }

    exit 0
}

# ============================================================================
# INSTALL MODE: Install dependencies for both frontend and backend
# ============================================================================
if ($install) {
    $stepWatch = [System.Diagnostics.Stopwatch]::StartNew()
    Write-Host "[INSTALL] Installing/updating all dependencies..." -ForegroundColor Cyan
    Write-Host ""
    
    # Frontend: pnpm install
    # NOTE: In -r mode, -force cleaning happens later (Step 3) and would delete a fresh install.
    # We therefore defer the frontend install until after the force-clean step.
    if ($rebuild) {
        Write-Host "  [Frontend] Rebuild mode: deferring pnpm install until after force-clean..." -ForegroundColor Yellow
    } else {
        Write-Host "  [Frontend] Running pnpm install..." -ForegroundColor Yellow
        Push-Location $FrontendDir
        try {
            # Configure pnpm store first
            Configure-PnpmStore
            
            Invoke-Expression $EffectiveInstallCommand
            if ($LASTEXITCODE -ne 0) { throw "pnpm install failed" }
            $DidFrontendInstall = $true
            Write-Host "  ✓ Frontend dependencies installed" -ForegroundColor Green
        }
        finally {
            Pop-Location
        }
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
    
    if (-not $rebuild) {
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

    Write-Host "Continuing with rebuild (-r): will force-clean, then install frontend deps, then build/run..." -ForegroundColor Cyan
    Write-Host ""
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

            # Always clean pnpm-managed folders when forcing (even if not in config)
            # Remove both node_modules and pnpm PnP artifacts so the install check can't be fooled.
            foreach ($extraPath in @(
                "node_modules",
                ".pnpm",
                ".pnp.cjs",
                ".pnp.loader.mjs",
                ".pnp.data.json"
            )) {
                if (Test-Path $extraPath) {
                    Write-Host "  Removing: $extraPath..." -ForegroundColor Gray
                    Remove-Item -Recurse -Force $extraPath -ErrorAction SilentlyContinue
                }
            }
            
            # Clear pnpm cache if force mode
            if ($CheckPnpm) {
                Write-Host "  Clearing pnpm cache..." -ForegroundColor Gray
                pnpm store prune 2>&1 | Out-Null
            }

            # Clean backend runtime data (sessions, request-sessions, error logs)
            if ($DataDir) {
                $runtimePaths = @(
                    (Join-Path $DataDir "sessions"),
                    (Join-Path $DataDir "request-sessions"),
                    (Join-Path $DataDir "errors")
                )
                foreach ($rtPath in $runtimePaths) {
                    if (Test-Path $rtPath) {
                        Write-Host "  Removing: $rtPath..." -ForegroundColor Gray
                        Remove-Item -Recurse -Force $rtPath -ErrorAction SilentlyContinue
                    }
                }
                # Also clean standalone log files in data dir
                foreach ($logFile in @("log.txt", "error.log.txt")) {
                    $logPath = Join-Path $DataDir $logFile
                    if (Test-Path $logPath) {
                        Write-Host "  Removing: $logFile..." -ForegroundColor Gray
                        Remove-Item -Force $logPath -ErrorAction SilentlyContinue
                    }
                }
                Write-Host "  ✓ Backend runtime data cleaned" -ForegroundColor Magenta
            }
            
            Write-Host "  ✓ Clean complete" -ForegroundColor Magenta
        }
        
        # Check if install needed
        # Dependency presence depends on which pnpm linker is active.
        $depsPresent = if ($EffectiveNodeLinker -eq "pnp") { (Test-Path ".pnp.cjs") } else { (Test-Path "node_modules") }

        # -install / -rebuild always ensures deps exist before build
        $NeedsInstall = $install -or (-not $depsPresent)
        
        # Check for required modules (catches new deps after git pull)
        if (-not $NeedsInstall -and $EffectiveNodeLinker -ne "pnp" -and $RequiredModules.Count -gt 0) {
            foreach ($m in $RequiredModules) {
                $modulePath = Join-Path "node_modules" $m
                if (-not (Test-Path $modulePath)) {
                    $NeedsInstall = $true
                    Write-Host "  Missing module: $m - will reinstall" -ForegroundColor Gray
                    break
                }
            }
        }

        # Also install if -force was passed (clean build needs fresh deps)
        if ($NeedsInstall -or $force) {
            Write-Host "  Installing dependencies with pnpm..." -ForegroundColor Gray
            Invoke-Expression $EffectiveInstallCommand
            if ($LASTEXITCODE -ne 0) { throw "pnpm install failed" }
            $DidFrontendInstall = $true
        }
        
        # Build
        Write-Host "  Running build command: $BuildCommand" -ForegroundColor Gray

        # In PnP mode, ensure Node ESM can resolve packages during vite build
        $oldNodeOptions = $env:NODE_OPTIONS
        try {
            if ($EffectiveNodeLinker -eq "pnp") {
                Enable-PnpmPnpNodeOptions -ProjectDir (Get-Location)
            }
            Invoke-Expression $BuildCommand
            if ($LASTEXITCODE -ne 0) { throw "Build failed" }
        }
        finally {
            $env:NODE_OPTIONS = $oldNodeOptions
        }
        
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
