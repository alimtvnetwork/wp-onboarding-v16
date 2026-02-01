import { useState } from "react";
import { useSites } from "@/hooks/useSites";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { EmptyState } from "@/components/shared/EmptyState";
import { Globe, Plus, Loader2, X } from "lucide-react";
import { cn } from "@/lib/utils";

export default function Sites() {
  const { data: sites, isLoading } = useSites();
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
          <h1 className="text-2xl font-bold">Sites</h1>
          <p className="text-muted-foreground">
            Manage your WordPress site connections
          </p>
        </div>
        <Button onClick={() => setShowForm(true)}>
          <Plus className="h-4 w-4 mr-2" />
          Add Site
        </Button>
      </div>

      {sites?.length === 0 ? (
        <EmptyState
          icon={Globe}
          title="No sites connected"
          description="Add your first WordPress site to start syncing plugins."
          action={{
            label: "Add Site",
            onClick: () => setShowForm(true),
          }}
        />
      ) : (
        <div className="grid gap-4">
          {sites?.map((site) => (
            <Card key={site.id}>
              <CardContent className="flex items-center justify-between p-4">
                <div className="flex items-center gap-4">
                  <Globe className="h-8 w-8 text-muted-foreground" />
                  <div>
                    <h3 className="font-semibold">{site.name}</h3>
                    <p className="text-sm text-muted-foreground">{site.url}</p>
                    <div className="flex items-center gap-2 mt-1">
                      <span
                        className={cn(
                          "w-2 h-2 rounded-full",
                          site.connectionStatus === "connected"
                            ? "bg-green-500"
                            : site.connectionStatus === "disconnected"
                            ? "bg-red-500"
                            : "bg-gray-400"
                        )}
                      />
                      <span className="text-xs text-muted-foreground">
                        {site.connectionStatus === "connected"
                          ? "Connected"
                          : site.connectionStatus === "disconnected"
                          ? "Disconnected"
                          : "Not tested"}
                      </span>
                    </div>
                  </div>
                </div>

                <div className="flex gap-2">
                  <Button variant="outline" size="sm">
                    Test
                  </Button>
                  <Button variant="outline" size="sm">
                    Edit
                  </Button>
                  <Button variant="ghost" size="sm">
                    <X className="h-4 w-4" />
                  </Button>
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      )}
    </div>
  );
}
