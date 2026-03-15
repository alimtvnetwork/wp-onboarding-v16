import { useState, useEffect } from "react";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
  DialogDescription,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Loader2, Eye, EyeOff, Info } from "lucide-react";
import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip";
import type {
  CloudStorageAccount,
  CloudStorageAccountCreateRequest,
  CloudStorageAccountUpdateRequest,
  CloudStorageProvider,
} from "@/types/cloudStorage";
import { PROVIDER_CONFIG } from "@/types/cloudStorage";

interface CloudStorageAccountDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  account?: CloudStorageAccount | null;
  onSubmit: (data: CloudStorageAccountCreateRequest | { id: number; body: CloudStorageAccountUpdateRequest }) => void;
  isSubmitting: boolean;
}

const PROVIDERS: CloudStorageProvider[] = ["GitHub", "GitLab"];

export function CloudStorageAccountDialog({
  open,
  onOpenChange,
  account,
  onSubmit,
  isSubmitting,
}: CloudStorageAccountDialogProps) {
  const isEditing = !!account;

  const [provider, setProvider] = useState<CloudStorageProvider>("GitHub");
  const [label, setLabel] = useState("");
  const [token, setToken] = useState("");
  const [showToken, setShowToken] = useState(false);
  const [fieldValues, setFieldValues] = useState<Record<string, string>>({});

  // Reset form when dialog opens/account changes
  useEffect(() => {
    if (open) {
      if (account) {
        setProvider(account.Provider);
        setLabel(account.AccountLabel);
        setToken("");
        setFieldValues({
          Username: account.Username || "",
          Email: account.Email || "",
          BaseUrl: account.BaseUrl || "",
          RepoOwner: account.RepoOwner || "",
          RepoName: account.RepoName || "",
          FolderId: account.FolderId || "",
          FolderName: account.FolderName || "",
        });
      } else {
        setProvider("GitHub");
        setLabel("");
        setToken("");
        setShowToken(false);
        setFieldValues({});
      }
    }
  }, [open, account]);

  const config = PROVIDER_CONFIG[provider];

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    if (isEditing && account) {
      const body: CloudStorageAccountUpdateRequest = {
        AccountLabel: label,
        ...fieldValues,
      };

      const hasNewToken = token.trim().length > 0;

      if (hasNewToken) {
        body.AccessToken = token;
      }

      onSubmit({ id: account.Id, body });
    } else {
      const data: CloudStorageAccountCreateRequest = {
        Provider: provider,
        AccountLabel: label,
        AccessToken: token,
        ...fieldValues,
      };

      onSubmit(data);
    }
  };

  const updateField = (key: string, value: string) => {
    setFieldValues((prev) => ({ ...prev, [key]: value }));
  };

  const isTokenRequired = !isEditing;
  const isFormValid = label.trim().length > 0 && (isEditing || token.trim().length > 0);

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-[520px] backdrop-blur-xl">
        <DialogHeader>
          <DialogTitle>
            {isEditing ? "Edit Account" : "Add Cloud Storage Account"}
          </DialogTitle>
          <DialogDescription>
            {isEditing
              ? "Update account settings. Leave the token field blank to keep the existing token."
              : "Connect a cloud storage provider for remote backups."}
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={handleSubmit} className="space-y-4">
          {/* Provider selector (only for new accounts) */}
          {!isEditing && (
            <div className="space-y-2">
              <Label>Provider</Label>
              <Select
                value={provider}
                onValueChange={(v) => {
                  setProvider(v as CloudStorageProvider);
                  setFieldValues({});
                }}
              >
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {PROVIDERS.map((p) => (
                    <SelectItem key={p} value={p}>
                      {PROVIDER_CONFIG[p].label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          )}

          {/* Account label */}
          <div className="space-y-2">
            <Label>Account Label</Label>
            <Input
              value={label}
              onChange={(e) => setLabel(e.target.value)}
              placeholder="Work GitHub"
              required
            />
          </div>

          {/* Access token */}
          {config?.authType === "pat" && (
            <div className="space-y-2">
              <div className="flex items-center gap-2">
                <Label>
                  Access Token {isEditing && <span className="text-muted-foreground font-normal">(optional)</span>}
                </Label>
                <Tooltip>
                  <TooltipTrigger type="button">
                    <Info className="h-3.5 w-3.5 text-muted-foreground" />
                  </TooltipTrigger>
                  <TooltipContent className="max-w-xs">
                    <p className="text-sm">{config.tokenHelp}</p>
                  </TooltipContent>
                </Tooltip>
              </div>
              <div className="relative">
                <Input
                  type={showToken ? "text" : "password"}
                  value={token}
                  onChange={(e) => setToken(e.target.value)}
                  placeholder={config.tokenPlaceholder}
                  required={isTokenRequired}
                  className="pr-10 font-mono text-sm"
                />
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  className="absolute right-1 top-1/2 -translate-y-1/2 h-7 w-7"
                  onClick={() => setShowToken((prev) => !prev)}
                >
                  {showToken ? <EyeOff className="h-3.5 w-3.5" /> : <Eye className="h-3.5 w-3.5" />}
                </Button>
              </div>
            </div>
          )}

          {/* Dynamic provider fields */}
          {config?.fields.map((field) => (
            <div key={field.key} className="space-y-2">
              <div className="flex items-center gap-2">
                <Label>{field.label}</Label>
                <Tooltip>
                  <TooltipTrigger type="button">
                    <Info className="h-3.5 w-3.5 text-muted-foreground" />
                  </TooltipTrigger>
                  <TooltipContent className="max-w-xs">
                    <p className="text-sm">{field.help}</p>
                  </TooltipContent>
                </Tooltip>
              </div>
              <Input
                value={fieldValues[field.key] || ""}
                onChange={(e) => updateField(field.key, e.target.value)}
                placeholder={field.placeholder}
                required={field.required}
              />
            </div>
          ))}

          <DialogFooter>
            <Button
              type="button"
              variant="outline"
              onClick={() => onOpenChange(false)}
            >
              Cancel
            </Button>
            <Button type="submit" disabled={!isFormValid || isSubmitting}>
              {isSubmitting && <Loader2 className="h-4 w-4 mr-2 animate-spin" />}
              {isEditing ? "Save Changes" : "Add Account"}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
