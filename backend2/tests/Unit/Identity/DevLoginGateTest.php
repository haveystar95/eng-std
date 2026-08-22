<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Service\DevLoginGate;

/**
 * The whole truth table of the dev door. Four rows, and only one of them opens it.
 *
 * A table test rather than three examples, because the rule's value is entirely in the row that
 * must NOT open — and a rule that is only tested where it says yes is a rule nobody has tested.
 */
it('opens only outside production and only with the flag on', function (string $env, bool $flag, bool $expected) {
    expect(DevLoginGate::isOpen($env, $flag))->toBe($expected);
})->with([
    'local, flag on — the QA bench'        => ['local', true, true],
    'local, flag off — nobody asked'       => ['local', false, false],
    'testing, flag on'                     => ['testing', true, true],
    'production, flag on — refused anyway' => ['production', true, false],
    'production, flag off'                 => ['production', false, false],
    'staging, flag off'                    => ['staging', false, false],
]);

it('names production as the environment it refuses', function () {
    expect(DevLoginGate::FORBIDDEN_ENVIRONMENT)->toBe('production');
});
