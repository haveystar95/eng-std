/// Languages offered in settings. `code` is the 2-letter code backend2 stores.
class Language {
  final String code;
  final String name;
  final String flag;
  const Language(this.code, this.name, this.flag);
}

const List<Language> kLanguages = [
  Language('ru', 'Русский', '🇷🇺'),
  Language('en', 'English', '🇬🇧'),
  Language('uk', 'Українська', '🇺🇦'),
  Language('es', 'Español', '🇪🇸'),
  Language('de', 'Deutsch', '🇩🇪'),
  Language('fr', 'Français', '🇫🇷'),
  Language('it', 'Italiano', '🇮🇹'),
  Language('pt', 'Português', '🇵🇹'),
  Language('pl', 'Polski', '🇵🇱'),
  Language('tr', 'Türkçe', '🇹🇷'),
  Language('zh', '中文', '🇨🇳'),
  Language('ja', '日本語', '🇯🇵'),
];

const List<String> kCefrLevels = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'];

Language languageByCode(String code) =>
    kLanguages.firstWhere((l) => l.code == code, orElse: () => kLanguages.first);

/// BCP-47 locales for `flutter_tts`, keyed by our 2-letter language code, so a
/// word is pronounced in the language actually being learned.
const Map<String, String> _ttsLocales = {
  'en': 'en-US',
  'ru': 'ru-RU',
  'uk': 'uk-UA',
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
