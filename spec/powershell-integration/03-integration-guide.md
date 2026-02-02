# PowerShell Integration Guide

> **Version:** 1.0.0  
> **Created:** 2026-01-31  
> **Status:** Active  
> **Purpose:** Step-by-step guide to integrate the PowerShell runner into any project

---

## Prerequisites

| Requirement | Minimum Version | Auto-Install |
|-------------|-----------------|--------------|
| Windows | 10/11 or Server 2019+ | N/A |
| PowerShell | 5.1 or 7+ | N/A |
| winget | Latest | N/A |
| Go | 1.21+ | ✅ Yes |
| Node.js | 18+ LTS | ✅ Yes |

---

## Step 1: Copy Template Files

Copy from spec templates to your project:

```powershell
# From this spec folder
Copy-Item "spec/.../30-powershell-integration/templates/run.ps1" "YOUR_PROJECT/run.ps1"
```

Or manually create `run.ps1` in your project root.

---

## Step 2: Create Configuration

Create `powershell.json` in your project root:

### Minimal Config

```json
{
  "projectName": "My Project",
  "backendDir": "go-backend"
}
```

### Standard Config

```json
{
  "projectName": "My Project",
  "rootDir": ".",
  "backendDir": "go-backend",
  "frontendDir": ".",
  "distDir": "dist",
  "targetDir": "go-backend/frontend/dist",
  "dataDir": "go-backend/data",
  "ports": [8080],
  "cleanPaths": [
    "node_modules",
    "dist",
    ".vite"
  ]
}
```

---

## Step 3: Verify Folder Structure

Ensure your project matches this structure:

```
your-project/
├── run.ps1                 ← PowerShell script
├── powershell.json         ← Configuration
├── package.json            ← Frontend dependencies
├── src/                    ← React source
├── go-backend/             ← Go backend
│   ├── main.go
│   ├── config.json         ← (created automatically)
│   ├── config.example.json ← Template config
│   └── frontend/
│       └── dist/           ← Build output copied here
└── dist/                   ← npm build output
```

---

## Step 4: First Run

```powershell
# Navigate to project
cd YOUR_PROJECT

# Run with help to verify
.\run.ps1 -Help

# Full build and run
.\run.ps1
```

**Expected Output:**

```
========================================
  My Project - Build & Run Script
========================================

[1/5] Pulling latest changes from git...
  ✓ Git pull complete
  ⏱ 1.2s

[2/5] Checking prerequisites...
  ✓ Go found: go version go1.21.0
  ✓ npm found: 10.2.0
  ⏱ 0.3s

[3/5] Building React frontend...
  ✓ Frontend built successfully
  ⏱ 15.2s

[4/5] Copying build to Go backend...
  ✓ Build files copied
  ⏱ 0.1s

[5/5] Starting Go backend...
========================================
  My Project starting...
  Open: http://localhost:8080
  Press Ctrl+C to stop
========================================
```

---

## Step 5: Configure Firewall (Optional)

For network access, run as Administrator:

```powershell
# Right-click PowerShell → Run as Administrator
.\run.ps1 -OpenFirewall
```

This creates inbound rules for your configured ports.

---

## Customization

### Custom Build Command

If your project uses different build tools:

```json
{
  "buildCommand": "bun run build",
  "runCommand": "go run cmd/server/main.go"
}
```

### Custom Clean Paths

For projects with additional caches:

```json
{
  "cleanPaths": [
    "node_modules",
    "dist",
    ".vite",
    ".next",
    "go-backend/data/*.db",
    "tmp/"
  ]
}
```

### Monorepo Setup

For monorepo projects:

```json
{
  "projectName": "Monorepo App",
  "frontendDir": "packages/web",
  "backendDir": "packages/api",
  "distDir": "build",
  "targetDir": "packages/api/static"
}
```

---

## Troubleshooting

### Go Not Found After Install

**Problem:** Go installed but not in PATH

**Solution:**
```powershell
# Restart PowerShell or manually refresh PATH
$env:Path = [System.Environment]::GetEnvironmentVariable("Path","Machine") + ";" + [System.Environment]::GetEnvironmentVariable("Path","User")
```

### npm install Fails

**Problem:** Network issues or corrupt cache

**Solution:**
```powershell
# Clear npm cache
npm cache clean --force

# Then force rebuild
.\run.ps1 -Force
```

### Firewall Rules Not Applied

**Problem:** Not running as Administrator

**Solution:**
```powershell
# Right-click PowerShell → Run as Administrator
# Then run:
.\run.ps1 -OpenFirewall
```

### Build Takes Too Long

**Problem:** Cold build with no cache

**Solution:**
```powershell
# Skip build if no frontend changes
.\run.ps1 -SkipBuild

# Or use incremental builds
.\run.ps1  # (without -Force)
```

---

## CI/CD Integration

### GitHub Actions

```yaml
- name: Build Frontend
  shell: pwsh
  run: .\run.ps1 -BuildOnly -SkipPull
```

### Azure DevOps

```yaml
- task: PowerShell@2
  inputs:
    filePath: 'run.ps1'
    arguments: '-BuildOnly -SkipPull'
```

---

## AI Handoff Checklist

When asking an AI to integrate this PowerShell runner:

1. ✅ Share `30-powershell-integration/` spec folder
2. ✅ Provide current project structure
3. ✅ Specify port requirements
4. ✅ List any custom build commands

**Example Prompt:**

> "Integrate the PowerShell runner from spec `30-powershell-integration/` into this project. The backend is in `server/` and frontend in `client/`. Use port 3000. Follow the integration guide."

---

## Cross-References

- [Configuration Schema](./01-configuration-schema.md) - JSON config details
- [Script Reference](./02-script-reference.md) - All CLI flags
- [SM-010 Backend Spec](../SM-010-golang-backend-implementation.md) - Go backend structure
