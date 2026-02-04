// Centralized diagnostics utility for support and debugging

import { resolveApiBase, resolveApiOrigin, resolveWsUrl, toAbsoluteUrl } from "@/lib/endpoints";

export interface DiagnosticsInfo {
  appName: string;
  appVersion: string;
  gitCommit?: string;
  buildTime?: string;
  scriptVersion?: string;
  // Raw environment variable values (exactly as set, or "(not set)")
  envViteApiUrl: string;
  envViteWsUrl: string;
  // Resolved/effective values
  resolvedApiOrigin: string | null;
  effectiveUiOrigin: string;
  apiBase: string;
  apiBaseAbsolute: string;
  wsUrl: string;
  // Environment
  userAgent: string;
  timestamp: string;
}

export function getDiagnostics(versionInfo?: {
  appName?: string;
  version?: string;
  gitCommit?: string;
  buildTime?: string;
  scriptVersion?: string;
}): DiagnosticsInfo {
  const apiBase = resolveApiBase();
  const resolvedApiOrigin = resolveApiOrigin();

  // Raw environment variable values
  const envViteApiUrl = (import.meta.env.VITE_API_URL as string | undefined) || "(not set)";
  const envViteWsUrl = (import.meta.env.VITE_WS_URL as string | undefined) || "(not set)";

  return {
    appName: versionInfo?.appName || "WP Plugin Publish",
    appVersion: versionInfo?.version || "0.0.0",
    gitCommit: versionInfo?.gitCommit,
    buildTime: versionInfo?.buildTime,
    scriptVersion: versionInfo?.scriptVersion,
    // Raw env vars
    envViteApiUrl,
    envViteWsUrl,
    // Resolved values
    resolvedApiOrigin: resolvedApiOrigin || null,
    effectiveUiOrigin: typeof window !== "undefined" ? window.location.origin : "N/A",
    apiBase,
    apiBaseAbsolute: toAbsoluteUrl(apiBase),
    wsUrl: resolveWsUrl(),
    // Environment
    userAgent: typeof navigator !== "undefined" ? navigator.userAgent : "N/A",
    timestamp: new Date().toISOString(),
  };
}

export function formatDiagnosticsForCopy(info: DiagnosticsInfo): string {
  const lines = [
    `=== ${info.appName} Diagnostics ===`,
    `App Version: v${info.appVersion}`,
  ];

  if (info.gitCommit) {
    lines.push(`Git Commit: ${info.gitCommit}`);
  }
  if (info.buildTime) {
    lines.push(`Build Time: ${info.buildTime}`);
  }
  if (info.scriptVersion) {
    lines.push(`Script Version: v${info.scriptVersion}`);
  }

  lines.push("");
  lines.push("--- Environment Variables (raw) ---");
  lines.push(`VITE_API_URL: ${info.envViteApiUrl}`);
  lines.push(`VITE_WS_URL: ${info.envViteWsUrl}`);

  lines.push("");
  lines.push("--- Resolved/Effective URLs ---");
  lines.push(`UI Origin: ${info.effectiveUiOrigin}`);
  lines.push(`Resolved API Origin: ${info.resolvedApiOrigin || "(same-origin / not set)"}`);
  lines.push(`API Base (relative): ${info.apiBase}`);
  lines.push(`API Base (absolute): ${info.apiBaseAbsolute}`);
  lines.push(`WebSocket URL: ${info.wsUrl}`);

  lines.push("");
  lines.push("--- Environment ---");
  lines.push(`User Agent: ${info.userAgent}`);
  lines.push(`Timestamp: ${info.timestamp}`);

  return lines.join("\n");
}
