import { useQuery } from "@tanstack/react-query";
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
