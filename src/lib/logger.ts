/**
 * Frontend Logging Utility
 * 
 * Provides structured logging with:
 * - Automatic file path + line number extraction
 * - Function entry/exit tracking with duration
 * - Log level filtering (debug/info/warn/error)
 * - In-memory log buffer for diagnostics export
 * - Configurable via settings
 */

export type LogLevel = 'trace' | 'debug' | 'info' | 'warn' | 'error';

export interface LogEntry {
  id: string;
  timestamp: string;
  level: LogLevel;
  message: string;
  functionName?: string;
  action?: 'enter' | 'exit';
  duration?: number;
  filePath?: string;
  lineNumber?: number;
  context?: Record<string, unknown>;
  stack?: string;
}

interface LoggerConfig {
  enabled: boolean;
  minLevel: LogLevel;
  maxEntries: number;
  consoleOutput: boolean;
}

const LOG_LEVELS: Record<LogLevel, number> = {
  trace: 0,
  debug: 1,
  info: 2,
  warn: 3,
  error: 4,
};

// Default config - can be overridden via settings
let config: LoggerConfig = {
  enabled: true,
  minLevel: 'info',
  maxEntries: 500,
  consoleOutput: true,
};

// In-memory log buffer
const logBuffer: LogEntry[] = [];

// Track function entry times for duration calculation
const entryTimes = new Map<string, number>();

// Generate unique ID for log entries
function generateId(): string {
  return `${Date.now()}-${Math.random().toString(36).slice(2, 9)}`;
}

/**
 * Extract file path and line number from stack trace
 */
function extractStackInfo(): { filePath?: string; lineNumber?: number; stack?: string } {
  const stack = new Error().stack;
  if (!stack) return {};

  const lines = stack.split('\n');
  // Skip first 3 lines (Error, this function, and the logger method)
  const callerLine = lines[3] || lines[2];
  
  if (!callerLine) return { stack };

  // Match patterns like:
  // - "at functionName (http://localhost:5173/src/lib/api.ts:42:15)"
  // - "at http://localhost:5173/src/lib/api.ts:42:15"
  // - "at functionName (src/lib/api.ts:42:15)"
  const match = callerLine.match(/(?:at\s+(?:\w+\s+)?\(?)([^()]+):(\d+):\d+/);
  
  if (match) {
    let filePath = match[1];
    // Clean up the path
    if (filePath.includes('/src/')) {
      filePath = 'src/' + filePath.split('/src/').pop();
    }
    return {
      filePath,
      lineNumber: parseInt(match[2], 10),
      stack,
    };
  }

  return { stack };
}

/**
 * Check if log level should be output
 */
function shouldLog(level: LogLevel): boolean {
  if (!config.enabled) return false;
  return LOG_LEVELS[level] >= LOG_LEVELS[config.minLevel];
}

/**
 * Add entry to log buffer (FIFO)
 */
function addToBuffer(entry: LogEntry): void {
  logBuffer.push(entry);
  if (logBuffer.length > config.maxEntries) {
    logBuffer.shift();
  }
}

/**
 * Format log entry for console output
 */
function formatForConsole(entry: LogEntry): string {
  const parts = [
    `[${entry.level.toUpperCase()}]`,
    entry.filePath ? `${entry.filePath}:${entry.lineNumber}` : null,
    entry.functionName ? `${entry.functionName}()` : null,
    entry.action ? `(${entry.action}${entry.duration ? ` ${entry.duration}ms` : ''})` : null,
    entry.message,
  ].filter(Boolean);

  return parts.join(' ');
}

/**
 * Output to console with appropriate method
 */
function consoleOutput(entry: LogEntry): void {
  if (!config.consoleOutput) return;

  const formatted = formatForConsole(entry);
  const contextStr = entry.context ? entry.context : undefined;

  switch (entry.level) {
    case 'trace':
    case 'debug':
      console.debug(formatted, contextStr);
      break;
    case 'info':
      console.info(formatted, contextStr);
      break;
    case 'warn':
      console.warn(formatted, contextStr);
      break;
    case 'error':
      console.error(formatted, contextStr);
      break;
  }
}

/**
 * Core log function
 */
function log(
  level: LogLevel,
  message: string,
  options?: {
    functionName?: string;
    action?: 'enter' | 'exit';
    duration?: number;
    context?: Record<string, unknown>;
    error?: unknown;
  }
): void {
  if (!shouldLog(level)) return;

  const stackInfo = extractStackInfo();

  const entry: LogEntry = {
    id: generateId(),
    timestamp: new Date().toISOString(),
    level,
    message,
    functionName: options?.functionName,
    action: options?.action,
    duration: options?.duration,
    filePath: stackInfo.filePath,
    lineNumber: stackInfo.lineNumber,
    context: options?.context,
    stack: level === 'error' ? stackInfo.stack : undefined,
  };

  // Add error details to context if provided
  if (options?.error) {
    entry.context = {
      ...entry.context,
      errorMessage: options.error instanceof Error ? options.error.message : String(options.error),
      errorStack: options.error instanceof Error ? options.error.stack : undefined,
    };
  }

  addToBuffer(entry);
  consoleOutput(entry);
}

/**
 * Logger API
 */
export const logger = {
  /**
   * Track function entry/exit with automatic duration measurement
   */
  trace(functionName: string, action: 'enter' | 'exit', context?: Record<string, unknown>): void {
    const key = `${functionName}-${Math.random().toString(36).slice(2, 9)}`;
    
    if (action === 'enter') {
      entryTimes.set(functionName, Date.now());
      log('trace', `Entering ${functionName}`, { functionName, action, context });
    } else {
      const entryTime = entryTimes.get(functionName);
      const duration = entryTime ? Date.now() - entryTime : undefined;
      entryTimes.delete(functionName);
      log('trace', `Exiting ${functionName}`, { functionName, action, duration, context });
    }
  },

  /**
   * Debug level log
   */
  debug(message: string, context?: Record<string, unknown>): void {
    log('debug', message, { context });
  },

  /**
   * Info level log
   */
  info(message: string, context?: Record<string, unknown>): void {
    log('info', message, { context });
  },

  /**
   * Warning level log
   */
  warn(message: string, context?: Record<string, unknown>): void {
    log('warn', message, { context });
  },

  /**
   * Error level log
   */
  error(message: string, error?: unknown, context?: Record<string, unknown>): void {
    log('error', message, { error, context });
  },

  /**
   * Get all logs or filter by criteria
   */
  getLogs(filter?: { level?: LogLevel; search?: string; limit?: number }): LogEntry[] {
    let logs = [...logBuffer];

    if (filter?.level) {
      const minLevel = LOG_LEVELS[filter.level];
      logs = logs.filter((entry) => LOG_LEVELS[entry.level] >= minLevel);
    }

    if (filter?.search) {
      const search = filter.search.toLowerCase();
      logs = logs.filter(
        (entry) =>
          entry.message.toLowerCase().includes(search) ||
          entry.functionName?.toLowerCase().includes(search) ||
          entry.filePath?.toLowerCase().includes(search)
      );
    }

    if (filter?.limit) {
      logs = logs.slice(-filter.limit);
    }

    return logs;
  },

  /**
   * Clear all logs
   */
  clearLogs(): void {
    logBuffer.length = 0;
    entryTimes.clear();
  },

  /**
   * Export logs as formatted string for diagnostics
   */
  exportLogs(): string {
    return logBuffer
      .map((entry) => {
        const parts = [
          entry.timestamp,
          `[${entry.level.toUpperCase()}]`,
          entry.filePath ? `${entry.filePath}:${entry.lineNumber}` : null,
          entry.functionName ? `${entry.functionName}()` : null,
          entry.action ? `(${entry.action}${entry.duration ? ` ${entry.duration}ms` : ''})` : null,
          entry.message,
          entry.context ? JSON.stringify(entry.context) : null,
        ]
          .filter(Boolean)
          .join(' ');
        return parts;
      })
      .join('\n');
  },

  /**
   * Update logger configuration
   */
  configure(newConfig: Partial<LoggerConfig>): void {
    config = { ...config, ...newConfig };
  },

  /**
   * Get current configuration
   */
  getConfig(): LoggerConfig {
    return { ...config };
  },
};

// Export type for external use
export type Logger = typeof logger;
