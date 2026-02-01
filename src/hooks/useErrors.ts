import { useQuery } from "@tanstack/react-query";
import { api, ErrorLog } from "@/lib/api";

export function useErrors(limit?: number) {
  return useQuery({
    queryKey: ["errors", limit],
    queryFn: async () => {
      const response = await api.getErrors(limit);
      if (!response.success) throw new Error(response.error?.message);
      return response.data as ErrorLog[];
    },
  });
}
