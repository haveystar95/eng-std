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
            TermModel::query()->updateOrCreate(
                ['id' => $term->id()->value],
                $this->mapper->toAttributes($term),
            );

            foreach ($term->translations() as $translation) {
                TermTranslationModel::query()->firstOrCreate(
                    [
                        'term_id' => $term->id()->value,
                        'lang' => $translation->lang->value,
                        'text' => $translation->text,
                    ],
                    [
                        'id' => Ulid::generate(),
                        'is_primary' => $translation->isPrimary,
                    ],
                );
            }

            foreach ($term->examples() as $example) {
                TermExampleModel::query()->firstOrCreate(
                    [
                        'term_id' => $term->id()->value,
                        'sentence' => $example->sentence,
                    ],
                    [
                        'id' => Ulid::generate(),
                        'sentence_translation' => $example->sentenceTranslation,
                        'source' => $term->source()->value,
                    ],
                );
            }
        });
    }
}
