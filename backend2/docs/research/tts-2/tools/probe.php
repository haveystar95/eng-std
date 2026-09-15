<?php

declare(strict_types=1);

/*
 * WHAT THE VOICE VENDOR SAYS ABOUT ITSELF (TTS-2) — one request at a time, printed as it came back.
 *
 *   docker compose exec -T app php docs/research/tts-2/tools/probe.php account
 *   docker compose exec -T app php docs/research/tts-2/tools/probe.php voices <voice_id> …
 *   docker compose exec -T app php docs/research/tts-2/tools/probe.php library "gender=male&language=en&page_size=20"
 *   docker compose exec -T app php docs/research/tts-2/tools/probe.php line <model_id> <voice_id> "text" [stability] [speed]
 *   docker compose exec -T app php docs/research/tts-2/tools/probe.php parallel <model_id> <voice_id> <n>
 *   docker compose exec -T app php docs/research/tts-2/tools/probe.php takes <model_id> <voice_id> <n> "text" <label> [stability]
 *   docker compose exec -T app php docs/research/tts-2/tools/probe.php hear <label> <n>
 *
 * `hear` puts the takes through speech-to-text: what was actually said (a direction in brackets must not be read aloud)
 * and how fast — words from the first one's start to the last one's end. The voice key has no `speech_to_text`
 * permission, so the ears are OpenAI `whisper-1` on `OPENAI_API_KEY` (a fraction of a cent a take).
 *
 * Every paid mode is a purchase from a vendor: run it only on command, with the price named first (handoff, TTS-2).
 *
 * `account`, `voices`, `library` are free. `line`, `parallel` and `takes` buy characters — keep the texts short. The key
 * is read from `ELEVENLABS_API_KEY`; nothing secret is printed. The sound of the last paid line lands in
 * `storage/app/tts-2/probe-line.mp3` to listen to; `takes` keeps every take as `probe-<label>-<i>.mp3` — one take of
 * a line differs from the next, so a pace is judged on several.
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
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
$headers = static function (Response $r): array {
    $keep = [];
    foreach ($r->headers() as $name => $values) {
        if (in_array(strtolower($name), ['set-cookie', 'alt-svc', 'via', 'strict-transport-security', 'content-security-policy'], true)) {
            continue;
        }
        $keep[strtolower($name)] = implode(', ', $values);
    }
    ksort($keep);

    return $keep;
};
$print = static fn (mixed $v): int => print(json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
$dir = storage_path('app/tts-2');
@mkdir($dir, 0777, true);

switch ($argv[1] ?? 'account') {
    case 'account':
        $sub = $client()->get("{$base}/v1/user/subscription");
        echo "subscription {$sub->status()}\n";
        $print(is_array($sub->json()) ? array_intersect_key($sub->json(), array_flip([
            'tier', 'status', 'character_count', 'character_limit', 'next_character_count_reset_unix', 'can_extend_character_limit',
            'max_credit_limit_extension', 'current_overage', 'currency', 'billing_period', 'character_refresh_period',
        ])) : $sub->body());
        $print($headers($sub));
        $models = $client()->get("{$base}/v1/models");
        echo "models {$models->status()}\n";
        foreach ((array) $models->json() as $m) {
            if (is_array($m)) {
                $print(array_intersect_key($m, array_flip([
                    'model_id', 'can_do_text_to_speech', 'concurrency_group', 'model_rates', 'token_cost_factor',
                    'max_characters_request_free_user', 'max_characters_request_subscribed_user', 'maximum_text_length_per_request', 'requires_alpha_access',
                ])));
            }
        }
        break;

    case 'voices':
        foreach (array_slice($argv, 2) as $id) {
            $v = $client()->get("{$base}/v1/voices/{$id}");
            echo "voice {$id} {$v->status()}\n";
            $print(is_array($v->json()) ? array_intersect_key($v->json(), array_flip([
                'name', 'category', 'labels', 'description', 'is_owner', 'sharing', 'available_for_tiers', 'high_quality_base_model_ids', 'verified_languages', 'detail',
            ])) : $v->body());
        }
        break;

    case 'library':
        $r = $client()->get("{$base}/v1/shared-voices?".($argv[2] ?? 'gender=male&language=en&page_size=20'));
        echo "library {$r->status()}\n";
        foreach ((array) $r->json('voices') as $s) {
            if (is_array($s)) {
                $print(array_intersect_key($s, array_flip([
                    'voice_id', 'public_owner_id', 'name', 'gender', 'age', 'accent', 'language', 'locale', 'use_case', 'descriptive', 'category',
                    'free_users_allowed', 'cloned_by_count', 'usage_character_count_1y', 'featured', 'description',
                ])));
            }
        }
        if (! is_array($r->json('voices'))) {
            echo $r->body(), "\n";
        }
        break;

    case 'line':
        [$model, $voice, $text] = [$argv[2], $argv[3], $argv[4] ?? 'Hello.'];
        $settings = ['stability' => (float) ($argv[5] ?? 0.5)] + (isset($argv[6]) ? ['speed' => (float) $argv[6]] : []);
        $r = $client()->post("{$base}/v1/text-to-speech/{$voice}?output_format=mp3_44100_128", [
            'text' => $text,
            'model_id' => $model,
            'voice_settings' => $settings,
        ]);
        echo "line {$r->status()}\n";
        $print($headers($r));
        if ($r->successful()) {
            file_put_contents("{$dir}/probe-line.mp3", $r->body());
            echo 'mp3 bytes ', strlen($r->body()), ' head ', bin2hex(substr($r->body(), 0, 12)), "\n";
        } else {
            echo $r->body(), "\n";
        }
        break;

    case 'parallel':
        [$model, $voice, $n] = [$argv[2], $argv[3], (int) ($argv[4] ?? 4)];
        $responses = Http::pool(static function (Pool $pool) use ($base, $key, $model, $voice, $n): array {
            $out = [];
            for ($i = 0; $i < $n; $i++) {
                $out[] = $pool->withHeaders(['xi-api-key' => $key])->timeout(120)
                    ->post("{$base}/v1/text-to-speech/{$voice}?output_format=mp3_44100_128", ['text' => 'Yes.', 'model_id' => $model]);
            }

            return $out;
        });
        foreach ($responses as $i => $r) {
            if (! $r instanceof Response) {
                echo "#{$i} ", get_debug_type($r), "\n";

                continue;
            }
            echo "#{$i} {$r->status()}\n";
            $print($headers($r));
            if (! $r->successful()) {
                echo $r->body(), "\n";
            }
        }
        break;

    case 'takes':
        [$model, $voice, $n, $text, $label] = [$argv[2], $argv[3], (int) ($argv[4] ?? 3), $argv[5] ?? 'Hello.', $argv[6] ?? 'take'];
        $stability = (float) ($argv[7] ?? 0.5);
        foreach (array_chunk(range(1, max(1, $n)), 3) as $round) {
            $responses = Http::pool(static function (Pool $pool) use ($base, $key, $model, $voice, $text, $stability, $round): array {
                $out = [];
                foreach ($round as $i) {
                    $out[$i] = $pool->as((string) $i)->withHeaders(['xi-api-key' => $key])->timeout(120)
                        ->post("{$base}/v1/text-to-speech/{$voice}?output_format=mp3_44100_128", [
                            'text' => $text,
                            'model_id' => $model,
                            'voice_settings' => ['stability' => $stability],
                        ]);
                }

                return $out;
            });
            foreach ($responses as $i => $r) {
                if (! $r instanceof Response) {
                    echo "#{$i} ", get_debug_type($r), "\n";

                    continue;
                }
                if (! $r->successful()) {
                    echo "#{$i} {$r->status()} ", $r->body(), "\n";

                    continue;
                }
                file_put_contents("{$dir}/probe-{$label}-{$i}.mp3", $r->body());
                echo "#{$i} 200 bytes ", strlen($r->body()), ' credits ', $r->header('character-cost'), "\n";
            }
        }
        break;

    case 'hear':
        [$label, $n] = [$argv[2] ?? 'take', (int) ($argv[3] ?? 1)];
        for ($i = 1; $i <= $n; $i++) {
            $path = "{$dir}/probe-{$label}-{$i}.mp3";
            if (! is_file($path)) {
                echo "#{$i} no take\n";

                continue;
            }
            $r = Http::withToken((string) env('OPENAI_API_KEY', ''))->timeout(120)
                ->attach('file', (string) file_get_contents($path), basename($path))
                ->post('https://api.openai.com/v1/audio/transcriptions', [
                    'model' => 'whisper-1', 'language' => 'en', 'response_format' => 'verbose_json', 'timestamp_granularities[]' => 'word',
                ]);
            if (! $r->successful()) {
                echo "#{$i} {$r->status()} ", $r->body(), "\n";

                continue;
            }
            $words = array_values(array_filter((array) $r->json('words'), is_array(...)));
            $first = (float) ($words[0]['start'] ?? 0);
            $last = (float) ($words === [] ? 0 : ($words[count($words) - 1]['end'] ?? 0));
            $span = $last - $first;
            printf("#%d words %d  %.2fs → %.2fs  (%.2fs, %.2f words/s)  \"%s\"\n", $i, count($words), $first, $last, $span, $span > 0 ? count($words) / $span : 0, (string) $r->json('text'));
        }
        break;

    default:
        fwrite(STDERR, "unknown probe\n");
        exit(1);
}
