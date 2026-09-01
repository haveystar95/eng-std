<?php

declare(strict_types=1);

use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Shared\Domain\ValueObject\UserId;
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
function shelfTerm(string $collectionId, string $text, string $translation, ?string $cefr = 'A2'): string
{
    $termId = Ulid::generate();
    DB::table('terms')->insert([
        'id' => $termId, 'lang' => 'en', 'text' => $text, 'normalized_text' => mb_strtolower($text),
        'type' => 'word', 'source' => 'ai', 'cefr' => $cefr, 'created_at' => now(), 'updated_at' => now(),
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

it('never offers a term out of another learner`s private collection', function () {
    [$me] = learner();
    [$stranger] = learner();

    // The target, alone on its own shelf: the pool cannot supply a single distractor, so the
    // top-up is forced to run. This is the shape the leak needed.
    $mine = shelf($me->id, 'custom', 'private', 'Моя папка');
    $target = shelfTerm($mine, 'passport', 'паспорт');

    // Somebody else's private material, in the same language and at the same level — everything
    // the old query filtered on.
    $theirs = shelf($stranger->id, 'custom', 'private', 'Чужая папка');
    shelfTerm($theirs, "Hi, I'm Alex, and I work as a backend developer.", 'Привет, я Алекс…');
    shelfTerm($theirs, 'severance package', 'выходное пособие');

    $options = app(DistractorReader::class)->forTarget(
        UserId::fromString($me->id),
        TermId::fromString($target),
        [$target],
        3,
    );

    expect($options)->not->toContain("Hi, I'm Alex, and I work as a backend developer.")
        ->and($options)->not->toContain('severance package')
        // Nothing was reachable, so nothing is offered. An empty answer here is the trainer's own
        // problem to solve (the option floor drops the card); it is not a reason to reach further.
        ->and($options)->toBe([]);
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

it('never offers a spoken LINE as the wrong answer to a word', function () {
    [$me] = learner();

    $mine = shelf($me->id, 'custom', 'private', 'Моя папка');
    $target = shelfTerm($mine, 'passport', 'паспорт');
    $word = shelfTerm($mine, 'suitcase', 'чемодан');
    $line = shelfTerm($mine, 'Hello. Do you have a reservation?', 'Здравствуйте. У вас есть бронирование?');
    DB::table('terms')->where('id', $line)->update(['kind' => 'line', 'is_line' => true]);

    $options = app(DistractorReader::class)->forTarget(
        UserId::fromString($me->id),
        TermId::fromString($target),
        [$target, $word, $line],
        3,
    );

    expect($options)->toContain('suitcase')
        ->and($options)->not->toContain('Hello. Do you have a reservation?');
});

it('offers a line the other lines OF ITS OWN FORM, and no words', function () {
    // The rule reads both ways: a turn is answered against turns. Since Д-2 it also reads one level
    // finer — a statement is answered against statements, because a question among them is the
    // answer and needs no reading (`49-session-25.png`).
    [$me] = learner();

    $mine = shelf($me->id, 'custom', 'private', 'Моя папка');
    $target = shelfTerm($mine, 'Yes, I have a reservation.', 'Да, у меня есть бронирование.');
    $otherStatement = shelfTerm($mine, 'I booked it last week.', 'Я забронировал на прошлой неделе.');
    $question = shelfTerm($mine, 'Hello. Do you have a reservation?', 'Здравствуйте. У вас есть бронирование?');
    shelfTerm($mine, 'suitcase', 'чемодан');
    DB::table('terms')->whereIn('id', [$target, $otherStatement, $question])
        ->update(['kind' => 'line', 'is_line' => true]);

    $options = app(DistractorReader::class)->forTarget(
        UserId::fromString($me->id),
        TermId::fromString($target),
        DB::table('collection_items')->where('collection_id', $mine)->pluck('term_id')->all(),
        3,
    );

    expect($options)->toBe(['I booked it last week.']);
});

it('offers a question the other questions, and never a statement (Д-2)', function () {
    [$me] = learner();

    $mine = shelf($me->id, 'custom', 'private', 'Моя папка');
    $target = shelfTerm($mine, 'Could you repeat?', 'Вы можете повторить?');
    $otherQuestion = shelfTerm($mine, 'How old is your child?', 'Сколько лет вашему ребёнку?');
    $statement = shelfTerm($mine, 'He has a fever.', 'У него температура.');
    DB::table('terms')->whereIn('id', [$target, $otherQuestion, $statement])
        ->update(['kind' => 'line', 'is_line' => true]);

    $options = app(DistractorReader::class)->forTarget(
        UserId::fromString($me->id),
        TermId::fromString($target),
        DB::table('collection_items')->where('collection_id', $mine)->pluck('term_id')->all(),
        3,
    );

    expect($options)->toBe(['How old is your child?']);
});
