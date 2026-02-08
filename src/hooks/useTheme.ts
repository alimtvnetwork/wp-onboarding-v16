import { useCallback, useEffect, useState } from "react";
import { useSettings } from "./useSettings";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { api, requireSuccess } from "@/lib/api";

export type Theme = 
  | "light"
  | "dark"
  | "system"
  | "high-contrast"
  | "high-contrast-dark";

export type AccentColor =
  | "blue"
  | "indigo"
  | "violet"
  | "purple"
  | "pink"
  | "rose"
  | "red"
  | "orange"
  | "amber"
  | "yellow"
  | "lime"
  | "green"
  | "emerald"
  | "teal"
  | "cyan"
  | "sky";

export type FontSize = "x-small" | "small" | "medium" | "large" | "x-large";

export type BorderRadius = "none" | "small" | "medium" | "large" | "full";

export type SidebarTheme = "night-blue" | "midnight-purple" | "emerald-dark" | "solar-white";

export interface ThemeConfig {
  theme: Theme;
  accentColor: AccentColor;
  fontSize: FontSize;
  borderRadius: BorderRadius;
  compactMode: boolean;
  animationsEnabled: boolean;
  sidebarTheme: SidebarTheme;
}

const defaultThemeConfig: ThemeConfig = {
  theme: "system",
  accentColor: "blue",
  fontSize: "medium",
  borderRadius: "medium",
  compactMode: false,
  animationsEnabled: true,
  sidebarTheme: "night-blue",
};

// Get system preference for dark mode
function getSystemTheme(): "light" | "dark" {
  if (typeof window === "undefined") return "light";
  return window.matchMedia("(prefers-color-scheme: dark)").matches
    ? "dark"
    : "light";
}

// Resolve theme including system preference
function resolveTheme(theme: Theme): "light" | "dark" | "high-contrast" | "high-contrast-dark" {
  if (theme === "system") {
    return getSystemTheme();
  }
  return theme;
}

export function useTheme() {
  const { data: settings, isLoading } = useSettings();
  const queryClient = useQueryClient();

  // Local state for immediate UI updates
  const [localConfig, setLocalConfig] = useState<ThemeConfig>(defaultThemeConfig);

  // Initialize from settings when loaded
  useEffect(() => {
    if (settings?.appearance) {
      const appearance = settings.appearance;
      setLocalConfig({
        theme: (appearance.theme as Theme) || defaultThemeConfig.theme,
        accentColor: (appearance.accentColor as AccentColor) || defaultThemeConfig.accentColor,
        fontSize: (appearance.fontSize as FontSize) || defaultThemeConfig.fontSize,
        borderRadius: (appearance.borderRadius as BorderRadius) || defaultThemeConfig.borderRadius,
        compactMode: appearance.compactMode ?? defaultThemeConfig.compactMode,
        animationsEnabled: appearance.animationsEnabled ?? defaultThemeConfig.animationsEnabled,
        sidebarTheme: ((appearance as any).sidebarTheme as SidebarTheme) || defaultThemeConfig.sidebarTheme,
      });
    }
  }, [settings]);

  // Apply theme to document
  useEffect(() => {
    const resolved = resolveTheme(localConfig.theme);
    const root = document.documentElement;

    // Remove all theme classes
    root.classList.remove("light", "dark", "high-contrast", "high-contrast-dark");
    
    // Add resolved theme class
    root.classList.add(resolved);
    
    // Set data attributes for CSS targeting
    root.setAttribute("data-theme", localConfig.theme);
    root.setAttribute("data-accent", localConfig.accentColor);
    root.setAttribute("data-font-size", localConfig.fontSize);
    root.setAttribute("data-radius", localConfig.borderRadius);
    root.setAttribute("data-sidebar-theme", localConfig.sidebarTheme);
    if (localConfig.compactMode) {
      root.setAttribute("data-compact", "true");
    } else {
      root.removeAttribute("data-compact");
    }

    if (!localConfig.animationsEnabled) {
      root.setAttribute("data-reduce-motion", "true");
    } else {
      root.removeAttribute("data-reduce-motion");
    }
  }, [localConfig]);

  // Listen for system theme changes
  useEffect(() => {
    if (localConfig.theme !== "system") return;

    const mediaQuery = window.matchMedia("(prefers-color-scheme: dark)");
    const handler = () => {
      const resolved = getSystemTheme();
      document.documentElement.classList.remove("light", "dark");
      document.documentElement.classList.add(resolved);
    };

    mediaQuery.addEventListener("change", handler);
    return () => mediaQuery.removeEventListener("change", handler);
  }, [localConfig.theme]);

  // Mutation for saving theme settings
  const updateSettingMutation = useMutation({
    mutationFn: async ({ key, value }: { key: string; value: string }) => {
      const response = await api.updateSetting(key, value);
      // Ensure errors surface in the GlobalErrorModal with the full resolved URL.
      return requireSuccess(response, {
        endpoint: `/settings/${encodeURIComponent(key)}`,
        method: "PUT",
        requestBody: { value },
      });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["settings"] });
    },
  });

  const setTheme = useCallback((theme: Theme) => {
    setLocalConfig((prev) => ({ ...prev, theme }));
    updateSettingMutation.mutate({ key: "appearance.theme", value: theme });
  }, [updateSettingMutation]);

  const setAccentColor = useCallback((accentColor: AccentColor) => {
    setLocalConfig((prev) => ({ ...prev, accentColor }));
    updateSettingMutation.mutate({ key: "appearance.accentColor", value: accentColor });
  }, [updateSettingMutation]);

  const setFontSize = useCallback((fontSize: FontSize) => {
    setLocalConfig((prev) => ({ ...prev, fontSize }));
    updateSettingMutation.mutate({ key: "appearance.fontSize", value: fontSize });
  }, [updateSettingMutation]);

  const setBorderRadius = useCallback((borderRadius: BorderRadius) => {
    setLocalConfig((prev) => ({ ...prev, borderRadius }));
    updateSettingMutation.mutate({ key: "appearance.borderRadius", value: borderRadius });
  }, [updateSettingMutation]);

  const setCompactMode = useCallback((compactMode: boolean) => {
    setLocalConfig((prev) => ({ ...prev, compactMode }));
    updateSettingMutation.mutate({ key: "appearance.compactMode", value: String(compactMode) });
  }, [updateSettingMutation]);

  const setAnimationsEnabled = useCallback((animationsEnabled: boolean) => {
    setLocalConfig((prev) => ({ ...prev, animationsEnabled }));
    updateSettingMutation.mutate({ key: "appearance.animationsEnabled", value: String(animationsEnabled) });
  }, [updateSettingMutation]);

  const setSidebarTheme = useCallback((sidebarTheme: SidebarTheme) => {
    setLocalConfig((prev) => ({ ...prev, sidebarTheme }));
    updateSettingMutation.mutate({ key: "appearance.sidebarTheme", value: sidebarTheme });
  }, [updateSettingMutation]);

  return {
    // Current config
    ...localConfig,
    resolvedTheme: resolveTheme(localConfig.theme),
    isLoading,
    isSaving: updateSettingMutation.isPending,

    // Setters
    setTheme,
    setAccentColor,
    setFontSize,
    setBorderRadius,
    setCompactMode,
    setAnimationsEnabled,
    setSidebarTheme,
  };
}
