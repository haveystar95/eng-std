import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/plan/session/session_models.dart';
import 'package:eng_std/data/plan/session/session_outcomes.dart';

import '../../../support/session_harness.dart';

/// «YOUR SLOT» (32-9) — SCREENSHOT FIX of SESSION-1b: the live recognition line runs UNDER the chip row and never
/// lies over it in any state — idle, a chosen chip, recording, pass, the judge's rejection; nothing overflows in any
/// state. Sizes — the card field under the header and the scene strip on an 844 pt phone and on the lowest phone
/// (iPhone SE, 667 pt).
void main() {
  final card = fixtureCard(sessionFixture('day-doctor'), SessionKind.phraseOwnSlot);
  const sizes = {'844 pt': Size(390, 674), 'SE 667 pt': Size(375, 497)};

  Rect rectOf(WidgetTester tester, String key) => tester.getRect(find.byKey(ValueKey(key)));

  /// The whole chip row is above [lowerKey], and nothing overflowed.
  void expectBelowChips(WidgetTester tester, String lowerKey) {
    expect(tester.takeException(), isNull, reason: 'no overflow');
    final chips = rectOf(tester, 'own-slot-chips');
    final lower = rectOf(tester, lowerKey);
    expect(chips.bottom, lessThanOrEqualTo(lower.top), reason: '«$lowerKey» is under the chip row, not over it');
    expect(chips.overlaps(lower), isFalse);
  }

  for (final entry in sizes.entries) {
    testWidgets('${entry.key}: idle → chip → recording → pass — the line stays under the chips, nothing overflows', (tester) async {
      final probe = CardProbe()
        ..verdict = (_) => const SessionJudgeOutcome(accepted: true, slotValue: 'last night', result: SessionResult.passed, attempts: 1);
      await pumpCard(tester, probeEnv(card, probe), size: entry.value);
      expect(tester.takeException(), isNull, reason: 'idle: no overflow');
      final idle = rectOf(tester, 'own-slot-chips');
      expect(find.text('тап — говорить'), findsNothing, reason: 'no caption under the chips — the task line says it (SESSION-2a §5)');
      expect(idle.bottom, lessThanOrEqualTo(tester.getRect(find.text('Пропустить')).top));

      await tester.tap(find.byKey(const ValueKey('chip-1')));
      await tester.pump();
      expect(tester.takeException(), isNull, reason: 'a chosen chip: no overflow');
      expect(rectOf(tester, 'own-slot-chips'), idle, reason: 'a chip in the slot does not move the row');

      // Recording: the line is still live (the «what was heard» field takes the recognizer's road). The zone under
      // the chips grows and lifts the row — the row stays on screen and above the line.
      await enterHeard(tester, 'It started last night when he came home');
      expect(find.byKey(const ValueKey('session-live-line')), findsOneWidget);
      expectBelowChips(tester, 'session-live-line');
      expect(rectOf(tester, 'own-slot-chips').top, greaterThanOrEqualTo(0), reason: 'the lifted row is still on screen');

      // The pause after the speech → the judge → pass: the «heard» line in sage.
      await tester.pump(const Duration(milliseconds: 1010));
      await tester.pump();
      expect(probe.judged, ['It started last night when he came home']);
      expect(find.byKey(const ValueKey('session-heard-line')), findsOneWidget);
      expectBelowChips(tester, 'session-heard-line');
      await settleCard(tester);
    });

    // CATCHES (simulator pass of SESSION-1b′): a voice zone reserved at its recording height — the chip row sat 80 pt
    // higher than idle needs and pushed the sheet's footer off an 844 pt screen.
    testWidgets('${entry.key}: idle — the chip row sits right above the idle voice zone', (tester) async {
      await pumpCard(tester, probeEnv(card, CardProbe()), size: entry.value);
      final chips = rectOf(tester, 'own-slot-chips');
      final skip = tester.getRect(find.text('Пропустить'));
      expect(skip.top - chips.bottom, lessThan(16 + 40), reason: 'no recording-height reserve between the chips and «Skip»');
      await settleCard(tester);
    });

    testWidgets('${entry.key}: the judge rejects — the reason and «Try again» under the chips; a long line wraps, no overflow', (tester) async {
      final probe = CardProbe()
        ..verdict = (_) => const SessionJudgeOutcome(accepted: false, reasonNative: 'Ты сказал не про время — назови, когда это началось.', attempts: 1);
      await pumpCard(tester, probeEnv(card, probe), size: entry.value);
      const long = 'It started a very long time ago when we were all still living in the old house by the river';
      await enterHeard(tester, long);
      expectBelowChips(tester, 'session-live-line');

      await tester.pump(const Duration(milliseconds: 1010));
      await tester.pump();
      expect(probe.judged, [long]);
      expect(find.byKey(const ValueKey('own-slot-reason')), findsOneWidget);
      expectBelowChips(tester, 'own-slot-reason');
      expect(tester.getRect(find.text('Ещё раз')).top, greaterThanOrEqualTo(rectOf(tester, 'own-slot-chips').bottom));
      await settleCard(tester);
    });
  }
}
