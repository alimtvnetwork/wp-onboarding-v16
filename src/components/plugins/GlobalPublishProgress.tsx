import { useEffect, useMemo } from "react";
import { usePublishStore, initializePublishWebSocketListeners } from "@/stores/publishStore";
import { useShallow } from "zustand/react/shallow";
import { Button } from "@/components/ui/button";
import { Progress } from "@/components/ui/progress";
import { Badge } from "@/components/ui/badge";
import { ScrollArea } from "@/components/ui/scroll-area";
import {
  Sheet,
  SheetContent,
  SheetHeader,
  SheetTitle,
  SheetTrigger,
} from "@/components/ui/sheet";
import {
  Loader2,
  CheckCircle2,
  XCircle,
  Upload,
  X,
  Globe,
  Package,
  ChevronRight,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { formatDistanceToNow } from "date-fns";

/**
 * Global publish progress indicator shown in the header/sidebar.
 * Displays count of active operations and allows viewing all operations.
 */
export function GlobalPublishProgress() {
  // Use shallow comparison to prevent re-renders when array content is the same
  const operations = usePublishStore(
    useShallow((state) => Array.from(state.operations.values()))
  );
  const showGlobalProgress = usePublishStore((state) => state.showGlobalProgress);
  const toggleGlobalProgress = usePublishStore((state) => state.toggleGlobalProgress);
  const clearCompletedOperations = usePublishStore((state) => state.clearCompletedOperations);
  const removeOperation = usePublishStore((state) => state.removeOperation);
  
  // Initialize WebSocket listeners
  useEffect(() => {
    initializePublishWebSocketListeners();
  }, []);
  
  const activeOps = operations.filter(op => op.status === 'running' || op.status === 'pending');
  const completedOps = operations.filter(op => op.status === 'success' || op.status === 'error');
  const hasErrors = operations.some(op => op.status === 'error');
  const allSuccess = completedOps.length > 0 && completedOps.every(op => op.status === 'success') && activeOps.length === 0;
  
  // Calculate overall progress
  const overallProgress = activeOps.length > 0
    ? Math.round(activeOps.reduce((sum, op) => sum + op.progress, 0) / activeOps.length)
    : 100;
  
  if (operations.length === 0) return null;
  
  return (
    <Sheet open={showGlobalProgress} onOpenChange={toggleGlobalProgress}>
      <SheetTrigger asChild>
        <Button
          variant="ghost"
          size="sm"
          className={cn(
            "relative h-8 gap-1.5",
            activeOps.length > 0 && "text-primary animate-pulse",
            hasErrors && "text-destructive",
            allSuccess && "text-green-600"
          )}
        >
          {activeOps.length > 0 ? (
            <>
              <Loader2 className="h-4 w-4 animate-spin" />
              <span className="text-sm font-medium">{overallProgress}%</span>
            </>
          ) : hasErrors ? (
            <>
              <XCircle className="h-4 w-4" />
              <span className="text-sm">Failed</span>
            </>
          ) : (
            <>
              <CheckCircle2 className="h-4 w-4" />
              <span className="text-sm">Done</span>
            </>
          )}
          {operations.length > 1 && (
            <Badge variant="secondary" className="h-5 px-1.5 text-xs ml-1">
              {operations.length}
            </Badge>
          )}
        </Button>
      </SheetTrigger>
      <SheetContent className="w-[400px] sm:w-[540px] p-0">
        <SheetHeader className="p-4 border-b">
          <div className="flex items-center justify-between">
            <SheetTitle className="flex items-center gap-2">
              <Upload className="h-5 w-5" />
              Publish Operations
            </SheetTitle>
            {completedOps.length > 0 && (
              <Button
                variant="ghost"
                size="sm"
                onClick={clearCompletedOperations}
                className="text-xs"
              >
                Clear Completed
              </Button>
            )}
          </div>
          {activeOps.length > 0 && (
            <div className="mt-2">
              <div className="flex items-center justify-between text-sm mb-1">
                <span className="text-muted-foreground">
                  {activeOps.length} active operation{activeOps.length > 1 ? 's' : ''}
                </span>
                <span className="font-medium">{overallProgress}%</span>
              </div>
              <Progress value={overallProgress} className="h-2" />
            </div>
          )}
        </SheetHeader>
        
        <ScrollArea className="h-[calc(100vh-140px)]">
          <div className="p-4 space-y-3">
            {operations.length === 0 ? (
              <div className="text-center py-8 text-muted-foreground">
                <Upload className="h-8 w-8 mx-auto mb-2 opacity-50" />
                <p>No publish operations</p>
              </div>
            ) : (
              operations.map((op) => (
                <OperationCard
                  key={op.id}
                  operation={op}
                  onRemove={() => removeOperation(op.id)}
                />
              ))
            )}
          </div>
        </ScrollArea>
      </SheetContent>
    </Sheet>
  );
}

interface OperationCardProps {
  operation: ReturnType<typeof usePublishStore.getState>['operations'] extends Map<string, infer T> ? T : never;
  onRemove: () => void;
}

function OperationCard({ operation, onRemove }: OperationCardProps) {
  const isActive = operation.status === 'running' || operation.status === 'pending';
  const isSuccess = operation.status === 'success';
  const isError = operation.status === 'error';
  
  return (
    <div className={cn(
      "p-3 rounded-lg border",
      isActive && "border-primary/30 bg-primary/5",
      isSuccess && "border-green-500/30 bg-green-500/5",
      isError && "border-destructive/30 bg-destructive/5"
    )}>
      <div className="flex items-start justify-between gap-2">
        <div className="flex items-start gap-3 min-w-0 flex-1">
          <div className={cn(
            "p-1.5 rounded-md flex-shrink-0",
            isActive && "bg-primary/10",
            isSuccess && "bg-green-500/10",
            isError && "bg-destructive/10"
          )}>
            {isActive && <Loader2 className="h-4 w-4 animate-spin text-primary" />}
            {isSuccess && <CheckCircle2 className="h-4 w-4 text-green-500" />}
            {isError && <XCircle className="h-4 w-4 text-destructive" />}
          </div>
          <div className="min-w-0 flex-1">
            <div className="flex items-center gap-1.5 mb-0.5">
              <Package className="h-3.5 w-3.5 text-muted-foreground" />
              <span className="font-medium text-sm truncate">{operation.pluginName}</span>
            </div>
            <div className="flex items-center gap-1.5 text-xs text-muted-foreground">
              <Globe className="h-3 w-3" />
              <span className="truncate">{operation.siteName}</span>
              <ChevronRight className="h-3 w-3" />
              <span className="truncate">{operation.siteUrl}</span>
            </div>
          </div>
        </div>
        {!isActive && (
          <Button
            variant="ghost"
            size="sm"
            className="h-6 w-6 p-0"
            onClick={onRemove}
          >
            <X className="h-3.5 w-3.5" />
          </Button>
        )}
      </div>
      
      {/* Progress bar for active operations */}
      {isActive && (
        <div className="mt-3">
          <Progress value={operation.progress} className="h-1.5" />
          <div className="flex items-center justify-between mt-1.5 text-xs">
            <div className="flex items-center gap-2">
              {operation.stages.map((stage) => (
                <span
                  key={stage.name}
                  className={cn(
                    "capitalize",
                    stage.status === 'running' && "text-primary font-medium",
                    stage.status === 'success' && "text-green-600",
                    stage.status === 'error' && "text-destructive",
                    stage.status === 'pending' && "text-muted-foreground"
                  )}
                >
                  {stage.status === 'running' && '● '}
                  {stage.name}
                </span>
              ))}
            </div>
            <span className="font-medium">{operation.progress}%</span>
          </div>
        </div>
      )}
      
      {/* Error message */}
      {isError && operation.error && (
        <p className="mt-2 text-xs text-destructive line-clamp-2">
          {operation.error}
        </p>
      )}
      
      {/* Success summary */}
      {isSuccess && (
        <p className="mt-2 text-xs text-green-600">
          {operation.filesUpdated !== undefined
            ? `${operation.filesUpdated} files updated`
            : 'Completed successfully'
          }
        </p>
      )}
      
      {/* Timestamp */}
      <div className="mt-2 text-xs text-muted-foreground">
        {operation.completedAt
          ? `Completed ${formatDistanceToNow(new Date(operation.completedAt), { addSuffix: true })}`
          : `Started ${formatDistanceToNow(new Date(operation.startedAt), { addSuffix: true })}`
        }
      </div>
    </div>
  );
}
