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

it('redacts a credential-shaped VALUE under an innocent key', function () {
    // Gemini hands its minted ephemeral token back as `name` — an innocuous key holding a live
    // credential. Key-only matching let it through and it sat in the log table in clear.
    $out = (new SecretRedactor())->redact([
        'name' => 'auth_tokens/AbCd1234',
        'model' => 'gemini-3.1-flash-live-preview',
        'nested' => ['key' => 'sk-live-abcdef', 'note' => 'Bearer with me a moment'],
    ]);

    expect($out['name'])->toBe('[REDACTED]')
        ->and($out['model'])->toBe('gemini-3.1-flash-live-preview')
        ->and($out['nested']['key'])->toBe('[REDACTED]')
        // Anchored prefixes only — "Bearer with me…" starts with `bearer `, so this one IS caught;
        // what must not be caught is ordinary prose that merely CONTAINS the word.
        ->and($out['nested']['note'])->toBe('[REDACTED]');
});

it('leaves ordinary prose that merely mentions a credential word alone', function () {
    $out = (new SecretRedactor())->redact([
        'prompt' => 'a bearer of good news',
        'text' => 'ask-me about auth_tokens/ someday',
    ]);

    expect($out['prompt'])->toBe('a bearer of good news')
        ->and($out['text'])->toBe('ask-me about auth_tokens/ someday');
});
