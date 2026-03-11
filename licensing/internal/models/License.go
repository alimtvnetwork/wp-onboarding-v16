// Package models defines database entity structs for the licensing server.
package models

import (
	"time"

	"riseup-licensing/internal/enums/licensestatustype"
	"riseup-licensing/internal/enums/licensetype"
	"riseup-licensing/internal/enums/producttype"
)

// License represents a license key record in the database.
type License struct {
	Id             int64                  `json:"id"`
	Key            string                 `json:"key"`
	Email          string                 `json:"email"`
	Product        producttype.Variant    `json:"product"`
	Type           licensetype.Variant    `json:"type"`
	Status         licensestatustype.Variant  `json:"status"`
	MaxActivations int                    `json:"max_activations"`
	Notes          string                 `json:"notes,omitempty"`
	CreatedAt      time.Time              `json:"created_at"`
	ExpiresAt      *time.Time             `json:"expires_at,omitempty"`
	UpdatedAt      time.Time              `json:"updated_at"`
}

// IsActive returns true if the license status is active.
func (l *License) IsActive() bool {
	isNil := l == nil

	if isNil {
		return false
	}

	return l.Status.IsActive()
}

// IsExpired returns true if the license has passed its expiration date.
func (l *License) IsExpired() bool {
	isNil := l == nil

	if isNil {
		return false
	}

	hasNoExpiry := l.ExpiresAt == nil

	if hasNoExpiry {
		return false
	}

	return time.Now().After(*l.ExpiresAt)
}

// IsUsable returns true if the license is active and not expired.
func (l *License) IsUsable() bool {
	return l.IsActive() && !l.IsExpired()
}
