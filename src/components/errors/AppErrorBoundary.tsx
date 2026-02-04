import React from "react";
import { Button } from "@/components/ui/button";
import { useErrorStore, type CapturedError } from "@/stores/errorStore";

type AppErrorBoundaryProps = {
  children: React.ReactNode;
};

type AppErrorBoundaryState = {
  captured: CapturedError | null;
};

/**
 * Prevents full white-screen crashes by catching render errors and surfacing them
 * in the Global Error Modal with an explicit source.
 */
export class AppErrorBoundary extends React.Component<AppErrorBoundaryProps, AppErrorBoundaryState> {
  state: AppErrorBoundaryState = {
    captured: null,
  };

  componentDidCatch(error: unknown, info: React.ErrorInfo) {
    const { captureException, openErrorModal } = useErrorStore.getState();

    const captured = captureException(error, {
      endpoint: "render",
      method: "RENDER",
      source: "AppErrorBoundary.componentDidCatch",
      context: {
        componentStack: info.componentStack,
      },
    });

    // Open immediately so the user sees exact stack/function info.
    openErrorModal(captured);
    this.setState({ captured });
  }

  render() {
    if (!this.state.captured) return this.props.children;

    return (
      <div className="flex min-h-[60vh] items-center justify-center px-6 py-12">
        <div className="w-full max-w-lg space-y-4 rounded-lg border bg-background p-6">
          <h1 className="text-lg font-semibold">Something went wrong</h1>
          <p className="text-sm text-muted-foreground">
            A UI render error was caught. You can open the error details modal to see the exact
            function/file/stack.
          </p>
          <div className="flex flex-col gap-2 sm:flex-row sm:justify-end">
            <Button
              variant="outline"
              onClick={() => useErrorStore.getState().openErrorModal(this.state.captured!)}
            >
              View error details
            </Button>
            <Button onClick={() => window.location.reload()}>Reload</Button>
          </div>
        </div>
      </div>
    );
  }
}
