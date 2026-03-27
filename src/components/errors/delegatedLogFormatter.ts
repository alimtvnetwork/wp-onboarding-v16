import type { CapturedError, PHPStackFrame } from "@/stores/errorStore";
import { formatDateTimeUtc, unescapeEmbeddedNewlines } from "@/lib/logText";

type DelegatedSessionDiagnostics = {
  phpStackTraceLog?: string | null;
  stackTrace?: {
    php?: PHPStackFrame[];
  };
} | null | undefined;

function stringifyValue(value: unknown): string {
  if (value == null) return "";

  if (typeof value === "string") {
    try {
      return JSON.stringify(JSON.parse(value), null, 2);
    } catch {
      return unescapeEmbeddedNewlines(value);
    }
  }

  try {
    return JSON.stringify(value, null, 2);
  } catch {
    return String(value);
  }
}

function getDelegatedRawResponse(error: CapturedError): string {
  const contextBody = error.context?.remoteResponseBody;
  if (typeof contextBody === "string" && contextBody.length > 0) {
    return contextBody;
  }

  const envelopeBody = error.envelopeErrors?.RemoteResponseBody;
  if (typeof envelopeBody === "string" && envelopeBody.length > 0) {
    return envelopeBody;
  }

  return stringifyValue(error.envelopeErrors?.DelegatedRequestServer?.Response);
}

function formatIndentedBlock(title: string, content: string): string {
  return `${title}:\n${content.split("\n").map((line) => `    ${line}`).join("\n")}`;
}

function formatPhpFrames(title: string, frames: PHPStackFrame[]): string {
  const content = frames.map((frame, index) => {
    const fn = frame.class ? `${frame.class}::${frame.function}` : frame.function || "unknown";
    return `#${index} ${fn}() at ${frame.file || frame.fileBase || "unknown"}:${frame.line || "?"}`;
  }).join("\n");

  return formatIndentedBlock(title, content);
}

export function buildDelegatedLogsSection(error: CapturedError): string {
  const raw = getDelegatedRawResponse(error);
  if (!raw) return "";

  try {
    const parsed = JSON.parse(raw) as Record<string, unknown>;

    // Skip successful log-retrieve responses — they contain embedded log content, not error data
    if (Array.isArray(parsed.plugins)) {
      const pluginCount = parsed.plugins.length;
      const pluginNames = (parsed.plugins as Array<Record<string, unknown>>)
        .map((p) => p.label || p.namespace || "unknown")
        .join(", ");
      return `Log retrieve response (${pluginCount} plugin${pluginCount !== 1 ? "s" : ""}): ${pluginNames}\n(Use Remote Logs panel to inspect full content)`;
    }

    const status = parsed.Status as Record<string, unknown> | undefined;
    const errors = parsed.Errors as Record<string, unknown> | undefined;
    const attrs = parsed.Attributes as Record<string, unknown> | undefined;
    const data = (parsed.data as Record<string, unknown> | undefined) ?? {};
    const coreError = (data.error as Record<string, unknown> | undefined) ?? {};

    const lines: string[] = [];

    const code = status?.Code ?? status?.code ?? "";
    const msg = status?.Message ?? status?.message ?? "";
    if (msg) lines.push(`Status: ${code} — ${msg}`);

    const delegatedAt = (attrs?.RequestDelegatedAt ?? attrs?.requestDelegatedAt) as string | undefined;
    if (delegatedAt) lines.push(`Delegated To: ${delegatedAt}`);

    const namespace = (attrs?.Namespace ?? parsed.Namespace) as string | undefined;
    if (namespace) lines.push(`Namespace: ${namespace}`);

    const requestedAt = (attrs?.RequestedAt ?? attrs?.requestedAt) as string | undefined;
    if (requestedAt) lines.push(`Remote Endpoint: ${requestedAt}`);

    const backendMsg = (errors?.BackendMessage ?? errors?.backendMessage) as string | undefined;
    if (backendMsg && !lines.some((line) => line.includes(backendMsg))) {
      lines.push(`Message: ${backendMsg}`);
    }

    const phpStack = (errors?.Backend ?? errors?.backend) as string[] | undefined;
    if (phpStack && phpStack.length > 0) {
      lines.push("", "PHP Stack Trace:");
      phpStack.forEach((frame, index) => lines.push(`  #${index} ${frame}`));
    }

    const delegatedStack = Array.isArray(data.stack_trace)
      ? data.stack_trace.filter((line): line is string => typeof line === "string")
      : [];
    if (!phpStack?.length && delegatedStack.length > 0) {
      lines.push("", "Delegated Stack Trace:");
      delegatedStack.forEach((frame, index) => lines.push(`  #${index} ${frame}`));
    }

    const coreMessage = typeof coreError.message === "string" ? coreError.message : undefined;
    if (!phpStack?.length && coreMessage) {
      lines.push("", `PHP Fatal: ${coreMessage}`);
      if (typeof coreError.file === "string") {
        lines.push(`  File: ${coreError.file}:${String(coreError.line ?? "?")}`);
      }
    }

    const pluginVersion = parsed.PluginVersion as string | undefined;
    if (pluginVersion) lines.push(`Remote Plugin Version: ${pluginVersion}`);

    const logHint = (parsed.LogHint as string | undefined)
      || (typeof data.log_hint === "string" ? data.log_hint : undefined)
      || error.envelopeErrors?.DelegatedRequestServer?.AdditionalMessages;
    if (logHint) lines.push(`Hint: ${logHint}`);

    return lines.length > 0 ? lines.join("\n") : stringifyValue(parsed);
  } catch {
    const truncated = raw.length > 4000 ? `${raw.slice(0, 4000)}…` : raw;
    return `Raw Response:\n${unescapeEmbeddedNewlines(truncated)}`;
  }
}

export function buildDelegatedErrorLogSection(
  error: CapturedError,
  sessionDiag?: DelegatedSessionDiagnostics,
): string {
  const delegatedServer = error.envelopeErrors?.DelegatedRequestServer;
  const summary = buildDelegatedLogsSection(error);
  const sections: string[] = [];

  const header = [
    `[${formatDateTimeUtc(error.createdAt)}] HTTP ${delegatedServer?.StatusCode || error.responseStatus || 500} ${delegatedServer?.Method || error.method || "GET"} DELEGATED FAILED`,
    `  Requested To: ${error.method || "GET"} ${error.endpoint || "unknown"}`,
  ];

  if (delegatedServer?.DelegatedEndpoint || error.requestDelegatedAt) {
    header.push(`  Delegated To: ${delegatedServer?.DelegatedEndpoint || error.requestDelegatedAt}`);
  }

  if (delegatedServer?.Namespace) {
    header.push(`  Namespace: ${delegatedServer.Namespace}`);
  }

  header.push(`  Error Message: ${error.message}`);

  if (delegatedServer) {
    sections.push([
      "  Delegated Server Info:",
      `    Endpoint: \"${delegatedServer.DelegatedEndpoint}\"`,
      `    Method: \"${delegatedServer.Method}\"`,
      `    Status: ${delegatedServer.StatusCode}`,
      ...(delegatedServer.Namespace ? [`    Namespace: ${delegatedServer.Namespace}`] : []),
      ...(delegatedServer.AdditionalMessages ? ["    Additional Message:", `        ${delegatedServer.AdditionalMessages}`] : []),
    ].join("\n"));
  }

  if (summary) {
    sections.push(formatIndentedBlock("  Delegated Logs", summary));
  }

  if (delegatedServer?.StackTrace?.length) {
    sections.push(formatIndentedBlock("  Delegated Stack Trace", delegatedServer.StackTrace.join("\n")));
  }

  if (error.envelopeErrors?.DelegatedServiceErrorStack?.length) {
    sections.push(formatIndentedBlock("  Delegated Error Stack", error.envelopeErrors.DelegatedServiceErrorStack.join("\n")));
  }

  if (delegatedServer?.RequestBody != null) {
    const requestBody = stringifyValue(delegatedServer.RequestBody);
    if (requestBody) {
      sections.push(formatIndentedBlock("  Request Body", requestBody));
    }
  }

  if (delegatedServer?.Response != null) {
    const responseBody = stringifyValue(delegatedServer.Response);
    if (responseBody) {
      sections.push(formatIndentedBlock("  Response Body", responseBody));
    }
  }

  if (error.phpStackFrames?.length) {
    sections.push(formatPhpFrames("  PHP Stack Frames", error.phpStackFrames));
  }

  if (sessionDiag?.stackTrace?.php?.length) {
    sections.push(formatPhpFrames("  Session PHP Stack", sessionDiag.stackTrace.php));
  }

  if (sessionDiag?.phpStackTraceLog) {
    sections.push(formatIndentedBlock("  stacktrace.txt", unescapeEmbeddedNewlines(sessionDiag.phpStackTraceLog)));
  }

  if (sections.length === 0 && !delegatedServer && !error.requestDelegatedAt) return "";
  return [...header, ...sections].join("\n");
}