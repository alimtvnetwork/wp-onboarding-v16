import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
} from "@/components/ui/dialog";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Badge } from "@/components/ui/badge";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Separator } from "@/components/ui/separator";
import {
  Sparkles,
  Clock,
  AlertCircle,
  Rocket,
  CheckCircle2,
  Circle,
  Loader2,
} from "lucide-react";
import { useWhatsNew, ChangelogEntry, RoadmapItem } from "@/hooks/useWhatsNew";

interface WhatsNewModalProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

function ChangelogSection({ entry }: { entry: ChangelogEntry }) {
  return (
    <div className="space-y-4">
      <div className="flex items-center gap-3">
        <Badge variant="default" className="text-sm">
          v{entry.version}
        </Badge>
        <span className="text-sm text-muted-foreground flex items-center gap-1">
          <Clock className="h-3 w-3" />
          {entry.date}
        </span>
      </div>

      <h3 className="font-semibold text-lg">{entry.title}</h3>

      <div className="space-y-2">
        <h4 className="text-sm font-medium text-muted-foreground">What's New</h4>
        <ul className="space-y-1.5">
          {entry.changes.map((change, idx) => (
            <li key={idx} className="text-sm flex items-start gap-2">
              <span className="mt-0.5">•</span>
              <span>{change}</span>
            </li>
          ))}
        </ul>
      </div>

      {entry.knownIssues && entry.knownIssues.length > 0 && (
        <div className="space-y-2 mt-4">
          <h4 className="text-sm font-medium text-muted-foreground flex items-center gap-1">
            <AlertCircle className="h-3 w-3" />
            Known Issues
          </h4>
          <ul className="space-y-1.5">
            {entry.knownIssues.map((issue, idx) => (
              <li
                key={idx}
                className="text-sm text-muted-foreground flex items-start gap-2"
              >
                <span className="mt-0.5">•</span>
                <span>{issue}</span>
              </li>
            ))}
          </ul>
        </div>
      )}
    </div>
  );
}

function RoadmapSection({ items }: { items: RoadmapItem[] }) {
  const getStatusIcon = (status: RoadmapItem["status"]) => {
    switch (status) {
      case "completed":
        return <CheckCircle2 className="h-4 w-4 text-green-500" />;
      case "in-progress":
        return <Loader2 className="h-4 w-4 text-blue-500 animate-spin" />;
      case "planned":
        return <Circle className="h-4 w-4 text-muted-foreground" />;
    }
  };

  const getStatusBadge = (status: RoadmapItem["status"]) => {
    switch (status) {
      case "completed":
        return <Badge variant="default">Done</Badge>;
      case "in-progress":
        return (
          <Badge variant="secondary" className="bg-blue-100 text-blue-700">
            In Progress
          </Badge>
        );
      case "planned":
        return <Badge variant="outline">Planned</Badge>;
    }
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center gap-2">
        <Rocket className="h-4 w-4" />
        <h3 className="font-semibold">Roadmap</h3>
      </div>

      <div className="space-y-3">
        {items.map((item, idx) => (
          <div
            key={idx}
            className="flex items-start gap-3 p-3 rounded-lg border bg-card"
          >
            <div className="mt-0.5">{getStatusIcon(item.status)}</div>
            <div className="flex-1 space-y-1">
              <div className="flex items-center gap-2">
                <span className="font-medium text-sm">{item.title}</span>
                {getStatusBadge(item.status)}
              </div>
              <p className="text-sm text-muted-foreground">{item.description}</p>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

function VersionHistorySection({ changelog }: { changelog: ChangelogEntry[] }) {
  return (
    <div className="space-y-6">
      {changelog.map((entry, idx) => (
        <div key={entry.version}>
          <ChangelogSection entry={entry} />
          {idx < changelog.length - 1 && <Separator className="mt-6" />}
        </div>
      ))}
    </div>
  );
}

export function WhatsNewModal({ open, onOpenChange }: WhatsNewModalProps) {
  const { versionInfo, isLoading } = useWhatsNew();

  if (isLoading || !versionInfo) {
    return null;
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-2xl max-h-[90vh] overflow-hidden flex flex-col">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2 text-xl">
            <Sparkles className="h-5 w-5 text-primary" />
            What's New in v{versionInfo.version}
          </DialogTitle>
          <DialogDescription>
            Check out the latest updates, upcoming features, and known issues.
          </DialogDescription>
        </DialogHeader>

        <Tabs defaultValue="latest" className="flex-1 flex flex-col min-h-0">
          <TabsList className="grid w-full grid-cols-3">
            <TabsTrigger value="latest">Latest</TabsTrigger>
            <TabsTrigger value="roadmap">Roadmap</TabsTrigger>
            <TabsTrigger value="history">History</TabsTrigger>
          </TabsList>

          <ScrollArea className="flex-1 mt-4">
            <TabsContent value="latest" className="mt-0 pr-4">
              {versionInfo.changelog[0] && (
                <ChangelogSection entry={versionInfo.changelog[0]} />
              )}
            </TabsContent>

            <TabsContent value="roadmap" className="mt-0 pr-4">
              <RoadmapSection items={versionInfo.roadmap} />
            </TabsContent>

            <TabsContent value="history" className="mt-0 pr-4">
              <VersionHistorySection changelog={versionInfo.changelog} />
            </TabsContent>
          </ScrollArea>
        </Tabs>
      </DialogContent>
    </Dialog>
  );
}

export default WhatsNewModal;
