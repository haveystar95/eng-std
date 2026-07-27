<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Modules\Vocabulary\Domain\Entity\Term;
use App\Modules\Vocabulary\Domain\Repository\TermRepository;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Vocabulary\Domain\ValueObject\PartOfSpeech;
use App\Modules\Shared\Domain\ValueObject\TermId;

final class InMemoryTermRepository implements TermRepository
{
    /** @var array<string, Term> */
    private array $byKey = [];

    /** @var array<string, Term> */
    private array $byId = [];

    public function findByDedup(LanguageCode $lang, string $normalizedText, ?PartOfSpeech $pos): ?Term
    {
        return $this->byKey[$this->key($lang, $normalizedText, $pos)] ?? null;
    }

    public function findById(TermId $id): ?Term
    {
        return $this->byId[$id->value] ?? null;
    }

    public function save(Term $term): void
    {
        $this->byKey[$this->key($term->lang(), $term->normalizedText(), $term->pos())] = $term;
        $this->byId[$term->id()->value] = $term;
    }

    public function count(): int
    {
        return count($this->byId);
    }

    private function key(LanguageCode $lang, string $normalizedText, ?PartOfSpeech $pos): string
    {
        return $lang->value . '|' . $normalizedText . '|' . ($pos?->value ?? '');
    }
}
