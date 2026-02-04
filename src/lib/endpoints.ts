// Centralized endpoint resolution for API + WebSocket.
//
// ENV (recommended):
// - VITE_API_URL="http://localhost:8080"   (origin only)
// - VITE_WS_URL="ws://localhost:8080/ws"  (full ws URL)

const API_PREFIX = "/api/v1";

function isLoopbackHost(hostname: string): boolean {
  const host = hostname.toLowerCase();
  return host === "localhost" || host === "127.0.0.1" || host === "::1";
}

function normalizeHttpOrigin(value: string): string {
  const raw = value.trim();
  if (!raw) return raw;

  // Already has scheme
  if (/^https?:\/\//i.test(raw)) return raw.replace(/\/$/, "");

  // Protocol-relative URL
  if (raw.startsWith("//")) {
    const proto = typeof window !== "undefined" ? window.location.protocol : "http:";
    return `${proto}${raw}`.replace(/\/$/, "");
  }

  // Bare host:port
  return `http://${raw}`.replace(/\/$/, "");
}

function normalizeWsUrl(value: string): string {
  const raw = value.trim();
  if (!raw) return raw;
  if (/^wss?:\/\//i.test(raw)) return raw;
  if (raw.startsWith("//")) {
    const proto = typeof window !== "undefined" && window.location.protocol === "https:" ? "wss:" : "ws:";
    return `${proto}${raw}`;
  }
  return `ws://${raw}`;
}

function shouldIgnoreLoopbackTarget(targetUrl: string): boolean {
  if (typeof window === "undefined") return false;

  // If the UI is not running on localhost, the browser cannot reach the user's
  // local machine via `localhost`.
  if (isLoopbackHost(window.location.hostname)) return false;

  try {
    const url = new URL(targetUrl);
    return isLoopbackHost(url.hostname);
  } catch {
    return false;
  }
}

export function resolveApiOrigin(): string | undefined {
  const raw = (import.meta.env.VITE_API_URL as string | undefined) || "";
  if (!raw) return undefined;

  const normalized = normalizeHttpOrigin(raw);
  if (!normalized) return undefined;

  // Safety: ignore localhost targets when running in a hosted preview domain.
  if (shouldIgnoreLoopbackTarget(normalized)) return undefined;

  return normalized;
}

export function resolveApiBase(): string {
  const origin = resolveApiOrigin();
  if (!origin) return API_PREFIX;
  return `${origin.replace(/\/$/, "")}${API_PREFIX}`;
}

/** Returns a fetch-ready URL (relative or absolute). */
export function resolveApiUrl(endpoint: string): string {
  if (!endpoint.startsWith("/")) {
    throw new Error(`API endpoint must start with '/': ${endpoint}`);
  }
  return `${resolveApiBase()}${endpoint}`;
}

/** Returns an absolute URL string for display/debugging. */
export function toAbsoluteUrl(urlOrPath: string): string {
  if (/^https?:\/\//i.test(urlOrPath)) return urlOrPath;
  if (typeof window === "undefined") return urlOrPath;
  return new URL(urlOrPath, window.location.origin).toString();
}

export function resolveWsUrl(): string {
  const envUrl = import.meta.env.VITE_WS_URL as string | undefined;
  if (envUrl) {
    const normalized = normalizeWsUrl(envUrl);
    if (normalized && !shouldIgnoreLoopbackTarget(normalized.replace(/^ws/i, "http"))) {
      return normalized;
    }
  }

  // During tests / SSR-like environments
  if (typeof window === "undefined") {
    return "ws://localhost:8080/ws";
  }

  // If API origin is configured, derive WS from it unless explicitly overridden.
  const apiOrigin = resolveApiOrigin();
  if (apiOrigin) {
    try {
      const url = new URL(apiOrigin);
      const wsProto = url.protocol === "https:" ? "wss:" : "ws:";
      return `${wsProto}//${url.host}/ws`;
    } catch {
      // fall through
    }
  }

  const protocol = window.location.protocol === "https:" ? "wss:" : "ws:";
  return `${protocol}//${window.location.host}/ws`;
}
