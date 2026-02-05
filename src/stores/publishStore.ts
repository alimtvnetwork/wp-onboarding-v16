import { create } from 'zustand';
import { wsClient, WS_EVENTS } from '@/lib/ws';

/**
 * Log entry from backend publish operations
 */
export interface PublishLogEntry {
  timestamp: string;
  level: 'debug' | 'info' | 'warn' | 'error';
  step: string;
  message: string;
  details?: Record<string, unknown>;
}

/**
 * Stage status for publish pipeline
 */
export interface PublishStage {
  name: 'backup' | 'package' | 'upload' | 'activate' | 'cleanup';
  status: 'pending' | 'running' | 'success' | 'error' | 'skipped';
  message?: string;
}

/**
 * Individual publish operation tracked in the store
 */
export interface PublishOperation {
  id: string; // Unique operation ID (pluginId-siteId-timestamp)
  sessionId?: string; // Backend session ID for log retrieval
  pluginId: number;
  pluginName: string;
  siteId: number;
  siteName: string;
  siteUrl: string;
  status: 'pending' | 'running' | 'success' | 'error';
  progress: number; // 0-100
  stages: PublishStage[];
  logs: PublishLogEntry[];
  error?: string;
  startedAt: string;
  completedAt?: string;
  filesUpdated?: number;
}

/**
 * Quick publish request for a plugin to all mapped sites
 */
export interface QuickPublishRequest {
  pluginId: number;
  pluginName: string;
  mappings: Array<{
    siteId: number;
    siteName: string;
    siteUrl: string;
  }>;
}

interface PublishStore {
  // Active operations indexed by operation ID
  operations: Map<string, PublishOperation>;
  
  // Quick access: which plugins have active operations
  activePluginIds: Set<number>;
  
  // UI state
  expandedOperationId: string | null;
  showGlobalProgress: boolean;
  
  // Actions
  startOperation: (op: Omit<PublishOperation, 'id' | 'status' | 'progress' | 'stages' | 'logs' | 'startedAt'>) => string;
  updateOperation: (id: string, updates: Partial<PublishOperation>) => void;
  updateStage: (id: string, stageName: string, status: PublishStage['status'], message?: string) => void;
  addLog: (id: string, log: PublishLogEntry) => void;
  completeOperation: (id: string, success: boolean, error?: string, filesUpdated?: number) => void;
  removeOperation: (id: string) => void;
  clearCompletedOperations: () => void;
  
  // UI actions
  setExpandedOperation: (id: string | null) => void;
  toggleGlobalProgress: () => void;
  
  // Helpers
  getOperationsForPlugin: (pluginId: number) => PublishOperation[];
  hasActiveOperation: (pluginId: number, siteId?: number) => boolean;
  getActiveCount: () => number;
}

const DEFAULT_STAGES: PublishStage[] = [
  { name: 'backup', status: 'pending' },
  { name: 'package', status: 'pending' },
  { name: 'upload', status: 'pending' },
  { name: 'activate', status: 'pending' },
  { name: 'cleanup', status: 'pending' },
];

// Auto-cleanup completed operations after 30 minutes
const CLEANUP_DELAY_MS = 30 * 60 * 1000;

export const usePublishStore = create<PublishStore>((set, get) => ({
  operations: new Map(),
  activePluginIds: new Set(),
  expandedOperationId: null,
  showGlobalProgress: false,
  
  startOperation: (op) => {
    const id = `${op.pluginId}-${op.siteId}-${Date.now()}`;
    const operation: PublishOperation = {
      ...op,
      id,
      status: 'pending',
      progress: 0,
      stages: DEFAULT_STAGES.map(s => ({ ...s })),
      logs: [],
      startedAt: new Date().toISOString(),
    };
    
    set((state) => {
      const newOps = new Map(state.operations);
      newOps.set(id, operation);
      const newActiveIds = new Set(state.activePluginIds);
      newActiveIds.add(op.pluginId);
      return { 
        operations: newOps, 
        activePluginIds: newActiveIds,
        showGlobalProgress: true, // Auto-show when operation starts
      };
    });
    
    return id;
  },
  
  updateOperation: (id, updates) => {
    set((state) => {
      const op = state.operations.get(id);
      if (!op) return state;
      
      const newOps = new Map(state.operations);
      newOps.set(id, { ...op, ...updates });
      return { operations: newOps };
    });
  },
  
  updateStage: (id, stageName, status, message) => {
    set((state) => {
      const op = state.operations.get(id);
      if (!op) return state;
      
      const newStages = op.stages.map(s => 
        s.name === stageName ? { ...s, status, message } : s
      );
      
      // Calculate progress based on stage completion
      const completedStages = newStages.filter(s => 
        s.status === 'success' || s.status === 'skipped'
      ).length;
      const progress = Math.round((completedStages / newStages.length) * 100);
      
      // Determine overall status
      let operationStatus = op.status;
      if (status === 'running') {
        operationStatus = 'running';
      } else if (status === 'error') {
        operationStatus = 'error';
      }
      
      const newOps = new Map(state.operations);
      newOps.set(id, { 
        ...op, 
        stages: newStages, 
        progress,
        status: operationStatus,
      });
      return { operations: newOps };
    });
  },
  
  addLog: (id, log) => {
    set((state) => {
      const op = state.operations.get(id);
      if (!op) return state;
      
      const newOps = new Map(state.operations);
      newOps.set(id, { 
        ...op, 
        logs: [...op.logs, log].slice(-500), // Keep last 500 logs
      });
      return { operations: newOps };
    });
  },
  
  completeOperation: (id, success, error, filesUpdated) => {
    set((state) => {
      const op = state.operations.get(id);
      if (!op) return state;
      
      const newOps = new Map(state.operations);
      newOps.set(id, { 
        ...op, 
        status: success ? 'success' : 'error',
        progress: 100,
        error,
        filesUpdated,
        completedAt: new Date().toISOString(),
      });
      
      // Update active plugin IDs
      const remainingActiveForPlugin = Array.from(newOps.values()).some(
        o => o.pluginId === op.pluginId && o.status === 'running'
      );
      const newActiveIds = new Set(state.activePluginIds);
      if (!remainingActiveForPlugin) {
        newActiveIds.delete(op.pluginId);
      }
      
      return { operations: newOps, activePluginIds: newActiveIds };
    });
    
    // Schedule auto-cleanup
    setTimeout(() => {
      get().removeOperation(id);
    }, CLEANUP_DELAY_MS);
  },
  
  removeOperation: (id) => {
    set((state) => {
      const newOps = new Map(state.operations);
      const op = newOps.get(id);
      newOps.delete(id);
      
      // Update active plugin IDs if needed
      const newActiveIds = new Set(state.activePluginIds);
      if (op) {
        const stillActive = Array.from(newOps.values()).some(
          o => o.pluginId === op.pluginId && (o.status === 'running' || o.status === 'pending')
        );
        if (!stillActive) {
          newActiveIds.delete(op.pluginId);
        }
      }
      
      return { operations: newOps, activePluginIds: newActiveIds };
    });
  },
  
  clearCompletedOperations: () => {
    set((state) => {
      const newOps = new Map(state.operations);
      for (const [id, op] of newOps) {
        if (op.status === 'success' || op.status === 'error') {
          newOps.delete(id);
        }
      }
      return { operations: newOps };
    });
  },
  
  setExpandedOperation: (id) => {
    set({ expandedOperationId: id });
  },
  
  toggleGlobalProgress: () => {
    set((state) => ({ showGlobalProgress: !state.showGlobalProgress }));
  },
  
  getOperationsForPlugin: (pluginId) => {
    const { operations } = get();
    return Array.from(operations.values()).filter(op => op.pluginId === pluginId);
  },
  
  hasActiveOperation: (pluginId, siteId) => {
    const { operations } = get();
    return Array.from(operations.values()).some(op => 
      op.pluginId === pluginId && 
      (siteId === undefined || op.siteId === siteId) &&
      (op.status === 'running' || op.status === 'pending')
    );
  },
  
  getActiveCount: () => {
    const { operations } = get();
    return Array.from(operations.values()).filter(
      op => op.status === 'running' || op.status === 'pending'
    ).length;
  },
}));

// =============================================================================
// WEBSOCKET INTEGRATION
// Setup WebSocket listeners for publish events
// =============================================================================

let wsListenersInitialized = false;

export function initializePublishWebSocketListeners() {
  if (wsListenersInitialized) return;
  wsListenersInitialized = true;
  
  const store = usePublishStore.getState();
  
  // Listen for publish_started events
  wsClient.on(WS_EVENTS.PUBLISH_STARTED, (data: unknown) => {
    const { pluginId, siteId, sessionId } = data as { 
      pluginId: number; 
      siteId: number;
      sessionId?: string;
    };
    
    // Find matching operation and update sessionId
    const operations = usePublishStore.getState().operations;
    for (const [id, op] of operations) {
      if (op.pluginId === pluginId && op.siteId === siteId && op.status === 'pending') {
        usePublishStore.getState().updateOperation(id, { 
          status: 'running',
          sessionId,
        });
        break;
      }
    }
  });
  
  // Listen for publish_progress events
  wsClient.on(WS_EVENTS.PUBLISH_PROGRESS, (data: unknown) => {
    const { pluginId, siteId, stage, status, message, progress } = data as {
      pluginId: number;
      siteId: number;
      stage: string;
      status: string;
      message?: string;
      progress?: number;
    };
    
    // Find matching operation
    const operations = usePublishStore.getState().operations;
    for (const [id, op] of operations) {
      if (op.pluginId === pluginId && op.siteId === siteId && op.status === 'running') {
        usePublishStore.getState().updateStage(
          id, 
          stage, 
          status as PublishStage['status'],
          message
        );
        if (progress !== undefined) {
          usePublishStore.getState().updateOperation(id, { progress });
        }
        break;
      }
    }
  });
  
  // Listen for log events
  wsClient.on(WS_EVENTS.LOG, (data: unknown) => {
    const { pluginId, siteId, operationType, log } = data as {
      pluginId?: number;
      siteId?: number;
      operationType?: string;
      log: {
        timestamp: string;
        level: string;
        step: string;
        message: string;
        details?: Record<string, unknown>;
      };
    };
    
    if (operationType !== 'publish' || !pluginId) return;
    
    // Find matching operation
    const operations = usePublishStore.getState().operations;
    for (const [id, op] of operations) {
      if (op.pluginId === pluginId && (siteId === undefined || op.siteId === siteId) && op.status === 'running') {
        usePublishStore.getState().addLog(id, {
          timestamp: log.timestamp,
          level: log.level as PublishLogEntry['level'],
          step: log.step,
          message: log.message,
          details: log.details,
        });
        break;
      }
    }
  });
  
  // Listen for publish_complete events
  wsClient.on(WS_EVENTS.PUBLISH_COMPLETE, (data: unknown) => {
    const { pluginId, siteId, success, error, filesUpdated } = data as {
      pluginId: number;
      siteId: number;
      success: boolean;
      error?: string;
      filesUpdated?: number;
    };
    
    // Find matching operation
    const operations = usePublishStore.getState().operations;
    for (const [id, op] of operations) {
      if (op.pluginId === pluginId && op.siteId === siteId && op.status === 'running') {
        usePublishStore.getState().completeOperation(id, success, error, filesUpdated);
        break;
      }
    }
  });
}
