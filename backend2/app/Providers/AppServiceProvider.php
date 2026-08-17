<?php

namespace App\Providers;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // One Postgres database per paratest process (`{database}_test_{token}`). Laravel's own
        // parallel-testing machinery (Illuminate\Testing\Concerns\TestDatabases, wired in by
        // RefreshDatabase) already creates that database and switches the connection to it — this
        // callback fires only once, right after a NEW one is created, and just needs to migrate it.
        // Without this, every process would share `wordtrainer_test` and one process's RefreshDatabase
        // truncation would blow up assertions mid-flight in another.
        ParallelTesting::setUpTestDatabase(function (): void {
            Artisan::call('migrate', ['--force' => true]);
        });

        // 2026-08-14: `php artisan migrate:fresh` was run inside the app container with no database
        // override and dropped the DEV database (`wordtrainer`) — the store catalogue, the enriched
        // content and the owner's collections went with it. The migration-rollback check that
        // induced it is a legitimate check; running it against a disposable database is the only
        // legitimate way to run it. So the destructive migration commands are now refused whenever
        // the resolved database is not disposable, instead of relying on whoever types the command
        // to remember the `-e DB_DATABASE=wordtrainer_test` prefix.
        //
        // Escape hatch for a deliberate reset: `DB_ALLOW_DESTRUCTIVE=true` in the environment.
        DB::prohibitDestructiveCommands($this->shouldProtectDatabase());
    }

    /**
     * Is the currently resolved database one that must not be dropped?
     *
     * Computed at boot, which is before `RefreshDatabase` switches the connection to the
     * per-process `_test_{token}` database — but the name it starts from (`wordtrainer_test`, from
     * `phpunit.xml`) is already disposable, so a test run is never prohibited.
     */
    private function shouldProtectDatabase(): bool
    {
        if ((bool) config('database.allow_destructive_commands') === true) {
            return false;
        }

        $connection = (string) config('database.default');

        return ! self::isDisposableDatabase((string) config("database.connections.{$connection}.database"));
    }

    /**
     * A database is disposable when its name marks it as a test database: `wordtrainer_test` (the
     * serial suite, from `phpunit.xml`) or `wordtrainer_test_test_7` (one paratest process).
     * Everything else — `wordtrainer` above all — holds data nobody re-creates by running a command.
     */
    public static function isDisposableDatabase(string $database): bool
    {
        return preg_match('/_test(_test_\d+)?$/', $database) === 1;
    }
}
