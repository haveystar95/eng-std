/// ОКНО ДНЯ В ТЕСТЕ (наряд DAY-UI-2) — живой ответ сервера → настоящий экран `DayWindowScreen`.
///
/// Фикстуры `test/fixtures/plan/room_window_*.json` сняты с backend2 одним планом, пройденным по API
/// (`docs/research/day-ui-2/README.md`): день 1 не начат, идёт («Слушаю и отвечаю» 6 из 16), пройден.
/// Сеть закрыта тем же заглушкой, что у снимков плана ([setUpPlanGoldens]): фото — серый прямоугольник;
/// голос не качается ([_SilentLines]) и не звучит (каналы синтеза отвечают пустотой).
///
/// Кадр — как в канве: 390 × 844, статус-бар 54, домашняя полоса 34.
library;

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/line_audio.dart';
import 'package:eng_std/data/plan/day_providers.dart';
import 'package:eng_std/data/plan/plan_models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/plan/day/day_window_screen.dart';
import 'package:eng_std/features/plan/day/window/window_compact_header.dart';
import 'package:eng_std/features/plan/day/window/window_tabs.dart';
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import 'plan_goldens.dart';

const _insets = EdgeInsets.only(top: 54, bottom: 34);

/// Сервер дня для теста: ответ можно поменять между входами в окно, чтения считаются.
class WindowServer {
  WindowServer(this.room);

  PlanDayRoom room;
  int reads = 0;
}

/// Экран окна над ответом сервера. [reduceMotion] — снимок (конечные состояния); без него — поведение
/// с настоящими длительностями. [launcher] — окно не сразу, а за кнопкой «open», как за плитой таба:
/// вход и выход настоящие.
Widget dayWindowApp(WindowServer server, {bool reduceMotion = true, bool launcher = false}) {
  Widget window() => DayWindowScreen(plan: planFrom('plan_window'), number: server.room.day.number);

  return ProviderScope(
    overrides: [
      dayRoomProvider.overrideWith((ref, address) async {
        server.reads++;

        return server.room;
      }),
      lineAudioCacheProvider.overrideWithValue(_SilentLines()),
    ],
    child: MaterialApp(
      debugShowCheckedModeBanner: false,
      theme: buildAppTheme(),
      locale: const Locale('ru'),
      supportedLocales: const [Locale('ru'), Locale('en')],
      localizationsDelegates: AppLocalizations.localizationsDelegates,
      builder: (context, child) => MediaQuery(
        data: MediaQuery.of(context).copyWith(disableAnimations: reduceMotion, padding: _insets, viewPadding: _insets),
        child: child!,
      ),
      home: launcher
          ? Builder(
              builder: (context) => Scaffold(
                body: Center(
                  child: TextButton(
                    onPressed: () => Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => window())),
                    child: const Text('open'),
                  ),
                ),
              ),
            )
          : window(),
    ),
  );
}

/// Кадр 390 × 844 @2×, экран нарисован, плита измерена.
Future<void> pumpDayWindow(WidgetTester tester, PlanDayRoom room, {bool reduceMotion = true}) =>
    pumpDayWindowServer(tester, WindowServer(room), reduceMotion: reduceMotion);

/// То же над [server]; с [launcher] на экране только кнопка «open».
Future<void> pumpDayWindowServer(
  WidgetTester tester,
  WindowServer server, {
  bool reduceMotion = true,
  bool launcher = false,
}) async {
  tester.view
    ..devicePixelRatio = kGoldenDpr
    ..physicalSize = kFrameSize * kGoldenDpr;
  addTearDown(tester.view.reset);
  _muteVoice(tester);
  await tester.pumpWidget(dayWindowApp(server, reduceMotion: reduceMotion, launcher: launcher));
  // Ответ сервера, раскладка невидимой копии плиты, лента по её высоте.
  for (var i = 0; i < 4; i++) {
    await tester.pump(const Duration(milliseconds: 50));
  }
}

/// Прокрутить ленту ровно до шапки: вкладки встают под компактную строку, содержимое вкладки — с
/// начала (кадры «прокручено»). Лента дожидается, пока встанет.
Future<void> scrollToHeader(WidgetTester tester) async {
  final tabsTop = tester.getRect(find.byType(WindowTabBar)).top;
  await tester.drag(find.byType(NestedScrollView), Offset(0, -(tabsTop - _insets.top - WindowCompactHeader.height)));
  for (var i = 0; i < 6; i++) {
    await tester.pump(const Duration(milliseconds: 100));
  }
}

/// Тап по вкладке [name] и ожидание, пока лента страниц доедет: переход ленты — 220 мс по
/// контроллеру вкладок, первый кадр только запускает его.
Future<void> openWindowTab(WidgetTester tester, String name) async {
  await tester.tap(find.descendant(of: find.byType(WindowTabBar), matching: find.text(name)));
  await tester.pump();
  await tester.pump(AppMotion.windowTabSwitch);
  await tester.pump(const Duration(milliseconds: 50));
}

/// Прозрачность компактной шапки: 0 — плита на месте, 1 — строка 56 встала.
double compactOpacity(WidgetTester tester) => tester
    .widget<FadeTransition>(find.ancestor(of: find.byType(WindowCompactHeader), matching: find.byType(FadeTransition)).first)
    .opacity
    .value;

/// Ответ дня из фикстуры окна: `not_started`, `in_progress`, `passed`.
PlanDayRoom windowRoom(String state, [Map<String, dynamic> Function(Map<String, dynamic>)? edit]) =>
    roomFrom('room_window_$state', edit);

/// Голос окна в тесте молчит: докачки нет, синтез и плеер отвечают пустотой.
void _muteVoice(WidgetTester tester) {
  final messenger = tester.binding.defaultBinaryMessenger;
  messenger.setMockMethodCallHandler(const MethodChannel('flutter_tts'), (call) async => call.method == 'speak' ? 0 : 1);
  messenger.setMockMethodCallHandler(const MethodChannel('com.denis.engstd/line_audio'), (_) async => null);
  addTearDown(() {
    messenger.setMockMethodCallHandler(const MethodChannel('flutter_tts'), null);
    messenger.setMockMethodCallHandler(const MethodChannel('com.denis.engstd/line_audio'), null);
  });
}

class _SilentLines extends LineAudioCache {
  @override
  Future<void> preload(Iterable<LineAudioRef> lines) async {}
}
