import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'providers.dart';

/// The locales the app ships. English joined at the A3 close now that
/// `lib/l10n/app_en.arb` is complete (258/258 keys), so «English» in the profile
/// row resolves to English instead of falling back to Russian. Russian stays the
/// source of truth and the fallback.
const List<Locale> kSupportedLocales = [Locale('ru'), Locale('en')];

/// Fallback when the device locale isn't supported (resolution tail).
const Locale kFallbackLocale = Locale('ru');

/// drift `SyncMeta` key holding the UI-language override. Stored locally only —
/// the backend is never told (rule: override is a device preference).
const String kLocaleOverrideKey = 'ui_locale';

/// UI-language override values surfaced by the profile row (кадр 2.10). The row
/// itself lands in A3.7; this is only the mechanism.
enum UiLanguageOption {
  /// Follow the device locale (no override stored).
  system(null),
  russian('ru'),
  english('en');

  const UiLanguageOption(this.code);

  /// Stored code, or null for «Системный».
  final String? code;

  static UiLanguageOption fromCode(String? code) => switch (code) {
    'ru' => UiLanguageOption.russian,
    'en' => UiLanguageOption.english,
    _ => UiLanguageOption.system,
  };
}

/// Resolves and persists the UI-language override.
///
/// Resolution order (used by [MaterialApp] via [override] + a
/// `localeResolutionCallback`): stored override → **ЯЗЫК ПОДДЕРЖКИ ПАРЫ** → device locale →
/// [kFallbackLocale]. The override lives in drift (`SyncMeta`), survives restarts, and is never
/// synced.
///
/// ## Почему язык поддержки стоит выше языка устройства (наряд DAY-2-FIX, Ч.1.2)
///
/// Служебные подписи продукта — «сказано вслух», «Что ты ответишь?», «говорит собеседник · текст
/// скрыт» — это язык, на котором с человеком РАЗГОВАРИВАЕТ приложение, и канон называет его: язык
/// поддержки пары («для ru→en это русский»). Язык телефона отвечает на другой вопрос — на каком
/// языке человек держит систему, — и у владельца он английский. Из-за этого весь плановый контур
/// живьём вышел по-английски: не хардкодом, а честной локализацией, которой задали не тот вопрос.
///
/// Изучаемого контента это не касается никогда: реплики, фразы и слова остаются на изучаемом языке,
/// потому что они приходят с сервера, а не из `.arb`.
class LocaleController extends AsyncNotifier<UiLanguageOption> {
  @override
  Future<UiLanguageOption> build() async {
    final code = await ref.read(appDatabaseProvider).getMeta(kLocaleOverrideKey);
    return UiLanguageOption.fromCode(code);
  }

  /// Persist a new override (or «Системный» → clears it) and update state.
  Future<void> setOption(UiLanguageOption option) async {
    await ref.read(appDatabaseProvider).setMeta(kLocaleOverrideKey, option.code);
    state = AsyncData(option);
  }

  /// The concrete [Locale] to force, or null to let [resolveLocale] answer.
  static Locale? overrideLocale(UiLanguageOption option) =>
      option.code == null ? null : Locale(option.code!);

  /// «Системный» ЗНАЧИТ ЯЗЫК ПОДДЕРЖКИ, а не язык телефона — см. докблок класса.
  ///
  /// [supportLang] — родной язык аккаунта (`profiles.native_language`), он же `support_lang` пары.
  /// Null, пока профиль не приехал: тогда решает устройство, как решало всегда, — экран логина
  /// показывается раньше, чем есть чей-то родной язык.
  static Locale? localeFor(UiLanguageOption option, String? supportLang) {
    final forced = overrideLocale(option);
    if (forced != null) return forced;

    final code = supportLang?.trim().toLowerCase();
    if (code == null || code.isEmpty) return null;

    for (final locale in kSupportedLocales) {
      if (locale.languageCode == code) return locale;
    }

    // Язык поддержки, которого у интерфейса нет (uk, de, pl…): решает устройство, а хвост
    // [resolveLocale] доводит до [kFallbackLocale]. Молча — это не ошибка, а недостающий перевод.
    return null;
  }
}

final localeControllerProvider = AsyncNotifierProvider<LocaleController, UiLanguageOption>(
  LocaleController.new,
);

/// Device → fallback resolution, exposed for [MaterialApp.localeResolutionCallback]
/// and unit tests. The override is applied separately via [MaterialApp.locale].
Locale resolveLocale(Locale? deviceLocale, Iterable<Locale> supported) {
  if (deviceLocale != null) {
    for (final s in supported) {
      if (s.languageCode == deviceLocale.languageCode) return s;
    }
  }
  return kFallbackLocale;
}
