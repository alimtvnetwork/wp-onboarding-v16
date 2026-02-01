# Link Manager Separation Policy

> **Date:** 2026-01-31  
> **Status:** Archived ✅

---

## Summary

Link Manager has been extracted to a self-contained folder at `link-manager/` for independent development/handoff.

## Policy

**Do NOT implement Link Manager code in the main repository** unless explicitly requested.

## Location

All Link Manager resources are now at:
- `link-manager/spec/` - 30 spec files
- `link-manager/.lovable/plan.md` - Implementation roadmap
- `link-manager/.lovable/memory/` - LM-specific memories
- `link-manager/CONTEXT-FOR-AI.md` - AI handoff context

---

*Archived from link-manager-separation.md*
