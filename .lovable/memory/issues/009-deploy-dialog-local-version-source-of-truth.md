# Deploy dialog local version source-of-truth bug

## Rule

For deploy/status UI, always compare remote plugin versions against the actual local plugin inventory/API records, not `public/version.json`.

## Why

`public/version.json` can drift behind the real plugin version used by ZIP/build/upload flows. That causes false UI states such as showing local older than remote immediately after a successful upload.

## How to apply

- Use local plugin records (`/plugins`, `usePlugins()`, or equivalent real plugin metadata) as the local source-of-truth.
- Use semantic version comparison, not raw string inequality, when deciding `up to date`, `needs publish`, `upgrade`, or `downgrade` states.
- Only show `Needs publish` when local is newer than remote.
- If remote is newer than local, show a distinct neutral/warning state instead of implying the user needs to publish.