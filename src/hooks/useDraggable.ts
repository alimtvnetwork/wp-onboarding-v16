import { useState, useCallback, useRef, type MouseEvent } from "react";

interface DragState {
  x: number;
  y: number;
}

/** Minimum pixels of the modal that must remain visible on each edge. */
const EDGE_MARGIN = 80;

/**
 * Clamp offset so at least EDGE_MARGIN px of the dragged element stays visible.
 * Uses the element's bounding rect (before transform) to compute limits.
 */
function clampOffset(rawX: number, rawY: number, el: HTMLElement | null): DragState {
  if (!el) return { x: rawX, y: rawY };

  const rect = el.getBoundingClientRect();
  // Current transform is already baked into getBoundingClientRect,
  // so subtract it to get the "natural" position.
  const naturalLeft = rect.left - rawX;
  const naturalTop = rect.top - rawY;
  const naturalRight = naturalLeft + rect.width;
  const naturalBottom = naturalTop + rect.height;

  const vw = window.innerWidth;
  const vh = window.innerHeight;

  // Max offset right: viewport right edge minus EDGE_MARGIN from left side of element
  const maxX = vw - EDGE_MARGIN - naturalLeft;
  // Max offset left: EDGE_MARGIN from right side of element minus viewport left
  const minX = EDGE_MARGIN - naturalRight;
  // Max offset down: viewport bottom minus EDGE_MARGIN from top
  const maxY = vh - EDGE_MARGIN - naturalTop;
  // Max offset up: EDGE_MARGIN from bottom minus viewport top
  const minY = EDGE_MARGIN - naturalBottom;

  return {
    x: Math.max(minX, Math.min(maxX, rawX)),
    y: Math.max(minY, Math.min(maxY, rawY)),
  };
}

/**
 * Hook to make an element draggable by its header with boundary clamping.
 * At least 80px of the modal stays visible on every edge.
 * Returns style transform and mouse event handlers for the drag handle.
 */
export function useDraggable() {
  const [offset, setOffset] = useState<DragState>({ x: 0, y: 0 });
  const dragging = useRef(false);
  const startPos = useRef({ x: 0, y: 0 });
  const startOffset = useRef({ x: 0, y: 0 });
  const elementRef = useRef<HTMLElement | null>(null);

  const resetPosition = useCallback(() => setOffset({ x: 0, y: 0 }), []);

  const onMouseDown = useCallback((e: MouseEvent) => {
    if (e.button !== 0) return;
    const target = e.target as HTMLElement;
    if (target.closest("button, a, input, [role='button']")) return;

    // Find the DialogContent element (closest ancestor with data-error-modal)
    const modal = (e.currentTarget as HTMLElement).closest("[data-error-modal]") as HTMLElement | null;
    elementRef.current = modal;

    dragging.current = true;
    startPos.current = { x: e.clientX, y: e.clientY };
    startOffset.current = { x: offset.x, y: offset.y };

    const onMouseMove = (ev: globalThis.MouseEvent) => {
      if (!dragging.current) return;
      const rawX = startOffset.current.x + (ev.clientX - startPos.current.x);
      const rawY = startOffset.current.y + (ev.clientY - startPos.current.y);
      setOffset(clampOffset(rawX, rawY, elementRef.current));
    };

    const onMouseUp = () => {
      dragging.current = false;
      document.removeEventListener("mousemove", onMouseMove);
      document.removeEventListener("mouseup", onMouseUp);
    };

    document.addEventListener("mousemove", onMouseMove);
    document.addEventListener("mouseup", onMouseUp);
  }, [offset.x, offset.y]);

  const style = offset.x === 0 && offset.y === 0
    ? undefined
    : { transform: `translate(${offset.x}px, ${offset.y}px)` };

  return { style, onMouseDown, resetPosition, isDragged: offset.x !== 0 || offset.y !== 0 };
}
