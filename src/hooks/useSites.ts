import { useQuery } from "@tanstack/react-query";
import { api, Site } from "@/lib/api";

export function useSites() {
  return useQuery({
    queryKey: ["sites"],
    queryFn: async () => {
      const response = await api.getSites();
      if (!response.success) throw new Error(response.error?.message);
      return response.data as Site[];
    },
  });
}

export function useSite(id: number) {
  return useQuery({
    queryKey: ["sites", id],
    queryFn: async () => {
      const response = await api.getSite(id);
      if (!response.success) throw new Error(response.error?.message);
      return response.data as Site;
    },
    enabled: !!id,
  });
}
