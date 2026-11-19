<?php

namespace DevGuard\Laravel\Commands;

use DevGuard\WebGuard;
use Illuminate\Console\Command;

/**
 * Artisan command to inspect DevGuard status and configuration.
 *
 * Usage: php artisan devguard:status
 */
class DevGuardStatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'devguard:status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display current DevGuard lock status, allowed domains, and project details';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $this->info('=============================================');
        $this->info('       DevGuard Protection Status (Laravel)  ');
        $this->info('=============================================');

        $enabled = config('devguard.enabled', true);
        $projectId = config('devguard.project_id', '');
        $apiUrl = config('devguard.api_url', 'https://api.devguard.uk');
        $allowedDomains = config('devguard.allowed_domains');
        $failSafe = config('devguard.fail_safe', 'open');
        $cacheTtl = config('devguard.cache_ttl', 300);

        $this->table(
            ['Setting', 'Value'],
            [
                ['Guard Enabled', $enabled ? '<info>ENABLED</info>' : '<comment>DISABLED</comment>'],
                ['Project ID', $projectId ?: '<error>NOT SET</error>'],
                ['API Endpoint', $apiUrl],
                ['Allowed Domains', is_array($allowedDomains) ? implode(', ', $allowedDomains) : ($allowedDomains ?: '<comment>From Dashboard</comment>')],
                ['Fail-Safe Policy', strtolower($failSafe) === 'open' ? '<info>OPEN (Safe)</info>' : '<comment>CLOSED (Strict)</comment>'],
                ['Cache TTL', "{$cacheTtl}s"],
            ]
        );

        if (!$enabled) {
            $this->warn('DevGuard middleware is currently DISABLED in configuration.');
            return 0;
        }

        if (empty($projectId)) {
            $this->error('Missing DEVGUARD_PROJECT_ID. Please set in your .env file.');
            return 1;
        }

        $this->line('');
        $this->info('Checking remote project status from DevGuard API...');

        try {
            /** @var WebGuard $guard */
            $guard = app(WebGuard::class);
            $status = $guard->resolveStatus();

            $statusText = strtoupper($status['status'] ?? 'UNKNOWN');
            if ($statusText === 'ACTIVE') {
                $this->info("Current Project Status: [ {$statusText} ]");
            } else {
                $this->error("Current Project Status: [ {$statusText} ]");
                if (!empty($status['message'])) {
                    $this->line("Reason: " . $status['message']);
                }
            }

            if (!empty($status['appName'])) {
                $this->line("Application Name: " . $status['appName']);
            }
            if (!empty($status['contactEmail'])) {
                $this->line("Contact Email:    " . $status['contactEmail']);
            }
        } catch (\Throwable $e) {
            $this->warn("Failed to query DevGuard API: " . $e->getMessage());
            if ($failSafe === 'open') {
                $this->info("Fail-safe OPEN active: Traffic continues uninterrupted.");
            }
        }

        return 0;
    }
}
