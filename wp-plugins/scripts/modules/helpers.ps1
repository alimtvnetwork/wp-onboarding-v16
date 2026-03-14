# Module: helpers.ps1
# Utility functions for the run.ps1 automation suite.
# Dot-sourced by run.ps1 — expects $ScriptDir to be set.

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
        $cmd = "$cmd --dangerously-allow-all-builds"
    }
    return $cmd
}

function Decode-Base64 {
    param([string]$Encoded)
    return [System.Text.Encoding]::UTF8.GetString([Convert]::FromBase64String($Encoded))
}

function Resolve-RelativePath($Path) {
    if ([string]::IsNullOrWhiteSpace($Path) -or $Path -eq ".") {
        return $ScriptDir
    }
    if ($Path -match '^[A-Za-z]:' -or $Path -match '^\\\\') {
        return $Path -replace '/', '\'
    }
    return Join-Path $ScriptDir $Path
}
