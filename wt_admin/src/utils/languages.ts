/**
 * THE table of languages this product knows — one row per language, one copy per repository.
 *
 * The three runtimes need the same four facts about a language and used to keep private copies of
 * them: backend2 had eight tables of English names, the app had endonyms and flags, and this
 * console had a four-entry map inside `langLabel()`. Copies of a table are not a style problem —
 * `ro` was missing from every backend copy while the app already offered Romanian, so a Romanian
 * collection reached the model as «write in ro»; each copy looked complete on its own.
 *
 * The other two catalogues are `backend2/app/Modules/Shared/Domain/Service/LanguageCatalog.php` and
 * `mobile/lib/l10n/language_endonyms.dart`; the columns and the spellings are the same in all
 * three, which is what makes a future divergence a one-line diff. Membership comes from
 * `docs/research/language-capability-matrix.md` and is held to it by `test/languages.spec.ts`.
 *
 * The columns: `endonym` — the language's name in itself, and what this console SHOWS (the app's
 * rule: a language visible to a person is named in its own language); `nameRu`/`nameEn` — the name
 * in the interface language, for a surface written about a language rather than in it; `flag` — the
 * emoji shown beside it in a picker. This console has no picker yet and carries `flag` so the three
 * catalogues stay diffable.
 */
export interface LanguageEntry {
  endonym: string
  nameRu: string
  nameEn: string
  flag: string
}

/** Keyed by ISO 639-1 code, in the order the app's pickers list them. */
export const LANGUAGES: Record<string, LanguageEntry> = {
  ru: { endonym: 'Русский', nameRu: 'Русский', nameEn: 'Russian', flag: '🇷🇺' },
  en: { endonym: 'English', nameRu: 'Английский', nameEn: 'English', flag: '🇬🇧' },
  uk: { endonym: 'Українська', nameRu: 'Украинский', nameEn: 'Ukrainian', flag: '🇺🇦' },
  ro: { endonym: 'Română', nameRu: 'Румынский', nameEn: 'Romanian', flag: '🇷🇴' },
  es: { endonym: 'Español', nameRu: 'Испанский', nameEn: 'Spanish', flag: '🇪🇸' },
  de: { endonym: 'Deutsch', nameRu: 'Немецкий', nameEn: 'German', flag: '🇩🇪' },
  fr: { endonym: 'Français', nameRu: 'Французский', nameEn: 'French', flag: '🇫🇷' },
  it: { endonym: 'Italiano', nameRu: 'Итальянский', nameEn: 'Italian', flag: '🇮🇹' },
  pt: { endonym: 'Português', nameRu: 'Португальский', nameEn: 'Portuguese', flag: '🇵🇹' },
  pl: { endonym: 'Polski', nameRu: 'Польский', nameEn: 'Polish', flag: '🇵🇱' },
  tr: { endonym: 'Türkçe', nameRu: 'Турецкий', nameEn: 'Turkish', flag: '🇹🇷' },
  zh: { endonym: '中文', nameRu: 'Китайский', nameEn: 'Chinese', flag: '🇨🇳' },
  ja: { endonym: '日本語', nameRu: 'Японский', nameEn: 'Japanese', flag: '🇯🇵' },
}

export const LANGUAGE_CODES: string[] = Object.keys(LANGUAGES)

/**
 * How a language code is shown in this console: its endonym, falling back to the uppercased code.
 *
 * The fallback is deliberate — `lang` comes from the SERVER, so a language enabled after this panel
 * was last deployed is a code the table has never heard of, and it should read as a visible `SW`
 * rather than as a blank cell or an invented name.
 */
export function langLabel(code: string): string {
  return LANGUAGES[code]?.endonym ?? code.toUpperCase()
}
