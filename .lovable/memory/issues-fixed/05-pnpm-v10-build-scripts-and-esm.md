# Issue: pnpm v10 ignored build scripts / PnP ESM resolution

> **Category:** Build/Dependencies  
> **Severity:** Build-breaking  
> **Fixed:** 2026-02-02

---

## Symptoms

During install:

```
Ignored build scripts: @swc/core@..., esbuild@...
Run "pnpm approve-builds"...
```

During `vite build`:

```
Error [ERR_MODULE_NOT_FOUND]: Cannot find package 'esbuild' imported from ...vite...dep-*.js
```

---

## Root Cause

There are three common failure modes that can appear together in pnpm v10 + PnP / global-virtual-store setups:

1. **pnpm v10 build-script blocking (security default)**
   - pnpm v10+ blocks dependency build scripts by default.
   - Native deps like `esbuild` / `@swc/core` often rely on postinstall scripts.

2. **PnP + Node ESM resolution requires the PnP loader**
   - Vite runs in Node as ESM.
   - In PnP mode, ESM resolution needs `.pnp.loader.mjs` (in addition to `.pnp.cjs`).
   - Without the loader, Node can throw `ERR_MODULE_NOT_FOUND` from `node:internal/modules/esm/resolve`.

3. **Shared virtual store directory (anti-pattern)**
   - `virtual-store-dir` must be **per-project** (pnpm docs: virtual store cannot be shared).
   - Pointing `virtual-store-dir` into a shared store directory can make packages run without proper dependency links,
     leading to errors like Vite failing to resolve `esbuild`.

---

## Solution (implemented in `run.ps1`)

### 1) Non-interactive install that allows build scripts (pnpm v10+)

The runner detects pnpm major version and automatically appends:

```bash
pnpm install --dangerously-allow-all-builds
```

This avoids needing the interactive `pnpm approve-builds` step.

### 2) Write pnpm config per-project (avoid shared virtual store)

The runner now writes pnpm config using `--location=project` (so it doesn't pollute global config) and forces a
**project-local** virtual store:

```bash
pnpm config set --location=project virtual-store-dir .pnpm
```

### 3) Ensure Node gets the PnP ESM loader during Vite build (when PnP is active)

Before running the build command, the runner temporarily sets `NODE_OPTIONS` to include:

- `--require .pnp.cjs`
- `--experimental-loader .pnp.loader.mjs`

### 4) Compatibility fallback: `node-linker=isolated` on Node 24+ / cross-drive

If PnP is enabled but the environment is likely to break resolution (Node 24+ or cross-drive store), the runner falls
back to:

```bash
pnpm config set --location=project node-linker isolated
```

---

## Verification

1. Run a clean rebuild:
   ```powershell
   .\run.ps1 -r
   ```
2. Confirm the install step no longer prints “Ignored build scripts”.
3. Confirm `pnpm run build` completes successfully.

---

## Manual Workaround (if needed)

If you want to keep install strict but approve specific deps:

```bash
pnpm approve-builds
```

Then rerun the build.
