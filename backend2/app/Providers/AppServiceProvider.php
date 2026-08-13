<?php

namespace App\Providers;

use Illuminate\Support\Facades\Artisan;
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
    }
}
