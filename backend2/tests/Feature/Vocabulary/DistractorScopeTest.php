<?php

declare(strict_types=1);

use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Shared\Domain\ValueObject\UserId;
use App\Modules\Shared\Domain\Service\DistractorLength;
use App\Modules\Vocabulary\Application\Query\DistractorReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * WHOSE WORDS MAY BECOME A WRONG ANSWER.
 *
 * The top-up used to read `terms` whole, filtered by language and nothing else. Terms are
 * deduplicated globally — a row genuinely has no owner — so the query looked harmless, and what it
 * actually reached was every phrase every user had ever generated. On the owner's own base that was
 * 930 English terms outside his pool, 411 of them multi-word phrases, and 593 English terms sitting
 * in other people's PRIVATE collections.
 *
 * Ownership of a term does not exist and must not be invented; what exists is the SHELF it stands
 * on. So the shelf is what the top-up reads: the learner's own, the ones they subscribe to, and the
 * published catalogue.
 */
function shelfTerm(string $collectionId, string $text, string $translation, ?string $cefr = 'A2', string $source = 'ai'): string
{
    $termId = Ulid::generate();
    DB::table('terms')->insert([
        'id' => $termId, 'lang' => 'en', 'text' => $text, 'normalized_text' => mb_strtolower($text),
        'type' => 'word', 'source' => $source, 'cefr' => $cefr, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('term_translations')->insert([
        'id' => Ulid::generate(), 'term_id' => $termId, 'lang' => 'ru', 'text' => $translation,
        'is_primary' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('collection_items')->insert([
        'id' => Ulid::generate(), 'collection_id' => $collectionId, 'term_id' => $termId,
        'position' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return $termId;
}

function shelf(?string $ownerId, string $type, string $visibility, string $title): string
{
    $id = Ulid::generate();
    DB::table('collections')->insert([
        'id' => $id, 'owner_id' => $ownerId, 'type' => $type, 'source' => $type === 'system' ? 'curated' : 'user',
        'title' => $title, 'topic' => null, 'source_lang' => 'ru', 'target_lang' => 'en',
        'visibility' => $visibility, 'items_count' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return $id;
}

it('never offers a word somebody typed in themselves, whatever shelf it stands on', function () {
    [$me] = learner();
    [$stranger] = learner();

    // The target, alone on its own shelf: the pool cannot supply a single distractor, so the
    // top-up is forced to run. This is the shape the leak needed.
    $mine = shelf($me->id, 'custom', 'private', 'Моя папка');
    $target = shelfTerm($mine, 'passport', 'паспорт');

    // Somebody else's own writing — a word they added by hand and a phrase they saved out of the
    // translator. Both `source = 'user'`, both in the right language and at the right level, and
    // neither may ever be offered to anyone but the person who wrote it.
    $theirs = shelf($stranger->id, 'custom', 'private', 'Чужая папка');
    shelfTerm($theirs, 'severance package', 'выходное пособие', source: 'user');
    shelfTerm($theirs, 'notice period', 'срок уведомления', source: 'user');

    $options = app(DistractorReader::class)->forTarget(
        UserId::fromString($me->id),
        TermId::fromString($target),
        [$target],
        3,
    );

    expect($options)->not->toContain('severance package')
        ->and($options)->not->toContain('notice period')
        // Nothing else was reachable, so nothing is offered. An empty answer here is the trainer's
        // own problem to solve (the option floor drops the card); it is not a reason to reach into
        // somebody's writing.
        ->and($options)->toBe([]);
});

it('offers generated material off any shelf, including one it has never seen', function () {
    [$me] = learner();
    [$stranger] = learner();

    $mine = shelf($me->id, 'custom', 'private', 'Моя папка');
    $target = shelfTerm($mine, 'passport', 'паспорт');

    // A stranger's PRIVATE folder — but the words in it were generated, not written by them. That
    // is catalogue: it belongs to the app, and meeting an unfamiliar English word as a wrong answer
    // is the trainer working. The old shelf rule refused these and starved cards for it.
    $theirs = shelf($stranger->id, 'custom', 'private', 'Чужая папка');
    shelfTerm($theirs, 'suitcase', 'чемодан');
    shelfTerm($theirs, 'ticket', 'билет');

    $options = app(DistractorReader::class)->forTarget(
        UserId::fromString($me->id),
        TermId::fromString($target),
        [$target],
        2,
    );

    expect($options)->toContain('suitcase')
        ->and($options)->toContain('ticket');
});

it('still offers the learner their OWN hand-written words', function () {
    [$me] = learner();

    $mine = shelf($me->id, 'custom', 'private', 'Моя папка');
    $target = shelfTerm($mine, 'passport', 'паспорт');

    // «Не попадают в ЧУЖИЕ варианты» — their own are not somebody else's. A learner whose vocabulary
    // is mostly words they typed would otherwise get no options at all on a card of one of them,
    // which is not privacy, it is an empty session.
    $alsoMine = shelf($me->id, 'custom', 'private', 'Другая моя папка');
    shelfTerm($alsoMine, 'luggage', 'багаж', source: 'user');

    $options = app(DistractorReader::class)->forTarget(
        UserId::fromString($me->id),
        TermId::fromString($target),
        [$target],
        3,
    );

    expect($options)->toContain('luggage');
});

it('still tops up from the published catalogue and from the learner`s own shelves', function () {
    [$me] = learner();

    $mine = shelf($me->id, 'custom', 'private', 'Моя папка');
    $target = shelfTerm($mine, 'passport', 'паспорт');

    // My OWN other folder — private, and mine, so it is fair game.
    $alsoMine = shelf($me->id, 'custom', 'private', 'Другая моя папка');
    shelfTerm($alsoMine, 'suitcase', 'чемодан');

    // The catalogue: nobody's in particular, published on purpose.
    $catalogue = shelf(null, 'system', 'public', 'Витрина');
    shelfTerm($catalogue, 'ticket', 'билет');

    $options = app(DistractorReader::class)->forTarget(
        UserId::fromString($me->id),
        TermId::fromString($target),
        [$target],
        3,
    );

    expect($options)->toContain('suitcase')
        ->and($options)->toContain('ticket');
});

// ── the length band ───────────────────────────────────────────────────────────────────────────

it('never offers a twelve-letter word beside a four-letter one', function () {
    [$me] = learner();

    $mine = shelf($me->id, 'custom', 'private', 'Моя папка');
    // Four letters. ±50 % of that is two to six: `door` and `lamp` fit, `refrigerator` does not.
    $target = shelfTerm($mine, 'door', 'дверь');

    $catalogue = shelf(null, 'system', 'public', 'Витрина');
    shelfTerm($catalogue, 'refrigerator', 'холодильник');
    shelfTerm($catalogue, 'accommodation', 'жильё');
    shelfTerm($catalogue, 'lamp', 'лампа');

    $options = app(DistractorReader::class)->forTarget(
        UserId::fromString($me->id),
        TermId::fromString($target),
        [$target],
        3,
    );

    // Same shape and still answerable at a glance: the short one is the answer and nobody reads the
    // rest. The band refuses them rather than ranking them below `lamp`.
    expect($options)->not->toContain('refrigerator')
        ->and($options)->not->toContain('accommodation')
        ->and($options)->toContain('lamp');
});

it('reads its threshold from config, so it moves without a deploy', function () {
    [$me] = learner();

    $mine = shelf($me->id, 'custom', 'private', 'Моя папка');
    $target = shelfTerm($mine, 'door', 'дверь');

    $catalogue = shelf(null, 'system', 'public', 'Витрина');
    shelfTerm($catalogue, 'refrigerator', 'холодильник');

    // Wide open: twelve letters is now within ±300 % of four, and the same card takes it.
    config(['learning.distractor_length.char_tolerance' => 3.0]);
    app()->forgetInstance(DistractorLength::class);

    $options = app(DistractorReader::class)->forTarget(
        UserId::fromString($me->id),
        TermId::fromString($target),
        [$target],
        3,
    );

    expect($options)->toContain('refrigerator');
});
