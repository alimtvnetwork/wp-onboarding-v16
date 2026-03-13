import { useMutation, useQueryClient } from "@tanstack/react-query";
import { api, requireSuccess, Settings } from "@/lib/api";
import { useApiQuery } from "@/hooks/useApiQuery";
import { useErrorStore } from "@/stores/errorStore";

type DeepPartial<T> = { [P in keyof T]?: T[P] extends object ? DeepPartial<T[P]> : T[P] };

export function useSettings() {
  return useApiQuery<Settings>({
    queryKey: ["settings"],
    apiFn: () => api.getSettings(),
    endpoint: "/settings",
  });
}

export function useSaveSettings() {
  const queryClient = useQueryClient();
  return useMutation({
    meta: { suppressGlobalError: true },
    mutationFn: async (patch: DeepPartial<Settings>) => {
      const response = await api.updateSettings(patch as Partial<Settings>);
      return requireSuccess(response, { endpoint: "/settings", method: "PUT" });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["settings"] });
      // Cross-invalidate site snapshot settings & cron jobs to stay in sync
      queryClient.invalidateQueries({ predicate: (q) => {
        const key = q.queryKey;
        return Array.isArray(key) && key.includes("snapshots") && (key.includes("settings") || key.includes("cron"));
      }});
    },
  });
}
