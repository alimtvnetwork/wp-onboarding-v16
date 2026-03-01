package rules

import (
	"testing"

	"consistency-checker/internal/config"
	"consistency-checker/internal/engine"
)

// helper builds a CheckContext for GoRawError tests.
func rawErrorCtx(filePath string, lines []string) engine.CheckContext {
	return engine.CheckContext{
		FilePath: filePath,
		Language: "go",
		Lines:    lines,
		Spec:     config.RuleSpec{Severity: "error"},
	}
}

// ─── Violation tests ─────────────────────────────────────────────────────────

func TestGoRawError_DetectsSimpleErrorReturn(t *testing.T) {
	r := &GoRawError{}
	ctx := rawErrorCtx("internal/services/foo/Service.go", []string{
		`func (s *Service) DoWork() error {`,
	})

	findings := r.Check(ctx)
	if len(findings) != 1 {
		t.Errorf("expected 1 finding, got %d", len(findings))
	}
}

func TestGoRawError_DetectsTupleErrorReturn(t *testing.T) {
	r := &GoRawError{}
	ctx := rawErrorCtx("internal/database/Settings.go", []string{
		`func (db *DB) GetSetting(key string) (string, error) {`,
	})

	findings := r.Check(ctx)
	if len(findings) != 1 {
		t.Errorf("expected 1 finding, got %d", len(findings))
	}
}

func TestGoRawError_DetectsMultiTupleErrorReturn(t *testing.T) {
	r := &GoRawError{}
	ctx := rawErrorCtx("internal/services/version/Service.go", []string{
		`func (s *Service) Create(input Input) (int64, error) {`,
	})

	findings := r.Check(ctx)
	if len(findings) != 1 {
		t.Errorf("expected 1 finding, got %d", len(findings))
	}
}

func TestGoRawError_DetectsPointerTupleErrorReturn(t *testing.T) {
	r := &GoRawError{}
	ctx := rawErrorCtx("internal/services/site/Service.go", []string{
		`func (s *Service) GetById(id int64) (*Site, error) {`,
	})

	findings := r.Check(ctx)
	if len(findings) != 1 {
		t.Errorf("expected 1 finding, got %d", len(findings))
	}
}

// ─── Exempt: interface implementations ───────────────────────────────────────

func TestGoRawError_ExemptUnmarshalJSON(t *testing.T) {
	r := &GoRawError{}
	ctx := rawErrorCtx("internal/models/Model.go", []string{
		`func (v *Variant) UnmarshalJSON(data []byte) error {`,
	})

	findings := r.Check(ctx)
	if len(findings) != 0 {
		t.Errorf("expected 0 findings for UnmarshalJSON, got %d", len(findings))
	}
}

func TestGoRawError_ExemptMarshalJSON(t *testing.T) {
	r := &GoRawError{}
	ctx := rawErrorCtx("internal/models/Model.go", []string{
		`func (v Variant) MarshalJSON() ([]byte, error) {`,
	})

	findings := r.Check(ctx)
	if len(findings) != 0 {
		t.Errorf("expected 0 findings for MarshalJSON, got %d", len(findings))
	}
}

func TestGoRawError_ExemptCloseError(t *testing.T) {
	r := &GoRawError{}
	ctx := rawErrorCtx("internal/services/db/Pool.go", []string{
		`func (p *Pool) Close() error {`,
	})

	findings := r.Check(ctx)
	if len(findings) != 0 {
		t.Errorf("expected 0 findings for Close() error, got %d", len(findings))
	}
}

func TestGoRawError_ExemptStartAndShutdown(t *testing.T) {
	r := &GoRawError{}
	ctx := rawErrorCtx("internal/api/Router.go", []string{
		`func (s *Server) Start() error {`,
		`func (s *Server) Shutdown(ctx context.Context) error {`,
	})

	findings := r.Check(ctx)
	if len(findings) != 0 {
		t.Errorf("expected 0 findings for Start/Shutdown, got %d", len(findings))
	}
}

// ─── Exempt: filepath.Walk callbacks ─────────────────────────────────────────

func TestGoRawError_ExemptWalkCallback(t *testing.T) {
	r := &GoRawError{}
	ctx := rawErrorCtx("internal/services/publish/ServiceZip.go", []string{
		`func (s *Service) addEntry(path string, info os.FileInfo, err error) error {`,
	})

	// This doesn't match the walkCallbackPattern exactly (no func(...) signature),
	// but real Walk callbacks in the codebase are anonymous functions, not named methods.
	// Named methods returning error that accept os.FileInfo are Walk helpers.
	findings := r.Check(ctx)
	// This particular signature doesn't match the Walk callback regex pattern
	// (which looks for `func(.*os.FileInfo.*error) error`), so it would be flagged.
	// Walk callbacks in practice are closures, not named methods.
	if len(findings) != 1 {
		t.Errorf("expected 1 finding for named Walk-style method, got %d", len(findings))
	}
}

// ─── Exempt: Parse functions ─────────────────────────────────────────────────

func TestGoRawError_ExemptParseFunc(t *testing.T) {
	r := &GoRawError{}
	ctx := rawErrorCtx("internal/models/Status.go", []string{
		`func Parse(s string) (Variant, error) {`,
	})

	findings := r.Check(ctx)
	if len(findings) != 0 {
		t.Errorf("expected 0 findings for Parse(), got %d", len(findings))
	}
}

// ─── Exempt: packages ────────────────────────────────────────────────────────

func TestGoRawError_ExemptApperrorPackage(t *testing.T) {
	r := &GoRawError{}
	ctx := rawErrorCtx("pkg/apperror/ErrorJson.go", []string{
		`func (e *AppError) UnmarshalJSON(data []byte) error {`,
		`func doSomething() error {`,
	})

	findings := r.Check(ctx)
	if len(findings) != 0 {
		t.Errorf("expected 0 findings for apperror package, got %d", len(findings))
	}
}

func TestGoRawError_ExemptPathutilPackage(t *testing.T) {
	r := &GoRawError{}
	ctx := rawErrorCtx("pkg/pathutil/Pathutil.go", []string{
		`func ToAbsolute(path string) (string, error) {`,
	})

	findings := r.Check(ctx)
	if len(findings) != 0 {
		t.Errorf("expected 0 findings for pathutil package, got %d", len(findings))
	}
}

func TestGoRawError_ExemptDbopsPackage(t *testing.T) {
	r := &GoRawError{}
	ctx := rawErrorCtx("internal/database/dbops/Dbops.go", []string{
		`func ExecInsert(db interface{ Exec(string, ...any) (sql.Result, error) }, ctx Context, query string, args ...any) (*Result, error) {`,
	})

	findings := r.Check(ctx)
	if len(findings) != 0 {
		t.Errorf("expected 0 findings for dbops package, got %d", len(findings))
	}
}

func TestGoRawError_ExemptEnumsPackage(t *testing.T) {
	r := &GoRawError{}
	ctx := rawErrorCtx("internal/enums/statustype/Variant.go", []string{
		`func (v *Variant) UnmarshalJSON(data []byte) error {`,
		`func Parse(s string) (Variant, error) {`,
	})

	findings := r.Check(ctx)
	if len(findings) != 0 {
		t.Errorf("expected 0 findings for enums package, got %d", len(findings))
	}
}

func TestGoRawError_ExemptCmdPackage(t *testing.T) {
	r := &GoRawError{}
	ctx := rawErrorCtx("cmd/server/Main.go", []string{
		`func run() error {`,
	})

	findings := r.Check(ctx)
	if len(findings) != 0 {
		t.Errorf("expected 0 findings for cmd package, got %d", len(findings))
	}
}

// ─── Non-violations ──────────────────────────────────────────────────────────

func TestGoRawError_IgnoresApperrorReturn(t *testing.T) {
	r := &GoRawError{}
	ctx := rawErrorCtx("internal/services/foo/Service.go", []string{
		`func (s *Service) DoWork() *apperror.AppError {`,
		`func (s *Service) Get(id int64) (*Thing, *apperror.AppError) {`,
		`func (s *Service) List() apperror.Result[[]Thing] {`,
	})

	findings := r.Check(ctx)
	if len(findings) != 0 {
		t.Errorf("expected 0 findings for *apperror.AppError returns, got %d", len(findings))
	}
}

func TestGoRawError_IgnoresNoReturnFunc(t *testing.T) {
	r := &GoRawError{}
	ctx := rawErrorCtx("internal/services/foo/Service.go", []string{
		`func (s *Service) Close() {`,
		`func init() {`,
	})

	findings := r.Check(ctx)
	if len(findings) != 0 {
		t.Errorf("expected 0 findings for void functions, got %d", len(findings))
	}
}

func TestGoRawError_IgnoresNonFuncLines(t *testing.T) {
	r := &GoRawError{}
	ctx := rawErrorCtx("internal/services/foo/Service.go", []string{
		`	if err != nil {`,
		`	return nil, apperror.Wrap(err, apperror.ErrInternal, "failed")`,
		`	var err error`,
		`// This function returns error`,
	})

	findings := r.Check(ctx)
	if len(findings) != 0 {
		t.Errorf("expected 0 findings for non-func lines, got %d", len(findings))
	}
}

func TestGoRawError_MultipleViolationsInFile(t *testing.T) {
	r := &GoRawError{}
	ctx := rawErrorCtx("internal/services/foo/Service.go", []string{
		`func (s *Service) Create() error {`,
		`	return nil`,
		`}`,
		``,
		`func (s *Service) Get(id int64) (*Thing, error) {`,
		`	return nil, nil`,
		`}`,
		``,
		`func (s *Service) Delete(id int64) *apperror.AppError {`,
	})

	findings := r.Check(ctx)
	if len(findings) != 2 {
		t.Errorf("expected 2 findings, got %d", len(findings))
		for _, f := range findings {
			t.Logf("  line %d: %s", f.Line, f.Message)
		}
	}
}
