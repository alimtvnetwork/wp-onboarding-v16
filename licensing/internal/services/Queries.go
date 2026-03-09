package services

// SQL query constants for the licensing service layer.

const licenseSelectSql = `
	SELECT id, key, email, product, type, status, max_activations, notes, created_at, expires_at, updated_at
	FROM licenses`

const licenseSelectByIdSql = licenseSelectSql + ` WHERE id = ?`

const licenseSelectByKeySql = licenseSelectSql + ` WHERE key = ?`

const licenseListSql = licenseSelectSql + ` ORDER BY created_at DESC`

const licenseInsertSql = `
	INSERT INTO licenses (key, email, product, type, status, max_activations, notes, expires_at)
	VALUES (?, ?, ?, ?, ?, ?, ?, ?)`

const licenseDeleteSql = `DELETE FROM licenses WHERE id = ?`

const activationSelectSql = `
	SELECT id, license_id, domain, ip_address, user_agent, activated_at, deactivated_at
	FROM activations`

const activationFindExistingSql = activationSelectSql + ` WHERE license_id = ? AND domain = ?`

const activationListByLicenseSql = activationSelectSql + ` WHERE license_id = ? ORDER BY activated_at DESC`

const activationInsertSql = `
	INSERT INTO activations (license_id, domain, ip_address, user_agent)
	VALUES (?, ?, ?, ?)`

const activationReactivateSql = `
	UPDATE activations SET deactivated_at = NULL, ip_address = ?, user_agent = ?, activated_at = ?
	WHERE id = ?`

const activationDeactivateSql = `
	UPDATE activations SET deactivated_at = ?
	WHERE license_id = ? AND domain = ? AND deactivated_at IS NULL`

const activationCountActiveSql = `
	SELECT COUNT(*) FROM activations
	WHERE license_id = ? AND deactivated_at IS NULL`

const auditInsertSql = `
	INSERT INTO audit_log (license_id, action, domain, ip_address, details)
	VALUES (?, ?, ?, ?, ?)`
