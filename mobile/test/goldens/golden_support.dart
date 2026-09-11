/// ХАРНЕСС GOLDEN-ТЕСТОВ ДНЯ (наряд DAY-UI): фикстура ответа сервера → экран → PNG.
///
/// Это и есть тесты «состояние сервера → экран» по канону: каждый golden — одно состояние из
/// наряда (кадр 23-x / правка «Базы»), экран собран настоящими виджетами против сохранённого
/// ответа сервера (`test/goldens/fixtures/*.json` — снято с живого backend2 11.09.2026).
///
/// Шрифты — настоящие Literata и Inter из `assets/fonts/`, иначе flutter_test рисует Ahem и
/// снимок ничего не показывает. Сеть закрыта: фото не грузятся (слот остаётся серым), звук и
/// микрофон — заглушки на каналах.
///
///     flutter test test/goldens --update-goldens   — перерисовать эталоны
library;

import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/api_client.dart';
import 'package:eng_std/data/line_audio.dart';
import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/plan/plan_contract.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/data/speech/speech_diagnostics.dart';
import 'package:eng_std/data/speech/speech_recognizer.dart';
import 'package:eng_std/data/token_store.dart';
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

/// Экран iPhone 17 в логических точках — как на кадрах (390 × 844).
const goldenSize = Size(390, 844);

Future<void> loadAppFonts() async {
  TestWidgetsFlutterBinding.ensureInitialized();
  final literata = FontLoader(AppFonts.literata);
  for (final file in ['Literata-Regular.ttf', 'Literata-Italic.ttf', 'Literata-Medium.ttf']) {
    literata.addFont(File('assets/fonts/$file').readAsBytes().then((b) => ByteData.sublistView(b)));
  }
  await literata.load();
  final inter = FontLoader(AppFonts.inter);
  for (final file in ['Inter-Regular.ttf', 'Inter-SemiBold.ttf', 'Inter-Bold.ttf', 'Inter-ExtraBold.ttf']) {
    inter.addFont(File('assets/fonts/$file').readAsBytes().then((b) => ByteData.sublistView(b)));
  }
  await inter.load();
  // Значки Lucide — из ассетов пакета; без них галки и крестики рисуются квадратами.
  final lucide = FontLoader('packages/lucide_icons_flutter/Lucide');
  lucide.addFont(rootBundle.load('packages/lucide_icons_flutter/assets/lucide.ttf'));
  await lucide.load();
}

Map<String, dynamic> fixture(String name) =>
    jsonDecode(File('test/goldens/fixtures/$name.json').readAsStringSync()) as Map<String, dynamic>;

/// Каналы платформы, которых в тесте нет: звук, озвучка, проба микрофона.
void muteNativeChannels(WidgetTester tester) {
  final messenger = tester.binding.defaultBinaryMessenger;
  for (final name in ['com.denis.engstd/feedback_sound', 'com.denis.engstd/line_audio', 'com.denis.engstd/links']) {
    messenger.setMockMethodCallHandler(MethodChannel(name), (_) async => null);
  }
  messenger.setMockMethodCallHandler(const MethodChannel('com.denis.engstd/speech_probe'), (_) async => {
    'recognition': 'granted',
    'microphone': 'granted',
    'recognizer_supported': true,
    'recognizer_available': true,
    'on_device_supported': true,
  });
  // `speak` → 0: движок «не начал», и Pronouncer не ставит сторож на 12 с (висящий таймер валит тест).
  messenger.setMockMethodCallHandler(const MethodChannel('flutter_tts'), (call) async => call.method == 'speak' ? 0 : 1);
}

/// СЕРВЕР ДНЯ ИЗ ФИКСТУР. Ответы на карточки — как у backend2: первый `failed` возвращает повтор
/// в конец этапа, `failed` у повтора помечает «вернётся».
class GoldenApi extends ApiClient {
  GoldenApi({required Plan plan, required this.room, required this.cards, this.sheet}) : current = plan, super(TokenStore());

  Plan current;
  DayRoom room;
  List<DayCard> cards;
  DaySheet? sheet;
  int _position = 1000;

  @override
  Future<Plan?> currentPlan() async => current;

  @override
  Future<Plan> planById(String planId) async => current;

  @override
  Future<DayRoom> dayRoom(String planId, int number) async => room;

  @override
  Future<DayCards> openDay(String planId, int number) async => DayCards(planId: planId, dayId: room.day.id, number: number, status: room.day.status, cards: cards);

  @override
  Future<DayCards> dayCards(String planId, int number) async => DayCards(planId: planId, dayId: room.day.id, number: number, status: room.day.status, cards: cards);

  @override
  Future<DaySheet> daySheet(String planId, int number) async => sheet ?? DaySheet(planId: planId, number: number, words: const [], phrases: const []);

  @override
  Future<DayAnswerOutcome> answerDayCard(String planId, int number, String cardId, {required DayCardResult result, required int attempts}) async {
    final i = cards.indexWhere((c) => c.id == cardId);
    final c = cards[i];
    final isRetry = c.retryOf != null;
    final answered = _copy(c, result: result, attempts: attempts, returns: isRetry && result == DayCardResult.failed);
    cards[i] = answered;
    DayCard? requeued;
    if (result == DayCardResult.failed && !isRetry) {
      requeued = DayCard(
        id: '${c.id}-retry',
        stage: c.stage,
        position: _position++,
        kind: c.kind,
        source: c.source,
        sourceDayId: c.sourceDayId,
        unitKind: c.unitKind,
        unitRef: c.unitRef,
        payload: c.payload,
        retryOf: c.id,
        attempts: 0,
        returns: false,
      );
      cards.add(requeued);
    }
    return DayAnswerOutcome(card: answered, requeued: requeued);
  }

  @override
  Future<DayRoom> closeStage(String planId, int number, DayStage stage) async => room;

  @override
  Future<DayRoom> closeDay(String planId, int number) async => room;

  @override
  Future<void> retryLesson(String planId, String sceneId) async {}

  static DayCard _copy(DayCard c, {DayCardResult? result, int? attempts, bool? returns}) => DayCard(
    id: c.id,
    stage: c.stage,
    position: c.position,
    kind: c.kind,
    source: c.source,
    sourceDayId: c.sourceDayId,
    unitKind: c.unitKind,
    unitRef: c.unitRef,
    payload: c.payload,
    retryOf: c.retryOf,
    result: result ?? c.result,
    attempts: attempts ?? c.attempts,
    returns: returns ?? c.returns,
  );
}

/// Карточки фикстуры, где всё ДО [target] (по этапу и position) уже отвечено `passed` — сессия
/// откроется ровно на нужной карточке.
List<DayCard> cardsUpTo(List<DayCard> all, bool Function(DayCard) target, {List<DayStage>? onlyStages}) {
  final sorted = [...all]..sort((a, b) => a.stage.index != b.stage.index ? a.stage.index.compareTo(b.stage.index) : a.position.compareTo(b.position));
  final i = sorted.indexWhere(target);
  assert(i >= 0, 'no such card in the fixture');
  return [
    for (var k = 0; k < sorted.length; k++)
      if (onlyStages == null || onlyStages.contains(sorted[k].stage))
        k < i ? GoldenApi._copy(sorted[k], result: DayCardResult.passed, attempts: 1) : sorted[k],
  ];
}

/// МИКРОФОН ПО СЦЕНАРИЮ: [hear] закрывает открытый ход услышанным текстом, [silence] — тишиной.
class ScriptedRecognizer implements SpeechRecognizer {
  Completer<SpeechAttempt>? _pending;
  ValueChanged<String>? _onPartial;
  ValueChanged<double>? _onLevel;

  @override
  bool get isReady => true;

  @override
  Future<bool> get hasPermission async => true;

  @override
  Future<bool> prepare() async => true;

  @override
  Future<SpeechAttempt> listenOnce({
    required List<String> expected,
    required String localeId,
    Duration timeout = const Duration(seconds: 8),
    Duration pauseFor = const Duration(seconds: 2),
    List<String> contextualStrings = const [],
    ValueChanged<String>? onPartial,
    ValueChanged<double>? onLevel,
  }) {
    _onPartial = onPartial;
    _onLevel = onLevel;
    final c = Completer<SpeechAttempt>();
    _pending = c;
    return c.future;
  }

  /// Живой уровень и частичный текст — «пишу» (23-3b).
  void speaking(String partial, {double db = 6}) {
    _onLevel?.call(db);
    _onPartial?.call(partial);
  }

  void hear(String text) {
    _onPartial?.call(text);
    final c = _pending;
    _pending = null;
    c?.complete(SpeechAttempt.heard(text));
  }

  void silence() {
    final c = _pending;
    _pending = null;
    c?.complete(const SpeechAttempt.silent());
  }

  @override
  Future<void> stop() async {}

  @override
  Future<void> cancel() async {
    final c = _pending;
    _pending = null;
    if (c != null && !c.isCompleted) c.complete(const SpeechAttempt.silent());
  }
}

class _GoldenAuth extends AuthController {
  @override
  Future<AppUser?> build() async => AppUser(id: '01GOLDEN', name: 'Golden');
}

/// Приложение вокруг экрана: русская локаль, размеры кадра, анимации выключены (снимок — конечное
/// состояние, не середина перехода).
Widget goldenApp({
  required Widget home,
  required GoldenApi api,
  required ScriptedRecognizer recognizer,
  required Directory audioDir,
}) => ProviderScope(
  overrides: [
    apiClientProvider.overrideWithValue(api),
    speechRecognizerProvider.overrideWithValue(recognizer),
    speechDiagnosticsProvider.overrideWithValue(SpeechDiagnostics()),
    lineAudioCacheProvider.overrideWithValue(LineAudioCache(directory: audioDir, bearer: () => null)),
    authControllerProvider.overrideWith(_GoldenAuth.new),
    appDatabaseProvider.overrideWith((ref) {
      final db = AppDatabase.forTesting(NativeDatabase.memory());
      ref.onDispose(db.close);
      return db;
    }),
  ],
  child: MaterialApp(
    debugShowCheckedModeBanner: false,
    theme: buildAppTheme(),
    locale: const Locale('ru'),
    localizationsDelegates: AppLocalizations.localizationsDelegates,
    supportedLocales: const [Locale('ru'), Locale('en')],
    builder: (context, child) => MediaQuery(
      data: MediaQuery.of(context).copyWith(disableAnimations: true, padding: const EdgeInsets.only(top: 54, bottom: 34)),
      child: child!,
    ),
    home: home,
  ),
);

/// Кадр [height] логических точек высотой (кабинет снимается целиком — 23-0d это и есть
/// прокрученная страница).
Future<void> setFrame(WidgetTester tester, {double height = 844}) async {
  tester.view.physicalSize = Size(goldenSize.width * 2, height * 2);
  tester.view.devicePixelRatio = 2;
  addTearDown(tester.view.reset);
}

/// Снимок в `test/goldens/<name>.png`.
Future<void> expectGolden(WidgetTester tester, String name) async {
  await expectLater(find.byType(MaterialApp), matchesGoldenFile('$name.png'));
}

/// Дать экрану догрузить данные и доиграть переходы, не дожидаясь бесконечных анимаций.
Future<void> settle(WidgetTester tester, {int frames = 6, Duration step = const Duration(milliseconds: 120)}) async {
  for (var i = 0; i < frames; i++) {
    await tester.pump(step);
  }
}
