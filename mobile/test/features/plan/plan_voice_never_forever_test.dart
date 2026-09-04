/// «ГОТОВИМ ОЗВУЧКУ» НЕ БЫВАЕТ НАВСЕГДА (канон §7, доп. к наряду DAY-2-FIX).
///
/// Живьём реплика роли висела в этом состоянии бессрочно: докачка на входе в день была ОДНОЙ
/// попыткой, и упавшая попытка не повторялась ничем. Экран честно ждал того, чего никто больше не
/// просил, и разговор останавливался на середине.
///
/// Два обещания, и оба про секунды, а не про «навсегда»:
///   упавшая докачка повторяется, пока человек смотрит на пузырь;
///   если и повтор не помог — реплика звучит СИСТЕМНЫМ голосом, а не тишиной, и кэш засчитывает
///   тихий фолбэк, чтобы дев-бейдж это показал.
library;

import 'dart:async';
import 'dart:io';

import 'package:dio/dio.dart';
import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/api_client.dart';
import 'package:eng_std/data/line_audio.dart';
import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/plan_models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/data/review_sync.dart';
import 'package:eng_std/data/session_completion_sync.dart';
import 'package:eng_std/features/training/session_screen.dart';
import 'package:eng_std/l10n/app_localizations.dart';

void main() {
  const planId = '01PLAN';
  const line = 'Could you tell me about your background?';
  const url = 'https://x/api/v1/audio/lines/A.mp3';
  const ttsChannel = MethodChannel('flutter_tts');

  late LineAudioCache cache;
  late Directory dir;

  /// Кэш с заданной сетью и с токеном — токен спрашивается В МОМЕНТ запроса, как в приложении.
  LineAudioCache cacheWith(HttpClientAdapter adapter) {
    final dio = Dio();
    dio.httpClientAdapter = adapter;

    return LineAudioCache(http: dio, directory: dir, bearer: () => 'T0K3N');
  }

  setUp(() {
    // Свой каталог, а не системный: `getApplicationSupportDirectory()` в виджет-тесте не отвечает,
    // и кэш застревал бы на первом же `load()` — проверяли бы отсутствие плагина, а не ожидание.
    dir = Directory.systemTemp.createTempSync('plan_voice_test');
    FlutterSecureStorage.setMockInitialValues({});
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger.setMockMethodCallHandler(
      ttsChannel,
      (call) async => 1,
    );
    cache = cacheWith(_DeadAdapter());
  });

  tearDown(() {
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger.setMockMethodCallHandler(
      ttsChannel,
      null,
    );
    if (dir.existsSync()) dir.deleteSync(recursive: true);
  });

  const chain = PlanDialogue(
    dayIndex: 1,
    sceneTitle: 'Рассказ о прошлом опыте',
    turns: [
      PlanDialogueTurn(turn: 'role', termId: '01HEAR', text: line, shelf: 'hear'),
      PlanDialogueTurn(
        turn: 'you',
        termId: '01SAY',
        text: 'My background is in backend development.',
        shelf: 'say',
      ),
    ],
  );

  Widget host() => ProviderScope(
    overrides: [
      apiClientProvider.overrideWithValue(_PlanApi()),
      lineAudioCacheProvider.overrideWithValue(cache),
      appDatabaseProvider.overrideWith((ref) {
        final database = AppDatabase.forTesting(NativeDatabase.memory());
        ref.onDispose(database.close);
        return database;
      }),
      reviewSyncProvider.overrideWith((ref) => _SilentReviewSync(ref)),
      sessionCompletionSyncProvider.overrideWithValue(_SilentCompletion()),
      planSessionProvider.overrideWith(
        (ref, args) async => PlanSession(
          sessionId: args.sessionId,
          planId: planId,
          dayIndex: 2,
          strict: true,
          dialogues: const [chain],
          lineAudio: const [(text: line, url: url)],
          tasks: [
            PlanSessionTask(
              card: SessionCard(
                termId: '01SAY',
                mode: ExerciseMode.situationalSay,
                type: 'phrase',
                answer: 'My background is in backend development.',
                options: const ['My background is in backend development.', 'See you tomorrow.'],
              ),
              stage: PlanStage.b,
              ordinal: 1,
              ofSteps: 1,
              fromDayIndex: 1,
              softened: false,
              section: PlanSessionTask.sectionReview,
              sectionCode: PlanSessionTask.sectionCodeDialogue,
              kind: 'line',
              shelf: PlanTermRow.shelfSay,
            ),
          ],
          raw: const {'session_id': 'S', 'plan_id': planId, 'day_index': 2, 'strict': true},
        ).asStudySession(),
      ),
    ],
    child: const MaterialApp(
      locale: Locale('ru'),
      localizationsDelegates: AppLocalizations.localizationsDelegates,
      supportedLocales: [Locale('ru')],
      home: SessionScreen(title: 'День 2', planId: planId, planDayIndex: 2, targetLang: 'en'),
    ),
  );

  testWidgets('докачка упала — реплика не ждёт вовсе и звучит системным голосом', (tester) async {
    // ЖИВОЙ ДЕФЕКТ: бейдж показал «0 системным, 10 не скачалось, http 401». Ноль значил, что
    // реплики просто МОЛЧАЛИ: экран ждал файла, которого уже не будет. Отказ — не задержка.
    cache = cacheWith(_DeadAdapter());
    await tester.pumpWidget(host());
    await tester.pumpAndSettle();
    await tester.tap(find.text('Начать диалог'));
    await tester.pumpAndSettle();

    expect(find.text('Готовим озвучку'), findsNothing);
    expect(find.textContaining('говорит собеседник'), findsOneWidget);
    expect(find.text('Ещё раз'), findsOneWidget);
    // Тихий фолбэк засчитан: у реплики БЫЛ адрес, а прозвучала она системным голосом, — и это
    // то самое число, которое дев-бейдж показывает.
    expect(cache.trouble.silentFallbacks, greaterThan(0));
    expect(cache.trouble.downloads, greaterThan(0));

    // …и одна отложенная попытка всё-таки идёт: «первая попытка пришлась не на тот момент» —
    // это класс, а не один баг (токен, поднявшийся из кейчейна секундой позже; сеть в лифте).
    final firstTry = cache.trouble.downloads;
    await tester.pump(const Duration(seconds: 4));
    await tester.pumpAndSettle();
    expect(cache.trouble.downloads, greaterThan(firstTry));

    await tester.pumpWidget(const SizedBox.shrink());
    await tester.pumpAndSettle();
  });

  testWidgets('файл ещё едет — ждём секунды, а потом всё равно звучим', (tester) async {
    // Второе состояние, и только оно — про кадр DL·08: докачка не ответила ни да, ни нет.
    cache = cacheWith(_HangingAdapter());
    await tester.pumpWidget(host());
    await tester.pumpAndSettle();
    await tester.tap(find.text('Начать диалог'));
    await tester.pumpAndSettle();

    expect(find.text('Готовим озвучку'), findsOneWidget);
    // Никаких «осталось 5 секунд»: время докачки неизвестно, поэтому не обещается.
    expect(find.textContaining('осталось'), findsNothing);

    await tester.pump(const Duration(seconds: 4));
    await tester.pumpAndSettle();
    expect(find.text('Готовим озвучку'), findsOneWidget);

    await tester.pump(const Duration(seconds: 6));
    await tester.pumpAndSettle();
    expect(find.text('Готовим озвучку'), findsNothing);
    expect(find.textContaining('говорит собеседник'), findsOneWidget);
    expect(cache.trouble.silentFallbacks, greaterThan(0));

    await tester.pumpWidget(const SizedBox.shrink());
    await tester.pumpAndSettle();
  });
}

/// Сервер отвечает `401` — ровно то, что живьём получили все десять файлов посадки.
class _DeadAdapter implements HttpClientAdapter {
  @override
  void close({bool force = false}) {}

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<List<int>>? requestStream,
    Future<void>? cancelFuture,
  ) async => ResponseBody.fromBytes(const [], 401);
}

/// Докачка, которая не отвечает ни да, ни нет: файл ЕДЕТ.
class _HangingAdapter implements HttpClientAdapter {
  @override
  void close({bool force = false}) {}

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<List<int>>? requestStream,
    Future<void>? cancelFuture,
  ) => Completer<ResponseBody>().future;
}

class _PlanApi implements ApiClient {
  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

class _SilentCompletion implements SessionCompletionSync {
  @override
  Future<void> record({required String sessionId, DateTime? endedAt}) async {}

  @override
  Future<void> flush() async {}

  @override
  Future<int> pendingCount() async => 0;

  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

class _SilentReviewSync extends ReviewSync {
  _SilentReviewSync(Ref ref)
    : super(
        ref.read(apiClientProvider),
        ref.read(reviewQueueProvider),
        ref.read(seqCounterProvider),
        ref,
      );

  @override
  Future<void> record({
    required String termId,
    required String exerciseMode,
    required String response,
    bool usedHint = false,
    bool isPractice = false,
    int? latencyMs,
    String? sessionId,
    int? ladderStep,
  }) async {}

  @override
  Future<void> flush() async {}
}
