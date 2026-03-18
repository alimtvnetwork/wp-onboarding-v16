// Licensing Admin Dashboard — main page.

import { useState } from "react";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Button } from "@/components/ui/button";
import { useLicenses, useAuditLogs } from "@/hooks/useLicensing";
import { LicenseTable } from "@/components/licensing/LicenseTable";
import { CreateLicenseDialog } from "@/components/licensing/CreateLicenseDialog";
import { LicenseDetailPanel } from "@/components/licensing/LicenseDetailPanel";
import { AuditLogList } from "@/components/licensing/AuditLogList";
import { LicensingHealthBadge } from "@/components/licensing/LicensingHealthBadge";
import { KeyRound, Plus, ScrollText, RefreshCw } from "lucide-react";
import type { License } from "@/types/licensing";
import { useQueryClient } from "@tanstack/react-query";

export default function Licensing() {
  const [createOpen, setCreateOpen] = useState(false);
  const [selectedLicense, setSelectedLicense] = useState<License | null>(null);

  const { data: licenses, isLoading, isError } = useLicenses();
  const { data: auditLogs, isLoading: auditLoading } = useAuditLogs();
  const queryClient = useQueryClient();

  const handleRefresh = () => {
    queryClient.invalidateQueries({ queryKey: ["licensing"] });
  };

  const totalLicenses = licenses?.length ?? 0;
  const activeLicenses = licenses?.filter((l) => l.status === "active").length ?? 0;

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
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <StatCard label="Total Licenses" value={totalLicenses} />
        <StatCard label="Active" value={activeLicenses} />
        <StatCard
          label="Expired"
          value={licenses?.filter((l) => l.status === "expired").length ?? 0}
        />
        <StatCard
          label="Audit Entries"
          value={auditLogs?.length ?? 0}
        />
      </div>

      {/* Tabs */}
      <Tabs defaultValue="licenses">
        <TabsList>
          <TabsTrigger value="licenses" className="gap-1.5">
            <KeyRound className="h-3.5 w-3.5" />
            Licenses
          </TabsTrigger>
          <TabsTrigger value="audit" className="gap-1.5">
            <ScrollText className="h-3.5 w-3.5" />
            Audit Log
          </TabsTrigger>
        </TabsList>

        <TabsContent value="licenses" className="mt-4">
          {isLoading ? (
            <div className="text-center py-12 text-muted-foreground">Loading licenses…</div>
          ) : isError ? (
            <div className="text-center py-12 text-destructive">
              Failed to load licenses. Check that the licensing server is running and configured.
            </div>
          ) : (
            <LicenseTable licenses={licenses ?? []} onSelect={setSelectedLicense} />
          )}
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

function StatCard({ label, value }: { label: string; value: number }) {
  return (
    <div className="rounded-lg border border-border bg-card p-4">
      <p className="text-xs text-muted-foreground">{label}</p>
      <p className="text-2xl font-bold mt-1">{value}</p>
    </div>
  );
}
