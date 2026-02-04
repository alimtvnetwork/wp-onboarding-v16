// Centralized diagnostics utility for support and debugging

import { resolveApiBase, resolveApiOrigin, resolveWsUrl, toAbsoluteUrl } from "@/lib/endpoints";

export interface DiagnosticsInfo {
  appName: string;
  appVersion: string;
  gitCommit?: string;
  buildTime?: string;
  scriptVersion?: string;
  apiOrigin: string | null;
  apiBase: string;
  apiBaseAbsolute: string;
  wsUrl: string;
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
  const apiOrigin = resolveApiOrigin();

  return {
    appName: versionInfo?.appName || "WP Plugin Publish",
    appVersion: versionInfo?.version || "0.0.0",
    gitCommit: versionInfo?.gitCommit,
    buildTime: versionInfo?.buildTime,
    scriptVersion: versionInfo?.scriptVersion,
    apiOrigin: apiOrigin || null,
    apiBase,
    apiBaseAbsolute: toAbsoluteUrl(apiBase),
    wsUrl: resolveWsUrl(),
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
  lines.push("--- API Configuration ---");
  lines.push(`VITE_API_URL: ${info.apiOrigin || "(not set)"}`);
  lines.push(`API Base: ${info.apiBase}`);
  lines.push(`API Base (absolute): ${info.apiBaseAbsolute}`);
  lines.push(`WebSocket URL: ${info.wsUrl}`);
  lines.push("");
  lines.push("--- Environment ---");
  lines.push(`User Agent: ${info.userAgent}`);
  lines.push(`Timestamp: ${info.timestamp}`);

  return lines.join("\n");
}
