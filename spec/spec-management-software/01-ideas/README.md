# Ideas Folder

> **Purpose:** Capture raw ideas exactly as spoken or typed, preserving original intent.

---

## What Goes Here

- **Verbatim voice transcriptions** — Unedited speech-to-text output
- **Raw text input** — Initial thoughts before refinement
- **Brainstorming notes** — Unstructured feature proposals
- **Feature sketches** — Early-stage concepts

---

## File Naming

```
{nn}-{idea-slug}.md
```

Examples:
- `01-voice-input-feature.md`
- `02-ai-chat-integration.md`
- `03-spec-management-software.md`

---

## Template

```markdown
# Idea: {Title}

**ID:** idea_{uuid}  
**Status:** draft | refined | promoted | archived  
**Source:** voice | text  
**Created:** {ISO8601}  
**Updated:** {ISO8601}  

---

## Raw Content

{Verbatim transcription or original text - preserve exactly as captured}

---

## Notes

{Optional clarifications, context, or references}

---

## Metadata

\`\`\`json
{
  "sourceType": "voice",
  "voiceModelId": "whisper-large-v3",
  "transcriptionConfidence": 0.95,
  "promotedToInstructionId": null
}
\`\`\`
```

---

## Lifecycle

```
1. CAPTURE → Draft idea saved
2. REFINE  → Proofread and clarify
3. PROMOTE → Convert to instruction in 02-instructions/
4. ARCHIVE → Mark as archived after promotion
```

---

## Related

- [Instructions Folder](../02-instructions/README.md) — Where promoted ideas go
- [Folder Structure Guideline](../../00-folder-structure-guideline.md) — Master organization guide
