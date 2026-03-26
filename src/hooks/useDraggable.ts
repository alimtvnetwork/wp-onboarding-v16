import { useState, useCallback, useRef, useEffect, type MouseEvent, type TouchEvent } from "react";

interface Position {
  x: number;
  y: number;
}

const EDGE_MARGIN = 80;

function clamp(val: number, min: number, max: number) {
  return Math.max(min, Math.min(max, val));
}

function applyMove(clientX: number, clientY: number, d: DragState, setOffset: (p: Position) => void) {
  const rawX = d.startOffset.x + (clientX - d.startMouse.x);
  const rawY = d.startOffset.y + (clientY - d.startMouse.y);

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
}

interface DragState {
  active: boolean;
  startMouse: Position;
  startOffset: Position;
  el: HTMLElement | null;
}

/**
 * Lightweight drag hook for repositioning a modal via its header.
 * Supports both mouse and touch. Transform-based with edge clamping.
 */
export function useDraggable() {
  const [offset, setOffset] = useState<Position>({ x: 0, y: 0 });
  const dragRef = useRef<DragState>({
    active: false, startMouse: { x: 0, y: 0 }, startOffset: { x: 0, y: 0 }, el: null,
  });

  const resetPosition = useCallback(() => setOffset({ x: 0, y: 0 }), []);

  useEffect(() => {
    const onMouseMove = (e: globalThis.MouseEvent) => {
      if (!dragRef.current.active) return;
      applyMove(e.clientX, e.clientY, dragRef.current, setOffset);
    };

    const onTouchMove = (e: globalThis.TouchEvent) => {
      if (!dragRef.current.active || !e.touches[0]) return;
      e.preventDefault();
      applyMove(e.touches[0].clientX, e.touches[0].clientY, dragRef.current, setOffset);
    };

    const onEnd = () => { dragRef.current.active = false; };

    document.addEventListener("mousemove", onMouseMove);
    document.addEventListener("mouseup", onEnd);
    document.addEventListener("touchmove", onTouchMove, { passive: false });
    document.addEventListener("touchend", onEnd);
    document.addEventListener("touchcancel", onEnd);

    return () => {
      document.removeEventListener("mousemove", onMouseMove);
      document.removeEventListener("mouseup", onEnd);
      document.removeEventListener("touchmove", onTouchMove);
      document.removeEventListener("touchend", onEnd);
      document.removeEventListener("touchcancel", onEnd);
    };
  }, []);

  const startDrag = useCallback((clientX: number, clientY: number, target: HTMLElement) => {
    if (target.closest("button, a, input, [role='button']")) return;
    dragRef.current = {
      active: true,
      startMouse: { x: clientX, y: clientY },
      startOffset: { x: offset.x, y: offset.y },
      el: target.closest("[data-error-modal]") as HTMLElement | null,
    };
  }, [offset.x, offset.y]);

  const onMouseDown = useCallback((e: MouseEvent) => {
    if (e.button !== 0) return;
    startDrag(e.clientX, e.clientY, e.target as HTMLElement);
  }, [startDrag]);

  const onTouchStart = useCallback((e: TouchEvent) => {
    const touch = e.touches[0];
    if (!touch) return;
    startDrag(touch.clientX, touch.clientY, e.target as HTMLElement);
  }, [startDrag]);

  const isDragged = offset.x !== 0 || offset.y !== 0;
  const style = isDragged ? { transform: `translate(${offset.x}px, ${offset.y}px)` } : undefined;

  return { style, onMouseDown, onTouchStart, resetPosition, isDragged };
}
