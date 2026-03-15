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
import type { CloudStorageSettings, CloudStorageAccount, CloudStorageProvider } from "@/types/cloudStorage";

interface CloudStorageProviderSettingsProps {
  provider: CloudStorageProvider;
  settings: CloudStorageSettings | undefined;
  accounts: CloudStorageAccount[];
  isLoading: boolean;
  onSave: (settings: Partial<CloudStorageSettings>) => void;
  isSaving: boolean;
}

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

  useEffect(() => {
    if (settings) {
      setIsEnabled(settings.IsEnabled);
      setAutoBackup(settings.AutoBackupEnabled);
      setDefaultAccountId(settings.DefaultAccountId?.toString() || "none");
      setRetentionCount(settings.RetentionCount);
      setRotationEnabled(settings.RotationEnabled);
      setBackupPrefix(settings.BackupPrefix);
    }
  }, [settings]);

  const providerAccounts = accounts.filter((a) => a.Provider === provider && a.IsActive);

  const handleSave = () => {
    onSave({
      IsEnabled: isEnabled,
      AutoBackupEnabled: autoBackup,
      DefaultAccountId: defaultAccountId === "none" ? null : parseInt(defaultAccountId, 10),
      RetentionCount: retentionCount,
      RotationEnabled: rotationEnabled,
      BackupPrefix: backupPrefix,
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

  return (
    <Card className="border border-border">
      <CardHeader className="pb-4">
        <CardTitle className="text-base">Provider Settings</CardTitle>
      </CardHeader>
      <CardContent className="space-y-5">
        {/* Enable toggle */}
        <div className="flex items-center justify-between">
          <Label htmlFor="cs-enabled" className="text-sm">Enable {provider} Backups</Label>
          <Switch
            id="cs-enabled"
            checked={isEnabled}
            onCheckedChange={setIsEnabled}
          />
        </div>

        {/* Auto-backup toggle */}
        <div className="flex items-center justify-between">
          <Label htmlFor="cs-auto" className="text-sm">Auto-backup after publish</Label>
          <Switch
            id="cs-auto"
            checked={autoBackup}
            onCheckedChange={setAutoBackup}
          />
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
                <SelectItem key={a.Id} value={a.Id.toString()}>
                  {a.AccountLabel}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        {/* Rotation toggle */}
        <div className="flex items-center justify-between">
          <Label htmlFor="cs-rotation" className="text-sm">Enable rotation (delete oldest)</Label>
          <Switch
            id="cs-rotation"
            checked={rotationEnabled}
            onCheckedChange={setRotationEnabled}
          />
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
