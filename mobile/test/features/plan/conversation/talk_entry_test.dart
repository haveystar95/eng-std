import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/plan/plan_models.dart';
import 'package:eng_std/features/plan/conversation/talk_entry.dart';
import 'package:eng_std/features/plan/session/parts/session_bits.dart' show SessionSheet;
import 'package:eng_std/features/plan/session/parts/session_chrome.dart' show SessionSceneStrip;
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../../support/plan_goldens.dart' show setUpPlanGoldens;
import '../../../support/session_harness.dart' show sessionFixture;

/// THE WAY INTO THE TALK (кадр 37-5, приёмка снимков CLIENT-CONV-1a) — on a 390 × 844 phone with the real fonts.
void main() {
  setUpAll(setUpPlanGoldens);
  final day = sessionFixture('day-doctor');

  Future<void> pumpEntry(WidgetTester tester, PlanScene? scene) async {
    tester.view.physicalSize = const Size(390, 844) * 2;
    tester.view.devicePixelRatio = 2;
    // The frame's phone has a 52 status bar over the screen, as a real one does.
    tester.view.padding = const FakeViewPadding(top: 52 * 2);
    addTearDown(tester.view.reset);
    await tester.pumpWidget(
      MaterialApp(
        theme: buildAppTheme(),
        locale: const Locale('ru'),
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        supportedLocales: const [Locale('ru'), Locale('en')],
        home: Scaffold(
          backgroundColor: AppColors.ground,
          body: SafeArea(
            bottom: false,
            child: TalkEntryView(
              scene: scene,
              minutes: 3,
              rehearsal: false,
              noHints: false,
              onNoHints: (_) {},
              onStart: () {},
              onBack: () {},
            ),
          ),
        ),
      ),
    );
    await tester.pump();
  }

  BoxDecoration photoBox(WidgetTester tester) => tester
      .widget<Container>(find.descendant(of: find.byKey(const ValueKey('talk-entry-photo')), matching: find.byType(Container)).first)
      .decoration! as BoxDecoration;

  // ПРАВИЛО (кадр 37-5): между полосой сцены и бровью — карточка с фото сцены, 170 в высоту; пустота между
  // переключателем «Без подсказок» и кнопкой уходит — она и была местом этой карточки.
  // ЛОВИТ: вход без фото с пустотой в треть экрана над «Начать разговор» — 37-5 до приёмки.
  testWidgets('37-5: фото сцены между полосой и бровью, пустоты над кнопкой нет', (tester) async {
    await pumpEntry(tester, day.scene);
    final photo = tester.getRect(find.byKey(const ValueKey('talk-entry-photo')));
    expect(photo.height, 170);
    expect(photo.top, greaterThanOrEqualTo(tester.getRect(find.byType(SessionSceneStrip)).bottom), reason: 'под полосой');
    final title = day.scene!.titleNative.toUpperCase();
    expect(photo.bottom, lessThanOrEqualTo(tester.getRect(find.textContaining(title)).top), reason: 'над бровью');
    expect(photoBox(tester).image, isNotNull, reason: 'фото сцены');
    final toggle = find.ancestor(of: find.byKey(const ValueKey('talk-entry-no-hints')), matching: find.byType(SessionSheet));
    final gap = tester.getRect(find.byKey(const ValueKey('talk-entry-start'))).top - tester.getRect(toggle).bottom;
    expect(gap, lessThanOrEqualTo(120), reason: 'между переключателем и кнопкой — воздух кадра (≈ 86), а не пустота');
  });

  // ПРАВИЛО: у сцены без фото карточка держит место и форму — бумажная плашка того же размера.
  // ЛОВИТ: схлопнутую карточку (и снова пустоту над кнопкой) и серую заглушку, притворяющуюся фото.
  testWidgets('37-5: без фото — бумажная плашка того же размера', (tester) async {
    const scene = PlanScene(
      id: 'ulid-scene',
      order: 1,
      priority: 1,
      titleNative: 'Приём у врача',
      titleTarget: 'At the doctor',
      teachesNative: '',
      goalsNative: [],
      lessonStatus: LessonStatus.unknown,
      partnerRoleNative: 'Врач',
    );
    await pumpEntry(tester, scene);
    expect(tester.getSize(find.byKey(const ValueKey('talk-entry-photo'))).height, 170);
    expect(photoBox(tester).color, AppColors.paper);
    expect(photoBox(tester).image, isNull);
  });
}
