<?php

declare(strict_types=1);

use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Vocabulary\Application\Query\DistractorReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * QA-17, the card half: two options that mean the same thing are ONE option.
 *
 * A distractor was already dropped when its translation matched the PROMPT's — otherwise the card
 * has two correct answers. What was missing is the same rule between the options themselves:
 * «check-in desk» and «front desk» are both «стойка регистрации», so a card carrying both is asking
 * the learner to pick one of two answers that are equally right, and whichever they take, half the
 * time the card marks them wrong.
 */
function seedTwinTerm(string $text, string $translation): string
{
    $id = Ulid::generate();
    DB::table('terms')->insert([
        'id' => $id, 'lang' => 'en', 'text' => $text, 'normalized_text' => mb_strtolower($text),
        'type' => 'word', 'source' => 'ai', 'cefr' => 'A2', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('term_translations')->insert([
        'id' => Ulid::generate(), 'term_id' => $id, 'lang' => 'ru', 'text' => $translation,
        'is_primary' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return $id;
}

it('never puts two translation twins on one card', function () {
    $target = seedTwinTerm('withdraw cash', 'снять наличные');
    // The twins, from the owner's own data.
    seedTwinTerm('check-in desk', 'стойка регистрации');
    seedTwinTerm('front desk', 'стойка регистрации');
    seedTwinTerm('boarding pass', 'посадочный талон');

    $pool = [$target, ...DB::table('terms')->where('id', '<>', $target)->pluck('id')->all()];
    $options = app(DistractorReader::class)->forTarget(TermId::fromString($target), array_map('strval', $pool), 3);

    $twins = array_intersect($options, ['check-in desk', 'front desk']);
    expect(count($twins))->toBeLessThanOrEqual(1, 'one meaning, one option');
});

it('still drops an option that means the same as the PROMPT', function () {
    // The rule this one extends, kept honest: it was there first and must not be lost.
    $target = seedTwinTerm('How much does it cost?', 'Сколько это стоит?');
    seedTwinTerm('How much does this cost?', 'Сколько это стоит?');
    seedTwinTerm('boarding pass', 'посадочный талон');

    $pool = [$target, ...DB::table('terms')->where('id', '<>', $target)->pluck('id')->all()];
    $options = app(DistractorReader::class)->forTarget(TermId::fromString($target), array_map('strval', $pool), 3);

    expect($options)->not->toContain('How much does this cost?');
});

it('still fills the card when the meanings are all different', function () {
    // The floor must not become a reason cards stop having options (QA-15 lives next door).
    $target = seedTwinTerm('withdraw cash', 'снять наличные');
    seedTwinTerm('boarding pass', 'посадочный талон');
    seedTwinTerm('front desk', 'стойка регистрации');
    seedTwinTerm('towel', 'полотенце');

    $pool = [$target, ...DB::table('terms')->where('id', '<>', $target)->pluck('id')->all()];
    $options = app(DistractorReader::class)->forTarget(TermId::fromString($target), array_map('strval', $pool), 3);

    expect($options)->toHaveCount(3);
});
