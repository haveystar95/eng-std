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
import 'package:eng_std/features/plan/session/session_controller.dart';

/// ОЧЕРЕДЬ СЕССИИ (наряд SESSION-1b, разд. 1 и 6): копия после провала встаёт в конец этапа; после повторного
/// GET сессия продолжает с первой неотвеченной; отложенный ответ уходит после восстановления сети и держит
/// следующую карточку, пока не ушёл.
Map<String, dynamic> _raw() =>
    jsonDecode(File('../backend2/docs/fixtures/day-doctor.json').readAsStringSync()) as Map<String, dynamic>;

Map<String, dynamic> _cardJson(Map<String, dynamic> raw, String stage, int position) {
  final s = (raw['stages'] as List).cast<Map<String, dynamic>>().firstWhere((x) => x['stage'] == stage);
  return (s['cards'] as List).cast<Map<String, dynamic>>().firstWhere((c) => c['position'] == position);
}

/// Ответ сервера на карточку — как `POST …/answer`.
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

  /// Ответы `GET` по очереди; последний повторяется.
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

  @override
  Future<SessionAnswerOutcome> answer(String planId, int number, String cardId, SessionAnswer answer) {
    answers.add((cardId, answer));
    return onAnswer!(cardId, answer);
  }

  @override
  Future<SessionJudgeOutcome> judge(String planId, int number, String cardId, {required String heard, required bool hinted}) =>
      throw UnimplementedError();
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
    test('очередь этапа — неотвеченные по позиции; копия после провала — в конец этапа', () {
      final raw = _raw();
      final q = SessionQueue(SessionDay.fromJson(raw).stages);
      expect(q.firstOpenStage(), PlanStage.words);
      final first = q.nextIn(PlanStage.words)!;
      expect(first.position, 1);

      // Первый провал выбора: сервер отвечает карточкой и копией с новой позицией 25.
      final choose = _cardJson(raw, 'words', 21);
      final copy = {...choose, 'id': 'ulid-copy', 'position': 25, 'retry_of': choose['id']};
      final out = _outcome(choose, 'failed', requeued: copy);
      q.apply(answered: out.card, requeued: out.requeued);

      final cards = q.cardsOf(PlanStage.words);
      expect(cards, hasLength(25));
      expect(cards.last.id, 'ulid-copy');
      expect(cards.last.retryOf, choose['id']);

      // Всё, кроме копии, отвечено — следующая карточка этапа — копия.
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

    test('счёт шапки — по единицам: «ещё N слов», бусины, полоса по карточкам', () {
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

    test('«вернётся завтра» — единицы, у чьей карточки сервер поставил returns', () {
      final raw = _raw();
      final q = SessionQueue(SessionDay.fromJson(raw).stages);
      final assemble = _cardJson(raw, 'words', 6);
      q.apply(answered: _outcome(assemble, 'failed', returns: true).card);
      expect(q.returningUnits(PlanStage.words), [assemble['unit']['ref']]);
    });
  });

  group('SessionController', () {
    test('после повторного GET — продолжение с первой неотвеченной; нерозданный день раздаётся', () async {
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

    test('вид прохождения с failed — отказ до отправки', () async {
      final session = SessionController(backend: _FakeBackend([_raw()]), plan: _plan(), number: 1);
      await session.load();
      session.startStage();
      expect(
        () => session.submit(session.card!, const SessionAnswer(result: SessionResult.failed, attempts: 1)),
        throwsStateError,
      );
      session.dispose();
    });

    test('«дальше» ждёт ответа сервера; копия после провала — последняя карточка этапа', () {
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

        // Отвечаем на выбор (позиция 21) — сразу, чтобы проверить копию.
        final chooseCard = session.queue!.cardsOf(PlanStage.words).firstWhere((c) => c.id == choose['id']);
        session.submit(chooseCard, const SessionAnswer(result: SessionResult.failed, attempts: 1));
        var advanced = false;
        unawaited(session.next().then((_) => advanced = true));
        async.flushMicrotasks();
        expect(session.advancing, isTrue);
        expect(advanced, isFalse, reason: 'следующая карточка не открывается, пока ответ не ушёл');

        gate.complete();
        async.flushMicrotasks();
        expect(advanced, isTrue);
        expect(session.advancing, isFalse);
        expect(session.queue!.cardsOf(PlanStage.words).last.id, 'ulid-copy');
        session.dispose();
      }, initialTime: DateTime(2026, 9, 16));
    });

    // Живой прогон SESSION-1b: сервер завис, клиент повторил ответ, после возврата сети первый запрос был
    // принят, а повтор получил 409 — итог ответа (копия в конец этапа) до телефона не доехал.
    test('409 «уже отвечена» после обрыва — день перечитывается до следующей карточки, копия приходит с сервера', () async {
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

      expect(backend.gets, 2, reason: 'после 409 день перечитан до следующей карточки');
      final queue = session.queue!.cardsOf(PlanStage.words);
      expect(queue.last.id, 'ulid-copy');
      expect(queue.firstWhere((c) => c.id == choose['id']).result, SessionResult.failed);
      expect(session.card!.position, 1);
      session.dispose();
    });
  });

  group('AnswerOutbox', () {
    test('сеть упала — ответ ждёт, «нет связи»; сеть вернулась — уходит, очередь опустела', () {
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

        // Первый повтор — через секунду; снова мимо.
        async.elapse(const Duration(seconds: 1));
        expect(outbox.offline, isTrue);
        expect(delivered, isNull);

        // Сеть вернулась — не ждать паузы.
        outbox.retryNow();
        async.flushMicrotasks();
        expect(outbox.offline, isFalse);
        expect(delivered, isNotNull);
        expect(drained, isTrue);
        expect(outbox.isEmpty, isTrue);
        outbox.dispose();
      });
    });

    test('409 plan_card_answered — доставлено; 422 — выброшено; ответы уходят по порядку', () async {
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

    test('разбор ошибок: сеть, 5xx и 429 — повторить', () {
      expect(AnswerOutbox.classify(_offline()), OutboxFailure.transient);
      expect(AnswerOutbox.classify(_status(503, 'x')), OutboxFailure.transient);
      expect(AnswerOutbox.classify(_status(429, 'x')), OutboxFailure.transient);
      expect(AnswerOutbox.classify(_status(409, 'plan_card_answered')), OutboxFailure.alreadyAnswered);
      expect(AnswerOutbox.classify(_status(422, 'plan_card_result_not_allowed')), OutboxFailure.permanent);
    });
  });
}
