import { Loader2, CheckCircle, XCircle, AlertCircle } from "lucide-react";
import { cn } from "@/lib/utils";

export type LogStatus = "Running" | "Success" | "Error" | "Warning" | "Info";

export interface LiveLogEntryProps {
  timestamp: Date;
  status: LogStatus;
  message: string;
  className?: string;
  /** Additional content to render below the main message */
  children?: React.ReactNode;
}

/**
 * Reusable live log entry component with status icons and animations.
 * Used in connection test logs, sync logs, publish logs, etc.
 */
export function LiveLogEntry({
  timestamp,
  status,
  message,
  className,
  children,
}: LiveLogEntryProps) {
  const getStatusIcon = () => {
    switch (status) {
      case "Running":
        return <Loader2 className="h-3 w-3 animate-spin text-primary" />;
      case "Success":
        return <CheckCircle className="h-3 w-3 text-primary" />;
      case "Error":
        return <XCircle className="h-3 w-3 text-destructive" />;
      case "Warning":
        return <AlertCircle className="h-3 w-3 text-warning" />;
      case "Info":
      default:
        return <CheckCircle className="h-3 w-3 text-muted-foreground" />;
    }
  };

  const getStatusColor = () => {
    switch (status) {
      case "Error":
        return "text-destructive";
      case "Success":
        return "text-primary";
      case "Warning":
        return "text-warning";
      default:
        return "";
    }
  };

  const getBackgroundColor = () => {
    switch (status) {
      case "Error":
        return "bg-destructive/10";
      case "Success":
        return "bg-primary/5";
      case "Warning":
        return "bg-warning/10";
      default:
        return "";
    }
  };

  return (
    <div className={className}>
      <div
        className={cn(
          "flex items-start gap-2 px-2 py-1 rounded font-mono text-xs",
          getBackgroundColor()
        )}
      >
        <span className="text-muted-foreground shrink-0 w-16">
          {timestamp.toLocaleTimeString([], {
            hour: "2-digit",
            minute: "2-digit",
            second: "2-digit",
          })}
        </span>
        <span className="shrink-0">{getStatusIcon()}</span>
        <span className={cn("flex-1", getStatusColor())}>{message}</span>
      </div>
      {children}
    </div>
  );
}

export default LiveLogEntry;
