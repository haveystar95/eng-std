<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Command;

use App\Modules\Generation\Application\Dto\TermReadingBrief;
use App\Modules\Generation\Application\Port\TermTransliteratorPort;
use App\Modules\Generation\Domain\Service\EnrichmentValidator;
use App\Modules\Generation\Domain\ValueObject\TermReadingOutcome;
use App\Modules\Shared\Domain\Service\LanguagePurity;
use App\Modules\Vocabulary\Application\Port\TermTransliterationWriter;
use App\Modules\Vocabulary\Application\Query\TermReadingTargetReader;

/**
 * The reading hint for ONE word, bought at the moment a card is built for it.
 *
 * The станок writes this field for a whole generated collection; the two doors that build a single
 * card — «Собрать карточку» in the translator and a word typed into a folder — never did, so a word
 * that arrived through either of them had no hint and never would. This is that gap closed, and it
 * is deliberately the SAME product rather than a cheaper one near it: the same section of the same
 * core prompt, sent to the same strong model, stamped with the same version. One word is pennies,
 * and a second, cheaper reading writer would be a second answer to «how does this word sound».
 *
 * ## Four ways to write nothing, and only one of them costs money
 *
 * The order of the checks IS the cost control, and each one is in front of the call rather than
 * behind it:
 *
 *  1. the switch — `GENERATION_WRITE_TRANSLITERATION`, the same one the core reads, so «stop writing
 *     readings» means it on every door and not just on the loud one;
 *  2. «does it already have one» — a term is globally deduplicated, so the word being saved today
 *     may have been given its hint by a collection generated a month ago. The writer would refuse
 *     the duplicate anyway; asking first is what makes the repeat FREE;
 *  3. «are the two alphabets the same» — the prompt's own answer for an en→ro pair is an empty
 *     string, and buying an empty string on every save is the kind of bleed nobody notices;
 *  4. the alphabet gate, after the call, which is the one check that cannot come first.
 *
 * Nothing here throws for content reasons. A card is not conditional on its reading hint: a refusal
 * is an outcome, the caller writes it down, and the learner has their word either way.
 */
final readonly class WriteTermReadingHandler
{
    public function __construct(
        private TermReadingTargetReader $targets,
        private TermTransliteratorPort $model,
        private TermTransliterationWriter $writer,
        private LanguagePurity $purity = new LanguagePurity(),
        /** The SAME deterministic alphabet gate the core's own hints pass. One product, one judge. */
        private EnrichmentValidator $gate = new EnrichmentValidator(),
        /** `GENERATION_WRITE_TRANSLITERATION` — bound in the provider, like the core's copy of it. */
        private bool $writeTransliteration = true,
    ) {}

    public function __invoke(WriteTermReading $command): TermReadingOutcome
    {
        if (! $this->writeTransliteration) {
            return TermReadingOutcome::Disabled;
        }

        $target = $this->targets->find($command->termId, $command->supportLang);
        if ($target === null) {
            return TermReadingOutcome::AlreadyPresent;
        }

        // Not «are the language codes equal» but «does this word carry a letter the support reader
        // cannot read»: that is the question the field exists to answer, and it is the same reading
        // of the same rule the prompt states in its last bullet.
        if ($this->purity->foreignScriptLetters($command->supportLang, $target->text) === []) {
            return TermReadingOutcome::SameAlphabet;
        }

        $answer = $this->model->read(new TermReadingBrief(
            text: $target->text,
            termLang: $target->lang,
            supportLang: $command->supportLang,
        ));

        $hint = $this->gate->transliterationFor($command->supportLang, $answer->text);
        if ($hint === null) {
            return TermReadingOutcome::Refused;
        }

        // `ensure` can still return false under a race — two saves of the same word at once — and
        // that is the unique index doing its job, not a failure. Reported as «already there», which
        // is what it is by the time this returns.
        $written = $this->writer->ensure(
            $command->termId,
            $command->supportLang,
            $hint,
            generatorVersion: $answer->promptVersion,
        );

        return $written ? TermReadingOutcome::Written : TermReadingOutcome::AlreadyPresent;
    }
}
