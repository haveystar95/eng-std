<?php

declare(strict_types=1);

use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Vocabulary\Application\Command\ImportTerm;
use App\Modules\Vocabulary\Application\Command\ImportTermHandler;
use App\Modules\Vocabulary\Application\Dto\TranslationInput;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('persists idiom and phrasal_verb term types past the widened check constraint', function () {
    $import = app(ImportTermHandler::class);

    $idiom = $import(new ImportTerm(
        lang: new LanguageCode('en'),
        text: 'break the ice',
        type: 'idiom',
        pos: null,
        source: 'ai',
        translations: [new TranslationInput(new LanguageCode('ru'), 'растопить лёд', isPrimary: true)],
    ));
    $phrasal = $import(new ImportTerm(
        lang: new LanguageCode('en'),
        text: 'run into',
        type: 'phrasal_verb',
        pos: null,
        source: 'ai',
        translations: [new TranslationInput(new LanguageCode('ru'), 'наткнуться', isPrimary: true)],
    ));

    $this->assertDatabaseHas('terms', ['id' => $idiom->value, 'type' => 'idiom']);
    $this->assertDatabaseHas('terms', ['id' => $phrasal->value, 'type' => 'phrasal_verb']);
});
