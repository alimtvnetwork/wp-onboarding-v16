import { useState, useMemo } from "react";
import { useSearchParams } from "react-router-dom";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Button } from "@/components/ui/button";
import { Plus, Cloud, Github, HardDrive, FolderOpen } from "lucide-react";
import { toast } from "sonner";
import { CloudStorageAccountCard } from "@/components/cloud-storage/CloudStorageAccountCard";
import { CloudStorageAccountDialog } from "@/components/cloud-storage/CloudStorageAccountDialog";
import { CloudStorageProviderSettings } from "@/components/cloud-storage/CloudStorageProviderSettings";
import {
  useCloudStorageAccounts,
  useCloudStorageSettings,
  useCreateCloudStorageAccount,
  useUpdateCloudStorageAccount,
  useDeleteCloudStorageAccount,
  useTestCloudStorageAccount,
  useSaveCloudStorageSettings,
} from "@/hooks/useCloudStorage";
import type {
  CloudStorageAccount,
  CloudStorageAccountCreateRequest,
  CloudStorageAccountUpdateRequest,
  CloudStorageProvider,
} from "@/types/cloudStorage";

const PROVIDERS: { id: CloudStorageProvider; label: string; icon: React.ReactNode }[] = [
  { id: "GitHub", label: "GitHub", icon: <Github className="h-4 w-4" /> },
  { id: "GitLab", label: "GitLab", icon: <HardDrive className="h-4 w-4" /> },
  // Google Drive will be added in Phase 3
];

export default function CloudStorage() {
  const [activeProvider, setActiveProvider] = useState<CloudStorageProvider>("GitHub");
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editingAccount, setEditingAccount] = useState<CloudStorageAccount | null>(null);
  const [testResults, setTestResults] = useState<Record<number, { Success: boolean; Message?: string; Error?: string }>>(
    {},
  );
  const [testingId, setTestingId] = useState<number | null>(null);

  // Data hooks
  const { data: accounts = [], isLoading: accountsLoading } = useCloudStorageAccounts();
  const { data: settings, isLoading: settingsLoading } = useCloudStorageSettings(activeProvider);
  const createAccount = useCreateCloudStorageAccount();
  const updateAccount = useUpdateCloudStorageAccount();
  const deleteAccount = useDeleteCloudStorageAccount();
  const testAccount = useTestCloudStorageAccount();
  const saveSettings = useSaveCloudStorageSettings();

  // Filtered accounts for active tab
  const filteredAccounts = useMemo(
    () => accounts.filter((a) => a.Provider === activeProvider),
    [accounts, activeProvider],
  );

  const handleAddAccount = () => {
    setEditingAccount(null);
    setDialogOpen(true);
  };

  const handleEditAccount = (account: CloudStorageAccount) => {
    setEditingAccount(account);
    setDialogOpen(true);
  };

  const handleSubmit = (
    data: CloudStorageAccountCreateRequest | { id: number; body: CloudStorageAccountUpdateRequest },
  ) => {
    const isUpdate = "id" in data;

    if (isUpdate) {
      updateAccount.mutate(data, {
        onSuccess: () => {
          toast.success("Account updated");
          setDialogOpen(false);
        },
      });
    } else {
      createAccount.mutate(data, {
        onSuccess: () => {
          toast.success("Account added");
          setDialogOpen(false);
        },
      });
    }
  };

  const handleDelete = (id: number) => {
    deleteAccount.mutate(id, {
      onSuccess: () => toast.success("Account deleted"),
    });
  };

  const handleTest = (id: number) => {
    setTestingId(id);
    setTestResults((prev) => ({ ...prev, [id]: undefined as any }));

    testAccount.mutate(id, {
      onSuccess: (result) => {
        setTestResults((prev) => ({ ...prev, [id]: result }));
        const isSuccess = result.Success;

        if (isSuccess) {
          toast.success(result.Message || "Connection successful");
        } else {
          toast.error(result.Error || "Connection failed");
        }
      },
      onSettled: () => setTestingId(null),
    });
  };

  const handleSaveSettings = (partialSettings: Partial<typeof settings>) => {
    saveSettings.mutate(
      { provider: activeProvider, settings: partialSettings as any },
      {
        onSuccess: () => toast.success("Settings saved"),
      },
    );
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <Cloud className="h-6 w-6 text-primary" />
          <div>
            <h1 className="text-2xl font-bold text-foreground">Cloud Storage</h1>
            <p className="text-sm text-muted-foreground">
              Manage remote backup accounts and provider settings
            </p>
          </div>
        </div>
        <Button onClick={handleAddAccount}>
          <Plus className="h-4 w-4 mr-2" />
          Add Account
        </Button>
      </div>

      {/* Provider Tabs */}
      <Tabs
        value={activeProvider}
        onValueChange={(v) => setActiveProvider(v as CloudStorageProvider)}
      >
        <TabsList>
          {PROVIDERS.map((p) => (
            <TabsTrigger key={p.id} value={p.id} className="flex items-center gap-2">
              {p.icon}
              {p.label}
              {accounts.filter((a) => a.Provider === p.id).length > 0 && (
                <span className="ml-1 text-xs bg-muted text-muted-foreground px-1.5 py-0.5 rounded-full">
                  {accounts.filter((a) => a.Provider === p.id).length}
                </span>
              )}
            </TabsTrigger>
          ))}
        </TabsList>

        {PROVIDERS.map((p) => (
          <TabsContent key={p.id} value={p.id} className="mt-4">
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
              {/* Accounts list (2/3 width) */}
              <div className="lg:col-span-2 space-y-3">
                {accountsLoading ? (
                  <div className="text-center py-12 text-muted-foreground">
                    Loading accounts…
                  </div>
                ) : filteredAccounts.length === 0 ? (
                  <div className="text-center py-12 border border-dashed border-border rounded-lg">
                    <Cloud className="h-10 w-10 mx-auto mb-3 text-muted-foreground/50" />
                    <p className="text-muted-foreground">
                      No {p.label} accounts configured
                    </p>
                    <Button
                      variant="outline"
                      size="sm"
                      className="mt-3"
                      onClick={handleAddAccount}
                    >
                      <Plus className="h-4 w-4 mr-1" />
                      Add {p.label} Account
                    </Button>
                  </div>
                ) : (
                  filteredAccounts.map((account) => (
                    <CloudStorageAccountCard
                      key={account.Id}
                      account={account}
                      onEdit={handleEditAccount}
                      onDelete={handleDelete}
                      onTest={handleTest}
                      isTesting={testingId === account.Id}
                      testResult={testResults[account.Id]}
                    />
                  ))
                )}
              </div>

              {/* Provider settings (1/3 width) */}
              <div>
                <CloudStorageProviderSettings
                  provider={p.id}
                  settings={activeProvider === p.id ? settings : undefined}
                  accounts={accounts}
                  isLoading={settingsLoading && activeProvider === p.id}
                  onSave={handleSaveSettings}
                  isSaving={saveSettings.isPending}
                />
              </div>
            </div>
          </TabsContent>
        ))}
      </Tabs>

      {/* Account Dialog */}
      <CloudStorageAccountDialog
        open={dialogOpen}
        onOpenChange={setDialogOpen}
        account={editingAccount}
        onSubmit={handleSubmit}
        isSubmitting={createAccount.isPending || updateAccount.isPending}
      />
    </div>
  );
}
