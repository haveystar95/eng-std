<?php

declare(strict_types=1);

/*
 * DOES EVERY LINE OF A DAY HAVE ITS VOICE? (TTS-2)
 *
 *   docker compose exec -T app php docs/research/tts-2/tools/day_voice.php <plan_id> <day>
 *
 * Asks `GET /api/v1/plans/{id}/days/{n}` through the app's own HTTP kernel as the plan's learner — no token is minted,
 * the guard is handed the user for this one request — and prints, by kind, how many lines carry an `audio_url`; then
 * downloads every file behind those addresses the same way and checks that it is an mp3 (an ID3 tag or an MPEG frame),
 * with its size and the voice, characters, credits and price stored for it. Read-only.
 */

use App\Modules\Identity\Infrastructure\Eloquent\User;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../../../vendor/autoload.php';
$app = require __DIR__.'/../../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

[$planId, $day] = [$argv[1] ?? '', (int) ($argv[2] ?? 1)];
$plan = DB::table('plans')->where('id', $planId)->first();
if ($plan === null) {
    fwrite(STDERR, "no plan {$planId}\n");
    exit(1);
}
$user = User::query()->findOrFail($plan->user_id);

$get = static function (string $path) use ($app, $user): Illuminate\Http\Response|Symfony\Component\HttpFoundation\Response {
    $app['auth']->forgetGuards();
    $app['auth']->guard('sanctum')->setUser($user);
    $request = Request::create($path, 'GET', server: ['HTTP_ACCEPT' => 'application/json']);

    return $app->make(HttpKernel::class)->handle($request);
};

$response = $get("/api/v1/plans/{$planId}/days/{$day}");
if ($response->getStatusCode() !== 200) {
    fwrite(STDERR, "GET day {$day}: {$response->getStatusCode()} ".mb_substr((string) $response->getContent(), 0, 300)."\n");
    exit(1);
}
$program = json_decode((string) $response->getContent(), true)['data']['window']['program'];

$urls = [
    'partner lines' => array_map(static fn (array $p): mixed => $p['partner']['audio_url'] ?? null, $program['dialogue']['items']),
    'learner lines' => array_map(static fn (array $p): mixed => $p['learner']['audio_url'] ?? null, $program['dialogue']['items']),
    'phrases' => array_column($program['phrases']['items'], 'audio_url'),
    'fillers' => array_column(array_merge(...array_map(static fn (array $p): array => $p['frame']['slot']['fillers'] ?? [], $program['phrases']['items'])), 'audio_url'),
    'words' => array_column($program['words']['items'], 'audio_url'),
    'word lines («в разговоре»)' => array_values(array_filter(array_map(static fn (array $w): mixed => $w['usage']['audio_url'] ?? false, $program['words']['items']), static fn (mixed $u): bool => $u !== false)),
];
printf("GET /plans/%s/days/%d — 200\n", $planId, $day);
$missing = 0;
foreach ($urls as $kind => $list) {
    $with = count(array_filter($list, 'is_string'));
    $missing += count($list) - $with;
    printf("  %-28s %2d of %2d with audio_url\n", $kind, $with, count($list));
}

$files = array_values(array_unique(array_filter(array_merge(...array_values($urls)), 'is_string')));
$bad = 0;
foreach ($files as $url) {
    $path = (string) parse_url($url, PHP_URL_PATH);
    $file = $get($path);
    $bytes = $file instanceof Symfony\Component\HttpFoundation\BinaryFileResponse ? (string) file_get_contents($file->getFile()->getPathname()) : (string) $file->getContent();
    $isMp3 = str_starts_with($bytes, 'ID3') || (strlen($bytes) > 1 && ord($bytes[0]) === 0xFF && (ord($bytes[1]) & 0xE0) === 0xE0);
    $row = DB::table('plan_line_audios')->where('id', basename($path))->first();
    if ($file->getStatusCode() !== 200 || ! $isMp3) {
        $bad++;
    }
    printf("  %-6s %3d %-10s %6d B %5s ms  %3s chars %3s credits  $%s  %s\n", $row->line_ref ?? '?', $file->getStatusCode(), $file->headers->get('Content-Type'), strlen($bytes), $row->duration_ms ?? '?', $row->characters ?? '?', $row->credits ?? '?', $row->cost_usd ?? '?', $row->voice_key ?? '?');
}
printf("files: %d, not an mp3 or not served: %d · lines without a voice: %d\n", count($files), $bad, $missing);
