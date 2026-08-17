<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Infrastructure\Eloquent;

use App\Modules\Vocabulary\Domain\Entity\Term;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Vocabulary\Domain\ValueObject\PartOfSpeech;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Domain\ValueObject\TermSource;
use App\Modules\Vocabulary\Domain\ValueObject\TermText;
use App\Modules\Vocabulary\Domain\ValueObject\TermType;
use App\Modules\Vocabulary\Domain\ValueObject\Translation;

final class TermMapper
{
    public function toDomain(TermModel $model): Term
    {
        $translations = [];
        foreach ($model->translations as $row) {
            $translations[] = new Translation(new LanguageCode($row->lang), $row->text, $row->is_primary);
        }

        return Term::create(
            id: TermId::fromString($model->id),
            lang: new LanguageCode($model->lang),
            text: new TermText($model->text),
            normalizedText: $model->normalized_text,
            type: TermType::from($model->type),
            pos: $model->pos !== null ? PartOfSpeech::from($model->pos) : null,
            source: TermSource::from($model->source),
            createdAt: $model->created_at->toDateTimeImmutable(),
            translations: $translations,
            ipa: $model->ipa !== null ? (string) $model->ipa : null,
            cefr: $model->cefr !== null ? (string) $model->cefr : null,
            imageUrl: $model->image_url !== null ? (string) $model->image_url : null,
            imageApiPrompt: $model->image_api_prompt !== null ? (string) $model->image_api_prompt : null,
            imageAuthor: $model->image_author !== null ? (string) $model->image_author : null,
            imageAuthorUrl: $model->image_author_url !== null ? (string) $model->image_author_url : null,
        );
    }

    /** @return array<string, mixed> */
    public function toAttributes(Term $term): array
    {
        return [
            'lang' => $term->lang()->value,
            'text' => $term->text()->value,
            'normalized_text' => $term->normalizedText(),
            'type' => $term->type()->value,
            'pos' => $term->pos()?->value,
            'ipa' => $term->ipa(),
            'cefr' => $term->cefr(),
            'image_url' => $term->imageUrl(),
            'image_api_prompt' => $term->imageApiPrompt(),
            'image_author' => $term->imageAuthor(),
            'image_author_url' => $term->imageAuthorUrl(),
            'source' => $term->source()->value,
            'created_at' => $term->createdAt(),
        ];
    }
}
