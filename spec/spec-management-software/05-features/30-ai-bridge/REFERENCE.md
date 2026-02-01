# AI Bridge - External Specification Reference

> **Status:** Extracted to standalone spec  
> **Location:** `spec/ai-bridge/`  
> **Version:** 1.0.0

---

## Specification Location

This tool has been extracted to a standalone specification for independent development and AI training.

**Full Spec:** [`spec/ai-bridge/`](../../../ai-bridge/)

---

## Quick Reference

| Aspect | Value |
|--------|-------|
| Error Range | 9000-9999 |
| Port | 8089 |
| Language | Golang |
| Modes | Binary, Daemon |

---

## Core Capabilities

| Feature | Description |
|---------|-------------|
| Multi-format Input | Markdown, JSON, YAML, CSV |
| LLM Backends | Ollama, llama.cpp, OpenAI-compatible |
| Dual Execution | CLI binary + background daemon |
| Rate Limiting | Request queue with throttling |

---

## Usage in Spec Management Software

AI Bridge provides:
- Unified LLM interface for AI-powered features
- Prompt processing from structured files
- Background service for async AI operations
- Abstraction over multiple LLM providers

The spec-management-software integrates with AI Bridge via:
1. Direct binary invocation for one-off prompts
2. Daemon REST API for real-time AI features
3. WebSocket for streaming responses

---

*Reference created 2026-02-01 during spec extraction*
