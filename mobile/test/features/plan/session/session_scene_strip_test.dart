import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/plan/plan_models.dart';
import 'package:eng_std/data/plan/session/session_models.dart';
import 'package:eng_std/features/plan/session/parts/session_bits.dart' show SessionEyebrow;
import 'package:eng_std/features/plan/session/parts/session_chrome.dart';
import 'package:eng_std/features/plan/session/session_texts.dart';
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../../support/session_harness.dart';

/// THE ROLE IN THE SCENE STRIP (правка архитектора CLIENT-CONV-1a, 21.09): «Приём у врача · врач».
void main() {
  PlanScene scene(String role) => PlanScene(
    id: 'ulid-scene',
    order: 1,
    priority: 1,
    titleNative: 'Приём у врача',
    titleTarget: 'At the doctor',
    teachesNative: '',
    goalsNative: const [],
    lessonStatus: LessonStatus.unknown,
    partnerRoleNative: role,
  );

  Future<void> pumpStrip(WidgetTester tester, PlanScene s) => tester.pumpWidget(
    MaterialApp(
      theme: buildAppTheme(),
      locale: const Locale('ru'),
      localizationsDelegates: AppLocalizations.localizationsDelegates,
      supportedLocales: const [Locale('ru'), Locale('en')],
      home: Scaffold(body: SessionSceneStrip(scene: s)),
    ),
  );

  // ПРАВИЛО: в полосе сцены роль ПРОДОЛЖАЕТ строку — первая буква строчная: «Приём у врача · врач»
  // (кадры 30-2b, 37-x). Сервер шлёт роль с заглавной; клиент опускает только эту букву.
  // ЛОВИТ: «Приём у врача · Врач» — полоса на снимках и живых кадрах до правки.
  testWidgets('полоса сцены: роль со строчной', (tester) async {
    await pumpStrip(tester, scene('Врач'));
    expect(find.text('Приём у врача · врач'), findsOneWidget);
    expect(find.text('Приём у врача · Врач'), findsNothing);
  });

  // ПРАВИЛО: аббревиатура держит заглавные — буква опускается, только если за ней уже строчная.
  // ЛОВИТ: «лОР» вместо «ЛОР».
  testWidgets('аббревиатура в роли остаётся как есть', (tester) async {
    await pumpStrip(tester, scene('ЛОР'));
    expect(find.text('Приём у врача · ЛОР'), findsOneWidget);
    expect(SessionTexts.roleInline('ЛОР-врач'), 'ЛОР-врач');
    expect(SessionTexts.roleInline('Администратор'), 'администратор');
    expect(SessionTexts.roleInline('врач'), 'врач');
  });

  // ПРАВИЛО: заголовки и другие места НЕ трогаются — бровь, которая НАЧИНАЕТСЯ с роли, получает роль,
  // как прислал сервер: «Врач · спрашивает» (32-8; на экране бровь набрана капсом).
  // ЛОВИТ: правку, расползшуюся из полосы по всем местам, где печатается роль.
  testWidgets('бровь «… · спрашивает» — роль как прислал сервер', (tester) async {
    final day = sessionFixture('day-doctor');
    await pumpCard(tester, probeEnv(fixtureCard(day, SessionKind.phraseCombine), CardProbe(), role: 'Врач'));
    final brow = tester.widget<SessionEyebrow>(find.byWidgetPredicate((w) => w is SessionEyebrow && w.text.contains('спрашивает')));
    expect(brow.text, 'Врач · спрашивает');
    await settleCard(tester);
  });
}
