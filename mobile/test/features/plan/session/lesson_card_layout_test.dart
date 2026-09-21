import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/features/plan/session/parts/session_bits.dart' show SessionDockButton;

import '../../../support/plan_goldens.dart' show setUpPlanGoldens;
import '../../../support/session_harness.dart';

/// THE LESSON CARD 32-1, THIRD STATE, ON A REAL PHONE — measured with the real fonts (the test font draws every letter
/// an em wide and makes any sheet twice as tall as it is).
void main() {
  setUpAll(setUpPlanGoldens);
  final day = sessionFixture('day-doctor');
  final one = fixtureCardEdited('day-doctor', 'phrase_intro', (p) {
    final frame = p['frame'] as Map<String, dynamic>;
    final slot = frame['slot'] as Map<String, dynamic>;
    slot['fillers'] = [(slot['fillers'] as List).first];
  });

  double buttonTop(WidgetTester tester) => tester.getRect(find.byType(SessionDockButton)).top;

  // ПРАВИЛО (кадр 32-1, третье состояние, приёмка снимков): в кадре снимка 390 × 844 содержимое влезает над кнопкой —
  // лист, «В разговоре» и оба пузыря стоят целиком над «Дальше».
  // ЛОВИТ: пузырь «В разговоре», срезанный кнопкой (снимок 24 до приёмки: лист 288 + подвал съедал экран).
  testWidgets('32-1, третье состояние: в кадре снимка всё влезает над «Дальше»', (tester) async {
    await pumpCard(tester, probeEnv(one, CardProbe(), day: day), size: const Size(390, 844));
    final learner = tester.getRect(find.byKey(const ValueKey('in-talk-learner')));
    expect(learner.bottom, lessThanOrEqualTo(buttonTop(tester)), reason: 'последний пузырь целиком над кнопкой');
    expect(tester.takeException(), isNull);
    await settleCard(tester);
  });

  // ПРАВИЛО: то, что не влезло в поле карточки, ПРОКРУЧИВАЕТСЯ — и доходит до кнопки целиком, а не уходит под неё: на
  // телефоне владельца (поле 674 под шапкой и полосой) и на самом низком (SE).
  // ЛОВИТ: поле, которое не прокручивается, и пузырь, навсегда спрятанный под «Дальше».
  for (final (name, size) in [('844', const Size(390, 674)), ('SE 667', const Size(375, 497))]) {
    testWidgets('32-1, третье состояние, $name: прокручивается до последнего пузыря над «Дальше»', (tester) async {
      await pumpCard(tester, probeEnv(one, CardProbe(), day: day), size: size);
      final learner = find.byKey(const ValueKey('in-talk-learner'));
      await tester.dragUntilVisible(learner, find.byType(CustomScrollView).first, const Offset(0, -80));
      await tester.pump();
      expect(tester.getRect(learner).bottom, lessThanOrEqualTo(buttonTop(tester)), reason: 'прокрутился и стоит над кнопкой');
      expect(tester.takeException(), isNull);
      await settleCard(tester);
    });
  }
}
