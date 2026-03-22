import { useState, useEffect, useCallback } from "react";
import { Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import { Loader2, RefreshCw, GitBranch, FolderPlus } from "lucide-react";
import { api } from "@/lib/api";
import type {
  CloudStorageProvider,
  CloudStorageRepository,
  CloudStorageBranch,
  RepoSelectionMode,
} from "@/types/cloudStorage";

interface CloudStorageRepoSelectorProps {
  provider: CloudStorageProvider;
  accountId?: number;
  repoSelectionMode: RepoSelectionMode;
  onRepoSelectionModeChange: (mode: RepoSelectionMode) => void;
  repoName: string;
  onRepoNameChange: (name: string) => void;
  repoOwner: string;
  onRepoOwnerChange: (owner: string) => void;
  defaultBranch: string;
  onDefaultBranchChange: (branch: string) => void;
}

export function CloudStorageRepoSelector({
  provider,
  accountId,
  repoSelectionMode,
  onRepoSelectionModeChange,
  repoName,
  onRepoNameChange,
  repoOwner,
  onRepoOwnerChange,
  defaultBranch,
  onDefaultBranchChange,
}: CloudStorageRepoSelectorProps) {
  const [repos, setRepos] = useState<CloudStorageRepository[]>([]);
  const [branches, setBranches] = useState<CloudStorageBranch[]>([]);
  const [isLoadingRepos, setIsLoadingRepos] = useState(false);
  const [isLoadingBranches, setIsLoadingBranches] = useState(false);
  const [repoSearch, setRepoSearch] = useState("");

  const isGitProvider = provider === "GitHub" || provider === "GitLab";
  const hasAccountId = !!accountId;

  const loadRepos = useCallback(async () => {
    if (!hasAccountId) return;

    setIsLoadingRepos(true);
    try {
      const res = await api.getCloudStorageRepos(accountId!);
      const isSuccess = res.success && res.data;

      if (isSuccess) {
        setRepos(res.data!.Repositories || []);
      }
    } catch {
      // Silently fail — user can retry
    } finally {
      setIsLoadingRepos(false);
    }
  }, [accountId, hasAccountId]);

  const loadBranches = useCallback(async () => {
    if (!hasAccountId || !repoOwner || !repoName) return;

    setIsLoadingBranches(true);
    try {
      const fullRepo = `${repoOwner}/${repoName}`;
      const res = await api.getCloudStorageBranches(accountId!, fullRepo);
      const isSuccess = res.success && res.data;

      if (isSuccess) {
        setBranches(res.data!.Branches || []);
      }
    } catch {
      // Silently fail
    } finally {
      setIsLoadingBranches(false);
    }
  }, [accountId, repoOwner, repoName, hasAccountId]);

  // Load repos when switching to "existing" mode
  useEffect(() => {
    const shouldLoadRepos = repoSelectionMode === "existing" && hasAccountId;

    if (shouldLoadRepos) {
      loadRepos();
    }
  }, [repoSelectionMode, hasAccountId, loadRepos]);

  // Load branches when a repo is selected in "existing" mode
  useEffect(() => {
    const shouldLoadBranches = repoSelectionMode === "existing" && hasAccountId && repoName.length > 0;

    if (shouldLoadBranches) {
      loadBranches();
    }
  }, [repoSelectionMode, repoName, hasAccountId, loadBranches]);

  if (!isGitProvider) return null;

  const filteredRepos = repoSearch.length > 0
    ? repos.filter((r) => r.fullName.toLowerCase().includes(repoSearch.toLowerCase()))
    : repos;

  const handleRepoSelect = (fullName: string) => {
    const selected = repos.find((r) => r.fullName === fullName);
    const isFound = !!selected;

    if (isFound) {
      const [owner, name] = fullName.split("/");
      onRepoOwnerChange(owner);
      onRepoNameChange(name);
      onDefaultBranchChange(selected!.defaultBranch || "main");
    }
  };

  return (
    <div className="space-y-4 rounded-lg border border-border p-4">
      <Label className="text-sm font-semibold flex items-center gap-2">
        <GitBranch className="h-4 w-4 text-muted-foreground" />
        Repository Configuration
      </Label>

      <RadioGroup
        value={repoSelectionMode}
        onValueChange={(v) => onRepoSelectionModeChange(v as RepoSelectionMode)}
        className="space-y-3"
      >
        {/* Create new repo */}
        <div className="flex items-start gap-3">
          <RadioGroupItem value="create" id="repo-create" className="mt-1" />
          <div className="space-y-2 flex-1">
            <Label htmlFor="repo-create" className="flex items-center gap-2 cursor-pointer">
              <FolderPlus className="h-3.5 w-3.5" />
              Create new repository
            </Label>
            {repoSelectionMode === "create" && (
              <div className="space-y-2 pl-1">
                <Input
                  value={repoName}
                  onChange={(e) => onRepoNameChange(e.target.value)}
                  placeholder="wp-backups"
                  className="text-sm"
                />
                <p className="text-xs text-muted-foreground">
                  A private repository will be created if it doesn't exist.
                </p>
              </div>
            )}
          </div>
        </div>

        {/* Select existing repo */}
        <div className="flex items-start gap-3">
          <RadioGroupItem value="existing" id="repo-existing" className="mt-1" />
          <div className="space-y-2 flex-1">
            <Label htmlFor="repo-existing" className="flex items-center gap-2 cursor-pointer">
              <GitBranch className="h-3.5 w-3.5" />
              Select existing repository
            </Label>
            {repoSelectionMode === "existing" && (
              <div className="space-y-3 pl-1">
                {/* Repo search/select */}
                <div className="space-y-1.5">
                  <div className="flex items-center gap-2">
                    <Input
                      value={repoSearch}
                      onChange={(e) => setRepoSearch(e.target.value)}
                      placeholder="Search repositories..."
                      className="text-sm flex-1"
                    />
                    <Button
                      type="button"
                      variant="outline"
                      size="icon"
                      onClick={loadRepos}
                      disabled={isLoadingRepos || !hasAccountId}
                      className="h-9 w-9 shrink-0"
                    >
                      {isLoadingRepos
                        ? <Loader2 className="h-3.5 w-3.5 animate-spin" />
                        : <RefreshCw className="h-3.5 w-3.5" />}
                    </Button>
                  </div>

                  {!hasAccountId && (
                    <p className="text-xs text-muted-foreground">
                      Save the account first to browse repositories.
                    </p>
                  )}

                  {hasAccountId && repos.length > 0 && (
                    <Select
                      value={repoOwner && repoName ? `${repoOwner}/${repoName}` : ""}
                      onValueChange={handleRepoSelect}
                    >
                      <SelectTrigger className="text-sm">
                        <SelectValue placeholder="Select a repository" />
                      </SelectTrigger>
                      <SelectContent className="max-h-48">
                        {filteredRepos.map((repo) => (
                          <SelectItem key={repo.fullName} value={repo.fullName}>
                            <span className="flex items-center gap-2">
                              {repo.fullName}
                              {repo.isPrivate && (
                                <span className="text-[10px] px-1 py-0.5 rounded bg-muted text-muted-foreground">
                                  private
                                </span>
                              )}
                            </span>
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  )}
                </div>

                {/* Branch selector */}
                {repoName && (
                  <div className="space-y-1.5">
                    <Label className="text-xs text-muted-foreground">
                      Default branch (full backups)
                    </Label>
                    <div className="flex items-center gap-2">
                      <Select
                        value={defaultBranch}
                        onValueChange={onDefaultBranchChange}
                      >
                        <SelectTrigger className="text-sm flex-1">
                          <SelectValue placeholder="main" />
                        </SelectTrigger>
                        <SelectContent>
                          {branches.length > 0 ? (
                            branches.map((b) => (
                              <SelectItem key={b.name} value={b.name}>
                                {b.name}
                                {b.isDefault && " (default)"}
                              </SelectItem>
                            ))
                          ) : (
                            <SelectItem value="main">main</SelectItem>
                          )}
                        </SelectContent>
                      </Select>
                      <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        onClick={loadBranches}
                        disabled={isLoadingBranches}
                        className="h-9 w-9 shrink-0"
                      >
                        {isLoadingBranches
                          ? <Loader2 className="h-3.5 w-3.5 animate-spin" />
                          : <RefreshCw className="h-3.5 w-3.5" />}
                      </Button>
                    </div>
                  </div>
                )}
              </div>
            )}
          </div>
        </div>
      </RadioGroup>
    </div>
  );
}