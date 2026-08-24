<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Infrastructure\Eloquent;

use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Vocabulary\Domain\Entity\Term;
use App\Modules\Vocabulary\Domain\Repository\TermRepository;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Vocabulary\Domain\ValueObject\PartOfSpeech;
use App\Modules\Shared\Domain\ValueObject\TermId;
use Illuminate\Support\Facades\DB;

final class EloquentTermRepository implements TermRepository
{
    public function __construct(private readonly TermMapper $mapper) {}

    public function findByDedup(LanguageCode $lang, string $normalizedText, ?PartOfSpeech $pos): ?Term
    {
        $query = TermModel::query()
            ->with('translations')
            ->where('lang', $lang->value)
            ->where('normalized_text', $normalizedText);

        $pos === null
            ? $query->whereNull('pos')
            : $query->where('pos', $pos->value);

        $model = $query->first();

        return $model !== null ? $this->mapper->toDomain($model) : null;
    }

    public function findById(TermId $id): ?Term
    {
        $model = TermModel::query()->with('translations')->find($id->value);

        return $model !== null ? $this->mapper->toDomain($model) : null;
    }

    public function save(Term $term): void
    {
        DB::transaction(function () use ($term): void {
            // Did anything a synced device holds about this term's translations change?
            $repinned = false;

            TermModel::query()->updateOrCreate(
                ['id' => $term->id()->value],
                $this->mapper->toAttributes($term),
            );

            foreach ($term->translations() as $translation) {
                $row = TermTranslationModel::query()->firstOrCreate(
                    [
                        'term_id' => $term->id()->value,
                        'lang' => $translation->lang->value,
                        'text' => $translation->text,
                    ],
                    [
                        'id' => Ulid::generate(),
                        'is_primary' => $translation->isPrimary,
                        // Provenance is stamped on CREATE only: firstOrCreate leaves an existing
                        // row alone, so a re-run can never re-label content it did not write.
                        'prompt_version' => $translation->provenance?->promptVersion,
                        'generation_model' => $translation->provenance?->model,
                    ],
                );

                // …and `is_primary` is the one column an EXISTING row still has to hear about. The
                // pin only moves through {@see Term::pinTranslation()} now, and a promotion or
                // demotion that stopped at the aggregate would leave the table saying the opposite.
                // Text and provenance stay untouched: the row keeps saying what it said and who
                // wrote it, it only stops (or starts) being the question on the card.
                if (! $row->wasRecentlyCreated && $row->is_primary !== $translation->isPrimary) {
                    $row->forceFill(['is_primary' => $translation->isPrimary])->save();
                    $repinned = true;
                }
                // A NEW alternative reading is a change the device has to hear about too, now that
                // the client is shipped every translation beside the pinned one
                // ({@see \App\Modules\Vocabulary\Application\Dto\TermContentView::$translations}).
                // Before that list existed only the pin was visible, so only a re-pin mattered.
                if ($row->wasRecentlyCreated) {
                    $repinned = true;
                }
            }

            // The delta feed decides what to ship by `terms.updated_at`, and a translation lives in
            // ANOTHER table — so a moved pin, or a reading added beside it, would never reach an
            // already-synced phone (the QA-19 shape, seen there on a replaced example). Only when
            // something actually changed: a merge that changed nothing must not re-send the term to
            // every client.
            if ($repinned) {
                TermModel::query()->where('id', $term->id()->value)->update(['updated_at' => now()]);
            }

            foreach ($term->examples() as $example) {
                $row = TermExampleModel::query()->firstOrCreate(
                    [
                        'term_id' => $term->id()->value,
                        'sentence' => $example->sentence,
                    ],
                    [
                        'id' => Ulid::generate(),
                        // The sentence uses the term, so it is in the term's language — the
                        // aggregate's own `lang`, never a second opinion from the caller.
                        'lang' => $term->lang()->value,
                        'source' => $term->source()->value,
                        'prompt_version' => $example->provenance?->promptVersion,
                        'generation_model' => $example->provenance?->model,
                    ],
                );

                // The gloss goes in beside the sentence, in the language it was written in. Ignoring
                // on conflict for the same reason the translation merge above uses firstOrCreate: a
                // second generation pass that produces the same sentence must not overwrite a gloss
                // a human may have since corrected. A gloss in a NEW language is a new row, which is
                // the whole point of the table — the same example glossed for a ru learner and for a
                // uk one is two rows, not a coin flip between them.
                if ($example->sentenceTranslation !== null
                    && trim($example->sentenceTranslation) !== ''
                    && $example->translationLang !== null) {
                    DB::table('example_translations')->insertOrIgnore([
                        'id' => Ulid::generate(),
                        'term_example_id' => $row->id,
                        'lang' => $example->translationLang->value,
                        'text' => $example->sentenceTranslation,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });
    }
}
