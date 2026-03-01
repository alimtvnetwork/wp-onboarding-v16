# WordPress Plugin Uploader V4 — Imunify360 Session Bypass
# Version: 4.0.0
#
# Solves Imunify360 anti-bot challenges by establishing a persistent
# WebSession with cookies before making API calls. Delegates core
# upload logic to V2 once session is warmed.
#
# Usage:
#   .\upload-plugin-v4.ps1                          # Use wp-plugin-config.json
#   .\upload-plugin-v4.ps1 -DebugMode               # Verbose logging
#   .\upload-plugin-v4.ps1 -SkipGitPull              # Skip git step
#   .\upload-plugin-v4.ps1 -MaxChallengeRetries 5    # More retries for stubborn WAFs
#
# CHANGELOG:
#   4.0.0 (2026-02-20) — Initial: Imunify360 cookie-based challenge solving,
#                         persistent WebSession across all API calls,
#                         wp-login.php auth cookie warm-up, User-Agent spoofing,
#                         automatic fallback to V2 if session bypass unnecessary.

param(
    [Parameter(Mandatory=$false)]
    [string]$ConfigPath = "",

    [Parameter(Mandatory=$false)]
    [string]$JsonConfig = "",

    [Parameter(Mandatory=$false)]
    [switch]$SkipGitPull = $false,

    [Parameter(Mandatory=$false)]
    [switch]$DebugMode = $false,

    [Parameter(Mandatory=$false)]
    [int]$MaxChallengeRetries = 3,

    [Parameter(Mandatory=$false)]
    [int]$ChallengeDelaySec = 4,

    [Parameter(Mandatory=$false)]
    [switch]$Quiet = $false
)

$ErrorActionPreference = "Stop"

# --- Self-lint: detect parse errors before execution ---
$_lintScriptFile = $MyInvocation.MyCommand.Path
if ($_lintScriptFile -and (Test-Path $_lintScriptFile)) {
    $_lintErrors = $null
    [void][System.Management.Automation.Language.Parser]::ParseFile(
        $_lintScriptFile, [ref]$null, [ref]$_lintErrors
    )
    if ($_lintErrors -and $_lintErrors.Count -gt 0) {
        $scriptName = Split-Path $_lintScriptFile -Leaf
        Write-Host "LINT FAILED: $scriptName has parse errors" -ForegroundColor Red
        foreach ($e in $_lintErrors) {
            Write-Host "  Line $($e.Extent.StartLineNumber): $($e.Message)" -ForegroundColor Yellow
        }
        Write-Host "Fix: Ensure UTF-8 (no BOM) encoding with straight ASCII quotes." -ForegroundColor Cyan
        exit 1
    }
}

$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path

# ============================================================================
# HELPERS
# ============================================================================

function Write-Status {
    param([string]$Message, [string]$Color = "White", [switch]$NoNewline)
    if (-not $Quiet) {
        if ($NoNewline) { Write-Host $Message -ForegroundColor $Color -NoNewline }
        else { Write-Host $Message -ForegroundColor $Color }
    }
}

function Write-Debug-Log {
    param([string]$Message)
    if ($DebugMode) { Write-Host "      [DEBUG] $Message" -ForegroundColor Magenta }
}

function Write-Step {
    param([string]$Step, [string]$Text, [string]$Color = "Yellow")
    Write-Status "[$Step] $Text" -Color $Color
}

# ============================================================================
# IMUNIFY360 DETECTION PATTERNS
# ============================================================================

$script:ImunifyPatterns = @(
    'Access denied.*Imunify360',
    'bot.protection',
    'imunify360',
    '__im360',
    'One moment, please',
    'Checking your browser',
    'splashscreen\.js',
    'AntiBot',
    'captcha_splash',
    'Suspicious activity'
)

function Test-IsImunifyBlock {
    param([string]$Content)
    if ([string]::IsNullOrWhiteSpace($Content)) { return $false }
    foreach ($pattern in $script:ImunifyPatterns) {
        if ($Content -match $pattern) { return $true }
    }
    return $false
}

function Test-IsImunifyJsonBlock {
    param($JsonObject)
    if ($null -eq $JsonObject) { return $false }
    $msg = $JsonObject.message
    if ($msg -and ($msg -match 'Access denied' -or $msg -match 'Imunify360' -or $msg -match 'bot.protection')) {
        return $true
    }
    return $false
}

# ============================================================================
# CHALLENGE SOLVER — Parse and solve Imunify360 splash screen
# ============================================================================

function Resolve-ImunifyChallenge {
    param(
        [string]$SiteUrl,
        [Microsoft.PowerShell.Commands.WebRequestSession]$Session,
        [int]$MaxRetries,
        [int]$DelaySec
    )

    Write-Status ""
    Write-Status "  ╔══════════════════════════════════════════════════════════╗" -Color Cyan
    Write-Status "  ║     🛡️  Imunify360 Challenge Detected — Solving...     ║" -Color Cyan
    Write-Status "  ╚══════════════════════════════════════════════════════════╝" -Color Cyan
    Write-Status ""

    for ($attempt = 1; $attempt -le $MaxRetries; $attempt++) {
        Write-Status "      Attempt $attempt/$MaxRetries — Warming session..." -Color Yellow

        # Strategy 1: Hit the site root to trigger cookie placement
        try {
            $rootResponse = Invoke-WebRequest -Uri $SiteUrl -WebSession $Session `
                -UseBasicParsing -TimeoutSec 15 -ErrorAction SilentlyContinue -MaximumRedirection 5
            Write-Debug-Log "Root GET → Status: $($rootResponse.StatusCode)"
            Write-Debug-Log "Root GET → Cookies: $($Session.Cookies.Count)"

            # Check if challenge page returned — parse for form/POST
            $html = $rootResponse.Content
            if ($html -match 'action=["\x27]([^"\x27]+)["\x27]') {
                $formAction = $Matches[1]
                Write-Debug-Log "Found form action: $formAction"

                # Extract hidden fields
                $hiddenFields = @{}
                $regex = [regex]'<input[^>]+type=["\x27]hidden["\x27][^>]+name=["\x27]([^"\x27]+)["\x27][^>]+value=["\x27]([^"\x27]*)["\x27]'
                $fieldMatches = $regex.Matches($html)
                foreach ($m in $fieldMatches) {
                    $hiddenFields[$m.Groups[1].Value] = $m.Groups[2].Value
                    Write-Debug-Log "Hidden field: $($m.Groups[1].Value) = $($m.Groups[2].Value)"
                }

                if ($hiddenFields.Count -gt 0) {
                    # Resolve form action URL
                    $postUrl = if ($formAction.StartsWith('http')) { $formAction }
                               elseif ($formAction.StartsWith('/')) { "$SiteUrl$formAction" }
                               else { "$SiteUrl/$formAction" }

                    Write-Debug-Log "POSTing challenge form to: $postUrl"
                    try {
                        $challengeResponse = Invoke-WebRequest -Uri $postUrl -Method POST `
                            -WebSession $Session -Body $hiddenFields `
                            -UseBasicParsing -TimeoutSec 15 -MaximumRedirection 5 `
                            -ErrorAction SilentlyContinue
                        Write-Debug-Log "Challenge POST → Status: $($challengeResponse.StatusCode)"
                    } catch {
                        Write-Debug-Log "Challenge POST failed: $($_.Exception.Message)"
                    }
                }
            }
        } catch {
            Write-Debug-Log "Root GET failed: $($_.Exception.Message)"
        }

        # Strategy 2: Hit wp-login.php to establish WordPress auth cookies
        try {
            $loginPageUrl = "$SiteUrl/wp-login.php"
            Write-Debug-Log "Hitting wp-login.php for auth cookies..."
            $loginResponse = Invoke-WebRequest -Uri $loginPageUrl -WebSession $Session `
                -UseBasicParsing -TimeoutSec 15 -ErrorAction SilentlyContinue -MaximumRedirection 5
            Write-Debug-Log "wp-login.php → Status: $($loginResponse.StatusCode)"
        } catch {
            Write-Debug-Log "wp-login.php failed: $($_.Exception.Message)"
        }

        # Strategy 3: Hit wp-cron.php (often whitelisted by Imunify360)
        try {
            $cronUrl = "$SiteUrl/wp-cron.php?doing_wp_cron=1"
            Write-Debug-Log "Hitting wp-cron.php (often whitelisted)..."
            $cronResponse = Invoke-WebRequest -Uri $cronUrl -WebSession $Session `
                -UseBasicParsing -TimeoutSec 10 -ErrorAction SilentlyContinue
            Write-Debug-Log "wp-cron.php → Status: $($cronResponse.StatusCode)"
        } catch {
            Write-Debug-Log "wp-cron.php failed (non-fatal): $($_.Exception.Message)"
        }

        # Verify: Try a lightweight API call with the session
        try {
            $testUrl = "$SiteUrl/wp-json/"
            Write-Debug-Log "Testing session with wp-json root..."
            $testResponse = Invoke-WebRequest -Uri $testUrl -WebSession $Session `
                -UseBasicParsing -TimeoutSec 15 -ErrorAction Stop
            $testBody = $testResponse.Content

            if (-not (Test-IsImunifyBlock $testBody)) {
                $testJson = $testBody | ConvertFrom-Json -ErrorAction SilentlyContinue
                if ($testJson -and (-not (Test-IsImunifyJsonBlock $testJson)) -and ($testJson.name -or $testJson.namespaces)) {
                    Write-Status "      ✓ Session established — challenge bypassed!" -Color Green
                    $cookieCount = $Session.Cookies.GetCookies($SiteUrl).Count
                    Write-Debug-Log "Active cookies: $cookieCount"
                    foreach ($cookie in $Session.Cookies.GetCookies($SiteUrl)) {
                        Write-Debug-Log "  Cookie: $($cookie.Name) = $($cookie.Value.Substring(0, [Math]::Min(20, $cookie.Value.Length)))..."
                    }
                    return $true
                }
            }

            Write-Status "      ⚠ Session not yet accepted (attempt $attempt)" -Color Yellow
        } catch {
            Write-Status "      ⚠ Test request failed: $($_.Exception.Message)" -Color Yellow
        }

        if ($attempt -lt $MaxRetries) {
            Write-Status "      Waiting ${DelaySec}s before retry..." -Color Gray
            Start-Sleep -Seconds $DelaySec
            # Increase delay for each attempt (exponential backoff)
            $DelaySec = [Math]::Min($DelaySec * 2, 30)
        }
    }

    Write-Status "      ✗ Could not solve Imunify360 challenge after $MaxRetries attempts" -Color Red
    return $false
}

# ============================================================================
# SESSION-AWARE REST REQUEST
# ============================================================================

function Invoke-SessionRestRequest {
    param(
        [string]$Uri,
        [string]$Method = "Get",
        [hashtable]$Headers = @{},
        [string]$Body = "",
        [string]$ContentType = "",
        [int]$TimeoutSec = 30,
        [int]$MaxRetries = 3,
        [int]$RetryDelaySec = 5,
        [string]$Label = "Request",
        [Microsoft.PowerShell.Commands.WebRequestSession]$Session
    )

    if (-not $Headers.ContainsKey("Accept")) {
        $Headers["Accept"] = "application/json"
    }

    for ($attempt = 1; $attempt -le $MaxRetries; $attempt++) {
        Write-Debug-Log "$Label → Attempt $attempt/$MaxRetries ($Method $Uri)"

        try {
            $reqParams = @{
                Uri             = $Uri
                Method          = $Method
                Headers         = $Headers
                TimeoutSec      = $TimeoutSec
                UseBasicParsing = $true
                ErrorAction     = "Stop"
            }

            if ($Session) { $reqParams.WebSession = $Session }
            if ($Body -ne "") {
                $reqParams.Body = $Body
                $reqParams.ContentType = $ContentType
            }

            $webResponse = Invoke-WebRequest @reqParams
            $rawBody = $webResponse.Content
            $statusCode = $webResponse.StatusCode

            Write-Debug-Log "$Label → Status: $statusCode"
            Write-Debug-Log "$Label → Body (first 500): $($rawBody.Substring(0, [Math]::Min(500, $rawBody.Length)))"

            # Detect Imunify360 blocks in response
            if (Test-IsImunifyBlock $rawBody) {
                Write-Status "      ⚠ Imunify360 block in response ($Label)" -Color Yellow
                if ($attempt -lt $MaxRetries) {
                    Write-Status "        Retrying in ${RetryDelaySec}s..." -Color Yellow
                    Start-Sleep -Seconds $RetryDelaySec
                    continue
                }
                return $null
            }

            # HTML challenge pages
            if ($rawBody -match "One moment, please" -or $rawBody -match "Checking your browser") {
                Write-Status "      ⚠ HTML challenge page ($Label)" -Color Yellow
                if ($attempt -lt $MaxRetries) {
                    Start-Sleep -Seconds $RetryDelaySec
                    continue
                }
                return $null
            }

            # Non-JSON HTML response
            $respCT = $webResponse.Headers["Content-Type"]
            if ($respCT -and $respCT -match "text/html") {
                Write-Status "      ⚠ HTML response (not JSON) — Status: $statusCode ($Label)" -Color Yellow
                return $null
            }

            # Parse JSON
            try {
                $parsed = $rawBody | ConvertFrom-Json
                # Check for JSON-level Imunify block
                if (Test-IsImunifyJsonBlock $parsed) {
                    Write-Status "      ⚠ Imunify360 JSON block: $($parsed.message)" -Color Yellow
                    if ($attempt -lt $MaxRetries) {
                        Start-Sleep -Seconds $RetryDelaySec
                        continue
                    }
                    return $null
                }
                return $parsed
            } catch {
                Write-Status "      ⚠ Response is not valid JSON ($Label)" -Color Yellow
                return $null
            }

        } catch {
            Write-Debug-Log "$Label → Exception: $($_.Exception.Message)"
            if ($attempt -lt $MaxRetries) {
                Write-Status "      ⚠ $Label failed, retrying in ${RetryDelaySec}s..." -Color Yellow
                Start-Sleep -Seconds $RetryDelaySec
            } else {
                throw $_
            }
        }
    }
    return $null
}

# ============================================================================
# LOAD CONFIG
# ============================================================================

$config = $null

if ($JsonConfig -ne "") {
    $config = $JsonConfig | ConvertFrom-Json
} elseif ($ConfigPath -ne "" -and (Test-Path $ConfigPath)) {
    $config = Get-Content $ConfigPath -Raw | ConvertFrom-Json
} else {
    $defaultConfig = Join-Path $ScriptDir "wp-plugin-config.json"
    if (Test-Path $defaultConfig) {
        $config = Get-Content $defaultConfig -Raw | ConvertFrom-Json
    }
}

if (-not $config) {
    Write-Host "ERROR: No config found. Provide -ConfigPath or -JsonConfig, or create wp-plugin-config.json" -ForegroundColor Red
    exit 1
}

$PluginFolderPath = $config.pluginFolderPath
$WordPressSiteURL = $config.wordPressSiteURL.TrimEnd('/')
$Username         = $config.username
$AppPassword      = $config.appPassword
$ActivateAfterInstall = if ($null -ne $config.activateAfterInstall) { $config.activateAfterInstall } else { $true }
$PluginSlug       = if ($config.pluginSlug) { $config.pluginSlug } else { Split-Path $PluginFolderPath -Leaf }
$AdminPageSlug    = if ($config.adminPageSlug) { $config.adminPageSlug } else { "" }

# ============================================================================
# BANNER
# ============================================================================

Write-Status ""
Write-Status "═══════════════════════════════════════════════════" -Color Cyan
Write-Status "  WordPress Plugin Uploader V4" -Color Cyan
Write-Status "  Imunify360 Session Bypass → Upload → Verify" -Color Cyan
Write-Status "═══════════════════════════════════════════════════" -Color Cyan
Write-Status ""
Write-Status "  Site:    $WordPressSiteURL" -Color Gray
Write-Status "  Plugin:  $PluginSlug" -Color Gray
Write-Status "  User:    $Username" -Color Gray
Write-Status ""

# ============================================================================
# STEP 1: GIT PULL (reuse v2 logic)
# ============================================================================
if (-not $SkipGitPull) {
    Write-Step "1/7" "Git pull..."
    $gitDir = $PluginFolderPath
    $foundGit = $false
    for ($i = 0; $i -lt 10; $i++) {
        if (Test-Path (Join-Path $gitDir ".git")) { $foundGit = $true; break }
        $parent = Split-Path $gitDir -Parent
        if ($parent -eq $gitDir) { break }
        $gitDir = $parent
    }
    if ($foundGit) {
        Push-Location $gitDir
        try {
            $branch = (git rev-parse --abbrev-ref HEAD 2>&1).Trim()
            Write-Status "      Branch: $branch" -Color Gray
            git pull 2>&1 | Out-Host
            Write-Status "      ✓ Git pull complete" -Color Green
        } finally { Pop-Location }
    } else {
        Write-Status "      Skipping (no .git found)" -Color Gray
    }
} else {
    Write-Step "1/7" "Skipping git pull" "Gray"
}
Write-Status ""

# ============================================================================
# STEP 2: READ LOCAL VERSION
# ============================================================================
Write-Step "2/7" "Reading local version..."

$LocalVersion = "unknown"

# Priority 1: PluginConfigType enum
$enumFile = Join-Path $PluginFolderPath "includes/Enums/PluginConfigType.php"
if (Test-Path $enumFile) {
    $enumContent = Get-Content $enumFile -Raw
    if ($enumContent -match "case\s+Version\s*=\s*'([0-9]+\.[0-9]+\.[0-9]+)'") {
        $LocalVersion = $Matches[1]
    }
}

# Priority 2: Plugin file header
if ($LocalVersion -eq "unknown") {
    $mainFile = Get-ChildItem $PluginFolderPath -Filter "*.php" | Where-Object {
        (Get-Content $_.FullName -Head 5) -match 'Plugin Name:'
    } | Select-Object -First 1
    if ($mainFile) {
        $content = Get-Content $mainFile.FullName -Raw
        if ($content -match "Version:\s*([0-9]+\.[0-9]+\.[0-9]+)") {
            $LocalVersion = $Matches[1]
        }
    }
}

Write-Status "      Local Version: $LocalVersion" -Color White
Write-Status ""

# ============================================================================
# STEP 3: ESTABLISH WEB SESSION (Imunify360 bypass)
# ============================================================================
Write-Step "3/7" "Establishing web session..."

$webSession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$webSession.UserAgent = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36"

$CleanAppPassword = $AppPassword -replace '\s', ''
$base64Auth = [Convert]::ToBase64String([Text.Encoding]::ASCII.GetBytes("${Username}:${CleanAppPassword}"))
$authHeaders = @{
    "Authorization" = "Basic $base64Auth"
    "Accept"        = "application/json"
}

# First, test if we even need the challenge bypass
$needsBypass = $false
try {
    $probeUrl = "$WordPressSiteURL/wp-json/"
    Write-Debug-Log "Probing REST API without session..."
    $probeResponse = Invoke-WebRequest -Uri $probeUrl -Headers $authHeaders `
        -UseBasicParsing -TimeoutSec 15 -SessionVariable probeSession -ErrorAction Stop

    if (Test-IsImunifyBlock $probeResponse.Content) {
        $needsBypass = $true
        Write-Status "      ⚠ Imunify360 detected — starting challenge bypass" -Color Yellow
    } else {
        try {
            $probeJson = $probeResponse.Content | ConvertFrom-Json
            if (Test-IsImunifyJsonBlock $probeJson) {
                $needsBypass = $true
                Write-Status "      ⚠ Imunify360 JSON block detected — starting challenge bypass" -Color Yellow
            } else {
                Write-Status "      ✓ REST API accessible — no Imunify360 block" -Color Green
                $webSession = $probeSession
            }
        } catch {
            # Not JSON — might be HTML challenge
            $needsBypass = $true
        }
    }
} catch {
    $needsBypass = $true
    Write-Debug-Log "Probe failed: $($_.Exception.Message)"
    Write-Status "      ⚠ Initial probe failed — attempting session bypass" -Color Yellow
}

if ($needsBypass) {
    $solved = Resolve-ImunifyChallenge -SiteUrl $WordPressSiteURL -Session $webSession `
        -MaxRetries $MaxChallengeRetries -DelaySec $ChallengeDelaySec

    if (-not $solved) {
        Write-Status ""
        Write-Host "  ╔══════════════════════════════════════════════════════════╗" -ForegroundColor Red
        Write-Host "  ║  ⚠  IMUNIFY360 CHALLENGE COULD NOT BE SOLVED  ⚠       ║" -ForegroundColor Red
        Write-Host "  ╠══════════════════════════════════════════════════════════╣" -ForegroundColor Red
        Write-Host "  ║  The server's bot protection blocked all attempts.     ║" -ForegroundColor Yellow
        Write-Host "  ║                                                        ║" -ForegroundColor Yellow
        Write-Host "  ║  Options:                                              ║" -ForegroundColor Yellow
        Write-Host "  ║  1. Whitelist your IP in Imunify360                    ║" -ForegroundColor White
        Write-Host "  ║     cPanel → Security → Imunify360 → White List        ║" -ForegroundColor White
        Write-Host "  ║  2. Disable Anti-Bot splash screen temporarily         ║" -ForegroundColor White
        Write-Host "  ║  3. Use cPanel File Manager to upload manually         ║" -ForegroundColor White
        Write-Host "  ║                                                        ║" -ForegroundColor Yellow
        Write-Host "  ║  Falling back to V2 (may also be blocked)...           ║" -ForegroundColor Gray
        Write-Host "  ╚══════════════════════════════════════════════════════════╝" -ForegroundColor Red
        Write-Status ""

        # Fall back to V2 as last resort
        $v2Script = Join-Path $ScriptDir "upload-plugin-v2.ps1"
        if (Test-Path $v2Script) {
            Write-Status "      Delegating to upload-plugin-v2.ps1..." -Color Yellow
            $v2Args = @()
            if ($JsonConfig -ne "") { $v2Args += @("-JsonConfig", $JsonConfig) }
            elseif ($ConfigPath -ne "") { $v2Args += @("-ConfigPath", $ConfigPath) }
            if ($SkipGitPull) { $v2Args += "-SkipGitPull" }
            if ($DebugMode) { $v2Args += "-DebugMode" }
            & $v2Script @v2Args
            exit $LASTEXITCODE
        }
        exit 1
    }
}

Write-Status ""

# ============================================================================
# STEP 4: GET REMOTE VERSION (with session)
# ============================================================================
Write-Step "4/7" "Checking remote version..."

$RemoteVersion = "not installed"
$activeNamespace = $null

$apiNamespaces = @(
    @{ name = "riseup-asia-uploader/v1"; display = "Riseup Asia Uploader" },
    @{ name = "riseup-uploader/v1"; display = "Riseup Uploader (Legacy)" }
)

foreach ($ns in $apiNamespaces) {
    $statusUrl = "$WordPressSiteURL/wp-json/$($ns.name)/status"
    Write-Debug-Log "Trying: $statusUrl"

    try {
        $statusResponse = Invoke-SessionRestRequest -Uri $statusUrl -Method "Get" `
            -Headers $authHeaders -Session $webSession -TimeoutSec 15 `
            -Label "Status ($($ns.display))" -MaxRetries 3 -RetryDelaySec 5

        if ($null -eq $statusResponse) { continue }

        # Unwrap envelope
        $statusData = $statusResponse
        if ($statusResponse.Results -and $statusResponse.Results.Count -gt 0) {
            $statusData = $statusResponse.Results[0]
        } elseif ($statusResponse.Results -is [PSCustomObject]) {
            $statusData = $statusResponse.Results
        }

        $detectedVersion = if ($statusData.Version) { $statusData.Version }
                          elseif ($statusData.version) { $statusData.version }
                          else { $null }

        if ($detectedVersion) {
            $RemoteVersion = $detectedVersion
            $activeNamespace = $ns.name
            Write-Status "      ✓ $($ns.display) is active — v$detectedVersion" -Color Green
            break
        } else {
            Write-Status "      ⚠ $($ns.display) responded but no version detected" -Color Yellow
            Write-Debug-Log "statusData keys: $($statusData.PSObject.Properties.Name -join ', ')"
        }
    } catch {
        Write-Debug-Log "Namespace $($ns.name) failed: $($_.Exception.Message)"
    }
}

Write-Status "      Remote Version: $RemoteVersion" -Color White

# Version comparison
$VersionAction = "install"
if ($RemoteVersion -ne "not installed" -and $RemoteVersion -ne "unknown") {
    $localParts = $LocalVersion -split '\.' | ForEach-Object { [int]$_ }
    $remoteParts = $RemoteVersion -split '\.' | ForEach-Object { [int]$_ }
    $maxLen = [Math]::Max($localParts.Count, $remoteParts.Count)
    $comparison = 0
    for ($i = 0; $i -lt $maxLen; $i++) {
        $l = if ($i -lt $localParts.Count) { $localParts[$i] } else { 0 }
        $r = if ($i -lt $remoteParts.Count) { $remoteParts[$i] } else { 0 }
        if ($l -gt $r) { $comparison = 1; break }
        if ($l -lt $r) { $comparison = -1; break }
    }
    if ($comparison -gt 0) { $VersionAction = "upgrade"; Write-Status "      ▲ UPGRADE: $RemoteVersion → $LocalVersion" -Color Green }
    elseif ($comparison -lt 0) { $VersionAction = "downgrade"; Write-Status "      ▼ DOWNGRADE: $RemoteVersion → $LocalVersion" -Color Red }
    else { $VersionAction = "reinstall"; Write-Status "      ═ SAME VERSION (reinstall)" -Color Yellow }
} else {
    Write-Status "      ★ FRESH INSTALL: $LocalVersion" -Color Cyan
}

Write-Status ""

# ============================================================================
# STEP 5: CREATE ZIP
# ============================================================================
Write-Step "5/7" "Creating ZIP..."

$folderName = Split-Path $PluginFolderPath -Leaf
$cacheDir = Join-Path $env:TEMP "RiseupUploader\$LocalVersion"
if (-not (Test-Path $cacheDir)) {
    New-Item -ItemType Directory -Path $cacheDir -Force | Out-Null
}
$OutputZipPath = Join-Path $cacheDir "$folderName-$(Get-Date -Format 'yyyyMMddHHmmss').zip"

try {
    $tempDir = Join-Path $env:TEMP "wp-plugin-upload-$(Get-Random)"
    $pluginTempDir = Join-Path $tempDir $folderName
    New-Item -ItemType Directory -Path $pluginTempDir -Force | Out-Null

    $allFiles = Get-ChildItem -Path $PluginFolderPath -Recurse -File
    foreach ($file in $allFiles) {
        $relativePath = $file.FullName.Substring($PluginFolderPath.Length)
        $destPath = Join-Path $pluginTempDir $relativePath
        $destDir = Split-Path $destPath -Parent
        if (-not (Test-Path $destDir)) {
            New-Item -ItemType Directory -Path $destDir -Force | Out-Null
        }
        Copy-Item -Path $file.FullName -Destination $destPath
    }

    Add-Type -AssemblyName System.IO.Compression.FileSystem
    [System.IO.Compression.ZipFile]::CreateFromDirectory(
        $pluginTempDir, $OutputZipPath,
        [System.IO.Compression.CompressionLevel]::SmallestSize, $true
    )
    Remove-Item $tempDir -Recurse -Force

    $zipSizeKB = [math]::Round((Get-Item $OutputZipPath).Length / 1KB, 2)
    Write-Status "      ✓ ZIP: $zipSizeKB KB ($($allFiles.Count) files)" -Color Green

    # Deduplicate against cache
    $newHash = (Get-FileHash $OutputZipPath -Algorithm SHA256).Hash
    $cachedZips = Get-ChildItem $cacheDir -Filter "*.zip" | Where-Object { $_.FullName -ne $OutputZipPath } | Sort-Object LastWriteTime -Descending
    if ($cachedZips.Count -gt 0) {
        $cachedHash = (Get-FileHash $cachedZips[0].FullName -Algorithm SHA256).Hash
        if ($newHash -eq $cachedHash -and $RemoteVersion -eq $LocalVersion) {
            Write-Status "      ✓ Hash matches — already deployed. Skipping upload." -Color Cyan
            Remove-Item $OutputZipPath -Force
            Write-Status "" 
            Write-Status "Done! (no upload needed)" -Color Green
            exit 0
        }
    }

    # Prune old cache
    $allCachedZips = Get-ChildItem $cacheDir -Filter "*.zip" | Sort-Object LastWriteTime -Descending
    if ($allCachedZips.Count -gt 2) {
        $allCachedZips | Select-Object -Skip 2 | ForEach-Object { Remove-Item $_.FullName -Force }
    }
} catch {
    Write-Host "      ERROR creating ZIP: $_" -ForegroundColor Red
    exit 1
}

Write-Status ""

# ============================================================================
# STEP 6: UPLOAD (with session cookies)
# ============================================================================
Write-Step "6/7" "Uploading plugin..."

if (-not $activeNamespace) { $activeNamespace = "riseup-asia-uploader/v1" }
$uploadUrl = "$WordPressSiteURL/wp-json/$activeNamespace/upload"

$machineName = $env:COMPUTERNAME
$fileBytes = [System.IO.File]::ReadAllBytes($OutputZipPath)
$base64Data = [Convert]::ToBase64String($fileBytes)

$uploadBody = @{
    plugin_zip     = $base64Data
    slug           = $PluginSlug
    activate       = $ActivateAfterInstall
    upload_source  = "upload_script_v4"
    plugin_version = $LocalVersion
    machine_name   = $machineName
} | ConvertTo-Json

$bodySizeKB = [math]::Round($uploadBody.Length / 1KB, 1)
Write-Status "      POST $uploadUrl" -Color Gray
Write-Status "      Payload: $bodySizeKB KB (base64 ZIP + metadata)" -Color Gray
Write-Status "      Machine: $machineName" -Color Gray

$uploadHeaders = @{
    "Authorization"           = "Basic $base64Auth"
    "Accept"                  = "application/json"
    "X-Riseup-Source-Machine" = $machineName
    "X-Riseup-Plugin-Version" = $LocalVersion
}

$uploadSuccess = $false

try {
    $response = Invoke-SessionRestRequest -Uri $uploadUrl -Method "Post" `
        -Headers $uploadHeaders -Body $uploadBody -ContentType "application/json" `
        -Session $webSession -TimeoutSec 300 -Label "Upload" `
        -MaxRetries 3 -RetryDelaySec 8

    if ($null -eq $response) {
        throw "Upload failed — server returned non-JSON response after all retries"
    }

    $uploadSuccess = $true

    # Unwrap envelope
    $resultData = $response
    if ($response.Results -and $response.Results.Count -gt 0) {
        $resultData = $response.Results[0]
    }

    $responseVersion = if ($resultData.plugin_version) { $resultData.plugin_version }
                      elseif ($resultData.version) { $resultData.version }
                      else { "unknown" }
    $pActivated = if ($null -ne $resultData.activated) { $resultData.activated } else { "N/A" }
    $isSelfUpdate = ($PluginSlug -eq "riseup-asia-uploader")

    Write-Status ""
    Write-Status "  ╔══════════════════════════════════════════════════════════╗" -Color Green
    Write-Status "  ║  ✓  UPLOAD SUCCESSFUL                                  ║" -Color Green
    Write-Status "  ╚══════════════════════════════════════════════════════════╝" -Color Green
    Write-Status ""
    Write-Status "  Plugin:    $PluginSlug" -Color White
    Write-Status "  Uploaded:  v$LocalVersion" -Color White
    Write-Status "  Response:  v$responseVersion$(if ($isSelfUpdate -and $responseVersion -ne $LocalVersion) { ' (stale — self-update)' } else { '' })" -Color $(if ($responseVersion -eq $LocalVersion) { "Green" } else { "Yellow" })
    Write-Status "  Action:    $VersionAction" -Color White
    Write-Status "  Activated: $pActivated" -Color White

} catch {
    $errorMessage = $_.Exception.Message
    Write-Host ""
    Write-Host "  ⚠ Upload failed: $errorMessage" -ForegroundColor Red
    Write-Host "  Endpoint: $uploadUrl" -ForegroundColor Gray
    Write-Host ""

    # Fall back to V2
    Write-Host "  Falling back to upload-plugin-v2.ps1..." -ForegroundColor Yellow
    $v2Script = Join-Path $ScriptDir "upload-plugin-v2.ps1"
    if (Test-Path $v2Script) {
        $v2Args = @("-SkipGitPull")  # Already pulled
        if ($JsonConfig -ne "") { $v2Args += @("-JsonConfig", $JsonConfig) }
        elseif ($ConfigPath -ne "") { $v2Args += @("-ConfigPath", $ConfigPath) }
        if ($DebugMode) { $v2Args += "-DebugMode" }
        & $v2Script @v2Args
        exit $LASTEXITCODE
    }
    exit 1
}

Write-Status ""

# ============================================================================
# STEP 7: POST-UPLOAD VERIFICATION (with session)
# ============================================================================
Write-Step "7/7" "Verifying deployment..."

$isSelfUpdate = ($PluginSlug -eq "riseup-asia-uploader")

# OPcache reset for self-updates
if ($isSelfUpdate -and $responseVersion -ne $LocalVersion) {
    $opcacheUrl = "$WordPressSiteURL/wp-json/$activeNamespace/opcache-reset"
    Write-Status "      POST $opcacheUrl (OPcache flush)" -Color Gray
    try {
        $opcacheResponse = Invoke-SessionRestRequest -Uri $opcacheUrl -Method "Post" `
            -Headers $authHeaders -Body "{}" -ContentType "application/json" `
            -Session $webSession -TimeoutSec 15 -Label "OPcache reset" -MaxRetries 2
        if ($null -ne $opcacheResponse) {
            $opcacheData = $opcacheResponse
            if ($opcacheResponse.Results -and $opcacheResponse.Results.Count -gt 0) {
                $opcacheData = $opcacheResponse.Results[0]
            }
            if ($opcacheData.success -eq $true) {
                Write-Status "      ✓ OPcache reset successful" -Color Green
            }
        }
    } catch {
        Write-Status "      ⚠ OPcache reset failed (expected on first upgrade)" -Color Yellow
    }
    Start-Sleep -Seconds 2
}

# Verify version
$verifyUrl = "$WordPressSiteURL/wp-json/$activeNamespace/status"
Write-Status "      GET $verifyUrl" -Color Gray

try {
    $verifyResponse = Invoke-SessionRestRequest -Uri $verifyUrl -Method "Get" `
        -Headers $authHeaders -Session $webSession -TimeoutSec 15 `
        -Label "Verify" -MaxRetries 3 -RetryDelaySec 5

    if ($null -ne $verifyResponse) {
        $verifyData = $verifyResponse
        if ($verifyResponse.Results -and $verifyResponse.Results.Count -gt 0) {
            $verifyData = $verifyResponse.Results[0]
        }
        $deployedVersion = if ($verifyData.Version) { $verifyData.Version }
                          elseif ($verifyData.version) { $verifyData.version }
                          else { "unknown" }

        if ($deployedVersion -eq $LocalVersion) {
            Write-Status "      ✓ VERIFIED: Server running v$deployedVersion" -Color Green
        } elseif ($deployedVersion -ne "unknown") {
            Write-Status "      ⚠ Server reports v$deployedVersion (expected v$LocalVersion)" -Color Yellow
            if ($isSelfUpdate) {
                Write-Status "        OPcache TTL will expire shortly." -Color DarkGray
            }
        } else {
            Write-Status "      ⚠ Could not determine deployed version" -Color Yellow
        }
    } else {
        Write-Status "      ⚠ Verification blocked — check WP admin manually" -Color Yellow
    }
} catch {
    Write-Status "      ⚠ Verification failed: $($_.Exception.Message)" -Color Yellow
}

# Open admin page
if ($AdminPageSlug -ne "") {
    $adminUrl = "$WordPressSiteURL/wp-admin/admin.php?page=$AdminPageSlug"
    Write-Status ""
    Write-Status "  🌐 Admin: $adminUrl" -Color Cyan
    try { Start-Process $adminUrl } catch {}
}

Write-Status ""
Write-Status "Done!" -Color Cyan
Write-Status ""
