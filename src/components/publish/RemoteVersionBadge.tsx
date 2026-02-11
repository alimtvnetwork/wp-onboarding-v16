/**
 * RemoteVersionBadge - Fetches and displays the remote (site) version independently.
 * Shows a skeleton while loading, then the remote version + comparison status badge.
 */

import { useState, useEffect } from "react";
import { compareVersions } from "@/lib/versionUtils";
import { Badge } from "@/components/ui/badge";
import { Skeleton } from "@/components/ui/skeleton";
import { api } from "@/lib/api";

interface RemoteVersionBadgeProps {
  pluginId: number;
  siteId: number;
  localVersion?: string;
  className?: string;
}

export function RemoteVersionBadge({ pluginId, siteId, localVersion, className = "" }: RemoteVersionBadgeProps) {
  const [remoteVersion, setRemoteVersion] = useState<string | null>(null);
  const [loaded, setLoaded] = useState(false);
  const [error, setError] = useState(false);

  useEffect(() => {
    let cancelled = false;

    const fetch = async () => {
      try {
        const response = await api.previewPublish(pluginId, siteId);
        if (cancelled) return;
        if (response.success && response.data) {
          setRemoteVersion(response.data.remoteVersion || null);
        } else {
          setError(true);
        }
      } catch {
        if (!cancelled) setError(true);
      } finally {
        if (!cancelled) setLoaded(true);
      }
    };

    fetch();
    return () => { cancelled = true; };
  }, [pluginId, siteId]);

  if (error) {
    return (
      <Badge variant="outline" className={`text-[10px] font-mono h-5 px-1.5 text-muted-foreground ${className}`}>
        ?
      </Badge>
    );
  }

  if (!loaded) {
    return <Skeleton className={`h-5 w-14 ${className}`} />;
  }

  const isNewInstall = !remoteVersion;
  const cmp = remoteVersion && localVersion ? compareVersions(localVersion, remoteVersion) : 0;
  const isUpgrade = !isNewInstall && cmp > 0;
  const isDowngrade = !isNewInstall && cmp < 0;

  return (
    <div className={`flex items-center gap-1.5 ${className}`}>
      {remoteVersion ? (
        <Badge variant="outline" className="text-[10px] font-mono h-5 px-1.5">
          v{remoteVersion}
        </Badge>
      ) : (
        <Badge variant="outline" className="text-[10px] italic text-muted-foreground h-5 px-1.5">
          new
        </Badge>
      )}
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
    </div>
  );
}
