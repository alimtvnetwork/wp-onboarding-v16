// License table — lists all licenses with inline actions.

import { useState } from "react";
import { format } from "date-fns";
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
import { Checkbox } from "@/components/ui/checkbox";
import { Trash2, Eye, Copy, Check } from "lucide-react";
import { LicenseStatusBadge } from "./LicenseStatusBadge";
import { LicenseTypeBadge } from "./LicenseTypeBadge";
import { useDeleteLicense } from "@/hooks/useLicensing";
import type { License } from "@/types/licensing";

interface Props {
  licenses: License[];
  onSelect: (license: License) => void;
  batchSelected?: License[];
  onBatchToggle?: (license: License) => void;
  onSelectAll?: () => void;
}

export function LicenseTable({ licenses, onSelect }: Props) {
  const [deleteTarget, setDeleteTarget] = useState<License | null>(null);
  const [copiedId, setCopiedId] = useState<number | null>(null);
  const deleteMutation = useDeleteLicense();

  const handleCopyKey = (license: License) => {
    navigator.clipboard.writeText(license.key);
    setCopiedId(license.id);
    setTimeout(() => setCopiedId(null), 2000);
  };

  const handleDelete = () => {
    if (!deleteTarget) return;
    deleteMutation.mutate(deleteTarget.id, {
      onSettled: () => setDeleteTarget(null),
    });
  };

  const isEmpty = licenses.length === 0;

  if (isEmpty) {
    return (
      <div className="text-center py-12 text-muted-foreground">
        No licenses found. Create one to get started.
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
              <TableHead>Key</TableHead>
              <TableHead>Email</TableHead>
              <TableHead>Status</TableHead>
              <TableHead>Type</TableHead>
              <TableHead className="text-center">Activations</TableHead>
              <TableHead>Created</TableHead>
              <TableHead className="text-right">Actions</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {licenses.map((license) => (
              <TableRow
                key={license.id}
                className="cursor-pointer hover:bg-muted/50"
                onClick={() => onSelect(license)}
              >
                <TableCell className="font-mono text-xs text-muted-foreground">
                  {license.id}
                </TableCell>
                <TableCell>
                  <div className="flex items-center gap-1.5">
                    <code className="text-xs bg-muted px-1.5 py-0.5 rounded font-mono">
                      {license.key.slice(0, 8)}…{license.key.slice(-4)}
                    </code>
                    <Button
                      variant="ghost"
                      size="icon"
                      className="h-6 w-6"
                      onClick={(e) => {
                        e.stopPropagation();
                        handleCopyKey(license);
                      }}
                    >
                      {copiedId === license.id ? (
                        <Check className="h-3 w-3 text-success" />
                      ) : (
                        <Copy className="h-3 w-3" />
                      )}
                    </Button>
                  </div>
                </TableCell>
                <TableCell className="text-sm">{license.email}</TableCell>
                <TableCell>
                  <LicenseStatusBadge status={license.status} />
                </TableCell>
                <TableCell>
                  <LicenseTypeBadge type={license.type} />
                </TableCell>
                <TableCell className="text-center text-sm">
                  {license.max_activations}
                </TableCell>
                <TableCell className="text-sm text-muted-foreground">
                  {format(new Date(license.created_at), "MMM d, yyyy")}
                </TableCell>
                <TableCell className="text-right">
                  <div className="flex items-center justify-end gap-1">
                    <Button
                      variant="ghost"
                      size="icon"
                      className="h-7 w-7"
                      onClick={(e) => {
                        e.stopPropagation();
                        onSelect(license);
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
                        setDeleteTarget(license);
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
            <AlertDialogTitle>Delete License</AlertDialogTitle>
            <AlertDialogDescription>
              This will permanently delete the license for{" "}
              <strong>{deleteTarget?.email}</strong>. This action cannot be undone.
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
