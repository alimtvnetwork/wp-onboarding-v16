import { useTheme, Theme, AccentColor } from "@/hooks/useTheme";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Monitor, Moon, Sun, Palette, Type, Square, Zap } from "lucide-react";

const themeOptions: { value: Theme; label: string; icon: React.ReactNode }[] = [
  { value: "light", label: "Light", icon: <Sun className="h-4 w-4" /> },
  { value: "dark", label: "Dark", icon: <Moon className="h-4 w-4" /> },
  { value: "system", label: "System", icon: <Monitor className="h-4 w-4" /> },
  { value: "high-contrast", label: "High Contrast", icon: <Sun className="h-4 w-4" /> },
  { value: "high-contrast-dark", label: "High Contrast Dark", icon: <Moon className="h-4 w-4" /> },
];

const accentColors: { value: AccentColor; label: string; color: string }[] = [
  { value: "blue", label: "Blue", color: "bg-blue-500" },
  { value: "indigo", label: "Indigo", color: "bg-indigo-500" },
  { value: "violet", label: "Violet", color: "bg-violet-500" },
  { value: "purple", label: "Purple", color: "bg-purple-500" },
  { value: "pink", label: "Pink", color: "bg-pink-500" },
  { value: "rose", label: "Rose", color: "bg-rose-500" },
  { value: "red", label: "Red", color: "bg-red-500" },
  { value: "orange", label: "Orange", color: "bg-orange-500" },
  { value: "amber", label: "Amber", color: "bg-amber-500" },
  { value: "yellow", label: "Yellow", color: "bg-yellow-500" },
  { value: "lime", label: "Lime", color: "bg-lime-500" },
  { value: "green", label: "Green", color: "bg-green-500" },
  { value: "emerald", label: "Emerald", color: "bg-emerald-500" },
  { value: "teal", label: "Teal", color: "bg-teal-500" },
  { value: "cyan", label: "Cyan", color: "bg-cyan-500" },
  { value: "sky", label: "Sky", color: "bg-sky-500" },
];

export function ThemeSelector() {
  const {
    theme,
    accentColor,
    fontSize,
    borderRadius,
    compactMode,
    animationsEnabled,
    setTheme,
    setAccentColor,
    setFontSize,
    setBorderRadius,
    setCompactMode,
    setAnimationsEnabled,
    isSaving,
  } = useTheme();

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <Palette className="h-5 w-5" />
          Appearance
        </CardTitle>
        <CardDescription>
          Customize the look and feel of the application
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-6">
        {/* Theme Selection */}
        <div className="space-y-2">
          <Label htmlFor="theme">Theme</Label>
          <Select value={theme} onValueChange={(v) => setTheme(v as Theme)}>
            <SelectTrigger id="theme" className="w-full">
              <SelectValue placeholder="Select theme" />
            </SelectTrigger>
            <SelectContent>
              {themeOptions.map((option) => (
                <SelectItem key={option.value} value={option.value}>
                  <div className="flex items-center gap-2">
                    {option.icon}
                    {option.label}
                  </div>
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        {/* Accent Color */}
        <div className="space-y-2">
          <Label>Accent Color</Label>
          <div className="grid grid-cols-8 gap-2">
            {accentColors.map((color) => (
              <button
                key={color.value}
                onClick={() => setAccentColor(color.value)}
                className={`h-8 w-8 rounded-full ${color.color} transition-all hover:scale-110 ${
                  accentColor === color.value
                    ? "ring-2 ring-offset-2 ring-offset-background ring-primary"
                    : ""
                }`}
                title={color.label}
                disabled={isSaving}
              />
            ))}
          </div>
        </div>

        {/* Font Size */}
        <div className="space-y-2">
          <Label htmlFor="fontSize" className="flex items-center gap-2">
            <Type className="h-4 w-4" />
            Font Size
          </Label>
          <Select value={fontSize} onValueChange={(v) => setFontSize(v as any)}>
            <SelectTrigger id="fontSize">
              <SelectValue placeholder="Select size" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="x-small">Extra Small</SelectItem>
              <SelectItem value="small">Small</SelectItem>
              <SelectItem value="medium">Medium</SelectItem>
              <SelectItem value="large">Large</SelectItem>
              <SelectItem value="x-large">Extra Large</SelectItem>
            </SelectContent>
          </Select>
        </div>

        {/* Border Radius */}
        <div className="space-y-2">
          <Label htmlFor="borderRadius" className="flex items-center gap-2">
            <Square className="h-4 w-4" />
            Border Radius
          </Label>
          <Select value={borderRadius} onValueChange={(v) => setBorderRadius(v as any)}>
            <SelectTrigger id="borderRadius">
              <SelectValue placeholder="Select radius" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="none">None (Sharp)</SelectItem>
              <SelectItem value="small">Small</SelectItem>
              <SelectItem value="medium">Medium</SelectItem>
              <SelectItem value="large">Large</SelectItem>
              <SelectItem value="full">Full (Pill)</SelectItem>
            </SelectContent>
          </Select>
        </div>

        {/* Toggles */}
        <div className="space-y-4">
          <div className="flex items-center justify-between">
            <div className="space-y-0.5">
              <Label htmlFor="compactMode">Compact Mode</Label>
              <p className="text-sm text-muted-foreground">
                Reduce padding and spacing throughout the UI
              </p>
            </div>
            <Switch
              id="compactMode"
              checked={compactMode}
              onCheckedChange={setCompactMode}
              disabled={isSaving}
            />
          </div>

          <div className="flex items-center justify-between">
            <div className="space-y-0.5">
              <Label htmlFor="animations" className="flex items-center gap-2">
                <Zap className="h-4 w-4" />
                Animations
              </Label>
              <p className="text-sm text-muted-foreground">
                Enable smooth transitions and animations
              </p>
            </div>
            <Switch
              id="animations"
              checked={animationsEnabled}
              onCheckedChange={setAnimationsEnabled}
              disabled={isSaving}
            />
          </div>
        </div>

        {isSaving && (
          <p className="text-sm text-muted-foreground animate-pulse">
            Saving changes...
          </p>
        )}
      </CardContent>
    </Card>
  );
}
