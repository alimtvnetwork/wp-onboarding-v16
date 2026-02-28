// Package wordpress provides PowerShell-based plugin upload execution.
package wordpress

import (
	"encoding/json"
	"fmt"
	"os/exec"
	"runtime"

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
	isNotWindows := runtime.GOOS != "windows"

	if isNotWindows {

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
	isCallbackMissing := onOutput == nil

	if isCallbackMissing {

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
	isNotWindows := runtime.GOOS != "windows"

	if isNotWindows {

		return nil, apperror.New(apperror.ErrPublishPlatform, "PowerShell upload only available on Windows")
	}

	args := buildPsDirectArgs(input)

	hasOutputCallback := input.OnOutput != nil
	if hasOutputCallback {
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

	hasSlug := input.Slug != ""
	if hasSlug {
		args = append(args, "-Slug", input.Slug)
	}

	if input.IsActivate {
		args = append(args, "-Activate")
	}

	return args
}

// FindUploadScript looks for upload-plugin.ps1 in common locations.
func FindUploadScript(backendDir string) string {
	candidates := buildScriptCandidates(backendDir)

	for _, path := range candidates {
		resolved := resolveScriptPath(path)
		hasResolved := resolved != ""

		if hasResolved {

			return resolved
		}
	}

	return ""
}

// buildScriptCandidates builds the list of candidate script paths.
func buildScriptCandidates(backendDir string) []string {
	var candidates []string

	p1, err1 := pathutil.Join(backendDir, "scripts", "upload-plugin.ps1")

	if err1 == nil {
		candidates = append(candidates, p1)
	}

	p2, err2 := pathutil.Join(backendDir, "upload-plugin.ps1")

	if err2 == nil {
		candidates = append(candidates, p2)
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
	isNotWindows := runtime.GOOS != "windows"

	if isNotWindows {

		return false
	}

	_, err := exec.LookPath("powershell.exe")
	isAvailable := err == nil

	return isAvailable
}
