/**
 * Maps React Router paths to their React component names.
 * Used by the error reporter to display the active component
 * in the Page header and interaction path.
 */
const ROUTE_COMPONENT_MAP: Record<string, string> = {
  '/dashboard': 'Dashboard',
  '/sites': 'Sites',
  '/plugins': 'Plugins',
  '/publish-history': 'PublishHistory',
  '/site-health': 'SiteHealth',
  '/tests': 'Tests',
  '/logs': 'Logs',
  '/sessions': 'Sessions',
  '/request-sessions': 'RequestSessions',
  '/api-explorer': 'ApiExplorer',
  '/settings': 'Settings',
  '/errors': 'Errors',
};

/**
 * Resolve the React component name for a given route path.
 * Returns undefined if no match is found.
 */
export function getComponentForRoute(pathname: string): string | undefined {
  // Direct match first
  if (ROUTE_COMPONENT_MAP[pathname]) return ROUTE_COMPONENT_MAP[pathname];

  // Strip trailing slash and retry
  const clean = pathname.replace(/\/+$/, '') || '/';
  return ROUTE_COMPONENT_MAP[clean];
}
