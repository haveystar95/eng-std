/// ОКНО ДНЯ В ТЕСТЕ (наряды DAY-UI-2, DAY-UI-3) — живой ответ сервера → настоящий экран `DayWindowScreen`.
///
/// Фикстуры `test/fixtures/plan/room_window_*.json` сняты с backend2 одним планом, пройденным по API
/// (`docs/research/day-ui-3/tools/snap_window.py`): день 1 не начат, идёт («Слушаю и отвечаю» 6 из 16),
/// пройден. Сеть закрыта тем же заглушкой, что у снимков плана ([setUpPlanGoldens]): фото — серый
/// прямоугольник; голос не качается ([RecordingLines] запоминает, что у него попросили) и не звучит
/// (каналы синтеза отвечают пустотой, сказанное пишется в [WindowServer.spoken]).
///
/// Кадр — как в канве серии 23: 390 × 844, статус-бар 52, домашняя полоса 34.
library;

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/audio_mixer.dart';
import 'package:eng_std/data/line_audio.dart';
import 'package:eng_std/data/plan/day_providers.dart';
import 'package:eng_std/data/plan/plan_models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/plan/day/day_window_screen.dart';
import 'package:eng_std/features/plan/day/window/window_compact_header.dart';
import 'package:eng_std/features/plan/day/window/window_pill.dart';
import 'package:eng_std/features/plan/day/window/window_words.dart';
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import 'plan_goldens.dart';

/// Отступы кадра канвы: статус-бар 52 (`height:52px` в коде кадров 23-0a…0e) и домашняя полоса 34.
const kWindowInsets = EdgeInsets.only(top: 52, bottom: 34);

/// Сервер дня для теста: ответ можно поменять между входами в окно, чтения считаются; рядом — что окно
/// попросило докачать и что оно сказало вслух.
class WindowServer {
  WindowServer(this.room, {Plan? plan}) : plan = plan ?? planFrom('plan_window');

  PlanDayRoom room;

  /// The plan the window is opened from — the route a review and the rehearsal read their list off.
  final Plan plan;
  int reads = 0;
  final RecordingLines lines = RecordingLines();

  /// Тексты, отданные синтезу или плееру, по порядку.
  final List<String> spoken = [];
}

/// Экран окна над ответом сервера. [reduceMotion] — снимок (конечные состояния); без него — поведение
/// с настоящими длительностями. [launcher] — окно не сразу, а за кнопкой «open», как за плитой таба:
/// вход и выход настоящие.
Widget dayWindowApp(WindowServer server, {bool reduceMotion = true, bool launcher = false}) {
  Widget window() => DayWindowScreen(plan: server.plan, number: server.room.day.number);

  return ProviderScope(
    overrides: [
      dayRoomProvider.overrideWith((ref, address) async {
        server.reads++;

        return server.room;
      }),
      lineAudioCacheProvider.overrideWithValue(server.lines),
    ],
    child: MaterialApp(
      debugShowCheckedModeBanner: false,
      theme: buildAppTheme(),
      locale: const Locale('ru'),
      supportedLocales: const [Locale('ru'), Locale('en')],
      localizationsDelegates: AppLocalizations.localizationsDelegates,
      builder: (context, child) => MediaQuery(
        data: MediaQuery.of(context).copyWith(disableAnimations: reduceMotion, padding: kWindowInsets, viewPadding: kWindowInsets),
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
Future<void> pumpDayWindow(WidgetTester tester, PlanDayRoom room, {bool reduceMotion = true, Plan? plan}) =>
    pumpDayWindowServer(tester, WindowServer(room, plan: plan), reduceMotion: reduceMotion);

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
  _muteVoice(tester, server);
  await tester.pumpWidget(dayWindowApp(server, reduceMotion: reduceMotion, launcher: launcher));
  // Ответ сервера, раскладка невидимой копии плиты, лента по её высоте.
  for (var i = 0; i < 4; i++) {
    await tester.pump(const Duration(milliseconds: 50));
  }
}

/// Прокрутить ленту ровно до шапки: пилюля встаёт под компактную строку, содержимое вкладки — с
/// начала (кадры «прокручено»). Лента дожидается, пока встанет.
Future<void> scrollToHeader(WidgetTester tester) async {
  await tester.drag(find.byType(NestedScrollView), Offset(0, -pillTravel(tester)));
  for (var i = 0; i < 6; i++) {
    await tester.pump(const Duration(milliseconds: 100));
  }
}

/// Путь пилюли от шва плиты до места под компактной шапкой — весь путь шапки ленты.
double pillTravel(WidgetTester tester) => tester.getRect(find.byType(WindowPill)).top - pillPinnedTop;

/// Где стоит прилипшая пилюля: под статус-баром и строкой 56.
double get pillPinnedTop => kWindowInsets.top + WindowCompactHeader.height;

/// Тап по вкладке [name] и ожидание, пока чип и лента страниц доедут: оба — 220 мс одним контроллером
/// вкладок, первый кадр только запускает переход.
Future<void> openWindowTab(WidgetTester tester, String name) async {
  await tester.tap(find.descendant(of: find.byType(WindowPill), matching: find.text(name)));
  await tester.pump();
  await tester.pump(AppMotion.windowTabChip);
  await tester.pump(const Duration(milliseconds: 50));
}

/// Прозрачность компактной шапки: 0 — плита на месте, 1 — строка 56 встала.
double compactOpacity(WidgetTester tester) =>
    tester.widget<Opacity>(find.byKey(const ValueKey('window-compact'))).opacity;

/// Прокрутить окно так, чтобы [finder] встал посередине экрана. `tester.ensureVisible` ставит его к
/// верху ленты — ровно под прилипшую шапку с пилюлей, где тап достаётся пилюле.
Future<void> revealInWindow(WidgetTester tester, Finder finder) async {
  await Scrollable.ensureVisible(tester.element(finder), alignment: .5);
  await tester.pumpAndSettle();
}

/// Тап по карточке слова [term] и ожидание, пока шит 23-0e поднимется.
Future<void> openWordSheet(WidgetTester tester, String term) async {
  final card = find.ancestor(of: find.text(term).first, matching: find.byType(WindowWordCard));
  await tester.tap(card);
  await tester.pump();
  await tester.pump(AppMotion.windowSheetRise);
  await tester.pump(const Duration(milliseconds: 50));
}

/// Ответ дня из фикстуры окна: `not_started`, `in_progress`, `passed`.
PlanDayRoom windowRoom(String state, [Map<String, dynamic> Function(Map<String, dynamic>)? edit]) =>
    roomFrom('room_window_$state', edit);

/// Голос окна в тесте молчит: докачки нет, синтез и плеер отвечают пустотой, сказанное записывается.
void _muteVoice(WidgetTester tester, WindowServer server) {
  final messenger = tester.binding.defaultBinaryMessenger;
  messenger.setMockMethodCallHandler(const MethodChannel('flutter_tts'), (call) async {
    if (call.method == 'speak') server.spoken.add('${call.arguments}');

    return call.method == 'speak' ? 0 : 1;
  });
  messenger.setMockMethodCallHandler(AudioMixer.channel, (_) async => null);
  addTearDown(() {
    messenger.setMockMethodCallHandler(const MethodChannel('flutter_tts'), null);
    messenger.setMockMethodCallHandler(AudioMixer.channel, null);
  });
}

/// Кэш голоса, который ничего не качает, а запоминает, что у него попросили: строки докачки окна.
class RecordingLines extends LineAudioCache {
  final List<LineAudioRef> asked = [];

  @override
  Future<void> preload(Iterable<LineAudioRef> lines) async => asked.addAll(lines);
}
