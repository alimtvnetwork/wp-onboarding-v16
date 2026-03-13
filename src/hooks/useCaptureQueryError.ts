import { useEffect } from "react";
import { useErrorStore } from "@/stores/errorStore";

/**
 * Captures query/mutation errors to the error history when suppressGlobalError is used.
 * This ensures errors are persisted for audit even when the global error modal is suppressed.
 */
export function useCaptureQueryError(
  isError: boolean,
  error: Error | null | undefined,
  meta: {
    source: string;
    endpoint: string;
    method?: string;
    triggerComponent?: string;
  }
) {
  const { captureException } = useErrorStore();

  useEffect(() => {
    if (isError && error) {
      captureException(error, {
        source: meta.source,
        endpoint: meta.endpoint,
        method: meta.method || "GET",
        triggerComponent: meta.triggerComponent || meta.source,
      });
    }
    // Only re-run when error state changes
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [isError, error]);
}

/**
 * Captures a mutation error inline (call from onError callback).
 * Returns a stable captureException-based handler.
 */
export function useCaptureOnError(meta: {
  source: string;
  endpoint: string;
  method?: string;
  triggerComponent?: string;
}) {
  const { captureException } = useErrorStore();

  return (error: Error) => {
    captureException(error, {
      source: meta.source,
      endpoint: meta.endpoint,
      method: meta.method || "POST",
      triggerComponent: meta.triggerComponent || meta.source,
    });
  };
}
