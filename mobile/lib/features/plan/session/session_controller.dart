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

/// Where the session stands.
enum SessionPhase {
  /// Reading the day.
  loading,

  /// The day did not load — «Retry».
  failed,

  /// Stage entry (30-1).
  entry,

  /// A card.
  card,

  /// Stage summary (30-6).
  summary,
}

/// What the session does with the server. Separate from [ApiClient] — a test substitutes its own.
abstract interface class SessionBackend {
  Future<SessionDay> day(String planId, int number);
  Future<void> open(String planId, int number);
  Future<SessionAnswerOutcome> answer(String planId, int number, String cardId, SessionAnswer answer);
  Future<SessionJudgeOutcome> judge(String planId, int number, String cardId, {required String heard, required bool hinted});
}

/// The server via [ApiClient].
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

/// DAY SESSION — the state of one screen (work order SESSION-1b, section 1). Draws nothing.
///
/// The server is the source of truth: every entry reads the day (`GET …/days/{n}`; an undealt day is first
/// dealt by `POST …/open`) and continues from the first unanswered card of the first unfinished stage.
/// An answer goes into the queue of deferred answers ([AnswerOutbox]); the next card does not open until the
/// queue is empty ([next]). The copy after a first failure is placed at the end of the stage by the server's answer.
///
/// Stages without screens in this build (Dialogue, Listen and answer, Speak myself) — entry is blocked, there are
/// no answers for them, the day does not close.
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

  /// Server answers by card id — «comes back tomorrow» on the card after the second failure.
  final Map<String, SessionAnswerOutcome> _outcomes = {};

  /// A stage's minutes from the latest answer in that stage — the stage summary (30-6).
  final Map<PlanStage, int> _stageMinutes = {};

  /// The server answered «card already answered» (409 `plan_card_answered`): the answer arrived earlier — a retry
  /// after a dropped connection whose first request the server did accept — but its outcome (the copy at the end
  /// of the stage) never reached the phone. Before the next card the day is re-read.
  bool _resync = false;

  SessionPhase get phase => _phase;
  Object? get error => _error;
  SessionDay? get day => _day;
  SessionQueue? get queue => _queue;

  /// The stage of the entry, the cards or the summary.
  PlanStage get stage => _stage;
  SessionCard? get card => _card;

  /// Grows with every new card — the key of its widget.
  int get cardSerial => _cardSerial;

  /// «Next» waits until the answer is sent.
  bool get advancing => _advancing;
  bool get offline => outbox.offline;
  bool get noHints => _noHints;
  PlanScene? get scene => _day?.scene;

  SessionAnswerOutcome? outcomeOf(String cardId) => _outcomes[cardId];
  int? minutesOf(PlanStage stage) => _stageMinutes[stage];

  /// The stage has a screen in this build.
  static bool hasScreens(PlanStage stage) => SessionKind.stagesWithScreens.contains(stage);

  /// Read the day and stand at the entry of the first unfinished stage.
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

  /// «No hints» — on the phone, per plan; in 1b it affects nothing.
  Future<void> setNoHints(bool value) async {
    _noHints = value;
    _notify();
    await store?.setNoHints(plan.id, value);
  }

  /// «Start» at the stage entry.
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

  /// A card's answer: marked locally, to the server — via the queue of deferred answers.
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

  /// The slot judge: a synchronous request, the learner waits for the verdict on the card. A network error — an
  /// exception to the caller.
  Future<SessionJudgeOutcome> judge(SessionCard card, String heard) async {
    final outcome = await backend.judge(plan.id, number, card.id, heard: heard, hinted: false);
    if (outcome.accepted && outcome.card != null) _queue?.apply(answered: outcome.card);
    _notify();
    return outcome;
  }

  /// To the next card: first wait until the answer is sent; no cards of the stage left — the stage summary.
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

  /// The queue — afresh from the server (all answers have already been sent: called after `outbox.drained`). The
  /// network did not answer — the previous queue stays.
  Future<void> _reloadQueue() async {
    try {
      final day = await backend.day(plan.id, number);
      if (_disposed) return;
      _setDay(day);
    } catch (e) {
      debugPrint('[session] day reload after 409: $e');
    }
  }

  /// The stage is answered — re-read the day: only the day window knows the next stage's minutes.
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

  /// The stage after the current one — on the stage summary.
  PlanStage? get nextStage => _queue?.stageAfter(_stage);

  /// «Next» on the stage summary — entry to the next stage (stages without screens have their entry blocked).
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
