import { useState, useCallback, useRef, type MouseEvent } from "react";

interface DragState {
  x: number;
  y: number;
}

/**
 * Hook to make an element draggable by its header.
 * Returns style transform and mouse event handlers for the drag handle.
 */
export function useDraggable() {
  const [offset, setOffset] = useState<DragState>({ x: 0, y: 0 });
  const dragging = useRef(false);
  const startPos = useRef({ x: 0, y: 0 });
  const startOffset = useRef({ x: 0, y: 0 });

  const resetPosition = useCallback(() => setOffset({ x: 0, y: 0 }), []);

  const onMouseDown = useCallback((e: MouseEvent) => {
    // Only drag on left-click, ignore buttons/inputs inside the header
    if (e.button !== 0) return;
    const target = e.target as HTMLElement;
    if (target.closest("button, a, input, [role='button']")) return;

    dragging.current = true;
    startPos.current = { x: e.clientX, y: e.clientY };
    startOffset.current = { x: offset.x, y: offset.y };

    const onMouseMove = (ev: globalThis.MouseEvent) => {
      if (!dragging.current) return;
      setOffset({
        x: startOffset.current.x + (ev.clientX - startPos.current.x),
        y: startOffset.current.y + (ev.clientY - startPos.current.y),
      });
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
