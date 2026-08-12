<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Command;

use App\Modules\Generation\Application\Dto\LanguageRepairReport;
use App\Modules\Generation\Application\Dto\TranslationRepairBrief;
use App\Modules\Generation\Application\Port\TranslationRepairPort;
use App\Modules\Generation\Application\Service\LanguageBarrier;
use App\Modules\Shared\Domain\Service\LanguagePurity;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Command\CurateTerm;
use App\Modules\Vocabulary\Application\Command\CurateTermHandler;
use App\Modules\Vocabulary\Application\Command\DropTermTranslation;
use App\Modules\Vocabulary\Application\Command\DropTermTranslationHandler;
use App\Modules\Vocabulary\Application\Dto\TermLanguageRow;
use App\Modules\Vocabulary\Application\Query\TermLanguageAuditReader;
use Throwable;

/**
 * The retrospective half of the language fix: the barrier stops NEW bad content, this cleans up what
 * was written before it existed.
 *
 * It is not a barrier and it is not the станок. The станок reports; the barrier refuses; this one
 * repairs — because for content already on a phone there is no "refuse", there is only "make it
 * right and let the delta carry it".
 *
 * Two offences, two repairs, and the difference is the whole reason this is not one loop:
 *
 *  - a translation row honestly labelled as another language, with a correct one beside it, is
 *    DELETED. Nothing is corrupt about it — it was simply the wrong row to show — and asking a model
 *    to re-translate a term that already has a good Russian translation would spend money to
 *    produce a second one;
 *  - a field that is supposed to be in the learner's language and is not — an example translation,
 *    or a translation with no correct sibling — is RE-TRANSLATED, through the same port and the
 *    same detector the barrier uses. The English sentence is passed through unchanged and written
 *    back unchanged, which is what keeps the distractors hanging off it valid (and, on the pinned
 *    example, what keeps the card the learner is looking at the same card).
 *
 * Every write goes through Vocabulary's Application, which bumps `terms.updated_at` — the column
 * the delta sync detects a changed term by. A repair that forgets that is a repair the phone never
 * hears about, which is the lesson of 46f9076 and the reason this does not touch tables directly.
 */
final readonly class RepairContentLanguageHandler
{
    public function __construct(
        private TermLanguageAuditReader $audit,
        private TranslationRepairPort $repairer,
        private CurateTermHandler $curate,
        private DropTermTranslationHandler $dropTranslation,
        private LanguagePurity $purity = new LanguagePurity(),
    ) {}

    /** @return list<LanguageRepairReport> */
    public function __invoke(RepairContentLanguage $command): array
    {
        $lang = $command->sourceLang->value;
        $out = [];

        foreach ($this->audit->reachableRows($lang) as $row) {
            $verdict = $this->judge($row, $lang);
            if ($verdict === null) {
                continue;
            }

            $out[] = $command->apply
                ? $this->repair($row, $command->sourceLang, $verdict)
                : new LanguageRepairReport($row->termId, $row->termText, $row->field, $row->value, null, 'planned', $verdict);
        }

        return $out;
    }

    /**
     * Why this row is wrong, or null if it is fine. Two independent questions, because a row can
     * fail either: does it claim to be in a language other than the learner's, and — whatever it
     * claims — does it contain letters that language does not have?
     */
    private function judge(TermLanguageRow $row, string $lang): ?string
    {
        if ($row->declaredLang !== $lang) {
            return "строка помечена «{$row->declaredLang}», а коллекция на «{$lang}»";
        }

        $foreign = $this->purity->foreignLetters($lang, $row->value);
        if ($foreign !== []) {
            return 'чужие буквы (' . implode(' ', $foreign) . ") в поле на «{$lang}»";
        }

        // The whole-field case the letter check cannot see — see LanguagePurity::isWrongScript().
        if ($this->purity->isWrongScript($lang, $row->value)) {
            return "поле на «{$lang}», а написано другим письмом";
        }

        return null;
    }

    private function repair(TermLanguageRow $row, LanguageCode $lang, string $why): LanguageRepairReport
    {
        $termId = TermId::fromString($row->termId);

        // The cheap case: a correct translation is already there, so the wrong-language one is
        // surplus rather than damage.
        if ($row->field === 'translation' && $row->hasSiblingInDeclared) {
            $dropped = ($this->dropTranslation)(new DropTermTranslation($termId, $row->rowId));

            return new LanguageRepairReport(
                $row->termId, $row->termText, $row->field, $row->value, null,
                $dropped ? 'dropped' : 'unfixable',
                $dropped ? $why . '; удалена, корректный перевод остался' : $why . '; удалить не удалось',
            );
        }

        [$value, $attempts, $error] = $this->retranslate($row, $lang);

        if ($value === null) {
            return new LanguageRepairReport(
                $row->termId, $row->termText, $row->field, $row->value, null, 'unfixable',
                $why . '; ' . $error, $attempts,
            );
        }

        $written = $row->field === 'translation'
            ? ($this->curate)(new CurateTerm($termId, translation: $value))
            : ($this->curate)(new CurateTerm(
                $termId,
                // The sentence is passed back byte for byte: an example whose sentence changed drops
                // its distractors, and nothing about this repair concerns the English side.
                exampleId: $row->rowId,
                exampleSentence: (string) $row->exampleSentence,
                exampleTranslation: $value,
            ));

        return new LanguageRepairReport(
            $row->termId, $row->termText, $row->field, $row->value,
            $written ? $value : null,
            $written ? 'retranslated' : 'unfixable',
            $written ? $why : $why . '; запись не прошла',
            $attempts,
        );
    }

    /**
     * Ask for a clean rendering, up to the barrier's own attempt budget, and check the answer with
     * the same detector. Same number as the barrier on purpose: "how many times is it worth asking
     * the same model the same question" has one answer, and two places disagreeing about it would
     * mean a term the barrier gives up on gets a third chance here for no stated reason.
     *
     * @return array{0: string|null, 1: int, 2: string}  clean value, attempts spent, why not
     */
    private function retranslate(TermLanguageRow $row, LanguageCode $lang): array
    {
        $attempts = 0;
        $last = 'модель не ответила';

        while ($attempts < LanguageBarrier::MAX_ATTEMPTS) {
            $attempts++;

            try {
                $repaired = $this->repairer->repair(new TranslationRepairBrief(
                    text: $row->termText,
                    type: $row->termType,
                    sentence: $row->exampleSentence,
                    targetLang: new LanguageCode($row->termLang),
                    sourceLang: $lang,
                ));
            } catch (Throwable $e) {
                $last = 'вызов упал: ' . $e->getMessage();

                continue;
            }

            $value = $row->field === 'translation' ? $repaired->translation : $repaired->exampleTranslation;
            if ($value === null || trim($value) === '') {
                $last = 'модель вернула пустое поле';

                continue;
            }

            if ($this->purity->isClean($lang->value, $value)) {
                return [trim($value), $attempts, ''];
            }

            $last = "снова не на «{$lang->value}»: «{$value}»";
        }

        return [null, $attempts, "починка не удалась за {$attempts} попыт(ки): {$last}"];
    }
}
