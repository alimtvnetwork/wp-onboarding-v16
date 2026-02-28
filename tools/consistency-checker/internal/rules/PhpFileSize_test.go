package rules

import (
	"strings"
	"testing"

	"consistency-checker/internal/config"
	"consistency-checker/internal/engine"
)

func TestPhpFileSize_UnderLimit(t *testing.T) {
	ctx := phpContext(100, 500)
	findings := (&PhpFileSize{}).Check(ctx)

	if len(findings) != 0 {
		t.Errorf("expected 0 findings, got %d", len(findings))
	}
}

func TestPhpFileSize_AtLimit(t *testing.T) {
	ctx := phpContext(500, 500)
	findings := (&PhpFileSize{}).Check(ctx)

	if len(findings) != 0 {
		t.Errorf("expected 0 findings at exact limit, got %d", len(findings))
	}
}

func TestPhpFileSize_OverLimit(t *testing.T) {
	ctx := phpContext(501, 500)
	findings := (&PhpFileSize{}).Check(ctx)

	if len(findings) != 1 {
		t.Fatalf("expected 1 finding, got %d", len(findings))
	}
	assertFinding(t, findings[0], "php-file-size", "warning", 1)
}

func TestPhpFileSize_CustomLimit(t *testing.T) {
	ctx := phpContext(301, 300)
	findings := (&PhpFileSize{}).Check(ctx)

	if len(findings) != 1 {
		t.Fatalf("expected 1 finding, got %d", len(findings))
	}
	if !strings.Contains(findings[0].Message, "301") {
		t.Errorf("expected line count in message, got %q", findings[0].Message)
	}
}

func phpContext(lineCount, maxLines int) engine.CheckContext {
	lines := make([]string, lineCount)
	for i := range lines {
		lines[i] = "<?php echo 'line';"
	}
	return engine.CheckContext{
		FilePath: "src/Service.php",
		Language: "php",
		Lines:    lines,
		Spec: config.RuleSpec{
			Id:        "php-file-size",
			Severity:  "warning",
			Params:    map[string]any{"max_lines": float64(maxLines)},
			Reference: "spec/03-rules.md#php-file-size",
		},
	}
}

func assertFinding(t *testing.T, f engine.Finding, ruleId, severity string, line int) {
	t.Helper()
	if f.RuleId != ruleId {
		t.Errorf("expected rule_id %q, got %q", ruleId, f.RuleId)
	}
	if f.Severity != severity {
		t.Errorf("expected severity %q, got %q", severity, f.Severity)
	}
	if f.Line != line {
		t.Errorf("expected line %d, got %d", line, f.Line)
	}
}
