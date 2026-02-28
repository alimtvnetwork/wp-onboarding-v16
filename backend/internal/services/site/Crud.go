package site

import (
	"context"
	"fmt"
	"net/url"
	"strings"

	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/dbutil"
)

// List returns all registered sites.
func (s *Service) List(ctx context.Context) apperror.ResultSlice[models.Site] {
	set := dbutil.QueryMany[models.Site](ctx, s.dbu, siteListQuery, scanSiteRows)
	if set.HasError() {
		return set.ToAppResultSlice()
	}

	items := set.Items()
	if items == nil {
		items = []models.Site{}
	}
	return apperror.OkSlice(items)
}

// GetById returns a site by its ID.
func (s *Service) GetById(ctx context.Context, id int64) apperror.Result[models.Site] {
	result := dbutil.QueryOne[models.Site](ctx, s.dbu, siteSelectByIdQuery, scanSiteRow, id)
	if result.HasError() {
		return apperror.Fail[models.Site](result.AppError())
	}
	if result.IsEmpty() {
		return apperror.FailNew[models.Site](apperror.ErrNotFound, "site not found")
	}

	return apperror.Ok(result.Value())
}

// GetByUrl returns a site by its URL.
func (s *Service) GetByUrl(ctx context.Context, siteUrl string) apperror.Result[models.Site] {
	normalizedUrl := normalizeUrl(siteUrl)
	result := dbutil.QueryOne[models.Site](ctx, s.dbu, siteSelectByUrlQuery, scanSiteRow, normalizedUrl)
	return result.ToAppResult()
}

// Create adds a new WordPress site.
func (s *Service) Create(ctx context.Context, input CreateInput) apperror.Result[models.Site] {
	if appErr := s.validateInput(input); appErr != nil {
		return apperror.Fail[models.Site](appErr)
	}

	normalizedUrl := normalizeUrl(input.Url)
	if dupErr := s.checkDuplicateUrl(ctx, normalizedUrl); dupErr != nil {
		return dupErr
	}

	encryptedPassword, err := encrypt([]byte(input.Password), s.encryptionKey)
	if err != nil {
		return apperror.FailWrap[models.Site](err, apperror.ErrInternal, "failed to encrypt password")
	}

	return s.insertSite(ctx, input, normalizedUrl, encryptedPassword)
}

// checkDuplicateUrl verifies no existing site has the same URL.
func (s *Service) checkDuplicateUrl(ctx context.Context, normalizedUrl string) apperror.Result[models.Site] {
	existing := s.GetByUrl(ctx, normalizedUrl)
	if existing.HasError() {
		return existing
	}
	if existing.IsDefined() {
		return apperror.FailNew[models.Site](apperror.ErrValidation, "site with this URL already exists")
	}
	return apperror.Result[models.Site]{}
}

// insertSite executes the INSERT and returns the newly created site.
func (s *Service) insertSite(ctx context.Context, input CreateInput, normalizedUrl, encryptedPassword string) apperror.Result[models.Site] {
	res := dbutil.Exec(ctx, s.dbu, siteInsertQuery, input.Name, normalizedUrl, input.Username, encryptedPassword)
	if res.HasError() {
		return apperror.Fail[models.Site](res.AppError())
	}

	s.log.Info("Site created", "id", res.LastInsertId, "name", input.Name, "url", normalizedUrl)
	return s.GetById(ctx, res.LastInsertId)
}

// Update modifies an existing site.
func (s *Service) Update(ctx context.Context, id int64, input UpdateInput) apperror.Result[models.Site] {
	existingResult := s.GetById(ctx, id)
	if existingResult.HasError() {
		return existingResult
	}

	existing := existingResult.Value()
	updates, args := s.buildUpdateFields(ctx, id, input, &existing)
	if len(updates) == 0 {
		return existingResult
	}

	return s.executeUpdate(ctx, id, updates, args)
}

// executeUpdate runs the UPDATE query and returns the refreshed site.
func (s *Service) executeUpdate(ctx context.Context, id int64, updates []string, args []any) apperror.Result[models.Site] {
	updates = append(updates, "UpdatedAt = datetime('now')")
	args = append(args, id)

	query := fmt.Sprintf("UPDATE Sites SET %s WHERE Id = ?", strings.Join(updates, ", "))
	res := dbutil.Exec(ctx, s.dbu, query, args...)
	if res.HasError() {
		return apperror.Fail[models.Site](res.AppError())
	}

	s.log.Info("Site updated", "id", id)
	return s.GetById(ctx, id)
}

// buildUpdateFields constructs SET clauses and args from non-nil input fields.
func (s *Service) buildUpdateFields(_ context.Context, id int64, input UpdateInput, existing *models.Site) ([]string, []any) {
	var updates []string
	var args []any

	appendNameUpdate(&updates, &args, input.Name)
	appendUrlUpdate(urlUpdateInput{Updates: &updates, Args: &args, UrlInput: input.Url, ExistingUrl: existing.Url})
	appendUsernameUpdate(&updates, &args, input.Username)
	s.appendPasswordUpdate(&updates, &args, input.Password)

	return updates, args
}

// appendNameUpdate adds a Name update if provided.
func appendNameUpdate(updates *[]string, args *[]any, name *string) {
	isNameProvided := name != nil && *name != ""

	if isNameProvided {
		*updates = append(*updates, "Name = ?")
		*args = append(*args, *name)
	}
}

// urlUpdateInput bundles parameters for appendUrlUpdate.
type urlUpdateInput struct {
	Updates     *[]string
	Args        *[]any
	UrlInput    *string
	ExistingUrl string
}

// appendUrlUpdate adds a Url update if provided and changed.
func appendUrlUpdate(input urlUpdateInput) {
	isUrlMissing := input.UrlInput == nil || *input.UrlInput == ""

	if isUrlMissing {
		return
	}
	normalizedUrl := normalizeUrl(*input.UrlInput)
	if normalizedUrl != input.ExistingUrl {
		*input.Updates = append(*input.Updates, "Url = ?")
		*input.Args = append(*input.Args, normalizedUrl)
	}
}

// appendUsernameUpdate adds a Username update if provided.
func appendUsernameUpdate(updates *[]string, args *[]any, username *string) {
	isUsernameProvided := username != nil && *username != ""

	if isUsernameProvided {
		*updates = append(*updates, "Username = ?")
		*args = append(*args, *username)
	}
}

// appendPasswordUpdate adds a PasswordEncrypted update if provided.
func (s *Service) appendPasswordUpdate(updates *[]string, args *[]any, password *string) {
	isPasswordMissing := password == nil || *password == ""

	if isPasswordMissing {
		return
	}
	encryptedPassword, err := encrypt([]byte(*password), s.encryptionKey)
	if err == nil {
		*updates = append(*updates, "PasswordEncrypted = ?")
		*args = append(*args, encryptedPassword)
		*updates = append(*updates, "ConnectionStatus = 'unknown'")
	}
}

// Delete removes a site and its mappings (cascaded by FK).
func (s *Service) Delete(ctx context.Context, id int64) error {
	result := s.GetById(ctx, id)
	if result.HasError() {
		return result.AppError()
	}

	res := dbutil.Exec(ctx, s.dbu, siteDeleteQuery, id)
	if res.HasError() {
		return res.AppError()
	}
	if res.IsEmpty() {
		return apperror.New(apperror.ErrNotFound, "site not found")
	}

	s.log.Info("Site deleted", "id", id)
	return nil
}

// updateConnectionStatus updates the connection status and last tested time.
func (s *Service) updateConnectionStatus(ctx context.Context, id int64, status string) {
	res := dbutil.Exec(ctx, s.dbu, siteUpdateConnectionStatusQuery, status, id)
	if res.HasError() {
		s.log.Error("Failed to update connection status", "id", id, "error", res.AppError())
	}
}

// validateInput validates the create input.
func (s *Service) validateInput(input CreateInput) *apperror.AppError {
	if input.Name == "" {
		return apperror.New(apperror.ErrValidation, "name is required")
	}
	if input.Url == "" {
		return apperror.New(apperror.ErrValidation, "URL is required")
	}
	if input.Username == "" {
		return apperror.New(apperror.ErrValidation, "username is required")
	}
	if input.Password == "" {
		return apperror.New(apperror.ErrValidation, "application password is required")
	}
	if _, err := url.Parse(input.Url); err != nil {
		return apperror.Wrap(err, apperror.ErrValidation, "invalid URL format")
	}
	return nil
}
