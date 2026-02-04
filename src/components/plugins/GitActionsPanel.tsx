import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from "@/components/ui/collapsible";
import {
  GitBranch,
  GitCommit,
  GitPullRequest,
  Upload,
  Loader2,
  ChevronDown,
  ChevronRight,
  Check,
  AlertCircle,
  RefreshCw,
} from "lucide-react";
import { api, Plugin } from "@/lib/api";
import { toast } from "sonner";
import { useQueryClient } from "@tanstack/react-query";

interface GitStatus {
  branch: string;
  ahead: number;
  behind: number;
  staged: number;
  modified: number;
  untracked: number;
  hasChanges: boolean;
  lastCommit?: string;
}

interface GitActionsPanelProps {
  plugin: Plugin;
}

export function GitActionsPanel({ plugin }: GitActionsPanelProps) {
  const queryClient = useQueryClient();
  const [isOpen, setIsOpen] = useState(false);
  const [isPulling, setIsPulling] = useState(false);
  const [isCommitting, setIsCommitting] = useState(false);
  const [isPushing, setIsPushing] = useState(false);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [commitMessage, setCommitMessage] = useState("");
  const [gitStatus, setGitStatus] = useState<GitStatus | null>(null);

  const handleRefreshStatus = async () => {
    setIsRefreshing(true);
    try {
      const response = await api.gitStatus(plugin.id);
      if (response.success && response.data) {
        setGitStatus(response.data);
      } else {
        toast.error(response.error?.message || "Failed to get git status");
      }
    } catch (error) {
      toast.error("Failed to refresh git status");
    } finally {
      setIsRefreshing(false);
    }
  };

  const handlePull = async () => {
    setIsPulling(true);
    try {
      const response = await api.gitPull(plugin.id);
      if (response.success) {
        toast.success(`Pulled ${response.data?.filesChanged || 0} changes from ${response.data?.branch || 'origin'}`);
        queryClient.invalidateQueries({ queryKey: ["plugins"] });
        handleRefreshStatus();
      } else {
        toast.error(response.error?.message || "Git pull failed");
      }
    } catch (error) {
      toast.error("Git pull failed");
    } finally {
      setIsPulling(false);
    }
  };

  const handleCommit = async () => {
    if (!commitMessage.trim()) {
      toast.error("Please enter a commit message");
      return;
    }
    setIsCommitting(true);
    try {
      const response = await api.gitCommit(plugin.id, commitMessage);
      if (response.success) {
        toast.success(`Committed: ${commitMessage}`);
        setCommitMessage("");
        handleRefreshStatus();
      } else {
        toast.error(response.error?.message || "Commit failed");
      }
    } catch (error) {
      toast.error("Commit failed");
    } finally {
      setIsCommitting(false);
    }
  };

  const handlePush = async () => {
    setIsPushing(true);
    try {
      const response = await api.gitPush(plugin.id);
      if (response.success) {
        toast.success("Pushed to remote successfully");
        handleRefreshStatus();
      } else {
        toast.error(response.error?.message || "Push failed");
      }
    } catch (error) {
      toast.error("Push failed");
    } finally {
      setIsPushing(false);
    }
  };

  return (
    <Collapsible open={isOpen} onOpenChange={setIsOpen}>
      <CollapsibleTrigger asChild>
        <Button
          variant="ghost"
          size="sm"
          className="w-full justify-between h-8 px-2"
          onClick={() => {
            if (!isOpen && !gitStatus) {
              handleRefreshStatus();
            }
          }}
        >
          <div className="flex items-center gap-2">
            <GitBranch className="h-4 w-4 text-primary" />
            <span className="text-xs font-medium">Git</span>
            {gitStatus && (
              <div className="flex items-center gap-1">
                {gitStatus.hasChanges ? (
                  <Badge variant="outline" className="text-[10px] h-4 px-1 text-warning border-warning/30">
                    {gitStatus.modified + gitStatus.staged} changes
                  </Badge>
                ) : (
                  <Badge variant="outline" className="text-[10px] h-4 px-1 text-primary border-primary/30">
                    <Check className="h-2.5 w-2.5 mr-0.5" />
                    clean
                  </Badge>
                )}
              </div>
            )}
          </div>
          {isOpen ? (
            <ChevronDown className="h-4 w-4 text-muted-foreground" />
          ) : (
            <ChevronRight className="h-4 w-4 text-muted-foreground" />
          )}
        </Button>
      </CollapsibleTrigger>
      <CollapsibleContent>
        <Card className="mt-2 border-dashed">
          <CardContent className="p-3 space-y-3">
            {/* Git Status */}
            <div className="flex items-center justify-between text-xs">
              <div className="flex items-center gap-2">
                {gitStatus ? (
                  <>
                    <Badge variant="secondary" className="text-[10px] h-5">
                      <GitBranch className="h-3 w-3 mr-1" />
                      {gitStatus.branch}
                    </Badge>
                    {gitStatus.ahead > 0 && (
                      <Badge variant="outline" className="text-[10px] h-5 text-primary">
                        ↑{gitStatus.ahead}
                      </Badge>
                    )}
                    {gitStatus.behind > 0 && (
                      <Badge variant="outline" className="text-[10px] h-5 text-warning">
                        ↓{gitStatus.behind}
                      </Badge>
                    )}
                  </>
                ) : (
                  <span className="text-muted-foreground">Loading status...</span>
                )}
              </div>
              <Button
                variant="ghost"
                size="icon"
                className="h-6 w-6"
                onClick={handleRefreshStatus}
                disabled={isRefreshing}
              >
                <RefreshCw className={`h-3 w-3 ${isRefreshing ? 'animate-spin' : ''}`} />
              </Button>
            </div>

            {/* Action Buttons */}
            <div className="flex gap-2">
              <Button
                variant="outline"
                size="sm"
                className="flex-1 h-7 text-xs"
                onClick={handlePull}
                disabled={isPulling}
              >
                {isPulling ? (
                  <Loader2 className="h-3 w-3 animate-spin mr-1" />
                ) : (
                  <GitPullRequest className="h-3 w-3 mr-1" />
                )}
                Pull
              </Button>
              <Button
                variant="outline"
                size="sm"
                className="flex-1 h-7 text-xs"
                onClick={handlePush}
                disabled={isPushing || (gitStatus && gitStatus.ahead === 0)}
              >
                {isPushing ? (
                  <Loader2 className="h-3 w-3 animate-spin mr-1" />
                ) : (
                  <Upload className="h-3 w-3 mr-1" />
                )}
                Push
              </Button>
            </div>

            {/* Commit Section */}
            {gitStatus?.hasChanges && (
              <div className="space-y-2 pt-2 border-t">
                <div className="flex items-center gap-2 text-xs text-muted-foreground">
                  <AlertCircle className="h-3 w-3" />
                  <span>
                    {gitStatus.staged} staged, {gitStatus.modified} modified, {gitStatus.untracked} untracked
                  </span>
                </div>
                <div className="flex gap-2">
                  <Input
                    placeholder="Commit message..."
                    value={commitMessage}
                    onChange={(e) => setCommitMessage(e.target.value)}
                    className="h-7 text-xs"
                    onKeyDown={(e) => {
                      if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        handleCommit();
                      }
                    }}
                  />
                  <Button
                    variant="default"
                    size="sm"
                    className="h-7 text-xs"
                    onClick={handleCommit}
                    disabled={isCommitting || !commitMessage.trim()}
                  >
                    {isCommitting ? (
                      <Loader2 className="h-3 w-3 animate-spin" />
                    ) : (
                      <GitCommit className="h-3 w-3" />
                    )}
                  </Button>
                </div>
              </div>
            )}
          </CardContent>
        </Card>
      </CollapsibleContent>
    </Collapsible>
  );
}
