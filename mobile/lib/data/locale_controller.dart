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
/// `localeResolutionCallback`): stored override → device locale → [kFallbackLocale].
/// The override lives in drift (`SyncMeta`), survives restarts, and is never synced.
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

  /// The concrete [Locale] to force, or null to follow the device.
  static Locale? overrideLocale(UiLanguageOption option) =>
      option.code == null ? null : Locale(option.code!);
}

final localeControllerProvider =
    AsyncNotifierProvider<LocaleController, UiLanguageOption>(LocaleController.new);

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
