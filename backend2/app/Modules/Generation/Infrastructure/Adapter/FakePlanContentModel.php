<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Adapter;

use App\Modules\Generation\Application\Dto\ModelAnswer;
use App\Modules\Generation\Application\Dto\RenderedPrompt;
use App\Modules\Generation\Application\Port\ContentModelPort;
use App\Modules\Generation\Domain\ValueObject\ProviderId;

/**
 * The offline plan model: deterministic, free, and — this is the part that matters — it produces
 * answers that PASS the validators.
 *
 * A fake that returned obviously-broken material would make every feature test a test of the
 * failure path. So this one obeys the numbers it is given (exactly `phrase_count` replies and
 * `word_count` substitutions, every checkpoint closed by a reply, no example equal to any term, no
 * two keys the same) and produces nonsense CONTENT, which is what a fake is for. The counts are
 * read out of the rendered prompt, because that is where the server put them and reading them back
 * is also a check that it did.
 */
final class FakePlanContentModel implements ContentModelPort
{
    public function provider(): ProviderId
    {
        return ProviderId::OpenAi;
    }

    public function model(): string
    {
        return 'fake-plan';
    }

    public function complete(RenderedPrompt $prompt, string $userMessage, array $schema): ModelAnswer
    {
        $properties = $schema['properties'] ?? [];
        $isDay = is_array($properties) && isset($properties['phrases']);

        return new ModelAnswer(
            payload: $isDay ? $this->day($prompt->text) : $this->outline($prompt->text),
            model: 'fake-plan',
            latencyMs: 0,
            tokensIn: 0,
            tokensOut: 0,
            costUsd: '0.000000',
            raw: '{}',
        );
    }

    /** @return array<string, mixed> */
    private function outline(string $prompt): array
    {
        $days = max(1, $this->intAfter($prompt, 'DAYS:'));
        $minutes = max(1, $this->intAfter($prompt, 'MINUTES PER DAY:'));
        // The same table the prompt and the scheduler use — a fake that invented its own
        // budget would let a scheduler/validator disagreement through every test.
        $budget = $minutes >= 40 ? 16 : ($minutes <= 10 ? 5 : 9);

        $introDays = max(1, $days - 1);
        $out = [];
        for ($i = 1; $i <= $introDays; $i++) {
            $out[] = [
                'index' => $i,
                'title' => "День {$i} — сделать шаг",
                'term_budget' => $budget,
                'outcome' => ["сказать вещь {$i}", "спросить вещь {$i}"],
                'topics' => ["область {$i}"],
                'role' => [
                    'name' => 'собеседник',
                    'opening_lines' => [['text' => 'Hello?', 'translation' => 'Здравствуйте?']],
                    'checkpoints' => ["слышно, как он говорит вещь {$i}", "слышно, как он спрашивает вещь {$i}"],
                    'if_silent' => 'переспрашивает проще',
                ],
            ];
        }

        return [
            'title' => 'Тестовый план',
            'goal_restated' => 'Цель, пересказанная одной строкой',
            'entities' => [],
            'constraints' => [],
            'goal_terms' => [],
            'single_day' => $days === 1,
            'days' => $out,
            'final_day' => ['index' => $days, 'same_day' => $days === 1, 'title' => 'Прогон перед событием'],
            'estimated_terms' => $introDays * $budget,
        ];
    }

    /** @return array<string, mixed> */
    private function day(string $prompt): array
    {
        $phrases = max(1, $this->intAfter($prompt, 'TERM BUDGET:', '('));
        $words = max(0, $this->intAfter($prompt, 'phrases +'));
        $checkpoints = max(1, substr_count($prompt, 'слышно, как'));

        $lines = [];
        for ($i = 1; $i <= $phrases; $i++) {
            $lines[] = [
                'text' => "This is reply number {$i}.",
                'type' => 'phrase',
                'is_line' => true,
                'translation' => "Это реплика номер {$i}.",
                'transliteration' => 'зис из риплай намбер',
                'description' => "Somebody says it at moment {$i} of the conversation.",
                'example' => "This is reply number {$i}, said out loud.",
                'example_translation' => "Это реплика номер {$i}, сказанная вслух.",
                // Spread over the checkpoints so every one of them is closed.
                'covers_checkpoint' => (($i - 1) % $checkpoints) + 1,
            ];
        }

        $substitutions = [];
        for ($i = 1; $i <= $words; $i++) {
            $substitutions[] = [
                'text' => "wordnumber{$i}",
                'type' => 'word',
                'is_line' => false,
                'translation' => "словономер{$i}",
                'transliteration' => 'уорднамбер',
                'description' => "A thing you drop into a sentence, number {$i}.",
                'example' => "I used wordnumber{$i} in a sentence.",
                'example_translation' => "Я употребил словономер{$i} в предложении.",
                'covers_checkpoint' => null,
            ];
        }

        return [
            'day_index' => 1,
            'day_title' => 'Тестовый день',
            'phrases' => $lines,
            'words' => $substitutions,
            'known' => [],
        ];
    }

    /** The first integer after `$marker` (and after `$then`, when given). */
    private function intAfter(string $text, string $marker, ?string $then = null): int
    {
        $at = strpos($text, $marker);
        if ($at === false) {
            return 0;
        }

        $tail = substr($text, $at + strlen($marker), 200);
        if ($then !== null) {
            $thenAt = strpos($tail, $then);
            $tail = $thenAt === false ? $tail : substr($tail, $thenAt);
        }

        return preg_match('/\d+/', $tail, $m) === 1 ? (int) $m[0] : 0;
    }
}
