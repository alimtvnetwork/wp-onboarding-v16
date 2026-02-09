import { CapturedError } from '@/stores/errorStore';
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Copy, ChevronRight, Layers, Server, Route, AlertTriangle } from "lucide-react";
import { cn } from "@/lib/utils";

interface TraversalDetailsProps {
  error: CapturedError;
  copySection: (label: string, content: string) => void;
}

export function TraversalDetails({ error, copySection }: TraversalDetailsProps) {
  const hasEndpoints = error.requestedAt || error.requestDelegatedAt;
  const hasMethodsStack = error.envelopeMethodsStack?.Backend && error.envelopeMethodsStack.Backend.length > 0;
  const hasDelegatedStack = error.envelopeErrors?.DelegatedServiceErrorStack && error.envelopeErrors.DelegatedServiceErrorStack.length > 0;
  const hasBackendTrace = error.envelopeErrors?.Backend && error.envelopeErrors.Backend.length > 0;

  const copyAll = () => {
    const parts: string[] = [];
    if (error.requestedAt) parts.push(`Requested At: ${error.requestedAt}`);
    if (error.requestDelegatedAt) parts.push(`Delegated At: ${error.requestDelegatedAt}`);
    if (hasMethodsStack) {
      parts.push(`\nMethods Stack:\n${error.envelopeMethodsStack!.Backend.map((f, i) => `  #${i} ${f.Method} at ${f.File}:${f.LineNumber}`).join('\n')}`);
    }
    if (hasDelegatedStack) {
      parts.push(`\nDelegated Service Error Stack:\n${error.envelopeErrors!.DelegatedServiceErrorStack!.map(l => `  ${l}`).join('\n')}`);
    }
    if (hasBackendTrace) {
      parts.push(`\nBackend Trace:\n${error.envelopeErrors!.Backend!.map(l => `  ${l}`).join('\n')}`);
    }
    copySection("Traversal details", parts.join('\n'));
  };

  return (
    <div className="space-y-4">
      {hasEndpoints && (
        <div>
          <div className="flex items-center justify-between mb-2">
            <h4 className="text-sm font-medium text-muted-foreground flex items-center gap-2">
              <Route className="h-4 w-4" />
              Endpoint Flow
            </h4>
            <Button variant="ghost" size="sm" onClick={copyAll}>
              <Copy className="h-4 w-4" />
            </Button>
          </div>
          <div className="bg-muted p-3 rounded-md space-y-2">
            {error.requestedAt && (
              <div className="flex items-center gap-2 text-sm">
                <Badge variant="outline" className="shrink-0 text-xs">Go</Badge>
                <code className="text-xs bg-background/60 px-1.5 py-0.5 rounded break-all">{error.requestedAt}</code>
              </div>
            )}
            {error.requestDelegatedAt && (
              <>
                <div className="flex items-center justify-center">
                  <ChevronRight className="h-4 w-4 text-muted-foreground" />
                </div>
                <div className="flex items-center gap-2 text-sm">
                  <Badge variant="outline" className="shrink-0 text-xs bg-orange-500/10 border-orange-500/30 text-orange-600 dark:text-orange-400">PHP</Badge>
                  <code className="text-xs bg-background/60 px-1.5 py-0.5 rounded break-all">{error.requestDelegatedAt}</code>
                </div>
              </>
            )}
          </div>
        </div>
      )}

      {hasMethodsStack && (
        <div>
          <h4 className="text-sm font-medium text-muted-foreground mb-2 flex items-center gap-2">
            <Layers className="h-4 w-4" />
            Methods Stack ({error.envelopeMethodsStack!.Backend.length})
          </h4>
          <div className="border rounded-md overflow-hidden">
            <table className="w-full text-xs">
              <thead className="bg-muted">
                <tr>
                  <th className="text-left p-2 font-medium text-muted-foreground">#</th>
                  <th className="text-left p-2 font-medium text-muted-foreground">Method</th>
                  <th className="text-left p-2 font-medium text-muted-foreground">File</th>
                  <th className="text-right p-2 font-medium text-muted-foreground">Line</th>
                </tr>
              </thead>
              <tbody>
                {error.envelopeMethodsStack!.Backend.map((frame, index) => (
                  <tr key={index} className={cn("border-t border-border/50", index === 0 && "bg-primary/5")}>
                    <td className="p-2 font-mono text-muted-foreground">{index}</td>
                    <td className="p-2 font-mono">
                      <span className={cn(index === 0 && "text-primary font-semibold")}>{frame.Method || 'unknown'}</span>
                    </td>
                    <td className="p-2 font-mono text-muted-foreground truncate max-w-[200px]" title={frame.File}>{frame.File || 'unknown'}</td>
                    <td className="p-2 font-mono text-right">{frame.LineNumber || '?'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {hasDelegatedStack && (
        <div>
          <div className="flex items-center justify-between mb-2">
            <h4 className="text-sm font-medium flex items-center gap-2">
              <AlertTriangle className="h-4 w-4 text-orange-500" />
              <span className="text-orange-600 dark:text-orange-400">Delegated Service Error Stack</span>
            </h4>
            <Button variant="ghost" size="sm" onClick={() => copySection("Delegated error stack", error.envelopeErrors!.DelegatedServiceErrorStack!.join('\n'))}>
              <Copy className="h-3 w-3" />
            </Button>
          </div>
          <ScrollArea className="h-[200px] rounded-md border border-orange-500/30 bg-orange-500/5">
            <pre className="text-xs p-3 font-mono whitespace-pre-wrap break-all">
              {error.envelopeErrors!.DelegatedServiceErrorStack!.join('\n')}
            </pre>
          </ScrollArea>
        </div>
      )}

      {hasBackendTrace && (
        <div>
          <div className="flex items-center justify-between mb-2">
            <h4 className="text-sm font-medium text-muted-foreground flex items-center gap-2">
              <Server className="h-4 w-4" />
              Backend Trace ({error.envelopeErrors!.Backend!.length} lines)
            </h4>
            <Button variant="ghost" size="sm" onClick={() => copySection("Backend trace", error.envelopeErrors!.Backend!.join('\n'))}>
              <Copy className="h-3 w-3" />
            </Button>
          </div>
          <ScrollArea className="h-[150px] rounded-md border bg-muted">
            <pre className="text-xs p-3 font-mono whitespace-pre-wrap break-all">
              {error.envelopeErrors!.Backend!.join('\n')}
            </pre>
          </ScrollArea>
        </div>
      )}

      {!hasEndpoints && !hasMethodsStack && !hasDelegatedStack && !hasBackendTrace && (
        <div className="text-center py-8 text-muted-foreground">
          <Route className="h-8 w-8 mx-auto mb-2 opacity-50" />
          <p className="text-sm">No traversal data available</p>
        </div>
      )}
    </div>
  );
}
