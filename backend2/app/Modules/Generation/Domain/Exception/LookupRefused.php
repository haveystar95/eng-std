<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\Exception;

use App\Modules\Shared\Domain\Exception\ProblemDetails;
use DomainException;

/**
 * The model answered, and the answer is not storable.
 *
 * 422 rather than 502: the vendor call succeeded, so this is not an outage the client should retry
 * against — it is a refusal about the CONTENT, and retrying the same query usually produces the same
 * refusal. The `reason` rides in the problem meta so a log can group by it; the client shows one
 * honest line («это слово не удалось найти») and offers the database results it already has.
 */
final class LookupRefused extends DomainException implements ProblemDetails
{
    private function __construct(string $message, private readonly string $reason)
    {
        parent::__construct($message);
    }

    public static function wrongLanguage(string $field, string $lang, string $value): self
    {
        return new self(
            "The lookup's «{$field}» is not written in «{$lang}»: «{$value}».",
            'wrong_language',
        );
    }

    public static function descriptionGivesAway(string $term, string $description): self
    {
        return new self(
            "The description of «{$term}» contains the word itself: «{$description}».",
            'description_gives_away',
        );
    }

    public static function emptyQuery(): self
    {
        return new self('A lookup needs something to look up.', 'empty_query');
    }

    public static function modelUnavailable(): self
    {
        return new self('The lookup model could not be reached.', 'model_unavailable');
    }

    public function problemStatus(): int
    {
        return 422;
    }

    public function problemCode(): string
    {
        return 'lookup_refused';
    }

    public function problemTitle(): string
    {
        return 'The word could not be looked up';
    }

    public function problemMeta(): array
    {
        return ['reason' => $this->reason];
    }
}
