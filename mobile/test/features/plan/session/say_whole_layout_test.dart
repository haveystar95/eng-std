import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/plan/session/session_models.dart';
import 'package:eng_std/data/plan/session/session_outcomes.dart';
import 'package:eng_std/features/plan/session/parts/session_tiles.dart';

import '../../../support/session_harness.dart';

/// «СКАЖИ ЦЕЛИКОМ» (32-7, наряд FIX-1 §6) — ПОЛЕ КАРТОЧКИ НА НИЗКОМ ТЕЛЕФОНЕ. Лист, ряд плашек-состояний и
/// микрофон стоят друг под другом и не наезжают ни в одном состоянии: круг значения, запись, зачёт, свой круг,
/// отказ судьи. Размеры — поле карточки под шапкой и полосой сцены на 844 pt и на самом низком телефоне (SE, 667).
///
/// Наследник `own_slot_layout_test.dart`: экран «своё окно» снесён вместе с отдельным тренажёром, а правило
/// «ничего не наезжает и ничего не срезано» осталось — оно и было тем, что нашли снимки SESSION-1b.
void main() {
  final day = sessionFixture('day-doctor');
  const sizes = {'844 pt': Size(390, 674), 'SE 667 pt': Size(375, 497)};

  Rect chipRow(WidgetTester tester) {
    final chips = find.byType(SessionTile);
    final rects = [for (final e in tester.elementList(chips)) tester.getRect(find.byElementPredicate((x) => identical(x, e)))];
    return rects.reduce((a, b) => a.expandToInclude(b));
  }

  for (final entry in sizes.entries) {
    testWidgets('${entry.key}: the round, the recording and the pass — nothing overflows, the chips stay on screen', (tester) async {
      final probe = CardProbe();
      await pumpCard(tester, probeEnv(fixtureCard(day, SessionKind.phraseOtherSlot), probe), size: entry.value);
      expect(tester.takeException(), isNull, reason: 'the round of a meaning: no overflow');
      final chips = chipRow(tester);
      expect(chips.top, greaterThanOrEqualTo(0), reason: 'the chip row is on screen');
      if (entry.key.startsWith('844')) {
        // On the owner's phone the whole card stands at once: sheet, meanings, microphone.
        expect(chips.bottom, lessThanOrEqualTo(tester.getRect(find.text('Пропустить')).top), reason: 'the microphone stands under the chips');
      }

      await enterHeard(tester, 'It hurts in his lower back and it has been like this all week');
      expect(find.byKey(const ValueKey('session-live-line')), findsOneWidget);
      expect(tester.takeException(), isNull, reason: 'recording: no overflow');
      expect(chipRow(tester).top, greaterThanOrEqualTo(0), reason: 'the row stays on screen while the line grows');

      await tester.pump(const Duration(milliseconds: 1010));
      await tester.pump();
      expect(tester.takeException(), isNull, reason: 'the pass: no overflow');
      await settleCard(tester);
    });

    testWidgets('${entry.key}: the own word and the judge\'s refusal — the reason fits, nothing overflows', (tester) async {
      final probe = CardProbe()
        ..verdict = (_) => const SessionJudgeOutcome(accepted: false, reasonNative: 'Ты сказал не про время — назови, когда это началось.', attempts: 1);
      await pumpCard(tester, probeEnv(fixtureCard(day, SessionKind.phraseOwnSlot), probe), size: entry.value);
      for (final said in ['It started three days ago', 'It started last night', 'It started this morning']) {
        await sayDebug(tester, said);
        await tester.pump();
        await tester.pump(const Duration(milliseconds: 700));
      }
      expect(find.text('а теперь со своим словом'), findsOneWidget);
      expect(tester.takeException(), isNull, reason: 'the own round: no overflow');

      const long = 'It started a very long time ago when we were all still living in the old house by the river';
      await enterHeard(tester, long);
      expect(tester.takeException(), isNull, reason: 'a long live line: no overflow');
      await tester.pump(const Duration(milliseconds: 1010));
      await tester.pump();
      expect(probe.judged, [long]);
      expect(find.text('Ты сказал не про время — назови, когда это началось.'), findsOneWidget);
      expect(tester.takeException(), isNull, reason: 'the judge\'s reason: no overflow');
      await settleCard(tester);
    });
  }
}
