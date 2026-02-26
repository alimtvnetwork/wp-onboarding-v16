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

// psJsonOutput is the typed struct for parsing PowerShell quiet-mode JSON.
type psJsonOutput struct {
	Success   bool   `json:"success"`   // external key (PowerShell JSON output)
	Plugin    string `json:"plugin"`    // external key
	Activated bool   `json:"activated"` // external key
	Error     string `json:"error"`     // external key
}

// RunPowerShellUpload executes the upload-plugin.ps1 script with the given configuration.
// It passes config as inline JSON for direct invocation from the app.
func RunPowerShellUpload(scriptPath string, cfg PowerShellConfig, onOutput func(line string)) (*PowerShellResult, error) {
	if runtime.GOOS != "windows" {
		return nil, apperror.New(apperror.ErrPublishPlatform, "PowerShell upload only available on Windows")
	}

	configBytes, err := json.Marshal(cfg)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrPublishConfig, "failed to marshal PowerShell config")
	}

	args := buildPsJsonConfigArgs(scriptPath, string(configBytes))
	emitPsStartLog(onOutput, cfg.PluginFolderPath, cfg.WordPressSiteURL)

	return executePowerShellCommand(args, onOutput)
}

// buildPsJsonConfigArgs constructs PowerShell arguments for JSON config mode.
func buildPsJsonConfigArgs(scriptPath, jsonConfig string) []string {
	return []string{
		"-ExecutionPolicy", "Bypass",
		"-NoProfile",
		"-NonInteractive",
		"-File", scriptPath,
		"-JsonConfig", jsonConfig,
		"-Quiet",
	}
}

// emitPsStartLog logs the start of a PowerShell upload if callback is set.
func emitPsStartLog(onOutput func(line string), pluginPath, siteURL string) {
	if onOutput == nil {
		return
	}

	onOutput(fmt.Sprintf("Executing PowerShell upload script..."))
	onOutput(fmt.Sprintf("  Plugin: %s", pluginPath))
	onOutput(fmt.Sprintf("  Site: %s", siteURL))
}

// DirectUploadInput holds parameters for direct PowerShell upload invocation.
type DirectUploadInput struct {
	ScriptPath string
	PluginPath string
	SiteUrl    string
	Username   string
	Password   string
	Slug       string
	IsActivate bool
	OnOutput   func(line string)
}

// RunPowerShellUploadDirect executes the upload script with direct command-line parameters.
// This is simpler than JSON config and works well for programmatic invocation.
func RunPowerShellUploadDirect(input DirectUploadInput) (*PowerShellResult, error) {
	if runtime.GOOS != "windows" {
		return nil, apperror.New(apperror.ErrPublishPlatform, "PowerShell upload only available on Windows")
	}

	args := buildPsDirectArgs(input)

	if input.OnOutput != nil {
		input.OnOutput("Executing PowerShell upload...")
	}

	return executePowerShellCommand(args, input.OnOutput)
}

// buildPsDirectArgs constructs PowerShell arguments for direct parameter mode.
func buildPsDirectArgs(input DirectUploadInput) []string {
	args := []string{
		"-ExecutionPolicy", "Bypass",
		"-NoProfile",
		"-NonInteractive",
		"-File", input.ScriptPath,
		"-PluginPath", input.PluginPath,
		"-SiteUrl", input.SiteUrl,
		"-User", input.Username,
		"-Password", input.Password,
		"-Quiet",
		"-DeleteZip",
	}

	if input.Slug != "" {
		args = append(args, "-Slug", input.Slug)
	}

	if input.IsActivate {
		args = append(args, "-Activate")
	}

	return args
}

// executePowerShellCommand runs a PowerShell command and processes the output.
func executePowerShellCommand(args []string, onOutput func(line string)) (*PowerShellResult, error) {
	cmd := exec.Command("powershell.exe", args...)

	var stdout, stderr bytes.Buffer
	cmd.Stdout = &stdout
	cmd.Stderr = &stderr

	err := cmd.Run()

	result := buildPsResult(cmd, &stdout, &stderr)
	parsePsJsonOutput(result)
	streamPsStderr(result, onOutput)
	finalizePsResult(result, err)

	return result, nil
}

// buildPsResult creates a PowerShellResult from command output.
func buildPsResult(cmd *exec.Cmd, stdout, stderr *bytes.Buffer) *PowerShellResult {
	result := &PowerShellResult{
		IsSuccess: false,
		ExitCode:  -1,
		Stdout:    stdout.String(),
		Stderr:    stderr.String(),
	}

	if cmd.ProcessState != nil {
		result.ExitCode = cmd.ProcessState.ExitCode()
	}

	return result
}

// parsePsJsonOutput parses JSON from PowerShell stdout quiet mode.
func parsePsJsonOutput(result *PowerShellResult) {
	if result.Stdout == "" {
		return
	}

	var jsonResult psJsonOutput
	if err := json.Unmarshal([]byte(strings.TrimSpace(result.Stdout)), &jsonResult); err != nil {
		return
	}

	result.IsSuccess = jsonResult.Success
	result.Plugin = jsonResult.Plugin
	result.IsActivated = jsonResult.Activated
	result.ErrorMessage = jsonResult.Error
}

// streamPsStderr streams stderr lines to the output callback.
func streamPsStderr(result *PowerShellResult, onOutput func(line string)) {
	if onOutput == nil || result.Stderr == "" {
		return
	}

	for _, line := range strings.Split(result.Stderr, "\n") {
		line = strings.TrimSpace(line)

		if line != "" {
			onOutput("[PS] " + line)
		}
	}
}

// finalizePsResult sets final success/error state on the result.
func finalizePsResult(result *PowerShellResult, err error) {
	if err != nil && result.ErrorMessage == "" {
		result.ErrorMessage = err.Error()
	}

	if result.ExitCode == 0 && result.ErrorMessage == "" {
		result.IsSuccess = true
	}
}

// FindUploadScript looks for upload-plugin.ps1 in common locations.
func FindUploadScript(backendDir string) string {
	candidates := buildScriptCandidates(backendDir)

	for _, path := range candidates {
		if resolved := resolveScriptPath(path); resolved != "" {
			return resolved
		}
	}

	return ""
}

// buildScriptCandidates builds the list of candidate script paths.
func buildScriptCandidates(backendDir string) []string {
	var candidates []string

	if p, err := pathutil.Join(backendDir, "scripts", "upload-plugin.ps1"); err == nil {
		candidates = append(candidates, p)
	}

	if p, err := pathutil.Join(backendDir, "upload-plugin.ps1"); err == nil {
		candidates = append(candidates, p)
	}

	candidates = append(candidates, "scripts/upload-plugin.ps1", "upload-plugin.ps1")

	return candidates
}

// resolveScriptPath checks if a path exists and returns its absolute form.
func resolveScriptPath(path string) string {
	if pathutil.IsFileMissing(path) {
		return ""
	}

	absPath, err := pathutil.ToAbsolute(path)
	if err != nil {
		return path
	}

	return absPath
}

// IsPowerShellAvailable checks if PowerShell is available on the system.
func IsPowerShellAvailable() bool {
	if runtime.GOOS != "windows" {
		return false
	}

	_, err := exec.LookPath("powershell.exe")
	return err == nil
}
