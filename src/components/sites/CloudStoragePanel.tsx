import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Separator } from "@/components/ui/separator";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import {
  Loader2,
  Plus,
  Trash2,
  CheckCircle,
  XCircle,
  Cloud,
  RefreshCw,
  Clock,
  HardDrive,
  AlertCircle,
  RotateCcw,
  GitBranch,
  FolderOpen,
  Zap,
  History,
  Settings,
} from "lucide-react";
import { toast } from "sonner";
import { Site } from "@/lib/api";
import {
  useCloudStorageAccounts,
  useCreateCloudStorageAccount,
  useDeleteCloudStorageAccount,
  useTestCloudStorageAccount,
  useCloudStorageSettings,
  useSaveCloudStorageSettings,
} from "@/hooks/useCloudStorage";
import { api, requireSuccess } from "@/lib/api";
import type {
  CloudStorageAccount,
  CloudStorageProvider,
  CloudStorageSettings,
  CloudStorageBackupHistoryRecord,
  BackupScheduleType,
  BackupStrategyType,
} from "@/types/cloudStorage";
import {
  PROVIDER_CONFIG,
  BACKUP_STRATEGY_LABELS,
  BACKUP_SCHEDULE_LABELS,
  DAY_OF_WEEK_LABELS,
} from "@/types/cloudStorage";
import { formatDistanceToNow, parseISO } from "date-fns";

// ── Props ────────────────────────────────────────────────────────

interface CloudStoragePanelProps {
  site: Site;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

// ── Main Panel ───────────────────────────────────────────────────

export function CloudStoragePanel({ site, open, onOpenChange }: CloudStoragePanelProps) {
  const [activeTab, setActiveTab] = useState("accounts");

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-2xl max-h-[90vh] flex flex-col">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <Cloud className="h-5 w-5 text-primary" />
            Cloud Storage — {site.name}
          </DialogTitle>
        </DialogHeader>
        <Tabs value={activeTab} onValueChange={setActiveTab} className="flex-1 min-h-0">
          <TabsList className="grid w-full grid-cols-3">
            <TabsTrigger value="accounts" className="gap-1.5 text-xs">
              <HardDrive className="h-3.5 w-3.5" />
              Accounts
            </TabsTrigger>
            <TabsTrigger value="schedule" className="gap-1.5 text-xs">
              <Settings className="h-3.5 w-3.5" />
              Schedule
            </TabsTrigger>
            <TabsTrigger value="history" className="gap-1.5 text-xs">
              <History className="h-3.5 w-3.5" />
              History
            </TabsTrigger>
          </TabsList>

          <ScrollArea className="flex-1 mt-3" style={{ maxHeight: "calc(90vh - 180px)" }}>
            <TabsContent value="accounts" className="mt-0 px-1">
              <AccountsTab />
            </TabsContent>
            <TabsContent value="schedule" className="mt-0 px-1">
              <ScheduleTab />
            </TabsContent>
            <TabsContent value="history" className="mt-0 px-1">
              <HistoryTab />
            </TabsContent>
          </ScrollArea>
        </Tabs>
      </DialogContent>
    </Dialog>
  );
}

// ── Accounts Tab ─────────────────────────────────────────────────

function AccountsTab() {
  const { data: accounts, isLoading } = useCloudStorageAccounts();
  const createAccount = useCreateCloudStorageAccount();
  const deleteAccount = useDeleteCloudStorageAccount();
  const testAccount = useTestCloudStorageAccount();
  const [showAddForm, setShowAddForm] = useState(false);
  const [deleteId, setDeleteId] = useState<number | null>(null);
  const [testingId, setTestingId] = useState<number | null>(null);

  const handleTest = async (id: number) => {
    setTestingId(id);
    try {
      const result = await testAccount.mutateAsync(id);
      if (result.success) {
        toast.success("Connection successful", { description: result.username ? `Authenticated as ${result.username}` : undefined });
      } else {
        toast.error("Connection failed", { description: result.error || result.message });
      }
    } catch {
      toast.error("Test failed");
    } finally {
      setTestingId(null);
    }
  };

  const handleDelete = async () => {
    if (deleteId === null) return;
    try {
      await deleteAccount.mutateAsync(deleteId);
      toast.success("Account deleted");
    } catch {
      toast.error("Failed to delete account");
    }
    setDeleteId(null);
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center py-12">
        <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />
      </div>
    );
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <p className="text-sm text-muted-foreground">
          {accounts?.length ?? 0} account{(accounts?.length ?? 0) !== 1 ? "s" : ""} configured
        </p>
        <Button size="sm" variant="outline" onClick={() => setShowAddForm(true)} className="gap-1.5">
          <Plus className="h-3.5 w-3.5" /> Add Account
        </Button>
      </div>

      {accounts && accounts.length > 0 ? (
        <div className="space-y-2">
          {accounts.map((account) => (
            <AccountCard
              key={account.id}
              account={account}
              onTest={() => handleTest(account.id)}
              onDelete={() => setDeleteId(account.id)}
              isTesting={testingId === account.id}
            />
          ))}
        </div>
      ) : (
        <div className="text-center py-8 text-muted-foreground">
          <Cloud className="h-10 w-10 mx-auto mb-2 opacity-30" />
          <p className="text-sm">No cloud storage accounts configured</p>
          <p className="text-xs mt-1">Add a GitHub, GitLab, or Google Drive account to enable cloud backups</p>
        </div>
      )}

      {showAddForm && (
        <AddAccountForm
          onClose={() => setShowAddForm(false)}
          onCreate={createAccount.mutateAsync}
          isCreating={createAccount.isPending}
        />
      )}

      <AlertDialog open={deleteId !== null} onOpenChange={() => setDeleteId(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete Cloud Storage Account</AlertDialogTitle>
            <AlertDialogDescription>
              This will remove the account and its credentials. Existing backups on the remote storage will not be deleted.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction onClick={handleDelete} className="bg-destructive text-destructive-foreground hover:bg-destructive/90">
              Delete
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}

// ── Account Card ─────────────────────────────────────────────────

interface AccountCardProps {
  account: CloudStorageAccount;
  onTest: () => void;
  onDelete: () => void;
  isTesting: boolean;
}

function AccountCard({ account, onTest, onDelete, isTesting }: AccountCardProps) {
  const providerConfig = PROVIDER_CONFIG[account.provider];
  const providerLabel = providerConfig?.label ?? account.provider;

  return (
    <div className="rounded-lg border bg-card p-3 space-y-2">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2 min-w-0">
          <ProviderIcon provider={account.provider} />
          <div className="min-w-0">
            <p className="text-sm font-medium truncate">{account.accountLabel}</p>
            <p className="text-xs text-muted-foreground truncate">{providerLabel}</p>
          </div>
        </div>
        <div className="flex items-center gap-1 shrink-0">
          <Badge variant={account.isActive ? "default" : "secondary"} className="text-[10px] h-5">
            {account.isActive ? "Active" : "Inactive"}
          </Badge>
        </div>
      </div>

      {/* Details row */}
      <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
        {account.username && <span>User: {account.username}</span>}
        {account.email && <span>Email: {account.email}</span>}
        {account.repoName && (
          <span className="flex items-center gap-1">
            <GitBranch className="h-3 w-3" /> {account.repoOwner}/{account.repoName}
          </span>
        )}
        {account.folderName && (
          <span className="flex items-center gap-1">
            <FolderOpen className="h-3 w-3" /> {account.folderName}
          </span>
        )}
        {account.tokenMask && <span>Token: {account.tokenMask}</span>}
      </div>

      {account.lastError && (
        <div className="flex items-start gap-1.5 text-xs text-destructive bg-destructive/5 rounded p-2">
          <AlertCircle className="h-3.5 w-3.5 shrink-0 mt-0.5" />
          <span>{account.lastError}</span>
        </div>
      )}

      {/* Actions */}
      <div className="flex items-center gap-1.5 pt-1">
        <Button variant="ghost" size="sm" className="h-7 text-xs gap-1" onClick={onTest} disabled={isTesting}>
          {isTesting ? <Loader2 className="h-3 w-3 animate-spin" /> : <Zap className="h-3 w-3" />}
          Test
        </Button>
        <Button variant="ghost" size="sm" className="h-7 text-xs gap-1 text-destructive hover:text-destructive" onClick={onDelete}>
          <Trash2 className="h-3 w-3" /> Remove
        </Button>
        {account.lastUsedAt && (
          <span className="ml-auto text-[10px] text-muted-foreground flex items-center gap-1">
            <Clock className="h-3 w-3" />
            Used {formatDistanceToNow(parseISO(account.lastUsedAt), { addSuffix: true })}
          </span>
        )}
      </div>
    </div>
  );
}

// ── Provider Icon ────────────────────────────────────────────────

function ProviderIcon({ provider }: { provider: CloudStorageProvider }) {
  const colorClass =
    provider === "GitHub" ? "bg-foreground/10 text-foreground" :
    provider === "GitLab" ? "bg-orange-500/10 text-orange-600" :
    "bg-blue-500/10 text-blue-600";

  return (
    <div className={`p-1.5 rounded-md ${colorClass}`}>
      {provider === "GitHub" ? <GitBranch className="h-4 w-4" /> :
       provider === "GitLab" ? <GitBranch className="h-4 w-4" /> :
       <FolderOpen className="h-4 w-4" />}
    </div>
  );
}

// ── Add Account Form ─────────────────────────────────────────────

interface AddAccountFormProps {
  onClose: () => void;
  onCreate: (body: CloudStorageAccountCreateRequest) => Promise<unknown>;
  isCreating: boolean;
}

function AddAccountForm({ onClose, onCreate, isCreating }: AddAccountFormProps) {
  const [provider, setProvider] = useState<CloudStorageProvider>("GitHub");
  const [label, setLabel] = useState("");
  const [token, setToken] = useState("");
  const [fields, setFields] = useState<Record<string, string>>({});

  const config = PROVIDER_CONFIG[provider];

  const handleSubmit = async () => {
    if (!label.trim() || !token.trim()) {
      toast.error("Label and token are required");
      return;
    }

    const body: Record<string, unknown> = {
      Provider: provider,
      AccountLabel: label.trim(),
      AccessToken: token.trim(),
    };

    for (const field of config.fields) {
      const val = fields[field.key]?.trim();
      if (val) body[field.key] = val;
    }

    try {
      await onCreate(body);
      toast.success("Account created");
      onClose();
    } catch {
      toast.error("Failed to create account");
    }
  };

  return (
    <div className="rounded-lg border bg-muted/30 p-4 space-y-3">
      <div className="flex items-center justify-between">
        <h4 className="text-sm font-medium">New Cloud Storage Account</h4>
        <Button variant="ghost" size="sm" className="h-7 text-xs" onClick={onClose}>Cancel</Button>
      </div>

      <div className="grid gap-3">
        <div>
          <Label className="text-xs">Provider</Label>
          <Select value={provider} onValueChange={(v) => { setProvider(v as CloudStorageProvider); setFields({}); }}>
            <SelectTrigger className="h-8 text-sm"><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem value="GitHub">GitHub</SelectItem>
              <SelectItem value="GitLab">GitLab</SelectItem>
              <SelectItem value="GoogleDrive">Google Drive</SelectItem>
            </SelectContent>
          </Select>
        </div>

        <div>
          <Label className="text-xs">Account Label</Label>
          <Input className="h-8 text-sm" placeholder="My backup account" value={label} onChange={(e) => setLabel(e.target.value)} />
        </div>

        <div>
          <Label className="text-xs">Access Token</Label>
          <Input
            className="h-8 text-sm font-mono"
            type="password"
            placeholder={config.tokenPlaceholder}
            value={token}
            onChange={(e) => setToken(e.target.value)}
          />
          <p className="text-[10px] text-muted-foreground mt-1">{config.tokenHelp}</p>
        </div>

        {config.fields.map((field) => (
          <div key={field.key}>
            <Label className="text-xs">{field.label}</Label>
            <Input
              className="h-8 text-sm"
              placeholder={field.placeholder}
              value={fields[field.key] || ""}
              onChange={(e) => setFields((prev) => ({ ...prev, [field.key]: e.target.value }))}
            />
            <p className="text-[10px] text-muted-foreground mt-0.5">{field.help}</p>
          </div>
        ))}
      </div>

      <Button size="sm" onClick={handleSubmit} disabled={isCreating} className="w-full gap-1.5">
        {isCreating ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Plus className="h-3.5 w-3.5" />}
        Create Account
      </Button>
    </div>
  );
}

// ── Schedule Tab ─────────────────────────────────────────────────

function ScheduleTab() {
  const [selectedProvider, setSelectedProvider] = useState<CloudStorageProvider>("GitHub");
  const { data: settings, isLoading } = useCloudStorageSettings(selectedProvider);
  const saveSettings = useSaveCloudStorageSettings();
  const [localSettings, setLocalSettings] = useState<Partial<CloudStorageSettings>>({});
  const [isDirty, setIsDirty] = useState(false);

  const merged: CloudStorageSettings = {
    isEnabled: false,
    autoBackupEnabled: false,
    defaultAccountId: null,
    retentionCount: 5,
    rotationEnabled: false,
    backupPrefix: "",
    backupType: "full_only",
    fullBackupSchedule: "weekly",
    incrementalBackupSchedule: "daily",
    fullBackupDayOfWeek: 0,
    fullBackupTimeUtc: "03:00",
    incrementalBackupTimeUtc: "03:00",
    ...settings,
    ...localSettings,
  };

  const update = <K extends keyof CloudStorageSettings>(key: K, value: CloudStorageSettings[K]) => {
    setLocalSettings((prev) => ({ ...prev, [key]: value }));
    setIsDirty(true);
  };

  const handleSave = async () => {
    try {
      await saveSettings.mutateAsync({ provider: selectedProvider, settings: localSettings });
      toast.success("Settings saved");
      setLocalSettings({});
      setIsDirty(false);
    } catch {
      toast.error("Failed to save settings");
    }
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center py-12">
        <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />
      </div>
    );
  }

  return (
    <div className="space-y-4">
      {/* Provider selector */}
      <div>
        <Label className="text-xs">Provider</Label>
        <Select value={selectedProvider} onValueChange={(v) => { setSelectedProvider(v as CloudStorageProvider); setLocalSettings({}); setIsDirty(false); }}>
          <SelectTrigger className="h-8 text-sm"><SelectValue /></SelectTrigger>
          <SelectContent>
            <SelectItem value="GitHub">GitHub</SelectItem>
            <SelectItem value="GitLab">GitLab</SelectItem>
            <SelectItem value="GoogleDrive">Google Drive</SelectItem>
          </SelectContent>
        </Select>
      </div>

      <Separator />

      {/* Enable toggle */}
      <div className="flex items-center justify-between">
        <div>
          <p className="text-sm font-medium">Cloud Backup Enabled</p>
          <p className="text-xs text-muted-foreground">Enable automatic backups to {PROVIDER_CONFIG[selectedProvider].label}</p>
        </div>
        <Switch checked={merged.isEnabled} onCheckedChange={(v) => update("isEnabled", v)} />
      </div>

      <div className="flex items-center justify-between">
        <div>
          <p className="text-sm font-medium">Auto Backup</p>
          <p className="text-xs text-muted-foreground">Automatically backup on publish</p>
        </div>
        <Switch checked={merged.autoBackupEnabled} onCheckedChange={(v) => update("autoBackupEnabled", v)} />
      </div>

      <Separator />

      {/* Backup strategy */}
      <div>
        <Label className="text-xs">Backup Strategy</Label>
        <Select value={merged.backupType} onValueChange={(v) => update("backupType", v as BackupStrategyType)}>
          <SelectTrigger className="h-8 text-sm"><SelectValue /></SelectTrigger>
          <SelectContent>
            {Object.entries(BACKUP_STRATEGY_LABELS).map(([key, label]) => (
              <SelectItem key={key} value={key}>{label}</SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>

      {/* Full backup schedule */}
      <div className="grid grid-cols-2 gap-3">
        <div>
          <Label className="text-xs">Full Backup Schedule</Label>
          <Select value={merged.fullBackupSchedule} onValueChange={(v) => update("fullBackupSchedule", v as BackupScheduleType)}>
            <SelectTrigger className="h-8 text-sm"><SelectValue /></SelectTrigger>
            <SelectContent>
              {Object.entries(BACKUP_SCHEDULE_LABELS).map(([key, label]) => (
                <SelectItem key={key} value={key}>{label}</SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <div>
          <Label className="text-xs">Time (UTC)</Label>
          <Input className="h-8 text-sm" type="time" value={merged.fullBackupTimeUtc} onChange={(e) => update("fullBackupTimeUtc", e.target.value)} />
        </div>
      </div>

      {merged.fullBackupSchedule === "weekly" && (
        <div>
          <Label className="text-xs">Day of Week</Label>
          <Select value={String(merged.fullBackupDayOfWeek)} onValueChange={(v) => update("fullBackupDayOfWeek", Number(v))}>
            <SelectTrigger className="h-8 text-sm"><SelectValue /></SelectTrigger>
            <SelectContent>
              {DAY_OF_WEEK_LABELS.map((day, i) => (
                <SelectItem key={i} value={String(i)}>{day}</SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      )}

      {/* Incremental schedule (only for full_and_incremental) */}
      {merged.backupType === "full_and_incremental" && (
        <div className="grid grid-cols-2 gap-3">
          <div>
            <Label className="text-xs">Incremental Schedule</Label>
            <Select value={merged.incrementalBackupSchedule} onValueChange={(v) => update("incrementalBackupSchedule", v as BackupScheduleType)}>
              <SelectTrigger className="h-8 text-sm"><SelectValue /></SelectTrigger>
              <SelectContent>
                {Object.entries(BACKUP_SCHEDULE_LABELS).map(([key, label]) => (
                  <SelectItem key={key} value={key}>{label}</SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <div>
            <Label className="text-xs">Time (UTC)</Label>
            <Input className="h-8 text-sm" type="time" value={merged.incrementalBackupTimeUtc} onChange={(e) => update("incrementalBackupTimeUtc", e.target.value)} />
          </div>
        </div>
      )}

      <Separator />

      {/* Retention */}
      <div className="grid grid-cols-2 gap-3">
        <div>
          <Label className="text-xs">Retention Count</Label>
          <Input
            className="h-8 text-sm"
            type="number"
            min={1}
            max={100}
            value={merged.retentionCount}
            onChange={(e) => update("retentionCount", Number(e.target.value))}
          />
          <p className="text-[10px] text-muted-foreground mt-0.5">Max backups to keep per account</p>
        </div>
        <div>
          <Label className="text-xs">Backup Prefix</Label>
          <Input className="h-8 text-sm" placeholder="wp-backup" value={merged.backupPrefix} onChange={(e) => update("backupPrefix", e.target.value)} />
        </div>
      </div>

      <div className="flex items-center justify-between">
        <div>
          <p className="text-sm font-medium">Rotation</p>
          <p className="text-xs text-muted-foreground">Auto-delete oldest backups when retention limit is reached</p>
        </div>
        <Switch checked={merged.rotationEnabled} onCheckedChange={(v) => update("rotationEnabled", v)} />
      </div>

      {/* Save button */}
      {isDirty && (
        <Button size="sm" onClick={handleSave} disabled={saveSettings.isPending} className="w-full gap-1.5">
          {saveSettings.isPending ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <CheckCircle className="h-3.5 w-3.5" />}
          Save Settings
        </Button>
      )}
    </div>
  );
}

// ── History Tab ───────────────────────────────────────────────────

function HistoryTab() {
  const { data: accounts } = useCloudStorageAccounts();
  const [selectedAccountId, setSelectedAccountId] = useState<number | null>(null);
  const [page, setPage] = useState(1);
  const [restoreId, setRestoreId] = useState<number | null>(null);
  const queryClient = useQueryClient();

  const accountId = selectedAccountId ?? accounts?.[0]?.id ?? null;

  const { data: historyData, isLoading } = useQuery({
    queryKey: ["cloud-storage-backup-history", accountId, page],
    queryFn: async () => {
      if (!accountId) return null;
      const res = await api.getCloudStorageBackupHistory(accountId, page, 10);
      return requireSuccess(res, { endpoint: `/cloud-storage/backup-history?account_id=${accountId}` }) as import("@/types/cloudStorage").CloudStorageBackupHistoryListResponse;
    },
    enabled: !!accountId,
  });

  const restoreMutation = useMutation({
    mutationFn: async (backupId: number) => {
      const res = await api.restoreCloudStorageBackup(backupId);
      return requireSuccess(res, { endpoint: "/cloud-storage/restore", method: "POST" });
    },
    onSuccess: () => {
      toast.success("Restore initiated");
      queryClient.invalidateQueries({ queryKey: ["cloud-storage-backup-history"] });
    },
    onError: () => toast.error("Restore failed"),
  });

  const deleteMutation = useMutation({
    mutationFn: async (backupId: number) => {
      const res = await api.deleteCloudStorageBackup(backupId);
      return requireSuccess(res, { endpoint: `/cloud-storage/backup-history/${backupId}`, method: "DELETE" });
    },
    onSuccess: () => {
      toast.success("Backup deleted");
      queryClient.invalidateQueries({ queryKey: ["cloud-storage-backup-history"] });
    },
    onError: () => toast.error("Delete failed"),
  });

  const handleRestore = () => {
    if (restoreId !== null) {
      restoreMutation.mutate(restoreId);
      setRestoreId(null);
    }
  };

  if (!accounts?.length) {
    return (
      <div className="text-center py-8 text-muted-foreground">
        <History className="h-10 w-10 mx-auto mb-2 opacity-30" />
        <p className="text-sm">No accounts configured</p>
        <p className="text-xs mt-1">Add a cloud storage account to view backup history</p>
      </div>
    );
  }

  const records = historyData?.backupHistory ?? [];
  const totalPages = historyData ? Math.ceil(historyData.total / historyData.perPage) : 1;

  return (
    <div className="space-y-4">
      {/* Account filter */}
      {accounts.length > 1 && (
        <Select value={String(accountId)} onValueChange={(v) => { setSelectedAccountId(Number(v)); setPage(1); }}>
          <SelectTrigger className="h-8 text-sm"><SelectValue placeholder="Select account" /></SelectTrigger>
          <SelectContent>
            {accounts.map((a) => (
              <SelectItem key={a.id} value={String(a.id)}>{a.accountLabel} ({a.provider})</SelectItem>
            ))}
          </SelectContent>
        </Select>
      )}

      {isLoading ? (
        <div className="flex items-center justify-center py-12">
          <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />
        </div>
      ) : records.length === 0 ? (
        <div className="text-center py-8 text-muted-foreground">
          <History className="h-10 w-10 mx-auto mb-2 opacity-30" />
          <p className="text-sm">No backups found</p>
        </div>
      ) : (
        <div className="space-y-2">
          {records.map((record) => (
            <BackupHistoryCard
              key={record.id}
              record={record}
              onRestore={() => setRestoreId(record.id)}
              onDelete={() => deleteMutation.mutate(record.id)}
              isDeleting={deleteMutation.isPending}
            />
          ))}
        </div>
      )}

      {/* Pagination */}
      {totalPages > 1 && (
        <div className="flex items-center justify-center gap-2">
          <Button variant="outline" size="sm" className="h-7 text-xs" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
            Previous
          </Button>
          <span className="text-xs text-muted-foreground">Page {page} of {totalPages}</span>
          <Button variant="outline" size="sm" className="h-7 text-xs" disabled={page >= totalPages} onClick={() => setPage((p) => p + 1)}>
            Next
          </Button>
        </div>
      )}

      {/* Restore confirmation */}
      <AlertDialog open={restoreId !== null} onOpenChange={() => setRestoreId(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Restore from Cloud Backup</AlertDialogTitle>
            <AlertDialogDescription>
              This will restore the plugin files from the selected cloud backup. The current files will be overwritten.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction onClick={handleRestore}>Restore</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}

// ── Backup History Card ──────────────────────────────────────────

interface BackupHistoryCardProps {
  record: CloudStorageBackupHistoryRecord;
  onRestore: () => void;
  onDelete: () => void;
  isDeleting: boolean;
}

function BackupHistoryCard({ record, onRestore, onDelete, isDeleting }: BackupHistoryCardProps) {
  const statusColor =
    record.status === "success" ? "text-primary" :
    record.status === "failed" ? "text-destructive" :
    "text-warning";

  const StatusIcon =
    record.status === "success" ? CheckCircle :
    record.status === "failed" ? XCircle :
    Loader2;

  return (
    <div className="rounded-lg border bg-card p-3 space-y-2">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2 min-w-0">
          <StatusIcon className={`h-4 w-4 shrink-0 ${statusColor} ${record.status === "uploading" || record.status === "pending" ? "animate-spin" : ""}`} />
          <div className="min-w-0">
            <p className="text-sm font-medium truncate">{record.fileName}</p>
            <p className="text-xs text-muted-foreground">
              {formatDistanceToNow(parseISO(record.createdAt), { addSuffix: true })}
            </p>
          </div>
        </div>
        <Badge variant="secondary" className="text-[10px] h-5 shrink-0">
          {record.backupType}
        </Badge>
      </div>

      <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
        <span>{formatFileSize(record.fileSizeBytes)}</span>
        {record.duration > 0 && <span>{record.duration.toFixed(1)}s</span>}
        {record.branchName && (
          <span className="flex items-center gap-1">
            <GitBranch className="h-3 w-3" /> {record.branchName}
          </span>
        )}
        {record.commitSha && <span className="font-mono">{record.commitSha.slice(0, 7)}</span>}
      </div>

      {record.errorMessage && (
        <div className="flex items-start gap-1.5 text-xs text-destructive bg-destructive/5 rounded p-2">
          <AlertCircle className="h-3.5 w-3.5 shrink-0 mt-0.5" />
          <span>{record.errorMessage}</span>
        </div>
      )}

      <div className="flex items-center gap-1.5 pt-1">
        {record.status === "success" && (
          <Button variant="ghost" size="sm" className="h-7 text-xs gap-1" onClick={onRestore}>
            <RotateCcw className="h-3 w-3" /> Restore
          </Button>
        )}
        <Button
          variant="ghost"
          size="sm"
          className="h-7 text-xs gap-1 text-destructive hover:text-destructive"
          onClick={onDelete}
          disabled={isDeleting}
        >
          <Trash2 className="h-3 w-3" /> Delete
        </Button>
        {record.remoteUrl && (
          <a
            href={record.remoteUrl}
            target="_blank"
            rel="noopener noreferrer"
            className="ml-auto text-[10px] text-primary hover:underline"
          >
            View in {record.branchName ? "repo" : "storage"} →
          </a>
        )}
      </div>
    </div>
  );
}

// ── Helpers ──────────────────────────────────────────────────────

function formatFileSize(bytes: number): string {
  if (bytes === 0) return "0 B";
  const units = ["B", "KB", "MB", "GB"];
  const i = Math.floor(Math.log(bytes) / Math.log(1024));
  return `${(bytes / Math.pow(1024, i)).toFixed(i > 0 ? 1 : 0)} ${units[i]}`;
}
