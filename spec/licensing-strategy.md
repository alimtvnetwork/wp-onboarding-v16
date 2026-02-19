# Licensing Strategy — Future Implementation

> **Status:** Planning / Not yet implemented  
> **Updated:** 2026-02-19

## Overview

Two-tier plugin distribution (Base + Pro) with a two-step validation flow combining local integrity verification and remote license validation.

## Plugin Tiers

| Tier | Distribution | Features |
|------|-------------|----------|
| **Base** | Free / standard | Core uploader functionality |
| **Pro** | Licensed | Snapshots, remote plugin activation, advanced features |

Both tiers share the **same version number** (e.g., `1.53.0`).

## Two-Step Validation Flow

### Step 1 — Local Integrity (File Manifest Hash)

Instead of hashing ZIP files, the system uses a **file manifest approach**:

```
1. Scan plugin directory → build sorted file list (JSON manifest)
2. Hash each file's contents → SHA-256
3. Combine file hashes in deterministic order → final manifest hash
4. Compare manifest hash against server-provided expected hash
```

**Manifest JSON structure (conceptual):**
```json
{
  "version": "1.53.0",
  "tier": "pro",
  "files": [
    { "path": "includes/Admin/AdminPage.php", "hash": "a1b2c3..." },
    { "path": "includes/Core/Bootstrap.php", "hash": "d4e5f6..." }
  ],
  "manifest_hash": "final_combined_hash"
}
```

**Ordering rules:**
- Files sorted alphabetically by relative path
- Deterministic traversal (depth-first, sorted)
- Excludes: `.git/`, `node_modules/`, transient/cache files

**Computation trigger:** First activation or post-update initialization.

**Per-tier hashes:**
```
Base files  →  manifest_hash_base
Pro files   →  manifest_hash_pro
Combined    →  SHA-256(manifest_hash_base + manifest_hash_pro) = build_fingerprint
```

### Step 2 — Remote License Validation

After the fingerprint passes, the plugin contacts the Go license server to validate the license key (activation status, site count, expiry, etc.).

---

## Security Analysis

### Attack Vectors & Vulnerabilities

| Attack | Risk | Mitigation |
|--------|------|------------|
| **Patch hash-check PHP code** | 🔴 HIGH — attacker modifies `verifyManifest()` to return `true` | Code obfuscation, multiple scattered checks |
| **Modify manifest JSON** | 🟡 MEDIUM — attacker regenerates manifest with tampered files | Server-side manifest comparison |
| **First-load race condition** | 🟡 MEDIUM — files modified before first integrity check | Immediate check on activation, not lazy |
| **Decompile & remove license logic** | 🔴 HIGH — PHP is plain text, trivially editable | Cannot fully prevent; server-gated features mitigate |
| **Share license key across sites** | 🟢 LOW — remote validation enforces site count | Already handled by remote validation |

### Core Weakness

**Any client-side integrity check in PHP can be bypassed** because PHP source is plaintext. An attacker can simply edit the verification function. This is an inherent limitation of all WordPress plugin licensing — even major plugins (ACF, Elementor, WooCommerce) face this.

---

## Suggested Hardening Approaches

### 1. Server-Gated Feature Delivery (Strongest)
Don't ship Pro code in the plugin at all. Pro features are fetched as encrypted code fragments from the server, decrypted with a session key tied to the license. **Unbreakable without valid license** but adds complexity and server dependency.

### 2. License-Key-Encrypted Pro Modules
Pro PHP files are shipped AES-encrypted. The decryption key is derived from the license key + site URL. Without a valid key, Pro code is gibberish. **Strong** but adds runtime decryption overhead.

### 3. Scattered Integrity Checks (Practical)
Instead of one `verifyManifest()` call, embed integrity checks in 10–15 random locations throughout the codebase. Each checks a different subset of files. Attacker must find and patch ALL of them. **Not unbreakable** but raises the effort significantly.

### 4. Server-Side Feature Flags (Recommended Complement)
Pro features require a valid `feature_token` from the server (short-lived, 1–4h TTL). Even if local checks are bypassed, Pro features call the server for authorization tokens. **Strong complement** to local checks.

### 5. Telemetry + Anomaly Detection
Plugin phones home with manifest hash periodically. If the server detects a hash mismatch (tampered files) with a valid license, flag the license for review. **Passive detection**, doesn't prevent but enables enforcement.

---

## Recommended Layered Strategy

```
Layer 1: File manifest hash (deters casual piracy)
Layer 2: Remote license validation (enforces keys/sites)
Layer 3: Server-side feature tokens (Pro features need live auth)
Layer 4: Scattered integrity checks (raises bypass effort)
Layer 5: Telemetry anomaly detection (catches tamperers post-facto)
```

## Architecture Decisions (Pending)

- Remote validation with local cache (Hybrid approach) — confirmed direction
- Grace period duration: TBD (3–7 days recommended)
- Cache TTL: TBD (24–72h recommended)
- Pro feature gating mechanism: TBD (server-gated vs encrypted vs flags)
- Go server endpoint design: TBD
- Manifest generation: on activation / post-update
- File exclusion rules for manifest: TBD
