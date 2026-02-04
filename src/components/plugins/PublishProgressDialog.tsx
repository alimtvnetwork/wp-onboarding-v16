import { useState, useEffect } from "react";
import { Progress } from "@/components/ui/progress";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { LiveLogEntry } from "@/components/shared/LiveLogEntry";
import { wsClient, WS_EVENTS } from "@/lib/ws";
import { Check, X, Upload, AlertCircle } from "lucide-react";
import { cn } from "@/lib/utils";

export interface PublishStage {
  name: string;
  label: string;
  status: "pending" | "running" | "success" | "error" | "skipped";
  message?: string;
  startedAt?: string;
  completedAt?: string;
}

interface PublishProgressPayload {
  publishId: string;
  pluginId: number;
  siteId: number;
  stage: string;
  progress: number;
  message: string;
  status: "running" | "success" | "error";
}

interface PublishCompletePayload {
  publishId: string;
  pluginId: number;
  siteId: number;
  success: boolean;
  filesUpdated?: number;
  error?: string;
  stages: PublishStage[];
}

interface PublishProgressDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  pluginName: string;
  siteName: string;
  pluginId: number;
  siteId: number;
  onComplete?: (success: boolean) => void;
}

const STAGE_LABELS: Record<string, string> = {
  backup: "Creating Backup",
  package: "Packaging Files",
  upload: "Uploading to Site",
  activate: "Activating Plugin",
  cleanup: "Cleaning Up",
  verify: "Verifying Deployment",
};

const DEFAULT_STAGES: PublishStage[] = [
  { name: "backup", label: "Creating Backup", status: "pending" },
  { name: "package", label: "Packaging Files", status: "pending" },
  { name: "upload", label: "Uploading to Site", status: "pending" },
  { name: "activate", label: "Activating Plugin", status: "pending" },
];

export function PublishProgressDialog({
  open,
  onOpenChange,
  pluginName,
  siteName,
  pluginId,
  siteId,
  onComplete,
}: PublishProgressDialogProps) {
  const [stages, setStages] = useState<PublishStage[]>(DEFAULT_STAGES);
  const [overallProgress, setOverallProgress] = useState(0);
  const [isComplete, setIsComplete] = useState(false);
  const [isSuccess, setIsSuccess] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [filesUpdated, setFilesUpdated] = useState<number | null>(null);

  // Reset state when dialog opens
  useEffect(() => {
    if (open) {
      setStages(DEFAULT_STAGES.map(s => ({ ...s, status: "pending" })));
      setOverallProgress(0);
      setIsComplete(false);
      setIsSuccess(false);
      setErrorMessage(null);
      setFilesUpdated(null);
    }
  }, [open]);

  // Listen for WebSocket events
  useEffect(() => {
    if (!open) return;

    // Handle progress updates
    const unsubProgress = wsClient.on(WS_EVENTS.PUBLISH_STARTED, (data: unknown) => {
      const payload = data as { pluginId: number; siteId: number };
      if (payload.pluginId === pluginId && payload.siteId === siteId) {
        setStages(prev => prev.map(s => 
          s.name === "backup" ? { ...s, status: "running" } : s
        ));
      }
    });

    const unsubStageProgress = wsClient.on("publish_progress", (data: unknown) => {
      const payload = data as PublishProgressPayload;
      if (payload.pluginId === pluginId && payload.siteId === siteId) {
        setStages(prev => prev.map(s => {
          if (s.name === payload.stage) {
            return {
              ...s,
              status: payload.status,
              message: payload.message,
            };
          }
          return s;
        }));
        setOverallProgress(payload.progress);
      }
    });

    const unsubComplete = wsClient.on(WS_EVENTS.PUBLISH_COMPLETE, (data: unknown) => {
      const payload = data as PublishCompletePayload;
      if (payload.pluginId === pluginId && payload.siteId === siteId) {
        setIsComplete(true);
        setIsSuccess(payload.success);
        setFilesUpdated(payload.filesUpdated ?? null);
        setOverallProgress(100);
        
        if (payload.error) {
          setErrorMessage(payload.error);
        }
        
        if (payload.stages) {
          setStages(payload.stages);
        } else {
          // Mark all stages as complete if no stages provided
          setStages(prev => prev.map(s => ({
            ...s,
            status: payload.success ? "success" : (s.status === "running" ? "error" : s.status)
          })));
        }

        onComplete?.(payload.success);
      }
    });

    return () => {
      unsubProgress();
      unsubStageProgress();
      unsubComplete();
    };
  }, [open, pluginId, siteId, onComplete]);

  const getStageIcon = (stage: PublishStage) => {
    switch (stage.status) {
      case "success":
        return <Check className="h-4 w-4 text-primary" />;
      case "error":
        return <X className="h-4 w-4 text-destructive" />;
      case "running":
        return <div className="h-4 w-4 border-2 border-primary border-t-transparent rounded-full animate-spin" />;
      default:
        return <div className="h-4 w-4 rounded-full border-2 border-muted-foreground/30" />;
    }
  };

  const activeStageCount = stages.filter(s => s.status === "success" || s.status === "running").length;
  const totalStages = stages.length;

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <Upload className="h-5 w-5 text-primary" />
            {isComplete ? (isSuccess ? "Publish Complete" : "Publish Failed") : "Publishing..."}
          </DialogTitle>
          <DialogDescription>
            Deploying <strong>{pluginName}</strong> to <strong>{siteName}</strong>
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4 py-4">
          {/* Overall Progress */}
          <div className="space-y-2">
            <div className="flex justify-between text-sm">
              <span className="text-muted-foreground">
                Stage {Math.min(activeStageCount + 1, totalStages)} of {totalStages}
              </span>
              <span className="font-medium">{Math.round(overallProgress)}%</span>
            </div>
            <Progress value={overallProgress} className="h-2" />
          </div>

          {/* Stage List */}
          <div className="space-y-2">
            {stages.map((stage) => (
              <div
                key={stage.name}
                className={cn(
                  "flex items-center gap-3 p-3 rounded-lg border transition-colors",
                  stage.status === "running" && "border-primary bg-primary/5",
                  stage.status === "success" && "border-primary/30 bg-primary/5",
                  stage.status === "error" && "border-destructive bg-destructive/5",
                  stage.status === "pending" && "border-border opacity-60"
                )}
              >
                {getStageIcon(stage)}
                <div className="flex-1 min-w-0">
                  <p className="text-sm font-medium">{stage.label}</p>
                  {stage.message && (
                    <p className="text-xs text-muted-foreground truncate">
                      {stage.message}
                    </p>
                  )}
                </div>
                {stage.status === "success" && (
                  <Badge variant="outline" className="text-xs text-primary border-primary/30">
                    Done
                  </Badge>
                )}
                {stage.status === "error" && (
                  <Badge variant="outline" className="text-xs text-destructive border-destructive/30">
                    Failed
                  </Badge>
                )}
              </div>
            ))}
          </div>

          {/* Success Message */}
          {isComplete && isSuccess && filesUpdated !== null && (
            <div className="rounded-lg border border-primary/30 bg-primary/5 p-4">
              <div className="flex items-center gap-2">
                <Check className="h-5 w-5 text-primary" />
                <span className="font-medium">
                  Successfully published {filesUpdated} files
                </span>
              </div>
            </div>
          )}

          {/* Error Message */}
          {isComplete && !isSuccess && errorMessage && (
            <div className="rounded-lg border border-destructive/30 bg-destructive/5 p-4">
              <div className="flex items-start gap-2">
                <AlertCircle className="h-5 w-5 text-destructive mt-0.5" />
                <div>
                  <p className="font-medium text-destructive">Publish Failed</p>
                  <p className="text-sm text-muted-foreground mt-1">{errorMessage}</p>
                </div>
              </div>
            </div>
          )}
        </div>

        <DialogFooter>
          <Button
            variant={isComplete ? "default" : "outline"}
            onClick={() => onOpenChange(false)}
          >
            {isComplete ? "Done" : "Close"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
