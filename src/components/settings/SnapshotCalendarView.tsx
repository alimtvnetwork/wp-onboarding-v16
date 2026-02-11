import { useMemo, useState } from "react";
import { SnapshotRecord, SnapshotCronJob } from "@/lib/api/types";
import {
  startOfMonth,
  endOfMonth,
  eachDayOfInterval,
  format,
  parseISO,
  isSameDay,
  addMonths,
  subMonths,
  isToday,
  isFuture,
  startOfDay,
} from "date-fns";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import {
  ChevronLeft,
  ChevronRight,
  CalendarDays,
  CheckCircle2,
  XCircle,
  Clock,
  Zap,
} from "lucide-react";
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from "@/components/ui/tooltip";

interface Props {
  snapshots: SnapshotRecord[];
  cronJobs?: SnapshotCronJob[];
}

interface DayInfo {
  date: Date;
  snapshots: SnapshotRecord[];
  hasScheduled: boolean;
}

const WEEKDAYS = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];

// Estimate upcoming scheduled runs from cron jobs
function getScheduledDates(cronJobs: SnapshotCronJob[], monthStart: Date, monthEnd: Date): Date[] {
  const dates: Date[] = [];
  cronJobs?.forEach((job) => {
    if (job.status !== "active" || !job.nextRunAt) return;
    const nextRun = parseISO(job.nextRunAt);
    if (nextRun >= monthStart && nextRun <= monthEnd) {
      dates.push(startOfDay(nextRun));
    }
  });
  return dates;
}

export function SnapshotCalendarView({ snapshots, cronJobs }: Props) {
  const [currentMonth, setCurrentMonth] = useState(new Date());

  const monthStart = startOfMonth(currentMonth);
  const monthEnd = endOfMonth(currentMonth);
  const days = eachDayOfInterval({ start: monthStart, end: monthEnd });

  const scheduledDates = useMemo(
    () => getScheduledDates(cronJobs ?? [], monthStart, monthEnd),
    [cronJobs, monthStart, monthEnd]
  );

  const dayInfoMap = useMemo(() => {
    const map = new Map<string, DayInfo>();
    days.forEach((day) => {
      const key = format(day, "yyyy-MM-dd");
      const daySnaps = (snapshots ?? []).filter((s) =>
        s.created_at ? isSameDay(parseISO(s.created_at), day) : false
      );
      const hasScheduled = scheduledDates.some((sd) => isSameDay(sd, day));
      map.set(key, { date: day, snapshots: daySnaps, hasScheduled });
    });
    return map;
  }, [days, snapshots, scheduledDates]);

  // Pad start of month to align with weekday grid
  const startPadding = monthStart.getDay();

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2 text-sm font-medium">
          <CalendarDays className="h-4 w-4" />
          Schedule Calendar
        </div>
        <div className="flex items-center gap-1">
          <Button
            variant="ghost"
            size="sm"
            className="h-7 w-7 p-0"
            onClick={() => setCurrentMonth((m) => subMonths(m, 1))}
          >
            <ChevronLeft className="h-4 w-4" />
          </Button>
          <span className="text-xs font-medium w-[100px] text-center">
            {format(currentMonth, "MMMM yyyy")}
          </span>
          <Button
            variant="ghost"
            size="sm"
            className="h-7 w-7 p-0"
            onClick={() => setCurrentMonth((m) => addMonths(m, 1))}
          >
            <ChevronRight className="h-4 w-4" />
          </Button>
        </div>
      </div>

      <p className="text-xs text-muted-foreground">
        Past snapshot runs and upcoming scheduled backups
      </p>

      {/* Legend */}
      <div className="flex items-center gap-4 text-[10px] text-muted-foreground">
        <span className="flex items-center gap-1">
          <span className="h-2.5 w-2.5 rounded-full bg-emerald-500" />
          Completed
        </span>
        <span className="flex items-center gap-1">
          <span className="h-2.5 w-2.5 rounded-full bg-destructive" />
          Failed
        </span>
        <span className="flex items-center gap-1">
          <span className="h-2.5 w-2.5 rounded-full border-2 border-primary border-dashed" />
          Scheduled
        </span>
      </div>

      {/* Calendar grid */}
      <div className="rounded-lg border overflow-hidden">
        {/* Header */}
        <div className="grid grid-cols-7 bg-muted/40">
          {WEEKDAYS.map((wd) => (
            <div key={wd} className="text-center text-[10px] font-medium text-muted-foreground py-1.5">
              {wd}
            </div>
          ))}
        </div>

        {/* Days */}
        <div className="grid grid-cols-7">
          {/* Empty padding cells */}
          {Array.from({ length: startPadding }).map((_, i) => (
            <div key={`pad-${i}`} className="aspect-square border-t border-r last:border-r-0 bg-muted/10" />
          ))}

          {days.map((day) => {
            const key = format(day, "yyyy-MM-dd");
            const info = dayInfoMap.get(key);
            const snaps = info?.snapshots ?? [];
            const hasScheduled = info?.hasScheduled ?? false;
            const hasCompleted = snaps.some((s) => s.status === "completed" || s.status === "success");
            const hasFailed = snaps.some((s) => s.status === "failed" || s.status === "error");
            const today = isToday(day);
            const future = isFuture(day);

            return (
              <TooltipProvider key={key} delayDuration={200}>
                <Tooltip>
                  <TooltipTrigger asChild>
                    <div
                      className={cn(
                        "aspect-square border-t border-r flex flex-col items-center justify-center gap-0.5 transition-colors relative",
                        today && "bg-primary/5 ring-1 ring-inset ring-primary/30",
                        future && "opacity-60",
                        !today && !future && snaps.length > 0 && "bg-accent/20"
                      )}
                    >
                      <span
                        className={cn(
                          "text-[11px] font-medium",
                          today && "text-primary font-bold",
                          future && "text-muted-foreground"
                        )}
                      >
                        {format(day, "d")}
                      </span>

                      {/* Indicators */}
                      <div className="flex items-center gap-0.5">
                        {hasCompleted && (
                          <span className="h-1.5 w-1.5 rounded-full bg-emerald-500" />
                        )}
                        {hasFailed && (
                          <span className="h-1.5 w-1.5 rounded-full bg-destructive" />
                        )}
                        {hasScheduled && !hasCompleted && !hasFailed && (
                          <span className="h-1.5 w-1.5 rounded-full border border-primary border-dashed" />
                        )}
                        {snaps.length > 1 && (
                          <span className="text-[8px] text-muted-foreground font-mono">
                            ×{snaps.length}
                          </span>
                        )}
                      </div>
                    </div>
                  </TooltipTrigger>
                  {(snaps.length > 0 || hasScheduled) && (
                    <TooltipContent side="top" className="max-w-[200px] text-xs space-y-1">
                      <p className="font-medium">{format(day, "EEEE, MMM d")}</p>
                      {snaps.map((s) => (
                        <div key={s.id} className="flex items-center gap-1.5">
                          {s.status === "completed" || s.status === "success" ? (
                            <CheckCircle2 className="h-3 w-3 text-emerald-500 shrink-0" />
                          ) : (
                            <XCircle className="h-3 w-3 text-destructive shrink-0" />
                          )}
                          <span className="truncate">
                            #{s.sequence} · {s.total_rows?.toLocaleString() ?? "?"} rows
                          </span>
                        </div>
                      ))}
                      {hasScheduled && (
                        <div className="flex items-center gap-1.5 text-muted-foreground">
                          <Clock className="h-3 w-3 shrink-0" />
                          <span>Scheduled run</span>
                        </div>
                      )}
                    </TooltipContent>
                  )}
                </Tooltip>
              </TooltipProvider>
            );
          })}
        </div>
      </div>
    </div>
  );
}
