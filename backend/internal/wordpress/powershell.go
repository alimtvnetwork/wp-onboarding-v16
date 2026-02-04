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
	PluginSlug           string `json:"pluginSlug,omitempty"`
	OutputZipPath        string `json:"outputZipPath,omitempty"`
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
	Plugin       string `json:"plugin,omitempty"`
	Activated    bool   `json:"activated,omitempty"`
}

// RunPowerShellUpload executes the upload-plugin.ps1 script with the given configuration.
// It passes config as inline JSON for direct invocation from the app.
func RunPowerShellUpload(scriptPath string, cfg PowerShellConfig, onOutput func(line string)) (*PowerShellResult, error) {
	// Only available on Windows
	if runtime.GOOS != "windows" {
		return nil, fmt.Errorf("PowerShell upload only available on Windows")
	}

	result := &PowerShellResult{
		Success:  false,
		ExitCode: -1,
	}

	// Serialize config to JSON
	configBytes, err := json.Marshal(cfg)
	if err != nil {
		return nil, fmt.Errorf("marshal config: %w", err)
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
		var jsonResult map[string]interface{}
		if parseErr := json.Unmarshal([]byte(strings.TrimSpace(result.Stdout)), &jsonResult); parseErr == nil {
			if success, ok := jsonResult["success"].(bool); ok {
				result.Success = success
			}
			if plugin, ok := jsonResult["plugin"].(string); ok {
				result.Plugin = plugin
			}
			if activated, ok := jsonResult["activated"].(bool); ok {
				result.Activated = activated
			}
			if errMsg, ok := jsonResult["error"].(string); ok {
				result.ErrorMessage = errMsg
			}
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
		result.Success = true
	}

	return result, nil
}

// RunPowerShellUploadDirect executes the upload script with direct command-line parameters.
// This is simpler than JSON config and works well for programmatic invocation.
func RunPowerShellUploadDirect(scriptPath, pluginPath, siteUrl, username, password, slug string, activate bool, onOutput func(line string)) (*PowerShellResult, error) {
	if runtime.GOOS != "windows" {
		return nil, fmt.Errorf("PowerShell upload only available on Windows")
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
		var jsonResult map[string]interface{}
		if parseErr := json.Unmarshal([]byte(strings.TrimSpace(result.Stdout)), &jsonResult); parseErr == nil {
			if success, ok := jsonResult["success"].(bool); ok {
				result.Success = success
			}
			if plugin, ok := jsonResult["plugin"].(string); ok {
				result.Plugin = plugin
			}
			if activated, ok := jsonResult["activated"].(bool); ok {
				result.Activated = activated
			}
			if errMsg, ok := jsonResult["error"].(string); ok {
				result.ErrorMessage = errMsg
			}
		}
	}

	if err != nil && result.ErrorMessage == "" {
		result.ErrorMessage = err.Error()
	}

	if result.ExitCode == 0 && result.ErrorMessage == "" {
		result.Success = true
	}

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
