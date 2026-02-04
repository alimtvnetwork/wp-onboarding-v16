import { useState, useEffect } from "react";
import { AlertTriangle, RefreshCw, ExternalLink } from "lucide-react";
import { Button } from "@/components/ui/button";
import { resolveApiUrl, resolveApiBase, resolveApiOrigin } from "@/lib/endpoints";
import { useErrorStore } from "@/stores/errorStore";

interface BackendStatusProps {
  /** Polling interval in ms. Default: 10000 (10 seconds) */
  pollInterval?: number;
}

/**
 * Displays a banner when the backend is disconnected.
 * Detects backend unavailability by checking if API responses return HTML instead of JSON.
 */
export function BackendStatus({ pollInterval = 10000 }: BackendStatusProps) {
  const [isConnected, setIsConnected] = useState(true);
  const [isChecking, setIsChecking] = useState(false);
  const [lastError, setLastError] = useState<{ message: string; url: string } | null>(null);
  const { captureError, openErrorModal } = useErrorStore();

  const checkBackendConnection = async () => {
    setIsChecking(true);
    const healthUrl = resolveApiUrl("/health");
    
    try {
      const response = await fetch(healthUrl, {
        method: "GET",
        headers: { Accept: "application/json" },
      });

      // Always read as text first: some servers/dev proxies return HTML but still claim application/json.
      const raw = await response.text();
      const trimmed = raw.trim();

      const looksLikeJson = trimmed.startsWith("{") || trimmed.startsWith("[");
      if (!looksLikeJson) {
        const errorInfo = {
          message: "Backend returned HTML instead of JSON. This usually means the backend is not running or the API URL is misconfigured.",
          url: healthUrl,
        };
        setLastError(errorInfo);
        setIsConnected(false);
        return;
      }

      const data = JSON.parse(raw) as { success?: boolean; status?: string };
      const connected = data.success === true || data.status === "ok";
      setIsConnected(connected);
      if (connected) {
        setLastError(null);
      }
    } catch (err) {
      const errorInfo = {
        message: err instanceof Error ? err.message : "Network error - backend unreachable",
        url: healthUrl,
      };
      setLastError(errorInfo);
      setIsConnected(false);
    } finally {
      setIsChecking(false);
    }
  };

  const handleViewDetails = () => {
    const apiBase = resolveApiBase();
    const apiOrigin = resolveApiOrigin();
    
    const captured = captureError(
      {
        code: "E9005",
        message: lastError?.message || "Backend disconnected",
        details: `The frontend cannot reach the backend API. This is expected in the hosted Lovable preview since it cannot connect to localhost.`,
        timestamp: new Date().toISOString(),
      },
      {
        endpoint: "/health",
        method: "GET",
        context: {
          requestUrl: lastError?.url || resolveApiUrl("/health"),
          apiBase,
          VITE_API_URL: apiOrigin || "(not set)",
          VITE_WS_URL: import.meta.env.VITE_WS_URL || "(not set)",
          suggestion: "Run .\\run.ps1 -r locally and open http://localhost:8080 in your browser",
        },
      }
    );
    openErrorModal(captured);
  };

  useEffect(() => {
    // Initial check
    checkBackendConnection();

    // Poll periodically
    const interval = setInterval(checkBackendConnection, pollInterval);
    return () => clearInterval(interval);
  }, [pollInterval]);

  if (isConnected) {
    return null;
  }

  return (
    <div className="fixed top-0 left-0 right-0 z-[100] bg-warning text-warning-foreground px-4 py-2">
      <div className="container mx-auto flex items-center justify-between gap-4">
        <div className="flex items-center gap-2">
          <AlertTriangle className="h-4 w-4" />
          <span className="text-sm font-medium">
            Backend disconnected — API requests are returning HTML instead of JSON
          </span>
        </div>
        <div className="flex items-center gap-2">
          <span className="text-xs opacity-80">
            Run <code className="bg-warning-foreground/10 px-1 rounded">.\run.ps1 -r</code> to start the backend
          </span>
          <Button
            variant="ghost"
            size="sm"
            className="h-7 text-warning-foreground hover:bg-warning-foreground/20"
            onClick={handleViewDetails}
          >
            <ExternalLink className="h-3 w-3" />
            <span className="ml-1">View Details</span>
          </Button>
          <Button
            variant="ghost"
            size="sm"
            className="h-7 text-warning-foreground hover:bg-warning-foreground/20"
            onClick={checkBackendConnection}
            disabled={isChecking}
          >
            <RefreshCw className={cn("h-3 w-3", isChecking && "animate-spin")} />
            <span className="ml-1">Retry</span>
          </Button>
        </div>
      </div>
    </div>
  );
}

// Helper for conditional classnames
function cn(...classes: (string | boolean | undefined)[]) {
  return classes.filter(Boolean).join(" ");
}

export default BackendStatus;

