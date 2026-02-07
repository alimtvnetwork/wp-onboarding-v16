import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { api, requireSuccess, Site } from "@/lib/api";
import { requireSuccessWithEnvelope, withPaginationParams, PaginatedResult } from "@/lib/apiHelpers";

export function useSites() {
  return useQuery({
    queryKey: ["sites"],
    queryFn: async () => {
      const response = await api.getSites();
      return requireSuccess(response, { endpoint: "/sites", method: "GET" });
    },
  });
}

export function useSitesPaginated(page: number = 1, perPage: number = 25) {
  return useQuery({
    queryKey: ["sites", "paginated", page, perPage],
    queryFn: async () => {
      const endpoint = withPaginationParams("/sites", { page, perPage });
      const response = await api.getSites();
      // When the backend returns an envelope, requireSuccessWithEnvelope will extract pagination
      return requireSuccessWithEnvelope<Site[]>(response, { endpoint, method: "GET" });
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
