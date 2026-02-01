import { useQuery } from "@tanstack/react-query";
import { api, Plugin } from "@/lib/api";

export function usePlugins() {
  return useQuery({
    queryKey: ["plugins"],
    queryFn: async () => {
      const response = await api.getPlugins();
      if (!response.success) throw new Error(response.error?.message);
      return response.data as Plugin[];
    },
  });
}

export function usePlugin(id: number) {
  return useQuery({
    queryKey: ["plugins", id],
    queryFn: async () => {
      const response = await api.getPlugin(id);
      if (!response.success) throw new Error(response.error?.message);
      return response.data as Plugin;
    },
    enabled: !!id,
  });
}
