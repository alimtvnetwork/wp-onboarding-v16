// User role badge component.

import { Badge } from "@/components/ui/badge";

const roleConfig: Record<string, { className: string }> = {
  administrator: { className: "bg-destructive/10 text-destructive border-destructive/20" },
  editor: { className: "bg-info/10 text-info border-info/20" },
  author: { className: "bg-success/10 text-success border-success/20" },
  contributor: { className: "bg-warning/10 text-warning border-warning/20" },
  subscriber: { className: "bg-muted text-muted-foreground" },
};

export function UserRoleBadge({ role }: { role: string }) {
  const config = roleConfig[role] ?? { className: "" };

  return (
    <Badge variant="outline" className={`capitalize text-xs ${config.className}`}>
      {role}
    </Badge>
  );
}
