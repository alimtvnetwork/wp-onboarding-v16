import { useEffect } from "react";
import { wsClient, WS_EVENTS } from "@/lib/ws";
import { toast } from "sonner";

/**
 * Listens to key WebSocket events and surfaces them as Sonner toasts
 * so users get notified of important operations regardless of which page they're on.
 */
export function useWsToastNotifications() {
  useEffect(() => {
    const unsubs: (() => void)[] = [];

    // ── Publish ──────────────────────────────────────────────
    unsubs.push(
      wsClient.on(WS_EVENTS.PUBLISH_COMPLETE, (data: unknown) => {
        const d = data as { pluginSlug?: string; siteName?: string; success?: boolean; error?: string };
        if (d.success === false || d.error) {
          toast.error("Publish failed", {
            description: d.error || `${d.pluginSlug ?? "Plugin"} → ${d.siteName ?? "site"}`,
          });
        } else {
          toast.success("Publish complete", {
            description: `${d.pluginSlug ?? "Plugin"} → ${d.siteName ?? "site"}`,
          });
        }
      })
    );

    // ── Auto-publish ─────────────────────────────────────────
    unsubs.push(
      wsClient.on(WS_EVENTS.AUTO_PUBLISH_TRIGGERED, (data: unknown) => {
        const d = data as { pluginSlug?: string };
        toast.info("Auto-publish triggered", {
          description: d.pluginSlug ?? "File changes detected",
        });
      })
    );

    unsubs.push(
      wsClient.on(WS_EVENTS.AUTO_PUBLISH_COMPLETE, (data: unknown) => {
        const d = data as { pluginSlug?: string; sitesCount?: number };
        toast.success("Auto-publish complete", {
          description: `${d.pluginSlug ?? "Plugin"} deployed to ${d.sitesCount ?? "all"} site(s)`,
        });
      })
    );

    unsubs.push(
      wsClient.on(WS_EVENTS.AUTO_PUBLISH_FAILED, (data: unknown) => {
        const d = data as { pluginSlug?: string; error?: string };
        toast.error("Auto-publish failed", {
          description: d.error || d.pluginSlug || "An error occurred",
        });
      })
    );

    // ── Sync ─────────────────────────────────────────────────
    unsubs.push(
      wsClient.on(WS_EVENTS.SYNC_COMPLETE, (data: unknown) => {
        const d = data as { pluginSlug?: string; siteName?: string; success?: boolean; error?: string };
        if (d.success === false || d.error) {
          toast.error("Sync failed", {
            description: d.error || `${d.pluginSlug ?? "Plugin"} ↔ ${d.siteName ?? "site"}`,
          });
        } else {
          toast.success("Sync complete", {
            description: `${d.pluginSlug ?? "Plugin"} ↔ ${d.siteName ?? "site"}`,
          });
        }
      })
    );

    // ── Connection test ──────────────────────────────────────
    unsubs.push(
      wsClient.on(WS_EVENTS.CONNECTION_TEST_COMPLETE, (data: unknown) => {
        const d = data as { siteName?: string; success?: boolean; error?: string };
        if (d.success === false || d.error) {
          toast.error("Connection test failed", {
            description: d.error || d.siteName || "Could not reach site",
          });
        } else {
          toast.success("Connection test passed", {
            description: d.siteName ?? "Site is reachable",
          });
        }
      })
    );

    // ── E2E tests ────────────────────────────────────────────
    unsubs.push(
      wsClient.on(WS_EVENTS.E2E_TEST_COMPLETE, (data: unknown) => {
        const d = data as { passed?: number; failed?: number; total?: number };
        const failed = d.failed ?? 0;
        if (failed > 0) {
          toast.error("E2E tests finished with failures", {
            description: `${d.passed ?? 0} passed, ${failed} failed out of ${d.total ?? "?"}`,
          });
        } else {
          toast.success("All E2E tests passed", {
            description: `${d.passed ?? d.total ?? "All"} test(s) passed`,
          });
        }
      })
    );

    // ── Git operations ───────────────────────────────────────
    unsubs.push(
      wsClient.on("git_pull_complete", (data: unknown) => {
        const d = data as { pluginSlug?: string };
        toast.success("Git pull complete", {
          description: d.pluginSlug ?? "Repository updated",
        });
      })
    );

    unsubs.push(
      wsClient.on("git_pull_failed", (data: unknown) => {
        const d = data as { pluginSlug?: string; error?: string };
        toast.error("Git pull failed", {
          description: d.error || d.pluginSlug || "Pull operation failed",
        });
      })
    );

    // ── Backend errors ───────────────────────────────────────
    unsubs.push(
      wsClient.on(WS_EVENTS.ERROR, (data: unknown) => {
        const d = data as { message?: string; code?: string };
        toast.error(d.message || "Backend error", {
          description: d.code ? `Error code: ${d.code}` : undefined,
        });
      })
    );

    // ── Remote plugin actions ────────────────────────────────
    unsubs.push(
      wsClient.on(WS_EVENTS.REMOTE_PLUGIN_ACTION_COMPLETE, (data: unknown) => {
        const d = data as { action?: string; pluginSlug?: string; siteName?: string; success?: boolean; error?: string };
        if (d.success === false || d.error) {
          toast.error(`Remote ${d.action ?? "action"} failed`, {
            description: d.error || `${d.pluginSlug ?? "Plugin"} on ${d.siteName ?? "site"}`,
          });
        } else {
          toast.success(`Remote ${d.action ?? "action"} complete`, {
            description: `${d.pluginSlug ?? "Plugin"} on ${d.siteName ?? "site"}`,
          });
        }
      })
    );

    return () => unsubs.forEach((fn) => fn());
  }, []);
}
