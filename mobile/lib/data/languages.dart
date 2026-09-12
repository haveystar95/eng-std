/// CEFR levels, locales and scripts — app FACTS about a language code, not UI copy. The endonyms
/// (`Language`, [kLanguages], [languageByCode]) live in `lib/l10n/language_endonyms.dart` and are
/// re-exported here so a single import covers language pickers. Moved out of the deleted
/// `lib/core/` at the A3 close.
///
/// Everything here is keyed by the same two-letter code and derived from as few tables as possible:
/// TTS, STT and keyboard locales are ONE table read three ways, because they answer one question
/// and a hand-kept copy is how the app ends up speaking a word in one locale and listening for it
/// in another.
library;

import 'package:flutter/widgets.dart' show Locale;

import 'package:eng_std/l10n/language_endonyms.dart';

export 'package:eng_std/l10n/language_endonyms.dart';

const List<String> kCefrLevels = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'];

/// РОДНОЙ ЯЗЫК — the one the learner reads translations in (ONB-1).
///
/// It is asked ONCE, at first run, and lives on the account (`profiles.native_language`). Everything
/// else in the product derives from it: a collection's pair is «родной аккаунта × изучаемый», the
/// plan takes its `support_lang` from the same column, and no screen asks for it a second time.
/// A per-collection source language was the old shape and it is gone — two places to answer one
/// question is how a learner ends up with folders in two «родных» and no way to tell which.
///
/// The list is deliberately SHORTER than [kLanguages]: these are the languages the generator has
/// been evaluated in as a support language. English is not among them on purpose — it is a language
/// this product teaches, not one it explains in.
const List<String> kNativeLanguageCodes = ['uk', 'ru', 'de', 'pl', 'fr', 'it', 'es', 'ro'];

/// ИЗУЧАЕМЫЙ ЯЗЫК, as the UI offers it: English and German, and nothing else.
///
/// The catalogue below still knows thirteen languages and the server still accepts them — this is a
/// PRODUCT decision about what is offered, not a capability claim. A profile that already studies
/// something else keeps studying it; see [studyLanguagesFor], which adds whatever the account is
/// actually on so a picker can never fail to show the current value.
const List<String> kStudyLanguageCodes = ['en', 'de'];

/// ЯЗЫКИ, КОТОРЫЕ ПРЕДЛАГАЕТ ВХОД В ПЛАН (кадр 22-2) — английский, испанский, немецкий.
///
/// Отдельный список, а не [kStudyLanguageCodes]: канва серии 22 называет на этом шаге три языка,
/// а продуктовый список профиля пока держит два (`en`, `de`). Расхождение НАМЕРЕННО оставлено
/// видимым и названо в отчёте наряда PLAN-UI-2 — менять продуктовое решение о том, что вообще
/// предлагается в профиле, наряд про экран плана не вправе.
const List<String> kPlanEntryLanguageCodes = ['en', 'es', 'de'];

/// Строки входа для языка, плюс текущий язык аккаунта, если он не из трёх.
List<Language> planEntryLanguagesFor(String? current) {
  final codes = [
    ...kPlanEntryLanguageCodes,
    if (current != null && current.isNotEmpty && !kPlanEntryLanguageCodes.contains(current))
      current,
  ];

  return codes.map(languageByCode).toList(growable: false);
}

/// ПРИВЕТСТВИЕ НА ЯЗЫКЕ — подпись под его названием в карточке выбора (кадр 22-2).
///
/// Это НЕ подпись интерфейса и в ARB ей не место: строка написана на том языке, про который
/// говорит, и переводить её на русский или английский нечем — она и есть образец языка.
const Map<String, String> kLanguageGreetings = {
  'en': 'Hello, how are you?',
  'es': 'Hola, ¿qué tal?',
  'de': "Hallo, wie geht's?",
  'fr': 'Bonjour, ça va ?',
  'it': 'Ciao, come stai?',
  'pl': 'Cześć, jak się masz?',
  'pt': 'Olá, tudo bem?',
  'ro': 'Salut, ce faci?',
  'uk': 'Привіт, як справи?',
  'ru': 'Привет, как дела?',
};

/// Приветствие на языке [code], или null — тогда карточка стоит без подписи.
String? greetingFor(String code) => kLanguageGreetings[code.trim().toLowerCase()];

/// Монограмма языка для кружка 36 — «En», «Es», «De» (кадр 22-2).
String monogramFor(String code) {
  final c = code.trim();
  if (c.isEmpty) return '';
  if (c.length == 1) return c.toUpperCase();

  return c[0].toUpperCase() + c.substring(1, 2).toLowerCase();
}

/// The native-language rows, in the order the picker lists them.
List<Language> get kNativeLanguages =>
    kNativeLanguageCodes.map(languageByCode).toList(growable: false);

/// The study-language rows for a learner whose account is on [current].
///
/// [kStudyLanguageCodes] plus [current] when the account is on something the list no longer offers.
/// A picker that cannot show the value it is editing is a picker that silently changes it.
List<Language> studyLanguagesFor(String? current) {
  final codes = [
    ...kStudyLanguageCodes,
    if (current != null && current.isNotEmpty && !kStudyLanguageCodes.contains(current)) current,
  ];

  return codes.map(languageByCode).toList(growable: false);
}

/// The native language to OFFER on a first run, from the device's own locale.
///
/// A guess, and only a guess: the step is a question with a preselected answer, not a decision made
/// for the learner. Anything the list does not carry falls back to `ru` — the interface's own
/// default language, which is the honest fallback for a product whose UI ships in ru and en.
String defaultNativeLanguageFor(String localeLanguageCode) {
  final code = localeLanguageCode.trim().toLowerCase();

  return kNativeLanguageCodes.contains(code) ? code : 'ru';
}

/// BCP-47 locales for `flutter_tts`, keyed by our 2-letter language code, so a
/// word is pronounced in the language actually being learned.
const Map<String, String> _ttsLocales = {
  'en': 'en-US',
  'ru': 'ru-RU',
  'uk': 'uk-UA',
  'ro': 'ro-RO',
  'es': 'es-ES',
  'de': 'de-DE',
  'fr': 'fr-FR',
  'it': 'it-IT',
  'pt': 'pt-PT',
  'pl': 'pl-PL',
  'tr': 'tr-TR',
  'zh': 'zh-CN',
  'ja': 'ja-JP',
};

String ttsLocaleFor(String code) => _ttsLocales[code] ?? 'en-US';

/// The same mapping in the shape `speech_to_text` wants — underscores, not hyphens.
///
/// Derived from the TTS table rather than written out a second time: the two answer the same
/// question («what locale is this language»), and a hand-kept copy is how the app would end up
/// speaking a word in one locale and listening for it in another.
String sttLocaleFor(String code) => ttsLocaleFor(code).replaceAll('-', '_');

/// The locale a KEYBOARD should open in for text written in [code] — the third consumer of the one
/// table above, for the same reason the second one derives from it.
///
/// Handed to a `TextField` as `hintLocales`. Worth knowing exactly what that buys: Flutter
/// documents `hintLocales` as **honoured on Android only** (it becomes `EditorInfo.hintLocales`),
/// and this app ships on iOS, where the keyboard language is the person's own setting and no app
/// may change it. So the hint is the correct thing to state and, on this platform, states it to
/// nobody. What actually protects the learner here is [looksLikeWrongKeyboard] below — which is why
/// the two landed together rather than one standing in for the other.
Locale keyboardLocaleFor(String code) {
  final parts = ttsLocaleFor(code).split('-');

  return Locale(parts.first, parts.length > 1 ? parts[1] : null);
}

/// The SCRIPT each language is written in, as a character class. Mirrors the server's
/// `LanguagePurity::SCRIPTS` — one product, one answer to «какими буквами это пишется». The
/// spelling differs (`\p{Script=Latin}` here, `\p{Latin}` in PCRE) because Dart's engine wants the
/// property named; the LANGUAGES and the SCRIPTS they map to are the same list.
///
/// A language with no entry gets an honest «no opinion», and every caller here reads that as a
/// PASS. Guessing would be worse than silence: this table exists to catch one specific, known
/// failure, and a check that fires on a language nobody taught it about is a check that gets
/// switched off.
const Map<String, String> _scripts = {
  'ru': r'\p{Script=Cyrillic}', 'uk': r'\p{Script=Cyrillic}',
  'en': r'\p{Script=Latin}', 'ro': r'\p{Script=Latin}', 'es': r'\p{Script=Latin}',
  'de': r'\p{Script=Latin}', 'fr': r'\p{Script=Latin}', 'it': r'\p{Script=Latin}',
  'pt': r'\p{Script=Latin}', 'pl': r'\p{Script=Latin}', 'tr': r'\p{Script=Latin}',
  'zh': r'\p{Script=Han}',
  'ja': r'\p{Script=Han}\p{Script=Hiragana}\p{Script=Katakana}',
};

/// Is [text] written ENTIRELY in an alphabet [lang] is not written in — «ФЕЬ» typed at an English
/// card, which is a Russian keyboard left on, not an answer.
///
/// DELIBERATELY ALL-OR-NOTHING, and that threshold is the whole design. A wrong answer and a wrong
/// keyboard are different things, and the only way to tell them apart without guessing is that a
/// wrong keyboard produces text with NO letter of the target alphabet in it. One Latin letter among
/// the Cyrillic means somebody was typing in the right alphabet and got the word wrong, and that is
/// a mistake the trainer exists to record. The server's `LanguagePurity` asks the softer question
/// («most of them») because it is judging CONTENT, where a foreign fragment is legitimate; here the
/// text is one short answer and there is nothing legitimate to protect.
///
/// Letters only: digits, spaces, hyphens and apostrophes belong to no alphabet and cannot vote.
/// No letters at all — an empty answer, «...» — is not a wrong keyboard either.
bool looksLikeWrongKeyboard(String lang, String text) {
  final script = _scripts[lang.trim().toLowerCase()];
  if (script == null) return false;

  final letters = RegExp(r'\p{L}', unicode: true).allMatches(text);
  if (letters.isEmpty) return false;

  final expected = RegExp('[$script]', unicode: true);

  return letters.every((m) => !expected.hasMatch(m[0]!));
}
