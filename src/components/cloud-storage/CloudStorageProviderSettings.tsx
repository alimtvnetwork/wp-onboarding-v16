import { useEffect, useState } from "react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { Slider } from "@/components/ui/slider";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Loader2, Save } from "lucide-react";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import type { CloudStorageSettings, CloudStorageAccount, CloudStorageProvider, RotationPolicy } from "@/types/cloudStorage";

interface CloudStorageProviderSettingsProps {
  provider: CloudStorageProvider;
  settings: CloudStorageSettings | undefined;
  accounts: CloudStorageAccount[];
  isLoading: boolean;
  onSave: (settings: Partial<CloudStorageSettings>) => void;
  isSaving: boolean;
}

const ROTATION_POLICY_LABELS: Record<RotationPolicy, string> = {
  delete_oldest: "Delete oldest backups",
  archive_oldest: "Archive oldest to folder",
  keep_full_delete_incremental: "Keep full, delete incrementals first",
};

export function CloudStorageProviderSettings({
  provider,
  settings,
  accounts,
  isLoading,
  onSave,
  isSaving,
}: CloudStorageProviderSettingsProps) {
  const [isEnabled, setIsEnabled] = useState(false);
  const [autoBackup, setAutoBackup] = useState(false);
  const [defaultAccountId, setDefaultAccountId] = useState<string>("none");
  const [retentionCount, setRetentionCount] = useState(10);
  const [rotationEnabled, setRotationEnabled] = useState(true);
  const [backupPrefix, setBackupPrefix] = useState("wp-backup");
  // Google Drive rotation fields
  const [maxBackupCount, setMaxBackupCount] = useState(30);
  const [maxTotalSizeMB, setMaxTotalSizeMB] = useState(5000);
  const [archiveFolderId, setArchiveFolderId] = useState("");
  const [rotationPolicy, setRotationPolicy] = useState<RotationPolicy>("delete_oldest");

  useEffect(() => {
    if (settings) {
      setIsEnabled(settings.isEnabled);
      setAutoBackup(settings.autoBackupEnabled);
      setDefaultAccountId(settings.defaultAccountId?.toString() || "none");
      setRetentionCount(settings.retentionCount);
      setRotationEnabled(settings.rotationEnabled);
      setBackupPrefix(settings.backupPrefix);
      setMaxBackupCount(settings.maxBackupCount ?? 30);
      setMaxTotalSizeMB(settings.maxTotalSizeMB ?? 5000);
      setArchiveFolderId(settings.archiveFolderId ?? "");
      setRotationPolicy(settings.rotationPolicy ?? "delete_oldest");
    }
  }, [settings]);

  const providerAccounts = accounts.filter((a) => a.provider === provider && a.isActive);
  const isGoogleDrive = provider === "GoogleDrive";

  const handleSave = () => {
    const base: Partial<CloudStorageSettings> = {
      isEnabled,
      autoBackupEnabled: autoBackup,
      defaultAccountId: defaultAccountId === "none" ? null : parseInt(defaultAccountId, 10),
      retentionCount,
      rotationEnabled,
      backupPrefix,
    };

    if (isGoogleDrive) {
      base.maxBackupCount = maxBackupCount;
      base.maxTotalSizeMB = maxTotalSizeMB;
      base.archiveFolderId = archiveFolderId || undefined;
      base.rotationPolicy = rotationPolicy;
    }

    onSave(base);
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

  return (
    <Card className="border border-border">
      <CardHeader className="pb-4">
        <CardTitle className="text-base">Provider Settings</CardTitle>
      </CardHeader>
      <CardContent className="space-y-5">
        {/* Enable toggle */}
        <div className="flex items-center justify-between">
          <Label htmlFor="cs-enabled" className="text-sm">Enable {provider} Backups</Label>
          <Switch id="cs-enabled" checked={isEnabled} onCheckedChange={setIsEnabled} />
        </div>

        {/* Auto-backup toggle */}
        <div className="flex items-center justify-between">
          <Label htmlFor="cs-auto" className="text-sm">Auto-backup after publish</Label>
          <Switch id="cs-auto" checked={autoBackup} onCheckedChange={setAutoBackup} />
        </div>

        {/* Default account */}
        <div className="space-y-2">
          <Label className="text-sm">Default Account</Label>
          <Select value={defaultAccountId} onValueChange={setDefaultAccountId}>
            <SelectTrigger>
              <SelectValue placeholder="Select account" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="none">None</SelectItem>
              {providerAccounts.map((a) => (
                <SelectItem key={a.id} value={a.id.toString()}>
                  {a.accountLabel}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        {/* Rotation toggle */}
        <div className="flex items-center justify-between">
          <Label htmlFor="cs-rotation" className="text-sm">Enable rotation (delete oldest)</Label>
          <Switch id="cs-rotation" checked={rotationEnabled} onCheckedChange={setRotationEnabled} />
        </div>

        {/* Retention count slider */}
        {rotationEnabled && (
          <div className="space-y-2">
            <div className="flex items-center justify-between">
              <Label className="text-sm">Retention Count</Label>
              <span className="text-sm font-mono text-muted-foreground">{retentionCount}</span>
            </div>
            <Slider
              value={[retentionCount]}
              onValueChange={([v]) => setRetentionCount(v)}
              min={1}
              max={50}
              step={1}
            />
          </div>
        )}

        {/* Google Drive rotation config */}
        {isGoogleDrive && rotationEnabled && (
          <div className="space-y-4 pt-2 border-t border-border">
            <p className="text-xs font-medium text-muted-foreground uppercase tracking-wider">
              Google Drive Rotation
            </p>

            {/* Rotation policy */}
            <div className="space-y-2">
              <Label className="text-sm">Rotation Policy</Label>
              <Select value={rotationPolicy} onValueChange={(v) => setRotationPolicy(v as RotationPolicy)}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {(Object.entries(ROTATION_POLICY_LABELS) as [RotationPolicy, string][]).map(([key, label]) => (
                    <SelectItem key={key} value={key}>{label}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            {/* Max backup count */}
            <div className="space-y-2">
              <div className="flex items-center justify-between">
                <Label className="text-sm">Max Backup Count</Label>
                <span className="text-sm font-mono text-muted-foreground">{maxBackupCount}</span>
              </div>
              <Slider
                value={[maxBackupCount]}
                onValueChange={([v]) => setMaxBackupCount(v)}
                min={5}
                max={100}
                step={5}
              />
            </div>

            {/* Max total size */}
            <div className="space-y-2">
              <Label className="text-sm">Max Total Size (MB)</Label>
              <Input
                type="number"
                value={maxTotalSizeMB}
                onChange={(e) => setMaxTotalSizeMB(parseInt(e.target.value, 10) || 0)}
                min={100}
                max={50000}
                className="font-mono text-sm"
              />
            </div>

            {/* Archive folder ID (for archive_oldest policy) */}
            {rotationPolicy === "archive_oldest" && (
              <div className="space-y-2">
                <Label className="text-sm">Archive Folder ID</Label>
                <Input
                  value={archiveFolderId}
                  onChange={(e) => setArchiveFolderId(e.target.value)}
                  placeholder="Google Drive folder ID"
                  className="font-mono text-sm"
                />
                <p className="text-xs text-muted-foreground">
                  Folder ID where old backups will be moved instead of deleted
                </p>
              </div>
            )}
          </div>
        )}

        {/* Backup prefix */}
        <div className="space-y-2">
          <Label className="text-sm">Backup File Prefix</Label>
          <Input
            value={backupPrefix}
            onChange={(e) => setBackupPrefix(e.target.value)}
            placeholder="wp-backup"
            className="font-mono text-sm"
          />
        </div>

        {/* Save */}
        <Button onClick={handleSave} disabled={isSaving} className="w-full">
          {isSaving ? <Loader2 className="h-4 w-4 mr-2 animate-spin" /> : <Save className="h-4 w-4 mr-2" />}
          Save Settings
        </Button>
      </CardContent>
    </Card>
  );
}
