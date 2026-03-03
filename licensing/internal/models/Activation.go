package models

import "time"

// Activation represents a domain activation record for a license.
type Activation struct {
	Id            int64      `json:"id"`
	LicenseId     int64      `json:"license_id"`
	Domain        string     `json:"domain"`
	IpAddress     string     `json:"ip_address,omitempty"`
	UserAgent     string     `json:"user_agent,omitempty"`
	ActivatedAt   time.Time  `json:"activated_at"`
	DeactivatedAt *time.Time `json:"deactivated_at,omitempty"`
}

// IsActive returns true if the activation has not been deactivated.
func (a *Activation) IsActive() bool {
	isNil := a == nil

	if isNil {
		return false
	}

	return a.DeactivatedAt == nil
}
