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
/// language — for surfaces written about a language rather than in it ([languageNameFor]; the plan's
/// entry is one: «Английский · Средний» is a sentence ABOUT the pair, in the interface's own
/// language). [flag] is the emoji shown in pickers; `MiniFlag` draws the same flags for the codes it
/// has painters for.
class Language {
  final String code;
  final String endonym;
  final String nameRu;
  final String nameEn;
  final String flag;
  const Language(this.code, this.endonym, this.nameRu, this.nameEn, this.flag);

  /// The language NAMED in the interface language [uiLanguage] — see [languageNameFor].
  String nameIn(String uiLanguage) => uiLanguage == 'ru' ? nameRu : nameEn;
}

/// The order here is the order the pickers list, and [languageByCode] falls back to the first row.
const List<Language> kLanguages = [
  Language('ru', 'Русский', 'Русский', 'Russian', '🇷🇺'),
  Language('en', 'English', 'Английский', 'English', '🇬🇧'),
  Language('uk', 'Українська', 'Украинский', 'Ukrainian', '🇺🇦'),
  // Belarusian joined with LANG-1 as a plan NATIVE (the language a plan is read in), not as a taught one.
  Language('be', 'Беларуская', 'Белорусский', 'Belarusian', '🇧🇾'),
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

/// The catalogue row of [code], or null when this build does not know the code.
///
/// [languageByCode] falls back to the first row, which is right for a value the account already
/// holds and wrong for a list the SERVER sends: a new language there would be drawn as «Русский».
Language? findLanguage(String code) {
  final c = code.trim().toLowerCase();
  for (final language in kLanguages) {
    if (language.code == c) return language;
  }

  return null;
}

/// The row to DRAW for a language the server listed — this catalogue's, always, when it knows the
/// code: names and flags have one table per runtime (HYG-1), and a server spelling that differs
/// from it must not win on one screen and lose on the next. The server's [endonym] and [flag] only
/// stand in for a code this build has never heard of; with neither, the code itself is the name and
/// the flag is empty (a picker then draws its monogram or the neutral circle).
Language resolveLanguage(String code, {String? endonym, String? flag}) {
  final known = findLanguage(code);
  if (known != null) return known;
  final c = code.trim().toLowerCase();
  final name = (endonym ?? '').trim().isEmpty ? c : endonym!.trim();

  return Language(c, name, name, name, (flag ?? '').trim());
}

/// «Английский» / «English» — the language NAMED in the interface's language.
///
/// Everywhere the app writes IN a language it uses the endonym (a picker that offers «Romanian» to
/// a Romanian speaker names their language in someone else's). The entry of the plan writes ABOUT
/// the pair — «Английский · Средний» in the tape and under «Маршрут» — and the canvas's text table
/// spells those rows in the interface language (`entry.language.name`, кадр 22-2).
String languageNameFor(String code, String uiLanguage) => languageByCode(code).nameIn(uiLanguage);

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
/// one, and a fourteen-row table of hand-written adjectives is one more place to forget a
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
