# Memory: wp-plugin/link-manager-architecture
Updated: 2026-01-31

The Link Manager WordPress plugin architecture (`spec/wp-plugin/link-manager/`) is defined as a specification-only project with **30 spec files**, **29 database tables**, and **34 PHP entity classes**. It utilizes a three-tier storage system: main DB (29 tables), per-content history DBs, and system snapshots. The plugin provides **110+ REST API endpoints** under the `lm/v1` namespace across 13 core services.

## Services (13 total)
- **Core**: ScanService, LinkParser, ModificationService, HistoryService, SnapshotService
- **Integration**: ElementorService, CSVImportService, InternalLinkingService
- **Monitoring**: HealthMonitorService, NotificationService
- **SEO**: YoastSeoService
- **AI**: AiProviderService (seedable config for OpenAI, Gemini, Anthropic, Mistral, Groq, Ollama)
- **Background**: CronService

## Database Tables (29 total)
- **Core** (5): Posts, Pages, Categories, Links, Settings
- **Scan** (3): ScanHistory, ScanJobs, ScanQueue
- **History** (2): ContentVersions, ModificationLog
- **Cron** (2): CronJobs, CronLogs
- **Internal Linking** (4): LinkTargets, LinkTemplates, InternalLinks, VariableFiles
- **Auto-Linking** (3): AutoLinkJobs, AutoLinkQueue, AutoLinkSchedules
- **Health Monitor** (4): LinkHealthChecks, HealthAlerts, HealthCheckJobs, HealthExclusions
- **Notifications** (5): NotificationQueue, NotificationRecipients, WebhookEndpoints, NotificationLog, NotificationSettings
- **Yoast SEO** (3): YoastSettings, YoastAuditLog, YoastOptimizationQueue
- **AI Providers** (4): AiProviders, AiProviderCredentials, AiModels, AiOAuthSessions

## Error Codes (14xxx range - 81 codes)
- 140xx: General/Plugin
- 141xx: Scan
- 142xx: Parse
- 143xx: Modification
- 144xx: History/Rollback
- 145xx: Snapshot
- 146xx: CSV Import
- 147xx: Cron
- 14800-14829: AI Provider
- 14830-14849: API
- 14850-14899: Health Monitor/Notifications
- 149xx: Internal Linking

## AI Provider Features
- **Seedable Configuration**: Default providers from config.json with version tracking
- **Multi-Provider Support**: OpenAI, Gemini, Anthropic, Mistral, Groq, Ollama, Custom
- **Authentication Types**: Bearer Token, OAuth 2.0 (Client/Code), Custom Headers
- **Customizable Models**: User-defined display names per model

## Logging Standards
All logs must include function name and file path. Error logs require full stack traces.
