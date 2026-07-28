<?php

declare(strict_types=1);

use App\Modules\Observability\Domain\Service\SecretRedactor;

it('redacts secret-bearing keys and leaves the rest', function () {
    $out = (new SecretRedactor())->redact([
        'id_token' => 'ya29.secret',
        'email' => 'a@b.com',
        'nested' => ['access_token' => 'x', 'name' => 'Denis'],
    ]);

    expect($out['id_token'])->toBe('[REDACTED]')
        ->and($out['email'])->toBe('a@b.com')
        ->and($out['nested']['access_token'])->toBe('[REDACTED]')
        ->and($out['nested']['name'])->toBe('Denis');
});

it('redacts auth-style header keys case-insensitively', function () {
    $out = (new SecretRedactor())->redact([
        'Authorization' => ['Bearer sk-live-123'],
        'X-Api-Key' => ['abc'],
        'Accept' => ['application/json'],
        'Cookie' => ['session=1'],
    ]);

    expect($out['Authorization'])->toBe('[REDACTED]')
        ->and($out['X-Api-Key'])->toBe('[REDACTED]')
        ->and($out['Cookie'])->toBe('[REDACTED]')
        ->and($out['Accept'])->toBe(['application/json']);
});

it('does not over-redact ordinary keys', function () {
    $out = (new SecretRedactor())->redact(['monkey' => 'ok', 'prompt' => 'иду в банк']);

    expect($out['monkey'])->toBe('ok')->and($out['prompt'])->toBe('иду в банк');
});
