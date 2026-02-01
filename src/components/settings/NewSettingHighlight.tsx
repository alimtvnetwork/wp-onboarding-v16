import { Badge } from "@/components/ui/badge";
import { Sparkles } from "lucide-react";

interface NewSettingHighlightProps {
  addedInVersion?: string;
  currentVersion?: string;
  children: React.ReactNode;
  className?: string;
}

/**
 * Wraps a setting and shows a "New" badge if it was added in the current version
 */
export function NewSettingHighlight({
  addedInVersion,
  currentVersion,
  children,
  className = "",
}: NewSettingHighlightProps) {
  const isNew = addedInVersion && currentVersion && addedInVersion === currentVersion;

  return (
    <div className={`relative ${className}`}>
      {isNew && (
        <Badge 
          variant="secondary" 
          className="absolute -top-2 -right-2 text-xs px-1.5 py-0.5 flex items-center gap-1"
        >
          <Sparkles className="h-3 w-3" />
          New in v{currentVersion}
        </Badge>
      )}
      {children}
    </div>
  );
}
