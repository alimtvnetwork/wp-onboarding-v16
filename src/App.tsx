import { Toaster } from "@/components/ui/toaster";
import { Toaster as Sonner } from "@/components/ui/sonner";
import { TooltipProvider } from "@/components/ui/tooltip";
import {
  MutationCache,
  QueryCache,
  QueryClient,
  QueryClientProvider,
} from "@tanstack/react-query";
import { BrowserRouter, Routes, Route, Navigate } from "react-router-dom";
import { ThemeProvider } from "@/components/theme-provider";
import { Layout } from "@/components/layout/Layout";
import { useWebSocket } from "@/hooks/useWebSocket";
import { GlobalErrorModal } from "@/components/errors/GlobalErrorModal";
import { toast } from "sonner";
import { isApiClientError } from "@/lib/api";
import { useErrorStore } from "@/stores/errorStore";

// Pages
import Dashboard from "@/pages/Dashboard";
import Sites from "@/pages/Sites";
import Plugins from "@/pages/Plugins";
import Sync from "@/pages/Sync";
import Logs from "@/pages/Logs";
import Settings from "@/pages/Settings";
import Errors from "@/pages/Errors";
import Tests from "@/pages/Tests";
import NotFound from "@/pages/NotFound";

function showGlobalError(error: unknown, context?: { endpoint?: string; method?: string }) {
  const { captureError, captureException, openErrorModal } = useErrorStore.getState();

  if (isApiClientError(error)) {
    const captured = captureError(error.apiError, {
      endpoint: error.meta.requestUrl,
      method: error.meta.method,
      requestBody: error.meta.requestBody,
      responseStatus: (error.apiError.context?.responseStatus as number | undefined) ?? undefined,
    });

    if (error.apiError.code === "E9005") {
      openErrorModal(captured);
      return;
    }

    toast.error(error.apiError.message, {
      description: "Click for details",
      action: { label: "View Details", onClick: () => openErrorModal(captured) },
      duration: 10000,
    });
    return;
  }

  const captured = captureException(error, {
    endpoint: context?.endpoint,
    method: context?.method,
  });
  toast.error("Request failed", {
    description: "Click for details",
    action: { label: "View Details", onClick: () => openErrorModal(captured) },
    duration: 10000,
  });
}

const queryClient = new QueryClient({
  queryCache: new QueryCache({
    onError: (error, query) => {
      showGlobalError(error, { endpoint: String(query.queryKey?.[0] ?? "query") });
    },
  }),
  mutationCache: new MutationCache({
    onError: (error, _variables, _context, mutation) => {
      showGlobalError(error, { endpoint: String(mutation.options.mutationKey?.[0] ?? "mutation") });
    },
  }),
  defaultOptions: {
    queries: {
      staleTime: 5 * 60 * 1000, // 5 minutes
      retry: 1,
    },
  },
});

// WebSocket connection wrapper
function WebSocketProvider({ children }: { children: React.ReactNode }) {
  useWebSocket();
  return <>{children}</>;
}

const App = () => (
  <QueryClientProvider client={queryClient}>
    <ThemeProvider defaultTheme="system" storageKey="wpp-theme">
      <TooltipProvider>
        <Toaster />
        <Sonner />
        <GlobalErrorModal />
        <BrowserRouter>
          <WebSocketProvider>
            <Routes>
              <Route path="/" element={<Layout />}>
                <Route index element={<Navigate to="/dashboard" replace />} />
                <Route path="dashboard" element={<Dashboard />} />
                <Route path="sites" element={<Sites />} />
                <Route path="plugins" element={<Plugins />} />
                <Route path="sync" element={<Sync />} />
                <Route path="tests" element={<Tests />} />
                <Route path="logs" element={<Logs />} />
                <Route path="settings" element={<Settings />} />
                <Route path="errors" element={<Errors />} />
              </Route>
              <Route path="*" element={<NotFound />} />
            </Routes>
          </WebSocketProvider>
        </BrowserRouter>
      </TooltipProvider>
    </ThemeProvider>
  </QueryClientProvider>
);

export default App;
