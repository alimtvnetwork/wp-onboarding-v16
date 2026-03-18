// Server health indicator for the licensing dashboard header.

import { Badge } from "@/components/ui/badge";
import { useLicensingHealth } from "@/hooks/useLicensing";
import { Circle } from "lucide-react";

export function LicensingHealthBadge() {
  const { data, isError, isLoading } = useLicensingHealth();

  if (isLoading) {
    return (
      <Badge variant="outline" className="text-xs gap-1.5">
        <Circle className="h-2 w-2 fill-muted-foreground text-muted-foreground animate-pulse" />
        Checking…
      </Badge>
    );
  }

  if (isError || !data) {
    return (
      <Badge variant="outline" className="text-xs gap-1.5 border-destructive/30">
        <Circle className="h-2 w-2 fill-destructive text-destructive" />
        Offline
      </Badge>
    );
  }

  return (
    <Badge variant="outline" className="text-xs gap-1.5 border-success/30">
      <Circle className="h-2 w-2 fill-success text-success" />
      Online
    </Badge>
  );
}
