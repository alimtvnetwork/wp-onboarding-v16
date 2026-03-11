package services

import (
	"database/sql"

	"riseup-licensing/internal/enums/auditactiontype"
	"riseup-licensing/internal/enums/licensestatustype"
	"riseup-licensing/internal/enums/licensetype"
	"riseup-licensing/internal/enums/producttype"
	"riseup-licensing/internal/models"
	"riseup-licensing/pkg/apperror"
)

// scannable is an interface for types that support row scanning.
type scannable interface {
	Scan(dest ...any) error
}

// rowIterator is an interface for types that support iterating over rows.
type rowIterator interface {
	Next() bool
	Scan(dest ...any) error
	Err() error
}

// scanLicense scans a single license from a scannable source.
func scanLicense(row scannable) apperror.Result[*models.License] {
	var m models.License
	var product, ltype, status string

	scanErr := row.Scan(
		&m.Id,
		&m.Key,
		&m.Email,
		&product,
		&ltype,
		&status,
		&m.MaxActivations,
		&m.Notes,
		&m.CreatedAt,
		&m.ExpiresAt,
		&m.UpdatedAt,
	)
	if scanErr != nil {

		return apperror.FailWrap[*models.License](scanErr, apperror.ErrDatabaseScan, "scan license")
	}

	m.Product = producttype.Parse(product)
	m.Type = licensetype.Parse(ltype)
	m.Status = licensestatustype.Parse(status)

	return apperror.Ok(&m)
}

// scanLicenseRow scans a single license row from an iterator.
func scanLicenseRow(row scannable) (*models.License, error) {
	var m models.License
	var product, ltype, status string

	scanErr := row.Scan(
		&m.Id,
		&m.Key,
		&m.Email,
		&product,
		&ltype,
		&status,
		&m.MaxActivations,
		&m.Notes,
		&m.CreatedAt,
		&m.ExpiresAt,
		&m.UpdatedAt,
	)
	if scanErr != nil {

		return nil, scanErr
	}

	m.Product = producttype.Parse(product)
	m.Type = licensetype.Parse(ltype)
	m.Status = licensestatustype.Parse(status)

	return &m, nil
}

// scanLicenseRows scans multiple license rows from an iterator.
func scanLicenseRows(rows rowIterator) apperror.Result[[]models.License] {
	var licenses []models.License

	for rows.Next() {
		m, scanErr := scanLicenseRow(rows)
		if scanErr != nil {

			return apperror.FailWrap[[]models.License](scanErr, apperror.ErrDatabaseScan, "scan license row")
		}

		licenses = append(licenses, *m)
	}

	if rows.Err() != nil {

		return apperror.FailWrap[[]models.License](rows.Err(), apperror.ErrDatabaseQuery, "iterate license rows")
	}

	return apperror.Ok(licenses)
}

// scanActivation scans a single activation from a scannable source.
func scanActivation(row scannable) (*models.Activation, error) {
	var m models.Activation

	scanErr := row.Scan(
		&m.Id,
		&m.LicenseId,
		&m.Domain,
		&m.IpAddress,
		&m.UserAgent,
		&m.ActivatedAt,
		&m.DeactivatedAt,
	)
	if scanErr != nil {

		return nil, scanErr
	}

	return &m, nil
}

// scanActivationRows scans multiple activation rows from an iterator.
func scanActivationRows(rows rowIterator) apperror.Result[[]models.Activation] {
	var activations []models.Activation

	for rows.Next() {
		var m models.Activation

		scanErr := rows.Scan(
			&m.Id,
			&m.LicenseId,
			&m.Domain,
			&m.IpAddress,
			&m.UserAgent,
			&m.ActivatedAt,
			&m.DeactivatedAt,
		)
		if scanErr != nil {

			return apperror.FailWrap[[]models.Activation](scanErr, apperror.ErrDatabaseScan, "scan activation")
		}

		activations = append(activations, m)
	}

	if rows.Err() != nil {

		return apperror.FailWrap[[]models.Activation](rows.Err(), apperror.ErrDatabaseQuery, "iterate activation rows")
	}

	return apperror.Ok(activations)
}

// scanAuditLogRows scans multiple audit log rows from an iterator.
func scanAuditLogRows(rows *sql.Rows) apperror.Result[[]models.AuditLog] {
	var logs []models.AuditLog

	for rows.Next() {
		var m models.AuditLog
		var action string

		scanErr := rows.Scan(
			&m.Id,
			&m.LicenseId,
			&action,
			&m.Domain,
			&m.IpAddress,
			&m.Details,
			&m.CreatedAt,
		)
		if scanErr != nil {

			return apperror.FailWrap[[]models.AuditLog](scanErr, apperror.ErrDatabaseScan, "scan audit log")
		}

		m.Action = auditaction.Variant(action)
		logs = append(logs, m)
	}

	return apperror.Ok(logs)
}
