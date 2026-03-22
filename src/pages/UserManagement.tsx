// User Management page — remote WordPress user CRUD.

import { useState, useEffect } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { useSites } from "@/hooks/useSites";
import { useRemoteUsers } from "@/hooks/useRemoteUsers";
import { UserTable } from "@/components/users/UserTable";
import { CreateUserDialog } from "@/components/users/CreateUserDialog";
import { UserDetailPanel } from "@/components/users/UserDetailPanel";
import { Users, Plus, Search, RefreshCw } from "lucide-react";
import { WP_ROLES } from "@/types/wpUser";
import type { WPUserSummary } from "@/types/wpUser";
import { useQueryClient } from "@tanstack/react-query";

export default function UserManagement() {
  const { data: sites } = useSites();
  const [selectedSiteId, setSelectedSiteId] = useState<number | null>(null);
  const [roleFilter, setRoleFilter] = useState<string>("");
  const [searchQuery, setSearchQuery] = useState("");
  const [createOpen, setCreateOpen] = useState(false);
  const [selectedUser, setSelectedUser] = useState<WPUserSummary | null>(null);
  const queryClient = useQueryClient();

  const { data: usersResponse, isLoading, isError } = useRemoteUsers(
    selectedSiteId,
    {
      role: roleFilter || undefined,
      search: searchQuery || undefined,
    }
  );

  // Auto-select first site if none selected
  const hasSites = sites && sites.length > 0;
  if (hasSites && selectedSiteId === null) {
    setSelectedSiteId(sites[0].id);
  }

  // Extract users from envelope response
  const users: WPUserSummary[] = Array.isArray(usersResponse)
    ? usersResponse
    : (usersResponse as any)?.Result ?? [];

  const handleRefresh = () => {
    if (selectedSiteId) {
      queryClient.invalidateQueries({ queryKey: ["users", selectedSiteId] });
    }
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <Users className="h-6 w-6 text-primary" />
          <div>
            <h1 className="text-2xl font-bold tracking-tight">User Management</h1>
            <p className="text-sm text-muted-foreground">
              Manage WordPress users across your connected sites
            </p>
          </div>
        </div>
        <div className="flex items-center gap-2">
          <Button variant="outline" size="icon" onClick={handleRefresh} className="h-8 w-8">
            <RefreshCw className="h-4 w-4" />
          </Button>
          {selectedSiteId && (
            <Button size="sm" onClick={() => setCreateOpen(true)}>
              <Plus className="h-4 w-4 mr-1.5" />
              New User
            </Button>
          )}
        </div>
      </div>

      {/* Filters */}
      <div className="flex items-center gap-3 flex-wrap">
        <Select
          value={selectedSiteId ? String(selectedSiteId) : ""}
          onValueChange={(v) => setSelectedSiteId(Number(v))}
        >
          <SelectTrigger className="w-[220px]">
            <SelectValue placeholder="Select a site…" />
          </SelectTrigger>
          <SelectContent>
            {sites?.map((site) => (
              <SelectItem key={site.id} value={String(site.id)}>
                {site.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>

        <Select value={roleFilter} onValueChange={setRoleFilter}>
          <SelectTrigger className="w-[160px]">
            <SelectValue placeholder="All roles" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="">All Roles</SelectItem>
            {WP_ROLES.map((r) => (
              <SelectItem key={r.value} value={r.value}>{r.label}</SelectItem>
            ))}
          </SelectContent>
        </Select>

        <div className="relative flex-1 max-w-xs">
          <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
          <Input
            placeholder="Search users…"
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            className="pl-9"
          />
        </div>
      </div>

      {/* Content */}
      {!selectedSiteId ? (
        <div className="text-center py-12 text-muted-foreground">
          Select a site to manage its WordPress users.
        </div>
      ) : isLoading ? (
        <div className="text-center py-12 text-muted-foreground">Loading users…</div>
      ) : isError ? (
        <div className="text-center py-12 text-destructive">
          Failed to load users. Ensure the site is running and the Riseup Asia Uploader plugin is active.
        </div>
      ) : (
        <UserTable users={users} siteId={selectedSiteId} onSelect={setSelectedUser} />
      )}

      {/* Dialogs */}
      {selectedSiteId && (
        <>
          <CreateUserDialog open={createOpen} onOpenChange={setCreateOpen} siteId={selectedSiteId} />
          <UserDetailPanel siteId={selectedSiteId} user={selectedUser} onClose={() => setSelectedUser(null)} />
        </>
      )}
    </div>
  );
}
