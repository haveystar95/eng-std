<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Service;

/**
 * THE table of languages this product knows — one row per language, one copy per repository.
 *
 * Three runtimes need the same four facts about a language and used to keep private copies of
 * them: backend2 had EIGHT tables of English names (seven `LANGUAGE_NAMES` constants plus a
 * `match` in the bake-off runner), the app had its endonyms and flags, the admin console had a
 * four-entry `langLabel()`. Copies of a table are not a style problem: `ro` was missing from every
 * backend copy while the app already offered Romanian in its picker, so a Romanian collection
 * rendered its prompt as «write in ro» — a language the model has to guess at — and nobody saw it,
 * because each copy looked complete on its own.
 *
 * The other two catalogues (`mobile/lib/l10n/language_endonyms.dart`,
 * `wt_admin/src/utils/languages.ts`) hold the same codes with the same spellings, and each repo
 * has a test that the list is complete and has no duplicate code. They are three files rather than
 * one generated artefact because three runtimes ship separately; keeping the columns identical is
 * what makes a divergence a one-line diff instead of an investigation.
 *
 * The columns:
 *
 * - `name` — the ENGLISH name, and the only column backend2 reads today. Prompts carry
 *   `{{source_lang}}` / `{{target_lang}}` as "Russian" / "English" rather than as ISO codes;
 *   {@see LanguageName} is the reader.
 * - `endonym` — the language's name in itself. This is what the USER sees: a picker that offers
 *   "Romanian" to a Romanian speaker is naming their language in someone else's.
 * - `nameRu` — the name in Russian, for surfaces written in the interface language rather than in
 *   the language being named (admin console columns, reports).
 * - `flag` — the emoji shown beside the name in a picker. `en` flies 🇬🇧 by product choice.
 *
 * Membership is the capability matrix's list (`docs/research/language-capability-matrix.md`):
 * the seven taught languages, the two reference-only ones (zh, ja), the three support languages,
 * plus `pt` and `tr`, which the table has always carried. Being in this table is NOT permission to
 * teach a language — that is {@see LanguageRoles}, which reads the capability table. This table
 * only says how a language is NAMED. Being in it IS, however, permission to READ in a language:
 * the support side of a pair takes a name and nothing else (DECISIONS п. 85).
 *
 * An unknown code is not guessed at anywhere: see {@see LanguageName::of()}.
 */
final class LanguageCatalog
{
    /**
     * Keyed by ISO 639-1 code, in the order the app's pickers list them.
     *
     * @var array<string, array{name: string, endonym: string, nameRu: string, flag: string}>
     */
    private const LANGUAGES = [
        'ru' => ['name' => 'Russian', 'endonym' => 'Русский', 'nameRu' => 'Русский', 'flag' => '🇷🇺'],
        'en' => ['name' => 'English', 'endonym' => 'English', 'nameRu' => 'Английский', 'flag' => '🇬🇧'],
        'uk' => ['name' => 'Ukrainian', 'endonym' => 'Українська', 'nameRu' => 'Украинский', 'flag' => '🇺🇦'],
        'ro' => ['name' => 'Romanian', 'endonym' => 'Română', 'nameRu' => 'Румынский', 'flag' => '🇷🇴'],
        'es' => ['name' => 'Spanish', 'endonym' => 'Español', 'nameRu' => 'Испанский', 'flag' => '🇪🇸'],
        'de' => ['name' => 'German', 'endonym' => 'Deutsch', 'nameRu' => 'Немецкий', 'flag' => '🇩🇪'],
        'fr' => ['name' => 'French', 'endonym' => 'Français', 'nameRu' => 'Французский', 'flag' => '🇫🇷'],
        'it' => ['name' => 'Italian', 'endonym' => 'Italiano', 'nameRu' => 'Итальянский', 'flag' => '🇮🇹'],
        'pt' => ['name' => 'Portuguese', 'endonym' => 'Português', 'nameRu' => 'Португальский', 'flag' => '🇵🇹'],
        'pl' => ['name' => 'Polish', 'endonym' => 'Polski', 'nameRu' => 'Польский', 'flag' => '🇵🇱'],
        'tr' => ['name' => 'Turkish', 'endonym' => 'Türkçe', 'nameRu' => 'Турецкий', 'flag' => '🇹🇷'],
        'zh' => ['name' => 'Chinese', 'endonym' => '中文', 'nameRu' => 'Китайский', 'flag' => '🇨🇳'],
        'ja' => ['name' => 'Japanese', 'endonym' => '日本語', 'nameRu' => 'Японский', 'flag' => '🇯🇵'],
    ];

    /** @return array<string, array{name: string, endonym: string, nameRu: string, flag: string}> */
    public static function all(): array
    {
        return self::LANGUAGES;
    }

    /** @return list<string> */
    public static function codes(): array
    {
        return array_keys(self::LANGUAGES);
    }

    public static function knows(string $code): bool
    {
        return isset(self::LANGUAGES[$code]);
    }

    /** @return array{name: string, endonym: string, nameRu: string, flag: string}|null */
    public static function entry(string $code): ?array
    {
        return self::LANGUAGES[$code] ?? null;
    }
}
