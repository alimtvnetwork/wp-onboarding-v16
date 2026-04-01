// Package manifest provides validation for chunk reassembly manifests.
package manifest

// Manifest represents the top-level manifest.json structure produced by ZipSplitter.
type Manifest struct {
	Type      string  `json:"type"`
	Sequence  int     `json:"sequence"`
	Label     string  `json:"label"`
	CreatedAt string  `json:"createdAt"`
	TotalSize int64   `json:"totalSize"`
	ChunkSize int64   `json:"chunkSize"`
	Chunks    []Chunk `json:"chunks"`
}

// Chunk represents a single chunk entry within the manifest.
type Chunk struct {
	File   string `json:"file"`
	Size   int64  `json:"size"`
	SHA256 string `json:"sha256"`
}

// ValidationResult holds the outcome of manifest validation.
type ValidationResult struct {
	Valid    bool     `json:"valid"`
	Errors  []string `json:"errors,omitempty"`
	Summary *Summary `json:"summary,omitempty"`
}

// Summary provides computed statistics about a valid manifest.
type Summary struct {
	ChunkCount       int    `json:"chunkCount"`
	DeclaredTotal    int64  `json:"declaredTotal"`
	ComputedTotal    int64  `json:"computedTotal"`
	Type             string `json:"type"`
	Sequence         int    `json:"sequence"`
	SizeConsistent   bool   `json:"sizeConsistent"`
}
