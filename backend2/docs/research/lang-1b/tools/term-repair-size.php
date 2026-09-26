<?php

declare(strict_types=1);

/**
 * LANG-1b §10.2 · WHAT THE REPAIR OF ONE DEFINITION WEIGHS — no model call. The order says «одна карточка, ≤ $0.01»; the live
 * day of §10.4 wrote every definition in Romanian, so no word was repaired live. This builds the request P2R would send
 * (`PlanPromptFiles::repairSystem()` + `repairUser()`, `lesson_card_repair.v1.4` over `lesson_day.v4.10`) for the word v6
 * «calculator» of the owner's v4.9 day (`live/before/ru-ro.prod-v4.9.lesson.json`: «an electronic machine used for work and
 * information»), and — to turn characters into tokens — for the check x8.check of the §10.4 day, whose real repair counted
 * its tokens (`runs/ru-ro.json`). Prints characters, the tokens by that ratio, and the price of `gpt-5.4` with no cache.
 *
 *   docker exec -w /wt -e DB_DATABASE=wordtrainer_lang1b10_test wt_lang1b10 php docs/research/lang-1b/tools/term-repair-size.php
 */

use App\Modules\Plan\Application\Dto\LessonCardRepairRequest;
use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Application\Service\LessonContexts;
use App\Modules\Plan\Domain\Lesson\EarlierDays;
use App\Modules\Plan\Domain\Lesson\LessonAssembly;
use App\Modules\Plan\Domain\Lesson\LessonCard;
use App\Modules\Plan\Domain\Lesson\LessonCardContext;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\Lesson\LessonRoles;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Plan\Infrastructure\Prompt\PlanPromptFiles;
use App\Modules\Plan\Infrastructure\Prompt\PlanSchemas;
use App\Modules\Shared\Domain\Service\ModelCost;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../../../vendor/autoload.php';
$app = require __DIR__.'/../../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$live = realpath(__DIR__.'/../live');
$run = json_decode((string) file_get_contents("{$live}/runs/ru-ro.json"), true);
$r = $run['request'];
$roles = new LessonRoles($r['roles']['learnerTarget'], $r['roles']['learnerNative'], $r['roles']['partnerTarget'], $r['roles']['partnerNative']);
$request = new LessonRequest($r['topic'], $r['topic_description'], $r['target_language'], $r['native_language'], PlanLevel::from($r['level']),
    VoiceGender::from((string) $r['learner_gender']), $r['vocabulary_count'], $r['dialogue_count'], $roles, new EarlierDays, [], $r['target_lang_code'], $r['native_lang_code']);
$prompts = new PlanPromptFiles(app_path('Modules/Plan/Infrastructure/Prompt'));

/** @return array{chars: int, system: int, user: int, schema: int} */
function repairSize(PlanPromptFiles $prompts, LessonRequest $request, array $payload, string $address, array $findings): array
{
    $lesson = (new LessonParser)->parse($payload)->withRoles($request->roles);
    $read = LessonAssembly::said($lesson, app(LessonContexts::class)->of($request)->target);
    $card = LessonCard::at($address);
    $ask = new LessonCardRepairRequest(
        address: $card->address, kind: $card->kind, card: $card->of($read), context: LessonCardContext::of($read, $card), findings: $findings,
        neighbours: null, earlierDays: $request->earlierDays, targetLanguage: $request->targetLanguage, nativeLanguage: $request->nativeLanguage,
        level: $request->level, learnerGender: $request->learnerGender, dialogueCount: $request->dialogueCount, vocabularyCount: $request->vocabularyCount,
    );
    $system = $prompts->repairSystem($card->kind);
    $user = $prompts->repairUser($ask);
    $schema = (string) json_encode(PlanSchemas::lessonCard($card->kind, $request->dialogueCount, $request->vocabularyCount));

    return ['chars' => mb_strlen($system) + mb_strlen($user) + mb_strlen($schema), 'system' => mb_strlen($system), 'user' => mb_strlen($user), 'schema' => mb_strlen($schema)];
}

$before = json_decode((string) file_get_contents("{$live}/before/ru-ro.prod-v4.9.lesson.json"), true);
$after = json_decode((string) file_get_contents("{$live}/answers/ru-ro.json"), true);
$term = repairSize($prompts, $request, $before, 'v6', [['code' => 'vocab.definition_language', 'detail' => 'v6: the definition «an electronic machine used for work and information» of «calculator» reads as en, not ro (for, and)']]);
$check = repairSize($prompts, $request, $after, 'x8.check', [['code' => 'options.form_mismatch', 'detail' => 'x8.check: the option «Что вы едите на обед» is 16 letters against 35 of the right «Какие программы вы используете на работе»']]);

$checkCall = null;
foreach ($run['day1']['calls'] as $call) {
    if ($call['call'] === 'repair' && ($call['asked']['address'] ?? null) === 'x8.check') {
        $checkCall = $call;
    }
}
$ratio = $checkCall === null ? null : $checkCall['tokens_in'] / $check['chars'];
$tokens = $ratio === null ? null : (int) round($term['chars'] * $ratio);
$out = 150;
$cost = app(ModelCost::class);
printf("term v6 (definition): %d chars (system %d, user %d, schema %d)\n", $term['chars'], $term['system'], $term['user'], $term['schema']);
printf("check x8.check: %d chars (system %d, user %d, schema %d) · live: %s tokens in, %s out, $%s\n", $check['chars'], $check['system'], $check['user'], $check['schema'],
    $checkCall['tokens_in'] ?? '?', $checkCall['tokens_out'] ?? '?', $checkCall['cost_usd'] ?? '?');
printf("chars per token %.2f → the term repair ≈ %s tokens in; with %d out: $%s with no cache, $%s with the rules cached\n",
    $ratio === null ? 0 : 1 / $ratio, $tokens ?? '?', $out,
    $cost->estimate('gpt-5.4', $tokens, $out) ?? '?',
    $cost->estimate('gpt-5.4', $tokens, $out, (int) round($term['system'] * ($ratio ?? 0))) ?? '?');
