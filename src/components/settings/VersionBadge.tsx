import { Badge } from "@/components/ui/badge";
import { useSettings } from "@/hooks/useSettings";
import { Sparkles } from "lucide-react";

interface VersionBadgeProps {
  className?: string;
  showUpdateIndicator?: boolean;
}

export function VersionBadge({ className, showUpdateIndicator = true }: VersionBadgeProps) {
  const { data: settings, isLoading } = useSettings();

  if (isLoading) {
    return (
      <Badge variant="outline" className={className}>
        <span className="animate-pulse">Loading...</span>
      </Badge>
    );
  }

  const seedVersion = settings?.meta?.seedVersion || "1.0.0";
  const currentVersion = settings?.meta?.currentVersion || seedVersion;
  const isUpdated = seedVersion !== currentVersion;

  return (
    <Badge 
      variant={isUpdated ? "default" : "outline"} 
      className={className}
    >
      {showUpdateIndicator && isUpdated && (
        <Sparkles className="mr-1 h-3 w-3" />
      )}
      v{currentVersion}
      {showUpdateIndicator && isUpdated && (
        <span className="ml-1 text-xs opacity-75">(updated)</span>
      )}
    </Badge>
  );
}
