/**
 * Shared types for the Global Error Modal sub-components.
 */

/** PHP stack trace frame from WordPress error context. */
export interface PHPStackFrame {
  file?: string;
  fileBase?: string;
  line?: number;
  function?: string;
  class?: string;
}

/** App metadata passed to report generators and action dropdowns. */
export interface AppInfo {
  appName: string;
  appVersion: string;
  gitCommit?: string;
  buildTime?: string;
}

/** Common props shared by section components. */
export interface SectionCommonProps {
  copySection: (label: string, content: string) => void;
  formatTs: (ts: string) => string;
}
