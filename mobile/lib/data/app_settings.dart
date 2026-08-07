import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'providers.dart';

/// Device-local app preferences (кадры 11a / 13a) — reminders and auto-pronounce. Stored in the
/// drift `sync_meta` KV, never synced (they're device settings). The reminder toggle + time are the
/// preference only; wiring them to real OS notifications (the 2.12 pre-permission flow 13c/13d +
/// a local-notifications plugin) is deferred — noted in the roadmap.
class AppSettings {
  const AppSettings({
    required this.remindersEnabled,
    required this.reminderTime,
    required this.autoPronounce,
  });

  final bool remindersEnabled;

  /// «HH:mm», 24h. Default 20:00.
  final String reminderTime;

  /// Auto-pronounce the target word when a study card appears (default on).
  final bool autoPronounce;

  static const defaults = AppSettings(
    remindersEnabled: false,
    reminderTime: '20:00',
    autoPronounce: true,
  );

  AppSettings copyWith({bool? remindersEnabled, String? reminderTime, bool? autoPronounce}) => AppSettings(
        remindersEnabled: remindersEnabled ?? this.remindersEnabled,
        reminderTime: reminderTime ?? this.reminderTime,
        autoPronounce: autoPronounce ?? this.autoPronounce,
      );
}

abstract final class _Keys {
  static const remindersEnabled = 'reminders_enabled';
  static const reminderTime = 'reminder_time';
  static const autoPronounce = 'autopronounce';
}

class AppSettingsController extends AsyncNotifier<AppSettings> {
  @override
  Future<AppSettings> build() async {
    final db = ref.read(appDatabaseProvider);
    return AppSettings(
      remindersEnabled: (await db.getMeta(_Keys.remindersEnabled)) == '1',
      reminderTime: (await db.getMeta(_Keys.reminderTime)) ?? AppSettings.defaults.reminderTime,
      autoPronounce: (await db.getMeta(_Keys.autoPronounce)) != '0', // default on
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
}

final appSettingsProvider =
    AsyncNotifierProvider<AppSettingsController, AppSettings>(AppSettingsController.new);
