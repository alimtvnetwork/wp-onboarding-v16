import { useState, useCallback, useRef, useEffect, type MouseEvent } from "react";

interface Position {
  x: number;
  y: number;
}

const EDGE_MARGIN = 80;

function clamp(val: number, min: number, max: number) {
  return Math.max(min, Math.min(max, val));
}

/**
 * Lightweight drag hook for repositioning a modal via its header.
 * Transform-based (no layout reflow), with edge clamping.
 */
export function useDraggable() {
  const [offset, setOffset] = useState<Position>({ x: 0, y: 0 });
  const dragRef = useRef<{
    active: boolean;
    startMouse: Position;
    startOffset: Position;
    el: HTMLElement | null;
  }>({ active: false, startMouse: { x: 0, y: 0 }, startOffset: { x: 0, y: 0 }, el: null });

  const resetPosition = useCallback(() => setOffset({ x: 0, y: 0 }), []);

  useEffect(() => {
    const onMove = (e: globalThis.MouseEvent) => {
      const d = dragRef.current;
      if (!d.active) return;

      const rawX = d.startOffset.x + (e.clientX - d.startMouse.x);
      const rawY = d.startOffset.y + (e.clientY - d.startMouse.y);

      if (!d.el) {
        setOffset({ x: rawX, y: rawY });
        return;
      }

      const rect = d.el.getBoundingClientRect();
      const natL = rect.left - rawX;
      const natT = rect.top - rawY;
      const vw = window.innerWidth;
      const vh = window.innerHeight;

      setOffset({
        x: clamp(rawX, EDGE_MARGIN - natL - rect.width, vw - EDGE_MARGIN - natL),
        y: clamp(rawY, EDGE_MARGIN - natT - rect.height, vh - EDGE_MARGIN - natT),
      });
    };

    const onUp = () => { dragRef.current.active = false; };

    document.addEventListener("mousemove", onMove);
    document.addEventListener("mouseup", onUp);
    return () => {
      document.removeEventListener("mousemove", onMove);
      document.removeEventListener("mouseup", onUp);
    };
  }, []);

  const onMouseDown = useCallback((e: MouseEvent) => {
    if (e.button !== 0) return;
    if ((e.target as HTMLElement).closest("button, a, input, [role='button']")) return;

    dragRef.current = {
      active: true,
      startMouse: { x: e.clientX, y: e.clientY },
      startOffset: { x: offset.x, y: offset.y },
      el: (e.currentTarget as HTMLElement).closest("[data-error-modal]") as HTMLElement | null,
    };
  }, [offset.x, offset.y]);

  const isDragged = offset.x !== 0 || offset.y !== 0;
  const style = isDragged ? { transform: `translate(${offset.x}px, ${offset.y}px)` } : undefined;

  return { style, onMouseDown, resetPosition, isDragged };
}
