// Licensing Admin Dashboard — main page.

import { useState, useMemo } from "react";
import { differenceInDays, parseISO } from "date-fns";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { useLicenses, useAuditLogs, useUpdateLicense } from "@/hooks/useLicensing";
import { LicenseTable } from "@/components/licensing/LicenseTable";
import { CreateLicenseDialog } from "@/components/licensing/CreateLicenseDialog";
import { LicenseDetailPanel } from "@/components/licensing/LicenseDetailPanel";
import { AuditLogList } from "@/components/licensing/AuditLogList";
import { LicensingHealthBadge } from "@/components/licensing/LicensingHealthBadge";
import { LicensingAnalyticsTab } from "@/components/licensing/LicensingAnalyticsTab";
import { LicenseBatchActions } from "@/components/licensing/LicenseBatchActions";
import { KeyRound, Plus, ScrollText, RefreshCw, BarChart3 } from "lucide-react";
import type { License } from "@/types/licensing";
import { useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

export default function Licensing() {
  const [createOpen, setCreateOpen] = useState(false);
  const [selectedLicense, setSelectedLicense] = useState<License | null>(null);
  const [batchSelected, setBatchSelected] = useState<License[]>([]);

  const { data: licenses, isLoading, isError } = useLicenses();
  const { data: auditLogs, isLoading: auditLoading } = useAuditLogs();
  const queryClient = useQueryClient();
  const updateMutation = useUpdateLicense();

  const handleRefresh = () => {
    queryClient.invalidateQueries({ queryKey: ["licensing"] });
  };

  const stats = useMemo(() => {
    const all = licenses ?? [];
    const now = new Date();
    return {
      total: all.length,
      active: all.filter((l) => l.status === "active").length,
      expired: all.filter((l) => l.status === "expired").length,
      revoked: all.filter((l) => l.status === "revoked").length,
      expiringSoon: all.filter((l) => {
        if (l.status !== "active" || !l.expires_at) return false;
        return differenceInDays(parseISO(l.expires_at), now) <= 30;
      }).length,
      totalActivations: all.reduce((sum, l) => sum + l.max_activations, 0),
    };
  }, [licenses]);

  const toggleBatchSelect = (license: License) => {
    setBatchSelected((prev) =>
      prev.some((l) => l.id === license.id)
        ? prev.filter((l) => l.id !== license.id)
        : [...prev, license]
    );
  };

  const toggleSelectAll = () => {
    if (!licenses) return;
    setBatchSelected((prev) =>
      prev.length === licenses.length ? [] : [...licenses]
    );
  };

  const handleExtendLicense = (id: number) => {
    updateMutation.mutate(
      { id, input: { status: "active" } },
      { onSuccess: () => toast.success("License extended") }
    );
  };

  const handleRevokeLicense = (id: number) => {
    updateMutation.mutate(
      { id, input: { status: "revoked" } },
      { onSuccess: () => toast.success("License revoked") }
    );
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <KeyRound className="h-6 w-6 text-primary" />
          <div>
            <h1 className="text-2xl font-bold tracking-tight">Licensing</h1>
            <p className="text-sm text-muted-foreground">
              Manage license keys, activations, and audit trail
            </p>
          </div>
        </div>
        <div className="flex items-center gap-2">
          <LicensingHealthBadge />
          <Button variant="outline" size="icon" onClick={handleRefresh} className="h-8 w-8">
            <RefreshCw className="h-4 w-4" />
          </Button>
          <Button size="sm" onClick={() => setCreateOpen(true)}>
            <Plus className="h-4 w-4 mr-1.5" />
            New License
          </Button>
        </div>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
        <StatCard label="Total Licenses" value={stats.total} />
        <StatCard label="Active" value={stats.active} />
        <StatCard label="Expired" value={stats.expired} />
        <StatCard label="Revoked" value={stats.revoked} />
        <StatCard label="Expiring Soon" value={stats.expiringSoon} highlight={stats.expiringSoon > 0} />
        <StatCard label="Total Activations" value={stats.totalActivations} />
      </div>

      {/* Tabs */}
      <Tabs defaultValue="licenses">
        <TabsList>
          <TabsTrigger value="licenses" className="gap-1.5">
            <KeyRound className="h-3.5 w-3.5" />
            Licenses
          </TabsTrigger>
          <TabsTrigger value="analytics" className="gap-1.5">
            <BarChart3 className="h-3.5 w-3.5" />
            Analytics
          </TabsTrigger>
          <TabsTrigger value="audit" className="gap-1.5">
            <ScrollText className="h-3.5 w-3.5" />
            Audit Log
          </TabsTrigger>
        </TabsList>

        <TabsContent value="licenses" className="mt-4 space-y-3">
          <LicenseBatchActions
            selected={batchSelected}
            onClear={() => setBatchSelected([])}
            allLicenses={licenses ?? []}
          />
          {isLoading ? (
            <div className="text-center py-12 text-muted-foreground">Loading licenses…</div>
          ) : isError ? (
            <div className="text-center py-12 text-destructive">
              Failed to load licenses. Check that the licensing server is running and configured.
            </div>
          ) : (
            <LicenseTable
              licenses={licenses ?? []}
              onSelect={setSelectedLicense}
              batchSelected={batchSelected}
              onBatchToggle={toggleBatchSelect}
              onSelectAll={toggleSelectAll}
            />
          )}
        </TabsContent>

        <TabsContent value="analytics" className="mt-4">
          <LicensingAnalyticsTab
            licenses={licenses ?? []}
            onExtendLicense={handleExtendLicense}
            onRevokeLicense={handleRevokeLicense}
          />
        </TabsContent>

        <TabsContent value="audit" className="mt-4">
          {auditLoading ? (
            <div className="text-center py-12 text-muted-foreground">Loading audit log…</div>
          ) : (
            <AuditLogList logs={auditLogs ?? []} />
          )}
        </TabsContent>
      </Tabs>

      {/* Dialogs */}
      <CreateLicenseDialog open={createOpen} onOpenChange={setCreateOpen} />
      <LicenseDetailPanel license={selectedLicense} onClose={() => setSelectedLicense(null)} />
    </div>
  );
}

function StatCard({ label, value, highlight }: { label: string; value: number; highlight?: boolean }) {
  return (
    <div className={`rounded-lg border p-4 ${highlight ? "border-warning/50 bg-warning/5" : "border-border bg-card"}`}>
      <p className="text-xs text-muted-foreground">{label}</p>
      <p className={`text-2xl font-bold mt-1 ${highlight ? "text-warning" : ""}`}>{value}</p>
    </div>
  );
}
