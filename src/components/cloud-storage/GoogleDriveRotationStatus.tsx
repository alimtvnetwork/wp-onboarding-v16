// Google Drive Rotation Status card + manual trigger.

import { useState } from "react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Progress } from "@/components/ui/progress";
import { Loader2, RotateCcw, AlertTriangle, CheckCircle2 } from "lucide-react";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { useRotationStatus, useTriggerRotation } from "@/hooks/useCloudStorage";
import type { RotationStatus } from "@/types/cloudStorage";

interface Props {
  accountId: number;
  accountLabel: string;
}

export function GoogleDriveRotationStatus({ accountId, accountLabel }: Props) {
  const { data: status, isLoading, isError } = useRotationStatus(accountId);
  const triggerRotation = useTriggerRotation();
  const [confirmOpen, setConfirmOpen] = useState(false);

  const handleRotateNow = () => {
    triggerRotation.mutate(accountId, {
      onSettled: () => setConfirmOpen(false),
    });
  };

  if (isLoading) {
    return (
      <Card className="border border-border">
        <CardContent className="p-6 flex items-center justify-center">
          <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />
        </CardContent>
      </Card>
    );
  }

  if (isError || !status) {
    return (
      <Card className="border border-border">
        <CardContent className="p-6 text-center text-sm text-muted-foreground">
          Rotation status unavailable
        </CardContent>
      </Card>
    );
  }

  const countPct = status.maxCount > 0 ? Math.min((status.currentCount / status.maxCount) * 100, 100) : 0;
  const sizePct = status.maxSizeMB > 0 ? Math.min((status.currentSizeMB / status.maxSizeMB) * 100, 100) : 0;

  return (
    <>
      <Card className={`border ${status.isOverLimit ? "border-destructive/50" : "border-border"}`}>
        <CardHeader className="pb-3">
          <div className="flex items-center justify-between">
            <CardTitle className="text-sm font-medium">Rotation Status</CardTitle>
            {status.isOverLimit ? (
              <Badge variant="destructive" className="text-xs">Over Limit</Badge>
            ) : (
              <Badge variant="secondary" className="text-xs">OK</Badge>
            )}
          </div>
        </CardHeader>
        <CardContent className="space-y-4">
          {/* Count usage */}
          <div className="space-y-1.5">
            <div className="flex items-center justify-between text-xs">
              <span className="text-muted-foreground">Backup Count</span>
              <span className="font-mono">
                {status.currentCount} / {status.maxCount}
              </span>
            </div>
            <Progress value={countPct} className="h-2" />
          </div>

          {/* Size usage */}
          <div className="space-y-1.5">
            <div className="flex items-center justify-between text-xs">
              <span className="text-muted-foreground">Total Size</span>
              <span className="font-mono">
                {status.currentSizeMB.toFixed(1)} / {status.maxSizeMB} MB
              </span>
            </div>
            <Progress value={sizePct} className="h-2" />
          </div>

          {/* Next action */}
          <div className="flex items-center gap-2 rounded-md bg-muted/50 p-2">
            {status.isOverLimit ? (
              <AlertTriangle className="h-3.5 w-3.5 text-destructive shrink-0" />
            ) : (
              <CheckCircle2 className="h-3.5 w-3.5 text-success shrink-0" />
            )}
            <span className="text-xs text-muted-foreground">{status.nextAction}</span>
          </div>

          {/* Manual trigger */}
          <Button
            variant="outline"
            size="sm"
            className="w-full"
            onClick={() => setConfirmOpen(true)}
            disabled={triggerRotation.isPending}
          >
            {triggerRotation.isPending ? (
              <Loader2 className="h-3.5 w-3.5 mr-1.5 animate-spin" />
            ) : (
              <RotateCcw className="h-3.5 w-3.5 mr-1.5" />
            )}
            Rotate Now
          </Button>
        </CardContent>
      </Card>

      <AlertDialog open={confirmOpen} onOpenChange={setConfirmOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Trigger Manual Rotation</AlertDialogTitle>
            <AlertDialogDescription>
              This will apply the rotation policy to <strong>{accountLabel}</strong>, potentially
              deleting or archiving older backups. This cannot be undone.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction onClick={handleRotateNow}>
              Rotate Now
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}
