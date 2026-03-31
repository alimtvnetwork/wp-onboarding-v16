# 26-08 — `-tc` Phase Summary Output

## Purpose

Formats and displays the phase summary table after `run.ps1 -tc` (test & compile). Each phase reports a symbol, name, result description, and overall status. The current implementation has **column alignment issues** — this spec defines the correct format.

## Current Output (Broken Alignment)

The result description column does not align consistently because phase names vary in length and no fixed-width formatting is applied:

```
  P H A S E   S U M M A R Y

  ✓ Git Pull              pulled from remote
  ✓ Dependencies          up to date
  ✓ Data Cleanup          cleaned
  ✓ SafeTest Lint         all clean
  ✓ Auto-Fixer            no fixable issues
  ✓ Syntax Check          209 file(s) parsed OK
  ✓ Split Recovery        31 subfolders recovered
  ⚠ Compile Check         119/90 passed, 1 blocked
  ✓ Coverage Run          119 packages
  ✓ Coverage Report       generated

  PHASES                  9/10 passed
  STATUS                  ⚠ REVIEW
```

**Problems:**
1. Phase name column has no fixed width — result descriptions drift left/right depending on name length.
2. No separator between the phase rows and the totals footer.
3. No color-coded status column — only the symbol carries status.

## Correct Output Format

### Column Layout

| Column | Width | Alignment | Content |
|--------|-------|-----------|---------|
| Symbol | 2 | Left | `✓` (Green), `⚠` (Yellow), `✗` (Red) |
| Phase Name | 22 | Left-padded | Fixed-width, left-aligned |
| Result | 36 | Left | Description text |

### Format String

```powershell
# Phase row
Write-Host ("  {0} {1,-22} {2}" -f $symbol, $phaseName, $resultText) -ForegroundColor $color

# Footer separator + totals
Write-Host ""
Write-Host "  $('-' * 50)" -ForegroundColor DarkGray
Write-Host ("  {0,-24} {1}" -f "PHASES", "$passCount/$totalCount passed") -ForegroundColor White
Write-Host ("  {0,-24} {1}" -f "STATUS", $statusLabel) -ForegroundColor $statusColor
```

### Expected Aligned Output

```
  P H A S E   S U M M A R Y

  ✓ Git Pull               pulled from remote
  ✓ Dependencies            up to date
  ✓ Data Cleanup            cleaned
  ✓ SafeTest Lint           all clean
  ✓ Auto-Fixer              no fixable issues
  ✓ Syntax Check            209 file(s) parsed OK
  ✓ Split Recovery          31 subfolders recovered
  ⚠ Compile Check           119/90 passed, 1 blocked
  ✓ Coverage Run            119 packages
  ✓ Coverage Report         generated

  ──────────────────────────────────────────────────
  PHASES                   9/10 passed
  STATUS                   ⚠ REVIEW
```

## Color Coding

| Status | Symbol | Color |
|--------|--------|-------|
| Pass | ✓ | Green |
| Warning / Review | ⚠ | Yellow |
| Fail | ✗ | Red |

### Footer Status Color

| Condition | STATUS Label | Color |
|-----------|-------------|-------|
| All phases pass | `✓ ALL PASSED` | Green |
| Any phase warns (no fails) | `⚠ REVIEW` | Yellow |
| Any phase fails | `✗ FAILED` | Red |

## Phases (Ordered)

| # | Phase Name | Example Result |
|---|-----------|---------------|
| 1 | Git Pull | `pulled from remote` / `skipped (-p)` |
| 2 | Dependencies | `up to date` / `installed` |
| 3 | Data Cleanup | `cleaned` |
| 4 | SafeTest Lint | `all clean` / `N issue(s)` |
| 5 | Auto-Fixer | `no fixable issues` / `N fixed` |
| 6 | Syntax Check | `N file(s) parsed OK` |
| 7 | Split Recovery | `N subfolders recovered` |
| 8 | Compile Check | `N/M passed` / `N/M passed, K blocked` |
| 9 | Coverage Run | `N packages` |
| 10 | Coverage Report | `generated` / `failed` |

## Implementation Notes

1. The phase name column width of 22 accommodates the longest current name (`Coverage Report` = 15 chars) with room for future phases.
2. The footer uses the same column width (24, accounting for no symbol) to keep `PHASES` and `STATUS` labels aligned with phase names.
3. The separator line width (50) spans the full table width.
4. The spaced-out `P H A S E   S U M M A R Y` banner header is cosmetic and remains unchanged.
