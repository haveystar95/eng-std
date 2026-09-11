import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/plan/day_rules.dart';
import 'package:eng_std/data/plan/plan_contract.dart';

/// КАНОН ЗАЧЁТА ДНЯ — наряд DAY-UI, раздел 5. Тесты на правила, не на код: каждый называет
/// строку канона, которую охраняет.
void main() {
  group('выбор и сборка (§5)', () {
    test('верно с первого раза — passed', () {
      expect(DayRules.graded(correct: true, isRetry: false), DayAttempt.passed);
      expect(DayRules.resultOf(DayAttempt.passed), DayCardResult.passed);
    });

    test('ошибка — карточка в конец этапа; вторая ошибка — failed, вернётся в день N', () {
      expect(DayRules.graded(correct: false, isRetry: false), DayAttempt.requeue);
      expect(DayRules.graded(correct: false, isRetry: true), DayAttempt.failed);
      // На сервер уходит `failed` в обоих случаях — повтор в конце этапа ставит он сам.
      expect(DayRules.resultOf(DayAttempt.requeue), DayCardResult.failed);
      expect(DayRules.resultOf(DayAttempt.failed), DayCardResult.failed);
    });

    test('вернувшаяся карточка, собранная верно, — passed без оговорок', () {
      expect(DayRules.graded(correct: true, isRetry: true), DayAttempt.passed);
    });

    test('сборка сравнивает слова, не регистр и не знаки', () {
      expect(DayRules.assembledMatches(placed: ['my', 'lower', 'back', 'hurts.'], answer: 'My lower back hurts.'), isTrue);
      expect(DayRules.assembledMatches(placed: ['lower', 'my', 'back', 'hurts'], answer: 'My lower back hurts.'), isFalse);
      expect(DayRules.assembledMatches(placed: ['my', 'back', 'hurts'], answer: 'My lower back hurts.'), isFalse);
    });

    test('лишняя плитка — та, которой нет в ответе; она и вздрагивает', () {
      expect(DayRules.extraTileIndex(placed: ['my', 'back', 'fever', 'hurts'], answer: 'My back hurts'), 2);
      expect(DayRules.extraTileIndex(placed: ['my', 'back', 'hurts'], answer: 'My back hurts'), isNull);
    });
  });

  group('микрофон (§5)', () {
    test('зачёт, если услышан speaking_key целиком', () {
      expect(
        DayRules.spokenAccepted(transcript: 'well my lower back', expected: 'My lower back hurts when I sit', key: 'lower back'),
        isTrue,
      );
      // Ключ порванный — не ключ.
      expect(
        DayRules.spokenAccepted(transcript: 'lower my back', expected: 'My lower back hurts when I sit', key: 'lower back'),
        isFalse,
      );
    });

    test('зачёт, если ≥ 70 % слов текста или любого варианта', () {
      const line = 'It started three days ago';
      expect(DayRules.spokenAccepted(transcript: 'it started three days', expected: line), isTrue); // 4/5
      expect(DayRules.spokenAccepted(transcript: 'it started three', expected: line), isFalse); // 3/5
      expect(
        DayRules.spokenAccepted(transcript: 'three days ago', expected: line, variants: ['Three days ago']),
        isTrue,
      );
    });

    test('порог покрытия приходит с карточкой', () {
      const line = 'It started three days ago';
      expect(DayRules.spokenAccepted(transcript: 'it started three', expected: line, coverage: 0.6), isTrue);
    });

    test('токены: регистр и знаки сняты, апостроф внутри слова остаётся', () {
      expect(DayRules.tokens("I don't have a fever!"), ['i', "don't", 'have', 'a', 'fever']);
      expect(DayRules.coverageOf(expected: "I don't have a fever", spoken: "i DON'T have a fever."), 1.0);
      expect(DayRules.coverageOf(expected: 'a a b', spoken: 'a b'), closeTo(2 / 3, 1e-9));
    });

    test('тишина — не зачёт', () {
      expect(DayRules.spokenAccepted(transcript: '', expected: 'lower back', key: 'lower back'), isFalse);
    });

    test('«Пропустить» — после второй неудачной попытки или сразу без микрофона', () {
      expect(DayRules.canSkip(attempts: 1, micUnavailable: false), isFalse);
      expect(DayRules.canSkip(attempts: 2, micUnavailable: false), isTrue);
      expect(DayRules.canSkip(attempts: 0, micUnavailable: true), isTrue);
    });

    test('лестница подсказок: ключ — полный зачёт, весь текст — с подсказкой', () {
      expect(DayRules.spokenResult(hintLevel: 0), DayCardResult.passed);
      expect(DayRules.spokenResult(hintLevel: 1), DayCardResult.passed);
      expect(DayRules.spokenResult(hintLevel: 2), DayCardResult.hinted);
    });
  });

  group('состав дня по уровню (§4)', () {
    test('«услышал → собери» — только Intermediate, только утверждения ≤ 10 слов', () {
      expect(DayRules.listenAssembleAllowed(level: PlanLevel2.intermediate, partnerLine: 'Take this twice a day after meals.'), isTrue);
      expect(DayRules.listenAssembleAllowed(level: PlanLevel2.beginner, partnerLine: 'Take this twice a day after meals.'), isFalse);
      expect(DayRules.listenAssembleAllowed(level: PlanLevel2.intermediate, partnerLine: 'Did it start today, or earlier?'), isFalse);
      expect(
        DayRules.listenAssembleAllowed(
          level: PlanLevel2.intermediate,
          partnerLine: 'It looks like a muscle strain so rest and use a heating pad every evening.',
        ),
        isFalse,
      );
    });

    test('вид карточки решает сервер: неизвестный kind не рендерится', () {
      expect(DayCard.fromJson({'id': 'x', 'kind': 'something_new', 'stage': 'words'}), isNull);
      expect(DayCard.fromJson({'id': 'x', 'kind': 'listen_assemble', 'stage': 'listen'})?.kind, DayCardKind.listenAssemble);
    });
  });

  group('метрики дня', () {
    DayCard card(String id, DayCardKind kind, {DayCardResult? result, int attempts = 0, String? retryOf}) => DayCard(
      id: id,
      stage: DayStage.words,
      position: 0,
      kind: kind,
      source: DayCardSource.today,
      unitKind: DayUnitKind.word,
      unitRef: id,
      payload: const {},
      result: result,
      attempts: attempts,
      retryOf: retryOf,
      returns: false,
    );

    test('«с первого раза» — оценённые карточки, сданные с первой попытки; знакомства не считаются', () {
      final cards = [
        card('a', DayCardKind.wordIntro, result: DayCardResult.passed, attempts: 1),
        card('b', DayCardKind.wordChoose, result: DayCardResult.passed, attempts: 1),
        card('c', DayCardKind.wordChoose, result: DayCardResult.failed, attempts: 1),
        card('c2', DayCardKind.wordChoose, result: DayCardResult.passed, attempts: 1, retryOf: 'c'),
        card('d', DayCardKind.wordSay, result: DayCardResult.passed, attempts: 2),
      ];
      // b — да; c — нет; c2 — повтор, не считается; d — со второй попытки. 1 из 3.
      expect(DayRules.firstTryPercent(cards), 33);
      expect(DayRules.firstTryPercent([card('a', DayCardKind.wordIntro)]), isNull);
    });

    test('оценка минут этапа — по числу карточек', () {
      expect(DayRules.estimateMinutes(0), 0);
      expect(DayRules.estimateMinutes(32), 7);
    });
  });
}
