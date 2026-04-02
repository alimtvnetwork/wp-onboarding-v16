// Failure analysis panel — categorized error breakdown with examples.

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Progress } from "@/components/ui/progress";
import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from "@/components/ui/accordion";
import {
  AlertTriangle,
  Wifi,
  Clock,
  Shield,
  HardDrive,
  Upload,
  Database,
  HelpCircle,
  Zap,
} from "lucide-react";
import type { FailureCategory } from "@/hooks/usePublishAnalytics";

interface Props {
  failures: FailureCategory[];
  totalFailed: number;
}

const CATEGORY_ICONS: Record<string, React.ReactNode> = {
  Timeout: <Clock className="h-4 w-4" />,
  Network: <Wifi className="h-4 w-4" />,
  Activation: <Zap className="h-4 w-4" />,
  Permission: <Shield className="h-4 w-4" />,
  Storage: <HardDrive className="h-4 w-4" />,
  Upload: <Upload className="h-4 w-4" />,
  Backup: <Database className="h-4 w-4" />,
  Other: <HelpCircle className="h-4 w-4" />,
};

const CATEGORY_COLORS: Record<string, string> = {
  Timeout: "text-warning",
  Network: "text-destructive",
  Activation: "text-orange-500",
  Permission: "text-destructive",
  Storage: "text-warning",
  Upload: "text-orange-500",
  Backup: "text-muted-foreground",
  Other: "text-muted-foreground",
};

export function FailureAnalysisPanel({ failures, totalFailed }: Props) {
  if (failures.length === 0) {
    return (
      <Card>
        <CardHeader className="pb-2">
          <CardTitle className="text-sm font-medium flex items-center gap-2">
            <AlertTriangle className="h-4 w-4 text-primary" />
            Failure Analysis
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div className="text-center py-8 text-sm text-muted-foreground">
            🎉 No failures in the selected period
          </div>
        </CardContent>
      </Card>
    );
  }

  return (
    <Card>
      <CardHeader className="pb-2">
        <div className="flex items-center justify-between">
          <CardTitle className="text-sm font-medium flex items-center gap-2">
            <AlertTriangle className="h-4 w-4 text-primary" />
            Failure Analysis
          </CardTitle>
          <Badge variant="destructive" className="text-xs">
            {totalFailed} failure{totalFailed !== 1 ? "s" : ""}
          </Badge>
        </div>
      </CardHeader>
      <CardContent className="space-y-3">
        {/* Category breakdown bars */}
        <div className="space-y-3">
          {failures.map((f) => (
            <div key={f.category} className="space-y-1.5">
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                  <span className={CATEGORY_COLORS[f.category] ?? "text-muted-foreground"}>
                    {CATEGORY_ICONS[f.category] ?? CATEGORY_ICONS.Other}
                  </span>
                  <span className="text-sm font-medium">{f.category}</span>
                </div>
                <div className="flex items-center gap-2">
                  <span className="text-xs font-mono text-muted-foreground">{f.count}</span>
                  <Badge variant="outline" className="text-[10px] px-1.5">
                    {f.percentage}%
                  </Badge>
                </div>
              </div>
              <Progress value={f.percentage} className="h-1.5" />
            </div>
          ))}
        </div>

        {/* Expandable error examples */}
        <Accordion type="single" collapsible className="mt-4">
          {failures.map((f) => (
            <AccordionItem key={f.category} value={f.category} className="border-b-0">
              <AccordionTrigger className="text-xs py-2 hover:no-underline">
                <span className="flex items-center gap-1.5">
                  {f.category} — {f.examples.length} example{f.examples.length !== 1 ? "s" : ""}
                </span>
              </AccordionTrigger>
              <AccordionContent>
                <div className="space-y-1.5">
                  {f.examples.map((ex, i) => (
                    <div
                      key={i}
                      className="text-xs font-mono bg-muted/50 rounded px-2 py-1.5 text-muted-foreground break-all"
                    >
                      {ex}
                    </div>
                  ))}
                </div>
              </AccordionContent>
            </AccordionItem>
          ))}
        </Accordion>
      </CardContent>
    </Card>
  );
}
