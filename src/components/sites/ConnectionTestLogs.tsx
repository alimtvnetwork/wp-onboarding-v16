import { ConnectionTestStep } from "@/hooks/useConnectionTestLogs";
import { cn } from "@/lib/utils";
import { Loader2, CheckCircle, XCircle, ChevronDown, ChevronUp, Copy } from "lucide-react";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { toast } from "sonner";

interface ConnectionTestLogsProps {
  steps: ConnectionTestStep[];
  isActive: boolean;
  onClear?: () => void;
}

export function ConnectionTestLogs({ steps, isActive, onClear }: ConnectionTestLogsProps) {
  const [expanded, setExpanded] = useState(true);

  if (steps.length === 0) {
    return null;
  }

  const getStepIcon = (status: string) => {
    switch (status) {
      case "running":
        return <Loader2 className="h-3 w-3 animate-spin text-primary" />;
      case "success":
        return <CheckCircle className="h-3 w-3 text-primary" />;
      case "error":
        return <XCircle className="h-3 w-3 text-destructive" />;
      default:
        return null;
    }
  };

  const copyLogs = () => {
    const logText = steps
      .map((s) => {
        const time = s.timestamp.toLocaleTimeString();
        const details = s.details ? `\n  Details: ${JSON.stringify(s.details)}` : "";
        return `[${time}] [${s.status.toUpperCase()}] ${s.step}: ${s.message}${details}`;
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
        <div className="border-t max-h-48 overflow-y-auto">
          <div className="space-y-0.5 p-2 font-mono text-xs">
            {steps.map((step, idx) => (
              <div
                key={idx}
                className={cn(
                  "flex items-start gap-2 px-2 py-1 rounded",
                  step.status === "error" && "bg-destructive/10",
                  step.status === "success" && "bg-primary/5"
                )}
              >
                <span className="text-muted-foreground shrink-0 w-16">
                  {step.timestamp.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit", second: "2-digit" })}
                </span>
                <span className="shrink-0">{getStepIcon(step.status)}</span>
                <span
                  className={cn(
                    "flex-1",
                    step.status === "error" && "text-destructive",
                    step.status === "success" && "text-primary"
                  )}
                >
                  {step.message}
                </span>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
