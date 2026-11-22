<?php

return [
    /*
    |--------------------------------------------------------------------------
    | DevGuard Middleware Enabled
    |--------------------------------------------------------------------------
    |
    | Master switch to enable or disable DevGuard protection. Setting this
    | to false bypasses all domain verification and autolock checks.
    |
    */
    'enabled' => env('DEVGUARD_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | DevGuard Project ID
    |--------------------------------------------------------------------------
    |
    | The unique identifier for your project in the DevGuard dashboard.
    | Required for remote status checks and milestone autolock synchronization.
    |
    */
    'project_id' => env('DEVGUARD_PROJECT_ID', ''),

    /*
    |--------------------------------------------------------------------------
    | DevGuard API Endpoint
    |--------------------------------------------------------------------------
    |
    | Base URL for DevGuard verification and autolock telemetry APIs.
    |
    */
    'api_url' => env('DEVGUARD_API_URL', 'https://api.devguard.uk'),

    /*
    |--------------------------------------------------------------------------
    | DevGuard Project SDK Key (Preferred)
    |--------------------------------------------------------------------------
    |
    | Per-project ingest key (dg_sdk_*). Prefer over Master Secret / API key.
    | Env: DEVGUARD_SDK_KEY. Falls back to api_key / DEVGUARD_API_KEY.
    |
    */
    'sdk_key' => env('DEVGUARD_SDK_KEY', null),

    /*
    |--------------------------------------------------------------------------
    | DevGuard API Key (Optional legacy fallback)
    |--------------------------------------------------------------------------
    |
    | Legacy API Key / Master Secret used when sdk_key is unset.
    |
    */
    'api_key' => env('DEVGUARD_API_KEY', null),

    /*
    |--------------------------------------------------------------------------
    | Allowed Domains (Allowlist)
    |--------------------------------------------------------------------------
    |
    | Comma-separated or array of authorized domains. Supports wildcards
    | (e.g. 'example.com', '*.example.com'). If null, falls back to project
    | configuration in the DevGuard remote dashboard.
    |
    */
    'allowed_domains' => env('DEVGUARD_ALLOWED_DOMAINS')
        ? array_map('trim', explode(',', env('DEVGUARD_ALLOWED_DOMAINS')))
        : null,

    /*
    |--------------------------------------------------------------------------
    | Fail-Safe Policy
    |--------------------------------------------------------------------------
    |
    | In the event of network timeouts, DNS errors, or API unreachability:
    | - 'open' (or true): Permits incoming traffic without disruption (Default).
    | - 'closed' (or false): Denies incoming traffic with a 503 error.
    |
    */
    'fail_safe' => env('DEVGUARD_FAIL_SAFE', 'open'),

    /*
    |--------------------------------------------------------------------------
    | Cache TTL (Seconds)
    |--------------------------------------------------------------------------
    |
    | Duration in seconds to cache remote project status locally in memory
    | or application cache. Default is 300 seconds (5 minutes).
    |
    */
    'cache_ttl' => (int) env('DEVGUARD_CACHE_TTL', 300),

    /*
    |--------------------------------------------------------------------------
    | Reverse Proxy Trust
    |--------------------------------------------------------------------------
    |
    | When true, trusts X-Forwarded-Host headers for domain matching.
    | Only enable if running behind a verified reverse proxy or Cloudflare.
    |
    */
    'trust_proxy' => (bool) env('DEVGUARD_TRUST_PROXY', false),

    /*
    |--------------------------------------------------------------------------
    | Exempt / Allowed Paths
    |--------------------------------------------------------------------------
    |
    | Path prefixes or exact paths that are unconditionally allowed through,
    | even when the application is locked (e.g. health checks, ACME certs).
    |
    */
    'allowed_paths' => [
        '/health',
        '/api/health',
        '/healthz',
        '/livez',
        '/readyz',
        '/favicon.ico',
        '/robots.txt',
        '/sitemap.xml',
        '/.well-known',
    ],
];
