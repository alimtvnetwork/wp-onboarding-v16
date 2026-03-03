package services

import (
	"testing"
)

func TestActivateNew(t *testing.T) {
	db := newTestDB(t)
	licId := seedLicense(t, db)
	svc := NewActivationService(db)

	act, err := svc.Activate(ActivateInput{
		LicenseId: licId,
		Domain:    "example.com",
		IpAddress: "1.2.3.4",
		UserAgent: "TestAgent/1.0",
	})
	if err != nil {
		t.Fatalf("activate: %v", err)
	}

	if act.Domain != "example.com" {
		t.Errorf("domain = %q, want example.com", act.Domain)
	}
	if !act.IsActive() {
		t.Error("new activation should be active")
	}
}

func TestActivateReactivation(t *testing.T) {
	db := newTestDB(t)
	licId := seedLicense(t, db)
	svc := NewActivationService(db)

	svc.Activate(ActivateInput{LicenseId: licId, Domain: "reactivate.com", IpAddress: "1.1.1.1", UserAgent: "A"})
	svc.Deactivate(licId, "reactivate.com")

	act, err := svc.Activate(ActivateInput{LicenseId: licId, Domain: "reactivate.com", IpAddress: "2.2.2.2", UserAgent: "B"})
	if err != nil {
		t.Fatalf("reactivate: %v", err)
	}

	if !act.IsActive() {
		t.Error("reactivated entry should be active")
	}
	if act.IpAddress != "2.2.2.2" {
		t.Errorf("ip = %q, want 2.2.2.2", act.IpAddress)
	}
}

func TestDeactivate(t *testing.T) {
	db := newTestDB(t)
	licId := seedLicense(t, db)
	svc := NewActivationService(db)

	svc.Activate(ActivateInput{LicenseId: licId, Domain: "deact.com", IpAddress: "1.1.1.1", UserAgent: "A"})

	err := svc.Deactivate(licId, "deact.com")
	if err != nil {
		t.Fatalf("deactivate: %v", err)
	}

	count, _ := svc.CountActive(licId)
	if count != 0 {
		t.Errorf("active count = %d, want 0", count)
	}
}

func TestCountActive(t *testing.T) {
	db := newTestDB(t)
	licId := seedLicense(t, db)
	svc := NewActivationService(db)

	for _, domain := range []string{"a.com", "b.com", "c.com"} {
		svc.Activate(ActivateInput{LicenseId: licId, Domain: domain, IpAddress: "1.1.1.1", UserAgent: "A"})
	}

	svc.Deactivate(licId, "b.com")

	count, err := svc.CountActive(licId)
	if err != nil {
		t.Fatalf("count: %v", err)
	}
	if count != 2 {
		t.Errorf("active count = %d, want 2", count)
	}
}

func TestListByLicense(t *testing.T) {
	db := newTestDB(t)
	licId := seedLicense(t, db)
	svc := NewActivationService(db)

	svc.Activate(ActivateInput{LicenseId: licId, Domain: "x.com", IpAddress: "1.1.1.1", UserAgent: "A"})
	svc.Activate(ActivateInput{LicenseId: licId, Domain: "y.com", IpAddress: "1.1.1.1", UserAgent: "A"})

	list, err := svc.ListByLicense(licId)
	if err != nil {
		t.Fatalf("list: %v", err)
	}
	if len(list) != 2 {
		t.Errorf("len = %d, want 2", len(list))
	}
}

func TestDuplicateActivationReturnsExisting(t *testing.T) {
	db := newTestDB(t)
	licId := seedLicense(t, db)
	svc := NewActivationService(db)

	first, _ := svc.Activate(ActivateInput{LicenseId: licId, Domain: "dup.com", IpAddress: "1.1.1.1", UserAgent: "A"})
	second, err := svc.Activate(ActivateInput{LicenseId: licId, Domain: "dup.com", IpAddress: "2.2.2.2", UserAgent: "B"})
	if err != nil {
		t.Fatalf("duplicate activate: %v", err)
	}

	if first.Id != second.Id {
		t.Errorf("expected same activation id, got %d and %d", first.Id, second.Id)
	}
}
