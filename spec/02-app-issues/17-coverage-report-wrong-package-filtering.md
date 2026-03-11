# Issue: Coverage Report Shows Test Packages Instead of Source Packages

> **ID:** 17-coverage-report-wrong-package-filtering
> **Date:** 2026-03-11
> **Category:** Tooling / Coverage Reporting
> **Status:** Resolved

---

## Issue Summary

1. **What happened:** The Go coverage summary script reported per-package coverage percentages for **test packages** (names ending in `tests`) instead of **source packages** (the actual code under test). This made the coverage report useless — it showed `coregenerictests 4.9%` instead of `coregeneric 4.9%`.
2. **Where it happened:** Coverage summary generation — the PowerShell script that parses `go test -coverprofile` output and produces both a text summary and HTML dashboard.
3. **Symptoms and impact:** Every line in the coverage summary had a `tests` suffix. Users could not see which source packages had low coverage. The HTML report was either missing or malformed.
4. **How it was discovered:** Manual review of the coverage summary output on 2026-03-11.

## Root Cause Analysis

1. **Direct cause:** The coverage aggregation logic grouped coverage data by the **test package name** (the package containing `_test.go` files) rather than by the **source package name** (the package whose code is actually instrumented by `go test -cover`).
2. **Contributing factors:**
   - Go's `coverage.out` profile format uses **source file paths** (e.g., `github.com/user/project/coregeneric/file.go:10.5,20.3 1 1`), not test package names. The script was likely extracting package names from test output lines (`ok  coregenerictests  0.5s  coverage: 4.9%`) instead of from the profile file itself.
   - No spec existed for how coverage parsing should work.
3. **Triggering conditions:** Any Go project where test packages follow the `{name}tests` naming convention (external test packages in a separate directory).
4. **Why the existing spec did not prevent it:** No spec or acceptance criteria existed for the coverage reporting tool.

## Fix Description

1. **What was changed in the spec:** Created a new coverage tool spec defining correct behavior — parse `coverage.out` profile lines, extract the source package from the file path (not from test runner output), and aggregate statement coverage per source package.
2. **New rules or constraints added:**
   - **Rule:** Coverage must be reported by **source package** (the package whose `.go` files appear in `coverage.out`), never by test package.
   - **Rule:** Packages whose names end with `tests` must be excluded from the coverage report (they are test helpers, not source code).
   - **Rule:** The HTML report must be a self-contained single-file dashboard with per-package drill-down.
3. **Why the fix resolves the root cause:** By parsing `coverage.out` line-by-line and extracting the package path from the source file path (everything before the last `/filename.go`), we always get the source package, never the test package.
4. **Config changes or defaults affected:** New `tools/coverage/run-coverage.ps1` script with configurable project root and output directory.
5. **Logging or diagnostics required:** The script prints a summary to stdout and writes both `coverage-summary.txt` and `coverage.html` to the output directory.

## Prevention and Non-Regression

1. **Prevention rule:** Coverage tools must parse the `coverage.out` profile file for package identification. Never derive package names from `go test` stdout (which shows test package names).
2. **Acceptance criteria / test scenarios:**
   - Given a Go project with external test packages (`foobar/` tested by `foobartests/`), the coverage report lists `foobar` (not `foobartests`).
   - Packages ending in `tests` never appear in the coverage summary.
   - The HTML file is a valid self-contained HTML document openable in any browser.
3. **Guardrails or linting policies:** N/A (tooling, not application code).
4. **References to updated spec sections:** `tools/coverage/README.md`

## Done Checklist

- [x] Issue write-up created under `./`
- [x] Memory updated with summary and prevention rule
- [x] Acceptance criteria updated or added
- [x] Script created: `tools/coverage/run-coverage.ps1`
