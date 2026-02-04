import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Copy, Check, Bug } from "lucide-react";
import { toast } from "sonner";
import { useVersionInfo } from "@/hooks/useWhatsNew";
import { getDiagnostics, formatDiagnosticsForCopy } from "@/lib/diagnostics";

interface CopyDiagnosticsButtonProps {
  variant?: "default" | "ghost" | "outline";
  size?: "default" | "sm" | "lg" | "icon";
  showLabel?: boolean;
  className?: string;
}

/**
 * A small button that copies API base, WS URL, and app version
 * for faster support and debugging.
 */
export function CopyDiagnosticsButton({
  variant = "ghost",
  size = "sm",
  showLabel = true,
  className,
}: CopyDiagnosticsButtonProps) {
  const [copied, setCopied] = useState(false);
  const { data: versionInfo } = useVersionInfo();

  const handleCopy = async () => {
    const diagnostics = getDiagnostics(versionInfo);
    const text = formatDiagnosticsForCopy(diagnostics);

    try {
      await navigator.clipboard.writeText(text);
      setCopied(true);
      toast.success("Diagnostics copied to clipboard");
      setTimeout(() => setCopied(false), 2000);
    } catch {
      toast.error("Failed to copy diagnostics");
    }
  };

  return (
    <Button
      variant={variant}
      size={size}
      onClick={handleCopy}
      className={className}
    >
      {copied ? (
        <Check className="h-4 w-4" />
      ) : (
        <Bug className="h-4 w-4" />
      )}
      {showLabel && (
        <span className="ml-1">{copied ? "Copied!" : "Copy Diagnostics"}</span>
      )}
    </Button>
  );
}

export default CopyDiagnosticsButton;
