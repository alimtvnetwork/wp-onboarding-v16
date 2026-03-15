import { useState, useEffect } from "react";
import { Checkbox } from "@/components/ui/checkbox";
import { Badge } from "@/components/ui/badge";
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from "@/components/ui/collapsible";
import { Cloud, ChevronDown, Github, HardDrive, FolderOpen } from "lucide-react";
import { cn } from "@/lib/utils";
import { useCloudStorageAccounts } from "@/hooks/useCloudStorage";
import { PROVIDER_CONFIG, type CloudStorageAccount } from "@/types/cloudStorage";

const PROVIDER_ICONS: Record<string, React.ReactNode> = {
  GitHub: <Github className="h-3.5 w-3.5" />,
  GitLab: <HardDrive className="h-3.5 w-3.5" />,
  GoogleDrive: <FolderOpen className="h-3.5 w-3.5" />,
};

interface CloudStorageBackupSelectorProps {
  selectedAccountIds: number[];
  onSelectionChange: (accountIds: number[]) => void;
  className?: string;
}

export function CloudStorageBackupSelector({
  selectedAccountIds,
  onSelectionChange,
  className,
}: CloudStorageBackupSelectorProps) {
  const { data: accounts, isLoading } = useCloudStorageAccounts();
  const [isOpen, setIsOpen] = useState(false);

  const activeAccounts = (accounts ?? []).filter((a) => a.IsActive);

  // Auto-open if user previously had selections saved
  useEffect(() => {
    try {
      const saved = localStorage.getItem("wppp_cloud_storage_accounts");
      if (saved) {
        const ids = JSON.parse(saved) as number[];
        if (ids.length > 0) {
          setIsOpen(true);
          // Only restore valid account IDs
          const validIds = ids.filter((id) =>
            activeAccounts.some((a) => a.Id === id)
          );
          if (validIds.length > 0 && selectedAccountIds.length === 0) {
            onSelectionChange(validIds);
          }
        }
      }
    } catch {
      // ignore
    }
  }, [accounts]); // eslint-disable-line react-hooks/exhaustive-deps

  // Persist selection
  useEffect(() => {
    try {
      localStorage.setItem(
        "wppp_cloud_storage_accounts",
        JSON.stringify(selectedAccountIds)
      );
    } catch {
      // ignore
    }
  }, [selectedAccountIds]);

  const toggleAccount = (accountId: number) => {
    const isSelected = selectedAccountIds.includes(accountId);

    if (isSelected) {
      onSelectionChange(selectedAccountIds.filter((id) => id !== accountId));
    } else {
      onSelectionChange([...selectedAccountIds, accountId]);
    }
  };

  if (isLoading || activeAccounts.length === 0) {
    return null;
  }

  return (
    <Collapsible open={isOpen} onOpenChange={setIsOpen} className={className}>
      <CollapsibleTrigger className="flex w-full items-center justify-between rounded-md border px-3 py-2 text-sm hover:bg-muted/50 transition-colors">
        <span className="flex items-center gap-2 text-muted-foreground">
          <Cloud className="h-4 w-4" />
          Upload backup to cloud storage
          {selectedAccountIds.length > 0 && (
            <Badge variant="secondary" className="ml-1 text-xs px-1.5 py-0">
              {selectedAccountIds.length}
            </Badge>
          )}
        </span>
        <ChevronDown
          className={cn(
            "h-4 w-4 text-muted-foreground transition-transform",
            isOpen && "rotate-180"
          )}
        />
      </CollapsibleTrigger>
      <CollapsibleContent className="pt-2 space-y-1">
        {activeAccounts.map((account) => (
          <AccountRow
            key={account.Id}
            account={account}
            isSelected={selectedAccountIds.includes(account.Id)}
            onToggle={() => toggleAccount(account.Id)}
          />
        ))}
      </CollapsibleContent>
    </Collapsible>
  );
}

function AccountRow({
  account,
  isSelected,
  onToggle,
}: {
  account: CloudStorageAccount;
  isSelected: boolean;
  onToggle: () => void;
}) {
  const config = PROVIDER_CONFIG[account.Provider];
  const icon = PROVIDER_ICONS[account.Provider];

  return (
    <label
      className={cn(
        "flex items-center gap-3 rounded-md border px-3 py-2 cursor-pointer transition-colors",
        isSelected
          ? "border-primary/40 bg-primary/5"
          : "hover:bg-muted/50"
      )}
    >
      <Checkbox
        checked={isSelected}
        onCheckedChange={onToggle}
        className="shrink-0"
      />
      <span className="flex items-center gap-1.5 text-muted-foreground">
        {icon}
      </span>
      <div className="flex-1 min-w-0">
        <p className="text-sm font-medium truncate">{account.AccountLabel}</p>
        <p className="text-xs text-muted-foreground truncate">
          {config?.label} · {account.TokenMask}
        </p>
      </div>
    </label>
  );
}
