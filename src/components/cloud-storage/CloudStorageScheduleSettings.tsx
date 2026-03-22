import { useState } from "react";
import { Label } from "@/components/ui/label";
import { Button } from "@/components/ui/button";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import { Loader2, Clock, Save } from "lucide-react";
import { Separator } from "@/components/ui/separator";
import { Slider } from "@/components/ui/slider";
import type {
  CloudStorageSettings,
  BackupStrategyType,
  BackupScheduleType,
} from "@/types/cloudStorage";
import {
  BACKUP_STRATEGY_LABELS,
  BACKUP_SCHEDULE_LABELS,
  DAY_OF_WEEK_LABELS,
} from "@/types/cloudStorage";

interface CloudStorageScheduleSettingsProps {
  settings: CloudStorageSettings;
  onSave: (settings: Partial<CloudStorageSettings>) => Promise<void>;
  isSaving: boolean;
}

const FULL_SCHEDULES: BackupScheduleType[] = ["daily", "weekly", "biweekly", "monthly", "manual"];
const INCREMENTAL_SCHEDULES: BackupScheduleType[] = ["hourly", "daily", "manual"];
const TIME_OPTIONS = Array.from({ length: 24 }, (_, i) =>
  `${String(i).padStart(2, "0")}:00`
);

export function CloudStorageScheduleSettings({
  settings,
  onSave,
  isSaving,
}: CloudStorageScheduleSettingsProps) {
  const [backupType, setBackupType] = useState<BackupStrategyType>(
    settings.backupType || "full_only"
  );
  const [fullSchedule, setFullSchedule] = useState<BackupScheduleType>(
    settings.fullBackupSchedule || "weekly"
  );
  const [incrSchedule, setIncrSchedule] = useState<BackupScheduleType>(
    settings.incrementalBackupSchedule || "daily"
  );
  const [fullDay, setFullDay] = useState(settings.fullBackupDayOfWeek ?? 0);
  const [fullTime, setFullTime] = useState(settings.fullBackupTimeUtc || "02:00");
  const [incrTime, setIncrTime] = useState(settings.incrementalBackupTimeUtc || "02:00");
  const [fullRetention, setFullRetention] = useState(settings.retentionCount || 4);
  const [incrRetention, setIncrRetention] = useState(6);

  const isIncremental = backupType === "full_and_incremental";
  const showDayPicker = fullSchedule === "weekly" || fullSchedule === "biweekly";

  const handleSave = async () => {
    await onSave({
      backupType: backupType,
      fullBackupSchedule: fullSchedule,
      incrementalBackupSchedule: isIncremental ? incrSchedule : "manual",
      fullBackupDayOfWeek: fullDay,
      fullBackupTimeUtc: fullTime,
      incrementalBackupTimeUtc: incrTime,
      retentionCount: fullRetention,
    });
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-2">
        <Clock className="h-4 w-4 text-muted-foreground" />
        <h3 className="text-sm font-semibold">Backup Schedule</h3>
      </div>

      {/* Strategy */}
      <div className="space-y-3">
        <Label className="text-xs text-muted-foreground uppercase tracking-wide">Strategy</Label>
        <RadioGroup
          value={backupType}
          onValueChange={(v) => setBackupType(v as BackupStrategyType)}
          className="space-y-2"
        >
          {(Object.entries(BACKUP_STRATEGY_LABELS) as [BackupStrategyType, string][]).map(([value, label]) => (
            <div key={value} className="flex items-center gap-2">
              <RadioGroupItem value={value} id={`strategy-${value}`} />
              <Label htmlFor={`strategy-${value}`} className="cursor-pointer text-sm">
                {label}
              </Label>
            </div>
          ))}
        </RadioGroup>
      </div>

      <Separator />

      {/* Full backup schedule */}
      <div className="space-y-3">
        <Label className="text-xs text-muted-foreground uppercase tracking-wide">Full Backup</Label>

        <div className="grid grid-cols-2 gap-3">
          <div className="space-y-1.5">
            <Label className="text-xs">Frequency</Label>
            <Select value={fullSchedule} onValueChange={(v) => setFullSchedule(v as BackupScheduleType)}>
              <SelectTrigger className="text-sm">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {FULL_SCHEDULES.map((s) => (
                  <SelectItem key={s} value={s}>{BACKUP_SCHEDULE_LABELS[s]}</SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          {showDayPicker && (
            <div className="space-y-1.5">
              <Label className="text-xs">Day</Label>
              <Select value={String(fullDay)} onValueChange={(v) => setFullDay(Number(v))}>
                <SelectTrigger className="text-sm">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {DAY_OF_WEEK_LABELS.map((day, i) => (
                    <SelectItem key={i} value={String(i)}>{day}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          )}

          <div className="space-y-1.5">
            <Label className="text-xs">Time (UTC)</Label>
            <Select value={fullTime} onValueChange={setFullTime}>
              <SelectTrigger className="text-sm">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {TIME_OPTIONS.map((t) => (
                  <SelectItem key={t} value={t}>{t}</SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
        </div>

        {/* Retention slider */}
        <div className="space-y-2">
          <div className="flex items-center justify-between">
            <Label className="text-xs">Full backups to keep</Label>
            <span className="text-xs text-muted-foreground">{fullRetention}</span>
          </div>
          <Slider
            value={[fullRetention]}
            onValueChange={([v]) => setFullRetention(v)}
            min={1}
            max={52}
            step={1}
          />
        </div>
      </div>

      {/* Incremental schedule (conditional) */}
      {isIncremental && (
        <>
          <Separator />
          <div className="space-y-3">
            <Label className="text-xs text-muted-foreground uppercase tracking-wide">
              Incremental Backup
            </Label>

            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-1.5">
                <Label className="text-xs">Frequency</Label>
                <Select value={incrSchedule} onValueChange={(v) => setIncrSchedule(v as BackupScheduleType)}>
                  <SelectTrigger className="text-sm">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {INCREMENTAL_SCHEDULES.map((s) => (
                      <SelectItem key={s} value={s}>{BACKUP_SCHEDULE_LABELS[s]}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>

              <div className="space-y-1.5">
                <Label className="text-xs">Time (UTC)</Label>
                <Select value={incrTime} onValueChange={setIncrTime}>
                  <SelectTrigger className="text-sm">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {TIME_OPTIONS.map((t) => (
                      <SelectItem key={t} value={t}>{t}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            </div>

            <div className="space-y-2">
              <div className="flex items-center justify-between">
                <Label className="text-xs">Incrementals per full cycle</Label>
                <span className="text-xs text-muted-foreground">{incrRetention}</span>
              </div>
              <Slider
                value={[incrRetention]}
                onValueChange={([v]) => setIncrRetention(v)}
                min={1}
                max={30}
                step={1}
              />
            </div>

            <p className="text-xs text-muted-foreground">
              Incremental backups use timestamp-based detection (post_modified_gmt) to capture only changed rows since the last backup.
              Old incremental branches are auto-deleted when their parent full backup is rotated out.
            </p>
          </div>
        </>
      )}

      <Separator />

      {/* WP-Cron note */}
      <div className="rounded-md bg-muted/50 p-3 text-xs text-muted-foreground space-y-1">
        <p className="font-medium text-foreground">Scheduling note</p>
        <p>
          Schedules run via WordPress Cron, which triggers on page visits.
          For reliable timing on low-traffic sites, set up a real system cron:
        </p>
        <code className="block bg-background rounded px-2 py-1 mt-1 text-[11px] font-mono">
          */15 * * * * curl -s https://your-site.com/wp-cron.php &gt;/dev/null 2&gt;&amp;1
        </code>
      </div>

      {/* Save */}
      <Button onClick={handleSave} disabled={isSaving} className="w-full">
        {isSaving
          ? <Loader2 className="h-4 w-4 mr-2 animate-spin" />
          : <Save className="h-4 w-4 mr-2" />}
        Save Schedule
      </Button>
    </div>
  );
}