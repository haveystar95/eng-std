import 'dart:convert';
import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/plan/plan_models.dart';
import 'package:eng_std/data/plan/session/session_day.dart';
import 'package:eng_std/data/plan/session/session_models.dart';
import 'package:eng_std/data/plan/session/session_outcomes.dart';
import 'package:eng_std/data/plan/session/session_rules.dart';
import 'package:eng_std/data/plan/session/speech_match.dart';

/// THE SESSION'S GRADING RULES (work order SESSION-1b §1 and §6): the «what the client may write» matrix, checking
/// choices and tiles, the voice pass.
void main() {
  final day = SessionDay.fromJson(
    jsonDecode(File('../backend2/docs/fixtures/day-doctor.json').readAsStringSync()) as Map<String, dynamic>,
  );
  T first<T extends CardPayload>(PlanStage stage) => day.stageOf(stage)!.cards.map((c) => c.payload).whereType<T>().first;
  final en = day.speech;

  group('what the client may write', () {
    const choice = {SessionResult.passed, SessionResult.failed};
    const voice = {SessionResult.passed, SessionResult.skipped};
    const judged = {SessionResult.skipped};
    const walkthrough = {SessionResult.passed};

    // The matrix of the work orders SESSION-1b §1 and SESSION-1c §1, kind by kind — the server's table
    // (`CardKind::allows`, plan-api «What the client sends») narrowed to what this client does.
    const matrix = <SessionKind, Set<SessionResult>>{
      SessionKind.wordIntro: walkthrough,
      SessionKind.wordRepeat: voice,
      SessionKind.wordChoose: choice,
      SessionKind.wordListen: choice,
      SessionKind.wordAssemble: choice,
      SessionKind.wordInLine: choice,
      SessionKind.phraseIntro: walkthrough,
      SessionKind.phraseAssemble: choice,
      SessionKind.phraseChooseBack: choice,
      SessionKind.phraseSlot: choice,
      SessionKind.phraseSlotListen: choice,
      SessionKind.phraseRepeat: voice,
      SessionKind.phraseOtherSlot: voice,
      SessionKind.phraseCombine: choice,
      SessionKind.dialoguePartner: choice,
      SessionKind.dialogueAnswer: voice,
      SessionKind.dialogueAsk: voice,
      SessionKind.dialogueRescue: walkthrough,
      SessionKind.listenDialogue: walkthrough,
      SessionKind.listenQuestion: choice,
      SessionKind.listenReview: walkthrough,
      SessionKind.listenPredict: choice,
      SessionKind.listenPace: walkthrough,
      SessionKind.listenNumber: choice,
      SessionKind.speakAnswer: judged,
      SessionKind.speakEcho: voice,
      SessionKind.speakRetell: voice,
    };

    // CATCHES: a voice kind that may write `failed` (422 and a dropped answer), a judged kind that writes its own pass,
    // a walkthrough that is skipped, and a new kind added without a row.
    test('the matrix on all 27 kinds: choice passed | failed, voice passed | skipped, judged only skipped, walkthrough passed', () {
      expect(matrix.keys.toSet(), SessionKind.values.toSet());
      expect(SessionKind.values, hasLength(27));
      for (final kind in SessionKind.values) {
        final writes = SessionRules.clientWrites(kind);
        expect(writes, matrix[kind], reason: kind.wire);
        expect(writes, isNot(contains(SessionResult.hinted)), reason: '${kind.wire}: hinted is the judge\'s word');
        // Whatever the client writes, the server always accepts — otherwise a 422.
        expect(SessionRules.serverAccepts(kind).containsAll(writes), isTrue, reason: kind.wire);
        for (final result in SessionResult.values) {
          expect(SessionRules.mayWrite(kind, result), writes.contains(result), reason: '${kind.wire} ${result.wire}');
        }
      }
    });

    test('the kinds are sorted by grading method as on the server', () {
      // Only `speak_answer` is JUDGED since FIX-2 §5: «Скажи целиком» asks the judge on its last round and is graded
      // as a voice card — that round is practice and its result is its value rounds'.
      expect(SessionKind.speakAnswer.grading, SessionGrading.judge);
      // `speak_retell` left the judge with BACK-TAILS-1 §1.1: «Say your line again» is graded by coverage, and a
      // question to `…/judge` on it comes back 422.
      for (final k in [
        SessionKind.wordRepeat,
        SessionKind.phraseRepeat,
        SessionKind.phraseOtherSlot,
        SessionKind.dialogueAnswer,
        SessionKind.dialogueAsk,
        SessionKind.speakEcho,
        SessionKind.speakRetell,
      ]) {
        expect(k.grading, SessionGrading.voice, reason: k.wire);
      }
      for (final k in [SessionKind.dialogueRescue, SessionKind.listenDialogue, SessionKind.listenReview, SessionKind.listenPace]) {
        expect(k.grading, SessionGrading.pass, reason: k.wire);
      }
      for (final k in [SessionKind.listenQuestion, SessionKind.listenPredict, SessionKind.listenNumber, SessionKind.dialoguePartner]) {
        expect(k.grading, SessionGrading.choice, reason: k.wire);
      }
      for (final kind in SessionKind.values) {
        expect(matrix[kind], SessionRules.clientWrites(kind), reason: kind.wire);
      }
    });
  });

  group('SESSION-1c', () {
    final dialogue = day.stageOf(PlanStage.dialogue)!.cards;
    DialogueAnswerPayload answerOf(String exchange) =>
        dialogue.map((c) => c.payload).whereType<DialogueAnswerPayload>().firstWhere((p) => p.exchange.ref == exchange);

    // CATCHES: «No hints» that changes nothing, a beginner asked by voice, an intermediate given chips, and chips drawn
    // for a frame that has none.
    test('the dialogue mode: beginner — chips, intermediate — the line as a hint, «No hints» — blind at any level', () {
      final slot = answerOf('x1');
      final noSlot = answerOf('x4');
      expect(noSlot.frame.hasSlot, isFalse);
      expect(noSlot.modes.chips, isEmpty);
      expect(SessionRules.dialogueMode(slot, level: PlanLevel.beginner, noHints: false), DialogueMode.chips);
      expect(SessionRules.dialogueMode(slot, level: PlanLevel.intermediate, noHints: false), DialogueMode.voiceHint);
      expect(SessionRules.dialogueMode(slot, level: PlanLevel.beginner, noHints: true), DialogueMode.voiceBlind);
      expect(SessionRules.dialogueMode(slot, level: PlanLevel.intermediate, noHints: true), DialogueMode.voiceBlind);
      expect(SessionRules.dialogueMode(noSlot, level: PlanLevel.beginner, noHints: false), DialogueMode.voiceHint,
          reason: 'a frame without a slot has no chips — the beginner says the line');
      expect([for (final m in DialogueMode.values) m.wire], ['chips', 'voice_hint', 'voice_blind']);
      expect(SessionRules.dialogueExpected(slot, DialogueMode.voiceHint), 'It hurts in his lower back.');
      expect(SessionRules.dialogueExpected(slot, DialogueMode.voiceBlind), 'It hurts in his');
    });

    test('the frame\'s own words — the server\'s FrameParts::part', () {
      expect(SessionRules.framePart('It hurts in his ___.'), 'It hurts in his');
      expect(SessionRules.framePart("I'd like a ___, please."), "I'd like a, please");
      expect(SessionRules.framePart('The pain is ___ when he bends.'), 'The pain is when he bends');
      expect(SessionRules.framePart("He doesn't have a fever."), "He doesn't have a fever");
      expect(SessionRules.framePart('Do we need ___?'), 'Do we need');
    });

    // CATCHES: a dialogue answer that demands the lesson's own filler (the slot is anyone's), and one that passes without
    // the frame.
    test('dialogue voice: the frame is the key and the slot is anyone\'s; the echo — the line as it stands', () {
      final x1 = answerOf('x1');
      expect(x1.speechMode, SpeechMode.free);
      expect(SessionRules.voiceAccepted(x1, 'it hurts in his knee', en), isTrue, reason: 'any slot');
      expect(SessionRules.voiceAccepted(x1, 'It hurts in his lower back', en), isTrue);
      expect(SessionRules.voiceAccepted(x1, 'lower back', en), isFalse, reason: 'no frame');
      final x2 = answerOf('x2');
      expect(SessionRules.voiceAccepted(x2, 'it started yesterday', en), isTrue);
      expect(SessionRules.voiceAccepted(x2, 'started yesterday', en), isFalse, reason: 'a two-word frame needs both words');

      final echo = day.stageOf(PlanStage.speak)!.cards.map((c) => c.payload).whereType<SpeakEchoPayload>().single;
      expect(echo.speechMode, SpeechMode.repeat);
      expect(SessionRules.voiceAccepted(echo, 'It looks like a muscle strain so he should rest and use a heating pad', en), isTrue);
      expect(SessionRules.voiceAccepted(echo, 'muscle strain rest', en), isFalse);
      expect(SessionRules.expectedSpeech(echo), echo.expectedText);
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
    test('word_repeat: the word on the screen, said as it stands', () {
      final p = first<WordRepeatPayload>(PlanStage.words);
      expect(p.speechMode, SpeechMode.repeat);
      expect(SessionRules.voiceAccepted(p, 'Lower back', en), isTrue);
      expect(SessionRules.voiceAccepted(p, 'lowerback', en), isTrue, reason: 'gluing counts as both words');
      expect(SessionRules.voiceAccepted(p, 'lower', en), isFalse);
      expect(SessionRules.voiceAccepted(p, '', en), isFalse);
    });

    // Canon (FIX-2 §5): a VALUE round of «Скажи целиком» is the whole phrase with that value, on the screen — so
    // every content word of it, in order. CATCHES: a round passed on the frame alone, and one passed with another
    // value in the window.
    test('«Скажи целиком»: a value round is the whole phrase with that value', () {
      final p = first<PhraseOtherSlotPayload>(PlanStage.phrases);
      expect(p.speechMode, SpeechMode.repeat);
      expect(p.rounds.first.expectedText, 'It hurts in his lower back.');
      expect(SessionRules.roundAccepted(p, p.rounds.first, 'it hurts in his lower back', en), isTrue);
      expect(SessionRules.roundAccepted(p, p.rounds.first, 'it hurts in his neck', en), isFalse);
      expect(SessionRules.roundAccepted(p, p.rounds.first, 'lower back', en), isFalse);
      expect(SessionRules.roundAccepted(p, p.rounds[1], 'it hurts in his neck', en), isTrue);
    });

    // RULE (FIX-1 §5, наряд FIX-2 §5): «Ещё раз» с итога дня не может спросить судью — сервер откажет отвеченной
    // карточке, — и телефон судит ТО ЖЕ, что судил бы судья: ключ, то есть слова каркаса вне окна, в режиме `free`.
    // CATCHES: повтор, который принимает любой звук (так было до FIX-1); и «Скажи целиком», у которой круг своего
    // слова сверяют с фразой круга значений — своё слово с ней не совпадёт никогда.
    test('a replay grades what the judge would have: the frame\'s own words, the window anyone\'s', () {
      final answer = day.stageOf(PlanStage.speak)!.cards.map((c) => c.payload).whereType<SpeakAnswerPayload>().first;
      final whole = first<PhraseOtherSlotPayload>(PlanStage.phrases);
      expect(SessionRules.replayAccepted(answer, 'it hurts in his knee', en), isTrue);
      expect(SessionRules.replayAccepted(answer, 'knee', en), isFalse, reason: 'no frame');
      expect(SessionRules.replayAccepted(whole, 'it hurts in his elbow', en), isTrue, reason: 'the own word');
      expect(SessionRules.replayAccepted(whole, 'my elbow', en), isFalse, reason: 'no frame');
    });

    test('the kind\'s expected speech', () {
      expect(SessionRules.expectedSpeech(first<WordRepeatPayload>(PlanStage.words)), 'lower back');
      expect(SessionRules.expectedSpeech(first<PhraseOtherSlotPayload>(PlanStage.phrases)), 'It hurts in his lower back.');
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
