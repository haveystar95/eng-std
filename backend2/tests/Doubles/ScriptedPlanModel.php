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
 * is P2R, `phrases` is P2, anything else is P1.
 */
final class ScriptedPlanModel implements ContentModelPort
{
    /** @var list<string> the user message of every P2 call, in order */
    public array $dayMessages = [];

    /** @var list<string> the rendered system prompt of every P2R call, in order */
    public array $repairPrompts = [];

    /** @var list<string> the user message of every P2R call, in order */
    public array $repairMessages = [];

    /**
     * @param  list<array<string, mixed>>  $days     one P2 answer per call
     * @param  list<array<string, mixed>>  $repairs  one P2R answer per call
     */
    public function __construct(
        private array $days,
        private array $repairs = [],
        private string $outlineFixture = 's1-outline.v0.2.json',
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
        } elseif (isset($properties['phrases'])) {
            $this->dayMessages[] = $userMessage;
            $payload = array_shift($this->days) ?? [];
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
}
