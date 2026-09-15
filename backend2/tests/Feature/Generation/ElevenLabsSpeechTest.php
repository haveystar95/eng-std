<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Dto\SpeechLine;
use App\Modules\Generation\Application\Dto\SpokenLine;
use App\Modules\Generation\Application\Port\SpeechAccountError;
use App\Modules\Generation\Application\Port\TransientSpeechError;
use App\Modules\Generation\Infrastructure\Adapter\ElevenLabsSpeechSynthesizer;
use App\Modules\Observability\Application\Support\OutboundCallContext;
use App\Modules\Shared\Domain\ValueObject\LineVoice;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * THE REAL ELEVENLABS ADAPTER OVER A FAKED WIRE (TTS-2): what it asks for, how it reads the answer and its bill, what it
 * does with a refusal. `Http::fake()` underneath — nothing reaches the vendor.
 */

function elevenVendor(int $concurrency = 3): ElevenLabsSpeechSynthesizer
{
    return new ElevenLabsSpeechSynthesizer(
        context: app(OutboundCallContext::class),
        apiKey: 'test-key',
        usdPerThousandCredits: 0.20,
        concurrency: $concurrency,
        timeout: 30,
        baseUrl: 'https://api.elevenlabs.test',
        backoffMs: 0,
    );
}

function elevenVoice(string $id): LineVoice
{
    return new LineVoice('elevenlabs', 'eleven_v3_conversational', $id, 0.5);
}

// Canon (TTS-2, architect's clarification): «модель везде — v3 Conversational; каждая реплика собеседника и ученика —
// отдельный вызов своим голосом; mp3 44.1 kHz 128 kbps; stability Natural». Catches lines packed into one call, a line in
// another line's voice, a model other than the conversational one, and the vendor's mp3 re-encoded or cut.
it('says every line on a call of its own in its own voice: text to speech, eleven_v3_conversational, stability Natural, mp3 44.1 kHz 128 kbps', function () {
    $n = 0;
    Http::fake(['api.elevenlabs.test/*' => function () use (&$n) {
        $n++;

        return Http::response('ID3-line-'.$n, 200, ['character-cost' => (string) (2 + $n), 'request-id' => 'req-'.$n, 'content-type' => 'audio/mpeg']);
    }]);
    $lines = [
        new SpeechLine('Where does it hurt?', elevenVoice('partner-woman')),
        new SpeechLine('It hurts in his lower back.', elevenVoice('learner-man')),
        new SpeechLine('Did it start today?', elevenVoice('partner-woman')),
        new SpeechLine('It started three days ago.', elevenVoice('learner-man')),
        new SpeechLine('sharp', elevenVoice('learner-man')),
    ];
    $spoken = [];

    elevenVendor()->speakLines($lines, function (int $index, SpokenLine $line) use (&$spoken): void {
        $spoken[$index] = $line;
    });

    Http::assertSentCount(5);
    foreach ($lines as $line) {
        Http::assertSent(static fn (Request $r): bool => $r->url() === 'https://api.elevenlabs.test/v1/text-to-speech/'.$line->voice->voice.'?output_format=mp3_44100_128'
            && $r['text'] === $line->text
            && $r['model_id'] === 'eleven_v3_conversational'
            && $r['voice_settings'] === ['stability' => 0.5]
            && $r->header('xi-api-key') === ['test-key']);
    }
    Http::assertNotSent(static fn (Request $r): bool => str_contains($r->url(), 'dialogue'));
    ksort($spoken);
    expect(array_keys($spoken))->toBe([0, 1, 2, 3, 4])
        ->and(array_map(static fn (SpokenLine $l): string => substr($l->bytes, 0, 8), $spoken))->each->toBe('ID3-line')
        ->and(array_unique(array_map(static fn (SpokenLine $l): ?string => $l->requestId, $spoken)))->toHaveCount(5);
});

// Canon (TTS-2): «в учёт — точный id модели и фактическая цена из заголовка ответа». Live 15.09 on Starter: 31
// characters on v3 Conversational — `character-cost: 8`. Catches a price estimated from the text instead of the
// vendor's charge, and credits taken for characters.
it('reads the credits off the vendor’s character-cost and prices them at the account’s credit price, the characters beside', function () {
    Http::fake(['api.elevenlabs.test/*' => Http::response('ID3-ok', 200, ['character-cost' => '8', 'request-id' => 'req-live'])]);
    $spoken = [];

    elevenVendor()->speakLines([new SpeechLine('Hello, how are you doing today?', elevenVoice('v'))], function (int $i, SpokenLine $l) use (&$spoken): void {
        $spoken[] = $l;
    });

    expect($spoken[0]->credits)->toBe(8)
        ->and($spoken[0]->characters)->toBe(31)
        ->and($spoken[0]->costUsd)->toBe('0.001600')
        ->and($spoken[0]->requestId)->toBe('req-live');
});

// Canon (TTS-2): «429 / лимит одновременности → повтор с задержкой, очередь не падает». Live 15.09: the 429 of the
// concurrency limit comes without Retry-After.
it('retries the concurrency limit and buys the line once; after the short retries it asks the queue to wait', function () {
    Http::fake(['api.elevenlabs.test/*' => Http::sequence()
        ->push(['detail' => ['type' => 'rate_limit_error', 'code' => 'concurrent_limit_exceeded', 'status' => 'too_many_concurrent_requests']], 429)
        ->push('ID3-ok', 200, ['character-cost' => '2'])]);
    $spoken = [];

    elevenVendor()->speakLines([new SpeechLine('dizzy', elevenVoice('learner-man'))], function (int $i, SpokenLine $l) use (&$spoken): void {
        $spoken[] = $l;
    });

    expect($spoken)->toHaveCount(1)->and($spoken[0]->credits)->toBe(2);
    Http::assertSentCount(2);

    Http::fake(['api.elevenlabs.test/*' => Http::response(['detail' => ['code' => 'concurrent_limit_exceeded']], 429)]);
    expect(fn () => elevenVendor()->speakLines([new SpeechLine('dizzy', elevenVoice('learner-man'))], static fn () => null))
        ->toThrow(TransientSpeechError::class);
});

// Canon (TTS-2): «401/402 (нет баланса) → job в failed с кодом». Live 15.09: a library voice on the Free plan is 402
// `paid_plan_required`, and a voice of a higher tier is a 400 `free_users_not_allowed`.
it('stops on a refusal of the account with the vendor’s code — whatever status it came with — keeping the lines bought before it', function (int $status, array $detail, string $code) {
    Http::fake(['api.elevenlabs.test/*' => Http::sequence()
        ->push('ID3-a', 200, ['character-cost' => '1'])
        ->push('ID3-b', 200, ['character-cost' => '1'])
        ->push(['detail' => $detail], $status)]);
    $kept = [];
    $lines = array_map(static fn (string $t): SpeechLine => new SpeechLine($t, elevenVoice('v')), ['one', 'two', 'three', 'four']);

    try {
        elevenVendor(concurrency: 2)->speakLines($lines, function (int $i) use (&$kept): void {
            $kept[] = $i;
        });
        $this->fail('the account refusal did not stop the lines');
    } catch (SpeechAccountError $e) {
        expect($e->vendorCode)->toBe($code)->and($e->httpStatus)->toBe($status);
    }

    expect($kept)->toBe([0, 1]);
})->with([
    'no library voice on Free' => [402, ['type' => 'payment_required', 'code' => 'paid_plan_required', 'status' => 'payment_required', 'message' => 'Free users cannot use library voices via the API.'], 'paid_plan_required'],
    'a voice of a higher tier' => [400, ['type' => 'invalid_request', 'code' => 'bad_request', 'status' => 'free_users_not_allowed', 'message' => 'You need to be on the creator tier or above to use this voice.'], 'free_users_not_allowed'],
    'no credits left' => [401, ['code' => 'quota_exceeded', 'status' => 'quota_exceeded'], 'quota_exceeded'],
]);

it('skips a line whose text the vendor refuses and says the rest', function () {
    Http::fake(['api.elevenlabs.test/*' => Http::sequence()
        ->push(['detail' => [['loc' => ['body', 'text'], 'msg' => 'bad', 'type' => 'value_error']]], 422)
        ->push('ID3-b', 200, ['character-cost' => '1'])]);
    $kept = [];

    elevenVendor(concurrency: 1)->speakLines(
        [new SpeechLine('???', elevenVoice('v')), new SpeechLine('two', elevenVoice('v'))],
        function (int $i) use (&$kept): void {
            $kept[] = $i;
        },
    );

    expect($kept)->toBe([1]);
});

it('reads the account’s balance as the vendor counts it, and nothing when the key may not read it', function () {
    Http::fake(['api.elevenlabs.test/v1/user/subscription' => Http::sequence()
        ->push(['tier' => 'starter', 'character_count' => 776, 'character_limit' => 39224, 'next_character_count_reset_unix' => 1792059247])
        ->push(['detail' => ['code' => 'unauthorized', 'status' => 'missing_permissions']], 401)]);

    $balance = elevenVendor()->balance();

    expect($balance?->used)->toBe(776)->and($balance?->limit)->toBe(39224)->and($balance?->resetsAtUnix)->toBe(1792059247)
        ->and(elevenVendor()->balance())->toBeNull();
});
