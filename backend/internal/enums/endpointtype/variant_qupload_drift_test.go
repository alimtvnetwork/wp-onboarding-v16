package endpointtype

import (
	"os"
	"path/filepath"
	"runtime"
	"testing"
)

// TestVariantsMatchQUploadEndpointType reads the QUpload PHP EndpointType enum
// and verifies that every PHP case value has a matching Go Variant value, and
// vice-versa. This prevents drift between the two endpoint definitions.
func TestVariantsMatchQUploadEndpointType(t *testing.T) {
	phpPath := findQUploadPhpEnumFile(t)
	phpValues := parsePhpEnumValues(t, phpPath)

	if len(phpValues) == 0 {
		t.Fatal("parsed zero QUpload PHP enum values — check the file path or regex")
	}

	goSet := buildGoValueSet()
	phpSet := buildPhpValueSet(phpValues)

	checkPhpToGo(t, phpValues, goSet, "QUpload")
	checkGoToQUploadPhp(t, phpSet)

	t.Logf("Compared %d QUpload PHP cases against %d Go variants", len(phpValues), len(All()))
}

// findQUploadPhpEnumFile locates the QUpload PHP EndpointType file relative to this test file.
func findQUploadPhpEnumFile(t *testing.T) string {
	t.Helper()

	_, thisFile, _, ok := runtime.Caller(0)
	if !ok {
		t.Fatal("runtime.Caller failed")
	}

	repoRoot := filepath.Join(filepath.Dir(thisFile), "..", "..", "..", "..")
	phpFile := filepath.Join(repoRoot, "wp-plugins", "qupload", "includes", "Enums", "EndpointType.php")

	if _, err := os.Stat(phpFile); err != nil {
		t.Skipf("QUpload PHP enum file not found at %s — skipping cross-language drift test", phpFile)
	}

	return phpFile
}

// buildGoValueSet builds a set of all Go variant values.
func buildGoValueSet() map[string]bool {
	goSet := make(map[string]bool)

	for _, v := range All() {
		goSet[v.Value()] = true
	}

	return goSet
}

// buildPhpValueSet normalises PHP values to Go format (prepend '/').
func buildPhpValueSet(phpValues []string) map[string]bool {
	phpSet := make(map[string]bool)

	for _, v := range phpValues {
		phpSet["/"+v] = true
	}

	return phpSet
}

// checkPhpToGo verifies every QUpload PHP case has a Go match.
func checkPhpToGo(t *testing.T, phpValues []string, goSet map[string]bool, source string) {
	t.Helper()

	for _, raw := range phpValues {
		normalised := "/" + raw

		if !goSet[normalised] {
			t.Errorf("%s PHP EndpointType case %q (normalised: %s) has no matching Go Variant", source, raw, normalised)
		}
	}
}

// quploadGoOnlyEndpoints lists Go-only endpoints that QUpload PHP does not implement.
// QUpload is a minimal uploader — most riseup-asia endpoints are not mirrored.
var quploadGoOnlyEndpoints = map[string]bool{
	"/posts/%d":                            true,
	"/upload-active":                       true,
	"/plugins/info":                        true,
	"/plugins/exists":                      true,
	"/plugins/enable":                      true,
	"/plugins/disable":                     true,
	"/plugins/delete":                      true,
	"/plugins/files":                       true,
	"/plugins/file":                        true,
	"/plugins/sync":                        true,
	"/plugins/sync-manifest":               true,
	"/plugins/export":                      true,
	"/plugins/backup":                      true,
	"/plugins/backup-restore":              true,
	"/plugins/backup-list":                 true,
	"/plugins/backup-delete":               true,
	"/logs":                                true,
	"/logs/stats":                          true,
	"/posts":                               true,
	"/categories":                          true,
	"/media":                               true,
	"/export-self":                         true,
	"/error-logs":                          true,
	"/error-sessions":                      true,
	"/openapi":                             true,
	"/opcache-reset":                       true,
	"/snapshots/list":                      true,
	"/snapshots/schedule":                  true,
	"/snapshots/info":                      true,
	"/snapshots/delete":                    true,
	"/snapshots/restore":                   true,
	"/snapshots/export":                    true,
	"/snapshots/settings":                  true,
	"/snapshots/providers":                 true,
	"/snapshots/tables":                    true,
	"/snapshots/dependencies":              true,
	"/snapshots/export-pertable":           true,
	"/snapshots/full-backup":               true,
	"/snapshots/incremental":               true,
	"/snapshots/import":                    true,
	"/snapshots/cleanup":                   true,
	"/snapshots/progress":                  true,
	"/snapshots/download":                  true,
	"/snapshots/download-file":             true,
	"/agents":                              true,
	"/agents/add":                          true,
	"/agents/remove":                       true,
	"/agents/test":                         true,
	"/agents/sync":                         true,
	"/agents/plugins":                      true,
	"/agents/action":                       true,
	"/agents/history":                      true,
	"/users":                               true,
	"/users/(?P<id>\\d+)":                  true,
	"/users/app-password":                  true,
	"/users/export":                        true,
	"/users/import":                        true,
	"/users/export-sqlite":                 true,
	"/users/import-sqlite":                 true,
	"/cloud-storage/accounts":              true,
	"/cloud-storage/accounts/(?P<id>\\d+)": true,
	"/cloud-storage/accounts/test":         true,
	"/cloud-storage/settings":              true,
	"/cloud-storage/settings/(?P<provider>[a-zA-Z]+)": true,
	"/cloud-storage/upload":                           true,
	"/cloud-storage/files":                            true,
	"/cloud-storage/delete":                           true,
	"/cloud-storage/oauth/callback":                   true,
	"/cloud-storage/oauth/initiate":                   true,
	"/cloud-storage/repos":                            true,
	"/cloud-storage/branches":                         true,
	"/cloud-storage/backup-history":                   true,
	"/cloud-storage/backup-history/(?P<id>\\d+)":      true,
	"/cloud-storage/restore":                          true,
}

// checkGoToQUploadPhp verifies every Go variant either exists in QUpload PHP or is explicitly excluded.
func checkGoToQUploadPhp(t *testing.T, phpSet map[string]bool) {
	t.Helper()

	for _, v := range All() {
		val := v.Value()

		if val == "invalid" {
			continue
		}

		if quploadGoOnlyEndpoints[val] {
			continue
		}

		if !phpSet[val] {
			t.Errorf("Go Variant %q (value: %s) has no matching QUpload PHP EndpointType case", v.Label(), val)
		}
	}
}
