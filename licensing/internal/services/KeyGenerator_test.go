package services

import (
	"strings"
	"testing"
)

func TestGenerateKeyFormat(t *testing.T) {
	key, err := GenerateKey()
	if err != nil {
		t.Fatalf("generate: %v", err)
	}

	if !strings.HasPrefix(key, KeyPrefix+"-") {
		t.Errorf("key %q missing prefix %s-", key, KeyPrefix)
	}

	segments := strings.Split(key, "-")
	if len(segments) != 5 {
		t.Fatalf("expected 5 segments, got %d: %q", len(segments), key)
	}

	if segments[0] != KeyPrefix {
		t.Errorf("first segment = %q, want %q", segments[0], KeyPrefix)
	}

	for i := 1; i <= 4; i++ {
		if len(segments[i]) != 4 {
			t.Errorf("segment %d length = %d, want 4: %q", i, len(segments[i]), segments[i])
		}
	}
}

func TestGenerateKeyCharset(t *testing.T) {
	key, _ := GenerateKey()
	body := strings.TrimPrefix(key, KeyPrefix+"-")
	body = strings.ReplaceAll(body, "-", "")

	for _, ch := range body {
		if !strings.ContainsRune(keyChars, ch) {
			t.Errorf("invalid char %q in key %q", string(ch), key)
		}
	}
}

func TestGenerateKeyUniqueness(t *testing.T) {
	seen := make(map[string]bool)
	count := 200

	for i := 0; i < count; i++ {
		key, err := GenerateKey()
		if err != nil {
			t.Fatalf("generate %d: %v", i, err)
		}

		if seen[key] {
			t.Fatalf("duplicate key at iteration %d: %q", i, key)
		}

		seen[key] = true
	}
}
