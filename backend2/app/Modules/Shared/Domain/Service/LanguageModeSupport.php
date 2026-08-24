<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Service;

/**
 * WHICH TRAINERS A LANGUAGE CAN CARRY — the sibling table of {@see LanguageCatalog}, and the second
 * half of the mode gate.
 *
 * The admin matrix (`learning_mode_settings`) is a PRODUCT judgement: which trainers are switched
 * on, and from which rung of the ladder. This is a CAPABILITY fact: whether a trainer's question is
 * honest in this language at all. The effective availability of a mode is the intersection of the
 * two, and it points one way only — the panel can CLOSE a trainer, and can never open one for a
 * language that cannot carry it (DECISIONS п. 130). Which is why this is code and not a column:
 * a capability that an admin screen can toggle is not a capability.
 *
 * The rows are the capability matrix's (`docs/research/language-capability-matrix.md` §2, §2.1),
 * turned into decisions:
 *
 *  - **`pick_correct` is English-only in v1** (п. 47). A distractor is a sentence broken on purpose,
 *    and the taxonomy of breakages ({@see \App\Modules\Generation\Domain\ValueObject\ErrorType},
 *    six classes fixed by a CHECK constraint) is written as «типичные ошибки русскоязычного в
 *    английском» — `article` does not exist as a class in Polish at all. Without a judge that can
 *    say «broken exactly once and exactly as claimed», the card is not a question, it is a lottery.
 *    The mode opens for a language when that judge exists for it.
 *  - **`speaking` and `dictation` in pl and ro are ONLINE-ONLY** (п. 48). iOS has no on-device
 *    recognition for either language, so the trainer works with a network and, without one, is
 *    Skipped free of charge. Available, not absent — {@see isOnlineOnly()} is what the client reads
 *    to say so before the learner is left talking to a microphone that is not listening.
 *  - **zh and ja carry NO trainers** (пп. 84, 136). They are reference collections in v1: neither
 *    language puts spaces between words, so `word_bank` and `scramble` deal one chip for a whole
 *    sentence, and typing goes through an IME — a sequence of choices rather than a spelling, on
 *    which «one character apart» means a different word, not a typo.
 *  - **A language this product does not teach carries no trainers either.** Not a slight: v1 teaches
 *    seven languages (п. 83), and a term in a language outside that list has no strictness rules, no
 *    normalisation and no grader written for it. Silence would be the dangerous answer here.
 *
 * Mode names are STRINGS, not `ExerciseMode`: `Shared\Domain` is the kernel and imports no module,
 * Learning included. Learning turns them back into its enum in one place
 * ({@see \App\Modules\Learning\Domain\ValueObject\EnabledModes::forLanguage()}), and a test pins
 * the two vocabularies together so a renamed mode cannot drift into a table nobody re-reads.
 */
final class LanguageModeSupport
{
    /**
     * Every trainer the app has, in the registry's own order. The taught languages are described as
     * «all of these, minus …», because that is how the capability matrix reads and how a new trainer
     * should arrive: available everywhere unless a language cannot carry it.
     *
     * @var list<string>
     */
    private const ALL_MODES = [
        'intro',
        'multiple_choice',
        'word_bank',
        'cloze',
        'typing',
        'listening',
        'scramble',
        'dictation',
        'pick_correct',
        'speaking',
        'description_match',
    ];

    /**
     * Language => what it cannot carry, and what it carries only with a network.
     *
     * A language ABSENT from this table carries nothing: see the class docblock. Present with an
     * empty `closed` means the full set.
     *
     * @var array<string, array{closed: list<string>, online_only: list<string>}>
     */
    private const SUPPORT = [
        // The language every gate, grader and distractor taxonomy in this app was written for.
        'en' => ['closed' => [], 'online_only' => []],
        // Vendors are complete (DeepL, TTS, on-device STT); only the distractor judge is missing.
        'de' => ['closed' => ['pick_correct'], 'online_only' => []],
        'es' => ['closed' => ['pick_correct'], 'online_only' => []],
        'it' => ['closed' => ['pick_correct'], 'online_only' => []],
        'fr' => ['closed' => ['pick_correct'], 'online_only' => []],
        // …plus no on-device recognition, so the two trainers that listen need a network.
        'pl' => ['closed' => ['pick_correct'], 'online_only' => ['speaking', 'dictation']],
        'ro' => ['closed' => ['pick_correct'], 'online_only' => ['speaking', 'dictation']],
        // Reference-only in v1: a collection, an audio and a translation — no training at all.
        'zh' => ['closed' => self::ALL_MODES, 'online_only' => []],
        'ja' => ['closed' => self::ALL_MODES, 'online_only' => []],
    ];

    /** @return list<string> the modes this language can carry, in registry order; empty = none */
    public static function modesFor(string $lang): array
    {
        $row = self::SUPPORT[$lang] ?? null;
        if ($row === null) {
            return [];
        }

        return array_values(array_filter(
            self::ALL_MODES,
            static fn (string $mode): bool => ! in_array($mode, $row['closed'], true),
        ));
    }

    public static function supports(string $lang, string $mode): bool
    {
        return in_array($mode, self::modesFor($lang), true);
    }

    /**
     * Does this trainer need a network in this language? True only for a mode the language DOES
     * carry — «closed» and «online-only» are different answers and must not collapse into one.
     */
    public static function isOnlineOnly(string $lang, string $mode): bool
    {
        $row = self::SUPPORT[$lang] ?? null;

        return $row !== null
            && in_array($mode, $row['online_only'], true)
            && self::supports($lang, $mode);
    }

    /** Every language with an entry here — the taught seven plus the two reference ones. */
    /** @return list<string> */
    public static function languages(): array
    {
        return array_keys(self::SUPPORT);
    }

    /** @return list<string> */
    public static function allModes(): array
    {
        return self::ALL_MODES;
    }
}
