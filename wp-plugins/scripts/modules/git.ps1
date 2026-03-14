# Module: git.ps1
# Git pull automation.
# Dot-sourced by run.ps1 — expects $skippull, $RootDir, Format-ElapsedTime from helpers.ps1.

function Invoke-GitPull {
    if ($skippull) {
        Write-Host "[GIT] Skipping git pull (-p)" -ForegroundColor Gray
        Write-Host ""
        return
    }

    $pullWatch = [System.Diagnostics.Stopwatch]::StartNew()
    Write-Host "[GIT] Pulling latest changes..." -ForegroundColor Yellow

    Push-Location $RootDir
    try {
        if (Test-Path ".git") {
            git pull 2>&1 | Out-Host
            if ($LASTEXITCODE -ne 0) {
                Write-Host "  WARNING: git pull failed, continuing anyway..." -ForegroundColor Yellow
            } else {
                Write-Host "  Git pull complete" -ForegroundColor Green
            }
        } else {
            Write-Host "  Skipping git pull (not a git repository)" -ForegroundColor Gray
        }
    }
    finally {
        Pop-Location
    }

    $pullWatch.Stop()
    Write-Host "  $(Format-ElapsedTime $pullWatch)" -ForegroundColor DarkGray
    Write-Host ""
}
