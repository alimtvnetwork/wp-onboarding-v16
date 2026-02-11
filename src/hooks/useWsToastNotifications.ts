import { useEffect } from "react";
import { wsClient, WS_EVENTS } from "@/lib/ws";
import { dedupToast as toast } from "@/lib/dedupToast";
import { useNotificationStore, type NotificationType } from "@/stores/notificationStore";

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
      if (type === "success") toast.success(title, { description });
      else if (type === "error") toast.error(title, { description });
      else if (type === "warning") toast.warning(title, { description });
      else toast.info(title, { description });

      // Persist to store
      addNotification({ type, title, description, source });
    };

    // ── Publish ──────────────────────────────────────────────
    unsubs.push(
      wsClient.on(WS_EVENTS.PUBLISH_COMPLETE, (data: unknown) => {
        const d = data as { pluginSlug?: string; siteName?: string; success?: boolean; error?: string };
        if (d.success === false || d.error) {
          notify("error", "Publish failed", d.error || `${d.pluginSlug ?? "Plugin"} → ${d.siteName ?? "site"}`, "publish");
        } else {
          notify("success", "Publish complete", `${d.pluginSlug ?? "Plugin"} → ${d.siteName ?? "site"}`, "publish");
        }
      })
    );

    // ── Auto-publish ─────────────────────────────────────────
    unsubs.push(
      wsClient.on(WS_EVENTS.AUTO_PUBLISH_TRIGGERED, (data: unknown) => {
        const d = data as { pluginSlug?: string };
        notify("info", "Auto-publish triggered", d.pluginSlug ?? "File changes detected", "auto-publish");
      })
    );

    unsubs.push(
      wsClient.on(WS_EVENTS.AUTO_PUBLISH_COMPLETE, (data: unknown) => {
        const d = data as { pluginSlug?: string; sitesCount?: number };
        notify("success", "Auto-publish complete", `${d.pluginSlug ?? "Plugin"} deployed to ${d.sitesCount ?? "all"} site(s)`, "auto-publish");
      })
    );

    unsubs.push(
      wsClient.on(WS_EVENTS.AUTO_PUBLISH_FAILED, (data: unknown) => {
        const d = data as { pluginSlug?: string; error?: string };
        notify("error", "Auto-publish failed", d.error || d.pluginSlug || "An error occurred", "auto-publish");
      })
    );

    // ── Sync ─────────────────────────────────────────────────
    unsubs.push(
      wsClient.on(WS_EVENTS.SYNC_COMPLETE, (data: unknown) => {
        const d = data as { pluginSlug?: string; siteName?: string; success?: boolean; error?: string };
        if (d.success === false || d.error) {
          notify("error", "Sync failed", d.error || `${d.pluginSlug ?? "Plugin"} ↔ ${d.siteName ?? "site"}`, "sync");
        } else {
          notify("success", "Sync complete", `${d.pluginSlug ?? "Plugin"} ↔ ${d.siteName ?? "site"}`, "sync");
        }
      })
    );

    // ── Connection test ──────────────────────────────────────
    unsubs.push(
      wsClient.on(WS_EVENTS.CONNECTION_TEST_COMPLETE, (data: unknown) => {
        const d = data as { siteName?: string; success?: boolean; error?: string };
        if (d.success === false || d.error) {
          notify("error", "Connection test failed", d.error || d.siteName || "Could not reach site", "connection");
        } else {
          notify("success", "Connection test passed", d.siteName ?? "Site is reachable", "connection");
        }
      })
    );

    // ── E2E tests ────────────────────────────────────────────
    unsubs.push(
      wsClient.on(WS_EVENTS.E2E_TEST_COMPLETE, (data: unknown) => {
        const d = data as { passed?: number; failed?: number; total?: number };
        const failed = d.failed ?? 0;
        if (failed > 0) {
          notify("error", "E2E tests finished with failures", `${d.passed ?? 0} passed, ${failed} failed out of ${d.total ?? "?"}`, "e2e");
        } else {
          notify("success", "All E2E tests passed", `${d.passed ?? d.total ?? "All"} test(s) passed`, "e2e");
        }
      })
    );

    // ── Git operations ───────────────────────────────────────
    unsubs.push(
      wsClient.on("git_pull_complete", (data: unknown) => {
        const d = data as { pluginSlug?: string };
        notify("success", "Git pull complete", d.pluginSlug ?? "Repository updated", "git");
      })
    );

    unsubs.push(
      wsClient.on("git_pull_failed", (data: unknown) => {
        const d = data as { pluginSlug?: string; error?: string };
        notify("error", "Git pull failed", d.error || d.pluginSlug || "Pull operation failed", "git");
      })
    );

    // ── Backend errors ───────────────────────────────────────
    unsubs.push(
      wsClient.on(WS_EVENTS.ERROR, (data: unknown) => {
        const d = data as { message?: string; code?: string };
        notify("error", d.message || "Backend error", d.code ? `Error code: ${d.code}` : undefined, "error");
      })
    );

    // ── Remote plugin actions ────────────────────────────────
    unsubs.push(
      wsClient.on(WS_EVENTS.REMOTE_PLUGIN_ACTION_COMPLETE, (data: unknown) => {
        const d = data as { action?: string; pluginSlug?: string; siteName?: string; success?: boolean; error?: string };
        if (d.success === false || d.error) {
          notify("error", `Remote ${d.action ?? "action"} failed`, d.error || `${d.pluginSlug ?? "Plugin"} on ${d.siteName ?? "site"}`, "remote-plugin");
        } else {
          notify("success", `Remote ${d.action ?? "action"} complete`, `${d.pluginSlug ?? "Plugin"} on ${d.siteName ?? "site"}`, "remote-plugin");
        }
      })
    );

    return () => unsubs.forEach((fn) => fn());
  }, [addNotification]);
}
