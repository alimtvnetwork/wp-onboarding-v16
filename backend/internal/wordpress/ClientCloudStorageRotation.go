package wordpress

import (
	"fmt"
	"net/http"

	ep "wp-plugin-publish/internal/enums/endpointtype"
	httpmethod "wp-plugin-publish/internal/enums/httpmethodtype"
	operationtype "wp-plugin-publish/internal/enums/operationtype"
	"wp-plugin-publish/pkg/apperror"
)

// CloudStorageRotationStatusRequest is the request for fetching rotation status.
type CloudStorageRotationStatusRequest struct {
	AccountId int `json:"AccountId"`
}

// CloudStorageRotateRequest is the request for triggering manual rotation.
type CloudStorageRotateRequest struct {
	AccountId int `json:"AccountId"`
}

// CloudStorageRotateResult is the response from a manual rotation trigger.
type CloudStorageRotateResult struct {
	Applied      bool     `json:"Applied"`
	FilesDeleted int      `json:"FilesDeleted"`
	FilesMoved   int      `json:"FilesMoved"`
	DeletedFiles []string `json:"DeletedFiles,omitempty"`
	MovedFiles   []string `json:"MovedFiles,omitempty"`
	Message      string   `json:"Message"`
}

// GetCloudStorageRotationStatus fetches the rotation status for a cloud storage account.
func (c *Client) GetCloudStorageRotationStatus(accountId int) apperror.Result[RotationStatus] {
	namespace := c.resolveNamespace()
	endpoint := fmt.Sprintf("/%s%s?account_id=%d", namespace, ep.CloudStorageRotationStatus.String(), accountId)

	return DoApiCall[RotationStatus](c, ApiCallInput{
		Method:     httpmethod.Get,
		Endpoint:   endpoint,
		Body:       nil,
		Operation:  operationtype.CloudStorageRotationStatus,
		OkStatuses: []int{http.StatusOK},
		ErrorCode:  apperror.ErrWPConnection,
	})
}

// TriggerCloudStorageRotation triggers a manual rotation for a cloud storage account.
func (c *Client) TriggerCloudStorageRotation(req CloudStorageRotateRequest) apperror.Result[CloudStorageRotateResult] {
	namespace := c.resolveNamespace()
	endpoint := "/" + namespace + ep.CloudStorageRotate.String()

	return DoApiCall[CloudStorageRotateResult](c, ApiCallInput{
		Method:     httpmethod.Post,
		Endpoint:   endpoint,
		Body:       req,
		Operation:  operationtype.CloudStorageRotate,
		OkStatuses: []int{http.StatusOK},
		ErrorCode:  apperror.ErrWPConnection,
	})
}
