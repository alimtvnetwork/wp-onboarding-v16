import { ConnectionTestStep } from "@/hooks/useConnectionTestLogs";
import { cn } from "@/lib/utils";
import { ChevronDown, ChevronUp, Copy, Terminal, Loader2 } from "lucide-react";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { toast } from "sonner";
import { LiveLogEntry } from "@/components/shared/LiveLogEntry";

interface ConnectionTestLogsProps {
  steps: ConnectionTestStep[];
  isActive: boolean;
  onClear?: () => void;
  debugMode?: boolean;
}

/**
 * Generates a curl command equivalent for a connection test step
 */
function generateCurlCommand(step: ConnectionTestStep): string | null {
  const details = step.details as Record<string, unknown> | undefined;
  
  // Generate curl commands for specific steps
  switch (step.step) {
    case "dns_check":
      if (details?.normalizedUrl || details?.url) {
        const url = (details.normalizedUrl || details.url) as string;
        return `curl -I -s -o /dev/null -w "%{http_code}" "${url}"`;
      }
      break;
    case "rest_api_check":
      if (details?.normalizedUrl || details?.url) {
        const url = (details.normalizedUrl || details.url) as string;
        return `curl -s "${url}/wp-json/" | head -c 500`;
      }
      break;
    case "auth_check":
      // We don't show the actual password in curl for security
      if (details?.url || details?.normalizedUrl) {
        const url = (details.normalizedUrl || details.url) as string;
        return `curl -s -u "USERNAME:APP_PASSWORD" "${url}/wp-json/wp/v2/users/me"`;
      }
      break;
    case "plugin_access_check":
      if (details?.url || details?.normalizedUrl) {
        const url = (details.normalizedUrl || details.url) as string;
        return `curl -s -u "USERNAME:APP_PASSWORD" "${url}/wp-json/wp/v2/plugins"`;
      }
      break;
    case "write_test":
      if (details?.url || details?.normalizedUrl) {
        const url = (details.normalizedUrl || details.url) as string;
        return `curl -s -X POST -u "USERNAME:APP_PASSWORD" -H "Content-Type: application/json" \\\n  -d '{"title":"Test","content":"Test","status":"draft"}' \\\n  "${url}/wp-json/wp/v2/posts"`;
      }
      break;
  }
  
  return null;
}

export function ConnectionTestLogs({ steps, isActive, onClear, debugMode = false }: ConnectionTestLogsProps) {
  const [expanded, setExpanded] = useState(true);
  const [showCurlCommands, setShowCurlCommands] = useState(false);

  if (steps.length === 0) {
    return null;
  }

  const copyLogs = () => {
    const logText = steps
      .map((s) => {
        const time = s.timestamp.toLocaleTimeString();
        const details = s.details ? `\n  Details: ${JSON.stringify(s.details)}` : "";
        const curl = debugMode ? generateCurlCommand(s) : null;
        const curlLine = curl ? `\n  Command: ${curl}` : "";
        return `[${time}] [${s.status.toUpperCase()}] ${s.step}: ${s.message}${details}${curlLine}`;
      })
      .join("\n");
    
    navigator.clipboard.writeText(logText);
    toast.success("Logs copied to clipboard");
  };

  return (
    <div className="border rounded-lg bg-muted/30 overflow-hidden">
      <div
        className="flex items-center justify-between px-3 py-2 cursor-pointer hover:bg-muted/50"
        onClick={() => setExpanded(!expanded)}
      >
        <div className="flex items-center gap-2">
          <span className="text-sm font-medium">Connection Log</span>
          {isActive && <Loader2 className="h-3 w-3 animate-spin text-primary" />}
          <span className="text-xs text-muted-foreground">
            ({steps.length} {steps.length === 1 ? "step" : "steps"})
          </span>
        </div>
        <div className="flex items-center gap-1">
          {debugMode && (
            <Button
              variant="ghost"
              size="sm"
              className={cn("h-6 px-2", showCurlCommands && "bg-muted")}
              onClick={(e) => {
                e.stopPropagation();
                setShowCurlCommands(!showCurlCommands);
              }}
              title="Show curl commands"
            >
              <Terminal className="h-3 w-3" />
            </Button>
          )}
          <Button
            variant="ghost"
            size="sm"
            className="h-6 px-2"
            onClick={(e) => {
              e.stopPropagation();
              copyLogs();
            }}
          >
            <Copy className="h-3 w-3" />
          </Button>
          {onClear && !isActive && (
            <Button
              variant="ghost"
              size="sm"
              className="h-6 px-2 text-xs"
              onClick={(e) => {
                e.stopPropagation();
                onClear();
              }}
            >
              Clear
            </Button>
          )}
          {expanded ? (
            <ChevronUp className="h-4 w-4 text-muted-foreground" />
          ) : (
            <ChevronDown className="h-4 w-4 text-muted-foreground" />
          )}
        </div>
      </div>

      {expanded && (
        <div className="border-t max-h-64 overflow-y-auto">
          <div className="space-y-0.5 p-2">
            {steps.map((step, idx) => {
              const curlCommand = debugMode && showCurlCommands ? generateCurlCommand(step) : null;
              
              return (
                <LiveLogEntry
                  key={`${step.step}-${idx}`}
                  timestamp={step.timestamp}
                  status={step.status}
                  message={step.message}
                >
                  {/* Show curl command when debug mode is on and commands are toggled */}
                  {curlCommand && (
                    <div className="ml-20 px-2 py-1 bg-muted/50 rounded text-[10px] text-muted-foreground mt-0.5 mb-1 overflow-x-auto font-mono">
                      <div className="flex items-start gap-2">
                        <Terminal className="h-3 w-3 shrink-0 mt-0.5" />
                        <pre className="whitespace-pre-wrap break-all">{curlCommand}</pre>
                      </div>
                    </div>
                  )}
                </LiveLogEntry>
              );
            })}
          </div>
        </div>
      )}
    </div>
  );
}
