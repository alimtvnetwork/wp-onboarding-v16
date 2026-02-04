import { useState } from "react";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover";
import { useCategories, CategoryOption } from "@/hooks/useCategories";
import { Plus, X } from "lucide-react";
import { cn } from "@/lib/utils";
import { toast } from "sonner";

interface CategorySelectProps {
  value: string | null | undefined;
  onValueChange: (value: string | null) => void;
  placeholder?: string;
  allowCustom?: boolean;
  className?: string;
}

export function CategorySelect({
  value,
  onValueChange,
  placeholder = "Select category...",
  allowCustom = true,
  className,
}: CategorySelectProps) {
  // Radix Select does NOT allow empty-string item values; using one can crash the UI.
  const NONE_VALUE = "__none__";
  const { categories, addCategory, removeCategory } = useCategories();
  const [newCategoryName, setNewCategoryName] = useState("");
  const [showAddPopover, setShowAddPopover] = useState(false);

  const handleAddCategory = () => {
    if (!newCategoryName.trim()) return;
    
    const success = addCategory(newCategoryName.trim());
    if (success) {
      toast.success(`Category "${newCategoryName}" added`);
      const value = newCategoryName.toLowerCase().replace(/\s+/g, "-");
      onValueChange(value);
      setNewCategoryName("");
      setShowAddPopover(false);
    } else {
      toast.error("Category already exists");
    }
  };

  const handleRemoveCustomCategory = (categoryValue: string, e: React.MouseEvent) => {
    e.stopPropagation();
    removeCategory(categoryValue);
    if (value === categoryValue) {
      onValueChange(null);
    }
    toast.success("Category removed");
  };

  const getCategoryColor = (cat: CategoryOption) => {
    if (cat.value === "production") return "bg-primary";
    if (cat.value === "staging") return "bg-warning";
    if (cat.value === "development") return "bg-muted-foreground";
    return "bg-secondary-foreground";
  };

  return (
    <div className={cn("flex gap-2", className)}>
      <Select
        value={value ? value : NONE_VALUE}
        onValueChange={(v) => onValueChange(v === NONE_VALUE ? null : v)}
      >
        <SelectTrigger className="flex-1">
          <SelectValue placeholder={placeholder} />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value={NONE_VALUE}>
            <span className="text-muted-foreground">No category</span>
          </SelectItem>
          {categories.map((cat) => (
            <SelectItem key={cat.value} value={cat.value}>
              <div className="flex items-center gap-2">
                <div className={cn("w-2 h-2 rounded-full", getCategoryColor(cat))} />
                {cat.label}
                {cat.isCustom && (
                  <Button
                    variant="ghost"
                    size="sm"
                    className="h-4 w-4 p-0 ml-auto hover:bg-destructive/10"
                    onClick={(e) => handleRemoveCustomCategory(cat.value, e)}
                  >
                    <X className="h-3 w-3" />
                  </Button>
                )}
              </div>
            </SelectItem>
          ))}
        </SelectContent>
      </Select>

      {allowCustom && (
        <Popover open={showAddPopover} onOpenChange={setShowAddPopover}>
          <PopoverTrigger asChild>
            <Button variant="outline" size="icon" title="Add custom category">
              <Plus className="h-4 w-4" />
            </Button>
          </PopoverTrigger>
          <PopoverContent className="w-64" align="end">
            <div className="space-y-3">
              <Label htmlFor="new-category">New Category</Label>
              <Input
                id="new-category"
                placeholder="Category name"
                value={newCategoryName}
                onChange={(e) => setNewCategoryName(e.target.value)}
                onKeyDown={(e) => {
                  if (e.key === "Enter") {
                    handleAddCategory();
                  }
                }}
              />
              <div className="flex justify-end gap-2">
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => setShowAddPopover(false)}
                >
                  Cancel
                </Button>
                <Button size="sm" onClick={handleAddCategory}>
                  Add
                </Button>
              </div>
            </div>
          </PopoverContent>
        </Popover>
      )}
    </div>
  );
}

