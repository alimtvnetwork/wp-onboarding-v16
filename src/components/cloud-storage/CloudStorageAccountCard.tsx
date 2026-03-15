import { useState } from "react";
import { Card, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
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
import {
  MoreVertical,
  Plug,
  Pencil,
  Trash2,
  CheckCircle2,
  XCircle,
  AlertTriangle,
  Loader2,
  Clock,
  Shield,
} from "lucide-react";
import type { CloudStorageAccount } from "@/types/cloudStorage";
import { PROVIDER_CONFIG } from "@/types/cloudStorage";
import { formatDistanceToNow } from "date-fns";

interface CloudStorageAccountCardProps {
  account: CloudStorageAccount;
  onEdit: (account: CloudStorageAccount) => void;
  onDelete: (id: number) => void;
  onTest: (id: number) => void;
  isTesting: boolean;
  testResult?: { Success: boolean; Message?: string; Error?: string } | null;
}

export function CloudStorageAccountCard({
  account,
  onEdit,
  onDelete,
  onTest,
  isTesting,
  testResult,
}: CloudStorageAccountCardProps) {
  const [showDeleteDialog, setShowDeleteDialog] = useState(false);
  const config = PROVIDER_CONFIG[account.Provider];
  const hasError = !!account.LastError;
  const hasLastUsed = !!account.LastUsedAt;

  return (
    <>
      <Card className="border border-border bg-card hover:shadow-md transition-shadow">
        <CardContent className="p-4 sm:p-5">
          <div className="flex items-start justify-between gap-3">
            {/* Left: Info */}
            <div className="flex-1 min-w-0 space-y-2">
              <div className="flex items-center gap-2 flex-wrap">
                <h3 className="font-semibold text-foreground truncate">
                  {account.AccountLabel}
                </h3>
                <Badge
                  variant={account.IsActive ? "default" : "secondary"}
                  className="text-xs"
                >
                  {account.IsActive ? "Active" : "Inactive"}
                </Badge>
                {hasError && (
                  <Tooltip>
                    <TooltipTrigger>
                      <AlertTriangle className="h-4 w-4 text-destructive" />
                    </TooltipTrigger>
                    <TooltipContent className="max-w-xs">
                      <p className="text-sm">{account.LastError}</p>
                    </TooltipContent>
                  </Tooltip>
                )}
              </div>

              {/* Provider + Token mask */}
              <div className="flex items-center gap-3 text-sm text-muted-foreground">
                <span className="font-medium">{config?.label ?? account.Provider}</span>
                <span className="font-mono text-xs bg-muted px-2 py-0.5 rounded">
                  {account.TokenMask || "••••••"}
                </span>
              </div>

              {/* Repo/Folder info */}
              <div className="flex items-center gap-2 text-xs text-muted-foreground flex-wrap">
                {account.RepoOwner && account.RepoName && (
                  <span className="font-mono">
                    {account.RepoOwner}/{account.RepoName}
                  </span>
                )}
                {account.BaseUrl && (
                  <span className="truncate max-w-[200px]">{account.BaseUrl}</span>
                )}
                {account.FolderName && (
                  <span>📁 {account.FolderName}</span>
                )}
              </div>

              {/* Last used */}
              <div className="flex items-center gap-4 text-xs text-muted-foreground">
                {hasLastUsed && (
                  <span className="flex items-center gap-1">
                    <Clock className="h-3 w-3" />
                    Used {formatDistanceToNow(new Date(account.LastUsedAt), { addSuffix: true })}
                  </span>
                )}
              </div>

              {/* Test result feedback */}
              {testResult && (
                <div className={`flex items-center gap-1.5 text-xs mt-1 ${testResult.Success ? "text-success" : "text-destructive"}`}>
                  {testResult.Success ? (
                    <CheckCircle2 className="h-3.5 w-3.5" />
                  ) : (
                    <XCircle className="h-3.5 w-3.5" />
                  )}
                  <span>{testResult.Message || testResult.Error}</span>
                </div>
              )}
            </div>

            {/* Right: Actions */}
            <div className="flex items-center gap-1">
              <Tooltip>
                <TooltipTrigger asChild>
                  <Button
                    variant="ghost"
                    size="icon"
                    onClick={() => onTest(account.Id)}
                    disabled={isTesting}
                    className="h-8 w-8"
                  >
                    {isTesting ? (
                      <Loader2 className="h-4 w-4 animate-spin" />
                    ) : (
                      <Plug className="h-4 w-4" />
                    )}
                  </Button>
                </TooltipTrigger>
                <TooltipContent>Test Connection</TooltipContent>
              </Tooltip>

              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button variant="ghost" size="icon" className="h-8 w-8">
                    <MoreVertical className="h-4 w-4" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                  <DropdownMenuItem onClick={() => onEdit(account)}>
                    <Pencil className="h-4 w-4 mr-2" />
                    Edit
                  </DropdownMenuItem>
                  <DropdownMenuItem
                    onClick={() => setShowDeleteDialog(true)}
                    className="text-destructive focus:text-destructive"
                  >
                    <Trash2 className="h-4 w-4 mr-2" />
                    Delete
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Delete confirmation */}
      <AlertDialog open={showDeleteDialog} onOpenChange={setShowDeleteDialog}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete Account</AlertDialogTitle>
            <AlertDialogDescription>
              Are you sure you want to delete <strong>{account.AccountLabel}</strong>?
              This will not delete any files stored remotely, but the account credentials
              will be permanently removed.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction
              onClick={() => onDelete(account.Id)}
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
            >
              Delete
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}
