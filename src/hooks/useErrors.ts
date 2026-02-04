import { useQuery } from "@tanstack/react-query";
import { api, ErrorLog, requireSuccess } from "@/lib/api";

export function useErrors(limit?: number) {
  return useQuery({
    queryKey: ["errors", limit],
    queryFn: async () => {
      const response = await api.getErrors(limit);
      return requireSuccess(response, {
        endpoint: `/errors${limit ? `?limit=${limit}` : ""}`,
        method: "GET",
      });
    },
  });
}
