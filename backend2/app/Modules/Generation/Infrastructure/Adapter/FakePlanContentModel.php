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
 * failure path. So this one obeys the numbers it is given (exactly `phrase_count` lines,
 * `word_count` words and `chunk_count` connectors, every checkpoint closed by a line, every
 * substitution standing in one of the day's frames, no example equal to any term, no two keys the
 * same) and produces nonsense CONTENT, which is what a fake is for.
 */
final class FakePlanContentModel implements ContentModelPort
{
    /**
     * The shape of every fake skeleton: two scenes, two abilities each, four terms apiece.
     *
     * Named constants and not literals because the scheduling tests read them back — «need is
     * 16» is a fact about this double, and a test that hard-codes 16 while the double says
     * something else is a test measuring nothing.
     */
    public const FAKE_SCENES = 2;

    /**
     * How a test asks for a BIGGER skeleton: `[scenes:5]` anywhere in the goal text.
     *
     * The fake cannot infer the size of a plan from anything else any more — P1 v0.2 is not told
     * the days or the minutes, so the only input it has is the goal. A test that needs a long plan
     * (more introduction days than {@see \App\Modules\Learning\Domain\Service\PlanGenerationPolicy::EAGER_INTRO_DAYS})
     * needs a goal that is honestly bigger, and this is how it says so out loud instead of moving
     * the event date and hoping.
     */
    private const SCENES_MARKER = '/\[scenes:(\d)\]/';

    public const FAKE_SKILLS_PER_SCENE = 2;

    public const FAKE_EST_TERMS = 4;

    /** The top of the range P1 is allowed to price an ability at — what a marked goal uses. */
    public const FAKE_EST_TERMS_MAX = 8;

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

    /**
     * A v0.2 SKELETON: scenes with priced abilities, and not one number about the calendar.
     *
     * It no longer reads `DAYS:` or `MINUTES PER DAY:` out of the prompt, because P1 v0.2 is not
     * told either — that is the whole change. What it does read is the goal, so a test that asks
     * for two different plans gets two different titles and the coherence gate has something to
     * work with.
     *
     * The prices are what make the arithmetic testable: four scenes-worth of abilities at 4 terms
     * each is a `need` of 16, which the scheduler turns into a day count from the learner's
     * minutes. A fake that priced everything at 1 would make every plan one day long and every
     * scheduling test vacuous.
     *
     * @return array<string, mixed>
     */
    private function outline(string $prompt): array
    {
        $goal = $this->after($prompt, 'GOAL:');
        $marked = preg_match(self::SCENES_MARKER, $goal, $m) === 1;
        $sceneCount = $marked ? max(1, min(5, (int) $m[1])) : self::FAKE_SCENES;
        // A goal big enough to need five scenes has abilities at the top of the range too. Without
        // this, «больше сцен» would not buy more DAYS — the days come from the sum of the prices,
        // and five cheap scenes still fit in three days.
        $estTerms = $marked ? self::FAKE_EST_TERMS_MAX : self::FAKE_EST_TERMS;
        // The marker is a TEST directive, not content, so it never reaches the skeleton — the
        // outline gate refuses a Latin word on the screen the learner reads, and it is right to.
        $goal = trim((string) preg_replace(self::SCENES_MARKER, '', $goal));

        $scenes = [];
        for ($scene = 1; $scene <= $sceneCount; $scene++) {
            $skills = [];
            for ($skill = 1; $skill <= self::FAKE_SKILLS_PER_SCENE; $skill++) {
                $skills[] = [
                    'outcome' => "сказать вещь {$scene}.{$skill}",
                    // The wording matters to the fake DAY below, which counts checkpoints by
                    // looking for it. Two doubles that disagree about the shape of a plan produce
                    // a day whose checkpoints nothing closes.
                    'checkpoint' => "слышно, как он говорит вещь {$scene}.{$skill}",
                    'est_terms' => $estTerms,
                    'topics' => ["область {$scene}"],
                ];
            }

            $scenes[] = [
                'title' => "Сцена {$scene} — сделать шаг",
                'role' => [
                    'name' => 'собеседник',
                    'opening_lines' => [
                        ['text' => 'Hello?', 'translation' => 'Здравствуйте?'],
                        ['text' => 'And then?', 'translation' => 'А дальше?'],
                    ],
                    'if_silent' => 'переспрашивает проще',
                ],
                'skills' => $skills,
            ];
        }

        return [
            'title' => $goal === '' ? 'Тестовый план' : mb_substr('План: ' . $goal, 0, 60),
            'goal_restated' => 'Цель, пересказанная одной строкой',
            'entities' => [],
            'constraints' => [],
            'goal_terms' => [],
            'scenes' => $scenes,
        ];
    }

    /**
     * A v0.3 DAY: three arrays, and lines that are a FRAME and a FILLER rather than a sentence.
     *
     * The three counts are read back out of the rendered prompt, because that is where the server
     * put them and reading them back is also a check that it did. The frames matter as much as the
     * counts: the validator refuses a word whose example is not one of the day's frames with that
     * word in the hole, so a double that ignored frames would make every feature test a test of
     * the failure path.
     *
     * v0.3 moved the assembly to the server, and this double moved with it — it writes no `text`
     * on a line at all, and its fillers are the day's own word and connector cards, character for
     * character, because that is what the gate demands of a real answer.
     *
     * @return array<string, mixed>
     */
    private function day(string $prompt): array
    {
        $phrases = max(1, $this->intAfter($prompt, 'TERM BUDGET:', '('));
        $words = max(0, $this->intAfter($prompt, 'lines +'));
        $chunks = max(0, $this->intAfter($prompt, 'words +'));
        $checkpoints = max(1, substr_count($prompt, 'слышно, как'));
        // WHICH DAY this is, read out of the day JSON the server put in the prompt.
        //
        // Every term the fake produced used to be «This is reply number 1» whatever day asked for
        // it — and terms are GLOBALLY DEDUPLICATED, so day 2 imported day 1's words and came out as
        // the same nine terms. Every stage a learner closed on day 1 was therefore also closed on
        // day 2, and the plan's focus jumped two days on one sitting. The real model does not do
        // that (and from PLAN-1b the coherence validator refuses a day that does), so the fake must
        // not either: a double whose output breaks an invariant tests the invariant, not the code.
        $day = max(1, $this->intAfter($prompt, '"index":'));

        // THE CARDS FIRST, because a line's `filler` has to be one of them, character for
        // character. Words then connectors, so the first filler is a word whenever the day has
        // one — a connector standing in the first frame's hole would make its own example a clone
        // of the line it fills.
        $cards = [];
        for ($i = 1; $i <= $words; $i++) {
            $cards[] = ["day{$day}word{$i}", "день{$day}слово{$i}", 'word'];
        }
        for ($i = 1; $i <= $chunks; $i++) {
            $cards[] = ["day{$day} chunk {$i}", "день{$day} связка {$i}", 'phrasal_verb'];
        }

        $lines = [];
        for ($i = 1; $i <= $phrases; $i++) {
            // A day with no substitutions at all has nothing to paste, so its lines are formulas.
            // That is over the formula cap and the cap is a WARNING since v0.3, which is exactly
            // the behaviour this double should exercise: the day is written anyway.
            $filler = $cards === [] ? '' : $cards[($i - 1) % count($cards)][0];
            $lines[] = [
                'frame' => $cards === []
                    ? "Day {$day} line {$i}."
                    : "Day {$day} line {$i} about ___.",
                'filler' => $filler,
                'speaker' => 'learner',
                'type' => 'phrase',
                'is_line' => true,
                'translation' => "День {$day}, реплика номер {$i}.",
                'transliteration' => 'дэй лайн эбаут',
                'description' => "Somebody says it at moment {$i} of conversation {$day}.",
                'example' => "Day {$day} line {$i} said out loud, about {$filler}.",
                'example_translation' => "День {$day}, реплика номер {$i}, сказанная вслух.",
                'image_api_prompt' => "Two people talking at moment {$i} of a day, close-up.",
                // Spread over the checkpoints so every one of them is closed.
                'covers_checkpoint' => (($i - 1) % $checkpoints) + 1,
            ];
        }

        // EVERY substitution stands in the FIRST frame of the day — a word in its hole, a
        // connector in its hole with a word after it, so that neither example is a clone of the
        // line that frame actually assembles into.
        $substitution = fn (string $text, string $key, string $type, string $tail): array => [
            'text' => $text,
            'type' => $type,
            'is_line' => false,
            'translation' => $key,
            'transliteration' => 'дэй уорд',
            'description' => "A thing you drop into a sentence on day {$day}.",
            'example' => "Day {$day} line 1 about {$text}{$tail}.",
            'example_translation' => "День {$day}, реплика номер 1, про «{$key}».",
            'image_api_prompt' => 'A single object on a table, close-up, no text.',
            'covers_checkpoint' => null,
        ];

        $substitutions = [];
        for ($i = 1; $i <= $words; $i++) {
            $substitutions[] = $substitution("day{$day}word{$i}", "день{$day}слово{$i}", 'word', ' again');
        }

        $connectors = [];
        for ($i = 1; $i <= $chunks; $i++) {
            $connectors[] = $substitution("day{$day} chunk {$i}", "день{$day} связка {$i}", 'phrasal_verb', ' today');
        }

        return [
            'day_index' => $day,
            'day_title' => "Тестовый день {$day}",
            'phrases' => $lines,
            'words' => $substitutions,
            'chunks' => $connectors,
            'known' => [],
        ];
    }

    /** The rest of the line after `$marker`, trimmed. */
    private function after(string $text, string $marker): string
    {
        $at = strpos($text, $marker);
        if ($at === false) {
            return '';
        }

        $tail = substr($text, $at + strlen($marker));
        $line = strtok($tail, "\n");

        return $line === false ? '' : trim($line);
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
