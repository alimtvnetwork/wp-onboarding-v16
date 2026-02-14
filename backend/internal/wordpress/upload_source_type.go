package wordpress

// UploadSourceType represents the source of a plugin upload.
type UploadSourceType string

const (
	// UploadSourceScript indicates upload via PowerShell/deployment script.
	UploadSourceScript UploadSourceType = "upload_script"

	// UploadSourceRestAPI indicates upload via direct REST API call.
	UploadSourceRestAPI UploadSourceType = "rest_api"

	// UploadSourceAdminUI indicates upload via WordPress admin panel.
	UploadSourceAdminUI UploadSourceType = "admin_ui"

	// UploadSourceWPCLI indicates upload via WP-CLI command.
	UploadSourceWPCLI UploadSourceType = "wp_cli"
)

// IsEqual checks type-safe equality against another UploadSourceType.
func (u UploadSourceType) IsEqual(other UploadSourceType) bool {
	return u == other
}

// String returns the raw string value.
func (u UploadSourceType) String() string {
	return string(u)
}
