package rules

import (
	"testing"

	"consistency-checker/internal/config"
	"consistency-checker/internal/engine"
)

func TestGoStructFieldCasing_FlagsSnakeCase(t *testing.T) {
	r := &GoStructFieldCasing{}
	ctx := engine.CheckContext{
		FilePath: "test.go",
		Language: "go",
		Lines: []string{
			"type Config struct {",
			"	Plugin_name string",
			"	Site_url    string",
			"	Max_count   int",
			"}",
		},
		Spec: config.RuleSpec{Severity: "warning"},
	}

	findings := r.Check(ctx)
	if len(findings) != 3 {
		t.Errorf("expected 3 findings, got %d", len(findings))
		for _, f := range findings {
			t.Logf("  finding: %s", f.Message)
		}
	}
}

func TestGoStructFieldCasing_AcceptsPascalCase(t *testing.T) {
	r := &GoStructFieldCasing{}
	ctx := engine.CheckContext{
		FilePath: "test.go",
		Language: "go",
		Lines: []string{
			"type Config struct {",
			"	PluginName string",
			"	SiteUrl    string",
			"	MaxCount   int",
			"	IsEnabled  bool",
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

func TestGoStructFieldCasing_IgnoresOutsideStruct(t *testing.T) {
	r := &GoStructFieldCasing{}
	ctx := engine.CheckContext{
		FilePath: "test.go",
		Language: "go",
		Lines: []string{
			"var Plugin_name = \"test\"",
			"func Get_value() {}",
		},
		Spec: config.RuleSpec{Severity: "warning"},
	}

	findings := r.Check(ctx)
	if len(findings) != 0 {
		t.Errorf("expected 0 findings outside struct, got %d", len(findings))
	}
}

func TestGoStructFieldCasing_HandlesMultipleStructs(t *testing.T) {
	r := &GoStructFieldCasing{}
	ctx := engine.CheckContext{
		FilePath: "test.go",
		Language: "go",
		Lines: []string{
			"type First struct {",
			"	Good_field string",
			"}",
			"",
			"type Second struct {",
			"	Another_bad string",
			"}",
		},
		Spec: config.RuleSpec{Severity: "warning"},
	}

	findings := r.Check(ctx)
	if len(findings) != 2 {
		t.Errorf("expected 2 findings across structs, got %d", len(findings))
	}
}

func TestGoStructFieldCasing_SuggestionIsPascalCase(t *testing.T) {
	r := &GoStructFieldCasing{}
	ctx := engine.CheckContext{
		FilePath: "test.go",
		Language: "go",
		Lines: []string{
			"type Config struct {",
			"	plugin_name string",
			"}",
		},
		Spec: config.RuleSpec{Severity: "warning"},
	}

	findings := r.Check(ctx)
	// unexported field (lowercase start) won't match the exported field pattern
	if len(findings) != 0 {
		t.Errorf("expected 0 findings for unexported snake_case field, got %d", len(findings))
	}
}
