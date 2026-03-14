package publish

import (
	"fmt"
	"time"

	"wp-plugin-publish/pkg/apperror"
)

// calculateNextRun parses the schedule expression and returns the next run time
func (s *PublishScheduler) calculateNextRun(cfg ScheduleConfig) (time.Time, error) {
	loc := resolveTimezone(cfg.Timezone)
	now := time.Now().In(loc)

	switch {
	case len(cfg.CronExpr) > 6 && cfg.CronExpr[:6] == "daily:":
		return parseDailySchedule(cfg.CronExpr, now, loc)
	case len(cfg.CronExpr) > 7 && cfg.CronExpr[:7] == "weekly:":
		return parseWeeklySchedule(cfg.CronExpr, now, loc)
	case len(cfg.CronExpr) > 9 && cfg.CronExpr[:9] == "interval:":
		return parseIntervalSchedule(cfg.CronExpr, now)
	default:
		return time.Time{}, apperror.New(apperror.ErrValidation, "unknown schedule format").WithValue("cronExpr", cfg.CronExpr)
	}
}

// resolveTimezone loads the timezone or defaults to UTC.
func resolveTimezone(tz string) *time.Location {
	hasTimezone := tz != ""

	if hasTimezone {
		loc, err := time.LoadLocation(tz)
		if err == nil {
			return loc
		}
	}
	return time.UTC
}

// parseDailySchedule parses "daily:HH:MM" format.
func parseDailySchedule(expr string, now time.Time, loc *time.Location) (time.Time, error) {
	var hour, minute int
	_, err := fmt.Sscanf(expr, "daily:%d:%d", &hour, &minute)
	if err != nil {
		return time.Time{}, apperror.New(apperror.ErrValidation, "invalid daily schedule").WithValue("cronExpr", expr)
	}
	next := time.Date(now.Year(), now.Month(), now.Day(), hour, minute, 0, 0, loc)
	if next.Before(now) {
		next = next.Add(24 * time.Hour)
	}
	return next, nil
}

// parseWeeklySchedule parses "weekly:DAY:HH:MM" format.
func parseWeeklySchedule(expr string, now time.Time, loc *time.Location) (time.Time, error) {
	var dayName string
	var hour, minute int
	_, err := fmt.Sscanf(expr, "weekly:%3s:%d:%d", &dayName, &hour, &minute)
	if err != nil {
		return time.Time{}, apperror.New(apperror.ErrValidation, "invalid weekly schedule").WithValue("cronExpr", expr)
	}

	targetDay := parseDayOfWeek(dayName)
	daysUntil := (int(targetDay) - int(now.Weekday()) + 7) % 7
	isTargetToday := daysUntil == 0

	if isTargetToday {
		next := time.Date(now.Year(), now.Month(), now.Day(), hour, minute, 0, 0, loc)
		isFutureToday := !next.Before(now)

		if isFutureToday {
			return next, nil
		}
		daysUntil = 7
	}
	return time.Date(now.Year(), now.Month(), now.Day()+daysUntil, hour, minute, 0, 0, loc), nil
}

// parseIntervalSchedule parses "interval:MINUTES" format.
func parseIntervalSchedule(expr string, now time.Time) (time.Time, error) {
	var minutes int
	_, err := fmt.Sscanf(expr, "interval:%d", &minutes)
	isInvalidMinutes := minutes < 1

	if err != nil || isInvalidMinutes {
		return time.Time{}, apperror.New(apperror.ErrValidation, "invalid interval schedule").WithValue("cronExpr", expr)
	}
	return now.Add(time.Duration(minutes) * time.Minute), nil
}

func parseDayOfWeek(day string) time.Weekday {
	switch day {
	case "Sun":
		return time.Sunday
	case "Mon":
		return time.Monday
	case "Tue":
		return time.Tuesday
	case "Wed":
		return time.Wednesday
	case "Thu":
		return time.Thursday
	case "Fri":
		return time.Friday
	case "Sat":
		return time.Saturday
	default:
		return time.Monday
	}
}
