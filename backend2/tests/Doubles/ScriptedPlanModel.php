<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Modules\Generation\Application\Dto\ModelAnswer;
use App\Modules\Generation\Application\Dto\RenderedPrompt;
use App\Modules\Generation\Application\Port\ContentModelPort;
use App\Modules\Generation\Domain\ValueObject\ProviderId;

/**
 * A plan model that answers from a script — one prepared payload per call, in order.
 *
 * Unlike {@see \App\Modules\Generation\Infrastructure\Adapter\FakePlanContentModel}, whose whole
 * job is to produce days that PASS, this one produces exactly what a test needs to have gone
 * wrong. It also keeps every prompt and user message it was handed, because half of what v0.3.1 is
 * about is what the second call is TOLD — and «the retry message contains no line of the previous
 * answer» is an assertion, not a reading.
 *
 * The three prompts are told apart by their schema, the way the production doubles do it: `cards`
 * is P2R, `hear` is P2 (the first shelf of a day-scene), anything else is P1.
 */
final class ScriptedPlanModel implements ContentModelPort
{
    /** @var list<string> the user message of every P2 call, in order */
    public array $dayMessages = [];

    /** @var list<string> the rendered system prompt of every P2R call, in order */
    public array $repairPrompts = [];

    /** @var list<string> the user message of every P2R call, in order */
    public array $repairMessages = [];

    /** @var list<string> the user message of every P2J (pair judge) call, in order */
    public array $judgeMessages = [];

    /** @var list<string> the user message of every P2P (pair rewrite) call, in order */
    public array $rewriteMessages = [];

    /**
     * @param  list<array<string, mixed>>  $days      one P2 answer per call
     * @param  list<array<string, mixed>>  $repairs   one P2R answer per call
     * @param  list<bool>  $verdicts                  one P2J verdict per call; exhausted = «fits»
     * @param  list<array<string, mixed>>  $rewrites  one P2P answer per call; exhausted = a stock line
     */
    public function __construct(
        private array $days,
        private array $repairs = [],
        private string $outlineFixture = 's1-outline.v0.4.json',
        private array $verdicts = [],
        private array $rewrites = [],
    ) {}

    public function provider(): ProviderId
    {
        return ProviderId::OpenAi;
    }

    public function model(): string
    {
        return 'scripted-plan';
    }

    public function complete(RenderedPrompt $prompt, string $userMessage, array $schema): ModelAnswer
    {
        $properties = $schema['properties'] ?? [];
        $properties = is_array($properties) ? $properties : [];

        if (isset($properties['cards'])) {
            $this->repairPrompts[] = $prompt->text;
            $this->repairMessages[] = $userMessage;
            $payload = array_shift($this->repairs) ?? ['cards' => []];
        } elseif (isset($properties['pairs']) || isset($properties['hear'])) {
            $this->dayMessages[] = $userMessage;
            $payload = array_shift($this->days) ?? [];
        } elseif (isset($properties['fits'])) {
            $this->judgeMessages[] = $userMessage;
            $verdict = array_shift($this->verdicts);
            // v0.2 — four answers and a summary. A scripted «no» fails the FIRST question
            // (`answers`) unless the script says otherwise: a verdict may also be an array of the
            // four booleans, for a test about one particular question.
            $checks = is_array($verdict)
                ? $verdict
                : array_fill_keys(\App\Modules\Generation\Application\Service\PlanPairCourt::CHECKS, $verdict ?? true);
            if (! is_array($verdict) && $verdict === false) {
                $checks['answers'] = false;
                foreach (['not_clarification', 'level_fits', 'translation_exact'] as $ok) {
                    $checks[$ok] = true;
                }
            }
            $fits = ! in_array(false, $checks, true);
            $payload = [...$checks, 'fits' => $fits, 'reason' => $fits ? 'scripted: fits' : 'scripted: does not follow'];
        } elseif (isset($properties['frame'])) {
            $this->rewriteMessages[] = $userMessage;
            $payload = array_shift($this->rewrites) ?? [
                'skill_ref' => 's1.1',
                // A FORMULA — no gap, empty filler — so the stock rewrite passes the day's gates.
                'frame' => 'Scripted rewritten line.',
                'filler' => '',
                'translation' => 'Переписанная по сценарию реплика.',
                'transliteration' => '',
                'speaking_keys' => ['scripted rewritten'],
            ];
        } else {
            /** @var array<string, mixed> $payload */
            $payload = json_decode(
                (string) file_get_contents(__DIR__ . '/../Fixtures/plan/' . $this->outlineFixture),
                true,
            );
        }

        return new ModelAnswer(
            payload: $payload,
            model: 'scripted-plan',
            latencyMs: 0,
            tokensIn: 100,
            tokensOut: 200,
            costUsd: '0.047000',
            raw: '{}',
        );
    }

    public function dayCalls(): int
    {
        return count($this->dayMessages);
    }

    public function repairCalls(): int
    {
        return count($this->repairMessages);
    }

    public function judgeCalls(): int
    {
        return count($this->judgeMessages);
    }

    public function rewriteCalls(): int
    {
        return count($this->rewriteMessages);
    }
}
