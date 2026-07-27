<?php

declare(strict_types=1);

use App\Modules\Shared\Domain\ValueObject\Ulid;

it('generates a valid 26-char ULID', function () {
    $ulid = Ulid::generate();

    expect(strlen($ulid))->toBe(26)
        ->and(Ulid::isValid($ulid))->toBeTrue();
});

it('rejects invalid ULIDs', function () {
    expect(Ulid::isValid('too-short'))->toBeFalse()
        ->and(Ulid::isValid('ILOU00000000000000000000000'))->toBeFalse(); // I,L,O,U not in Crockford
});

it('is time-sortable — later ULIDs sort after earlier ones', function () {
    $first = Ulid::generate();
    usleep(2000);
    $second = Ulid::generate();

    expect(strcmp($second, $first))->toBeGreaterThan(0);
});
