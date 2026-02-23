import type { ActivityType, ActivityMetadata } from "@/lib/api";

/** Extract metadata entries as string key-value pairs for display */
export function getMetadataEntries(metadata: ActivityMetadata): [string, unknown][] {
  return Object.entries(metadata);
}

/** Color-coded badge classes per activity type, following existing snapshot color conventions */
export function getActivityTypeBadgeClasses(type: ActivityType): string {
  switch (type) {
    case "Publish":
      return "bg-teal-500/15 text-teal-700 dark:text-teal-400 border-teal-500/25";
    case "Snapshot":
      return "bg-cyan-500/15 text-cyan-700 dark:text-cyan-400 border-cyan-500/25";
    case "Plugin":
      return "bg-indigo-500/15 text-indigo-700 dark:text-indigo-400 border-indigo-500/25";
    case "Config":
      return "bg-slate-500/15 text-slate-700 dark:text-slate-400 border-slate-500/25";
    case "Connection":
      return "bg-primary/15 text-primary border-primary/25";
    default:
      return "bg-muted text-muted-foreground border-border";
  }
}

/** Action-specific badge colors (more granular than type) */
export function getActivityActionBadgeClasses(type: ActivityType, action: string): string {
  // Snapshot sub-actions use the established color system
  if (type === "Snapshot") {
    switch (action) {
      case "create": return "bg-teal-500/15 text-teal-700 dark:text-teal-400 border-teal-500/25";
      case "restore": return "bg-amber-500/15 text-amber-700 dark:text-amber-400 border-amber-500/25";
      case "delete": return "bg-rose-500/15 text-rose-700 dark:text-rose-400 border-rose-500/25";
      case "export": return "bg-cyan-500/15 text-cyan-700 dark:text-cyan-400 border-cyan-500/25";
      case "import": return "bg-indigo-500/15 text-indigo-700 dark:text-indigo-400 border-indigo-500/25";
      case "cleanup": return "bg-slate-500/15 text-slate-700 dark:text-slate-400 border-slate-500/25";
    }
  }

  if (type === "Publish") {
    if (action === "self-update") return "bg-amber-500/15 text-amber-700 dark:text-amber-400 border-amber-500/25";

    return "bg-teal-500/15 text-teal-700 dark:text-teal-400 border-teal-500/25";
  }

  return getActivityTypeBadgeClasses(type);
}

/** Format action label in Title Case */
export function formatActivityAction(action: string): string {
  return action
    .split("-")
    .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
    .join(" ");
}

/** All activity type options for filter dropdown */
export const ACTIVITY_TYPE_OPTIONS: { value: ActivityType | "all"; label: string }[] = [
  { value: "all", label: "All Types" },
  { value: "Publish", label: "Publish" },
  { value: "Snapshot", label: "Snapshot" },
  { value: "Plugin", label: "Plugin" },
  { value: "Config", label: "Config" },
  { value: "Connection", label: "Connection" },
];
