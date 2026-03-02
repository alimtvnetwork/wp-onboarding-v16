package operationtype

import (
	"testing"
)

// TestAllVariantsHaveLabelsAndValues ensures every iota constant has a
// non-empty label and value, catching copy-paste omissions.
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

	t.Logf("Verified %d operation type variants", len(variants))
}

// TestLabelArrayLengthMatchesValueArray ensures both lookup arrays are the same size.
func TestLabelArrayLengthMatchesValueArray(t *testing.T) {
	labelLen := len(variantLabels)
	valueLen := len(variantValues)

	isMismatch := labelLen != valueLen

	if isMismatch {
		t.Errorf("variantLabels length (%d) != variantValues length (%d)", labelLen, valueLen)
	}
}

// TestInvalidVariantBehavior verifies the Invalid sentinel behaves correctly.
func TestInvalidVariantBehavior(t *testing.T) {
	isValid := Invalid.IsValid()

	if isValid {
		t.Error("Invalid.IsValid() should return false")
	}

	isDefined := Invalid.IsDefined()

	if isDefined {
		t.Error("Invalid.IsDefined() should return false")
	}

	isInvalid := Invalid.IsInvalid()

	if !isInvalid {
		t.Error("Invalid.IsInvalid() should return true")
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

// TestParseLabelRoundTrip verifies every variant can be parsed from its label.
func TestParseLabelRoundTrip(t *testing.T) {
	for _, v := range All() {
		parsed, err := Parse(v.Label())

		if err != nil {
			t.Errorf("Parse(%q) failed: %v", v.Label(), err)

			continue
		}

		isMismatch := parsed != v

		if isMismatch {
			t.Errorf("Parse(%q) = %d, want %d", v.Label(), parsed, v)
		}
	}
}

// TestParseInvalidString verifies unknown strings return an error.
func TestParseInvalidString(t *testing.T) {
	_, err := Parse("nonexistent operation xyz")

	isNilErr := err == nil

	if isNilErr {
		t.Error("Parse(unknown) should return an error")
	}
}

// TestNoGapsBetweenVariants ensures iota constants are contiguous (no gaps).
func TestNoGapsBetweenVariants(t *testing.T) {
	variants := All()
	expectedCount := len(variantLabels) - 1 // minus Invalid

	isMismatch := len(variants) != expectedCount

	if isMismatch {
		t.Errorf("All() returned %d variants, expected %d (variantLabels-1)", len(variants), expectedCount)
	}

	// Verify each variant byte value is sequential
	for i, v := range variants {
		expectedByte := Variant(i + 1) // starts at 1 (after Invalid=0)
		isGap := v != expectedByte

		if isGap {
			t.Errorf("variant at index %d has byte %d, expected %d — possible iota gap", i, v, expectedByte)
		}
	}
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

// TestNoDuplicateLabels ensures no two variants share the same label string.
func TestNoDuplicateLabels(t *testing.T) {
	seen := make(map[string]Variant)

	for _, v := range All() {
		label := v.Label()
		existing, isDuplicate := seen[label]

		if isDuplicate {
			t.Errorf("duplicate label %q shared by variants %d and %d", label, existing, v)
		}

		seen[label] = v
	}
}

// TestJsonRoundTrip verifies MarshalJSON/UnmarshalJSON for all variants.
func TestJsonRoundTrip(t *testing.T) {
	for _, v := range All() {
		data, err := v.MarshalJSON()

		if err != nil {
			t.Errorf("MarshalJSON(%s) failed: %v", v.Label(), err)

			continue
		}

		var parsed Variant
		unmarshalErr := parsed.UnmarshalJSON(data)

		if unmarshalErr != nil {
			t.Errorf("UnmarshalJSON(%s) failed: %v", string(data), unmarshalErr)

			continue
		}

		isMismatch := parsed != v

		if isMismatch {
			t.Errorf("JSON round-trip: got %s, want %s", parsed.Label(), v.Label())
		}
	}
}
