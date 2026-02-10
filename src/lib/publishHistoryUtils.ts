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
      return "bg-yellow-400/90 text-yellow-950 border border-yellow-600/40 font-semibold text-xs hover:bg-yellow-400";
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
