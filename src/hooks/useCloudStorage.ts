import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { api, requireSuccess } from "@/lib/api";
import type {
  CloudStorageAccount,
  CloudStorageAccountCreateRequest,
  CloudStorageAccountUpdateRequest,
  CloudStorageSettings,
  CloudStorageTestResult,
  CloudStorageProvider,
} from "@/types/cloudStorage";

const ACCOUNTS_KEY = "cloud-storage-accounts";
const SETTINGS_KEY = "cloud-storage-settings";

export function useCloudStorageAccounts() {
  return useQuery({
    queryKey: [ACCOUNTS_KEY],
    queryFn: async () => {
      const res = await api.getCloudStorageAccounts();
      const data = requireSuccess(res, { endpoint: "/cloud-storage/accounts" });
      return (data as { Accounts: CloudStorageAccount[] }).Accounts;
    },
  });
}

export function useCloudStorageSettings(provider: CloudStorageProvider) {
  return useQuery({
    queryKey: [SETTINGS_KEY, provider],
    queryFn: async () => {
      const res = await api.getCloudStorageSettings(provider);
      const data = requireSuccess(res, { endpoint: `/cloud-storage/settings/${provider}` });
      return data as unknown as CloudStorageSettings;
    },
  });
}

export function useCreateCloudStorageAccount() {
  const qc = useQueryClient();

  return useMutation({
    mutationKey: ["cloud-storage-account-create"],
    mutationFn: async (body: CloudStorageAccountCreateRequest) => {
      const res = await api.createCloudStorageAccount(body);
      return requireSuccess(res, { endpoint: "/cloud-storage/accounts", method: "POST" });
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: [ACCOUNTS_KEY] }),
  });
}

export function useUpdateCloudStorageAccount() {
  const qc = useQueryClient();

  return useMutation({
    mutationKey: ["cloud-storage-account-update"],
    mutationFn: async ({ id, body }: { id: number; body: CloudStorageAccountUpdateRequest }) => {
      const res = await api.updateCloudStorageAccount(id, body);
      return requireSuccess(res, { endpoint: `/cloud-storage/accounts/${id}`, method: "PUT" });
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: [ACCOUNTS_KEY] }),
  });
}

export function useDeleteCloudStorageAccount() {
  const qc = useQueryClient();

  return useMutation({
    mutationKey: ["cloud-storage-account-delete"],
    mutationFn: async (id: number) => {
      const res = await api.deleteCloudStorageAccount(id);
      return requireSuccess(res, { endpoint: `/cloud-storage/accounts/${id}`, method: "DELETE" });
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: [ACCOUNTS_KEY] }),
  });
}

export function useTestCloudStorageAccount() {
  return useMutation({
    mutationKey: ["cloud-storage-account-test"],
    mutationFn: async (accountId: number) => {
      const res = await api.testCloudStorageAccount(accountId);
      const data = requireSuccess(res, { endpoint: "/cloud-storage/accounts/test", method: "POST" });
      return data as CloudStorageTestResult;
    },
  });
}

export function useSaveCloudStorageSettings() {
  const qc = useQueryClient();

  return useMutation({
    mutationKey: ["cloud-storage-settings-save"],
    mutationFn: async ({ provider, settings }: { provider: CloudStorageProvider; settings: Partial<CloudStorageSettings> }) => {
      const res = await api.updateCloudStorageSettings(provider, settings);
      return requireSuccess(res, { endpoint: `/cloud-storage/settings/${provider}`, method: "PUT" });
    },
    onSuccess: (_data, vars) => qc.invalidateQueries({ queryKey: [SETTINGS_KEY, vars.provider] }),
  });
}
