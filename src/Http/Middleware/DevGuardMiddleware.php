<?php

namespace DevGuard\Laravel\Http\Middleware;

use Closure;
use DevGuard\LockResponse;
use DevGuard\WebGuard;

/**
 * DevGuard HTTP Middleware for Laravel.
 *
 * Intercepts incoming HTTP requests, enforces domain allowlisting and remote
 * autolock status, and returns SEO-safe HTTP 503 responses when locked.
 */
class DevGuardMiddleware
{
    /**
     * @var WebGuard|null
     */
    protected ?WebGuard $guard;

    /**
     * Create a new middleware instance.
     *
     * @param WebGuard|null $guard
     */
    public function __construct(?WebGuard $guard = null)
    {
        $this->guard = $guard;
    }

    /**
     * Handle an incoming request.
     *
     * @param mixed $request \Illuminate\Http\Request
     * @param Closure $next
     * @return mixed \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    public function handle($request, Closure $next)
    {
        // 1. Check if DevGuard protection is enabled
        $isEnabled = function_exists('config') ? config('devguard.enabled', true) : true;
        if (!$isEnabled) {
            return $next($request);
        }

        // 2. Extract request URI path
        $path = '/';
        if (is_object($request) && method_exists($request, 'path')) {
            $path = '/' . ltrim((string) $request->path(), '/');
        } elseif (isset($_SERVER['REQUEST_URI'])) {
            $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
        }

        // 3. Check allowed / exempt paths (health checks, ACME certs, etc.)
        $allowedPaths = function_exists('config') ? (array) config('devguard.allowed_paths', []) : [];
        if (class_exists(LockResponse::class) && LockResponse::isPathAllowed($path, $allowedPaths)) {
            return $next($request);
        }

        // 4. Resolve host
        $host = null;
        if (is_object($request) && method_exists($request, 'getHost')) {
            $host = $request->getHost();
        } elseif (isset($_SERVER['HTTP_HOST'])) {
            $host = $_SERVER['HTTP_HOST'];
        }

        // 5. Evaluate guard status
        $guard = $this->guard;
        if ($guard === null && function_exists('app')) {
            try {
                $guard = app(WebGuard::class);
            } catch (\Throwable $e) {
                $guard = null;
            }
        }

        if ($guard === null) {
            $cfg = function_exists('config') ? config('devguard', []) : [];
            $failSafeVal = $cfg['fail_safe'] ?? 'open';
            $guard = new WebGuard([
                'projectId' => (string) ($cfg['project_id'] ?? ''),
                'apiUrl' => (string) ($cfg['api_url'] ?? 'https://api.devguard.uk'),
                'apiKey' => !empty($cfg['api_key']) ? (string) $cfg['api_key'] : null,
                'failSafe' => ($failSafeVal === false || $failSafeVal === 'closed') ? 'closed' : 'open',
                'allowedDomains' => $cfg['allowed_domains'] ?? null,
                'allowedPaths' => (array) ($cfg['allowed_paths'] ?? []),
                'cacheTtl' => (int) ($cfg['cache_ttl'] ?? 300),
                'trustProxy' => (bool) ($cfg['trust_proxy'] ?? false),
            ]);
        }

        $result = $guard->check($host, $path);

        // If authorized, proceed through middleware chain
        if (!empty($result['allowed'])) {
            return $next($request);
        }

        // 6. Request is locked - generate SEO-safe HTTP 503 response
        return $this->buildLockResponse($request, $result);
    }

    /**
     * Builds the HTTP 503 response in JSON or HTML format.
     *
     * @param mixed $request
     * @param array $result
     * @return mixed
     */
    protected function buildLockResponse($request, array $result)
    {
        $headers = [
            'Retry-After' => '3600',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0',
            'X-DevGuard-Status' => 'locked',
            'X-DevGuard-Reason' => $result['reason'] ?? 'project_locked',
        ];

        $wantsJson = false;
        if (is_object($request) && method_exists($request, 'wantsJson')) {
            $wantsJson = $request->wantsJson();
        } elseif (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
            $wantsJson = true;
        }

        // JSON response for API requests
        if ($wantsJson) {
            $payload = [
                'status' => 'locked',
                'code' => $result['code'] ?? 'DEVGUARD_PROJECT_LOCKED',
                'title' => $result['title'] ?? 'Service Temporarily Locked',
                'message' => $result['message'] ?? 'This service is temporarily unavailable.',
                'appName' => $result['appName'] ?? null,
                'contactEmail' => $result['contactEmail'] ?? null,
                'contactPhone' => $result['contactPhone'] ?? null,
                'contactWhatsapp' => $result['contactWhatsapp'] ?? null,
            ];

            if (function_exists('response')) {
                return response()->json($payload, 503, $headers);
            }

            http_response_code(503);
            foreach ($headers as $k => $v) {
                header("{$k}: {$v}");
            }
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            exit(0);
        }

        // HTML response for browser requests
        $html = class_exists(LockResponse::class)
            ? LockResponse::renderHtml($result)
            : $this->fallbackHtml($result);

        $headers['Content-Type'] = 'text/html; charset=UTF-8';

        if (function_exists('response')) {
            return response($html, 503, $headers);
        }

        http_response_code(503);
        foreach ($headers as $k => $v) {
            header("{$k}: {$v}");
        }
        echo $html;
        exit(0);
    }

    /**
     * Minimal fallback HTML if LockResponse class is unavailable.
     *
     * @param array $result
     * @return string
     */
    protected function fallbackHtml(array $result): string
    {
        $title = htmlspecialchars($result['title'] ?? 'Service Temporarily Unavailable', ENT_QUOTES, 'UTF-8');
        $message = htmlspecialchars($result['message'] ?? 'This application is currently locked.', ENT_QUOTES, 'UTF-8');
        return "<!DOCTYPE html><html><head><title>{$title}</title></head><body><h1>{$title}</h1><p>{$message}</p></body></html>";
    }
}
