package rules

import (
	"testing"

	"consistency-checker/internal/config"
	"consistency-checker/internal/engine"
)

func TestMdHeading_SingleH1(t *testing.T) {
	lines := []string{"# My Document", "", "## Section One", "Some text"}
	findings := (&MdHeading{}).Check(mdContext(lines))

	if len(findings) != 0 {
		t.Errorf("expected 0 findings for single H1, got %d", len(findings))
	}
}

func TestMdHeading_NoH1(t *testing.T) {
	lines := []string{"## Section One", "Some text", "## Section Two"}
	findings := (&MdHeading{}).Check(mdContext(lines))

	if len(findings) != 0 {
		t.Errorf("expected 0 findings for no H1, got %d", len(findings))
	}
}

func TestMdHeading_MultipleH1(t *testing.T) {
	lines := []string{"# Title One", "", "## Section", "", "# Title Two", "", "# Title Three"}
	findings := (&MdHeading{}).Check(mdContext(lines))

	if len(findings) != 2 {
		t.Fatalf("expected 2 findings for 3 H1s, got %d", len(findings))
	}
	if findings[0].Line != 5 {
		t.Errorf("expected first extra H1 at line 5, got %d", findings[0].Line)
	}
	if findings[1].Line != 7 {
		t.Errorf("expected second extra H1 at line 7, got %d", findings[1].Line)
	}
}

func TestMdHeading_H2NotCountedAsH1(t *testing.T) {
	lines := []string{"# Title", "## Sub", "### Deep", "## Another"}
	findings := (&MdHeading{}).Check(mdContext(lines))

	if len(findings) != 0 {
		t.Errorf("expected 0 findings, got %d", len(findings))
	}
}

func TestMdHeading_IndentedH1(t *testing.T) {
	lines := []string{"# Title", "  # Indented Title"}
	findings := (&MdHeading{}).Check(mdContext(lines))

	if len(findings) != 1 {
		t.Fatalf("expected 1 finding for indented duplicate H1, got %d", len(findings))
	}
}

func mdContext(lines []string) engine.CheckContext {
	return engine.CheckContext{
		FilePath: "docs/README.md",
		Language: "md",
		Lines:    lines,
		Spec: config.RuleSpec{
			ID:        "md-heading",
			Severity:  "info",
			Params:    map[string]any{},
			Reference: "spec/03-rules.md#md-heading",
		},
	}
}
