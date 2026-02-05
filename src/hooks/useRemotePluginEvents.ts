import { useEffect, useCallback } from "react";
import { useQueryClient } from "@tanstack/react-query";
import { wsClient, WS_EVENTS } from "@/lib/ws";
import { useErrorStore, PHPStackFrame } from "@/stores/errorStore";
import { toast } from "sonner";

/**
 * WebSocket event data for remote plugin actions
 */
interface RemotePluginActionStartedData {
  siteId: number;
  siteName?: string;
  siteUrl?: string;
  pluginSlug: string;
  action: "enable" | "disable" | "delete";
  sessionId: string;
}

interface RemotePluginActionCompleteData {
  siteId: number;
  siteName?: string;
  siteUrl?: string;
  pluginSlug: string;
  action: "enable" | "disable" | "delete";
  sessionId: string;
  success: boolean;
  error?: string;
  errorDetails?: {
    statusCode?: number;
    stackTrace?: string;
    stackTraceFrames?: PHPStackFrame[];
    errorFile?: string;
    errorLine?: number;
  };
  duration?: number;
}

/**
 * Hook to subscribe to remote plugin action WebSocket events.
 * Automatically captures PHP errors with stack traces when actions fail.
 */
export function useRemotePluginEvents(siteId?: number) {
  const queryClient = useQueryClient();
  const { captureException, openErrorModal } = useErrorStore();

  const handleActionComplete = useCallback(
    (data: RemotePluginActionCompleteData) => {
      // Filter by siteId if provided
      if (siteId !== undefined && data.siteId !== siteId) {
        return;
      }

      // Invalidate remote plugins query on any action completion
      queryClient.invalidateQueries({ queryKey: ["sites", data.siteId, "remote-plugins"] });

      if (!data.success && data.error) {
        // Capture error with PHP stack frames
        const captured = captureException(new Error(data.error), {
          source: `RemotePluginAction.${data.action}`,
          endpoint: `/sites/${data.siteId}/remote-plugins/${data.pluginSlug}/${data.action}`,
          method: data.action === "delete" ? "DELETE" : "POST",
          siteUrl: data.siteUrl,
          sessionId: data.sessionId,
          sessionType: `remote_plugin_${data.action}`,
          phpStackFrames: data.errorDetails?.stackTraceFrames,
          errorFile: data.errorDetails?.errorFile,
          errorLine: data.errorDetails?.errorLine,
          backendStackTrace: data.errorDetails?.stackTrace,
          context: {
            pluginSlug: data.pluginSlug,
            siteName: data.siteName,
            statusCode: data.errorDetails?.statusCode,
            duration: data.duration,
          },
        });

        // Show toast with link to error modal
        toast.error(`Failed to ${data.action} ${data.pluginSlug}`, {
          description: data.error,
          action: {
            label: "View Details",
            onClick: () => openErrorModal(captured),
          },
          duration: 10000,
        });
      }
    },
    [siteId, queryClient, captureException, openErrorModal]
  );

  useEffect(() => {
    const unsubStarted = wsClient.on(
      WS_EVENTS.REMOTE_PLUGIN_ACTION_STARTED,
      (data: unknown) => {
        const eventData = data as RemotePluginActionStartedData;
        if (siteId !== undefined && eventData.siteId !== siteId) {
          return;
        }
        // Optional: show loading toast or track in-progress actions
      }
    );

    const unsubComplete = wsClient.on(
      WS_EVENTS.REMOTE_PLUGIN_ACTION_COMPLETE,
      (data: unknown) => {
        handleActionComplete(data as RemotePluginActionCompleteData);
      }
    );

    return () => {
      unsubStarted();
      unsubComplete();
    };
  }, [siteId, handleActionComplete]);
}
