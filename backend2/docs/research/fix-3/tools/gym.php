<?php

/**
 * FIX-3 — the owner's gym plan read by the BRANCH's code, read-only: what the new rules make of the material and the
 * transcripts GYM-DUMP-2 described (`docs/research/gym-day2/`).
 *
 *   §2 — the seconds every stage of days 1 and 2 took (gaps between answers ≤ 120 s, the first answer of the day none)
 *        against what the old and the new price list reckon for the same cards;
 *   §3 — «Фразы» of both scenes dealt by the branch's assembler at the plan's price list: the rungs, what every frame
 *        kept, and «How heavy should ___ be?» (p5 of scene 2);
 *   §6 — the targets of both talks read by the new rule over the learner's moves as they were heard, the role's own
 *        `phrases_used` (from the model's raw answers the dump kept) as the second support;
 *   §1 — the sound of the day-2 cards as the phone reads them now: the returned lines of «Ресепшен зала» keep their files.
 *
 * NOTHING IS WRITTEN to the database: the session is READ ONLY (checked below), no model, no voice, no queue, no cache,
 * no log file. Output: `../live/gym-*.json`.
 *
 * Run: docker exec -e DB_DATABASE=wordtrainer -e PGOPTIONS='-c default_transaction_read_only=on' -e LOG_CHANNEL=stderr \
 *        -e CACHE_STORE=array -e QUEUE_CONNECTION=fix3_none -e OPENAI_API_KEY= -e GEMINI_API_KEY= -e ELEVENLABS_API_KEY= \
 *        -e ANTHROPIC_API_KEY= wt_fix3 php docs/research/fix-3/tools/gym.php
 */

declare(strict_types=1);

use App\Modules\Plan\Application\Service\CardViews;
use App\Modules\Plan\Application\Service\ConversationMaterial;
use App\Modules\Plan\Application\Service\DayDealer;
use App\Modules\Plan\Application\Service\PlanAccess;
use App\Modules\Plan\Application\Service\PlanPaces;
use App\Modules\Plan\Domain\Assembly\CardDraft;
use App\Modules\Plan\Domain\Check\Language\LanguagePacks;
use App\Modules\Plan\Domain\Entity\DayCard;
use App\Modules\Plan\Domain\Repository\ConversationRepository;
use App\Modules\Plan\Domain\Repository\DayCardRepository;
use App\Modules\Plan\Domain\Service\DayPace;
use App\Modules\Plan\Domain\Service\PhraseUse;
use App\Modules\Plan\Domain\ValueObject\ConversationId;
use App\Modules\Plan\Domain\ValueObject\ConversationPhrase;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Domain\ValueObject\TurnKind;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

const PLAN = '01M32DX8QCABM348XP45Z1ZD4M';
const USER = '01M12HTZ1QHPNDZ5J8SPKB58QP';
const SCENES = [1 => '01M32DXHYG50H7SWQEMD33A39F', 2 => '01M32DXHYGYMK0E1DBR01PYDHA'];
const TALKS = [1 => '01M32FJ5FQNRNSQH6PNC7DP5E0', 2 => '01M34HXHP05JY8NVF1305NRY5N'];
/** The price list of the order (SESSION-1a … BACK-TAILS-1), the one the gym days were reckoned by. */
const OLD_PACE = [
    'word_intro' => 8, 'word_repeat' => 12, 'word_choose' => 10, 'word_listen' => 10, 'word_assemble' => 20, 'word_in_line' => 10,
    'phrase_intro' => 12, 'phrase_assemble' => 25, 'phrase_choose_back' => 12, 'phrase_slot' => 12, 'phrase_slot_listen' => 12,
    'phrase_repeat' => 25, 'phrase_other_slot' => 25, 'phrase_combine' => 20, 'dialogue_partner' => 15, 'dialogue_answer' => 30,
    'dialogue_ask' => 45, 'dialogue_rescue' => 15, 'listen_dialogue' => 110, 'listen_question' => 12, 'listen_review' => 30,
    'listen_predict' => 15, 'listen_pace' => 25, 'listen_number' => 15, 'speak_answer' => 35, 'speak_echo' => 25, 'speak_retell' => 30,
    'recall_scenes' => 60,
];
const GAP = 120;

require '/wt/vendor/autoload.php';
$app = require '/wt/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

DB::connection()->getPdo()->exec('SET SESSION CHARACTERISTICS AS TRANSACTION READ ONLY');
$session = DB::selectOne("select current_database() as db, current_setting('default_transaction_read_only') as d, current_setting('transaction_read_only') as t");
if ($session->db !== 'wordtrainer' || $session->d !== 'on' || $session->t !== 'on') {
    fwrite(STDERR, "not a read-only session of the live base — stop\n");
    exit(1);
}
foreach (['OPENAI_API_KEY', 'GEMINI_API_KEY', 'ELEVENLABS_API_KEY', 'ANTHROPIC_API_KEY'] as $key) {
    if ((string) env($key) !== '') {
        fwrite(STDERR, "{$key} is set — stop\n");
        exit(1);
    }
}
Http::preventStrayRequests();

$live = dirname(__DIR__).'/live';
$put = static function (string $name, mixed $data) use ($live): void {
    file_put_contents("{$live}/{$name}", json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
    echo "wrote {$name}\n";
};

$planId = PlanId::fromString(PLAN);
$plan = app(PlanAccess::class)->owned($planId, UserId::fromString(USER));
$cardsRepo = app(DayCardRepository::class);
$newPace = app(PlanPaces::class)->for($plan);
$oldPace = new DayPace(OLD_PACE);

// ── §2: the stages of days 1–2 — fact against the reckoning ───────────────────────────────────────
$pace = [];
foreach ([1, 2] as $n) {
    $cards = $cardsRepo->forDay($plan->day($n)->id());
    $answered = array_values(array_filter($cards, static fn (DayCard $c): bool => $c->answeredAt() !== null));
    usort($answered, static fn (DayCard $a, DayCard $b): int => [$a->answeredAt()?->getTimestamp(), $a->stage()->value, $a->position()] <=> [$b->answeredAt()?->getTimestamp(), $b->stage()->value, $b->position()]);
    $fact = [];
    $previous = null;
    foreach ($answered as $card) {
        $at = (int) $card->answeredAt()?->getTimestamp();
        if ($previous !== null && $at - $previous <= GAP) {
            $fact[$card->stage()->value] = ($fact[$card->stage()->value] ?? 0) + ($at - $previous);
        }
        $previous = $at;
    }
    $rows = [];
    foreach ($cards as $card) {
        $stage = $card->stage()->value;
        $rows[$stage] ??= ['cards' => 0, 'fact_seconds' => $fact[$stage] ?? 0, 'old_seconds' => 0, 'new_seconds' => 0];
        $rows[$stage]['cards']++;
        $rows[$stage]['old_seconds'] += $oldPace->seconds($card->kind(), $card->payload());
        $rows[$stage]['new_seconds'] += $newPace->seconds($card->kind(), $card->payload());
    }
    $pace["day_{$n}"] = $rows;
}
$put('gym-pace.json', $pace);

// ── §3: «Фразы» of both scenes at the plan's price list ───────────────────────────────────────────
$dealer = app(DayDealer::class);
$material = new ReflectionMethod($dealer, 'material');
$assembler = (new ReflectionMethod($dealer, 'assembler'))->invoke($dealer, $plan);
$deals = [];
foreach (SCENES as $n => $sceneId) {
    $scene = $material->invoke($dealer, $plan, [PlanSceneId::fromString($sceneId)])[$sceneId];
    $deal = $assembler->phrasesDeal($scene, $plan->level());
    $deals["day_{$n}"] = [
        'scene_id' => $sceneId,
        'seconds' => $deal->seconds,
        'budget' => $deal->budget,
        'over_ceiling' => $deal->overCeiling(),
        'rungs' => $deal->rungs,
        'frames' => $deal->frames,
        'say_whole' => array_values(array_map(static fn (CardDraft $d): array => [
            'unit_ref' => $d->unitRef,
            'rounds' => array_map(static fn (array $r): array => ['expected_text' => $r['expected_text'], 'task_native' => $r['task_native']], $d->payload['rounds']),
            'chips' => array_map(static fn (array $f): string => $f['target'].' — '.$f['native_line'], $d->payload['frame']['slot']['fillers'] ?? []),
        ], array_filter($deal->drafts, static fn (CardDraft $d): bool => $d->kind->value === 'phrase_other_slot'))),
    ];
}
$put('gym-phrases.json', $deals);

// ── §6: the targets of both talks by the new rule ─────────────────────────────────────────────────
/** The role's `phrases_used` of every answer, from the model's raw replies the dump kept, in the order they were given. */
$modelSays = static function (int $day): array {
    $rows = json_decode((string) file_get_contents(dirname(__DIR__, 2)."/gym-day2/raw/api-log-conversation-day-{$day}.json"), true);
    $out = [];
    foreach ($rows as $row) {
        if (str_contains((string) $row['host'], 'openai')) {
            $body = is_string($row['response_body']) ? json_decode($row['response_body'], true) : $row['response_body'];
            $out[] = json_decode($body['choices'][0]['message']['content'], true)['phrases_used'] ?? [];
        }
    }

    return $out;
};
$pack = app(LanguagePacks::class)->for($plan->targetLang()->value);
$rule = new PhraseUse;
$talks = [];
foreach (TALKS as $n => $talkId) {
    $talk = app(ConversationRepository::class)->findById(ConversationId::fromString($talkId));
    $targets = app(ConversationMaterial::class)->for($plan, $plan->day($n))->targets;
    $answers = $modelSays($n);
    $said = [];
    $moves = [];
    $agent = 0;
    $turns = $talk->turns();
    foreach ($turns as $i => $turn) {
        if ($turn->kind === TurnKind::Agent) {
            $agent++;

            continue;
        }
        if ($turn->kind !== TurnKind::Said) {
            continue;
        }
        // The role's answer to this move is the next agent line — its `phrases_used` is about this move.
        $named = $answers[$agent] ?? [];
        $unsaid = array_values(array_filter($targets, static fn (ConversationPhrase $t): bool => ! isset($said[$t->id()])));
        $heard = (string) $turn->textTarget;
        $credited = $rule->heardIn($heard, $unsaid, $named, $pack);
        foreach ($credited as $id) {
            $said[$id] = ['turn' => $turn->index, 'heard' => $heard, 'value' => null];
        }
        $moves[] = ['turn' => $turn->index, 'heard' => $heard, 'model_says' => $named, 'credited' => $credited];
    }
    $rows = [];
    foreach ($targets as $target) {
        $hit = $said[$target->id()] ?? null;
        $per = [];
        foreach ($moves as $move) {
            $tally = $rule->keyTally($move['heard'], $target, $pack);
            $per[] = ['turn' => $move['turn'], 'key' => "{$tally['found']}/{$tally['total']}", 'window' => $rule->valueOf($move['heard'], $target, $pack), 'model' => in_array($target->id(), $move['model_says'], true)];
        }
        $rows[] = [
            'ref' => $target->ref,
            'frame_target' => $target->frameTarget,
            'example' => $target->exampleTarget,
            'said' => $hit !== null,
            'said_on_turn' => $hit['turn'] ?? null,
            'value_target' => $hit === null ? null : $rule->valueOf($hit['heard'], $target, $pack),
            'hint_native' => $target->hintNative(),
            'moves' => $per,
        ];
    }
    $talks["day_{$n}"] = ['moves' => $moves, 'targets' => $rows, 'said' => count($said).' of '.count($targets)];
}
$put('gym-targets.json', $talks);

// ── §1: the sound of the day-2 cards as the phone reads them now ──────────────────────────────────
$day2 = $cardsRepo->forDay($plan->day(2)->id());
$views = app(CardViews::class)->forCards($day2, $plan->targetLang()->value);
$audio = [];
foreach ($views as $view) {
    if (! in_array($view->kind, ['speak_retell', 'speak_echo', 'speak_answer'], true)) {
        continue;
    }
    $line = $view->payload['own_line'] ?? null;
    $audio[] = [
        'position' => $view->position,
        'kind' => $view->kind,
        'source' => $view->source,
        'scene_id' => $view->payload['scene_id'] ?? null,
        'ref' => $line['ref'] ?? null,
        'audio_id' => $line['audio']['audio_id'] ?? null,
        'text_target' => $line['text_target'] ?? null,
    ];
}
$rows = DB::select('select id, scene_id, line_ref, voice_key from plan_line_audios where scene_id in (?, ?)', [SCENES[1], SCENES[2]]);
$files = [];
foreach ($rows as $row) {
    $files[(string) $row->id] = ['scene_id' => $row->scene_id, 'ref' => $row->line_ref, 'voice_key' => $row->voice_key];
}
foreach ($audio as $i => $line) {
    $audio[$i]['file'] = $line['audio_id'] === null ? null : ($files[$line['audio_id']] ?? '?');
}
$put('gym-audio.json', $audio);
