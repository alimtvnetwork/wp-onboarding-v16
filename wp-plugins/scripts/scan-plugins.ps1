# WordPress Plugin Scanner
# Scans directories to detect WordPress plugins and creates config JSON files

param(
    [Parameter(Mandatory=$false)]
    [string]$ScanPath = "",
    
    [Parameter(Mandatory=$false)]
    [int]$MaxDepth = 3,
    
    [Parameter(Mandatory=$false)]
    [switch]$CreateConfigs = $false,
    
    [Parameter(Mandatory=$false)]
    [switch]$Quiet = $false
)

function Write-Status {
    param([string]$Message, [string]$Color = "White")
    if (-not $Quiet) {
        Write-Host $Message -ForegroundColor $Color
    }
}

function Find-PluginHeader {
    param([string]$FilePath)
    
    $content = Get-Content $FilePath -TotalCount 30 -ErrorAction SilentlyContinue
    if (-not $content) { return $null }
    
    $headerInfo = @{
        PluginName = ""
        Version = ""
        Description = ""
        Author = ""
        AuthorURI = ""
        PluginURI = ""
        TextDomain = ""
        RequiresPHP = ""
        RequiresWP = ""
    }
    
    $text = $content -join "`n"
    
    # Check for Plugin Name header (required)
    if ($text -match "Plugin Name:\s*(.+)") {
        $headerInfo.PluginName = $matches[1].Trim()
    } else {
        return $null
    }
    
    # Extract other headers
    if ($text -match "Version:\s*(.+)") { $headerInfo.Version = $matches[1].Trim() }
    if ($text -match "Description:\s*(.+)") { $headerInfo.Description = $matches[1].Trim() }
    if ($text -match "Author:\s*(.+)") { $headerInfo.Author = $matches[1].Trim() }
    if ($text -match "Author URI:\s*(.+)") { $headerInfo.AuthorURI = $matches[1].Trim() }
    if ($text -match "Plugin URI:\s*(.+)") { $headerInfo.PluginURI = $matches[1].Trim() }
    if ($text -match "Text Domain:\s*(.+)") { $headerInfo.TextDomain = $matches[1].Trim() }
    if ($text -match "Requires PHP:\s*(.+)") { $headerInfo.RequiresPHP = $matches[1].Trim() }
    if ($text -match "Requires at least:\s*(.+)") { $headerInfo.RequiresWP = $matches[1].Trim() }
    
    return $headerInfo
}

function Scan-Directory {
    param(
        [string]$Path,
        [int]$CurrentDepth,
        [int]$MaxDepth
    )
    
    $plugins = @()
    
    if ($CurrentDepth -gt $MaxDepth) { return $plugins }
    
    # Check PHP files in current directory for plugin header
    $phpFiles = Get-ChildItem -Path $Path -Filter "*.php" -File -ErrorAction SilentlyContinue
    
    foreach ($file in $phpFiles) {
        $header = Find-PluginHeader -FilePath $file.FullName
        if ($header) {
            $plugins += @{
                Path = $Path
                MainFile = $file.Name
                Header = $header
                Slug = (Split-Path $Path -Leaf)
            }
            break  # Found main plugin file, stop searching this directory
        }
    }
    
    # Recurse into subdirectories
    $subdirs = Get-ChildItem -Path $Path -Directory -ErrorAction SilentlyContinue | 
               Where-Object { $_.Name -notmatch "^(node_modules|vendor|\.git|\.svn)$" }
    
    foreach ($dir in $subdirs) {
        $plugins += Scan-Directory -Path $dir.FullName -CurrentDepth ($CurrentDepth + 1) -MaxDepth $MaxDepth
    }
    
    return $plugins
}

# Main execution
if ($ScanPath -eq "") {
    $ScanPath = Get-Location
}

if (-not (Test-Path $ScanPath)) {
    Write-Host "Error: Path not found: $ScanPath" -ForegroundColor Red
    exit 1
}

Write-Status "WordPress Plugin Scanner" -Color Cyan
Write-Status "========================" -Color Cyan
Write-Status "Scanning: $ScanPath" -Color Gray
Write-Status "Max Depth: $MaxDepth" -Color Gray
Write-Status ""

$plugins = Scan-Directory -Path $ScanPath -CurrentDepth 0 -MaxDepth $MaxDepth

Write-Status "Found $($plugins.Count) WordPress plugin(s):" -Color Green
Write-Status ""

foreach ($plugin in $plugins) {
    Write-Status "  Plugin: $($plugin.Header.PluginName)" -Color Yellow
    Write-Status "    Path: $($plugin.Path)" -Color Gray
    Write-Status "    Main File: $($plugin.MainFile)" -Color Gray
    Write-Status "    Version: $($plugin.Header.Version)" -Color Gray
    Write-Status "    Slug: $($plugin.Slug)" -Color Gray
    Write-Status ""
    
    if ($CreateConfigs) {
        $configPath = Join-Path $plugin.Path ".plugin-detected.json"
        $config = @{
            pluginName = $plugin.Header.PluginName
            version = $plugin.Header.Version
            slug = $plugin.Slug
            mainFile = $plugin.MainFile
            description = $plugin.Header.Description
            author = $plugin.Header.Author
            authorUri = $plugin.Header.AuthorURI
            pluginUri = $plugin.Header.PluginURI
            textDomain = $plugin.Header.TextDomain
            requiresPHP = $plugin.Header.RequiresPHP
            requiresWP = $plugin.Header.RequiresWP
            detectedAt = (Get-Date -Format "o")
        }
        
        $config | ConvertTo-Json -Depth 10 | Set-Content $configPath -Encoding UTF8
        Write-Status "    Created: .plugin-detected.json" -Color Green
    }
}

# Output JSON for programmatic use
if ($Quiet) {
    $output = $plugins | ForEach-Object {
        @{
            path = $_.Path
            slug = $_.Slug
            mainFile = $_.MainFile
            pluginName = $_.Header.PluginName
            version = $_.Header.Version
        }
    }
    Write-Output ($output | ConvertTo-Json -Depth 10)
}

Write-Status "Done!" -Color Cyan
