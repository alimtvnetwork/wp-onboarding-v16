import { useState, useEffect, useCallback } from "react";

const STORAGE_KEY = "wppp_plugin_form_draft";

export interface PluginFormData {
  name: string;
  path: string;
  gitEnabled: boolean;
  gitRemoteUrl: string;
  buildCommand: string;
}

const initialFormData: PluginFormData = {
  name: "",
  path: "",
  gitEnabled: false,
  gitRemoteUrl: "",
  buildCommand: "",
};

/**
 * Hook to persist plugin form data to localStorage
 * so users don't lose their input when the dialog closes or page refreshes
 */
export function usePluginFormPersistence() {
  const [formData, setFormData] = useState<PluginFormData>(initialFormData);

  // Load from localStorage on mount
  useEffect(() => {
    try {
      const saved = localStorage.getItem(STORAGE_KEY);
      if (saved) {
        const parsed = JSON.parse(saved) as Partial<PluginFormData>;
        setFormData({
          name: parsed.name || "",
          path: parsed.path || "",
          gitEnabled: parsed.gitEnabled ?? false,
          gitRemoteUrl: parsed.gitRemoteUrl || "",
          buildCommand: parsed.buildCommand || "",
        });
      }
    } catch (e) {
      console.warn("[PluginFormPersistence] Failed to load saved form data:", e);
    }
  }, []);

  // Save to localStorage whenever form changes
  const updateFormData = useCallback((updates: Partial<PluginFormData>) => {
    setFormData((prev) => {
      const next = { ...prev, ...updates };
      try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(next));
      } catch (e) {
        console.warn("[PluginFormPersistence] Failed to save form data:", e);
      }
      return next;
    });
  }, []);

  const handleInputChange = useCallback(
    (field: keyof PluginFormData, value: string | boolean) => {
      updateFormData({ [field]: value });
    },
    [updateFormData]
  );

  const clearForm = useCallback(() => {
    setFormData(initialFormData);
    try {
      localStorage.removeItem(STORAGE_KEY);
    } catch (e) {
      console.warn("[PluginFormPersistence] Failed to clear form data:", e);
    }
  }, []);

  const resetForm = useCallback(() => {
    setFormData(initialFormData);
  }, []);

  return {
    formData,
    setFormData,
    handleInputChange,
    clearForm,
    resetForm,
  };
}
