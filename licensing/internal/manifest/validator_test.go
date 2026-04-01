package manifest

import (
	"bytes"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"
)

// --- Validator unit tests ---

func TestValidManifest(t *testing.T) {
	m := &Manifest{
		Type:      "full",
		Sequence:  1,
		Label:     "test-backup",
		CreatedAt: "2026-04-01T12:00:00Z",
		TotalSize: 6000,
		ChunkSize: 3145728,
		Chunks: []Chunk{
			{File: "backup.zip.001", Size: 3000, SHA256: "a" + strings.Repeat("0", 63)},
			{File: "backup.zip.002", Size: 3000, SHA256: "b" + strings.Repeat("0", 63)},
		},
	}

	result := Validate(m)

	isInvalid := !result.Valid

	if isInvalid {
		t.Fatalf("expected valid manifest, got errors: %v", result.Errors)
	}

	isSummaryNil := result.Summary == nil

	if isSummaryNil {
		t.Fatal("expected summary, got nil")
	}

	isChunkCountWrong := result.Summary.ChunkCount != 2

	if isChunkCountWrong {
		t.Errorf("expected 2 chunks, got %d", result.Summary.ChunkCount)
	}
}

func TestEmptyManifest(t *testing.T) {
	m := &Manifest{}

	result := Validate(m)

	isValid := result.Valid

	if isValid {
		t.Fatal("expected invalid manifest for empty input")
	}

	hasNoErrors := len(result.Errors) == 0

	if hasNoErrors {
		t.Fatal("expected errors for empty manifest")
	}
}

func TestMissingType(t *testing.T) {
	m := &Manifest{
		CreatedAt: "2026-04-01T12:00:00Z",
		TotalSize: 1000,
		ChunkSize: 3145728,
		Chunks:    []Chunk{{File: "backup.zip.001", Size: 1000, SHA256: strings.Repeat("a", 64)}},
	}

	result := Validate(m)

	isValid := result.Valid

	if isValid {
		t.Fatal("expected invalid for missing type")
	}

	found := containsError(result.Errors, "missing required field: type")

	if !found {
		t.Errorf("expected 'missing required field: type', got: %v", result.Errors)
	}
}

func TestInvalidType(t *testing.T) {
	m := &Manifest{
		Type:      "partial",
		CreatedAt: "2026-04-01T12:00:00Z",
		TotalSize: 1000,
		ChunkSize: 3145728,
		Chunks:    []Chunk{{File: "backup.zip.001", Size: 1000, SHA256: strings.Repeat("a", 64)}},
	}

	result := Validate(m)

	isValid := result.Valid

	if isValid {
		t.Fatal("expected invalid for bad type")
	}

	found := containsError(result.Errors, "invalid type")

	if !found {
		t.Errorf("expected 'invalid type' error, got: %v", result.Errors)
	}
}

func TestNoChunks(t *testing.T) {
	m := &Manifest{
		Type:      "full",
		CreatedAt: "2026-04-01T12:00:00Z",
		TotalSize: 1000,
		ChunkSize: 3145728,
		Chunks:    []Chunk{},
	}

	result := Validate(m)

	isValid := result.Valid

	if isValid {
		t.Fatal("expected invalid for empty chunks")
	}

	found := containsError(result.Errors, "manifest contains no chunks")

	if !found {
		t.Errorf("expected 'manifest contains no chunks', got: %v", result.Errors)
	}
}

func TestDuplicateChunkFile(t *testing.T) {
	m := &Manifest{
		Type:      "full",
		CreatedAt: "2026-04-01T12:00:00Z",
		TotalSize: 2000,
		ChunkSize: 3145728,
		Chunks: []Chunk{
			{File: "backup.zip.001", Size: 1000, SHA256: strings.Repeat("a", 64)},
			{File: "backup.zip.001", Size: 1000, SHA256: strings.Repeat("b", 64)},
		},
	}

	result := Validate(m)

	isValid := result.Valid

	if isValid {
		t.Fatal("expected invalid for duplicate chunk files")
	}

	found := containsError(result.Errors, "duplicate file name")

	if !found {
		t.Errorf("expected 'duplicate file name' error, got: %v", result.Errors)
	}
}

func TestInvalidChunkFileName(t *testing.T) {
	m := &Manifest{
		Type:      "full",
		CreatedAt: "2026-04-01T12:00:00Z",
		TotalSize: 1000,
		ChunkSize: 3145728,
		Chunks:    []Chunk{{File: "chunk_1.zip", Size: 1000, SHA256: strings.Repeat("a", 64)}},
	}

	result := Validate(m)

	isValid := result.Valid

	if isValid {
		t.Fatal("expected invalid for bad chunk filename")
	}

	found := containsError(result.Errors, "invalid file name")

	if !found {
		t.Errorf("expected 'invalid file name' error, got: %v", result.Errors)
	}
}

func TestInvalidSHA256(t *testing.T) {
	m := &Manifest{
		Type:      "full",
		CreatedAt: "2026-04-01T12:00:00Z",
		TotalSize: 1000,
		ChunkSize: 3145728,
		Chunks:    []Chunk{{File: "backup.zip.001", Size: 1000, SHA256: "not-a-hash"}},
	}

	result := Validate(m)

	isValid := result.Valid

	if isValid {
		t.Fatal("expected invalid for bad sha256")
	}

	found := containsError(result.Errors, "invalid sha256 hash")

	if !found {
		t.Errorf("expected 'invalid sha256 hash' error, got: %v", result.Errors)
	}
}

func TestSizeMismatch(t *testing.T) {
	m := &Manifest{
		Type:      "full",
		CreatedAt: "2026-04-01T12:00:00Z",
		TotalSize: 5000,
		ChunkSize: 3145728,
		Chunks: []Chunk{
			{File: "backup.zip.001", Size: 3000, SHA256: strings.Repeat("a", 64)},
			{File: "backup.zip.002", Size: 1000, SHA256: strings.Repeat("b", 64)},
		},
	}

	result := Validate(m)

	isValid := result.Valid

	if isValid {
		t.Fatal("expected invalid for size mismatch")
	}

	found := containsError(result.Errors, "size mismatch")

	if !found {
		t.Errorf("expected 'size mismatch' error, got: %v", result.Errors)
	}
}

func TestOversizedNonLastChunk(t *testing.T) {
	m := &Manifest{
		Type:      "full",
		CreatedAt: "2026-04-01T12:00:00Z",
		TotalSize: 7000,
		ChunkSize: 3000,
		Chunks: []Chunk{
			{File: "backup.zip.001", Size: 4000, SHA256: strings.Repeat("a", 64)},
			{File: "backup.zip.002", Size: 3000, SHA256: strings.Repeat("b", 64)},
		},
	}

	result := Validate(m)

	isValid := result.Valid

	if isValid {
		t.Fatal("expected invalid for oversized non-last chunk")
	}

	found := containsError(result.Errors, "exceeds declared chunkSize")

	if !found {
		t.Errorf("expected 'exceeds declared chunkSize' error, got: %v", result.Errors)
	}
}

func TestIncrementalType(t *testing.T) {
	m := &Manifest{
		Type:      "incremental",
		Sequence:  5,
		CreatedAt: "2026-04-01T12:00:00Z",
		TotalSize: 1000,
		ChunkSize: 3145728,
		Chunks:    []Chunk{{File: "backup.zip.001", Size: 1000, SHA256: strings.Repeat("c", 64)}},
	}

	result := Validate(m)

	isInvalid := !result.Valid

	if isInvalid {
		t.Fatalf("expected valid incremental manifest, got errors: %v", result.Errors)
	}
}

// --- Handler integration tests ---

func TestHandlerValidManifest(t *testing.T) {
	m := Manifest{
		Type:      "full",
		Sequence:  1,
		Label:     "test",
		CreatedAt: "2026-04-01T12:00:00Z",
		TotalSize: 1000,
		ChunkSize: 3145728,
		Chunks:    []Chunk{{File: "backup.zip.001", Size: 1000, SHA256: strings.Repeat("a", 64)}},
	}

	body, _ := json.Marshal(m)
	req := httptest.NewRequest(http.MethodPost, "/api/v1/admin/manifest/validate", bytes.NewReader(body))
	rec := httptest.NewRecorder()

	HandleValidateManifest(rec, req)

	isNotOK := rec.Code != http.StatusOK

	if isNotOK {
		t.Errorf("expected 200, got %d", rec.Code)
	}

	var resp validateManifestResponse
	json.NewDecoder(rec.Body).Decode(&resp)

	isNotSuccess := !resp.Success

	if isNotSuccess {
		t.Errorf("expected success=true, got false")
	}
}

func TestHandlerInvalidManifest(t *testing.T) {
	body := []byte(`{"type":"","chunks":[]}`)
	req := httptest.NewRequest(http.MethodPost, "/api/v1/admin/manifest/validate", bytes.NewReader(body))
	rec := httptest.NewRecorder()

	HandleValidateManifest(rec, req)

	isNotUnprocessable := rec.Code != http.StatusUnprocessableEntity

	if isNotUnprocessable {
		t.Errorf("expected 422, got %d", rec.Code)
	}
}

func TestHandlerBadJSON(t *testing.T) {
	body := []byte(`{not valid json}`)
	req := httptest.NewRequest(http.MethodPost, "/api/v1/admin/manifest/validate", bytes.NewReader(body))
	rec := httptest.NewRecorder()

	HandleValidateManifest(rec, req)

	isNotBadRequest := rec.Code != http.StatusBadRequest

	if isNotBadRequest {
		t.Errorf("expected 400, got %d", rec.Code)
	}
}

// --- helpers ---

func containsError(errs []string, substr string) bool {
	for _, e := range errs {
		isMatch := strings.Contains(e, substr)

		if isMatch {
			return true
		}
	}

	return false
}
