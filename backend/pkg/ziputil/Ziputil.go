// Package ziputil provides ZIP compression helpers
package ziputil

import (
	"archive/zip"
	"compress/flate"
	"io"
)

// RegisterBestCompression registers flate.BestCompression (level 9) on the
// given zip.Writer. Level 9 produces the smallest output at the cost of
// slightly more CPU time. For plugin ZIPs (typically 1-10 MB of PHP/JS/CSS),
// the compression time difference is negligible while the size savings
// directly reduce upload time over the network. The resulting ZIP is fully
// standard-compliant and can be opened by PHP's ZipArchive, Windows Explorer,
// macOS Archive Utility, etc.
func RegisterBestCompression(w *zip.Writer) {
	w.RegisterCompressor(zip.Deflate, func(out io.Writer) (io.WriteCloser, error) {
		return flate.NewWriter(out, flate.BestCompression)
	})
}
