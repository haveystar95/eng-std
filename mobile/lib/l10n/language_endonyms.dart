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

/// THE NAME OF A LANGUAGE AS A CARD'S INSTRUCTION NEEDS IT — «выбери итальянский эквивалент».
///
/// The instructions under a session prompt used to say «английский» outright, in a product whose
/// pool mixes pairs by design: an Italian card told the learner to choose the English equivalent
/// while showing them Italian options. The template now carries the language as a parameter, and
/// these two functions produce the word it wants.
///
/// Two of them, because Russian needs two forms of the same fact — an adjective beside a noun
/// («итальянский эквивалент») and an adverb beside a verb («напиши по-итальянски») — and English
/// needs one word in both places. An ARB template cannot choose a morphology, so the CALLER supplies
/// the form each sentence wants and each locale spells it its own way.
///
/// These are language DATA, not UI copy, which is why they live here beside the endonyms rather
/// than in an ARB file: the Russian forms are DERIVED from `nameRu` by a rule, not translated one by
/// one, and a thirteen-row table of hand-written adjectives is a thirteenth place to forget a
/// language. Pinned by `test/l10n/instruction_language_test.dart`.

/// «итальянский» / «Italian» — for «выбери … эквивалент».
///
/// `nameRu` is already the nominative masculine adjective the phrase wants («Итальянский»), so the
/// Russian form is its lowercase. Nothing to derive and nothing to decline: the noun it modifies
/// («эквивалент») is inanimate masculine, whose accusative equals its nominative.
String languageAdjectiveFor(String code, String uiLanguage) {
  final language = languageByCode(code);

  return uiLanguage == 'ru' ? language.nameRu.toLowerCase() : language.nameEn;
}

/// «по-итальянски» / «Italian» — for «напиши …» and «прослушай и напиши …».
///
/// Russian builds the adverb off the same adjective: `по-` + the stem + `-и`. Every row of
/// [kLanguages] ends in `-ский` or `-кий`, so dropping the final `ий` is the whole rule —
/// русский → по-русски, английский → по-английски, немецкий → по-немецки, японский → по-японски.
/// A row that ever ends otherwise falls back to the adjective rather than inventing a word.
///
/// English has no separate form; the preposition lives in the template («write it in {lang}»), which
/// is exactly why the two locales cannot share one placeholder value.
String languageAdverbFor(String code, String uiLanguage) {
  final adjective = languageAdjectiveFor(code, uiLanguage);
  if (uiLanguage != 'ru' || !adjective.endsWith('ий')) return adjective;

  return 'по-${adjective.substring(0, adjective.length - 2)}и';
}
