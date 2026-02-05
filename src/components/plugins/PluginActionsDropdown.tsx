import { useState } from "react";
import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import {
  RefreshCw,
  GitBranch,
  Archive,
  MoreHorizontal,
  Loader2,
  FolderOpen,
  History,
} from "lucide-react";
import { Plugin } from "@/lib/api";

interface PluginActionsDropdownProps {
  plugin: Plugin;
  isScanning: boolean;
  isPulling: boolean;
  onScan: () => void;
  onGitPull: () => void;
  onBackup: () => void;
  onOpenFolder?: () => void;
  onViewHistory?: () => void;
}

/**
 * Grouped dropdown for secondary plugin actions (Scan, Pull, Backup).
 * Keeps the main action bar clean and focused on primary actions.
 */
export function PluginActionsDropdown({
  plugin,
  isScanning,
  isPulling,
  onScan,
  onGitPull,
  onBackup,
  onOpenFolder,
  onViewHistory,
}: PluginActionsDropdownProps) {
  const [open, setOpen] = useState(false);
  
  const handleAction = (action: () => void) => {
    setOpen(false);
    action();
  };
  
  return (
    <DropdownMenu open={open} onOpenChange={setOpen}>
      <DropdownMenuTrigger asChild>
        <Button
          variant="ghost"
          size="sm"
          className="h-8 w-8 p-0"
          title="More actions"
        >
          {isScanning || isPulling ? (
            <Loader2 className="h-4 w-4 animate-spin" />
          ) : (
            <MoreHorizontal className="h-4 w-4" />
          )}
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-48">
        <DropdownMenuItem 
          onClick={() => handleAction(onScan)}
          disabled={isScanning}
          className="gap-2"
        >
          {isScanning ? (
            <Loader2 className="h-4 w-4 animate-spin" />
          ) : (
            <RefreshCw className="h-4 w-4" />
          )}
          <span>Scan for Changes</span>
        </DropdownMenuItem>
        
        {plugin.gitEnabled && (
          <DropdownMenuItem 
            onClick={() => handleAction(onGitPull)}
            disabled={isPulling}
            className="gap-2"
          >
            {isPulling ? (
              <Loader2 className="h-4 w-4 animate-spin" />
            ) : (
              <GitBranch className="h-4 w-4" />
            )}
            <span>Git Pull</span>
          </DropdownMenuItem>
        )}
        
        <DropdownMenuItem 
          onClick={() => handleAction(onBackup)}
          className="gap-2"
        >
          <Archive className="h-4 w-4" />
          <span>Create Backup</span>
        </DropdownMenuItem>
        
        <DropdownMenuSeparator />
        
        {onOpenFolder && (
          <DropdownMenuItem 
            onClick={() => handleAction(onOpenFolder)}
            className="gap-2"
          >
            <FolderOpen className="h-4 w-4" />
            <span>Open Folder</span>
          </DropdownMenuItem>
        )}
        
        {onViewHistory && (
          <DropdownMenuItem 
            onClick={() => handleAction(onViewHistory)}
            className="gap-2"
          >
            <History className="h-4 w-4" />
            <span>View History</span>
          </DropdownMenuItem>
        )}
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
