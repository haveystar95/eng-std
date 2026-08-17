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
