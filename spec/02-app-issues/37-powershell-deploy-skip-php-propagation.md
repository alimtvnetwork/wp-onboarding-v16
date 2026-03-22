# PowerShell -d: Skip PHP Propagation When No PHP Changes

> **Created:** 2026-03-22  
> **Status:** 🔴 Open

---

## Problem

Running `.\run.ps1 -d` always executes `-uas` (upload all sites) and `-pas` (plugin status) after `git pull`, even when no PHP plugin files have changed. This wastes time on unnecessary network round-trips to all WordPress sites.

---

## Desired Behavior

After `git pull`, check if any files in `wp-plugins/` were modified:

```powershell
$gitDiff = git diff --name-only HEAD@{1} HEAD -- "wp-plugins/"
$hasPhpChanges = ($gitDiff | Where-Object { $_ -match '\.(php|js|css|json)$' }).Count -gt 0
```

### Decision Logic

| PHP changes detected | Action |
|---------------------|--------|
| Yes | Run `-uas -pas` as usual |
| No | Skip `-uas -pas`, print "No PHP changes — skipping plugin propagation" |
| Git pull failed | Abort deploy |

---

## Implementation

In `run.ps1`, within the `-deploy` block:

1. Run `git pull`
2. Capture `HEAD@{1}` before pull, `HEAD` after
3. Check diff for `wp-plugins/**` changes
4. Conditionally run `-uas -pas`
5. Always proceed to build & run regardless

---

## Edge Cases

- First clone (no `HEAD@{1}`) → always propagate
- Merge conflicts → abort with error message
- Force flag (`-f`) → always propagate regardless of diff

---

## References

- `run.ps1` — deploy block
- `.lovable/memory/workflow/powershell-automation.md`
