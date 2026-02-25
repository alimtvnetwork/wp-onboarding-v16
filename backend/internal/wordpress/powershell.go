// Package wordpress provides PowerShell-based plugin upload execution.
package wordpress

import (
	"bytes"
	"encoding/json"
	"fmt"
	"os"
	"os/exec"
	"runtime"
	"strings"

	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/pathutil"
)

// PowerShellConfig holds configuration for the PowerShell uploader script.
type PowerShellConfig struct {
	PluginFolderPath     string `json:"pluginFolderPath"`     // external key (PowerShell script config)
	WordPressSiteURL     string `json:"wordPressSiteURL"`     // external key
	Username             string `json:"username"`             // external key
	AppPassword          string `json:"appPassword"`          // external key
	PluginSlug           string `json:"pluginSlug,omitempty"` // external key
	OutputZipPath        string `json:"outputZipPath,omitempty"` // external key
	ActivateAfterInstall bool   `json:"activateAfterInstall"` // external key
	DeleteZipAfterUpload bool   `json:"deleteZipAfterUpload"` // external key
}

// PowerShellResult holds the result of a PowerShell upload execution.
type PowerShellResult struct {
	IsSuccess    bool
	ExitCode     int
	Stdout       string
	Stderr       string
	ErrorMessage string `json:",omitempty"`
	Plugin       string `json:",omitempty"`
	IsActivated  bool   `json:",omitempty"`
}

// RunPowerShellUpload executes the upload-plugin.ps1 script with the given configuration.
// It passes config as inline JSON for direct invocation from the app.
func RunPowerShellUpload(scriptPath string, cfg PowerShellConfig, onOutput func(line string)) (*PowerShellResult, error) {
	// Only available on Windows
	if runtime.GOOS != "windows" {
		return nil, apperror.New(apperror.ErrPublishPlatform, "PowerShell upload only available on Windows")
	}

	result := &PowerShellResult{
		Success:  false,
		ExitCode: -1,
	}

	// Serialize config to JSON
	configBytes, err := json.Marshal(cfg)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrPublishConfig, "failed to marshal PowerShell config")
	}

	// Build PowerShell command with inline JSON config
	args := []string{
		"-ExecutionPolicy", "Bypass",
		"-NoProfile",
		"-NonInteractive",
		"-File", scriptPath,
		"-JsonConfig", string(configBytes),
		"-Quiet",
	}

	cmd := exec.Command("powershell.exe", args...)

	// Capture stdout and stderr
	var stdout, stderr bytes.Buffer
	cmd.Stdout = &stdout
	cmd.Stderr = &stderr

	// Run the command
	if onOutput != nil {
		onOutput(fmt.Sprintf("Executing PowerShell upload script..."))
		onOutput(fmt.Sprintf("  Plugin: %s", cfg.PluginFolderPath))
		onOutput(fmt.Sprintf("  Site: %s", cfg.WordPressSiteURL))
	}

	err = cmd.Run()

	result.Stdout = stdout.String()
	result.Stderr = stderr.String()

	if cmd.ProcessState != nil {
		result.ExitCode = cmd.ProcessState.ExitCode()
	}

	// Parse JSON output from quiet mode
	if result.Stdout != "" {
		var jsonResult struct {
			Success   bool   `json:"success"`   // external key (PowerShell JSON output)
			Plugin    string `json:"plugin"`     // external key
			Activated bool   `json:"activated"`  // external key
			Error     string `json:"error"`      // external key
		}
		if parseErr := json.Unmarshal([]byte(strings.TrimSpace(result.Stdout)), &jsonResult); parseErr == nil {
			result.IsSuccess = jsonResult.Success
			result.Plugin = jsonResult.Plugin
			result.IsActivated = jsonResult.Activated
			result.ErrorMessage = jsonResult.Error
		}
	}

	// Stream output lines if callback provided (for non-quiet debug)
	if onOutput != nil && result.Stderr != "" {
		for _, line := range strings.Split(result.Stderr, "\n") {
			line = strings.TrimSpace(line)
			if line != "" {
				onOutput("[PS] " + line)
			}
		}
	}

	if err != nil && result.ErrorMessage == "" {
		result.ErrorMessage = err.Error()
	}

	if result.ExitCode == 0 && result.ErrorMessage == "" {
		result.IsSuccess = true
	}

	return result, nil
}

// RunPowerShellUploadDirect executes the upload script with direct command-line parameters.
// This is simpler than JSON config and works well for programmatic invocation.
func RunPowerShellUploadDirect(scriptPath, pluginPath, siteUrl, username, password, slug string, activate bool, onOutput func(line string)) (*PowerShellResult, error) {
	if runtime.GOOS != "windows" {
		return nil, apperror.New(apperror.ErrPublishPlatform, "PowerShell upload only available on Windows")
	}

	result := &PowerShellResult{
		Success:  false,
		ExitCode: -1,
	}

	args := []string{
		"-ExecutionPolicy", "Bypass",
		"-NoProfile",
		"-NonInteractive",
		"-File", scriptPath,
		"-PluginPath", pluginPath,
		"-SiteUrl", siteUrl,
		"-User", username,
		"-Password", password,
		"-Quiet",
		"-DeleteZip",
	}

	if slug != "" {
		args = append(args, "-Slug", slug)
	}

	if activate {
		args = append(args, "-Activate")
	}

	cmd := exec.Command("powershell.exe", args...)

	var stdout, stderr bytes.Buffer
	cmd.Stdout = &stdout
	cmd.Stderr = &stderr

	if onOutput != nil {
		onOutput("Executing PowerShell upload...")
	}

	err := cmd.Run()

	result.Stdout = stdout.String()
	result.Stderr = stderr.String()

	if cmd.ProcessState != nil {
		result.ExitCode = cmd.ProcessState.ExitCode()
	}

	// Parse JSON output
	if result.Stdout != "" {
		var jsonResult struct {
			Success   bool   `json:"success"`   // external key (PowerShell JSON output)
			Plugin    string `json:"plugin"`     // external key
			Activated bool   `json:"activated"`  // external key
			Error     string `json:"error"`      // external key
		}
		if parseErr := json.Unmarshal([]byte(strings.TrimSpace(result.Stdout)), &jsonResult); parseErr == nil {
			result.IsSuccess = jsonResult.Success
			result.Plugin = jsonResult.Plugin
			result.IsActivated = jsonResult.Activated
			result.ErrorMessage = jsonResult.Error
		}
	}

	if err != nil && result.ErrorMessage == "" {
		result.ErrorMessage = err.Error()
	}

	if result.ExitCode == 0 && result.ErrorMessage == "" {
		result.IsSuccess = true
	}

	return result, nil
}

// FindUploadScript looks for upload-plugin.ps1 in common locations.
func FindUploadScript(backendDir string) string {
	// Build candidates, skipping paths that fail resolution
	var candidates []string
	if p, err := pathutil.Join(backendDir, "scripts", "upload-plugin.ps1"); err == nil {
		candidates = append(candidates, p)
	}
	if p, err := pathutil.Join(backendDir, "upload-plugin.ps1"); err == nil {
		candidates = append(candidates, p)
	}
	candidates = append(candidates, "scripts/upload-plugin.ps1", "upload-plugin.ps1")

	for _, path := range candidates {
		if _, err := os.Stat(path); err == nil {
			absPath, err := pathutil.ToAbsolute(path)
			if err != nil {
				return path // Return unresolved if absolute fails
			}
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
