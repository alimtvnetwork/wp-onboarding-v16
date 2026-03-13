import { useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
import {
  Users,
  Plus,
  Star,
  Trash2,
  Edit,
  Loader2,
  CheckCircle,
  XCircle,
} from "lucide-react";
import { api, Site, SiteCredentialResponse } from "@/lib/api";
import { toast } from "sonner";

interface SiteCredentialsPanelProps {
  site: Site;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

export function SiteCredentialsPanel({ site, open, onOpenChange }: SiteCredentialsPanelProps) {
  const queryClient = useQueryClient();
  const [showAddForm, setShowAddForm] = useState(false);
  const [editingCred, setEditingCred] = useState<SiteCredentialResponse | null>(null);
  const [formData, setFormData] = useState({ appName: "", username: "", password: "" });
  const [isSubmitting, setIsSubmitting] = useState(false);

  const { data: credentials, isLoading } = useQuery({
    queryKey: ["sites", site.id, "credentials"],
    queryFn: async () => {
      const res = await api.listSiteCredentials(site.id);
      if (res.success) return res.data || [];
      return [];
    },
    enabled: open,
  });

  const resetForm = () => {
    setFormData({ appName: "", username: "", password: "" });
    setShowAddForm(false);
    setEditingCred(null);
  };

  const handleAdd = async () => {
    if (!formData.appName || !formData.username || !formData.password) {
      toast.error("All fields are required");
      return;
    }

    setIsSubmitting(true);
    try {
      const res = await api.createSiteCredential(site.id, formData);
      if (res.success) {
        toast.success("Credential added");
        queryClient.invalidateQueries({ queryKey: ["sites", site.id, "credentials"] });
        resetForm();
      } else {
        toast.error(res.error?.message || "Failed to add credential");
      }
    } catch {
      toast.error("Failed to add credential");
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleUpdate = async () => {
    if (!editingCred || !formData.appName || !formData.username || !formData.password) {
      toast.error("All fields are required");
      return;
    }

    setIsSubmitting(true);
    try {
      const res = await api.updateSiteCredential(site.id, editingCred.id, formData);
      if (res.success) {
        toast.success("Credential updated");
        queryClient.invalidateQueries({ queryKey: ["sites", site.id, "credentials"] });
        resetForm();
      } else {
        toast.error(res.error?.message || "Failed to update credential");
      }
    } catch {
      toast.error("Failed to update credential");
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleDelete = async (credId: number) => {
    try {
      const res = await api.deleteSiteCredential(site.id, credId);
      if (res.success) {
        toast.success("Credential deleted");
        queryClient.invalidateQueries({ queryKey: ["sites", site.id, "credentials"] });
      } else {
        toast.error(res.error?.message || "Failed to delete credential");
      }
    } catch {
      toast.error("Failed to delete credential");
    }
  };

  const handleSetDefault = async (credId: number) => {
    try {
      const res = await api.setDefaultCredential(site.id, credId);
      if (res.success) {
        toast.success("Default credential updated");
        queryClient.invalidateQueries({ queryKey: ["sites", site.id, "credentials"] });
      } else {
        toast.error(res.error?.message || "Failed to set default");
      }
    } catch {
      toast.error("Failed to set default");
    }
  };

  const startEdit = (cred: SiteCredentialResponse) => {
    setEditingCred(cred);
    setFormData({ appName: cred.appName, username: cred.username, password: "" });
    setShowAddForm(true);
  };

  return (
    <Dialog open={open} onOpenChange={(v) => { onOpenChange(v); if (!v) resetForm(); }}>
      <DialogContent className="max-w-lg max-h-[80vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <Users className="h-5 w-5" />
            Credentials — {site.name}
          </DialogTitle>
          <DialogDescription>
            Manage application passwords for this site. The default credential is used for all API operations.
          </DialogDescription>
        </DialogHeader>

        {/* Credentials list */}
        <div className="space-y-2">
          {isLoading && (
            <div className="flex items-center justify-center py-6">
              <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />
            </div>
          )}

          {credentials?.map((cred) => (
            <div
              key={cred.id}
              className="flex items-center justify-between p-3 rounded-lg border bg-card hover:bg-secondary/30 transition-colors"
            >
              <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2">
                  <span className="font-medium text-sm truncate">{cred.appName}</span>
                  {cred.isDefault && (
                    <Badge variant="default" className="text-[10px] px-1.5 py-0">
                      <Star className="h-3 w-3 mr-0.5" />
                      Default
                    </Badge>
                  )}
                  {cred.connectionStatus === "connected" ? (
                    <CheckCircle className="h-3.5 w-3.5 text-primary shrink-0" />
                  ) : cred.connectionStatus === "disconnected" ? (
                    <XCircle className="h-3.5 w-3.5 text-destructive shrink-0" />
                  ) : null}
                </div>
                <p className="text-xs text-muted-foreground truncate">{cred.username}</p>
              </div>
              <div className="flex items-center gap-1 shrink-0">
                {!cred.isDefault && (
                  <Button
                    variant="ghost"
                    size="icon"
                    className="h-7 w-7"
                    onClick={() => handleSetDefault(cred.id)}
                    title="Set as default"
                  >
                    <Star className="h-3.5 w-3.5" />
                  </Button>
                )}
                <Button
                  variant="ghost"
                  size="icon"
                  className="h-7 w-7"
                  onClick={() => startEdit(cred)}
                  title="Edit"
                >
                  <Edit className="h-3.5 w-3.5" />
                </Button>
                <Button
                  variant="ghost"
                  size="icon"
                  className="h-7 w-7 text-destructive hover:text-destructive hover:bg-destructive/10"
                  onClick={() => handleDelete(cred.id)}
                  title="Delete"
                >
                  <Trash2 className="h-3.5 w-3.5" />
                </Button>
              </div>
            </div>
          ))}

          {!isLoading && (!credentials || credentials.length === 0) && (
            <p className="text-sm text-muted-foreground text-center py-4">
              No credentials found. Add one below.
            </p>
          )}
        </div>

        {/* Add/Edit form */}
        {showAddForm ? (
          <div className="space-y-3 pt-3 border-t">
            <h4 className="text-sm font-medium">{editingCred ? "Edit Credential" : "Add Credential"}</h4>
            <div className="space-y-2">
              <div>
                <Label htmlFor="appName" className="text-xs">App Name</Label>
                <Input
                  id="appName"
                  placeholder="e.g. test-plg-v1"
                  value={formData.appName}
                  onChange={(e) => setFormData((p) => ({ ...p, appName: e.target.value }))}
                />
              </div>
              <div>
                <Label htmlFor="username" className="text-xs">Username</Label>
                <Input
                  id="username"
                  placeholder="e.g. admin@example.com"
                  value={formData.username}
                  onChange={(e) => setFormData((p) => ({ ...p, username: e.target.value }))}
                />
              </div>
              <div>
                <Label htmlFor="password" className="text-xs">Application Password</Label>
                <Input
                  id="password"
                  type="password"
                  placeholder="xxxx xxxx xxxx xxxx xxxx xxxx"
                  value={formData.password}
                  onChange={(e) => setFormData((p) => ({ ...p, password: e.target.value }))}
                />
              </div>
            </div>
            <div className="flex gap-2">
              <Button
                size="sm"
                onClick={editingCred ? handleUpdate : handleAdd}
                disabled={isSubmitting}
              >
                {isSubmitting && <Loader2 className="h-3 w-3 animate-spin mr-1" />}
                {editingCred ? "Update" : "Add"}
              </Button>
              <Button size="sm" variant="outline" onClick={resetForm}>
                Cancel
              </Button>
            </div>
          </div>
        ) : (
          <Button
            variant="outline"
            size="sm"
            className="w-full"
            onClick={() => setShowAddForm(true)}
          >
            <Plus className="h-4 w-4 mr-1" />
            Add Credential
          </Button>
        )}
      </DialogContent>
    </Dialog>
  );
}
