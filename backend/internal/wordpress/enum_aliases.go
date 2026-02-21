// Package wordpress — backward-compatible type aliases and constant re-exports.
//
// The canonical definitions now live in internal/enums/{endpoint,response_message,upload_source}.
// This file keeps the wordpress package compiling without changing every internal caller at once.
package wordpress

import (
	"wp-plugin-publish/internal/enums/endpoint"
	responsemessage "wp-plugin-publish/internal/enums/response_message"
	uploadsource "wp-plugin-publish/internal/enums/upload_source"
)

// --- Type aliases ---

type EndpointType = endpoint.Variant
type ResponseMessageType = responsemessage.Variant
type UploadSourceType = uploadsource.Variant

// --- Endpoint constant re-exports ---

var (
	EndpointStatus                = endpoint.Status
	EndpointUpload                = endpoint.Upload
	EndpointUploadActive          = endpoint.UploadActive
	EndpointPlugins               = endpoint.Plugins
	EndpointPluginInfo            = endpoint.PluginInfo
	EndpointPluginExists          = endpoint.PluginExists
	EndpointEnable                = endpoint.Enable
	EndpointDisable               = endpoint.Disable
	EndpointDelete                = endpoint.Delete
	EndpointFiles                 = endpoint.Files
	EndpointFile                  = endpoint.File
	EndpointSync                  = endpoint.Sync
	EndpointLogs                  = endpoint.Logs
	EndpointLogsStats             = endpoint.LogsStats
	EndpointPosts                 = endpoint.Posts
	EndpointPostsById             = endpoint.PostsById
	EndpointCategories            = endpoint.Categories
	EndpointMedia                 = endpoint.Media
	EndpointExportSelf            = endpoint.ExportSelf
	EndpointExportPlugin          = endpoint.ExportPlugin
	EndpointSyncManifest          = endpoint.SyncManifest
	EndpointErrorLogs             = endpoint.ErrorLogs
	EndpointErrorSessions         = endpoint.ErrorSessions
	EndpointSnapshotsList         = endpoint.SnapshotsList
	EndpointSnapshotsSchedule     = endpoint.SnapshotsSchedule
	EndpointSnapshotsInfo         = endpoint.SnapshotsInfo
	EndpointSnapshotsDelete       = endpoint.SnapshotsDelete
	EndpointSnapshotsRestore      = endpoint.SnapshotsRestore
	EndpointSnapshotsExport       = endpoint.SnapshotsExport
	EndpointSnapshotsSettings     = endpoint.SnapshotsSettings
	EndpointSnapshotsProviders    = endpoint.SnapshotsProviders
	EndpointSnapshotsTables       = endpoint.SnapshotsTables
	EndpointSnapshotsFullBackup   = endpoint.SnapshotsFullBackup
	EndpointSnapshotsIncremental  = endpoint.SnapshotsIncremental
	EndpointSnapshotsImport       = endpoint.SnapshotsImport
	EndpointSnapshotsCleanup      = endpoint.SnapshotsCleanup
	EndpointSnapshotsDownload     = endpoint.SnapshotsDownload
	EndpointSnapshotsDownloadFile = endpoint.SnapshotsDownloadFile
)

// --- ResponseMessage constant re-exports ---

var (
	ResponseMessageSuccess                = responsemessage.Success
	ResponseMessageUnauthorized           = responsemessage.Unauthorized
	ResponseMessageForbidden              = responsemessage.Forbidden
	ResponseMessageInvalidRequest         = responsemessage.InvalidRequest
	ResponseMessagePluginNotFound         = responsemessage.PluginNotFound
	ResponseMessageUploadFailed           = responsemessage.UploadFailed
	ResponseMessageActivationFailed       = responsemessage.ActivationFailed
	ResponseMessageDeactivationFailed     = responsemessage.DeactivationFailed
	ResponseMessageDeleteFailed           = responsemessage.DeleteFailed
	ResponseMessagePostCreateFailed       = responsemessage.PostCreateFailed
	ResponseMessagePostUpdateFailed       = responsemessage.PostUpdateFailed
	ResponseMessageCategoryCreateFailed   = responsemessage.CategoryCreateFailed
	ResponseMessageMediaUploadFailed      = responsemessage.MediaUploadFailed
	ResponseMessageDbError                = responsemessage.DbError
	ResponseMessageFileIgnored            = responsemessage.FileIgnored
	ResponseMessageInvalidRequestBody     = responsemessage.InvalidRequestBody
	ResponseMessageServiceNotAvailable    = responsemessage.ServiceNotAvailable
	ResponseMessageInvalidId              = responsemessage.InvalidId
	ResponseMessageConnectionSuccessful   = responsemessage.ConnectionSuccessful
	ResponseMessageSnapshotNotFound       = responsemessage.SnapshotNotFound
	ResponseMessageSnapshotProviderMissing = responsemessage.SnapshotProviderMissing
	ResponseMessageProviderMissing        = responsemessage.ProviderMissing
	ResponseMessageSnapshotFileMissing    = responsemessage.SnapshotFileMissing
	ResponseMessageUploadedFileMissing    = responsemessage.UploadedFileMissing
	ResponseMessageZipCreateFailed        = responsemessage.ZipCreateFailed
	ResponseMessageTempDirCreateFailed    = responsemessage.TempDirCreateFailed
	ResponseMessageInvalidFileTypeZip     = responsemessage.InvalidFileTypeZip
)

// --- UploadSource constant re-exports ---

var (
	UploadSourceScript  = uploadsource.Script
	UploadSourceRestAPI = uploadsource.RestAPI
	UploadSourceAdminUI = uploadsource.AdminUI
	UploadSourceWPCLI   = uploadsource.WPCLI
)
