# DevGuard Laravel Guard

Official Laravel package for **DevGuard** anti-theft protection, domain allowlisting, invoice milestone autolock enforcement, and SEO-safe HTTP 503 lockouts.

---

## Features
- **Auto-Discovery**: Automatically registers `DevGuardServiceProvider` and `devguard` middleware alias.
- **Fail-Safe Open Policy**: Network timeouts, DNS issues, or unreachable APIs will **never** bring down your production application.
- **Milestone Autolock**: Automatically syncs due-dates and delinquency states with your DevGuard dashboard.
- **Domain Authorization**: Enforces authorized hosting domains with support for wildcard subdomains (`*.example.com`).
- **SEO Preservation**: Returns `503 Service Unavailable` with `Retry-After: 3600` so Google and search engines never de-index your site during temporary disputes.
- **Artisan Commands**: Includes `php artisan devguard:status` and `php artisan devguard:lock`.

---

## Installation

Install via Composer:
```bash
composer require devguard/devguard-laravel
```

Publish the configuration file (optional):
```bash
php artisan vendor:publish --tag="devguard-config"
```

---

## Configuration

Add the following environment variables to your `.env` file:
```dotenv
DEVGUARD_ENABLED=true
DEVGUARD_PROJECT_ID=your_project_id_here
DEVGUARD_API_URL=https://api.devguard.uk
DEVGUARD_API_KEY=your_optional_api_key
DEVGUARD_ALLOWED_DOMAINS=example.com,*.example.com
DEVGUARD_FAIL_SAFE=open
DEVGUARD_CACHE_TTL=300
```

---

## Middleware Usage

### Laravel 11 (`bootstrap/app.php`):
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->append(\DevGuard\Laravel\Http\Middleware\DevGuardMiddleware::class);
})
```

### Laravel 9 & 10 (`app/Http/Kernel.php`):
Add to your global or web middleware stack:
```php
protected $middlewareGroups = [
    'web' => [
        // ...
        \DevGuard\Laravel\Http\Middleware\DevGuardMiddleware::class,
    ],
];
```

Or apply to specific route groups:
```php
Route::middleware(['devguard'])->group(function () {
    Route::get('/', [HomeController::class, 'index']);
});
```

---

## Artisan Commands

### Check Project & Lock Status
```bash
php artisan devguard:status
```

### Emergency Local Lock
```bash
php artisan devguard:lock --toggle
php artisan devguard:lock --unlock
```
