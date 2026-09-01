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
 * WHAT MAY STAND BESIDE THE ANSWER — kind for kind, and, inside a spoken turn, form for form.
 *
 * Д-2 of the live run, photographed twice (`49-session-25.png`, `52-session-28.png`): the card
 * asked for «Could you repeat?» and offered three statements. The learner picks the one with the
 * question mark and never reads it. The old family rule split `line` from everything else and put
 * `word`, `chunk` and «no kind» together, so both of those cards were legal.
 *
 * Both halves are now absolute. What that buys is a pool that starves — two connectors in a day of
 * fourteen cannot furnish three of their own kind — and starving is the intended outcome: the card
 * is dropped ({@see \App\Modules\Learning\Application\Service\StudyCardAssembler}) rather than
 * filled with something of another shape.
 */
function seedFamilyTerm(string $text, string $translation, ?string $kind, string $lang = 'en'): string
{
    $id = Ulid::generate();
    DB::table('terms')->insert([
        'id' => $id,
        'lang' => $lang,
        'text' => $text,
        'normalized_text' => mb_strtolower($text),
        'type' => 'word',
        'kind' => $kind,
        'source' => 'ai',
        'cefr' => 'A2',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('term_translations')->insert([
        'id' => Ulid::generate(),
        'term_id' => $id,
        'lang' => 'ru',
        'text' => $translation,
        'is_primary' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}

/**
 * The whole seeded set as the pool, exactly as a plan day is its own pool.
 *
 * @return list<string>
 */
function familyOptionsFor(string $targetId, int $count = 3): array
{
    /** @var list<string> $pool */
    $pool = array_map('strval', DB::table('terms')->pluck('id')->all());

    return app(DistractorReader::class)->forTarget(
        UserId::fromString(Ulid::generate()),
        TermId::fromString($targetId),
        $pool,
        $count,
    );
}

it('offers a question only other questions (Д-2)', function () {
    // The live day's shape: eight lines, two of them questions.
    $target = seedFamilyTerm('Could you repeat?', 'Вы можете повторить?', 'line');
    seedFamilyTerm('How old is your child?', 'Сколько лет вашему ребёнку?', 'line');
    foreach ([
        'He has a fever.' => 'У него температура.',
        'He is also coughing.' => 'Он ещё кашляет.',
        'I came with my son.' => 'Я пришёл с сыном.',
        'It started yesterday evening.' => 'Это началось вчера вечером.',
        'He has a sore throat.' => 'У него болит горло.',
        'He drinks enough.' => 'Он пьёт достаточно.',
    ] as $text => $translation) {
        seedFamilyTerm($text, $translation, 'line');
    }

    // Only ONE other question exists, so two wrong answers cannot be found — and none of the six
    // statements is allowed to make up the difference.
    expect(familyOptionsFor($target, 2))->toBe(['How old is your child?']);
});

it('offers a statement only other statements', function () {
    $target = seedFamilyTerm('He has a fever.', 'У него температура.', 'line');
    seedFamilyTerm('He is also coughing.', 'Он ещё кашляет.', 'line');
    seedFamilyTerm('Could you repeat?', 'Вы можете повторить?', 'line');
    seedFamilyTerm('How old is your child?', 'Сколько лет вашему ребёнку?', 'line');

    expect(familyOptionsFor($target, 3))->toBe(['He is also coughing.']);
});

it('offers a connector only connectors — never single words (наряд PLAN-FIX-2, 1.3)', function () {
    // Two `chunk` cards in a day is the ordinary shape, and it is not enough for a choice card.
    $target = seedFamilyTerm('sore throat', 'больное горло', 'chunk');
    seedFamilyTerm('yesterday evening', 'вчера вечером', 'chunk');
    seedFamilyTerm('fever', 'температура', 'word');
    seedFamilyTerm('son', 'сын', 'word');
    seedFamilyTerm('coughing', 'кашель', 'word');

    expect(familyOptionsFor($target, 2))->toBe(['yesterday evening']);
});

it('offers a word only words', function () {
    $target = seedFamilyTerm('fever', 'температура', 'word');
    seedFamilyTerm('son', 'сын', 'word');
    seedFamilyTerm('sore throat', 'больное горло', 'chunk');
    seedFamilyTerm('He has a fever.', 'У него температура.', 'line');

    expect(familyOptionsFor($target, 3))->toBe(['son']);
});

it('keeps ordinary vocabulary — no kind at all — in a family of its own', function () {
    // Everything outside a plan has `kind = null`, which is most of the catalogue. It is not
    // «word»: a plan's `word` means «this card is a single word in this day», and a store term has
    // never been in a day.
    $target = seedFamilyTerm('passport', 'паспорт', null);
    seedFamilyTerm('luggage', 'багаж', null);
    seedFamilyTerm('ticket', 'билет', null);
    seedFamilyTerm('fever', 'температура', 'word');

    expect(familyOptionsFor($target, 3))->toBe(['luggage', 'ticket']);
});
