/// THE table of languages this product knows — one row per language, one copy per repository.
///
/// The three runtimes need the same four facts about a language and used to keep private copies of
/// them: backend2 had eight tables of English names, this file had endonyms and flags, the admin
/// console had a four-entry `langLabel()`. Copies of a table are not a style problem — `ro` was
/// missing from every backend copy while this picker had offered Romanian for months, and it
/// offered it under the name of the COUNTRY (`România`, QA-OBS-16) rather than of the language.
/// Each copy looked complete on its own.
///
/// The other two catalogues are `backend2/app/Modules/Shared/Domain/Service/LanguageCatalog.php`
/// and `wt_admin/src/utils/languages.ts`; the columns and the spellings are the same in all three,
/// which is what makes a future divergence a one-line diff. Membership comes from
/// `docs/research/language-capability-matrix.md`, and `test/l10n/language_catalog_test.dart` holds
/// the list to it.
///
/// Endonyms are not translated by definition, so they are language **data**, not UI copy: they live
/// here beside the ARB files, outside the cyrillic-guard's scan
/// (`test/l10n/no_cyrillic_outside_l10n_test.dart` skips `lib/l10n/`). `lib/data/languages.dart`
/// re-exports this file so one import covers language pickers.
library;

/// A selectable language.
///
/// [code] is the 2-letter code backend2 stores. [endonym] is the language's name in itself, and it
/// is what the USER sees everywhere (rule: a picker that offers "Romanian" to a Romanian speaker is
/// naming their language in someone else's). [nameRu]/[nameEn] name the language in the INTERFACE
/// language — for surfaces written about a language rather than in it; the app has no such surface
/// today and carries the columns so the three catalogues stay diffable. [flag] is the emoji shown
/// in pickers; `MiniFlag` draws the same flags for the codes it has painters for.
class Language {
  final String code;
  final String endonym;
  final String nameRu;
  final String nameEn;
  final String flag;
  const Language(this.code, this.endonym, this.nameRu, this.nameEn, this.flag);
}

/// The order here is the order the pickers list, and [languageByCode] falls back to the first row.
const List<Language> kLanguages = [
  Language('ru', 'Русский', 'Русский', 'Russian', '🇷🇺'),
  Language('en', 'English', 'Английский', 'English', '🇬🇧'),
  Language('uk', 'Українська', 'Украинский', 'Ukrainian', '🇺🇦'),
  Language('ro', 'Română', 'Румынский', 'Romanian', '🇷🇴'),
  Language('es', 'Español', 'Испанский', 'Spanish', '🇪🇸'),
  Language('de', 'Deutsch', 'Немецкий', 'German', '🇩🇪'),
  Language('fr', 'Français', 'Французский', 'French', '🇫🇷'),
  Language('it', 'Italiano', 'Итальянский', 'Italian', '🇮🇹'),
  Language('pt', 'Português', 'Португальский', 'Portuguese', '🇵🇹'),
  Language('pl', 'Polski', 'Польский', 'Polish', '🇵🇱'),
  Language('tr', 'Türkçe', 'Турецкий', 'Turkish', '🇹🇷'),
  Language('zh', '中文', 'Китайский', 'Chinese', '🇨🇳'),
  Language('ja', '日本語', 'Японский', 'Japanese', '🇯🇵'),
];

Language languageByCode(String code) =>
    kLanguages.firstWhere((l) => l.code == code, orElse: () => kLanguages.first);
