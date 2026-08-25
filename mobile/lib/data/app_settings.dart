import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'providers.dart';

/// Languages whose readers already read Cyrillic, and for whom a Cyrillic reading hint under a
/// foreign word is therefore useful rather than noise. The DEFAULT of the pronunciation-hint
/// setting is read off this, never a stored value — someone who has never opened the setting
/// gets the answer their own alphabet implies.
const Set<String> kCyrillicNativeLanguages = {'ru', 'uk', 'be', 'bg', 'sr', 'mk', 'kk'};

/// The default for «Подсказка произношения» given the learner's own language. Unknown (signed
/// out, profile not restored yet) is not a Cyrillic reader as far as we know, so: off.
bool transliterationDefaultFor(String? nativeLanguage) =>
    nativeLanguage != null && kCyrillicNativeLanguages.contains(nativeLanguage.toLowerCase());

/// Device-local app preferences (кадры 11a / 13a) — reminders and auto-pronounce. Stored in the
/// drift `sync_meta` KV, never synced (they're device settings). The reminder toggle + time are the
/// preference only; wiring them to real OS notifications (the 2.12 pre-permission flow 13c/13d +
/// a local-notifications plugin) is deferred — noted in the roadmap.
class AppSettings {
  const AppSettings({
    required this.remindersEnabled,
    required this.reminderTime,
    required this.autoPronounce,
    this.transliteration,
  });

  final bool remindersEnabled;

  /// «HH:mm», 24h. Default 20:00.
  final String reminderTime;

  /// Auto-pronounce the target word when a study card appears (default on).
  final bool autoPronounce;

  /// «Подсказка произношения» — show the word's reading in the learner's own letters on the word
  /// card and on the translator's card.
  ///
  /// THREE-VALUED on purpose. `null` = the learner has never touched the switch, so the answer is
  /// still the one their own language implies ([transliterationDefaultFor]); `true`/`false` = a
  /// decision, which outlives any later change to that default.
  final bool? transliteration;

  static const defaults = AppSettings(
    remindersEnabled: false,
    reminderTime: '20:00',
    autoPronounce: true,
  );

  AppSettings copyWith({
    bool? remindersEnabled,
    String? reminderTime,
    bool? autoPronounce,
    bool? transliteration,
  }) => AppSettings(
    remindersEnabled: remindersEnabled ?? this.remindersEnabled,
    reminderTime: reminderTime ?? this.reminderTime,
    autoPronounce: autoPronounce ?? this.autoPronounce,
    transliteration: transliteration ?? this.transliteration,
  );
}

abstract final class _Keys {
  static const remindersEnabled = 'reminders_enabled';
  static const reminderTime = 'reminder_time';
  static const autoPronounce = 'autopronounce';
  static const transliteration = 'transliteration';
}

class AppSettingsController extends AsyncNotifier<AppSettings> {
  @override
  Future<AppSettings> build() async {
    final db = ref.read(appDatabaseProvider);
    return AppSettings(
      remindersEnabled: (await db.getMeta(_Keys.remindersEnabled)) == '1',
      reminderTime: (await db.getMeta(_Keys.reminderTime)) ?? AppSettings.defaults.reminderTime,
      autoPronounce: (await db.getMeta(_Keys.autoPronounce)) != '0', // default on
      // Absent key = never decided, which is NOT the same as «off» — see the field's note.
      transliteration: switch (await db.getMeta(_Keys.transliteration)) {
        '1' => true,
        '0' => false,
        _ => null,
      },
    );
  }

  Future<void> setRemindersEnabled(bool on) async {
    await ref.read(appDatabaseProvider).setMeta(_Keys.remindersEnabled, on ? '1' : '0');
    state = AsyncData((state.value ?? AppSettings.defaults).copyWith(remindersEnabled: on));
  }

  Future<void> setReminderTime(String hhmm) async {
    await ref.read(appDatabaseProvider).setMeta(_Keys.reminderTime, hhmm);
    state = AsyncData((state.value ?? AppSettings.defaults).copyWith(reminderTime: hhmm));
  }

  Future<void> setAutoPronounce(bool on) async {
    await ref.read(appDatabaseProvider).setMeta(_Keys.autoPronounce, on ? '1' : '0');
    state = AsyncData((state.value ?? AppSettings.defaults).copyWith(autoPronounce: on));
  }

  /// Touching the switch is a DECISION — it stores `1`/`0` and the language-derived default stops
  /// applying to this device from then on.
  Future<void> setTransliteration(bool on) async {
    await ref.read(appDatabaseProvider).setMeta(_Keys.transliteration, on ? '1' : '0');
    state = AsyncData((state.value ?? AppSettings.defaults).copyWith(transliteration: on));
  }
}

final appSettingsProvider = AsyncNotifierProvider<AppSettingsController, AppSettings>(
  AppSettingsController.new,
);

/// Does this device SHOW the reading hint? The stored decision if there is one, otherwise the
/// default the learner's own language implies.
///
/// One provider, read by every surface that draws the hint, so the card and the translator can
/// never disagree — and so the trainers, which read it nowhere, stay obviously out of it.
final transliterationEnabledProvider = Provider<bool>((ref) {
  final decided = ref.watch(appSettingsProvider).value?.transliteration;
  if (decided != null) return decided;
  return transliterationDefaultFor(
    ref.watch(authControllerProvider).value?.profile?.nativeLanguage,
  );
});
