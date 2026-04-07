package services

// SQL query constants for license statistics.

const statsCountSql = `
	SELECT
		COUNT(*) AS total,
		SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active,
		SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) AS expired,
		SUM(CASE WHEN status = 'revoked' THEN 1 ELSE 0 END) AS revoked,
		SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) AS suspended
	FROM licenses`

const statsExpiringSoonSql = `
	SELECT COUNT(*)
	FROM licenses
	WHERE status = 'active'
	  AND expires_at IS NOT NULL
	  AND expires_at <= datetime('now', '+30 days')
	  AND expires_at > datetime('now')`

const statsTotalActivationsSql = `
	SELECT COALESCE(SUM(max_activations), 0)
	FROM licenses`

const statsDistByProductSql = `
	SELECT product AS name, COUNT(*) AS value
	FROM licenses
	GROUP BY product
	ORDER BY value DESC`

const statsDistByTypeSql = `
	SELECT type AS name, COUNT(*) AS value
	FROM licenses
	GROUP BY type
	ORDER BY value DESC`

const statsDistByStatusSql = `
	SELECT status AS name, COUNT(*) AS value
	FROM licenses
	GROUP BY status
	ORDER BY value DESC`
