import { useState, useCallback } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from "@/components/ui/collapsible";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import {
  FileText,
  Trash2,
  Mail,
  Loader2,
  ChevronDown,
  ChevronRight,
  RefreshCw,
  AlertTriangle,
  CheckCircle,
  Archive,
} from "lucide-react";
import { api } from "@/lib/api";
import type {
  RemoteLogsStatusResponse,
  RemoteLogsClearResponse,
} from "@/lib/api/types";
import { toast } from "sonner";

interface RemoteLogsPanelProps {
  siteId: number;
  siteName?: string;
}

function formatBytes(bytes: number): string {
  if (bytes === 0) return "0 B";
  const units = ["B", "KB", "MB", "GB"];
  const i = Math.floor(Math.log(bytes) / Math.log(1024));
  return `${(bytes / Math.pow(1024, i)).toFixed(i > 0 ? 1 : 0)} ${units[i]}`;
}

export function RemoteLogsPanel({ siteId, siteName }: RemoteLogsPanelProps) {
  const [isOpen, setIsOpen] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [status, setStatus] = useState<RemoteLogsStatusResponse | null>(null);

  // Clear state
  const [clearToken, setClearToken] = useState<string | null>(null);
  const [clearExpiry, setClearExpiry] = useState<number>(0);
  const [isClearing, setIsClearing] = useState(false);
  const [isConfirming, setIsConfirming] = useState(false);

  // Email state
  const [showEmailDialog, setShowEmailDialog] = useState(false);
  const [emailRecipient, setEmailRecipient] = useState("");
  const [includeArchives, setIncludeArchives] = useState(false);
  const [isSendingEmail, setIsSendingEmail] = useState(false);

  const fetchStatus = useCallback(async () => {
    setIsLoading(true);

    try {
      const response = await api.getRemoteLogsStatus(siteId);
      setStatus(response.data ?? response as any);
    } catch {
      toast.error("Failed to fetch log status");
    } finally {
      setIsLoading(false);
    }
  }, [siteId]);

  const handleOpen = useCallback(
    (open: boolean) => {
      setIsOpen(open);

      if (open && !status) {
        fetchStatus();
      }
    },
    [status, fetchStatus]
  );

  // ── Two-Step Clear ────────────────────────────────────────────

  const handleClearStep1 = async () => {
    setIsClearing(true);

    try {
      const response = await api.clearRemoteLogs(siteId);
      const data = response.data ?? response as any;
      setClearToken(data.Token ?? data.token);
      setClearExpiry(data.ExpiresIn ?? data.expiresIn ?? 60);
      toast.info("Clear token issued — confirm within " + (data.ExpiresIn ?? data.expiresIn ?? 60) + "s");
    } catch {
      toast.error("Failed to initiate log clearing");
    } finally {
      setIsClearing(false);
    }
  };

  const handleClearConfirm = async () => {
    if (!clearToken) return;

    setIsConfirming(true);

    try {
      await api.confirmClearRemoteLogs(siteId, clearToken);
      toast.success("Remote logs cleared successfully");
      setClearToken(null);
      await fetchStatus();
    } catch {
      toast.error("Failed to confirm log clearing");
    } finally {
      setIsConfirming(false);
    }
  };

  const handleClearCancel = () => {
    setClearToken(null);
  };

  // ── Email Logs ────────────────────────────────────────────────

  const handleSendEmail = async () => {
    setIsSendingEmail(true);

    try {
      await api.emailRemoteLogs(siteId, {
        recipient: emailRecipient || undefined,
        include_archives: includeArchives,
      });
      toast.success("Logs emailed successfully");
      setShowEmailDialog(false);
      setEmailRecipient("");
      setIncludeArchives(false);
    } catch {
      toast.error("Failed to email logs");
    } finally {
      setIsSendingEmail(false);
    }
  };

  const hasFiles = status?.Files && status.Files.length > 0;

  return (
    <Collapsible open={isOpen} onOpenChange={handleOpen}>
      <Card>
        <CollapsibleTrigger asChild>
          <CardHeader className="cursor-pointer hover:bg-muted/50 transition-colors">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2">
                {isOpen ? (
                  <ChevronDown className="h-4 w-4 text-muted-foreground" />
                ) : (
                  <ChevronRight className="h-4 w-4 text-muted-foreground" />
                )}
                <FileText className="h-4 w-4 text-muted-foreground" />
                <CardTitle className="text-sm font-medium">
                  Remote Logs
                </CardTitle>
              </div>
              {status && (
                <Badge variant="secondary" className="text-xs">
                  {formatBytes(status.TotalSizeBytes)}
                  {status.ArchiveCount > 0 && (
                    <span className="ml-1 text-muted-foreground">
                      · {status.ArchiveCount} archived
                    </span>
                  )}
                </Badge>
              )}
            </div>
          </CardHeader>
        </CollapsibleTrigger>

        <CollapsibleContent>
          <CardContent className="space-y-4 pt-0">
            {/* Loading */}
            {isLoading && (
              <div className="flex items-center justify-center py-6">
                <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />
              </div>
            )}

            {/* File List */}
            {!isLoading && status && (
              <>
                {hasFiles ? (
                  <div className="space-y-1.5">
                    {status.Files.map((file) => (
                      <div
                        key={file.Name}
                        className="flex items-center justify-between rounded-md bg-muted/40 px-3 py-2 text-sm"
                      >
                        <span className="font-mono text-xs text-foreground">
                          {file.Name}
                        </span>
                        <div className="flex items-center gap-3 text-xs text-muted-foreground">
                          <span>{file.LineCount.toLocaleString()} lines</span>
                          <Badge variant="outline" className="text-xs">
                            {formatBytes(file.SizeBytes)}
                          </Badge>
                        </div>
                      </div>
                    ))}
                  </div>
                ) : (
                  <div className="flex items-center gap-2 py-4 text-sm text-muted-foreground">
                    <CheckCircle className="h-4 w-4 text-success" />
                    No log files found
                  </div>
                )}

                {status.ArchiveCount > 0 && (
                  <div className="flex items-center gap-2 text-xs text-muted-foreground">
                    <Archive className="h-3.5 w-3.5" />
                    {status.ArchiveCount} archived rotation
                    {status.ArchiveCount !== 1 ? "s" : ""}
                  </div>
                )}

                {/* Actions */}
                <div className="flex items-center gap-2 border-t pt-3">
                  <Button
                    size="sm"
                    variant="outline"
                    onClick={fetchStatus}
                    disabled={isLoading}
                  >
                    <RefreshCw className="mr-1.5 h-3.5 w-3.5" />
                    Refresh
                  </Button>

                  {/* Clear — two-step */}
                  {!clearToken ? (
                    <Button
                      size="sm"
                      variant="destructive"
                      onClick={handleClearStep1}
                      disabled={isClearing || !hasFiles}
                    >
                      {isClearing ? (
                        <Loader2 className="mr-1.5 h-3.5 w-3.5 animate-spin" />
                      ) : (
                        <Trash2 className="mr-1.5 h-3.5 w-3.5" />
                      )}
                      Clear Logs
                    </Button>
                  ) : (
                    <div className="flex items-center gap-1.5">
                      <Button
                        size="sm"
                        variant="destructive"
                        onClick={handleClearConfirm}
                        disabled={isConfirming}
                      >
                        {isConfirming ? (
                          <Loader2 className="mr-1.5 h-3.5 w-3.5 animate-spin" />
                        ) : (
                          <AlertTriangle className="mr-1.5 h-3.5 w-3.5" />
                        )}
                        Confirm ({clearExpiry}s)
                      </Button>
                      <Button
                        size="sm"
                        variant="ghost"
                        onClick={handleClearCancel}
                      >
                        Cancel
                      </Button>
                    </div>
                  )}

                  {/* Email */}
                  <Button
                    size="sm"
                    variant="outline"
                    onClick={() => setShowEmailDialog(true)}
                    disabled={!hasFiles}
                  >
                    <Mail className="mr-1.5 h-3.5 w-3.5" />
                    Email Logs
                  </Button>
                </div>
              </>
            )}
          </CardContent>
        </CollapsibleContent>
      </Card>

      {/* Email Dialog */}
      <Dialog open={showEmailDialog} onOpenChange={setShowEmailDialog}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Email Log Files</DialogTitle>
            <DialogDescription>
              Send log files as attachments
              {siteName ? ` for ${siteName}` : ""}.
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-4 py-2">
            <div className="space-y-2">
              <Label htmlFor="email-recipient">
                Recipient (optional — defaults to admin)
              </Label>
              <Input
                id="email-recipient"
                type="email"
                placeholder="admin@example.com"
                value={emailRecipient}
                onChange={(e) => setEmailRecipient(e.target.value)}
              />
            </div>

            <div className="flex items-center gap-2">
              <Checkbox
                id="include-archives"
                checked={includeArchives}
                onCheckedChange={(checked) =>
                  setIncludeArchives(checked === true)
                }
              />
              <Label htmlFor="include-archives" className="text-sm">
                Include archived log rotations
              </Label>
            </div>
          </div>

          <DialogFooter>
            <Button
              variant="outline"
              onClick={() => setShowEmailDialog(false)}
            >
              Cancel
            </Button>
            <Button onClick={handleSendEmail} disabled={isSendingEmail}>
              {isSendingEmail ? (
                <Loader2 className="mr-1.5 h-3.5 w-3.5 animate-spin" />
              ) : (
                <Mail className="mr-1.5 h-3.5 w-3.5" />
              )}
              Send Email
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </Collapsible>
  );
}
