import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Switch } from "@/components/ui/switch";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
import { Separator } from "@/components/ui/separator";
import { ScrollArea } from "@/components/ui/scroll-area";
import {
  Loader2,
  RefreshCw,
  Save,
  Search,
  Bug,
  FileText,
  Upload,
  HardDrive,
  AlertTriangle,
  CheckCircle,
  Shield,
} from "lucide-react";
import { api, Site, requireSuccess } from "@/lib/api";
import type { SiteSettingsResponse, SiteSettingsUpdate } from "@/lib/api";
import { toast } from "sonner";

interface SiteSettingsPanelProps {
  site: Site;
  open: boolean;
}

export function SiteSettingsPanel({ site, open }: SiteSettingsPanelProps) {
  const queryClient = useQueryClient();
  const queryKey = ["sites", site.id, "site-settings"];

  const [pendingChanges, setPendingChanges] = useState<Partial<SiteSettingsUpdate>>({});

  const { data: settings, isLoading, refetch, isFetching } = useQuery({
    queryKey,
    queryFn: async () => {
      const response = await api.getRemoteSiteSettings(site.id);
      return requireSuccess(response, { endpoint: `/sites/${site.id}/site-settings`, method: "GET" }) as SiteSettingsResponse;
    },
    enabled: open,
    retry: false,
    meta: { suppressGlobalError: true },
  });

  const saveMutation = useMutation({
    meta: { suppressGlobalError: true },
    mutationFn: async (patch: Partial<SiteSettingsUpdate>) => {
      const response = await api.updateRemoteSiteSettings(site.id, patch);
      return requireSuccess(response, { endpoint: `/sites/${site.id}/site-settings`, method: "PUT" });
    },
    onSuccess: (data) => {
      toast.success("Site settings updated");
      if (data?.settings) {
        queryClient.setQueryData(queryKey, data.settings);
      }
      if (data?.warnings?.length) {
        data.warnings.forEach((w: string) => toast.warning(w));
      }
      setPendingChanges({});
    },
    onError: (error: Error) => {
      toast.error("Failed to update settings", { description: error.message });
    },
  });

  const handleToggle = (key: keyof SiteSettingsUpdate, value: boolean) => {
    setPendingChanges((prev) => ({ ...prev, [key]: value }));
  };

  const handleSizeChange = (key: keyof SiteSettingsUpdate, value: string) => {
    setPendingChanges((prev) => ({ ...prev, [key]: value }));
  };

  const handleSave = () => {
    if (Object.keys(pendingChanges).length === 0) {
      toast.info("No changes to save");
      return;
    }
    saveMutation.mutate(pendingChanges);
  };

  const hasPendingChanges = Object.keys(pendingChanges).length > 0;

  const getEffectiveValue = <K extends keyof SiteSettingsResponse>(key: K): SiteSettingsResponse[K] => {
    if (key in pendingChanges) {
      return (pendingChanges as any)[key];
    }
    return settings?.[key] as SiteSettingsResponse[K];
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center py-8">
        <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />
        <span className="ml-2 text-sm text-muted-foreground">Loading settings...</span>
      </div>
    );
  }

  if (!settings) {
    return (
      <div className="text-center py-8 text-muted-foreground text-sm">
        <AlertTriangle className="h-5 w-5 mx-auto mb-2" />
        <p>Could not load site settings.</p>
        <p className="text-xs mt-1">The remote plugin may need to be updated to support this feature.</p>
      </div>
    );
  }

  return (
    <ScrollArea className="h-full">
      <div className="space-y-4 p-1">
        {/* Header with save/refresh */}
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-2">
            <Button
              size="sm"
              variant="outline"
              onClick={() => refetch()}
              disabled={isFetching}
            >
              <RefreshCw className={`h-3 w-3 mr-1 ${isFetching ? "animate-spin" : ""}`} />
              Refresh
            </Button>
            {hasPendingChanges && (
              <Badge variant="secondary" className="text-xs">
                {Object.keys(pendingChanges).length} unsaved
              </Badge>
            )}
          </div>
          <Button
            size="sm"
            onClick={handleSave}
            disabled={!hasPendingChanges || saveMutation.isPending}
          >
            {saveMutation.isPending ? (
              <Loader2 className="h-3 w-3 mr-1 animate-spin" />
            ) : (
              <Save className="h-3 w-3 mr-1" />
            )}
            Save Changes
          </Button>
        </div>

        {/* Search Engine Visibility */}
        <Card>
          <CardHeader className="py-3 px-4">
            <CardTitle className="text-sm flex items-center gap-2">
              <Search className="h-4 w-4" />
              Search Engine Visibility
            </CardTitle>
          </CardHeader>
          <CardContent className="px-4 pb-3">
            <div className="flex items-center justify-between">
              <div>
                <Label className="text-sm">Allow search engine indexing</Label>
                <p className="text-xs text-muted-foreground">
                  {getEffectiveValue("searchEngineVisible")
                    ? "Search engines can index this site"
                    : "Discouraged from indexing (not guaranteed)"}
                </p>
              </div>
              <Switch
                checked={getEffectiveValue("searchEngineVisible") as boolean}
                onCheckedChange={(v) => handleToggle("searchEngineVisible", v)}
              />
            </div>
          </CardContent>
        </Card>

        {/* Debug Mode */}
        <Card>
          <CardHeader className="py-3 px-4">
            <CardTitle className="text-sm flex items-center gap-2">
              <Bug className="h-4 w-4" />
              Debug Mode
              {!settings.wpConfigWritable && (
                <Badge variant="outline" className="text-xs text-warning">
                  wp-config.php read-only
                </Badge>
              )}
            </CardTitle>
          </CardHeader>
          <CardContent className="px-4 pb-3 space-y-3">
            <div className="flex items-center justify-between">
              <div>
                <Label className="text-sm">WP_DEBUG</Label>
                <p className="text-xs text-muted-foreground">Enable WordPress debug mode</p>
              </div>
              <Switch
                checked={getEffectiveValue("wpDebug") as boolean}
                onCheckedChange={(v) => handleToggle("wpDebug", v)}
                disabled={!settings.wpConfigWritable}
              />
            </div>
            <Separator />
            <div className="flex items-center justify-between">
              <div>
                <Label className="text-sm">WP_DEBUG_LOG</Label>
                <p className="text-xs text-muted-foreground">Log errors to debug.log</p>
              </div>
              <Switch
                checked={getEffectiveValue("wpDebugLog") as boolean}
                onCheckedChange={(v) => handleToggle("wpDebugLog", v)}
                disabled={!settings.wpConfigWritable}
              />
            </div>
            <Separator />
            <div className="flex items-center justify-between">
              <div>
                <Label className="text-sm">WP_DEBUG_DISPLAY</Label>
                <p className="text-xs text-muted-foreground">Show errors on screen</p>
              </div>
              <Switch
                checked={getEffectiveValue("wpDebugDisplay") as boolean}
                onCheckedChange={(v) => handleToggle("wpDebugDisplay", v)}
                disabled={!settings.wpConfigWritable}
              />
            </div>
          </CardContent>
        </Card>

        {/* PHP Limits */}
        <Card>
          <CardHeader className="py-3 px-4">
            <CardTitle className="text-sm flex items-center gap-2">
              <Upload className="h-4 w-4" />
              PHP Limits
              {!settings.htaccessWritable && (
                <Badge variant="outline" className="text-xs text-warning">
                  .htaccess read-only
                </Badge>
              )}
            </CardTitle>
          </CardHeader>
          <CardContent className="px-4 pb-3 space-y-3">
            <div className="grid grid-cols-2 gap-3">
              <div>
                <Label className="text-xs">Upload Max Filesize</Label>
                <Input
                  className="h-8 text-sm mt-1"
                  defaultValue={settings.uploadMaxFilesize}
                  placeholder="128M"
                  onChange={(e) => handleSizeChange("uploadMaxFilesize", e.target.value)}
                />
              </div>
              <div>
                <Label className="text-xs">Post Max Size</Label>
                <Input
                  className="h-8 text-sm mt-1"
                  defaultValue={settings.postMaxSize}
                  placeholder="128M"
                  onChange={(e) => handleSizeChange("postMaxSize", e.target.value)}
                />
              </div>
              <div>
                <Label className="text-xs">Memory Limit</Label>
                <Input
                  className="h-8 text-sm mt-1"
                  defaultValue={settings.memoryLimit}
                  placeholder="256M"
                  onChange={(e) => handleSizeChange("memoryLimit", e.target.value)}
                />
              </div>
              <div>
                <Label className="text-xs text-muted-foreground">Max Execution Time</Label>
                <Input
                  className="h-8 text-sm mt-1"
                  value={`${settings.maxExecutionTime}s`}
                  disabled
                />
              </div>
            </div>
          </CardContent>
        </Card>

        {/* System Info (read-only) */}
        <Card>
          <CardHeader className="py-3 px-4">
            <CardTitle className="text-sm flex items-center gap-2">
              <Shield className="h-4 w-4" />
              System Info
            </CardTitle>
          </CardHeader>
          <CardContent className="px-4 pb-3">
            <div className="grid grid-cols-2 gap-2 text-xs">
              <div className="text-muted-foreground">PHP Version</div>
              <div className="font-mono">{settings.phpVersion}</div>
              <div className="text-muted-foreground">WordPress</div>
              <div className="font-mono">{settings.wpVersion}</div>
              <div className="text-muted-foreground">Theme</div>
              <div className="font-mono truncate">{settings.activeTheme}</div>
              <div className="text-muted-foreground">Server</div>
              <div className="font-mono truncate">{settings.serverSoftware}</div>
              <div className="text-muted-foreground">Timezone</div>
              <div className="font-mono">{settings.timezone}</div>
              <div className="text-muted-foreground">Multisite</div>
              <div>{settings.isMultisite ? "Yes" : "No"}</div>
            </div>
          </CardContent>
        </Card>
      </div>
    </ScrollArea>
  );
}
