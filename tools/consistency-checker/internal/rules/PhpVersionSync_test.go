package rules

import (
	"testing"

	"consistency-checker/internal/config"
	"consistency-checker/internal/engine"
)

func TestExtractHeaderVersion(t *testing.T) {
	tests := []struct {
		name     string
		lines    []string
		expected string
	}{
		{
			name:     "standard header",
			lines:    []string{"<?php", "/**", " * Plugin Name: QUpload", " * Version: 2.18.0", " */"},
			expected: "2.18.0",
		},
		{
			name:     "no version",
			lines:    []string{"<?php", "/**", " * Plugin Name: QUpload", " */"},
			expected: "",
		},
		{
			name:     "asterisk prefix",
			lines:    []string{"<?php", "/**", " * Version:     1.2.3", " */"},
			expected: "1.2.3",
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			got := extractHeaderVersion(tt.lines)
			if got != tt.expected {
				t.Errorf("extractHeaderVersion() = %q, want %q", got, tt.expected)
			}
		})
	}
}

func TestPhpVersionSyncSkipsNonPluginFiles(t *testing.T) {
	rule := &PhpVersionSync{}
	ctx := engine.CheckContext{
		FilePath: "some/random/file.php",
		Language: "php",
		Lines:    []string{"<?php", "echo 'hello';"},
		Spec:     config.RuleSpec{Severity: "error"},
	}

	findings := rule.Check(ctx)
	if len(findings) != 0 {
		t.Errorf("Expected no findings for non-plugin file, got %d", len(findings))
	}
}

func TestReadEnumVersionRegex(t *testing.T) {
	content := "case Version = '2.18.0';"
	match := enumVersionRe.FindStringSubmatch(content)
	if match == nil || match[1] != "2.18.0" {
		t.Errorf("enumVersionRe failed to match, got %v", match)
	}
}
