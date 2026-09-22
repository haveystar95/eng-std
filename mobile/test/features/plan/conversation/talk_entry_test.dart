import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/plan/conversation/conversation_models.dart';
import 'package:eng_std/data/plan/day_window.dart';
import 'package:eng_std/data/plan/plan_models.dart';
import 'package:eng_std/features/plan/conversation/talk_entry.dart';
import 'package:eng_std/features/plan/session/parts/session_bits.dart' show SessionDock, SessionSheet;
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../../support/nbsp.dart';
import '../../../support/plan_goldens.dart' show setUpPlanGoldens;
import '../../../support/server_fixtures.dart';
import '../../../support/session_harness.dart' show sessionFixture;

/// THE WAY INTO THE TALK (кадр 37-5 серии 38, наряд FIX-3 §3) — on a 390 × 844 phone with the real fonts: the screen
/// scrolls as one under a pinned dock, and «Скажи в разговоре» lists the day's CONSTRUCTIONS with the lesson's own
/// example grey under each.
void main() {
  setUpAll(setUpPlanGoldens);
  final day = sessionFixture('day-doctor');

  /// The talk row's `targets` as the server sends them on the day window before the day's first talk — `day-doctor.json`
  /// re-shot by the code of FIX-3: constructions with their windows, nothing said yet.
  final targets = DayWindow.fromJson(serverFixtureJson('day-doctor')['window'])
      .stages
      .firstWhere((s) => s.stage == PlanStage.conversation)
      .targets;

  Future<void> pumpEntry(
    WidgetTester tester,
    PlanScene? scene, {
    String? title = 'Поговори с врачом',
    List<TalkTarget> targets = const [],
    bool rehearsal = false,
    int? scenesCount,
    int minutes = 3,
    double height = 844,
  }) async {
    tester.view.physicalSize = Size(390, height) * 2;
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
              minutes: minutes,
              title: title,
              targets: targets,
              scenesCount: scenesCount,
              rehearsal: rehearsal,
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

  Finder toggleOf() => find.ancestor(of: find.byKey(const ValueKey('talk-entry-no-hints')), matching: find.byType(SessionSheet));

  /// The entry's one scroll — there is no window with a scroll of its own any more (наряд FIX-3 §3).
  ScrollPosition entryScroll(WidgetTester tester) => tester
      .state<ScrollableState>(find.descendant(of: find.byType(TalkEntryView), matching: find.byType(Scrollable)).first)
      .position;

  // ПРАВИЛО (кадр 37-5 серии 38, наряд FIX-3 §3, правка владельца 23.09): ЭКРАН ПРОКРУЧИВАЕТСЯ ЦЕЛИКОМ, а док —
  // «Без подсказок» и «Начать разговор» — прижат к низу: список конструкций уходит под него, а не в окно со своей
  // прокруткой. Полоса фото — кадровые 64 всегда. Семь конструкций достаются большим пальцем.
  // ЛОВИТ: окно со своей прокруткой (кадр до серии 38), список, обрезанный кромкой, и док, уехавший вместе с текстом.
  testWidgets('37-5: экран прокручивается целиком, док прижат к низу', (tester) async {
    await pumpEntry(tester, day.scene, targets: targets);
    final dock = tester.getRect(find.byType(SessionDock));
    expect(dock.bottom, moreOrLessEquals(844, epsilon: 0.5), reason: 'док у нижней кромки');
    expect(tester.getRect(toggleOf()).bottom, lessThanOrEqualTo(dock.bottom));
    expect(tester.getSize(find.byKey(const ValueKey('talk-entry-photo'))).height, 64, reason: 'полоса фото — 64 всегда');

    // One scroll for the whole screen, and the last construction is reached by it while the dock stays put.
    final scroll = entryScroll(tester);
    expect(scroll.maxScrollExtent, greaterThan(0), reason: 'семь конструкций длиннее экрана');
    final last = targets.last;
    await tester.scrollUntilVisible(
      find.byKey(ValueKey('talk-entry-target-${last.sceneId}-${last.ref}')),
      200,
      scrollable: find.descendant(of: find.byType(TalkEntryView), matching: find.byType(Scrollable)).first,
    );
    expect(tester.getRect(find.byType(SessionDock)), dock, reason: 'док не уехал');
    expect(find.byKey(const ValueKey('talk-entry-start')), findsOneWidget);
  });

  // ПРАВИЛО (кадр 37-5 серии 38, наряд FIX-3 §3): «Скажи в разговоре» — конструкции в порядке сервера: каркас
  // Literata 17 с окном (пустое `___` латунью — ученик ещё ничего не сказал), под ним через 2 серым 13 пример урока
  // на обоих языках одной строкой через «·»; между конструкциями 10, бровь — через 24 от правил и 14 над списком.
  // ЛОВИТ: фразы дня вместо конструкций, заполненное окно до разговора и пример, набранный как перевод.
  testWidgets('37-5: «Скажи в разговоре» — каркасы с окном и пример урока серым', (tester) async {
    await pumpEntry(tester, day.scene, targets: targets);
    final brow = find.text('СКАЖИ В РАЗГОВОРЕ');
    expect(brow, findsOneWidget);
    final rules = tester.getRect(find.byKey(const ValueKey('talk-entry-rule-counts')));
    expect(tester.getRect(brow).top - rules.bottom, moreOrLessEquals(24, epsilon: 1), reason: 'под правилами через 24');

    final tops = <double>[];
    for (final t in targets) {
      final row = find.byKey(ValueKey('talk-entry-target-${t.sceneId}-${t.ref}'));
      expect(row, findsOneWidget, reason: t.ref);
      await tester.scrollUntilVisible(row, 200,
          scrollable: find.descendant(of: find.byType(TalkEntryView), matching: find.byType(Scrollable)).first);
      expect(t.said, isFalse, reason: '${t.ref}: до разговора ничего не сказано');
      if (t.exampleTarget == null) {
        // A construction with no window is said as it is: the whole line, and the lesson has no value to show under it.
        expect(find.descendant(of: row, matching: find.text(t.frameTarget)), findsOneWidget, reason: '${t.ref}: каркас целиком');
        expect(find.descendant(of: row, matching: find.byType(Text)), findsOneWidget, reason: '${t.ref}: без примера');
        continue;
      }
      expect(find.descendant(of: row, matching: find.text(TalkTarget.window)), findsOneWidget, reason: '${t.ref}: окно пустое');
      final example = find.descendant(of: row, matching: find.text('${t.saidWith(t.exampleTarget)} · ${t.nativeWith(t.exampleNative)}'));
      expect(example, findsOneWidget, reason: '${t.ref}: пример урока одной строкой');
      expect(tester.widget<Text>(example).style, AppTextSession.meta);
      tops.add(tester.getRect(row).top);
    }

    await pumpEntry(tester, day.scene, targets: targets);
    final first = find.byKey(ValueKey('talk-entry-target-${targets[0].sceneId}-${targets[0].ref}'));
    final second = find.byKey(ValueKey('talk-entry-target-${targets[1].sceneId}-${targets[1].ref}'));
    expect(tester.getRect(brow).bottom + 14, moreOrLessEquals(tester.getRect(first).top, epsilon: 1), reason: '14 под бровью');
    expect(tester.getRect(second).top - tester.getRect(first).bottom, moreOrLessEquals(10, epsilon: 0.5));

    await pumpEntry(tester, day.scene);
    expect(find.text('СКАЖИ В РАЗГОВОРЕ'), findsNothing, reason: 'нет конструкций — нет блока');
  });

  // ПРАВИЛО: после «около» — родительный падеж; у строки свой plural, а не planMinutesCount.
  // ЛОВИТ: «около 3 минуты» — так вход 37-5 выглядел в живом прогоне CLIENT-CONV-1a.
  testWidgets('37-5: «около N минут» — родительный падеж', (tester) async {
    for (final (minutes, text) in [(1, 'около 1 минуты'), (3, 'около 3 минут'), (6, 'около 6 минут'), (21, 'около 21 минуты')]) {
      await pumpEntry(tester, day.scene, minutes: minutes);
      expect(tester.widget<Text>(find.byKey(const ValueKey('talk-entry-minutes'))).data, nb(text));
    }
  });

  // ПРАВИЛО: у сцены без фото полоса держит место и форму — бумажная плашка того же размера.
  // ЛОВИТ: схлопнутую полосу и серую заглушку, притворяющуюся фото.
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
    expect(tester.getSize(find.byKey(const ValueKey('talk-entry-photo'))).height, 64);
    expect(photoBox(tester).color, AppColors.paper);
    expect(photoBox(tester).image, isNull);
  });

  // ПРАВИЛО (кадр 37-5, §1.8 отчёта CLIENT-CONV-1a): у каждого из трёх правил — свой значок 20 слева (разговор,
  // «не понял» — вопрос в круге, «считается» — галка), текст правила — через 12 от значка.
  // ЛОВИТ: правила голым текстом — 37-5 до приёмки 1b.
  testWidgets('37-5: три правила со значками 20 слева', (tester) async {
    await pumpEntry(tester, day.scene);
    for (final rule in ['talk', 'rescue', 'counts']) {
      final row = find.byKey(ValueKey('talk-entry-rule-$rule'));
      expect(row, findsOneWidget, reason: rule);
      final icon = find.descendant(of: row, matching: find.byWidgetPredicate((w) => w.runtimeType.toString() == 'SvgPicture'));
      expect(icon, findsOneWidget, reason: '$rule: its icon');
      expect(tester.getSize(icon), const Size(20, 20));
      final text = find.descendant(of: row, matching: find.byType(Text));
      expect(tester.getRect(text).left - tester.getRect(icon).right, moreOrLessEquals(12, epsilon: 0.5));
    }
  });
}
