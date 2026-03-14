# Module: pnpm.ps1
# Pnpm store configuration and PnP node options.
# Dot-sourced by run.ps1 — expects $FrontendDir, $PnpmStorePath, $UsePnp, $NodeMajor to be set.

function Configure-PnpmStore {
    $projectDrive = Get-DriveRoot $FrontendDir
    $storeDrive = Get-DriveRoot $PnpmStorePath
    $crossDrive = $false
    if ($projectDrive -and $storeDrive -and ($projectDrive -ne $storeDrive)) {
        $crossDrive = $true
    }

    $nodeLinker = "isolated"
    if ($UsePnp -and (-not $crossDrive) -and ($NodeMajor -lt 24)) {
        $nodeLinker = "pnp"
    }

    $script:EffectiveNodeLinker = $nodeLinker

    if ($UsePnp -and $nodeLinker -ne "pnp") {
        Write-Host "  NOTE: Falling back to node-linker=isolated for compatibility (Node v$NodeMajor / cross-drive store)." -ForegroundColor Yellow
    }

    if ($PnpmStorePath) {
        Write-Host "  Configuring pnpm store path: $PnpmStorePath" -ForegroundColor Gray
        if (-not (Test-Path $PnpmStorePath)) {
            New-Item -ItemType Directory -Path $PnpmStorePath -Force | Out-Null
        }
        pnpm config set --location=project store-dir $PnpmStorePath 2>$null
    }

    pnpm config set --location=project virtual-store-dir .pnpm 2>$null
    pnpm config set --location=project node-linker $nodeLinker 2>$null

    if ($nodeLinker -eq "pnp") {
        pnpm config set --location=project symlink false 2>$null
    } else {
        pnpm config set --location=project symlink true 2>$null
    }

    pnpm config set --location=project package-import-method auto 2>$null
}

function Enable-PnpmPnpNodeOptions([string]$ProjectDir) {
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
