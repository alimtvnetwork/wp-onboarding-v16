package plugin

import (
	"context"
	"encoding/json"
	"os"
	"path/filepath"
	"time"

	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/pathutil"
)

const pluginDetectedFile = "wp-plugin-detected.json"

// ScanDirectory scans a plugin directory and returns file information.
func (s *Service) ScanDirectory(ctx context.Context, path string) apperror.Result[ScanResult] {
	s.log.Debug("Scanning directory", "path", path)

	scan := ScanResult{Path: path, IsValid: false, Files: []FileInfo{}}

	if result, ok := s.tryLoadDetected(path, &scan); ok {
		return result
	}

	if err := validateDirectoryExists(path, &scan); err != nil {
		return *err
	}

	pluginInfo, findErr := s.findMainPluginFile(path)
	if findErr != nil {
		scan.Error = findErr.Error()
		return apperror.Ok(scan)
	}

	applyPluginInfo(&scan, pluginInfo)

	if err := s.walkDirectory(path, &scan); err != nil {
		return apperror.FailWrap[ScanResult](err, apperror.ErrDirRead, "failed to scan directory")
	}

	s.log.Info("Directory scanned", "path", path, "pluginName", scan.PluginName, "files", scan.FileCount)
	return apperror.Ok(scan)
}

// validateDirectoryExists checks the path exists and is a directory.
func validateDirectoryExists(path string, scan *ScanResult) *apperror.Result[ScanResult] {
	info, err := os.Stat(path)
	if os.IsNotExist(err) {
		scan.Error = "directory does not exist"
		r := apperror.Ok(*scan)
		return &r
	}
	if err != nil {
		r := apperror.FailWrap[ScanResult](err, apperror.ErrDirRead, "failed to stat directory")
		return &r
	}
	if !info.IsDir() {
		scan.Error = "path is not a directory"
		r := apperror.Ok(*scan)
		return &r
	}
	return nil
}

// tryLoadDetected attempts to load plugin info from .plugin-detected.json.
func (s *Service) tryLoadDetected(path string, scan *ScanResult) (apperror.Result[ScanResult], bool) {
	detectedPath, err := pathutil.Join(path, pluginDetectedFile)
	if err != nil {
		return apperror.Result[ScanResult]{}, false
	}
	if _, err := os.Stat(detectedPath); err != nil {
		return apperror.Result[ScanResult]{}, false
	}

	detected, err := s.readPluginDetected(detectedPath)
	if err != nil {
		return apperror.Result[ScanResult]{}, false
	}

	applyDetectedInfo(scan, detected)
	s.log.Info("Found .plugin-detected.json", "path", path, "pluginName", detected.PluginName)
	return apperror.Ok(*scan), true
}

// applyDetectedInfo copies detected plugin data into the scan result.
func applyDetectedInfo(scan *ScanResult, d *PluginDetected) {
	scan.IsValid = true
	scan.PluginName = d.PluginName
	scan.Version = d.Version
	scan.MainFile = d.MainFile
	scan.Description = d.Description
	scan.Author = d.Author
	scan.AuthorURI = d.AuthorURI
	scan.PluginURI = d.PluginURI
	scan.TextDomain = d.TextDomain
	scan.RequiresPHP = d.RequiresPHP
	scan.RequiresWP = d.RequiresWP
}


// WritePluginDetected creates .plugin-detected.json for a valid WordPress plugin.
func (s *Service) WritePluginDetected(ctx context.Context, path string) error {
	scan := s.ScanDirectory(ctx, path)
	if scan.HasError() {
		return scan.AppError()
	}

	scanVal := scan.Value()
	if !scanVal.IsValid {
		return apperror.New(apperror.ErrPathInvalid, scanVal.Error)
	}

	return s.writeDetectedFile(path, scanVal)
}

// writeDetectedFile marshals and writes the detected plugin JSON.
func (s *Service) writeDetectedFile(path string, scanVal ScanResult) error {
	detected := buildPluginDetected(path, scanVal)

	data, err := json.MarshalIndent(detected, "", "  ")
	if err != nil {
		return apperror.Wrap(err, apperror.ErrInternal, "failed to marshal plugin detected")
	}

	detectedPath, err := pathutil.Join(path, pluginDetectedFile)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrInternal, "failed to resolve plugin detected path")
	}

	if err := os.WriteFile(detectedPath, data, 0644); err != nil {
		return apperror.Wrap(err, apperror.ErrFSWrite, "failed to write plugin detected file")
	}

	s.log.Info("Created .plugin-detected.json", "path", detectedPath)
	return nil
}

// buildPluginDetected constructs a PluginDetected from scan results.
func buildPluginDetected(path string, s ScanResult) PluginDetected {
	return PluginDetected{
		PluginName:  s.PluginName,
		Version:     s.Version,
		Slug:        filepath.Base(path),
		MainFile:    s.MainFile,
		Description: s.Description,
		Author:      s.Author,
		AuthorURI:   s.AuthorURI,
		PluginURI:   s.PluginURI,
		TextDomain:  s.TextDomain,
		RequiresPHP: s.RequiresPHP,
		RequiresWP:  s.RequiresWP,
		DetectedAt:  time.Now().UTC().Format(time.RFC3339),
	}
}

func (s *Service) readPluginDetected(path string) (*PluginDetected, error) {
	data, err := os.ReadFile(path)
	if err != nil {
		return nil, err
	}
	var detected PluginDetected
	if err := json.Unmarshal(data, &detected); err != nil {
		return nil, err
	}
	return &detected, nil
}

// ValidatePath checks if a path is a valid WordPress plugin directory.
func (s *Service) ValidatePath(ctx context.Context, path string) *apperror.AppError {
	scan := s.ScanDirectory(ctx, path)
	if scan.HasError() {
		return scan.AppError()
	}
	scanVal := scan.Value()
	if !scanVal.IsValid {
		return apperror.New(apperror.ErrPathInvalid, scanVal.Error).WithPath(path)
	}
	return nil
}
