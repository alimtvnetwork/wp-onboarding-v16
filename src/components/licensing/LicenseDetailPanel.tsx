// License detail panel — shows full license info with edit capabilities.

import { useState } from "react";
import { format } from "date-fns";
import {
  Sheet,
  SheetContent,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Separator } from "@/components/ui/separator";
import { LicenseStatusBadge } from "./LicenseStatusBadge";
import { LicenseTypeBadge } from "./LicenseTypeBadge";
import { useUpdateLicense, useAuditLogs } from "@/hooks/useLicensing";
import { AuditLogList } from "./AuditLogList";
import type { License, LicenseStatus, LicenseType } from "@/types/licensing";
import { Copy, Check, Save, KeyRound } from "lucide-react";

interface Props {
  license: License | null;
  onClose: () => void;
}

export function LicenseDetailPanel({ license, onClose }: Props) {
  const [editing, setEditing] = useState(false);
  const [status, setStatus] = useState<LicenseStatus>("active");
  const [type, setType] = useState<LicenseType>("standard");
  const [maxAct, setMaxAct] = useState("1");
  const [notes, setNotes] = useState("");
  const [copied, setCopied] = useState(false);

  const updateMutation = useUpdateLicense();
  const { data: auditLogs } = useAuditLogs(
    license ? { license_id: license.id } : undefined
  );

  const isOpen = license !== null;

  const startEdit = () => {
    if (!license) return;
    setStatus(license.status);
    setType(license.type);
    setMaxAct(String(license.max_activations));
    setNotes(license.notes || "");
    setEditing(true);
  };

  const handleSave = () => {
    if (!license) return;
    updateMutation.mutate(
      {
        id: license.id,
        input: {
          status,
          type,
          maxActivations: parseInt(maxAct, 10) || 1,
          notes,
        },
      },
      { onSuccess: () => setEditing(false) }
    );
  };

  const handleCopy = () => {
    if (!license) return;
    navigator.clipboard.writeText(license.key);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  return (
    <Sheet open={isOpen} onOpenChange={(open) => !open && onClose()}>
      <SheetContent className="w-full sm:max-w-lg overflow-y-auto">
        <SheetHeader>
          <SheetTitle className="flex items-center gap-2">
            <KeyRound className="h-5 w-5 text-primary" />
            License #{license?.id}
          </SheetTitle>
        </SheetHeader>

        {license && (
          <div className="mt-6 space-y-6">
            {/* Key */}
            <div className="space-y-1">
              <Label className="text-xs text-muted-foreground">License Key</Label>
              <div className="flex items-center gap-2">
                <code className="flex-1 text-xs bg-muted px-3 py-2 rounded font-mono break-all">
                  {license.key}
                </code>
                <Button variant="ghost" size="icon" className="h-8 w-8 shrink-0" onClick={handleCopy}>
                  {copied ? <Check className="h-4 w-4 text-success" /> : <Copy className="h-4 w-4" />}
                </Button>
              </div>
            </div>

            {/* Info grid */}
            <div className="grid grid-cols-2 gap-4">
              <InfoItem label="Email" value={license.email} />
              <InfoItem label="Product" value={license.product} />
              <div>
                <Label className="text-xs text-muted-foreground">Status</Label>
                <div className="mt-1">
                  {editing ? (
                    <Select value={status} onValueChange={(v) => setStatus(v as LicenseStatus)}>
                      <SelectTrigger className="h-8"><SelectValue /></SelectTrigger>
                      <SelectContent>
                        <SelectItem value="active">Active</SelectItem>
                        <SelectItem value="expired">Expired</SelectItem>
                        <SelectItem value="suspended">Suspended</SelectItem>
                        <SelectItem value="revoked">Revoked</SelectItem>
                      </SelectContent>
                    </Select>
                  ) : (
                    <LicenseStatusBadge status={license.status} />
                  )}
                </div>
              </div>
              <div>
                <Label className="text-xs text-muted-foreground">Type</Label>
                <div className="mt-1">
                  {editing ? (
                    <Select value={type} onValueChange={(v) => setType(v as LicenseType)}>
                      <SelectTrigger className="h-8"><SelectValue /></SelectTrigger>
                      <SelectContent>
                        <SelectItem value="standard">Standard</SelectItem>
                        <SelectItem value="professional">Professional</SelectItem>
                        <SelectItem value="enterprise">Enterprise</SelectItem>
                      </SelectContent>
                    </Select>
                  ) : (
                    <LicenseTypeBadge type={license.type} />
                  )}
                </div>
              </div>
              <div>
                <Label className="text-xs text-muted-foreground">Max Activations</Label>
                <div className="mt-1">
                  {editing ? (
                    <Input
                      type="number"
                      min="1"
                      value={maxAct}
                      onChange={(e) => setMaxAct(e.target.value)}
                      className="h-8"
                    />
                  ) : (
                    <span className="text-sm font-medium">{license.max_activations}</span>
                  )}
                </div>
              </div>
              <InfoItem
                label="Expires"
                value={license.expires_at ? format(new Date(license.expires_at), "MMM d, yyyy") : "Never"}
              />
              <InfoItem label="Created" value={format(new Date(license.created_at), "MMM d, yyyy HH:mm")} />
              <InfoItem label="Updated" value={format(new Date(license.updated_at), "MMM d, yyyy HH:mm")} />
            </div>

            {/* Notes */}
            <div className="space-y-1">
              <Label className="text-xs text-muted-foreground">Notes</Label>
              {editing ? (
                <Textarea value={notes} onChange={(e) => setNotes(e.target.value)} rows={3} />
              ) : (
                <p className="text-sm text-muted-foreground">{license.notes || "—"}</p>
              )}
            </div>

            {/* Actions */}
            <div className="flex gap-2">
              {editing ? (
                <>
                  <Button size="sm" onClick={handleSave} disabled={updateMutation.isPending}>
                    <Save className="h-3.5 w-3.5 mr-1.5" />
                    {updateMutation.isPending ? "Saving…" : "Save"}
                  </Button>
                  <Button size="sm" variant="outline" onClick={() => setEditing(false)}>
                    Cancel
                  </Button>
                </>
              ) : (
                <Button size="sm" variant="outline" onClick={startEdit}>
                  Edit License
                </Button>
              )}
            </div>

            <Separator />

            {/* Audit log for this license */}
            <div>
              <h3 className="text-sm font-semibold mb-3">Audit History</h3>
              <AuditLogList logs={auditLogs ?? []} compact />
            </div>
          </div>
        )}
      </SheetContent>
    </Sheet>
  );
}

function InfoItem({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <Label className="text-xs text-muted-foreground">{label}</Label>
      <p className="text-sm font-medium mt-0.5">{value}</p>
    </div>
  );
}
