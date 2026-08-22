<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\Service;

/**
 * Which language pairs this deployment will search in.
 *
 * ONE FIXED SIDE. Every pair is «the language being taught» ↔ «a language the learner reads», and
 * in v1 the first is always English. That is a product decision rather than a schema one — a term
 * carries its own `lang` and a term may hold translations in several languages at once, so a pair
 * without English is representable today — but the content around it is not ready: see
 * docs/search-language-pair.md.
 *
 * DIRECTION IS NOT VALIDATED HERE, only membership. «EN → RU» and «RU → EN» are the same pair asked
 * two ways, and both are legitimate; which way round a given search goes is the learner's choice on
 * the pill, not something this class has an opinion about.
 */
final readonly class SupportedLanguages
{
    /** @param list<string> $natives */
    public function __construct(
        private string $target,
        private array $natives,
    ) {}

    /** The language being taught — the fixed half of every pair. */
    public function target(): string
    {
        return $this->target;
    }

    /**
     * The languages a learner may read, in the order the pill offers them.
     *
     * @return list<string>
     */
    public function natives(): array
    {
        return $this->natives;
    }

    /** Is `$code` a language this deployment can put on the learner's side? */
    public function knowsNative(string $code): bool
    {
        return in_array($this->normalize($code), $this->natives, true);
    }

    /**
     * Is this an orderable pair — exactly one side the taught language, the other one we know?
     *
     * «Exactly one» rules out `en → en`, which would ask the vendor to translate a word into its
     * own language and is the shape a swapped-twice client bug takes.
     */
    public function supports(string $source, string $target): bool
    {
        [$from, $to] = [$this->normalize($source), $this->normalize($target)];

        return ($from === $this->target && $this->knowsNative($to))
            || ($to === $this->target && $this->knowsNative($from));
    }

    /** The learner's side of a pair this class has already accepted. */
    public function nativeSideOf(string $source, string $target): string
    {
        return $this->normalize($source) === $this->target
            ? $this->normalize($target)
            : $this->normalize($source);
    }

    private function normalize(string $code): string
    {
        return strtolower(trim($code));
    }
}
