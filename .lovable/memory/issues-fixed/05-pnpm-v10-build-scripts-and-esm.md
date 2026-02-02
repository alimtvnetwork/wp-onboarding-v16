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

There are two common failure modes that can appear together in pnpm v10 + PnP setups:

1. **pnpm v10 build-script blocking (security default)**
   - pnpm v10+ blocks dependency build scripts by default.
   - Native deps like `esbuild` / `@swc/core` often rely on postinstall scripts.

2. **PnP + Node ESM resolution requires the PnP loader**
   - Vite runs in Node as ESM.
   - In PnP mode, ESM resolution needs `.pnp.loader.mjs` (in addition to `.pnp.cjs`).
   - Without the loader, Node can throw `ERR_MODULE_NOT_FOUND` from `node:internal/modules/esm/resolve`.

---

## Solution (implemented in `run.ps1`)

### 1) Non-interactive install that allows build scripts (pnpm v10+)

The runner detects pnpm major version and automatically appends:

```bash
pnpm install --dangerously-allow-all-builds
```

This avoids needing the interactive `pnpm approve-builds` step.

### 2) Ensure Node gets the PnP ESM loader during Vite build

Before running the build command, the runner temporarily sets `NODE_OPTIONS` to include:

- `--require .pnp.cjs`
- `--experimental-loader .pnp.loader.mjs`

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
