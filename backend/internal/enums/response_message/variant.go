package responsemessage

import (
	"encoding/json"
	"fmt"
	"strings"
)

// Variant represents standardized API response messages.
type Variant byte

const (
	Invalid                Variant = iota
	Success
	Unauthorized
	Forbidden
	InvalidRequest
	PluginNotFound
	UploadFailed
	ActivationFailed
	DeactivationFailed
	DeleteFailed
	PostCreateFailed
	PostUpdateFailed
	CategoryCreateFailed
	MediaUploadFailed
	DbError
	FileIgnored
	InvalidRequestBody
	ServiceNotAvailable
	InvalidId
	ConnectionSuccessful
	SnapshotNotFound
	SnapshotProviderMissing
	ProviderMissing
	SnapshotFileMissing
	UploadedFileMissing
	ZipCreateFailed
	TempDirCreateFailed
	InvalidFileTypeZip
)

var variantStrings = [...]string{
	Invalid:                 "invalid",
	Success:                 "Operation completed successfully",
	Unauthorized:            "Authentication required",
	Forbidden:               "Insufficient permissions",
	InvalidRequest:          "Invalid request data",
	PluginNotFound:          "Plugin not found",
	UploadFailed:            "Upload failed",
	ActivationFailed:        "Plugin activation failed",
	DeactivationFailed:      "Plugin deactivation failed",
	DeleteFailed:            "Plugin deletion failed",
	PostCreateFailed:        "Post creation failed",
	PostUpdateFailed:        "Post update failed",
	CategoryCreateFailed:    "Category creation failed",
	MediaUploadFailed:       "Media upload failed",
	DbError:                 "Database error",
	FileIgnored:             "File ignored by .uploadignore",
	InvalidRequestBody:      "Invalid request body",
	ServiceNotAvailable:     "Service not available",
	InvalidId:               "Invalid ID",
	ConnectionSuccessful:    "Connection successful",
	SnapshotNotFound:        "Snapshot not found",
	SnapshotProviderMissing: "No snapshot provider available",
	ProviderMissing:         "No provider available",
	SnapshotFileMissing:     "Snapshot file not found",
	UploadedFileMissing:     "Uploaded file not found",
	ZipCreateFailed:         "Failed to create ZIP file",
	TempDirCreateFailed:     "Failed to create temp directory",
	InvalidFileTypeZip:      "Invalid file type. Expected ZIP file.",
}

var variantLabels = [...]string{
	Invalid:                 "Invalid Message",
	Success:                 "Success",
	Unauthorized:            "Unauthorized",
	Forbidden:               "Forbidden",
	InvalidRequest:          "Invalid Request",
	PluginNotFound:          "Plugin Not Found",
	UploadFailed:            "Upload Failed",
	ActivationFailed:        "Activation Failed",
	DeactivationFailed:      "Deactivation Failed",
	DeleteFailed:            "Delete Failed",
	PostCreateFailed:        "Post Create Failed",
	PostUpdateFailed:        "Post Update Failed",
	CategoryCreateFailed:    "Category Create Failed",
	MediaUploadFailed:       "Media Upload Failed",
	DbError:                 "Database Error",
	FileIgnored:             "File Ignored",
	InvalidRequestBody:      "Invalid Request Body",
	ServiceNotAvailable:     "Service Not Available",
	InvalidId:               "Invalid ID",
	ConnectionSuccessful:    "Connection Successful",
	SnapshotNotFound:        "Snapshot Not Found",
	SnapshotProviderMissing: "Snapshot Provider Missing",
	ProviderMissing:         "Provider Missing",
	SnapshotFileMissing:     "Snapshot File Missing",
	UploadedFileMissing:     "Uploaded File Missing",
	ZipCreateFailed:         "ZIP Create Failed",
	TempDirCreateFailed:     "Temp Dir Create Failed",
	InvalidFileTypeZip:      "Invalid File Type (ZIP)",
}

func (v Variant) String() string {
	if !v.IsValid() {
		return variantStrings[Invalid]
	}
	return variantStrings[v]
}

func (v Variant) Label() string {
	if !v.IsValid() {
		return variantLabels[Invalid]
	}
	return variantLabels[v]
}

func (v Variant) IsValid() bool {
	return v > Invalid && v < Variant(len(variantStrings))
}

func (v Variant) IsInvalid() bool { return v == Invalid }

// IsFailure returns true if this is an error/failure message.
func (v Variant) IsFailure() bool {
	return v != Success && v != FileIgnored && v.IsValid()
}

func All() []Variant {
	all := make([]Variant, 0, len(variantStrings)-1)
	for i := 1; i < len(variantStrings); i++ {
		all = append(all, Variant(i))
	}
	return all
}

func ByIndex(i int) Variant {
	if i < 0 || i >= len(variantStrings) {
		return Invalid
	}
	return Variant(i)
}

func Parse(s string) (Variant, error) {
	trimmed := strings.TrimSpace(s)
	for i, str := range variantStrings {
		if strings.EqualFold(str, trimmed) {
			return Variant(i), nil
		}
	}
	return Invalid, fmt.Errorf("invalid response message: %q", s)
}

func Values() []string {
	result := make([]string, 0, len(variantStrings)-1)
	for _, s := range variantStrings[1:] {
		result = append(result, s)
	}
	return result
}

func (v Variant) MarshalJSON() ([]byte, error) {
	return json.Marshal(v.String())
}

func (v *Variant) UnmarshalJSON(data []byte) error {
	var s string
	if err := json.Unmarshal(data, &s); err != nil {
		return err
	}
	parsed, err := Parse(s)
	if err != nil {
		return err
	}
	*v = parsed
	return nil
}
