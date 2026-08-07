/// Language endonyms — each language's name written in *itself* (Русский,
/// English, 中文, …). Endonyms are not translated by definition, so they are
/// language **data**, not UI copy: they live here beside the ARB files, outside
/// the cyrillic-guard's scan (`test/l10n/no_cyrillic_outside_l10n_test.dart`
/// skips `lib/l10n/`). This is the permanent home; `lib/core/languages.dart`
/// only re-exports it during the A-block migration and dies with `lib/core/`.
library;

/// A selectable language. [code] is the 2-letter code backend2 stores; [name]
/// is the endonym; [flag] is the emoji shown in language pickers.
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
  Language('ro', 'România', '🇷🇴'),
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

Language languageByCode(String code) =>
    kLanguages.firstWhere((l) => l.code == code, orElse: () => kLanguages.first);
