import { cn } from "@/lib/utils";

interface JsonHighlighterProps {
  json: unknown;
  className?: string;
}

/**
 * Syntax-highlighted JSON viewer with color-coded values
 */
export function JsonHighlighter({ json, className }: JsonHighlighterProps) {
  const formatted = typeof json === "string" 
    ? json 
    : JSON.stringify(json, null, 2);
  
  const highlighted = formatJsonWithHighlighting(formatted);
  
  return (
    <pre 
      className={cn(
        "text-xs font-mono whitespace-pre-wrap break-words",
        className
      )}
      dangerouslySetInnerHTML={{ __html: highlighted }}
    />
  );
}

function formatJsonWithHighlighting(json: string): string {
  // Escape HTML first
  const escaped = json
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;");
  
  // Apply syntax highlighting
  return escaped
    // Keys (in quotes before colon)
    .replace(/"([^"]+)"(?=\s*:)/g, '<span class="text-blue-500 dark:text-blue-400">"$1"</span>')
    // String values (in quotes after colon)
    .replace(/:(\s*)"([^"]*)"/g, ':$1<span class="text-emerald-600 dark:text-emerald-400">"$2"</span>')
    // Numbers
    .replace(/:\s*(-?\d+\.?\d*)/g, ': <span class="text-amber-600 dark:text-amber-400">$1</span>')
    // Booleans
    .replace(/:\s*(true|false)/g, ': <span class="text-purple-600 dark:text-purple-400">$1</span>')
    // Null
    .replace(/:\s*(null)/g, ': <span class="text-muted-foreground italic">$1</span>');
}
