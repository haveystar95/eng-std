import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/data/plan/plan_models.dart';
import 'package:eng_std/data/plan/session/session_models.dart';
import 'package:eng_std/data/plan/session/session_outcomes.dart';
import 'package:eng_std/features/plan/conversation/talk_ribbon.dart' show TalkPartnerBubble, TalkPill;
import 'package:eng_std/features/plan/session/parts/session_bits.dart' show SessionListenButton;
import 'package:eng_std/features/plan/session/parts/session_bubbles.dart';
import 'package:eng_std/features/plan/session/parts/session_mic_panel.dart' show SessionTextExit;
import 'package:eng_std/features/plan/session/session_mic.dart';
import 'package:eng_std/theme/theme.dart';

import '../../../support/session_harness.dart';

/// SPEAK MYSELF (work order SESSION-1c §4, canvas series 35): the answer judged by meaning with its frame hint (by
/// silence or by «Hint», `hinted` to the judge, none under «No hints»), the three exits of a rejection, the echo after
/// its pause, the retelling in the native language.
void main() {
  final day = sessionFixture('day-doctor');

  SessionCard speakAt(int position) => day.stageOf(PlanStage.speak)!.cards.firstWhere((c) => c.position == position);
  List<SessionResult> results(CardProbe probe) => [for (final a in probe.answers) a.result];
  const rejected = SessionJudgeOutcome(accepted: false, reasonNative: 'Ты сказал не про время.', attempts: 1);

  group('35-2 · 35-5 speak_answer — на ленте разговора', () {
    // ПРАВИЛО (наряд CLIENT-CONV-1a, кадр 35-2): подсказка — ЧИП с заданием на родном, и «Подсказать»
    // уходит вместе с ним: вместе они не стоят. Задание до подсказки на экране не стоит вовсе —
    // «Ответь своими словами» перестаёт быть заданием, если ответ уже написан под вопросом.
    // ЛОВИТ: задание, показанное в пузыре с самого начала; чип и кнопку рядом; `hinted: false` после
    // открытой подсказки (сервер написал бы `passed` вместо `hinted`).
    testWidgets('подсказка — чип на родном, и «Подсказать» уходит вместе с ней', (tester) async {
      final probe = CardProbe()
        ..verdict = (_) => const SessionJudgeOutcome(accepted: true, slotValue: 'last night', result: SessionResult.hinted, attempts: 1);
      final voice = QuietVoice();
      await pumpCard(tester, probeEnv(speakAt(2), probe, voice: voice));
      expect(find.text('Ответь своими словами'), findsOneWidget);
      expect(find.text('Did it start today, or earlier this week?'), findsOneWidget);
      expect(find.text('Началось три дня назад.'), findsNothing, reason: 'задание не стоит на экране до подсказки');
      await tester.pump(const Duration(milliseconds: 300));
      expect(voice.played, ['x2@1.0']);
      expect(find.byKey(const ValueKey('talk-hint-chip')), findsNothing);
      expect(find.byKey(const ValueKey('exit-hint')), findsOneWidget);

      await tester.pump(const Duration(seconds: 5));
      await tester.pump();
      expect(find.byKey(const ValueKey('talk-hint-chip')), findsOneWidget);
      expect(find.text('Началось три дня назад.'), findsOneWidget, reason: 'чип печатает задание сервера как есть');
      expect(find.byKey(const ValueKey('exit-hint')), findsNothing, reason: 'чип и кнопка вместе не стоят');

      await enterHeard(tester, 'It started last night');
      await tester.pump(const Duration(milliseconds: 1010));
      await tester.pump();
      expect(probe.judged, ['It started last night']);
      expect(probe.hinted, [true]);
      expect(probe.answers, isEmpty, reason: 'судейский зачёт пишет сервер');
      expect(find.byKey(const ValueKey('speak-own')), findsOneWidget);
      expect(find.text('услышал'), findsOneWidget);
      await settleCard(tester);
      expect(probe.nexts, 1, reason: 'зачтено — карточка уходит сама');
    });

    // ПРАВИЛО (кадр 35-2, правка 21.09 «по кадру»): пузырь собеседника — тот же светлый контейнер, что в
    // ленте разговора: текст в тренажёре открыт, поэтому в контейнере один кружок 28 — в правом верхнем
    // углу, в 12 от верха и в 14 от края; чипа «текст» нет.
    // ЛОВИТ: кружок 44 под пузырём и кружок 28 справа от пузыря — 35-2 до правки.
    testWidgets('пузырь собеседника — тот же контейнер с одним кружком 28 в углу', (tester) async {
      await pumpCard(tester, probeEnv(speakAt(2), CardProbe()));
      await tester.pump(const Duration(milliseconds: 300));
      final partner = find.byType(TalkPartnerBubble);
      final plate = tester.getRect(find.descendant(of: partner, matching: find.byType(SessionBubble)));
      final listen = find.descendant(of: partner, matching: find.byKey(const ValueKey('talk-listen')));
      expect(tester.widget<SessionListenButton>(listen).size, 28);
      final circle = tester.getRect(listen).deflate(8);
      expect(plate.right - circle.right, moreOrLessEquals(14, epsilon: 0.5), reason: 'в 14 от правого края');
      expect(circle.top - plate.top, moreOrLessEquals(12, epsilon: 0.5), reason: 'в 12 от верха');
      expect(find.descendant(of: partner, matching: find.byType(SessionListenButton)), findsOneWidget, reason: 'кружок один');
      expect(find.byKey(const ValueKey('talk-open-text')), findsNothing, reason: 'текст открыт — «текст» не нужен');
      await settleCard(tester);
    });

    // ПРАВИЛО (кадр 35-2): «Пропустить» и «Подсказать» — контурные плашки 44 по бокам микрофона, как в
    // разговоре; после отказа судьи «Пропустить» — латунная ссылка над «Ещё раз» («сказал · не зачтено»).
    // ЛОВИТ: боковые выходы серыми словами и серую ссылку после отказа — 35-2 до правки 21.09.
    testWidgets('«Пропустить» и «Подсказать» — плашки 44; после отказа «Пропустить» — латунная ссылка', (tester) async {
      final probe = CardProbe()..verdict = (_) => rejected;
      await pumpCard(tester, probeEnv(speakAt(2), probe));
      await tester.pump(const Duration(milliseconds: 300));
      final skip = find.byKey(const ValueKey('exit-skip'));
      final hint = find.byKey(const ValueKey('exit-hint'));
      expect(tester.widget<TalkPill>(skip).brass, isFalse);
      expect(tester.widget<TalkPill>(hint).brass, isTrue);
      expect(tester.getSize(skip).height, 44);
      expect(tester.getSize(hint).height, 44);

      await sayDebug(tester, 'It started the car');
      await tester.pump();
      expect(tester.widget<SessionTextExit>(find.byKey(const ValueKey('exit-skip'))).brass, isTrue, reason: 'ссылка латунью, как в кадре');
      await settleCard(tester);
    });

    // ПРАВИЛО (кадр 35-2 «сказал · не зачтено»): сказанное остаётся в тёмном пузыре — ЦВЕТОМ БУМАГИ, как любая
    // своя реплика; строка судьи — Inter 15/500 чернилами, слева под пузырём, от края ленты (24); «Ещё раз» —
    // кнопкой, «Пропустить» — ссылкой. Микрофона в этом состоянии нет: следующая попытка начинается с «Ещё раз».
    // ЛОВИТ: серую реплику в пузыре и серую строку судьи справа — 35-2 до приёмки снимков; причину отказа,
    // спрятанную под микрофон; «Ещё раз» ссылкой наравне с «Пропустить».
    testWidgets('не зачтено — реплика бумагой, строка судьи чернилами слева, «Ещё раз» кнопкой', (tester) async {
      final probe = CardProbe()..verdict = (_) => rejected;
      await pumpCard(tester, probeEnv(speakAt(2), probe));
      await tester.pump(const Duration(milliseconds: 300));
      await sayDebug(tester, 'It started the car');
      await tester.pump();
      expect(probe.hinted, [false]);
      final own = find.byKey(const ValueKey('speak-own'));
      final line = tester.widget<Text>(find.descendant(of: own, matching: find.byKey(const ValueKey('talk-own-line'))));
      expect(line.textSpan!.toPlainText(), 'It started the car', reason: 'сказанное остаётся в пузыре');
      final spans = <TextSpan>[];
      line.textSpan!.visitChildren((s) {
        if (s is TextSpan && (s.text ?? '').isNotEmpty) spans.add(s);
        return true;
      });
      expect(spans.every((s) => s.style!.color == AppColors.paper), isTrue, reason: 'цветом бумаги, не серым');
      final judge = find.byKey(const ValueKey('speak-judge-line'));
      expect(find.text('Ты сказал не про время.'), findsOneWidget);
      final judgeText = tester.widget<Text>(judge);
      expect(judgeText.style!.color, AppColors.ink, reason: 'чернилами');
      expect(judgeText.style!.fontSize, 15);
      expect(judgeText.style!.fontWeight, FontWeight.w500);
      expect(tester.getRect(judge).left, moreOrLessEquals(tester.getRect(find.byType(TalkPartnerBubble)).left, epsilon: 0.5),
          reason: 'слева, от края ленты');
      expect(tester.getRect(judge).top, greaterThanOrEqualTo(tester.getRect(own).bottom), reason: 'под пузырём');
      expect(find.byKey(const ValueKey('talk-mic')), findsNothing);
      expect(dockEnabled(tester, 'Ещё раз'), isTrue);

      await tester.tap(find.byKey(const ValueKey('exit-again')));
      await tester.pump();
      expect(find.byKey(const ValueKey('talk-mic')), findsOneWidget, reason: '«Ещё раз» возвращает микрофон');
      await sayDebug(tester, 'It started this morning');
      await tester.pump();
      expect(probe.hinted, [false, false], reason: 'подсказку не открывали');

      await tester.tap(find.byKey(const ValueKey('exit-skip')));
      await tester.pump();
      expect(results(probe), [SessionResult.skipped]);
      expect(probe.nexts, 1);
      await settleCard(tester);
    });

    // ПРАВИЛО НАРЯДА: в «Без подсказок» нет НИ ЧИПА, НИ КНОПКИ — ни по молчанию, ни по нажатию.
    // ЛОВИТ: чип, встающий по пятисекундному таймеру независимо от режима.
    testWidgets('в «Без подсказок» нет ни чипа, ни кнопки', (tester) async {
      final probe = CardProbe()..verdict = (_) => rejected;
      await pumpCard(tester, probeEnv(speakAt(2), probe, noHints: true));
      await tester.pump(const Duration(milliseconds: 300));
      await tester.pump(const Duration(seconds: 6));
      expect(find.byKey(const ValueKey('talk-hint-chip')), findsNothing);
      expect(find.byKey(const ValueKey('exit-hint')), findsNothing);
      await sayDebug(tester, 'It started the car');
      await tester.pump();
      expect(probe.hinted, [false]);
      expect(find.byKey(const ValueKey('talk-hint-chip')), findsNothing);
      await settleCard(tester);
    });

    // ПРАВИЛО (кадр 35-2 «Намерение»): в «Спроси сам» задание стоит СВЕТЛОЙ плашкой справа — тёмный
    // пузырь появляется только после того, как ты сказал.
    // ЛОВИТ: намерение, набранное тёмным пузырём, то есть выданное за речь ученика.
    testWidgets('«Спроси сам» — намерение светлой плашкой, тёмный пузырь только после речи', (tester) async {
      final probe = CardProbe()..verdict = (_) => const SessionJudgeOutcome(accepted: true, attempts: 1);
      final ask = day.stageOf(PlanStage.speak)!.cards.firstWhere((c) => (c.payload as SpeakAnswerPayload).partnerLine == null);
      await pumpCard(tester, probeEnv(ask, probe));
      expect(find.text('Спроси сам'), findsOneWidget);
      expect(find.byKey(const ValueKey('speak-ask-intent')), findsOneWidget);
      expect(find.byKey(const ValueKey('speak-own')), findsNothing, reason: 'сказать ещё нечего');

      await tester.pump(const Duration(milliseconds: 300));
      await sayDebug(tester, 'Do we need an X-ray?');
      await tester.pump();
      expect(find.byKey(const ValueKey('speak-own')), findsOneWidget);
      await settleCard(tester);
    });

    // ПРАВИЛО НАРЯДА — ОДНО НА ВСЕ ЭКРАНЫ: микрофон открывается ТОЛЬКО по тапу.
    // ЛОВИТ: карточку, которая включает запись по концу реплики собеседника (DECISIONS п. 299).
    testWidgets('микрофон не открывается сам', (tester) async {
      final probe = CardProbe();
      await pumpCard(tester, probeEnv(speakAt(2), probe));
      await tester.pump(const Duration(milliseconds: 300));
      await tester.pump(const Duration(seconds: 6));
      expect(probe.mics.single.state, MicState.idle);
      expect(find.byKey(const ValueKey('session-debug-heard')), findsOneWidget);
      await tester.tap(find.byKey(const ValueKey('talk-mic')));
      await tester.pump();
      expect(probe.mics.single.state, MicState.listening, reason: 'и открывается по тапу');
      await settleCard(tester);
    });
  });

  group('30-3 «Microphone needed» inside «Speak myself»', () {
    // CATCHES: «Listen and answer» named «ahead» after it was passed, the stage under way named «ahead» (live pass).
    testWidgets('the passed stage says «passed», the current one «in progress»', (tester) async {
      await pumpCard(tester, probeEnv(speakAt(1), CardProbe(), micAvailable: false, stageDone: (s) => s != PlanStage.speak));
      await tester.tap(find.byIcon(LucideIcons.mic).first);
      await tester.pump();
      await tester.pump(const Duration(seconds: 3));
      expect(find.text('Нужен микрофон'), findsOneWidget);
      expect(tester.widget<Text>(find.byKey(const ValueKey('no-mic-state-listen'))).data, 'пройден');
      expect(tester.widget<Text>(find.byKey(const ValueKey('no-mic-state-speak'))).data, 'идёт');
      await settleCard(tester);
    });
  });

  group('35-3 speak_echo', () {
    // CATCHES: the text shown before the answer, a microphone that records during the pause, a pass that leaves before
    // the revealed line is read.
    testWidgets('the line sounds, the pause ring holds the microphone, then the answer — the line opens, matched words in sage', (tester) async {
      final probe = CardProbe();
      final voice = QuietVoice();
      await pumpCard(tester, probeEnv(speakAt(7), probe, voice: voice));
      expect(find.text('Повтори через паузу'), findsOneWidget);
      expect(find.text('текст закрыт'), findsOneWidget);
      expect(find.text('слушай'), findsOneWidget);
      await tester.pump(const Duration(milliseconds: 300));
      await tester.pump();
      expect(voice.played, ['x5@1.0']);
      expect(find.byKey(const ValueKey('echo-pause-ring')), findsOneWidget);
      expect(find.text('жду'), findsOneWidget);
      expect(find.byKey(const ValueKey('session-debug-heard')), findsNothing, reason: 'the microphone is inactive in the pause');

      await tester.pump(const Duration(milliseconds: 3000));
      await tester.pump();
      expect(find.byKey(const ValueKey('echo-pause-ring')), findsNothing);
      expect(find.text('тап — говорить'), findsOneWidget);
      expect(find.text('It looks like a muscle strain, so he should rest and use a heating pad.'), findsNothing);

      // The line is on the screen, so it is said as it stands (`speech_mode: repeat`, FIX-2 §2): every content word,
      // in its order. «and use heat» would have passed the old 0.7 share and is two content words short of the line.
      await sayDebug(tester, 'It looks like a muscle strain so he should rest and use a heating pad');
      expect(results(probe), [SessionResult.passed]);
      await tester.pump(const Duration(milliseconds: 250));
      final text = tester.widget<SessionMarkedText>(find.byKey(const ValueKey('echo-text')));
      expect(text.text, 'It looks like a muscle strain, so he should rest and use a heating pad.');
      expect([for (final m in text.marks) text.text.substring(m.start, m.end)], ['It', 'looks', 'like', 'a', 'muscle', 'strain', 'so', 'he', 'should', 'rest', 'and', 'use', 'a', 'heating', 'pad']);
      // Under the eyebrow stands the line's own TRANSLATION. «совпавшее — шалфеем» is the canvas telling its reader
      // what the sage marks mean; it was on the card for a while, and it is not a sentence the learner is told.
      expect(find.text('Похоже на растяжение мышцы, так что ему нужен покой и грелка.'), findsOneWidget);
      expect(find.text('совпавшее — шалфеем'), findsNothing);
      await settleCard(tester);
      expect(probe.nexts, 0, reason: 'the revealed line waits for «Next»');
      await tapText(tester, 'Дальше');
      expect(probe.nexts, 1);
    });

    // RULE (наряд SESSION-1c, кадр 35-3): верная голосовая карточка уходит САМА через 600 мс — и 35-3 названное
    // исключение из этого правила: зачёт ОТКРЫВАЕТ реплику, и она ждёт «Дальше», иначе открытый текст мелькнёт и
    // пропадёт, а «Ещё раз» будет некуда нажать. «Дальше» здесь — не след промаха.
    // CATCHES: эхо, которое уехало по общему правилу и унесло с собой открытую реплику; «Дальше», появившаяся только
    // после промаха; и общее правило, отменённое ради этого исключения (соседняя карточка обязана уезжать сама).
    testWidgets('a pass leaves by itself after 600 ms — 35-3 is the exception: the opened line waits for «Дальше»', (tester) async {
      final echo = CardProbe();
      await pumpCard(tester, probeEnv(speakAt(7), echo));
      await tester.pump(const Duration(milliseconds: 3400));
      await sayDebug(tester, 'It looks like a muscle strain so he should rest and use a heating pad');
      expect(results(echo), [SessionResult.passed]);
      await tester.pump(const Duration(milliseconds: 600));
      await tester.pump();
      expect(echo.nexts, 0, reason: '35-3 holds: the line has just opened');
      expect(find.text('Дальше'), findsOneWidget);
      await settleCard(tester);

      // The rule it is an exception TO, on the neighbouring kind: a repeat passes and leaves on its own.
      final repeat = CardProbe();
      await pumpCard(tester, probeEnv(fixtureCard(day, SessionKind.phraseRepeat), repeat));
      final expected = (fixtureCard(day, SessionKind.phraseRepeat).payload as PhraseRepeatPayload).expectedText;
      await sayDebug(tester, expected);
      expect(results(repeat), [SessionResult.passed]);
      await tester.pump(const Duration(milliseconds: 600));
      await tester.pump();
      expect(repeat.nexts, 1, reason: 'the rule: a pass leaves by itself after 600 ms');
      expect(find.text('Дальше'), findsNothing);
      await settleCard(tester);
    });

    testWidgets('two misses — skipped, the line opens, «Next»', (tester) async {
      final probe = CardProbe();
      await pumpCard(tester, probeEnv(speakAt(7), probe));
      await tester.pump(const Duration(milliseconds: 300));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 3000));
      await tester.pump();
      await sayDebug(tester, 'heat');
      await sayDebug(tester, 'rest');
      expect(results(probe), [SessionResult.skipped]);
      expect(probe.answers.single.attempts, 2);
      await tester.pump(const Duration(milliseconds: 250));
      expect(find.byKey(const ValueKey('echo-text')), findsOneWidget);
      await tapText(tester, 'Дальше');
      expect(probe.nexts, 1);
      await settleCard(tester);
    });
  });

  group('35-4 speak_retell', () {
    // RULE (SESSION-2b §4, кадр 35-4, контракт BACK-TAILS-1 §1.1): the card says the LEARNER'S own line again —
    // it sounds, its English text is closed, the translation under the plate is the hint of the meaning, and the
    // pass is coverage on the phone: there is no judge on this card at all.
    // CATCHES: a question sent to the judge (the server answers 422 and the card hangs), the English line shown
    // before the attempt, and the partner's line taken instead of the learner's.
    testWidgets('the learner\'s own line sounds with its text closed; coverage passes it, no judge', (tester) async {
      final probe = CardProbe();
      final voice = QuietVoice();
      final card = speakAt(8);
      final p = card.payload as SpeakRetellPayload;
      expect(p.ownLine.textTarget, 'Do we need a follow-up appointment?');
      await pumpCard(tester, probeEnv(card, probe, voice: voice));
      expect(find.text('Повтори свою реплику'), findsOneWidget);
      expect(find.text('Нам нужно прийти на повторный приём?'), findsOneWidget, reason: 'the translation is the meaning');
      expect(find.text(p.ownLine.textTarget), findsNothing, reason: 'the English line is closed');
      expect(find.byKey(const ValueKey('retell-wave')), findsOneWidget);
      expect(probe.mics.single.localeId, 'en_US', reason: 'said in the target language');
      await tester.pump(const Duration(milliseconds: 300));
      expect(voice.played, ['x8b@1.0'], reason: 'the learner\'s own file');

      await sayDebug(tester, 'Do we need a follow up appointment');
      await tester.pump();
      expect(probe.judged, isEmpty, reason: 'the judge is not asked any more');
      expect(results(probe), [SessionResult.passed]);
      expect(
        tester.widget<SessionMarkedText>(find.byKey(const ValueKey('retell-text'))).text,
        p.ownLine.textTarget,
        reason: 'the line opens after the attempt, the heard words in sage',
      );
      expect(find.text('услышал'), findsOneWidget);
      await settleCard(tester);
      expect(probe.nexts, 0, reason: 'the opened line waits for «Next»');
      await tapText(tester, 'Дальше');
      expect(probe.nexts, 1);
    });

    // CATCHES: a miss counted as a pass, a card that keeps asking after the second attempt, the line left closed on
    // a skip (the learner never sees what they were saying).
    testWidgets('two misses — skipped, the line opens too', (tester) async {
      final probe = CardProbe();
      final card = speakAt(8);
      await pumpCard(tester, probeEnv(card, probe));
      await sayDebug(tester, 'hello');
      expect(probe.answers, isEmpty);
      expect(find.text('не расслышал, ещё раз'), findsOneWidget);
      await sayDebug(tester, 'nothing like it');
      expect(results(probe), [SessionResult.skipped]);
      expect(probe.answers.single.attempts, 2);
      expect(
        tester.widget<SessionMarkedText>(find.byKey(const ValueKey('retell-text'))).text,
        (card.payload as SpeakRetellPayload).ownLine.textTarget,
      );
      await tapText(tester, 'Дальше');
      expect(probe.nexts, 1);
      await settleCard(tester);
    });
  });

  // ПРАВИЛО (наряд CLIENT-CONV-1a, кадр 35-2): у своего пузыря нет ни перевода, ни оценки — в нём
  // стоит только сказанное. «По смыслу ✓» снято вместе со старым экраном: вердикт судьи виден тем,
  // что карточка уходит сама, а отказ — строкой судьи под пузырём.
  // CATCHES: возврат значка оценки в пузырь — и в проходе, и в повторе, где судьи нет вовсе.
  group('replay of a stage', () {
    testWidgets('свой пузырь — только сказанное: ни перевода, ни отметки оценки', (tester) async {
      for (final replay in [true, false]) {
        final probe = CardProbe()
          ..verdict = (_) => const SessionJudgeOutcome(accepted: true, slotValue: 'my back', result: SessionResult.passed, attempts: 1);
        await pumpCard(tester, probeEnv(speakAt(2), probe, replay: replay));
        await sayDebug(tester, 'It hurts in my back');
        await tester.pump();
        expect(find.text('по смыслу ✓'), findsNothing, reason: 'replay: $replay');
        expect(find.byKey(const ValueKey('speak-own')), findsOneWidget);
        expect(find.text('Началось три дня назад.'), findsNothing, reason: 'перевода под своей репликой нет');
        await settleCard(tester);
      }
    });

    testWidgets('the note «replay, not graded» is gone: a replay grades like an ordinary walk', (tester) async {
      await pumpCard(tester, probeEnv(speakAt(2), CardProbe(), replay: true));
      expect(find.text('повтор без оценки'), findsNothing);
      await settleCard(tester);
    });
  });
}
