import { useState, useMemo } from "react";
import { useCaptureQueryError } from "@/hooks/useCaptureQueryError";
import { useQuery } from "@tanstack/react-query";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Badge } from "@/components/ui/badge";
import { CategoryBadge } from "@/components/shared/CategoryBadge";
import {
  Globe,
  Loader2,
  RefreshCw,
  Edit,
  Trash2,
  CheckCircle,
  XCircle,
  HelpCircle,
  ExternalLink,
  Package,
  Upload,
  Eye,
  FlaskConical,
  Database,
  Activity,
  Clock,
  Calendar,
  Users,
  FileText,
} from "lucide-react";
import { useNavigate } from "react-router-dom";
import { api, Site, PluginMapping, SnapshotRecord, SnapshotCronJob } from "@/lib/api";
import { ConnectionStatus, CronJobStatus, STALE_TIME_DEFAULT_MS } from "@/lib/constants";
import { toast } from "sonner";
import { useQueryClient } from "@tanstack/react-query";
import { useErrorStore } from "@/stores/errorStore";
import { formatDistanceToNow, parseISO } from "date-fns";
import { RemotePluginsPanel } from "./RemotePluginsPanel";
import { RemoteSnapshotsPanel } from "./RemoteSnapshotsPanel";
import { SiteCredentialsPanel } from "./SiteCredentialsPanel";
import { RemoteLogsPanel } from "@/components/plugins/RemoteLogsPanel";
import { useSettings } from "@/hooks/useSettings";

interface SiteCardProps {
  site: Site;
  onEdit: (site: Site) => void;
  onDelete: (id: number) => void;
}

export function SiteCard({ site, onEdit, onDelete }: SiteCardProps) {
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const { captureError, captureException, openErrorModal } = useErrorStore();
  const [testingSiteId, setTestingSiteId] = useState<number | null>(null);
  const [deployingUploader, setDeployingUploader] = useState(false);
  const [showRemotePlugins, setShowRemotePlugins] = useState(false);
  const [showSnapshots, setShowSnapshots] = useState(false);
  const [showCredentials, setShowCredentials] = useState(false);
  const [showLogs, setShowLogs] = useState(false);
  const { data: settings } = useSettings();
  const uploaderPath = settings?.publish?.uploaderHelperPath || undefined;

  // Fetch linked plugins for this site
  const { data: mappings } = useQuery({
    queryKey: ["sites", site.id, "mappings"],
    queryFn: async () => {
      const response = await api.getSiteMappings(site.id);
      if (response.success) return response.data || [];
      return [];
    },
  });

  // Fetch latest snapshot for "last backup" badge
  const { data: snapshots, isError: snapshotsError, error: snapshotsQueryError } = useQuery({
    queryKey: ["sites", site.id, "snapshots", "latest"],
    queryFn: async () => {
      const res = await api.getRemoteSnapshots(site.id);
      if (res.success) return res.data || [];
      return [];
    },
    enabled: site.connectionStatus === ConnectionStatus.Connected,
    staleTime: STALE_TIME_DEFAULT_MS,
    retry: false,
    meta: { suppressGlobalError: true },
  });

  useCaptureQueryError(snapshotsError, snapshotsQueryError, {
    source: "SiteCard.fetchSnapshots",
    endpoint: `/sites/${site.id}/snapshots`,
    triggerComponent: "SiteCard",
  });

  // Fetch cron jobs for "next backup" badge
  const { data: cronJobs, isError: cronError, error: cronQueryError } = useQuery({
    queryKey: ["sites", site.id, "snapshots", "cron"],
    queryFn: async () => {
      const res = await api.getSnapshotCronJobs(site.id);
      if (res.success) return res.data || [];
      return [];
    },
    enabled: site.connectionStatus === ConnectionStatus.Connected,
    staleTime: STALE_TIME_DEFAULT_MS,
    retry: false,
    meta: { suppressGlobalError: true },
  });

  useCaptureQueryError(cronError, cronQueryError, {
    source: "SiteCard.fetchCronJobs",
    endpoint: `/sites/${site.id}/snapshots/cron`,
    triggerComponent: "SiteCard",
  });

  // Derive running backup
  const runningBackup = useMemo(() => {
    if (!snapshots?.length) return null;
    return (snapshots as SnapshotRecord[]).find(
      (s) => s.status === "in_progress" || s.status === "running" || s.status === "pending"
    ) || null;
  }, [snapshots]);

  // Derive last completed backup
  const lastBackup = useMemo(() => {
    if (!snapshots?.length) return null;
    const completed = (snapshots as SnapshotRecord[])
      .filter((s) => s.status === "complete" || s.status === "completed")
      .sort((a, b) => new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime());

    return completed[0] || null;
  }, [snapshots]);

  // Derive next scheduled run
  const nextScheduledRun = useMemo(() => {
    if (!cronJobs?.length) return null;
    const active = (cronJobs as SnapshotCronJob[])
      .filter((j) => j.status === CronJobStatus.Active && j.nextRunAt)
      .sort((a, b) => new Date(a.nextRunAt!).getTime() - new Date(b.nextRunAt!).getTime());

    return active[0] || null;
  }, [cronJobs]);

  const handleTestConnection = async () => {
    setTestingSiteId(site.id);
    try {
      const response = await api.testConnection(site.id);
      if (response.success && response.data?.isSuccess) {
        toast.success(`Connection successful! WP ${response.data.wpVersion}`);
        queryClient.invalidateQueries({ queryKey: ["sites"] });
      } else if (response.error) {
        const captured = captureError(response.error, { endpoint: `/sites/${site.id}/test`, method: "POST" });
        toast.error(response.error.message, {
          description: "Click for details",
          action: { label: "View Details", onClick: () => openErrorModal(captured) },
          duration: 10000,
        });
        queryClient.invalidateQueries({ queryKey: ["sites"] });
      } else {
        toast.error(response.data?.message || "Connection failed");
        queryClient.invalidateQueries({ queryKey: ["sites"] });
      }
    } catch (error: unknown) {
      const captured = captureException(error, { endpoint: `/sites/${site.id}/test`, method: "POST" });
      toast.error("Connection test failed", {
        description: "Click for details",
        action: { label: "View Details", onClick: () => openErrorModal(captured) },
        duration: 10000,
      });
    } finally {
      setTestingSiteId(null);
    }
  };

  const handleDeployUploader = async () => {
    setDeployingUploader(true);
    try {
      const response = await api.bootstrapUploader(site.id, uploaderPath);
      if (response.success && response.data?.isSuccess) {
        toast.success("Riseup Asia Uploader deployed!", {
          description: response.data.isActivated ? "Plugin is active" : "Plugin uploaded but not activated",
        });
        queryClient.invalidateQueries({ queryKey: ["sites"] });
      } else if (response.error) {
        const captured = captureError(response.error, { endpoint: `/sites/${site.id}/bootstrap-uploader`, method: "POST" });
        toast.error(response.error.message, {
          description: "Click for details",
          action: { label: "View Details", onClick: () => openErrorModal(captured) },
          duration: 10000,
        });
      } else {
        toast.error("Deploy failed");
      }
    } catch (error: unknown) {
      const captured = captureException(error, { endpoint: `/sites/${site.id}/bootstrap-uploader`, method: "POST" });
      toast.error("Deploy failed", {
        description: "Click for details",
        action: { label: "View Details", onClick: () => openErrorModal(captured) },
        duration: 10000,
      });
    } finally {
      setDeployingUploader(false);
    }
  };

  const getStatusIcon = (status: string) => {
    switch (status) {
      case "connected":
        return <CheckCircle className="h-4 w-4 text-primary" />;
      case "disconnected":
        return <XCircle className="h-4 w-4 text-destructive" />;
      default:
        return <HelpCircle className="h-4 w-4 text-muted-foreground" />;
    }
  };

  const getStatusText = (status: string) => {
    switch (status) {
      case "connected":
        return "Connected";
      case "disconnected":
        return "Disconnected";
      default:
        return "Not tested";
    }
  };

  const getStatusBadgeClass = (status: string) => {
    switch (status) {
      case "connected":
        return "bg-primary/10 text-primary border-primary/20";
      case "disconnected":
        return "bg-destructive/10 text-destructive border-destructive/20";
      default:
        return "bg-muted text-muted-foreground border-border";
    }
  };

  return (
    <Card className="group transition-colors hover:bg-secondary/30">
      <CardHeader className="pb-3">
        <div className="flex items-start justify-between">
          <div className="flex items-center gap-3 flex-1 min-w-0">
            <div className="p-2 rounded-lg bg-primary/10 shrink-0">
              <Globe className="h-5 w-5 text-primary" />
            </div>
            <div className="flex-1 min-w-0">
              <CardTitle className="text-base truncate flex items-center gap-2">
                {site.name}
                <CategoryBadge category={site.category} size="sm" />
              </CardTitle>
              <a
                href={site.url}
                target="_blank"
                rel="noopener noreferrer"
                className="text-xs text-muted-foreground hover:text-primary flex items-center gap-1 truncate"
              >
                {site.url.replace(/^https?:\/\//, "")}
                <ExternalLink className="h-3 w-3 flex-shrink-0" />
              </a>
            </div>
          </div>
        </div>
      </CardHeader>
      <CardContent className="pt-0 space-y-3">
        {/* Linked Plugins */}
        {mappings && mappings.length > 0 && (
          <div className="flex flex-wrap gap-1.5">
            {mappings.map((mapping: PluginMapping) => (
              <Badge key={mapping.id} variant="secondary" className="text-xs flex items-center gap-1">
                <Package className="h-3 w-3" />
                {mapping.remoteSlug}
              </Badge>
            ))}
          </div>
        )}

        {/* Status Badge */}
        <div className="flex items-center justify-between">
          <div
            className={`inline-flex items-center gap-1.5 px-2 py-1 rounded-full text-xs font-medium border ${getStatusBadgeClass(site.connectionStatus)}`}
          >
            {getStatusIcon(site.connectionStatus)}
            <span>{getStatusText(site.connectionStatus)}</span>
          </div>
          
          {/* Retest Button - Always visible for connected sites */}
          {site.connectionStatus === ConnectionStatus.Connected && (
            <Button
              variant="ghost"
              size="sm"
              className="h-7 text-xs"
              onClick={handleTestConnection}
              disabled={testingSiteId === site.id}
            >
              {testingSiteId === site.id ? (
                <Loader2 className="h-3 w-3 animate-spin mr-1" />
              ) : (
                <RefreshCw className="h-3 w-3 mr-1" />
              )}
              Retest
            </Button>
          )}
          
          {/* Test Button - For disconnected or unknown */}
          {site.connectionStatus !== ConnectionStatus.Connected && (
            <Button
              variant="outline"
              size="sm"
              className="h-7 text-xs"
              onClick={handleTestConnection}
              disabled={testingSiteId === site.id}
            >
              {testingSiteId === site.id ? (
                <Loader2 className="h-3 w-3 animate-spin mr-1" />
              ) : (
                <RefreshCw className="h-3 w-3 mr-1" />
              )}
              Test
            </Button>
          )}
        </div>

        {/* Last tested info */}
        {site.lastTestedAt && (
          <p className="text-xs text-muted-foreground">
            Last tested: {new Date(site.lastTestedAt).toLocaleDateString()}
          </p>
        )}

        {/* Running backup, last backup & next schedule indicators */}
        {site.connectionStatus === ConnectionStatus.Connected && (runningBackup || lastBackup || nextScheduledRun) && (
          <div className="flex flex-wrap items-center gap-2 text-xs">
            {runningBackup && (
              <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/20 font-medium">
                <Loader2 className="h-3 w-3 animate-spin" />
                <span>Backup Running</span>
              </span>
            )}
            {lastBackup && (
              <span className="inline-flex items-center gap-1 text-muted-foreground">
                <Clock className="h-3 w-3" />
                <span>Last backup {formatDistanceToNow(parseISO(lastBackup.createdAt), { addSuffix: true })}</span>
              </span>
            )}
            {nextScheduledRun?.nextRunAt && (
              <span className="inline-flex items-center gap-1 text-muted-foreground">
                <Calendar className="h-3 w-3" />
                <span>Next: {formatDistanceToNow(parseISO(nextScheduledRun.nextRunAt), { addSuffix: true })}</span>
              </span>
            )}
          </div>
        )}

        {/* Action buttons - responsive flex wrap layout */}
        <div className="flex flex-wrap gap-1 pt-2 border-t">
          <Button
            variant="ghost"
            size="sm"
            className="flex flex-col items-center justify-center h-auto py-2 px-2 gap-0.5 min-w-[3.5rem] flex-1"
            onClick={() => navigate(`/api-explorer?siteId=${site.id}`)}
            disabled={site.connectionStatus !== ConnectionStatus.Connected}
            title={site.connectionStatus !== ConnectionStatus.Connected ? "Connect site first" : "Test API endpoints"}
          >
            <FlaskConical className="h-4 w-4 shrink-0" />
            <span className="text-[10px] leading-tight truncate">API</span>
          </Button>
          <Button
            variant="ghost"
            size="sm"
            className="flex flex-col items-center justify-center h-auto py-2 px-2 gap-0.5 min-w-[3.5rem] flex-1"
            onClick={() => setShowRemotePlugins(true)}
            disabled={site.connectionStatus !== ConnectionStatus.Connected}
            title={site.connectionStatus !== ConnectionStatus.Connected ? "Connect site first" : "View plugins on this site"}
          >
            <Eye className="h-4 w-4 shrink-0" />
            <span className="text-[10px] leading-tight truncate">Plugins</span>
          </Button>
          <Button
            variant="ghost"
            size="sm"
            className="flex flex-col items-center justify-center h-auto py-2 px-2 gap-0.5 min-w-[3.5rem] flex-1"
            onClick={() => setShowSnapshots(true)}
            disabled={site.connectionStatus !== ConnectionStatus.Connected}
            title={site.connectionStatus !== ConnectionStatus.Connected ? "Connect site first" : "Manage database snapshots"}
          >
            <Database className="h-4 w-4 shrink-0" />
            <span className="text-[10px] leading-tight truncate">Snapshots</span>
          </Button>
          <Button
            variant="ghost"
            size="sm"
            className="flex flex-col items-center justify-center h-auto py-2 px-2 gap-0.5 min-w-[3.5rem] flex-1"
            onClick={() => navigate(`/publish-history?siteId=${site.id}&siteName=${encodeURIComponent(site.name)}`)}
            title="View activity logs for this site"
          >
            <Activity className="h-4 w-4 shrink-0" />
            <span className="text-[10px] leading-tight truncate">Activity</span>
          </Button>
          <Button
            variant="ghost"
            size="sm"
            className="flex flex-col items-center justify-center h-auto py-2 px-2 gap-0.5 min-w-[3.5rem] flex-1"
            onClick={() => setShowLogs(true)}
            disabled={site.connectionStatus !== ConnectionStatus.Connected}
            title={site.connectionStatus !== ConnectionStatus.Connected ? "Connect site first" : "View remote logs"}
          >
            <FileText className="h-4 w-4 shrink-0" />
            <span className="text-[10px] leading-tight truncate">Logs</span>
          </Button>
          <Button
            variant="ghost"
            size="sm"
            className="flex flex-col items-center justify-center h-auto py-2 px-2 gap-0.5 min-w-[3.5rem] flex-1"
            onClick={handleDeployUploader}
            disabled={deployingUploader || site.connectionStatus !== ConnectionStatus.Connected}
            title={site.connectionStatus !== ConnectionStatus.Connected ? "Connect site first" : "Deploy Riseup Asia Uploader to this site"}
          >
            {deployingUploader ? (
              <Loader2 className="h-4 w-4 animate-spin shrink-0" />
            ) : (
              <Upload className="h-4 w-4 shrink-0" />
            )}
            <span className="text-[10px] leading-tight truncate">Deploy</span>
          </Button>
          <Button
            variant="ghost"
            size="sm"
            className="flex flex-col items-center justify-center h-auto py-2 px-2 gap-0.5 min-w-[3.5rem] flex-1"
            onClick={() => setShowCredentials(true)}
            title="Manage credentials for this site"
          >
            <Users className="h-4 w-4 shrink-0" />
            <span className="text-[10px] leading-tight truncate">Users</span>
          </Button>
          <Button
            variant="ghost"
            size="sm"
            className="flex flex-col items-center justify-center h-auto py-2 px-2 gap-0.5 min-w-[3.5rem] flex-1"
            onClick={() => onEdit(site)}
          >
            <Edit className="h-4 w-4 shrink-0" />
            <span className="text-[10px] leading-tight truncate">Edit</span>
          </Button>
          <Button
            variant="ghost"
            size="sm"
            className="flex flex-col items-center justify-center h-auto py-2 px-2 gap-0.5 min-w-[3.5rem] flex-1 text-destructive hover:text-destructive hover:bg-destructive/10"
            onClick={() => onDelete(site.id)}
          >
            <Trash2 className="h-4 w-4 shrink-0" />
            <span className="text-[10px] leading-tight truncate">Delete</span>
          </Button>
        </div>
      </CardContent>

      <RemotePluginsPanel
        site={site}
        open={showRemotePlugins}
        onOpenChange={setShowRemotePlugins}
      />
      <RemoteSnapshotsPanel
        site={site}
        open={showSnapshots}
        onOpenChange={setShowSnapshots}
      />
      <SiteCredentialsPanel
        site={site}
        open={showCredentials}
        onOpenChange={setShowCredentials}
      />
      <Dialog open={showLogs} onOpenChange={setShowLogs}>
        <DialogContent className="max-w-2xl max-h-[80vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>Remote Logs — {site.name}</DialogTitle>
          </DialogHeader>
          <RemoteLogsPanel siteId={site.id} siteName={site.name} />
        </DialogContent>
      </Dialog>
    </Card>
  );
}
