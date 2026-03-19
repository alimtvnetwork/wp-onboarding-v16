# Module: php-check-parallel.ps1
# Parallel PHP syntax + backed enum check: validates all plugins before ZIP phase.
# Dot-sourced by run.ps1 — expects plugin-helpers.ps1 loaded.

function Get-PhpCheckSkipFolders {
    param(
        [Parameter(Mandatory)][string]$PluginDir
    )

    $skipFolders = @()

    # Global defaults from powershell.json
    if ($Config -and $Config.wpPlugins -and $Config.wpPlugins.phpCheckSkipFolders) {
        $skipFolders += @($Config.wpPlugins.phpCheckSkipFolders)
    }

    # Per-plugin settings.json overrides
    $settingsPath = Join-Path $PluginDir "settings.json"
    if (Test-Path $settingsPath) {
        try {
            $pluginSettings = Get-Content $settingsPath -Raw | ConvertFrom-Json
            if ($pluginSettings.phpCheck -and $pluginSettings.phpCheck.skipFolders) {
                $skipFolders += @($pluginSettings.phpCheck.skipFolders)
            }
        } catch {
            # Silently ignore malformed settings.json
        }
    }

    return @($skipFolders | Select-Object -Unique)
}

function Get-FilteredPhpFiles {
    param(
        [Parameter(Mandatory)][string]$PluginDir,
        [string[]]$SkipFolders = @()
    )

    $allFiles = @(Get-ChildItem -Path $PluginDir -Recurse -File -Filter "*.php" | Sort-Object FullName)

    if ($SkipFolders.Count -eq 0) {
        return @{
            Files       = $allFiles
            SkippedCount = 0
        }
    }

    $filtered = @()
    $skippedCount = 0

    foreach ($file in $allFiles) {
        $relativePath = $file.FullName.Substring($PluginDir.Length + 1)
        $isSkipped = $false

        foreach ($skip in $SkipFolders) {
            if ($relativePath -like "$skip\*" -or $relativePath -like "$skip/*") {
                $isSkipped = $true
                break
            }
        }

        if ($isSkipped) {
            $skippedCount++
        } else {
            $filtered += $file
        }
    }

    return @{
        Files        = $filtered
        SkippedCount = $skippedCount
    }
}

function Test-PluginPhpSyntaxStandalone {
    param(
        [Parameter(Mandatory)][string]$PluginDir,
        [string[]]$SkipFolders = @(),
        [switch]$Quiet
    )

    $pluginName = Split-Path $PluginDir -Leaf
    $sw = [System.Diagnostics.Stopwatch]::StartNew()

    $result = @{
        Slug         = $pluginName
        Status       = "FAILED"
        Error        = $null
        FileCount    = 0
        SkippedCount = 0
        Duration     = 0
    }

    if (-not (Test-Path $PluginDir)) {
        $result.Error = "Plugin folder not found: $PluginDir"
        $sw.Stop()
        $result.Duration = $sw.Elapsed.TotalSeconds
        return $result
    }

    $phpCommand = Get-Command php -ErrorAction SilentlyContinue
    if ($null -eq $phpCommand) {
        $result.Status = "SKIPPED"
        $result.Error = "PHP CLI not available"
        $sw.Stop()
        $result.Duration = $sw.Elapsed.TotalSeconds
        return $result
    }

    # Load skip folders if not passed (standalone mode)
    if ($SkipFolders.Count -eq 0) {
        $settingsPath = Join-Path $PluginDir "settings.json"
        if (Test-Path $settingsPath) {
            try {
                $pluginSettings = Get-Content $settingsPath -Raw | ConvertFrom-Json
                if ($pluginSettings.phpCheck -and $pluginSettings.phpCheck.skipFolders) {
                    $SkipFolders = @($pluginSettings.phpCheck.skipFolders)
                }
            } catch { }
        }
    }

    $fileResult = Get-FilteredPhpFiles -PluginDir $PluginDir -SkipFolders $SkipFolders
    $phpFiles = $fileResult.Files
    $result.FileCount = $phpFiles.Count
    $result.SkippedCount = $fileResult.SkippedCount

    # Phase A: php -l syntax check
    foreach ($file in $phpFiles) {
        $lintOutput = & php -l $file.FullName 2>&1
        if ($LASTEXITCODE -ne 0) {
            $result.Error = "Syntax error in $($file.FullName): $(($lintOutput | Out-String).Trim())"
            $sw.Stop()
            $result.Duration = $sw.Elapsed.TotalSeconds
            return $result
        }
    }

    # Phase B: backed enum duplicate value check
    foreach ($file in $phpFiles) {
        $lines = Get-Content $file.FullName
        $isInBackedEnum = $false
        $hasEnteredEnumBody = $false
        $enumName = ""
        $enumDepth = 0
        $valueToCase = [System.Collections.Hashtable]::new([StringComparer]::Ordinal)

        foreach ($line in $lines) {
            $trimmed = $line.Trim()

            if (-not $isInBackedEnum) {
                if ($trimmed -match '^enum\s+([A-Za-z_][A-Za-z0-9_]*)\s*:\s*(int|string)\b') {
                    $isInBackedEnum = $true
                    $hasEnteredEnumBody = $false
                    $enumName = $Matches[1]
                    $enumDepth = 0
                    $valueToCase = [System.Collections.Hashtable]::new([StringComparer]::Ordinal)
                } else {
                    continue
                }
            }

            $openCount = ([regex]::Matches($line, '\{')).Count
            $closeCount = ([regex]::Matches($line, '\}')).Count
            $enumDepth += ($openCount - $closeCount)

            if (-not $hasEnteredEnumBody -and $openCount -gt 0) {
                $hasEnteredEnumBody = $true
            }

            if ($hasEnteredEnumBody -and $enumDepth -le 0) {
                $isInBackedEnum = $false
                continue
            }

            if ($trimmed -match '^\s*case\s+([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.+?)\s*;') {
                $caseName = $Matches[1]
                $caseValue = $Matches[2]

                if ($valueToCase.ContainsKey($caseValue)) {
                    $result.Error = "Duplicate enum value in $($file.FullName): $enumName::$caseName = $caseValue (same as $($valueToCase[$caseValue]))"
                    $sw.Stop()
                    $result.Duration = $sw.Elapsed.TotalSeconds
                    return $result
                }
                $valueToCase[$caseValue] = $caseName
            }
        }
    }

    $result.Status = "OK"
    $sw.Stop()
    $result.Duration = $sw.Elapsed.TotalSeconds
    return $result
}

function Invoke-ParallelPhpCheck {
    param(
        [Parameter(Mandatory)][array]$PluginFolders,
        [switch]$Sequential
    )

    Write-Host ""
    Write-Host "  ── Phase 0: PHP Syntax Check ──────────────────────────" -ForegroundColor Cyan

    # Resolve global skip folders
    $globalSkipFolders = @()
    if ($Config -and $Config.wpPlugins -and $Config.wpPlugins.phpCheckSkipFolders) {
        $globalSkipFolders = @($Config.wpPlugins.phpCheckSkipFolders)
    }

    if ($globalSkipFolders.Count -gt 0) {
        Write-Host "  Skipping folders: $($globalSkipFolders -join ', ')" -ForegroundColor DarkGray
    }

    if ($Sequential) {
        return Invoke-SequentialPhpCheck -PluginFolders $PluginFolders -GlobalSkipFolders $globalSkipFolders
    }

    Write-Host "  Checking $($PluginFolders.Count) plugin(s) in parallel..." -ForegroundColor Yellow
    foreach ($folder in $PluginFolders) {
        Write-Host "    [PHP] Starting: $($folder.Name)" -ForegroundColor DarkGray
    }

    $phpCheckModulePath = Join-Path $ScriptDir "wp-plugins" "scripts" "modules" "php-check-parallel.ps1"

    $jobs = @()
    $jobIndex = 0

    foreach ($folder in $PluginFolders) {
        $folderPath = $folder.FullName
        $currentIndex = $jobIndex

        # Resolve per-plugin skip folders and merge with global
        $pluginSkipFolders = @() + $globalSkipFolders
        $settingsPath = Join-Path $folderPath "settings.json"
        if (Test-Path $settingsPath) {
            try {
                $pluginSettings = Get-Content $settingsPath -Raw | ConvertFrom-Json
                if ($pluginSettings.phpCheck -and $pluginSettings.phpCheck.skipFolders) {
                    $pluginSkipFolders += @($pluginSettings.phpCheck.skipFolders)
                }
            } catch { }
        }
        $pluginSkipFolders = @($pluginSkipFolders | Select-Object -Unique)

        $jobs += Start-Job -Name "php-$currentIndex-$($folder.Name)" -ScriptBlock {
            param($ModulePath, $PluginPath, $Index, $SkipFoldersJson)
            . $ModulePath
            $skipFolders = @()
            if ($SkipFoldersJson) {
                $skipFolders = @($SkipFoldersJson | ConvertFrom-Json)
            }
            $result = Test-PluginPhpSyntaxStandalone -PluginDir $PluginPath -SkipFolders $skipFolders -Quiet
            $result.Index = $Index
            return $result
        } -ArgumentList $phpCheckModulePath, $folderPath, $currentIndex, ($pluginSkipFolders | ConvertTo-Json -Compress)

        $jobIndex++
    }

    $results = @()
    foreach ($job in $jobs) {
        $result = Receive-Job -Job $job -Wait | Select-Object -First 1

        if ($null -eq $result) {
            $idx = [int](($job.Name -split '-')[1])
            $folderName = ($job.Name -replace "^php-\d+-", "")
            $result = @{
                Index        = $idx
                Slug         = $folderName
                Status       = "FAILED"
                Error        = "Background job returned no result"
                FileCount    = 0
                SkippedCount = 0
                Duration     = 0
            }
        }

        $results += $result
        $duration = "{0:N1}s" -f $result.Duration
        $skippedLabel = if ($result.SkippedCount -gt 0) { ", $($result.SkippedCount) skipped" } else { "" }

        if ($result.Status -eq "OK") {
            Write-Host "    [PHP] Passed: $($result.Slug) ($($result.FileCount) files$skippedLabel) [$duration]" -ForegroundColor Green
        } elseif ($result.Status -eq "SKIPPED") {
            Write-Host "    [PHP] Skipped: $($result.Slug) ($($result.Error)) [$duration]" -ForegroundColor Yellow
        } else {
            Write-Host "    [PHP] FAILED: $($result.Slug)" -ForegroundColor Red
            Write-Host "          $($result.Error)" -ForegroundColor Red
        }

        Remove-Job -Job $job -Force
    }

    return @($results | Sort-Object { $_.Index })
}

function Invoke-SequentialPhpCheck {
    param(
        [Parameter(Mandatory)][array]$PluginFolders,
        [string[]]$GlobalSkipFolders = @()
    )

    Write-Host "  Checking $($PluginFolders.Count) plugin(s) sequentially..." -ForegroundColor Yellow

    $results = @()
    $jobIndex = 0

    foreach ($folder in $PluginFolders) {
        Write-Host "    [PHP] Checking: $($folder.Name)..." -ForegroundColor DarkGray

        # Merge global + per-plugin skip folders
        $pluginSkipFolders = @() + $GlobalSkipFolders
        $settingsPath = Join-Path $folder.FullName "settings.json"
        if (Test-Path $settingsPath) {
            try {
                $pluginSettings = Get-Content $settingsPath -Raw | ConvertFrom-Json
                if ($pluginSettings.phpCheck -and $pluginSettings.phpCheck.skipFolders) {
                    $pluginSkipFolders += @($pluginSettings.phpCheck.skipFolders)
                }
            } catch { }
        }
        $pluginSkipFolders = @($pluginSkipFolders | Select-Object -Unique)

        $result = Test-PluginPhpSyntaxStandalone -PluginDir $folder.FullName -SkipFolders $pluginSkipFolders -Quiet
        $result.Index = $jobIndex

        $duration = "{0:N1}s" -f $result.Duration
        $skippedLabel = if ($result.SkippedCount -gt 0) { ", $($result.SkippedCount) skipped" } else { "" }

        if ($result.Status -eq "OK") {
            Write-Host "    [PHP] Passed: $($result.Slug) ($($result.FileCount) files$skippedLabel) [$duration]" -ForegroundColor Green
        } elseif ($result.Status -eq "SKIPPED") {
            Write-Host "    [PHP] Skipped: $($result.Slug) ($($result.Error)) [$duration]" -ForegroundColor Yellow
        } else {
            Write-Host "    [PHP] FAILED: $($result.Slug)" -ForegroundColor Red
            Write-Host "          $($result.Error)" -ForegroundColor Red
        }

        $results += $result
        $jobIndex++
    }

    return $results
}
