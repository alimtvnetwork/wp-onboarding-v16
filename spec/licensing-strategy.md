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

### Step 1 — Local Integrity (Combined Hash Fingerprint)

```
Base ZIP  →  SHA-256(base.zip)   = hash_base
Pro ZIP   →  SHA-256(pro.zip)    = hash_pro
Combined  →  hash(hash_base + hash_pro) = build_fingerprint
```

The **build fingerprint** must match the expected value from the update server. This ensures:
- Base and Pro versions are from the same build
- No version mismatch between tiers
- Package integrity (no tampering)

### Step 2 — Remote License Validation

After the fingerprint passes, the plugin contacts the Go license server to validate the license key (activation status, site count, expiry, etc.).

## Architecture Decisions (Pending)

- Remote validation with local cache (Hybrid approach) — confirmed direction
- Grace period duration: TBD (3–7 days recommended)
- Cache TTL: TBD (24–72h recommended)
- Pro feature gating mechanism: TBD
- Go server endpoint design: TBD
