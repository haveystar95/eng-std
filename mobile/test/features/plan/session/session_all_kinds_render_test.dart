import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/plan/plan_models.dart';
import 'package:eng_std/data/plan/session/session_models.dart';

import '../../../support/plan_goldens.dart';
import '../../../support/session_harness.dart';

/// EVERY DEALT KIND HAS A SCREEN (work orders SESSION-1c §0 and §7, SESSION-2b): each of the 166 cards of both fixtures renders its
/// own widget on a phone-sized screen, at the fixture's level, with and without «No hints», without an exception; no
/// card falls back to an empty box. Between them the two fixtures deal all 28 kinds — the beginner's day has no
/// `phrase_other_slot` and no `phrase_own_slot` — and each deals all 13 of dialogue, listening and speaking. The app's
/// own fonts: the test font's square glyphs make a filler chip («a follow-up appointment») half as wide again as on
/// the phone.
void main() {
  setUpAll(setUpPlanGoldens);

  final conversation = {for (final k in SessionKind.values) if (const {PlanStage.dialogue, PlanStage.listen, PlanStage.speak}.contains(k.stage)) k};

  testWidgets('both fixtures: all 166 cards render; all 28 kinds between them, the 13 of the conversation in each', (tester) async {
    final all = <SessionKind>{};
    for (final name in ['day-doctor', 'day-doctor-beginner']) {
      final day = sessionFixture(name);
      final level = name.endsWith('beginner') ? PlanLevel.beginner : PlanLevel.intermediate;
      final kinds = <SessionKind>{};
      var cards = 0;
      for (final stage in day.stages) {
        for (final card in stage.cards) {
          for (final noHints in [false, true]) {
            await pumpCard(tester, probeEnv(card, CardProbe(), day: day, level: level, noHints: noHints), size: const Size(390, 844));
            expect(tester.takeException(), isNull, reason: '$name ${card.kind.wire} #${card.position}');
            expect(find.byWidgetPredicate((w) => w.runtimeType.toString().endsWith('Card')), findsOneWidget, reason: card.kind.wire);
            await tester.pump(const Duration(milliseconds: 400));
            expect(tester.takeException(), isNull, reason: '$name ${card.kind.wire} #${card.position} after its autoplay');
          }
          kinds.add(card.kind);
          cards++;
        }
      }
      expect(cards, 83, reason: name);
      expect(kinds.containsAll(conversation), isTrue, reason: '$name: ${conversation.difference(kinds)}');
      all.addAll(kinds);
    }
    expect(conversation, hasLength(13));
    expect(all, SessionKind.values.toSet());
    await settleCard(tester);
  });
}
