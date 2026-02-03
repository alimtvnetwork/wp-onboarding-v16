import { useState, useEffect, useCallback } from "react";
import { wsClient, WS_EVENTS } from "@/lib/ws";

export interface ConnectionTestStep {
  step: string;
  status: "running" | "success" | "error";
  message: string;
  details?: Record<string, unknown>;
  timestamp: Date;
}

export interface ConnectionTestState {
  siteId: number | null;
  isActive: boolean;
  steps: ConnectionTestStep[];
}

export function useConnectionTestLogs() {
  const [state, setState] = useState<ConnectionTestState>({
    siteId: null,
    isActive: false,
    steps: [],
  });

  useEffect(() => {
    const handleProgress = (data: unknown) => {
      const { siteId, step, status, message, details } = data as {
        siteId: number;
        step: string;
        status: "running" | "success" | "error";
        message: string;
        details?: Record<string, unknown>;
      };

      setState((prev) => {
        // Start a new test session
        if (step === "start") {
          return {
            siteId,
            isActive: true,
            steps: [{ step, status, message, details, timestamp: new Date() }],
          };
        }

        // Complete the test
        if (step === "complete") {
          return {
            ...prev,
            isActive: false,
            steps: [...prev.steps, { step, status, message, details, timestamp: new Date() }],
          };
        }

        // Add step to existing session
        return {
          ...prev,
          siteId: siteId || prev.siteId,
          steps: [...prev.steps, { step, status, message, details, timestamp: new Date() }],
        };
      });
    };

    const unsubProgress = wsClient.on(WS_EVENTS.CONNECTION_TEST_PROGRESS, handleProgress);

    return () => {
      unsubProgress();
    };
  }, []);

  const clearLogs = useCallback(() => {
    setState({
      siteId: null,
      isActive: false,
      steps: [],
    });
  }, []);

  return { ...state, clearLogs };
}
