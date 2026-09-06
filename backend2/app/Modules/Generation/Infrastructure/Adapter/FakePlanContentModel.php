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
        $properties = is_array($properties) ? $properties : [];
        $isDay = isset($properties['pairs']) || isset($properties['hear']);
        // P2R. This double never reaches it — its days pass every gate — and the branch exists so
        // that a day which somehow does not comes back as an empty repair rather than as a
        // SKELETON, which is what «anything that is not a day» used to mean here.
        $isRepair = isset($properties['cards']);

        if ($isRepair) {
            return $this->answer(['cards' => []]);
        }
        // P2J — the judge of one pair. The fake's own pairs fit by construction, and a court that
        // said otherwise would make every feature test a test of the rewrite path.
        if (isset($properties['fits'])) {
            // v0.2: four answers and a summary; the court reads the four ({@see PlanPairCourt::verdictOf()}).
            return $this->answer([
                'answers' => true,
                'not_clarification' => true,
                'level_fits' => true,
                'translation_exact' => true,
                'fits' => true,
                'reason' => 'fake: fits',
            ]);
        }
        // P2P — never reached while the judge above says yes; answers with a line of the right
        // shape so a test that scripts a «no» elsewhere still gets a card back.
        if (isset($properties['frame']) && ! $isDay) {
            return $this->answer([
                'skill_ref' => 's1.1',
                'frame' => 'Fake rewritten line.',
                'filler' => '',
                'translation' => 'Фейковая переписанная реплика.',
                'transliteration' => 'фейк',
                'speaking_keys' => ['fake rewritten'],
            ]);
        }

        return $this->answer($isDay ? $this->day($prompt->text) : $this->outline($prompt->text));
    }

    /** @param array<string, mixed> $payload */
    private function answer(array $payload): ModelAnswer
    {
        return new ModelAnswer(
            payload: $payload,
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

        // ONE ROLE LINE PER PAIR: three questions for the three replies, two invitations for the two
        // questions the learner asks (P2 v0.6 — an `ask` pair's role line is an invitation). FIVE
        // pairs, the top of the prompt's own guide (4–5): a day of one sitting ≤ 40 cards has to
        // hold the scene met AND spoken (DAY-FIX-2, Ч.2) with room for a seam behind it.
        $hear = [];
        for ($i = 1; $i <= 5; $i++) {
            $hear[] = [
                'kind' => 'line',
                'skill_ref' => $skill($i - 1),
                // The fourth word stands on the words shelf alone, so the role lines carry it:
                // since v0.7 a word that stands in no line of the scene is DROPPED by the server
                // ({@see \App\Modules\Generation\Application\Service\PlanDayComposer::pruneUnspoken()}),
                // and a fake day that lost a card would fail every count a test makes.
                'frame' => $i <= 3
                    ? "Day {$day} question {$i}{$mark} about {$words[3][0]}, please?"
                    : "Day {$day} anything to ask{$mark}, part " . ($i - 3) . '?',
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
        // Three replies for four words: the fourth word lives on the words shelf alone, which is
        // what keeps the day at five pairs.
        foreach (array_slice($words, 0, 3) as $i => [$text, $key]) {
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
                // v0.7: a spoken line carries 1–2 simpler forms that also count when spoken.
                'speaking_keys' => ["about {$text}"],
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
                'speaking_keys' => ["where {$text}"],
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
            'pairs' => self::pairs($hear, $say, $ask),
            'words' => $wordCards,
            'chunks' => $chunkCards,
            'numbers' => $numbers,
        ];
    }

    /**
     * THE FAKE SCENE AS PAIRS (P2 v0.6) — one exchange per reply, `answer` for a `say` line and
     * `ask` for an `ask` line, the role lines taken round-robin when the replies outnumber them.
     *
     * Built rather than hand-written for the same reason the shelves are: the counts move with the
     * `[tag:…]` and `[scenes:…]` marks, and a literal list would go stale the first time one of
     * them changed. Every pair has its OWN role line — the composer lays each pair's role onto its
     * own `hear[i]`, so a role line shared by two pairs would be two cards with one text and
     * `card.clone` would refuse the day. Four questions plus two invitations is what six
     * exchanges need.
     *
     * @param  list<array<string, mixed>>  $hear  role lines, at least as many as replies
     * @param  list<array<string, mixed>>  $say
     * @param  list<array<string, mixed>>  $ask
     * @return list<array<string, mixed>>
     */
    private static function pairs(array $hear, array $say, array $ask): array
    {
        $out = [];
        $position = 0;
        foreach ($say as $you) {
            $out[] = ['kind' => 'answer', 'role' => $hear[$position++], 'you' => $you];
        }
        foreach ($ask as $you) {
            $out[] = ['kind' => 'ask', 'role' => $hear[$position++], 'you' => $you];
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
