import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { AlertCircle } from "lucide-react";
import { useErrorStore } from "@/stores/errorStore";
import { useErrorHistory } from "@/hooks/useErrorHistory";
import { ErrorHistoryDrawer } from "./ErrorHistoryDrawer";
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from "@/components/ui/tooltip";

export function ErrorQueueBadge() {
  const [drawerOpen, setDrawerOpen] = useState(false);
  const { recentErrors } = useErrorStore();
  const { total } = useErrorHistory();
  
  // Show session errors count, fall back to total from backend
  const sessionCount = recentErrors.length;
  const displayCount = sessionCount || total;
  
  if (displayCount === 0) {
    return null;
  }
  
  return (
    <>
      <TooltipProvider>
        <Tooltip>
          <TooltipTrigger asChild>
            <Button
              variant="ghost"
              size="sm"
              className="relative h-8 px-2 text-destructive hover:text-destructive hover:bg-destructive/10"
              onClick={() => setDrawerOpen(true)}
            >
              <AlertCircle className="h-4 w-4" />
              <Badge 
                variant="destructive" 
                className="absolute -top-1 -right-1 h-5 min-w-5 px-1 text-xs"
              >
                {displayCount > 99 ? "99+" : displayCount}
              </Badge>
            </Button>
          </TooltipTrigger>
          <TooltipContent>
            <p>{displayCount} error{displayCount !== 1 ? "s" : ""} captured</p>
            <p className="text-xs text-muted-foreground">Click to view history</p>
          </TooltipContent>
        </Tooltip>
      </TooltipProvider>
      
      <ErrorHistoryDrawer open={drawerOpen} onOpenChange={setDrawerOpen} />
    </>
  );
}
