import { useState } from "react";
import { usePlugins } from "@/hooks/usePlugins";
import { Button } from "@/components/ui/button";
import { EmptyState } from "@/components/shared/EmptyState";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Label } from "@/components/ui/label";
import { RefreshCw, Link, Loader2 } from "lucide-react";

export default function Sync() {
  const { data: plugins, isLoading } = usePlugins();
  const [selectedPluginId, setSelectedPluginId] = useState<string>("");

  const selectedPlugin = plugins?.find(
    (p) => p.id === Number(selectedPluginId)
  );

  if (isLoading) {
    return (
      <div className="flex items-center justify-center py-12">
        <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-2xl font-bold">Sync Dashboard</h1>
          <p className="text-muted-foreground">
            View file changes and publish to WordPress sites
          </p>
        </div>
        <Button disabled={!selectedPluginId}>
          <RefreshCw className="h-4 w-4 mr-2" />
          Check All Sites
        </Button>
      </div>

      {/* Filters */}
      <div className="flex gap-4">
        <div className="w-64">
          <Label className="text-sm text-muted-foreground mb-1.5 block">
            Plugin
          </Label>
          <Select value={selectedPluginId} onValueChange={setSelectedPluginId}>
            <SelectTrigger>
              <SelectValue placeholder="Select plugin..." />
            </SelectTrigger>
            <SelectContent>
              {plugins?.map((plugin) => (
                <SelectItem key={plugin.id} value={String(plugin.id)}>
                  {plugin.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      </div>

      {/* Content */}
      {!selectedPluginId ? (
        <EmptyState
          icon={RefreshCw}
          title="Select a plugin"
          description="Choose a plugin to view its sync status across sites."
        />
      ) : !selectedPlugin?.mappings?.length ? (
        <EmptyState
          icon={Link}
          title="No site mappings"
          description="This plugin isn't mapped to any sites yet. Go to Plugins page to add mappings."
        />
      ) : (
        <div className="space-y-4">
          {selectedPlugin.mappings.map((mapping) => (
            <div
              key={mapping.id}
              className="border rounded-lg p-4 bg-card space-y-4"
            >
              <div className="flex items-center justify-between">
                <div>
                  <h3 className="font-semibold">{mapping.siteName}</h3>
                  <p className="text-sm text-muted-foreground">
                    {mapping.siteUrl}
                  </p>
                </div>
                <div className="flex gap-2">
                  <Button variant="outline" size="sm">
                    Check
                  </Button>
                  <Button size="sm">Publish</Button>
                </div>
              </div>

              <p className="text-sm text-muted-foreground">
                No changes detected. Click "Check" to compare with remote.
              </p>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
