import { Toaster } from "@/components/ui/toaster";
import { Toaster as Sonner } from "@/components/ui/sonner";
import { TooltipProvider } from "@/components/ui/tooltip";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { BrowserRouter, Routes, Route, Navigate } from "react-router-dom";
import { ThemeProvider } from "@/components/theme-provider";
import { Layout } from "@/components/layout/Layout";
import { useWebSocket } from "@/hooks/useWebSocket";

// Pages
import Dashboard from "@/pages/Dashboard";
import Sites from "@/pages/Sites";
import Plugins from "@/pages/Plugins";
import Sync from "@/pages/Sync";
import Settings from "@/pages/Settings";
import Errors from "@/pages/Errors";
import NotFound from "@/pages/NotFound";

const queryClient = new QueryClient({
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
        <BrowserRouter>
          <WebSocketProvider>
            <Routes>
              <Route path="/" element={<Layout />}>
                <Route index element={<Navigate to="/dashboard" replace />} />
                <Route path="dashboard" element={<Dashboard />} />
                <Route path="sites" element={<Sites />} />
                <Route path="plugins" element={<Plugins />} />
                <Route path="sync" element={<Sync />} />
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
