<?php

declare(strict_types=1);

/*
 * THE PARTNER VOICES OF TODAY IN THE SIX NEW TARGET LANGUAGES — SAMPLES FOR DEN'S EAR (наряд LANG-1, п. 3).
 *
 *   docker exec -w /wt wt_lang1 php docs/research/lang-1/tools/voice-samples.php account
 *   docker exec -w /wt wt_lang1 php docs/research/lang-1/tools/voice-samples.php samples
 *
 * `account` is free: what the vendor account has used of its limit — read before and after the purchase.
 * `samples` BUYS one partner line per language (pl, ro, es, it, de, fr) in each of the two partner voices the pack speaks
 * with today (`generation.speech.voices.en.partner.female` / `.male` — 4Nej…, Enjk…): 12 lines, the same model and
 * stability as the app. The line is the FIX-4c sample line («Good morning. What brings you in today? Take a seat,
 * please.») said in each language, so Den compares one sentence across languages and against the English sample he
 * already heard. The body carries the vendor's `language_code` (ISO 639-1) — the order's item 9 sends it on every line,
 * and this purchase is also the probe that the model takes it: a refusal is recorded and the line is bought once more
 * WITHOUT it, so a sample exists either way and the refusal is a fact in `samples.json`, not a guess.
 * Every mp3 lands in `docs/research/lang-1/voices/`; `voices/samples.json` keeps what each line cost as the vendor counted
 * it (`character-cost` is CREDITS, not characters — TTS-2). The key is read from `ELEVENLABS_API_KEY`; nothing secret is
 * printed. Cap: 12 lines, ≤ 90 characters each, ≤ 400 credits — the run refuses to start above it.
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Http;

require __DIR__.'/../../../../vendor/autoload.php';
$app = require __DIR__.'/../../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$key = (string) env('ELEVENLABS_API_KEY', '');
if ($key === '') {
    fwrite(STDERR, "ELEVENLABS_API_KEY is empty\n");
    exit(1);
}
$base = 'https://api.elevenlabs.io';
$client = static fn () => Http::withHeaders(['xi-api-key' => $key])->timeout(120);

/** The FIX-4c line, said by a receptionist or a doctor in each language (polite form, no gendered address). */
const LINES = [
    'pl' => 'Dzień dobry. W czym mogę dziś pomóc? Proszę usiąść.',
    'ro' => 'Bună dimineața. Ce vă aduce azi la noi? Luați loc, vă rog.',
    'es' => 'Buenos días. ¿Qué le trae por aquí hoy? Siéntese, por favor.',
    'it' => 'Buongiorno. Cosa la porta qui oggi? Si accomodi, prego.',
    'de' => 'Guten Morgen. Was führt Sie heute zu uns? Nehmen Sie bitte Platz.',
    'fr' => 'Bonjour. Qu\'est-ce qui vous amène aujourd\'hui ? Asseyez-vous, je vous en prie.',
];

$account = static function () use ($client, $base): array {
    $r = $client()->get("{$base}/v1/user/subscription");
    $j = $r->json();

    return ['status' => $r->status()] + (is_array($j) ? array_intersect_key($j, array_flip(['tier', 'character_count', 'character_limit'])) : []);
};

switch ($argv[1] ?? 'account') {
    case 'account':
        echo json_encode($account(), JSON_UNESCAPED_UNICODE), "\n";
        break;

    case 'samples':
        $voices = [];
        foreach (['female', 'male'] as $gender) {
            $row = config("generation.speech.voices.en.partner.{$gender}");
            if (! is_array($row) || ! is_string($row['voice'] ?? null)) {
                fwrite(STDERR, "no partner {$gender} voice in generation.speech.voices.en\n");
                exit(1);
            }
            $voices[$gender] = $row;
        }
        $lines = count(LINES) * count($voices);
        $chars = array_sum(array_map('mb_strlen', LINES)) * count($voices);
        if ($lines > 12 || max(array_map('mb_strlen', LINES)) > 90 || (int) ceil($chars / 4) > 400) {
            fwrite(STDERR, "over the order's cap\n");
            exit(1);
        }
        $dir = base_path('docs/research/lang-1/voices');
        @mkdir($dir, 0777, true);

        $before = $account();
        $rows = [];
        foreach (LINES as $lang => $line) {
            foreach ($voices as $gender => $voice) {
                $body = [
                    'text' => $line,
                    'model_id' => (string) $voice['model'],
                    'voice_settings' => ['stability' => (float) $voice['stability']],
                    'language_code' => $lang,
                ];
                $r = $client()->post("{$base}/v1/text-to-speech/{$voice['voice']}?output_format=mp3_44100_128", $body);
                $refused = null;
                if (! $r->successful() && $r->status() >= 400 && $r->status() < 500) {
                    $refused = ['status' => $r->status(), 'error' => mb_substr($r->body(), 0, 300)];
                    unset($body['language_code']);
                    $r = $client()->post("{$base}/v1/text-to-speech/{$voice['voice']}?output_format=mp3_44100_128", $body);
                }
                $file = "{$lang}-partner-{$gender}.mp3";
                $row = [
                    'lang' => $lang,
                    'gender' => $gender,
                    'voice' => $voice['voice'],
                    'line' => $line,
                    'language_code_sent' => $refused === null,
                    'language_code_refusal' => $refused,
                    'status' => $r->status(),
                    'file' => $r->successful() ? $file : null,
                    'characters' => mb_strlen($line),
                    'credits' => (int) ($r->header('character-cost') ?: 0),
                    'request_id' => $r->header('request-id') ?: null,
                    'error' => $r->successful() ? null : mb_substr($r->body(), 0, 300),
                ];
                if ($r->successful()) {
                    file_put_contents("{$dir}/{$file}", $r->body());
                }
                $rows[] = $row;
                echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
            }
        }
        $after = $account();
        $credits = array_sum(array_column($rows, 'credits'));
        $summary = [
            'model' => (string) $voices['female']['model'],
            'stability' => (float) $voices['female']['stability'],
            'account_before' => $before,
            'account_after' => $after,
            'characters' => array_sum(array_column($rows, 'characters')),
            'credits' => $credits,
            'usd' => round($credits * 0.20 / 1000, 4),
            'language_code_accepted' => count(array_filter($rows, static fn (array $r): bool => $r['language_code_sent'])).' of '.count($rows),
            'samples' => $rows,
        ];
        file_put_contents("{$dir}/samples.json", json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
        echo json_encode(array_diff_key($summary, ['samples' => 1]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
        break;

    default:
        fwrite(STDERR, "account | samples\n");
        exit(1);
}
