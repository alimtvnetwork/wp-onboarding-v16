// TypeScript types for the licensing admin dashboard.

// ── Enum-aligned string unions ──────────────────────────

export type LicenseStatus = "active" | "expired" | "suspended" | "revoked";
export type LicenseType = "standard" | "professional" | "enterprise";
export type ProductType = "riseup-uploader";
export type AuditAction =
  | "created"
  | "activated"
  | "deactivated"
  | "validated"
  | "expired"
  | "revoked"
  | "updated"
  | "deleted";

// ── Domain models (match Go JSON output) ────────────────

export interface License {
  id: number;
  key: string;
  email: string;
  product: ProductType;
  type: LicenseType;
  status: LicenseStatus;
  max_activations: number;
  notes?: string;
  created_at: string;
  expires_at?: string | null;
  updated_at: string;
}

export interface Activation {
  id: number;
  license_id: number;
  domain: string;
  ip_address?: string;
  user_agent?: string;
  activated_at: string;
  deactivated_at?: string | null;
}

export interface AuditLog {
  id: number;
  license_id?: number | null;
  action: AuditAction;
  domain?: string;
  ip_address?: string;
  details?: unknown;
  created_at: string;
}

// ── Request DTOs ────────────────────────────────────────

export interface CreateLicenseInput {
  email: string;
  product: ProductType;
  type: LicenseType;
  maxActivations: number;
  notes?: string;
}

export interface UpdateLicenseInput {
  status?: LicenseStatus;
  type?: LicenseType;
  maxActivations?: number;
  notes?: string;
}

// ── License + activations (status endpoint) ─────────────

export interface LicenseWithActivations {
  license: License;
  activations: Activation[];
}
