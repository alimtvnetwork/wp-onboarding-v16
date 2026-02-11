/**
 * SiteVersionBadge - Composes local + remote version badges.
 * Local version renders instantly from props (no API wait).
 * Remote version loads independently via RemoteVersionBadge.
 */

import { Badge } from "@/components/ui/badge";
import { ArrowRight } from "lucide-react";
import { RemoteVersionBadge } from "./RemoteVersionBadge";

interface SiteVersionBadgeProps {
  pluginId: number;
  siteId: number;
  /** Local version — rendered instantly, no API call needed */
  localVersion?: string;
  className?: string;
}

export interface VersionInfo {
  localVersion: string;
  remoteVersion: string;
  isNewInstall: boolean;
  isUpgrade: boolean;
  isDowngrade: boolean;
}

export function SiteVersionBadge({ pluginId, siteId, localVersion, className = "" }: SiteVersionBadgeProps) {
  return (
    <div className={`flex items-center gap-2 ${className}`}>
      {/* Local version — always instant */}
      {localVersion ? (
        <Badge className="text-[10px] font-mono h-5 px-1.5 bg-primary">
          v{localVersion}
        </Badge>
      ) : (
        <Badge variant="outline" className="text-[10px] font-mono h-5 px-1.5 text-muted-foreground italic">
          unknown
        </Badge>
      )}

      <ArrowRight className="h-3 w-3 text-primary flex-shrink-0" />

      {/* Remote version — fetched independently */}
      <RemoteVersionBadge
        pluginId={pluginId}
        siteId={siteId}
        localVersion={localVersion}
      />
    </div>
  );
}

export default SiteVersionBadge;
