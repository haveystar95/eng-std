<?php

declare(strict_types=1);

/**
 * EVERY FEATURE FILE ROLLS ITS WRITES BACK (наряд ACC-1 §4). A file without `uses(RefreshDatabase::class)` commits what
 * it writes to the test database for good, and a serial run hands it to every file after it. Caught in ACC-1: two
 * Generation files over a faked wire left 24 rows in `api_request_logs` and 2 in `model_calls`, and the journal's own
 * tests counted them — 7 red serially, green in parallel, where the rows landed in another worker's database.
 *
 * A file may go without the reset only when it writes nothing, checked by running it alone and counting every table of the
 * test database after it: the list below. A file added to it is a claim — check it the same way.
 */
it('gives every Feature file a database reset, except the ones checked to write nothing', function () {
    $writesNothing = [
        'DatabaseIsolationGuardTest.php',
        'ExampleTest.php',
        'Generation/AutoEnrichChainTest.php',
        'Generation/LiveModelGuardTest.php',
        'Generation/OneVoiceVendorTest.php',
        'Generation/RepairBeforeEnrichmentTest.php',
        'OpenApiLintTest.php',
        'Vocabulary/StoreSeedEmphasisTest.php',
    ];

    $withoutReset = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
            continue;
        }
        $source = (string) file_get_contents($file->getPathname());
        if (preg_match('/^uses\([^;]*\b(RefreshDatabase|DatabaseTransactions|DatabaseTruncation|LazilyRefreshDatabase)::class/m', $source) !== 1) {
            $withoutReset[] = substr($file->getPathname(), strlen(__DIR__) + 1);
        }
    }

    expect($withoutReset)->toEqualCanonicalizing($writesNothing);
});
