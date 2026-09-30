import 'dart:async';

import 'package:flutter/foundation.dart';

import '../../../data/api_client.dart';
import '../../../data/plan/conversation/conversation_models.dart' show PlanConversation, TalkTarget;
import '../../../data/plan/day_window.dart';
import '../../../data/plan/plan_models.dart';
import '../../../data/plan/plan_store.dart';
import '../../../data/plan/session/session_day.dart';
import '../../../data/plan/session/session_models.dart';
import '../../../data/plan/session/session_outbox.dart';
import '../../../data/plan/session/session_outcomes.dart';
import '../../../data/plan/session/session_queue.dart';
import '../../../data/plan/session/session_rules.dart';
import '../../../data/plan/session/speech_match.dart';

/// Where the session stands.
enum SessionPhase {
  /// Reading the day.
  loading,

  /// The day did not load — «Retry».
  failed,

  /// The day's lesson is still being written (409 `plan_day_building`, or `plan_lesson_not_ready` with a lesson that
  /// is not failed): the «building the lesson» plate, the plan is polled until the lesson is ready (GEN-3 §11).
  building,

  /// The day's lesson failed to build (`lesson_status: failed`): the plate with «Retry» — the lesson, not the plan.
  lessonFailed,

  /// The day opens with a subscription (409 `plan_day_locked`, `meta.lock_reason: subscription`, ACC-1): the plate
  /// «по подписке» with «Подписка» — no toast, no error (CLIENT-START §6).
  lockedBySubscription,

  /// Stage entry (30-1).
  entry,

  /// A card.
  card,

  /// Stage summary (30-6, 33-8, 34-8, 35-6).
  summary,

  /// The way into the talk (37-5) — the sixth stage's own entry, in place of 30-1.
  talkEntry,

  /// The talk itself (37-6…37-11).
  talk,

  /// The talk's summary (37-12).
  talkSummary,

  /// Day summary (30-7): every card of the day is answered and its talk is over — «Close the day».
  daySummary,
}

/// What the session does with the server. Separate from [ApiClient] — a test substitutes its own.
abstract interface class SessionBackend {
  Future<SessionDay> day(String planId, int number);
  Future<void> open(String planId, int number);
  Future<Plan> plan(String planId);
  Future<Plan> retryLesson(String planId, String sceneId);
  Future<SessionAnswerOutcome> answer(String planId, int number, String cardId, SessionAnswer answer);
  Future<SessionJudgeOutcome> judge(String planId, int number, String cardId, {required String heard, required bool hinted});
  Future<SessionDay> close(String planId, int number);
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
  Future<Plan> plan(String planId) => api.plan(planId);

  @override
  Future<Plan> retryLesson(String planId, String sceneId) => api.retryPlanLesson(planId, sceneId);

  @override
  Future<SessionAnswerOutcome> answer(String planId, int number, String cardId, SessionAnswer answer) =>
      api.answerSessionCard(planId, number, cardId, answer);

  @override
  Future<SessionJudgeOutcome> judge(String planId, int number, String cardId, {required String heard, required bool hinted}) =>
      api.judgeSessionCard(planId, number, cardId, heard: heard, hinted: hinted);

  @override
  Future<SessionDay> close(String planId, int number) => api.closePlanDay(planId, number);
}

/// DAY SESSION — the state of one screen (work orders SESSION-1b, section 1; SESSION-1c). Draws nothing.
///
/// The server is the source of truth: every entry reads the day (`GET …/days/{n}`; an undealt day is first
/// dealt by `POST …/open`) and continues from the first unanswered card of the first unfinished stage; a day with
/// every card answered opens straight on the day summary (30-7). An answer goes into the queue of deferred answers
/// ([AnswerOutbox]); the next card does not open until the queue is empty ([next]). The copy after a first failure
/// is placed at the end of the stage by the server's answer — the listening stage deals none. «Close the day» is
/// the server's `POST …/close` ([closeDay]).
///
/// «ONCE MORE» RESTARTS THE STAGE ([replay], work order SESSION-2a §4): the window's `again` opens the session on the
/// entry of «Speak myself» (the stage the contract names; a day without it — its last stage with cards) with that
/// stage's cards unanswered on the phone. Nothing is sent — no answer, no judge, no close — and the day's progress is
/// untouched; after the stage summary the session stands on the day summary again, drawn from the server's day.
class SessionController extends ChangeNotifier {
  SessionController({
    required this.backend,
    required this.plan,
    required this.number,
    this.store,
    this.replayStage,
    this.readTalk,
    Duration Function(int failures)? outboxBackoff,
    this.lessonPollEvery = const Duration(seconds: 3),
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

  /// «Once more» on a passed day — see the class.
  /// «ЕЩЁ РАЗ» ИМЕНЕМ ЭТАПА (`stages[].again`, наряд FIX-3 §5): какой этап проходится снова. Null — обычная сессия.
  final PlanStage? replayStage;

  /// Идёт повтор этапа: ничего не отправляется, судья не зовётся, день не меняется.
  bool get replay => replayStage != null;

  /// Reads an ended talk back (`GET …/conversation/{cid}`) — the summary a session opened again still owes (наряд
  /// CLIENT-FIX-4 §4). Null — no talk to read, and the owed summary is not shown.
  final Future<PlanConversation> Function(String conversationId)? readTalk;

  /// How often the plan is asked about a lesson that is still being written.
  final Duration lessonPollEvery;
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

  /// Attempts at a free answer during a replay, by card id — the judge counts them on the server, and a replay has
  /// no server to count them.
  final Map<String, int> _replayAttempts = {};

  /// A stage's minutes from the latest answer in that stage — the stage summary (30-6).
  final Map<PlanStage, int> _stageMinutes = {};

  bool _closing = false;
  bool _closeFailed = false;

  /// The server answered «card already answered» (409 `plan_card_answered`): the answer arrived earlier — a retry
  /// after a dropped connection whose first request the server did accept — but its outcome (the copy at the end
  /// of the stage) never reached the phone. Before the next card the day is re-read.
  bool _resync = false;

  /// The plan as last read — the next day's status on the day summary, the day of the «building» plate.
  Plan? _freshPlan;
  Timer? _lessonPoll;

  /// The scene whose lesson failed — «Retry» asks for it again.
  String? _failedScene;
  bool _retrying = false;

  /// The ended talk whose summary the session opens on before the day's own (§4) — read back on [load].
  PlanConversation? _owedTalk;

  SessionPhase get phase => _phase;

  /// THE TALK'S SUMMARY STILL OWED — a session opened again between the talk's goodbye and «Дальше» on its summary
  /// stands on that summary (37-12) before «День пройден» (30-7), with this talk read back from the server. Null — none
  /// is owed, and the talk's summary is the talk screen's own document.
  PlanConversation? get owedTalk => _owedTalk;

  /// The plan as the server last said it — [plan] until the session read it again.
  Plan get currentPlan => _freshPlan ?? plan;

  /// The day after this one, as the latest plan names it — «Day N — building / ready» on 30-7; null — the last day.
  PlanDayRoute? get nextDay {
    for (final d in currentPlan.days) {
      if (d.number == number + 1) return d;
    }
    return null;
  }

  /// «Retry» on a failed lesson is on its way.
  bool get retrying => _retrying;
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

  /// THE SCENE THE STRIP NAMES. A scene day has its own; a review and the rehearsal have none (наряд
  /// CLIENT-CONV-1b), and there the strip names the scene of the day's first card — the rehearsal's recall sheet
  /// starts with the plan's first scene, a review with the first scene it brings back.
  PlanScene? get scene => _day?.scene ?? _sceneById(_firstCardScene);

  /// The scene a card belongs to — the day's own, or on a day without one the card's (`payload.scene_id`).
  PlanScene? sceneOfCard(SessionCard card) => _day?.scene ?? _sceneById(card.payload.sceneId) ?? scene;

  String? get _firstCardScene {
    for (final s in _day?.stages ?? const <SessionStageCards>[]) {
      for (final c in s.cards) {
        if (c.payload.sceneId.isNotEmpty) return c.payload.sceneId;
      }
    }
    return null;
  }

  PlanScene? _sceneById(String? id) => currentPlan.sceneById(id) ?? plan.sceneById(id);

  SessionAnswerOutcome? outcomeOf(String cardId) => _outcomes[cardId];
  int? minutesOf(PlanStage stage) => _stageMinutes[stage];

  /// THE DAY'S STAGES, IN WALKING ORDER, AS THE SERVER DEALT THEM (наряд CLIENT-CONV-1a) — six on a
  /// day with a talk, five on one dealt before it, two on the rehearsal. The list of rows on the
  /// stage entry (30-1), the plate of the day summary (30-7) and what follows what all read this and
  /// nothing else.
  ///
  /// Without the window (it did not parse) the day falls back to the stages that brought cards: a
  /// session that cannot say which stages exist is worse than one that names the ones it can see.
  List<PlanStage> get dayStages {
    final rows = _day?.window?.stages ?? const <WindowStage>[];
    if (rows.isNotEmpty) return [for (final r in rows) r.stage];

    return [for (final s in PlanStage.known) if (_queue?.hasCards(s) ?? false) s];
  }

  /// The day walks the sixth stage. A day without it is walked and closed on five (наряд CONV-1).
  bool get hasTalk => dayStages.contains(PlanStage.conversation);

  /// The talk is over — the server's own row says so, and «разговор окончен» is the only meaning of
  /// «пройден» a stage made of no cards can have.
  bool get talkDone => _talkRow?.state == WindowStageState.done;

  /// «около N минут» on the talk's entry (37-5) — the server's, for the current row only.
  int? get talkMinutes => _talkRow?.minutesLeft;

  /// The talk row's own words for its entry (37-5; CONV-2 п. 12, BACK-TAILS-2): «Поговори с врачом», how many scenes
  /// the talk walks, and the phrases it is for — each null / empty when the server did not send it.
  String? get talkTitle => _talkRow?.talkTitleNative;
  int? get talkScenesCount => _talkRow?.scenesCount;
  List<TalkTarget> get talkTargets => _talkRow?.targets ?? const [];

  /// Where the SERVER says [stage] stands — its row in the day window (`window.stages[].state`); null — the window did
  /// not parse or has no such row.
  WindowStageState? serverStateOf(PlanStage stage) {
    for (final r in _day?.window?.stages ?? const <WindowStage>[]) {
      if (r.stage == stage) return r.state;
    }
    return null;
  }

  /// ИТОГ ЭТАПА ЧИСЛАМИ СЕРВЕРА (`stages[].summary`, наряд FIX-3 §7; кадр 30-6): объём, «с первого раза», возвраты.
  /// Null — ряд без итога (разговор) или день, розданный до наряда: тогда 30-6 печатает только то, что знает сам.
  StageSummary? summaryOf(PlanStage stage) {
    for (final r in _day?.window?.stages ?? const <WindowStage>[]) {
      if (r.stage == stage) return r.summary;
    }
    return null;
  }

  WindowStage? get _talkRow {
    for (final r in _day?.window?.stages ?? const <WindowStage>[]) {
      if (r.stage == PlanStage.conversation) return r;
    }
    return null;
  }

  /// «Что было хорошо» (30-7) — two or three ready lines of the server; empty until the day is
  /// passed, and the block is then not drawn at all.
  List<String> get highlights => _day?.window?.highlights ?? const [];

  /// «Close the day» is on its way to the server.
  bool get closing => _closing;

  /// The last «Close the day» did not get through (no network, a server error) — the button stays.
  bool get closeFailed => _closeFailed;

  /// «День пройден · N минут» (30-7) — the day's `minutes_spent` AS THE SERVER GIVES IT, from the day read after the
  /// last stage and after the talk (наряд CLIENT-CONV-1c §9ж). No second number beside it and no «the larger of two»:
  /// only the server knows which minutes the day counts — the talk's among them (BACK-TAILS-2).
  int get dayMinutes => _day?.day.minutesSpent ?? 0;

  /// Read the day and stand at the entry of the first unfinished stage; every card answered — the day summary. A lesson
  /// still being written — the «building» plate and a poll; a lesson that failed — the plate with «Retry».
  Future<void> load() async {
    _lessonPoll?.cancel();
    _phase = SessionPhase.loading;
    _error = null;
    _notify();
    try {
      var day = await backend.day(plan.id, number);
      if (!replay && !day.dealt && day.day.status != PlanDayStatus.closed) {
        await backend.open(plan.id, number);
        day = await backend.day(plan.id, number);
      }
      if (_disposed) return;
      _setDay(day);
      _noHints = await store?.noHints(plan.id) ?? false;
      if (replay) {
        _startReplay(day);
      } else {
        final open = _queue!.firstOpenStage();
        if (open != null) {
          _stage = open;
          _phase = SessionPhase.entry;
        } else if (hasTalk && !talkDone) {
          // Every card is answered and the day still owes its talk: «день пройден» is six stages
          // through, not five (409 `plan_stage_incomplete`, `meta.stage: conversation`).
          _stage = PlanStage.conversation;
          _phase = SessionPhase.talkEntry;
        } else if (await _readOwedTalk(day) case final owed?) {
          // The talk is over and its summary was never read to the end: it comes first, then the day's (§4).
          _owedTalk = owed;
          _stage = PlanStage.conversation;
          _phase = SessionPhase.talkSummary;
        } else {
          _stage = _lastStageWithCards();
          _phase = day.dealt ? SessionPhase.daySummary : SessionPhase.entry;
        }
      }
    } catch (e) {
      if (_disposed) return;
      _onLoadError(e);
    }
    if (_phase == SessionPhase.daySummary) unawaited(_readPlan());
    _notify();
  }

  void _onLoadError(Object e) {
    final meta = problemMetaOf(e);
    switch (problemCodeOf(e)) {
      case 'plan_lesson_not_ready' when meta['lesson_status'] == 'failed':
        _failedScene = meta['scene_id'] as String?;
        _phase = SessionPhase.lessonFailed;
      case 'plan_day_building' || 'plan_lesson_not_ready':
        _phase = SessionPhase.building;
        _armLessonPoll();
      case 'plan_day_locked' when meta['lock_reason'] == 'subscription':
        _phase = SessionPhase.lockedBySubscription;
      default:
        _error = e;
        _phase = SessionPhase.failed;
    }
  }

  /// Ask the plan whether the lesson is written: ready — read the day; failed — the plate with «Retry»; still being
  /// written, or no answer — ask again later.
  void _armLessonPoll() {
    _lessonPoll?.cancel();
    _lessonPoll = Timer(lessonPollEvery, () => unawaited(_pollLesson()));
  }

  Future<void> _pollLesson() async {
    if (_disposed || _phase != SessionPhase.building) return;
    final day = await _readPlan();
    if (_disposed || _phase != SessionPhase.building) return;
    if (day == null || day.lessonBuilding) {
      _armLessonPoll();
    } else if (day.lessonFailed) {
      _failedScene = day.sceneId;
      _phase = SessionPhase.lessonFailed;
      _notify();
    } else {
      await load();
    }
  }

  /// Read the plan again; returns this day as it now reads, null — no answer.
  Future<PlanDayRoute?> _readPlan() async {
    try {
      final fresh = await backend.plan(plan.id);
      if (_disposed) return null;
      _freshPlan = fresh;
      _notify();
      for (final d in fresh.days) {
        if (d.number == number) return d;
      }
    } catch (e) {
      debugPrint('[session] plan read: $e');
    }
    return null;
  }

  /// «Retry» on a failed lesson: the server writes the lesson again, the plate returns to «building».
  Future<void> retryLesson() async {
    final scene = _failedScene ?? _dayRoute?.sceneId;
    if (_retrying || scene == null) return;
    _retrying = true;
    _notify();
    try {
      _freshPlan = await backend.retryLesson(plan.id, scene);
      if (_disposed) return;
      _retryOffline = false;
      _phase = SessionPhase.building;
      _armLessonPoll();
    } catch (e) {
      debugPrint('[session] lesson retry: $e');
      _retryOffline = isOffline(e);
    }
    _retrying = false;
    _notify();
  }

  /// The last «Retry» could not leave — there really is no network: the plate says so (CLIENT-START §6).
  bool get retryOffline => _retryOffline;
  bool _retryOffline = false;

  PlanDayRoute? get _dayRoute {
    for (final d in currentPlan.days) {
      if (d.number == number) return d;
    }
    return null;
  }

  /// «Ещё раз» — ИМЕННО ТОТ этап, чей ряд нажали (наряд FIX-3 §5); этап без карточек в этом дне — последний, у
  /// которого они есть (так «Ещё раз» никогда не открывает пустой этап).
  void _startReplay(SessionDay day) {
    final named = replayStage;
    final stage = named != null && (_queue?.hasCards(named) ?? false) ? named : _lastStageWithCards();
    _queue = SessionQueue.replaying(day.stages, stage);
    _stage = stage;
    _phase = SessionPhase.entry;
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

  /// «No hints» — on the phone, per plan: the dialogue asks blind, «Speak myself» offers no frame (SESSION-1c).
  Future<void> setNoHints(bool value) async {
    _noHints = value;
    _notify();
    await store?.setNoHints(plan.id, value);
  }

  /// «Start» at the stage entry.
  void startStage() {
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

  /// A card's answer: marked locally, to the server — via the queue of deferred answers. A replay sends nothing.
  void submit(SessionCard card, SessionAnswer answer) {
    if (!SessionRules.mayWrite(card.kind, answer.result)) {
      throw StateError('${card.kind.wire} may not write ${answer.result.wire}');
    }
    _queue?.markAnswered(card, answer.result, answer.attempts);
    if (replay) {
      _notify();
      return;
    }
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

  /// The slot judge: a synchronous request, the learner waits for the verdict on the card. [hinted] — the frame was
  /// on screen before this attempt (`speak_answer`: 5 s of silence or «Hint»); the server then writes `hinted`. A
  /// network error — an exception to the caller.
  Future<SessionJudgeOutcome> judge(SessionCard card, String heard, {bool hinted = false}) async {
    // A replay asks nobody: the card was judged when the day was walked, and the server would refuse it
    // (`plan_card_answered`, `plan_day_not_open`). The PHONE grades it instead — by coverage, exactly as it grades
    // every voice card (FIX-1 §5). Before this, any sound at all passed: the first word heard closed the card.
    if (replay) {
      final attempts = _replayAttempts.update(card.id, (n) => n + 1, ifAbsent: () => 1);
      final accepted = SessionRules.replayAccepted(card.payload, heard, day?.speech ?? SpeechRules.none);
      if (accepted) _queue?.markAnswered(card, SessionResult.passed, attempts);
      _notify();
      return SessionJudgeOutcome(accepted: accepted, result: accepted ? SessionResult.passed : null, attempts: attempts);
    }
    final outcome = await backend.judge(plan.id, number, card.id, heard: heard, hinted: hinted);
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
      if (!replay) unawaited(_refreshAfterStage());
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

  /// THE STAGE AFTER THE CURRENT ONE, IN THE DAY'S OWN ORDER — on the stage summary. A card stage
  /// with no cards is stepped over; the talk is offered while it is not over. A replay has none: it
  /// returns to the day summary.
  PlanStage? get nextStage {
    if (replay) return null;
    final order = dayStages;
    final at = order.indexOf(_stage);
    if (at < 0) return null;
    for (var i = at + 1; i < order.length; i++) {
      final stage = order[i];
      if (stage == PlanStage.conversation) return talkDone ? null : stage;
      if (_queue?.hasCards(stage) ?? false) return stage;
    }
    return null;
  }

  /// «Next» on the stage summary — entry to the next stage; the talk has an entry of its own (37-5);
  /// after the last stage — the day summary (30-7). After a replay — the day summary again, from the
  /// server's day, not the replayed answers.
  void continueAfterSummary() {
    final next = nextStage;
    _card = null;
    if (next == null) {
      final day = _day;
      if (replay && day != null) _setDay(day);
      _phase = SessionPhase.daySummary;
      unawaited(_readPlan());
    } else {
      _stage = next;
      _phase = next == PlanStage.conversation ? SessionPhase.talkEntry : SessionPhase.entry;
    }
    _notify();
  }

  /// The talk has started (37-5 → 37-6).
  void talkStarted() {
    _phase = SessionPhase.talk;
    _notify();
  }

  /// THE ROLE HAS SAID GOODBYE (37-11): from now until «Дальше» on the talk's summary the session owes that summary —
  /// the device remembers which talk it is, so a session opened again in between shows it before «День пройден» (§4).
  Future<void> talkFinished(String conversationId) async {
    if (replay) return;
    await store?.setTalkSummaryOwed(plan.id, number, conversationId);
  }

  /// «Итог» on the end sheet (37-11 → 37-12).
  void talkEnded() {
    _phase = SessionPhase.talkSummary;
    _notify();
  }

  /// «Дальше» on the talk's summary — the day summary (30-7). The day is read again first: its sixth
  /// row is now «пройден», and only the server knows that. The summary has been read: nothing is owed any more.
  Future<void> afterTalk() async {
    _owedTalk = null;
    _phase = SessionPhase.daySummary;
    _notify();
    unawaited(store?.setTalkSummaryOwed(plan.id, number, null));
    unawaited(_readPlan());
    await _refreshAfterStage();
  }

  /// The ended talk the device still owes the summary of — read back, and only when it is this day's, over and
  /// summed up; anything else (no id, no network, a talk still going) — none, and the session goes on as it would.
  Future<PlanConversation?> _readOwedTalk(SessionDay day) async {
    final read = readTalk;
    if (read == null || !day.dealt || day.day.status == PlanDayStatus.closed) return null;
    final id = await store?.talkSummaryOwed(plan.id, number);
    if (id == null) return null;
    try {
      final talk = await read(id);
      return talk.isEnded && talk.summary != null && talk.day == number ? talk : null;
    } catch (e) {
      debugPrint('[session] owed talk $id: $e');
      return null;
    }
  }

  /// «Close the day» (30-7): every deferred answer delivered first, then `POST …/close`. True — the day is closed
  /// (or had been closed already: the server refuses a day that is not being walked with 409 `plan_day_not_open`,
  /// and a day read as closed is not sent at all). A card the server still misses (409 `plan_stage_incomplete`) —
  /// the day is read again and the session stands at that stage's entry. No network — false, [closeFailed].
  Future<bool> closeDay() async {
    if (_closing) return false;
    if (_day?.day.status == PlanDayStatus.closed) return true;
    _closing = true;
    _closeFailed = false;
    _notify();
    await outbox.drained;
    if (_disposed) return false;
    var closed = false;
    try {
      _setDay(await backend.close(plan.id, number));
      closed = true;
    } catch (e) {
      switch (problemCodeOf(e)) {
        case 'plan_day_not_open':
          closed = true;
        // A card the server still misses — or the talk the day has not had (`meta.stage:
        // conversation`): the day is read again and the session stands at that stage's entry.
        case 'plan_stage_incomplete':
          await _reloadQueue();
          final open = _queue?.firstOpenStage();
          if (open != null) {
            _stage = open;
            _phase = SessionPhase.entry;
          } else if (hasTalk && !talkDone) {
            _stage = PlanStage.conversation;
            _phase = SessionPhase.talkEntry;
          }
        default:
          debugPrint('[session] close day: $e');
          _closeFailed = true;
      }
    }
    if (_disposed) return closed;
    _closing = false;
    _notify();
    return closed;
  }

  void _notify() {
    if (!_disposed) notifyListeners();
  }

  @override
  void dispose() {
    _disposed = true;
    _lessonPoll?.cancel();
    outbox.removeListener(_notify);
    outbox.dispose();
    super.dispose();
  }
}
