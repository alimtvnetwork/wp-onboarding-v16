# Deploy dialog local version source-of-truth bug

## Root cause

The deploy UI in `src/components/sites/DeployUploaderDialog.tsx` was showing the **local** Riseup Asia / QUpload versions from `public/version.json` via `useVersionInfo()`.

That is not the same source-of-truth used by the actual local plugin inventory and upload flow.

In this case:

- `run.ps1` uploaded `riseup-asia-uploader v2.38.0`
- the remote site correctly reported `v2.38.0`
- but `public/version.json` still contained `wpPluginVersion: 2.31.0`

So the UI rendered a false comparison:

- local `v2.31.0`
- remote `v2.38.0`

This made the deploy dialog look logically wrong even though the upload itself succeeded.

There was a second UI logic issue too: the dialog used raw string inequality (`remoteVersion !== localVersion`) to decide `Needs publish`, instead of semantic version comparison.

## Fix applied

Updated `DeployUploaderDialog.tsx` to:

1. derive local plugin versions from `usePlugins()` (the actual local plugin records)
2. stop using `public/version.json` as the deploy dialog local version source
3. use `compareVersions(localVersion, remoteVersion)` for status logic
4. only show `Needs publish` when local is actually newer than remote
5. show a neutral `Remote is newer than local` state when remote is ahead

## Why this is the correct fix

The deploy dialog must compare:

- **actual local plugin version**
- **actual remote plugin version**

not:

- marketing/build metadata from `version.json`
- versus remote runtime plugin state

`version.json` can lag behind plugin bumps, so it is unsuitable as the deploy dialog source-of-truth.

## Follow-up recommendation

Keep `public/version.json`, `run.ps1`, and plugin versions in sync, but never rely on `version.json` alone for operational deploy comparisons.