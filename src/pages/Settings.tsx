import { useState, useEffect } from "react";
import { Card, CardContent } from "@/components/ui/card";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { useSettings, useSaveSettings } from "@/hooks/useSettings";
import { api } from "@/lib/api";
import { Eye, Archive, Palette, Loader2, Upload, Bug, RotateCcw, Zap, Info, ChevronRight, Shield, Database } from "lucide-react";
import { AboutPanel } from "@/components/settings/AboutPanel";
import { SnapshotSettingsTab } from "@/components/settings/SnapshotSettingsTab";
import { ThemeSelector } from "@/components/settings/ThemeSelector";
import { useLocation } from "react-router-dom";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import { logger } from "@/lib/logger";
import { configureRetry } from "@/lib/retry";
import { useExecutionLoggerStore } from "@/hooks/useExecutionLogger";
import { configureCircuitBreaker, circuitBreaker } from "@/lib/circuitBreaker";
import { toast } from "sonner";
import { cn } from "@/lib/utils";
import { useErrorStore } from "@/stores/errorStore";

type SettingsTab = "watching" | "backups" | "snapshots" | "publish" | "appearance" | "developer" | "about";

interface TabItem {
  id: SettingsTab;
  label: string;
  icon: React.ReactNode;
}

const TABS: TabItem[] = [
  { id: "watching", label: "File Watching", icon: <Eye className="h-4 w-4" /> },
  { id: "backups", label: "Backups", icon: <Archive className="h-4 w-4" /> },
  { id: "snapshots", label: "Snapshots", icon: <Database className="h-4 w-4" /> },
  { id: "publish", label: "Publish", icon: <Upload className="h-4 w-4" /> },
  { id: "appearance", label: "Appearance", icon: <Palette className="h-4 w-4" /> },
  { id: "developer", label: "Developer", icon: <Bug className="h-4 w-4" /> },
  { id: "about", label: "About", icon: <Info className="h-4 w-4" /> },
];

export default function Settings() {
  const { data: settings, isLoading } = useSettings();
  const saveSettings = useSaveSettings();
  const location = useLocation();
  const { captureError, openErrorModal } = useErrorStore();
  
  // Active tab
  const [activeTab, setActiveTab] = useState<SettingsTab>(() => {
    if (location.hash === "#about") return "about";
    return "watching";
  });
  
  // Upload mode state (persisted to localStorage)
  const [uploadMode, setUploadMode] = useState<"file" | "zip">(() => {
    try {
      const saved = localStorage.getItem("wppp_upload_mode");
      return saved === "zip" ? "zip" : "file";
    } catch {
      return "file";
    }
  });
  
  // Keep ZIP files after publish (for debugging)
  const [keepZipFiles, setKeepZipFiles] = useState<boolean>(() => {
    try {
      const saved = localStorage.getItem("wppp_keep_zip_files");
      return saved === "true";
    } catch {
      return false;
    }
  });
  
  // Auto-backup state (default: disabled)
  const [autoBackup, setAutoBackup] = useState<boolean>(() => {
    try {
      const saved = localStorage.getItem("wppp_auto_backup");
      return saved === "true";
    } catch {
      return false;
    }
  });
  
  // Developer settings state
  const [frontendDebugMode, setFrontendDebugMode] = useState(false);
  const [retryMaxAttempts, setRetryMaxAttempts] = useState(3);
  const [retryInitialDelayMs, setRetryInitialDelayMs] = useState(1000);
  const [circuitBreakerThreshold, setCircuitBreakerThreshold] = useState(5);
  const [circuitBreakerCooldownMs, setCircuitBreakerCooldownMs] = useState(60000);
  
  // Response Debug settings (backend-persisted)
  const [includeErrors, setIncludeErrors] = useState(true);
  const [includeStackTrace, setIncludeStackTrace] = useState(true);
  const [includeMethodsStack, setIncludeMethodsStack] = useState(true);
  const [stackTraceDepth, setStackTraceDepth] = useState(20);
  const [phpStackTraceDepth, setPhpStackTraceDepth] = useState(0);
  const [maxStackFrames, setMaxStackFrames] = useState(20);
  const [defaultPerPage, setDefaultPerPage] = useState(10);
  
  // Appearance settings
  const [theme, setTheme] = useState<string>("system");
  const [compactMode, setCompactMode] = useState(false);
  
  // File watching settings
  const [pollInterval, setPollInterval] = useState("5000");
  const [debounceDelay, setDebounceDelay] = useState("500");
  
  // Publish settings
  const [uploaderHelperPath, setUploaderHelperPath] = useState("");
  
  // Backup settings
  const [retentionDays, setRetentionDays] = useState("30");
  const [maxBackups, setMaxBackups] = useState("10");
  
  // Initialize from settings when loaded
  useEffect(() => {
    if (settings?.logging) {
      setFrontendDebugMode(settings.logging.frontendDebugMode ?? false);
      setRetryMaxAttempts(settings.logging.retryMaxAttempts ?? 3);
      setRetryInitialDelayMs(settings.logging.retryInitialDelayMs ?? 1000);
      setCircuitBreakerThreshold(settings.logging.circuitBreakerThreshold ?? 5);
      setCircuitBreakerCooldownMs(settings.logging.circuitBreakerCooldownMs ?? 60000);
      
      // Apply settings to utilities
      logger.configure({ 
        enabled: true, 
        minLevel: settings.logging.frontendDebugMode ? 'trace' : 'info',
        consoleOutput: true 
      });
      // Sync execution logger state with debug mode setting
      useExecutionLoggerStore.getState().setEnabled(settings.logging.frontendDebugMode ?? false);
      configureRetry({
        maxAttempts: settings.logging.retryMaxAttempts ?? 3,
        initialDelayMs: settings.logging.retryInitialDelayMs ?? 1000,
      });
      configureCircuitBreaker({
        failureThreshold: settings.logging.circuitBreakerThreshold ?? 5,
        cooldownMs: settings.logging.circuitBreakerCooldownMs ?? 60000,
      });
    }
    if (settings?.responseDebug) {
      setIncludeErrors(settings.responseDebug.includeErrors ?? true);
      setIncludeStackTrace(settings.responseDebug.includeStackTrace ?? true);
      setIncludeMethodsStack(settings.responseDebug.includeMethodsStack ?? true);
      setMaxStackFrames(settings.responseDebug.maxStackFrames ?? 20);
    }
    if (settings?.logging) {
      setStackTraceDepth(settings.logging.stackTraceDepth ?? 20);
      setPhpStackTraceDepth(settings.logging.phpStackTraceDepth ?? 0);
    }
    if (settings?.pagination) {
      setDefaultPerPage(settings.pagination.defaultPerPage ?? 10);
    }
    if (settings?.publish) {
      setUploaderHelperPath(settings.publish.uploaderHelperPath ?? "");
    }
  }, [settings]);
  
  // Handle hash change for about
  useEffect(() => {
    if (location.hash === "#about") {
      setActiveTab("about");
    }
  }, [location.hash]);
  
  // Auto-save helper with toast notifications
  const saveWithToast = (key: string, value: unknown, label: string) => {
    try {
      localStorage.setItem(key, String(value));
      toast.success(`${label} saved`, {
        style: {
          background: "linear-gradient(135deg, hsl(142 76% 36%) 0%, hsl(142 76% 30%) 100%)",
          color: "white",
          border: "none",
        },
      });
    } catch (error) {
      const captured = captureError(
        {
          code: "E9010",
          message: `Failed to save ${label}`,
          details: String(error),
          timestamp: new Date().toISOString(),
        },
        {
          endpoint: "localStorage",
          method: "POST",
          context: {
            source: "Settings.saveWithToast",
            triggerComponent: "Settings",
            triggerAction: "save_setting",
            key,
            value,
          },
        }
      );
      toast.error(`Failed to save ${label}`, {
        action: {
          label: "Details",
          onClick: () => openErrorModal(captured),
        },
      });
    }
  };
  
  const handleUploadModeChange = (value: string) => {
    const mode = value as "file" | "zip";
    setUploadMode(mode);
    saveWithToast("wppp_upload_mode", mode, "Upload mode");
  };
  
  const handleKeepZipFilesChange = (enabled: boolean) => {
    setKeepZipFiles(enabled);
    saveWithToast("wppp_keep_zip_files", enabled, "Keep ZIP files");
  };
  
  const handleAutoBackupChange = (enabled: boolean) => {
    setAutoBackup(enabled);
    saveWithToast("wppp_auto_backup", enabled, "Auto-backup setting");
  };
  
  const handleFrontendDebugModeChange = (enabled: boolean) => {
    setFrontendDebugMode(enabled);
    logger.configure({ 
      enabled: true, 
      minLevel: enabled ? 'trace' : 'info',
      consoleOutput: true 
    });
    // Enable/disable the React execution logger (call chain tracking)
    useExecutionLoggerStore.getState().setEnabled(enabled);
    toast.success(`Debug mode ${enabled ? 'enabled' : 'disabled'} (execution logger ${enabled ? 'on' : 'off'})`, {
      style: {
        background: "linear-gradient(135deg, hsl(142 76% 36%) 0%, hsl(142 76% 30%) 100%)",
        color: "white",
        border: "none",
      },
    });
  };
  
  const handleRetrySettingsChange = () => {
    configureRetry({
      maxAttempts: retryMaxAttempts,
      initialDelayMs: retryInitialDelayMs,
    });
    toast.success("Retry settings saved", {
      style: {
        background: "linear-gradient(135deg, hsl(142 76% 36%) 0%, hsl(142 76% 30%) 100%)",
        color: "white",
        border: "none",
      },
    });
  };
  
  const handleCircuitBreakerSettingsChange = () => {
    configureCircuitBreaker({
      failureThreshold: circuitBreakerThreshold,
      cooldownMs: circuitBreakerCooldownMs,
    });
    toast.success("Circuit breaker settings saved", {
      style: {
        background: "linear-gradient(135deg, hsl(142 76% 36%) 0%, hsl(142 76% 30%) 100%)",
        color: "white",
        border: "none",
      },
    });
  };
  
  const handleResetCircuits = () => {
    circuitBreaker.resetAll();
    toast.success("All circuits reset", {
      style: {
        background: "linear-gradient(135deg, hsl(142 76% 36%) 0%, hsl(142 76% 30%) 100%)",
        color: "white",
        border: "none",
      },
    });
  };
  
  const handleResponseDebugSave = (patch: { includeErrors?: boolean; includeStackTrace?: boolean; includeMethodsStack?: boolean; maxStackFrames?: number }) => {
    const updated = {
      includeErrors: patch.includeErrors ?? includeErrors,
      includeStackTrace: patch.includeStackTrace ?? includeStackTrace,
      includeMethodsStack: patch.includeMethodsStack ?? includeMethodsStack,
      maxStackFrames: patch.maxStackFrames ?? maxStackFrames,
    };
    saveSettings.mutate(
      { responseDebug: updated },
      {
        onSuccess: () => {
          toast.success("Response debug settings saved", {
            style: {
              background: "linear-gradient(135deg, hsl(142 76% 36%) 0%, hsl(142 76% 30%) 100%)",
              color: "white",
              border: "none",
            },
          });
        },
        onError: (err) => {
          toast.error(`Failed to save: ${err.message}`);
        },
      }
    );
  };
  
  const handlePollIntervalChange = (value: string) => {
    setPollInterval(value);
    saveWithToast("wppp_poll_interval", value, "Poll interval");
  };
  
  const handleDebounceDelayChange = (value: string) => {
    setDebounceDelay(value);
    saveWithToast("wppp_debounce_delay", value, "Debounce delay");
  };
  
  const handleRetentionDaysChange = (value: string) => {
    setRetentionDays(value);
    saveWithToast("wppp_retention_days", value, "Retention days");
  };
  
  const handleMaxBackupsChange = (value: string) => {
    setMaxBackups(value);
    saveWithToast("wppp_max_backups", value, "Max backups");
  };
  
  const handleThemeChange = (value: string) => {
    setTheme(value);
    saveWithToast("wppp_theme", value, "Theme");
  };
  
  const handleCompactModeChange = (enabled: boolean) => {
    setCompactMode(enabled);
    saveWithToast("wppp_compact_mode", enabled, "Compact mode");
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center py-12">
        <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
      </div>
    );
  }

  const renderTabContent = () => {
    switch (activeTab) {
      case "watching":
        return (
          <div className="space-y-4 sm:space-y-6">
            <div>
              <h2 className="text-base sm:text-lg font-semibold mb-1">File Watching</h2>
              <p className="text-xs sm:text-sm text-muted-foreground">Configure how files are monitored for changes</p>
            </div>
            
            <div className="space-y-4">
              <div className="space-y-2">
                <Label className="text-sm">Poll Interval</Label>
                <Select value={pollInterval} onValueChange={handlePollIntervalChange}>
                  <SelectTrigger className="h-9 sm:h-10">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="1000">1 second</SelectItem>
                    <SelectItem value="2000">2 seconds</SelectItem>
                    <SelectItem value="5000">5 seconds</SelectItem>
                    <SelectItem value="10000">10 seconds</SelectItem>
                  </SelectContent>
                </Select>
                <p className="text-xs text-muted-foreground">
                  How often to check for file changes
                </p>
              </div>

              <div className="space-y-2">
                <Label className="text-sm">Debounce Delay</Label>
                <Select value={debounceDelay} onValueChange={handleDebounceDelayChange}>
                  <SelectTrigger className="h-9 sm:h-10">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="100">100 ms</SelectItem>
                    <SelectItem value="250">250 ms</SelectItem>
                    <SelectItem value="500">500 ms</SelectItem>
                    <SelectItem value="1000">1 second</SelectItem>
                  </SelectContent>
                </Select>
                <p className="text-xs text-muted-foreground">
                  Wait time before processing changes
                </p>
              </div>
            </div>
          </div>
        );
        
      case "backups":
        return (
          <div className="space-y-4 sm:space-y-6">
            <div>
              <h2 className="text-base sm:text-lg font-semibold mb-1">Backups</h2>
              <p className="text-xs sm:text-sm text-muted-foreground">Configure backup behavior and retention</p>
            </div>
            
            <div className="space-y-4">
              <div className="flex items-center justify-between gap-3">
                <div className="min-w-0">
                  <Label className="text-sm">Auto-backup before publish</Label>
                  <p className="text-xs text-muted-foreground">
                    Always create a backup before publishing
                  </p>
                </div>
                <Switch 
                  checked={autoBackup}
                  onCheckedChange={handleAutoBackupChange}
                  className="shrink-0"
                />
              </div>

              <div className="space-y-2">
                <Label className="text-sm">Retention Days</Label>
                <Select value={retentionDays} onValueChange={handleRetentionDaysChange}>
                  <SelectTrigger className="h-9 sm:h-10">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="7">7 days</SelectItem>
                    <SelectItem value="14">14 days</SelectItem>
                    <SelectItem value="30">30 days</SelectItem>
                    <SelectItem value="60">60 days</SelectItem>
                  </SelectContent>
                </Select>
              </div>

              <div className="space-y-2">
                <Label className="text-sm">Max Backups per Plugin</Label>
                <Select value={maxBackups} onValueChange={handleMaxBackupsChange}>
                  <SelectTrigger className="h-9 sm:h-10">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="5">5 backups</SelectItem>
                    <SelectItem value="10">10 backups</SelectItem>
                    <SelectItem value="20">20 backups</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>
          </div>
        );
        
      case "snapshots":
        return <SnapshotSettingsTab />;
        
      case "publish":
        return (
          <div className="space-y-4 sm:space-y-6">
            <div>
              <h2 className="text-base sm:text-lg font-semibold mb-1">Publish Settings</h2>
              <p className="text-xs sm:text-sm text-muted-foreground">Configure how plugins are uploaded to sites</p>
            </div>
            
            <div className="space-y-3">
              <Label className="text-sm">Upload Mode</Label>
              <RadioGroup value={uploadMode} onValueChange={handleUploadModeChange} className="space-y-3">
                <div className="flex items-start space-x-3 p-3 rounded-lg border hover:bg-accent/50 transition-colors cursor-pointer">
                  <RadioGroupItem value="file" id="upload-file" className="mt-0.5" />
                  <div className="grid gap-0.5 leading-none min-w-0">
                    <Label htmlFor="upload-file" className="cursor-pointer font-medium text-sm">
                      File-by-file (default)
                    </Label>
                    <p className="text-xs text-muted-foreground">
                      Upload changed files individually. Better for small updates.
                    </p>
                  </div>
                </div>
                <div className="flex items-start space-x-3 p-3 rounded-lg border hover:bg-accent/50 transition-colors cursor-pointer">
                  <RadioGroupItem value="zip" id="upload-zip" className="mt-0.5" />
                  <div className="grid gap-0.5 leading-none min-w-0">
                    <Label htmlFor="upload-zip" className="cursor-pointer font-medium text-sm">
                      ZIP package
                    </Label>
                    <p className="text-xs text-muted-foreground">
                      Bundle all files into a ZIP. Faster for large plugins.
                    </p>
                  </div>
                </div>
              </RadioGroup>
            </div>

            {/* Keep ZIP files toggle */}
            <div className="flex items-center justify-between gap-3 pt-4 border-t">
              <div className="min-w-0">
                <Label className="text-sm">Keep ZIP Files</Label>
                <p className="text-xs text-muted-foreground">
                  Preserve ZIP files after publish (debugging)
                </p>
              </div>
              <Switch 
                checked={keepZipFiles}
                onCheckedChange={handleKeepZipFilesChange}
                className="shrink-0"
              />
            </div>

            {/* Show ZIP file tree in logs toggle */}
            <div className="flex items-center justify-between gap-3 pt-4 border-t">
              <div className="min-w-0">
                <Label className="text-sm">Show ZIP File Tree in Logs</Label>
                <p className="text-xs text-muted-foreground">
                  Include detailed file listing from ZIP packages in publish logs. Disable to reduce log verbosity.
                </p>
              </div>
              <Switch 
                checked={settings?.logging?.showZipFileTreeInLogs ?? false}
                onCheckedChange={(enabled) => {
                  saveSettings.mutate(
                    { logging: { showZipFileTreeInLogs: enabled } },
                    {
                      onSuccess: () => {
                        toast.success(`ZIP file tree in logs ${enabled ? 'enabled' : 'disabled'}`, {
                          style: {
                            background: "linear-gradient(135deg, hsl(142 76% 36%) 0%, hsl(142 76% 30%) 100%)",
                            color: "white",
                            border: "none",
                          },
                        });
                      },
                      onError: (err) => toast.error(`Failed to save: ${err.message}`),
                    }
                  );
                }}
                className="shrink-0"
              />
            </div>

            {/* Uploader Helper Path */}
            <div className="space-y-2 pt-4 border-t">
              <Label className="text-sm">Uploader Helper Plugin Path</Label>
              <div className="flex gap-2">
                <Input
                  value={uploaderHelperPath}
                  onChange={(e) => setUploaderHelperPath(e.target.value)}
                  placeholder="e.g. D:\wp-work\plugins\riseup-asia-uploader"
                  className="h-9 sm:h-10 font-mono text-xs"
                />
                <Button
                  size="sm"
                  variant="outline"
                  className="shrink-0"
                  onClick={() => {
                    saveSettings.mutate(
                      { publish: { uploaderHelperPath: uploaderHelperPath || undefined } },
                      {
                        onSuccess: () => {
                          toast.success("Uploader helper path saved", {
                            style: {
                              background: "linear-gradient(135deg, hsl(142 76% 36%) 0%, hsl(142 76% 30%) 100%)",
                              color: "white",
                              border: "none",
                            },
                          });
                        },
                        onError: (err) => toast.error(`Failed to save: ${err.message}`),
                      }
                    );
                  }}
                >
                  Save
                </Button>
              </div>
              <p className="text-xs text-muted-foreground">
                Absolute path to the uploader helper plugin folder. Used when deploying the uploader to WordPress sites. Leave empty to use the backend default.
              </p>
            </div>
          </div>
        );
        
      case "appearance":
        return <ThemeSelector />;
        
      case "developer":
        return (
          <div className="space-y-4 sm:space-y-6">
            <div>
              <h2 className="text-base sm:text-lg font-semibold mb-1">Developer & Debugging</h2>
              <p className="text-xs sm:text-sm text-muted-foreground">Advanced debugging and resilience settings</p>
            </div>
            
            {/* Frontend Debug Mode */}
            <div className="flex items-center justify-between gap-3">
              <div className="min-w-0">
                <Label className="text-sm">Frontend Debug Mode</Label>
                <p className="text-xs text-muted-foreground">
                  Log all function calls with file paths
                </p>
              </div>
              <Switch 
                checked={frontendDebugMode}
                onCheckedChange={handleFrontendDebugModeChange}
                className="shrink-0"
              />
            </div>

            {/* Retry Settings */}
            <div className="space-y-3 pt-4 border-t">
              <div className="flex items-center gap-2 text-sm font-medium">
                <RotateCcw className="h-4 w-4" />
                Retry Logic
              </div>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                <div className="space-y-1.5">
                  <Label htmlFor="retry-attempts" className="text-xs">Max Attempts</Label>
                  <Input
                    id="retry-attempts"
                    type="number"
                    min={1}
                    max={10}
                    value={retryMaxAttempts}
                    onChange={(e) => setRetryMaxAttempts(parseInt(e.target.value) || 3)}
                    onBlur={handleRetrySettingsChange}
                    className="h-9"
                  />
                </div>
                <div className="space-y-1.5">
                  <Label htmlFor="retry-delay" className="text-xs">Initial Delay (ms)</Label>
                  <Input
                    id="retry-delay"
                    type="number"
                    min={100}
                    max={10000}
                    step={100}
                    value={retryInitialDelayMs}
                    onChange={(e) => setRetryInitialDelayMs(parseInt(e.target.value) || 1000)}
                    onBlur={handleRetrySettingsChange}
                    className="h-9"
                  />
                </div>
              </div>
              <p className="text-xs text-muted-foreground">
                Failed API calls retry with exponential backoff
              </p>
            </div>

            {/* Circuit Breaker Settings */}
            <div className="space-y-3 pt-4 border-t">
              <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <div className="flex items-center gap-2 text-sm font-medium">
                  <Zap className="h-4 w-4" />
                  Circuit Breaker
                </div>
                <Button 
                  variant="outline" 
                  size="sm"
                  onClick={handleResetCircuits}
                  className="w-full sm:w-auto text-xs"
                >
                  Reset All Circuits
                </Button>
              </div>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                <div className="space-y-1.5">
                  <Label htmlFor="cb-threshold" className="text-xs">Failure Threshold</Label>
                  <Input
                    id="cb-threshold"
                    type="number"
                    min={1}
                    max={20}
                    value={circuitBreakerThreshold}
                    onChange={(e) => setCircuitBreakerThreshold(parseInt(e.target.value) || 5)}
                    onBlur={handleCircuitBreakerSettingsChange}
                    className="h-9"
                  />
                </div>
                <div className="space-y-1.5">
                  <Label htmlFor="cb-cooldown" className="text-xs">Cooldown (ms)</Label>
                  <Input
                    id="cb-cooldown"
                    type="number"
                    min={1000}
                    max={300000}
                    step={1000}
                    value={circuitBreakerCooldownMs}
                    onChange={(e) => setCircuitBreakerCooldownMs(parseInt(e.target.value) || 60000)}
                    onBlur={handleCircuitBreakerSettingsChange}
                    className="h-9"
                  />
                </div>
              </div>
              <p className="text-xs text-muted-foreground">
                Stops calling failing functions after threshold failures
              </p>
            </div>

            {/* Response Debug Settings (backend-persisted) */}
            <div className="space-y-3 pt-4 border-t">
              <div className="flex items-center gap-2 text-sm font-medium">
                <Shield className="h-4 w-4" />
                Response Debug (Backend)
              </div>
              <p className="text-xs text-muted-foreground">
                Controls which diagnostic sections the Go backend includes in the response envelope. Changes are persisted to config.json.
              </p>

              <div className="flex items-center justify-between gap-3">
                <div className="min-w-0">
                  <Label className="text-sm">Include Errors</Label>
                  <p className="text-xs text-muted-foreground">
                    Include the Errors block in responses when errors occur
                  </p>
                </div>
                <Switch
                  checked={includeErrors}
                  onCheckedChange={(v) => {
                    setIncludeErrors(v);
                    handleResponseDebugSave({ includeErrors: v });
                  }}
                  className="shrink-0"
                />
              </div>

              <div className="flex items-center justify-between gap-3">
                <div className="min-w-0">
                  <Label className="text-sm">Include Stack Traces</Label>
                  <p className="text-xs text-muted-foreground">
                    Expose Go and delegated service stack traces in the Errors block
                  </p>
                </div>
                <Switch
                  checked={includeStackTrace}
                  onCheckedChange={(v) => {
                    setIncludeStackTrace(v);
                    handleResponseDebugSave({ includeStackTrace: v });
                  }}
                  className="shrink-0"
                />
              </div>

              <div className="flex items-center justify-between gap-3">
                <div className="min-w-0">
                  <Label className="text-sm">Include Methods Stack</Label>
                  <p className="text-xs text-muted-foreground">
                    Include the MethodsStack traversal trace in responses
                  </p>
                </div>
                <Switch
                  checked={includeMethodsStack}
                  onCheckedChange={(v) => {
                    setIncludeMethodsStack(v);
                    handleResponseDebugSave({ includeMethodsStack: v });
                  }}
                  className="shrink-0"
                />
              </div>

              {/* Stack Trace Depth Settings */}
              <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4 pt-2">
                <div className="space-y-1.5">
                  <Label htmlFor="stack-trace-depth" className="text-xs">Go Stack Trace Depth</Label>
                  <Input
                    id="stack-trace-depth"
                    type="number"
                    min={1}
                    max={100}
                    value={stackTraceDepth}
                    onChange={(e) => setStackTraceDepth(parseInt(e.target.value) || 20)}
                    onBlur={() => {
                      saveSettings.mutate(
                        { logging: { stackTraceDepth } },
                        {
                          onSuccess: () => toast.success("Stack trace depth saved", {
                            style: {
                              background: "linear-gradient(135deg, hsl(142 76% 36%) 0%, hsl(142 76% 30%) 100%)",
                              color: "white",
                              border: "none",
                            },
                          }),
                          onError: (err) => toast.error(`Failed to save: ${err.message}`),
                        }
                      );
                    }}
                    className="h-9"
                  />
                  <p className="text-xs text-muted-foreground">
                    Max Go stack frames (default 20)
                  </p>
                </div>
                <div className="space-y-1.5">
                  <Label htmlFor="php-stack-trace-depth" className="text-xs">PHP Stack Trace Depth</Label>
                  <Input
                    id="php-stack-trace-depth"
                    type="number"
                    min={0}
                    max={500}
                    value={phpStackTraceDepth}
                    onChange={(e) => setPhpStackTraceDepth(parseInt(e.target.value) || 0)}
                    onBlur={() => {
                      saveSettings.mutate(
                        { logging: { phpStackTraceDepth } },
                        {
                          onSuccess: () => toast.success("PHP stack trace depth saved", {
                            style: {
                              background: "linear-gradient(135deg, hsl(142 76% 36%) 0%, hsl(142 76% 30%) 100%)",
                              color: "white",
                              border: "none",
                            },
                          }),
                          onError: (err) => toast.error(`Failed to save: ${err.message}`),
                        }
                      );
                    }}
                    className="h-9"
                  />
                  <p className="text-xs text-muted-foreground">
                    Max PHP frames (0 = unlimited)
                  </p>
                </div>
                <div className="space-y-1.5">
                  <Label htmlFor="max-stack-frames" className="text-xs">Max Response Frames</Label>
                  <Input
                    id="max-stack-frames"
                    type="number"
                    min={1}
                    max={100}
                    value={maxStackFrames}
                    onChange={(e) => setMaxStackFrames(parseInt(e.target.value) || 20)}
                    onBlur={() => handleResponseDebugSave({ maxStackFrames })}
                    className="h-9"
                  />
                  <p className="text-xs text-muted-foreground">
                    Max frames in envelope (default 20)
                  </p>
                </div>
              </div>

              <div className="space-y-1.5">
                <Label htmlFor="default-per-page" className="text-xs">Default Per Page</Label>
                <Input
                  id="default-per-page"
                  type="number"
                  min={1}
                  max={100}
                  value={defaultPerPage}
                  onChange={(e) => setDefaultPerPage(parseInt(e.target.value) || 10)}
                  onBlur={() => {
                    saveSettings.mutate(
                      { pagination: { defaultPerPage } },
                      {
                        onSuccess: () => toast.success("Pagination setting saved", {
                          style: {
                            background: "linear-gradient(135deg, hsl(142 76% 36%) 0%, hsl(142 76% 30%) 100%)",
                            color: "white",
                            border: "none",
                          },
                        }),
                        onError: (err) => toast.error(`Failed to save: ${err.message}`),
                      }
                    );
                  }}
                  className="h-9"
                />
                <p className="text-xs text-muted-foreground">
                  Default number of items per page for paginated responses
                </p>
              </div>
            </div>

            {/* Error Log Deduplication */}
            <div className="space-y-3 pt-4 border-t">
              <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <div>
                  <div className="flex items-center gap-2 text-sm font-medium">
                    <Shield className="h-4 w-4" />
                    Error Log Deduplication
                  </div>
                  <p className="text-xs text-muted-foreground mt-1">
                    Identical errors are logged only once. Clear the hash map to allow previously suppressed errors to be logged again.
                  </p>
                </div>
                <Button
                  variant="outline"
                  size="sm"
                  onClick={async () => {
                    try {
                      const res = await api.clearErrorDedup();
                      const data = res.success ? res.data : null;
                      toast.success(data?.message || "Dedup hashes cleared", {
                        style: {
                          background: "linear-gradient(135deg, hsl(142 76% 36%) 0%, hsl(142 76% 30%) 100%)",
                          color: "white",
                          border: "none",
                        },
                      });
                    } catch (err: unknown) {
                      const message = err instanceof Error ? err.message : String(err);
                      toast.error(`Failed: ${message}`);
                    }
                  }}
                  className="w-full sm:w-auto text-xs"
                >
                  Clear Dedup Hashes
                </Button>
              </div>
            </div>
          </div>
        );
        
      case "about":
        return <AboutPanel />;
        
      default:
        return null;
    }
  };

  return (
    <div className="space-y-4 sm:space-y-6">
      <div>
        <h1 className="text-xl sm:text-2xl font-bold">Settings</h1>
        <p className="text-sm text-muted-foreground">
          Configure application preferences
        </p>
      </div>

      <div className="flex flex-col lg:flex-row gap-4 lg:gap-6">
        {/* Tab menu - horizontal scroll on mobile, sidebar on desktop */}
        <Card className="lg:w-56 shrink-0 h-fit">
          <CardContent className="p-2">
            {/* Mobile: horizontal scroll tabs */}
            <nav className="flex lg:flex-col gap-1 overflow-x-auto lg:overflow-x-visible touch-pan-x -mx-2 px-2 lg:mx-0 lg:px-0 pb-2 lg:pb-0">
              {TABS.map((tab) => (
                <button
                  key={tab.id}
                  onClick={() => setActiveTab(tab.id)}
                  className={cn(
                    "flex items-center gap-2 lg:gap-3 px-3 py-2 lg:py-2.5 rounded-md text-xs sm:text-sm font-medium transition-all duration-200 whitespace-nowrap shrink-0 lg:shrink lg:w-full",
                    "hover:bg-accent hover:text-accent-foreground lg:hover:translate-x-0.5",
                    activeTab === tab.id
                      ? "bg-primary text-primary-foreground shadow-sm"
                      : "text-muted-foreground"
                  )}
                >
                  {tab.icon}
                  <span className="lg:flex-1 lg:text-left">{tab.label}</span>
                  {activeTab === tab.id && (
                    <ChevronRight className="h-4 w-4 opacity-70 hidden lg:block" />
                  )}
                </button>
              ))}
            </nav>
          </CardContent>
        </Card>

        {/* Content area */}
        <Card className="flex-1 min-w-0">
          <CardContent className="p-4 sm:p-6">
            {renderTabContent()}
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
