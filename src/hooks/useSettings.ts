import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { api, requireSuccess, Settings } from "@/lib/api";

export function useSettings() {
  return useQuery({
    queryKey: ["settings"],
    queryFn: async () => {
      const response = await api.getSettings();
      return requireSuccess(response, { endpoint: "/settings", method: "GET" });
    },
  });
}

export function useSaveSettings() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (patch: Partial<Settings>) => {
      const response = await api.updateSettings(patch);
      return requireSuccess(response, { endpoint: "/settings", method: "PUT" });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["settings"] });
    },
  });
}
