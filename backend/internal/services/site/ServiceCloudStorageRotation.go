// Package site — Cloud storage rotation proxy methods.
package site

import (
	"context"
	"fmt"
	"net/http"

	ep "wp-plugin-publish/internal/enums/endpointtype"
	httpmethod "wp-plugin-publish/internal/enums/httpmethodtype"
	operationtype "wp-plugin-publish/internal/enums/operationtype"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
)

// GetCloudStorageRotationStatus fetches the rotation status from a remote WordPress site.
func (s *Service) GetCloudStorageRotationStatus(ctx context.Context, siteId int64, query string) (*wordpress.RotationStatus, *apperror.AppError) {
	client, appErr := s.createWPClient(ctx, siteId)
	if appErr != nil {
		return nil, appErr
	}

	endpoint := wordpress.BuildNamespacedEndpoint(wordpress.RiseupAsiaNamespace, ep.CloudStorageRotationStatus)
	if query != "" {
		endpoint = fmt.Sprintf("%s?%s", endpoint, query)
	}

	result := wordpress.DoApiCall[wordpress.RotationStatus](client, wordpress.ApiCallInput{
		Method:     httpmethod.Get,
		Endpoint:   endpoint,
		Operation:  operationtype.CloudStorageRotationStatus,
		OkStatuses: []int{http.StatusOK},
		ErrorCode:  apperror.ErrWPConnection,
	})

	if result.HasError() {
		return nil, result.AppError()
	}

	v := result.Value()
	return &v, nil
}

// TriggerCloudStorageRotation triggers a manual rotation on a remote WordPress site.
func (s *Service) TriggerCloudStorageRotation(ctx context.Context, siteId int64, body wordpress.CloudStorageRotateRequest) (*wordpress.CloudStorageRotateResult, *apperror.AppError) {
	client, appErr := s.createWPClient(ctx, siteId)
	if appErr != nil {
		return nil, appErr
	}

	endpoint := wordpress.BuildNamespacedEndpoint(wordpress.RiseupAsiaNamespace, ep.CloudStorageRotate)

	result := wordpress.DoApiCall[wordpress.CloudStorageRotateResult](client, wordpress.ApiCallInput{
		Method:     httpmethod.Post,
		Endpoint:   endpoint,
		Body:       body,
		Operation:  operationtype.CloudStorageRotate,
		OkStatuses: []int{http.StatusOK},
		ErrorCode:  apperror.ErrWPConnection,
	})

	if result.HasError() {
		return nil, result.AppError()
	}

	v := result.Value()
	return &v, nil
}
