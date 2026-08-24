<?php

declare(strict_types=1);

/**
 * Does every OpenAPI file still PARSE as YAML?
 *
 * That is the whole question, and it is deliberately the whole question. The contract already has a
 * reader that cares about its meaning — the person writing the client against it — and a strict
 * schema validator here would fail on every legitimate thing this repo does that the spec has not
 * caught up with. What nothing was checking is far more basic: on 24.08 `openapi.yaml` had not
 * parsed as YAML for an unknown number of days, and the full gates went green through all of them.
 * Three inline descriptions with unquoted commas in them; a client generator would have died on
 * the first, and the commit hook did not notice because nothing ever opened the file.
 *
 * Twice now, which is the reason this exists rather than a rule about quoting commas.
 *
 * Run by `composer check` (and therefore by the commit hook) before the expensive gates, because it
 * costs milliseconds and catches the class of mistake that survives everything else.
 */

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

require __DIR__ . '/../vendor/autoload.php';

// The directory is an argument so the lint can be pointed at a fixture and proved to FAIL — a gate
// that cannot be shown to fail is not a gate. Defaults to the contract's own folder, which is how
// `composer check` calls it.
$dir = $argv[1] ?? __DIR__ . '/../openapi';
$files = glob($dir . '/*.yaml') ?: [];

if ($files === []) {
    fwrite(STDERR, "openapi: no *.yaml under {$dir} — the lint has nothing to read, which is itself wrong.\n");
    exit(1);
}

$failed = false;

foreach ($files as $file) {
    $name = basename($file);

    try {
        $parsed = Yaml::parseFile($file);
    } catch (ParseException $e) {
        // The message already carries the line and the snippet; repeating the file name is what
        // makes it actionable when two documents are linted in one run.
        fwrite(STDERR, "openapi: {$name} does not parse — {$e->getMessage()}\n");
        $failed = true;

        continue;
    }

    // The one structural check, and not a schema: a file that parses into a string or a list is
    // valid YAML and is not an OpenAPI document, and that mistake reads exactly like a passing lint.
    if (! is_array($parsed) || ! isset($parsed['openapi'])) {
        fwrite(STDERR, "openapi: {$name} parses, but has no top-level `openapi:` version key.\n");
        $failed = true;

        continue;
    }

    echo "openapi: {$name} ok (OpenAPI {$parsed['openapi']})\n";
}

exit($failed ? 1 : 0);
