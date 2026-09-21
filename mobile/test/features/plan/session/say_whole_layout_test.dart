import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/plan/session/session_models.dart';
import 'package:eng_std/data/plan/session/session_outcomes.dart';
import 'package:eng_std/features/plan/session/parts/session_tiles.dart';
import 'package:eng_std/theme/theme.dart';

import '../../../support/plan_goldens.dart' show setUpPlanGoldens;
import '../../../support/session_harness.dart';

/// «СКАЖИ ЦЕЛИКОМ» (32-7, наряд FIX-1 §6) — ПОЛЕ КАРТОЧКИ НА НИЗКОМ ТЕЛЕФОНЕ. Лист, ряд плашек-состояний и
/// микрофон стоят друг под другом и не наезжают ни в одном состоянии: круг значения, запись, зачёт, свой круг,
/// отказ судьи. Размеры — поле карточки под шапкой и полосой сцены на 844 pt и на самом низком телефоне (SE, 667).
///
/// Наследник `own_slot_layout_test.dart`: экран «своё окно» снесён вместе с отдельным тренажёром, а правило
/// «ничего не наезжает и ничего не срезано» осталось — оно и было тем, что нашли снимки SESSION-1b.
///
/// МЕРИТСЯ НАСТОЯЩИМИ ШРИФТАМИ (Literata, Inter — как в снимках): тестовый шрифт рисует каждую букву в кегль шириной,
/// и лист по кадру 32-7 (бровь, каркас, чтение, перевод, «прослушать») на нём выходил вдвое выше, чем на телефоне.
void main() {
  setUpAll(setUpPlanGoldens);
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
      final card = fixtureCard(day, SessionKind.phraseOtherSlot);
      await pumpCard(tester, probeEnv(card, probe), size: entry.value);
      // Through every value round the stage's ceiling left the card (DECISIONS п. 354) to the last one — the own
      // word (FIX-2 §5).
      for (final round in (card.payload as PhraseOtherSlotPayload).rounds) {
        await sayDebug(tester, round.expectedText);
        await tester.pump();
        await tester.pump(const Duration(milliseconds: 700));
      }
      expect(find.text('а теперь со своим словом'), findsOneWidget);
      expect(tester.takeException(), isNull, reason: 'the own round: no overflow');

      const long = 'It hurts in a very long place we have been talking about since the old house by the river';
      await enterHeard(tester, long);
      expect(tester.takeException(), isNull, reason: 'a long live line: no overflow');
      // «his» of the frame is not in it: the recording waits the longer pause (CLIENT-CONV-1b, silence by length).
      await tester.pump(const Duration(milliseconds: 2010));
      await tester.pump();
      expect(probe.judged, [long]);
      expect(find.text('Ты сказал не про время — назови, когда это началось.'), findsOneWidget);
      // ПРАВИЛО (правка прохода 21.09, наряд CLIENT-CONV-1b): под отказом — «услышал: …» с тем, что распознал телефон;
      // «Пропустить» на 32-7 — латунная ссылка (§1.8 отчёта 1a).
      expect(find.text('услышал: $long'), findsOneWidget, reason: 'the heard line under the refusal');
      final heard = tester.getRect(find.byKey(const ValueKey('session-heard-text')));
      expect(heard.top, greaterThanOrEqualTo(tester.getRect(find.text('Ты сказал не про время — назови, когда это началось.')).bottom));
      expect(tester.widget<Text>(find.byKey(const ValueKey('session-skip'))).style!.color, AppColors.brassInk);
      expect(tester.takeException(), isNull, reason: 'the judge\'s reason: no overflow');
      await settleCard(tester);
    });
  }

  // RULE (наряд CLIENT-CONV-1c §2г, CONV-2 п. 8): «услышал: …» under the refusal of the own word is what the JUDGE
  // judged — `heard` of its answer; the phone's own recognition stands there only when the answer carries none (the
  // test above).
  // CATCHES: the line printing the phone's recognition when the judge read something else — a refusal over a misheard
  // word that reads as the learner's mistake.
  testWidgets('32-7: «услышал» under the refusal is the judge\'s `heard`', (tester) async {
    final probe = CardProbe()
      ..verdict = (_) => const SessionJudgeOutcome(
        accepted: false,
        reasonNative: 'Каркас не прозвучал.',
        attempts: 1,
        heard: 'it hurts in hiss elbow',
      );
    final card = fixtureCard(day, SessionKind.phraseOtherSlot);
    await pumpCard(tester, probeEnv(card, probe), size: sizes['844 pt']!);
    for (final round in (card.payload as PhraseOtherSlotPayload).rounds) {
      await sayDebug(tester, round.expectedText);
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 700));
    }
    await sayDebug(tester, 'It hurts in his elbow');
    await tester.pump();
    expect(probe.judged, ['It hurts in his elbow']);
    expect(find.text('Каркас не прозвучал.'), findsOneWidget);
    expect(tester.widget<Text>(find.byKey(const ValueKey('session-heard-text'))).data, 'услышал: it hurts in hiss elbow');
    await settleCard(tester);
  });
}
