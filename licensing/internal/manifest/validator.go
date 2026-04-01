package manifest

import (
	"fmt"
	"regexp"
	"strings"
)

// validTypes are the allowed manifest backup types.
var validTypes = map[string]bool{
	"full":        true,
	"incremental": true,
}

// chunkFilePattern matches expected chunk filenames like backup.zip.001, backup.zip.012.
var chunkFilePattern = regexp.MustCompile(`^backup\.zip\.\d{3}$`)

// sha256Pattern matches a 64-character lowercase hex string.
var sha256Pattern = regexp.MustCompile(`^[0-9a-f]{64}$`)

// Validate checks a Manifest for structural integrity and returns a ValidationResult.
func Validate(m *Manifest) ValidationResult {
	var errs []string

	errs = append(errs, validateRequiredFields(m)...)
	errs = append(errs, validateChunks(m)...)
	errs = append(errs, validateSizeConsistency(m)...)

	hasErrors := len(errs) > 0

	if hasErrors {
		return ValidationResult{Valid: false, Errors: errs}
	}

	return ValidationResult{
		Valid: true,
		Summary: buildSummary(m),
	}
}

// validateRequiredFields checks that top-level manifest fields are present and valid.
func validateRequiredFields(m *Manifest) []string {
	var errs []string

	isTypeEmpty := m.Type == ""

	if isTypeEmpty {
		errs = append(errs, "missing required field: type")
	} else {
		isTypeInvalid := !validTypes[strings.ToLower(m.Type)]

		if isTypeInvalid {
			errs = append(errs, fmt.Sprintf("invalid type %q: must be 'full' or 'incremental'", m.Type))
		}
	}

	isCreatedAtEmpty := m.CreatedAt == ""

	if isCreatedAtEmpty {
		errs = append(errs, "missing required field: createdAt")
	}

	isTotalSizeInvalid := m.TotalSize <= 0

	if isTotalSizeInvalid {
		errs = append(errs, "totalSize must be greater than 0")
	}

	isChunkSizeInvalid := m.ChunkSize <= 0

	if isChunkSizeInvalid {
		errs = append(errs, "chunkSize must be greater than 0")
	}

	isSequenceInvalid := m.Sequence < 0

	if isSequenceInvalid {
		errs = append(errs, "sequence must be non-negative")
	}

	return errs
}

// validateChunks checks that each chunk entry is complete and well-formed.
func validateChunks(m *Manifest) []string {
	var errs []string

	isChunksEmpty := len(m.Chunks) == 0

	if isChunksEmpty {
		errs = append(errs, "manifest contains no chunks")

		return errs
	}

	seenFiles := make(map[string]bool)

	for i, chunk := range m.Chunks {
		prefix := fmt.Sprintf("chunks[%d]", i)

		isFileEmpty := chunk.File == ""

		if isFileEmpty {
			errs = append(errs, fmt.Sprintf("%s: missing file name", prefix))
		} else {
			isPatternInvalid := !chunkFilePattern.MatchString(chunk.File)

			if isPatternInvalid {
				errs = append(errs, fmt.Sprintf("%s: invalid file name %q (expected backup.zip.NNN)", prefix, chunk.File))
			}

			isDuplicate := seenFiles[chunk.File]

			if isDuplicate {
				errs = append(errs, fmt.Sprintf("%s: duplicate file name %q", prefix, chunk.File))
			}

			seenFiles[chunk.File] = true
		}

		isSizeInvalid := chunk.Size <= 0

		if isSizeInvalid {
			errs = append(errs, fmt.Sprintf("%s: size must be greater than 0", prefix))
		}

		isHashEmpty := chunk.SHA256 == ""

		if isHashEmpty {
			errs = append(errs, fmt.Sprintf("%s: missing sha256 hash", prefix))
		} else {
			isHashInvalid := !sha256Pattern.MatchString(chunk.SHA256)

			if isHashInvalid {
				errs = append(errs, fmt.Sprintf("%s: invalid sha256 hash %q", prefix, chunk.SHA256))
			}
		}
	}

	return errs
}

// validateSizeConsistency checks that chunk sizes sum to totalSize.
func validateSizeConsistency(m *Manifest) []string {
	var errs []string

	hasNoChunks := len(m.Chunks) == 0

	if hasNoChunks {
		return errs
	}

	isTotalInvalid := m.TotalSize <= 0

	if isTotalInvalid {
		return errs
	}

	var computedTotal int64

	for _, chunk := range m.Chunks {
		computedTotal += chunk.Size
	}

	isMismatch := computedTotal != m.TotalSize

	if isMismatch {
		errs = append(errs, fmt.Sprintf(
			"size mismatch: chunks sum to %d bytes but totalSize declares %d bytes",
			computedTotal, m.TotalSize,
		))
	}

	// Validate no chunk exceeds declared chunkSize (except possibly the last).
	isChunkSizeValid := m.ChunkSize > 0

	if isChunkSizeValid {
		lastIndex := len(m.Chunks) - 1

		for i, chunk := range m.Chunks {
			isNotLast := i < lastIndex
			isOversized := chunk.Size > m.ChunkSize

			if isNotLast && isOversized {
				errs = append(errs, fmt.Sprintf(
					"chunks[%d]: size %d exceeds declared chunkSize %d",
					i, chunk.Size, m.ChunkSize,
				))
			}
		}
	}

	return errs
}

// buildSummary creates a Summary from a valid manifest.
func buildSummary(m *Manifest) *Summary {
	var computedTotal int64

	for _, chunk := range m.Chunks {
		computedTotal += chunk.Size
	}

	return &Summary{
		ChunkCount:    len(m.Chunks),
		DeclaredTotal: m.TotalSize,
		ComputedTotal: computedTotal,
		Type:          m.Type,
		Sequence:      m.Sequence,
		SizeConsistent: computedTotal == m.TotalSize,
	}
}
