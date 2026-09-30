<?php

declare(strict_types=1);

/**
 * GEN-4c-2 · THE PARTNER'S REPLIES TO THE LEARNER'S QUESTIONS OF AN E2E DAY, ATTEMPT BY ATTEMPT (наряд GEN-4c-2 §2) — every
 * partner line paired with an `ask` frame, in every skeleton the model wrote for the day (`calls-bodies.json`), then as the
 * day stored it: the question and what it asks for (`AskedFor`, the target's pack), the reply, the yes or no it opens with, the
 * code's findings at the line (`replay.json`, the conveyor run again over the same answers — `gen4c-replay.php`), what the seam
 * judge said of it at every read, and the repairs sent for it. The table of the report is written from its output.
 *
 *   docker exec -e DB_DATABASE=wordtrainer_e2e_test wt_gen4c php docs/research/gen-4b/tools/gen4c-replies.php \
 *       docs/research/gen-4b/e2e-c/ro-day2 2 ro
 */

use App\Modules\Plan\Domain\Check\Language\LanguagePacks;
use App\Modules\Plan\Domain\Check\Language\LanguageWords;
use App\Modules\Plan\Domain\Check\Skeleton\AskedFor;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../../../vendor/autoload.php';
$app = require __DIR__.'/../../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$dir, $number, $lang] = [(string) ($argv[1] ?? ''), (int) ($argv[2] ?? 0), (string) ($argv[3] ?? '')];
$words = new LanguageWords(app(LanguagePacks::class)->for($lang));
$bodies = json_decode((string) file_get_contents("{$dir}/calls-bodies.json"), true, flags: JSON_THROW_ON_ERROR)['calls'];
$replay = json_decode((string) file_get_contents("{$dir}/replay.json"), true, flags: JSON_THROW_ON_ERROR);
$stored = json_decode((string) file_get_contents("{$dir}/day{$number}-skeleton.json"), true, flags: JSON_THROW_ON_ERROR);

/**
 * @param  array<string, mixed>  $skeleton
 * @return array<string, array{frame: string, question: string, values: list<string>, line_kind: string, reply: string, native: string}>
 */
function repliesOf(array $skeleton): array
{
    $out = [];
    foreach ($skeleton['partner_lines'] ?? [] as $line) {
        foreach ($line['pairs_with'] ?? [] as $item) {
            foreach ($skeleton['phrases'] ?? [] as $frame) {
                if (($frame['kind'] ?? '') === 'ask' && in_array($item, $frame['must_say'] ?? [], true) && ! isset($out[$line['id']])) {
                    $out[$line['id']] = [
                        'frame' => $frame['id'], 'question' => $frame['frame_target'],
                        'values' => array_map(static fn (array $f): string => $f['target'], $frame['slot']['fillers'] ?? []),
                        'line_kind' => $line['kind'] ?? '', 'reply' => $line['text_target'], 'native' => $line['text_native'] ?? '',
                    ];
                }
            }
        }
    }

    return $out;
}

$attempts = [];
$judged = [];
foreach ($bodies as $call) {
    if (str_starts_with((string) $call['prompt'], 'LESSON SKELETON')) {
        $attempts[] = repliesOf(json_decode((string) $call['answer'], true, flags: JSON_THROW_ON_ERROR));
    }
    if (str_starts_with((string) $call['prompt'], 'LESSON SEAM JUDGE')) {
        $user = (string) $call['user'];
        $sent = [];
        $at = strpos($user, 'REPLIES');
        if ($at !== false && preg_match('/\[\s*(.*)\s*\]\s*$/s', substr($user, $at), $m)) {
            $sent = array_column((array) json_decode('['.$m[1].']', true), 'reply', 'id');
        }
        $judged[] = ['sent' => $sent, 'naming' => json_decode((string) $call['answer'], true)['replies_naming_values'] ?? []];
    }
}

$rows = [];
foreach (repliesOf($stored) as $id => $final) {
    $asked = AskedFor::of($final['question'], $words);
    $row = [
        'id' => $id, 'frame' => $final['frame'], 'question' => $final['question'], 'values' => $final['values'],
        'asks_for' => $asked->kind.($asked->word !== null ? " («{$asked->word}»)" : ''),
        'line_kind' => $final['line_kind'],
    ];
    foreach ($attempts as $n => $replies) {
        $reply = $replies[$id]['reply'] ?? null;
        $row['attempt_'.($n + 1)] = $reply === null ? null : [
            'reply' => $reply, 'opens_with' => $words->yesNoOpening($reply),
            'question' => $replies[$id]['question'], 'frame' => $replies[$id]['frame'],
            'findings' => array_values(array_map(
                static fn (array $f): string => $f['code'],
                array_filter($replay['attempts'][$n]['findings'] ?? [], static fn (array $f): bool => $f['address'] === $id),
            )),
        ];
    }
    $row['judge'] = array_values(array_filter(array_map(
        static fn (array $j): ?array => array_key_exists($id, $j['sent']) ? ['reply' => $j['sent'][$id], 'names_a_value' => in_array($id, $j['naming'], true)] : null,
        $judged,
    )));
    $row['repairs'] = array_values(array_filter($replay['repairs'], static fn (array $r): bool => $r['address'] === $id));
    $row['final'] = ['reply' => $final['reply'], 'native' => $final['native'], 'opens_with' => $words->yesNoOpening($final['reply'])];
    $row['left'] = array_values(array_map(
        static fn (array $f): string => $f['code'],
        array_filter($replay['findings_left'], static fn (array $f): bool => $f['address'] === $id),
    ));
    $rows[] = $row;
}
file_put_contents("{$dir}/replies.json", json_encode(['day' => $number, 'replies' => $rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
fwrite(STDERR, count($rows)." replies → {$dir}/replies.json\n");
