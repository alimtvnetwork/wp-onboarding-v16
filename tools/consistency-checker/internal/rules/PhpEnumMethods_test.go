package rules

import (
	"testing"

	"consistency-checker/internal/config"
	"consistency-checker/internal/engine"
)

func makeMethodsCtx(filePath string, lines []string) engine.CheckContext {
	return engine.CheckContext{
		FilePath: filePath,
		Language: "php",
		Lines:    lines,
		Spec: config.RuleSpec{
			Id:        "php-enum-methods",
			Severity:  "warning",
			Reference: "spec/03-rules.md#php-enum-methods",
		},
	}
}

func TestPhpEnumMethods_AllPresent(t *testing.T) {
	ctx := makeMethodsCtx("src/Enums/StatusType.php", []string{
		"<?php",
		"enum StatusType: string {",
		"    case Active = 'Active';",
		"    public function isEqual(self $other): bool { return $this === $other; }",
		"    public function isOtherThan(self $other): bool { return $this !== $other; }",
		"    public function isAnyOf(self ...$others): bool { return in_array($this, $others); }",
		"}",
	})

	findings := (&PhpEnumMethods{}).Check(ctx)
	if len(findings) != 0 {
		t.Errorf("expected 0 findings, got %d: %+v", len(findings), findings)
	}
}

func TestPhpEnumMethods_MissingOne(t *testing.T) {
	ctx := makeMethodsCtx("src/Enums/StatusType.php", []string{
		"<?php",
		"enum StatusType: string {",
		"    case Active = 'Active';",
		"    public function isEqual(self $other): bool { return $this === $other; }",
		"    public function isOtherThan(self $other): bool { return $this !== $other; }",
		"}",
	})

	findings := (&PhpEnumMethods{}).Check(ctx)
	if len(findings) != 1 {
		t.Fatalf("expected 1 finding, got %d: %+v", len(findings), findings)
	}
	if findings[0].Message != "Enum StatusType is missing required method isAnyOf()" {
		t.Errorf("unexpected message: %s", findings[0].Message)
	}
}

func TestPhpEnumMethods_MissingAll(t *testing.T) {
	ctx := makeMethodsCtx("src/Enums/StatusType.php", []string{
		"<?php",
		"enum StatusType: string {",
		"    case Active = 'Active';",
		"}",
	})

	findings := (&PhpEnumMethods{}).Check(ctx)
	if len(findings) != 3 {
		t.Errorf("expected 3 findings, got %d: %+v", len(findings), findings)
	}
}

func TestPhpEnumMethods_NonEnumFile(t *testing.T) {
	ctx := makeMethodsCtx("src/Service.php", []string{
		"<?php",
		"class Service {",
		"    public function run() {}",
		"}",
	})

	findings := (&PhpEnumMethods{}).Check(ctx)
	if len(findings) != 0 {
		t.Errorf("expected 0 findings for non-enum file, got %d", len(findings))
	}
}
