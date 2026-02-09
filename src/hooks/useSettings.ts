import { useMutation, useQueryClient } from "@tanstack/react-query";
import { api, requireSuccess, Settings } from "@/lib/api";
import { useApiQuery } from "@/hooks/useApiQuery";

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
    mutationFn: async (patch: DeepPartial<Settings>) => {
      const response = await api.updateSettings(patch as Partial<Settings>);
      return requireSuccess(response, { endpoint: "/settings", method: "PUT" });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["settings"] });
    },
  });
}
