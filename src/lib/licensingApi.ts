// Licensing server API client.
// Talks directly to the licensing server (separate from main Go backend).
// Uses Bearer token auth via VITE_LICENSING_ADMIN_TOKEN.

import type {
  License,
  AuditLog,
  CreateLicenseInput,
  UpdateLicenseInput,
} from "@/types/licensing";

const LICENSING_PREFIX = "/api/v1/admin";

function getBaseUrl(): string {
  const raw = (import.meta.env.VITE_LICENSING_URL as string | undefined) || "";
  return raw ? raw.replace(/\/$/, "") : "";
}

function getAdminToken(): string {
  return (import.meta.env.VITE_LICENSING_ADMIN_TOKEN as string | undefined) || "";
}

function buildUrl(path: string): string {
  return `${getBaseUrl()}${LICENSING_PREFIX}${path}`;
}

function authHeaders(): HeadersInit {
  const token = getAdminToken();
  return {
    "Content-Type": "application/json",
    ...(token ? { Authorization: `Bearer ${token}` } : {}),
  };
}

async function handleResponse<T>(res: Response): Promise<T> {
  if (!res.ok) {
    const body = await res.text();
    let message = `Licensing API error (${res.status})`;
    try {
      const parsed = JSON.parse(body);
      if (parsed.error) message = parsed.error;
    } catch {
      // use default message
    }
    throw new Error(message);
  }
  return res.json();
}

// ── License CRUD ────────────────────────────────────────

export async function listLicenses(): Promise<License[]> {
  const res = await fetch(buildUrl("/licenses"), { headers: authHeaders() });
  return handleResponse<License[]>(res);
}

export async function getLicense(id: number): Promise<License> {
  const res = await fetch(buildUrl(`/licenses/${id}`), { headers: authHeaders() });
  return handleResponse<License>(res);
}

export async function createLicense(input: CreateLicenseInput): Promise<License> {
  const res = await fetch(buildUrl("/licenses"), {
    method: "POST",
    headers: authHeaders(),
    body: JSON.stringify(input),
  });
  return handleResponse<License>(res);
}

export async function updateLicense(id: number, input: UpdateLicenseInput): Promise<License> {
  const res = await fetch(buildUrl(`/licenses/${id}`), {
    method: "PATCH",
    headers: authHeaders(),
    body: JSON.stringify(input),
  });
  return handleResponse<License>(res);
}

export async function deleteLicense(id: number): Promise<void> {
  const res = await fetch(buildUrl(`/licenses/${id}`), {
    method: "DELETE",
    headers: authHeaders(),
  });
  if (!res.ok) {
    throw new Error(`Failed to delete license (${res.status})`);
  }
}

// ── Audit Logs ──────────────────────────────────────────

export async function listAuditLogs(params?: {
  action?: string;
  license_id?: number;
}): Promise<AuditLog[]> {
  const url = new URL(buildUrl("/audit"));
  if (params?.action) url.searchParams.set("action", params.action);
  if (params?.license_id) url.searchParams.set("license_id", String(params.license_id));

  const res = await fetch(url.toString(), { headers: authHeaders() });
  return handleResponse<AuditLog[]>(res);
}

// ── Health Check ────────────────────────────────────────

export async function checkHealth(): Promise<{ status: string; service: string }> {
  const base = getBaseUrl();
  const res = await fetch(`${base}/api/v1/health`);
  return handleResponse<{ status: string; service: string }>(res);
}
