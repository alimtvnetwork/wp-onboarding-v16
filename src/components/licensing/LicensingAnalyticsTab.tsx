// Licensing Analytics Tab — charts, distribution, and expiration alerts.

import { useMemo } from "react";
import { format, differenceInDays, parseISO } from "date-fns";
import {
  PieChart,
  Pie,
  Cell,
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  AreaChart,
  Area,
  ResponsiveContainer,
  Legend,
} from "recharts";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { AlertTriangle, Clock, ShieldAlert } from "lucide-react";
import type { License } from "@/types/licensing";

interface Props {
  licenses: License[];
  onExtendLicense?: (id: number) => void;
  onRevokeLicense?: (id: number) => void;
}

const STATUS_COLORS: Record<string, string> = {
  active: "hsl(var(--success, 142 71% 45%))",
  expired: "hsl(var(--muted-foreground))",
  revoked: "hsl(var(--destructive))",
  suspended: "hsl(var(--warning, 38 92% 50%))",
};

const TYPE_COLORS = [
  "hsl(var(--primary))",
  "hsl(var(--accent-foreground))",
  "hsl(var(--muted-foreground))",
];

const PRODUCT_COLORS = [
  "hsl(var(--primary))",
  "hsl(210 80% 55%)",
  "hsl(280 60% 55%)",
  "hsl(30 90% 55%)",
];

export function LicensingAnalyticsTab({ licenses, onExtendLicense, onRevokeLicense }: Props) {
  const { byProduct, byType, byStatus, timeline, expiringAlerts } = useMemo(() => {
    // By Product
    const productMap = new Map<string, number>();
    licenses.forEach((l) => {
      productMap.set(l.product, (productMap.get(l.product) ?? 0) + 1);
    });
    const byProduct = Array.from(productMap, ([name, value]) => ({ name, value }));

    // By Type
    const typeMap = new Map<string, number>();
    licenses.forEach((l) => {
      typeMap.set(l.type, (typeMap.get(l.type) ?? 0) + 1);
    });
    const byType = Array.from(typeMap, ([name, value]) => ({ name, value }));

    // By Status
    const statusMap = new Map<string, number>();
    licenses.forEach((l) => {
      statusMap.set(l.status, (statusMap.get(l.status) ?? 0) + 1);
    });
    const byStatus = Array.from(statusMap, ([name, value]) => ({ name, value }));

    // Creation Timeline (last 90 days)
    const now = new Date();
    const ninetyDaysAgo = new Date(now.getTime() - 90 * 24 * 60 * 60 * 1000);
    const dayMap = new Map<string, number>();
    licenses.forEach((l) => {
      const d = parseISO(l.created_at);
      if (d >= ninetyDaysAgo) {
        const key = format(d, "MMM d");
        dayMap.set(key, (dayMap.get(key) ?? 0) + 1);
      }
    });
    const timeline = Array.from(dayMap, ([date, count]) => ({ date, count }));

    // Expiration Alerts
    const expiringAlerts: Array<License & { daysLeft: number; urgency: "red" | "amber" | "yellow" }> = [];
    licenses.forEach((l) => {
      if (l.status !== "active" || !l.expires_at) return;
      const daysLeft = differenceInDays(parseISO(l.expires_at), now);
      if (daysLeft <= 30 && daysLeft >= 0) {
        const urgency = daysLeft <= 7 ? "red" : daysLeft <= 14 ? "amber" : "yellow";
        expiringAlerts.push({ ...l, daysLeft, urgency });
      }
    });
    expiringAlerts.sort((a, b) => a.daysLeft - b.daysLeft);

    return { byProduct, byType, byStatus, timeline, expiringAlerts };
  }, [licenses]);

  return (
    <div className="space-y-6">
      {/* Expiration Alerts */}
      {expiringAlerts.length > 0 && (
        <Card className="border-warning/50">
          <CardHeader className="pb-3">
            <CardTitle className="flex items-center gap-2 text-base">
              <AlertTriangle className="h-4 w-4 text-warning" />
              Expiring Soon ({expiringAlerts.length})
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-2">
            {expiringAlerts.map((alert) => (
              <div
                key={alert.id}
                className="flex items-center justify-between rounded-md border border-border p-3"
              >
                <div className="flex items-center gap-3">
                  <UrgencyIcon urgency={alert.urgency} />
                  <div>
                    <p className="text-sm font-medium">{alert.email}</p>
                    <p className="text-xs text-muted-foreground">
                      <code className="font-mono">{alert.key.slice(0, 8)}…</code> · Expires in{" "}
                      {alert.daysLeft} day{alert.daysLeft !== 1 ? "s" : ""}
                    </p>
                  </div>
                </div>
                <div className="flex items-center gap-1.5">
                  {onExtendLicense && (
                    <Button variant="outline" size="sm" onClick={() => onExtendLicense(alert.id)}>
                      Extend
                    </Button>
                  )}
                  {onRevokeLicense && (
                    <Button variant="ghost" size="sm" className="text-destructive" onClick={() => onRevokeLicense(alert.id)}>
                      Revoke
                    </Button>
                  )}
                </div>
              </div>
            ))}
          </CardContent>
        </Card>
      )}

      {/* Charts Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        {/* By Product */}
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium">By Product</CardTitle>
          </CardHeader>
          <CardContent>
            {byProduct.length === 0 ? (
              <EmptyChart />
            ) : (
              <ResponsiveContainer width="100%" height={200}>
                <PieChart>
                  <Pie data={byProduct} dataKey="value" nameKey="name" cx="50%" cy="50%" outerRadius={70} label>
                    {byProduct.map((_, i) => (
                      <Cell key={i} fill={PRODUCT_COLORS[i % PRODUCT_COLORS.length]} />
                    ))}
                  </Pie>
                  <Tooltip />
                  <Legend />
                </PieChart>
              </ResponsiveContainer>
            )}
          </CardContent>
        </Card>

        {/* By Type */}
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium">By Type</CardTitle>
          </CardHeader>
          <CardContent>
            {byType.length === 0 ? (
              <EmptyChart />
            ) : (
              <ResponsiveContainer width="100%" height={200}>
                <PieChart>
                  <Pie data={byType} dataKey="value" nameKey="name" cx="50%" cy="50%" outerRadius={70} label>
                    {byType.map((_, i) => (
                      <Cell key={i} fill={TYPE_COLORS[i % TYPE_COLORS.length]} />
                    ))}
                  </Pie>
                  <Tooltip />
                  <Legend />
                </PieChart>
              </ResponsiveContainer>
            )}
          </CardContent>
        </Card>

        {/* By Status */}
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium">By Status</CardTitle>
          </CardHeader>
          <CardContent>
            {byStatus.length === 0 ? (
              <EmptyChart />
            ) : (
              <ResponsiveContainer width="100%" height={200}>
                <BarChart data={byStatus}>
                  <CartesianGrid strokeDasharray="3 3" className="stroke-border" />
                  <XAxis dataKey="name" className="text-xs" />
                  <YAxis allowDecimals={false} className="text-xs" />
                  <Tooltip />
                  <Bar dataKey="value" radius={[4, 4, 0, 0]}>
                    {byStatus.map((entry) => (
                      <Cell key={entry.name} fill={STATUS_COLORS[entry.name] ?? "hsl(var(--primary))"} />
                    ))}
                  </Bar>
                </BarChart>
              </ResponsiveContainer>
            )}
          </CardContent>
        </Card>

        {/* Creation Timeline */}
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium">Created (Last 90 Days)</CardTitle>
          </CardHeader>
          <CardContent>
            {timeline.length === 0 ? (
              <EmptyChart />
            ) : (
              <ResponsiveContainer width="100%" height={200}>
                <AreaChart data={timeline}>
                  <CartesianGrid strokeDasharray="3 3" className="stroke-border" />
                  <XAxis dataKey="date" className="text-xs" />
                  <YAxis allowDecimals={false} className="text-xs" />
                  <Tooltip />
                  <Area
                    type="monotone"
                    dataKey="count"
                    stroke="hsl(var(--primary))"
                    fill="hsl(var(--primary) / 0.2)"
                  />
                </AreaChart>
              </ResponsiveContainer>
            )}
          </CardContent>
        </Card>
      </div>
    </div>
  );
}

function UrgencyIcon({ urgency }: { urgency: "red" | "amber" | "yellow" }) {
  if (urgency === "red") return <ShieldAlert className="h-4 w-4 text-destructive" />;
  if (urgency === "amber") return <AlertTriangle className="h-4 w-4 text-orange-500" />;
  return <Clock className="h-4 w-4 text-yellow-500" />;
}

function EmptyChart() {
  return (
    <div className="flex items-center justify-center h-[200px] text-muted-foreground text-sm">
      No data available
    </div>
  );
}
