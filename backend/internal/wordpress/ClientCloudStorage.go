package wordpress

import (
	"net/http"

	ep "wp-plugin-publish/internal/enums/endpointtype"
	httpmethod "wp-plugin-publish/internal/enums/httpmethodtype"
	operationtype "wp-plugin-publish/internal/enums/operationtype"
	"wp-plugin-publish/pkg/apperror"
)

// UploadToCloudStorage triggers a cloud storage upload on the WordPress site.
func (c *Client) UploadToCloudStorage(req CloudStorageUploadRequest) apperror.Result[CloudStorageUploadResult] {
	namespace := c.resolveNamespace()
	endpoint := "/" + namespace + ep.CloudStorageUpload.String()

	return doApiCall[CloudStorageUploadResult](c, apiCallInput{
		Method:     httpmethod.Post,
		Endpoint:   endpoint,
		Body:       req,
		Operation:  operationtype.CloudStorageUpload,
		OkStatuses: []int{http.StatusOK, http.StatusCreated},
		ErrorCode:  apperror.ErrWPConnection,
	})
}
