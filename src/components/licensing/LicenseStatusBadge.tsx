// License status badge component.

import { Badge } from "@/components/ui/badge";
import type { LicenseStatus } from "@/types/licensing";

const statusConfig: Record<LicenseStatus, { label: string; variant: "default" | "destructive" | "secondary" | "outline" }> = {
  active: { label: "Active", variant: "default" },
  expired: { label: "Expired", variant: "destructive" },
  suspended: { label: "Suspended", variant: "secondary" },
  revoked: { label: "Revoked", variant: "destructive" },
};

interface Props {
  status: LicenseStatus;
}

export function LicenseStatusBadge({ status }: Props) {
  const config = statusConfig[status] ?? { label: status, variant: "outline" as const };

  return (
    <Badge variant={config.variant} className="capitalize text-xs">
      {config.label}
    </Badge>
  );
}
