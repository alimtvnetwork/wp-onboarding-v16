/**
 * SiteVersionBadge - Displays version comparison for a site in the publish dialog
 * Fetches version info from the preview endpoint and shows upgrade/new badges
 * Shows local version immediately once available, remote version loads independently
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
  /** Pass a known local version to display immediately without waiting for the API */
  knownLocalVersion?: string;
  className?: string;
}

export interface VersionInfo {
  localVersion: string;
  remoteVersion: string;
  isNewInstall: boolean;
  isUpgrade: boolean;
  isDowngrade: boolean;
}

export function SiteVersionBadge({ pluginId, siteId, knownLocalVersion, className = "" }: SiteVersionBadgeProps) {
  const [localVersion, setLocalVersion] = useState<string | null>(knownLocalVersion || null);
  const [remoteVersion, setRemoteVersion] = useState<string | null>(null);
  const [remoteLoaded, setRemoteLoaded] = useState(false);
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
          setLocalVersion(response.data.localVersion || null);
          setRemoteVersion(response.data.remoteVersion || null);
          setRemoteLoaded(true);
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

  if (error && !localVersion) {
    return (
      <div className={`flex items-center gap-1 text-xs text-muted-foreground ${className}`}>
        <RefreshCw className="h-3 w-3" />
        <span>-</span>
      </div>
    );
  }

  // Derive comparison info
  const isNewInstall = remoteLoaded && !remoteVersion;
  const cmp = remoteLoaded && remoteVersion && localVersion
    ? compareVersions(localVersion, remoteVersion)
    : 0;
  const isUpgrade = remoteLoaded && !isNewInstall && cmp > 0;
  const isDowngrade = remoteLoaded && !isNewInstall && cmp < 0;
  const noChanges = remoteLoaded && !isNewInstall && !isUpgrade && !isDowngrade;

  return (
    <div className={`flex items-center gap-2 ${className}`}>
      {/* Local version — show immediately or skeleton */}
      {localVersion ? (
        <Badge className="text-[10px] font-mono h-5 px-1.5 bg-primary">
          v{localVersion}
        </Badge>
      ) : loading ? (
        <Skeleton className="h-5 w-14" />
      ) : null}
      
      {/* Arrow — only show when we have local version */}
      {localVersion && (
        <ArrowRight className="h-3 w-3 text-primary flex-shrink-0" />
      )}
      
      {/* Remote version — skeleton while loading, then actual value */}
      {!remoteLoaded ? (
        loading ? <Skeleton className="h-5 w-14" /> : null
      ) : remoteVersion ? (
        <Badge variant="outline" className="text-[10px] font-mono h-5 px-1.5">
          v{remoteVersion}
        </Badge>
      ) : (
        <Badge variant="outline" className="text-[10px] italic text-muted-foreground h-5 px-1.5">
          new
        </Badge>
      )}
      
      {/* Status badge — only after remote is loaded */}
      {remoteLoaded && (
        <>
          {isNewInstall && (
            <Badge variant="secondary" className="text-[10px] h-5 px-1.5 bg-accent text-accent-foreground">
              Install
            </Badge>
          )}
          {isUpgrade && (
            <Badge variant="secondary" className="text-[10px] h-5 px-1.5 bg-primary/10 text-primary">
              Upgrade
            </Badge>
          )}
          {isDowngrade && (
            <Badge variant="secondary" className="text-[10px] h-5 px-1.5 bg-destructive/10 text-destructive">
              Downgrade
            </Badge>
          )}
          {noChanges && (
            <Badge variant="secondary" className="text-[10px] h-5 px-1.5 bg-muted text-muted-foreground">
              No changes
            </Badge>
          )}
        </>
      )}
    </div>
  );
}

export default SiteVersionBadge;
