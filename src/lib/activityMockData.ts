import type { ActivityEntry } from "@/lib/api";

const now = Date.now();
const h = (hours: number) => new Date(now - hours * 3600_000).toISOString();

export const MOCK_ACTIVITY: ActivityEntry[] = [
  { id: "a1", timestamp: h(0.2), siteId: 1, siteName: "Production Site", type: "publish", action: "deploy", title: "Published contact-form-pro v2.4.1", metadata: { pluginName: "contact-form-pro", version: "2.4.1", filesUpdated: 12 }, source: "go", machineName: "DEV-01", version: "2.4.1" },
  { id: "a2", timestamp: h(1), siteId: 2, siteName: "Staging Site", type: "snapshot", action: "create", title: "Full backup completed (42 tables, 1.2 MB)", metadata: { snapshotType: "Full", tables: 42, size: 1258000 }, source: "wordpress" },
  { id: "a3", timestamp: h(2.5), siteId: 1, siteName: "Production Site", type: "plugin", action: "activate", title: "Activated woo-extensions on Production", metadata: { pluginSlug: "woo-extensions" }, source: "wordpress" },
  { id: "a4", timestamp: h(3), siteId: 3, siteName: "Dev Environment", type: "connection", action: "test", title: "Connection test successful (WP 6.7.1)", metadata: { wpVersion: "6.7.1" }, source: "go" },
  { id: "a5", timestamp: h(5), siteId: 1, siteName: "Production Site", type: "snapshot", action: "restore", title: "Restored selective backup (wp_posts, wp_options)", metadata: { mode: "selective", tables: ["wp_posts", "wp_options"] }, source: "wordpress" },
  { id: "a6", timestamp: h(6), siteId: 2, siteName: "Staging Site", type: "config", action: "update", title: "Updated snapshot schedule to daily at 03:00", metadata: { setting: "schedule", value: "daily", time: "03:00" }, source: "go" },
  { id: "a7", timestamp: h(8), siteId: 1, siteName: "Production Site", type: "publish", action: "self-update", title: "Self-update riseup-asia-uploader 1.56.0 → 1.57.0", metadata: { from: "1.56.0", to: "1.57.0", isSelfUpdate: true }, source: "go", machineName: "DEV-01", version: "1.57.0" },
  { id: "a8", timestamp: h(12), siteId: 3, siteName: "Dev Environment", type: "snapshot", action: "delete", title: "Deleted snapshot #14 + 3 incremental children", metadata: { snapshotId: 14, cascadeCount: 3 }, source: "wordpress" },
  { id: "a9", timestamp: h(18), siteId: 2, siteName: "Staging Site", type: "plugin", action: "deactivate", title: "Deactivated debug-bar on Staging", metadata: { pluginSlug: "debug-bar" }, source: "wordpress" },
  { id: "a10", timestamp: h(24), siteId: 1, siteName: "Production Site", type: "snapshot", action: "export", title: "ZIP export built (3.4 MB, cached)", metadata: { size: 3400000, cached: true }, source: "wordpress" },
  { id: "a11", timestamp: h(30), siteId: 3, siteName: "Dev Environment", type: "publish", action: "deploy", title: "Published theme-starter v1.0.0", metadata: { pluginName: "theme-starter", version: "1.0.0", filesUpdated: 47 }, source: "go", machineName: "DEV-02", version: "1.0.0" },
  { id: "a12", timestamp: h(36), siteId: 2, siteName: "Staging Site", type: "connection", action: "disconnect", title: "Connection lost to Staging Site", metadata: { reason: "timeout" }, source: "go" },
  { id: "a13", timestamp: h(48), siteId: 1, siteName: "Production Site", type: "config", action: "update", title: "Enabled worker pool parallelism (5 workers)", metadata: { setting: "workerCount", value: 5 }, source: "go" },
  { id: "a14", timestamp: h(52), siteId: 2, siteName: "Staging Site", type: "snapshot", action: "create", title: "Incremental backup on Staging (delta: 3 tables)", metadata: { snapshotType: "Incremental", deltaTables: 3 }, source: "wordpress" },
  { id: "a15", timestamp: h(60), siteId: 3, siteName: "Dev Environment", type: "plugin", action: "install", title: "Installed query-monitor on Dev", metadata: { pluginSlug: "query-monitor" }, source: "wordpress" },
];
