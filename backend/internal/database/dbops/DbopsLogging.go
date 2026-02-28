package dbops

import "fmt"

// logSuccess logs a successful database operation
func logSuccess(ctx Context, res *Result) {
	isLoggerMissing := ctx.Logger == nil

	if isLoggerMissing {
		return
	}

	file, line := getCallerInfo(3)

	fields := mergeFields(ctx.Fields, OperationFields{
		Table:        ctx.Table,
		Operation:    ctx.Operation,
		AffectedRows: res.AffectedRows,
		Caller:       fmt.Sprintf("%s:%d", file, line),
	})

	hasLastInsertID := res.LastInsertID > 0

	if hasLastInsertID {
		fields.LastInsertID = res.LastInsertID
	}

	if res.IsCreated {
		fields.IsCreated = true
	}

	if res.IsExists {
		fields.IsExists = true
	}

	ctx.Logger.Debug(fmt.Sprintf("DB %s on %s", ctx.Operation, ctx.Table), fields.toKeyvals()...)
}

// logError logs a failed database operation with stack trace
func logError(ctx Context, err error) {
	isLoggerMissing := ctx.Logger == nil

	if isLoggerMissing {
		return
	}

	file, line := getCallerInfo(3)
	stack := captureStackTrace(3)

	fields := mergeFields(ctx.Fields, OperationFields{
		Table:      ctx.Table,
		Operation:  ctx.Operation,
		Error:      err.Error(),
		Caller:     fmt.Sprintf("%s:%d", file, line),
		StackTrace: stack,
	})

	ctx.Logger.Error(fmt.Sprintf("DB %s FAILED on %s", ctx.Operation, ctx.Table), fields.toKeyvals()...)
}
