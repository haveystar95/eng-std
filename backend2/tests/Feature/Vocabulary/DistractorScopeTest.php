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
    // All three within the length band on purpose (three to four words): what this test is about is
    // the FORM split, so the length rule beside it must not be what excludes anything here.
    $target = shelfTerm($mine, 'Could you repeat?', 'Вы можете повторить?');
    $otherQuestion = shelfTerm($mine, 'Can you say that?', 'Можете это сказать?');
    $statement = shelfTerm($mine, 'He has a fever.', 'У него температура.');
    DB::table('terms')->whereIn('id', [$target, $otherQuestion, $statement])
        ->update(['kind' => 'line', 'is_line' => true]);

    $options = app(DistractorReader::class)->forTarget(
        UserId::fromString($me->id),
        TermId::fromString($target),
        DB::table('collection_items')->where('collection_id', $mine)->pluck('term_id')->all(),
        3,
    );

    expect($options)->toBe(['Can you say that?']);
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

it('bands a spoken line by WORDS, because a phrase is not a long word', function () {
    [$me] = learner();

    $mine = shelf($me->id, 'custom', 'private', 'Мои реплики');
    // Five words. ±40 % is three to seven.
    $target = shelfTerm($mine, 'Could you say that again please', 'Повторите, пожалуйста');
    DB::table('terms')->where('id', $target)->update(['kind' => 'line', 'is_line' => true]);

    $catalogue = shelf(null, 'system', 'public', 'Витрина реплик');
    $fits = shelfTerm($catalogue, 'I did not catch that', 'Я не расслышал');
    $tooLong = shelfTerm($catalogue, 'I am terribly sorry but I am afraid I did not manage to catch a single word of what you just said', 'Простите, я совсем не расслышал');
    DB::table('terms')->whereIn('id', [$fits, $tooLong])->update(['kind' => 'line', 'is_line' => true]);

    $options = app(DistractorReader::class)->forTarget(
        UserId::fromString($me->id),
        TermId::fromString($target),
        [$target],
        3,
    );

    expect($options)->toContain('I did not catch that')
        ->and($options)->not->toContain('I am terribly sorry but I am afraid I did not manage to catch a single word of what you just said');
});

it('refuses a line that leaves the answer the only option on two rows (Д-2, скрин 247)', function () {
    // The live card, verbatim. «Your child needs this medicine twice a day.» is eight words, so the
    // word band's ±40 % opened it to five — and five-word replies came in at nineteen and twenty-one
    // characters against the answer's forty-two. On the phone the answer was the only option that
    // wrapped onto a second row, and it was picked without being read.
    [$me] = learner();

    $mine = shelf($me->id, 'custom', 'private', 'День плана');
    $target = shelfTerm($mine, 'Your child needs this medicine twice a day.', 'Вашему ребёнку нужно это лекарство два раза в день.');
    DB::table('terms')->where('id', $target)->update(['kind' => 'line', 'is_line' => true]);

    $catalogue = shelf(null, 'system', 'public', 'Витрина реплик');
    $short = shelfTerm($catalogue, 'I came with my son.', 'Я пришёл с сыном.');
    $alsoShort = shelfTerm($catalogue, 'He has a sore throat.', 'У него болит горло.');
    // Same number of rows on the card, which is the whole test: inside the word band AND inside the
    // character band, so it may stand there.
    $sameSize = shelfTerm($catalogue, 'He needs to take this syrup twice a day.', 'Ему нужно принимать этот сироп дважды в день.');
    DB::table('terms')->whereIn('id', [$short, $alsoShort, $sameSize])
        ->update(['kind' => 'line', 'is_line' => true]);

    $options = app(DistractorReader::class)->forTarget(
        UserId::fromString($me->id),
        TermId::fromString($target),
        [$target],
        3,
    );

    expect($options)->toContain('He needs to take this syrup twice a day.')
        ->and($options)->not->toContain('I came with my son.')
        ->and($options)->not->toContain('He has a sore throat.');
});

it('reads its two thresholds from config, so they move without a deploy', function () {
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
