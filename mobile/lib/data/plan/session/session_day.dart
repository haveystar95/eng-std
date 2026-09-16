/// ДЕНЬ ДЛЯ СЕССИИ — тело `GET /plans/{id}/days/{n}` так, как его читает сессия (наряд SESSION-1b):
/// этапы с карточками по `position`, статус дня, сцена и окно (минуты текущего этапа).
///
/// Сервер — источник правды: локального прогресса у сессии нет. Каждый вход в сессию читает день заново,
/// и сессия продолжает с первой неотвеченной карточки.
library;

import 'package:flutter/foundation.dart';

import '../day_window.dart';
import '../plan_models.dart';
import 'session_models.dart';

/// Этап дня с его карточками.
class SessionStageCards {
  const SessionStageCards({
    required this.stage,
    required this.state,
    required this.total,
    required this.done,
    required this.cards,
  });

  final PlanStage stage;
  final PlanStageState state;
  final int total;
  final int done;

  /// По `position`; карточки незнакомых видов сюда не попадают.
  final List<SessionCard> cards;
}

/// Ответ `GET …/days/{n}`, прочитанный сессией.
class SessionDay {
  const SessionDay({
    required this.planId,
    required this.day,
    required this.stages,
    this.scene,
    this.window,
    this.skipped = 0,
  });

  final String planId;
  final PlanDayRoute day;
  final PlanScene? scene;
  final List<SessionStageCards> stages;

  /// Окно дня — отсюда «≈ N мин» текущего этапа. Null, если окно не разобралось: минут тогда нет.
  final DayWindow? window;

  /// Сколько карточек пропущено: вид незнаком этой сборке или payload сломан.
  final int skipped;

  /// Карточки розданы: у нерозданного дня списки пусты (у контура нет id).
  bool get dealt => stages.any((s) => s.cards.isNotEmpty);

  SessionStageCards? stageOf(PlanStage stage) {
    for (final s in stages) {
      if (s.stage == stage) return s;
    }
    return null;
  }

  /// «≈ N мин» — только у текущего этапа окна; у остальных сервер цифру не отдаёт.
  int? minutesLeft(PlanStage stage) {
    for (final s in window?.stages ?? const <WindowStage>[]) {
      if (s.stage == stage) return s.minutesLeft;
    }
    return null;
  }

  factory SessionDay.fromJson(Map<String, dynamic> j) {
    var skipped = 0;
    final stages = <SessionStageCards>[];
    for (final raw in (j['stages'] as List?) ?? const []) {
      if (raw is! Map<String, dynamic>) continue;
      final stage = PlanStage.fromWire(raw['stage'] as String?);
      if (stage == PlanStage.unknown) continue;
      final cards = <SessionCard>[];
      for (final c in (raw['cards'] as List?) ?? const []) {
        if (c is! Map<String, dynamic>) continue;
        try {
          final card = SessionCard.fromJson(c);
          if (card == null) {
            // Вид, которого эта сборка не знает, — пропуск без запроса к серверу.
            skipped++;
            continue;
          }
          cards.add(card);
        } on FormatException catch (e) {
          skipped++;
          debugPrint('[session] card ${c['id']} skipped: $e');
        } on TypeError catch (e) {
          skipped++;
          debugPrint('[session] card ${c['id']} skipped: $e');
        }
      }
      cards.sort((a, b) => a.position.compareTo(b.position));
      stages.add(SessionStageCards(
        stage: stage,
        state: PlanStageState.fromWire(raw['state'] as String?),
        total: (raw['total'] as num?)?.toInt() ?? cards.length,
        done: (raw['done'] as num?)?.toInt() ?? 0,
        cards: cards,
      ));
    }
    DayWindow? window;
    try {
      window = j['window'] == null ? null : DayWindow.fromJson(j['window']);
    } on FormatException catch (e) {
      debugPrint('[session] window unreadable: $e');
    }
    return SessionDay(
      planId: (j['plan_id'] as String?) ?? '',
      day: PlanDayRoute.fromJson(j['day'] as Map<String, dynamic>),
      scene: j['scene'] is Map<String, dynamic> ? PlanScene.fromJson(j['scene'] as Map<String, dynamic>) : null,
      stages: stages,
      window: window,
      skipped: skipped,
    );
  }
}
