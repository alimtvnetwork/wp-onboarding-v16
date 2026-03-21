// Remote user management proxy methods for site service
package site

import (
	"context"

	ep "wp-plugin-publish/internal/enums/endpointtype"
	httpmethod "wp-plugin-publish/internal/enums/httpmethodtype"
	"wp-plugin-publish/internal/enums/operationtype"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
)

// ListRemoteUsers fetches paginated user list from a remote WordPress site.
func (s *Service) ListRemoteUsers(ctx context.Context, siteId int64, query string) (any, *apperror.AppError) {
	client, appErr := s.createWPClient(ctx, siteId)
	if appErr != nil {
		return nil, appErr
	}

	endpoint := ep.Users.String()
	hasQuery := query != ""

	if hasQuery {
		endpoint += "?" + query
	}

	result := wordpress.DoApiCall[any](client, wordpress.ApiCallInput{
		Method:    httpmethod.Get,
		Endpoint:  endpoint,
		Operation: operationtype.ListUsers,
	})
	if result.HasError() {
		return nil, result.AppError()
	}

	return result.Value(), nil
}

// GetRemoteUser fetches a single user from a remote WordPress site.
func (s *Service) GetRemoteUser(ctx context.Context, siteId int64, userId string) (any, *apperror.AppError) {
	client, appErr := s.createWPClient(ctx, siteId)
	if appErr != nil {
		return nil, appErr
	}

	endpoint := ep.Users.String() + "/" + userId

	result := wordpress.DoApiCall[wordpress.UserResponse](client, wordpress.ApiCallInput{
		Method:    httpmethod.Get,
		Endpoint:  endpoint,
		Operation: operationtype.GetUser,
	})
	if result.HasError() {
		return nil, result.AppError()
	}

	return result.Value(), nil
}

// CreateRemoteUser creates a new user on a remote WordPress site.
func (s *Service) CreateRemoteUser(ctx context.Context, siteId int64, input wordpress.UserCreateRequest) (any, *apperror.AppError) {
	client, appErr := s.createWPClient(ctx, siteId)
	if appErr != nil {
		return nil, appErr
	}

	result := wordpress.DoApiCall[wordpress.UserCreateResult](client, wordpress.ApiCallInput{
		Method:    httpmethod.Post,
		Endpoint:  ep.Users.String(),
		Operation: operationtype.CreateUser,
		Body:      input,
	})
	if result.HasError() {
		return nil, result.AppError()
	}

	return result.Value(), nil
}

// UpdateRemoteUser updates a user on a remote WordPress site.
func (s *Service) UpdateRemoteUser(ctx context.Context, siteId int64, userId string, input wordpress.UserUpdateRequest) (any, *apperror.AppError) {
	client, appErr := s.createWPClient(ctx, siteId)
	if appErr != nil {
		return nil, appErr
	}

	endpoint := ep.Users.String() + "/" + userId

	result := wordpress.DoApiCall[wordpress.UserUpdateResult](client, wordpress.ApiCallInput{
		Method:    httpmethod.Put,
		Endpoint:  endpoint,
		Operation: operationtype.UpdateUser,
		Body:      input,
	})
	if result.HasError() {
		return nil, result.AppError()
	}

	return result.Value(), nil
}

// DeleteRemoteUser deletes a user on a remote WordPress site.
func (s *Service) DeleteRemoteUser(ctx context.Context, siteId int64, userId string, reassign string) (any, *apperror.AppError) {
	client, appErr := s.createWPClient(ctx, siteId)
	if appErr != nil {
		return nil, appErr
	}

	endpoint := ep.Users.String() + "/" + userId
	hasReassign := reassign != ""

	if hasReassign {
		endpoint += "?reassign=" + reassign
	}

	result := wordpress.DoApiCall[wordpress.UserDeleteResult](client, wordpress.ApiCallInput{
		Method:    httpmethod.Delete,
		Endpoint:  endpoint,
		Operation: operationtype.DeleteUser,
	})
	if result.HasError() {
		return nil, result.AppError()
	}

	return result.Value(), nil
}
