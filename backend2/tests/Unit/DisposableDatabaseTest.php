<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;

/**
 * The guard that stands between a migration-rollback check and the dev database. On 2026-08-14 a
 * bare `migrate:fresh` inside the app container dropped `wordtrainer`; the predicate below decides
 * whether the destructive migration commands are allowed to run at all.
 */
it('treats only test databases as disposable', function (string $database, bool $disposable) {
    expect(AppServiceProvider::isDisposableDatabase($database))->toBe($disposable);
})->with([
    // The dev database and anything shaped like it — the whole point of the guard.
    ['wordtrainer', false],
    ['wordtrainer_dev', false],
    ['postgres', false],
    ['/var/www/html/database/database.sqlite', false],
    // "test" has to be the suffix, not a substring somewhere in the middle.
    ['wordtrainer_test_content', false],
    ['testing', false],
    // The serial suite (phpunit.xml) and one paratest process.
    ['wordtrainer_test', true],
    ['wordtrainer_test_test_1', true],
    ['wordtrainer_test_test_10', true],
]);
