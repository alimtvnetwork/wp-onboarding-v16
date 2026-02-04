import { useQuery } from "@tanstack/react-query";
import { api, requireSuccess, Site } from "@/lib/api";

export function useSites() {
  return useQuery({
    queryKey: ["sites"],
    queryFn: async () => {
      const response = await api.getSites();
      return requireSuccess(response, { endpoint: "/sites", method: "GET" });
    },
  });
}

export function useSite(id: number) {
  return useQuery({
    queryKey: ["sites", id],
    queryFn: async () => {
      const response = await api.getSite(id);
      return requireSuccess(response, { endpoint: `/sites/${id}`, method: "GET" });
    },
    enabled: !!id,
  });
}
