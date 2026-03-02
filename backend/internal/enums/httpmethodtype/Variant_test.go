package httpmethodtype

import (
	"testing"
)

// TestAllVariantsHaveLabelsAndValues ensures every method variant has non-empty label and value.
func TestAllVariantsHaveLabelsAndValues(t *testing.T) {
	variants := All()

	if len(variants) == 0 {
		t.Fatal("All() returned zero variants")
	}

	for _, v := range variants {
		label := v.Label()
		value := v.Value()

		isLabelEmpty := label == "" || label == "Invalid"

		if isLabelEmpty {
			t.Errorf("Variant(%d) has missing or Invalid label", v)
		}

		isValueEmpty := value == "" || value == "invalid"

		if isValueEmpty {
			t.Errorf("Variant(%d) %s has missing or invalid value", v, label)
		}
	}

	t.Logf("Verified %d HTTP method variants", len(variants))
}

// TestNoDuplicateValues ensures no two variants share the same value string.
func TestNoDuplicateValues(t *testing.T) {
	seen := make(map[string]Variant)

	for _, v := range All() {
		val := v.Value()
		existing, isDuplicate := seen[val]

		if isDuplicate {
			t.Errorf("duplicate value %q shared by %s and %s", val, existing.Label(), v.Label())
		}

		seen[val] = v
	}
}

// TestParseRoundTrip verifies every variant can be parsed from its value string.
func TestParseRoundTrip(t *testing.T) {
	for _, v := range All() {
		parsed, err := Parse(v.Value())

		if err != nil {
			t.Errorf("Parse(%q) failed: %v", v.Value(), err)

			continue
		}

		isMismatch := parsed != v

		if isMismatch {
			t.Errorf("Parse(%q) = %d, want %d", v.Value(), parsed, v)
		}
	}
}

// TestInvalidVariantBehavior verifies the Invalid sentinel.
func TestInvalidVariantBehavior(t *testing.T) {
	isValid := Invalid.IsValid()

	if isValid {
		t.Error("Invalid.IsValid() should return false")
	}

	isDefined := Invalid.IsDefined()

	if isDefined {
		t.Error("Invalid.IsDefined() should return false")
	}
}

// TestHttpMethodValues verifies the standard HTTP method strings.
func TestHttpMethodValues(t *testing.T) {
	cases := []struct {
		variant  Variant
		expected string
	}{
		{Get, "GET"},
		{Post, "POST"},
		{Put, "PUT"},
		{Delete, "DELETE"},
		{Patch, "PATCH"},
		{Head, "HEAD"},
		{Options, "OPTIONS"},
	}

	for _, tc := range cases {
		actual := tc.variant.Value()
		isMismatch := actual != tc.expected

		if isMismatch {
			t.Errorf("%s.Value() = %q, want %q", tc.variant.Label(), actual, tc.expected)
		}
	}
}
