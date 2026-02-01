import { useState } from "react";
import { usePlugins } from "@/hooks/usePlugins";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { EmptyState } from "@/components/shared/EmptyState";
import { Package, Plus, Loader2, Eye, FileText, AlertCircle, X } from "lucide-react";
import { cn } from "@/lib/utils";

export default function Plugins() {
  const { data: plugins, isLoading } = usePlugins();
  const [showForm, setShowForm] = useState(false);

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
          <h1 className="text-2xl font-bold">Plugins</h1>
          <p className="text-muted-foreground">
            Register local plugin directories for syncing
          </p>
        </div>
        <Button onClick={() => setShowForm(true)}>
          <Plus className="h-4 w-4 mr-2" />
          Register Plugin
        </Button>
      </div>

      {plugins?.length === 0 ? (
        <EmptyState
          icon={Package}
          title="No plugins registered"
          description="Register a local plugin directory to start syncing with WordPress sites."
          action={{
            label: "Register Plugin",
            onClick: () => setShowForm(true),
          }}
        />
      ) : (
        <div className="grid gap-4">
          {plugins?.map((plugin) => (
            <Card key={plugin.id}>
              <CardContent className="p-4 space-y-3">
                <div className="flex items-start justify-between">
                  <div className="flex items-center gap-3">
                    <Package className="h-8 w-8 text-muted-foreground" />
                    <div>
                      <h3 className="font-semibold">{plugin.name}</h3>
                      <p className="text-sm text-muted-foreground font-mono">
                        {plugin.path}
                      </p>
                    </div>
                  </div>

                  <div className="flex gap-2">
                    <Button variant="outline" size="sm">
                      Mappings
                    </Button>
                    <Button variant="outline" size="sm">
                      Edit
                    </Button>
                    <Button variant="ghost" size="sm">
                      <X className="h-4 w-4" />
                    </Button>
                  </div>
                </div>

                {/* Mapped Sites */}
                {plugin.mappings && plugin.mappings.length > 0 && (
                  <div className="bg-muted/50 rounded-md p-3">
                    <p className="text-xs font-medium text-muted-foreground mb-2">
                      Mapped Sites:
                    </p>
                    <ul className="space-y-1">
                      {plugin.mappings.map((mapping) => (
                        <li
                          key={mapping.id}
                          className="text-sm flex items-center gap-2"
                        >
                          <span className="w-1.5 h-1.5 rounded-full bg-primary" />
                          {mapping.siteName}
                          <span className="text-muted-foreground">
                            ({mapping.remoteSlug})
                          </span>
                        </li>
                      ))}
                    </ul>
                  </div>
                )}

                {/* Stats Row */}
                <div className="flex items-center gap-4 pt-2 border-t text-sm">
                  <span className="flex items-center gap-1.5">
                    <Eye
                      className={cn(
                        "h-4 w-4",
                        plugin.watchEnabled
                          ? "text-green-500"
                          : "text-muted-foreground"
                      )}
                    />
                    Watching: {plugin.watchEnabled ? "ON" : "OFF"}
                  </span>

                  <span className="flex items-center gap-1.5 text-muted-foreground">
                    <FileText className="h-4 w-4" />
                    Files: {plugin.fileCount}
                  </span>

                  <span
                    className={cn(
                      "flex items-center gap-1.5",
                      plugin.modifiedCount > 0
                        ? "text-yellow-600"
                        : "text-muted-foreground"
                    )}
                  >
                    <AlertCircle className="h-4 w-4" />
                    Modified: {plugin.modifiedCount}
                  </span>
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      )}
    </div>
  );
}
