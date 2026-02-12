// Package ziputil provides ZIP compression helpers
package ziputil

import (
	"archive/zip"
	"compress/flate"
	"io"
)

// RegisterCompression registers flate.DefaultCompression (level 6) on the
// given zip.Writer. Level 6 is ~2-3x faster than BestCompression (level 9)
// with only ~2-5% larger output. The resulting ZIP is fully standard-compliant
// and can be opened by PHP's ZipArchive, Windows Explorer, macOS Archive Utility, etc.
func RegisterBestCompression(w *zip.Writer) {
	w.RegisterCompressor(zip.Deflate, func(out io.Writer) (io.WriteCloser, error) {
		return flate.NewWriter(out, flate.DefaultCompression)
	})
}
