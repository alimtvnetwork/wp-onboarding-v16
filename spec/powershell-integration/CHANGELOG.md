# PowerShell Script Changelog

All notable changes to the PowerShell runner script (`run.ps1`) will be documented in this file.

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
