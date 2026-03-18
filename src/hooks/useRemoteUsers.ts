// React Query hooks for remote WordPress user management.

import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { api, requireSuccess } from "@/lib/api";
import type { WPUser, WPUserSummary, UserCreateInput, UserUpdateInput, UserCreateResult, UserUpdateResult, UserDeleteResult } from "@/types/wpUser";
import { toast } from "sonner";
import { request } from "@/lib/api";

// ── API Methods ─────────────────────────────────────────

function listUsers(siteId: number, params?: { page?: number; per_page?: number; role?: string; search?: string }) {
  const query = new URLSearchParams();
  if (params?.page) query.set("page", String(params.page));
  if (params?.per_page) query.set("per_page", String(params.per_page));
  if (params?.role) query.set("role", params.role);
  if (params?.search) query.set("search", params.search);
  const qs = query.toString();
  return request<any>(`/sites/${siteId}/users${qs ? `?${qs}` : ""}`);
}

function getUser(siteId: number, userId: number) {
  return request<WPUser>(`/sites/${siteId}/users/${userId}`);
}

function createUser(siteId: number, input: UserCreateInput) {
  return request<UserCreateResult>(`/sites/${siteId}/users`, {
    method: "POST",
    body: JSON.stringify(input),
  });
}

function updateUser(siteId: number, userId: number, input: UserUpdateInput) {
  return request<UserUpdateResult>(`/sites/${siteId}/users/${userId}`, {
    method: "PUT",
    body: JSON.stringify(input),
  });
}

function deleteUser(siteId: number, userId: number, reassignTo?: number) {
  const qs = reassignTo ? `?reassign=${reassignTo}` : "";
  return request<UserDeleteResult>(`/sites/${siteId}/users/${userId}${qs}`, {
    method: "DELETE",
  });
}

// ── Hooks ───────────────────────────────────────────────

const KEYS = {
  users: (siteId: number) => ["users", siteId] as const,
  user: (siteId: number, userId: number) => ["users", siteId, userId] as const,
};

export function useRemoteUsers(siteId: number | null, params?: { role?: string; search?: string }) {
  return useQuery({
    queryKey: [...KEYS.users(siteId!), params],
    queryFn: async () => {
      const res = await listUsers(siteId!, params);
      return requireSuccess(res, { endpoint: `/sites/${siteId}/users` });
    },
    enabled: siteId !== null,
    meta: { suppressGlobalError: true },
  });
}

export function useRemoteUser(siteId: number | null, userId: number | null) {
  return useQuery({
    queryKey: [...KEYS.user(siteId!, userId!)],
    queryFn: async () => {
      const res = await getUser(siteId!, userId!);
      return requireSuccess(res, { endpoint: `/sites/${siteId}/users/${userId}` });
    },
    enabled: siteId !== null && userId !== null,
    meta: { suppressGlobalError: true },
  });
}

export function useCreateRemoteUser(siteId: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (input: UserCreateInput) => {
      const res = await createUser(siteId, input);
      return requireSuccess(res, { endpoint: `/sites/${siteId}/users`, method: "POST" });
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: [...KEYS.users(siteId)] });
      toast.success("User created");
    },
    onError: (err: Error) => toast.error(err.message),
  });
}

export function useUpdateRemoteUser(siteId: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async ({ userId, input }: { userId: number; input: UserUpdateInput }) => {
      const res = await updateUser(siteId, userId, input);
      return requireSuccess(res, { endpoint: `/sites/${siteId}/users/${userId}`, method: "PUT" });
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: [...KEYS.users(siteId)] });
      toast.success("User updated");
    },
    onError: (err: Error) => toast.error(err.message),
  });
}

export function useDeleteRemoteUser(siteId: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async ({ userId, reassignTo }: { userId: number; reassignTo?: number }) => {
      const res = await deleteUser(siteId, userId, reassignTo);
      return requireSuccess(res, { endpoint: `/sites/${siteId}/users/${userId}`, method: "DELETE" });
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: [...KEYS.users(siteId)] });
      toast.success("User deleted");
    },
    onError: (err: Error) => toast.error(err.message),
  });
}
