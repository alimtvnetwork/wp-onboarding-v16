import { useState, useEffect } from "react";
import { Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Eye, EyeOff, Loader2, ExternalLink } from "lucide-react";
import { api } from "@/lib/api";
import { toast } from "sonner";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";

interface GoogleOAuthSettings {
  GoogleOAuthClientId?: string;
  GoogleOAuthClientSecret?: string;
  GoogleOAuthConfigured?: boolean;
}

export function GoogleOAuthSettingsPanel() {
  const queryClient = useQueryClient();
  const [clientId, setClientId] = useState("");
  const [clientSecret, setClientSecret] = useState("");
  const [showSecret, setShowSecret] = useState(false);
  const [hasExisting, setHasExisting] = useState(false);

  const { data, isLoading } = useQuery({
    queryKey: ["cloud-storage-settings", "googledrive"],
    queryFn: async () => {
      const result = await api.getCloudStorageSettings("googledrive");
      if (!result.success) throw new Error("Failed to load settings");

      return result.data as GoogleOAuthSettings;
    },
  });

  useEffect(() => {
    if (data) {
      setClientId(data.GoogleOAuthClientId ?? "");
      setHasExisting(data.GoogleOAuthConfigured ?? false);
    }
  }, [data]);

  const saveMutation = useMutation({
    mutationFn: async () => {
      const trimmedId = clientId.trim();
      const trimmedSecret = clientSecret.trim();

      if (!trimmedId) {
        throw new Error("Client ID is required");
      }

      const payload: Record<string, unknown> = {
        GoogleOAuthClientId: trimmedId,
      };

      if (trimmedSecret) {
        payload.GoogleOAuthClientSecret = trimmedSecret;
      }

      const result = await api.updateCloudStorageSettings("googledrive", payload);
      if (!result.success) throw new Error("Failed to save settings");

      return result.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["cloud-storage-settings", "googledrive"] });
      setClientSecret("");
      setShowSecret(false);
      toast.success("Google OAuth credentials saved", {
        style: {
          background: "linear-gradient(135deg, hsl(142 76% 36%) 0%, hsl(142 76% 30%) 100%)",
          color: "white",
          border: "none",
        },
      });
    },
    onError: (err: Error) => {
      toast.error(`Failed to save: ${err.message}`);
    },
  });

  if (isLoading) {
    return (
      <div className="flex items-center justify-center py-8">
        <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />
      </div>
    );
  }

  return (
    <div className="space-y-4 sm:space-y-6">
      <div>
        <h2 className="text-base sm:text-lg font-semibold mb-1">Google OAuth Credentials</h2>
        <p className="text-xs sm:text-sm text-muted-foreground">
          Configure OAuth 2.0 credentials for Google Drive cloud storage integration.
          These are required to enable the "Connect with Google" flow for Google Drive backup accounts.
        </p>
      </div>

      {hasExisting && (
        <div className="flex items-center gap-2 p-3 rounded-lg bg-accent/50 border border-accent">
          <div className="h-2 w-2 rounded-full bg-green-500 shrink-0" />
          <p className="text-xs text-muted-foreground">
            Google OAuth credentials are configured. Update them below if needed.
          </p>
        </div>
      )}

      <div className="space-y-4">
        <div className="space-y-2">
          <Label htmlFor="google-client-id" className="text-sm">Client ID</Label>
          <Input
            id="google-client-id"
            value={clientId}
            onChange={(e) => setClientId(e.target.value)}
            placeholder="123456789-abc.apps.googleusercontent.com"
            className="h-9 sm:h-10 font-mono text-xs"
            autoComplete="off"
          />
        </div>

        <div className="space-y-2">
          <Label htmlFor="google-client-secret" className="text-sm">
            Client Secret
            {hasExisting && (
              <span className="ml-2 text-xs text-muted-foreground font-normal">
                (leave blank to keep existing)
              </span>
            )}
          </Label>
          <div className="relative">
            <Input
              id="google-client-secret"
              type={showSecret ? "text" : "password"}
              value={clientSecret}
              onChange={(e) => setClientSecret(e.target.value)}
              placeholder={hasExisting ? "••••••••••••" : "GOCSPX-..."}
              className="h-9 sm:h-10 font-mono text-xs pr-10"
              autoComplete="new-password"
            />
            <button
              type="button"
              onClick={() => setShowSecret(!showSecret)}
              className="absolute right-2 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground transition-colors"
            >
              {showSecret ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
            </button>
          </div>
        </div>

        <div className="flex flex-col sm:flex-row gap-2 pt-2">
          <Button
            size="sm"
            onClick={() => saveMutation.mutate()}
            disabled={saveMutation.isPending || !clientId.trim()}
            className="w-full sm:w-auto"
          >
            {saveMutation.isPending && <Loader2 className="h-4 w-4 mr-2 animate-spin" />}
            {hasExisting ? "Update Credentials" : "Save Credentials"}
          </Button>
        </div>
      </div>

      <div className="pt-4 border-t space-y-2">
        <p className="text-xs font-medium text-muted-foreground">How to obtain credentials</p>
        <ol className="text-xs text-muted-foreground space-y-1 list-decimal list-inside">
          <li>Go to the Google Cloud Console</li>
          <li>Create or select a project</li>
          <li>Navigate to <strong>APIs & Services → Credentials</strong></li>
          <li>Create an <strong>OAuth 2.0 Client ID</strong> (Web application type)</li>
          <li>Add your WordPress site URL as an authorized redirect URI</li>
          <li>Copy the Client ID and Client Secret here</li>
        </ol>
        <a
          href="https://console.cloud.google.com/apis/credentials"
          target="_blank"
          rel="noopener noreferrer"
          className="inline-flex items-center gap-1 text-xs text-primary hover:underline mt-1"
        >
          Open Google Cloud Console
          <ExternalLink className="h-3 w-3" />
        </a>
      </div>
    </div>
  );
}
