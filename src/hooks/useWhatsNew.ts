import { useState, useEffect, useCallback } from "react";
import { useQuery } from "@tanstack/react-query";

const LOCAL_STORAGE_KEY = "wp-plugin-publish-last-seen-version";

export interface ChangelogEntry {
  version: string;
  date: string;
  title: string;
  changes: string[];
  knownIssues?: string[];
}

export interface RoadmapItem {
  status: "planned" | "in-progress" | "completed";
  title: string;
  description: string;
}

export interface VersionInfo {
  appName?: string;
  version: string;
  releaseDate: string;
  changelog: ChangelogEntry[];
  roadmap: RoadmapItem[];
}

function resolvePublicUrl(path: string): string {
  const base = (import.meta.env.BASE_URL || "/").replace(/\/?$/, "/");
  const cleanPath = path.replace(/^\//, "");
  return `${base}${cleanPath}`;
}

export async function fetchVersionInfo(): Promise<VersionInfo> {
  const response = await fetch(resolvePublicUrl("version.json"));
  if (!response.ok) {
    throw new Error("Failed to fetch version info");
  }
  return response.json();
}

/** Read-only access to version.json (no modal state / side effects). */
export function useVersionInfo() {
  return useQuery({
    queryKey: ["version-info"],
    queryFn: fetchVersionInfo,
    staleTime: 1000 * 60 * 60, // 1 hour
    retry: 1,
  });
}

export function useWhatsNew() {
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [hasNewVersion, setHasNewVersion] = useState(false);

  const { data: versionInfo, isLoading, error } = useVersionInfo();

  // Check if there's a new version on mount
  useEffect(() => {
    if (!versionInfo) return;

    const lastSeenVersion = localStorage.getItem(LOCAL_STORAGE_KEY);
    const currentVersion = versionInfo.version;

    if (lastSeenVersion !== currentVersion) {
      setHasNewVersion(true);
      // Auto-open the modal if this is a new version
      if (lastSeenVersion !== null) {
        // Only auto-open if user has seen a previous version (not first-time users)
        setIsModalOpen(true);
      }
    }
  }, [versionInfo]);

  const openModal = useCallback(() => {
    setIsModalOpen(true);
  }, []);

  const closeModal = useCallback(() => {
    setIsModalOpen(false);
    // Mark version as seen when modal is closed
    if (versionInfo) {
      localStorage.setItem(LOCAL_STORAGE_KEY, versionInfo.version);
      setHasNewVersion(false);
    }
  }, [versionInfo]);

  const markAsSeen = useCallback(() => {
    if (versionInfo) {
      localStorage.setItem(LOCAL_STORAGE_KEY, versionInfo.version);
      setHasNewVersion(false);
    }
  }, [versionInfo]);

  const currentVersion = versionInfo?.version || "0.0.0";
  const latestChangelog = versionInfo?.changelog?.[0];

  return {
    versionInfo,
    currentVersion,
    latestChangelog,
    isLoading,
    error,
    isModalOpen,
    hasNewVersion,
    openModal,
    closeModal,
    markAsSeen,
  };
}
