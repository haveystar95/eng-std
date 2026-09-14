<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Dto\SpeechScript;
use App\Modules\Generation\Application\Dto\SpeechTurn;
use App\Modules\Generation\Application\Port\SpeechEncoder;
use App\Modules\Generation\Application\Port\SpeechNotCut;
use App\Modules\Generation\Application\Port\TransientSpeechError;
use App\Modules\Generation\Infrastructure\Adapter\GeminiSpeechSynthesizer;
use App\Modules\Observability\Application\Support\OutboundCallContext;
use App\Modules\Shared\Domain\ValueObject\LineVoice;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * GEMINI TTS, A SCRIPT PER CALL (DAY-UI-3) — over a faked vendor: the dialogue of a day goes out as ONE
 * request with two speakers and comes back as one sound, cut into its lines; a refusal names its window.
 */

/** PCM of lines as «syllables» of a tone with a 520 ms pause after each line; 16-bit LE mono 24 kHz. */
function geminiSound(array $lines): string
{
    $rate = 24000;
    $pcm = '';
    foreach ($lines as $i => $line) {
        if ($i > 0) {
            $pcm .= str_repeat("\x00\x00", intdiv($rate * 520, 1000));
        }
        for ($k = 0, $n = max(2, (int) ceil(mb_strlen($line) / 3)); $k < $n; $k++) {
            for ($s = 0; $s < intdiv($rate * 90, 1000); $s++) {
                $pcm .= pack('v', (int) round(9000 * sin(2 * M_PI * 220 * $s / $rate)) & 0xFFFF);
            }
            $pcm .= str_repeat("\x00\x00", intdiv($rate * 40, 1000));
        }
    }

    return $pcm;
}

function geminiVendor(): GeminiSpeechSynthesizer
{
    $encoder = new class implements SpeechEncoder
    {
        public function pcmToMp3(string $pcm, int $sampleRate): ?string
        {
            return 'MP3:'.strlen($pcm);
        }
    };

    return new GeminiSpeechSynthesizer(app(OutboundCallContext::class), 'test-key', $encoder, baseUrl: 'https://gemini.test/v1beta');
}

function geminiDialogue(): SpeechScript
{
    $female = new LineVoice('gemini', 'gemini-2.5-flash-preview-tts', 'Aoede', 0.9);
    $male = new LineVoice('gemini', 'gemini-2.5-flash-preview-tts', 'Puck', 0.9);

    return new SpeechScript('en', [
        new SpeechTurn('female', 'Where exactly does it hurt: the upper back or the lower back?'),
        new SpeechTurn('male', 'It hurts in his lower back.'),
        new SpeechTurn('female', 'Did it start today, or earlier this week?'),
        new SpeechTurn('male', 'It started three days ago.'),
    ], ['female' => $female, 'male' => $male]);
}

// Canon (DAY-UI-3): «диалог дня — ОДНИМ вызовом Gemini TTS с двумя говорящими, затем режется на реплики».
// Catches a request per line, and a two-voice script sent as a one-voice request.
it('speaks a dialogue in one request with both voices and cuts the answer into its lines', function () {
    $script = geminiDialogue();
    Http::fake(['gemini.test/*' => Http::response(['candidates' => [['content' => ['parts' => [['inlineData' => [
        'mimeType' => 'audio/L16;codec=pcm;rate=24000',
        'data' => base64_encode(geminiSound(array_map(static fn (SpeechTurn $t): string => $t->text, $script->turns))),
    ]]]]]]])]);

    $lines = geminiVendor()->speakScript($script);

    Http::assertSentCount(1);
    Http::assertSent(static function (Request $request): bool {
        $configs = $request['generationConfig']['speechConfig']['multiSpeakerVoiceConfig']['speakerVoiceConfigs'] ?? [];

        return str_contains($request->url(), '/models/gemini-2.5-flash-preview-tts:generateContent')
            && array_column(array_column($configs, 'voiceConfig'), 'prebuiltVoiceConfig') === [['voiceName' => 'Aoede'], ['voiceName' => 'Puck']]
            && str_contains((string) $request['contents'][0]['parts'][0]['text'], "Alex: Where exactly does it hurt")
            && str_contains((string) $request['contents'][0]['parts'][0]['text'], 'Sam: It hurts in his lower back.');
    });
    expect($lines)->toHaveCount(4)
        ->and($lines[0]->format)->toBe('mp3')
        ->and($lines[0]->durationMs)->toBeGreaterThan($lines[1]->durationMs);
});

function geminiWords(): SpeechScript
{
    $male = new LineVoice('gemini', 'gemini-2.5-flash-preview-tts', 'Puck', 0.9);

    return new SpeechScript('en', array_map(static fn (string $w): SpeechTurn => new SpeechTurn('male', $w), ['leg', 'worse', 'numbness', 'muscle strain']), ['male' => $male]);
}

function geminiAudio(array $lines): array
{
    return ['candidates' => [['content' => ['parts' => [['inlineData' => [
        'mimeType' => 'audio/L16;codec=pcm;rate=24000',
        'data' => base64_encode(geminiSound($lines)),
    ]]]]]]];
}

// Live 14.09: six phrases sent as a one-voice list came back as 2.2 s — one phrase. Catches a batch sent in the form
// the vendor cuts short, and a second attempt that repeats the form that just failed.
it('reads a batch as turns of two readers in the batch’s one voice, and asks the plain list once when turns are refused', function () {
    $words = ['leg', 'worse', 'numbness', 'muscle strain'];
    Http::fake(['gemini.test/*' => Http::sequence()
        ->push(['error' => ['code' => 400, 'message' => 'speakers must differ']], 400)
        ->push(geminiAudio($words))]);

    $lines = geminiVendor()->speakScript(geminiWords());

    expect($lines)->toHaveCount(4);
    $sent = Http::recorded()->map(static fn (array $pair): Request => $pair[0])->values();
    expect($sent)->toHaveCount(2);
    $turns = $sent[0]['generationConfig']['speechConfig']['multiSpeakerVoiceConfig']['speakerVoiceConfigs'] ?? [];
    expect(array_column($turns, 'speaker'))->toBe(['Alex', 'Sam'])
        ->and(array_column(array_column($turns, 'voiceConfig'), 'prebuiltVoiceConfig'))->toBe([['voiceName' => 'Puck'], ['voiceName' => 'Puck']])
        ->and((string) $sent[0]['contents'][0]['parts'][0]['text'])->toContain("Alex: leg\nSam: worse\nAlex: numbness\nSam: muscle strain")
        ->and($sent[1]['generationConfig']['speechConfig']['voiceConfig']['prebuiltVoiceConfig']['voiceName'] ?? null)->toBe('Puck')
        ->and((string) $sent[1]['contents'][0]['parts'][0]['text'])->toContain("leg\nworse\nnumbness\nmuscle strain");
});

it('buys a batch the vendor reads as turns in one request', function () {
    Http::fake(['gemini.test/*' => Http::response(geminiAudio(['leg', 'worse', 'numbness', 'muscle strain']))]);

    expect(geminiVendor()->speakScript(geminiWords()))->toHaveCount(4);
    Http::assertSentCount(1);
    Http::assertSent(static fn (Request $r): bool => isset($r['generationConfig']['speechConfig']['multiSpeakerVoiceConfig']));
});

// Catches a sound that did not cut being stored under the wrong lines — and a second chance never taken.
it('asks once more when the sound does not cut, then gives up without a wrong cut', function () {
    Http::fake(['gemini.test/*' => Http::response(['candidates' => [['content' => ['parts' => [['inlineData' => [
        'mimeType' => 'audio/L16;codec=pcm;rate=24000',
        'data' => base64_encode(geminiSound(['one long run of speech with no pause between the lines at all'])),
    ]]]]]]])]);

    expect(fn () => geminiVendor()->speakScript(geminiDialogue()))->toThrow(SpeechNotCut::class);
    Http::assertSentCount(2);
});

// Canon (DAY-UI-3): «при 429 очередь ждёт до следующего окна»; owner 14.09: «время сброса суточной квоты — из ответа 429,
// не 00:00 UTC; RPD сбрасывается в полночь Pacific». Catches a daily refusal read as a minute's, and a daily wait that
// believes the answer's 00:00 UTC and knocks on a closed window for seven hours.
it('reads which window a refusal is — the day’s or the minute’s — and how long the vendor asked to wait', function () {
    $refusal = static fn (string $quota, string $delay): array => ['error' => ['code' => 429, 'status' => 'RESOURCE_EXHAUSTED', 'details' => [
        ['@type' => 'type.googleapis.com/google.rpc.QuotaFailure', 'violations' => [['quotaId' => $quota]]],
        ['@type' => 'type.googleapis.com/google.rpc.RetryInfo', 'retryDelay' => $delay],
    ]]];

    // Live 14.09, 17:23:45 UTC: the daily refusal named 23786 s — 00:00 UTC; the quota comes back at midnight Pacific.
    $this->travelTo(CarbonImmutable::parse('2026-09-14 17:23:45', 'UTC'));
    Http::fake(['gemini.test/*' => Http::sequence()
        ->push($refusal('GenerateRequestsPerDayPerProjectPerModel', '23786s'), 429)
        ->push($refusal('GenerateRequestsPerMinutePerProjectPerModel', '37.2s'), 429)
        ->push($refusal('GenerateRequestsPerDayPerProjectPerModel', '90000s'), 429)]);
    try {
        geminiVendor()->speakScript(geminiDialogue());
        $this->fail('no refusal');
    } catch (TransientSpeechError $e) {
        // 07:00 UTC (PDT midnight) is 13 h 36 min 15 s away — later than the answer's 00:00 UTC.
        expect($e->perDay)->toBeTrue()->and($e->retryAfterSeconds)->toBe(48975);
    }

    try {
        geminiVendor()->speakScript(geminiDialogue());
        $this->fail('no refusal');
    } catch (TransientSpeechError $e) {
        expect($e->perDay)->toBeFalse()->and($e->retryAfterSeconds)->toBe(38);
    }

    try {
        geminiVendor()->speakScript(geminiDialogue());
        $this->fail('no refusal');
    } catch (TransientSpeechError $e) {
        // An answer naming a LATER time than midnight Pacific is believed.
        expect($e->perDay)->toBeTrue()->and($e->retryAfterSeconds)->toBe(90001);
    }

    // Winter: midnight Pacific is 08:00 UTC.
    expect(GeminiSpeechSynthesizer::secondsToPacificMidnight(CarbonImmutable::parse('2026-12-01 18:00:00', 'UTC')))->toBe(14 * 3600);
});
