import { Link } from "react-router-dom";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Activity, ArrowRight, Plus, FolderPlus, FileCode, Settings } from "lucide-react";

interface QuickActionsProps {
  onAddSite: () => void;
}

const actions = [
  {
    title: "Add WordPress Site",
    description: "Connect a new WordPress site for plugin deployment",
    icon: Plus,
    href: undefined as string | undefined,
    actionKey: "addSite",
  },
  {
    title: "Register Plugin",
    description: "Add a local plugin directory to watch for changes",
    icon: FolderPlus,
    href: "/plugins",
    actionKey: undefined as string | undefined,
  },
  {
    title: "View Logs",
    description: "Check application logs and debug issues",
    icon: FileCode,
    href: "/logs",
  },
  {
    title: "Settings",
    description: "Configure application preferences",
    icon: Settings,
    href: "/settings",
  },
];

export function QuickActions({ onAddSite }: QuickActionsProps) {
  return (
    <Card>
      <CardHeader className="p-4 sm:p-6 pb-2 sm:pb-4">
        <CardTitle className="text-base sm:text-lg flex items-center gap-2">
          <Activity className="h-4 w-4 sm:h-5 sm:w-5 text-primary" />
          Quick Actions
        </CardTitle>
      </CardHeader>
      <CardContent className="grid gap-2 p-4 sm:p-6 pt-0">
        {actions.map((action) => {
          const inner = (
            <Button
              variant="outline"
              className="w-full justify-start h-auto py-2.5 sm:py-3 hover:border-primary/40 hover:bg-secondary/50"
              onClick={action.actionKey === "addSite" ? onAddSite : undefined}
            >
              <action.icon className="h-4 w-4 mr-2 sm:mr-3 text-primary shrink-0" />
              <div className="text-left min-w-0 flex-1">
                <p className="font-medium text-sm">{action.title}</p>
                <p className="text-xs text-muted-foreground truncate">{action.description}</p>
              </div>
              <ArrowRight className="h-4 w-4 ml-auto text-muted-foreground shrink-0 hidden sm:block" />
            </Button>
          );

          return action.href ? (
            <Link to={action.href} key={action.title}>{inner}</Link>
          ) : (
            <div key={action.title}>{inner}</div>
          );
        })}
      </CardContent>
    </Card>
  );
}
