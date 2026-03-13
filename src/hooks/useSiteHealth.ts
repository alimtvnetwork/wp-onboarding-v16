import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";
import { toast } from "sonner";
import type { SiteHealthSummary, SiteHealthStats } from "@/types/siteHealth";
import { useErrorStore } from "@/stores/errorStore";

export function useSiteHealthSummaries(pollInterval = 30000) {
  return useQuery({
    queryKey: ["site-health-summaries"],
    queryFn: async () => {
      const resp = await api.getSiteHealthSummaries();
      if (resp.success && resp.data) {
        return (Array.isArray(resp.data) ? resp.data : []) as SiteHealthSummary[];
      }
      return [] as SiteHealthSummary[];
    },
    refetchInterval: pollInterval,
  });
}

export function useSiteHealthStats(pollInterval = 30000) {
  return useQuery({
    queryKey: ["site-health-stats"],
    queryFn: async () => {
      const resp = await api.getSiteHealthStats();
      if (resp.success && resp.data) {
        return resp.data as SiteHealthStats;
      }
      return undefined;
    },
    refetchInterval: pollInterval,
  });
}

export function useCheckAllSitesHealth() {
  const queryClient = useQueryClient();
  const { captureException } = useErrorStore();
  return useMutation({
    meta: { suppressGlobalError: true },
    mutationFn: () => api.checkAllSitesHealth(),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["site-health-summaries"] });
      queryClient.invalidateQueries({ queryKey: ["site-health-stats"] });
      toast.success("Health checks completed");
    },
    onError: (error: Error) => {
      captureException(error, {
        source: "useSiteHealth.checkAllSitesHealth",
        endpoint: "/sites/health/check-all",
        method: "POST",
        triggerComponent: "SiteHealth",
      });
      toast.error("Health check failed");
    },
  });
}

export function useCheckSiteHealth() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (siteId: number) => api.checkSiteHealth(siteId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["site-health-summaries"] });
      queryClient.invalidateQueries({ queryKey: ["site-health-stats"] });
    },
  });
}
