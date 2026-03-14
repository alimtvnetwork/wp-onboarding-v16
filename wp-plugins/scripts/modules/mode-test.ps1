# Module: mode-test.ps1
# Test mode: -t (run Go backend tests and exit)
# Dot-sourced by run.ps1 — expects $ScriptDir, $Config, Resolve-RelativePath.

function Invoke-TestMode {
    $ConfigPath = Join-Path $ScriptDir "powershell.json"
    $Config = Get-Content $ConfigPath -Raw | ConvertFrom-Json

    $BackendDirTest = Resolve-RelativePath $Config.backendDir
    $DataDirTest = if ($Config.dataDir) { Resolve-RelativePath $Config.dataDir } else { Join-Path $BackendDirTest "data" }

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

        $testOutput | Out-File -FilePath $TestLogFile -Encoding UTF8

        $failLines = ($testOutput -split "`n") | Where-Object { $_ -match '--- FAIL|FAIL\s' }

        if ($testExitCode -ne 0) {
            $failLines -join "`n" | Out-File -FilePath $ErrorLogFile -Encoding UTF8

            Write-Host $testOutput
            Write-Host ""
            Write-Host "  TESTS FAILED" -ForegroundColor Red
            Write-Host "  Full log:  $TestLogFile" -ForegroundColor Yellow
            Write-Host "  Errors:    $ErrorLogFile" -ForegroundColor Yellow
        } else {
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
