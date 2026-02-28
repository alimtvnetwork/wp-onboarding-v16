// Package splitdb — query operations for listing projects and databases.
package splitdb

import "wp-plugin-publish/pkg/apperror"

// ListProjects returns all active projects
func (m *DBManager) ListProjects() ([]Project, error) {
	rows, err := m.rootDB.Query(`
		SELECT Id, Slug, DisplayName, Path, Status, CreatedAt, UpdatedAt
		FROM Projects WHERE Status = 'active'
		ORDER BY DisplayName
	`)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to list projects")
	}
	defer rows.Close()

	var projects []Project
	for rows.Next() {
		var p Project
		err := rows.Scan(&p.ID, &p.Slug, &p.DisplayName, &p.Path, &p.Status, &p.CreatedAt, &p.UpdatedAt)
		if err != nil {

			return nil, apperror.Wrap(err, apperror.ErrDatabaseScan, "failed to scan project row")
		}
		projects = append(projects, p)
	}

	return projects, nil
}

// ListDatabases returns all databases for a project
func (m *DBManager) ListDatabases(projectSlug string) ([]Database, error) {
	query := `
		SELECT d.Id, d.ProjectId, d.Type, d.EntityId, d.Path, 
		       d.SizeBytes, d.RecordCount, d.Status, d.CreatedAt, d.UpdatedAt
		FROM Databases d
		JOIN Projects p ON d.ProjectId = p.Id
		WHERE p.Slug = ? AND d.Status = 'active'
	`

	rows, err := m.rootDB.Query(query, projectSlug)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to list databases").
			WithSlug(projectSlug)
	}
	defer rows.Close()

	var dbs []Database
	for rows.Next() {
		var db Database
		err := rows.Scan(
			&db.ID, &db.ProjectID, &db.Type, &db.EntityID, &db.Path,
			&db.SizeBytes, &db.RecordCount, &db.Status, &db.CreatedAt, &db.UpdatedAt,
		)
		if err != nil {

			return nil, apperror.Wrap(err, apperror.ErrDatabaseScan, "failed to scan database row")
		}
		dbs = append(dbs, db)
	}

	return dbs, nil
}
