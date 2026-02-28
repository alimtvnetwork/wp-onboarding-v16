package responsemessagetype

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

var variantLabels = [...]string{
	Invalid:                "Invalid",
	Success:                "Success",
	Unauthorized:           "Unauthorized",
	Forbidden:              "Forbidden",
	InvalidRequest:         "InvalidRequest",
	PluginNotFound:         "PluginNotFound",
	UploadFailed:           "UploadFailed",
	ActivationFailed:       "ActivationFailed",
	DeactivationFailed:     "DeactivationFailed",
	DeleteFailed:           "DeleteFailed",
	PostCreateFailed:       "PostCreateFailed",
	PostUpdateFailed:       "PostUpdateFailed",
	CategoryCreateFailed:   "CategoryCreateFailed",
	MediaUploadFailed:      "MediaUploadFailed",
	DbError:                "DbError",
	FileIgnored:            "FileIgnored",
	InvalidRequestBody:     "InvalidRequestBody",
	ServiceNotAvailable:    "ServiceNotAvailable",
	InvalidId:              "InvalidId",
	ConnectionSuccessful:   "ConnectionSuccessful",
	SnapshotNotFound:       "SnapshotNotFound",
	SnapshotProviderMissing: "SnapshotProviderMissing",
	ProviderMissing:        "ProviderMissing",
	SnapshotFileMissing:    "SnapshotFileMissing",
	UploadedFileMissing:    "UploadedFileMissing",
	ZipCreateFailed:        "ZipCreateFailed",
	TempDirCreateFailed:    "TempDirCreateFailed",
	InvalidFileTypeZip:     "InvalidFileTypeZip",
}

var variantValues = [...]string{
	Invalid:                "invalid",
	Success:                "Operation completed successfully",
	Unauthorized:           "Authentication required",
	Forbidden:              "Insufficient permissions",
	InvalidRequest:         "Invalid request data",
	PluginNotFound:         "Plugin not found",
	UploadFailed:           "Upload failed",
	ActivationFailed:       "Plugin activation failed",
	DeactivationFailed:     "Plugin deactivation failed",
	DeleteFailed:           "Plugin deletion failed",
	PostCreateFailed:       "Post creation failed",
	PostUpdateFailed:       "Post update failed",
	CategoryCreateFailed:   "Category creation failed",
	MediaUploadFailed:      "Media upload failed",
	DbError:                "Database error",
	FileIgnored:            "File ignored by .uploadignore",
	InvalidRequestBody:     "Invalid request body",
	ServiceNotAvailable:    "Service not available",
	InvalidId:              "Invalid ID",
	ConnectionSuccessful:   "Connection successful",
	SnapshotNotFound:       "Snapshot not found",
	SnapshotProviderMissing: "No snapshot provider available",
	ProviderMissing:        "No provider available",
	SnapshotFileMissing:    "Snapshot file not found",
	UploadedFileMissing:    "Uploaded file not found",
	ZipCreateFailed:        "Failed to create ZIP file",
	TempDirCreateFailed:    "Failed to create temp directory",
	InvalidFileTypeZip:     "Invalid file type. Expected ZIP file.",
}

func (v Variant) String() string {
	return v.Value()
}

func (v Variant) Label() string {
	if v.IsInvalid() {
		return variantLabels[Invalid]
	}

	return variantLabels[v]
}

func (v Variant) Value() string {
	if v.IsInvalid() {
		return variantValues[Invalid]
	}

	return variantValues[v]
}

func (v Variant) IsValid() bool {
	return v > Invalid && v < Variant(len(variantLabels))
}

func (v Variant) IsInvalid() bool         { return v == Invalid }
func (v Variant) IsDefined() bool         { return v != Invalid }
func (v Variant) IsDefinedAndValid() bool { return v.IsDefined() && v.IsValid() }

func (v Variant) IsOther(other Variant) bool { return v != other }

func (v Variant) IsAnyOf(others ...Variant) bool {
	for _, o := range others {
		if v == o {
			return true
		}
	}

	return false
}

// IsFailure returns true if this is an error/failure message.
func (v Variant) IsFailure() bool {
	return v != Success && v != FileIgnored && v.IsValid()
}

func All() []Variant {
	all := make([]Variant, 0, len(variantLabels)-1)

	for i := 1; i < len(variantLabels); i++ {
		all = append(all, Variant(i))
	}

	return all
}

func ByIndex(i int) Variant {
	isOutOfRange := i < 0 || i >= len(variantLabels)

	if isOutOfRange {
		return Invalid
	}

	return Variant(i)
}

func Parse(s string) (Variant, error) {
	trimmed := strings.TrimSpace(s)

	for i, str := range variantLabels {
		if strings.EqualFold(str, trimmed) {
			return Variant(i), nil
		}
	}

	for i, str := range variantValues {
		if strings.EqualFold(str, trimmed) {
			return Variant(i), nil
		}
	}

	return Invalid, fmt.Errorf("invalid response message: %q", s)
}

func Values() []string {
	result := make([]string, 0, len(variantLabels)-1)

	for _, s := range variantLabels[1:] {
		result = append(result, s)
	}

	return result
}

func (v Variant) MarshalJSON() ([]byte, error) {
	return json.Marshal(v.Value())
}

func (v *Variant) UnmarshalJSON(data []byte) error {
	var s string

	err := json.Unmarshal(data, &s)

	if err != nil {
		return err
	}

	parsed, err := Parse(s)
	if err != nil {
		return err
	}

	*v = parsed

	return nil
}
