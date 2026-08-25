<?php

declare(strict_types=1);

use App\Modules\Collections\Application\Command\CreateCustomCollection;
use App\Modules\Collections\Application\Command\CreateCustomCollectionHandler;
use App\Modules\Generation\Application\Dto\TermReadingBrief;
use App\Modules\Generation\Application\Port\ImageSearchPort;
use App\Modules\Generation\Application\Port\TermEnricherPort;
use App\Modules\Generation\Application\Port\TermTransliteratorPort;
use App\Modules\Generation\Application\Port\WordLookupPort;
use App\Modules\Generation\Infrastructure\Adapter\FakePexelsImageSearch;
use App\Modules\Generation\Infrastructure\Adapter\FakeTermEnricher;
use App\Modules\Generation\Infrastructure\Adapter\FakeWordLookup;
use App\Modules\Generation\Infrastructure\Adapter\OpenAiTermTransliterator;
use App\Modules\Observability\Application\Support\OutboundCallContext;
use App\Modules\Shared\Domain\Service\LanguageName;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Doubles\CountingTermTransliterator;

uses(RefreshDatabase::class);

/**
 * The reading hint on the two doors that build ONE card: «Собрать карточку» in the translator
 * (`POST /search/add`, both of its branches) and a word typed into a folder
 * (`POST /collections/{id}/items` with no translation).
 *
 * The станок writes this field for a generated collection and always has; these two doors never did,
 * so every word that arrived through them was permanently without one. What is asserted here is
 * mostly about MONEY — which of these taps buys a strong-model call and which of them must not —
 * because that is the half that cannot be seen by looking at the table afterwards.
 */
beforeEach(function (): void {
    $this->model = new CountingTermTransliterator();
    $this->app->instance(TermTransliteratorPort::class, $this->model);
    $this->app->bind(WordLookupPort::class, FakeWordLookup::class);

    // The станок is a different product on a different model and it is not what this file is about;
    // left on, every save below would put its own (paid) call on the wire.
    config(['services.generation.auto_enrich' => false]);
});

/** Look a word up and press «Собрать карточку» — the whole Build path, as the app walks it. */
function buildCard(object $ctx, string $token, string $query = 'reimbursement'): string
{
    $lookupId = $ctx->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/search/lookup', ['query' => $query])
        ->assertOk()->json('data.lookup.lookup_id');

    return $ctx->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/search/add', ['lookup_id' => $lookupId])
        ->assertCreated()->json('data.term_id');
}

it('gives a word built from the translator its reading', function () {
    [, $token] = learner();

    $termId = buildCard($this, $token);

    $row = DB::table('term_transliterations')->where('term_id', $termId)->first();
    expect($row)->not->toBeNull()
        ->and($row->lang)->toBe('ru')
        ->and($row->text)->toBe('римбёрсмент')
        ->and($row->source)->toBe('auto')
        // The CORE's version, because the rules that produced it are the core's own section.
        ->and($row->generator_version)->toBe('v15.1');

    // Asked about the term in the pair's support language — never about the translation, and never
    // in the term's own alphabet.
    expect($this->model->calls)->toHaveCount(1)
        ->and($this->model->calls[0]->text)->toBe('reimbursement')
        ->and($this->model->calls[0]->termLang)->toBe('en')
        ->and($this->model->calls[0]->supportLang)->toBe('ru');
});

it('does not re-buy a reading the term already has', function () {
    [, $token] = learner();

    $termId = buildCard($this, $token);
    // The same tap again — a replay from a phone with a flaky connection, and the case a globally
    // deduplicated term makes ordinary: the word already carries its hint.
    buildCard($this, $token);

    expect($this->model->calls)->toHaveCount(1)
        ->and(DB::table('term_transliterations')->where('term_id', $termId)->count())->toBe(1);
});

it('lets the card live when the alphabet gate refuses the reading', function () {
    [, $token] = learner();
    // A Latin answer for a Russian reader — the one thing this field may not be, and the failure the
    // gate exists to catch.
    $this->app->instance(TermTransliteratorPort::class, $this->model = new CountingTermTransliterator('rimbersment'));

    $termId = buildCard($this, $token);

    // The call happened and was thrown away; the card is a card.
    expect($this->model->calls)->toHaveCount(1)
        ->and(DB::table('term_transliterations')->where('term_id', $termId)->count())->toBe(0)
        ->and(DB::table('terms')->where('id', $termId)->value('text'))->toBe('reimbursement');
});

it('writes nothing, and asks nothing, while the switch is off', function () {
    [, $token] = learner();
    config(['services.generation.write_transliteration' => false]);

    $termId = buildCard($this, $token);

    expect($this->model->calls)->toBe([])
        ->and(DB::table('term_transliterations')->where('term_id', $termId)->count())->toBe(0);
});

it('writes one reading per PAIR — the same word reads differently to two readers', function () {
    [$user, $token] = learner();
    $this->app->instance(TermTransliteratorPort::class, $this->model = new CountingTermTransliterator(
        fn (TermReadingBrief $brief): string => $brief->supportLang === 'uk' ? 'рімберсмент' : 'римбёрсмент',
    ));

    // Saved once into «Сохранённые» (en→ru)…
    $termId = buildCard($this, $token);

    // …and once into a folder of another pair. Same term — terms are global — and a second reader.
    $ukrainian = app(CreateCustomCollectionHandler::class)(new CreateCustomCollection(
        ownerId: UserId::fromString($user->id),
        title: 'Українська',
        sourceLang: new LanguageCode('uk'),
        targetLang: new LanguageCode('en'),
    ))->value;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/search/add', ['term_id' => $termId, 'collection_id' => $ukrainian])
        ->assertCreated();

    $hints = DB::table('term_transliterations')->where('term_id', $termId)->orderBy('lang')->get();
    expect($hints)->toHaveCount(2)
        ->and($hints[0]->lang)->toBe('ru')
        ->and($hints[0]->text)->toBe('римбёрсмент')
        ->and($hints[1]->lang)->toBe('uk')
        ->and($hints[1]->text)->toBe('рімберсмент');
    expect($this->model->calls)->toHaveCount(2);
});

it('buys nothing for a pair that already shares its alphabet', function () {
    [$user, $token] = learner();
    $termId = buildCard($this, $token);
    $this->model->calls = [];

    // en→ro: the term is already spelled in the letters this reader reads, and the prompt's own
    // answer here is an empty string. Refused before the call, not after it.
    $romanian = app(CreateCustomCollectionHandler::class)(new CreateCustomCollection(
        ownerId: UserId::fromString($user->id),
        title: 'Română',
        sourceLang: new LanguageCode('ro'),
        targetLang: new LanguageCode('en'),
    ))->value;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/search/add', ['term_id' => $termId, 'collection_id' => $romanian])
        ->assertCreated();

    expect($this->model->calls)->toBe([])
        ->and(DB::table('term_transliterations')->where('term_id', $termId)->where('lang', 'ro')->count())->toBe(0);
});

it('gives a word typed into a folder its reading too', function () {
    [$user, $token] = learner();
    $this->app->instance(TermEnricherPort::class, new FakeTermEnricher());
    $this->app->instance(ImageSearchPort::class, new FakePexelsImageSearch(FakePexelsImageSearch::FOUND));

    $collectionId = app(CreateCustomCollectionHandler::class)(new CreateCustomCollection(
        ownerId: UserId::fromString($user->id),
        title: 'Мои',
        sourceLang: new LanguageCode('ru'),
        targetLang: new LanguageCode('en'),
    ))->value;

    $termId = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/collections/{$collectionId}/items", ['text' => 'overwhelm'])
        ->assertStatus(201)
        ->json('data.items.0.term_id');

    expect($this->model->calls)->toHaveCount(1)
        ->and($this->model->calls[0]->supportLang)->toBe('ru')
        ->and(DB::table('term_transliterations')->where('term_id', $termId)->where('lang', 'ru')->count())->toBe(1);
});

it('books the reading call in the outbound log, under a purpose of its own', function () {
    Http::fake(['*' => Http::response([
        'model' => 'gpt-5.4-2026-03-05',
        'usage' => ['prompt_tokens' => 422, 'completion_tokens' => 16],
        'choices' => [['message' => ['content' => '{"transliteration":"дисмисэл"}']]],
    ], 200)]);

    $result = (new OpenAiTermTransliterator(
        context: app(OutboundCallContext::class),
        apiKey: 'test-key',
        model: 'gpt-5.4',
        promptVersion: 'v15.1',
    ))->read(new TermReadingBrief('dismissal', 'en', 'ru'));

    expect($result->text)->toBe('дисмисэл')
        ->and($result->promptVersion)->toBe('v15.1')
        ->and($result->tokensIn)->toBe(422);

    // The row is what the whitelist migration bought. `purpose` is CHECK-constrained, and
    // `LogOutboundHttp` swallows a refused insert on purpose — so a value missing from the list
    // does not fail loudly, it just makes the most expensive call in the app invisible. That is how
    // this was found, on the live run, and this is what stops it coming back.
    expect(DB::table('api_request_logs')->where('purpose', 'term_reading')->count())->toBe(1);
});

it('quotes the core prompt\'s transliteration section byte for byte', function () {
    $adapter = new OpenAiTermTransliterator(
        context: app(OutboundCallContext::class),
        apiKey: 'not-used-here',
        model: 'gpt-5.4',
        promptVersion: 'v15.1',
    );

    $extras = (string) file_get_contents(
        app_path('Modules/Generation/Infrastructure/Prompt/v15.1/21-extras.md'),
    );
    $section = $adapter->section();

    // The quote is a SUBSTRING of the live core file — not a copy that has to be kept in step, and
    // not a paraphrase. This is the whole contract of the reading path's prompt.
    expect($section)->toStartWith('## `transliteration`')
        ->and($extras)->toContain($section)
        // …and it stops where the section stops: no synonym rules dragged in behind it.
        ->and($section)->not->toContain('## `synonyms`')
        ->and($section)->not->toContain('## `other_translations`');

    // The wrapper carries the quote with the pair's own names substituted the way the core
    // substitutes them: `source_lang` is the SUPPORT side, whose alphabet the hint is written in.
    $prompt = $adapter->systemPrompt(new TermReadingBrief('cómo estás', 'es', 'ru'));
    expect($prompt)->toContain(strtr($section, [
        '{{source_lang}}' => LanguageName::of('ru'),
        '{{target_lang}}' => LanguageName::of('es'),
    ]));
});
