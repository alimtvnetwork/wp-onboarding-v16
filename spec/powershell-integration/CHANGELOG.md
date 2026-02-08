# PowerShell Script Changelog

All notable changes to the PowerShell runner script (`run.ps1`) will be documented in this file.

## New Entry Template (copy/paste)

Use this template whenever you change `run.ps1` or make a functional/config-schema change to `powershell.json`.

```md
## [X.Y.Z] - YYYY-MM-DD

### Added
- ...

### Changed
- ...

### Fixed
- ...

### Notes
- (optional) Migration steps, breaking changes, required config updates
```

---

## [1.2.0] - 2026-02-08

### Added
- **Runtime data cleanup**: Force mode (`-f`, `-r`) now cleans backend sessions, request-sessions, error logs, and standalone log files from `backend/data/`
- **cleanPaths expanded**: `powershell.json` now includes `backend/data/sessions`, `backend/data/request-sessions`, and `backend/data/errors`

### Changed
- `-r` flag description updated to reflect session/log cleanup behavior

### Notes
- Directories cleaned: `data/sessions/`, `data/request-sessions/`, `data/errors/`, `data/log.txt`, `data/error.log.txt`
- Cleanup only runs when `dataDir` is configured in `powershell.json`

---

## [1.1.0] - 2026-02-04

### Added
- **Version tracking**: Script now has version number in header and `powershell.json`
- **PnP artifact cleanup**: Force mode now removes `.pnp.cjs`, `.pnp.loader.mjs`, `.pnp.data.json`
- **Improved install detection**: Respects `EffectiveNodeLinker` (PnP vs isolated) when checking if install is needed

### Changed
- **Rebuild sequence**: `-r` flag now correctly defers frontend install until after force-clean
- **Install always runs**: `-i` and `-r` flags always trigger `pnpm install`, even if `node_modules` exists

### Fixed
- "vite is not recognized" error when using `-r` flag

---

## [1.0.0] - 2026-02-02

### Added
- Initial PowerShell runner with pnpm PnP support
- Git pull, prerequisites check, pnpm install, build, and run steps
- Flags: `-b`, `-s`, `-p`, `-f`, `-i`, `-r`, `-fw`, `-h`, `-v`
- Auto-install of Go, Node.js, and pnpm via winget
- Windows Firewall rule management
- Configurable via `powershell.json`

---

*Keep this file updated when the script changes.*
