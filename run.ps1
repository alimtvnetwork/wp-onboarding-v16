# WP Plugin Publish - PowerShell Build & Run Script
# Version: 2.14.0
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
    [Alias('ua')][switch]$uploadall,
    [switch]$uas,
    [Alias('as')][switch]$allsites,
    [switch]$za,
    [Alias('zq')][switch]$zipqupload,
    [Alias('z')][switch]$zip,
    [Alias('t')][switch]$test,
    [Alias('h')][switch]$help,
    [Alias('v')][switch]$verbose,
    [Alias('d')][switch]$debug,
    [Alias('c')][switch]$clear,
    [Alias('pp')][string]$pluginpath = "",
    [string]$site = "",
    [Alias('xs')][string]$exclude = "",
    [Alias('ls','lr')][switch]$listsites,
    [switch]$sync,
    [Alias('cl')][switch]$clearlogs
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
# PATH RESOLUTION: Script location is the working directory
# ============================================================================
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
if ([string]::IsNullOrWhiteSpace($ScriptDir)) {
    $ScriptDir = Get-Location
}

# ============================================================================
# DOT-SOURCE MODULES (order matters: helpers first, then dependents)
# ============================================================================
$ModulesDir = Join-Path $ScriptDir "wp-plugins" "scripts" "modules"

. (Join-Path $ModulesDir "helpers.ps1")
. (Join-Path $ModulesDir "install.ps1")
. (Join-Path $ModulesDir "pnpm.ps1")
. (Join-Path $ModulesDir "firewall.ps1")
. (Join-Path $ModulesDir "git.ps1")
. (Join-Path $ModulesDir "plugin-helpers.ps1")
. (Join-Path $ModulesDir "zip-single.ps1")
. (Join-Path $ModulesDir "zip-parallel.ps1")
. (Join-Path $ModulesDir "upload-single.ps1")
. (Join-Path $ModulesDir "upload-parallel.ps1")
. (Join-Path $ModulesDir "summary-printer.ps1")
. (Join-Path $ModulesDir "mode-zip.ps1")
. (Join-Path $ModulesDir "mode-upload.ps1")
. (Join-Path $ModulesDir "mode-upload-all.ps1")
. (Join-Path $ModulesDir "mode-upload-all-sites.ps1")
. (Join-Path $ModulesDir "mode-upload-default-all-sites.ps1")
. (Join-Path $ModulesDir "mode-list-sites.ps1")
. (Join-Path $ModulesDir "mode-test.ps1")
. (Join-Path $ModulesDir "mode-clear-logs.ps1")

# ============================================================================
# TEST MODE: Run Go tests and exit early
# ============================================================================
if ($test) {
    Invoke-TestMode
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

# pnpm version-aware install behavior
$PnpmMajor = 0
$NodeMajor = 0
$EffectiveInstallCommand = $InstallCommand
$DidFrontendInstall = $false
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
    Write-Host "BUILD & RUN:" -ForegroundColor Yellow
    Write-Host "  -h,  -help          Show this help message and exit"
    Write-Host "  -b,  -buildonly     Build frontend only, don't start the backend server"
    Write-Host "  -s,  -skipbuild     Skip frontend build, only run the backend server"
    Write-Host "  -p,  -skippull      Skip git pull step"
    Write-Host "  -f,  -force         Clean build: remove caches, dependencies, databases"
    Write-Host "  -i,  -install       Install/update dependencies (frontend + backend)"
    Write-Host "  -r,  -rebuild       Complete clean reinstall (combines -f + -i)"
    Write-Host "  -fw, -openfirewall  (Admin) Add Windows Firewall inbound rules"
    Write-Host "  -t,  -test          Run Go backend tests and exit"
    Write-Host "  -v,  -verbose       Show detailed debug output"
    Write-Host ""
    Write-Host "UPLOAD:" -ForegroundColor Yellow
    Write-Host "  -u,  -upload        Upload default plugin via Riseup Asia Uploader API"
    Write-Host "  -u -site 'name'    Upload default plugin to a specific site (via QUpload API)"
    Write-Host "  -q,  -qupload       Upload default plugin via QUpload API"
    Write-Host "  -u -q               Upload Riseup Asia Uploader itself via QUpload API"
    Write-Host "  -ua, -uploadall     ZIP + upload ALL plugins (except QUpload) via QUpload API"
    Write-Host "  -ua -xs 'slug'      ZIP + upload ALL plugins EXCEPT the named one(s)"
    Write-Host "  -uas                Upload ALL plugins to ALL configured sites (parallel)"
    Write-Host "  -uas -sync          Upload ALL plugins to ALL sites SEQUENTIALLY"
    Write-Host "  -uas -site 'name'   Upload ALL plugins to a specific site by name"
    Write-Host "  -uas -xs 'name'     Upload ALL plugins to all sites EXCEPT the named one(s)"
    Write-Host "  -u -as              Upload DEFAULT plugin only to ALL configured sites (parallel)"
    Write-Host "  -u -as -sync        Upload DEFAULT plugin to ALL sites SEQUENTIALLY"
    Write-Host "  -u -as -site 'name' Upload DEFAULT plugin to a specific site"
    Write-Host "  -u -as -xs 'name'   Upload DEFAULT plugin to all sites EXCEPT the named one(s)"
    Write-Host "  -d,  -debug         Enable debug logging (shows endpoints, paths, responses)"
    Write-Host "  -pp, -pluginpath    Override plugin folder path (use with -u, -q, -z, -zq)"
    Write-Host "  -sync               Sequential mode for -uas (no background jobs)"
    Write-Host ""
    Write-Host "LOG MANAGEMENT:" -ForegroundColor Yellow
    Write-Host "  -cl, -clearlogs     Clear logs on ALL configured sites (both plugins)"
    Write-Host "  -cl -site 'name'    Clear logs on a specific site"
    Write-Host "  -cl -xs 'name'      Clear logs on all sites EXCEPT the named one(s)"
    Write-Host ""
    Write-Host "ZIP:" -ForegroundColor Yellow
    Write-Host "  -z,  -zip           ZIP default plugin (Riseup Asia). With -pp: specific plugin"
    Write-Host "  -za                 ZIP ALL plugins in wp-plugins/ with version numbers"
    Write-Host "  -zq, -zipqupload    ZIP QUpload plugin only"
    Write-Host "  -c,  -clear         (Legacy) Clear is now automatic before all ZIP operations"
    Write-Host ""
    Write-Host "INFO:" -ForegroundColor Yellow
    Write-Host "  -ls, -lr, -listsites  List all configured sites (powershell.json + config.json)"
    Write-Host ""
    Write-Host "EXAMPLES:" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "  Build & Run:" -ForegroundColor DarkGray
    Write-Host "    .\run.ps1              # Full build and run"
    Write-Host "    .\run.ps1 -r           # Complete clean reinstall and build"
    Write-Host "    .\run.ps1 -s           # Just start the backend (skip build)"
    Write-Host "    .\run.ps1 -b           # Build only, don't start server"
    Write-Host "    .\run.ps1 -i           # Install/update all dependencies"
    Write-Host "    .\run.ps1 -f           # Clean rebuild everything"
    Write-Host "    .\run.ps1 -p -f        # Clean build without git pull"
    Write-Host "    .\run.ps1 -t           # Run Go backend tests"
    Write-Host ""
    Write-Host "  Upload (single plugin):" -ForegroundColor DarkGray
    Write-Host "    .\run.ps1 -u           # Upload default plugin (Riseup Asia API)"
    Write-Host "    .\run.ps1 -q           # Upload default plugin (QUpload API)"
    Write-Host "    .\run.ps1 -u -q        # Upload Riseup Asia Uploader via QUpload"
    Write-Host "    .\run.ps1 -u -d        # Upload with debug logging"
    Write-Host "    .\run.ps1 -u -pp 'C:\path\to\plugin'  # Upload specific plugin"
    Write-Host "    .\run.ps1 -q -pp 'wp-plugins/qupload' # Upload specific via QUpload"
    Write-Host ""
    Write-Host "  Upload (all plugins):" -ForegroundColor DarkGray
    Write-Host "    .\run.ps1 -ua          # ZIP + upload all plugins via QUpload"
    Write-Host "    .\run.ps1 -ua -xs 'riseup-asia-uploader'  # Exclude specific plugin"
    Write-Host ""
    Write-Host "  Upload (multi-site):" -ForegroundColor DarkGray
    Write-Host "    .\run.ps1 -uas                     # Upload all plugins to all sites (parallel)"
    Write-Host "    .\run.ps1 -uas -sync               # Upload all plugins to all sites (sequential)"
    Write-Host "    .\run.ps1 -uas -site 'Test V1'     # Upload all plugins to specific site"
    Write-Host "    .\run.ps1 -uas -xs 'Test V1'       # Upload to all sites EXCEPT Test V1"
    Write-Host "    .\run.ps1 -uas -xs 'Test V1,Test V2'  # Exclude multiple sites"
    Write-Host ""
    Write-Host "  Upload (default plugin, multi-site):" -ForegroundColor DarkGray
    Write-Host "    .\run.ps1 -u -as                   # Upload default plugin to all sites (parallel)"
    Write-Host "    .\run.ps1 -u -as -sync             # Upload default plugin to all sites (sequential)"
    Write-Host "    .\run.ps1 -u -as -site 'Test V1'   # Upload default plugin to specific site"
    Write-Host "    .\run.ps1 -u -as -xs 'Test V1'     # Exclude specific site"
    Write-Host ""
    Write-Host "  Log management:" -ForegroundColor DarkGray
    Write-Host "    .\run.ps1 -cl                      # Clear logs on all sites (both plugins)"
    Write-Host "    .\run.ps1 -cl -site 'Test V1'      # Clear logs on specific site"
    Write-Host "    .\run.ps1 -cl -xs 'Test V1'        # Clear logs on all sites EXCEPT Test V1"
    Write-Host ""
    Write-Host "  ZIP only:" -ForegroundColor DarkGray
    Write-Host "    .\run.ps1 -z           # ZIP default plugin (Riseup Asia)"
    Write-Host "    .\run.ps1 -za          # ZIP all plugins in wp-plugins/"
    Write-Host "    .\run.ps1 -zq          # ZIP QUpload plugin"
    Write-Host "    .\run.ps1 -z -pp 'wp-plugins/qupload' # ZIP a specific plugin"
    Write-Host ""
    Write-Host "  Info:" -ForegroundColor DarkGray
    Write-Host "    .\run.ps1 -ls          # List all sites (deploy + backend)"
    Write-Host "    .\run.ps1 -lr          # Same as -ls"
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
# LIST SITES (early exit)
# ============================================================================
if ($listsites) {
    Invoke-ListSitesMode
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
# GIT PULL (runs before ALL modes including upload/ZIP early exits)
# ============================================================================
Invoke-GitPull

# ============================================================================
# STEP TRACKING
# ============================================================================
$StepTimes = @{}

# ============================================================================
# STEP 1: PREREQUISITES
# ============================================================================
$stepWatch = [System.Diagnostics.Stopwatch]::StartNew()
Write-Host "[2/5] Checking prerequisites..." -ForegroundColor Yellow

if ($CheckGo) {
    if (-not (Test-Command "go")) {
        Write-Host "  Go is not installed or not in PATH" -ForegroundColor Yellow
        Install-Go
    }
    $goVersion = (go version 2>&1) -replace 'go version ', ''
    Write-Host "  Go found: $goVersion" -ForegroundColor Green
}

if ($CheckNode) {
    if (-not (Test-Command "node")) {
        Write-Host "  Node.js is not installed or not in PATH" -ForegroundColor Yellow
        Install-NodeJS
    }
    $nodeVersion = node --version 2>&1
    Write-Host "  Node.js found: $nodeVersion" -ForegroundColor Green
    $NodeMajor = Get-NodeMajorVersion $nodeVersion
}

if ($CheckPnpm) {
    if (-not (Test-Command "pnpm")) {
        Write-Host "  pnpm is not installed" -ForegroundColor Yellow
        Install-Pnpm
    }
    $pnpmVersion = pnpm --version 2>&1
    Write-Host "  pnpm found: $pnpmVersion" -ForegroundColor Green

    $PnpmMajor = Get-PnpmMajorVersion $pnpmVersion
    $EffectiveInstallCommand = Get-EffectivePnpmInstallCommand $InstallCommand $PnpmMajor
    if ($verbose -and $EffectiveInstallCommand -ne $InstallCommand) {
        Write-Host "  pnpm v$PnpmMajor detected: enabling dependency build scripts during install" -ForegroundColor Gray
    }
    
    Configure-PnpmStore
}

$stepWatch.Stop()
$StepTimes["Prerequisites"] = $stepWatch.Elapsed
Write-Host "  $(Format-ElapsedTime $stepWatch)" -ForegroundColor DarkGray
Write-Host ""

# ============================================================================
# EARLY EXIT MODES (ZIP, Upload, etc.)
# ============================================================================
if ($zip) { Invoke-ZipMode }
if ($za) { Invoke-ZipAllMode }
if ($zipqupload) { Invoke-ZipQUploadMode }
if ($uas) { Invoke-UploadAllSitesMode }
if ($clearlogs) { Invoke-ClearLogsMode }
if ($upload -and $allsites) { Invoke-UploadDefaultAllSitesMode }
if ($uploadall) { Invoke-UploadAllMode }
if ($upload -and $qupload) { Invoke-UploadComboMode }
if ($upload) { Invoke-UploadMode }
if ($qupload) { Invoke-QUploadMode }

# ============================================================================
# INSTALL MODE
# ============================================================================
if ($install) {
    $stepWatch = [System.Diagnostics.Stopwatch]::StartNew()
    Write-Host "[INSTALL] Installing/updating all dependencies..." -ForegroundColor Cyan
    Write-Host ""
    
    if ($rebuild) {
        Write-Host "  [Frontend] Rebuild mode: deferring pnpm install until after force-clean..." -ForegroundColor Yellow
    } else {
        Write-Host "  [Frontend] Running pnpm install..." -ForegroundColor Yellow
        Push-Location $FrontendDir
        try {
            Configure-PnpmStore
            Invoke-Expression $EffectiveInstallCommand
            if ($LASTEXITCODE -ne 0) { throw "pnpm install failed" }
            $DidFrontendInstall = $true
            Write-Host "  Frontend dependencies installed" -ForegroundColor Green
        }
        finally {
            Pop-Location
        }
    }
    
    Write-Host ""
    Write-Host "  [Backend] Running go mod tidy && go mod download..." -ForegroundColor Yellow
    Push-Location $BackendDir
    try {
        go mod tidy
        if ($LASTEXITCODE -ne 0) { throw "go mod tidy failed" }
        go mod download
        if ($LASTEXITCODE -ne 0) { throw "go mod download failed" }
        Write-Host "  Backend dependencies installed" -ForegroundColor Green
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
        if ($force) {
            Write-Host "  FORCE MODE: Cleaning build artifacts..." -ForegroundColor Magenta
            
            foreach ($cleanPath in $CleanPaths) {
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
            
            if ($CheckPnpm) {
                Write-Host "  Clearing pnpm cache..." -ForegroundColor Gray
                pnpm store prune 2>&1 | Out-Null
            }

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
                foreach ($logFile in @("log.txt", "error.log.txt")) {
                    $logPath = Join-Path $DataDir $logFile
                    if (Test-Path $logPath) {
                        Write-Host "  Removing: $logFile..." -ForegroundColor Gray
                        Remove-Item -Force $logPath -ErrorAction SilentlyContinue
                    }
                }
                Write-Host "  Backend runtime data cleaned" -ForegroundColor Magenta
            }
            
            Write-Host "  Clean complete" -ForegroundColor Magenta
        }
        
        $depsPresent = if ($EffectiveNodeLinker -eq "pnp") { (Test-Path ".pnp.cjs") } else { (Test-Path "node_modules") }
        $NeedsInstall = $install -or (-not $depsPresent)
        
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

        if ($NeedsInstall -or $force) {
            Write-Host "  Installing dependencies with pnpm..." -ForegroundColor Gray
            Invoke-Expression $EffectiveInstallCommand
            if ($LASTEXITCODE -ne 0) { throw "pnpm install failed" }
            $DidFrontendInstall = $true
        }
        
        Write-Host "  Running build command: $BuildCommand" -ForegroundColor Gray

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
        
        Write-Host "  Frontend built successfully" -ForegroundColor Green
    }
    finally {
        Pop-Location
    }
    $stepWatch.Stop()
    $StepTimes["Frontend Build"] = $stepWatch.Elapsed
    Write-Host "  $(Format-ElapsedTime $stepWatch)" -ForegroundColor DarkGray
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
            $TargetParent = Split-Path -Parent $TargetDir
            if (-not (Test-Path $TargetParent)) {
                New-Item -ItemType Directory -Path $TargetParent -Force | Out-Null
            }
            
            if (Test-Path $TargetDir) {
                Remove-Item -Recurse -Force $TargetDir
            }
            Copy-Item -Recurse $SourceDist $TargetDir
            Write-Host "  Build files copied to: $TargetDir" -ForegroundColor Green
        }
    } else {
        Write-Host "[4/5] Skipping copy (no targetDir configured)" -ForegroundColor Gray
    }
    
    $stepWatch.Stop()
    $StepTimes["Copy Build"] = $stepWatch.Elapsed
    Write-Host "  $(Format-ElapsedTime $stepWatch)" -ForegroundColor DarkGray
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
    
    if ($DataDir -and -not (Test-Path $DataDir)) {
        New-Item -ItemType Directory -Path $DataDir -Force | Out-Null
        Write-Host "  Created data directory: $DataDir" -ForegroundColor Gray
    }

    if ($openfirewall) {
        Write-Host "  Configuring Windows Firewall rules..." -ForegroundColor Yellow
        Ensure-FirewallRules -PortList $Ports
    }
    
    $TotalStopwatch.Stop()

    # Read version info from version.json
    $versionJsonPath = Join-Path $ScriptDir "public" "version.json"
    $appVersion = ""
    $scriptVersionLabel = ""
    $wpPluginVersionLabel = ""
    $quploadVersionLabel = ""
    if (Test-Path $versionJsonPath) {
        try {
            $versionData = Get-Content $versionJsonPath -Raw | ConvertFrom-Json
            if ($versionData.version) { $appVersion = $versionData.version }
            if ($versionData.scriptVersion) { $scriptVersionLabel = $versionData.scriptVersion }
            if ($versionData.wpPluginVersion) { $wpPluginVersionLabel = $versionData.wpPluginVersion }
            if ($versionData.quploadVersion) { $quploadVersionLabel = $versionData.quploadVersion }
        } catch { }
    }

    Write-Host ""
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "  $ProjectName starting..." -ForegroundColor Cyan
    if ($appVersion) {
        Write-Host "  App Version:    v$appVersion" -ForegroundColor White
    }
    if ($wpPluginVersionLabel) {
        Write-Host "  WP Plugin:      v$wpPluginVersionLabel" -ForegroundColor White
    }
    if ($quploadVersionLabel) {
        Write-Host "  QUpload:        v$quploadVersionLabel" -ForegroundColor White
    }
    if ($scriptVersionLabel) {
        Write-Host "  Script:         v$scriptVersionLabel" -ForegroundColor White
    }
    Write-Host "  Open: http://localhost:$($Ports[0])" -ForegroundColor Cyan
    Write-Host "  Press Ctrl+C to stop" -ForegroundColor Cyan
    Write-Host "  Build time: $(Format-ElapsedTime $TotalStopwatch)" -ForegroundColor Cyan
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host ""
    
    Invoke-Expression $RunCommand
}
finally {
    Pop-Location
}
