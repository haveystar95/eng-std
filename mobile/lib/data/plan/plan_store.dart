import 'dart:convert';

import '../local/app_database.dart';
import 'notify_permission.dart' show NotifyAsk;

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

  static const _kNotifyAsk = 'plan_notify_ask';
  static const _kPushEnabled = 'plan_push_enabled';

  /// The pre-permission for reminders (43-1): shown after day 1, again after day 2 on «Не сейчас», then never.
  Future<NotifyAsk> notifyAsk() async => NotifyAsk.fromKey(await _db.getMeta(_kNotifyAsk));
  Future<void> setNotifyAsk(NotifyAsk ask) => _db.setMeta(_kNotifyAsk, ask.key);

  /// Последний ответ сервера на регистрацию токена: доставляет ли он push сам. Нет ответа — false,
  /// и телефон ставит напоминания локально.
  Future<bool> pushEnabled() async => (await _db.getMeta(_kPushEnabled)) == '1';
  Future<void> setPushEnabled(bool enabled) => _db.setMeta(_kPushEnabled, enabled ? '1' : '0');

  /// «NO HINTS» (canvas 30-1, work order SESSION-1b) — per device and per plan: it is how this person wants
  /// to go through this plan, not a fact of the plan on the server. Affects nothing in 1b; in 1c — the
  /// dialogue mode.
  Future<bool> noHints(String planId) async => (await _db.getMeta('plan_no_hints:$planId')) == '1';
  Future<void> setNoHints(String planId, bool value) => _db.setMeta('plan_no_hints:$planId', value ? '1' : '0');

  /// THE TALK WHOSE SUMMARY THE LEARNER HAS NOT SEEN YET (наряд CLIENT-FIX-4 §4) — the id of the day's talk from the
  /// moment its role said goodbye until «Дальше» on its summary (37-12). A session opened again in between (the cross,
  /// the app killed in the background) shows that summary before «День пройден» instead of losing it: the day's row
  /// says only that the talk is over, not which talk it was. Per device — it is about this person's eyes.
  Future<String?> talkSummaryOwed(String planId, int day) async {
    final id = await _db.getMeta('plan_talk_summary_owed:$planId:$day');
    return id == null || id.isEmpty ? null : id;
  }

  Future<void> setTalkSummaryOwed(String planId, int day, String? conversationId) =>
      _db.setMeta('plan_talk_summary_owed:$planId:$day', conversationId);
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
