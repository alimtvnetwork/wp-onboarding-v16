package services

import (
	"riseup-licensing/pkg/apperror"
)

// LicenseStats holds aggregated dashboard statistics.
type LicenseStats struct {
	Total            int            `json:"total"`
	Active           int            `json:"active"`
	Expired          int            `json:"expired"`
	Revoked          int            `json:"revoked"`
	Suspended        int            `json:"suspended"`
	ExpiringSoon     int            `json:"expiring_soon"`
	TotalActivations int            `json:"total_activations"`
	ByProduct        []CountEntry   `json:"by_product"`
	ByType           []CountEntry   `json:"by_type"`
	ByStatus         []CountEntry   `json:"by_status"`
}

// CountEntry holds a name-value pair for distribution data.
type CountEntry struct {
	Name  string `json:"name"`
	Value int    `json:"value"`
}

// Stats returns aggregated license statistics for the dashboard.
func (s *LicenseService) Stats() apperror.Result[LicenseStats] {
	var stats LicenseStats

	// Total and by-status counts
	row := s.db.QueryRow(statsCountSql)
	scanErr := row.Scan(&stats.Total, &stats.Active, &stats.Expired, &stats.Revoked, &stats.Suspended)
	if scanErr != nil {

		return apperror.FailWrap[LicenseStats](scanErr, apperror.ErrDatabaseQuery, "count licenses")
	}

	// Expiring within 30 days
	row = s.db.QueryRow(statsExpiringSoonSql)
	scanErr = row.Scan(&stats.ExpiringSoon)
	if scanErr != nil {

		return apperror.FailWrap[LicenseStats](scanErr, apperror.ErrDatabaseQuery, "count expiring")
	}

	// Total activations
	row = s.db.QueryRow(statsTotalActivationsSql)
	scanErr = row.Scan(&stats.TotalActivations)
	if scanErr != nil {

		return apperror.FailWrap[LicenseStats](scanErr, apperror.ErrDatabaseQuery, "count activations")
	}

	// By product distribution
	byProductResult := s.queryDistribution(statsDistByProductSql)
	if byProductResult.HasError() {

		return apperror.FailFrom[LicenseStats](byProductResult.AppError())
	}
	stats.ByProduct = byProductResult.Value()

	// By type distribution
	byTypeResult := s.queryDistribution(statsDistByTypeSql)
	if byTypeResult.HasError() {

		return apperror.FailFrom[LicenseStats](byTypeResult.AppError())
	}
	stats.ByType = byTypeResult.Value()

	// By status distribution
	byStatusResult := s.queryDistribution(statsDistByStatusSql)
	if byStatusResult.HasError() {

		return apperror.FailFrom[LicenseStats](byStatusResult.AppError())
	}
	stats.ByStatus = byStatusResult.Value()

	return apperror.Ok(stats)
}

// queryDistribution runs a GROUP BY query and returns name-value pairs.
func (s *LicenseService) queryDistribution(query string) apperror.Result[[]CountEntry] {
	rows, queryErr := s.db.Query(query)
	if queryErr != nil {

		return apperror.FailWrap[[]CountEntry](queryErr, apperror.ErrDatabaseQuery, "distribution query")
	}
	defer rows.Close()

	var entries []CountEntry

	for rows.Next() {
		var entry CountEntry
		scanErr := rows.Scan(&entry.Name, &entry.Value)
		if scanErr != nil {

			return apperror.FailWrap[[]CountEntry](scanErr, apperror.ErrDatabaseQuery, "scan distribution row")
		}
		entries = append(entries, entry)
	}

	isEmpty := entries == nil

	if isEmpty {
		entries = []CountEntry{}
	}

	return apperror.Ok(entries)
}
