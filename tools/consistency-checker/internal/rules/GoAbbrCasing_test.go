package rules

import (
	"testing"

	"consistency-checker/internal/config"
	"consistency-checker/internal/engine"
)

func TestGoAbbrCasing_FlagsAllCapsID(t *testing.T) {
	r := &GoAbbrCasing{}
	ctx := engine.CheckContext{
		FilePath: "test.go",
		Language: "go",
		Lines: []string{
			"type ScanResult struct {",
			"	PluginID int64",
			"	SiteID   int64",
			"	Name     string",
			"}",
		},
		Spec: config.RuleSpec{Severity: "warning"},
	}

	findings := r.Check(ctx)
	if len(findings) != 2 {
		t.Errorf("expected 2 findings, got %d", len(findings))
		for _, f := range findings {
			t.Logf("  finding: %s", f.Message)
		}
	}
}

func TestGoAbbrCasing_IgnoresPascalCase(t *testing.T) {
	r := &GoAbbrCasing{}
	ctx := engine.CheckContext{
		FilePath: "test.go",
		Language: "go",
		Lines: []string{
			"type ScanResult struct {",
			"	PluginId int64",
			"	SiteId   int64",
			"	Url      string",
			"}",
		},
		Spec: config.RuleSpec{Severity: "warning"},
	}

	findings := r.Check(ctx)
	if len(findings) != 0 {
		t.Errorf("expected 0 findings, got %d", len(findings))
		for _, f := range findings {
			t.Logf("  finding: %s", f.Message)
		}
	}
}

func TestGoAbbrCasing_FlagsFuncDecl(t *testing.T) {
	r := &GoAbbrCasing{}
	ctx := engine.CheckContext{
		FilePath: "test.go",
		Language: "go",
		Lines: []string{
			`func GetByID(ctx context.Context, id int64) error {`,
		},
		Spec: config.RuleSpec{Severity: "error"},
	}

	findings := r.Check(ctx)
	if len(findings) != 1 {
		t.Errorf("expected 1 finding, got %d", len(findings))
	}
}

func TestGoAbbrCasing_IgnoresStringLiterals(t *testing.T) {
	r := &GoAbbrCasing{}
	ctx := engine.CheckContext{
		FilePath: "test.go",
		Language: "go",
		Lines: []string{
			`	s.log.Debug("pluginID", pluginID)`,
		},
		Spec: config.RuleSpec{Severity: "warning"},
	}

	findings := r.Check(ctx)
	// The "pluginID" inside the string should be ignored,
	// but the bare pluginID variable reference is not a declaration line
	// so it shouldn't be checked at all.
	if len(findings) != 0 {
		t.Errorf("expected 0 findings for non-declaration line, got %d", len(findings))
	}
}

func TestGoAbbrCasing_FlagsURL(t *testing.T) {
	r := &GoAbbrCasing{}
	ctx := engine.CheckContext{
		FilePath: "test.go",
		Language: "go",
		Lines: []string{
			"	SiteURL string",
		},
		Spec: config.RuleSpec{Severity: "warning"},
	}

	findings := r.Check(ctx)
	if len(findings) != 1 {
		t.Errorf("expected 1 finding for SiteURL, got %d", len(findings))
	}
}

func TestGoAbbrCasing_FlagsTypeDecl(t *testing.T) {
	r := &GoAbbrCasing{}
	ctx := engine.CheckContext{
		FilePath: "test.go",
		Language: "go",
		Lines: []string{
			"type PluginID int64",
			"type SiteURL string",
		},
		Spec: config.RuleSpec{Severity: "warning"},
	}

	findings := r.Check(ctx)
	if len(findings) != 2 {
		t.Errorf("expected 2 findings, got %d", len(findings))
	}
}
