import { Badge } from "@/components/ui/badge";
import { PREDEFINED_CATEGORIES, CategoryOption } from "@/hooks/useCategories";
import { cn } from "@/lib/utils";

interface CategoryBadgeProps {
  category: string | null | undefined;
  size?: "sm" | "default";
  className?: string;
}

export function CategoryBadge({ category, size = "default", className }: CategoryBadgeProps) {
  if (!category) return null;

  const predefined = PREDEFINED_CATEGORIES.find(c => c.value === category);
  
  const colorClass = predefined 
    ? {
        production: "bg-primary/10 text-primary border-primary/30",
        staging: "bg-warning/10 text-warning border-warning/30",
        development: "bg-muted text-muted-foreground border-border",
      }[predefined.value as keyof typeof PREDEFINED_CATEGORIES[number]["value"]] || ""
    : "bg-secondary text-secondary-foreground border-border";

  return (
    <Badge
      variant="outline"
      className={cn(
        colorClass,
        size === "sm" && "text-[10px] px-1.5 py-0",
        className
      )}
    >
      {predefined?.label || category}
    </Badge>
  );
}
