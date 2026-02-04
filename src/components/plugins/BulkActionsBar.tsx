import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { 
  X, 
  Eye, 
  EyeOff, 
  RefreshCw, 
  Trash2, 
  GitPullRequest,
  CheckSquare
} from "lucide-react";

interface BulkActionsBarProps {
  selectedCount: number;
  totalCount: number;
  onSelectAll: () => void;
  onClearSelection: () => void;
  onEnableWatch: () => void;
  onDisableWatch: () => void;
  onSyncAll: () => void;
  onGitPullAll: () => void;
  onDeleteAll: () => void;
  isProcessing: boolean;
}

export function BulkActionsBar({
  selectedCount,
  totalCount,
  onSelectAll,
  onClearSelection,
  onEnableWatch,
  onDisableWatch,
  onSyncAll,
  onGitPullAll,
  onDeleteAll,
  isProcessing,
}: BulkActionsBarProps) {
  if (selectedCount === 0) return null;

  return (
    <div className="sticky top-0 z-10 flex items-center justify-between gap-4 p-3 mb-4 rounded-lg border bg-accent/50 backdrop-blur-sm">
      <div className="flex items-center gap-3">
        <Badge variant="secondary" className="text-sm">
          {selectedCount} of {totalCount} selected
        </Badge>
        <Button
          variant="ghost"
          size="sm"
          onClick={onSelectAll}
          className="h-7 text-xs"
        >
          <CheckSquare className="h-3 w-3 mr-1" />
          Select All
        </Button>
        <Button
          variant="ghost"
          size="sm"
          onClick={onClearSelection}
          className="h-7 text-xs"
        >
          <X className="h-3 w-3 mr-1" />
          Clear
        </Button>
      </div>

      <div className="flex items-center gap-2">
        <Button
          variant="outline"
          size="sm"
          onClick={onEnableWatch}
          disabled={isProcessing}
          className="h-8"
        >
          <Eye className="h-4 w-4 mr-1" />
          Enable Watch
        </Button>
        <Button
          variant="outline"
          size="sm"
          onClick={onDisableWatch}
          disabled={isProcessing}
          className="h-8"
        >
          <EyeOff className="h-4 w-4 mr-1" />
          Disable Watch
        </Button>
        <Button
          variant="outline"
          size="sm"
          onClick={onSyncAll}
          disabled={isProcessing}
          className="h-8"
        >
          <RefreshCw className="h-4 w-4 mr-1" />
          Sync All
        </Button>
        <Button
          variant="outline"
          size="sm"
          onClick={onGitPullAll}
          disabled={isProcessing}
          className="h-8"
        >
          <GitPullRequest className="h-4 w-4 mr-1" />
          Git Pull
        </Button>
        <Button
          variant="outline"
          size="sm"
          onClick={onDeleteAll}
          disabled={isProcessing}
          className="h-8 text-destructive hover:text-destructive hover:bg-destructive/10"
        >
          <Trash2 className="h-4 w-4 mr-1" />
          Delete
        </Button>
      </div>
    </div>
  );
}
