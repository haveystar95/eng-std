import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'package:eng_std/theme/feedback.dart';

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

/// Device-local app preferences — stored in the drift `sync_meta` KV, never synced (they're device settings).
///
/// The reminders (work order CLIENT-START §§4–5, frames 42-1, 42-4, 43-1) are the INPUTS of the local schedule
/// (`PlanReminderScheduler`): off — the phone schedules nothing; a time — the day's reminder comes then instead of the
/// server's `reminder_hour`.
class AppSettings {
  const AppSettings({
    this.reminders,
    this.reminderTime,
    required this.autoPronounce,
    this.transliteration,
    this.soundsEnabled = true,
    this.sessionSoundsEnabled = true,
  });

  /// «Напоминать о дне». Null — never decided on this phone: the reminders follow the system's permission (on when
  /// iOS allows notifications) — that is how every build before (22) behaved, and an update must not silence them.
  final bool? reminders;

  /// «Время» — «HH:mm», 24 h. Null — the server's hour (`reminder_hour`: the usual visit, 19:00 without visits).
  final String? reminderTime;

  /// Auto-pronounce the target word when a study card appears (default on).
  final bool autoPronounce;

  /// «Подсказка произношения» — show the word's reading in the learner's own letters on the word
  /// card and on the translator's card.
  ///
  /// THREE-VALUED on purpose. `null` = the learner has never touched the switch, so the answer is
  /// still the one their own language implies ([transliterationDefaultFor]); `true`/`false` = a
  /// decision, which outlives any later change to that default.
  final bool? transliteration;

  /// «Звуки» (токен-лист 4к-3) — the four sounds of the trainers and the plan: верно · неверно ·
  /// этап закрыт · день закрыт. On by default; the phone's silent switch still wins (правило 4е —
  /// хаптика остаётся).
  final bool soundsEnabled;

  /// «Sounds in the session» (polish pass SESSION-1b′, item 5) — the owner's six sounds of the day session
  /// ([SessionSounds]). On by default, stored on the device; off — the session registers none; the phone's silent
  /// switch still wins.
  final bool sessionSoundsEnabled;

  static const defaults = AppSettings(autoPronounce: true);

  /// The switch as the profile shows it and the schedule obeys it: the decision, or — never decided — the system's.
  bool remindersOn({required bool systemAllows}) => reminders ?? systemAllows;

  AppSettings copyWith({
    bool? reminders,
    String? reminderTime,
    bool? autoPronounce,
    bool? transliteration,
    bool? soundsEnabled,
    bool? sessionSoundsEnabled,
  }) => AppSettings(
    reminders: reminders ?? this.reminders,
    reminderTime: reminderTime ?? this.reminderTime,
    autoPronounce: autoPronounce ?? this.autoPronounce,
    transliteration: transliteration ?? this.transliteration,
    soundsEnabled: soundsEnabled ?? this.soundsEnabled,
    sessionSoundsEnabled: sessionSoundsEnabled ?? this.sessionSoundsEnabled,
  );
}

/// «HH:mm» → hour and minute; null for anything else.
({int hour, int minute})? parseReminderTime(String? hhmm) {
  final m = RegExp(r'^(\d{1,2}):(\d{2})$').firstMatch(hhmm ?? '');
  if (m == null) return null;
  final hour = int.parse(m.group(1)!);
  final minute = int.parse(m.group(2)!);
  if (hour > 23 || minute > 59) return null;
  return (hour: hour, minute: minute);
}

abstract final class _Keys {
  static const remindersEnabled = 'reminders_enabled';
  static const reminderTime = 'reminder_time';
  static const autoPronounce = 'autopronounce';
  static const transliteration = 'transliteration';
  static const sounds = 'sounds_enabled';
  static const sessionSounds = 'session_sounds_enabled';
}

class AppSettingsController extends AsyncNotifier<AppSettings> {
  @override
  Future<AppSettings> build() async {
    final db = ref.read(appDatabaseProvider);
    final settings = AppSettings(
      reminders: switch (await db.getMeta(_Keys.remindersEnabled)) {
        '1' => true,
        '0' => false,
        _ => null,
      },
      reminderTime: parseReminderTime(await db.getMeta(_Keys.reminderTime)) == null ? null : await db.getMeta(_Keys.reminderTime),
      autoPronounce: (await db.getMeta(_Keys.autoPronounce)) != '0', // default on
      // Absent key = never decided, which is NOT the same as «off» — see the field's note.
      transliteration: switch (await db.getMeta(_Keys.transliteration)) {
        '1' => true,
        '0' => false,
        _ => null,
      },
      soundsEnabled: (await db.getMeta(_Keys.sounds)) != '0', // default on
      sessionSoundsEnabled: (await db.getMeta(_Keys.sessionSounds)) != '0', // default on
    );
    AppFeedback.soundsEnabled = settings.soundsEnabled;
    SessionSounds.enabled = settings.sessionSoundsEnabled;

    return settings;
  }

  Future<void> setSessionSoundsEnabled(bool on) async {
    SessionSounds.enabled = on;
    await ref.read(appDatabaseProvider).setMeta(_Keys.sessionSounds, on ? '1' : '0');
    state = AsyncData((state.value ?? AppSettings.defaults).copyWith(sessionSoundsEnabled: on));
  }

  /// «Напоминать о дне» — a decision from now on (the profile's switch, 42-4, the answer to 43-1).
  Future<void> setReminders(bool on) async {
    await ref.read(appDatabaseProvider).setMeta(_Keys.remindersEnabled, on ? '1' : '0');
    state = AsyncData((state.value ?? AppSettings.defaults).copyWith(reminders: on));
  }

  Future<void> setReminderTime(String hhmm) async {
    await ref.read(appDatabaseProvider).setMeta(_Keys.reminderTime, hhmm);
    state = AsyncData((state.value ?? AppSettings.defaults).copyWith(reminderTime: hhmm));
  }

}

final appSettingsProvider = AsyncNotifierProvider<AppSettingsController, AppSettings>(
  AppSettingsController.new,
);

/// ВЫКЛЮЧАТЕЛЬ ЗВУКОВ — один сервис [AppFeedback] читает его отсюда. Провайдер существует, чтобы
/// его смотрел корень приложения: настройка выставляется в момент загрузки и при каждом тапе по
/// тумблеру, и ни один экран не решает сам, звучать ему или нет.
final soundsEnabledProvider = Provider<bool>((ref) {
  final settings = ref.watch(appSettingsProvider).value;
  final on = settings?.soundsEnabled ?? true;
  AppFeedback.soundsEnabled = on;
  SessionSounds.enabled = settings?.sessionSoundsEnabled ?? true;
  return on;
});

/// Does this device SHOW the reading hint? The stored decision if there is one, otherwise the
/// default the learner's own language implies.
///
/// One provider, read by every surface that draws the hint, so the card and the translator can
/// never disagree.
///
/// Of the trainers, exactly ONE reads it: the intro card, which shows the word instead of asking
/// for it. Everything that asks stays out — a reading beside a word the learner is being asked to
/// produce is the answer printed out, and no setting should be able to turn that on.
final transliterationEnabledProvider = Provider<bool>((ref) {
  final decided = ref.watch(appSettingsProvider).value?.transliteration;
  if (decided != null) return decided;
  return transliterationDefaultFor(
    ref.watch(authControllerProvider).value?.profile?.nativeLanguage,
  );
});
