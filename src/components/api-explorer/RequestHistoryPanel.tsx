import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from "@/components/ui/collapsible";
import { ScrollArea } from "@/components/ui/scroll-area";
import { History, Trash2, Clock, ChevronDown, Copy, Check } from "lucide-react";
import { useState } from "react";

export interface RequestHistoryItem {
  id: string;
  method: string;
  url: string;
  status: number;
  duration: number;
  timestamp: Date;
  requestBody?: string;
  responseBody?: string;
}

interface RequestHistoryPanelProps {
  history: RequestHistoryItem[];
  onClear: () => void;
}

export function RequestHistoryPanel({ history, onClear }: RequestHistoryPanelProps) {
  const [expandedItems, setExpandedItems] = useState<Set<string>>(new Set());
  const [copiedId, setCopiedId] = useState<string | null>(null);

  const toggleExpand = (id: string) => {
    setExpandedItems(prev => {
      const next = new Set(prev);
      next.has(id) ? next.delete(id) : next.add(id);
      return next;
    });
  };

  const copyToClipboard = async (text: string, id: string) => {
    await navigator.clipboard.writeText(text);
    setCopiedId(id);
    setTimeout(() => setCopiedId(null), 2000);
  };

  return (
    <Card>
      <CardHeader className="pb-3">
        <div className="flex items-center justify-between">
          <CardTitle className="text-lg flex items-center gap-2">
            <History className="h-4 w-4" />
            Request History
          </CardTitle>
          {history.length > 0 && (
            <Button variant="ghost" size="sm" onClick={onClear}>
              <Trash2 className="h-4 w-4" />
            </Button>
          )}
        </div>
      </CardHeader>
      <CardContent className="p-0">
        <ScrollArea className="h-[500px]">
          {history.length === 0 ? (
            <div className="p-4 text-center text-muted-foreground text-sm">
              <History className="h-8 w-8 mx-auto mb-2 opacity-50" />
              <p>No requests yet</p>
              <p className="text-xs mt-1">Requests will appear here as you test the API</p>
            </div>
          ) : (
            <div className="divide-y">
              {history.map((item) => {
                const isExpanded = expandedItems.has(item.id);
                const hasDetails = item.requestBody || item.responseBody;

                return (
                  <Collapsible
                    key={item.id}
                    open={isExpanded}
                    onOpenChange={() => hasDetails && toggleExpand(item.id)}
                  >
                    <CollapsibleTrigger asChild disabled={!hasDetails}>
                      <div className={`p-3 transition-colors ${hasDetails ? 'cursor-pointer hover:bg-muted/50' : ''}`}>
                        <div className="flex items-center gap-2 mb-1">
                          <Badge variant="outline" className={`text-xs font-mono ${getMethodColor(item.method)}`}>
                            {item.method}
                          </Badge>
                          <span className={`text-sm font-medium ${getStatusColor(item.status)}`}>
                            {item.status === 0 ? "..." : item.status}
                          </span>
                          <span className="text-xs text-muted-foreground flex items-center gap-1">
                            <Clock className="h-3 w-3" />
                            {item.duration}ms
                          </span>
                          {hasDetails && (
                            <ChevronDown className={`h-3 w-3 ml-auto text-muted-foreground transition-transform ${isExpanded ? 'rotate-180' : ''}`} />
                          )}
                        </div>
                        <p className="text-xs text-muted-foreground truncate font-mono" title={item.url}>
                          {item.url.replace(/^https?:\/\/[^/]+/, "")}
                        </p>
                        <p className="text-xs text-muted-foreground mt-1">
                          {item.timestamp.toLocaleTimeString()}
                        </p>
                      </div>
                    </CollapsibleTrigger>

                    <CollapsibleContent>
                      <div className="px-3 pb-3 space-y-2">
                        {item.requestBody && (
                          <BodySection
                            label="Request Body"
                            body={item.requestBody}
                            copyId={`req-${item.id}`}
                            copiedId={copiedId}
                            onCopy={copyToClipboard}
                          />
                        )}
                        {item.responseBody && (
                          <BodySection
                            label="Response Body"
                            body={item.responseBody}
                            copyId={`res-${item.id}`}
                            copiedId={copiedId}
                            onCopy={copyToClipboard}
                          />
                        )}
                      </div>
                    </CollapsibleContent>
                  </Collapsible>
                );
              })}
            </div>
          )}
        </ScrollArea>
      </CardContent>
    </Card>
  );
}

function BodySection({ label, body, copyId, copiedId, onCopy }: {
  label: string;
  body: string;
  copyId: string;
  copiedId: string | null;
  onCopy: (text: string, id: string) => void;
}) {
  return (
    <div className="space-y-1">
      <div className="flex items-center justify-between">
        <span className="text-xs font-medium text-muted-foreground">{label}</span>
        <Button
          variant="ghost"
          size="sm"
          className="h-6 px-2"
          onClick={(e) => { e.stopPropagation(); onCopy(body, copyId); }}
        >
          {copiedId === copyId ? <Check className="h-3 w-3" /> : <Copy className="h-3 w-3" />}
        </Button>
      </div>
      <pre className="text-xs bg-muted/50 p-2 rounded overflow-x-auto max-h-48 whitespace-pre-wrap break-all">
        {body}
      </pre>
    </div>
  );
}

function getStatusColor(status: number) {
  if (status === 0) return "text-muted-foreground";
  if (status >= 200 && status < 300) return "text-emerald-600 dark:text-emerald-400";
  if (status >= 400 && status < 500) return "text-amber-600 dark:text-amber-400";
  return "text-destructive";
}

function getMethodColor(method: string) {
  switch (method.toUpperCase()) {
    case "GET": return "bg-primary/20 text-primary";
    case "POST": return "bg-emerald-500/20 text-emerald-700 dark:text-emerald-400";
    case "PUT": return "bg-amber-500/20 text-amber-700 dark:text-amber-400";
    case "DELETE": return "bg-destructive/20 text-destructive";
    default: return "bg-muted text-muted-foreground";
  }
}
