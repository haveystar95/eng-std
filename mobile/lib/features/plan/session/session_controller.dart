import 'dart:async';

import 'package:flutter/foundation.dart';

import '../../../data/api_client.dart';
import '../../../data/plan/plan_models.dart';
import '../../../data/plan/plan_store.dart';
import '../../../data/plan/session/session_day.dart';
import '../../../data/plan/session/session_models.dart';
import '../../../data/plan/session/session_outbox.dart';
import '../../../data/plan/session/session_outcomes.dart';
import '../../../data/plan/session/session_queue.dart';
import '../../../data/plan/session/session_rules.dart';

/// Где стоит сессия.
enum SessionPhase {
  /// Читаем день.
  loading,

  /// День не загрузился — «Повторить».
  failed,

  /// Вход в этап (30-1).
  entry,

  /// Карточка.
  card,

  /// Итог этапа (30-6).
  summary,
}

/// Что сессия делает с сервером. Отдельно от [ApiClient] — тест подставляет свой.
abstract interface class SessionBackend {
  Future<SessionDay> day(String planId, int number);
  Future<void> open(String planId, int number);
  Future<SessionAnswerOutcome> answer(String planId, int number, String cardId, SessionAnswer answer);
  Future<SessionJudgeOutcome> judge(String planId, int number, String cardId, {required String heard, required bool hinted});
}

/// Сервер через [ApiClient].
class ApiSessionBackend implements SessionBackend {
  const ApiSessionBackend(this.api);

  final ApiClient api;

  @override
  Future<SessionDay> day(String planId, int number) => api.sessionDay(planId, number);

  @override
  Future<void> open(String planId, int number) => api.openPlanDay(planId, number);

  @override
  Future<SessionAnswerOutcome> answer(String planId, int number, String cardId, SessionAnswer answer) =>
      api.answerSessionCard(planId, number, cardId, answer);

  @override
  Future<SessionJudgeOutcome> judge(String planId, int number, String cardId, {required String heard, required bool hinted}) =>
      api.judgeSessionCard(planId, number, cardId, heard: heard, hinted: hinted);
}

/// СЕССИЯ ДНЯ — состояние одного экрана (наряд SESSION-1b, разд. 1). Ничего не рисует.
///
/// Сервер — источник правды: каждый вход читает день (`GET …/days/{n}`; нерозданный день сначала
/// раздаётся `POST …/open`) и продолжает с первой неотвеченной карточки первого незаконченного этапа.
/// Ответ уходит в очередь отложенных ответов ([AnswerOutbox]); следующая карточка не открывается, пока
/// очередь не опустела ([next]). Копия после первого провала встаёт в конец этапа ответом сервера.
///
/// Этапы без экранов в этой сборке (Диалог, Слушаю и отвечаю, Говорю сам) — вход заблокирован, ответов по
/// ним нет, день не закрывается.
class SessionController extends ChangeNotifier {
  SessionController({
    required this.backend,
    required this.plan,
    required this.number,
    this.store,
    Duration Function(int failures)? outboxBackoff,
  }) {
    outbox = AnswerOutbox(
      send: (cardId, answer) => backend.answer(plan.id, number, cardId, answer),
      backoff: outboxBackoff,
    )..addListener(_notify);
  }

  final SessionBackend backend;
  final Plan plan;
  final int number;
  final PlanStore? store;
  late final AnswerOutbox outbox;

  SessionPhase _phase = SessionPhase.loading;
  Object? _error;
  SessionDay? _day;
  SessionQueue? _queue;
  PlanStage _stage = PlanStage.words;
  SessionCard? _card;
  int _cardSerial = 0;
  bool _advancing = false;
  bool _noHints = false;
  bool _disposed = false;

  /// Ответы сервера по id карточки — «вернётся завтра» на карточке после второго провала.
  final Map<String, SessionAnswerOutcome> _outcomes = {};

  /// Минуты этапа из последнего ответа по этапу — итог этапа (30-6).
  final Map<PlanStage, int> _stageMinutes = {};

  /// Сервер ответил «карточка уже отвечена» (409 `plan_card_answered`): ответ дошёл раньше — повтор после
  /// обрыва, чей первый запрос сервер всё-таки принял, — а его итог (копия в конец этапа) до телефона не
  /// доехал. Перед следующей карточкой день перечитывается.
  bool _resync = false;

  SessionPhase get phase => _phase;
  Object? get error => _error;
  SessionDay? get day => _day;
  SessionQueue? get queue => _queue;

  /// Этап входа, карточек или итога.
  PlanStage get stage => _stage;
  SessionCard? get card => _card;

  /// Растёт с каждой новой карточкой — ключ её виджета.
  int get cardSerial => _cardSerial;

  /// «Дальше» ждёт, пока уйдёт ответ.
  bool get advancing => _advancing;
  bool get offline => outbox.offline;
  bool get noHints => _noHints;
  PlanScene? get scene => _day?.scene;

  SessionAnswerOutcome? outcomeOf(String cardId) => _outcomes[cardId];
  int? minutesOf(PlanStage stage) => _stageMinutes[stage];

  /// У этапа есть экран в этой сборке.
  static bool hasScreens(PlanStage stage) => SessionKind.stagesWithScreens.contains(stage);

  /// Прочитать день и встать на вход первого незаконченного этапа.
  Future<void> load() async {
    _phase = SessionPhase.loading;
    _error = null;
    _notify();
    try {
      var day = await backend.day(plan.id, number);
      if (!day.dealt && day.day.status != PlanDayStatus.closed) {
        await backend.open(plan.id, number);
        day = await backend.day(plan.id, number);
      }
      _setDay(day);
      _noHints = await store?.noHints(plan.id) ?? false;
      _stage = _queue!.firstOpenStage() ?? _lastStageWithCards();
      _phase = SessionPhase.entry;
    } catch (e) {
      _error = e;
      _phase = SessionPhase.failed;
    }
    _notify();
  }

  void _setDay(SessionDay day) {
    _day = day;
    _queue = SessionQueue(day.stages);
  }

  PlanStage _lastStageWithCards() {
    for (final s in PlanStage.known.reversed) {
      if (_queue?.hasCards(s) ?? false) return s;
    }
    return PlanStage.speak;
  }

  /// «Без подсказок» — на телефоне, на план; в 1b ни на что не влияет.
  Future<void> setNoHints(bool value) async {
    _noHints = value;
    _notify();
    await store?.setNoHints(plan.id, value);
  }

  /// «Начать» на входе в этап.
  void startStage() {
    if (!hasScreens(_stage)) return;
    final next = _queue?.nextIn(_stage);
    if (next == null) {
      _phase = SessionPhase.summary;
    } else {
      _card = next;
      _cardSerial++;
      _phase = SessionPhase.card;
    }
    _notify();
  }

  /// Ответ карточки: локально отмечен, на сервер — через очередь отложенных ответов.
  void submit(SessionCard card, SessionAnswer answer) {
    if (!SessionRules.mayWrite(card.kind, answer.result)) {
      throw StateError('${card.kind.wire} may not write ${answer.result.wire}');
    }
    _queue?.markAnswered(card, answer.result, answer.attempts);
    outbox.enqueue(card.id, answer, (outcome, failure) {
      if (outcome != null) {
        _queue?.apply(answered: outcome.card, requeued: outcome.requeued);
        _outcomes[card.id] = outcome;
        if (outcome.stage != PlanStage.unknown) _stageMinutes[outcome.stage] = outcome.stageMinutesSpent;
      }
      if (failure == OutboxFailure.alreadyAnswered) _resync = true;
      if (failure == OutboxFailure.permanent) debugPrint('[session] answer ${card.id} refused — the server keeps the card open');
      _notify();
    });
    _notify();
  }

  /// Судья окна: синхронный запрос, ученик ждёт вердикт на карточке. Ошибка сети — исключение вызывающему.
  Future<SessionJudgeOutcome> judge(SessionCard card, String heard) async {
    final outcome = await backend.judge(plan.id, number, card.id, heard: heard, hinted: false);
    if (outcome.accepted && outcome.card != null) _queue?.apply(answered: outcome.card);
    _notify();
    return outcome;
  }

  /// К следующей карточке: сначала дождаться, пока уйдёт ответ; карточек этапа не осталось — итог этапа.
  Future<void> next() async {
    if (_advancing || _phase != SessionPhase.card) return;
    _advancing = true;
    _notify();
    await outbox.drained;
    if (_disposed) return;
    if (_resync) {
      _resync = false;
      await _reloadQueue();
      if (_disposed) return;
    }
    _advancing = false;
    final next = _queue?.nextIn(_stage);
    if (next == null) {
      _card = null;
      _phase = SessionPhase.summary;
      _notify();
      unawaited(_refreshAfterStage());
      return;
    }
    _card = next;
    _cardSerial++;
    _notify();
  }

  /// Очередь — заново с сервера (все ответы уже ушли: вызывается после `outbox.drained`). Сеть не ответила —
  /// остаётся прежняя очередь.
  Future<void> _reloadQueue() async {
    try {
      final day = await backend.day(plan.id, number);
      if (_disposed) return;
      _setDay(day);
    } catch (e) {
      debugPrint('[session] day reload after 409: $e');
    }
  }

  /// Этап отвечен — перечитать день: минуты следующего этапа знает только окно дня.
  Future<void> _refreshAfterStage() async {
    try {
      final day = await backend.day(plan.id, number);
      if (_disposed) return;
      _setDay(day);
      _notify();
    } catch (e) {
      debugPrint('[session] day refresh after stage: $e');
    }
  }

  /// Этап после текущего — на итоге этапа.
  PlanStage? get nextStage => _queue?.stageAfter(_stage);

  /// «Дальше» на итоге этапа — вход в следующий этап (у этапов без экранов вход заблокирован).
  void continueAfterSummary() {
    final next = nextStage;
    if (next == null) return;
    _stage = next;
    _card = null;
    _phase = SessionPhase.entry;
    _notify();
  }

  void _notify() {
    if (!_disposed) notifyListeners();
  }

  @override
  void dispose() {
    _disposed = true;
    outbox.removeListener(_notify);
    outbox.dispose();
    super.dispose();
  }
}
