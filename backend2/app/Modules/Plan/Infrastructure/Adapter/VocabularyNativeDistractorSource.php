<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Adapter;

use App\Modules\Plan\Application\Port\NativeDistractorSource;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Vocabulary\Application\Query\NativeDistractorReader;

/** The catalogue top-up for a thin Beginner choice card, through Vocabulary's own reader. */
final readonly class VocabularyNativeDistractorSource implements NativeDistractorSource
{
    public function __construct(private NativeDistractorReader $reader) {}

    public function translations(LanguageCode $targetLang, LanguageCode $nativeLang, string $like, array $exclude, int $count): array
    {
        return $this->reader->translations($targetLang, $nativeLang, $like, $exclude, $count);
    }
}
