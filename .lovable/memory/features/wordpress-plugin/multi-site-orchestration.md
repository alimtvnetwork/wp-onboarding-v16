# Memory: features/wordpress-plugin/multi-site-orchestration
Updated: 2026-02-06

## Overview

The Riseup Asia Uploader supports a master-agent architecture where one WordPress site can control others. Master sites onboard agents using their URL and application credentials (username and application password), enabling remote management of plugin lifecycle and updates across multiple WordPress instances directly from the dashboard.

## Architecture

### Master-Agent Model

```
┌─────────────────────────────────────────────────────────────┐
│                     MASTER SITE                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │          Riseup Asia Uploader (Master)               │   │
│  │  • Agent Sites Table                                 │   │
│  │  • Agent Actions Log                                 │   │
│  │  • Orchestration REST API                            │   │
│  └─────────────────────────────────────────────────────┘   │
│                           │                                 │
│                           ▼                                 │
│        ┌──────────────────┼──────────────────┐             │
│        │                  │                  │             │
│        ▼                  ▼                  ▼             │
│  ┌──────────┐      ┌──────────┐      ┌──────────┐         │
│  │ Agent 1  │      │ Agent 2  │      │ Agent 3  │         │
│  │ Site A   │      │ Site B   │      │ Site C   │         │
│  └──────────┘      └──────────┘      └──────────┘         │
└─────────────────────────────────────────────────────────────┘
```

### Agent Onboarding

Required credentials:
- **URL**: Agent site's WordPress URL
- **Username**: WordPress admin username
- **Application Password**: WordPress application password
- **Redirect URL** (optional): 301 redirect URL for dynamic URL resolution

### Supported Operations

| Operation | Description |
|-----------|-------------|
| `enable` | Activate a plugin on agent |
| `disable` | Deactivate a plugin on agent |
| `delete` | Remove a plugin from agent |
| `update` | Push plugin update to agent |
| `sync` | Fetch current plugin status |

## Database Schema

### agent_sites

```sql
CREATE TABLE IF NOT EXISTS agent_sites (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    url TEXT NOT NULL,
    username TEXT NOT NULL,
    app_password_encrypted TEXT NOT NULL,
    redirect_url TEXT,
    redirect_resolved TEXT,
    status TEXT DEFAULT 'pending',
    last_sync TEXT,
    created_at TEXT NOT NULL
);
```

### agent_actions

```sql
CREATE TABLE IF NOT EXISTS agent_actions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    agent_site_id INTEGER NOT NULL,
    action TEXT NOT NULL,
    target_plugin TEXT,
    status TEXT NOT NULL,
    details TEXT,
    created_at TEXT NOT NULL,
    FOREIGN KEY (agent_site_id) REFERENCES agent_sites(id)
);
```

## REST API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/agents` | List all agent sites |
| POST | `/agents` | Add new agent site |
| DELETE | `/agents/{id}` | Remove agent site |
| POST | `/agents/{id}/test` | Test agent connection |
| POST | `/agents/{id}/sync` | Sync agent status |
| POST | `/agents/{id}/action` | Execute action on agent |

## Admin Dashboard

The "Agent Sites" submenu provides:
- Table of all agents with status indicators
- Add Agent form
- Per-agent actions (Test, Sync, Remove)
- Remote plugin list per agent
- Bulk operations

## Security Considerations

1. Application passwords are encrypted in SQLite using AES-256
2. All API calls to agents use HTTPS
3. Actions are logged in `agent_actions` table
4. Connection tests validate credentials before saving

## Related Files

- `wp-plugins/riseup-asia-uploader/includes/class-agent-manager.php`
- `wp-plugins/riseup-asia-uploader/includes/class-database.php`
- `wp-plugins/riseup-asia-uploader/templates/admin-agents.php`
