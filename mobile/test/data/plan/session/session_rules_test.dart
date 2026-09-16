import 'dart:convert';
import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/plan/plan_models.dart';
import 'package:eng_std/data/plan/session/session_day.dart';
import 'package:eng_std/data/plan/session/session_models.dart';
import 'package:eng_std/data/plan/session/session_outcomes.dart';
import 'package:eng_std/data/plan/session/session_rules.dart';
import 'package:eng_std/data/plan/session/speech_coverage.dart';

/// THE SESSION'S GRADING RULES (work order SESSION-1b §1 and §6): the «what the client may write» matrix, checking
/// choices and tiles, the voice pass.
void main() {
  final day = SessionDay.fromJson(
    jsonDecode(File('../backend2/docs/fixtures/day-doctor.json').readAsStringSync()) as Map<String, dynamic>,
  );
  T first<T extends CardPayload>(PlanStage stage) => day.stageOf(stage)!.cards.map((c) => c.payload).whereType<T>().first;
  final en = SpeechCoverage.articlesFor('en');

  group('what the client may write', () {
    test('voice never writes failed; a judge-graded kind only skipped; a walkthrough — passed', () {
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
        // Whatever the client writes, the server always accepts — otherwise a 422.
        expect(SessionRules.serverAccepts(kind).containsAll(writes), isTrue, reason: kind.wire);
      }
    });

    test('the 1b kinds are sorted by grading method as on the server', () {
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

  test('choice: the option id equals correct', () {
    final p = first<WordChoosePayload>(PlanStage.words);
    expect(SessionRules.choiceCorrect(p, 'o2'), isTrue);
    expect(SessionRules.choiceCorrect(p, 'o1'), isFalse);
  });

  test('word_assemble: the assembly equals expected in order, word for word', () {
    expect(SessionRules.wordAssembled(['lower', 'back'], ['lower', 'back']), isTrue);
    expect(SessionRules.wordAssembled(['back', 'lower'], ['lower', 'back']), isFalse);
    expect(SessionRules.wordAssembled(['lower'], ['lower', 'back']), isFalse);
    expect(SessionRules.wordAssembled(['x-ray'], ['X-ray']), isFalse);
  });

  group('phrase_assemble', () {
    final p = first<PhraseAssemblePayload>(PlanStage.phrases);
    // tiles: in, his, it, the, started, hurts; expected: it hurts in his + slot at 4, filler 1.
    TilePiece t(String word) => TilePiece(p.tiles.indexOf(word), word);
    SlotPiece s(int index) => SlotPiece(p.chips.firstWhere((f) => f.index == index));

    test('words, the slot\'s place and the filler match — pass', () {
      expect(SessionRules.phraseAssembled(p, [t('it'), t('hurts'), t('in'), t('his'), s(1)]), isTrue);
    });

    test('another filler, another slot place or an extra tile — no pass', () {
      expect(SessionRules.phraseAssembled(p, [t('it'), t('hurts'), t('in'), t('his'), s(0)]), isFalse);
      expect(SessionRules.phraseAssembled(p, [t('it'), t('hurts'), t('in'), s(1), t('his')]), isFalse);
      expect(SessionRules.phraseAssembled(p, [t('it'), t('hurts'), t('in'), t('the'), s(1)]), isFalse);
      expect(SessionRules.phraseAssembled(p, [t('it'), t('hurts'), t('in'), t('his')]), isFalse);
    });

    test('the place of the first mistake and what should have stood there', () {
      final wrong = [t('it'), t('hurts'), t('in'), t('the'), s(1)];
      expect(SessionRules.phraseMismatch(p, wrong), 3);
      expect(SessionRules.phraseExpectedAt(p, 3), (word: 'his', fillerIndex: null));
      expect(SessionRules.phraseExpectedAt(p, 4), (word: null, fillerIndex: 1));
      expect(SessionRules.phraseMismatch(p, [t('it'), t('hurts'), t('in'), t('his'), s(1)]), -1);
    });
  });

  test('phrase_combine: the frame equals correct_frame, any filler', () {
    final p = first<PhraseCombinePayload>(PlanStage.phrases);
    expect(SessionRules.combineCorrect(p, 'p1'), isTrue);
    expect(SessionRules.combineCorrect(p, 'p5'), isFalse);
  });

  group('voice', () {
    test('word_repeat: a short word — all words', () {
      final p = first<WordRepeatPayload>(PlanStage.words);
      expect(p.coverageMin, 1.0);
      expect(SessionRules.voiceAccepted(p, 'Lower back', en), isTrue);
      expect(SessionRules.voiceAccepted(p, 'lowerback', en), isTrue, reason: 'gluing counts as both words');
      expect(SessionRules.voiceAccepted(p, 'lower', en), isFalse);
      expect(SessionRules.voiceAccepted(p, '', en), isFalse);
    });

    test('phrase_other_slot: the frame covered AND every slot word heard — counted separately', () {
      final p = first<PhraseOtherSlotPayload>(PlanStage.phrases);
      expect(p.expectedText, 'It hurts in his shoulder.');
      expect(SessionRules.voiceAccepted(p, 'it hurts in his shoulder', en), isTrue);
      expect(SessionRules.otherSlotParts(p, 'it hurts in his neck', en), (frame: true, slot: false));
      expect(SessionRules.voiceAccepted(p, 'it hurts in his neck', en), isFalse);
      expect(SessionRules.otherSlotParts(p, 'shoulder', en), (frame: false, slot: true));
      expect(SessionRules.voiceAccepted(p, 'shoulder', en), isFalse);
    });

    test('the kind\'s expected speech', () {
      expect(SessionRules.expectedSpeech(first<WordRepeatPayload>(PlanStage.words)), 'lower back');
      expect(SessionRules.expectedSpeech(first<PhraseOwnSlotPayload>(PlanStage.phrases)), 'It started .');
    });
  });

  test('the answer on the wire: attempts ≥ 1, response — only the contract\'s keys, never empty', () {
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
