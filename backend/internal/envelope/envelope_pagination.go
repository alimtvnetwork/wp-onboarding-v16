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
	if page < 1 {
		page = 1
	}
	if perPage < 1 {
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

	nav.NextPage = buildNextPageURL(basePath, p.Page, p.PerPage, total)
	nav.PrevPage = buildPrevPageURL(basePath, p.Page, p.PerPage)
	nav.CloserLinks = buildCloserLinks(basePath, p.Page, p.PerPage, total)

	return nav
}

// buildNextPageURL returns the next page URL or nil.
func buildNextPageURL(basePath string, page, perPage, total int) *string {
	if page >= total {
		return nil
	}
	next := fmt.Sprintf("%s?page=%d&perPage=%d", basePath, page+1, perPage)
	return &next
}

// buildPrevPageURL returns the previous page URL or nil.
func buildPrevPageURL(basePath string, page, perPage int) *string {
	if page <= 1 {
		return nil
	}
	prev := fmt.Sprintf("%s?page=%d&perPage=%d", basePath, page-1, perPage)
	return &prev
}

// buildCloserLinks generates a 5-page sliding window of links.
func buildCloserLinks(basePath string, page, perPage, total int) []string {
	windowSize := 5
	start, end := computeWindow(page, windowSize, total)

	links := make([]string, 0, windowSize)
	for i := start; i <= end; i++ {
		links = append(links, fmt.Sprintf("%s?page=%d&perPage=%d", basePath, i, perPage))
	}
	return links
}

// computeWindow calculates the start/end of a sliding page window.
func computeWindow(page, windowSize, total int) (int, int) {
	start := page - windowSize/2
	if start < 1 {
		start = 1
	}
	end := start + windowSize - 1
	if end > total {
		end = total
		start = end - windowSize + 1
		if start < 1 {
			start = 1
		}
	}
	return start, end
}
