<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Command;

use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Vocabulary\Domain\Entity\Term;
use App\Modules\Vocabulary\Domain\Repository\TermRepository;
use App\Modules\Vocabulary\Domain\Service\TermNormalizer;
use App\Modules\Shared\Domain\ValueObject\TermId;

/**
 * The one and only path to creating a term. Deduplicates on
 * (lang, normalized_text, pos); merges new translations into an existing term.
 */
final readonly class FindOrCreateTermHandler
{
    public function __construct(
        private TermRepository $terms,
        private TermNormalizer $normalizer,
        private Clock $clock,
    ) {}

    public function __invoke(FindOrCreateTerm $command): TermId
    {
        $normalized = $this->normalizer->normalize($command->text->value, $command->type);

        $existing = $this->terms->findByDedup($command->lang, $normalized, $command->pos);
        if ($existing !== null) {
            foreach ($command->translations as $translation) {
                $existing->addTranslation($translation);
            }
            foreach ($command->examples as $example) {
                $existing->addExample($example);
            }
            $existing->ensureIpa($command->ipa);
            $existing->ensureCefr($command->cefr);
            $existing->ensureImageApiPrompt($command->imageApiPrompt);
            $this->terms->save($existing);

            return $existing->id();
        }

        $term = Term::create(
            id: TermId::generate(),
            lang: $command->lang,
            text: $command->text,
            normalizedText: $normalized,
            type: $command->type,
            pos: $command->pos,
            source: $command->source,
            createdAt: $this->clock->now(),
            translations: $command->translations,
            ipa: $command->ipa,
            examples: $command->examples,
            cefr: $command->cefr,
            imageApiPrompt: $command->imageApiPrompt,
            provenance: $command->provenance,
        );

        $this->terms->save($term);

        return $term->id();
    }
}
