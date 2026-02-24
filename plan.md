# Future Work Roadmap

> **Location:** `plan.md` (repository root)  
> **Created:** 2026-02-24  
> **Purpose:** Prioritized backlog for AI handoff — defines what to build next

---

## Phase A: Code Quality & Migration (Current Priority)

### ~~A1. Define() Alias Caller Migration~~ ✅ ALREADY COMPLETE
- **Verified:** 2026-02-24 — No `constants.php` or `constants-compat.php` exists; all 53 enums in active use (354 ActionType refs, 285 EndpointType refs across 23+ files)

### ~~A2. Remove Define() Aliases from constants.php~~ ✅ ALREADY COMPLETE
- **Verified:** 2026-02-24 — File already deleted; no `require` references remain

### A3. Go Backend interface{} Type Safety
- **Objective:** Replace ~2,680 `interface{}` instances across 58 Go files with proper generics or typed parameters
- **Dependencies:** None (independent track)
- **Expected Outputs:** Type-safe Go code; reduced runtime panic risk
- **Acceptance Criteria:**
  - `go build` passes with zero errors
  - `go vet` clean
  - No `interface{}` in public API signatures
- **Estimated Effort:** 6–8 tasks

### A4. Naming Convention Phases 5-6
- **Objective:** Hook/path compliance and cleanup for PHP naming conventions
- **Dependencies:** None
- **Expected Outputs:** Consistent hook names and file paths
- **Acceptance Criteria:** Lint scripts pass; grep confirms zero violations

---

## Phase B: Template Magic String Elimination

### ~~B1. admin-agents.php JS Constants Block~~ ✅ ALREADY COMPLETE
- **Verified:** 2026-02-24 — All constant blocks (`ENDPOINTS`, `AGENT_STATUS`, `STATUS`, `RESPONSE_KEYS`, `PLUGIN_STATUS`, `ACTIONS`, `LABELS`) present at lines 290–355; zero magic strings in JS body

### B2. admin-snapshots.php SNAP_ENDPOINTS + SNAP_LABELS
- **Objective:** Add `SNAP_ENDPOINTS` and `SNAP_LABELS` JS constant blocks; replace ~30 strings
- **Dependencies:** `EndpointType` import added
- **Expected Outputs:** Fully enumified inline JS in `admin-snapshots.php`
- **Acceptance Criteria:** Zero hardcoded REST paths or UI labels in `<script>` section

---

## Phase C: Go Backend Standards

### C1. Go Phase 4 — Positive Logic & Boolean Standards
- **Objective:** Enforce `IsValid` (not `IsNotValid`) naming; eliminate negations
- **Dependencies:** None
- **Expected Outputs:** Consistent boolean naming across Go codebase
- **Acceptance Criteria:** `lint-negative.sh` passes with zero violations
- **Estimated Effort:** 2 tasks

### C2. Go Phase 5 — Code Organization Standards
- **Objective:** Package restructuring, file naming, import organization
- **Dependencies:** C1 (preferred but not blocking)
- **Expected Outputs:** Clean package structure
- **Estimated Effort:** 3 tasks

### C3. Go Phase 6 — CI Lint Scripts & Integration
- **Objective:** Complete lint script suite (5 scripts) + CI pipeline integration
- **Dependencies:** C1, C2
- **Expected Outputs:** Automated CI quality gates
- **Estimated Effort:** 2 tasks

---

## Phase D: New Features

### D1. Licensing System Architecture (Phase 5 from active.md)
- **Objective:** License server + WP plugin client for commercial distribution
- **Dependencies:** Decision needed: Build custom (Go) vs Keygen.sh vs LemonSqueezy vs EDD
- **Expected Outputs:** License validation, activation/deactivation, feature gating
- **Acceptance Criteria:** License can be issued, validated, and revoked; WP plugin checks license on activation
- **Estimated Effort:** 8–10 tasks

### D2. E2 Activity Feed (Fleet-Wide Audit Log)
- **Objective:** Implement the fleet-wide activity audit log per `spec/11-e2-activity-feed/`
- **Dependencies:** Go endpoint spec exists (e2.1)
- **Expected Outputs:** Go endpoint + React UI for activity feed
- **Acceptance Criteria:** Per spec acceptance criteria

### D3. Batch G — Snake_case Log Context Keys to camelCase
- **Objective:** Convert remaining snake_case log context array keys to camelCase across ~15 PHP files
- **Dependencies:** None
- **Expected Outputs:** Consistent camelCase log keys
- **Acceptance Criteria:** Grep confirms zero snake_case log context keys

---

## Phase E: Architecture Decisions (Open Questions)

These require user decisions before implementation:

| # | Question | Options | Impact |
|---|----------|---------|--------|
| 1 | Remote Plugin Backups — store on WP site or download locally? | WP site / Local / Both | Backup service architecture |
| 2 | Bulk Quick Publish — add "Quick Publish Selected" for multiple plugins? | Yes / No | UI + publish pipeline changes |
| 3 | True Diff Comparison — compare with remote files for accurate counts? | Yes / No | Sync service enhancement |
| 4 | Licensing — build custom or use third-party? | Custom Go / Keygen.sh / LemonSqueezy / EDD | Phase D1 architecture |

---

## Next Task Selection

**Ready to implement now (no blockers):**

1. ~~**B1 — admin-agents.php Magic Strings**~~ ✅ Already complete
2. **B2 — admin-snapshots.php Magic Strings** — ~30 endpoint + label replacements
3. **A3 — Go interface{} Type Safety** — Large but independent; 2,680 instances
4. **C1 — Go Positive Logic** — Small, self-contained; 2 tasks
5. **D3 — Snake_case Log Keys** — Small, ~15 files; no dependencies

**Requires user decision first:**
- D1 — Licensing (needs build-vs-buy decision)
- D2 — Activity Feed (needs priority confirmation)

---

*Ask the user which task to implement next.*
