import { create } from 'zustand';
import { wsClient, WS_EVENTS } from '@/lib/ws';
import type { LogEntryDetails } from '@/lib/api';
import {
  PublishOperationStatus,
  PublishStageName,
  PublishStageStatus,
  LogLevel,
  SessionType,
  PUBLISH_LOG_MAX,
  CLEANUP_DELAY_MS,
} from '@/lib/constants';

/**
 * Log entry from backend publish operations
 */
export interface PublishLogEntry {
  timestamp: string;
  level: LogLevel;
  step: string;
  message: string;
  details?: LogEntryDetails;
}

/**
 * Stage status for publish pipeline
 */
export interface PublishStage {
  name: PublishStageName;
  status: PublishStageStatus;
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
  status: PublishOperationStatus;
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
  { name: PublishStageName.Backup, status: PublishStageStatus.Pending },
  { name: PublishStageName.Package, status: PublishStageStatus.Pending },
  { name: PublishStageName.Upload, status: PublishStageStatus.Pending },
  { name: PublishStageName.Activate, status: PublishStageStatus.Pending },
  { name: PublishStageName.Cleanup, status: PublishStageStatus.Pending },
];

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
      status: PublishOperationStatus.Pending,
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
        s.status === PublishStageStatus.Success || s.status === PublishStageStatus.Skipped
      ).length;
      const progress = Math.round((completedStages / newStages.length) * 100);
      
      // Determine overall status
      let operationStatus = op.status;
      if (status === PublishStageStatus.Running) {
        operationStatus = PublishOperationStatus.Running;
      } else if (status === PublishStageStatus.Error) {
        operationStatus = PublishOperationStatus.Error;
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
        logs: [...op.logs, log].slice(-PUBLISH_LOG_MAX),
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
        status: success ? PublishOperationStatus.Success : PublishOperationStatus.Error,
        progress: 100,
        error,
        filesUpdated,
        completedAt: new Date().toISOString(),
      });
      
      // Update active plugin IDs
      const remainingActiveForPlugin = Array.from(newOps.values()).some(
        o => o.pluginId === op.pluginId && o.status === PublishOperationStatus.Running
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
          o => o.pluginId === op.pluginId && (o.status === PublishOperationStatus.Running || o.status === PublishOperationStatus.Pending)
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
        if (op.status === PublishOperationStatus.Success || op.status === PublishOperationStatus.Error) {
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
      (op.status === PublishOperationStatus.Running || op.status === PublishOperationStatus.Pending)
    );
  },
  
  getActiveCount: () => {
    const { operations } = get();
    return Array.from(operations.values()).filter(
      op => op.status === PublishOperationStatus.Running || op.status === PublishOperationStatus.Pending
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
      if (op.pluginId === pluginId && op.siteId === siteId && op.status === PublishOperationStatus.Pending) {
        usePublishStore.getState().updateOperation(id, { 
          status: PublishOperationStatus.Running,
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
      if (op.pluginId === pluginId && op.siteId === siteId && op.status === PublishOperationStatus.Running) {
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
        details?: LogEntryDetails;
      };
    };
    
    if (operationType !== SessionType.Publish || !pluginId) return;
    
    // Find matching operation
    const operations = usePublishStore.getState().operations;
    for (const [id, op] of operations) {
      if (op.pluginId === pluginId && (siteId === undefined || op.siteId === siteId) && op.status === PublishOperationStatus.Running) {
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
      if (op.pluginId === pluginId && op.siteId === siteId && op.status === PublishOperationStatus.Running) {
        usePublishStore.getState().completeOperation(id, success, error, filesUpdated);
        break;
      }
    }
  });
}
