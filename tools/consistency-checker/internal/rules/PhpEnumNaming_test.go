package rules

import (
	"testing"

	"consistency-checker/internal/config"
	"consistency-checker/internal/engine"
)

func makeEnumCtx(filePath string, lines []string) engine.CheckContext {
	return engine.CheckContext{
		FilePath: filePath,
		Language: "php",
		Lines:    lines,
		Spec: config.RuleSpec{
			Id:        "php-enum-naming",
			Severity:  "warning",
			Reference: "spec/03-rules.md#php-enum-naming",
		},
	}
}

func TestPhpEnumNaming_ValidEnum(t *testing.T) {
	ctx := makeEnumCtx("src/Enums/StatusType.php", []string{
		"<?php",
		"enum StatusType: string {",
		"    case Success = 'Success';",
		"}",
	})

	findings := (&PhpEnumNaming{}).Check(ctx)
	if len(findings) != 0 {
		t.Errorf("expected 0 findings for valid enum, got %d: %+v", len(findings), findings)
	}
}

func TestPhpEnumNaming_MissingTypeSuffix(t *testing.T) {
	ctx := makeEnumCtx("src/Enums/Status.php", []string{
		"<?php",
		"enum Status: string {",
		"    case Active = 'Active';",
		"}",
	})

	findings := (&PhpEnumNaming{}).Check(ctx)
	found := false
	for _, f := range findings {
		if f.Message == `Enum "Status" is missing the required "Type" suffix` {
			found = true
		}
	}
	if !found {
		t.Errorf("expected missing Type suffix finding, got: %+v", findings)
	}
}

func TestPhpEnumNaming_FileMismatch(t *testing.T) {
	ctx := makeEnumCtx("src/Enums/WrongName.php", []string{
		"<?php",
		"enum StatusType: string {",
		"    case Active = 'Active';",
		"}",
	})

	findings := (&PhpEnumNaming{}).Check(ctx)
	found := false
	for _, f := range findings {
		if f.Suggestion == "Rename file to StatusType.php" {
			found = true
		}
	}
	if !found {
		t.Errorf("expected file mismatch finding, got: %+v", findings)
	}
}

func TestPhpEnumNaming_NonPascalCase(t *testing.T) {
	ctx := makeEnumCtx("src/Enums/status_type.php", []string{
		"<?php",
		"enum status_type: string {",
		"    case active = 'active';",
		"}",
	})

	findings := (&PhpEnumNaming{}).Check(ctx)
	foundPascal := false
	foundSuffix := false
	for _, f := range findings {
		if f.Message == `Enum "status_type" does not follow PascalCase convention` {
			foundPascal = true
		}
		if f.Message == `Enum "status_type" is missing the required "Type" suffix` {
			foundSuffix = true
		}
	}
	if !foundPascal {
		t.Errorf("expected PascalCase violation finding, got: %+v", findings)
	}
	if !foundSuffix {
		t.Errorf("expected missing Type suffix finding for snake_case name, got: %+v", findings)
	}
}

func TestPhpEnumNaming_NoEnumDeclaration(t *testing.T) {
	ctx := makeEnumCtx("src/Service.php", []string{
		"<?php",
		"class Service {",
		"    public function run() {}",
		"}",
	})

	findings := (&PhpEnumNaming{}).Check(ctx)
	if len(findings) != 0 {
		t.Errorf("expected 0 findings for non-enum file, got %d", len(findings))
	}
}

func TestPhpEnumNaming_UnitEnumWithTypeSuffix(t *testing.T) {
	ctx := makeEnumCtx("src/Enums/ActionType.php", []string{
		"<?php",
		"enum ActionType {",
		"    case Create;",
		"    case Delete;",
		"}",
	})

	findings := (&PhpEnumNaming{}).Check(ctx)
	if len(findings) != 0 {
		t.Errorf("expected 0 findings for valid unit enum, got %d: %+v", len(findings), findings)
	}
}
