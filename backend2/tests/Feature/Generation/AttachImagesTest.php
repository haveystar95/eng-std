<?php

declare(strict_types=1);

use App\Modules\Collections\Application\Command\AddTermToCollection;
use App\Modules\Collections\Application\Command\AddTermToCollectionHandler;
use App\Modules\Collections\Application\Command\AttachCollectionImageHandler;
use App\Modules\Collections\Application\Command\CreateGeneratedCollection;
use App\Modules\Collections\Application\Command\CreateGeneratedCollectionHandler;
use App\Modules\Collections\Application\Query\GetCollectionTermSetHandler;
use App\Modules\Collections\Application\Query\PendingCollectionImageReader;
use App\Modules\Generation\Application\Command\AttachCollectionImages;
use App\Modules\Generation\Application\Command\AttachCollectionImagesHandler;
use App\Modules\Generation\Application\Port\TransientImageSearchError;
use App\Modules\Generation\Infrastructure\Adapter\FakePexelsImageSearch;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use App\Modules\Vocabulary\Application\Command\ImportTerm;
use App\Modules\Vocabulary\Application\Command\ImportTermHandler;
use App\Modules\Vocabulary\Application\Dto\TranslationInput;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/** Build the attach handler over the real readers/commands, with a chosen fake image search. */
function attachHandlerWith(FakePexelsImageSearch $images): AttachCollectionImagesHandler
{
    return new AttachCollectionImagesHandler(
        app(GetCollectionTermSetHandler::class),
        app(\App\Modules\Vocabulary\Application\Query\PendingTermImageReader::class),
        app(\App\Modules\Vocabulary\Application\Command\AttachTermImageHandler::class),
        app(PendingCollectionImageReader::class),
        app(AttachCollectionImageHandler::class),
        $images,
    );
}

/**
 * A generated collection with: one illustratable term (has image_api_prompt), one un-illustratable
 * term (no prompt), and a collection cover prompt. Returns [collectionId, illustratableTermId, blankTermId].
 *
 * @return array{0: CollectionId, 1: TermId, 2: TermId}
 */
function seedGeneratedCollection(?string $coverPrompt = 'bank branch interior'): array
{
    $owner = UserId::generate();
    $ru = new LanguageCode('ru');
    $en = new LanguageCode('en');

    $collectionId = app(CreateGeneratedCollectionHandler::class)(new CreateGeneratedCollection(
        ownerId: $owner, title: 'At the bank', sourceLang: $ru, targetLang: $en,
        description: 'banking', topic: 'иду в банк', imageApiPrompt: $coverPrompt,
    ));

    $withPrompt = app(ImportTermHandler::class)(new ImportTerm(
        lang: $en, text: 'withdraw cash', type: 'phrase', pos: null, source: 'ai',
        translations: [new TranslationInput($ru, 'снять наличные', true)],
        ipa: null, examples: [], cefr: 'A2', imageApiPrompt: 'atm cash withdrawal',
    ));
    $noPrompt = app(ImportTermHandler::class)(new ImportTerm(
        lang: $en, text: 'the account', type: 'phrase', pos: null, source: 'ai',
        translations: [new TranslationInput($ru, 'счёт', true)],
        ipa: null, examples: [], cefr: 'A2', imageApiPrompt: null,
    ));

    app(AddTermToCollectionHandler::class)(new AddTermToCollection($collectionId, $withPrompt, $owner));
    app(AddTermToCollectionHandler::class)(new AddTermToCollection($collectionId, $noPrompt, $owner));

    return [$collectionId, $withPrompt, $noPrompt];
}

it('attaches a photo + attribution to illustratable terms and the cover, skipping blank prompts', function () {
    [$collectionId, $withPrompt, $noPrompt] = seedGeneratedCollection();
    $images = new FakePexelsImageSearch(FakePexelsImageSearch::FOUND);

    attachHandlerWith($images)(new AttachCollectionImages($collectionId));

    $imaged = DB::table('terms')->where('id', $withPrompt->value)->first();
    $blank = DB::table('terms')->where('id', $noPrompt->value)->first();
    $collection = DB::table('collections')->where('id', $collectionId->value)->first();

    expect($imaged->image_url)->not->toBeNull()
        ->and($imaged->image_author)->toBe('Fake Photographer')
        ->and($imaged->image_author_url)->toBe('https://pexels.test/@fake')
        ->and($blank->image_url)->toBeNull()                 // no prompt → never searched
        ->and($collection->image_url)->not->toBeNull()
        ->and($collection->image_author)->toBe('Fake Photographer')
        ->and($images->calls)->toBe(2);                       // one term + the cover; blank term skipped
});

it('never overwrites an existing term image and does not re-search it', function () {
    [$collectionId, $withPrompt] = seedGeneratedCollection(coverPrompt: null);
    DB::table('terms')->where('id', $withPrompt->value)->update([
        'image_url' => 'https://existing.example/keep.jpg',
        'image_author' => 'Original',
    ]);
    $images = new FakePexelsImageSearch(FakePexelsImageSearch::FOUND);

    attachHandlerWith($images)(new AttachCollectionImages($collectionId));

    $imaged = DB::table('terms')->where('id', $withPrompt->value)->first();
    expect($imaged->image_url)->toBe('https://existing.example/keep.jpg')  // untouched
        ->and($imaged->image_author)->toBe('Original')
        ->and($images->calls)->toBe(0);                       // reader excluded it; cover prompt was null
});

it('leaves images null on an empty search result without erroring (no retry)', function () {
    [$collectionId, $withPrompt] = seedGeneratedCollection();
    $images = new FakePexelsImageSearch(FakePexelsImageSearch::NOT_FOUND);

    attachHandlerWith($images)(new AttachCollectionImages($collectionId));

    $imaged = DB::table('terms')->where('id', $withPrompt->value)->first();
    $collection = DB::table('collections')->where('id', $collectionId->value)->first();
    expect($imaged->image_url)->toBeNull()
        ->and($collection->image_url)->toBeNull()
        ->and($images->calls)->toBe(2);                       // searched term + cover, both empty
});

it('propagates a transient error so the job retries', function () {
    [$collectionId] = seedGeneratedCollection();

    attachHandlerWith(new FakePexelsImageSearch(FakePexelsImageSearch::RATE_LIMITED))(
        new AttachCollectionImages($collectionId),
    );
})->throws(TransientImageSearchError::class);
