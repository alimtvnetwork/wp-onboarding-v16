import { useQuery } from "@tanstack/react-query";
import { api, Settings } from "@/lib/api";

export function useSettings() {
  return useQuery({
    queryKey: ["settings"],
    queryFn: async () => {
      const response = await api.getSettings();
      if (!response.success) throw new Error(response.error?.message);
      return response.data as Settings;
    },
  });
}
