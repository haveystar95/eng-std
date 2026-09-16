import 'dart:convert';

import '../local/app_database.dart';

/// WHAT THE TAB KEEPS ON THE DEVICE (наряд PLAN-UI, §6).
///
/// Two kinds of rows in the drift `sync_meta` KV, like every other device-local fact in this app:
///
///   * the LAST KNOWN `GET /plans/current` answer, verbatim — so the tab opens offline on the last
///     state it saw, under a quiet «нет сети» line, instead of on a spinner. The rule of the plan
///     screens still holds: the network is asked on every visit and the cache is only what is drawn
///     while the answer is on its way or never comes;
///   * the ONE-TIME flags — the first-time hints of the tab (кадр 21-2c), the first closing's hint
///     (21-4c) and the «Как устроен план» sheet (21-8). A hint is shown until the first action and
///     then never again; the flag lives on the device because it is about this person's eyes, not
///     about the plan.
class PlanStore {
  PlanStore(this._db);

  final AppDatabase _db;

  static const _kCurrent = 'plan_current';
  static const _kHintsTab = 'plan_hint_tab_shown';
  static const _kHintClose = 'plan_hint_close_shown';
  static const _kSheetHow = 'plan_sheet_how_shown';

  /// The cached plan JSON, or null when nothing was ever cached — or when the last answer was
  /// «плана нет», which is cached as the empty string so the tab can tell «никогда не видел» from
  /// «видел, что плана нет».
  Future<PlanCacheEntry> readCurrent() async {
    final raw = await _db.getMeta(_kCurrent);
    if (raw == null) return const PlanCacheEntry.never();
    if (raw.isEmpty) return const PlanCacheEntry.none();
    try {
      return PlanCacheEntry.plan(jsonDecode(raw) as Map<String, dynamic>);
    } catch (_) {
      return const PlanCacheEntry.never();
    }
  }

  Future<void> writeCurrent(Map<String, dynamic>? raw) =>
      _db.setMeta(_kCurrent, raw == null ? '' : jsonEncode(raw));

  Future<bool> tabHintsShown() async => (await _db.getMeta(_kHintsTab)) == '1';
  Future<void> markTabHintsShown() => _db.setMeta(_kHintsTab, '1');

  Future<bool> closeHintShown() async => (await _db.getMeta(_kHintClose)) == '1';
  Future<void> markCloseHintShown() => _db.setMeta(_kHintClose, '1');

  Future<bool> howSheetShown() async => (await _db.getMeta(_kSheetHow)) == '1';
  Future<void> markHowSheetShown() => _db.setMeta(_kSheetHow, '1');

  static const _kNotifyAsked = 'plan_notify_permission_asked';
  static const _kPushEnabled = 'plan_push_enabled';

  /// Разрешение на уведомления спрошено — второй раз системного окна не будет (наряд PLAN-UI-3 §4).
  Future<bool> notifyPermissionAsked() async => (await _db.getMeta(_kNotifyAsked)) == '1';
  Future<void> markNotifyPermissionAsked() => _db.setMeta(_kNotifyAsked, '1');

  /// Последний ответ сервера на регистрацию токена: доставляет ли он push сам. Нет ответа — false,
  /// и телефон ставит напоминания локально.
  Future<bool> pushEnabled() async => (await _db.getMeta(_kPushEnabled)) == '1';
  Future<void> setPushEnabled(bool enabled) => _db.setMeta(_kPushEnabled, enabled ? '1' : '0');

  /// «БЕЗ ПОДСКАЗОК» (кадр 30-1, наряд SESSION-1b) — на телефоне и на план: это про то, как этот человек
  /// хочет проходить этот план, а не факт плана на сервере. В 1b ни на что не влияет; в 1c — режим диалога.
  Future<bool> noHints(String planId) async => (await _db.getMeta('plan_no_hints:$planId')) == '1';
  Future<void> setNoHints(String planId, bool value) => _db.setMeta('plan_no_hints:$planId', value ? '1' : '0');
}

/// What the cache holds: never written · «плана нет» · a plan's JSON.
class PlanCacheEntry {
  const PlanCacheEntry.never() : raw = null, known = false;
  const PlanCacheEntry.none() : raw = null, known = true;
  const PlanCacheEntry.plan(Map<String, dynamic> this.raw) : known = true;

  final Map<String, dynamic>? raw;

  /// The cache has an answer at all (a plan, or the fact that there is none).
  final bool known;
}
