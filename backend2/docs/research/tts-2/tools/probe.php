<?php

declare(strict_types=1);

/*
 * WHAT THE VOICE VENDOR SAYS ABOUT ITSELF (TTS-2) — one request at a time, printed as it came back.
 *
 *   docker compose exec -T app php docs/research/tts-2/tools/probe.php account
 *   docker compose exec -T app php docs/research/tts-2/tools/probe.php voices <voice_id> …
 *   docker compose exec -T app php docs/research/tts-2/tools/probe.php library "gender=male&language=en&page_size=20"
 *   docker compose exec -T app php docs/research/tts-2/tools/probe.php line <model_id> <voice_id> "text"
 *   docker compose exec -T app php docs/research/tts-2/tools/probe.php parallel <model_id> <voice_id> <n>
 *
 * `account`, `voices`, `library` are free. `line` and `parallel` buy characters — keep the texts short. The key is read
 * from `ELEVENLABS_API_KEY`; nothing secret is printed. The sound of the last paid line lands in
 * `storage/app/tts-2/probe-line.mp3` to listen to.
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
        $r = $client()->post("{$base}/v1/text-to-speech/{$voice}?output_format=mp3_44100_128", [
            'text' => $text,
            'model_id' => $model,
            'voice_settings' => ['stability' => (float) ($argv[5] ?? 0.5)],
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

    default:
        fwrite(STDERR, "unknown probe\n");
        exit(1);
}
