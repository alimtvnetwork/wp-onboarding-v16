import type { CapturedError } from "@/stores/errorStore";

/**
 * Creates a realistic demo CapturedError that populates ALL sections
 * of the Global Error Modal — Backend, Frontend, and Delegated.
 * Used when no live Go backend is available.
 */
export function createDemoError(): CapturedError {
  const now = new Date().toISOString();

  return {
    id: `demo-${Date.now()}`,
    code: "E6001",
    level: "error",
    message: "Delegated plugin activation failed on remote WordPress site",
    details: "POST /wp-json/riseup-asia-api/v1/plugin/activate returned HTTP 500 — PHP Fatal Error: Class 'RiseupAsia\\Uploader\\Core' not found in /var/www/html/wp-content/plugins/riseup-asia-uploader/riseup-asia-uploader.php on line 42",
    createdAt: now,
    endpoint: "/api/v1/sites/3/remote-plugins/activate",
    method: "POST",
    responseStatus: 502,
    requestBody: { plugin: "riseup-asia-uploader/riseup-asia-uploader.php" },
    siteUrl: "https://demo-site.example.com",
    sessionId: "demo-session-001",
    sessionType: "plugin-activate",

    // Frontend stack frames
    stackTrace: [
      "    at showGlobalError (src/App.tsx:61:26)",
      "    at Object.onError (src/App.tsx:110:13)",
      "    at requireSuccess (src/lib/api/methods.ts:45:11)",
      "    at activateRemotePlugin (src/lib/api/methods.ts:312:10)",
      "    at onClick (src/components/plugins/PluginCard.tsx:88:5)",
    ].join("\n"),
    parsedFrames: [
      { function: "showGlobalError", file: "src/App.tsx", line: 61, column: 26, isInternal: false },
      { function: "Object.onError", file: "src/App.tsx", line: 110, column: 13, isInternal: false },
      { function: "requireSuccess", file: "src/lib/api/methods.ts", line: 45, column: 11, isInternal: false },
      { function: "activateRemotePlugin", file: "src/lib/api/methods.ts", line: 312, column: 10, isInternal: false },
      { function: "onClick", file: "src/components/plugins/PluginCard.tsx", line: 88, column: 5, isInternal: false },
      { function: "callCallback", file: "node_modules/react-dom/cjs/react-dom.development.js", line: 4164, column: 14, isInternal: true },
    ],
    invocationChain: ["PluginCard.onClick", "api.activateRemotePlugin", "requireSuccess", "App.showGlobalError"],
    triggerComponent: "PluginCard",
    triggerAction: "activate_clicked",
    route: "/sites/3/plugins",
    routeComponent: "SitePluginsPage",

    // Backend stack trace (Go)
    backendStackTrace: [
      "goroutine 42 [running]:",
      "wp-plugin-publish/internal/services/site.(*Service).ActivateRemotePlugin(0xc0001a2000, {0x1a3f5e0, 0xc000234060}, 0x3, {0xc0003b6120, 0x2e})",
      "  /app/backend/internal/services/site/ServicePluginLifecycle.go:87 +0x2a4",
      "wp-plugin-publish/internal/api/handlers.HandleActivateRemotePlugin.func1({0x1a40860, 0xc000290000}, 0xc0002d8300)",
      "  /app/backend/internal/api/handlers/PluginLifecycleHandlers.go:34 +0x198",
      "net/http.HandlerFunc.ServeHTTP(0xc000164ea0, {0x1a40860, 0xc000290000}, 0xc0002d8300)",
      "  /usr/local/go/src/net/http/server.go:2166 +0x2f",
    ].join("\n"),

    // Backend execution logs
    backendLogs: [
      { timestamp: now, level: "info", message: "Starting remote plugin activation", step: "init", details: { source: "PluginLifecycle.Activate" } },
      { timestamp: now, level: "debug", message: "Resolved site credentials", step: "auth", details: { source: "PluginLifecycle.Activate" } },
      { timestamp: now, level: "info", message: "Delegating POST to /wp-json/riseup-asia-api/v1/plugin/activate", step: "delegate" },
      { timestamp: now, level: "error", message: "Remote site returned HTTP 500", step: "response", details: { source: "wordpress.DoApiCall" } },
    ],

    // PHP stack frames (parsed)
    phpStackFrames: [
      { file: "/var/www/html/wp-content/plugins/riseup-asia-uploader/riseup-asia-uploader.php", fileBase: "riseup-asia-uploader.php", line: 42, function: "activate", class: "RiseupAsia\\Plugin" },
      { file: "/var/www/html/wp-content/plugins/riseup-asia-uploader/includes/Core.php", fileBase: "Core.php", line: 118, function: "init", class: "RiseupAsia\\Uploader\\Core" },
      { file: "/var/www/html/wp-includes/class-wp-hook.php", fileBase: "class-wp-hook.php", line: 324, function: "apply_filters", class: "WP_Hook" },
      { file: "/var/www/html/wp-includes/plugin.php", fileBase: "plugin.php", line: 517, function: "do_action" },
    ],

    // Envelope errors (delegated)
    requestedAt: "/api/v1/sites/3/remote-plugins/activate",
    requestDelegatedAt: "/wp-json/riseup-asia-api/v1/plugin/activate",
    envelopeErrors: {
      BackendMessage: "Delegated plugin activation failed: remote server returned HTTP 500",
      Backend: [
        "wp-plugin-publish/internal/services/site.(*Service).ActivateRemotePlugin",
        "  ServicePluginLifecycle.go:87",
        "wp-plugin-publish/internal/wordpress.(*Client).DoApiCall",
        "  Client.go:142",
      ],
      DelegatedServiceErrorStack: [
        "PHP Fatal error: Class 'RiseupAsia\\Uploader\\Core' not found",
        "#0 /var/www/html/wp-content/plugins/riseup-asia-uploader/riseup-asia-uploader.php(42): RiseupAsia\\Plugin->activate()",
        "#1 /var/www/html/wp-content/plugins/riseup-asia-uploader/includes/Core.php(118): RiseupAsia\\Uploader\\Core->init()",
        "#2 /var/www/html/wp-includes/class-wp-hook.php(324): WP_Hook->apply_filters()",
        "#3 /var/www/html/wp-includes/plugin.php(517): do_action('activate_riseup...')",
        "#4 {main}",
      ],
      DelegatedRequestServer: {
        DelegatedEndpoint: "/wp-json/riseup-asia-api/v1/plugin/activate",
        Method: "POST",
        StatusCode: 500,
        RequestBody: { plugin: "riseup-asia-uploader/riseup-asia-uploader.php" },
        Response: {
          code: "internal_server_error",
          message: "PHP Fatal error: Class 'RiseupAsia\\Uploader\\Core' not found",
          data: {
            status: 500,
            error: {
              message: "Class 'RiseupAsia\\Uploader\\Core' not found in /var/www/html/wp-content/plugins/riseup-asia-uploader/riseup-asia-uploader.php on line 42",
              file: "/var/www/html/wp-content/plugins/riseup-asia-uploader/riseup-asia-uploader.php",
              line: 42,
            },
          },
        },
        StackTrace: [
          "#0 /var/www/html/wp-content/plugins/riseup-asia-uploader/riseup-asia-uploader.php(42): RiseupAsia\\Plugin->activate()",
          "#1 /var/www/html/wp-content/plugins/riseup-asia-uploader/includes/Core.php(118): RiseupAsia\\Uploader\\Core->init()",
          "#2 /var/www/html/wp-includes/class-wp-hook.php(324): WP_Hook->apply_filters()",
          "#3 /var/www/html/wp-includes/plugin.php(517): do_action('activate_riseup...')",
        ],
        AdditionalMessages: "The remote plugin may have a missing dependency or incompatible PHP version. Check the site's PHP error log for details.",
      },
      RemoteResponseBody: JSON.stringify({
        code: "internal_server_error",
        message: "PHP Fatal error: Class 'RiseupAsia\\Uploader\\Core' not found",
        data: { status: 500 },
      }),
    },
    envelopeMethodsStack: {
      Backend: [
        { Method: "ActivateRemotePlugin", File: "ServicePluginLifecycle.go", LineNumber: 87 },
        { Method: "DoApiCall", File: "Client.go", LineNumber: 142 },
        { Method: "respondErrorWithDelegated", File: "Response.go", LineNumber: 52 },
      ],
      Frontend: [],
    },

    // UI context
    uiClickPath: [
      { timestamp: Date.now() - 3000, target: "Sites", selector: "nav a[href='/sites']", type: "click" as const },
      { timestamp: Date.now() - 2000, target: "demo-site.example.com", selector: "tr[data-site-id='3']", type: "click" as const },
      { timestamp: Date.now() - 500, target: "Activate", selector: "button.activate-plugin", type: "click" as const },
    ],
    uiClickPathString: "Sites → demo-site.example.com → Activate",
    uiClickPathArrow: "nav > Sites → row > demo-site → btn > Activate",

    context: {
      source: "App.showGlobalError",
      triggerComponent: "PluginCard",
      triggerAction: "activate_clicked",
      remoteResponseBody: JSON.stringify({
        code: "internal_server_error",
        message: "PHP Fatal error: Class 'RiseupAsia\\Uploader\\Core' not found",
        data: { status: 500 },
      }),
    } as CapturedError["context"],
  };
}

/**
 * Creates a second demo error (backend-only, no delegated) for queue testing.
 */
export function createDemoBackendError(): CapturedError {
  const now = new Date().toISOString();

  return {
    id: `demo-backend-${Date.now()}`,
    code: "E3003",
    level: "error",
    message: "Database connection lost during site query",
    details: "dial tcp 127.0.0.1:5432: connect: connection refused",
    createdAt: now,
    endpoint: "/api/v1/sites",
    method: "GET",
    responseStatus: 500,
    route: "/dashboard",
    routeComponent: "Dashboard",

    backendStackTrace: [
      "goroutine 12 [running]:",
      "wp-plugin-publish/internal/services/site.(*Service).GetAllSites(0xc0001a2000, {0x1a3f5e0, 0xc000234060})",
      "  /app/backend/internal/services/site/ServiceSite.go:42 +0x1a8",
    ].join("\n"),

    parsedFrames: [
      { function: "showGlobalError", file: "src/App.tsx", line: 61, column: 26, isInternal: false },
      { function: "fetchSites", file: "src/hooks/useSites.ts", line: 22, column: 8, isInternal: false },
    ],

    envelopeErrors: {
      BackendMessage: "Database connection lost",
      Backend: [
        "wp-plugin-publish/internal/services/site.(*Service).GetAllSites",
        "  ServiceSite.go:42",
        "wp-plugin-publish/internal/db.(*DB).Query",
        "  DB.go:88",
      ],
    },
    envelopeMethodsStack: {
      Backend: [
        { Method: "GetAllSites", File: "ServiceSite.go", LineNumber: 42 },
        { Method: "Query", File: "DB.go", LineNumber: 88 },
      ],
      Frontend: [],
    },
    requestedAt: "/api/v1/sites",
  };
}
