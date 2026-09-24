<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Domain\Lesson\EarlierDays;
use App\Modules\Plan\Domain\Assembly\Audio;
use App\Modules\Plan\Domain\Assembly\CardDraft;
use App\Modules\Plan\Domain\Assembly\DayAssembler;
use App\Modules\Plan\Domain\Assembly\Rotation;
use App\Modules\Plan\Domain\Assembly\SceneMaterial;
use App\Modules\Plan\Domain\Assembly\WordCards;
use App\Modules\Plan\Domain\Assembly\WordChecks;
use App\Modules\Plan\Domain\Assembly\WordsStage;
use App\Modules\Plan\Domain\Check\Language\LanguagePack;
use App\Modules\Plan\Domain\Entity\DayCard;
use App\Modules\Plan\Domain\Entity\PlanTerm;
use App\Modules\Plan\Domain\Lesson\LessonAssembly;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\Service\Shuffle;
use App\Modules\Plan\Domain\ValueObject\CardKind;
use App\Modules\Plan\Domain\ValueObject\CardSource;
use App\Modules\Plan\Domain\ValueObject\DayCardId;
use App\Modules\Plan\Domain\ValueObject\PlanDayId;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Domain\ValueObject\Stage;
use App\Modules\Plan\Domain\ValueObject\UnitKind;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;

/**
 * «СЛОВА» (наряд SESSION-1a, разд. 1–2; D-07…D-10; SESSION-1e — общий круг проверок): three cards per word spaced A_i,
 * B_{i−1}, C_{i−2}; the checks walk ONE circle choose → listen → in_line → assemble over all the words by their index from
 * a seeded start, a kind a word cannot have swapped with the nearest word that can (решение архитектора 16.09); every
 * kind twice on eight words; word_choose asks both ways at any level; word_listen is heard and answered in the learner's
 * language; each kind carries exactly its keys.
 *
 * The clean fake lesson's eight terms: v1 «lower back», v2 «sharp», v3 «fever», v4 «muscle strain», v5 «heating pad»,
 * v6 «X-ray», v7 «follow-up appointment», v8 «sick note» — three single words (v2, v3, v6), every term but «sick note»
 * said by a line of the day. The live doctor (`planLiveDoctorScene()`): v1 «fever» and v4 «paracetamol» single, six
 * chunks, every one said.
 */

/** Its circle starts with word_choose, and every word can have the kind the circle points it at. */
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
    $payload = FakePlanModel::lessonPayload(new LessonRequest('Приём у врача', 'x', 'English', 'Russian', PlanLevel::Intermediate, null, 8, 8, FakePlanModel::roles(), new EarlierDays));
    if ($edit !== null) {
        $payload = $edit($payload);
    }
    $lesson = LessonAssembly::serve((new LessonParser)->parse($payload), $id->value, $packs->for('en'));
    $terms = planTermsOf($id, $lesson);
    if ($only !== null) {
        $terms = array_values(array_filter($terms, static fn (PlanTerm $t): bool => in_array($t->ref(), $only, true)));
    }

    return new SceneMaterial($id, $lesson, $terms, $target ?? $packs->for('en'), $packs->for('ru'));
}

/**
 * @param  list<string>  $topUp
 * @return list<CardDraft>
 */
function s1wDeal(SceneMaterial $scene, array $topUp = []): array
{
    return (new WordsStage)->build($scene, $topUp);
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

/**
 * The check kind of every term, by ref.
 *
 * @param  list<CardDraft>  $drafts
 * @return array<string, string>
 */
function s1wKinds(array $drafts): array
{
    return array_map(static fn (CardDraft $d): string => $d->kind->value, s1wChecks($drafts));
}

/**
 * How many checks of each kind, all four named.
 *
 * @param  list<CardDraft>  $drafts
 * @return array<string, int>
 */
function s1wCounts(array $drafts): array
{
    $counts = array_fill_keys(array_map(static fn (CardKind $k): string => $k->value, WordChecks::CYCLE), 0);
    foreach (s1wKinds($drafts) as $kind) {
        $counts[$kind]++;
    }

    return $counts;
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
 * A seed whose circle of these kinds starts with `$start`.
 *
 * @param  list<CardKind>  $circle
 */
function s1wSeed(CardKind $start, array $circle = WordChecks::CYCLE): string
{
    for ($n = 0; ; $n++) {
        if (Rotation::pick("seed-{$n}", 0, $circle) === $start) {
            return "seed-{$n}";
        }
    }
}

/**
 * What a word can have, as the pure circle reads it.
 *
 * @param  list<CardKind>  $kinds
 * @return array<string, true>
 */
function s1wCan(CardKind ...$kinds): array
{
    return array_fill_keys(array_map(static fn (CardKind $k): string => $k->value, $kinds), true);
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

// Canon (разд. 1): «три карточки на слово, разнесение A_i, B_{i−1}, C_{i−2}». Catches a word's cards dealt side by side and
// a word without its intro or its saying.
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
            'word_intro:v1', 'word_intro:v2', 'word_repeat:v1', 'word_intro:v3', 'word_repeat:v2', 'word_choose:v1',
            'word_intro:v4', 'word_repeat:v3',
        ])
        ->and(array_map(static fn (CardDraft $d): string => $address($d), array_slice($drafts, -3)))->toBe([
            'word_repeat:v8', 'word_in_line:v7', 'word_assemble:v8',
        ]);
    foreach ($byRef as $ref => $kinds) {
        expect(array_slice($kinds, 0, 2))->toBe([CardKind::WordIntro, CardKind::WordRepeat], $ref)
            ->and($kinds)->toHaveCount(3, $ref);
    }
});

// Canon (SESSION-1e, разд. 1): «один круг seeded по индексу слова: word_choose → word_listen → word_in_line →
// word_assemble; слово получает вид, на который указал круг». Catches the old privilege of the assembly (every chunk
// assembled), a circle over the single words only, a circle out of order, a start that is not the scene's, and a day not
// the same twice.
it('walks one circle choose → listen → in_line → assemble over all the words by their index, from the scene\'s seeded start', function () {
    $scene = s1wScene();
    $kinds = s1wKinds(s1wDeal($scene));

    // Every word of this scene can have the kind it is pointed at: the circle is all there is — v1 «lower back», a chunk,
    // is chosen, and v8 «sick note», said by no line, is assembled.
    expect(Rotation::pick(S1W_SCENE.':words:check', 0, WordChecks::CYCLE))->toBe(CardKind::WordChoose)
        ->and($kinds)->toBe([
            'v1' => 'word_choose', 'v2' => 'word_listen', 'v3' => 'word_in_line', 'v4' => 'word_assemble',
            'v5' => 'word_choose', 'v6' => 'word_listen', 'v7' => 'word_in_line', 'v8' => 'word_assemble',
        ])
        ->and(s1wDeal(s1wScene()))->toEqual(s1wDeal($scene));

    // The start is the scene's: over the four starts of the circle the first word is not checked alike.
    $firsts = [];
    foreach (planCircleStarts() as $start => $id) {
        $firsts[$start] = s1wKinds(s1wDeal(s1wScene(sceneId: $id)))['v1'];
    }
    expect(count(array_unique($firsts)))->toBeGreaterThan(1);
});

// Canon (SESSION-1e + решение архитектора 16.09): «вид слову не подходит — слово меняется видом с ближайшим словом, которому
// подходит его вид и чей вид подходит ему (ближайшее — по индексу, при равном расстоянии — следующее); обменяться не с кем —
// следующий подходящий вид по кругу». Catches a word given a kind it cannot have, a swap with a word farther than the nearest,
// the previous word taken over the next one at the same distance, a swap that breaks the other word, and a word with no
// swap left without the next kind it can have.
it('swaps a kind a word cannot have with the nearest word that can — the next one between two as near — else takes the next kind round the circle', function () {
    $all = s1wCan(...WordChecks::CYCLE);
    $choose = s1wSeed(CardKind::WordChoose);

    // Word 2 cannot find itself in a line; words 1 and 3 are as near. Word 3 (assemble, which word 2 can have) is next:
    // the swap goes forward. The counts stay one kind a word.
    $forward = WordChecks::kinds([
        $all,
        s1wCan(CardKind::WordChoose, CardKind::WordListen, CardKind::WordInLine),
        s1wCan(CardKind::WordChoose, CardKind::WordListen, CardKind::WordAssemble),
        $all,
        s1wCan(CardKind::WordChoose, CardKind::WordListen, CardKind::WordInLine),
    ], $choose);
    expect($forward)->toBe([CardKind::WordChoose, CardKind::WordListen, CardKind::WordAssemble, CardKind::WordInLine, CardKind::WordChoose]);

    // Word 3 (the next) cannot have in_line; word 1 (as near) can — and word 2 can have its listen: the previous one.
    $previous = WordChecks::kinds([
        $all,
        s1wCan(CardKind::WordChoose, CardKind::WordListen, CardKind::WordInLine),
        s1wCan(CardKind::WordChoose, CardKind::WordListen, CardKind::WordAssemble),
        s1wCan(CardKind::WordChoose, CardKind::WordListen, CardKind::WordAssemble),
        s1wCan(CardKind::WordChoose, CardKind::WordListen, CardKind::WordInLine),
    ], $choose);
    expect($previous)->toBe([CardKind::WordChoose, CardKind::WordInLine, CardKind::WordListen, CardKind::WordAssemble, CardKind::WordChoose]);

    // Nobody next door can: the nearest are two away on both sides (words 0 and 4, both choose) — the next one, word 4.
    $twoAway = WordChecks::kinds([
        $all,
        s1wCan(CardKind::WordChoose, CardKind::WordListen),
        s1wCan(CardKind::WordChoose, CardKind::WordListen, CardKind::WordAssemble),
        s1wCan(CardKind::WordChoose, CardKind::WordListen, CardKind::WordAssemble),
        s1wCan(CardKind::WordChoose, CardKind::WordListen, CardKind::WordInLine),
        s1wCan(CardKind::WordChoose, CardKind::WordListen),
    ], $choose);
    expect($twoAway)->toBe([CardKind::WordChoose, CardKind::WordListen, CardKind::WordChoose, CardKind::WordAssemble, CardKind::WordInLine, CardKind::WordListen]);

    // Nobody to swap with: word 1 can have neither in_line nor assemble, and no word that can has a kind it can take.
    $alone = WordChecks::kinds([
        s1wCan(CardKind::WordChoose, CardKind::WordListen),
        s1wCan(CardKind::WordChoose, CardKind::WordListen),
        $all,
    ], s1wSeed(CardKind::WordInLine));
    // From in_line: 0 in_line → swaps with word 2 (choose); 1 assemble → nobody → the next kind round it can have, choose.
    expect($alone)->toBe([CardKind::WordChoose, CardKind::WordChoose, CardKind::WordInLine])
        // A word that can have no check at all has none.
        ->and(WordChecks::kinds([[], $all], $choose))->toBe([null, CardKind::WordListen])
        ->and(WordChecks::kinds([[], []], $choose))->toBe([null, null]);
});

// Canon (SESSION-1e): «на 8 словах каждый из четырёх видов минимум дважды, когда есть материал» + решение архитектора 16.09:
// «тест — 2/2/2/2 на фикстуре и на живом «враче» при любом старте круга». Catches the next-by-circle fallback in place of the
// swap (the fixture starting at in_line assembled nothing, the live doctor at two starts assembled once), a circle that
// loses a kind to a word that cannot have it, and a start read off something else than the scene.
it('gives every one of the four kinds two words of eight at any start of the circle — on the clean lesson and on the live doctor', function () {
    $starts = planCircleStarts();
    expect(array_keys($starts))->toEqualCanonicalizing(array_map(static fn (CardKind $k): string => $k->value, WordChecks::CYCLE));

    foreach ($starts as $start => $id) {
        foreach (['clean' => s1wScene(sceneId: $id), 'live doctor' => planLiveDoctorScene($id)] as $lesson => $scene) {
            $drafts = s1wDeal($scene);
            expect(Rotation::pick($id.':words:check', 0, WordChecks::CYCLE)->value)->toBe($start)
                ->and(s1wCounts($drafts))->toBe(['word_choose' => 2, 'word_listen' => 2, 'word_in_line' => 2, 'word_assemble' => 2], "{$lesson} from {$start}")
                ->and(count($scene->vocabulary()))->toBe(8);
        }
    }

    // The live doctor as the phone got it (six chunks, all assembled, nothing heard) — now every kind twice at its own start.
    expect(s1wCounts(s1wDeal(planLiveDoctorScene())))->toBe(['word_choose' => 2, 'word_listen' => 2, 'word_in_line' => 2, 'word_assemble' => 2]);
});

// Canon (SESSION-1e): «материала нет — вид выпадает, остальные делят поровну». Catches a kind no word can have kept in the
// circle (its words pushed onto the next kind — 4/2/2 instead of 3/3/2), and a kind dropped though a word can have it.
it('drops a kind no word of the day can have from the circle, the others sharing the words evenly', function () {
    // Eight single words, every one said by a line: nothing to assemble.
    $single = static function (array $p): array {
        foreach (['v1' => 'back', 'v4' => 'muscle', 'v5' => 'rest', 'v7' => 'pain', 'v8' => 'home'] as $ref => $word) {
            $p = s1wEditTerm($p, $ref, ['term_target' => $word, 'used_in' => []]);
        }

        return $p;
    };
    foreach (planCircleStarts() as $start => $id) {
        $counts = s1wCounts(s1wDeal(s1wScene($single, sceneId: $id)));
        $shared = [$counts['word_choose'], $counts['word_listen'], $counts['word_in_line']];
        rsort($shared);

        expect($counts['word_assemble'])->toBe(0, $start)
            ->and($shared)->toBe([3, 3, 2], $start);
    }

    // Pure: no word says a line — in_line drops; the other three share eight words 3/3/2.
    $noLines = array_fill(0, 8, s1wCan(CardKind::WordChoose, CardKind::WordListen, CardKind::WordAssemble));
    $kinds = WordChecks::kinds($noLines, 'any-seed');
    $counts = array_count_values(array_map(static fn (?CardKind $k): string => $k?->value ?? 'none', $kinds));
    rsort($counts);
    expect(array_map(static fn (?CardKind $k): ?string => $k?->value, $kinds))->not->toContain('word_in_line')
        ->and($counts)->toBe([3, 3, 2]);
});

// Canon (SESSION-1e): «assemble — однословному не подходит; in_line — без строки дня не подходит»; articles of the target
// are no words (SESSION-1a). Catches a single word assembled, a word put in a line no line of the day says, an article
// counted as a word, and tiles that are not the term's own words and up to three others.
it('never assembles a single word nor finds a word in a line the day does not say, the target\'s articles counted by nobody', function () {
    // Three single words no line says: v2, v3, v6; v8 is said by none either.
    $unsaid = static fn (array $p): array => s1wEditTerm(s1wEditTerm(s1wEditTerm(
        $p, 'v2', ['term_target' => 'cough', 'used_in' => []]), 'v3', ['term_target' => 'rash', 'used_in' => []]), 'v6', ['term_target' => 'sneeze', 'used_in' => []]);
    foreach (planCircleStarts() as $start => $id) {
        $kinds = s1wKinds(s1wDeal(s1wScene($unsaid, sceneId: $id)));
        foreach (['v2', 'v3', 'v6'] as $ref) {
            expect($kinds[$ref])->not->toBe('word_assemble', "{$start} {$ref}")
                ->and($kinds[$ref])->not->toBe('word_in_line', "{$start} {$ref}");
        }
        expect($kinds['v8'])->not->toBe('word_in_line', $start);
    }
    $scene = s1wScene($unsaid);
    expect((new WordCards)->inLine($scene, $scene->term('v2')))->toBeNull();

    // «a fever» is one word to an English learner; a pack naming no articles counts two.
    $withArticle = static fn (array $p): array => s1wEditTerm(s1wEditTerm($p, 'v3', ['term_target' => 'a fever']), 'v5', ['term_target' => 'a heating pad']);
    $english = s1wScene($withArticle);
    $noArticles = s1wScene($withArticle, target: LanguagePack::none('en'));
    expect((new WordCards)->isMultiWord($english, $english->term('v3')))->toBeFalse()
        ->and((new WordCards)->isMultiWord($noArticles, $noArticles->term('v3')))->toBeTrue()
        ->and((new WordCards)->assemble($english, $english->term('v5'))->payload['expected'])->toBe(['heating', 'pad'])
        ->and((new WordCards)->assemble($noArticles, $noArticles->term('v5'))->payload['expected'])->toBe(['a', 'heating', 'pad']);
    foreach (planCircleStarts() as $start => $id) {
        expect(s1wKinds(s1wDeal(s1wScene($withArticle, sceneId: $id)))['v3'])->not->toBe('word_assemble', $start);
    }

    // The tiles: the term's own words and up to three words of other terms — none of them the term's, none an article.
    $cards = new WordCards;
    foreach ($english->vocabulary() as $term) {
        if (! $cards->isMultiWord($english, $term)) {
            continue;
        }
        $check = $cards->assemble($english, $term);
        $ref = $term->ref();
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

    $v1 = $cards->assemble(s1wScene(), s1wScene()->term('v1'))->payload;
    expect($v1['expected'])->toBe(['lower', 'back'])
        ->and($v1['tiles'])->toHaveCount(5);

    // Words the other terms share with this one (case aside), or with each other, are no extra tiles.
    $sharing = static fn (array $p): array => s1wEditTerm(s1wEditTerm($p, 'v4', ['term_target' => 'Back strain']), 'v8', ['term_target' => 'lower back strain pain']);
    $scene = s1wScene($sharing, only: ['v1', 'v4', 'v8']);
    expect($cards->assemble($scene, $scene->term('v1'))->payload['tiles'])->toEqualCanonicalizing(['lower', 'back', 'strain', 'pain']);
});

// Canon (SESSION-1e): «word_choose: направление чередуется по словам seeded — term_to_native / native_to_term — у ВСЕХ уровней;
// начинающий и средний отличаются только произнесением». Catches the direction read off the level, two choices of a day
// asked the same way in a row, a start that is not seeded, and a word stage that differs between the levels.
it('alternates word_choose between both directions over the day\'s choices from a seeded start — the words stage the same at both levels', function () {
    // Eight single words no line says: nothing to assemble, nothing to find in a line — four choices and four listens.
    $bare = static function (array $p): array {
        foreach (['v1' => 'cough', 'v2' => 'rash', 'v3' => 'sneeze', 'v4' => 'cold', 'v5' => 'flu', 'v6' => 'nurse', 'v7' => 'bandage', 'v8' => 'plaster'] as $ref => $word) {
            $p = s1wEditTerm($p, $ref, ['term_target' => $word, 'used_in' => []]);
        }

        return $p;
    };
    $firsts = [];
    foreach (['01J8SESS10NW0RDS0000000001', '01J8SESS10NW0RDS0000000002', '01J8SESS10NW0RDS0000000003', '01J8SESS10NW0RDS0000000004', '01J8SESS10NW0RDS0000000005', '01J8SESS10NW0RDS0000000006'] as $id) {
        $choices = array_values(array_filter(s1wDeal(s1wScene($bare, sceneId: $id)), static fn (CardDraft $d): bool => $d->kind === CardKind::WordChoose));
        $directions = array_map(static fn (CardDraft $d): string => $d->payload['direction'], $choices);

        expect($choices)->toHaveCount(4, $id)
            ->and($directions)->toBe(array_map(static fn (int $i): string => Rotation::pick($id.':words:direction', $i, WordChecks::DIRECTIONS), range(0, 3)), $id)
            ->and($directions[0])->not->toBe($directions[1])
            ->and($directions[1])->not->toBe($directions[2])
            ->and($directions[2])->not->toBe($directions[3]);
        $firsts[$directions[0]] = true;
    }
    expect(array_keys($firsts))->toEqualCanonicalizing(WordChecks::DIRECTIONS);

    // The same day at both levels: the words stage is one stage, only «Фразы» says aloud differently.
    $scene = s1wScene();
    $deal = static fn (PlanLevel $level): array => array_map(
        static fn (DayCard $c): array => [$c->position(), $c->kind()->value, $c->unitRef(), $c->payload()],
        array_values(array_filter(
            (new DayAssembler)->sceneDay(PlanDayId::fromString('01J8SESSDAY000000000000001'), $scene, [S1W_SCENE => $scene], $level, [], [], static fn (): DayCardId => DayCardId::generate()),
            static fn (DayCard $c): bool => $c->stage() === Stage::Words,
        )),
    );
    $beginner = $deal(PlanLevel::Beginner);
    $choices = array_values(array_filter($beginner, static fn (array $c): bool => $c[1] === 'word_choose'));

    expect($beginner)->toBe($deal(PlanLevel::Intermediate))
        ->and(array_map(static fn (array $c): string => $c[3]['direction'], $choices))->toBe(['term_to_native', 'native_to_term']);
});

// Canon (SESSION-1e, разд. 2): «word_listen — звук → перевод: варианты — 4 перевода на родном (text_native терминов дня),
// верный — перевод этого термина; текста цели в payload нет; direction: term_to_native». Catches the old spellings of the
// target as options, a right option that is another word's translation, a target text left anywhere on the card, and
// options that sound.
it('plays the word in word_listen and asks what it means: translations to choose among, the right one its own, no target text on the card', function () {
    $scene = s1wScene();
    $cards = new WordCards;
    $targets = array_map(static fn (PlanTerm $t): string => mb_strtolower($t->textTarget()), $scene->vocabulary());
    $natives = array_map(static fn (PlanTerm $t): string => $t->textNative(), $scene->vocabulary());

    foreach ($scene->vocabulary() as $term) {
        $listen = $cards->listen($scene, $term, []);
        $right = s1wAssertOptions($listen->payload, $term->ref());
        $texts = array_column($listen->payload['options'], 'text');
        $question = $listen->payload;
        unset($question['options'], $question['correct']);

        expect($listen->kind)->toBe(CardKind::WordListen)
            ->and(array_keys($listen->payload))->toBe(['scene_id', 'direction', 'audio', 'options', 'correct'])
            ->and($listen->payload['direction'])->toBe('term_to_native')
            ->and($listen->payload['audio'])->toBe(Audio::of($term->ref()))
            ->and($right)->toBe(['id' => $listen->payload['correct'], 'text' => $term->textNative()])
            ->and($texts)->toHaveCount(4)
            ->and(array_diff($texts, $natives))->toBe([], $term->ref())
            ->and(array_intersect(array_map('mb_strtolower', $texts), $targets))->toBe([], $term->ref())
            ->and(array_map(static fn (array $o): array => array_keys($o), $listen->payload['options']))->each->toBe(['id', 'text'])
            ->and(json_encode($question, JSON_UNESCAPED_UNICODE))->not->toContain($term->textTarget())
            ->and(json_encode($question, JSON_UNESCAPED_UNICODE))->not->toContain($term->textNative());
    }
});

// Canon (SESSION-1e, разд. 2): «добор из NativeDistractorSource как у word_choose» + D-07. Catches a catalogue reached while
// the day has words enough, a small day's choice among translations left short, and a top-up offered as words of the
// target.
it('tops up the translations of word_choose and word_listen from the catalogue only when the day has fewer than four words', function () {
    $topUp = ['кашель', 'сыпь', 'насморк'];
    $cards = new WordCards;

    $small = s1wScene(only: ['v2', 'v3']);
    expect(array_column($cards->choose($small, $small->term('v2'), WordCards::TERM_TO_NATIVE, $topUp)->payload['options'], 'text'))
        ->toEqualCanonicalizing(['острая', 'температура', 'кашель', 'сыпь'])
        ->and(array_column($cards->listen($small, $small->term('v2'), $topUp)->payload['options'], 'text'))
        ->toEqualCanonicalizing(['острая', 'температура', 'кашель', 'сыпь'])
        ->and(array_column($cards->choose($small, $small->term('v2'), WordCards::TERM_TO_NATIVE, [])->payload['options'], 'text'))
        ->toEqualCanonicalizing(['острая', 'температура'])
        // Chosen among the words of the target: the catalogue has nothing to add.
        ->and(array_column($cards->choose($small, $small->term('v2'), WordCards::NATIVE_TO_TERM, $topUp)->payload['options'], 'text'))
        ->toEqualCanonicalizing(['sharp', 'fever']);

    // The day's own words come first: a day of eight never reaches the catalogue…
    $full = s1wScene();
    foreach ([$cards->choose($full, $full->term('v2'), WordCards::TERM_TO_NATIVE, $topUp), $cards->listen($full, $full->term('v2'), $topUp)] as $card) {
        $texts = array_column($card->payload['options'], 'text');
        expect(array_intersect($texts, $topUp))->toBe([])
            ->and($texts)->toContain('острая')->toHaveCount(4);
    }

    // …in an order the card seeds, so every word does not offer the same three wrong ones.
    $others = array_values(array_filter($full->vocabulary(), static fn (PlanTerm $t): bool => $t->ref() !== 'v2'));
    $seeded = Shuffle::seeded(S1W_SCENE.':v2:listen:others', $others);
    expect(array_column($cards->listen($full, $full->term('v2'), [])->payload['options'], 'text'))
        ->toEqualCanonicalizing(['острая', ...array_map(static fn (PlanTerm $t): string => $t->textNative(), array_slice($seeded, 0, 3))]);

    // The stage passes the top-up on to its choices among translations, whatever the level.
    $dealt = s1wDeal(s1wScene(only: ['v2', 'v3', 'v6']), $topUp);
    $translated = array_values(array_filter($dealt, static fn (CardDraft $d): bool => ($d->payload['direction'] ?? null) === 'term_to_native'));
    expect($translated)->not->toBe([]);
    foreach ($translated as $card) {
        expect(array_values(array_intersect(array_column($card->payload['options'], 'text'), $topUp)))->toBe(['кашель'], $card->unitRef);
    }
});

// Canon: a wrong option never equals the right one or another, case aside; ids follow what is shown. The right option is
// the word's translation when the translation is chosen, the word when the word is.
it('offers distinct options on every choice, the right one named once by its id', function () {
    foreach (planCircleStarts() as $id) {
        $scene = s1wScene(sceneId: $id);
        foreach (s1wDeal($scene) as $draft) {
            if (! in_array($draft->kind, [CardKind::WordChoose, CardKind::WordListen, CardKind::WordInLine], true)) {
                continue;
            }
            $label = $id.':'.$draft->kind->value.':'.$draft->unitRef;
            $right = s1wAssertOptions($draft->payload, $label);
            $term = $scene->term($draft->unitRef);
            expect(count($draft->payload['options']))->toBeGreaterThanOrEqual(2, $label)
                // The clean lesson says every single word as it is written, so the line's form is the term.
                ->and($right['text'])->toBe(($draft->payload['direction'] ?? null) === 'term_to_native' ? $term->textNative() : $term->textTarget(), $label);
        }
    }

    // A day of four words where one translation equals v2's but for its case, and one spelling but for case and spaces:
    // the twin is dropped, not shown twice, and the card offers what is left.
    $twins = static fn (array $p): array => s1wEditTerm(s1wEditTerm($p, 'v3', ['translation_native' => 'ОСТРАЯ']), 'v6', ['term_target' => ' Sharp ']);
    $scene = s1wScene($twins, only: ['v1', 'v2', 'v3', 'v6']);
    $cards = new WordCards;
    $translation = $cards->choose($scene, $scene->term('v2'), WordCards::TERM_TO_NATIVE, [])->payload;
    $word = $cards->choose($scene, $scene->term('v2'), WordCards::NATIVE_TO_TERM, [])->payload;
    $listen = $cards->listen($scene, $scene->term('v2'), [])->payload;

    expect(s1wAssertOptions($translation, 'term_to_native twins')['text'])->toBe('острая')
        ->and(array_column($translation['options'], 'text'))->toEqualCanonicalizing(['острая', 'поясница', 'рентген'])
        ->and(s1wAssertOptions($word, 'native_to_term twins')['text'])->toBe('sharp')
        ->and(array_column($word['options'], 'text'))->toEqualCanonicalizing(['sharp', 'lower back', 'fever'])
        ->and(s1wAssertOptions($listen, 'listen twins')['text'])->toBe('острая')
        ->and(array_column($listen['options'], 'text'))->toEqualCanonicalizing(['острая', 'поясница', 'рентген']);
});

// Canon (SESSION-1a; SESSION-1e): a check with nothing to choose between is no check. Catches a choice or a listen dealt with
// one option, and a word stage that moves its other words' spacing for a word without a check.
it('deals no check a word cannot have: the only word of a day is met and said, and the catalogue gives it a choice among translations', function () {
    $lonely = s1wScene(only: ['v2']);
    $kinds = static fn (array $drafts): array => array_map(static fn (CardDraft $d): string => $d->kind->value.':'.$d->unitRef, $drafts);

    expect($kinds(s1wDeal($lonely)))->toBe(['word_intro:v2', 'word_repeat:v2'])
        ->and((new WordCards)->choose($lonely, $lonely->term('v2'), WordCards::NATIVE_TO_TERM, ['кашель']))->toBeNull()
        ->and((new WordCards)->listen($lonely, $lonely->term('v2'), []))->toBeNull()
        ->and((new WordCards)->inLine($lonely, $lonely->term('v2')))->toBeNull()
        ->and((new WordsStage)->returned($lonely, $lonely->term('v2'), []))->toBeNull();

    // The catalogue's translations: a choice among translations — word_choose asked term_to_native, or word_listen.
    $topped = s1wDeal($lonely, ['кашель']);
    expect($topped)->toHaveCount(3)
        ->and(in_array($topped[2]->kind, [CardKind::WordChoose, CardKind::WordListen], true))->toBeTrue()
        ->and($topped[2]->payload['direction'])->toBe('term_to_native')
        ->and((new WordsStage)->returned($lonely, $lonely->term('v2'), ['кашель'])?->payload['direction'])->toBe('term_to_native');

    // Two words are a choice again: the same word gets its check back.
    expect(s1wChecks(s1wDeal(s1wScene(only: ['v2', 'v3']))))->toHaveKey('v2');
});

// Canon (§6 «Возвраты»; SESSION-1e): «слово → word_choose»; its direction — the day's alternation. Catches a returned word
// asked the way the level used to say, and a returned choice that is not the very card the day deals.
it('returns a word as word_choose — the very card the day deals it, or asked as the next choice after the ones before it', function () {
    $scene = s1wScene();
    $stage = new WordsStage;
    $checks = s1wChecks(s1wDeal($scene));
    $seed = S1W_SCENE.':words:direction';

    foreach (['v1', 'v5'] as $ref) {
        expect($checks[$ref]->kind)->toBe(CardKind::WordChoose)
            ->and($stage->returned($scene, $scene->term($ref), []))->toEqual($checks[$ref]);
    }
    // v2 is heard today: one choice (v1) before it. v8 is assembled: two before it.
    $v2 = $stage->returned($scene, $scene->term('v2'), []);
    $v8 = $stage->returned($scene, $scene->term('v8'), []);
    expect($v2->kind)->toBe(CardKind::WordChoose)
        ->and($v2->unitKind)->toBe(UnitKind::Word)
        ->and($v2->unitRef)->toBe('v2')
        ->and($v2->payload['direction'])->toBe(Rotation::pick($seed, 1, WordChecks::DIRECTIONS))
        ->and($v8->payload['direction'])->toBe(Rotation::pick($seed, 2, WordChecks::DIRECTIONS))
        ->and($checks['v1']->payload['direction'])->toBe(Rotation::pick($seed, 0, WordChecks::DIRECTIONS));
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
    expect(array_keys($repeat))->toBe(['scene_id', 'term', 'expected_text', 'speech_mode', 'audio'])
        ->and(array_keys($repeat['term']))->toBe($term)
        ->and($repeat['expected_text'])->toBe('lower back')
        // The word is on the screen: it is said as it stands, whatever its length.
        ->and($repeat['speech_mode'])->toBe('repeat')
        ->and($repeat['audio'])->toBe(['term' => Audio::of('v1')])
        ->and($cards->repeat($scene, $scene->term('v5'))->payload['speech_mode'])->toBe('repeat');

    $toNative = $cards->choose($scene, $scene->term('v2'), WordCards::TERM_TO_NATIVE, [])->payload;
    $toTerm = $cards->choose($scene, $scene->term('v2'), WordCards::NATIVE_TO_TERM, [])->payload;
    $refOf = [];
    foreach ($scene->vocabulary() as $each) {
        $refOf[$each->textTarget()] = $each->ref();
    }
    expect(array_keys($toNative))->toBe(['scene_id', 'direction', 'prompt', 'options', 'correct'])
        ->and($toNative['direction'])->toBe('term_to_native')
        ->and($toNative['prompt'])->toBe(['text_target' => 'sharp', 'image' => ['url' => null, 'tone' => null], 'audio' => Audio::of('v2')])
        ->and(s1wAssertOptions($toNative, 'term_to_native'))->toBe(['id' => $toNative['correct'], 'text' => 'острая'])
        ->and(array_map(static fn (array $o): array => array_keys($o), $toNative['options']))->each->toBe(['id', 'text'])
        ->and(array_keys($toTerm))->toBe(['scene_id', 'direction', 'prompt', 'options', 'correct'])
        ->and($toTerm['direction'])->toBe('native_to_term')
        ->and($toTerm['prompt'])->toBe(['text_native' => 'острая', 'image' => ['url' => null, 'tone' => null]])
        ->and(s1wAssertOptions($toTerm, 'native_to_term'))->toBe(['id' => $toTerm['correct'], 'text' => 'sharp', 'audio' => Audio::of('v2')]);
    foreach ($toTerm['options'] as $option) {
        expect(array_keys($option))->toBe(['id', 'text', 'audio'])
            ->and($option['audio'])->toBe(Audio::of($refOf[$option['text']]));
    }

    expect(array_keys($cards->listen($scene, $scene->term('v3'), [])->payload))->toBe(['scene_id', 'direction', 'audio', 'options', 'correct']);

    $assemble = $cards->assemble($scene, $scene->term('v4'))->payload;
    expect(array_keys($assemble))->toBe(['scene_id', 'term', 'tiles', 'expected'])
        ->and(array_keys($assemble['term']))->toBe($term)
        ->and($assemble['expected'])->toBe(['muscle', 'strain']);

    $inLine = $cards->inLine($scene, $scene->term('v2'))->payload;
    $right = s1wAssertOptions($inLine, 'in line');
    expect(array_keys($inLine))->toBe(['scene_id', 'line', 'options', 'correct'])
        // «sharp» fills p3's window in x3b; the partner's x3 says it outside any window, and goes first (SESSION-1d).
        ->and($inLine['line'])->toBe([
            'ref' => 'A3', 'line_ref' => 'x3', 'text_target' => 'Is the pain ___, or more of a dull ache?',
            'text_native' => 'Боль острая или скорее ноющая?', 'audio' => Audio::of('x3'),
        ])
        ->and($right)->toBe(['id' => $inLine['correct'], 'text' => 'sharp', 'audio' => Audio::of('v2')])
        ->and($inLine['options'])->toHaveCount(4)
        ->and(array_map(static fn (array $o): array => array_keys($o), $inLine['options']))->each->toBe(['id', 'text', 'audio'])
        ->and($cards->inLine($scene, $scene->term('v3'))->payload['line'])->toMatchArray([
            'ref' => 'A4', 'line_ref' => 'x4', 'text_target' => 'Does he have a ___?', 'text_native' => 'У него есть температура?',
        ])
        ->and($cards->inLine($scene, $scene->term('v6'))->payload['line'])->toMatchArray([
            'ref' => 'A7', 'line_ref' => 'x7', 'text_target' => 'No, an ___ is not needed for a muscle strain.',
            'text_native' => 'Нет, при растяжении мышцы рентген не нужен.',
        ]);

    // The word as the line says it: an inflected form is the right option, not the term.
    $form = s1wScene(static fn (array $p): array => s1wEditTerm($p, 'v2', ['term_target' => 'bend', 'used_in' => ['p3']]));
    $formed = $cards->inLine($form, $form->term('v2'))->payload;
    expect(s1wAssertOptions($formed, 'form')['text'])->toBe('bends')
        ->and($formed['line']['text_target'])->toBe('The pain is sharp when he ___.');
});

// Canon (SESSION-1d, 5.1): «строка для слова — сначала реплика, где слово стоит ВНЕ окна каркаса (реплика собеседника — в
// первую очередь), иначе как сейчас». Catches the line the word's `used_in` names taken though the word fills its window
// there, and a partner's line passed over for a learner's.
it('puts word_in_line on a line where the word stands outside a window — a partner\'s first, else a learner\'s outside its filler', function () {
    $cards = new WordCards;

    // «neck» fills p1's window in x1b («It hurts in his neck.») and no partner says it; the rescue line x6b says it
    // outside any window — that line is the card's, though x1b comes first in the visit.
    $neck = s1wScene(static function (array $p): array {
        $p = s1wEditTerm($p, 'v2', ['term_target' => 'neck', 'translation_native' => 'шея', 'used_in' => ['p1']]);
        $p['dialogue'][0]['messages'][1]['text_target'] = 'It hurts in his neck.';
        $p['dialogue'][0]['messages'][1]['text_native'] = 'У него болит шея.';
        $p['dialogue'][5]['messages'][0]['text_target'] = 'Sorry, my neck, could you say that more slowly?';

        return $p;
    });
    expect($cards->inLine($neck, $neck->term('v2'))->payload['line'])->toMatchArray([
        'ref' => 'B6', 'line_ref' => 'x6b', 'text_target' => 'Sorry, my ___, could you say that more slowly?',
    ])
        // The word card still shows the line its `used_in` names — the rule is the check's alone.
        ->and($cards->intro($neck, $neck->term('v2'))->payload['used_in']['ref'])->toBe('p1');

    // «heating pad» is said outside a window first by the learner, in x2's glue («Heating pad, it started three days
    // ago.»), and later by the partner in x5 and x6: the partner's line goes first, though it comes later in the visit.
    $partnerFirst = s1wScene(static function (array $p): array {
        $p['dialogue'][1]['messages'][1]['text_target'] = 'Heating pad, it started three days ago.';

        return $p;
    });
    expect($partnerFirst->exchange(2)?->learner()?->filler)->toBe('three days ago')
        ->and($cards->inLine($partnerFirst, $partnerFirst->term('v5'))->payload['line'])->toMatchArray([
            'ref' => 'A5', 'line_ref' => 'x5', 'text_target' => 'It looks like a muscle strain, so he should rest and use a ___.',
        ]);

    // No line says it outside a window: the line it is said in, as before.
    $inside = s1wScene(static function (array $p): array {
        $p = s1wEditTerm($p, 'v2', ['term_target' => 'neck', 'translation_native' => 'шея', 'used_in' => ['p1']]);
        $p['dialogue'][0]['messages'][1]['text_target'] = 'It hurts in his neck.';
        $p['dialogue'][0]['messages'][1]['text_native'] = 'У него болит шея.';

        return $p;
    });
    expect($cards->inLine($inside, $inside->term('v2'))->payload['line'])->toMatchArray([
        'ref' => 'p1', 'line_ref' => 'x1b', 'text_target' => 'It hurts in his ___.', 'text_native' => 'У него болит шея.',
    ]);
});

// Canon (SESSION-1d, 5.1): «под строкой — ПОЛНЫЙ перевод, text_native_gapped убрать; ложные варианты не берутся из
// наполнений того же каркаса, что и верный». Catches a gapped translation left in the payload and a wrong option another
// value of the very window the word fills — it would fit the gap as well.
it('keeps the whole translation under the line and never offers a filler of the frame the word itself fills', function () {
    $cards = new WordCards;
    // «X-ray» fills p6 («an X-ray»); «follow-up appointment» and «sick note» are p6's other fillers, «sharp» fills p3.
    $scene = s1wScene(only: ['v2', 'v6', 'v7', 'v8']);
    $card = $cards->inLine($scene, $scene->term('v6'))->payload;

    expect(array_keys($card['line']))->toBe(['ref', 'line_ref', 'text_target', 'text_native', 'audio'])
        ->and($card['line']['text_native'])->toBe('Нет, при растяжении мышцы рентген не нужен.')
        ->and(array_column($card['options'], 'text'))->toEqualCanonicalizing(['X-ray', 'sharp'])
        // Nothing but the frame's own fillers to offer: no choice, no card.
        ->and($cards->inLine(s1wScene(only: ['v6', 'v7', 'v8']), $scene->term('v6')))->toBeNull();
});
