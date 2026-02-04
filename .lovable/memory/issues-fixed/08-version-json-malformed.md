# Issue: Malformed version.json Causes App Crash
Fixed: 2026-02-04

---

## Problem

When updating `public/version.json` to add new changelog entries, the JSON structure was corrupted during the line-replace operation, causing a parse error that crashed the application with:

```
SyntaxError: Expected ',' or ']' after array element in JSON at position 1123
```

The error occurred because:
1. A new changelog entry was inserted but the previous entry's `changes` array was not properly closed
2. The next version entry started inside the unclosed array

---

## Root Cause

Using `lov-line-replace` on JSON files with complex nested structures can result in partial replacements that break JSON validity. The replacement merged two version entries incorrectly.

---

## Solution

When editing `version.json`:
1. **Always validate JSON structure** after changes
2. **Include complete objects** in replacements (don't cut mid-array)
3. **Check for closing brackets** `]` and `}` are properly paired

---

## Prevention Rules for AI

When updating `public/version.json`:
1. **Never cut a replacement in the middle of an array or object**
2. **Always include the full changelog entry** including opening and closing braces/brackets
3. **Verify the replacement includes proper commas** between array elements
4. **After editing, mentally validate**: Does each `[` have a `]`? Does each `{` have a `}`?

---

## Files

- `public/version.json` - Application version and changelog

---

*This is the third occurrence of this issue. It must be prevented in future updates.*
