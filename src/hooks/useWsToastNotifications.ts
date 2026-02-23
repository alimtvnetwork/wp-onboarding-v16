import { useEffect } from "react";
import { wsClient, WS_EVENTS } from "@/lib/ws";
import { dedupToast as toast } from "@/lib/dedupToast";
import { useNotificationStore, type NotificationType } from "@/stores/notificationStore";

/** Map PascalCase NotificationType → sonner toast method */
const TOAST_DISPATCH: Record<NotificationType, typeof toast.success> = {
  Success: toast.success,
  Error: toast.error,
  Warning: toast.warning,
  Info: toast.info,
};

/**
 * Listens to key WebSocket events and surfaces them as Sonner toasts
 * AND persists them to the notification store for history.
 */
export function useWsToastNotifications() {
  const addNotification = useNotificationStore((s) => s.addNotification);

  useEffect(() => {
    const unsubs: (() => void)[] = [];

    const notify = (
      type: NotificationType,
      title: string,
      description: string | undefined,
      source: string
    ) => {
      // Show toast
      TOAST_DISPATCH[type](title, { description });

      // Persist to store
      addNotification({ type, title, description, source });
    };

    // ── Publish ──────────────────────────────────────────────
    unsubs.push(
      wsClient.on(WS_EVENTS.PUBLISH_COMPLETE, (data: unknown) => {
        const d = data as { pluginSlug?: string; siteName?: string; success?: boolean; error?: string };

        if (d.success === false || d.error) {
          notify("Error", "Publish failed", d.error || `${d.pluginSlug ?? "Plugin"} → ${d.siteName ?? "site"}`, "publish");
        } else {
          notify("Success", "Publish complete", `${d.pluginSlug ?? "Plugin"} → ${d.siteName ?? "site"}`, "publish");
        }
      })
    );

    // ── Auto-publish ─────────────────────────────────────────
    unsubs.push(
      wsClient.on(WS_EVENTS.AUTO_PUBLISH_TRIGGERED, (data: unknown) => {
        const d = data as { pluginSlug?: string };
        notify("Info", "Auto-publish triggered", d.pluginSlug ?? "File changes detected", "auto-publish");
      })
    );

    unsubs.push(
      wsClient.on(WS_EVENTS.AUTO_PUBLISH_COMPLETE, (data: unknown) => {
        const d = data as { pluginSlug?: string; sitesCount?: number };
        notify("Success", "Auto-publish complete", `${d.pluginSlug ?? "Plugin"} deployed to ${d.sitesCount ?? "all"} site(s)`, "auto-publish");
      })
    );

    unsubs.push(
      wsClient.on(WS_EVENTS.AUTO_PUBLISH_FAILED, (data: unknown) => {
        const d = data as { pluginSlug?: string; error?: string };
        notify("Error", "Auto-publish failed", d.error || d.pluginSlug || "An error occurred", "auto-publish");
      })
    );

    // ── Sync ─────────────────────────────────────────────────
    unsubs.push(
      wsClient.on(WS_EVENTS.SYNC_COMPLETE, (data: unknown) => {
        const d = data as { pluginSlug?: string; siteName?: string; success?: boolean; error?: string };

        if (d.success === false || d.error) {
          notify("Error", "Sync failed", d.error || `${d.pluginSlug ?? "Plugin"} ↔ ${d.siteName ?? "site"}`, "sync");
        } else {
          notify("Success", "Sync complete", `${d.pluginSlug ?? "Plugin"} ↔ ${d.siteName ?? "site"}`, "sync");
        }
      })
    );

    // ── Connection test ──────────────────────────────────────
    unsubs.push(
      wsClient.on(WS_EVENTS.CONNECTION_TEST_COMPLETE, (data: unknown) => {
        const d = data as { siteName?: string; success?: boolean; error?: string };

        if (d.success === false || d.error) {
          notify("Error", "Connection test failed", d.error || d.siteName || "Could not reach site", "connection");
        } else {
          notify("Success", "Connection test passed", d.siteName ?? "Site is reachable", "connection");
        }
      })
    );

    // ── E2E tests ────────────────────────────────────────────
    unsubs.push(
      wsClient.on(WS_EVENTS.E2E_TEST_COMPLETE, (data: unknown) => {
        const d = data as { passed?: number; failed?: number; total?: number };
        const failed = d.failed ?? 0;

        if (failed > 0) {
          notify("Error", "E2E tests finished with failures", `${d.passed ?? 0} passed, ${failed} failed out of ${d.total ?? "?"}`, "e2e");
        } else {
          notify("Success", "All E2E tests passed", `${d.passed ?? d.total ?? "All"} test(s) passed`, "e2e");
        }
      })
    );

    // ── Git operations ───────────────────────────────────────
    unsubs.push(
      wsClient.on("git_pull_complete", (data: unknown) => {
        const d = data as { pluginSlug?: string };
        notify("Success", "Git pull complete", d.pluginSlug ?? "Repository updated", "git");
      })
    );

    unsubs.push(
      wsClient.on("git_pull_failed", (data: unknown) => {
        const d = data as { pluginSlug?: string; error?: string };
        notify("Error", "Git pull failed", d.error || d.pluginSlug || "Pull operation failed", "git");
      })
    );

    // ── Backend errors ───────────────────────────────────────
    unsubs.push(
      wsClient.on(WS_EVENTS.ERROR, (data: unknown) => {
        const d = data as { message?: string; code?: string };
        notify("Error", d.message || "Backend error", d.code ? `Error code: ${d.code}` : undefined, "error");
      })
    );

    // ── Remote plugin actions ────────────────────────────────
    unsubs.push(
      wsClient.on(WS_EVENTS.REMOTE_PLUGIN_ACTION_COMPLETE, (data: unknown) => {
        const d = data as { action?: string; pluginSlug?: string; siteName?: string; success?: boolean; error?: string };

        if (d.success === false || d.error) {
          notify("Error", `Remote ${d.action ?? "action"} failed`, d.error || `${d.pluginSlug ?? "Plugin"} on ${d.siteName ?? "site"}`, "remote-plugin");
        } else {
          notify("Success", `Remote ${d.action ?? "action"} complete`, `${d.pluginSlug ?? "Plugin"} on ${d.siteName ?? "site"}`, "remote-plugin");
        }
      })
    );

    return () => unsubs.forEach((fn) => fn());
  }, [addNotification]);
}
