/// СНИМКИ СОСТОЯНИЙ ПЛАНА — фикстура ответа сервера → экран → PNG (наряд PLAN-UI, §9.3).
///
/// Решение владельца (11.09, 02:45): состояния таба и входа снимаются НЕ руками на симуляторе, а
/// golden-тестами. Ручной прогон каждого кадра стоил 30–120 с на тап, ломался от чужой сессии на
/// том же симуляторе и не оставался в проекте; снимок из фикстуры стоит секунды, ложится в
/// `test/goldens/` и назавтра ловит расхождение сам.
///
/// Что здесь важно и почему:
/// - **Шрифты настоящие.** flutter_test рисует в Ahem (каждый глиф — квадрат em), и снимок в нём
///   не сравнить ни с кадром, ни с телефоном. Грузятся те же файлы, что бандлит приложение.
/// - **Сеть не ходит.** Фикстуры несут настоящие ссылки Pexels; [_StubHttpOverrides] отдаёт им
///   один и тот же серый PNG, поэтому снимок не зависит ни от сети, ни от того, что там за фото.
/// - **Анимации выключены** (`disableAnimations`): и шиммер плиты, и скелет маршрута читают этот
///   флаг и замирают — иначе кадр зависел бы от того, на какой миллисекунде его сняли.
/// - **Время не участвует.** Всё, что склоняет событие и раздаёт слоты дней, приходит с сервера
///   строкой; клиент форматирует только даты ИЗ фикстуры. Единственное место с `DateTime.now()` —
///   шаг дней входа с включённой датой (кадр 22-3b), и он снимается не здесь, а живьём.
library;

import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';

import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/plan/plan_models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/plan/plan_providers.dart';
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

/// Логический размер кадра канваса — 390 × 844 (iPhone 14/15/16 в точках).
const Size kFrameSize = Size(390, 844);

/// Плотность снимка. 2 — как на телефоне: тонкие волосяные линии и мелкий шрифт на 1× сливаются, а
/// «выглядит пустым» на такой палитре и так самая дорогая ошибка чтения (`docs/qa/PLAYBOOK.md`).
const double kGoldenDpr = 2;

/// Готовит биндинг под снимки: настоящие шрифты, ru/en для `DateFormat`, заглушка сети.
///
/// Зовётся один раз на файл, из `setUpAll`.
Future<void> setUpPlanGoldens() async {
  TestWidgetsFlutterBinding.ensureInitialized();
  HttpOverrides.global = _StubHttpOverrides();
  await initializeDateFormatting('ru');
  await initializeDateFormatting('en');
  await _loadFont(AppFonts.inter, const [
    'assets/fonts/Inter-Regular.ttf',
    'assets/fonts/Inter-SemiBold.ttf',
    'assets/fonts/Inter-Bold.ttf',
    'assets/fonts/Inter-ExtraBold.ttf',
  ]);
  await _loadFont(AppFonts.literata, const [
    'assets/fonts/Literata-Regular.ttf',
    'assets/fonts/Literata-Medium.ttf',
    'assets/fonts/Literata-Italic.ttf',
  ]);
  // Иконки — тот же шрифт, что и в приложении; без него у меню, галок и шевронов пустые квадраты.
  // Имя семьи с префиксом пакета — именно так его ищет движок у `IconData(fontPackage: …)`.
  final lucide = FontLoader('packages/lucide_icons_flutter/Lucide')
    ..addFont(rootBundle.load('packages/lucide_icons_flutter/assets/lucide.ttf'));
  await lucide.load();
}

Future<void> _loadFont(String family, List<String> files) async {
  final loader = FontLoader(family);
  for (final path in files) {
    loader.addFont(File(path).readAsBytes().then(ByteData.sublistView));
  }
  await loader.load();
}

/// Экран приложения вокруг снимаемого виджета: тема, локаль, делегаты — как в `main.dart`.
///
/// Две подмены обязательны на любом снимке: аккаунт (кружок-аватар в шапке читает имя) и подсказки
/// первого раза (иначе провайдер полез бы в базу устройства). Снимку, которому нужна ещё одна,
/// хватает вложенного `ProviderScope` вокруг [home] — riverpod наследует всё неподменённое.
Widget planGoldenApp(
  Widget home, {
  PlanHints hints = const PlanHints(tabShown: true, closeShown: true, howShown: true),
  Locale locale = const Locale('ru'),
}) => ProviderScope(
  overrides: [
    authControllerProvider.overrideWith(() => _GoldenAuth()),
    planHintsProvider.overrideWith(() => _GoldenHints(hints)),
  ],
  child: MaterialApp(
    debugShowCheckedModeBanner: false,
    theme: buildAppTheme(),
    locale: locale,
    supportedLocales: const [Locale('ru'), Locale('en')],
    localizationsDelegates: AppLocalizations.localizationsDelegates,
    home: MediaQuery(
      // Анимации выключены — снимок не должен зависеть от кадра, на котором его сняли.
      data: const MediaQueryData(disableAnimations: true),
      child: home,
    ),
  ),
);

/// Ставит окно под размер кадра, рисует [app] и сверяет с `test/goldens/<name>.png`.
///
/// Путь поднимается на два уровня, потому что снимающие тесты лежат в `test/features/plan/`, а
/// снимки — общей папкой `test/goldens/`: их смотрят как альбом состояний, а не как хвост тестов.
///
/// [size] выше кадра там, где снимается длинный экран целиком (маршрут на 10 дней) — лист длиннее
/// телефона, и снимок в 844 точки показал бы только его верх.
Future<void> expectPlanGolden(
  WidgetTester tester,
  Widget app,
  String name, {
  Size size = kFrameSize,
  Duration settle = const Duration(milliseconds: 400),
  Future<void> Function(WidgetTester tester)? prime,
}) async {
  tester.view
    ..devicePixelRatio = kGoldenDpr
    ..physicalSize = size * kGoldenDpr;
  addTearDown(tester.view.reset);
  await tester.pumpWidget(app);
  await tester.pump();
  // Экран, который сам себя в нужное состояние не приведёт: тест доводит его теми же нажатиями,
  // какими это делает человек (вход — тремя «Далее»).
  await prime?.call(tester);
  // Не `pumpAndSettle`: у скелета и шиммера есть бесконечные контроллеры, которые она ждала бы до
  // таймаута даже выключенными. Фиксированные два кадра — и снимок.
  await tester.pump();
  await tester.pump(settle);

  await expectLater(find.byType(MaterialApp), matchesGoldenFile('../../goldens/$name.png'));
}

// ── фикстуры ────────────────────────────────────────────────────────────────────────────────

/// Ответ сервера, снятый с живого backend2 (`GET /plans/current`, `GET /plans/{id}/days/{n}`),
/// лежит рядом в `test/fixtures/plan/` как пришёл.
Map<String, dynamic> planFixture(String name) =>
    jsonDecode(File('test/fixtures/plan/$name.json').readAsStringSync()) as Map<String, dynamic>;

Plan planFrom(String fixture, [Map<String, dynamic> Function(Map<String, dynamic>)? edit]) {
  final json = planFixture(fixture);

  return Plan.fromJson(edit == null ? json : edit(json));
}

PlanDayRoom roomFrom(String fixture, [Map<String, dynamic> Function(Map<String, dynamic>)? edit]) {
  final json = planFixture(fixture);

  return PlanDayRoom.fromJson(edit == null ? json : edit(json));
}

class _GoldenAuth extends AuthController {
  @override
  Future<AppUser?> build() async => AppUser(id: 'u1', name: 'Денис');
}

class _GoldenHints extends PlanHintsController {
  _GoldenHints(this._hints);

  final PlanHints _hints;

  @override
  Future<PlanHints> build() async => _hints;
}

/// Любая картинка — один серый прямоугольник. Снимок про композицию, а не про то, что на фото.
class _StubHttpOverrides extends HttpOverrides {
  @override
  HttpClient createHttpClient(SecurityContext? context) => _StubHttpClient();
}

class _StubHttpClient implements HttpClient {
  @override
  bool autoUncompress = true;

  @override
  Duration idleTimeout = const Duration(seconds: 15);

  @override
  Duration? connectionTimeout;

  @override
  int? maxConnectionsPerHost;

  @override
  String? userAgent;

  @override
  Future<HttpClientRequest> getUrl(Uri url) async => _StubRequest(url);

  @override
  void close({bool force = false}) {}

  @override
  dynamic noSuchMethod(Invocation invocation) =>
      throw UnsupportedError('golden stub: ${invocation.memberName}');
}

class _StubRequest implements HttpClientRequest {
  _StubRequest(this.uri);

  @override
  final Uri uri;

  @override
  final HttpHeaders headers = _StubHeaders();

  @override
  Future<HttpClientResponse> close() async => _StubResponse();

  @override
  Future<HttpClientResponse> get done => close();

  @override
  dynamic noSuchMethod(Invocation invocation) => null;
}

class _StubHeaders implements HttpHeaders {
  @override
  dynamic noSuchMethod(Invocation invocation) => null;
}

class _StubResponse extends Stream<List<int>> implements HttpClientResponse {
  @override
  int get statusCode => HttpStatus.ok;

  @override
  int get contentLength => _greyPng.length;

  @override
  HttpClientResponseCompressionState get compressionState =>
      HttpClientResponseCompressionState.notCompressed;

  @override
  StreamSubscription<List<int>> listen(
    void Function(List<int> event)? onData, {
    Function? onError,
    void Function()? onDone,
    bool? cancelOnError,
  }) => Stream<List<int>>.fromIterable([_greyPng]).listen(
    onData,
    onError: onError,
    onDone: onDone,
    cancelOnError: cancelOnError,
  );

  @override
  dynamic noSuchMethod(Invocation invocation) => null;
}

/// 2 × 2 PNG цвета бумажной подложки — растягивается по слоту фото.
final Uint8List _greyPng = base64Decode(
  'iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAIAAAD91JpzAAAAEElEQVR4nGM4cWQPEDFAKABAHgkhsC+52AAAAABJRU5ErkJggg==',
);
