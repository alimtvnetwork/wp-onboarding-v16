// User table — lists all users from a remote WordPress site.

import { useState } from "react";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { Button } from "@/components/ui/button";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { Eye, Trash2 } from "lucide-react";
import { UserRoleBadge } from "./UserRoleBadge";
import { useDeleteRemoteUser } from "@/hooks/useRemoteUsers";
import type { WPUserSummary } from "@/types/wpUser";

interface Props {
  users: WPUserSummary[];
  siteId: number;
  onSelect: (user: WPUserSummary) => void;
}

export function UserTable({ users, siteId, onSelect }: Props) {
  const [deleteTarget, setDeleteTarget] = useState<WPUserSummary | null>(null);
  const deleteMutation = useDeleteRemoteUser(siteId);

  const handleDelete = () => {
    if (!deleteTarget) return;
    deleteMutation.mutate(
      { userId: deleteTarget.Id, reassignTo: 1 },
      { onSettled: () => setDeleteTarget(null) }
    );
  };

  const isEmpty = users.length === 0;

  if (isEmpty) {
    return (
      <div className="text-center py-12 text-muted-foreground">
        No users found on this site.
      </div>
    );
  }

  return (
    <>
      <div className="rounded-md border border-border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className="w-[60px]">ID</TableHead>
              <TableHead>Username</TableHead>
              <TableHead>Email</TableHead>
              <TableHead>Display Name</TableHead>
              <TableHead>Role</TableHead>
              <TableHead className="text-right">Actions</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {users.map((user) => (
              <TableRow
                key={user.Id}
                className="cursor-pointer hover:bg-muted/50"
                onClick={() => onSelect(user)}
              >
                <TableCell className="font-mono text-xs text-muted-foreground">
                  {user.Id}
                </TableCell>
                <TableCell className="font-medium text-sm">{user.Username}</TableCell>
                <TableCell className="text-sm">{user.Email}</TableCell>
                <TableCell className="text-sm text-muted-foreground">
                  {user.DisplayName || "—"}
                </TableCell>
                <TableCell>
                  <UserRoleBadge role={user.Role} />
                </TableCell>
                <TableCell className="text-right">
                  <div className="flex items-center justify-end gap-1">
                    <Button
                      variant="ghost"
                      size="icon"
                      className="h-7 w-7"
                      onClick={(e) => {
                        e.stopPropagation();
                        onSelect(user);
                      }}
                    >
                      <Eye className="h-3.5 w-3.5" />
                    </Button>
                    <Button
                      variant="ghost"
                      size="icon"
                      className="h-7 w-7 text-destructive hover:text-destructive"
                      onClick={(e) => {
                        e.stopPropagation();
                        setDeleteTarget(user);
                      }}
                    >
                      <Trash2 className="h-3.5 w-3.5" />
                    </Button>
                  </div>
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>

      <AlertDialog open={!!deleteTarget} onOpenChange={() => setDeleteTarget(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete User</AlertDialogTitle>
            <AlertDialogDescription>
              This will permanently delete <strong>{deleteTarget?.Username}</strong> and
              reassign their content to the site admin. This action cannot be undone.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction
              onClick={handleDelete}
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
            >
              Delete
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}
