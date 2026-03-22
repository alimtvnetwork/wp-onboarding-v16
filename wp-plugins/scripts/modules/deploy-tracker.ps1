# Module: deploy-tracker.ps1
# Tracks per-plugin deploy state using .deployed/ JSON files.
# Compares git SHA to determine if PHP files changed since last deploy.
# Dot-sourced by run.ps1.

function Get-DeployedDir {
    return Join-Path $ScriptDir ".deployed"
}

function Get-PluginLatestPath {
    param([string]$PluginSlug)
    return Join-Path (Get-DeployedDir) "$PluginSlug-latest.json"
}

function Get-PluginVersionsPath {
    param([string]$PluginSlug)
    return Join-Path (Get-DeployedDir) "$PluginSlug-versions.json"
}

function Get-LatestPath {
    return Join-Path (Get-DeployedDir) "latest.json"
}

# Reads the last deployed SHA for a plugin. Returns empty string if not tracked.
function Get-LastDeployedSha {
    param([string]$PluginSlug)

    $path = Get-PluginLatestPath $PluginSlug
    if (-not (Test-Path $path)) { return "" }

    try {
        $data = Get-Content $path -Raw | ConvertFrom-Json
        return $data.lastCommitSha
    } catch {
        return ""
    }
}

# Checks if a plugin's PHP files changed since its last deploy using git diff.
# Returns $true if changes detected (or if state is unknown), $false if no changes.
function Test-PluginPhpChanged {
    param([string]$PluginSlug, [string]$PluginPath)

    $lastSha = Get-LastDeployedSha $PluginSlug
    if (-not $lastSha) {
        Write-Host "  [$PluginSlug] No deploy history — needs deploy" -ForegroundColor Yellow
        return $true
    }

    try {
        $currentSha = (git rev-parse HEAD 2>$null)
        if ($currentSha -eq $lastSha) {
            Write-Host "  [$PluginSlug] Same commit ($($lastSha.Substring(0,7))) — skipping" -ForegroundColor Green
            return $false
        }

        # Check for PHP file changes between last deployed SHA and current HEAD
        $changedFiles = git diff --name-only $lastSha HEAD -- "$PluginPath/" 2>$null
        if ($LASTEXITCODE -ne 0) {
            Write-Host "  [$PluginSlug] Git diff failed — assuming changes" -ForegroundColor DarkGray
            return $true
        }

        # Filter to PHP files only
        $phpChanges = @($changedFiles | Where-Object { $_ -match '\.php$' })

        if ($phpChanges.Count -eq 0) {
            Write-Host "  [$PluginSlug] No PHP changes since $($lastSha.Substring(0,7)) — skipping" -ForegroundColor Green
            return $false
        }

        Write-Host "  [$PluginSlug] $($phpChanges.Count) PHP file(s) changed since $($lastSha.Substring(0,7)) — needs deploy" -ForegroundColor Yellow
        return $true
    } catch {
        Write-Host "  [$PluginSlug] Could not check git state — assuming changes" -ForegroundColor DarkGray
        return $true
    }
}

# Records a successful deploy for a plugin.
function Save-PluginDeployState {
    param([string]$PluginSlug, [string]$Version)

    $deployedDir = Get-DeployedDir
    if (-not (Test-Path $deployedDir)) {
        New-Item -Path $deployedDir -ItemType Directory -Force | Out-Null
    }

    $sha = ""
    try { $sha = (git rev-parse HEAD 2>$null) } catch {}
    $timestamp = (Get-Date).ToUniversalTime().ToString("yyyy-MM-ddTHH:mm:ssZ")

    # Update plugin-latest.json
    $latestData = @{
        pluginSlug    = $PluginSlug
        lastCommitSha = $sha
        version       = $Version
        deployedAt    = $timestamp
    }
    $latestData | ConvertTo-Json -Depth 4 | Set-Content (Get-PluginLatestPath $PluginSlug) -Encoding UTF8

    # Append to plugin-versions.json
    $versionsPath = Get-PluginVersionsPath $PluginSlug
    $versionsData = @{ pluginSlug = $PluginSlug; versions = @() }
    if (Test-Path $versionsPath) {
        try {
            $versionsData = Get-Content $versionsPath -Raw | ConvertFrom-Json
        } catch {}
    }
    $entry = @{
        commitSha  = $sha
        version    = $Version
        deployedAt = $timestamp
    }
    $versionsData.versions += $entry
    $versionsData | ConvertTo-Json -Depth 10 | Set-Content $versionsPath -Encoding UTF8

    # Update latest.json (aggregated)
    $latestPath = Get-LatestPath
    $aggregated = @{ lastDeployedAt = $timestamp; plugins = @{} }
    if (Test-Path $latestPath) {
        try { $aggregated = Get-Content $latestPath -Raw | ConvertFrom-Json } catch {}
    }
    # Ensure plugins is a hashtable for mutation
    if ($aggregated.plugins -is [PSCustomObject]) {
        $pluginsHash = @{}
        $aggregated.plugins.PSObject.Properties | ForEach-Object { $pluginsHash[$_.Name] = $_.Value }
        $aggregated.plugins = $pluginsHash
    }
    $aggregated.plugins[$PluginSlug] = $latestData
    $aggregated.lastDeployedAt = $timestamp
    $aggregated | ConvertTo-Json -Depth 10 | Set-Content $latestPath -Encoding UTF8

    Write-Host "  [$PluginSlug] Deploy state saved (SHA: $($sha.Substring(0,7)), v$Version)" -ForegroundColor Cyan
}

# Returns a list of plugin slugs that need deployment (have PHP changes since last deploy).
# Takes the plugin registry from powershell.json wpPlugins.plugins.
function Get-PluginsNeedingDeploy {
    param([hashtable]$PluginRegistry, [string[]]$SkipList = @())

    $needsDeploy = @()

    foreach ($slug in $PluginRegistry.Keys) {
        if ($SkipList -contains $slug) { continue }

        $pluginConfig = $PluginRegistry[$slug]
        $pluginPath = $pluginConfig.path
        if (-not $pluginPath) { $pluginPath = "wp-plugins/$slug" }

        $changed = Test-PluginPhpChanged -PluginSlug $slug -PluginPath $pluginPath
        if ($changed) {
            $needsDeploy += $slug
        }
    }

    return $needsDeploy
}
