<?php

namespace DevGuard\Laravel\Commands;

use DevGuard\WebGuard;
use Illuminate\Console\Command;

/**
 * Artisan command to check or toggle emergency local lock status.
 *
 * Usage: php artisan devguard:lock [--toggle] [--unlock]
 */
class DevGuardLockCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'devguard:lock
                            {--toggle : Toggle emergency lock state}
                            {--unlock : Clear lock state}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Query or toggle DevGuard emergency lock status';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $projectId = config('devguard.project_id', '');
        if (empty($projectId)) {
            $this->error('DEVGUARD_PROJECT_ID is not configured in .env');
            return 1;
        }

        $lockFilePath = storage_path('framework/cache/devguard_emergency.lock');

        if ($this->option('unlock')) {
            if (file_exists($lockFilePath)) {
                @unlink($lockFilePath);
                $this->info("Emergency lock file removed. Application unlocked.");
            } else {
                $this->info("Application is not in emergency lock state.");
            }
            return 0;
        }

        if ($this->option('toggle')) {
            if (file_exists($lockFilePath)) {
                @unlink($lockFilePath);
                $this->info("Emergency lock CLEARED.");
            } else {
                @file_put_contents($lockFilePath, json_encode([
                    'locked_at' => date('c'),
                    'reason' => 'Artisan manual emergency lock',
                ]));
                $this->warn("Emergency lock ACTIVATED.");
            }
            return 0;
        }

        if (file_exists($lockFilePath)) {
            $this->error("EMERGENCY LOCK ACTIVE: Found {$lockFilePath}");
            $this->line("Run `php artisan devguard:lock --unlock` to release.");
        } else {
            $this->info("Emergency local lock is not set.");
            $this->line("Querying remote API status...");
            try {
                $guard = app(WebGuard::class);
                $status = $guard->resolveStatus();
                $this->info("Remote Status: " . ($status['status'] ?? 'active'));
            } catch (\Throwable $e) {
                $this->warn("Unable to reach remote status: " . $e->getMessage());
            }
        }

        return 0;
    }
}
