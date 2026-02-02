# Issue: pnpm PnP Module Resolution Failures

> **Category:** Build/Dependencies  
> **Severity:** Build-breaking  
> **Fixed:** 2026-02-02

---

## Symptoms

```
error during build:
[vite]: Rollup failed to resolve import "zustand" from "src/stores/errorStore.ts"
```

- Build fails with "failed to resolve import" for installed packages
- Package exists in `package.json` but Vite/Rollup cannot find it
- Happens specifically with pnpm PnP (Plug'n'Play) mode

---

## Root Cause

When using pnpm with PnP mode and a **shared store on a different volume** (e.g., `E:/.pnpm-store/`):

1. **Symlink/hardlink breakage**: Cross-volume links may not work correctly on Windows
2. **PnP resolution cache stale**: The `.pnp.cjs` file may reference outdated paths
3. **Package not properly linked**: The package is in the store but not linked to the project

---

## Solution

### Immediate Fix
Reinstall the specific package:
```bash
pnpm add zustand@^5.0.11
```

### Permanent Fix
Use the `-Rebuild` flag to completely reset dependencies:
```powershell
.\run.ps1 -Rebuild
```

This combines `-Force` (cleans caches, prunes store) + `-Install` (reinstalls all deps).

---

## Prevention

1. **After git pull with new dependencies**: Always run `.\run.ps1 -Install` or `-Rebuild`
2. **Configure pnpm store properly**: Ensure `pnpmStorePath` in `powershell.json` is accessible
3. **Don't mix package managers**: Stick to pnpm, don't run `npm install`

---

## Related Files

- `run.ps1` - PowerShell runner with `-Install` and `-Rebuild` flags
- `powershell.json` - Configuration including `pnpmStorePath`
- `.pnp.cjs` - PnP resolution manifest (auto-generated)

---

## Code Reference

```powershell
# In run.ps1 - The Install logic
if ($Install) {
    # Frontend: pnpm install
    Push-Location $FrontendDir
    Invoke-Expression $InstallCommand  # pnpm install
    Pop-Location
    
    # Backend: go mod tidy + download
    Push-Location $BackendDir
    go mod tidy
    go mod download
    Pop-Location
}
```
