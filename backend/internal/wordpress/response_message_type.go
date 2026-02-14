package wordpress

// ResponseMessageType represents standardized API response messages.
type ResponseMessageType string

const (
	// ResponseMessageSuccess indicates the operation completed successfully.
	ResponseMessageSuccess ResponseMessageType = "Operation completed successfully"

	// ResponseMessageUnauthorized indicates authentication is required.
	ResponseMessageUnauthorized ResponseMessageType = "Authentication required"

	// ResponseMessageForbidden indicates insufficient permissions.
	ResponseMessageForbidden ResponseMessageType = "Insufficient permissions"

	// ResponseMessageInvalidRequest indicates invalid request data.
	ResponseMessageInvalidRequest ResponseMessageType = "Invalid request data"

	// ResponseMessagePluginNotFound indicates the plugin was not found.
	ResponseMessagePluginNotFound ResponseMessageType = "Plugin not found"

	// ResponseMessageUploadFailed indicates the upload failed.
	ResponseMessageUploadFailed ResponseMessageType = "Upload failed"

	// ResponseMessageActivationFailed indicates plugin activation failed.
	ResponseMessageActivationFailed ResponseMessageType = "Plugin activation failed"

	// ResponseMessageDeactivationFailed indicates plugin deactivation failed.
	ResponseMessageDeactivationFailed ResponseMessageType = "Plugin deactivation failed"

	// ResponseMessageDeleteFailed indicates plugin deletion failed.
	ResponseMessageDeleteFailed ResponseMessageType = "Plugin deletion failed"

	// ResponseMessagePostCreateFailed indicates post creation failed.
	ResponseMessagePostCreateFailed ResponseMessageType = "Post creation failed"

	// ResponseMessagePostUpdateFailed indicates post update failed.
	ResponseMessagePostUpdateFailed ResponseMessageType = "Post update failed"

	// ResponseMessageCategoryCreateFailed indicates category creation failed.
	ResponseMessageCategoryCreateFailed ResponseMessageType = "Category creation failed"

	// ResponseMessageMediaUploadFailed indicates media upload failed.
	ResponseMessageMediaUploadFailed ResponseMessageType = "Media upload failed"

	// ResponseMessageDbError indicates a database error.
	ResponseMessageDbError ResponseMessageType = "Database error"

	// ResponseMessageFileIgnored indicates the file was ignored by .uploadignore.
	ResponseMessageFileIgnored ResponseMessageType = "File ignored by .uploadignore"
)

// IsEqual checks type-safe equality against another ResponseMessageType.
func (r ResponseMessageType) IsEqual(other ResponseMessageType) bool {
	return r == other
}

// String returns the raw string value.
func (r ResponseMessageType) String() string {
	return string(r)
}

// IsFailure returns true if this is an error/failure message.
func (r ResponseMessageType) IsFailure() bool {
	return r != ResponseMessageSuccess && r != ResponseMessageFileIgnored
}
