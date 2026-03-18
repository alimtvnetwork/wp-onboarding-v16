// User detail panel — shows full user info with edit capabilities.

import { useState, useEffect } from "react";
import {
  Sheet,
  SheetContent,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Separator } from "@/components/ui/separator";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { UserRoleBadge } from "./UserRoleBadge";
import { useRemoteUser, useUpdateRemoteUser } from "@/hooks/useRemoteUsers";
import { WP_ROLES } from "@/types/wpUser";
import type { WPUserSummary, UserUpdateInput } from "@/types/wpUser";
import { User, Save, Globe, AtSign } from "lucide-react";

interface Props {
  siteId: number;
  user: WPUserSummary | null;
  onClose: () => void;
}

export function UserDetailPanel({ siteId, user, onClose }: Props) {
  const { data: fullUser } = useRemoteUser(siteId, user?.Id ?? null);
  const updateMutation = useUpdateRemoteUser(siteId);
  const [editing, setEditing] = useState(false);
  const [form, setForm] = useState<UserUpdateInput>({});

  const isOpen = user !== null;

  useEffect(() => {
    if (!isOpen) setEditing(false);
  }, [isOpen]);

  const startEdit = () => {
    if (!fullUser) return;
    setForm({
      Email: fullUser.Email,
      FirstName: fullUser.FirstName || "",
      LastName: fullUser.LastName || "",
      DisplayName: fullUser.DisplayName || "",
      Website: fullUser.Website || "",
      Bio: fullUser.Bio || "",
      Role: fullUser.Role,
    });
    setEditing(true);
  };

  const handleSave = () => {
    if (!user) return;
    updateMutation.mutate(
      { userId: user.Id, input: form },
      { onSuccess: () => setEditing(false) }
    );
  };

  const update = (key: string, value: string) => setForm((p) => ({ ...p, [key]: value }));

  return (
    <Sheet open={isOpen} onOpenChange={(open) => !open && onClose()}>
      <SheetContent className="w-full sm:max-w-lg overflow-y-auto">
        <SheetHeader>
          <SheetTitle className="flex items-center gap-2">
            <User className="h-5 w-5 text-primary" />
            {user?.Username}
          </SheetTitle>
        </SheetHeader>

        {fullUser && (
          <div className="mt-6 space-y-6">
            {/* Core info */}
            <div className="grid grid-cols-2 gap-4">
              <InfoItem label="Username" value={fullUser.Username} />
              <div>
                <Label className="text-xs text-muted-foreground">Role</Label>
                <div className="mt-1">
                  {editing ? (
                    <Select value={form.Role || fullUser.Role} onValueChange={(v) => update("Role", v)}>
                      <SelectTrigger className="h-8"><SelectValue /></SelectTrigger>
                      <SelectContent>
                        {WP_ROLES.map((r) => (
                          <SelectItem key={r.value} value={r.value}>{r.label}</SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  ) : (
                    <UserRoleBadge role={fullUser.Role} />
                  )}
                </div>
              </div>
              <EditableField label="Email" value={form.Email || fullUser.Email} editing={editing} onChange={(v) => update("Email", v)} icon={AtSign} />
              <EditableField label="Website" value={form.Website || fullUser.Website || ""} editing={editing} onChange={(v) => update("Website", v)} icon={Globe} />
              <EditableField label="First Name" value={form.FirstName || fullUser.FirstName || ""} editing={editing} onChange={(v) => update("FirstName", v)} />
              <EditableField label="Last Name" value={form.LastName || fullUser.LastName || ""} editing={editing} onChange={(v) => update("LastName", v)} />
              <EditableField label="Display Name" value={form.DisplayName || fullUser.DisplayName || ""} editing={editing} onChange={(v) => update("DisplayName", v)} />
              <InfoItem label="Registered" value={fullUser.RegisteredAt || "—"} />
            </div>

            {/* Bio */}
            <div className="space-y-1">
              <Label className="text-xs text-muted-foreground">Bio</Label>
              {editing ? (
                <Textarea value={form.Bio || ""} onChange={(e) => update("Bio", e.target.value)} rows={3} />
              ) : (
                <p className="text-sm text-muted-foreground">{fullUser.Bio || "—"}</p>
              )}
            </div>

            {/* Social links */}
            {fullUser.Social && (
              <>
                <Separator />
                <div>
                  <h3 className="text-sm font-semibold mb-3">Social Profiles</h3>
                  <div className="grid grid-cols-2 gap-3">
                    {Object.entries(fullUser.Social).map(([platform, url]) => {
                      const hasValue = !!url;
                      if (!hasValue) return null;
                      return (
                        <div key={platform}>
                          <Label className="text-xs text-muted-foreground capitalize">{platform}</Label>
                          <p className="text-xs font-mono truncate mt-0.5">{url}</p>
                        </div>
                      );
                    })}
                  </div>
                </div>
              </>
            )}

            {/* Yoast */}
            {fullUser.Yoast && (
              <>
                <Separator />
                <div>
                  <h3 className="text-sm font-semibold mb-3">Yoast SEO</h3>
                  <div className="grid grid-cols-2 gap-3">
                    {Object.entries(fullUser.Yoast).map(([key, value]) => {
                      const hasValue = !!value;
                      if (!hasValue) return null;
                      return (
                        <div key={key}>
                          <Label className="text-xs text-muted-foreground">{formatKey(key)}</Label>
                          <p className="text-sm mt-0.5">{value}</p>
                        </div>
                      );
                    })}
                  </div>
                </div>
              </>
            )}

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
                  Edit User
                </Button>
              )}
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

function EditableField({ label, value, editing, onChange, icon: Icon }: {
  label: string;
  value: string;
  editing: boolean;
  onChange: (v: string) => void;
  icon?: React.ComponentType<{ className?: string }>;
}) {
  if (editing) {
    return (
      <div className="space-y-1">
        <Label className="text-xs text-muted-foreground">{label}</Label>
        <Input value={value} onChange={(e) => onChange(e.target.value)} className="h-8" />
      </div>
    );
  }

  return (
    <div>
      <Label className="text-xs text-muted-foreground">{label}</Label>
      <p className="text-sm font-medium mt-0.5 flex items-center gap-1">
        {Icon && <Icon className="h-3 w-3 text-muted-foreground" />}
        {value || "—"}
      </p>
    </div>
  );
}

function formatKey(key: string): string {
  return key.replace(/([A-Z])/g, " $1").trim();
}
