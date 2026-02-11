/**
 * Converts action type strings (e.g., "PLUGIN_DISABLE", "UPLOAD_SCRIPT")
 * to Title Case labels (e.g., "Plugin Disabled", "Upload Script").
 */
export function formatActionLabel(actionType: string): string {
  if (!actionType) return "Publish";

  const mapping: Record<string, string> = {
    "PLUGIN_DISABLE": "Plugin Disabled",
    "PLUGIN DISABLE": "Plugin Disabled",
    "PLUGIN_ENABLE": "Plugin Enabled",
    "PLUGIN ENABLE": "Plugin Enabled",
    "PLUGIN_DELETE": "Plugin Deleted",
    "PLUGIN DELETE": "Plugin Deleted",
    "UPLOAD_SCRIPT": "Upload Script",
    "UPLOAD SCRIPT": "Upload Script",
    "PUBLISH": "Publish",
    "SYNC": "Sync",
    "BACKUP": "Backup",
    "RESTORE": "Restore",
    // Snapshot actions
    "SNAPSHOT_CREATE": "Snapshot Created",
    "SNAPSHOT_RESTORE": "Snapshot Restored",
    "SNAPSHOT_DELETE": "Snapshot Deleted",
    "SNAPSHOT_EXPORT": "Snapshot Exported",
    "SNAPSHOT_IMPORT": "Snapshot Imported",
    "SNAPSHOT_CLEANUP": "Snapshot Cleanup",
    "SNAPSHOT_FULL_BACKUP": "Full Backup",
    "SNAPSHOT_INCREMENTAL": "Incremental Backup",
    "SNAPSHOT_RESTORE_PERTABLE": "Per-Table Restore",
    "SNAPSHOT_IMPORT_PERTABLE": "Per-Table Import",
  };

  const upper = actionType.toUpperCase().trim();
  if (mapping[upper]) return mapping[upper];

  // Fallback: convert SNAKE_CASE or SPACE_SEPARATED to Title Case
  return actionType
    .replace(/[_-]/g, " ")
    .toLowerCase()
    .replace(/\b\w/g, (c) => c.toUpperCase());
}

/**
 * Returns Tailwind classes for the action type badge.
 * "Upload Script" gets yellow bg with dark text and border per style conventions.
 */
export function getActionBadgeClasses(actionType: string): string {
  const upper = (actionType || "").toUpperCase().replace(/[_ ]/g, "");

  switch (upper) {
    case "UPLOADSCRIPT":
      return "bg-yellow-400 text-yellow-950 border border-yellow-600/50 font-semibold text-xs hover:bg-yellow-500 shadow-sm";
    case "PLUGINDISABLE":
    case "PLUGINDISABLED":
      return "bg-orange-500/15 text-orange-700 border border-orange-500/30 font-semibold text-xs dark:text-orange-400";
    case "PLUGINENABLE":
    case "PLUGINENABLED":
      return "bg-emerald-500/15 text-emerald-700 border border-emerald-500/30 font-semibold text-xs dark:text-emerald-400";
    case "PLUGINDELETE":
    case "PLUGINDELETED":
      return "bg-red-500/15 text-red-700 border border-red-500/30 font-semibold text-xs dark:text-red-400";
    case "PUBLISH":
      return "bg-blue-500/15 text-blue-700 border border-blue-500/30 font-semibold text-xs dark:text-blue-400";
    case "SYNC":
      return "bg-purple-500/15 text-purple-700 border border-purple-500/30 font-semibold text-xs dark:text-purple-400";
    // Snapshot action badges
    case "SNAPSHOTCREATE":
    case "SNAPSHOTCREATED":
      return "bg-teal-500/15 text-teal-700 border border-teal-500/30 font-semibold text-xs dark:text-teal-400";
    case "SNAPSHOTRESTORE":
    case "SNAPSHOTRESTORED":
      return "bg-amber-500/15 text-amber-700 border border-amber-500/30 font-semibold text-xs dark:text-amber-400";
    case "SNAPSHOTDELETE":
    case "SNAPSHOTDELETED":
      return "bg-rose-500/15 text-rose-700 border border-rose-500/30 font-semibold text-xs dark:text-rose-400";
    case "SNAPSHOTEXPORT":
    case "SNAPSHOTEXPORTED":
      return "bg-cyan-500/15 text-cyan-700 border border-cyan-500/30 font-semibold text-xs dark:text-cyan-400";
    case "SNAPSHOTIMPORT":
    case "SNAPSHOTIMPORTED":
      return "bg-indigo-500/15 text-indigo-700 border border-indigo-500/30 font-semibold text-xs dark:text-indigo-400";
    case "SNAPSHOTCLEANUP":
      return "bg-slate-500/15 text-slate-700 border border-slate-500/30 font-semibold text-xs dark:text-slate-400";
    case "SNAPSHOTFULLBACKUP":
      return "bg-emerald-500/15 text-emerald-700 border border-emerald-500/30 font-semibold text-xs dark:text-emerald-400";
    case "SNAPSHOTINCREMENTAL":
      return "bg-lime-500/15 text-lime-700 border border-lime-500/30 font-semibold text-xs dark:text-lime-400";
    case "SNAPSHOTRESTOREPERTABLE":
      return "bg-amber-600/15 text-amber-800 border border-amber-600/30 font-semibold text-xs dark:text-amber-500";
    case "SNAPSHOTIMPORTPERTABLE":
      return "bg-indigo-600/15 text-indigo-800 border border-indigo-600/30 font-semibold text-xs dark:text-indigo-500";
    default:
      return "bg-secondary text-secondary-foreground border font-semibold text-xs";
  }
}

/**
 * Returns Tailwind classes for the plugin/target badge.
 * Colorful, label-like appearance.
 */
export function getPluginBadgeClasses(): string {
  return "bg-sky-500/10 text-sky-700 border-sky-500/30 font-medium text-xs dark:text-sky-400";
}
