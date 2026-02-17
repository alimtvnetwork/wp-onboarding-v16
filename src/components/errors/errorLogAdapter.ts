import type { ErrorLog } from "@/lib/api";
import type { CapturedError } from "@/stores/errorStore";

/**
 * Maps an ErrorLog (backend API shape) to a minimal CapturedError
 * so it can be used with generateCompactReport / generateErrorReport.
 */
export function errorLogToCapturedError(error: ErrorLog): CapturedError {
  return {
    id: String(error.id),
    code: error.code,
    level: (error.level as CapturedError["level"]) || "error",
    message: error.message,
    details: error.details,
    createdAt: error.createdAt,
    context: error.context as CapturedError["context"],
    backendStackTrace: error.stackTrace,
    parsedFrames: error.file
      ? [{ file: error.file, line: error.line ?? 0, function: error.function ?? "" }]
      : undefined,
  } as CapturedError;
}
