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
  captureError: (error: ApiError, meta?: { endpoint?: string; method?: string; requestBody?: unknown; responseStatus?: number }) => CapturedError;
  openErrorModal: (error: CapturedError) => void;
  closeErrorModal: () => void;
  clearRecentErrors: () => void;
}

export const useErrorStore = create<ErrorStore>((set, get) => ({
  selectedError: null,
  isModalOpen: false,
  recentErrors: [],
  
  captureError: (error, meta) => {
    const captured: CapturedError = {
      id: `${Date.now()}-${Math.random().toString(36).substr(2, 9)}`,
      code: error.code || 'E9999',
      level: 'error',
      message: error.message,
      details: error.details,
      context: {
        ...error.context,
        ...(meta?.requestBody ? { requestData: meta.requestBody } : {}),
      },
      file: error.file,
      line: error.line,
      function: error.function,
      stackTrace: error.stackTrace,
      createdAt: error.timestamp || new Date().toISOString(),
      endpoint: meta?.endpoint,
      method: meta?.method,
      requestBody: meta?.requestBody,
      responseStatus: meta?.responseStatus,
    };
    
    set((state) => ({
      recentErrors: [captured, ...state.recentErrors].slice(0, 50), // Keep last 50 errors
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
