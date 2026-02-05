import { create } from 'zustand';
import { ApiError } from '@/lib/api';

/**
 * Parsed stack frame with file, line, column info
 */
export interface StackFrame {
  function: string;
  file: string;
  line: number;
  column?: number;
  isInternal: boolean; // true if from node_modules or browser internals
}

/**
 * Full parsed stack trace result
 */
export interface ParsedStackTrace {
  frames: StackFrame[];
  primaryFrame: StackFrame | null;
  invocationChain: string[];
  rawStack: string;
}

/**
 * Error context required for all captureException calls
 */
export interface ErrorContext {
  source: string;              // REQUIRED: "ComponentName.functionName"
  triggerComponent?: string;   // UI component (EditSiteDialog)
  triggerAction?: string;      // User action (save_clicked, button_click, form_submit)
  parentSource?: string;       // Caller function for chain building
  endpoint?: string;
  method?: string;
  requestBody?: unknown;
  context?: Record<string, unknown>;
}

/**
 * Backend log entry from operation execution
 */
export interface BackendLogEntry {
  timestamp: string;
  level: 'debug' | 'info' | 'warn' | 'error';
  message: string;
  step?: string;
  details?: Record<string, unknown>;
}

export interface CapturedError {
  id: string;
  code: string;
  level: 'error' | 'warn' | 'info';
  message: string;
  details?: string;
  context?: Record<string, unknown>;
  file?: string;
  line?: number;
  function?: string;
  stackTrace?: string;
  createdAt: string;
  // Additional fields for API errors
  endpoint?: string;
  method?: string;
  requestBody?: unknown;
  responseStatus?: number;
  // Enhanced error reporting fields
  invocationChain?: string[];
  parsedFrames?: StackFrame[];
  triggerComponent?: string;
  triggerAction?: string;
  // Backend execution logs
  backendLogs?: BackendLogEntry[];
  backendStackTrace?: string;
  siteUrl?: string;
  // Session-based logging
  sessionId?: string;
  sessionType?: string;
}

interface ErrorStore {
  // Current error to show in modal
  selectedError: CapturedError | null;
  isModalOpen: boolean;
  
  // Recent errors list (for history)
  recentErrors: CapturedError[];
  
  // Actions
  captureError: (error: ApiError, meta?: { 
    endpoint?: string; 
    method?: string; 
    requestBody?: unknown; 
    responseStatus?: number; 
    context?: Record<string, unknown>;
    backendLogs?: BackendLogEntry[];
    backendStackTrace?: string;
    siteUrl?: string;
    sessionId?: string;
    sessionType?: string;
  }) => CapturedError;
  captureException: (
    error: unknown,
    context?: ErrorContext | {
      endpoint?: string;
      method?: string;
      requestBody?: unknown;
      source?: string;
      context?: Record<string, unknown>;
      backendLogs?: BackendLogEntry[];
      backendStackTrace?: string;
      siteUrl?: string;
      sessionId?: string;
      sessionType?: string;
    }
  ) => CapturedError;
  openErrorModal: (error: CapturedError) => void;
  closeErrorModal: () => void;
  clearRecentErrors: () => void;
}

/**
 * Capture a client-side stack trace from any error or create one from current call site
 */
function captureStackTrace(error?: unknown): string {
  if (error instanceof Error && error.stack) {
    return error.stack;
  }
  // Create a stack trace from current position
  const stackError = new Error();
  if (stackError.stack) {
    // Remove the first 2-3 lines (Error + captureStackTrace + captureError/captureException)
    const lines = stackError.stack.split('\n');
    return lines.slice(3).join('\n');
  }
  return '';
}

/**
 * Parse ALL stack frames from a stack trace string
 * Handles both development (full paths) and production (minified) formats
 */
export function parseFullStackTrace(stack: string): ParsedStackTrace {
  const result: ParsedStackTrace = {
    frames: [],
    primaryFrame: null,
    invocationChain: [],
    rawStack: stack,
  };
  
  if (!stack) return result;
  
  const lines = stack.split('\n');
  
  for (const line of lines) {
    // Skip empty lines and error message lines
    if (!line.trim() || !line.includes('at ')) continue;
    
    // Pattern 1: "at functionName (file:line:col)"
    // Pattern 2: "at file:line:col" (anonymous function)
    // Pattern 3: "at async functionName (file:line:col)"
    // Pattern 4: Webpack/Vite: "at functionName (http://localhost:5173/src/file.tsx:123:45)"
    
    let funcName = 'anonymous';
    let filePath = '';
    let lineNum = 0;
    let colNum: number | undefined;
    
    // Try to match with function name: "at funcName (path:line:col)"
    const withFuncMatch = line.match(/at\s+(?:async\s+)?(.+?)\s+\((.+?):(\d+):(\d+)\)/);
    if (withFuncMatch) {
      funcName = withFuncMatch[1].trim();
      filePath = withFuncMatch[2];
      lineNum = parseInt(withFuncMatch[3], 10);
      colNum = parseInt(withFuncMatch[4], 10);
    } else {
      // Try anonymous: "at path:line:col"
      const anonMatch = line.match(/at\s+(.+?):(\d+):(\d+)/);
      if (anonMatch) {
        filePath = anonMatch[1].trim();
        lineNum = parseInt(anonMatch[2], 10);
        colNum = parseInt(anonMatch[3], 10);
      }
    }
    
    if (!filePath) continue;
    
    // Determine if this is an internal frame (node_modules, browser internals)
    const isInternal = 
      filePath.includes('node_modules') ||
      filePath.includes('chrome-extension://') ||
      filePath.startsWith('<anonymous>') ||
      filePath.includes('@tanstack') ||
      filePath.includes('react-dom') ||
      filePath.includes('react.') ||
      filePath.includes('scheduler.') ||
      funcName.startsWith('Object.') ||
      funcName === 'Module' ||
      funcName === '<anonymous>';
    
    // Clean up file path for display
    let cleanFile = filePath;
    // Extract just the filename from URLs like http://localhost:5173/src/components/File.tsx
    const urlMatch = filePath.match(/\/src\/(.+)$/);
    if (urlMatch) {
      cleanFile = 'src/' + urlMatch[1];
    } else {
      // Handle file:// URLs or plain paths
      const fileMatch = filePath.match(/([^/\\]+\.(tsx?|jsx?|mjs|cjs))$/i);
      if (fileMatch) {
        cleanFile = fileMatch[1];
      }
    }
    
    result.frames.push({
      function: funcName,
      file: cleanFile,
      line: lineNum,
      column: colNum,
      isInternal,
    });
  }
  
  // Find the first non-internal frame as the primary frame
  result.primaryFrame = result.frames.find(f => !f.isInternal) || result.frames[0] || null;
  
  // Build invocation chain from non-internal frames (app code only)
  result.invocationChain = result.frames
    .filter(f => !f.isInternal && f.function !== 'anonymous')
    .slice(0, 8) // Limit to 8 levels
    .map(f => `${f.function} (${f.file}:${f.line})`);
  
  return result;
}

/**
 * Legacy parse function for backward compatibility
 */
function parseStackTrace(stack: string): { file?: string; line?: number; function?: string } {
  const parsed = parseFullStackTrace(stack);
  if (parsed.primaryFrame) {
    return {
      file: parsed.primaryFrame.file,
      line: parsed.primaryFrame.line,
      function: parsed.primaryFrame.function,
    };
  }
  return {};
}

/**
 * Build invocation chain from error context
 */
function buildInvocationChain(
  parsedChain: string[],
  source?: string,
  parentSource?: string
): string[] {
  const chain: string[] = [];
  
  // Add explicit source context first
  if (source) {
    chain.push(source);
  }
  if (parentSource && parentSource !== source) {
    chain.push(parentSource);
  }
  
  // Add parsed stack frames
  for (const frame of parsedChain) {
    // Avoid duplicates
    if (!chain.some(c => c.includes(frame.split(' ')[0]))) {
      chain.push(frame);
    }
  }
  
  return chain;
}

export const useErrorStore = create<ErrorStore>((set, get) => ({
  selectedError: null,
  isModalOpen: false,
  recentErrors: [],
  
  captureError: (error, meta) => {
    // Always capture client-side stack trace for better debugging
    const clientStack = captureStackTrace();
    const combinedStack = error.stackTrace 
      ? `${error.stackTrace}\n\n--- Client Stack ---\n${clientStack}`
      : clientStack;
    
    const parsed = parseFullStackTrace(error.stackTrace || clientStack);
    const stackInfo = parseStackTrace(error.stackTrace || clientStack);
    
    // Extract source from context if available
    const source = typeof meta?.context?.source === 'string' ? meta.context.source : undefined;
    const triggerComponent = typeof meta?.context?.triggerComponent === 'string' ? meta.context.triggerComponent : undefined;
    const triggerAction = typeof meta?.context?.triggerAction === 'string' ? meta.context.triggerAction : undefined;
    
    const captured: CapturedError = {
      id: `${Date.now()}-${Math.random().toString(36).substr(2, 9)}`,
      code: error.code || 'E9999',
      level: 'error',
      message: error.message,
      details: error.details,
      context: {
        ...error.context,
        ...(meta?.context || {}),
        ...(meta?.requestBody ? { requestData: meta.requestBody } : {}),
      },
      file: error.file || stackInfo.file,
      line: error.line || stackInfo.line,
      function: error.function || stackInfo.function,
      stackTrace: combinedStack || undefined,
      createdAt: error.timestamp || new Date().toISOString(),
      endpoint: meta?.endpoint,
      method: meta?.method,
      requestBody: meta?.requestBody,
      responseStatus: meta?.responseStatus,
      // Enhanced fields
      invocationChain: buildInvocationChain(parsed.invocationChain, source),
      parsedFrames: parsed.frames.filter(f => !f.isInternal),
      triggerComponent,
      triggerAction,
      // Backend execution data
      backendLogs: meta?.backendLogs,
      backendStackTrace: meta?.backendStackTrace,
      siteUrl: meta?.siteUrl,
      // Session-based logging
      sessionId: meta?.sessionId,
      sessionType: meta?.sessionType,
    };
    
    set((state) => ({
      recentErrors: [captured, ...state.recentErrors].slice(0, 50),
    }));
    
    return captured;
  },
  
  /**
   * Capture any JavaScript exception with full stack trace
   * MUST include source in context for proper error reporting
   */
  captureException: (error, context) => {
    const stack = captureStackTrace(error);
    const parsed = parseFullStackTrace(stack);
    const stackInfo = parseStackTrace(stack);
    
    const message = error instanceof Error ? error.message : String(error);
    const details = error instanceof Error && 'cause' in error && error.cause 
      ? String(error.cause) 
      : undefined;
    
    // Extract enhanced context fields
    const source = context?.source;
    const triggerComponent = 'triggerComponent' in (context || {}) 
      ? (context as ErrorContext).triggerComponent 
      : undefined;
    const triggerAction = 'triggerAction' in (context || {})
      ? (context as ErrorContext).triggerAction
      : undefined;
    const parentSource = 'parentSource' in (context || {})
      ? (context as ErrorContext).parentSource
      : undefined;
    
    const mergedContext: Record<string, unknown> | undefined = (() => {
      const base: Record<string, unknown> = {
        ...(context?.context || {}),
        ...(source ? { source } : {}),
        ...(triggerComponent ? { triggerComponent } : {}),
        ...(triggerAction ? { triggerAction } : {}),
        ...(context?.requestBody ? { requestData: context.requestBody } : {}),
      };
      return Object.keys(base).length ? base : undefined;
    })();

    const captured: CapturedError = {
      id: `${Date.now()}-${Math.random().toString(36).substr(2, 9)}`,
      code: 'E9003',
      level: 'error',
      message,
      details,
      context: mergedContext,
      file: stackInfo.file,
      line: stackInfo.line,
      function: stackInfo.function,
      stackTrace: stack || undefined,
      createdAt: new Date().toISOString(),
      endpoint: context?.endpoint,
      method: context?.method,
      requestBody: context?.requestBody,
      // Enhanced fields
      invocationChain: buildInvocationChain(parsed.invocationChain, source, parentSource),
      parsedFrames: parsed.frames.filter(f => !f.isInternal),
      triggerComponent,
      triggerAction,
      // Backend execution data (from context if available)
      backendLogs: 'backendLogs' in (context || {}) ? (context as { backendLogs?: BackendLogEntry[] }).backendLogs : undefined,
      backendStackTrace: 'backendStackTrace' in (context || {}) ? (context as { backendStackTrace?: string }).backendStackTrace : undefined,
      siteUrl: 'siteUrl' in (context || {}) ? (context as { siteUrl?: string }).siteUrl : undefined,
      // Session-based logging
      sessionId: 'sessionId' in (context || {}) ? (context as { sessionId?: string }).sessionId : undefined,
      sessionType: 'sessionType' in (context || {}) ? (context as { sessionType?: string }).sessionType : undefined,
    };
    
    set((state) => ({
      recentErrors: [captured, ...state.recentErrors].slice(0, 50),
    }));
    
    return captured;
  },
  
  openErrorModal: (error) => {
    set({ selectedError: error, isModalOpen: true });
  },
  
  closeErrorModal: () => {
    set({ isModalOpen: false });
  },
  
  clearRecentErrors: () => {
    set({ recentErrors: [] });
  },
}));
