package rules

import (
	"testing"

	"consistency-checker/internal/config"
	"consistency-checker/internal/engine"
)

func makeCaseCtx(filePath string, lines []string) engine.CheckContext {
	return engine.CheckContext{
		FilePath: filePath,
		Language: "php",
		Lines:    lines,
		Spec: config.RuleSpec{
			Id:        "php-enum-case-values",
			Severity:  "warning",
			Reference: "spec/03-rules.md#php-enum-case-values",
		},
	}
}

func TestPhpEnumCaseValues_ValidPascalCase(t *testing.T) {
	ctx := makeCaseCtx("src/Enums/StatusType.php", []string{
		"<?php",
		"enum StatusType: string {",
		"    case Success = 'Success';",
		"    case Failed = 'Failed';",
		"    case InProgress = 'InProgress';",
		"}",
	})

	findings := (&PhpEnumCaseValues{}).Check(ctx)
	if len(findings) != 0 {
		t.Errorf("expected 0 findings for valid PascalCase values, got %d: %+v", len(findings), findings)
	}
}

func TestPhpEnumCaseValues_LowercaseValue(t *testing.T) {
	ctx := makeCaseCtx("src/Enums/StatusType.php", []string{
		"<?php",
		"enum StatusType: string {",
		"    case Success = 'success';",
		"}",
	})

	findings := (&PhpEnumCaseValues{}).Check(ctx)
	if len(findings) != 1 {
		t.Fatalf("expected 1 finding for lowercase value, got %d: %+v", len(findings), findings)
	}
	if findings[0].Line != 3 {
		t.Errorf("expected finding on line 3, got %d", findings[0].Line)
	}
	if findings[0].Suggestion != "Change value to PascalCase (e.g., 'Success')" {
		t.Errorf("unexpected suggestion: %s", findings[0].Suggestion)
	}
}

func TestPhpEnumCaseValues_UpperCaseValue(t *testing.T) {
	ctx := makeCaseCtx("src/Enums/StatusType.php", []string{
		"<?php",
		"enum StatusType: string {",
		"    case Success = 'SUCCESS';",
		"}",
	})

	findings := (&PhpEnumCaseValues{}).Check(ctx)
	if len(findings) != 1 {
		t.Fatalf("expected 1 finding for UPPER_CASE value, got %d: %+v", len(findings), findings)
	}
	if findings[0].Line != 3 {
		t.Errorf("expected finding on line 3, got %d", findings[0].Line)
	}
}

func TestPhpEnumCaseValues_NonEnumFile(t *testing.T) {
	ctx := makeCaseCtx("src/Service.php", []string{
		"<?php",
		"class Service {",
		"    public function run() {}",
		"}",
	})

	findings := (&PhpEnumCaseValues{}).Check(ctx)
	if len(findings) != 0 {
		t.Errorf("expected 0 findings for non-enum file, got %d", len(findings))
	}
}

func TestPhpEnumCaseValues_MultipleInvalidValues(t *testing.T) {
	ctx := makeCaseCtx("src/Enums/StatusType.php", []string{
		"<?php",
		"enum StatusType: string {",
		"    case Success = 'success';",
		"    case Failed = 'FAILED';",
		"    case Pending = 'Pending';",
		"}",
	})

	findings := (&PhpEnumCaseValues{}).Check(ctx)
	if len(findings) != 2 {
		t.Errorf("expected 2 findings (success + FAILED), got %d: %+v", len(findings), findings)
	}
}

func TestPhpEnumCaseValues_UnitEnumSkipped(t *testing.T) {
	ctx := makeCaseCtx("src/Enums/ActionType.php", []string{
		"<?php",
		"enum ActionType {",
		"    case Create;",
		"    case Delete;",
		"}",
	})

	findings := (&PhpEnumCaseValues{}).Check(ctx)
	if len(findings) != 0 {
		t.Errorf("expected 0 findings for unit enum without values, got %d: %+v", len(findings), findings)
	}
}
