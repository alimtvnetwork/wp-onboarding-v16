import { CapturedError } from '@/stores/errorStore';
import { formatDateTimeUtc, toClipboardText, unescapeEmbeddedNewlines } from "@/lib/logText";

/** App metadata for report generation. */
interface AppInfo {
  appName: string;
  appVersion: string;
  gitCommit?: string;
  buildTime?: string;
}

/**
 * Generate a full Markdown error report from a CapturedError.
 * This is a pure function — no React components, no side effects.
 */
export function generateErrorReport(
  error: CapturedError,
  app?: AppInfo
): string {
  const appInfo = [
    `**App:** ${app?.appName || "WP Plugin Publish"} v${app?.appVersion || "0.0.0"}`,
  ];
  if (app?.gitCommit) {
    appInfo.push(`**Git Commit:** ${app.gitCommit.substring(0, 7)}`);
  }
  if (app?.buildTime) {
    appInfo.push(`**Build Time:** ${app.buildTime}`);
  }

  const triggerContext: string[] = [];
  if (error.triggerComponent) {
    triggerContext.push(`**Component:** ${error.triggerComponent}`);
  }
  if (error.triggerAction) {
    triggerContext.push(`**Action:** ${error.triggerAction}`);
  }
  if (error.context?.source) {
    triggerContext.push(`**Source:** ${error.context.source}`);
  }
  const triggerSection = triggerContext.length > 0 
    ? `### Trigger Context\n${triggerContext.join("\n")}\n` 
    : "";

  const chainSection = error.invocationChain && error.invocationChain.length > 0
    ? `### Invocation Chain\n\`\`\`\n${error.invocationChain.map((call, i) => 
        `${'  '.repeat(i)}${i > 0 ? '└─ ' : ''}${call}`
      ).join('\n')}\n\`\`\`\n`
    : "";

  const framesSection = error.parsedFrames && error.parsedFrames.length > 0
    ? `### Parsed Stack Frames\n| # | Function | File | Line |\n|---|----------|------|------|\n${
        error.parsedFrames.slice(0, 10).map((f, i) => 
          `| ${i + 1} | ${f.function} | ${f.file} | ${f.line} |`
        ).join('\n')
      }\n`
    : "";

  const backendLogsSection = error.backendLogs && error.backendLogs.length > 0
    ? `### Backend Execution Logs\n\`\`\`\n${
        error.backendLogs.map(l => {
          const base = `[${formatDateTimeUtc(l.timestamp)}] [${l.level.toUpperCase()}]${l.step ? ` [${l.step}]` : ''} ${unescapeEmbeddedNewlines(l.message)}`;
          if (l.details && Object.keys(l.details).length > 0) {
            return `${base}\n${unescapeEmbeddedNewlines(JSON.stringify(l.details, null, 2))}`;
          }
          return base;
        }).join('\n\n')
      }\n\`\`\`\n`
    : "";

  const backendStackSection = error.backendStackTrace
    ? `### Backend Stack Trace (Go)\n\`\`\`\n${error.backendStackTrace}\n\`\`\`\n`
    : "";

  const phpStackFramesSection = error.phpStackFrames && error.phpStackFrames.length > 0
    ? `### PHP Stack Trace\n| # | Function | File | Line |\n|---|----------|------|------|\n${
        error.phpStackFrames.map((f: { class?: string; function?: string; file?: string; fileBase?: string; line?: number }, i: number) => {
          const fn = f.class ? `${f.class}::${f.function}` : f.function || 'unknown';
          return `| ${i} | ${fn}() | ${f.fileBase || f.file || 'unknown'} | ${f.line || '?'} |`;
        }).join('\n')
      }\n`
    : "";

  // Route / page context
  const routeSection = error.route
    ? `### Page\n\`${error.route}\`${error.triggerComponent ? ` (${error.triggerComponent})` : ''}\n`
    : "";

  // Arrow-style interaction summary for the header
  const interactionArrowSection = error.uiClickPathArrow
    ? `### User Interaction\n\`\`\`\n${error.uiClickPathArrow}\n\`\`\`\n`
    : "";

  // Detailed numbered interaction path with routes
  const uiClickPathSection = error.uiClickPathString
    ? `### User Interaction Path (${error.uiClickPath?.length ?? 0} steps)\n\`\`\`\n${error.uiClickPathString}\n\`\`\`\n`
    : "";

  const executionChainSection = error.executionLogsFormatted
    ? `### Frontend Execution Chain\n\`\`\`\n${error.executionLogsFormatted}\n\`\`\`\n`
    : "";

  const siteUrlSection = error.siteUrl
    ? `### Target Site\n${error.siteUrl}\n`
    : "";

  const sessionSection = error.sessionId
    ? `### Session Info\n**Session ID:** ${error.sessionId}\n${error.sessionType ? `**Type:** ${error.sessionType}\n` : ""}*Fetch full logs via: GET /api/v1/sessions/${error.sessionId}/logs*\n`
    : "";

  return `## Error Report

${appInfo.join("\n")}

**ID:** ${error.id}
**Code:** ${error.code}
**Level:** ${error.level}
**Timestamp:** ${error.createdAt}

${routeSection}
${interactionArrowSection}
${triggerSection}
${chainSection}
${uiClickPathSection}
${siteUrlSection}
${sessionSection}
### Message
${error.message}

${error.details ? `### Details\n${error.details}\n` : ""}
${error.endpoint ? `### Request\n**${error.method || "GET"}** ${error.endpoint}\n${error.responseStatus ? `**Status:** ${error.responseStatus}\n` : ""}` : ""}
${error.requestBody ? `### Request Body\n\`\`\`json\n${JSON.stringify(error.requestBody, null, 2)}\n\`\`\`\n` : ""}
${backendLogsSection}
${backendStackSection}
${phpStackFramesSection}
${executionChainSection}
${framesSection}
${error.file ? `### Location\n\`${error.file}:${error.line}\` (${error.function})\n` : ""}
${error.context && Object.keys(error.context).length > 0 ? `### Context\n\`\`\`json\n${JSON.stringify(error.context, null, 2)}\n\`\`\`\n` : ""}
${error.stackTrace ? `### Frontend Stack Trace\n\`\`\`\n${error.stackTrace}\n\`\`\`` : ""}

---
*Generated by WP Plugin Publish Error Reporter*
`;
}

/**
 * Get suggested fixes for a given error code.
 */
export function getSuggestedFixes(code: string): string[] {
  const fixes: Record<string, string[]> = {
    E1001: [
      "Check that the backend server is running on the correct port",
      "Verify VITE_API_URL environment variable is correctly set",
      "Ensure no firewall is blocking the connection",
      "Try refreshing the page",
    ],
    E2001: [
      "Check site credentials (username and application password)",
      "Verify the WordPress site is accessible",
      "Ensure REST API is enabled on the WordPress site",
      "Check if Riseup Asia Uploader plugin is installed and activated",
    ],
    E2002: [
      "The remote site returned an unexpected response format",
      "Check if the WordPress site has any caching plugins that might interfere",
      "Verify the Riseup Asia Uploader plugin version is compatible",
    ],
    E3001: [
      "Check if the plugin files exist in the local directory",
      "Verify file permissions allow reading the plugin folder",
      "Ensure the plugin has a valid main PHP file with headers",
    ],
    E4001: [
      "Check available disk space on the WordPress server",
      "Verify PHP upload limits (upload_max_filesize, post_max_size)",
      "Try uploading a smaller plugin first to test",
    ],
    E5001: [
      "Check that the plugin has no fatal errors in its code",
      "Verify plugin dependencies are met",
      "Check WordPress debug.log for activation errors",
      "Try activating the plugin manually in WordPress admin",
    ],
    E9005: [
      "The API returned HTML instead of JSON - this usually means a routing issue",
      "Check if the backend server is running",
      "Verify VITE_API_URL points to the correct backend URL",
      "Look at the browser network tab for the actual response",
    ],
  };

  return fixes[code] || [
    "Check the error details for more context",
    "Review the stack trace for the error source",
    "Check backend logs for additional information",
    "Try the operation again - it may be a temporary issue",
  ];
}
