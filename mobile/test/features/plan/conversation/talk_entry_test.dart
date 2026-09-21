import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/plan/conversation/conversation_models.dart';
import 'package:eng_std/data/plan/plan_models.dart';
import 'package:eng_std/features/plan/conversation/talk_entry.dart';
import 'package:eng_std/features/plan/session/parts/session_bits.dart' show SessionSheet;
import 'package:eng_std/features/plan/session/parts/session_chrome.dart' show SessionSceneStrip;
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../../support/plan_goldens.dart' show setUpPlanGoldens;
import '../../../support/session_harness.dart' show sessionFixture;
import '../../../support/talk_harness.dart' show talkV2;

/// THE WAY INTO THE TALK (кадр 37-5, SESSION-DES-4; наряд CLIENT-CONV-1c §3) — on a 390 × 844 phone with the real fonts.
void main() {
  setUpAll(setUpPlanGoldens);
  final day = sessionFixture('day-doctor');

  /// The talk row's `targets` as the window sends them — the talk's own list, before its first move nothing said.
  final targets = [for (final t in talkV2('talk_day_open_v2').targets) TalkTarget(sceneId: t.sceneId, ref: t.ref, textTarget: t.textTarget, textNative: t.textNative, said: false)];

  Future<void> pumpEntry(
    WidgetTester tester,
    PlanScene? scene, {
    String? title = 'Поговори с врачом',
    List<TalkTarget> targets = const [],
    bool rehearsal = false,
    int? scenesCount,
    int minutes = 3,
  }) async {
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

  // ПРАВИЛО (кадр 37-5, SESSION-DES-4): между полосой сцены и бровью — полоса фото сцены, 64 в высоту (была 170, пока
  // на входе не встали фразы); переключатель «Без подсказок» и кнопка — у низа экрана, без пустоты между ними.
  // ЛОВИТ: фото 170, выталкивающее «Скажи в разговоре» и переключатель под кнопку.
  testWidgets('37-5: полоса фото 64 между полосой сцены и бровью', (tester) async {
    await pumpEntry(tester, day.scene, targets: targets);
    final photo = tester.getRect(find.byKey(const ValueKey('talk-entry-photo')));
    expect(photo.height, 64);
    expect(photo.top, greaterThanOrEqualTo(tester.getRect(find.byType(SessionSceneStrip)).bottom), reason: 'под полосой');
    final title = day.scene!.titleNative.toUpperCase();
    expect(photo.bottom, lessThanOrEqualTo(tester.getRect(find.textContaining(title)).top), reason: 'над бровью');
    expect(photoBox(tester).image, isNotNull, reason: 'фото сцены');
    final toggle = find.ancestor(of: find.byKey(const ValueKey('talk-entry-no-hints')), matching: find.byType(SessionSheet));
    final gap = tester.getRect(find.byKey(const ValueKey('talk-entry-start'))).top - tester.getRect(toggle).bottom;
    expect(gap, lessThanOrEqualTo(40), reason: 'переключатель над кнопкой, как в кадре');
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
    expect(find.text('РАЗГОВОР ЦЕЛИКОМ · 3 СЦЕНЫ'), findsOneWidget);
    await pumpEntry(tester, day.scene, rehearsal: true, scenesCount: 5, minutes: 6);
    expect(find.text('РАЗГОВОР ЦЕЛИКОМ · 5 СЦЕН'), findsOneWidget);
    await pumpEntry(tester, day.scene, rehearsal: true, minutes: 6);
    expect(find.text('РАЗГОВОР ЦЕЛИКОМ'), findsOneWidget);
  });

  // ПРАВИЛО (кадр 37-5 «Скажи в разговоре», SESSION-DES-4; архитектор 22.09 — `targets` ряда разговора окна): между
  // правилами и «Без подсказок» — бровь «Скажи в разговоре» через 24 и под ней, через 14, фразы разговора в порядке
  // сервера: фраза Literata 17/23 чернилами, перевод 15/20 серым второй строкой через 2, между фразами 10. Список —
  // окно 116 со своей прокруткой, нижний край тает на 28: нижняя фраза подрезана кромкой, а переключатель и кнопка
  // стоят на своих местах. Без фраз — блока нет.
  // ЛОВИТ: фразы дня вместо целей разговора, чужой порядок, список во весь рост, вытолкнувший переключатель под
  // кнопку, и пустую бровь без фраз.
  testWidgets('37-5: «Скажи в разговоре» — фразы сервера в окне 116 с тающим краем', (tester) async {
    await pumpEntry(tester, day.scene, targets: targets);
    final brow = find.text('СКАЖИ В РАЗГОВОРЕ');
    expect(brow, findsOneWidget);
    final window = find.byKey(const ValueKey('talk-entry-targets'));
    expect(tester.getSize(window).height, 116);
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
    final last = tester.getRect(find.byKey(ValueKey('talk-entry-target-${targets[4].sceneId}-p5')));
    expect(last.bottom, lessThanOrEqualTo(tester.getRect(window).bottom), reason: 'последняя фраза доступна прокруткой');
    expect(tester.getRect(toggle).top - tester.getRect(window).bottom, moreOrLessEquals(24, epsilon: 1), reason: 'экран не уехал');

    await pumpEntry(tester, day.scene);
    expect(find.text('СКАЖИ В РАЗГОВОРЕ'), findsNothing, reason: 'нет фраз — нет блока');
    expect(find.byKey(const ValueKey('talk-entry-targets')), findsNothing);
  });

  // ПРАВИЛО: после «около» — родительный падеж; у строки свой plural, а не planMinutesCount.
  // ЛОВИТ: «около 3 минуты» — так вход 37-5 выглядел в живом прогоне CLIENT-CONV-1a.
  testWidgets('37-5: «около N минут» — родительный падеж', (tester) async {
    for (final (minutes, text) in [(1, 'около 1 минуты'), (3, 'около 3 минут'), (6, 'около 6 минут'), (21, 'около 21 минуты')]) {
      await pumpEntry(tester, day.scene, minutes: minutes);
      expect(tester.widget<Text>(find.byKey(const ValueKey('talk-entry-minutes'))).data, text);
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
