import 'dart:convert';
import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/plan/plan_models.dart';
import 'package:eng_std/data/plan/session/session_day.dart';
import 'package:eng_std/data/plan/session/session_models.dart';
import 'package:eng_std/data/plan/session/session_outcomes.dart';
import 'package:eng_std/data/plan/session/session_rules.dart';
import 'package:eng_std/data/plan/session/speech_coverage.dart';

/// ПРАВИЛА ЗАЧЁТА СЕССИИ (наряд SESSION-1b, разд. 1 и 6): матрица «что клиент вправе записать», сверка
/// выбора и плиток, зачёт голоса.
void main() {
  final day = SessionDay.fromJson(
    jsonDecode(File('../backend2/docs/fixtures/day-doctor.json').readAsStringSync()) as Map<String, dynamic>,
  );
  T first<T extends CardPayload>(PlanStage stage) => day.stageOf(stage)!.cards.map((c) => c.payload).whereType<T>().first;
  final en = SpeechCoverage.articlesFor('en');

  group('что клиент вправе записать', () {
    test('голос никогда не пишет failed; судейский вид — только skipped; прохождение — passed', () {
      for (final kind in SessionKind.values) {
        final writes = SessionRules.clientWrites(kind);
        switch (kind.grading) {
          case SessionGrading.voice:
            expect(writes, {SessionResult.passed, SessionResult.skipped}, reason: kind.wire);
            expect(writes, isNot(contains(SessionResult.failed)), reason: kind.wire);
          case SessionGrading.judge:
            expect(writes, {SessionResult.skipped}, reason: kind.wire);
          case SessionGrading.pass:
            expect(writes, {SessionResult.passed}, reason: kind.wire);
          case SessionGrading.choice:
            expect(writes, {SessionResult.passed, SessionResult.failed}, reason: kind.wire);
        }
        // То, что пишет клиент, сервер всегда принимает — иначе 422.
        expect(SessionRules.serverAccepts(kind).containsAll(writes), isTrue, reason: kind.wire);
      }
    });

    test('виды 1b разложены по способам зачёта как на сервере', () {
      expect(SessionKind.phraseOwnSlot.grading, SessionGrading.judge);
      expect(SessionKind.speakAnswer.grading, SessionGrading.judge);
      expect(SessionKind.speakRetell.grading, SessionGrading.judge);
      for (final k in [SessionKind.wordRepeat, SessionKind.phraseRepeat, SessionKind.phraseOtherSlot]) {
        expect(k.grading, SessionGrading.voice);
        expect(SessionRules.mayWrite(k, SessionResult.failed), isFalse);
      }
      expect(SessionRules.mayWrite(SessionKind.phraseOwnSlot, SessionResult.passed), isFalse);
      expect(SessionRules.mayWrite(SessionKind.wordIntro, SessionResult.passed), isTrue);
      expect(
        [for (final k in SessionKind.values) if (k.hasScreen) k],
        hasLength(15),
      );
    });
  });

  test('выбор: id варианта = correct', () {
    final p = first<WordChoosePayload>(PlanStage.words);
    expect(SessionRules.choiceCorrect(p, 'o2'), isTrue);
    expect(SessionRules.choiceCorrect(p, 'o1'), isFalse);
  });

  test('word_assemble: собранное = expected по порядку, слово в слово', () {
    expect(SessionRules.wordAssembled(['lower', 'back'], ['lower', 'back']), isTrue);
    expect(SessionRules.wordAssembled(['back', 'lower'], ['lower', 'back']), isFalse);
    expect(SessionRules.wordAssembled(['lower'], ['lower', 'back']), isFalse);
    expect(SessionRules.wordAssembled(['x-ray'], ['X-ray']), isFalse);
  });

  group('phrase_assemble', () {
    final p = first<PhraseAssemblePayload>(PlanStage.phrases);
    // tiles: in, his, it, the, started, hurts; expected: it hurts in his + окно 4 наполнение 0.
    TilePiece t(String word) => TilePiece(p.tiles.indexOf(word), word);
    SlotPiece s(int index) => SlotPiece(p.chips.firstWhere((f) => f.index == index));

    test('слова, место окна и наполнение совпали — зачёт', () {
      expect(SessionRules.phraseAssembled(p, [t('it'), t('hurts'), t('in'), t('his'), s(0)]), isTrue);
    });

    test('другое наполнение, другое место окна или лишняя плитка — не зачёт', () {
      expect(SessionRules.phraseAssembled(p, [t('it'), t('hurts'), t('in'), t('his'), s(1)]), isFalse);
      expect(SessionRules.phraseAssembled(p, [t('it'), t('hurts'), t('in'), s(0), t('his')]), isFalse);
      expect(SessionRules.phraseAssembled(p, [t('it'), t('hurts'), t('in'), t('the'), s(0)]), isFalse);
      expect(SessionRules.phraseAssembled(p, [t('it'), t('hurts'), t('in'), t('his')]), isFalse);
    });

    test('место первой ошибки и что там должно было стоять', () {
      final wrong = [t('it'), t('hurts'), t('in'), t('the'), s(0)];
      expect(SessionRules.phraseMismatch(p, wrong), 3);
      expect(SessionRules.phraseExpectedAt(p, 3), (word: 'his', fillerIndex: null));
      expect(SessionRules.phraseExpectedAt(p, 4), (word: null, fillerIndex: 0));
      expect(SessionRules.phraseMismatch(p, [t('it'), t('hurts'), t('in'), t('his'), s(0)]), -1);
    });
  });

  test('phrase_combine: каркас = correct_frame, наполнение любое', () {
    final p = first<PhraseCombinePayload>(PlanStage.phrases);
    expect(SessionRules.combineCorrect(p, 'p2'), isTrue);
    expect(SessionRules.combineCorrect(p, 'p1'), isFalse);
  });

  group('голос', () {
    test('word_repeat: короткое слово — все слова', () {
      final p = first<WordRepeatPayload>(PlanStage.words);
      expect(p.coverageMin, 1.0);
      expect(SessionRules.voiceAccepted(p, 'Lower back', en), isTrue);
      expect(SessionRules.voiceAccepted(p, 'lower', en), isFalse);
      expect(SessionRules.voiceAccepted(p, '', en), isFalse);
    });

    test('phrase_other_slot: каркас покрыт И все слова окна услышаны — считаются раздельно', () {
      final p = first<PhraseOtherSlotPayload>(PlanStage.phrases);
      expect(p.expectedText, 'It hurts in his shoulder.');
      expect(SessionRules.voiceAccepted(p, 'it hurts in his shoulder', en), isTrue);
      expect(SessionRules.otherSlotParts(p, 'it hurts in his neck', en), (frame: true, slot: false));
      expect(SessionRules.voiceAccepted(p, 'it hurts in his neck', en), isFalse);
      expect(SessionRules.otherSlotParts(p, 'shoulder', en), (frame: false, slot: true));
      expect(SessionRules.voiceAccepted(p, 'shoulder', en), isFalse);
    });

    test('ожидаемая речь вида', () {
      expect(SessionRules.expectedSpeech(first<WordRepeatPayload>(PlanStage.words)), 'lower back');
      expect(SessionRules.expectedSpeech(first<PhraseOwnSlotPayload>(PlanStage.phrases)), 'It started .');
    });
  });

  test('ответ на провод: attempts ≥ 1, response — только ключи контракта и не пустые', () {
    expect(const SessionAnswer(result: SessionResult.passed, attempts: 0).toJson(), {'result': 'passed', 'attempts': 1});
    expect(
      const SessionAnswer(
        result: SessionResult.skipped,
        attempts: 2,
        response: SessionResponse(heard: 'lower', noMic: true, fillerIndex: 12),
      ).toJson(),
      {
        'result': 'skipped',
        'attempts': 2,
        'response': {'heard': 'lower', 'no_mic': true},
      },
    );
    expect(const SessionAnswer(result: SessionResult.failed, attempts: 1, response: SessionResponse()).toJson(), {
      'result': 'failed',
      'attempts': 1,
    });
    final long = SessionResponse(heard: 'a' * 1500).toJson();
    expect((long['heard'] as String).length, 1000);
  });
}
