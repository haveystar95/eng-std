import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/plan/conversation/conversation_models.dart';
import 'package:eng_std/data/plan/day_window.dart';
import 'package:eng_std/data/plan/plan_models.dart';
import 'package:eng_std/features/plan/conversation/talk_entry.dart';
import 'package:eng_std/features/plan/session/parts/session_bits.dart' show SessionDock, SessionSheet;
import 'package:eng_std/features/plan/session/parts/session_chrome.dart' show SessionSceneStrip;
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../../support/nbsp.dart';
import '../../../support/plan_goldens.dart' show setUpPlanGoldens;
import '../../../support/server_fixtures.dart';
import '../../../support/session_harness.dart' show sessionFixture;

/// THE WAY INTO THE TALK (кадр 37-5, SESSION-DES-4; наряд CLIENT-CONV-1c §3) — on a 390 × 844 phone with the real fonts.
void main() {
  setUpAll(setUpPlanGoldens);
  final day = sessionFixture('day-doctor');

  /// The talk row's `targets` as the server sends them on the day window before the day's first talk — `day-doctor.json`
  /// of BACK-TAILS-2 (six phrases, nothing said yet).
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

  /// The entry's own scroll — the outer one, not the phrases' window.
  ScrollPosition entryScroll(WidgetTester tester) => tester
      .state<ScrollableState>(find.descendant(of: find.byType(TalkEntryView), matching: find.byType(Scrollable)).first)
      .position;

  // ПРАВИЛО (кадр 37-5, SESSION-DES-4; приёмка снимков 22.09, второй и третий заходы): на 390 × 844 всё помещается —
  // переключатель «Без подсказок» целиком над доком, без прокрутки, и в окне «Скажи в разговоре» две фразы и третья,
  // подрезанная кромкой. Меры — кадра; чего экрану не хватает, отдаёт полоса фото: она стоит кадровыми 64 целиком, когда
  // место есть, и её нет совсем, когда нет, — никогда не сжимается. Полоса стоит между полосой сцены и бровью.
  // ЛОВИТ: переключатель под доком (заголовок в две строки на 390 — харнесс 01, 02), фото, съевшее третью фразу, и
  // полосу-щель уже 64.
  testWidgets('37-5: на 844 всё над доком без прокрутки — место отдаёт полоса фото', (tester) async {
    for (final (title, rehearsal) in [
      ('Поговори с врачом', false),
      ('Поговори с регистратором', false),
      ('Поговори с регистратором', true),
    ]) {
      await pumpEntry(tester, day.scene, title: title, targets: targets, rehearsal: rehearsal, scenesCount: rehearsal ? 2 : null);
      final dock = tester.getRect(find.byType(SessionDock));
      expect(tester.getRect(toggleOf()).bottom, lessThanOrEqualTo(dock.top), reason: '$title: переключатель над доком');
      expect(entryScroll(tester).maxScrollExtent, 0, reason: '$title: без прокрутки');

      final window = tester.getRect(find.byKey(const ValueKey('talk-entry-targets')));
      final third = tester.getRect(find.byKey(ValueKey('talk-entry-target-${targets[2].sceneId}-${targets[2].ref}')));
      expect(third.top + 23, lessThanOrEqualTo(window.bottom), reason: '$title: третья фраза видна своей строкой');
      expect(third.bottom, greaterThan(window.bottom), reason: '$title: и подрезана кромкой');

      final photo = find.byKey(const ValueKey('talk-entry-photo'));
      final band = tester.getSize(photo).height;
      expect(band == 0 || band == 64, isTrue, reason: '$title: полоса $band — 64 целиком или её нет');
      if (band > 0) {
        final rect = tester.getRect(photo);
        expect(rect.top, greaterThanOrEqualTo(tester.getRect(find.byType(SessionSceneStrip)).bottom), reason: 'под полосой сцены');
        expect(photoBox(tester).image, isNotNull, reason: 'фото сцены');
      }
    }

    // Nothing to fit — the frame's 64.
    await pumpEntry(tester, day.scene);
    expect(tester.getSize(find.byKey(const ValueKey('talk-entry-photo'))).height, 64);
  });

  // ПРАВИЛО (приёмка 22.09, третий заход): полоса фото — 64 целиком или её нет, на любой высоте экрана; вернулась —
  // значит, всё по-прежнему над доком без прокрутки.
  // ЛОВИТ: полосу 24…63, которой уступка места съела низ фото.
  testWidgets('37-5: полоса фото — 64 или нет, на любой высоте', (tester) async {
    for (final title in ['Поговори с врачом', 'Поговори с регистратором']) {
      final seen = <double>{};
      for (var height = 844.0; height <= 960; height += 4) {
        await pumpEntry(tester, day.scene, title: title, targets: targets, height: height);
        final band = tester.getSize(find.byKey(const ValueKey('talk-entry-photo'))).height;
        seen.add(band);
        expect(band == 0 || band == 64, isTrue, reason: '$title на $height: полоса $band');
        expect(tester.getRect(toggleOf()).bottom, lessThanOrEqualTo(tester.getRect(find.byType(SessionDock)).top), reason: '$title на $height');
        expect(entryScroll(tester).maxScrollExtent, 0, reason: '$title на $height: без прокрутки');
      }
      expect(seen, {0.0, 64.0}, reason: '$title: на 844 полосы нет, на высоком экране она встаёт целиком');
    }
  });

  // ПРАВИЛО: экрану, где и одной подрезанной фразы не поместить (крупный шрифт), остаётся прокрутка — ничего не
  // уходит под док безвозвратно.
  // ЛОВИТ: раскладку, которая режет переключатель вместо прокрутки.
  testWidgets('37-5: крупный шрифт — экран прокручивается к переключателю', (tester) async {
    tester.platformDispatcher.textScaleFactorTestValue = 1.6;
    addTearDown(tester.platformDispatcher.clearTextScaleFactorTestValue);
    await pumpEntry(tester, day.scene, title: 'Поговори с регистратором', targets: targets);
    expect(entryScroll(tester).maxScrollExtent, greaterThan(0));
    await tester.scrollUntilVisible(find.byKey(const ValueKey('talk-entry-no-hints')), 200,
        scrollable: find.descendant(of: find.byType(TalkEntryView), matching: find.byType(Scrollable)).first);
    expect(tester.getRect(toggleOf()).bottom, lessThanOrEqualTo(tester.getRect(find.byType(SessionDock)).top));
  });

  // ПРАВИЛО (наряд CLIENT-CONV-1c §2а, CONV-2 п. 12): заголовок входа — строка сервера `talk_title_native` («Поговори
  // с врачом») как есть; своей строки у клиента нет («Поговори с собеседником» снесена) — нет поля, нет заголовка.
  // ЛОВИТ: «Поговори с собеседником» при склонённой роли сервера и склонение на телефоне.
  testWidgets('37-5: заголовок — строка сервера; без неё заголовка нет', (tester) async {
    await pumpEntry(tester, day.scene);
    expect(tester.widget<Text>(find.byKey(const ValueKey('talk-entry-title'))).data, 'Поговори с врачом');
    expect(find.text('Поговори с собеседником'), findsNothing);

    await pumpEntry(tester, day.scene, title: null);
    expect(find.byKey(const ValueKey('talk-entry-title')), findsNothing);
    expect(find.text('Поговори с собеседником'), findsNothing);
    expect(find.byKey(const ValueKey('talk-entry-minutes')), findsOneWidget, reason: 'остальное на месте');
  });

  // ПРАВИЛО (кадр 37-5 «репетиция», CONV-2 п. 12): бровь репетиции — «Разговор целиком · 3 сцены», число сцен —
  // `scenes_count` ряда разговора; без него — «Разговор целиком» без числа.
  // ЛОВИТ: число сцен, посчитанное на телефоне, и бровь без числа при числе сервера.
  testWidgets('37-5 репетиция: «Разговор целиком · 3 сцены» — число сервера', (tester) async {
    await pumpEntry(tester, day.scene, rehearsal: true, scenesCount: 3, minutes: 6);
    expect(find.text(nb('РАЗГОВОР ЦЕЛИКОМ · 3 СЦЕНЫ')), findsOneWidget);
    await pumpEntry(tester, day.scene, rehearsal: true, scenesCount: 5, minutes: 6);
    expect(find.text(nb('РАЗГОВОР ЦЕЛИКОМ · 5 СЦЕН')), findsOneWidget);
    await pumpEntry(tester, day.scene, rehearsal: true, minutes: 6);
    expect(find.text('РАЗГОВОР ЦЕЛИКОМ'), findsOneWidget);
  });

  // ПРАВИЛО (кадр 37-5 «Скажи в разговоре», SESSION-DES-4; архитектор 22.09 — `targets` ряда разговора окна; приёмка
  // снимков 22.09 — «третья фраза видна подрезанной кромкой»): между правилами и «Без подсказок» — бровь «Скажи в
  // разговоре» через 24 и под ней, через 14, фразы разговора в порядке сервера: фраза Literata 17/23 чернилами, перевод
  // 15/20 серым второй строкой через 2, между фразами 10. Список — окно со своей прокруткой: две фразы и третья, край
  // режет её перевод, нижний край тает на 28 — своя строка третьей фразы читается. Список из двух фраз стоит целиком,
  // без тающего края. Без фраз — блока нет.
  // ЛОВИТ: «две фразы и пустота» (край в зазоре между фразами, затухание съело третью), фразы дня вместо целей
  // разговора, чужой порядок, список во весь рост, вытолкнувший переключатель под кнопку, и пустую бровь без фраз.
  testWidgets('37-5: «Скажи в разговоре» — две фразы и третья под кромкой, край тает', (tester) async {
    await pumpEntry(tester, day.scene, targets: targets);
    final brow = find.text('СКАЖИ В РАЗГОВОРЕ');
    expect(brow, findsOneWidget);
    final window = find.byKey(const ValueKey('talk-entry-targets'));
    final third = tester.getRect(find.byKey(ValueKey('talk-entry-target-${targets[2].sceneId}-${targets[2].ref}')));
    final thirdLine = tester.getRect(find.text(targets[2].textTarget));
    final thirdNative = tester.getRect(find.text(targets[2].textNative));
    expect(thirdLine.bottom, lessThanOrEqualTo(tester.getRect(window).bottom), reason: 'своя строка третьей фразы — в окне');
    expect(tester.getRect(window).bottom, moreOrLessEquals(thirdNative.top + 10, epsilon: 1), reason: 'край — в её переводе');
    expect(third.bottom, greaterThan(tester.getRect(window).bottom));
    expect(tester.getRect(window).top - tester.getRect(brow).bottom, moreOrLessEquals(14, epsilon: 1));
    final rules = tester.getRect(find.byKey(const ValueKey('talk-entry-rule-counts')));
    expect(tester.getRect(brow).top - rules.bottom, moreOrLessEquals(24, epsilon: 1), reason: 'под правилами через 24');
    final toggle = find.ancestor(of: find.byKey(const ValueKey('talk-entry-no-hints')), matching: find.byType(SessionSheet));
    expect(tester.getRect(toggle).top - tester.getRect(window).bottom, moreOrLessEquals(24, epsilon: 1), reason: 'над переключателем');

    final tops = <double>[];
    for (final t in targets) {
      final row = find.byKey(ValueKey('talk-entry-target-${t.sceneId}-${t.ref}'));
      expect(row, findsOneWidget, reason: t.ref);
      final phrase = tester.widget<Text>(find.descendant(of: row, matching: find.text(t.textTarget)));
      final native = tester.widget<Text>(find.descendant(of: row, matching: find.text(t.textNative)));
      expect(phrase.style, AppTextSession.phrase17);
      expect(native.style, AppTextSession.body);
      tops.add(tester.getRect(row).top);
    }
    expect([...tops]..sort(), tops, reason: 'в порядке сервера');
    final first = find.byKey(ValueKey('talk-entry-target-${targets[0].sceneId}-p1'));
    final second = find.byKey(ValueKey('talk-entry-target-${targets[1].sceneId}-p2'));
    expect(tester.getRect(second).top - tester.getRect(first).bottom, moreOrLessEquals(10, epsilon: 0.5));
    expect(tester.getRect(find.byKey(ValueKey('talk-entry-target-${targets[4].sceneId}-p5'))).top, greaterThan(tester.getRect(window).bottom),
        reason: 'нижние фразы — за кромкой окна');
    final fade = find.descendant(of: window, matching: find.byType(DecoratedBox)).last;
    expect(tester.getSize(fade).height, 28);

    // The window scrolls on its own: the last phrase comes up into it.
    await tester.drag(window, const Offset(0, -300));
    await tester.pump();
    final last = tester.getRect(find.byKey(ValueKey('talk-entry-target-${targets.last.sceneId}-${targets.last.ref}')));
    expect(last.bottom, lessThanOrEqualTo(tester.getRect(window).bottom), reason: 'последняя фраза доступна прокруткой');
    expect(tester.getRect(toggle).top - tester.getRect(window).bottom, moreOrLessEquals(24, epsilon: 1), reason: 'экран не уехал');

    // Two phrases stand whole: nothing past the edge, no fade.
    await pumpEntry(tester, day.scene, targets: targets.take(2).toList());
    final pair = find.byKey(const ValueKey('talk-entry-targets'));
    final lastOfTwo = tester.getRect(find.byKey(ValueKey('talk-entry-target-${targets[1].sceneId}-${targets[1].ref}')));
    expect(lastOfTwo.bottom, moreOrLessEquals(tester.getRect(pair).bottom, epsilon: 0.5), reason: 'две фразы — целиком');
    expect(find.descendant(of: pair, matching: find.byType(DecoratedBox)), findsNothing, reason: 'без тающего края');

    await pumpEntry(tester, day.scene);
    expect(find.text('СКАЖИ В РАЗГОВОРЕ'), findsNothing, reason: 'нет фраз — нет блока');
    expect(find.byKey(const ValueKey('talk-entry-targets')), findsNothing);
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
