import { useState, useMemo } from "react";
import { SnapshotRecord } from "@/lib/api/types";
import { Button } from "@/components/ui/button";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
} from "@/components/ui/dialog";
import {
  GitCompareArrows,
  Plus,
  Minus,
  Equal,
  ArrowUp,
  ArrowDown,
  Database,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { format } from "date-fns";

interface SnapshotComparisonViewProps {
  snapshots: SnapshotRecord[];
}

interface TableDiff {
  table: string;
  status: "added" | "removed" | "unchanged" | "changed";
  leftRows?: number;
  rightRows?: number;
  rowDelta?: number;
}

function parseTableInfo(snap: SnapshotRecord | undefined): Map<string, number> {
  const map = new Map<string, number>();
  if (!snap?.tables) return map;
  const tables = snap.tables.split(",").filter(Boolean);
  // We don't have per-table row counts from the record, so we distribute evenly as an estimate
  const perTable = snap.total_rows ? Math.round(snap.total_rows / (tables.length || 1)) : 0;
  tables.forEach((t) => map.set(t.trim(), perTable));
  return map;
}

function computeDiff(left: SnapshotRecord | undefined, right: SnapshotRecord | undefined): TableDiff[] {
  const leftTables = parseTableInfo(left);
  const rightTables = parseTableInfo(right);
  const allTables = new Set([...leftTables.keys(), ...rightTables.keys()]);
  const diffs: TableDiff[] = [];

  allTables.forEach((table) => {
    const inLeft = leftTables.has(table);
    const inRight = rightTables.has(table);
    const leftRows = leftTables.get(table) ?? 0;
    const rightRows = rightTables.get(table) ?? 0;

    if (inLeft && !inRight) {
      diffs.push({ table, status: "removed", leftRows, rightRows: undefined });
    } else if (!inLeft && inRight) {
      diffs.push({ table, status: "added", leftRows: undefined, rightRows });
    } else if (leftRows !== rightRows) {
      diffs.push({ table, status: "changed", leftRows, rightRows, rowDelta: rightRows - leftRows });
    } else {
      diffs.push({ table, status: "unchanged", leftRows, rightRows });
    }
  });

  // Sort: added first, then removed, changed, unchanged
  const order = { added: 0, removed: 1, changed: 2, unchanged: 3 };
  return diffs.sort((a, b) => order[a.status] - order[b.status]);
}

function formatBytes(bytes: number): string {
  if (bytes === 0) return "0 B";
  const k = 1024;
  const sizes = ["B", "KB", "MB", "GB"];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return `${parseFloat((bytes / Math.pow(k, i)).toFixed(1))} ${sizes[i]}`;
}

export function SnapshotComparisonView({ snapshots }: SnapshotComparisonViewProps) {
  const [open, setOpen] = useState(false);
  const [leftId, setLeftId] = useState<string>("");
  const [rightId, setRightId] = useState<string>("");

  const leftSnap = snapshots.find((s) => String(s.id) === leftId);
  const rightSnap = snapshots.find((s) => String(s.id) === rightId);

  const diffs = useMemo(() => {
    if (!leftSnap || !rightSnap) return [];
    return computeDiff(leftSnap, rightSnap);
  }, [leftSnap, rightSnap]);

  const summary = useMemo(() => {
    const added = diffs.filter((d) => d.status === "added").length;
    const removed = diffs.filter((d) => d.status === "removed").length;
    const changed = diffs.filter((d) => d.status === "changed").length;
    const unchanged = diffs.filter((d) => d.status === "unchanged").length;
    const totalRowDelta = leftSnap && rightSnap ? (rightSnap.total_rows ?? 0) - (leftSnap.total_rows ?? 0) : 0;
    const sizeDelta = leftSnap && rightSnap ? (rightSnap.file_size ?? 0) - (leftSnap.file_size ?? 0) : 0;
    return { added, removed, changed, unchanged, totalRowDelta, sizeDelta };
  }, [diffs, leftSnap, rightSnap]);

  if (snapshots.length < 2) return null;

  const snapLabel = (s: SnapshotRecord) =>
    `#${s.sequence} — ${s.created_at ? format(new Date(s.created_at), "MMM d, HH:mm") : "?"}`;

  return (
    <>
      <Button
        variant="outline"
        size="sm"
        onClick={() => setOpen(true)}
        className="h-7 text-xs"
      >
        <GitCompareArrows className="h-3.5 w-3.5 mr-1" />
        Compare
      </Button>

      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent className="max-w-2xl max-h-[85vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <GitCompareArrows className="h-5 w-5" />
              Compare Snapshots
            </DialogTitle>
            <DialogDescription>
              Select two snapshots to see table and row count differences
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-4 mt-2">
            {/* Selectors */}
            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-1.5">
                <label className="text-xs font-medium text-muted-foreground">Base (older)</label>
                <Select value={leftId} onValueChange={setLeftId}>
                  <SelectTrigger className="h-9 text-xs">
                    <SelectValue placeholder="Select snapshot…" />
                  </SelectTrigger>
                  <SelectContent>
                    {snapshots.map((s) => (
                      <SelectItem key={s.id} value={String(s.id)} disabled={String(s.id) === rightId}>
                        {snapLabel(s)}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-1.5">
                <label className="text-xs font-medium text-muted-foreground">Compare (newer)</label>
                <Select value={rightId} onValueChange={setRightId}>
                  <SelectTrigger className="h-9 text-xs">
                    <SelectValue placeholder="Select snapshot…" />
                  </SelectTrigger>
                  <SelectContent>
                    {snapshots.map((s) => (
                      <SelectItem key={s.id} value={String(s.id)} disabled={String(s.id) === leftId}>
                        {snapLabel(s)}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            </div>

            {/* Summary badges */}
            {leftSnap && rightSnap && (
              <>
                <div className="flex flex-wrap items-center gap-2">
                  {summary.added > 0 && (
                    <span className="inline-flex items-center gap-1 text-[11px] font-medium px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-600">
                      <Plus className="h-3 w-3" />
                      {summary.added} added
                    </span>
                  )}
                  {summary.removed > 0 && (
                    <span className="inline-flex items-center gap-1 text-[11px] font-medium px-2 py-0.5 rounded-full bg-destructive/10 text-destructive">
                      <Minus className="h-3 w-3" />
                      {summary.removed} removed
                    </span>
                  )}
                  {summary.changed > 0 && (
                    <span className="inline-flex items-center gap-1 text-[11px] font-medium px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-600">
                      <ArrowUp className="h-3 w-3" />
                      {summary.changed} changed
                    </span>
                  )}
                  {summary.unchanged > 0 && (
                    <span className="inline-flex items-center gap-1 text-[11px] font-medium px-2 py-0.5 rounded-full bg-muted text-muted-foreground">
                      <Equal className="h-3 w-3" />
                      {summary.unchanged} unchanged
                    </span>
                  )}
                  <span className="text-[11px] text-muted-foreground ml-auto">
                    Rows: {summary.totalRowDelta >= 0 ? "+" : ""}{summary.totalRowDelta.toLocaleString()}
                    {" · "}
                    Size: {summary.sizeDelta >= 0 ? "+" : ""}{formatBytes(Math.abs(summary.sizeDelta))}
                  </span>
                </div>

                {/* Diff table */}
                <div className="rounded-md border overflow-hidden max-h-[400px] overflow-y-auto">
                  <Table>
                    <TableHeader>
                      <TableRow className="bg-muted/40">
                        <TableHead className="text-[11px] py-1.5">Table</TableHead>
                        <TableHead className="text-[11px] py-1.5 w-[70px]">Status</TableHead>
                        <TableHead className="text-[11px] py-1.5 w-[80px] text-right">Base Rows</TableHead>
                        <TableHead className="text-[11px] py-1.5 w-[80px] text-right">New Rows</TableHead>
                        <TableHead className="text-[11px] py-1.5 w-[70px] text-right">Delta</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {diffs.map((d) => (
                        <TableRow
                          key={d.table}
                          className={cn(
                            "text-xs",
                            d.status === "added" && "bg-emerald-500/5",
                            d.status === "removed" && "bg-destructive/5",
                            d.status === "changed" && "bg-amber-500/5",
                          )}
                        >
                          <TableCell className="py-1.5 font-mono text-[11px]">
                            <div className="flex items-center gap-1.5">
                              <Database className="h-3 w-3 text-muted-foreground shrink-0" />
                              <span className="truncate max-w-[200px]">{d.table}</span>
                            </div>
                          </TableCell>
                          <TableCell className="py-1.5">
                            <span
                              className={cn(
                                "text-[10px] font-medium uppercase tracking-wider",
                                d.status === "added" && "text-emerald-600",
                                d.status === "removed" && "text-destructive",
                                d.status === "changed" && "text-amber-600",
                                d.status === "unchanged" && "text-muted-foreground",
                              )}
                            >
                              {d.status}
                            </span>
                          </TableCell>
                          <TableCell className="py-1.5 text-right font-mono text-[11px] text-muted-foreground">
                            {d.leftRows != null ? d.leftRows.toLocaleString() : "—"}
                          </TableCell>
                          <TableCell className="py-1.5 text-right font-mono text-[11px] text-muted-foreground">
                            {d.rightRows != null ? d.rightRows.toLocaleString() : "—"}
                          </TableCell>
                          <TableCell className="py-1.5 text-right font-mono text-[11px]">
                            {d.rowDelta != null ? (
                              <span
                                className={cn(
                                  "inline-flex items-center gap-0.5",
                                  d.rowDelta > 0 && "text-emerald-600",
                                  d.rowDelta < 0 && "text-destructive",
                                  d.rowDelta === 0 && "text-muted-foreground",
                                )}
                              >
                                {d.rowDelta > 0 ? <ArrowUp className="h-3 w-3" /> : d.rowDelta < 0 ? <ArrowDown className="h-3 w-3" /> : null}
                                {d.rowDelta > 0 ? "+" : ""}{d.rowDelta.toLocaleString()}
                              </span>
                            ) : (
                              <span className="text-muted-foreground">—</span>
                            )}
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </div>
              </>
            )}

            {(!leftSnap || !rightSnap) && (
              <div className="text-center py-8 text-muted-foreground text-xs border rounded-md border-dashed">
                Select two snapshots above to see the comparison
              </div>
            )}
          </div>
        </DialogContent>
      </Dialog>
    </>
  );
}
