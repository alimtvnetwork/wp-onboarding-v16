import { useEffect, useCallback } from 'react';
import { create } from 'zustand';

// Single click/interaction event
export interface ClickEvent {
  id: string;
  timestamp: string;
  element: string;        // e.g., "Button", "Link", "Input"
  text?: string;          // Button text or link text
  path: string;           // CSS selector path
  action: string;         // "click", "submit", "change", etc.
  targetId?: string;      // Element ID if present
  targetClass?: string;   // Main class if present
  componentName?: string; // Data attribute for component tracking
  route?: string;         // Current route when click happened
}

interface ClickTrackerState {
  clickPath: ClickEvent[];
  maxEvents: number;
  addClick: (event: Omit<ClickEvent, 'id' | 'timestamp'>) => void;
  getClickPath: () => ClickEvent[];
  getClickPathString: () => string;
  clear: () => void;
}

// Store click path in Zustand for real-time access
export const useClickTrackerStore = create<ClickTrackerState>((set, get) => ({
  clickPath: [],
  maxEvents: 20, // Keep last 20 interactions

  addClick: (event) => {
    const newEvent: ClickEvent = {
      ...event,
      id: `click-${Date.now()}-${Math.random().toString(36).substr(2, 5)}`,
      timestamp: new Date().toISOString(),
    };

    set((state) => ({
      clickPath: [...state.clickPath, newEvent].slice(-state.maxEvents),
    }));
  },

  getClickPath: () => get().clickPath,

  getClickPathString: () => {
    const path = get().clickPath;
    if (path.length === 0) return '';

    return path
      .map((e, i) => {
        const parts = [];
        if (e.componentName) parts.push(e.componentName);
        else if (e.element) parts.push(e.element);
        if (e.text) parts.push(`"${e.text.slice(0, 30)}${e.text.length > 30 ? '...' : ''}"`);
        if (e.action !== 'click') parts.push(`(${e.action})`);
        return `${i + 1}. ${parts.join(' ')}`;
      })
      .join('\n');
  },

  clear: () => set({ clickPath: [] }),
}));

// Get element description for logging
function getElementDescription(element: HTMLElement): string {
  const tagName = element.tagName.toLowerCase();
  
  // Map common elements to readable names
  const elementMap: Record<string, string> = {
    button: 'Button',
    a: 'Link',
    input: 'Input',
    select: 'Select',
    textarea: 'Textarea',
    form: 'Form',
    label: 'Label',
    img: 'Image',
    svg: 'Icon',
    div: 'Container',
    span: 'Span',
    li: 'ListItem',
    tr: 'TableRow',
    td: 'TableCell',
  };

  return elementMap[tagName] || tagName;
}

// Get text content from element (button text, link text, etc.)
function getElementText(element: HTMLElement): string | undefined {
  // For inputs, get placeholder or value
  if (element instanceof HTMLInputElement) {
    return element.placeholder || element.value?.slice(0, 50) || undefined;
  }
  
  // For buttons and links, get text content
  const text = element.textContent?.trim();
  if (text && text.length > 0 && text.length < 100) {
    return text;
  }
  
  // Check for aria-label
  const ariaLabel = element.getAttribute('aria-label');
  if (ariaLabel) return ariaLabel;
  
  // Check for title
  const title = element.getAttribute('title');
  if (title) return title;
  
  return undefined;
}

// Get CSS selector path for element
function getCssPath(element: HTMLElement): string {
  const path: string[] = [];
  let current: HTMLElement | null = element;
  
  while (current && current !== document.body && path.length < 5) {
    let selector = current.tagName.toLowerCase();
    
    if (current.id) {
      selector += `#${current.id}`;
    } else if (current.className && typeof current.className === 'string') {
      const mainClass = current.className.split(' ')[0];
      if (mainClass && !mainClass.startsWith('_')) {
        selector += `.${mainClass}`;
      }
    }
    
    path.unshift(selector);
    current = current.parentElement;
  }
  
  return path.join(' > ');
}

// Find the most relevant clickable ancestor
function findClickableAncestor(element: HTMLElement): HTMLElement {
  let current: HTMLElement | null = element;
  
  while (current) {
    const tagName = current.tagName.toLowerCase();
    
    // These are the clickable elements we want to track
    if (['button', 'a', 'input', 'select', 'textarea', 'label'].includes(tagName)) {
      return current;
    }
    
    // Check for role="button" or other interactive roles
    const role = current.getAttribute('role');
    if (role && ['button', 'link', 'menuitem', 'tab', 'checkbox', 'radio'].includes(role)) {
      return current;
    }
    
    // Check for data-click-track attribute (explicit tracking)
    if (current.hasAttribute('data-click-track')) {
      return current;
    }
    
    // Check for onClick handler indicator
    if (current.hasAttribute('data-radix-collection-item')) {
      return current;
    }
    
    current = current.parentElement;
  }
  
  return element;
}

// Hook to enable click tracking globally
export function useClickTracker() {
  const addClick = useClickTrackerStore((state) => state.addClick);

  const handleClick = useCallback((event: MouseEvent) => {
    const target = event.target as HTMLElement;
    if (!target) return;

    // Find the most relevant clickable element
    const clickable = findClickableAncestor(target);
    
    // Get component name from data attribute if present
    const componentName = 
      clickable.getAttribute('data-component') ||
      clickable.getAttribute('data-click-track') ||
      clickable.closest('[data-component]')?.getAttribute('data-component') ||
      undefined;

    addClick({
      element: getElementDescription(clickable),
      text: getElementText(clickable),
      path: getCssPath(clickable),
      action: 'click',
      targetId: clickable.id || undefined,
      targetClass: clickable.className?.split(' ')[0] || undefined,
      componentName,
      route: window.location.pathname,
    });
  }, [addClick]);

  const handleSubmit = useCallback((event: Event) => {
    const target = event.target as HTMLFormElement;
    if (!target || target.tagName.toLowerCase() !== 'form') return;

    addClick({
      element: 'Form',
      text: target.getAttribute('name') || target.id || undefined,
      path: getCssPath(target),
      action: 'submit',
      targetId: target.id || undefined,
      route: window.location.pathname,
    });
  }, [addClick]);

  const handleChange = useCallback((event: Event) => {
    const target = event.target as HTMLElement;
    if (!target) return;

    // Only track select and input changes
    const tagName = target.tagName.toLowerCase();
    if (!['select', 'input'].includes(tagName)) return;

    // Skip tracking password and sensitive inputs
    const input = target as HTMLInputElement;
    if (input.type === 'password') return;

    addClick({
      element: getElementDescription(target),
      text: input.placeholder || input.name || undefined,
      path: getCssPath(target),
      action: 'change',
      targetId: target.id || undefined,
      route: window.location.pathname,
    });
  }, [addClick]);

  useEffect(() => {
    // Add global event listeners
    document.addEventListener('click', handleClick, { capture: true });
    document.addEventListener('submit', handleSubmit, { capture: true });
    document.addEventListener('change', handleChange, { capture: true });

    return () => {
      document.removeEventListener('click', handleClick, { capture: true });
      document.removeEventListener('submit', handleSubmit, { capture: true });
      document.removeEventListener('change', handleChange, { capture: true });
    };
  }, [handleClick, handleSubmit, handleChange]);

  return useClickTrackerStore();
}

// Export a function to get click path for error capture (doesn't need hook)
export function getClickPathForError(): { clickPath: ClickEvent[]; clickPathString: string } {
  const state = useClickTrackerStore.getState();
  return {
    clickPath: state.getClickPath(),
    clickPathString: state.getClickPathString(),
  };
}
