// Package wordpress provides PowerShell-based plugin upload execution.
package wordpress

import (
	"bytes"
	"encoding/json"
	"fmt"
	"os"
	"os/exec"
	"path/filepath"
	"runtime"
	"strings"
)

// PowerShellConfig holds configuration for the PowerShell uploader script.
type PowerShellConfig struct {
	PluginFolderPath     string `json:"pluginFolderPath"`
	WordPressSiteURL     string `json:"wordPressSiteURL"`
	Username             string `json:"username"`
	AppPassword          string `json:"appPassword"`
	OutputZipPath        string `json:"outputZipPath"`
	ActivateAfterInstall bool   `json:"activateAfterInstall"`
	DeleteZipAfterUpload bool   `json:"deleteZipAfterUpload"`
}

// PowerShellResult holds the result of a PowerShell upload execution.
type PowerShellResult struct {
	Success      bool   `json:"success"`
	ExitCode     int    `json:"exitCode"`
	Stdout       string `json:"stdout"`
	Stderr       string `json:"stderr"`
	ErrorMessage string `json:"errorMessage,omitempty"`
}

// RunPowerShellUpload executes the upload-plugin.ps1 script with the given configuration.
// It creates a temporary config file, runs the script, captures output, and cleans up.
func RunPowerShellUpload(scriptPath string, cfg PowerShellConfig, onOutput func(line string)) (*PowerShellResult, error) {
	// Only available on Windows
	if runtime.GOOS != "windows" {
		return nil, fmt.Errorf("PowerShell upload only available on Windows")
	}

	result := &PowerShellResult{
		Success:  false,
		ExitCode: -1,
	}

	// Create temporary config file
	tempDir := os.TempDir()
	configPath := filepath.Join(tempDir, fmt.Sprintf("wp-upload-config-%d.json", os.Getpid()))

	configBytes, err := json.MarshalIndent(cfg, "", "  ")
	if err != nil {
		return nil, fmt.Errorf("marshal config: %w", err)
	}

	if err := os.WriteFile(configPath, configBytes, 0600); err != nil {
		return nil, fmt.Errorf("write temp config: %w", err)
	}
	defer os.Remove(configPath)

	// Build PowerShell command
	// Use -ExecutionPolicy Bypass to allow script execution
	args := []string{
		"-ExecutionPolicy", "Bypass",
		"-NoProfile",
		"-NonInteractive",
		"-File", scriptPath,
		"-ConfigPath", configPath,
	}

	cmd := exec.Command("powershell.exe", args...)

	// Capture stdout and stderr
	var stdout, stderr bytes.Buffer
	cmd.Stdout = &stdout
	cmd.Stderr = &stderr

	// Run the command
	if onOutput != nil {
		onOutput(fmt.Sprintf("Executing: powershell.exe %s", strings.Join(args, " ")))
	}

	err = cmd.Run()

	result.Stdout = stdout.String()
	result.Stderr = stderr.String()

	if cmd.ProcessState != nil {
		result.ExitCode = cmd.ProcessState.ExitCode()
	}

	// Stream output lines if callback provided
	if onOutput != nil {
		for _, line := range strings.Split(result.Stdout, "\n") {
			line = strings.TrimSpace(line)
			if line != "" {
				onOutput(line)
			}
		}
		if result.Stderr != "" {
			for _, line := range strings.Split(result.Stderr, "\n") {
				line = strings.TrimSpace(line)
				if line != "" {
					onOutput("[STDERR] " + line)
				}
			}
		}
	}

	if err != nil {
		result.ErrorMessage = err.Error()
		return result, nil // Return result with error info, not an error
	}

	result.Success = result.ExitCode == 0
	return result, nil
}

// FindUploadScript looks for upload-plugin.ps1 in common locations.
func FindUploadScript(backendDir string) string {
	candidates := []string{
		filepath.Join(backendDir, "scripts", "upload-plugin.ps1"),
		filepath.Join(backendDir, "upload-plugin.ps1"),
		"scripts/upload-plugin.ps1",
		"upload-plugin.ps1",
	}

	for _, path := range candidates {
		if _, err := os.Stat(path); err == nil {
			absPath, _ := filepath.Abs(path)
			return absPath
		}
	}

	return ""
}

// IsPowerShellAvailable checks if PowerShell is available on the system.
func IsPowerShellAvailable() bool {
	if runtime.GOOS != "windows" {
		return false
	}

	_, err := exec.LookPath("powershell.exe")
	return err == nil
}
