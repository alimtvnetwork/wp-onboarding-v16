package endpoint

import (
	"os"
	"path/filepath"
	"regexp"
	"runtime"
	"strings"
	"testing"
)

// TestVariantsMatchPhpEndpointType reads the PHP EndpointType enum and verifies
// that every PHP case value has a matching Go Variant label, and vice-versa.
// This prevents drift between the two endpoint definitions.
func TestVariantsMatchPhpEndpointType(t *testing.T) {
	phpPath := findPhpEnumFile(t)
	phpValues := parsePhpEnumValues(t, phpPath)

	if len(phpValues) == 0 {
		t.Fatal("parsed zero PHP enum values — check the file path or regex")
	}

	// Known asymmetries (documented in spec/02-app-issues/16-unused-php-enum-cases.md)
	// WpJson is a URL-building prefix in PHP, not an API endpoint.
	phpOnly := map[string]bool{"wp-json/": true}
	// PostsById is a Go-only parameterised route; PHP handles it via /posts + body ID.
	goOnly := map[string]bool{"/posts/%d": true}

	// Build sets for comparison (normalise PHP values to Go format: prepend '/')
	goSet := make(map[string]bool)
	for _, v := range All() {
		goSet[v.String()] = true
	}

	phpSet := make(map[string]bool)
	for _, v := range phpValues {
		phpSet["/"+v] = true
	}

	// Check PHP → Go
	for raw, normalised := range map[string]string{} {
		_ = raw
		_ = normalised
	}
	for _, raw := range phpValues {
		normalised := "/" + raw
		if phpOnly[raw] {
			continue
		}
		if !goSet[normalised] {
			t.Errorf("PHP EndpointType case %q (normalised: %s) has no matching Go Variant", raw, normalised)
		}
	}

	// Check Go → PHP
	for _, v := range All() {
		label := v.String()
		if goOnly[label] {
			continue
		}
		if !phpSet[label] {
			t.Errorf("Go Variant %q has no matching PHP EndpointType case", label)
		}
	}

	t.Logf("Compared %d PHP cases against %d Go variants", len(phpValues), len(All()))
}

// findPhpEnumFile locates the PHP EndpointType file relative to this test file.
func findPhpEnumFile(t *testing.T) string {
	t.Helper()

	_, thisFile, _, ok := runtime.Caller(0)
	if !ok {
		t.Fatal("runtime.Caller failed")
	}

	// Navigate from backend/internal/enums/endpoint/ up to repo root, then into wp-plugins.
	repoRoot := filepath.Join(filepath.Dir(thisFile), "..", "..", "..", "..")
	phpFile := filepath.Join(repoRoot, "wp-plugins", "riseup-asia-uploader", "includes", "Enums", "EndpointType.php")

	if _, err := os.Stat(phpFile); err != nil {
		t.Skipf("PHP enum file not found at %s — skipping cross-language drift test", phpFile)
	}

	return phpFile
}

// parsePhpEnumValues extracts all `case Foo = 'value';` values from the PHP enum file.
func parsePhpEnumValues(t *testing.T, path string) []string {
	t.Helper()

	data, err := os.ReadFile(path)
	if err != nil {
		t.Fatalf("failed to read PHP enum file: %v", err)
	}

	re := regexp.MustCompile(`case\s+\w+\s*=\s*'([^']+)'\s*;`)
	matches := re.FindAllStringSubmatch(string(data), -1)

	values := make([]string, 0, len(matches))
	for _, m := range matches {
		values = append(values, strings.TrimSpace(m[1]))
	}

	return values
}
