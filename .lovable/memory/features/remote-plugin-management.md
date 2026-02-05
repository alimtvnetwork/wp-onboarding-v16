# Memory: features/remote-plugin-management
Updated: 2026-02-05

The Remote Plugin Management feature allows users to view and control all plugins installed on a WordPress site directly from the dashboard. This provides centralized management without requiring direct WordPress dashboard access.

## UI Access

Click "Plugins" button on a connected Site Card to open the RemotePluginsPanel dialog.

## Capabilities

| Action | Description |
|--------|-------------|
| View | List all plugins with name, version, status, author |
| Search | Filter plugins by name |
| Enable | Activate an inactive plugin |
| Disable | Deactivate an active plugin |
| Delete | Remove plugin (with confirmation dialog) |

## API Endpoints

- `GET /api/v1/sites/{id}/remote-plugins` - List all plugins
- `POST /api/v1/sites/{id}/remote-plugins/{slug}/enable` - Activate
- `POST /api/v1/sites/{id}/remote-plugins/{slug}/disable` - Deactivate
- `DELETE /api/v1/sites/{id}/remote-plugins/{slug}` - Delete

## WordPress REST API

Uses the WordPress Plugins REST API with application password authentication:

- `GET /wp-json/wp/v2/plugins` - List
- `POST /wp-json/wp/v2/plugins/{plugin}` - Update status
- `DELETE /wp-json/wp/v2/plugins/{plugin}` - Delete

## Requirements

WordPress user must have `activate_plugins` and `delete_plugins` capabilities.

## Related Files

- `src/components/sites/RemotePluginsPanel.tsx` - Panel component
- `src/components/sites/SiteCard.tsx` - Card with Plugins button
- `backend/internal/wordpress/client.go` - WordPress client methods
- `backend/internal/services/site/service.go` - Site service proxy methods
- `spec/wp-plugin-publish/02-frontend/28-remote-plugins.md` - Full specification
