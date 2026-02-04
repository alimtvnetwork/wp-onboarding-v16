// Centralized endpoint resolution for API + WebSocket.
//
// ENV (recommended):
// - VITE_API_URL="http://localhost:8080"   (origin only)
// - VITE_WS_URL="ws://localhost:8080/ws"  (full ws URL)

const API_PREFIX = "/api/v1";

export function resolveApiOrigin(): string | undefined {
  return (import.meta.env.VITE_API_URL as string | undefined) || undefined;
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
  if (envUrl) return envUrl;

  // During tests / SSR-like environments
  if (typeof window === "undefined") {
    return "ws://localhost:8080/ws";
  }

  const protocol = window.location.protocol === "https:" ? "wss:" : "ws:";
  return `${protocol}//${window.location.host}/ws`;
}
