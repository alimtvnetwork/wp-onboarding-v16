# Issue: Malformed version.json Causes App Crash
Fixed: 2026-02-04 (4th occurrence)

---

## Problem

When updating `public/version.json` to add new changelog entries, the JSON structure was corrupted during the line-replace operation, causing a parse error that crashed the application with:

```
SyntaxError: Expected ',' or ']' after array element in JSON at position XXXX
```

The error occurs because:
1. A new changelog entry was inserted but the previous entry's `changes` array was not properly closed
2. The next version entry started inside the unclosed array
3. Using line-replace on JSON files with complex nested structures results in partial replacements

---

## Root Cause

Using `lov-line-replace` on JSON files with complex nested structures can result in partial replacements that break JSON validity. **NEVER use line-replace on version.json.**

---

## Solution - MANDATORY

When editing `public/version.json`:
1. **ALWAYS use `lov-write` to write the COMPLETE file** - never use line-replace
2. **Copy the entire existing content first**
3. **Add new entries at the beginning of the changelog array**
4. **Validate JSON structure before writing**

---

## Prevention Rules for AI - CRITICAL

1. **NEVER use `lov-line-replace` on `public/version.json`** - always rewrite the entire file
2. **Always include the full changelog array** when making changes
3. **Validate JSON structure before submitting**: Does each `[` have a `]`? Does each `{` have a `}`?
4. **Keep the file organized**: newest entries at the top of changelog array

---

## Occurrence Count

This issue has occurred **4 times**. Each time it was caused by using line-replace instead of full file write.

---

## Files

- `public/version.json` - Application version and changelog

---

*This is a recurring issue that MUST be prevented by using full file writes.*
