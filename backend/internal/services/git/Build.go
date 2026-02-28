// Package git — Build and PullAndBuild operations.
package git

import (
	"bytes"
	"context"
	"os/exec"
	"runtime"
	"time"

	"wp-plugin-publish/internal/ws"
	"wp-plugin-publish/pkg/apperror"
)

// Build executes the build command for a plugin
func (s *Service) Build(ctx context.Context, pluginId int64) apperror.Result[BuildResult] {
	startTime := time.Now()

	s.log.Info("Starting build", "pluginId", pluginId)

	pResult := s.pluginService.GetById(ctx, pluginId)
	if pResult.HasError() {
		return apperror.Fail[BuildResult](pResult.AppError())
	}

	p := pResult.Value()

	configResult := s.GetConfig(ctx, pluginId)
	if configResult.HasError() {
		return apperror.Fail[BuildResult](configResult.AppError())
	}

	config := configResult.Value()
	isBuildMissing := !config.BuildEnabled || config.BuildCommand == ""

	if isBuildMissing {
		return apperror.FailNew[BuildResult](apperror.ErrBuildNotConfigured, "build not configured for this plugin")
	}

	result := BuildResult{
		PluginId:   pluginId,
		PluginName: p.Name,
		Command:    config.BuildCommand,
		BuiltAt:    time.Now(),
	}

	ws.Broadcast(s.wsHub, ws.EventBuildStarted, ws.BuildStartedData{
		PluginId:   pluginId,
		PluginName: p.Name,
		Command:    config.BuildCommand,
	})

	return s.executeBuild(ctx, p.Path, &result, startTime)
}

// executeBuild runs the build command and populates the result.
func (s *Service) executeBuild(
	ctx context.Context,
	path string,
	result *BuildResult,
	startTime time.Time,
) apperror.Result[BuildResult] {
	cmd := buildShellCommand(ctx, result.Command)
	cmd.Dir = path

	var stdout, stderr bytes.Buffer
	cmd.Stdout = &stdout
	cmd.Stderr = &stderr

	err := cmd.Run()
	result.Duration = time.Since(startTime).Milliseconds()
	result.Output = stdout.String()

	if err != nil {
		result.IsSuccess = false
		result.Error = stderr.String()

		exitErr, ok := err.(*exec.ExitError)
		if ok {
			result.ExitCode = exitErr.ExitCode()
		}

		ws.Broadcast(s.wsHub, ws.EventBuildFailed, ws.BuildFailedData{
			PluginId: result.PluginId,
			Error:    result.Error,
			ExitCode: result.ExitCode,
		})

		return apperror.FailWrap[BuildResult](err, apperror.ErrBuildFailed, result.Error)
	}

	result.IsSuccess = true
	result.ExitCode = 0

	ws.Broadcast(s.wsHub, ws.EventBuildComplete, ws.BuildCompleteData{
		PluginId:  result.PluginId,
		IsSuccess: true,
		Duration:  result.Duration,
	})

	s.log.Info("Build complete", "plugin", result.PluginName, "pluginId", result.PluginId, "duration", result.Duration)

	return apperror.Ok(*result)
}

// buildShellCommand creates the platform-appropriate shell command.
func buildShellCommand(ctx context.Context, command string) *exec.Cmd {
	isWindows := runtime.GOOS == "windows"

	if isWindows {
		return exec.CommandContext(ctx, "powershell", "-ExecutionPolicy", "Bypass", "-Command", command)
	}

	return exec.CommandContext(ctx, "bash", "-c", command)
}

// PullAndBuild performs git pull followed by build
func (s *Service) PullAndBuild(ctx context.Context, pluginId int64) apperror.Result[PullAndBuildResult] {
	s.log.Info("Starting pull and build", "pluginId", pluginId)

	pullResult := s.Pull(ctx, pluginId)
	if pullResult.HasError() {
		return apperror.Fail[PullAndBuildResult](pullResult.AppError())
	}

	pull := pullResult.Value()
	combined := PullAndBuildResult{Pull: pull}

	shouldBuild := pull.IsSuccess && pull.FilesChanged > 0

	if shouldBuild {
		buildResult := s.Build(ctx, pluginId)
		if buildResult.HasError() {
			return apperror.Fail[PullAndBuildResult](buildResult.AppError())
		}

		v := buildResult.Value()
		combined.Build = &v
	}

	return apperror.Ok(combined)
}
