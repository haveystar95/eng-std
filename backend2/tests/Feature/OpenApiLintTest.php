<?php

declare(strict_types=1);

/**
 * The gate that reads the contract (наряд A-4.1 Ч.2 п.3).
 *
 * `openapi.yaml` sat un-parseable for days in August and the full gates went green through all of
 * them — nothing in `composer check` ever opened the file. On the day this lint was added it found
 * `openapi-admin.yaml` broken in THREE places, none of which any test, deptrac or PHPStan run had
 * ever had a reason to notice.
 *
 * These four cases exist because a lint nobody can see fail is indistinguishable from no lint. The
 * real contract is checked last, so this file also fails when the shipped documents break.
 */
function runLint(?string $fixture = null): int
{
    $script = base_path('scripts/lint-openapi.php');
    $arg = $fixture === null ? '' : ' ' . escapeshellarg(base_path("tests/Fixtures/openapi-lint/{$fixture}"));

    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . $arg . ' 2>&1', $output, $code);

    return $code;
}

it('passes on the contract this repo actually ships', function () {
    expect(runLint())->toBe(0);
});

it('fails on YAML that does not parse', function () {
    // The fixture is the exact mistake that shipped twice: an unquoted comma inside an inline
    // mapping, which ends the value early and malforms the node.
    expect(runLint('broken'))->toBe(1);
});

it('fails on a file that parses but is not an OpenAPI document', function () {
    // Valid YAML, wrong thing entirely — and the failure mode that reads most like a passing lint.
    expect(runLint('notadoc'))->toBe(1);
});

it('fails when there is nothing to lint at all', function () {
    // An empty folder means the glob or the path is wrong, and a silent pass would hide it.
    expect(runLint('empty'))->toBe(1);
});
