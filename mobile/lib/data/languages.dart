/// CEFR levels + TTS locale mapping (app data, not UI copy). The language endonyms (`Language`,
/// [kLanguages], [languageByCode]) live in `lib/l10n/language_endonyms.dart` and are re-exported
/// here so a single import covers language pickers. Moved out of the deleted `lib/core/` at the
/// A3 close.
library;

export 'package:eng_std/l10n/language_endonyms.dart';

const List<String> kCefrLevels = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'];

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
