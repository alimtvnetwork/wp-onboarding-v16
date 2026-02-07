/**
 * SiteVersionBadge - Displays version comparison for a site in the publish dialog
 * Fetches version info from the preview endpoint and shows upgrade/new badges
 */

import { useState, useEffect } from "react";
import { compareVersions } from "@/lib/versionUtils";
import { Badge } from "@/components/ui/badge";
import { Skeleton } from "@/components/ui/skeleton";
import { ArrowRight, RefreshCw } from "lucide-react";
import { api } from "@/lib/api";

interface SiteVersionBadgeProps {
  pluginId: number;
  siteId: number;
  className?: string;
}

export interface VersionInfo {
  localVersion: string;
  remoteVersion: string;
  isNewInstall: boolean;
  isUpgrade: boolean;
  isDowngrade: boolean;
}

export function SiteVersionBadge({ pluginId, siteId, className = "" }: SiteVersionBadgeProps) {
  const [versionInfo, setVersionInfo] = useState<VersionInfo | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;

    const fetchVersion = async () => {
      setLoading(true);
      setError(null);
      
      try {
        const response = await api.previewPublish(pluginId, siteId);
        if (cancelled) return;
        
        if (response.success && response.data) {
          const { localVersion, remoteVersion } = response.data;
          const isNewInstall = !remoteVersion;
          const cmp = isNewInstall ? 0 : compareVersions(localVersion, remoteVersion);
          const isUpgrade = !isNewInstall && cmp > 0;
          const isDowngrade = !isNewInstall && cmp < 0;
          
          setVersionInfo({
            localVersion: localVersion || "Unknown",
            remoteVersion: remoteVersion || "",
            isNewInstall,
            isUpgrade,
            isDowngrade,
          });
        } else {
          setError("Failed to fetch version");
        }
      } catch (err) {
        if (cancelled) return;
        setError(err instanceof Error ? err.message : "Failed to fetch");
      } finally {
        if (!cancelled) setLoading(false);
      }
    };

    fetchVersion();
    return () => { cancelled = true; };
  }, [pluginId, siteId]);

  if (loading) {
    return (
      <div className={`flex items-center gap-1.5 ${className}`}>
        <Skeleton className="h-5 w-12" />
        <ArrowRight className="h-3 w-3 text-muted-foreground" />
        <Skeleton className="h-5 w-12" />
      </div>
    );
  }

  if (error || !versionInfo) {
    return (
      <div className={`flex items-center gap-1 text-xs text-muted-foreground ${className}`}>
        <RefreshCw className="h-3 w-3" />
        <span>-</span>
      </div>
    );
  }

  return (
    <div className={`flex items-center gap-2 ${className}`}>
      {/* Local version (what we're deploying) */}
      <Badge className="text-[10px] font-mono h-5 px-1.5 bg-primary">
        v{versionInfo.localVersion}
      </Badge>
      
      <ArrowRight className="h-3 w-3 text-primary flex-shrink-0" />
      
      {/* Remote version (what's currently installed) */}
      {versionInfo.remoteVersion ? (
        <Badge variant="outline" className="text-[10px] font-mono h-5 px-1.5">
          v{versionInfo.remoteVersion}
        </Badge>
      ) : (
        <Badge variant="outline" className="text-[10px] italic text-muted-foreground h-5 px-1.5">
          new
        </Badge>
      )}
      
      {/* Status badge */}
      {versionInfo.isNewInstall && (
        <Badge variant="secondary" className="text-[10px] h-5 px-1.5 bg-accent text-accent-foreground">
          Install
        </Badge>
      )}
      {versionInfo.isUpgrade && (
        <Badge variant="secondary" className="text-[10px] h-5 px-1.5 bg-primary/10 text-primary">
          Upgrade
        </Badge>
      )}
      {versionInfo.isDowngrade && (
        <Badge variant="secondary" className="text-[10px] h-5 px-1.5 bg-destructive/10 text-destructive">
          Downgrade
        </Badge>
      )}
      {!versionInfo.isNewInstall && !versionInfo.isUpgrade && !versionInfo.isDowngrade && (
        <Badge variant="secondary" className="text-[10px] h-5 px-1.5 bg-muted text-muted-foreground">
          No changes
        </Badge>
      )}
    </div>
  );
}

export default SiteVersionBadge;
