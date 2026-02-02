# Issue: SPA Static File 404 Errors

> **Category:** Backend/Routing  
> **Severity:** App-breaking  
> **Fixed:** 2026-02-02

---

## Symptoms

- Browser shows 404 or "Cannot GET /" when accessing `http://localhost:8080/`
- API endpoints work (`/api/v1/health`) but frontend doesn't load
- Build succeeds but copied files aren't served

---

## Root Cause

The Go backend's `staticDir` configuration didn't match where `index.html` actually lives:

1. **Path mismatch**: Config says `frontend/dist` but files are in `frontend/dist/dist` (nested)
2. **Copy operation issue**: `Copy-Item -Recurse $SourceDist $TargetDir` copies the folder itself, not just contents
3. **No fallback detection**: Backend didn't auto-detect nested `dist/` folders

---

## Solution

### Backend Fix: Auto-detect nested dist folder

```go
// In router.go
func resolveSpaStaticDir(dir string) string {
    // Check if index.html exists directly
    if fileExists(filepath.Join(dir, "index.html")) {
        return dir
    }
    // Check if nested in dist/ subfolder
    nested := filepath.Join(dir, "dist")
    if fileExists(filepath.Join(nested, "index.html")) {
        return nested
    }
    return dir
}

func fileExists(path string) bool {
    info, err := os.Stat(path)
    return err == nil && !info.IsDir()
}
```

### PowerShell Copy Fix

The `run.ps1` correctly copies using:
```powershell
Copy-Item -Recurse $SourceDist $TargetDir
```

But if `$TargetDir` already exists, it may nest. The script now removes old target first:
```powershell
if (Test-Path $TargetDir) {
    Remove-Item -Recurse -Force $TargetDir
}
Copy-Item -Recurse $SourceDist $TargetDir
```

---

## Verification

After starting the backend:
1. Check `http://localhost:8080/` - should serve React app
2. Check `http://localhost:8080/api/v1/health` - should return JSON
3. Check browser DevTools Network tab - no 404s for JS/CSS assets

---

## Prevention

1. **Use consistent paths**: Ensure `distDir` in `powershell.json` matches Vite's `build.outDir`
2. **Verify after copy**: The script now logs the exact target path
3. **Backend auto-detection**: The `resolveSpaStaticDir` function handles edge cases

---

## Related Files

- `backend/internal/api/router.go` - Static file serving logic
- `run.ps1` - Build copy step (Step 4)
- `powershell.json` - `distDir` and `targetDir` configuration
- `vite.config.ts` - Vite build output configuration
