package services

import (
	"testing"
	"time"

	"riseup-licensing/internal/enums/licensestatustype"
	"riseup-licensing/internal/enums/licensetype"
	"riseup-licensing/internal/enums/producttype"
)

func TestCreateAndGetById(t *testing.T) {
	db := newTestDB(t)
	svc := NewLicenseService(db)

	expires := time.Now().Add(30 * 24 * time.Hour)

	lic, err := svc.Create(CreateInput{
		Key:            "RISEUP-AAAA-BBBB-CCCC-DDDD",
		Email:          "user@example.com",
		Product:        producttype.RiseupUploader,
		Type:           licensetype.Professional,
		MaxActivations: 5,
		Notes:          "test note",
		ExpiresAt:      &expires,
	})
	if err != nil {
		t.Fatalf("create: %v", err)
	}

	if lic.Key != "RISEUP-AAAA-BBBB-CCCC-DDDD" {
		t.Errorf("key = %q, want RISEUP-AAAA-BBBB-CCCC-DDDD", lic.Key)
	}
	if lic.Email != "user@example.com" {
		t.Errorf("email = %q, want user@example.com", lic.Email)
	}
	if lic.MaxActivations != 5 {
		t.Errorf("max_activations = %d, want 5", lic.MaxActivations)
	}
	if !lic.Status.IsActive() {
		t.Errorf("status = %q, want active", lic.Status)
	}

	fetched, err := svc.GetById(lic.Id)
	if err != nil {
		t.Fatalf("get by id: %v", err)
	}
	if fetched.Key != lic.Key {
		t.Errorf("fetched key = %q, want %q", fetched.Key, lic.Key)
	}
}

func TestGetByKey(t *testing.T) {
	db := newTestDB(t)
	svc := NewLicenseService(db)

	svc.Create(CreateInput{
		Key:            "RISEUP-FIND-MEBY-KEYY-PLZZ",
		Email:          "find@example.com",
		Product:        producttype.RiseupUploader,
		Type:           licensetype.Standard,
		MaxActivations: 1,
	})

	lic, err := svc.GetByKey("RISEUP-FIND-MEBY-KEYY-PLZZ")
	if err != nil {
		t.Fatalf("get by key: %v", err)
	}
	if lic.Email != "find@example.com" {
		t.Errorf("email = %q, want find@example.com", lic.Email)
	}
}

func TestList(t *testing.T) {
	db := newTestDB(t)
	svc := NewLicenseService(db)

	for i := 0; i < 3; i++ {
		key, _ := GenerateKey()
		svc.Create(CreateInput{
			Key:            key,
			Email:          "list@example.com",
			Product:        producttype.RiseupUploader,
			Type:           licensetype.Standard,
			MaxActivations: 1,
		})
	}

	all, err := svc.List()
	if err != nil {
		t.Fatalf("list: %v", err)
	}
	if len(all) != 3 {
		t.Errorf("len = %d, want 3", len(all))
	}
}

func TestUpdate(t *testing.T) {
	db := newTestDB(t)
	svc := NewLicenseService(db)

	lic, _ := svc.Create(CreateInput{
		Key:            "RISEUP-UPDT-AAAA-BBBB-CCCC",
		Email:          "upd@example.com",
		Product:        producttype.RiseupUploader,
		Type:           licensetype.Standard,
		MaxActivations: 1,
	})

	newStatus := licensestatus.Suspended
	newMax := 10
	newNotes := "updated notes"

	updated, err := svc.Update(lic.Id, UpdateInput{
		Status:         &newStatus,
		MaxActivations: &newMax,
		Notes:          &newNotes,
	})
	if err != nil {
		t.Fatalf("update: %v", err)
	}

	if !updated.Status.IsSuspended() {
		t.Errorf("status = %q, want suspended", updated.Status)
	}
	if updated.MaxActivations != 10 {
		t.Errorf("max_activations = %d, want 10", updated.MaxActivations)
	}
	if updated.Notes != "updated notes" {
		t.Errorf("notes = %q, want 'updated notes'", updated.Notes)
	}
}

func TestUpdateNoChanges(t *testing.T) {
	db := newTestDB(t)
	svc := NewLicenseService(db)

	lic, _ := svc.Create(CreateInput{
		Key:            "RISEUP-NOOP-AAAA-BBBB-CCCC",
		Email:          "noop@example.com",
		Product:        producttype.RiseupUploader,
		Type:           licensetype.Standard,
		MaxActivations: 1,
	})

	result, err := svc.Update(lic.Id, UpdateInput{})
	if err != nil {
		t.Fatalf("update no-op: %v", err)
	}
	if result.Id != lic.Id {
		t.Errorf("id = %d, want %d", result.Id, lic.Id)
	}
}

func TestDelete(t *testing.T) {
	db := newTestDB(t)
	svc := NewLicenseService(db)

	lic, _ := svc.Create(CreateInput{
		Key:            "RISEUP-DELT-AAAA-BBBB-CCCC",
		Email:          "del@example.com",
		Product:        producttype.RiseupUploader,
		Type:           licensetype.Standard,
		MaxActivations: 1,
	})

	err := svc.Delete(lic.Id)
	if err != nil {
		t.Fatalf("delete: %v", err)
	}

	_, err = svc.GetById(lic.Id)
	if err == nil {
		t.Error("expected error after delete, got nil")
	}
}

func TestLicenseIsExpired(t *testing.T) {
	db := newTestDB(t)
	svc := NewLicenseService(db)

	past := time.Now().Add(-24 * time.Hour)
	lic, _ := svc.Create(CreateInput{
		Key:            "RISEUP-EXPD-AAAA-BBBB-CCCC",
		Email:          "exp@example.com",
		Product:        producttype.RiseupUploader,
		Type:           licensetype.Standard,
		MaxActivations: 1,
		ExpiresAt:      &past,
	})

	fetched, _ := svc.GetById(lic.Id)
	if !fetched.IsExpired() {
		t.Error("expected license to be expired")
	}
	if fetched.IsUsable() {
		t.Error("expired license should not be usable")
	}
}

func TestLicenseNotExpiredWhenNoExpiry(t *testing.T) {
	db := newTestDB(t)
	svc := NewLicenseService(db)

	lic, _ := svc.Create(CreateInput{
		Key:            "RISEUP-NOEX-AAAA-BBBB-CCCC",
		Email:          "noex@example.com",
		Product:        producttype.RiseupUploader,
		Type:           licensetype.Standard,
		MaxActivations: 1,
	})

	fetched, _ := svc.GetById(lic.Id)
	if fetched.IsExpired() {
		t.Error("license without expiry should not be expired")
	}
}
