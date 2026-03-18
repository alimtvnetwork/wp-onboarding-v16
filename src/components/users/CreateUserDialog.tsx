// Create User dialog with all fields including social and Yoast.

import { useState } from "react";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { useCreateRemoteUser } from "@/hooks/useRemoteUsers";
import { WP_ROLES } from "@/types/wpUser";
import type { UserCreateInput } from "@/types/wpUser";

interface Props {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  siteId: number;
}

export function CreateUserDialog({ open, onOpenChange, siteId }: Props) {
  const [form, setForm] = useState<Partial<UserCreateInput>>({
    Role: "subscriber",
  });
  const [createAppPass, setCreateAppPass] = useState(false);

  const createMutation = useCreateRemoteUser(siteId);

  const update = (key: string, value: string) => setForm((p) => ({ ...p, [key]: value }));

  const handleSubmit = () => {
    const isUsernameEmpty = !form.Username?.trim();
    const isEmailEmpty = !form.Email?.trim();
    const isPasswordEmpty = !form.Password?.trim();

    if (isUsernameEmpty || isEmailEmpty || isPasswordEmpty) return;

    const input: UserCreateInput = {
      Username: form.Username!.trim(),
      Email: form.Email!.trim(),
      Password: form.Password!,
      FirstName: form.FirstName || undefined,
      LastName: form.LastName || undefined,
      DisplayName: form.DisplayName || undefined,
      Nickname: form.Nickname || undefined,
      Website: form.Website || undefined,
      Bio: form.Bio || undefined,
      Role: form.Role || "subscriber",
      CreateAppPassword: createAppPass,
      AppPasswordName: createAppPass ? "Dashboard API" : undefined,
    };

    createMutation.mutate(input, {
      onSuccess: () => {
        onOpenChange(false);
        setForm({ Role: "subscriber" });
        setCreateAppPass(false);
      },
    });
  };

  const isValid = !!form.Username?.trim() && !!form.Email?.trim() && !!form.Password?.trim();

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-lg max-h-[85vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>Create User</DialogTitle>
        </DialogHeader>

        <Tabs defaultValue="basic" className="mt-2">
          <TabsList className="grid w-full grid-cols-2">
            <TabsTrigger value="basic">Basic Info</TabsTrigger>
            <TabsTrigger value="profile">Profile</TabsTrigger>
          </TabsList>

          <TabsContent value="basic" className="space-y-4 mt-4">
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label>Username *</Label>
                <Input
                  value={form.Username || ""}
                  onChange={(e) => update("Username", e.target.value)}
                  placeholder="johndoe"
                />
              </div>
              <div className="space-y-2">
                <Label>Email *</Label>
                <Input
                  type="email"
                  value={form.Email || ""}
                  onChange={(e) => update("Email", e.target.value)}
                  placeholder="john@example.com"
                />
              </div>
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label>Password *</Label>
                <Input
                  type="password"
                  value={form.Password || ""}
                  onChange={(e) => update("Password", e.target.value)}
                />
              </div>
              <div className="space-y-2">
                <Label>Role</Label>
                <Select value={form.Role || "subscriber"} onValueChange={(v) => update("Role", v)}>
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    {WP_ROLES.map((r) => (
                      <SelectItem key={r.value} value={r.value}>{r.label}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            </div>

            <div className="flex items-center gap-3">
              <Switch checked={createAppPass} onCheckedChange={setCreateAppPass} />
              <Label className="text-sm">Create application password</Label>
            </div>
          </TabsContent>

          <TabsContent value="profile" className="space-y-4 mt-4">
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label>First Name</Label>
                <Input
                  value={form.FirstName || ""}
                  onChange={(e) => update("FirstName", e.target.value)}
                />
              </div>
              <div className="space-y-2">
                <Label>Last Name</Label>
                <Input
                  value={form.LastName || ""}
                  onChange={(e) => update("LastName", e.target.value)}
                />
              </div>
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label>Display Name</Label>
                <Input
                  value={form.DisplayName || ""}
                  onChange={(e) => update("DisplayName", e.target.value)}
                />
              </div>
              <div className="space-y-2">
                <Label>Website</Label>
                <Input
                  value={form.Website || ""}
                  onChange={(e) => update("Website", e.target.value)}
                  placeholder="https://"
                />
              </div>
            </div>

            <div className="space-y-2">
              <Label>Bio</Label>
              <Textarea
                value={form.Bio || ""}
                onChange={(e) => update("Bio", e.target.value)}
                rows={3}
                placeholder="Brief biographical info..."
              />
            </div>
          </TabsContent>
        </Tabs>

        <DialogFooter className="mt-4">
          <Button variant="outline" onClick={() => onOpenChange(false)}>Cancel</Button>
          <Button onClick={handleSubmit} disabled={!isValid || createMutation.isPending}>
            {createMutation.isPending ? "Creating…" : "Create User"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
