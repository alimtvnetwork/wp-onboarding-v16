import { CapturedError, StackFrame } from '@/stores/errorStore';
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import {
  Copy, AlertCircle, FileCode2, Lightbulb, ChevronRight, Layers,
  Activity, MousePointerClick
} from "lucide-react";
import { cn } from "@/lib/utils";
import { JsonHighlighter } from "@/components/shared/JsonHighlighter";
import type { SectionCommonProps } from "./ErrorModalTypes";

interface FrontendSectionProps extends SectionCommonProps {
  error: CapturedError;
  showRawStack: boolean;
  setShowRawStack: (v: boolean) => void;
  showInternalFrames: boolean;
  setShowInternalFrames: (v: boolean) => void;
  displayFrames: StackFrame[] | undefined;
  suggestedFixes: string[];
}

export function FrontendSection({
  error,
  showRawStack,
  setShowRawStack,
  showInternalFrames,
  setShowInternalFrames,
  displayFrames,
  suggestedFixes,
  copySection,
  formatTs,
}: FrontendSectionProps) {
  return (
    <Tabs defaultValue="overview" className="w-full">
      <div className="overflow-x-auto -mx-4 px-4 sm:mx-0 sm:px-0 pb-1">
        <TabsList className="mb-4 inline-flex h-auto gap-1 min-w-max sm:flex sm:flex-wrap">
          <TabsTrigger value="overview" className="gap-1 text-xs sm:text-sm px-2 sm:px-3">
            <AlertCircle className="h-3 w-3" />
            Overview
          </TabsTrigger>
          <TabsTrigger value="stack" className="gap-1 text-xs sm:text-sm px-2 sm:px-3">
            <FileCode2 className="h-3 w-3" />
            Stack
          </TabsTrigger>
          <TabsTrigger value="context" className="gap-1 text-xs sm:text-sm px-2 sm:px-3">
            <Layers className="h-3 w-3" />
            Context
          </TabsTrigger>
          <TabsTrigger value="fixes" className="gap-1 text-xs sm:text-sm px-2 sm:px-3">
            <Lightbulb className="h-3 w-3" />
            Fixes
          </TabsTrigger>
        </TabsList>
      </div>

      {/* Overview Tab */}
      <TabsContent value="overview" className="space-y-4 m-0">
        {(error.triggerComponent || error.triggerAction) && (
          <div className="flex items-center gap-2 flex-wrap">
            <Badge variant="outline" className="bg-primary/5 border-primary/20">
              <Layers className="h-3 w-3 mr-1" />
              {error.triggerComponent || "Unknown"}
              {error.triggerAction && (
                <>
                  <ChevronRight className="h-3 w-3 mx-1" />
                  {error.triggerAction}
                </>
              )}
            </Badge>
            {error.context?.source && (
              <Badge variant="secondary" className="font-mono text-xs">
                {String(error.context.source)}
              </Badge>
            )}
          </div>
        )}

        <div>
          <h4 className="text-sm font-medium text-muted-foreground mb-1">Message</h4>
          <p className="text-sm bg-muted p-3 rounded-md">{error.message}</p>
        </div>

        {error.details && (
          <div>
            <h4 className="text-sm font-medium text-muted-foreground mb-1">Details</h4>
            <p className="text-sm bg-muted p-3 rounded-md whitespace-pre-wrap">{error.details}</p>
          </div>
        )}

        {error.invocationChain && error.invocationChain.length > 0 && (
          <div>
            <h4 className="text-sm font-medium text-muted-foreground mb-2 flex items-center gap-2">
              <Layers className="h-4 w-4" />
              Call Chain
            </h4>
            <div className="bg-muted p-3 rounded-md">
              <div className="space-y-1">
                {error.invocationChain.map((call, index) => (
                  <div key={index} className="flex items-center gap-1 text-xs font-mono" style={{ marginLeft: `${index * 12}px` }}>
                    {index > 0 && <span className="text-muted-foreground">└─</span>}
                    <span className={cn(index === 0 ? "text-primary font-semibold" : "text-foreground")}>{call}</span>
                  </div>
                ))}
              </div>
            </div>
          </div>
        )}

        {error.uiClickPath && error.uiClickPath.length > 0 && (
          <div>
            <div className="flex items-center justify-between mb-2">
              <h4 className="text-sm font-medium text-muted-foreground flex items-center gap-2">
                <MousePointerClick className="h-4 w-4" />
                User Interaction Path ({error.uiClickPath.length} steps)
              </h4>
              <Button variant="ghost" size="sm" onClick={() => copySection("Click path", error.uiClickPathString || '')}>
                <Copy className="h-4 w-4" />
              </Button>
            </div>
            <div className="bg-muted p-3 rounded-md">
              <div className="space-y-1">
                {error.uiClickPath.slice(-10).map((click, index) => (
                  <div key={click.id} className="flex items-start gap-2 text-xs">
                    <span className="text-muted-foreground font-mono w-4 text-right shrink-0">{index + 1}.</span>
                    <div className="flex-1">
                      <span className={cn("font-medium", index === error.uiClickPath!.length - 1 && "text-primary")}>
                        {click.componentName || click.element}
                      </span>
                      {click.text && (
                        <span className="text-muted-foreground ml-1">
                          "{click.text.slice(0, 25)}{click.text.length > 25 ? '...' : ''}"
                        </span>
                      )}
                      {click.action !== 'click' && (
                        <Badge variant="outline" className="ml-1 text-[10px] px-1 py-0">{click.action}</Badge>
                      )}
                      {click.route && (
                        <span className="text-muted-foreground ml-1 font-mono text-[10px]">@ {click.route}</span>
                      )}
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </div>
        )}

        {error.file && (
          <div className="flex items-center gap-2 text-sm">
            <FileCode2 className="h-4 w-4 text-muted-foreground" />
            <code className="bg-muted px-2 py-1 rounded text-xs">{error.file}:{error.line}</code>
            {error.function && (
              <span className="text-muted-foreground">
                → <code className="bg-muted px-1 rounded text-xs">{error.function}</code>
              </span>
            )}
          </div>
        )}
      </TabsContent>

      {/* Stack Trace Tab */}
      <TabsContent value="stack" className="space-y-4 m-0">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-2">
            <Button variant={showRawStack ? "outline" : "default"} size="sm" onClick={() => setShowRawStack(false)}>Parsed</Button>
            <Button variant={showRawStack ? "default" : "outline"} size="sm" onClick={() => setShowRawStack(true)}>Raw</Button>
          </div>
          {!showRawStack && (
            <label className="flex items-center gap-2 text-xs text-muted-foreground cursor-pointer">
              <input type="checkbox" checked={showInternalFrames} onChange={(e) => setShowInternalFrames(e.target.checked)} className="rounded" />
              Show internal frames
            </label>
          )}
        </div>

        {error.executionLogs && error.executionLogs.length > 0 && (
          <div>
            <div className="flex items-center justify-between mb-2">
              <h4 className="text-sm font-medium text-muted-foreground flex items-center gap-2">
                <Activity className="h-4 w-4 text-blue-500" />
                React Execution Chain ({error.executionLogs.length} calls)
              </h4>
              <Button variant="ghost" size="sm" onClick={() => copySection("React execution logs", error.executionLogsFormatted || "")}>
                <Copy className="h-4 w-4" />
              </Button>
            </div>
            <ScrollArea className="h-32 rounded-md border bg-blue-500/5">
              <pre className="text-xs p-3 font-mono whitespace-pre-wrap">{error.executionLogsFormatted || "(no logs captured)"}</pre>
            </ScrollArea>
          </div>
        )}

        {!error.executionLogs && error.executionLogsEnabled === false && (
          <div className="p-3 rounded-md bg-muted text-xs text-muted-foreground">
            <span className="font-medium">Tip:</span> Enable Debug Mode in settings to capture React execution chains.
          </div>
        )}

        {showRawStack ? (
          error.stackTrace ? (
            <>
              <div className="flex items-center justify-between mb-2">
                <h4 className="text-sm font-medium text-muted-foreground flex items-center gap-2">
                  <FileCode2 className="h-4 w-4" />
                  Raw Stack Trace
                </h4>
                <Button variant="ghost" size="sm" onClick={() => copySection("Stack trace", error.stackTrace!)}>
                  <Copy className="h-4 w-4" />
                </Button>
              </div>
              <pre className="text-xs bg-muted p-3 rounded-md overflow-x-auto whitespace-pre-wrap font-mono max-h-64">{error.stackTrace}</pre>
            </>
          ) : (
            <div className="text-center py-8 text-muted-foreground">
              <FileCode2 className="h-8 w-8 mx-auto mb-2 opacity-50" />
              <p className="text-sm">No stack trace available</p>
            </div>
          )
        ) : displayFrames && displayFrames.length > 0 ? (
          <div className="border rounded-md overflow-hidden">
            <table className="w-full text-xs">
              <thead className="bg-muted">
                <tr>
                  <th className="text-left p-2 font-medium text-muted-foreground">#</th>
                  <th className="text-left p-2 font-medium text-muted-foreground">Function</th>
                  <th className="text-left p-2 font-medium text-muted-foreground">File</th>
                  <th className="text-right p-2 font-medium text-muted-foreground">Line</th>
                </tr>
              </thead>
              <tbody>
                {displayFrames.map((frame, index) => (
                  <tr key={index} className={cn("border-t border-border/50", index === 0 && "bg-primary/5", frame.isInternal && "opacity-50")}>
                    <td className="p-2 font-mono text-muted-foreground">{index + 1}</td>
                    <td className="p-2 font-mono">
                      <span className={cn(index === 0 && "text-primary font-semibold")}>{frame.function}</span>
                    </td>
                    <td className="p-2 font-mono text-muted-foreground truncate max-w-[200px]">{frame.file}</td>
                    <td className="p-2 font-mono text-right">{frame.line}</td>
                  </tr>
                ))}
              </tbody>
            </table>
            <div className="flex justify-end p-2 bg-muted/50 border-t">
              <Button variant="ghost" size="sm" onClick={() => {
                const tableText = displayFrames.map((f, i) => `${i + 1}. ${f.function} (${f.file}:${f.line})`).join('\n');
                copySection("Stack frames", tableText);
              }}>
                <Copy className="h-4 w-4 mr-1" />
                Copy
              </Button>
            </div>
          </div>
        ) : (
          <div className="text-center py-8 text-muted-foreground">
            <FileCode2 className="h-8 w-8 mx-auto mb-2 opacity-50" />
            <p className="text-sm">No parsed stack frames available</p>
            <Button variant="link" size="sm" onClick={() => setShowRawStack(true)} className="mt-2">View raw stack trace</Button>
          </div>
        )}

        {error.file && (
          <div className="pt-3 border-t">
            <h4 className="text-sm font-medium text-muted-foreground mb-2">Error Location</h4>
            <div className="bg-muted p-3 rounded-md space-y-1">
              <p className="text-sm flex items-center gap-2">
                <span className="text-muted-foreground">File:</span>
                <code className="text-xs bg-background/60 px-1 py-0.5 rounded">{error.file}</code>
              </p>
              {error.line && (
                <p className="text-sm flex items-center gap-2">
                  <span className="text-muted-foreground">Line:</span>
                  <code className="text-xs bg-background/60 px-1 py-0.5 rounded">{error.line}</code>
                </p>
              )}
              {error.function && (
                <p className="text-sm flex items-center gap-2">
                  <span className="text-muted-foreground">Function:</span>
                  <code className="text-xs bg-background/60 px-1 py-0.5 rounded">{error.function}</code>
                </p>
              )}
            </div>
          </div>
        )}
      </TabsContent>

      {/* Context Tab */}
      <TabsContent value="context" className="space-y-4 m-0">
        {error.context && Object.keys(error.context).length > 0 ? (
          <div>
            <div className="flex items-center justify-between mb-1">
              <h4 className="text-sm font-medium text-muted-foreground">Full Error Context</h4>
              <Button variant="ghost" size="sm" onClick={() => copySection("Context", JSON.stringify(error.context, null, 2))}>
                <Copy className="h-4 w-4" />
              </Button>
            </div>
            <ScrollArea className="h-64 rounded-md border bg-muted">
              <div className="p-3">
                <JsonHighlighter json={error.context} />
              </div>
            </ScrollArea>
          </div>
        ) : (
          <div className="text-center py-8 text-muted-foreground">No additional context available</div>
        )}
      </TabsContent>

      {/* Fixes Tab */}
      <TabsContent value="fixes" className="m-0">
        <div className="space-y-3">
          <div className="flex items-center gap-2 text-sm text-muted-foreground">
            <Lightbulb className="h-4 w-4" />
            <span>Suggested fixes for error code <code className="bg-muted px-1 rounded">{error.code}</code></span>
          </div>
          <ul className="space-y-2">
            {suggestedFixes.map((fix, index) => (
              <li key={index} className="flex items-start gap-2">
                <span className="flex-shrink-0 w-5 h-5 rounded-full bg-primary/10 text-primary text-xs flex items-center justify-center">{index + 1}</span>
                <span className="text-sm">{fix}</span>
              </li>
            ))}
          </ul>
        </div>
      </TabsContent>
    </Tabs>
  );
}
