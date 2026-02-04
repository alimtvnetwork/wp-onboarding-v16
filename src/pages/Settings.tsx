import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
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
import { useSettings } from "@/hooks/useSettings";
import { Eye, Archive, Palette, Loader2, Upload, Bug, RotateCcw, Zap } from "lucide-react";
import { AboutPanel } from "@/components/settings/AboutPanel";
import { useEffect, useState } from "react";
import { useLocation } from "react-router-dom";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import { logger } from "@/lib/logger";
import { configureRetry, getRetryConfig } from "@/lib/retry";
import { configureCircuitBreaker, getCircuitBreakerConfig, circuitBreaker } from "@/lib/circuitBreaker";

export default function Settings() {
  const { data: settings, isLoading } = useSettings();
  const location = useLocation();
  
  // Upload mode state (persisted to localStorage)
  const [uploadMode, setUploadMode] = useState<"file" | "zip">(() => {
    try {
      const saved = localStorage.getItem("wppp_upload_mode");
      return saved === "zip" ? "zip" : "file";
    } catch {
      return "file";
    }
  });
  
  // Developer settings state (initialized from settings or defaults)
  const [frontendDebugMode, setFrontendDebugMode] = useState(false);
  const [retryMaxAttempts, setRetryMaxAttempts] = useState(3);
  const [retryInitialDelayMs, setRetryInitialDelayMs] = useState(1000);
  const [circuitBreakerThreshold, setCircuitBreakerThreshold] = useState(5);
  const [circuitBreakerCooldownMs, setCircuitBreakerCooldownMs] = useState(60000);
  
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
      configureRetry({
        maxAttempts: settings.logging.retryMaxAttempts ?? 3,
        initialDelayMs: settings.logging.retryInitialDelayMs ?? 1000,
      });
      configureCircuitBreaker({
        failureThreshold: settings.logging.circuitBreakerThreshold ?? 5,
        cooldownMs: settings.logging.circuitBreakerCooldownMs ?? 60000,
      });
    }
  }, [settings]);
  
  const handleUploadModeChange = (value: string) => {
    const mode = value as "file" | "zip";
    setUploadMode(mode);
    try {
      localStorage.setItem("wppp_upload_mode", mode);
    } catch (e) {
      console.warn("[Settings] Failed to save upload mode:", e);
    }
  };
  
  const handleFrontendDebugModeChange = (enabled: boolean) => {
    setFrontendDebugMode(enabled);
    logger.configure({ 
      enabled: true, 
      minLevel: enabled ? 'trace' : 'info',
      consoleOutput: true 
    });
    logger.info(`Frontend debug mode ${enabled ? 'enabled' : 'disabled'}`);
  };
  
  const handleRetrySettingsChange = () => {
    configureRetry({
      maxAttempts: retryMaxAttempts,
      initialDelayMs: retryInitialDelayMs,
    });
    logger.info('Retry settings updated', { maxAttempts: retryMaxAttempts, initialDelayMs: retryInitialDelayMs });
  };
  
  const handleCircuitBreakerSettingsChange = () => {
    configureCircuitBreaker({
      failureThreshold: circuitBreakerThreshold,
      cooldownMs: circuitBreakerCooldownMs,
    });
    logger.info('Circuit breaker settings updated', { threshold: circuitBreakerThreshold, cooldownMs: circuitBreakerCooldownMs });
  };
  
  const handleResetCircuits = () => {
    circuitBreaker.resetAll();
    logger.info('All circuits reset manually');
  };

  useEffect(() => {
    if (location.hash !== "#about") return;
    const el = document.getElementById("about");
    if (el) el.scrollIntoView({ behavior: "smooth", block: "start" });
  }, [location.hash]);

  if (isLoading) {
    return (
      <div className="flex items-center justify-center py-12">
        <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
      </div>
    );
  }

  return (
    <div className="max-w-2xl mx-auto space-y-6">
      <div>
        <h1 className="text-2xl font-bold">Settings</h1>
        <p className="text-muted-foreground">
          Configure application preferences
        </p>
      </div>

      {/* File Watching */}
      <Card>
        <CardHeader className="pb-3">
          <CardTitle className="flex items-center gap-2 text-lg">
            <Eye className="h-5 w-5" />
            File Watching
          </CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="space-y-2">
            <Label>Poll Interval</Label>
            <Select defaultValue="5000">
              <SelectTrigger>
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
            <Label>Debounce Delay</Label>
            <Select defaultValue="500">
              <SelectTrigger>
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
        </CardContent>
      </Card>

      {/* Backups */}
      <Card>
        <CardHeader className="pb-3">
          <CardTitle className="flex items-center gap-2 text-lg">
            <Archive className="h-5 w-5" />
            Backups
          </CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="flex items-center justify-between">
            <div>
              <Label>Auto-backup before publish</Label>
              <p className="text-xs text-muted-foreground">
                Always create a backup before publishing
              </p>
            </div>
            <Switch defaultChecked />
          </div>

          <div className="space-y-2">
            <Label>Retention Days</Label>
            <Select defaultValue="30">
              <SelectTrigger>
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
            <Label>Max Backups per Plugin</Label>
            <Select defaultValue="10">
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="5">5 backups</SelectItem>
                <SelectItem value="10">10 backups</SelectItem>
                <SelectItem value="20">20 backups</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </CardContent>
      </Card>

      {/* Publish / Upload Mode */}
      <Card>
        <CardHeader className="pb-3">
          <CardTitle className="flex items-center gap-2 text-lg">
            <Upload className="h-5 w-5" />
            Publish Settings
          </CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="space-y-3">
            <Label>Upload Mode</Label>
            <RadioGroup value={uploadMode} onValueChange={handleUploadModeChange} className="space-y-2">
              <div className="flex items-start space-x-3">
                <RadioGroupItem value="file" id="upload-file" />
                <div className="grid gap-0.5 leading-none">
                  <Label htmlFor="upload-file" className="cursor-pointer font-medium">
                    File-by-file (default)
                  </Label>
                  <p className="text-xs text-muted-foreground">
                    Upload changed files individually. Better for small updates and debugging.
                  </p>
                </div>
              </div>
              <div className="flex items-start space-x-3">
                <RadioGroupItem value="zip" id="upload-zip" />
                <div className="grid gap-0.5 leading-none">
                  <Label htmlFor="upload-zip" className="cursor-pointer font-medium">
                    ZIP package
                  </Label>
                  <p className="text-xs text-muted-foreground">
                    Bundle all files into a ZIP and upload as one package. Faster for large plugins.
                  </p>
                </div>
              </div>
            </RadioGroup>
          </div>
        </CardContent>
      </Card>

      {/* Appearance */}
      <Card>
        <CardHeader className="pb-3">
          <CardTitle className="flex items-center gap-2 text-lg">
            <Palette className="h-5 w-5" />
            Appearance
          </CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="space-y-2">
            <Label>Theme</Label>
            <Select defaultValue="system">
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="light">Light</SelectItem>
                <SelectItem value="dark">Dark</SelectItem>
                <SelectItem value="system">System</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div className="flex items-center justify-between">
            <div>
              <Label>Compact Mode</Label>
              <p className="text-xs text-muted-foreground">
                Reduce spacing for more content density
              </p>
            </div>
            <Switch />
          </div>
        </CardContent>
      </Card>

      {/* Developer & Debugging */}
      <Card>
        <CardHeader className="pb-3">
          <CardTitle className="flex items-center gap-2 text-lg">
            <Bug className="h-5 w-5" />
            Developer & Debugging
          </CardTitle>
        </CardHeader>
        <CardContent className="space-y-6">
          {/* Frontend Debug Mode */}
          <div className="flex items-center justify-between">
            <div>
              <Label>Frontend Debug Mode</Label>
              <p className="text-xs text-muted-foreground">
                Log all function calls with file paths and line numbers
              </p>
            </div>
            <Switch 
              checked={frontendDebugMode}
              onCheckedChange={handleFrontendDebugModeChange}
            />
          </div>

          {/* Retry Settings */}
          <div className="space-y-3 pt-2 border-t">
            <div className="flex items-center gap-2 text-sm font-medium">
              <RotateCcw className="h-4 w-4" />
              Retry Logic
            </div>
            <div className="grid grid-cols-2 gap-4">
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
                  className="h-8"
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
                  className="h-8"
                />
              </div>
            </div>
            <p className="text-xs text-muted-foreground">
              Failed API calls retry with exponential backoff: delay × 2^attempt
            </p>
          </div>

          {/* Circuit Breaker Settings */}
          <div className="space-y-3 pt-2 border-t">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2 text-sm font-medium">
                <Zap className="h-4 w-4" />
                Circuit Breaker
              </div>
              <Button 
                variant="ghost" 
                size="sm"
                onClick={handleResetCircuits}
                className="h-7 text-xs"
              >
                Reset All Circuits
              </Button>
            </div>
            <div className="grid grid-cols-2 gap-4">
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
                  className="h-8"
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
                  className="h-8"
                />
              </div>
            </div>
            <p className="text-xs text-muted-foreground">
              Stops calling failing functions after threshold failures, retries after cooldown
            </p>
          </div>
        </CardContent>
      </Card>

      {/* About */}
      <AboutPanel />

      {/* Actions */}
      <div className="flex justify-end gap-3 pt-4 border-t">
        <Button variant="outline">Reset to Defaults</Button>
        <Button>Save</Button>
      </div>
    </div>
  );
}
