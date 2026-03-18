// License type badge component.

import { Badge } from "@/components/ui/badge";
import type { LicenseType } from "@/types/licensing";

const typeConfig: Record<LicenseType, { label: string; className: string }> = {
  standard: { label: "Standard", className: "bg-muted text-muted-foreground" },
  professional: { label: "Pro", className: "bg-info/10 text-info border-info/20" },
  enterprise: { label: "Enterprise", className: "bg-warning/10 text-warning border-warning/20" },
};

interface Props {
  type: LicenseType;
}

export function LicenseTypeBadge({ type }: Props) {
  const config = typeConfig[type] ?? { label: type, className: "" };

  return (
    <Badge variant="outline" className={`capitalize text-xs ${config.className}`}>
      {config.label}
    </Badge>
  );
}
