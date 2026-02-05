# Memory: features/sites/remote-plugin-control
Updated: 2026-02-05

The 'See Plugins' feature on the Site Card allows users to view and manage the full plugin inventory of a remote WordPress site. This interface supports remote plugin lifecycle management (Enable, Disable, Delete) and maintenance operations (Backup, Restore) by communicating directly with the Riseup Asia Uploader REST API. This feature provides centralized control over remote WordPress environments without requiring direct dashboard access.

## Implementation Details

### Backend Endpoints
- `GET /api/v1/sites/{id}/remote-plugins` - List all installed plugins
- `POST /api/v1/sites/{id}/remote-plugins/{plugin}/enable` - Activate a plugin
- `POST /api/v1/sites/{id}/remote-plugins/{plugin}/disable` - Deactivate a plugin
- `DELETE /api/v1/sites/{id}/remote-plugins/{plugin}` - Remove a plugin

### Frontend Components
- `RemotePluginsPanel.tsx` - Dialog showing plugin list with search, toggle switches, and delete actions
- Integrated into `SiteCard.tsx` via "Plugins" button (visible when site is connected)

### Data Flow
1. User clicks "Plugins" button on connected site card
2. Dialog opens and fetches plugin list from WordPress via backend proxy
3. User can toggle plugin status (active/inactive) or delete plugins
4. Changes are applied immediately to the remote WordPress site
