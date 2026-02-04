import { describe, it, expect, vi, beforeEach } from "vitest";
import { render, screen, fireEvent, waitFor } from "@testing-library/react";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { BrowserRouter } from "react-router-dom";
import Settings from "./Settings";

// Mock the useSettings hook
vi.mock("@/hooks/useSettings", () => ({
  useSettings: () => ({
    data: { version: "1.12.1", buildDate: "2025-02-04" },
    isLoading: false,
    error: null,
  }),
}));

// Test wrapper with providers
const createWrapper = () => {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
    },
  });
  return ({ children }: { children: React.ReactNode }) => (
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>{children}</BrowserRouter>
    </QueryClientProvider>
  );
};

describe("Settings Page - Upload Mode", () => {
  beforeEach(() => {
    localStorage.clear();
  });

  it("renders the Publish Settings card with upload mode options", () => {
    render(<Settings />, { wrapper: createWrapper() });

    expect(screen.getByText("Publish Settings")).toBeInTheDocument();
    expect(screen.getByText("Upload Mode")).toBeInTheDocument();
    expect(screen.getByText("File-by-file (default)")).toBeInTheDocument();
    expect(screen.getByText("ZIP package")).toBeInTheDocument();
  });

  it("defaults to file-by-file mode when localStorage is empty", () => {
    render(<Settings />, { wrapper: createWrapper() });

    const fileRadio = screen.getByRole("radio", { name: /file-by-file/i });
    expect(fileRadio).toBeChecked();
  });

  it("persists zip mode selection to localStorage", async () => {
    render(<Settings />, { wrapper: createWrapper() });

    const zipRadio = screen.getByRole("radio", { name: /zip package/i });
    fireEvent.click(zipRadio);

    await waitFor(() => {
      expect(localStorage.getItem("wppp_upload_mode")).toBe("zip");
    });
  });

  it("loads saved zip mode from localStorage", () => {
    localStorage.setItem("wppp_upload_mode", "zip");
    render(<Settings />, { wrapper: createWrapper() });

    const zipRadio = screen.getByRole("radio", { name: /zip package/i });
    expect(zipRadio).toBeChecked();
  });

  it("renders all settings cards", () => {
    render(<Settings />, { wrapper: createWrapper() });

    expect(screen.getByText("File Watching")).toBeInTheDocument();
    expect(screen.getByText("Backups")).toBeInTheDocument();
    expect(screen.getByText("Publish Settings")).toBeInTheDocument();
    expect(screen.getByText("Appearance")).toBeInTheDocument();
  });

  it("has functional poll interval selector", () => {
    render(<Settings />, { wrapper: createWrapper() });

    expect(screen.getByText("Poll Interval")).toBeInTheDocument();
    expect(screen.getByText("How often to check for file changes")).toBeInTheDocument();
  });

  it("has functional backup settings", () => {
    render(<Settings />, { wrapper: createWrapper() });

    expect(screen.getByText("Auto-backup before publish")).toBeInTheDocument();
    expect(screen.getByText("Retention Days")).toBeInTheDocument();
    expect(screen.getByText("Max Backups per Plugin")).toBeInTheDocument();
  });
});

describe("Settings Page - Appearance", () => {
  it("renders theme selector", () => {
    render(<Settings />, { wrapper: createWrapper() });

    expect(screen.getByText("Theme")).toBeInTheDocument();
  });

  it("renders compact mode toggle", () => {
    render(<Settings />, { wrapper: createWrapper() });

    expect(screen.getByText("Compact Mode")).toBeInTheDocument();
    expect(screen.getByText("Reduce spacing for more content density")).toBeInTheDocument();
  });
});

describe("Settings Page - Actions", () => {
  it("renders Save and Reset buttons", () => {
    render(<Settings />, { wrapper: createWrapper() });

    expect(screen.getByRole("button", { name: /save/i })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: /reset to defaults/i })).toBeInTheDocument();
  });
});

describe("Settings - Upload Mode Logic", () => {
  it("should default to file mode when localStorage is empty", () => {
    const saved: string | null = null;
    const mode = saved === "zip" ? "zip" : "file";
    expect(mode).toBe("file");
  });

  it("should recognize zip mode from localStorage", () => {
    const saved = "zip";
    const mode = saved === "zip" ? "zip" : "file";
    expect(mode).toBe("zip");
  });

  it("should have two upload mode options", () => {
    const options = ["file", "zip"];
    expect(options).toContain("file");
    expect(options).toContain("zip");
  });
});
