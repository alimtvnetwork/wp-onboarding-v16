/**
 * React Execution Logger
 * 
 * Tracks function calls, component renders, and creates call chains for debugging.
 * Can be enabled/disabled via debugMode setting.
 */

import { create } from 'zustand';

// Single execution log entry
export interface ExecutionLogEntry {
  id: string;
  timestamp: string;
  type: 'function' | 'component' | 'effect' | 'callback' | 'handler' | 'api';
  name: string;
  file?: string;
  line?: number;
  args?: string;
  result?: string;
  error?: string;
  duration?: number;
  parentId?: string;
  depth: number;
}

// Call chain represents a sequence of related function calls
export interface CallChain {
  id: string;
  startTime: string;
  endTime?: string;
  trigger: string;
  entries: ExecutionLogEntry[];
  error?: string;
}

interface ExecutionLoggerState {
  enabled: boolean;
  entries: ExecutionLogEntry[];
  chains: CallChain[];
  currentChainId: string | null;
  currentDepth: number;
  maxEntries: number;
  maxChains: number;
  
  // Actions
  setEnabled: (enabled: boolean) => void;
  startChain: (trigger: string) => string;
  endChain: (chainId: string, error?: string) => void;
  log: (entry: Omit<ExecutionLogEntry, 'id' | 'timestamp' | 'depth' | 'parentId'>) => string;
  getChain: (chainId: string) => CallChain | undefined;
  getRecentEntries: (limit?: number) => ExecutionLogEntry[];
  getChainForError: () => { chain: CallChain | null; entries: ExecutionLogEntry[] };
  clear: () => void;
  
  // Formatted outputs
  getEntriesAsString: () => string;
  getChainAsString: (chainId: string) => string;
}

// Generate unique ID
function generateId(): string {
  return `${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
}

// Format entry for display
function formatEntry(entry: ExecutionLogEntry): string {
  const indent = '  '.repeat(entry.depth);
  const typeIcon = {
    function: '→',
    component: '◆',
    effect: '◇',
    callback: '↩',
    handler: '⚡',
    api: '⬡',
  }[entry.type] || '•';
  
  let line = `${indent}${typeIcon} ${entry.name}`;
  
  if (entry.args) {
    line += `(${entry.args})`;
  }
  
  if (entry.duration !== undefined) {
    line += ` [${entry.duration}ms]`;
  }
  
  if (entry.error) {
    line += ` ✗ ${entry.error}`;
  } else if (entry.result) {
    line += ` → ${entry.result}`;
  }
  
  return line;
}

// Store for execution logging
export const useExecutionLoggerStore = create<ExecutionLoggerState>((set, get) => ({
  enabled: false,
  entries: [],
  chains: [],
  currentChainId: null,
  currentDepth: 0,
  maxEntries: 500,
  maxChains: 20,

  setEnabled: (enabled) => set({ enabled }),

  startChain: (trigger) => {
    const state = get();
    if (!state.enabled) return '';

    const chainId = generateId();
    const chain: CallChain = {
      id: chainId,
      startTime: new Date().toISOString(),
      trigger,
      entries: [],
    };

    set((state) => ({
      chains: [...state.chains, chain].slice(-state.maxChains),
      currentChainId: chainId,
      currentDepth: 0,
    }));

    return chainId;
  },

  endChain: (chainId, error) => {
    set((state) => ({
      chains: state.chains.map((c) =>
        c.id === chainId
          ? { ...c, endTime: new Date().toISOString(), error }
          : c
      ),
      currentChainId: state.currentChainId === chainId ? null : state.currentChainId,
      currentDepth: 0,
    }));
  },

  log: (entry) => {
    const state = get();
    if (!state.enabled) return '';

    const entryId = generateId();
    const fullEntry: ExecutionLogEntry = {
      ...entry,
      id: entryId,
      timestamp: new Date().toISOString(),
      depth: state.currentDepth,
      parentId: state.currentChainId || undefined,
    };

    set((state) => {
      // Add to global entries
      const newEntries = [...state.entries, fullEntry].slice(-state.maxEntries);

      // Add to current chain if exists
      const newChains = state.currentChainId
        ? state.chains.map((c) =>
            c.id === state.currentChainId
              ? { ...c, entries: [...c.entries, fullEntry] }
              : c
          )
        : state.chains;

      return {
        entries: newEntries,
        chains: newChains,
      };
    });

    return entryId;
  },

  getChain: (chainId) => {
    return get().chains.find((c) => c.id === chainId);
  },

  getRecentEntries: (limit = 50) => {
    const entries = get().entries;
    return entries.slice(-limit);
  },

  getChainForError: () => {
    const state = get();
    
    // Get the most recent chain or current chain
    const chain = state.currentChainId
      ? state.chains.find((c) => c.id === state.currentChainId)
      : state.chains[state.chains.length - 1];

    // Get recent entries regardless of chain
    const recentEntries = state.entries.slice(-30);

    return {
      chain: chain || null,
      entries: recentEntries,
    };
  },

  clear: () => set({ entries: [], chains: [], currentChainId: null, currentDepth: 0 }),

  getEntriesAsString: () => {
    const entries = get().entries.slice(-50);
    if (entries.length === 0) return '(no execution logs captured)';

    return entries.map((e) => {
      const time = new Date(e.timestamp).toLocaleTimeString();
      return `[${time}] ${formatEntry(e)}`;
    }).join('\n');
  },

  getChainAsString: (chainId) => {
    const chain = get().chains.find((c) => c.id === chainId);
    if (!chain) return '(chain not found)';

    const lines = [
      `Chain: ${chain.trigger}`,
      `Started: ${chain.startTime}`,
      chain.endTime ? `Ended: ${chain.endTime}` : '(in progress)',
      chain.error ? `Error: ${chain.error}` : '',
      '---',
      ...chain.entries.map(formatEntry),
    ];

    return lines.filter(Boolean).join('\n');
  },
}));

// Increment/decrement depth for nested calls
export function pushDepth(): void {
  const state = useExecutionLoggerStore.getState();
  if (state.enabled) {
    useExecutionLoggerStore.setState({ currentDepth: state.currentDepth + 1 });
  }
}

export function popDepth(): void {
  const state = useExecutionLoggerStore.getState();
  if (state.enabled && state.currentDepth > 0) {
    useExecutionLoggerStore.setState({ currentDepth: state.currentDepth - 1 });
  }
}

/**
 * Create a logged wrapper for a function
 * Usage: const myLoggedFn = loggedFunction('myFunction', originalFn);
 */
export function loggedFunction<T extends (...args: unknown[]) => unknown>(
  name: string,
  fn: T,
  type: ExecutionLogEntry['type'] = 'function'
): T {
  return ((...args: unknown[]) => {
    const state = useExecutionLoggerStore.getState();
    if (!state.enabled) {
      return fn(...args);
    }

    const startTime = performance.now();
    pushDepth();

    try {
      const result = fn(...args);
      
      // Handle promises
      if (result instanceof Promise) {
        return result
          .then((value) => {
            const duration = Math.round(performance.now() - startTime);
            state.log({
              type,
              name,
              args: args.length > 0 ? summarizeArgs(args) : undefined,
              result: summarizeResult(value),
              duration,
            });
            popDepth();
            return value;
          })
          .catch((error) => {
            const duration = Math.round(performance.now() - startTime);
            state.log({
              type,
              name,
              args: args.length > 0 ? summarizeArgs(args) : undefined,
              error: error?.message || String(error),
              duration,
            });
            popDepth();
            throw error;
          });
      }

      const duration = Math.round(performance.now() - startTime);
      state.log({
        type,
        name,
        args: args.length > 0 ? summarizeArgs(args) : undefined,
        result: summarizeResult(result),
        duration,
      });
      popDepth();
      return result;
    } catch (error) {
      const duration = Math.round(performance.now() - startTime);
      state.log({
        type,
        name,
        args: args.length > 0 ? summarizeArgs(args) : undefined,
        error: error instanceof Error ? error.message : String(error),
        duration,
      });
      popDepth();
      throw error;
    }
  }) as T;
}

// Summarize function arguments for logging
function summarizeArgs(args: unknown[]): string {
  return args
    .map((arg) => {
      if (arg === null) return 'null';
      if (arg === undefined) return 'undefined';
      if (typeof arg === 'string') return `"${arg.slice(0, 20)}${arg.length > 20 ? '...' : ''}"`;
      if (typeof arg === 'number' || typeof arg === 'boolean') return String(arg);
      if (Array.isArray(arg)) return `[${arg.length} items]`;
      if (typeof arg === 'object') return `{...}`;
      if (typeof arg === 'function') return 'fn()';
      return String(arg).slice(0, 20);
    })
    .join(', ');
}

// Summarize function result for logging
function summarizeResult(result: unknown): string {
  if (result === null) return 'null';
  if (result === undefined) return 'void';
  if (typeof result === 'string') return `"${result.slice(0, 30)}${result.length > 30 ? '...' : ''}"`;
  if (typeof result === 'number' || typeof result === 'boolean') return String(result);
  if (Array.isArray(result)) return `[${result.length} items]`;
  if (typeof result === 'object') return `{...}`;
  return String(result).slice(0, 30);
}

/**
 * Log a component render
 */
export function logComponent(componentName: string, props?: Record<string, unknown>): void {
  const state = useExecutionLoggerStore.getState();
  if (!state.enabled) return;

  state.log({
    type: 'component',
    name: componentName,
    args: props ? Object.keys(props).slice(0, 5).join(', ') : undefined,
  });
}

/**
 * Log an effect execution
 */
export function logEffect(effectName: string, deps?: unknown[]): void {
  const state = useExecutionLoggerStore.getState();
  if (!state.enabled) return;

  state.log({
    type: 'effect',
    name: effectName,
    args: deps ? `deps: [${deps.length}]` : undefined,
  });
}

/**
 * Log an event handler
 */
export function logHandler(handlerName: string, eventType?: string): void {
  const state = useExecutionLoggerStore.getState();
  if (!state.enabled) return;

  state.log({
    type: 'handler',
    name: handlerName,
    args: eventType,
  });
}

/**
 * Log an API call
 */
export function logApiCall(method: string, endpoint: string): string {
  const state = useExecutionLoggerStore.getState();
  if (!state.enabled) return '';

  return state.log({
    type: 'api',
    name: `${method} ${endpoint}`,
  });
}

/**
 * Get execution logs for error modal
 */
export function getExecutionLogsForError(): {
  enabled: boolean;
  entries: ExecutionLogEntry[];
  chain: CallChain | null;
  formatted: string;
} {
  const state = useExecutionLoggerStore.getState();
  const { chain, entries } = state.getChainForError();

  return {
    enabled: state.enabled,
    entries,
    chain,
    formatted: state.getEntriesAsString(),
  };
}
