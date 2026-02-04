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
import { useSettings } from "@/hooks/useSettings";
import { Eye, Archive, FileText, Palette, Loader2, Upload } from "lucide-react";
import { AboutPanel } from "@/components/settings/AboutPanel";
import { useEffect, useState } from "react";
import { useLocation } from "react-router-dom";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";

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
  
  const handleUploadModeChange = (value: string) => {
    const mode = value as "file" | "zip";
    setUploadMode(mode);
    try {
      localStorage.setItem("wppp_upload_mode", mode);
    } catch (e) {
      console.warn("[Settings] Failed to save upload mode:", e);
    }
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
