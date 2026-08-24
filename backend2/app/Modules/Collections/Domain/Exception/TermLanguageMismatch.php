<?php

declare(strict_types=1);

namespace App\Modules\Collections\Domain\Exception;

use App\Modules\Shared\Domain\Exception\ProblemDetails;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use DomainException;

/**
 * A word of one pair offered to a folder of another.
 *
 * A refusal and not a repair on purpose: the app cannot know whether the learner meant the other
 * folder or a different word, and writing the row anyway is how a folder quietly stops being one
 * pair — after which its cards have no single language to be asked in. The client has everything it
 * needs to say so: `expected` is the language this folder teaches, `actual` is the word's.
 */
final class TermLanguageMismatch extends DomainException implements ProblemDetails
{
    private function __construct(
        private readonly string $collectionId,
        private readonly string $expected,
        private readonly string $actual,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function make(CollectionId $collectionId, LanguageCode $expected, LanguageCode $actual): self
    {
        return new self(
            $collectionId->value,
            $expected->value,
            $actual->value,
            "Эта коллекция изучает {$expected->value}; слово на {$actual->value} в неё не кладётся — "
            . 'для другой пары нужна другая коллекция.',
        );
    }

    public function problemStatus(): int
    {
        return 422;
    }

    public function problemCode(): string
    {
        return 'term_language_mismatch';
    }

    public function problemTitle(): string
    {
        return 'Term language does not match the collection pair';
    }

    public function problemMeta(): array
    {
        return [
            'collection_id' => $this->collectionId,
            'expected_lang' => $this->expected,
            'actual_lang' => $this->actual,
        ];
    }
}
