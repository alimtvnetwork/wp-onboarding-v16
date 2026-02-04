import { useQuery } from "@tanstack/react-query";
import { api, Plugin, requireSuccess } from "@/lib/api";

export function usePlugins() {
  return useQuery({
    queryKey: ["plugins"],
    queryFn: async () => {
      const response = await api.getPlugins();
      return requireSuccess(response, { endpoint: "/plugins", method: "GET" });
    },
  });
}

export function usePlugin(id: number) {
  return useQuery({
    queryKey: ["plugins", id],
    queryFn: async () => {
      const response = await api.getPlugin(id);
      return requireSuccess(response, { endpoint: `/plugins/${id}`, method: "GET" });
    },
    enabled: !!id,
  });
}
