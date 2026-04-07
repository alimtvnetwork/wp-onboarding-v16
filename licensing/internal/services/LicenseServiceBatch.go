package services

import (
	"fmt"
	"strings"
	"time"

	"riseup-licensing/pkg/apperror"
)

// BatchRevoke revokes all licenses matching the given IDs. Returns affected count.
func (s *LicenseService) BatchRevoke(ids []int64) apperror.Result[int] {
	isEmpty := len(ids) == 0

	if isEmpty {
		return apperror.Ok(0)
	}

	placeholders, args := buildInClause(ids)
	args = append(args, "revoked")

	query := fmt.Sprintf(
		"UPDATE licenses SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id IN (%s)",
		placeholders,
	)

	// SQLite needs status arg first, then IDs — reorder
	reorderedArgs := make([]any, 0, len(args))
	reorderedArgs = append(reorderedArgs, "revoked")

	for _, id := range ids {
		reorderedArgs = append(reorderedArgs, id)
	}

	result, execErr := s.db.Exec(query, reorderedArgs...)
	if execErr != nil {

		return apperror.FailWrap[int](execErr, apperror.ErrDatabaseUpdate, "batch revoke")
	}

	affected, _ := result.RowsAffected()

	return apperror.Ok(int(affected))
}

// BatchExtend extends the expiry of all licenses matching the given IDs by the specified number of days.
// Returns affected count.
func (s *LicenseService) BatchExtend(ids []int64, days int) apperror.Result[int] {
	isEmpty := len(ids) == 0

	if isEmpty {
		return apperror.Ok(0)
	}

	isInvalidDuration := days <= 0

	if isInvalidDuration {
		return apperror.Fail[int](apperror.ErrValidation, "days must be positive")
	}

	// For each license, extend expiry from current expires_at (or from now if null)
	extension := time.Duration(days) * 24 * time.Hour
	newExpiry := time.Now().Add(extension)
	placeholders, _ := buildInClause(ids)

	query := fmt.Sprintf(
		"UPDATE licenses SET expires_at = ?, status = 'active', updated_at = CURRENT_TIMESTAMP WHERE id IN (%s)",
		placeholders,
	)

	args := make([]any, 0, len(ids)+1)
	args = append(args, newExpiry)

	for _, id := range ids {
		args = append(args, id)
	}

	result, execErr := s.db.Exec(query, args...)
	if execErr != nil {

		return apperror.FailWrap[int](execErr, apperror.ErrDatabaseUpdate, "batch extend")
	}

	affected, _ := result.RowsAffected()

	return apperror.Ok(int(affected))
}

// buildInClause creates SQL placeholders and args for an IN clause.
func buildInClause(ids []int64) (string, []any) {
	placeholders := make([]string, len(ids))
	args := make([]any, len(ids))

	for i, id := range ids {
		placeholders[i] = "?"
		args[i] = id
	}

	return strings.Join(placeholders, ", "), args
}
