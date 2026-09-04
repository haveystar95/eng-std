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
 * failure path. So this one obeys the v0.4 contract: six shelves, a `skill_ref` on every card taken
 * from the scene it was handed, frames whose fillers are cards of the same day, examples that are
 * neither terms nor each other's skeleton, and a number whose value is actually said in its line.
 * The CONTENT is nonsense, which is what a fake is for.
 *
 * ## What changed with v0.4, and why the double had to move with it
 *
 * The day is a SCENE now, so the double reads the scene out of the prompt instead of three counts:
 * which skills exist (it must name one on every card), and which day this is (terms are globally
 * deduplicated, so day 2 writing day 1's texts would make the two days one day — the defect the
 * coherence gate used to catch and `card.clone` catches now).
 */
final class FakePlanContentModel implements ContentModelPort
{
    /**
     * The shape of every fake skeleton: two scenes, two abilities each, four terms apiece.
     *
     * Named constants and not literals because the scheduling tests read them back — «two scenes is
     * two teaching days» is a fact about this double, and a test that hard-codes it while the double
     * says something else is a test measuring nothing.
     */
    public const FAKE_SCENES = 2;

    /**
     * How a test asks for a BIGGER skeleton: `[scenes:5]` anywhere in the goal text.
     *
     * Since v0.4 this is also how a test asks for more DAYS, and it is the only way: one day is one
     * scene, so a plan that needs four teaching days needs four scenes. Moving the event date buys
     * room and never material.
     */
    private const SCENES_MARKER = '/\[scenes:(\d)\]/';

    /**
     * How a test asks for a plan whose words are ITS OWN: `[tag:2]` anywhere in the goal text.
     *
     * Terms are globally deduplicated, and this double names every card after its day. Two plans of
     * one learner therefore stood on the SAME term rows, and «did a card of plan A get into plan
     * B's lesson» could not be asked at all. DIGITS, because the mark rides both sides of the card:
     * a Latin letter inside the Russian half would be a key in the wrong language.
     */
    private const TAG_MARKER = '/\[tag:(\d{1,3})\]/';

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
        $isDay = is_array($properties) && isset($properties['hear']);
        // P2R. This double never reaches it — its days pass every gate — and the branch exists so
        // that a day which somehow does not comes back as an empty repair rather than as a
        // SKELETON, which is what «anything that is not a day» used to mean here.
        $isRepair = is_array($properties) && isset($properties['cards']);

        if ($isRepair) {
            return new ModelAnswer(
                payload: ['cards' => []],
                model: 'fake-plan',
                latencyMs: 0,
                tokensIn: 0,
                tokensOut: 0,
                costUsd: '0.000000',
                raw: '{}',
            );
        }

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
     * A v0.4 SKELETON: scenes with a вводка, skills with ids, and not one number about the calendar.
     *
     * @return array<string, mixed>
     */
    private function outline(string $prompt): array
    {
        $goal = $this->goalIn($prompt);
        $marked = preg_match(self::SCENES_MARKER, $goal, $m) === 1;
        $sceneCount = $marked ? max(1, min(5, (int) $m[1])) : self::FAKE_SCENES;
        $estTerms = $marked ? self::FAKE_EST_TERMS_MAX : self::FAKE_EST_TERMS;
        // The markers are TEST directives, not content, so they never reach the skeleton — the
        // outline gate refuses a Latin word on the screen the learner reads, and it is right to.
        $goal = trim((string) preg_replace([self::SCENES_MARKER, self::TAG_MARKER], '', $goal));

        $scenes = [];
        for ($scene = 1; $scene <= $sceneCount; $scene++) {
            $skills = [];
            for ($skill = 1; $skill <= self::FAKE_SKILLS_PER_SCENE; $skill++) {
                $skills[] = [
                    'id' => "s{$scene}.{$skill}",
                    'outcome' => "сказать вещь {$scene}.{$skill}",
                    // The wording matters to the fake DAY below, which counts skills by looking for
                    // it. Two doubles that disagree about the shape of a plan produce a day whose
                    // skills nothing serves.
                    'checkpoint' => "слышно, как он говорит вещь {$scene}.{$skill}",
                    'est_terms' => $estTerms,
                    'topics' => ["область {$scene}"],
                ];
            }

            $scenes[] = [
                'position' => $scene,
                'title' => "Сцена {$scene} — сделать шаг",
                // In the SUPPORT language and with no target-language word in it: the вводка is the
                // screen a person with zero English reads before committing.
                'intro' => "Ты приходишь на место {$scene}. С тобой заговорит собеседник и задаст "
                    . 'пару вопросов. Успех — если ты ответил и понял ответ.',
                'skills' => $skills,
                'opening_lines' => ['Hello?', 'And then?'],
                'entities' => [],
            ];
        }

        return [
            'goal_summary' => $goal === '' ? 'Тестовый план' : mb_substr('План: ' . $goal, 0, 60),
            'scenes' => $scenes,
        ];
    }

    /**
     * A v0.4 DAY: six shelves, every card naming a skill of the scene it was handed.
     *
     * The sizes are the guides' own middles, so a fake day raises no size counter; the fillers are
     * the day's own words and connectors, character for character, because that is what the gate
     * demands of a real answer.
     *
     * @return array<string, mixed>
     */
    private function day(string $prompt): array
    {
        $skills = $this->skillIdsIn($prompt);
        $skill = static fn (int $i): string => $skills === [] ? 's1.1' : $skills[$i % count($skills)];

        // WHICH DAY this is. One day is one scene, and the scene's own title carries its number —
        // the only place the day prompt says it, since v0.4 hands over a scene rather than a day.
        $day = $this->sceneNumberIn($prompt);
        $tag = $this->tagIn($prompt);
        $mark = $tag === '' ? '' : " {$tag}";

        $words = [];
        $chunks = [];
        for ($i = 1; $i <= 4; $i++) {
            $words[] = ["{$tag}day{$day}word{$i}", "{$tag}день{$day}слово{$i}"];
        }
        for ($i = 1; $i <= 2; $i++) {
            $chunks[] = ["{$tag}day{$day} chunk {$i}", "{$tag}день{$day} связка {$i}"];
        }

        $hear = [];
        for ($i = 1; $i <= 4; $i++) {
            $hear[] = [
                'kind' => 'line',
                'skill_ref' => $skill($i - 1),
                'frame' => "Day {$day} question {$i}{$mark}, please?",
                'filler' => '',
                'speaker' => 'role',
                // THE TAG BELONGS IN THE TRANSLATION TOO, like it does on every other shelf. Without
                // it two plans of one fixture write the SAME Russian for their role lines, and a
                // scope test that checks isolation by TEXT reads one plan's own option as the
                // other's leak — which is exactly what happened the day «Тебе скажут» got a card
                // whose options are translations (situational_hear, наряд SIT-1).
                'translation' => "{$tag}День {$day}, вопрос собеседника номер {$i}.",
                'transliteration' => 'дэй куэсчен',
            ];
        }

        $say = [];
        foreach ($words as $i => [$text, $key]) {
            $say[] = [
                'kind' => 'line',
                'skill_ref' => $skill($i),
                'frame' => "Day {$day} line " . ($i + 1) . "{$mark} about ___.",
                'filler' => $text,
                // The gate added by PLAN-FIX-4 refuses a line whose translation renders everything
                // except the word it drills, and it is right to: the learner reads that Russian and
                // has no way to know what to say.
                'translation' => 'День ' . $day . ', реплика номер ' . ($i + 1) . ", про «{$key}».",
                'transliteration' => 'дэй лайн эбаут',
            ];
        }

        $ask = [];
        foreach ($chunks as $i => [$text, $key]) {
            $ask[] = [
                'kind' => 'line',
                'skill_ref' => $skill($i),
                'frame' => 'Where is ___?',
                'filler' => $text,
                'translation' => "Где находится «{$key}»?",
                'transliteration' => 'уэа из',
            ];
        }

        $wordCards = [];
        foreach ($words as $i => [$text, $key]) {
            $wordCards[] = [
                'kind' => 'word',
                'skill_ref' => $skill($i),
                'text' => $text,
                'translation' => $key,
                'transliteration' => 'дэй уорд',
                // A sentence of its own: not the term, not a line of the day, and not another
                // card's sentence with this term swapped in ({@see ExampleSkeleton}).
                'example' => "Day {$day} moment " . ($i + 1) . " with {$text} in it.",
                'example_translation' => "День {$day}, момент " . ($i + 1) . ", про «{$key}».",
                'image_api_prompt' => 'A single object on a table, close-up, no text.',
            ];
        }

        $chunkCards = [];
        foreach ($chunks as $i => [$text, $key]) {
            $chunkCards[] = [
                'kind' => 'chunk',
                'skill_ref' => $skill($i),
                'text' => $text,
                'translation' => $key,
                'transliteration' => 'дэй чанк',
                'example' => "Day {$day} corner " . ($i + 1) . " where {$text} happens.",
                'example_translation' => "День {$day}, угол " . ($i + 1) . ", где «{$key}».",
            ];
        }

        $numbers = [];
        foreach ([['twenty', '20'], ['thirty', '30']] as $i => [$spoken, $value]) {
            $numbers[] = [
                'kind' => 'number',
                'skill_ref' => $skill($i),
                // The DAY is in the line, like every other card of this double: the numbers of day 2
                // would otherwise be day 1's cards word for word, and `card.clone` would refuse the
                // day — correctly, which is how this defect was found.
                'frame' => "Day {$day} price " . ($i + 1) . " is ___ euros.",
                'filler' => $spoken,
                'value' => $value,
                'translation' => "Это стоит {$value} евро.",
            ];
        }

        return [
            'hear' => $hear,
            'say' => $say,
            'ask' => $ask,
            'words' => $wordCards,
            'chunks' => $chunkCards,
            'numbers' => $numbers,
            'dialogue' => self::dialogue(count($hear), count($say), count($ask)),
        ];
    }

    /**
     * THE ORDER THE FAKE SCENE IS SPOKEN IN — alternating, and covering every reply (P2 v0.5).
     *
     * Built rather than hand-written for the same reason the shelves are: the counts move with the
     * `[tag:…]` and `[scenes:…]` marks, and a chain of literal refs would go stale the first time
     * one of them changed — silently, as a `day.dialogue_missing` on a day nobody edited.
     *
     * `role` turns run out before the learner's do (four questions, six replies), and the chain
     * simply stops offering them: what matters for the gate is that the sides ALTERNATE, and the
     * gate reads consecutive pairs, so a `you` turn whose `role` turn is absent is only a defect if
     * the previous turn was also a `you`. The construction below never lets that happen — it emits
     * a `role` turn before every reply and takes the questions round-robin, which is also what a
     * real scene with more replies than questions does.
     *
     * @return list<array{turn: string, ref: string}>
     */
    private static function dialogue(int $hear, int $say, int $ask): array
    {
        if ($hear === 0) {
            return [];
        }

        $out = [];
        $replies = [];
        for ($i = 0; $i < $say; $i++) {
            $replies[] = "say[{$i}]";
        }
        for ($i = 0; $i < $ask; $i++) {
            $replies[] = "ask[{$i}]";
        }

        foreach ($replies as $position => $ref) {
            $out[] = ['turn' => 'role', 'ref' => 'hear[' . ($position % $hear) . ']'];
            $out[] = ['turn' => 'you', 'ref' => $ref];
        }

        return $out;
    }

    /**
     * The ids of the scene's skills, read back out of the prompt the server rendered.
     *
     * Reading them rather than inventing them is also a CHECK that the server put them there: a
     * brief with no ids would make every card of every fake day name a skill that does not exist,
     * and the suite would say so loudly.
     *
     * @return list<string>
     */
    private function skillIdsIn(string $prompt): array
    {
        if (preg_match_all('/"id"\s*:\s*"([^"]+)"/u', $prompt, $matches) === false) {
            return [];
        }

        return array_values(array_unique($matches[1]));
    }

    /** Which scene — and therefore which day — this prompt is for. */
    private function sceneNumberIn(string $prompt): int
    {
        return preg_match('/Сцена (\d+)/u', $prompt, $m) === 1 ? max(1, (int) $m[1]) : 1;
    }

    /**
     * The plan's own mark, or «» when the goal carries none.
     *
     * Read off the WHOLE prompt rather than off a `GOAL:` line: the template renders the goal
     * wherever it renders it, and this double must not depend on where.
     */
    private function tagIn(string $prompt): string
    {
        return preg_match(self::TAG_MARKER, $prompt, $m) === 1 ? $m[1] : '';
    }

    /**
     * The learner's goal, as P1 v0.4 renders it — «- Goal, in the user's own words (Russian): …».
     *
     * Matched on the line rather than on a bare marker, because the line carries the support
     * language in brackets between the two and a substring cut would hand the fake «(Russian): …»
     * as the goal.
     */
    private function goalIn(string $prompt): string
    {
        if (preg_match('/Goal, in the user\'s own words[^:]*:\s*(.*)/u', $prompt, $m) === 1) {
            return trim($m[1]);
        }

        return preg_match('/^\s*-?\s*Goal:\s*(.*)$/mu', $prompt, $m) === 1 ? trim($m[1]) : '';
    }
}
