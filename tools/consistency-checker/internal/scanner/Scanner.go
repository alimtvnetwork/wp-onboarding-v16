// Package scanner walks directories and filters files by language and exclusions.
package scanner

import (
	"os"
	"path/filepath"

	"consistency-checker/pkg/apperror"
)

// ScannedFile holds a discovered file and its classified language.
type ScannedFile struct {
	Path     string
	Language string
	Lines    []string
}

// ScanInput bundles parameters for Scan.
type ScanInput struct {
	Directory     string
	GlobalExclude []string
}

// Scan walks the directory and returns classified, non-excluded files.
func Scan(input ScanInput) apperror.Result[[]ScannedFile] {
	var files []ScannedFile

	err := filepath.Walk(input.Directory, func(path string, info os.FileInfo, err error) error {
		if err != nil {
			return nil // skip inaccessible
		}
		return collectFile(&files, path, info, input)
	})

	if err != nil {
		return apperror.Fail[[]ScannedFile](apperror.Wrap(err, apperror.ErrScanner, "failed to scan directory"))
	}

	return apperror.Ok(files)
}

// collectFile adds a file to the list if it passes filters.
func collectFile(files *[]ScannedFile, path string, info os.FileInfo, input ScanInput) error {
	if info.IsDir() {
		return skipExcludedDir(path, input)
	}

	relPath := toRelativePath(path, input.Directory)
	if IsExcluded(relPath, input.GlobalExclude) {
		return nil
	}

	lang := classifyLanguage(path)
	if lang == "" {
		return nil
	}

	*files = append(*files, ScannedFile{Path: relPath, Language: lang})
	return nil
}

// skipExcludedDir returns filepath.SkipDir for excluded directories.
func skipExcludedDir(path string, input ScanInput) error {
	base := filepath.Base(path)
	if base == ".git" || base == "vendor" || base == "node_modules" {
		return filepath.SkipDir
	}
	return nil
}

// toRelativePath computes a relative path from base.
func toRelativePath(path, base string) string {
	rel, err := filepath.Rel(base, path)
	if err != nil {
		return path
	}
	return filepath.ToSlash(rel)
}
