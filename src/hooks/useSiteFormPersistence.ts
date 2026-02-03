import { useState, useEffect, useCallback } from "react";

const STORAGE_KEY = "wppp_site_form_draft";

export interface SiteFormData {
  name: string;
  url: string;
  username: string;
  password: string;
}

const initialFormData: SiteFormData = {
  name: "",
  url: "",
  username: "",
  password: "",
};

/**
 * Hook to persist site form data to localStorage
 * so users don't lose their input when the dialog closes or page refreshes
 */
export function useSiteFormPersistence() {
  const [formData, setFormData] = useState<SiteFormData>(initialFormData);

  // Load from localStorage on mount
  useEffect(() => {
    try {
      const saved = localStorage.getItem(STORAGE_KEY);
      if (saved) {
        const parsed = JSON.parse(saved) as Partial<SiteFormData>;
        setFormData({
          name: parsed.name || "",
          url: parsed.url || "",
          username: parsed.username || "",
          // Don't restore password for security
          password: "",
        });
      }
    } catch (e) {
      console.warn("[SiteFormPersistence] Failed to load saved form data:", e);
    }
  }, []);

  // Save to localStorage whenever form changes (except password)
  const updateFormData = useCallback((updates: Partial<SiteFormData>) => {
    setFormData((prev) => {
      const next = { ...prev, ...updates };
      try {
        // Save everything except password
        localStorage.setItem(
          STORAGE_KEY,
          JSON.stringify({
            name: next.name,
            url: next.url,
            username: next.username,
            // Don't persist password for security
          })
        );
      } catch (e) {
        console.warn("[SiteFormPersistence] Failed to save form data:", e);
      }
      return next;
    });
  }, []);

  const handleInputChange = useCallback(
    (field: keyof SiteFormData, value: string) => {
      updateFormData({ [field]: value });
    },
    [updateFormData]
  );

  const clearForm = useCallback(() => {
    setFormData(initialFormData);
    try {
      localStorage.removeItem(STORAGE_KEY);
    } catch (e) {
      console.warn("[SiteFormPersistence] Failed to clear form data:", e);
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
