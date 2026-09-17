import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/data/plan/plan_models.dart';
import 'package:eng_std/data/plan/session/session_models.dart';
import 'package:eng_std/data/plan/session/session_outcomes.dart';
import 'package:eng_std/features/plan/session/parts/session_bits.dart';
import 'package:eng_std/features/plan/session/parts/session_bubbles.dart';

import '../../../support/session_harness.dart';

/// SPEAK MYSELF (work order SESSION-1c §4, canvas series 35): the answer judged by meaning with its frame hint (by
/// silence or by «Hint», `hinted` to the judge, none under «No hints»), the three exits of a rejection, the echo after
/// its pause, the retelling in the native language.
void main() {
  final day = sessionFixture('day-doctor');

  SessionCard speakAt(int position) => day.stageOf(PlanStage.speak)!.cards.firstWhere((c) => c.position == position);
  List<SessionResult> results(CardProbe probe) => [for (final a in probe.answers) a.result];
  const rejected = SessionJudgeOutcome(accepted: false, reasonNative: 'Ты сказал не про время.', attempts: 1);

  group('35-2 · 35-5 speak_answer', () {
    // CATCHES: a hint that never comes, `hinted` false after the hint was on screen (the server would write passed),
    // a judged pass the client writes itself.
    testWidgets('the partner sounds; 5 s of silence — the frame hint; the judge gets hinted = true; accepted — by meaning, auto-advance', (tester) async {
      final probe = CardProbe()
        ..verdict = (_) => const SessionJudgeOutcome(accepted: true, slotValue: 'last night', result: SessionResult.hinted, attempts: 1);
      final voice = QuietVoice();
      await pumpCard(tester, probeEnv(speakAt(2), probe, voice: voice));
      expect(find.text('Ответь своими словами'), findsOneWidget);
      expect(find.text('Did it start today, or earlier this week?'), findsOneWidget);
      expect(find.text('Началось три дня назад.'), findsOneWidget, reason: 'the task in the own bubble');
      await tester.pump(const Duration(milliseconds: 300));
      expect(voice.played, ['x2@1.0']);
      expect(find.byKey(const ValueKey('speak-hint')), findsNothing);
      await tester.pump(const Duration(seconds: 5));
      await tester.pump();
      expect(find.byKey(const ValueKey('speak-hint')), findsOneWidget);
      expect(find.text('подскажу каркас — окно твоё'), findsOneWidget);

      await enterHeard(tester, 'It started last night');
      await tester.pump(const Duration(milliseconds: 1010));
      await tester.pump();
      expect(probe.judged, ['It started last night']);
      expect(probe.hinted, [true]);
      expect(probe.answers, isEmpty, reason: 'the server records a judged pass');
      expect(find.text('по смыслу ✓'), findsOneWidget);
      expect(find.byKey(const ValueKey('bubble-mark-passed')), findsOneWidget);
      final frame = tester.widget<SessionFrameText>(find.descendant(of: find.byType(SessionOwnRow), matching: find.byType(SessionFrameText)));
      expect(frame.slot, 'last night');
      expect(frame.look, SlotLook.sage);
      await settleCard(tester);
      expect(probe.nexts, 1);
    });

    // CATCHES: a rejection without its exits, «Hint» that does not open the frame or does not mark `hinted`, «Skip»
    // that writes anything but skipped.
    testWidgets('rejected — the reason and three exits; «Hint» — the frame, two exits, hinted on the next attempt; «Skip» — skipped', (tester) async {
      final probe = CardProbe()..verdict = (_) => rejected;
      await pumpCard(tester, probeEnv(speakAt(2), probe));
      await tester.pump(const Duration(milliseconds: 300));
      await sayDebug(tester, 'It started the car');
      await tester.pump();
      expect(probe.hinted, [false]);
      expect(find.text('Ты сказал не про время.'), findsOneWidget);
      expect(find.byKey(const ValueKey('exit-again')), findsOneWidget);
      expect(find.byKey(const ValueKey('exit-hint')), findsOneWidget);
      expect(find.byKey(const ValueKey('exit-skip')), findsOneWidget);
      expect(find.byKey(const ValueKey('bubble-live-line')), findsOneWidget, reason: 'what was said stays in the bubble, faded');

      await tester.tap(find.byKey(const ValueKey('exit-hint')));
      await tester.pump();
      expect(find.text('каркас открыт — окно твоё'), findsOneWidget);
      expect(find.byKey(const ValueKey('exit-hint')), findsNothing, reason: 'after the hint — two exits');

      await tester.tap(find.byKey(const ValueKey('exit-again')));
      await tester.pump();
      await sayDebug(tester, 'It started this morning');
      await tester.pump();
      expect(probe.hinted, [false, true]);

      await tester.tap(find.byKey(const ValueKey('exit-skip')));
      await tester.pump();
      expect(results(probe), [SessionResult.skipped]);
      expect(probe.answers.single.response?.hintedAt, 'button');
      expect(probe.nexts, 1);
      await settleCard(tester);
    });

    // CATCHES: «No hints» that still raises the frame by silence or offers «Hint».
    testWidgets('«No hints» — no hint by silence and no «Hint»', (tester) async {
      final probe = CardProbe()..verdict = (_) => rejected;
      await pumpCard(tester, probeEnv(speakAt(2), probe, noHints: true));
      await tester.pump(const Duration(milliseconds: 300));
      await tester.pump(const Duration(seconds: 6));
      expect(find.byKey(const ValueKey('speak-hint')), findsNothing);
      await sayDebug(tester, 'It started the car');
      await tester.pump();
      expect(probe.hinted, [false]);
      expect(find.byKey(const ValueKey('exit-again')), findsOneWidget);
      expect(find.byKey(const ValueKey('exit-hint')), findsNothing);
      expect(find.byKey(const ValueKey('speak-hint')), findsNothing);
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

      // 11 of the 13 words that count (the articles are forgiven) — over the card's 0.7.
      await sayDebug(tester, 'It looks like a muscle strain so he should rest and use heat');
      expect(results(probe), [SessionResult.passed]);
      await tester.pump(const Duration(milliseconds: 250));
      final text = tester.widget<SessionMarkedText>(find.byKey(const ValueKey('echo-text')));
      expect(text.text, 'It looks like a muscle strain, so he should rest and use a heating pad.');
      expect([for (final m in text.marks) text.text.substring(m.start, m.end)], ['It', 'looks', 'like', 'a', 'muscle', 'strain', 'so', 'he', 'should', 'rest', 'and', 'use']);
      expect(find.text('совпавшее — шалфеем'), findsOneWidget);
      await settleCard(tester);
      expect(probe.nexts, 0, reason: 'the revealed line waits for «Next»');
      await tapText(tester, 'Дальше');
      expect(probe.nexts, 1);
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
    // CATCHES: a retelling recognized with the target language's hint words, a judge told the frame was hinted, the
    // reveal before the verdict.
    testWidgets('the native locale, no hint words; the judge with hinted = false; accepted — the sheet opens, «understood ✓»', (tester) async {
      final completer = Completer<void>();
      final probe = CardProbe()
        ..judgeGate = completer
        ..verdict = (_) => const SessionJudgeOutcome(accepted: true, result: SessionResult.passed, attempts: 1);
      final voice = QuietVoice();
      await pumpCard(tester, probeEnv(speakAt(8), probe, voice: voice, localeId: 'ru_RU'));
      expect(find.text('Скажи по-русски, что услышал'), findsOneWidget);
      expect(find.text('на родном'), findsOneWidget);
      expect(probe.mics.single.localeId, 'ru_RU');
      expect(probe.mics.single.contextualStrings, isEmpty);
      expect(probe.mics.single.expected, isEmpty);
      await tester.pump(const Duration(milliseconds: 300));
      expect(voice.played, ['x1@1.0']);

      await enterHeard(tester, 'Где болит вверху или в пояснице');
      await tester.pump(const Duration(milliseconds: 1010));
      await tester.pump();
      expect(probe.judged, ['Где болит вверху или в пояснице']);
      expect(probe.hinted, [false]);
      expect(find.byKey(const ValueKey('retell-text')), findsNothing, reason: 'nothing opens while the judge thinks');
      completer.complete();
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 250));
      expect(find.text('Where does it hurt: his upper back or his lower back?'), findsOneWidget);
      expect(find.text('Где болит: вверху спины или в пояснице?'), findsOneWidget);
      expect(find.text('понял ✓'), findsOneWidget);
      expect(probe.answers, isEmpty);
      await settleCard(tester);
      expect(probe.nexts, 0);
      await tapText(tester, 'Дальше');
      expect(probe.nexts, 1);
    });

    testWidgets('rejected — the reason, «Try again» / «Skip» → skipped', (tester) async {
      final probe = CardProbe()..verdict = (_) => const SessionJudgeOutcome(accepted: false, reasonNative: 'Смысл другой.', attempts: 1);
      await pumpCard(tester, probeEnv(speakAt(8), probe, localeId: 'ru_RU'));
      await sayDebug(tester, 'Когда можно вернуться на работу');
      await tester.pump();
      expect(find.text('Смысл другой.'), findsOneWidget);
      expect(find.byKey(const ValueKey('exit-again')), findsOneWidget);
      expect(find.byKey(const ValueKey('exit-hint')), findsNothing);
      await tester.tap(find.byKey(const ValueKey('exit-skip')));
      await tester.pump();
      expect(results(probe), [SessionResult.skipped]);
      await settleCard(tester);
    });
  });
}
