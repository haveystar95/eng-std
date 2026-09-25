<?php

declare(strict_types=1);

/*
 * THE SECOND PARTNER VOICE — SAMPLES FOR DEN TO CHOOSE FROM (наряд FIX-4c §1).
 *
 *   docker exec wt_fix4c php docs/research/fix-4c/tools/voice-samples.php account
 *   docker exec wt_fix4c php docs/research/fix-4c/tools/voice-samples.php samples
 *
 * `account` is free: what the vendor account has used of its limit — read before and after the purchase.
 * `samples` BUYS one line per candidate (the наряд's cap: ≤ 8 candidates, ≤ 90 characters each, ≈ $0.05): the same model
 * and stability the language pack speaks with (`generation.speech.voices.en`), so a sample sounds the way the app would.
 * Every mp3 lands in `docs/research/fix-4c/voices/`, and `voices/samples.json` keeps what each line cost as the vendor
 * counted it (`character-cost` is CREDITS, not characters — TTS-2).
 *
 * The candidates are fixed below, not searched: the voice key has no `voices_read`, so the library cannot be listed with
 * it (401 `missing_permissions`). They are ElevenLabs' own replacements of the Default voices (which expire 31.12.2026,
 * so none of those) and one ConvoAI voice of the public library page; the ids were read off the vendor's own links.
 * The key is read from `ELEVENLABS_API_KEY`; nothing secret is printed.
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

const LINE = 'Good morning. What brings you in today? Take a seat, please.';

/** @var list<array{gender: string, id: string, name: string, source: string}> $candidates */
$candidates = [
    ['gender' => 'female', 'id' => 'OZ0L6eISlOejga3XjDFt', 'name' => 'Talia - Warm Soft Guide', 'source' => 'замена Default «Sarah»'],
    ['gender' => 'female', 'id' => 'QtY3JBOUKEB5xzrRfOKc', 'name' => 'Maisie - Friendly Casual Neighbor', 'source' => 'замена Default «Matilda»'],
    ['gender' => 'female', 'id' => 'g7LVvkPWALzPxOQbF6OE', 'name' => 'Jade - Upbeat and Natural', 'source' => 'замена Default «Jessica»'],
    ['gender' => 'female', 'id' => 'aMSt68OGf4xUZAnLpTU8', 'name' => 'Juniper - Grounded and Professional', 'source' => 'библиотека, страница «conversational» (ConvoAI)'],
    ['gender' => 'male', 'id' => 'l7kNoIfnJKPg7779LI2t', 'name' => 'Eddie - Helpful and Comforting', 'source' => 'замена Default «Eric»'],
    ['gender' => 'male', 'id' => 'cymHWdiF8WjUCg6vvFxx', 'name' => 'Kellan - Casual Friendly Speaker', 'source' => 'замена Default «Callum»'],
    ['gender' => 'male', 'id' => 'AaOhDHYJ1XLZk74lXhdE', 'name' => 'Caleb - Trusted Guide', 'source' => 'замена Default «Chris»'],
    ['gender' => 'male', 'id' => 'FrS6cKLB1wg4WYgPa9GW', 'name' => 'Wyatt - Seasoned Mentor', 'source' => 'замена Default «Bill»'],
];
$taken = ['4NejU5DwQjevnR6mh3mb', 'EnjklPXGBMNldCJ7jqkE', 'TWutjvRaJqAX89preB4e', 'Nhs7eitvQWFTQBsf0yiT'];

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
        if (count($candidates) > 8 || mb_strlen(LINE) > 90) {
            fwrite(STDERR, "over the наряд's cap\n");
            exit(1);
        }
        foreach ($candidates as $c) {
            if (in_array($c['id'], $taken, true)) {
                fwrite(STDERR, "{$c['id']} is already one of the pack's voices\n");
                exit(1);
            }
        }
        $pack = config('generation.speech.voices.en.partner.female');
        $model = is_array($pack) ? (string) $pack['model'] : 'eleven_v3_conversational';
        $stability = is_array($pack) ? (float) $pack['stability'] : 0.5;
        $dir = base_path('docs/research/fix-4c/voices');
        @mkdir($dir, 0777, true);

        $before = $account();
        $rows = [];
        foreach ($candidates as $i => $c) {
            $r = $client()->post("{$base}/v1/text-to-speech/{$c['id']}?output_format=mp3_44100_128", [
                'text' => LINE,
                'model_id' => $model,
                'voice_settings' => ['stability' => $stability],
            ]);
            $slug = strtolower(preg_replace('/[^A-Za-z]+/', '-', explode(' - ', $c['name'])[0]) ?? 'voice');
            $file = sprintf('%s-%d-%s.mp3', $c['gender'], $i % 4 + 1, $slug);
            $row = $c + [
                'status' => $r->status(),
                'file' => $r->successful() ? $file : null,
                'characters' => mb_strlen(LINE),
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
        $after = $account();
        $credits = array_sum(array_column($rows, 'credits'));
        $summary = [
            'line' => LINE,
            'model' => $model,
            'stability' => $stability,
            'account_before' => $before,
            'account_after' => $after,
            'characters' => array_sum(array_column($rows, 'characters')),
            'credits' => $credits,
            'usd' => round($credits * 0.20 / 1000, 4),
            'samples' => $rows,
        ];
        file_put_contents("{$dir}/samples.json", json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
        echo json_encode(array_diff_key($summary, ['samples' => 1]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
        break;

    default:
        fwrite(STDERR, "account | samples\n");
        exit(1);
}
