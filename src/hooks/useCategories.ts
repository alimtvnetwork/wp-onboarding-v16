import { useState, useCallback, useMemo } from "react";

// Predefined categories
export const PREDEFINED_CATEGORIES = [
  { value: "production", label: "Production", color: "hsl(var(--primary))" },
  { value: "staging", label: "Staging", color: "hsl(var(--warning))" },
  { value: "development", label: "Development", color: "hsl(var(--muted-foreground))" },
] as const;

export type CategoryType = typeof PREDEFINED_CATEGORIES[number]["value"] | string;

export interface CategoryOption {
  value: string;
  label: string;
  color?: string;
  isCustom?: boolean;
}

// Local storage key for custom categories
const CUSTOM_CATEGORIES_KEY = "wp-plugin-publish-custom-categories";

export function useCategories() {
  // Load custom categories from localStorage
  const [customCategories, setCustomCategories] = useState<CategoryOption[]>(() => {
    try {
      const stored = localStorage.getItem(CUSTOM_CATEGORIES_KEY);
      return stored ? JSON.parse(stored) : [];
    } catch {
      return [];
    }
  });

  // Combine predefined and custom categories
  const allCategories = useMemo<CategoryOption[]>(() => {
    const predefined = PREDEFINED_CATEGORIES.map(c => ({
      ...c,
      isCustom: false,
    }));
    return [...predefined, ...customCategories.map(c => ({ ...c, isCustom: true }))];
  }, [customCategories]);

  // Add a custom category
  const addCategory = useCallback((label: string) => {
    const value = label.toLowerCase().replace(/\s+/g, "-");
    
    // Check if already exists
    if (allCategories.some(c => c.value === value)) {
      return false;
    }

    const newCategory: CategoryOption = {
      value,
      label,
      isCustom: true,
    };

    const updated = [...customCategories, newCategory];
    setCustomCategories(updated);
    localStorage.setItem(CUSTOM_CATEGORIES_KEY, JSON.stringify(updated));
    return true;
  }, [customCategories, allCategories]);

  // Remove a custom category
  const removeCategory = useCallback((value: string) => {
    const updated = customCategories.filter(c => c.value !== value);
    setCustomCategories(updated);
    localStorage.setItem(CUSTOM_CATEGORIES_KEY, JSON.stringify(updated));
  }, [customCategories]);

  // Get category by value
  const getCategory = useCallback((value: string) => {
    return allCategories.find(c => c.value === value);
  }, [allCategories]);

  return {
    categories: allCategories,
    predefinedCategories: PREDEFINED_CATEGORIES,
    customCategories,
    addCategory,
    removeCategory,
    getCategory,
  };
}
