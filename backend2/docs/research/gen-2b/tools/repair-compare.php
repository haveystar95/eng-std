<?php

declare(strict_types=1);

/**
 * GEN-2b · P2R ON A CHEAPER MODEL — «чинит не хуже?»: every card the comparison names, repaired ALONE on the answer as
 * the model wrote it, by P2R v1.1 with the narrow context (`LessonCardContext`) on two models — the lesson's own
 * (`gpt-5.4`) and a step cheaper (`gpt-5.4-mini`, the new default). Nothing is written.
 *
 * The cards: the two GEN-2a repaired in its live gate (rent `B3`, bank `p2`, v4.4 lessons on this database), the card
 * P2R v1 returned unchanged on Den's first interview lesson (`p1` «I work as an ___», v4.4, `cases/`), and every fatal
 * card of this наряд's v4.5 answers (`answers/`). For each run: the findings at the card before and after, the fatal
 * findings of the whole lesson before and after, the card as it came back, tokens, cost, time → `repair-compare.json`.
 *
 *   docker compose exec -T -e DB_DATABASE=wordtrainer_e2e_test app php docs/research/gen-2b/tools/repair-compare.php
 */

use App\Modules\Generation\Application\Port\ContentModelCatalog;
use App\Modules\Generation\Domain\ValueObject\ProviderId;
use App\Modules\Plan\Application\Command\BuildLessonHandler;
use App\Modules\Plan\Application\Dto\LessonCardRepairRequest;
use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Application\Dto\ModelReply;
use App\Modules\Plan\Application\Dto\NativeSeamJudgeRequest;
use App\Modules\Plan\Application\Dto\PlanRequest;
use App\Modules\Plan\Application\Port\PlanModelPort;
use App\Modules\Plan\Application\Service\LessonCardRepairer;
use App\Modules\Plan\Application\Service\LessonContexts;
use App\Modules\Plan\Domain\Check\LessonGate;
use App\Modules\Plan\Domain\Check\LessonValidator;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\LessonCard;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Plan\Infrastructure\Model\ContentModelPlanBuilder;
use App\Modules\Plan\Infrastructure\Prompt\PlanPromptFiles;
use App\Modules\Shared\Domain\Service\LanguageName;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../../../vendor/autoload.php';
$app = require __DIR__.'/../../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if ((string) config('database.connections.pgsql.database') === 'wordtrainer') {
    fwrite(STDERR, "Refusing to run against the main database.\n");
    exit(1);
}

final class TokenTap implements PlanModelPort
{
    public ?ModelReply $last = null;

    public function __construct(private readonly PlanModelPort $inner) {}

    public function buildPlan(PlanRequest $request): ModelReply
    {
        return $this->last = $this->inner->buildPlan($request);
    }

    public function buildLesson(LessonRequest $request): ModelReply
    {
        return $this->last = $this->inner->buildLesson($request);
    }

    public function repairLessonCard(LessonCardRepairRequest $request): ModelReply
    {
        return $this->last = $this->inner->repairLessonCard($request);
    }

    public function judgeNativeSeams(NativeSeamJudgeRequest $request): ModelReply
    {
        return $this->last = $this->inner->judgeNativeSeams($request);
    }

    public function planPromptVersion(): string
    {
        return $this->inner->planPromptVersion();
    }

    public function lessonPromptVersion(): string
    {
        return $this->inner->lessonPromptVersion();
    }

    public function repairPromptVersion(): string
    {
        return $this->inner->repairPromptVersion();
    }

    public function judgePromptVersion(): string
    {
        return $this->inner->judgePromptVersion();
    }
}

$dir = realpath(__DIR__.'/..');
$gen2a = array_column(json_decode((string) file_get_contents("{$dir}/../gen-2a/runs.json"), true), null, 'slug');
$gen2b = array_column(json_decode((string) file_get_contents("{$dir}/runs.json"), true), null, 'slug');
$parser = new LessonParser;
$validator = $app->make(LessonValidator::class);

/** A case: the answer, the inputs it was written with, the card. */
$cases = [];
foreach (['rent' => 'B3', 'bank' => 'p2'] as $slug => $address) {
    $scene = DB::table('plan_scenes')->where('id', $gen2a[$slug]['scene_id'])->first();
    $cases[] = [
        'case' => "GEN-2a {$slug} {$address} (v4.4)", 'answer' => json_decode((string) $scene->lesson_json, true), 'address' => $address, 'native' => 'ru',
        'level' => $gen2a[$slug]['level'], 'topic' => (string) $scene->title_native,
        'brief' => BuildLessonHandler::topicDescription((string) $scene->topic_description, (string) $gen2a[$slug]['goal']),
    ];
}
$input = (string) file_get_contents("{$dir}/cases/den-interview-v4.4.input.txt");
preg_match('/^TOPIC: (.+)$/m', $input, $topic);
preg_match('/TOPIC_DESCRIPTION: (.+?)\n\nTARGET_LANGUAGE:/s', $input, $brief);
$cases[] = [
    'case' => 'Ден interview p1 (v4.4; P2R v1 on gpt-5.4 returned it unchanged)', 'answer' => json_decode((string) file_get_contents("{$dir}/cases/den-interview-v4.4.answer.json"), true),
    'address' => 'p1', 'native' => 'ru', 'level' => 'intermediate', 'topic' => $topic[1], 'brief' => $brief[1],
];
foreach ($gen2b as $slug => $run) {
    $answer = json_decode((string) file_get_contents("{$dir}/answers/{$slug}.json"), true);
    $native = explode('→', $run['pair'])[0];
    $context = $app->make(LessonContexts::class)->of(new LessonRequest($run['topic'], $run['topic_description'], 'English', LanguageName::of($native), PlanLevel::from($run['level']), null, 8, 8, [], 'en', $native));
    $fatal = LessonGate::fatal($validator->run($parser->parse($answer), $context));
    // A day whose lines all break the same way (airport: every frame without its full stop) is shown by two of them.
    $lines = 0;
    foreach (LessonGate::cards($fatal) ?? [] as $card) {
        if ($card->kind === LessonCard::LINE && ++$lines > 2) {
            continue;
        }
        $cases[] = ['case' => "GEN-2b {$slug} {$card->address} (v4.5)", 'answer' => $answer, 'address' => $card->address, 'native' => $native, 'level' => $run['level'], 'topic' => $run['topic'], 'brief' => $run['topic_description']];
    }
}

$models = array_slice($argv, 1) ?: ['gpt-5.4', 'gpt-5.4-mini'];
$codes = static fn (array $rows): array => array_values(array_map(static fn (array $r): string => "{$r['code']}@{$r['address']}", $rows));
$out = [];
foreach ($cases as $case) {
    $answer = $parser->parse($case['answer']);
    $request = new LessonRequest($case['topic'], $case['brief'], 'English', LanguageName::of($case['native']), PlanLevel::from($case['level']), null, 8, 8, [], 'en', $case['native']);
    $card = LessonCard::at($case['address']);
    foreach ($models as $model) {
        $tap = new TokenTap(new ContentModelPlanBuilder(
            catalog: $app->make(ContentModelCatalog::class),
            prompts: $app->make(PlanPromptFiles::class),
            provider: ProviderId::OpenAi,
            planModel: 'gpt-5.4',
            lessonModel: 'gpt-5.4',
            planTimeout: 90,
            lessonTimeout: 90,
            repairModel: $model,
            judgeModel: 'gpt-5.4-mini',
        ));
        $repairer = $app->make(LessonCardRepairer::class, ['model' => $tap]);
        $context = $app->make(LessonContexts::class)->of($request);
        $found = $validator->run($answer, $context);
        $outcome = $repairer->repairIn($answer, $card, $found, $context, $request);
        $fatalAfter = $outcome->answer === null ? null : LessonGate::fatal($validator->run($outcome->answer, $app->make(LessonContexts::class)->of($request)));
        $row = [
            'case' => $case['case'], 'model' => $model, 'status' => $outcome->status, 'note' => $outcome->note,
            'at_card_before' => $codes($outcome->findingsBefore), 'at_card_after' => $codes($outcome->findingsAfter),
            'fatal_before' => array_map(static fn (LessonViolation $v): string => "{$v->code}@{$v->address}", LessonGate::fatal($found)),
            'fatal_after' => $fatalAfter === null ? null : array_map(static fn (LessonViolation $v): string => "{$v->code}@{$v->address}", $fatalAfter),
            'findings_before' => $outcome->lessonFindingsBefore, 'findings_after' => $outcome->answer === null ? null : count($outcome->lessonFindings),
            'card_before' => $outcome->before, 'card_after' => $outcome->after, 'frame_update' => $outcome->frameUpdate,
            'cost_usd' => $outcome->costUsd, 'latency_ms' => $outcome->latencyMs,
            'tokens_in' => $tap->last?->tokensIn, 'tokens_out' => $tap->last?->tokensOut, 'model_answered' => $tap->last?->model,
        ];
        $out[] = $row;
        fwrite(STDOUT, sprintf("%-44s %-13s %-10s card %s → %s · lesson fatal %d → %s · $%s · %d ms · tokens %s/%s\n",
            $case['case'], $model, $outcome->status, implode(',', $row['at_card_before']) ?: '—', implode(',', $row['at_card_after']) ?: '—',
            count($row['fatal_before']), $row['fatal_after'] === null ? '—' : (string) count($row['fatal_after']), $outcome->costUsd, $outcome->latencyMs,
            (string) $row['tokens_in'], (string) $row['tokens_out']));
    }
}
file_put_contents("{$dir}/repair-compare.json", json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
fwrite(STDOUT, "repair-compare.json written\n");
