import { CapturedError } from '@/stores/errorStore';
import { Button } from "@/components/ui/button";
import {
  DropdownMenu, DropdownMenuContent, DropdownMenuItem,
  DropdownMenuSeparator, DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Copy, Download, FileCode2, FileDown, FileText, Server, Terminal, ChevronDown } from "lucide-react";
import { toast } from "sonner";
import { toClipboardText } from "@/lib/logText";
import { api } from "@/lib/api";
import { generateErrorReport, generateCompactReport } from "./errorReportGenerator";
import type { AppInfo } from "./ErrorModalTypes";

interface DownloadDropdownProps extends AppInfo {
  error: CapturedError;
}

export function DownloadDropdown({ error, appName, appVersion, gitCommit, buildTime }: DownloadDropdownProps) {
  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="outline">
          <Download className="h-4 w-4 mr-2" />
          Download
          <ChevronDown className="h-4 w-4 ml-1" />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="bg-popover">
        <DropdownMenuItem onClick={async () => {
          try {
            const report = generateErrorReport(error, { appName, appVersion, gitCommit, buildTime });
            const resp = await fetch("/api/v1/errors/bundle", {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify({ report }),
            });
            if (!resp.ok) throw new Error(`bundle download failed: ${resp.status}`);
            const blob = await resp.blob();
            const url = window.URL.createObjectURL(blob);
            const link = document.createElement("a");
            link.href = url;
            link.download = `error-bundle-${new Date().toISOString().slice(0, 10)}.zip`;
            link.click();
            window.URL.revokeObjectURL(url);
            toast.success("Downloading error bundle...");
          } catch (err: unknown) {
            console.error(err);
            toast.error("Failed to download error bundle");
          }
        }}>
          <FileDown className="h-4 w-4 mr-2" />
          Full Bundle (ZIP)
        </DropdownMenuItem>
        <DropdownMenuSeparator />
        <DropdownMenuItem onClick={async () => {
          try {
            const resp = await api.getBackendErrorLog();
            if (resp.success && resp.data) {
              const blob = new Blob([resp.data.content], { type: "text/plain" });
              const url = window.URL.createObjectURL(blob);
              const link = document.createElement("a");
              link.href = url;
              link.download = "error.log.txt";
              link.click();
              window.URL.revokeObjectURL(url);
              toast.success("Downloaded error.log.txt");
            } else {
              toast.error(resp.error?.message || "No error log found");
            }
          } catch {
            toast.error("Failed to download error log");
          }
        }}>
          <FileText className="h-4 w-4 mr-2" />
          error.log.txt
        </DropdownMenuItem>
        <DropdownMenuItem onClick={async () => {
          try {
            const resp = await api.getBackendFullLog();
            if (resp.success && resp.data) {
              const blob = new Blob([resp.data.content], { type: "text/plain" });
              const url = window.URL.createObjectURL(blob);
              const link = document.createElement("a");
              link.href = url;
              link.download = "log.txt";
              link.click();
              window.URL.revokeObjectURL(url);
              toast.success("Downloaded log.txt");
            } else {
              toast.error(resp.error?.message || "No full log found");
            }
          } catch {
            toast.error("Failed to download log file");
          }
        }}>
          <Terminal className="h-4 w-4 mr-2" />
          log.txt (Full)
        </DropdownMenuItem>
        <DropdownMenuSeparator />
        <DropdownMenuItem onClick={() => {
          const report = generateErrorReport(error, { appName, appVersion, gitCommit, buildTime });
          const blob = new Blob([report], { type: "text/markdown" });
          const url = window.URL.createObjectURL(blob);
          const link = document.createElement("a");
          link.href = url;
          link.download = `error-report-${new Date().toISOString().slice(0, 10)}.md`;
          link.click();
          window.URL.revokeObjectURL(url);
          toast.success("Downloaded report as Markdown");
        }}>
          <FileCode2 className="h-4 w-4 mr-2" />
          Report (.md)
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}

interface CopyDropdownProps extends AppInfo {
  error: CapturedError;
  copyFullError: () => void;
}

/**
 * Split Copy Button — main click copies compact report instantly,
 * chevron arrow opens dropdown with all copy options.
 */
export function CopyDropdown({ error, appName, appVersion, gitCommit, buildTime, copyFullError }: CopyDropdownProps) {
  const copyCompact = () => {
    const text = generateCompactReport(error, { appName, appVersion, gitCommit, buildTime });
    navigator.clipboard.writeText(toClipboardText(text));
    toast.success("Compact report copied to clipboard");
  };

  return (
    <div className="inline-flex rounded-md shadow-sm">
      {/* Main button — copies compact report instantly */}
      <Button
        onClick={copyCompact}
        className="rounded-r-none border-r-0"
      >
        <Copy className="h-4 w-4 mr-2" />
        Copy
      </Button>

      {/* Arrow button — opens dropdown with all copy options */}
      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button
            className="rounded-l-none px-2 border-l border-l-primary-foreground/20"
            aria-label="More copy options"
          >
            <ChevronDown className="h-4 w-4" />
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" className="bg-popover">
          <DropdownMenuItem onClick={copyCompact}>
            <Copy className="h-4 w-4 mr-2" />
            Copy Compact Report
          </DropdownMenuItem>
          <DropdownMenuItem onClick={copyFullError}>
            <FileText className="h-4 w-4 mr-2" />
            Copy Full Report
          </DropdownMenuItem>
          <DropdownMenuItem onClick={async () => {
            try {
              const resp = await api.getBackendErrorLog();
              if (resp.success && resp.data) {
                const report = generateErrorReport(error, { appName, appVersion, gitCommit, buildTime });
                const fullReport = `${report}\n\n---\n\n## Backend Error Log (error.log.txt)\n\n\`\`\`\n${resp.data.content}\n\`\`\`\n`;
                navigator.clipboard.writeText(toClipboardText(fullReport));
                toast.success("Copied report with backend logs");
              } else {
                copyFullError();
                toast.info("Backend logs not available, copied standard report");
              }
            } catch {
              copyFullError();
            }
          }}>
            <Server className="h-4 w-4 mr-2" />
            Copy with Backend Logs
          </DropdownMenuItem>
          <DropdownMenuSeparator />
          <DropdownMenuItem onClick={async () => {
            try {
              const resp = await api.getBackendErrorLog();
              if (resp.success && resp.data) {
                navigator.clipboard.writeText(toClipboardText(resp.data.content));
                toast.success("Copied error.log.txt to clipboard");
              } else {
                toast.error(resp.error?.message || "No error log available");
              }
            } catch {
              toast.error("Failed to copy error log");
            }
          }}>
            <Terminal className="h-4 w-4 mr-2" />
            Copy error.log.txt
          </DropdownMenuItem>
          <DropdownMenuItem onClick={async () => {
            try {
              const resp = await api.getBackendFullLog();
              if (resp.success && resp.data) {
                navigator.clipboard.writeText(toClipboardText(resp.data.content));
                toast.success("Copied log.txt to clipboard");
              } else {
                toast.error(resp.error?.message || "No full log available");
              }
            } catch {
              toast.error("Failed to copy full log");
            }
          }}>
            <FileText className="h-4 w-4 mr-2" />
            Copy log.txt
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
    </div>
  );
}
