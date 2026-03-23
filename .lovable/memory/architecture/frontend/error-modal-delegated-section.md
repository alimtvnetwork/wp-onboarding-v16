# Memory: architecture/frontend/error-modal-delegated-section
Updated: 2026-03-23

The Global Error Modal has three top-level sections: Backend, Frontend, and Delegated. The Delegated section (`DelegatedSection.tsx`) is a standalone component that appears only when delegated data exists (orange Globe icon button). It displays: DelegatedRequestServer info, structured PHP stack traces, DelegatedServiceErrorStack, response/request bodies, session PHP frames, and stacktrace.txt content. This was previously a nested sub-tab inside BackendSection — it was elevated to top-level per user request for better discoverability. The compact and full error reports include delegated logs via `buildDelegatedLogsSection()` in `errorReportGenerator.ts`.
