import 'dart:convert';
import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/api_client.dart';
import 'package:eng_std/data/plan/day_session.dart';
import 'package:eng_std/data/plan/plan_models.dart';
import 'package:eng_std/data/plan/day_contract.dart';
import 'package:eng_std/data/token_store.dart';

/// КАНОН МАШИНЫ СОСТОЯНИЙ ДНЯ — карточка и этап (наряд DAY-UI, §3–5): порядок от сервера, повтор
/// в конце этапа, «вернётся», закрытие этапа и дня.
void main() {
  group('этапы и порядок', () {
    test('состав дня — только этапы, у которых есть карточки, в каноническом порядке', () {
      final s = DaySession(api: _Api(), planId: 'p', number: 1, returnDay: 2, cards: [
        _card('s1', PlanStage.speak, DayCardKind.speak, 0),
        _card('w1', PlanStage.words, DayCardKind.wordIntro, 0),
      ]);
      expect(s.stages, [PlanStage.words, PlanStage.speak]);
      expect(s.stage, PlanStage.words);
      expect(s.phase, DayPhase.stageEntry);
    });

    test('«Начать» открывает первую карточку этапа по position, а не по порядку в списке', () {
      final s = DaySession(api: _Api(), planId: 'p', number: 1, returnDay: 2, cards: [
        _card('w2', PlanStage.words, DayCardKind.wordSay, 1),
        _card('w1', PlanStage.words, DayCardKind.wordIntro, 0),
      ])..startStage();
      expect(s.phase, DayPhase.card);
      expect(s.current?.id, 'w1');
    });

    test('возврат после паузы — первый этап с неотвеченными карточками, «продолжаем»', () {
      final s = DaySession(api: _Api(), planId: 'p', number: 1, returnDay: 2, cards: [
        _card('w1', PlanStage.words, DayCardKind.wordIntro, 0, result: DayCardResult.passed),
        _card('l1', PlanStage.listen, DayCardKind.listenQuestion, 0, result: DayCardResult.passed),
        _card('l2', PlanStage.listen, DayCardKind.answerChoose, 1),
      ]);
      expect(s.stage, PlanStage.listen);
      expect(s.resumingIn(PlanStage.listen), isTrue);
      expect(s.remainingIn(PlanStage.listen), 1);
    });
  });

  group('ответ и повтор', () {
    test('первая ошибка — сервер возвращает requeued, карточка встаёт в конец этапа', () async {
      final api = _Api();
      final s = DaySession(api: api, planId: 'p', number: 1, returnDay: 2, cards: [
        _card('w1', PlanStage.words, DayCardKind.wordChoose, 0),
        _card('w2', PlanStage.words, DayCardKind.wordChoose, 1),
      ])..startStage();
      await s.answer(s.current!, DayCardResult.failed, attempts: 1);
      expect(api.answers, [('w1', DayCardResult.failed, 1)]);
      s.next();
      expect(s.current?.id, 'w2');
      await s.answer(s.current!, DayCardResult.passed, attempts: 1);
      s.next();
      // Повтор — в конце этапа, с retry_of.
      expect(s.current?.id, 'w1-retry');
      expect(s.current?.retryOf, 'w1');
      expect(s.totalIn(PlanStage.words), 3);
    });

    test('вторая ошибка — failed, «вернётся в день N», этап идёт дальше', () async {
      final api = _Api();
      final s = DaySession(api: api, planId: 'p', number: 1, returnDay: 2, cards: [
        _card('w1', PlanStage.words, DayCardKind.wordChoose, 0),
      ])..startStage();
      await s.answer(s.current!, DayCardResult.failed, attempts: 1);
      s.next();
      expect(s.current?.retryOf, 'w1');
      await s.answer(s.current!, DayCardResult.failed, attempts: 1);
      expect(s.cards.where((c) => c.id == 'w1-retry').single.returns, isTrue);
      expect(s.returningIn(PlanStage.words), 1);
      s.next();
      expect(s.phase, DayPhase.stageDone);
      expect(s.tallyOf(PlanStage.words), (passed: 0, hinted: 0, failed: 2));
    });

    test('уже отвеченная карточка (409 plan_card_answered) — не ошибка, день идёт', () async {
      final api = _Api(conflict: true);
      final s = DaySession(api: api, planId: 'p', number: 1, returnDay: 2, cards: [
        _card('w1', PlanStage.words, DayCardKind.wordIntro, 0),
      ])..startStage();
      await s.acknowledge(s.current!);
      expect(s.error, isNull);
      expect(s.cards.single.isAnswered, isTrue);
    });

    test('подсказка-текст — hinted, считается отдельно от «вернётся»', () async {
      final s = DaySession(api: _Api(), planId: 'p', number: 1, returnDay: 2, cards: [
        _card('s1', PlanStage.speak, DayCardKind.speak, 0),
      ])..startStage();
      await s.answer(s.current!, DayCardResult.hinted, attempts: 1, spokenText: 'my back hurts');
      expect(s.hintedIn(PlanStage.speak), 1);
      expect(s.returningIn(PlanStage.speak), 0);
      expect(s.spoken['s1'], 'my back hurts');
    });
  });

  group('закрытие', () {
    test('после последней карточки — итог этапа; «Дальше» закрывает этап и открывает следующий', () async {
      final api = _Api();
      final s = DaySession(api: api, planId: 'p', number: 1, returnDay: 2, cards: [
        _card('w1', PlanStage.words, DayCardKind.wordIntro, 0),
        _card('p1', PlanStage.phrases, DayCardKind.phraseIntro, 0),
      ])..startStage();
      await s.acknowledge(s.current!);
      s.next();
      expect(s.phase, DayPhase.stageDone);
      expect(s.nextStage, PlanStage.phrases);
      await s.closeStage();
      expect(api.closedStages, [PlanStage.words]);
      expect(s.stage, PlanStage.phrases);
      expect(s.phase, DayPhase.stageEntry);
    });

    test('закрытие последнего этапа — день закрыт', () async {
      final api = _Api();
      final s = DaySession(api: api, planId: 'p', number: 1, returnDay: 2, cards: [
        _card('s1', PlanStage.speak, DayCardKind.speak, 0),
      ])..startStage();
      await s.answer(s.current!, DayCardResult.passed, attempts: 1);
      s.next();
      await s.closeStage();
      expect(s.phase, DayPhase.dayDone);
      expect(s.nextStage, isNull);
    });
  });

  // «ЕЩЁ РАЗ» У ПРОЙДЕННОГО ДНЯ (наряд DAY-UI-2 §1): повторный «Говорю сам», не пересдача дня.
  // Карточки — живой ответ `GET …/cards` пройденного дня (`test/fixtures/plan/cards_window_passed.json`).
  group('«Ещё раз»', () {
    List<DayCard> passedDay() {
      final json = jsonDecode(File('test/fixtures/plan/cards_window_passed.json').readAsStringSync()) as Map<String, dynamic>;

      return [for (final c in (json['cards'] as List).cast<Map<String, dynamic>>()) ?DayCard.fromJson(c)];
    }

    // ЛОВИТ: «Ещё раз», открывающий весь день заново, или «Говорю сам» с ответами и повторами
    // прошлого прохода — человек увидел бы уже закрытые карточки.
    test('только «Говорю сам», по разу на обмен, ни одной отвеченной', () {
      final cards = passedDay();
      final again = DaySession.rehearsalOf(cards);

      expect(again, isNotEmpty);
      expect(again.map((c) => c.stage).toSet(), {PlanStage.speak});
      expect(again.where((c) => c.retryOf != null || c.isAnswered), isEmpty);
      expect(again.length, cards.where((c) => c.stage == PlanStage.speak && c.retryOf == null).length);
    });

    // ЛОВИТ: пересдачу — ответы повтора ушли бы на сервер поверх пройденного дня, этап и день
    // закрылись бы второй раз.
    test('ответы не уходят на сервер, этап и день не закрываются', () async {
      final api = _Api();
      final s = DaySession(api: api, planId: 'p', number: 1, returnDay: 2, cards: DaySession.rehearsalOf(passedDay()), rehearsal: true)
        ..startStage();
      while (s.phase == DayPhase.card) {
        await s.answer(s.current!, DayCardResult.failed, attempts: 2);
        s.next();
      }
      await s.closeStage();
      await s.closeDay();

      expect(s.phase, DayPhase.dayDone);
      expect(api.answers, isEmpty);
      expect(api.closedStages, isEmpty);
      expect(api.closedDays, 0);
    });
  });
}

DayCard _card(String id, PlanStage stage, DayCardKind kind, int position, {DayCardResult? result, String? retryOf, bool returns = false}) => DayCard(
  id: id,
  stage: stage,
  position: position,
  kind: kind,
  source: DayCardSource.today,
  unitKind: switch (stage) {
    PlanStage.words => PlanUnitKind.word,
    PlanStage.phrases => PlanUnitKind.phrase,
    _ => PlanUnitKind.exchange,
  },
  unitRef: id.split('-').first,
  payload: const {},
  result: result,
  attempts: result == null ? 0 : 1,
  retryOf: retryOf,
  returns: returns,
);

/// Сервер дня: первый `failed` — повтор в конец этапа; `failed` у повтора — «вернётся».
class _Api extends ApiClient {
  _Api({this.conflict = false}) : super(TokenStore());

  final bool conflict;
  final List<(String, DayCardResult, int)> answers = [];
  final List<PlanStage> closedStages = [];
  final Map<String, DayCard> _known = {};
  int _position = 100;

  @override
  Future<DayAnswerOutcome> answerDayCard(String planId, int number, String cardId, {required DayCardResult result, required int attempts}) async {
    if (conflict) {
      throw DioException(
        requestOptions: RequestOptions(path: '/x'),
        response: Response(requestOptions: RequestOptions(path: '/x'), statusCode: 409, data: {'code': 'plan_card_answered'}),
      );
    }
    answers.add((cardId, result, attempts));
    final isRetry = cardId.endsWith('-retry');
    final stage = _stageOf(cardId);
    final answered = DayCard(
      id: cardId,
      stage: stage,
      position: _known[cardId]?.position ?? 0,
      kind: DayCardKind.wordChoose,
      source: DayCardSource.today,
      unitKind: PlanUnitKind.word,
      unitRef: cardId.split('-').first,
      payload: const {},
      retryOf: isRetry ? cardId.replaceAll('-retry', '') : null,
      result: result,
      attempts: attempts,
      returns: isRetry && result == DayCardResult.failed,
    );
    DayCard? requeued;
    if (result == DayCardResult.failed && !isRetry) {
      requeued = DayCard(
        id: '$cardId-retry',
        stage: stage,
        position: _position++,
        kind: DayCardKind.wordChoose,
        source: DayCardSource.today,
        unitKind: PlanUnitKind.word,
        unitRef: cardId,
        payload: const {},
        retryOf: cardId,
        attempts: 0,
        returns: false,
      );
      _known[requeued.id] = requeued;
    }
    return DayAnswerOutcome(card: answered, requeued: requeued);
  }

  PlanStage _stageOf(String id) => switch (id[0]) {
    'w' => PlanStage.words,
    'p' => PlanStage.phrases,
    'l' => PlanStage.listen,
    _ => PlanStage.speak,
  };

  @override
  Future<PlanDayRoom> closeStage(String planId, int number, PlanStage stage) async {
    closedStages.add(stage);
    return _room();
  }

  int closedDays = 0;

  @override
  Future<PlanDayRoom> closeDay(String planId, int number) async {
    closedDays++;

    return _room();
  }

  PlanDayRoom _room() => PlanDayRoom(
    planId: 'p',
    day: PlanDayRoute.fromJson(const {'id': 'd1', 'number': 1, 'type': 'scene', 'status': 'in_progress'}),
    stages: const [],
    program: const [],
  );
}
