import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:dio/dio.dart';
import 'package:fake_async/fake_async.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/plan/plan_models.dart';
import 'package:eng_std/data/plan/session/session_day.dart';
import 'package:eng_std/data/plan/session/session_models.dart';
import 'package:eng_std/data/plan/session/session_outbox.dart';
import 'package:eng_std/data/plan/session/session_outcomes.dart';
import 'package:eng_std/data/plan/session/session_queue.dart';
import 'package:eng_std/data/plan/session/session_rules.dart';
import 'package:eng_std/data/plan/session/session_summary.dart';
import 'package:eng_std/features/plan/session/session_controller.dart';

/// THE SESSION QUEUE (work order SESSION-1b §1 and §6): the copy after a failure goes to the end of the stage;
/// after a repeated GET the session resumes at the first unanswered card; a deferred answer is sent once the network
/// is back and holds the next card until it has gone.
Map<String, dynamic> _raw() =>
    jsonDecode(File('../backend2/docs/fixtures/day-doctor.json').readAsStringSync()) as Map<String, dynamic>;

Map<String, dynamic> _cardJson(Map<String, dynamic> raw, String stage, int position) {
  final s = (raw['stages'] as List).cast<Map<String, dynamic>>().firstWhere((x) => x['stage'] == stage);
  return (s['cards'] as List).cast<Map<String, dynamic>>().firstWhere((c) => c['position'] == position);
}

/// The server's response to a card — as `POST …/answer`.
SessionAnswerOutcome _outcome(Map<String, dynamic> card, String result, {Map<String, dynamic>? requeued, bool returns = false, int minutes = 3}) =>
    SessionAnswerOutcome.fromJson({
      'card': {...card, 'result': result, 'attempts': 1, 'returns': returns},
      'requeued': requeued,
      'unit': {'kind': card['unit']['kind'], 'ref': card['unit']['ref'], 'returns_tomorrow': returns, 'returns_day': returns ? 2 : null},
      'day': {'cards_total': 76, 'cards_done': 1, 'minutes_spent': minutes},
      'stage': {'stage': card['stage'], 'minutes_spent': minutes},
    });

DioException _offline() => DioException(requestOptions: RequestOptions(path: '/x'), type: DioExceptionType.connectionError);

DioException _status(int code, String problem) => DioException(
  requestOptions: RequestOptions(path: '/x'),
  type: DioExceptionType.badResponse,
  response: Response(requestOptions: RequestOptions(path: '/x'), statusCode: code, data: {'code': problem}),
);

class _FakeBackend implements SessionBackend {
  _FakeBackend(this.days);

  /// `GET` responses in turn; the last one repeats.
  final List<Map<String, dynamic>> days;
  int gets = 0;
  int opens = 0;
  final List<(String, SessionAnswer)> answers = [];
  Future<SessionAnswerOutcome> Function(String cardId, SessionAnswer answer)? onAnswer;

  @override
  Future<SessionDay> day(String planId, int number) async {
    final json = days[gets < days.length ? gets : days.length - 1];
    gets++;
    return SessionDay.fromJson(json);
  }

  @override
  Future<void> open(String planId, int number) async => opens++;

  /// `GET /plans/{id}` answers in turn; the last one repeats.
  final List<Plan> plans = [];
  int planReads = 0;
  final List<String> retries = [];

  @override
  Future<Plan> plan(String planId) async => plans[planReads < plans.length ? planReads++ : plans.length - 1];

  @override
  Future<Plan> retryLesson(String planId, String sceneId) async {
    retries.add(sceneId);
    return plans.last;
  }

  @override
  Future<SessionAnswerOutcome> answer(String planId, int number, String cardId, SessionAnswer answer) {
    answers.add((cardId, answer));
    return onAnswer!(cardId, answer);
  }

  final List<({String cardId, String heard, bool hinted})> judged = [];
  SessionJudgeOutcome Function(String cardId)? onJudge;

  @override
  Future<SessionJudgeOutcome> judge(String planId, int number, String cardId, {required String heard, required bool hinted}) async {
    judged.add((cardId: cardId, heard: heard, hinted: hinted));
    return onJudge!(cardId);
  }

  int closes = 0;
  Future<SessionDay> Function()? onClose;

  @override
  Future<SessionDay> close(String planId, int number) {
    closes++;
    return onClose!();
  }
}

/// THE SAME DAY WITHOUT A SIXTH STAGE — what the server deals for a day dealt before наряд CONV-1,
/// and for every day while the talk is switched off (`plan.conversation.enabled = false`, the state
/// of прод at the time of this наряд): five rows in the window and no talk among them.
Map<String, dynamic> _withoutTalk(Map<String, dynamic> raw) {
  final window = raw['window'] as Map<String, dynamic>;
  window['stages'] = [
    for (final s in (window['stages'] as List).cast<Map<String, dynamic>>())
      if (s['stage'] != 'conversation') s,
  ];
  raw['stages'] = [
    for (final s in (raw['stages'] as List).cast<Map<String, dynamic>>())
      if (s['stage'] != 'conversation') s,
  ];
  return raw;
}

/// The day's talk is over — its own row says so, and nothing else can.
Map<String, dynamic> _talkDone(Map<String, dynamic> raw) {
  for (final s in ((raw['window'] as Map<String, dynamic>)['stages'] as List).cast<Map<String, dynamic>>()) {
    if (s['stage'] == 'conversation') s['state'] = 'done';
  }
  return raw;
}

/// [raw] with every card answered `passed`.
Map<String, dynamic> _allAnswered(Map<String, dynamic> raw) {
  for (final s in (raw['stages'] as List).cast<Map<String, dynamic>>()) {
    for (final c in (s['cards'] as List).cast<Map<String, dynamic>>()) {
      c['result'] = 'passed';
      c['attempts'] = 1;
    }
  }
  return raw;
}

Plan _plan() => Plan.fromJson({
  'id': 'ulid-0001',
  'status': 'active',
  'goal_text': 'врач',
  'target_lang': 'en',
  'native_lang': 'ru',
  'level': 'intermediate',
  'days_total': 1,
  'days_requested': 1,
  'route_summary': '',
  'days': const <Object>[],
  'scenes': const <Object>[],
  'rescue_kit': const <Object>[],
  'versions': const <String, dynamic>{},
});

void main() {
  group('SessionQueue', () {
    test('the stage queue — unanswered cards by position; the copy after a failure — at the end of the stage', () {
      final raw = _raw();
      final q = SessionQueue(SessionDay.fromJson(raw).stages);
      expect(q.firstOpenStage(), PlanStage.words);
      final first = q.nextIn(PlanStage.words)!;
      expect(first.position, 1);

      // The first failure of a choice: the server answers with the card and a copy at the new position 25.
      final choose = _cardJson(raw, 'words', 21);
      final copy = {...choose, 'id': 'ulid-copy', 'position': 25, 'retry_of': choose['id']};
      final out = _outcome(choose, 'failed', requeued: copy);
      q.apply(answered: out.card, requeued: out.requeued);

      final cards = q.cardsOf(PlanStage.words);
      expect(cards, hasLength(25));
      expect(cards.last.id, 'ulid-copy');
      expect(cards.last.retryOf, choose['id']);

      // Everything but the copy is answered — the stage's next card is the copy.
      for (final c in cards.where((c) => c.id != 'ulid-copy')) {
        q.markAnswered(c, SessionResult.passed, 1);
      }
      expect(q.nextIn(PlanStage.words)!.id, 'ulid-copy');
      expect(q.isDone(PlanStage.words), isFalse);
      q.markAnswered(q.nextIn(PlanStage.words)!, SessionResult.passed, 1);
      expect(q.isDone(PlanStage.words), isTrue);
      expect(q.firstOpenStage(), PlanStage.phrases);
      expect(q.stageAfter(PlanStage.words), PlanStage.phrases);
    });

    test('the header counts units: «N words left», the beads; the bar counts cards', () {
      final q = SessionQueue(SessionDay.fromJson(_raw()).stages);
      expect(q.unitsOf(PlanStage.words), ['v1', 'v2', 'v3', 'v4', 'v5', 'v6', 'v7', 'v8']);
      expect(q.unitsLeft(PlanStage.words), 8);
      expect(q.progress(PlanStage.words), 0);

      for (final c in q.cardsOf(PlanStage.words).where((c) => c.unit.ref == 'v1')) {
        q.markAnswered(c, SessionResult.passed, 1);
      }
      expect(q.unitsLeft(PlanStage.words), 7);
      expect(q.beads(PlanStage.words, currentUnit: 'v2').take(3), [SessionBead.done, SessionBead.current, SessionBead.ahead]);
      expect(q.progress(PlanStage.words), closeTo(3 / 24, 1e-9));
    });

    // CATCHES: «0 lines left» on the last exchange of «Speak myself» once its card is answered but still on screen
    // (live pass, SESSION-1c) — the canvas keeps one count across a card's states.
    test('the conversation stages count the unit on screen as left until its card is left', () {
      final q = SessionQueue(SessionDay.fromJson(_raw()).stages);
      final units = q.unitsOf(PlanStage.speak);
      for (final c in q.cardsOf(PlanStage.speak)) {
        q.markAnswered(c, SessionResult.passed, 1);
      }
      expect(q.unitsLeft(PlanStage.speak), 0);
      expect(q.unitsLeft(PlanStage.speak, openUnit: units.last), 1);
      expect(q.unitsLeft(PlanStage.words, openUnit: null), q.unitsLeft(PlanStage.words), reason: 'words and phrases unchanged');
    });

    test('«comes back tomorrow» — the units whose card the server marked returns', () {
      final raw = _raw();
      final q = SessionQueue(SessionDay.fromJson(raw).stages);
      final assemble = _cardJson(raw, 'words', 6);
      q.apply(answered: _outcome(assemble, 'failed', returns: true).card);
      expect(q.returningUnits(PlanStage.words), [assemble['unit']['ref']]);
    });
  });

  group('SessionController', () {
    test('after a repeated GET — resume at the first unanswered card; an undealt day gets dealt', () async {
      final raw = _raw();
      final words = (raw['stages'] as List).first as Map<String, dynamic>;
      for (final c in (words['cards'] as List).cast<Map<String, dynamic>>().take(3)) {
        c['result'] = 'passed';
        c['attempts'] = 1;
      }
      final backend = _FakeBackend([raw]);
      final session = SessionController(backend: backend, plan: _plan(), number: 1);
      await session.load();
      expect(session.phase, SessionPhase.entry);
      expect(session.stage, PlanStage.words);
      expect(backend.opens, 0);
      session.startStage();
      expect(session.card!.position, 4);
      session.dispose();

      final empty = _raw();
      for (final s in (empty['stages'] as List).cast<Map<String, dynamic>>()) {
        s['cards'] = <Object>[];
      }
      final dealing = _FakeBackend([empty, _raw()]);
      final fresh = SessionController(backend: dealing, plan: _plan(), number: 1);
      await fresh.load();
      expect(dealing.opens, 1);
      expect(dealing.gets, 2);
      fresh.startStage();
      expect(fresh.card!.position, 1);
      fresh.dispose();
    });

    test('a walkthrough kind with failed — refused before sending', () async {
      final session = SessionController(backend: _FakeBackend([_raw()]), plan: _plan(), number: 1);
      await session.load();
      session.startStage();
      expect(
        () => session.submit(session.card!, const SessionAnswer(result: SessionResult.failed, attempts: 1)),
        throwsStateError,
      );
      session.dispose();
    });

    test('«Next» waits for the server\'s response; the copy after a failure — the stage\'s last card', () {
      fakeAsync((async) {
        final raw = _raw();
        final backend = _FakeBackend([raw]);
        final gate = Completer<void>();
        final choose = _cardJson(raw, 'words', 21);
        backend.onAnswer = (cardId, answer) async {
          await gate.future;
          final json = cardId == choose['id'] ? choose : _cardJson(raw, 'words', 1);
          return _outcome(
            json,
            answer.result.wire,
            requeued: answer.result == SessionResult.failed ? {...choose, 'id': 'ulid-copy', 'position': 25, 'retry_of': choose['id']} : null,
          );
        };
        final session = SessionController(backend: backend, plan: _plan(), number: 1);
        unawaited(session.load());
        async.flushMicrotasks();
        session.startStage();

        // Answer the choice (position 21) right away, to check the copy.
        final chooseCard = session.queue!.cardsOf(PlanStage.words).firstWhere((c) => c.id == choose['id']);
        session.submit(chooseCard, const SessionAnswer(result: SessionResult.failed, attempts: 1));
        var advanced = false;
        unawaited(session.next().then((_) => advanced = true));
        async.flushMicrotasks();
        expect(session.advancing, isTrue);
        expect(advanced, isFalse, reason: 'the next card does not open until the answer has gone');

        gate.complete();
        async.flushMicrotasks();
        expect(advanced, isTrue);
        expect(session.advancing, isFalse);
        expect(session.queue!.cardsOf(PlanStage.words).last.id, 'ulid-copy');
        session.dispose();
      }, initialTime: DateTime(2026, 9, 16));
    });

    // Live run of SESSION-1b: the server hung, the client resent the answer, once the network was back the first
    // request was accepted and the resend got a 409 — the answer's outcome (the copy at the end of the stage) never
    // reached the phone.
    test('409 «already answered» after a drop — the day is re-read before the next card, the copy comes from the server', () async {
      final raw = _raw();
      final choose = _cardJson(raw, 'words', 21);
      final after = _raw();
      final words = (after['stages'] as List).cast<Map<String, dynamic>>().firstWhere((s) => s['stage'] == 'words');
      final cards = (words['cards'] as List).cast<Map<String, dynamic>>();
      final answered = cards.firstWhere((c) => c['id'] == choose['id']);
      answered['result'] = 'failed';
      answered['attempts'] = 1;
      cards.add({...choose, 'id': 'ulid-copy', 'position': 25, 'retry_of': choose['id']});
      final backend = _FakeBackend([raw, after])..onAnswer = (id, a) async => throw _status(409, 'plan_card_answered');

      final session = SessionController(backend: backend, plan: _plan(), number: 1);
      await session.load();
      session.startStage();
      final chooseCard = session.queue!.cardsOf(PlanStage.words).firstWhere((c) => c.id == choose['id']);
      session.submit(chooseCard, const SessionAnswer(result: SessionResult.failed, attempts: 1));
      await session.next();

      expect(backend.gets, 2, reason: 'after the 409 the day is re-read before the next card');
      final queue = session.queue!.cardsOf(PlanStage.words);
      expect(queue.last.id, 'ulid-copy');
      expect(queue.firstWhere((c) => c.id == choose['id']).result, SessionResult.failed);
      expect(session.card!.position, 1);
      session.dispose();
    });
  });

  group('SessionController · SESSION-1c', () {
    // CATCHES: a listening question that waits for a copy the server never deals (the stage would never end), and a
    // client that invents one.
    test('listen_question failed — final: no copy, the next card is the next position', () async {
      final raw = _raw();
      for (final s in (raw['stages'] as List).cast<Map<String, dynamic>>()) {
        if (s['stage'] == 'listen') break;
        for (final c in (s['cards'] as List).cast<Map<String, dynamic>>()) {
          c['result'] = 'passed';
          c['attempts'] = 1;
        }
      }
      final backend = _FakeBackend([raw]);
      backend.onAnswer = (cardId, answer) async {
        final card = [
          for (final s in (raw['stages'] as List).cast<Map<String, dynamic>>()) ...(s['cards'] as List).cast<Map<String, dynamic>>(),
        ].firstWhere((c) => c['id'] == cardId);
        return _outcome(card, answer.result.wire);
      };
      final session = SessionController(backend: backend, plan: _plan(), number: 1);
      await session.load();
      expect(session.stage, PlanStage.listen);
      session.startStage();
      expect(session.card!.kind, SessionKind.listenDialogue);
      session.submit(session.card!, const SessionAnswer(result: SessionResult.passed, attempts: 1));
      await session.next();
      final question = session.card!;
      expect(question.kind, SessionKind.listenQuestion);
      session.submit(question, const SessionAnswer(result: SessionResult.failed, attempts: 1));
      await session.next();
      expect(session.card!.position, question.position + 1);
      expect(session.queue!.cardsOf(PlanStage.listen), hasLength(9), reason: 'no copy at the end of the stage');
      session.dispose();
    });

    // CATCHES: «день пройден» on five stages while the day still owes its talk — the server refuses
    // that close with 409 `plan_stage_incomplete`, `meta.stage: conversation` (наряд CONV-1).
    test('every card answered but the talk not had — the talk\'s entry, not the day summary', () async {
      final session = SessionController(backend: _FakeBackend([_allAnswered(_raw())]), plan: _plan(), number: 1);
      await session.load();
      expect(session.hasTalk, isTrue);
      expect(session.phase, SessionPhase.talkEntry);
      expect(session.stage, PlanStage.conversation);
      session.dispose();
    });

    test('every card answered and the talk over — the day summary on entry', () async {
      final session = SessionController(backend: _FakeBackend([_talkDone(_allAnswered(_raw()))]), plan: _plan(), number: 1);
      await session.load();
      expect(session.phase, SessionPhase.daySummary);
      session.continueAfterSummary();
      expect(session.phase, SessionPhase.daySummary);
      session.dispose();
    });

    // THE DAY IS WHAT THE SERVER DEALT. A day without a sixth row is walked and closed on five, and
    // draws no talk anywhere — not greyed out, not «coming soon»: absent.
    test('five stages without «Разговор» close the day', () async {
      final session = SessionController(backend: _FakeBackend([_withoutTalk(_allAnswered(_raw()))]), plan: _plan(), number: 1);
      await session.load();
      expect(session.hasTalk, isFalse);
      expect(session.dayStages, [PlanStage.words, PlanStage.phrases, PlanStage.dialogue, PlanStage.listen, PlanStage.speak]);
      expect(session.phase, SessionPhase.daySummary);
      session.dispose();
    });

    // The last card stage leads into the talk, and the talk into the day summary.
    test('the last card stage\'s summary leads to the talk; «Дальше» of the talk to the day summary', () async {
      final session = SessionController(backend: _FakeBackend([_allAnswered(_raw()), _talkDone(_allAnswered(_raw()))]), plan: _plan(), number: 1);
      await session.load();
      session.talkStarted();
      session.talkEnded();
      await session.afterTalk();
      expect(session.phase, SessionPhase.daySummary);
      expect(session.talkDone, isTrue, reason: 'the day was read again — the sixth row is now «пройден»');
      session.dispose();
    });

    // CATCHES: a close sent before the last answer is delivered, a closed day that stays open on the phone, an already
    // closed day reported as an error, and a network failure that pops the learner out as if the day had closed.
    test('closeDay: the answers first, then POST …/close; 409 not open — closed; 409 incomplete — back to the stage; offline — stays', () async {
      final raw = _talkDone(_allAnswered(_raw()));
      final closed = jsonDecode(jsonEncode(raw)) as Map<String, dynamic>;
      (closed['day'] as Map<String, dynamic>)['status'] = 'closed';
      final backend = _FakeBackend([raw])..onClose = () async => SessionDay.fromJson(closed);
      final session = SessionController(backend: backend, plan: _plan(), number: 1);
      await session.load();
      expect(await session.closeDay(), isTrue);
      expect(backend.closes, 1);
      expect(session.day!.day.status, PlanDayStatus.closed);
      expect(await session.closeDay(), isTrue, reason: 'a closed day is not sent again');
      expect(backend.closes, 1);
      session.dispose();

      final notOpen = _FakeBackend([_talkDone(_allAnswered(_raw()))])..onClose = () async => throw _status(409, 'plan_day_not_open');
      final again = SessionController(backend: notOpen, plan: _plan(), number: 1);
      await again.load();
      expect(await again.closeDay(), isTrue);
      again.dispose();

      final incomplete = _raw();
      final missing = _FakeBackend([_talkDone(_allAnswered(_raw())), incomplete])..onClose = () async => throw _status(409, 'plan_stage_incomplete');
      final back = SessionController(backend: missing, plan: _plan(), number: 1);
      await back.load();
      expect(await back.closeDay(), isFalse);
      expect(back.phase, SessionPhase.entry);
      expect(back.stage, PlanStage.words);
      back.dispose();

      final offline = _FakeBackend([_talkDone(_allAnswered(_raw()))])..onClose = () async => throw _offline();
      final stays = SessionController(backend: offline, plan: _plan(), number: 1);
      await stays.load();
      expect(await stays.closeDay(), isFalse);
      expect(stays.closeFailed, isTrue);
      expect(stays.phase, SessionPhase.daySummary);
      stays.dispose();
    });

    test('the judge gets the card\'s real hinted; the day\'s minutes — the freshest server number', () async {
      final raw = _raw();
      final backend = _FakeBackend([raw])
        ..onJudge = ((id) => const SessionJudgeOutcome(accepted: false, attempts: 1))
        ..onAnswer = ((id, a) async => _outcome(_cardJson(raw, 'words', 1), 'passed', minutes: 7));
      final session = SessionController(backend: backend, plan: _plan(), number: 1);
      await session.load();
      session.startStage();
      final card = session.card!;
      await session.judge(card, 'It hurts in his knee', hinted: true);
      await session.judge(card, 'It hurts in his knee');
      expect([for (final j in backend.judged) j.hinted], [true, false]);
      expect(session.dayMinutes, 0);
      session.submit(card, const SessionAnswer(result: SessionResult.passed, attempts: 1));
      await session.outbox.drained;
      expect(session.dayMinutes, 7);
      session.dispose();
    });
  });

  group('SessionController · SESSION-2a', () {
    Plan planWithDay(Map<String, dynamic> day, {bool catchUp = false}) => Plan.fromJson({
      ...(_plan().raw),
      'catch_up': catchUp,
      'days': [
        {
          'id': 'ulid-day-1',
          'number': 1,
          'type': 'scene',
          'slot': {'code': 'today'},
          'cards_total': 0,
          'cards_done': 0,
          'minutes_spent': 0,
          'scene_id': 'ulid-0003',
          ...day,
        },
      ],
    });

    DioException problem(String code, Map<String, dynamic> meta) => DioException(
      requestOptions: RequestOptions(path: '/x'),
      type: DioExceptionType.badResponse,
      response: Response(requestOptions: RequestOptions(path: '/x'), statusCode: 409, data: {'code': code, 'meta': meta}),
    );

    // CATCHES: «Once more» throwing the owner onto the day summary (17.09), a replay that writes answers, asks the
    // judge or re-reads the day over the replayed stage, and a replay that leaves the replayed answers on the summary.
    test('«Once more» restarts the stage: «Speak myself» unanswered on the phone, nothing sent, then the day summary again', () async {
      final closed = _talkDone(_allAnswered(_raw()));
      (closed['day'] as Map<String, dynamic>)['status'] = 'closed';
      final backend = _FakeBackend([closed]);
      final session = SessionController(backend: backend, plan: _plan(), number: 1, replay: true);
      await session.load();
      expect(session.phase, SessionPhase.entry, reason: 'not the day summary');
      expect(session.stage, PlanStage.speak);
      expect(session.queue!.isDone(PlanStage.words), isTrue, reason: 'the other stages stay as walked');
      final speak = session.queue!.cardsOf(PlanStage.speak);
      expect(speak.where((c) => c.isAnswered), isEmpty);
      expect(speak.where((c) => c.retryOf != null), isEmpty, reason: 'the stage, not its retries');
      expect(backend.opens, 0, reason: 'a passed day is not opened again');

      session.startStage();
      while (session.phase == SessionPhase.card) {
        final card = session.card!;
        if (SessionRules.mayWrite(card.kind, SessionResult.passed)) {
          session.submit(card, const SessionAnswer(result: SessionResult.passed, attempts: 1));
        } else {
          // THE PHONE GRADES A REPLAY (наряд FIX-1 §5): coverage of the frame's own words, not «any sound at all».
          final missed = await session.judge(card, 'привет как дела');
          expect(missed.accepted, isFalse, reason: 'a replay is graded, not waved through');
          final verdict = await session.judge(card, SessionRules.expectedSpeech(card.payload));
          expect(verdict.accepted, isTrue);
          expect(verdict.attempts, 2, reason: 'the attempts of this card are counted on the phone');
        }
        await session.next();
      }
      expect(session.phase, SessionPhase.summary);
      expect(session.nextStage, isNull, reason: 'a replay does not walk on to another stage');
      expect(backend.answers, isEmpty);
      expect(backend.judged, isEmpty);
      expect(backend.gets, 1, reason: 'the replayed stage is not re-read over');

      session.continueAfterSummary();
      expect(session.phase, SessionPhase.daySummary);
      final speakCards = (closed['stages'] as List).cast<Map<String, dynamic>>().firstWhere((x) => x['stage'] == 'speak')['cards'] as List;
      expect(session.queue!.cardsOf(PlanStage.speak), hasLength(speakCards.length));
      expect(session.queue!.isDone(PlanStage.speak), isTrue, reason: 'the summary is the server\'s day');
      expect(await session.closeDay(), isTrue, reason: 'a closed day is not closed again');
      expect(backend.closes, 0);
      session.dispose();
    });

    // CATCHES: 409 plan_day_building shown as «did not load», a plate that never turns into the day, a failed lesson
    // with no way out.
    test('a day being built: the building plate, the plan polled until ready, then the day; failed — «Retry» the lesson', () {
      fakeAsync((async) {
        final backend = _FakeBackend([_raw()]);
        var gets = 0;
        final building = _BuildingBackend(backend, until: () => gets++ < 1, error: problem('plan_day_building', {'day': 1, 'lesson_status': 'building'}))
          ..inner.plans.addAll([
            planWithDay({'status': 'building', 'lesson_status': 'building'}),
            planWithDay({'status': 'open', 'lesson_status': 'ready'}),
          ]);
        final session = SessionController(backend: building, plan: _plan(), number: 1, lessonPollEvery: const Duration(seconds: 3));
        unawaited(session.load());
        async.flushMicrotasks();
        expect(session.phase, SessionPhase.building);
        expect(session.error, isNull, reason: 'not an error on screen');
        async.elapse(const Duration(seconds: 3));
        expect(session.phase, SessionPhase.building, reason: 'the plan still says building');
        async.elapse(const Duration(seconds: 3));
        expect(session.phase, SessionPhase.entry, reason: 'ready — the day is read and walked');
        session.dispose();

        final failing = _FakeBackend([_raw()])..plans.add(planWithDay({'status': 'open', 'lesson_status': 'building'}));
        final failed = _BuildingBackend(
          failing,
          until: () => true,
          error: problem('plan_lesson_not_ready', {'scene_id': 'ulid-0003', 'lesson_status': 'failed'}),
        );
        final retry = SessionController(backend: failed, plan: _plan(), number: 1);
        unawaited(retry.load());
        async.flushMicrotasks();
        expect(retry.phase, SessionPhase.lessonFailed);
        unawaited(retry.retryLesson());
        async.flushMicrotasks();
        expect(failing.retries, ['ulid-0003']);
        expect(retry.phase, SessionPhase.building);
        retry.dispose();
      });
    });

    test('plan_lesson_not_ready with a lesson still building — the same building plate, not an error', () async {
      final backend = _BuildingBackend(
        _FakeBackend([_raw()]),
        until: () => true,
        error: problem('plan_lesson_not_ready', {'scene_id': 'ulid-0003', 'lesson_status': 'building'}),
      );
      final session = SessionController(backend: backend, plan: _plan(), number: 1);
      await session.load();
      expect(session.phase, SessionPhase.building);
      session.dispose();
    });

    // CATCHES: «Day 2 — building» taken from the plan the window was opened with, or said of a lesson nobody asked for.
    test('the next day on the day summary — the status the latest plan states', () {
      PlanDayRoute next(Map<String, dynamic> j) => PlanDayRoute.fromJson({
        'id': 'd2',
        'number': 2,
        'type': 'scene',
        'slot': {'code': 'tomorrow'},
        'cards_total': 0,
        'cards_done': 0,
        'minutes_spent': 0,
        ...j,
      });
      expect(SessionSummaries.nextDayLesson(next({'status': 'building', 'lesson_status': null})), NextDayLesson.building);
      expect(SessionSummaries.nextDayLesson(next({'status': 'locked', 'lesson_status': 'building'})), NextDayLesson.building);
      expect(SessionSummaries.nextDayLesson(next({'status': 'locked', 'lesson_status': 'ready'})), NextDayLesson.ready);
      expect(SessionSummaries.nextDayLesson(next({'status': 'locked', 'lesson_status': 'failed'})), isNull);
      expect(SessionSummaries.nextDayLesson(next({'status': 'locked', 'lesson_status': null})), isNull, reason: 'not asked for yet');
      expect(SessionSummaries.nextDayLesson(null), isNull);
    });

    test('catch_up and a building day are read off the contract', () {
      final plan = planWithDay({'status': 'building', 'lesson_status': null}, catchUp: true);
      expect(plan.catchUp, isTrue);
      expect(plan.days.single.status, PlanDayStatus.building);
      expect(plan.days.single.lessonBuilding, isTrue);
      expect(_plan().catchUp, isFalse);
    });

    test('the day summary reads the plan again — the next day as the server states it now', () async {
      final backend = _FakeBackend([_talkDone(_allAnswered(_raw()))])..plans.add(planWithDay({'status': 'in_progress', 'lesson_status': 'ready'}));
      final session = SessionController(backend: backend, plan: _plan(), number: 1);
      await session.load();
      await pumpEventQueue();
      expect(session.phase, SessionPhase.daySummary);
      expect(backend.planReads, 1);
      expect(session.currentPlan.days, hasLength(1));
      session.dispose();
    });
  });

  group('AnswerOutbox', () {
    test('network down — the answer waits, «no connection»; network back — it goes, the queue is empty', () {
      fakeAsync((async) {
        var fails = 2;
        final raw = _raw();
        final card = _cardJson(raw, 'words', 1);
        final outbox = AnswerOutbox(
          send: (id, a) async {
            if (fails > 0) {
              fails--;
              throw _offline();
            }
            return _outcome(card, 'passed');
          },
        );
        SessionAnswerOutcome? delivered;
        outbox.enqueue(card['id'] as String, const SessionAnswer(result: SessionResult.passed, attempts: 1), (o, _) => delivered = o);
        var drained = false;
        unawaited(outbox.drained.then((_) => drained = true));
        async.flushMicrotasks();
        expect(outbox.offline, isTrue);
        expect(outbox.isEmpty, isFalse);
        expect(drained, isFalse);

        // The first retry — after a second; misses again.
        async.elapse(const Duration(seconds: 1));
        expect(outbox.offline, isTrue);
        expect(delivered, isNull);

        // The network is back — do not wait for the pause.
        outbox.retryNow();
        async.flushMicrotasks();
        expect(outbox.offline, isFalse);
        expect(delivered, isNotNull);
        expect(drained, isTrue);
        expect(outbox.isEmpty, isTrue);
        outbox.dispose();
      });
    });

    test('409 plan_card_answered — delivered; 422 — dropped; answers go in order', () async {
      final order = <String>[];
      final outbox = AnswerOutbox(
        send: (id, a) async {
          order.add(id);
          if (id == 'a') throw _status(409, 'plan_card_answered');
          throw _status(422, 'plan_card_result_not_allowed');
        },
      );
      final failures = <OutboxFailure?>[];
      outbox.enqueue('a', const SessionAnswer(result: SessionResult.passed, attempts: 1), (_, f) => failures.add(f));
      outbox.enqueue('b', const SessionAnswer(result: SessionResult.passed, attempts: 1), (_, f) => failures.add(f));
      await outbox.drained;
      expect(order, ['a', 'b']);
      expect(failures, [OutboxFailure.alreadyAnswered, OutboxFailure.permanent]);
      expect(outbox.offline, isFalse);
      outbox.dispose();
    });

    test('error triage: network, 5xx and 429 — retry', () {
      expect(AnswerOutbox.classify(_offline()), OutboxFailure.transient);
      expect(AnswerOutbox.classify(_status(503, 'x')), OutboxFailure.transient);
      expect(AnswerOutbox.classify(_status(429, 'x')), OutboxFailure.transient);
      expect(AnswerOutbox.classify(_status(409, 'plan_card_answered')), OutboxFailure.alreadyAnswered);
      expect(AnswerOutbox.classify(_status(422, 'plan_card_result_not_allowed')), OutboxFailure.permanent);
    });
  });
}

/// [inner], except that opening the day fails with [error] while [until] says so.
class _BuildingBackend implements SessionBackend {
  _BuildingBackend(this.inner, {required this.until, required this.error});

  final _FakeBackend inner;
  final bool Function() until;
  final Object error;

  @override
  Future<SessionDay> day(String planId, int number) async {
    if (until()) throw error;
    return inner.day(planId, number);
  }

  @override
  Future<void> open(String planId, int number) => inner.open(planId, number);

  @override
  Future<Plan> plan(String planId) => inner.plan(planId);

  @override
  Future<Plan> retryLesson(String planId, String sceneId) => inner.retryLesson(planId, sceneId);

  @override
  Future<SessionAnswerOutcome> answer(String planId, int number, String cardId, SessionAnswer answer) =>
      inner.answer(planId, number, cardId, answer);

  @override
  Future<SessionJudgeOutcome> judge(String planId, int number, String cardId, {required String heard, required bool hinted}) =>
      inner.judge(planId, number, cardId, heard: heard, hinted: hinted);

  @override
  Future<SessionDay> close(String planId, int number) => inner.close(planId, number);
}
