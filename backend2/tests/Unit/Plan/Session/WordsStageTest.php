<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Domain\Assembly\Audio;
use App\Modules\Plan\Domain\Assembly\CardDraft;
use App\Modules\Plan\Domain\Assembly\Rotation;
use App\Modules\Plan\Domain\Assembly\SceneMaterial;
use App\Modules\Plan\Domain\Assembly\WordCards;
use App\Modules\Plan\Domain\Assembly\WordsStage;
use App\Modules\Plan\Domain\Check\Language\LanguagePack;
use App\Modules\Plan\Domain\Entity\PlanTerm;
use App\Modules\Plan\Domain\Lesson\LessonAssembly;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\Service\Shuffle;
use App\Modules\Plan\Domain\ValueObject\CardKind;
use App\Modules\Plan\Domain\ValueObject\CardSource;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Domain\ValueObject\PlanTermId;
use App\Modules\Plan\Domain\ValueObject\UnitKind;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;

/**
 * «СЛОВА» (наряд SESSION-1a, разд. 1–2; D-07…D-10): three cards per word spaced A_i, B_{i−1}, C_{i−2}; the check of a
 * chunk is its assembly, a single word rotates choose → listen → in line from a seeded place; a check the word cannot
 * have becomes word_choose; the options of every choice are distinct and name the right one by id; each kind carries
 * exactly its keys.
 *
 * The clean fake lesson's eight terms: v1 «lower back», v2 «sharp», v3 «fever», v4 «muscle strain», v5 «heating pad»,
 * v6 «X-ray», v7 «follow-up appointment», v8 «sick note» — three single words (v2, v3, v6), every one of them said
 * by a line of the day; «sick note» is said by none.
 */

const S1W_SCENE = '01J8SESS10NW0RDS0000000000';

/**
 * The served clean lesson of a FIXED scene (seeds depend on the scene id), optionally edited before it is parsed,
 * with only some of its terms, or with another target pack.
 *
 * @param  (callable(array<string, mixed>): array<string, mixed>)|null  $edit
 * @param  list<string>|null  $only  the refs of the terms the material keeps
 */
function s1wScene(?callable $edit = null, ?array $only = null, string $sceneId = S1W_SCENE, ?LanguagePack $target = null): SceneMaterial
{
    $id = PlanSceneId::fromString($sceneId);
    $packs = lessonPacks();
    $payload = FakePlanModel::lessonPayload(new LessonRequest('Приём у врача', 'x', 'English', 'Russian', PlanLevel::Intermediate, null, 8, 8));
    if ($edit !== null) {
        $payload = $edit($payload);
    }
    $lesson = LessonAssembly::serve((new LessonParser)->parse($payload), $id->value, $packs->for('en'));
    $terms = PlanTerm::fromLesson($id, $lesson, static fn (): PlanTermId => PlanTermId::generate());
    if ($only !== null) {
        $terms = array_values(array_filter($terms, static fn (PlanTerm $t): bool => in_array($t->ref(), $only, true)));
    }

    return new SceneMaterial($id, $lesson, $terms, $target ?? $packs->for('en'), $packs->for('ru'));
}

/** @return list<CardDraft> */
function s1wDeal(SceneMaterial $scene, PlanLevel $level = PlanLevel::Intermediate, array $topUp = []): array
{
    return (new WordsStage)->build($scene, $level, $topUp);
}

/**
 * The check card (the third) of every term, by ref.
 *
 * @param  list<CardDraft>  $drafts
 * @return array<string, CardDraft>
 */
function s1wChecks(array $drafts): array
{
    $out = [];
    foreach ($drafts as $draft) {
        if (! in_array($draft->kind, [CardKind::WordIntro, CardKind::WordRepeat], true)) {
            $out[$draft->unitRef] = $draft;
        }
    }

    return $out;
}

/** @param list<CardDraft> $drafts */
function s1wFirst(array $drafts, CardKind $kind): CardDraft
{
    foreach ($drafts as $draft) {
        if ($draft->kind === $kind) {
            return $draft;
        }
    }
    throw new RuntimeException("No {$kind->value} dealt.");
}

/** @param array<string, mixed> $payload */
function s1wEditTerm(array $payload, string $ref, array $fields): array
{
    foreach ($payload['vocabulary'] as $i => $item) {
        if ($item['id'] === $ref) {
            $payload['vocabulary'][$i] = [...$item, ...$fields];
        }
    }

    return $payload;
}

/**
 * Every choice of a card is distinct by text (case and spaces aside), numbered o1… in the order shown, the right one
 * among them exactly once; returns the right option.
 *
 * @param  array<string, mixed>  $payload
 * @return array<string, mixed>
 */
function s1wAssertOptions(array $payload, string $label): array
{
    $options = $payload['options'];
    $texts = array_map(static fn (array $o): string => mb_strtolower(trim($o['text'])), $options);
    $ids = array_column($options, 'id');

    expect($ids)->toBe(array_map(static fn (int $i): string => 'o'.$i, range(1, count($options))), $label)
        ->and(array_unique($texts))->toHaveCount(count($texts), $label)
        ->and(array_filter($texts, static fn (string $t): bool => $t === ''))->toBe([], $label)
        ->and(array_filter($ids, static fn (string $id): bool => $id === $payload['correct']))->toHaveCount(1, $label);

    return array_column($options, null, 'id')[$payload['correct']];
}

it('deals three cards per word, spaced A_i, B_i−1, C_i−2, the first eight in exactly that order', function () {
    $drafts = s1wDeal(s1wScene());
    $address = static fn (CardDraft $d): string => $d->kind->value.':'.$d->unitRef;

    $byRef = [];
    foreach ($drafts as $draft) {
        $byRef[$draft->unitRef][] = $draft->kind;
        expect($draft->unitKind)->toBe(UnitKind::Word)
            ->and($draft->source)->toBe(CardSource::Today)
            ->and(array_key_first($draft->payload))->toBe('scene_id')
            ->and($draft->payload['scene_id'])->toBe(S1W_SCENE);
    }

    expect($drafts)->toHaveCount(24)
        ->and(array_keys($byRef))->toBe(['v1', 'v2', 'v3', 'v4', 'v5', 'v6', 'v7', 'v8'])
        ->and(array_map(static fn (CardDraft $d): string => $address($d), array_slice($drafts, 0, 8)))->toBe([
            'word_intro:v1', 'word_intro:v2', 'word_repeat:v1', 'word_intro:v3', 'word_repeat:v2', 'word_assemble:v1',
            'word_intro:v4', 'word_repeat:v3',
        ])
        ->and(array_map(static fn (CardDraft $d): string => $address($d), array_slice($drafts, -3)))->toBe([
            'word_repeat:v8', s1wChecks($drafts)['v7']->kind->value.':v7', s1wChecks($drafts)['v8']->kind->value.':v8',
        ]);
    foreach ($byRef as $ref => $kinds) {
        expect(array_slice($kinds, 0, 2))->toBe([CardKind::WordIntro, CardKind::WordRepeat], $ref)
            ->and($kinds)->toHaveCount(3, $ref);
    }
});

it('assembles only a term of two words or more, the target\'s articles counted by nobody', function () {
    $checks = array_map(static fn (CardDraft $d): CardKind => $d->kind, s1wChecks(s1wDeal(s1wScene())));

    expect(array_keys(array_filter($checks, static fn (CardKind $k): bool => $k === CardKind::WordAssemble)))
        ->toBe(['v1', 'v4', 'v5', 'v7', 'v8']);

    // «a fever» is one word to an English learner; a pack naming no articles counts two.
    $withArticle = static fn (array $p): array => s1wEditTerm(s1wEditTerm($p, 'v3', ['term_target' => 'a fever']), 'v5', ['term_target' => 'a heating pad']);
    $english = s1wChecks(s1wDeal(s1wScene($withArticle)));
    $noArticles = s1wChecks(s1wDeal(s1wScene($withArticle, target: LanguagePack::none('en'))));

    expect($english['v3']->kind)->not->toBe(CardKind::WordAssemble)
        ->and($noArticles['v3']->kind)->toBe(CardKind::WordAssemble)
        ->and($english['v5']->payload['expected'])->toBe(['heating', 'pad'])
        ->and($noArticles['v5']->payload['expected'])->toBe(['a', 'heating', 'pad']);

    // The tiles: the term's own words and up to three words of other terms — none of them the term's, none an article.
    foreach (s1wChecks(s1wDeal(s1wScene($withArticle))) as $ref => $check) {
        if ($check->kind !== CardKind::WordAssemble) {
            continue;
        }
        $expected = $check->payload['expected'];
        $extras = $check->payload['tiles'];
        foreach ($expected as $word) {
            $at = array_search($word, $extras, true);
            expect($at)->not->toBeFalse("{$ref}: {$word} is a tile");
            unset($extras[$at]);
        }
        $extras = array_values($extras);
        $lower = array_map('mb_strtolower', $extras);

        expect(count($extras))->toBeGreaterThan(0, $ref)->toBeLessThanOrEqual(3, $ref)
            ->and(array_intersect($lower, array_map('mb_strtolower', $expected)))->toBe([], $ref)
            ->and(array_unique($lower))->toHaveCount(count($lower), $ref)
            ->and(array_intersect($lower, ['a', 'an', 'the']))->toBe([], $ref);
    }

    $v1 = s1wChecks(s1wDeal(s1wScene()))['v1']->payload;
    expect($v1['expected'])->toBe(['lower', 'back'])
        ->and($v1['tiles'])->toHaveCount(5)
        ->and($v1['tiles'])->toBe(s1wChecks(s1wDeal(s1wScene()))['v1']->payload['tiles']);

    // Words the other terms share with this one (case aside), or with each other, are no extra tiles.
    $sharing = static fn (array $p): array => s1wEditTerm(s1wEditTerm($p, 'v4', ['term_target' => 'Back strain']), 'v8', ['term_target' => 'lower back strain pain']);
    $scene = s1wScene($sharing, only: ['v1', 'v4', 'v8']);
    $shared = (new WordCards)->assemble($scene, $scene->term('v1'))->payload;
    expect($shared['tiles'])->toEqualCanonicalizing(['lower', 'back', 'strain', 'pain']);
});

it('rotates the check of a single word from a place the scene seeds, word after word, the same way twice', function () {
    $scene = s1wScene();
    $first = s1wDeal($scene);
    $checks = s1wChecks($first);
    $seed = S1W_SCENE.':words:check';
    $cycle = WordsStage::CHECKS;

    $single = ['v2' => $checks['v2']->kind, 'v3' => $checks['v3']->kind, 'v6' => $checks['v6']->kind];

    expect($single)->toBe([
        'v2' => Rotation::pick($seed, 0, $cycle),
        'v3' => Rotation::pick($seed, 1, $cycle),
        'v6' => Rotation::pick($seed, 2, $cycle),
    ])
        ->and(array_unique(array_map(static fn (CardKind $k): string => $k->value, $single)))->toHaveCount(3)
        ->and(s1wDeal(s1wScene()))->toEqual($first);

    // Another scene starts the cycle elsewhere: over a few scenes the first single word is not always checked alike.
    $starts = [];
    foreach (['01J8SESS10NW0RDS0000000001', '01J8SESS10NW0RDS0000000002', '01J8SESS10NW0RDS0000000003', '01J8SESS10NW0RDS0000000004', '01J8SESS10NW0RDS0000000005', '01J8SESS10NW0RDS0000000006'] as $id) {
        $starts[s1wChecks(s1wDeal(s1wScene(sceneId: $id)))['v2']->kind->value] = true;
    }
    expect(count($starts))->toBeGreaterThan(1);
});

it('deals word_choose where a word has no line of the day, or no other word to be told from', function () {
    // Three single words no line says: the one the rotation sends to word_in_line is chosen instead.
    $unsaid = static fn (array $p): array => s1wEditTerm(s1wEditTerm(s1wEditTerm(
        $p, 'v2', ['term_target' => 'cough', 'used_in' => []]), 'v3', ['term_target' => 'rash', 'used_in' => []]), 'v6', ['term_target' => 'sneeze', 'used_in' => []]);
    $scene = s1wScene($unsaid);
    $drafts = s1wDeal($scene);
    $checks = s1wChecks($drafts);
    $seed = S1W_SCENE.':words:check';

    foreach (['v2' => 0, 'v3' => 1, 'v6' => 2] as $ref => $j) {
        $picked = Rotation::pick($seed, $j, WordsStage::CHECKS);
        expect($checks[$ref]->kind)->toBe($picked === CardKind::WordInLine ? CardKind::WordChoose : $picked, $ref);
    }
    $intro = array_values(array_filter($drafts, static fn (CardDraft $d): bool => $d->kind === CardKind::WordIntro && $d->unitRef === 'v2'))[0];

    expect(array_filter($drafts, static fn (CardDraft $d): bool => $d->kind === CardKind::WordInLine))->toBe([])
        ->and($intro->payload['used_in'])->toBeNull()
        ->and($intro->payload['audio'])->toBe(['term' => Audio::of('v2'), 'line' => null])
        ->and((new WordCards)->inLine($scene, $scene->term('v2')))->toBeNull();

    // A day of one word: nothing to hear it against, nothing to find it among — whatever the rotation picks, the
    // check is word_choose, on a Beginner's translations topped up from the catalogue.
    $picks = [];
    foreach (['01J8SESS10NW0RDS0000000001', '01J8SESS10NW0RDS0000000002', '01J8SESS10NW0RDS0000000003', '01J8SESS10NW0RDS0000000004'] as $id) {
        $lonely = s1wScene(only: ['v2'], sceneId: $id);
        $picks[Rotation::pick($id.':words:check', 0, WordsStage::CHECKS)->value] = true;

        expect(s1wChecks(s1wDeal($lonely, PlanLevel::Beginner, ['кашель', 'сыпь', 'насморк']))['v2']->kind)->toBe(CardKind::WordChoose, $id)
            ->and((new WordCards)->listen($lonely, $lonely->term('v2')))->toBeNull()
            ->and((new WordCards)->inLine($lonely, $lonely->term('v2')))->toBeNull();
    }
    expect(count($picks))->toBeGreaterThan(1);
});

it('deals no word_choose with nothing to choose between: the only word of an Intermediate day is met and said, and goes unchecked', function () {
    $lonely = s1wScene(only: ['v2']);
    $stage = new WordsStage;
    $kinds = static fn (array $drafts): array => array_map(static fn (CardDraft $d): string => $d->kind->value.':'.$d->unitRef, $drafts);

    // An Intermediate chooses among the day's words — there is no other one, and no catalogue stands in for it.
    expect($kinds(s1wDeal($lonely, PlanLevel::Intermediate, ['кашель', 'сыпь', 'насморк'])))->toBe(['word_intro:v2', 'word_repeat:v2'])
        ->and((new WordCards)->choose($lonely, $lonely->term('v2'), PlanLevel::Intermediate, ['кашель']))->toBeNull()
        ->and($stage->returned($lonely, $lonely->term('v2'), PlanLevel::Intermediate, []))->toBeNull();

    // A Beginner's choice has the catalogue to top it up; without it, it is the same empty choice.
    expect($kinds(s1wDeal($lonely, PlanLevel::Beginner, ['кашель'])))->toBe(['word_intro:v2', 'word_repeat:v2', 'word_choose:v2'])
        ->and($kinds(s1wDeal($lonely, PlanLevel::Beginner)))->toBe(['word_intro:v2', 'word_repeat:v2'])
        ->and($stage->returned($lonely, $lonely->term('v2'), PlanLevel::Beginner, []))->toBeNull();

    // Two words are a choice again: the same word gets its check back.
    expect(s1wChecks(s1wDeal(s1wScene(only: ['v2', 'v3']), PlanLevel::Intermediate)))->toHaveKey('v2');
});

it('offers distinct options on every choice, the right one named once by its id', function () {
    $cards = new WordCards;
    foreach ([PlanLevel::Beginner, PlanLevel::Intermediate] as $level) {
        $scene = s1wScene();
        foreach (s1wDeal($scene, $level) as $draft) {
            if (! in_array($draft->kind, [CardKind::WordChoose, CardKind::WordListen, CardKind::WordInLine], true)) {
                continue;
            }
            $label = $level->value.':'.$draft->kind->value.':'.$draft->unitRef;
            $right = s1wAssertOptions($draft->payload, $label);
            $term = $scene->term($draft->unitRef);
            expect($draft->payload['options'])->toHaveCount(4, $label)
                // The clean lesson says every single word as it is written, so the line's form is the term.
                ->and($right['text'])->toBe($level === PlanLevel::Beginner && $draft->kind === CardKind::WordChoose ? $term->textNative() : $term->textTarget(), $label);
        }
    }

    // A day of four words where one translation equals v2's but for its case, and one spelling but for case and spaces:
    // the twin is dropped, not shown twice, and the card offers what is left.
    $twins = static fn (array $p): array => s1wEditTerm(s1wEditTerm($p, 'v3', ['translation_native' => 'ОСТРАЯ']), 'v6', ['term_target' => ' Sharp ']);
    $scene = s1wScene($twins, only: ['v1', 'v2', 'v3', 'v6']);
    $beginner = $cards->choose($scene, $scene->term('v2'), PlanLevel::Beginner, [])->payload;
    $intermediate = $cards->choose($scene, $scene->term('v2'), PlanLevel::Intermediate, [])->payload;
    $listen = $cards->listen($scene, $scene->term('v2'))->payload;

    expect(s1wAssertOptions($beginner, 'beginner twins')['text'])->toBe('острая')
        ->and(array_column($beginner['options'], 'text'))->toEqualCanonicalizing(['острая', 'поясница', 'рентген'])
        ->and(s1wAssertOptions($intermediate, 'intermediate twins')['text'])->toBe('sharp')
        ->and(array_column($intermediate['options'], 'text'))->toEqualCanonicalizing(['sharp', 'lower back', 'fever'])
        ->and(s1wAssertOptions($listen, 'listen twins')['text'])->toBe('sharp')
        ->and($listen['options'])->toHaveCount(3);
});

it('plays the word in word_listen and never writes it in the question', function () {
    $scene = s1wScene();
    $listen = (new WordCards)->listen($scene, $scene->term('v7'));
    $question = $listen->payload;
    unset($question['options']);
    $right = s1wAssertOptions($listen->payload, 'listen');

    expect($listen->kind)->toBe(CardKind::WordListen)
        ->and(array_keys($listen->payload))->toBe(['scene_id', 'audio', 'options', 'correct'])
        ->and($listen->payload['audio'])->toBe(Audio::of('v7'))
        ->and(json_encode($question, JSON_UNESCAPED_UNICODE))->not->toContain('follow-up')
        ->and(json_encode($question, JSON_UNESCAPED_UNICODE))->not->toContain('повторный')
        ->and($right['text'])->toBe('follow-up appointment')
        ->and(array_map(static fn (array $o): array => array_keys($o), $listen->payload['options']))->each->toBe(['id', 'text']);
});

it('tops up a Beginner\'s translations from the catalogue only when the day has fewer than four words', function () {
    $topUp = ['кашель', 'сыпь', 'насморк'];
    $cards = new WordCards;

    $small = s1wScene(only: ['v2', 'v3']);
    $beginner = $cards->choose($small, $small->term('v2'), PlanLevel::Beginner, $topUp)->payload;
    expect(array_column($beginner['options'], 'text'))->toEqualCanonicalizing(['острая', 'температура', 'кашель', 'сыпь'])
        ->and(array_column($cards->choose($small, $small->term('v2'), PlanLevel::Beginner, [])->payload['options'], 'text'))
        ->toEqualCanonicalizing(['острая', 'температура'])
        // Intermediate chooses among the day's words and never among translations.
        ->and(array_column($cards->choose($small, $small->term('v2'), PlanLevel::Intermediate, $topUp)->payload['options'], 'text'))
        ->toEqualCanonicalizing(['sharp', 'fever']);

    // The day's own words come first: a day of eight never reaches the catalogue.
    $full = s1wScene();
    $texts = array_column($cards->choose($full, $full->term('v2'), PlanLevel::Beginner, $topUp)->payload['options'], 'text');
    expect(array_intersect($texts, $topUp))->toBe([])
        ->and($texts)->toContain('острая')->toHaveCount(4);

    // …in an order the card seeds, so every word does not offer the same three wrong ones.
    $others = array_values(array_filter($full->vocabulary(), static fn (PlanTerm $t): bool => $t->ref() !== 'v2'));
    $seeded = Shuffle::seeded(S1W_SCENE.':v2:choose:others', $others);
    expect($texts)->toEqualCanonicalizing(['острая', ...array_map(static fn (PlanTerm $t): string => $t->textNative(), array_slice($seeded, 0, 3))]);
    $wrong = [];
    foreach ($full->vocabulary() as $term) {
        $payload = $cards->choose($full, $term, PlanLevel::Beginner, [])->payload;
        foreach ($payload['options'] as $option) {
            if ($option['id'] !== $payload['correct']) {
                $wrong[$option['text']] = true;
            }
        }
    }
    expect(count($wrong))->toBe(8);

    // The stage passes the top-up on to its Beginner choice cards.
    $chosen = array_values(array_filter(
        s1wDeal(s1wScene(only: ['v2', 'v3', 'v6']), PlanLevel::Beginner, $topUp),
        static fn (CardDraft $d): bool => $d->kind === CardKind::WordChoose,
    ));
    expect($chosen)->toHaveCount(1)
        ->and(array_values(array_intersect(array_column($chosen[0]->payload['options'], 'text'), $topUp)))->toBe(['кашель']);
});

it('shows a Beginner the word to hear and an Intermediate the translation, with the sound on the options', function () {
    $scene = s1wScene();
    $cards = new WordCards;

    $beginner = $cards->choose($scene, $scene->term('v2'), PlanLevel::Beginner, [])->payload;
    expect($beginner['direction'])->toBe('term_to_native')
        ->and($beginner['prompt'])->toBe(['text_target' => 'sharp', 'image' => ['url' => null, 'tone' => null], 'audio' => Audio::of('v2')])
        ->and(s1wAssertOptions($beginner, 'beginner'))->toBe(['id' => $beginner['correct'], 'text' => 'острая'])
        ->and(array_map(static fn (array $o): array => array_keys($o), $beginner['options']))->each->toBe(['id', 'text']);

    $intermediate = $cards->choose($scene, $scene->term('v2'), PlanLevel::Intermediate, [])->payload;
    $refOf = [];
    foreach ($scene->vocabulary() as $term) {
        $refOf[$term->textTarget()] = $term->ref();
    }
    expect($intermediate['direction'])->toBe('native_to_term')
        ->and($intermediate['prompt'])->toBe(['text_native' => 'острая', 'image' => ['url' => null, 'tone' => null]])
        ->and(s1wAssertOptions($intermediate, 'intermediate'))->toBe(['id' => $intermediate['correct'], 'text' => 'sharp', 'audio' => Audio::of('v2')]);
    foreach ($intermediate['options'] as $option) {
        expect(array_keys($option))->toBe(['id', 'text', 'audio'])
            ->and($option['audio'])->toBe(Audio::of($refOf[$option['text']]));
    }
});

it('returns a word as word_choose — the very card the day would deal it', function () {
    $scene = s1wScene();
    $stage = new WordsStage;

    foreach ([PlanLevel::Beginner, PlanLevel::Intermediate] as $level) {
        $returned = $stage->returned($scene, $scene->term('v8'), $level, []);
        expect($returned->kind)->toBe(CardKind::WordChoose)
            ->and($returned->unitKind)->toBe(UnitKind::Word)
            ->and($returned->unitRef)->toBe('v8')
            ->and($returned->payload['direction'])->toBe($level === PlanLevel::Beginner ? 'term_to_native' : 'native_to_term');

        $chosen = array_values(array_filter(s1wChecks(s1wDeal($scene, $level)), static fn (CardDraft $d): bool => $d->kind === CardKind::WordChoose));
        expect($chosen)->not->toBe([])
            ->and($stage->returned($scene, $scene->term($chosen[0]->unitRef), $level, []))->toEqual($chosen[0]);
    }
});

it('gives every kind exactly its keys, and the line of the day where the word is said', function () {
    // «use a heating pad» counts three words: a long term is said by most of it.
    $scene = s1wScene(static fn (array $p): array => s1wEditTerm($p, 'v5', ['term_target' => 'use a heating pad']));
    $cards = new WordCards;
    $term = ['ref', 'text_target', 'pronunciation_native', 'text_native', 'definition_target', 'image'];

    $intro = $cards->intro($scene, $scene->term('v1'))->payload;
    expect(array_keys($intro))->toBe(['scene_id', 'term', 'used_in', 'audio'])
        ->and($intro['term'])->toBe([
            'ref' => 'v1', 'text_target' => 'lower back', 'pronunciation_native' => 'лоуэр бэк', 'text_native' => 'поясница',
            'definition_target' => 'the part of the back above the hips', 'image' => ['url' => null, 'tone' => null],
        ])
        ->and($intro['used_in'])->toBe([
            'ref' => 'p1', 'line_ref' => 'x1b', 'text_target' => 'It hurts in his lower back.', 'text_native' => 'У него болит поясница.',
            'term_span' => [16, 26],
        ])
        ->and($intro['audio'])->toBe(['term' => Audio::of('v1'), 'line' => Audio::of('x1b')])
        ->and($cards->intro($scene, $scene->term('v4'))->payload['used_in'])->toBe([
            'ref' => 'A5', 'line_ref' => 'x5', 'text_target' => 'It looks like a muscle strain, so he should rest and use a heating pad.',
            'text_native' => 'Похоже на растяжение мышцы, так что ему нужен покой и грелка.', 'term_span' => [16, 29],
        ])
        ->and($cards->intro($scene, $scene->term('v4'))->payload['audio']['line'])->toBe(Audio::of('x5'));

    $repeat = $cards->repeat($scene, $scene->term('v1'))->payload;
    expect(array_keys($repeat))->toBe(['scene_id', 'term', 'expected_text', 'coverage_min', 'audio'])
        ->and(array_keys($repeat['term']))->toBe($term)
        ->and($repeat['expected_text'])->toBe('lower back')
        ->and($repeat['coverage_min'])->toBe(1.0)
        ->and($repeat['audio'])->toBe(['term' => Audio::of('v1')])
        ->and($cards->repeat($scene, $scene->term('v5'))->payload['coverage_min'])->toBe(0.7);

    foreach ([PlanLevel::Beginner, PlanLevel::Intermediate] as $level) {
        $choose = $cards->choose($scene, $scene->term('v3'), $level, [])->payload;
        expect(array_keys($choose))->toBe(['scene_id', 'direction', 'prompt', 'options', 'correct'])
            ->and(array_keys($choose['prompt']))->toBe($level === PlanLevel::Beginner ? ['text_target', 'image', 'audio'] : ['text_native', 'image']);
    }

    $assemble = $cards->assemble($scene, $scene->term('v4'))->payload;
    expect(array_keys($assemble))->toBe(['scene_id', 'term', 'tiles', 'expected'])
        ->and(array_keys($assemble['term']))->toBe($term)
        ->and($assemble['expected'])->toBe(['muscle', 'strain']);

    $inLine = $cards->inLine($scene, $scene->term('v2'))->payload;
    $right = s1wAssertOptions($inLine, 'in line');
    expect(array_keys($inLine))->toBe(['scene_id', 'line', 'options', 'correct'])
        ->and($inLine['line'])->toBe([
            'ref' => 'p3', 'line_ref' => 'x3b', 'text_target' => 'The pain is ___ when he bends.',
            'text_native' => 'Боль острая, когда он наклоняется.', 'text_native_gapped' => 'Боль ___, когда он наклоняется.',
            'audio' => Audio::of('x3b'),
        ])
        ->and($right)->toBe(['id' => $inLine['correct'], 'text' => 'sharp', 'audio' => Audio::of('v2')])
        ->and($inLine['options'])->toHaveCount(4)
        ->and(array_map(static fn (array $o): array => array_keys($o), $inLine['options']))->each->toBe(['id', 'text', 'audio'])
        // «температура» is said «температуры» in the line: nothing to gap.
        ->and($cards->inLine($scene, $scene->term('v3'))->payload['line']['text_native_gapped'])->toBeNull()
        ->and($cards->inLine($scene, $scene->term('v3'))->payload['line']['text_target'])->toBe('No, he doesn\'t have a ___.')
        ->and($cards->inLine($scene, $scene->term('v6'))->payload['line'])->toMatchArray([
            'ref' => 'p6', 'line_ref' => 'x7b', 'text_target' => 'Do we need an ___?', 'text_native_gapped' => 'Нам нужно сделать ___?',
        ]);

    // The word as the line says it: an inflected form is the right option, not the term.
    $form = s1wScene(static fn (array $p): array => s1wEditTerm($p, 'v2', ['term_target' => 'bend', 'used_in' => ['p3']]));
    $formed = $cards->inLine($form, $form->term('v2'))->payload;
    expect(s1wAssertOptions($formed, 'form')['text'])->toBe('bends')
        ->and($formed['line']['text_target'])->toBe('The pain is sharp when he ___.');
});
