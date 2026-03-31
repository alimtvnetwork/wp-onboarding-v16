import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api/methods";

export function useDedupRegistry(siteId: number | null) {
  const queryClient = useQueryClient();

  const query = useQuery({
    queryKey: ["dedup-registry", siteId],
    queryFn: () => api.getDedupRegistry(siteId!),
    enabled: !!siteId,
    meta: { suppressGlobalError: true },
  });

  const clearMutation = useMutation({
    mutationFn: () => api.clearDedupRegistry(siteId!),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["dedup-registry", siteId] });
    },
  });

  return {
    data: query.data,
    isLoading: query.isLoading,
    isError: query.isError,
    error: query.error,
    refetch: query.refetch,
    clearRegistry: clearMutation.mutate,
    isClearing: clearMutation.isPending,
  };
}
