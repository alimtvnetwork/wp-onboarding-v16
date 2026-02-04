import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { useCategories } from "@/hooks/useCategories";
import { cn } from "@/lib/utils";
import { X } from "lucide-react";

interface CategoryFilterProps {
  selectedCategories: string[];
  onCategoryToggle: (category: string) => void;
  onClearAll: () => void;
  className?: string;
}

export function CategoryFilter({
  selectedCategories,
  onCategoryToggle,
  onClearAll,
  className,
}: CategoryFilterProps) {
  const { categories } = useCategories();

  const getCategoryColor = (value: string, isSelected: boolean) => {
    if (!isSelected) return "bg-muted/50 text-muted-foreground hover:bg-muted";
    
    switch (value) {
      case "production":
        return "bg-primary text-primary-foreground";
      case "staging":
        return "bg-warning text-warning-foreground";
      case "development":
        return "bg-muted-foreground text-background";
      default:
        return "bg-secondary text-secondary-foreground";
    }
  };

  return (
    <div className={cn("flex flex-wrap items-center gap-2", className)}>
      <span className="text-sm text-muted-foreground">Filter:</span>
      
      {categories.map((cat) => {
        const isSelected = selectedCategories.includes(cat.value);
        return (
          <Badge
            key={cat.value}
            variant="outline"
            className={cn(
              "cursor-pointer transition-colors border-transparent",
              getCategoryColor(cat.value, isSelected)
            )}
            onClick={() => onCategoryToggle(cat.value)}
          >
            {cat.label}
          </Badge>
        );
      })}

      {selectedCategories.length > 0 && (
        <Button
          variant="ghost"
          size="sm"
          className="h-6 px-2 text-xs"
          onClick={onClearAll}
        >
          <X className="h-3 w-3 mr-1" />
          Clear
        </Button>
      )}
    </div>
  );
}
