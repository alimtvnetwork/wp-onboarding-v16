import { useState, useMemo } from "react";
import { usePublishStore, PublishOperation } from "@/stores/publishStore";
import { useShallow } from "zustand/react/shallow";
import { Button } from "@/components/ui/button";
import { Progress } from "@/components/ui/progress";
import { Badge } from "@/components/ui/badge";
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover";
import { ScrollArea } from "@/components/ui/scroll-area";
import {
  Loader2,
  CheckCircle2,
  XCircle,
  FileText,
  ChevronDown,
  Clock,
  Globe,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { formatDistanceToNow } from "date-fns";

interface QuickPublishIndicatorProps {
  pluginId: number;
  pluginName: string;
  compact?: boolean;
  onViewLogs?: (operation: PublishOperation) => void;
}

/**
 * Inline progress indicator for quick publish operations.
 * Shows on plugin cards when publish is in progress.
 */
export function QuickPublishIndicator({
  pluginId,
  pluginName,
  compact = false,
  onViewLogs,
}: QuickPublishIndicatorProps) {
  const [isOpen, setIsOpen] = useState(false);
  // Use shallow comparison to prevent re-renders when array content is the same
  const operations = usePublishStore(
    useShallow((state) => 
      Array.from(state.operations.values()).filter(op => op.pluginId === pluginId)
    )
  );
  
  if (operations.length === 0) return null;
  
  // Get the most recent/active operation
  const activeOps = operations.filter(op => op.status === 'running' || op.status === 'pending');
  const completedOps = operations.filter(op => op.status === 'success' || op.status === 'error');
  const primaryOp = activeOps[0] || completedOps[0];
  
  if (!primaryOp) return null;
  
  const isActive = primaryOp.status === 'running' || primaryOp.status === 'pending';
  const isSuccess = primaryOp.status === 'success';
  const isError = primaryOp.status === 'error';
  
  // Compact mode: just show spinner/icon
  if (compact) {
    return (
      <div className="flex items-center gap-1.5">
        {isActive && (
          <div className="flex items-center gap-1 text-primary">
            <Loader2 className="h-3.5 w-3.5 animate-spin" />
            <span className="text-xs">{primaryOp.progress}%</span>
          </div>
        )}
        {isSuccess && (
          <CheckCircle2 className="h-3.5 w-3.5 text-green-500" />
        )}
        {isError && (
          <XCircle className="h-3.5 w-3.5 text-destructive" />
        )}
      </div>
    );
  }
  
  return (
    <Popover open={isOpen} onOpenChange={setIsOpen}>
      <PopoverTrigger asChild>
        <Button
          variant="ghost"
          size="sm"
          className={cn(
            "h-7 px-2 gap-1.5",
            isActive && "text-primary",
            isSuccess && "text-green-600",
            isError && "text-destructive"
          )}
        >
          {isActive && (
            <>
              <Loader2 className="h-3.5 w-3.5 animate-spin" />
              <span className="text-xs font-medium">{primaryOp.progress}%</span>
              {activeOps.length > 1 && (
                <Badge variant="secondary" className="h-4 px-1 text-[10px]">
                  +{activeOps.length - 1}
                </Badge>
              )}
            </>
          )}
          {isSuccess && (
            <>
              <CheckCircle2 className="h-3.5 w-3.5" />
              <span className="text-xs">Done</span>
            </>
          )}
          {isError && (
            <>
              <XCircle className="h-3.5 w-3.5" />
              <span className="text-xs">Failed</span>
            </>
          )}
          <ChevronDown className="h-3 w-3 opacity-50" />
        </Button>
      </PopoverTrigger>
      <PopoverContent className="w-80 p-0" align="end">
        <div className="p-3 border-b bg-muted/30">
          <h4 className="font-medium text-sm">Publish Operations</h4>
          <p className="text-xs text-muted-foreground">{pluginName}</p>
        </div>
        <ScrollArea className="max-h-64">
          <div className="p-2 space-y-2">
            {operations.map((op) => (
              <OperationItem 
                key={op.id} 
                operation={op} 
                onViewLogs={onViewLogs}
              />
            ))}
          </div>
        </ScrollArea>
      </PopoverContent>
    </Popover>
  );
}

interface OperationItemProps {
  operation: PublishOperation;
  onViewLogs?: (operation: PublishOperation) => void;
}

function OperationItem({ operation, onViewLogs }: OperationItemProps) {
  const isActive = operation.status === 'running' || operation.status === 'pending';
  const isSuccess = operation.status === 'success';
  const isError = operation.status === 'error';
  
  // Find current stage
  const currentStage = operation.stages.find(s => s.status === 'running');
  const failedStage = operation.stages.find(s => s.status === 'error');
  
  return (
    <div className={cn(
      "p-2 rounded-lg border",
      isActive && "border-primary/30 bg-primary/5",
      isSuccess && "border-green-500/30 bg-green-500/5",
      isError && "border-destructive/30 bg-destructive/5"
    )}>
      <div className="flex items-start justify-between gap-2">
        <div className="flex items-center gap-2 min-w-0">
          {isActive && <Loader2 className="h-4 w-4 animate-spin text-primary flex-shrink-0" />}
          {isSuccess && <CheckCircle2 className="h-4 w-4 text-green-500 flex-shrink-0" />}
          {isError && <XCircle className="h-4 w-4 text-destructive flex-shrink-0" />}
          <div className="min-w-0">
            <div className="flex items-center gap-1.5">
              <Globe className="h-3 w-3 text-muted-foreground flex-shrink-0" />
              <span className="text-sm font-medium truncate">{operation.siteName}</span>
            </div>
            <p className="text-xs text-muted-foreground truncate">{operation.siteUrl}</p>
          </div>
        </div>
        {onViewLogs && (
          <Button
            variant="ghost"
            size="sm"
            className="h-6 px-2"
            onClick={() => onViewLogs(operation)}
          >
            <FileText className="h-3 w-3" />
          </Button>
        )}
      </div>
      
      {isActive && (
        <div className="mt-2 space-y-1">
          <Progress value={operation.progress} className="h-1.5" />
          <div className="flex items-center justify-between text-xs text-muted-foreground">
            <span className="capitalize">
              {currentStage ? currentStage.name : 'Starting...'}
            </span>
            <span>{operation.progress}%</span>
          </div>
        </div>
      )}
      
      {isError && failedStage && (
        <p className="mt-1.5 text-xs text-destructive">
          Failed at: {failedStage.name}
          {failedStage.message && ` - ${failedStage.message}`}
        </p>
      )}
      
      {isSuccess && operation.filesUpdated !== undefined && (
        <p className="mt-1.5 text-xs text-green-600">
          {operation.filesUpdated} files updated
        </p>
      )}
      
      <div className="mt-1.5 flex items-center gap-1 text-xs text-muted-foreground">
        <Clock className="h-3 w-3" />
        <span>
          {operation.completedAt 
            ? `Completed ${formatDistanceToNow(new Date(operation.completedAt), { addSuffix: true })}`
            : `Started ${formatDistanceToNow(new Date(operation.startedAt), { addSuffix: true })}`
          }
        </span>
      </div>
    </div>
  );
}
