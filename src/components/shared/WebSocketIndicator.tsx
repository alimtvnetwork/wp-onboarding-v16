import { Wifi, WifiOff, RefreshCw } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from "@/components/ui/tooltip";
import { useWebSocketStatus } from "@/hooks/useWebSocketStatus";
import { cn } from "@/lib/utils";

interface WebSocketIndicatorProps {
  className?: string;
  showLabel?: boolean;
  /** When true, show toast notifications for connect/disconnect events */
  showToasts?: boolean;
}

export function WebSocketIndicator({ className, showLabel = false, showToasts = false }: WebSocketIndicatorProps) {
  const { isConnected, reconnectAttempts, maxReconnectAttempts, isReconnectEnabled, reconnect } = useWebSocketStatus({ showToasts });

  const isMaxAttemptsReached = reconnectAttempts >= maxReconnectAttempts;

  return (
    <TooltipProvider>
      <Tooltip>
        <TooltipTrigger asChild>
          <div className={cn("flex items-center gap-1.5", className)}>
            {isConnected ? (
              <Badge 
                variant="outline" 
                className="gap-1 px-2 py-0.5 text-xs border-primary/30 text-primary bg-primary/10"
              >
                <Wifi className="h-3 w-3" />
                {showLabel && <span>Live</span>}
              </Badge>
            ) : (
              <div className="flex items-center gap-1">
                <Badge 
                  variant="outline" 
                  className="gap-1 px-2 py-0.5 text-xs border-warning/30 text-warning bg-warning/10"
                >
                  <WifiOff className="h-3 w-3" />
                  {showLabel && <span>Offline</span>}
                </Badge>
                {isMaxAttemptsReached && (
                  <Button
                    variant="ghost"
                    size="sm"
                    className="h-6 w-6 p-0"
                    onClick={(e) => {
                      e.stopPropagation();
                      reconnect();
                    }}
                  >
                    <RefreshCw className="h-3 w-3" />
                  </Button>
                )}
              </div>
            )}
          </div>
        </TooltipTrigger>
        <TooltipContent side="bottom" align="end">
          <div className="text-xs space-y-1">
            <p className="font-medium">
              WebSocket: {isConnected ? "Connected" : "Disconnected"}
            </p>
            {!isConnected && (
              <>
                <p className="text-muted-foreground">
                  Reconnect attempts: {reconnectAttempts}/{maxReconnectAttempts}
                </p>
                {isMaxAttemptsReached && (
                  <p className="text-warning">
                    Max attempts reached. Click refresh to retry.
                  </p>
                )}
                {!isReconnectEnabled && !isMaxAttemptsReached && (
                  <p className="text-muted-foreground">
                    Auto-reconnect disabled
                  </p>
                )}
              </>
            )}
            <p className="text-muted-foreground pt-1 border-t border-border mt-1">
              Real-time logs and events from backend
            </p>
          </div>
        </TooltipContent>
      </Tooltip>
    </TooltipProvider>
  );
}
