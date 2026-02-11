/**
 * Deduplicated toast wrapper around Sonner.
 * Prevents duplicate toasts with the same title+description within a cooldown window.
 */
import { toast as sonnerToast, ExternalToast } from "sonner";

const DEDUP_WINDOW_MS = 3000; // 3 seconds
const recentToasts = new Map<string, number>();

function makeKey(title: string, description?: string): string {
  return `${title}::${description ?? ""}`;
}

function isDuplicate(key: string): boolean {
  const lastShown = recentToasts.get(key);
  if (lastShown && Date.now() - lastShown < DEDUP_WINDOW_MS) {
    return true;
  }
  recentToasts.set(key, Date.now());
  // Prune old entries periodically
  if (recentToasts.size > 50) {
    const now = Date.now();
    for (const [k, t] of recentToasts) {
      if (now - t > DEDUP_WINDOW_MS) recentToasts.delete(k);
    }
  }
  return false;
}

type ToastMethod = "success" | "error" | "warning" | "info" | "message";

function createDedupMethod(method: ToastMethod) {
  return (title: string, opts?: ExternalToast) => {
    const key = makeKey(title, typeof opts?.description === "string" ? opts.description : undefined);
    if (isDuplicate(key)) return;
    return sonnerToast[method](title, opts);
  };
}

/**
 * Drop-in replacement for `toast` from sonner with built-in deduplication.
 * Usage: import { dedupToast as toast } from "@/lib/dedupToast";
 */
export const dedupToast = {
  success: createDedupMethod("success"),
  error: createDedupMethod("error"),
  warning: createDedupMethod("warning"),
  info: createDedupMethod("info"),
  message: createDedupMethod("message"),
};
