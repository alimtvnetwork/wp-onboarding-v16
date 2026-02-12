import { useState, useEffect, useCallback } from "react";
import { wsClient, WS_EVENTS } from "@/lib/ws";
import { ConnectionTestStep as ConnectionTestStepConst, ConnectionTestStatus } from "@/lib/constants";

/** Connection test step details — replaces Record<string, unknown> per GE-1 */
export interface ConnectionTestStepDetails {
  [key: string]: unknown;
}

export interface ConnectionTestStep {
  step: string;
  status: ConnectionTestStatus;
  message: string;
  details?: ConnectionTestStepDetails;
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
        status: ConnectionTestStatus;
        message: string;
        details?: ConnectionTestStepDetails;
      };

      setState((prev) => {
        // Start a new test session - clear previous logs
        if (step === ConnectionTestStepConst.Start) {
          return {
            siteId,
            isActive: true,
            steps: [{ step, status, message, details, timestamp: new Date() }],
          };
        }

        // Complete the test - update the "start" step to show final status
        if (step === ConnectionTestStepConst.Complete) {
          const updatedSteps = prev.steps.map((s) =>
            s.step === ConnectionTestStepConst.Start && s.status === ConnectionTestStatus.Running
              ? { ...s, status, timestamp: new Date() }
              : s
          );
          return {
            ...prev,
            isActive: false,
            steps: [...updatedSteps, { step, status, message, details, timestamp: new Date() }],
          };
        }

        // Check if this step already exists with "running" status - update it in-place
        const existingIndex = prev.steps.findIndex(
          (s) => s.step === step && s.status === ConnectionTestStatus.Running
        );

        if (existingIndex !== -1) {
          // Update existing step in-place instead of appending
          const updatedSteps = [...prev.steps];
          updatedSteps[existingIndex] = {
            step,
            status,
            message,
            details,
            timestamp: new Date(),
          };
          return {
            ...prev,
            siteId: siteId || prev.siteId,
            steps: updatedSteps,
          };
        }

        // Add new step (not found in existing steps)
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
