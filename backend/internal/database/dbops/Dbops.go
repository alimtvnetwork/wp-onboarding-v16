// Package dbops provides shared database operation utilities with comprehensive logging,
// stack trace capture, and affected rows tracking. This is the single source of truth
// for all database operations requiring detailed traceability.
package dbops

import (
	"database/sql"
	"strings"
)

// ExecInsert executes an INSERT operation and returns detailed result with logging
func ExecInsert(db interface{ Exec(string, ...any) (sql.Result, error) }, ctx Context, query string, args ...any) (*Result, error) {
	ctx.Operation = "INSERT"

	result, err := db.Exec(query, args...)
	if err != nil {
		logError(ctx, err)
		return nil, err
	}

	rows, _ := result.RowsAffected()
	id, _ := result.LastInsertId()

	isCreated := rows > 0
	isExists := rows == 0

	res := &Result{
		AffectedRows: rows,
		LastInsertId: id,
		Created:      isCreated,
		Exists:       isExists,
	}

	logSuccess(ctx, res)
	return res, nil
}

// ExecUpdate executes an UPDATE operation and returns detailed result with logging
func ExecUpdate(db interface{ Exec(string, ...any) (sql.Result, error) }, ctx Context, query string, args ...any) (*Result, error) {
	ctx.Operation = "UPDATE"

	result, err := db.Exec(query, args...)
	if err != nil {
		logError(ctx, err)
		return nil, err
	}

	rows, _ := result.RowsAffected()

	res := &Result{
		AffectedRows: rows,
	}

	logSuccess(ctx, res)
	return res, nil
}

// ExecDelete executes a DELETE operation and returns detailed result with logging
func ExecDelete(db interface{ Exec(string, ...any) (sql.Result, error) }, ctx Context, query string, args ...any) (*Result, error) {
	ctx.Operation = "DELETE"

	result, err := db.Exec(query, args...)
	if err != nil {
		logError(ctx, err)
		return nil, err
	}

	rows, _ := result.RowsAffected()

	res := &Result{
		AffectedRows: rows,
	}

	logSuccess(ctx, res)
	return res, nil
}

// FindOrCreateResult holds the outcome of a FindOrCreate operation.
type FindOrCreateResult struct {
	Id      int64
	Created bool
}

// FindOrCreate attempts to find an existing record by key, or creates it if not found
func FindOrCreate(
	db interface {
		QueryRow(string, ...any) *sql.Row
		Exec(string, ...any) (sql.Result, error)
	},
	ctx Context,
	selectQuery string,
	selectArgs []any,
	insertQuery string,
	insertArgs []any,
) (*FindOrCreateResult, error) {
	var id int64
	err := db.QueryRow(selectQuery, selectArgs...).Scan(&id)
	isFound := err == nil

	if isFound {
		hasLogger := ctx.Logger != nil

		if hasLogger {
			fields := mergeFields(ctx.Fields, OperationFields{
				Table:     ctx.Table,
				Operation: "FIND",
				Id:        id,
				Exists:    true,
			})
			ctx.Logger.Debug("Record found (exists)", fields.toKeyvals()...)
		}

		return &FindOrCreateResult{Id: id, Created: false}, nil
	}

	isUnexpectedError := err != sql.ErrNoRows

	if isUnexpectedError {
		logError(ctx, err)
		return nil, err
	}

	ctx.Operation = "INSERT"
	result, err := db.Exec(insertQuery, insertArgs...)
	if err != nil {
		logError(ctx, err)
		return nil, err
	}

	rows, rowsErr := result.RowsAffected()
	if rowsErr != nil {
		logError(ctx, rowsErr)
	}

	id, idErr := result.LastInsertId()
	if idErr != nil {
		logError(ctx, idErr)
	}

	isRaceCondition := rows == 0

	if isRaceCondition {
		err := db.QueryRow(selectQuery, selectArgs...).Scan(&id)
		if err != nil {
			logError(ctx, err)
			return nil, err
		}

		hasLogger := ctx.Logger != nil

		if hasLogger {
			fields := mergeFields(ctx.Fields, OperationFields{
				Table:     ctx.Table,
				Operation: "FIND_AFTER_RACE",
				Id:        id,
				Exists:    true,
			})
			ctx.Logger.Debug("Record found after race condition", fields.toKeyvals()...)
		}

		return &FindOrCreateResult{Id: id, Created: false}, nil
	}

	res := &Result{
		AffectedRows: rows,
		LastInsertId: id,
		Created:      true,
	}
	logSuccess(ctx, res)
	return &FindOrCreateResult{Id: id, Created: true}, nil
}

// CreateMapping creates a many-to-many relationship record with proper logging
func CreateMapping(
	db interface{ Exec(string, ...any) (sql.Result, error) },
	ctx Context,
	query string,
	args ...any,
) (bool, error) {
	ctx.Operation = "INSERT_MAPPING"

	result, err := db.Exec(query, args...)
	if err != nil {
		errStr := err.Error()
		isConstraintViolation := strings.Contains(errStr, "UNIQUE") ||
			strings.Contains(errStr, "constraint") ||
			strings.Contains(errStr, "duplicate")

		if isConstraintViolation {
			hasLogger := ctx.Logger != nil

			if hasLogger {
				fields := mergeFields(ctx.Fields, OperationFields{
					Table:     ctx.Table,
					Operation: "INSERT_MAPPING",
					Exists:    true,
					Note:      "Mapping already exists (constraint)",
				})
				ctx.Logger.Debug("Mapping exists", fields.toKeyvals()...)
			}

			return false, nil
		}

		logError(ctx, err)
		return false, err
	}

	rows, _ := result.RowsAffected()
	isCreated := rows > 0

	if isCreated {
		hasLogger := ctx.Logger != nil

		if hasLogger {
			id, _ := result.LastInsertId()
			fields := mergeFields(ctx.Fields, OperationFields{
				Table:        ctx.Table,
				Operation:    "INSERT_MAPPING",
				AffectedRows: rows,
				Id:           id,
				Created:      true,
			})
			ctx.Logger.Info("Mapping CREATED", fields.toKeyvals()...)
		}
	} else {
		hasLogger := ctx.Logger != nil

		if hasLogger {
			fields := mergeFields(ctx.Fields, OperationFields{
				Table:     ctx.Table,
				Operation: "INSERT_MAPPING",
				Exists:    true,
				Note:      "Mapping already exists (INSERT OR IGNORE)",
			})
			ctx.Logger.Debug("Mapping EXISTS", fields.toKeyvals()...)
		}
	}

	return isCreated, nil
}
