import { useState, useMemo } from "react";
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetDescription } from "@/components/ui/sheet";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { Checkbox } from "@/components/ui/checkbox";
import { ScrollArea } from "@/components/ui/scroll-area";
import { 
  AlertCircle, 
  AlertTriangle, 
  Info, 
  Search, 
  Copy, 
  Trash2, 
  RefreshCw,
  X,
  CheckSquare,
  Square
} from "lucide-react";
import { useErrorHistory, recordToCapturedError } from "@/hooks/useErrorHistory";
import { useErrorStore } from "@/stores/errorStore";
import { ErrorHistoryRecord } from "@/lib/api";
import { toast } from "sonner";
import { format } from "date-fns";

interface ErrorHistoryDrawerProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

const levelIcons = {
  error: AlertCircle,
  warn: AlertTriangle,
  info: Info,
};

const levelColors = {
  error: "text-red-500",
  warn: "text-yellow-500",
  info: "text-blue-500",
};

export function ErrorHistoryDrawer({ open, onOpenChange }: ErrorHistoryDrawerProps) {
  const { errors, total, isLoading, refetch, deleteError, clearErrors, exportErrors, isExporting } = useErrorHistory();
  const { openErrorModal } = useErrorStore();
  
  const [search, setSearch] = useState("");
  const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set());
  
  // Filter errors by search
  const filteredErrors = useMemo(() => {
    if (!search.trim()) return errors;
    const lower = search.toLowerCase();
    return errors.filter(e => 
      e.message.toLowerCase().includes(lower) ||
      e.code.toLowerCase().includes(lower) ||
      e.endpoint?.toLowerCase().includes(lower)
    );
  }, [errors, search]);
  
  // Toggle selection
  const toggleSelect = (id: number) => {
    setSelectedIds(prev => {
      const next = new Set(prev);
      if (next.has(id)) {
        next.delete(id);
      } else {
        next.add(id);
      }
      return next;
    });
  };
  
  // Select all visible
  const selectAll = () => {
    setSelectedIds(new Set(filteredErrors.map(e => e.id)));
  };
  
  // Clear selection
  const clearSelection = () => {
    setSelectedIds(new Set());
  };
  
  // Copy selected errors
  const handleCopySelected = async () => {
    if (selectedIds.size === 0) {
      toast.error("No errors selected");
      return;
    }
    
    try {
      const response = await exportErrors(Array.from(selectedIds));
      const result = response?.data;
      if (result?.report) {
        await navigator.clipboard.writeText(result.report);
        toast.success(`Copied ${result.count} error(s) to clipboard`);
      } else {
        toast.error("No report data returned");
      }
    } catch (err) {
      toast.error("Failed to export errors");
    }
  };
  
  // Open error in modal
  const handleViewError = (record: ErrorHistoryRecord) => {
    const captured = recordToCapturedError(record);
    openErrorModal(captured);
    onOpenChange(false);
  };
  
  // Delete selected
  const handleDeleteSelected = () => {
    selectedIds.forEach(id => deleteError(id));
    setSelectedIds(new Set());
    toast.success(`Deleted ${selectedIds.size} error(s)`);
  };
  
  // Clear all
  const handleClearAll = () => {
    if (confirm("Are you sure you want to clear all error history?")) {
      clearErrors();
      setSelectedIds(new Set());
      toast.success("Error history cleared");
    }
  };
  
  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className="w-full sm:max-w-lg">
        <SheetHeader>
          <SheetTitle className="flex items-center gap-2">
            <AlertCircle className="h-5 w-5 text-destructive" />
            Error History
            <Badge variant="secondary" className="ml-2">{total}</Badge>
          </SheetTitle>
          <SheetDescription>
            View and manage captured errors and notifications
          </SheetDescription>
        </SheetHeader>
        
        {/* Search and Actions */}
        <div className="mt-4 space-y-3">
          <div className="relative">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
            <Input
              placeholder="Search errors..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="pl-9"
            />
          </div>
          
          {/* Bulk actions */}
          <div className="flex items-center gap-2 flex-wrap">
            <Button
              variant="outline"
              size="sm"
              onClick={selectedIds.size === filteredErrors.length ? clearSelection : selectAll}
            >
              {selectedIds.size === filteredErrors.length ? (
                <>
                  <Square className="h-4 w-4 mr-1" />
                  Deselect All
                </>
              ) : (
                <>
                  <CheckSquare className="h-4 w-4 mr-1" />
                  Select All ({filteredErrors.length})
                </>
              )}
            </Button>
            
            {selectedIds.size > 0 && (
              <>
                <Button
                  variant="outline"
                  size="sm"
                  onClick={handleCopySelected}
                  disabled={isExporting}
                >
                  <Copy className="h-4 w-4 mr-1" />
                  Copy ({selectedIds.size})
                </Button>
                <Button
                  variant="outline"
                  size="sm"
                  onClick={handleDeleteSelected}
                  className="text-destructive hover:text-destructive"
                >
                  <Trash2 className="h-4 w-4 mr-1" />
                  Delete
                </Button>
              </>
            )}
            
            <div className="flex-1" />
            
            <Button variant="ghost" size="sm" onClick={() => refetch()}>
              <RefreshCw className={`h-4 w-4 ${isLoading ? "animate-spin" : ""}`} />
            </Button>
            
            <Button
              variant="ghost"
              size="sm"
              onClick={handleClearAll}
              className="text-destructive hover:text-destructive"
            >
              <X className="h-4 w-4" />
            </Button>
          </div>
        </div>
        
        {/* Error List */}
        <ScrollArea className="mt-4 h-[calc(100vh-280px)]">
          {isLoading ? (
            <div className="flex items-center justify-center py-8 text-muted-foreground">
              <RefreshCw className="h-5 w-5 animate-spin mr-2" />
              Loading...
            </div>
          ) : filteredErrors.length === 0 ? (
            <div className="flex flex-col items-center justify-center py-12 text-muted-foreground">
              <AlertCircle className="h-12 w-12 mb-3 opacity-50" />
              <p>No errors in history</p>
            </div>
          ) : (
            <div className="space-y-2 pr-4">
              {filteredErrors.map((error) => {
                const Icon = levelIcons[error.level as keyof typeof levelIcons] || AlertCircle;
                const colorClass = levelColors[error.level as keyof typeof levelColors] || "text-red-500";
                const isSelected = selectedIds.has(error.id);
                
                return (
                  <div
                    key={error.id}
                    className={`
                      p-3 rounded-lg border cursor-pointer transition-colors
                      ${isSelected ? "bg-accent border-primary" : "bg-card hover:bg-accent/50"}
                    `}
                    onClick={() => handleViewError(error)}
                  >
                    <div className="flex items-start gap-3">
                      <Checkbox
                        checked={isSelected}
                        onClick={(e) => {
                          e.stopPropagation();
                          toggleSelect(error.id);
                        }}
                      />
                      
                      <Icon className={`h-5 w-5 mt-0.5 flex-shrink-0 ${colorClass}`} />
                      
                      <div className="flex-1 min-w-0">
                        <div className="flex items-center gap-2">
                          <Badge variant="outline" className="text-xs font-mono">
                            {error.code}
                          </Badge>
                          {error.endpoint && (
                            <span className="text-xs text-muted-foreground truncate">
                              {error.method} {error.endpoint}
                            </span>
                          )}
                        </div>
                        
                        <p className="text-sm mt-1 line-clamp-2">{error.message}</p>
                        
                        <p className="text-xs text-muted-foreground mt-1">
                          {format(new Date(error.createdAt), "MMM d, HH:mm:ss")}
                        </p>
                      </div>
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </ScrollArea>
      </SheetContent>
    </Sheet>
  );
}
