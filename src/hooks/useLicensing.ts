// React Query hooks for the licensing admin dashboard.

import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import {
  listLicenses,
  getLicense,
  createLicense,
  updateLicense,
  deleteLicense,
  listAuditLogs,
  checkHealth,
} from "@/lib/licensingApi";
import type { CreateLicenseInput, UpdateLicenseInput } from "@/types/licensing";
import { toast } from "sonner";

const KEYS = {
  licenses: ["licensing", "licenses"] as const,
  license: (id: number) => ["licensing", "licenses", id] as const,
  audit: (params?: { action?: string; license_id?: number }) =>
    ["licensing", "audit", params] as const,
  health: ["licensing", "health"] as const,
};

export function useLicenses() {
  return useQuery({
    queryKey: [...KEYS.licenses],
    queryFn: listLicenses,
    meta: { suppressGlobalError: true },
  });
}

export function useLicense(id: number | null) {
  return useQuery({
    queryKey: [...KEYS.license(id!)],
    queryFn: () => getLicense(id!),
    enabled: id !== null,
    meta: { suppressGlobalError: true },
  });
}

export function useCreateLicense() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (input: CreateLicenseInput) => createLicense(input),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: [...KEYS.licenses] });
      toast.success("License created");
    },
    onError: (err: Error) => toast.error(err.message),
  });
}

export function useUpdateLicense() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, input }: { id: number; input: UpdateLicenseInput }) =>
      updateLicense(id, input),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: [...KEYS.licenses] });
      toast.success("License updated");
    },
    onError: (err: Error) => toast.error(err.message),
  });
}

export function useDeleteLicense() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => deleteLicense(id),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: [...KEYS.licenses] });
      toast.success("License deleted");
    },
    onError: (err: Error) => toast.error(err.message),
  });
}

export function useAuditLogs(params?: { action?: string; license_id?: number }) {
  return useQuery({
    queryKey: [...KEYS.audit(params)],
    queryFn: () => listAuditLogs(params),
    meta: { suppressGlobalError: true },
  });
}

export function useLicensingHealth() {
  return useQuery({
    queryKey: [...KEYS.health],
    queryFn: checkHealth,
    refetchInterval: 30_000,
    meta: { suppressGlobalError: true },
  });
}
