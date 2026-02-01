# Glossary

**Version:** 1.0.0  
**Last Updated:** 2026-01-27  
**Status:** Reference Document  

---

## Purpose

This glossary defines all technical terms, acronyms, and domain-specific vocabulary used throughout the Spec Management Software specification suite.

---

## Acronyms

| Acronym | Expansion | Definition |
|---------|-----------|------------|
| API | Application Programming Interface | Contract defining how software components communicate |
| CRUD | Create, Read, Update, Delete | Four basic database operations |
| CSS | Cascading Style Sheets | Stylesheet language for visual presentation |
| DTO | Data Transfer Object | Object carrying data between processes |
| E2E | End-to-End | Testing approach covering complete user workflows |
| FS | File System | Operating system component managing files |
| GPU | Graphics Processing Unit | Processor optimized for parallel computation |
| HSL | Hue, Saturation, Lightness | Color model used in CSS |
| HTTP | Hypertext Transfer Protocol | Foundation protocol for web communication |
| HTTPS | HTTP Secure | Encrypted version of HTTP |
| ICU | International Components for Unicode | Library for internationalization |
| IDE | Integrated Development Environment | Software for writing code |
| JSON | JavaScript Object Notation | Lightweight data interchange format |
| JWT | JSON Web Token | Compact token format for authentication |
| LLM | Large Language Model | AI model trained on text data |
| LSP | Language Server Protocol | Protocol for IDE language features |
| MSW | Mock Service Worker | API mocking library for testing |
| ORM | Object-Relational Mapping | Technique bridging objects and databases |
| PCM | Pulse Code Modulation | Digital audio representation format |
| POM | Page Object Model | Design pattern for E2E test organization |
| PR | Pull Request | Code review mechanism in Git workflows |
| REST | Representational State Transfer | Architectural style for web APIs |
| RPC | Remote Procedure Call | Protocol for executing remote functions |
| RTL | Right-to-Left | Text direction for certain languages |
| SDK | Software Development Kit | Tools for building applications |
| SPA | Single Page Application | Web app loading a single HTML page |
| SQL | Structured Query Language | Language for database operations |
| SSE | Server-Sent Events | HTTP-based unidirectional streaming |
| SSH | Secure Shell | Protocol for secure remote access |
| TDD | Test-Driven Development | Writing tests before implementation |
| TLS | Transport Layer Security | Cryptographic protocol for security |
| UI | User Interface | Visual elements users interact with |
| URL | Uniform Resource Locator | Web address format |
| UUID | Universally Unique Identifier | 128-bit identifier standard |
| UX | User Experience | Overall experience using a product |
| WCAG | Web Content Accessibility Guidelines | Accessibility standards |
| WebM | Web Media | Open media container format |
| WYSIWYG | What You See Is What You Get | Editor showing final appearance |

---

## Technical Terms

### A

**Argon2id**  
A memory-hard password hashing algorithm recommended for secure credential storage. Combines Argon2i (side-channel resistant) and Argon2d (GPU-resistant).

**Auto-save**  
Feature that automatically persists document changes at regular intervals without explicit user action.

### B

**Bcrypt**  
Password hashing function based on the Blowfish cipher. Used as a fallback when Argon2id is unavailable.

**Bearer Token**  
Authentication scheme where a token is passed in the HTTP Authorization header.

### C

**Commit Queue**  
Debounced buffer that batches rapid file changes before creating a single Git commit.

**Context Window**  
Maximum number of tokens an LLM can process in a single request.

**CORS (Cross-Origin Resource Sharing)**  
HTTP mechanism allowing browsers to make cross-origin requests safely.

### D

**Debounce**  
Technique delaying function execution until a specified time has passed since the last invocation.

**Diff View**  
Side-by-side or unified display showing differences between two versions of a document.

### E

**Edge Function**  
Serverless function deployed at network edge for low-latency execution.

**Entity**  
Database model representing a domain object (e.g., Project, File, Snapshot).

### F

**Feature Flag**  
Configuration toggle enabling/disabling features without code deployment.

**Folder Tree**  
Hierarchical UI component displaying directory structure with expand/collapse capability.

### G

**Git Flow**  
Branching model using feature, develop, release, and main branches.

**go-git**  
Pure Go implementation of Git for programmatic repository operations.

### H

**Health Check**  
Endpoint returning system component status for monitoring and load balancing.

**History System**  
Mechanism for creating, storing, and restoring document snapshots.

### I

**Intent Analysis**  
AI stage that interprets user input and generates clarifying questions.

### J

**JSON Schema**  
Vocabulary for annotating and validating JSON documents.

### L

**LLaMA**  
Large Language Model Meta AI - open-source language model family.

**llama.cpp**  
C++ implementation for running LLaMA models efficiently on consumer hardware.

**Live Preview**  
Real-time rendering of Markdown content as the user types.

### M

**Markdown**  
Lightweight markup language for creating formatted text using plain text syntax.

**MediaRecorder**  
Web API for recording audio and video streams in the browser.

**Migration**  
Database schema change applied incrementally to evolve the data model.

**Mixtral**  
Mixture-of-experts language model optimized for reasoning tasks.

### P

**Page Object Model (POM)**  
Test design pattern encapsulating page elements and actions in reusable classes.

**PascalCase**  
Naming convention where each word starts with uppercase (e.g., `UserProfile`).

**Playwright**  
Cross-browser automation library for end-to-end testing.

### R

**Rate Limiting**  
Mechanism controlling request frequency to prevent abuse.

**Repository Pattern**  
Design pattern abstracting data access behind a collection-like interface.

**Rollback**  
Reverting system state to a previous known-good configuration.

### S

**Service Layer**  
Application layer containing business logic, sitting between API and data access.

**Snapshot**  
Point-in-time copy of a file stored in the `.history` directory.

**SQLite**  
Self-contained, serverless SQL database engine stored in a single file.

**SSE (Server-Sent Events)**  
Technology enabling servers to push updates to clients over HTTP.

**Streaming**  
Delivering content incrementally rather than waiting for complete response.

### T

**testify**  
Go testing toolkit providing assertions, mocking, and test suites.

**Token**  
Basic unit of text processed by language models (roughly 4 characters in English).

**Transcription**  
Converting spoken audio into written text.

### V

**Vitest**  
Vite-native unit testing framework for JavaScript/TypeScript.

**Voice Input**  
Feature enabling spoken commands to be converted to specification text.

### W

**Waveform Visualizer**  
UI component displaying audio amplitude as an animated graphic.

**Whisper**  
OpenAI's automatic speech recognition model for voice transcription.

---

## Domain Terms

### Project

Top-level organizational unit in the spec hierarchy. Corresponds to a folder in the `spec/` root directory.

### Category

Grouping mechanism for related projects (e.g., "WordPress Plugin" containing multiple plugin specs).

### Spec File

Individual Markdown document containing a specification section.

### Spec Root

The `spec/` directory serving as the root for all managed specifications.

### Reasoning Chain

Multi-step AI process: Voice → Intent → Questions → Generation.

### Clarifying Questions

AI-generated prompts to resolve ambiguity before spec generation.

---

## System Architecture Components

> *Terms defined below correspond to components in the [System Architecture Overview](../09-diagrams/00-system-architecture-overview.md).*

### User Layer

**Voice Input**  
Audio capture mechanism using the browser's MediaRecorder API. Captures user speech for transcription into specification text. Supports configurable audio formats (WebM/Opus, WAV) and chunk sizes (1-5 seconds).

**Text Input**  
Direct text entry alternative to voice input. Accepts typed specification content, ideas, or instructions through a React textarea component.

**React Frontend**  
Single-page application built with React 18+, TypeScript, and TailwindCSS. Provides the user interface for project navigation, markdown editing, voice capture, and AI interaction.

### API Layer

**REST API**  
RESTful endpoints following the `{success, data, error, meta}` envelope pattern. Handles synchronous request/response operations for CRUD, file management, and generation triggers.

**WebSocket/SSE**  
Real-time communication channels for streaming AI responses, progress updates, and live transcription. SSE (Server-Sent Events) used for unidirectional server-to-client streaming.

### Core Services

**Instruction Pipeline**  
Five-stage processing pipeline transforming voice/text input into specification artifacts: Transcription → Proofreading → Planning → Execution → Persistence. See [Diagram 03](../09-diagrams/03-instruction-builder-pipeline.md).

**Transcription Service**  
Converts audio input to text using ElevenLabs Scribe v2 or Whisper models. Provides confidence scores and quality checks with a 0.85 threshold for acceptance.

**Proofreading Service**  
LLM-powered text refinement that corrects grammar, improves clarity, normalizes technical terms, and classifies content type (idea, feature, task, codingGuideline, instruction).

**Planning Service**  
Reasoning model stage that decomposes refined input into actionable tasks, generates structured plans, and detects potential inconsistencies before execution.

**Execution Engine**  
Final generation stage that produces specification artifacts in Markdown and JSON formats, applies acceptance criteria, and triggers filesystem persistence.

**Prompt System**  
Two-component system managing prompt composition. Consists of Preset Manager (loads base prompts from repository/database) and Prompt Composer (merges layers and interpolates variables). See [Diagram 04](../09-diagrams/04-prompt-preset-layering.md).

**Preset Manager**  
Loads and manages base prompt templates from the `Prompts/` repository folder. Handles seeding to database, version tracking, and default selection per content type.

**Prompt Composer**  
Assembles final prompts by layering: Base Preset → User Override → Runtime Variables → User Content. Supports APPEND, PREPEND, REPLACE, and MERGE modes.

**Quality Loop**  
Three-component validation system: Issue Detector (finds ambiguities/conflicts), Question Generator (creates UI-friendly clarification prompts), and Regenerator (re-executes with refined context). See [Diagram 05](../09-diagrams/05-inconsistency-clarification-workflow.md).

**Issue Detector**  
Analyzes generated artifacts using a reasoning model to identify ambiguities, conflicts, missing information, and incomplete sections. Classifies issues into four phases: Critical, Conflict, Clarification, Enhancement.

**Question Generator**  
Transforms detected issues into structured UI questions with appropriate input controls (radio, checkbox, text, number). Groups questions by phase for phased user interaction.

**Regenerator**  
Re-executes the generation pipeline with answers compiled into refinement prompts. Injects clarifications as constraints and validates the new artifact version.

### AI Layer

**Speech-to-Text (STT)**  
Audio transcription service powered by ElevenLabs Scribe v2. Provides batch transcription for complete files and real-time streaming via WebSocket for live input.

**Reasoning Model (LLM)**  
Large language model for complex reasoning tasks: proofreading, planning, generation, and issue detection. Supports LLaMA (local via llama.cpp) and Gemini (cloud API).

**Embedding Model**  
Generates 768-dimensional vector representations of text chunks for semantic search. Powers the RAG retrieval system for context injection.

### Data Layer

**SQLite Database**  
Single-file relational database storing projects, files, snapshots, artifacts, presets, users, and RAG metadata. Uses GORM for ORM operations with PascalCase column names.

**Filesystem Storage**  
Directory structure under `Spec/` root containing project folders with `ideas/` and `instructions/` subdirectories. Files follow `NN-type-slug.md` naming convention.

**Vector Store**  
Embedding storage for RAG retrieval. Stores chunk vectors with metadata for similarity search using cosine distance.

**RAG System**  
Retrieval-Augmented Generation pipeline for injecting relevant context into generation prompts. Consists of Indexer (watches files), Chunker (splits content), Embedder (generates vectors), and Retriever (searches and reranks). See [Diagram 02](../09-diagrams/02-rag-retrieval-flow.md).

**Indexer**  
File watcher component that detects new/modified Markdown files and triggers the ingestion pipeline. Maintains registry of indexed files with hashes for change detection.

**Chunker**  
Splits Markdown content into semantically coherent chunks (100-500 words) at heading boundaries. Generates stable chunk IDs and preserves heading anchors.

**Retriever**  
Performs vector similarity search against the embedding store. Returns top-K relevant chunks with optional cross-encoder reranking for improved precision.

### Output Layer

**Generated Specs**  
Final specification artifacts saved to the filesystem in Markdown format with optional JSON companion files. Includes generated sections, acceptance criteria, and cross-references.

**History/Versions**  
Snapshot tracking system storing previous versions in `.history/` directory with `{timestamp}_{hash}.md` naming. Linked to Git commits for full traceability.

**Git Commits**  
Version control integration via go-git. Auto-commits file changes with debounced batching and meaningful commit messages referencing artifact IDs.

### Integration Components

**Idea Promotion**  
Workflow for elevating ideas to instructions. Triggers re-indexing, applies promotion templates, and maintains sourceIdeaId linking for traceability. See [Diagram 01](../09-diagrams/01-idea-promotion-workflow.md).

**Context Assembly**  
RAG stage that merges retrieved chunks with pinned artifacts, deduplicates content, and formats as Markdown for prompt injection.

**Variable Interpolation**  
Template processing that replaces `{{variable}}` placeholders with runtime values (projectName, contentType, timestamp, userName).

**Diff View**  
UI component displaying before/after comparison of regenerated artifacts. Supports side-by-side, unified, and split-sync modes with line-level highlighting.

---

## Cross-References

- [Summary Document](./02-summary.md) - Project overview
- [Features Overview](../05-features/00-overview.md) - Consolidated spec architecture
- [System Architecture Overview](../09-diagrams/00-system-architecture-overview.md) - Master diagram
- [General Spec Standards](../../general-spec/00-overview.md) - Architecture guidelines

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0.0 | 2026-01-27 | Initial glossary creation |
| 2.0.0 | 2026-01-28 | Added 30+ System Architecture Component definitions from diagrams |
