# Module: firewall.ps1
# Windows Firewall rule management.
# Dot-sourced by run.ps1 — expects $ProjectName and Test-IsAdmin from helpers.ps1.

function Ensure-FirewallRules {
    param([int[]]$PortList = @(8080))

    if (-not (Test-IsAdmin)) {
        Write-Host "  WARNING: -OpenFirewall requires Administrator. Re-run PowerShell as Admin." -ForegroundColor Yellow
        Write-Host "  TIP: Right-click PowerShell -> Run as Administrator" -ForegroundColor Gray
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
            Write-Host "  Firewall rule added: $ruleName" -ForegroundColor Green
        } else {
            Write-Host "  Firewall rule exists: $ruleName" -ForegroundColor Green
        }
    }
}
