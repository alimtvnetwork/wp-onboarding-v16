## Eliminate Magic Strings in All Template Files

### Problem

The JavaScript sections of the template files — particularly `admin-agents.php` and partially `admin-snapshots.php` — still contain hardcoded REST endpoint paths, status strings, response keys, and UI labels that should be driven by PHP enums. The PHP/HTML portions are generally well-enumified, but the inline `<script>` blocks were not fully refactored.

### Audit Summary


| Template              | PHP/HTML | JavaScript                                                                                                         | Verdict    |
| --------------------- | -------- | ------------------------------------------------------------------------------------------------------------------ | ---------- |
| `admin-settings.php`  | Clean    | Clean (uses `AjaxActionType`, `NonceType`, `SnapshotFrequencyType`, etc.)                                          | OK         |
| `admin-errors.php`    | Clean    | Deferred to partials (not in repo yet)                                                                             | OK         |
| `admin-logs.php`      | Clean    | No inline JS                                                                                                       | OK         |
| `admin-snapshots.php` | Clean    | Mostly clean (has `SNAP_STATUS`/`SNAP_MODE`/`SNAP_SCOPE`/`SNAP_FREQ` constants block) but REST paths are hardcoded | Needs work |
| `admin-agents.php`    | Clean    | **Major offender** — all REST paths, status strings, response keys, and UI labels are hardcoded magic strings      | Needs work |


### Detailed Findings

#### 1. `admin-agents.php` — JavaScript (lines 279-556)

**A. REST endpoint paths (hardcoded strings instead of `EndpointType` values):**


| Line(s) | Magic String                              | Should Use                                                                    |
| ------- | ----------------------------------------- | ----------------------------------------------------------------------------- |
| 312     | `'agents'`                                | `EndpointType::Agents->value`                                                 |
| 366     | `'agents'` (POST)                         | `EndpointType::AgentsAdd->value` (or `EndpointType::Agents->value` with POST) |
| 386     | `'agents/' + id + '/test'`                | Constructed from `EndpointType::AgentsTest->value` pattern                    |
| 402     | `'agents/' + id + '/sync'`                | Constructed from `EndpointType::AgentsSync->value` pattern                    |
| 424     | `'agents/' + id + '/sync'` (view plugins) | `EndpointType::AgentsSync->value`                                             |
| 472     | `'agents/' + currentAgentId + '/action'`  | `EndpointType::AgentAction->value`                                            |
| 497     | `'agents/' + id + '/history'`             | `EndpointType::AgentHistory->value`                                           |
| 533     | `'agents/' + id` (DELETE)                 | `EndpointType::AgentsRemove->value` pattern                                   |


**B. Status and UI label magic strings:**


| Line(s) | Magic String                                        | Should Use                             |
| ------- | --------------------------------------------------- | -------------------------------------- |
| 315     | `'agents'` (response key)                           | `ResponseKeyType`                      |
| 431     | `'active'` (status check)                           | `StatusType` or `AgentStatusType`      |
| 433     | `'Active'`, `'Inactive'` (labels)                   | Localized JS constants from PHP `__()` |
| 442     | `'Disable'`, `'Enable'`, `'Delete'` (button labels) | Localized JS constants                 |
| 451     | `'Failed to load plugins'`                          | Localized JS constant                  |
| 466     | `'Are you sure you want to delete...'`              | Localized JS constant                  |
| 500     | `'actions'` (response key)                          | `ResponseKeyType`                      |
| 501     | `'No action history'`                               | Localized JS constant                  |
| 504     | `'Success'` (status check)                          | `StatusType::Success->value`           |
| 517     | `'Failed to load history'`                          | Localized JS constant                  |
| 527     | `'Remove agent site...'`                            | Localized JS constant                  |


**C. Response JSON keys used directly:**

- `response.agents` (line 315) -- should match `ResponseKeyType::Agents`
- `response.count` (line 403)
- `response.plugins` (line 427)
- `response.actions` (line 500)
- `action.status`, `action.action`, `action.target_plugin` (lines 504-510)

#### 2. `admin-snapshots.php` — JavaScript (lines 553-1787)

**A. REST endpoint paths (hardcoded in `$.ajax` URL constructions):**

The snapshots template does NOT use a centralized `ENDPOINTS` constant block like it does for statuses/modes. All REST paths are inline:


| Line(s) | Magic String                  | Should Use     |
| ------- | ----------------------------- | -------------- |
| 775     | `'/snapshots/progress'`       | `EndpointType` |
| 854     | `'/snapshots/list?limit=...'` | `EndpointType` |
| 1004    | `'/snapshots/settings'` (GET) | `EndpointType` |
| 1048    | `'/snapshots/providers'`      | `EndpointType` |
| 1105    | `'/snapshots/tables'`         | `EndpointType` |
| 1137    | `'/snapshots/schedule'`       | `EndpointType` |
| 1180    | `'/snapshots/incremental'`    | `EndpointType` |
| 1231    | `'/snapshots/import'`         | `EndpointType` |
| 1295    | `'/snapshots/restore'`        | `EndpointType` |
| 1343    | `'/snapshots/download'`       | `EndpointType` |
| 1474    | `'/snapshots/delete'`         | `EndpointType` |


**B. Non-localized UI strings in JS:**


| Line(s)   | Magic String                                                            |
| --------- | ----------------------------------------------------------------------- |
| 749       | `'Copied!'`                                                             |
| 1055-1056 | `'Provider'`, `'Available'`, `'Priority'` (table headers)               |
| 1225      | `'Importing...'`                                                        |
| 1249      | `'Upload & Import'` (button restore text)                               |
| 1292      | `'Restoring...'`                                                        |
| 1320      | `'Restore Now'` (button restore text)                                   |
| 1363      | `'Cached'`, `'Built'`                                                   |
| 1424      | `'Copy Report'`                                                         |
| 1448      | `'Are you sure you want to delete snapshot...'`                         |
| 1648-1651 | Month names array (not localized)                                       |
| 1734-1736 | `'Full backup'`, `'Incremental'`, `'Scheduled backup'` (tooltip titles) |


**C. Response JSON keys used directly:**

- `response.snapshots` (line 862)
- `response.total` (line 922)
- `response.job_id` (multiple lines)
- `response.percent`, `response.status`, etc. (progress response)

### Implementation Plan

#### Step 1: Add `CONSTANTS` block to `admin-agents.php` JS

Add a PHP-to-JS constants block at the top of the `<script>` section (after the existing variable declarations), similar to how `admin-snapshots.php` already has `SNAP_STATUS`, `SNAP_MODE`, etc.:

```javascript
// ENUM CONSTANTS (from PHP -- prevents magic strings in JS)
var ENDPOINTS = {
    agents:       '<?php echo esc_js(EndpointType::Agents->value); ?>',
    agentsAdd:    '<?php echo esc_js(EndpointType::AgentsAdd->value); ?>',
    agentsRemove: '<?php echo esc_js(EndpointType::AgentsRemove->value); ?>',
    agentsTest:   '<?php echo esc_js(EndpointType::AgentsTest->value); ?>',
    agentsSync:   '<?php echo esc_js(EndpointType::AgentsSync->value); ?>',
    agentsPlugins:'<?php echo esc_js(EndpointType::AgentsPlugins->value); ?>',
    agentAction:  '<?php echo esc_js(EndpointType::AgentAction->value); ?>',
    agentHistory: '<?php echo esc_js(EndpointType::AgentHistory->value); ?>'
};
var AGENT_STATUS = {
    pending:   '<?php echo esc_js(AgentStatusType::Pending->value); ?>',
    connected: '<?php echo esc_js(AgentStatusType::Connected->value); ?>',
    error:     '<?php echo esc_js(AgentStatusType::Error->value); ?>'
};
var STATUS = {
    success: '<?php echo esc_js(StatusType::Success->value); ?>'
};
var LABELS = {
    active:              '<?php echo esc_js(__("Active", "riseup-asia-uploader")); ?>',
    inactive:            '<?php echo esc_js(__("Inactive", "riseup-asia-uploader")); ?>',
    enable:              '<?php echo esc_js(__("Enable", "riseup-asia-uploader")); ?>',
    disable:             '<?php echo esc_js(__("Disable", "riseup-asia-uploader")); ?>',
    deleteBtn:           '<?php echo esc_js(__("Delete", "riseup-asia-uploader")); ?>',
    noPluginsFound:      '<?php echo esc_js(__("No plugins found", "riseup-asia-uploader")); ?>',
    failedLoadPlugins:   '<?php echo esc_js(__("Failed to load plugins", "riseup-asia-uploader")); ?>',
    confirmDeletePlugin: '<?php echo esc_js(__("Are you sure you want to delete this plugin from the remote site?", "riseup-asia-uploader")); ?>',
    noActionHistory:     '<?php echo esc_js(__("No action history", "riseup-asia-uploader")); ?>',
    failedLoadHistory:   '<?php echo esc_js(__("Failed to load history", "riseup-asia-uploader")); ?>',
    confirmRemoveAgent:  '<?php echo esc_js(__("Remove agent site \"%s\"? This cannot be undone.", "riseup-asia-uploader")); ?>',
    connectionSuccess:   '<?php echo esc_js(__("Connection successful!", "riseup-asia-uploader")); ?>',
    connectionFailed:    '<?php echo esc_js(__("Connection failed:", "riseup-asia-uploader")); ?>',
    testFailed:          '<?php echo esc_js(__("Test failed:", "riseup-asia-uploader")); ?>',
    synced:              '<?php echo esc_js(__("Synced %d plugins", "riseup-asia-uploader")); ?>',
    syncFailed:          '<?php echo esc_js(__("Sync failed:", "riseup-asia-uploader")); ?>',
    actionFailed:        '<?php echo esc_js(__("Action failed:", "riseup-asia-uploader")); ?>',
    failedToRemove:      '<?php echo esc_js(__("Failed to remove:", "riseup-asia-uploader")); ?>'
};
```

Then replace all hardcoded strings in the JS body with these constants.

Note: The agents template uses a pattern like `'agents/' + id + '/test'` for per-agent endpoints. Since `EndpointType` stores `'agents/test'`, we need a helper or we construct the URL as `ENDPOINTS.agents + '/' + id + '/test'`. We will keep the pattern but centralize the base segment.

#### Step 2: Add `SNAP_ENDPOINTS` block to `admin-snapshots.php` JS

Add an endpoints constant block alongside the existing `SNAP_STATUS`/`SNAP_MODE` blocks:

```javascript
var SNAP_ENDPOINTS = {
    list:        '<?php echo esc_js(EndpointType::SnapshotsList->value); ?>',
    schedule:    '<?php echo esc_js(EndpointType::SnapshotsSchedule->value); ?>',
    info:        '<?php echo esc_js(EndpointType::SnapshotsInfo->value); ?>',
    delete_:     '<?php echo esc_js(EndpointType::SnapshotsDelete->value); ?>',
    restore:     '<?php echo esc_js(EndpointType::SnapshotsRestore->value); ?>',
    export_:     '<?php echo esc_js(EndpointType::SnapshotsExport->value); ?>',
    settings:    '<?php echo esc_js(EndpointType::SnapshotsSettings->value); ?>',
    providers:   '<?php echo esc_js(EndpointType::SnapshotsProviders->value); ?>',
    tables:      '<?php echo esc_js(EndpointType::SnapshotsTables->value); ?>',
    fullBackup:  '<?php echo esc_js(EndpointType::SnapshotsFullBackup->value); ?>',
    incremental: '<?php echo esc_js(EndpointType::SnapshotsIncremental->value); ?>',
    import_:     '<?php echo esc_js(EndpointType::SnapshotsImport->value); ?>',
    cleanup:     '<?php echo esc_js(EndpointType::SnapshotsCleanup->value); ?>',
    download:    '<?php echo esc_js(EndpointType::SnapshotsDownload->value); ?>',
    progress:    '<?php echo esc_js(EndpointType::SnapshotsProgress->value); ?>'
};
```

in your plan, you did not plan to update the Riseup- hyphen ASIA hyphen uploader in magic string, which should be the crucial to update, and this should not be-- come as a magic string anywhere. So make sure that you update this. This is not in the plan, uh, as I can see that you have generated code, but it's not there. But make sure that everything is actually constant or enum.

  
  
Add `use RiseupAsia\Enums\EndpointType;` to the imports if missing, then replace all `restBase + '/snapshots/...'` calls with `restBase + '/' + SNAP_ENDPOINTS.xxx`.

Also add a `SNAP_LABELS` block for the non-localized UI strings (month names, button text, confirmation messages, table headers).

#### Step 3: Add `EndpointType` import to `admin-snapshots.php`

The snapshots template does not currently import `EndpointType`. Add it to the `use` block at the top.

### Files to Modify


| File                  | Changes                                                                                                                                          |
| --------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------ |
| `admin-agents.php`    | Add `ENDPOINTS`, `AGENT_STATUS`, `STATUS`, `LABELS` JS constant blocks; replace ~30 hardcoded strings in JS with constants                       |
| `admin-snapshots.php` | Add `use EndpointType;` import; add `SNAP_ENDPOINTS` and `SNAP_LABELS` JS constant blocks; replace ~15 endpoint strings and ~15 UI label strings |


### Files NOT Needing Changes


| File                 | Reason                                                                                                                                   |
| -------------------- | ---------------------------------------------------------------------------------------------------------------------------------------- |
| `admin-settings.php` | Already fully enumified (PHP and JS both use `AjaxActionType`, `NonceType`, `SnapshotFrequencyType`, `RetentionType`, `StorageModeType`) |
| `admin-errors.php`   | PHP section fully enumified; JS deferred to partials                                                                                     |
| `admin-logs.php`     | Fully enumified with `LogColumnType`, `StatusType`, `TriggerSourceType`, etc.; no inline JS                                              |


### Estimated Scope

- ~45 magic string replacements across 2 files
- No structural changes, no new enums needed (all required enums already exist)
- Pattern follows the existing `SNAP_STATUS`/`SNAP_MODE` approach already proven in `admin-snapshots.php`