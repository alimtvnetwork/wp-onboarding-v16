import { create } from 'zustand';
import { ApiError } from '@/lib/api';

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
}

interface ErrorStore {
  // Current error to show in modal
  selectedError: CapturedError | null;
  isModalOpen: boolean;
  
  // Recent errors list (for history)
  recentErrors: CapturedError[];
  
  // Actions
  captureError: (error: ApiError, meta?: { endpoint?: string; method?: string; requestBody?: unknown; responseStatus?: number; context?: Record<string, unknown> }) => CapturedError;
  captureException: (error: unknown, context?: { endpoint?: string; method?: string; requestBody?: unknown }) => CapturedError;
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
 * Parse stack trace to extract file, line, function info
 */
function parseStackTrace(stack: string): { file?: string; line?: number; function?: string } {
  if (!stack) return {};
  
  // Try to parse the first meaningful stack line
  const lines = stack.split('\n');
  for (const line of lines) {
    // Match patterns like: "at functionName (file.ts:123:45)" or "at file.ts:123:45"
    const match = line.match(/at\s+(?:(.+?)\s+\()?(.+?):(\d+):\d+\)?/);
    if (match) {
      return {
        function: match[1] || undefined,
        file: match[2],
        line: parseInt(match[3], 10),
      };
    }
  }
  return {};
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
    
    const stackInfo = parseStackTrace(error.stackTrace || clientStack);
    
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
    };
    
    set((state) => ({
      recentErrors: [captured, ...state.recentErrors].slice(0, 50),
    }));
    
    return captured;
  },
  
  /**
   * Capture any JavaScript exception with full stack trace
   */
  captureException: (error, context) => {
    const stack = captureStackTrace(error);
    const stackInfo = parseStackTrace(stack);
    
    const message = error instanceof Error ? error.message : String(error);
    const details = error instanceof Error && 'cause' in error && error.cause 
      ? String(error.cause) 
      : undefined;
    
    const captured: CapturedError = {
      id: `${Date.now()}-${Math.random().toString(36).substr(2, 9)}`,
      code: 'E9003',
      level: 'error',
      message,
      details,
      context: context?.requestBody ? { requestData: context.requestBody } : undefined,
      file: stackInfo.file,
      line: stackInfo.line,
      function: stackInfo.function,
      stackTrace: stack || undefined,
      createdAt: new Date().toISOString(),
      endpoint: context?.endpoint,
      method: context?.method,
      requestBody: context?.requestBody,
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
