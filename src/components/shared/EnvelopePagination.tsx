import { Button } from "@/components/ui/button";
import { ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight } from "lucide-react";
import { EnvelopeAttributes, EnvelopeNavigation } from "@/lib/api";

export interface PaginationMeta {
  attributes?: EnvelopeAttributes;
  navigation?: EnvelopeNavigation;
}

interface EnvelopePaginationProps {
  meta?: PaginationMeta | null;
  onPageChange: (page: number) => void;
  className?: string;
}

/**
 * Reusable pagination component that reads envelope Navigation & Attributes.
 * Renders nothing when pagination data is absent or there's only one page.
 */
export function EnvelopePagination({ meta, onPageChange, className }: EnvelopePaginationProps) {
  if (!meta?.attributes) return null;

  const { TotalRecords, PerPage, TotalPages, CurrentPage } = meta.attributes;
  const totalPages = TotalPages ?? 1;
  const currentPage = CurrentPage ?? 1;

  if (totalPages <= 1) return null;

  const totalRecords = TotalRecords ?? 0;
  const perPage = PerPage ?? 0;
  const nav = meta.navigation;

  // Build visible page numbers from Navigation.Pages or generate a sliding window
  const pages: number[] = nav?.Pages?.length
    ? nav.Pages
    : buildPageWindow(currentPage, totalPages);

  const startRecord = (currentPage - 1) * perPage + 1;
  const endRecord = Math.min(currentPage * perPage, totalRecords);

  return (
    <div className={`flex items-center justify-between text-sm text-muted-foreground ${className ?? ""}`}>
      <span>
        Showing {startRecord}–{endRecord} of {totalRecords}
      </span>
      <div className="flex items-center gap-1">
        {/* First page */}
        <Button
          variant="ghost"
          size="icon"
          className="h-8 w-8"
          disabled={currentPage <= 1}
          onClick={() => onPageChange(1)}
          aria-label="First page"
        >
          <ChevronsLeft className="h-4 w-4" />
        </Button>

        {/* Previous */}
        <Button
          variant="ghost"
          size="icon"
          className="h-8 w-8"
          disabled={!nav?.PrevPage}
          onClick={() => nav?.PrevPage && onPageChange(nav.PrevPage)}
          aria-label="Previous page"
        >
          <ChevronLeft className="h-4 w-4" />
        </Button>

        {/* Page numbers */}
        {pages[0] > 1 && (
          <span className="px-1 text-muted-foreground/60">…</span>
        )}
        {pages.map((p) => (
          <Button
            key={p}
            variant={p === currentPage ? "default" : "ghost"}
            size="icon"
            className="h-8 w-8 text-xs"
            onClick={() => onPageChange(p)}
          >
            {p}
          </Button>
        ))}
        {pages[pages.length - 1] < totalPages && (
          <span className="px-1 text-muted-foreground/60">…</span>
        )}

        {/* Next */}
        <Button
          variant="ghost"
          size="icon"
          className="h-8 w-8"
          disabled={!nav?.NextPage}
          onClick={() => nav?.NextPage && onPageChange(nav.NextPage)}
          aria-label="Next page"
        >
          <ChevronRight className="h-4 w-4" />
        </Button>

        {/* Last page */}
        <Button
          variant="ghost"
          size="icon"
          className="h-8 w-8"
          disabled={currentPage >= totalPages}
          onClick={() => onPageChange(totalPages)}
          aria-label="Last page"
        >
          <ChevronsRight className="h-4 w-4" />
        </Button>
      </div>
    </div>
  );
}

/** Generate a sliding window of page numbers centered on current page */
function buildPageWindow(current: number, total: number, windowSize = 5): number[] {
  const half = Math.floor(windowSize / 2);
  let start = Math.max(1, current - half);
  let end = Math.min(total, start + windowSize - 1);
  // Adjust start if we're near the end
  if (end - start + 1 < windowSize) {
    start = Math.max(1, end - windowSize + 1);
  }
  const pages: number[] = [];
  for (let i = start; i <= end; i++) pages.push(i);
  return pages;
}
