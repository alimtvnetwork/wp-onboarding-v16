-- 001_initial.sql — Core licensing schema

CREATE TABLE licenses (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    key             TEXT    NOT NULL UNIQUE,
    email           TEXT    NOT NULL,
    product         TEXT    NOT NULL,
    type            TEXT    NOT NULL DEFAULT 'standard',
    status          TEXT    NOT NULL DEFAULT 'active',
    max_activations INTEGER NOT NULL DEFAULT 1,
    notes           TEXT,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at      DATETIME,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE activations (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    license_id      INTEGER NOT NULL REFERENCES licenses(id) ON DELETE CASCADE,
    domain          TEXT    NOT NULL,
    ip_address      TEXT,
    user_agent      TEXT,
    activated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deactivated_at  DATETIME,
    UNIQUE(license_id, domain)
);

CREATE TABLE audit_log (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    license_id      INTEGER REFERENCES licenses(id) ON DELETE SET NULL,
    action          TEXT    NOT NULL,
    domain          TEXT,
    ip_address      TEXT,
    details         TEXT,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_licenses_key ON licenses(key);
CREATE INDEX idx_licenses_email ON licenses(email);
CREATE INDEX idx_licenses_status ON licenses(status);
CREATE INDEX idx_activations_license ON activations(license_id);
CREATE INDEX idx_activations_domain ON activations(domain);
CREATE INDEX idx_audit_license ON audit_log(license_id);
CREATE INDEX idx_audit_action ON audit_log(action);
