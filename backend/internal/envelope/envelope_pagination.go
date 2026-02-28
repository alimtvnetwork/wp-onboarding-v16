package envelope

import (
	"fmt"
	"math"
)

// Pagination holds the parameters for paginated queries.
type Pagination struct {
	Page         int
	PerPage      int
	TotalRecords int
}

// DefaultPagination returns the default pagination (page 1, 20 per page).
func DefaultPagination() Pagination {
	return Pagination{Page: 1, PerPage: 20}
}

// NewPagination creates a Pagination with computed totals.
func NewPagination(totalRecords, page, perPage int) Pagination {
	isPageInvalid := page < 1

	if isPageInvalid {
		page = 1
	}

	isPerPageInvalid := perPage < 1

	if isPerPageInvalid {
		perPage = 20
	}
	return Pagination{
		Page:         page,
		PerPage:      perPage,
		TotalRecords: totalRecords,
	}
}

// TotalPages computes the total number of pages.
func (p Pagination) TotalPages() int {
	if p.PerPage <= 0 {
		return 0
	}
	return int(math.Ceil(float64(p.TotalRecords) / float64(p.PerPage)))
}

// Offset returns the SQL OFFSET for the current page.
func (p Pagination) Offset() int {
	return (p.Page - 1) * p.PerPage
}

// NavigationURLs computes the navigation block with URL string links.
func (p Pagination) NavigationURLs(basePath string) Navigation {
	total := p.TotalPages()
	nav := Navigation{}

	pageCtx := pageURLContext{BasePath: basePath, Page: p.Page, PerPage: p.PerPage, Total: total}
	nav.NextPage = buildNextPageURL(pageCtx)
	nav.PrevPage = buildPrevPageURL(pageCtx)
	nav.CloserLinks = buildCloserLinks(pageCtx)

	return nav
}

// pageURLContext bundles pagination URL parameters.
type pageURLContext struct {
	BasePath string
	Page     int
	PerPage  int
	Total    int
}

// buildNextPageURL returns the next page URL or nil.
func buildNextPageURL(ctx pageURLContext) *string {
	if ctx.Page >= ctx.Total {
		return nil
	}
	next := fmt.Sprintf("%s?page=%d&perPage=%d", ctx.BasePath, ctx.Page+1, ctx.PerPage)
	return &next
}

// buildPrevPageURL returns the previous page URL or nil.
func buildPrevPageURL(ctx pageURLContext) *string {
	isFirstPage := ctx.Page <= 1

	if isFirstPage {
		return nil
	}
	prev := fmt.Sprintf("%s?page=%d&perPage=%d", ctx.BasePath, ctx.Page-1, ctx.PerPage)
	return &prev
}

// buildCloserLinks generates a 5-page sliding window of links.
func buildCloserLinks(ctx pageURLContext) []string {
	windowSize := 5
	start, end := computeWindow(ctx.Page, windowSize, ctx.Total)

	links := make([]string, 0, windowSize)
	for i := start; i <= end; i++ {
		links = append(links, fmt.Sprintf("%s?page=%d&perPage=%d", ctx.BasePath, i, ctx.PerPage))
	}
	return links
}

// computeWindow calculates the start/end of a sliding page window.
func computeWindow(page, windowSize, total int) (int, int) {
	start := page - windowSize/2
	isStartUnderflow := start < 1

	if isStartUnderflow {
		start = 1
	}

	end := start + windowSize - 1
	isEndOverflow := end > total

	if isEndOverflow {
		end = total
		start = end - windowSize + 1
		isStartUnderflow = start < 1

		if isStartUnderflow {
			start = 1
		}
	}
	return start, end
}
