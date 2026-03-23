import { useState, useMemo } from "react";
import { useCaptureQueryError } from "@/hooks/useCaptureQueryError";
import { useQuery } from "@tanstack/react-query";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Badge } from "@/components/ui/badge";
import { CategoryBadge } from "@/components/shared/CategoryBadge";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
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
  Users,
  FileText,
  Settings,
  HeartPulse,
  MoreHorizontal,
  Cloud,
} from "lucide-react";
import { useNavigate } from "react-router-dom";
import { api, Site, PluginMapping, SnapshotRecord } from "@/lib/api";
import { ConnectionStatus, STALE_TIME_DEFAULT_MS } from "@/lib/constants";
import { toast } from "sonner";
import { useQueryClient } from "@tanstack/react-query";
import { useErrorStore } from "@/stores/errorStore";
import { formatDistanceToNow, parseISO } from "date-fns";
import { RemotePluginsPanel } from "./RemotePluginsPanel";
import { RemoteSnapshotsPanel } from "./RemoteSnapshotsPanel";
import { SiteCredentialsPanel } from "./SiteCredentialsPanel";
import { SiteSettingsPanel } from "./SiteSettingsPanel";
import { SiteHealthSummaryPanel } from "./SiteHealthSummaryPanel";
import { RemoteLogsPanel } from "@/components/plugins/RemoteLogsPanel";
import { CloudStoragePanel } from "./CloudStoragePanel";
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
  const [showSiteSettings, setShowSiteSettings] = useState(false);
  const [showHealthSummary, setShowHealthSummary] = useState(false);
  const [showCloudStorage, setShowCloudStorage] = useState(false);
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
    <Card className="group relative transition-all duration-300 ease-in-out hover:bg-site-card-hover hover:border-transparent hover:text-site-card-hover-foreground shadow-sm hover:shadow-[var(--site-card-hover-shadow)]">
      <CardHeader className="pb-3">
        <div className="flex items-start justify-between">
          <div
            className="flex items-center gap-3 flex-1 min-w-0 cursor-pointer"
            onClick={() => onEdit(site)}
            title="Click to edit"
          >
            <div className="p-2 rounded-lg bg-primary/10 shrink-0 transition-all duration-300 ease-in-out group-hover:bg-site-card-hover-foreground/15">
              <Globe className="h-5 w-5 text-primary transition-colors duration-300 ease-in-out group-hover:text-site-card-hover-foreground" />
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
                className="text-xs text-muted-foreground hover:text-primary flex items-center gap-1 truncate group-hover:text-site-card-hover-foreground/70"
                onClick={(e) => e.stopPropagation()}
              >
                {site.url.replace(/^https?:\/\//, "")}
                <ExternalLink className="h-3 w-3 flex-shrink-0" />
              </a>
            </div>
          </div>
          {/* Header actions: Edit + Overflow */}
          <div className="flex items-center gap-1 shrink-0">
            <Button variant="ghost" size="icon" className="h-7 w-7 group-hover:text-site-card-hover-foreground" onClick={() => onEdit(site)} title="Edit site">
              <Edit className="h-3.5 w-3.5" />
            </Button>
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" className="h-7 w-7 group-hover:text-site-card-hover-foreground">
                  <MoreHorizontal className="h-3.5 w-3.5" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end">
                <DropdownMenuItem onClick={() => navigate(`/api-explorer?siteId=${site.id}`)} disabled={site.connectionStatus !== ConnectionStatus.Connected}>
                  <FlaskConical className="h-4 w-4 mr-2" /> API Explorer
                </DropdownMenuItem>
                <DropdownMenuItem onClick={() => navigate(`/publish-history?siteId=${site.id}&siteName=${encodeURIComponent(site.name)}`)}>
                  <Activity className="h-4 w-4 mr-2" /> Activity
                </DropdownMenuItem>
                <DropdownMenuItem onClick={() => setShowSnapshots(true)} disabled={site.connectionStatus !== ConnectionStatus.Connected}>
                  <Database className="h-4 w-4 mr-2" /> Snapshots
                </DropdownMenuItem>
                <DropdownMenuItem onClick={() => setShowCredentials(true)}>
                  <Users className="h-4 w-4 mr-2" /> Credentials
                </DropdownMenuItem>
                <DropdownMenuItem onClick={() => setShowCloudStorage(true)} disabled={site.connectionStatus !== ConnectionStatus.Connected}>
                  <Cloud className="h-4 w-4 mr-2" /> Cloud Storage
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem className="text-destructive focus:text-destructive" onClick={() => onDelete(site.id)}>
                  <Trash2 className="h-4 w-4 mr-2" /> Delete
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        </div>
      </CardHeader>
      <CardContent className="pt-0 space-y-3">
        {/* Linked Plugins */}
        {mappings && mappings.length > 0 && (
          <div className="flex items-center gap-1.5 flex-wrap">
            {mappings.slice(0, 2).map((mapping: PluginMapping) => (
              <Badge key={mapping.id} variant="outline" className="text-[10px] h-5 px-1.5 flex items-center gap-1 bg-secondary/50 border-border/50 group-hover:bg-site-card-hover-foreground/15 group-hover:text-site-card-hover-foreground group-hover:border-site-card-hover-foreground/30">
                <Package className="h-2.5 w-2.5 shrink-0 text-muted-foreground group-hover:text-site-card-hover-foreground" />
                <span className="truncate max-w-[100px]">{mapping.remoteSlug}</span>
              </Badge>
            ))}
            {mappings.length > 2 && (
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <button className="text-[10px] h-5 px-1.5 rounded-md border border-border/50 bg-secondary/50 text-muted-foreground hover:text-foreground hover:bg-secondary transition-colors cursor-pointer group-hover:bg-site-card-hover-foreground/15 group-hover:text-site-card-hover-foreground group-hover:border-site-card-hover-foreground/30">
                    +{mappings.length - 2} more
                  </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="start">
                  {mappings.slice(2).map((mapping: PluginMapping) => (
                    <DropdownMenuItem key={mapping.id} className="text-xs">
                      <Package className="h-3 w-3 mr-2" />
                      {mapping.remoteSlug}
                    </DropdownMenuItem>
                  ))}
                </DropdownMenuContent>
              </DropdownMenu>
            )}
          </div>
        )}

        {/* Status Badge */}
        <div className="flex items-center justify-between">
          <div
            className={`inline-flex items-center gap-1.5 px-2 py-1 rounded-full text-xs font-medium border ${getStatusBadgeClass(site.connectionStatus)} group-hover:bg-site-card-hover-foreground/15 group-hover:text-site-card-hover-foreground group-hover:border-site-card-hover-foreground/30`}
          >
            <span className="group-hover:text-site-card-hover-foreground">{getStatusIcon(site.connectionStatus)}</span>
            <span>{getStatusText(site.connectionStatus)}</span>
          </div>
          
          {site.connectionStatus === ConnectionStatus.Connected ? (
            <Button variant="ghost" size="sm" className="h-7 text-xs group-hover:text-site-card-hover-foreground" onClick={handleTestConnection} disabled={testingSiteId === site.id}>
              {testingSiteId === site.id ? <Loader2 className="h-3 w-3 animate-spin mr-1" /> : <RefreshCw className="h-3 w-3 mr-1" />}
              Retest
            </Button>
          ) : (
            <Button variant="outline" size="sm" className="h-7 text-xs group-hover:text-site-card-hover-foreground group-hover:border-site-card-hover-foreground/30" onClick={handleTestConnection} disabled={testingSiteId === site.id}>
              {testingSiteId === site.id ? <Loader2 className="h-3 w-3 animate-spin mr-1" /> : <RefreshCw className="h-3 w-3 mr-1" />}
              Test
            </Button>
          )}
        </div>

        {/* Last tested info */}
        {site.lastTestedAt && (
          <p className="text-xs text-muted-foreground group-hover:text-site-card-hover-foreground/70">
            Last tested: {new Date(site.lastTestedAt).toLocaleDateString()}
          </p>
        )}

        {/* Running backup & last backup indicators */}
        {site.connectionStatus === ConnectionStatus.Connected && (runningBackup || lastBackup) && (
          <div className="flex flex-wrap items-center gap-2 text-xs">
            {runningBackup && (
              <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-warning/15 text-warning border border-warning/20 font-medium group-hover:bg-site-card-hover-foreground/15 group-hover:text-site-card-hover-foreground group-hover:border-site-card-hover-foreground/30">
                <Loader2 className="h-3 w-3 animate-spin" />
                <span>Backup Running</span>
              </span>
            )}
            {lastBackup && (
              <span className="inline-flex items-center gap-1 text-muted-foreground group-hover:text-site-card-hover-foreground/70">
                <Clock className="h-3 w-3" />
                <span>Last backup {formatDistanceToNow(parseISO(lastBackup.createdAt), { addSuffix: true })}</span>
              </span>
            )}
          </div>
        )}

        {/* Bottom bar: 3 visible + overflow menu */}
        <div className="flex items-center gap-0.5 pt-2 border-t transition-colors duration-300 ease-in-out group-hover:border-site-card-hover-foreground/20">
          <Button
            variant="ghost"
            size="sm"
            className="flex-1 h-8 text-xs gap-1.5 px-2 min-w-0 group-hover:text-site-card-hover-foreground"
            onClick={() => setShowRemotePlugins(true)}
            disabled={site.connectionStatus !== ConnectionStatus.Connected}
            title="Plugins"
          >
            <Eye className="h-3.5 w-3.5 shrink-0" />
            <span className="truncate">Plugins</span>
          </Button>
          <Button
            variant="ghost"
            size="sm"
            className="flex-1 h-8 text-xs gap-1.5 px-2 min-w-0 group-hover:text-site-card-hover-foreground"
            onClick={() => setShowHealthSummary(true)}
            disabled={site.connectionStatus !== ConnectionStatus.Connected}
            title="Health"
          >
            <HeartPulse className="h-3.5 w-3.5 shrink-0" />
            <span className="truncate">Health</span>
          </Button>
          <Button
            variant="ghost"
            size="sm"
            className="flex-1 h-8 text-xs gap-1.5 px-2 min-w-0 group-hover:text-site-card-hover-foreground"
            onClick={() => setShowLogs(true)}
            disabled={site.connectionStatus !== ConnectionStatus.Connected}
            title="Logs"
          >
            <FileText className="h-3.5 w-3.5 shrink-0" />
            <span className="truncate">Logs</span>
          </Button>
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="ghost" size="sm" className="h-8 w-8 px-0 shrink-0 group-hover:text-site-card-hover-foreground" title="More actions">
                <MoreHorizontal className="h-3.5 w-3.5" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuItem
                onClick={() => setShowSiteSettings(true)}
                disabled={site.connectionStatus !== ConnectionStatus.Connected}
              >
                <Settings className="h-4 w-4 mr-2" /> Settings
              </DropdownMenuItem>
              <DropdownMenuItem
                onClick={handleDeployUploader}
                disabled={deployingUploader || site.connectionStatus !== ConnectionStatus.Connected}
              >
                {deployingUploader ? <Loader2 className="h-4 w-4 mr-2 animate-spin" /> : <Upload className="h-4 w-4 mr-2" />}
                Deploy
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
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
          <RemoteLogsPanel siteId={site.id} siteName={site.name} autoOpen />
        </DialogContent>
      </Dialog>
      <Dialog open={showSiteSettings} onOpenChange={setShowSiteSettings}>
        <DialogContent className="max-w-lg max-h-[80vh]">
          <DialogHeader>
            <DialogTitle>Site Settings — {site.name}</DialogTitle>
          </DialogHeader>
          <SiteSettingsPanel site={site} open={showSiteSettings} />
        </DialogContent>
      </Dialog>
      <Dialog open={showHealthSummary} onOpenChange={setShowHealthSummary}>
        <DialogContent className="max-w-lg max-h-[80vh]">
          <DialogHeader>
            <DialogTitle>Health Summary — {site.name}</DialogTitle>
          </DialogHeader>
          <SiteHealthSummaryPanel site={site} open={showHealthSummary} />
        </DialogContent>
      </Dialog>
      <CloudStoragePanel
        site={site}
        open={showCloudStorage}
        onOpenChange={setShowCloudStorage}
      />
    </Card>
  );
}
