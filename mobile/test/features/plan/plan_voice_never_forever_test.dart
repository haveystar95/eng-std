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

  setUp(() {
    // Свой каталог, а не системный: `getApplicationSupportDirectory()` в виджет-тесте не отвечает,
    // и кэш застревал бы на первом же `load()` — проверяли бы отсутствие плагина, а не ожидание.
    dir = Directory.systemTemp.createTempSync('plan_voice_test');
    FlutterSecureStorage.setMockInitialValues({});
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger.setMockMethodCallHandler(
      ttsChannel,
      (call) async => 1,
    );
    final dio = Dio();
    // Сеть, которой нет и не будет: файл не приедет никогда.
    dio.httpClientAdapter = _DeadAdapter();
    cache = LineAudioCache(http: dio, directory: dir);
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

  testWidgets('реплика без файла ждёт секунды, а потом отдаётся системному голосу', (tester) async {
    await tester.pumpWidget(host());
    await tester.pumpAndSettle();
    await tester.tap(find.text('Начать диалог'));
    await tester.pumpAndSettle();

    // Пока ждём — честное «Готовим озвучку» и никаких обещаний про секунды.
    expect(find.text('Готовим озвучку'), findsOneWidget);
    expect(find.textContaining('осталось'), findsNothing);

    // Повтор докачки — и он тоже не помог: сети нет.
    await tester.pump(const Duration(seconds: 4));
    await tester.pumpAndSettle();
    expect(find.text('Готовим озвучку'), findsOneWidget);

    // …и ожидание кончается. Пузырь встаёт на место, реплику читает движок.
    await tester.pump(const Duration(seconds: 6));
    await tester.pumpAndSettle();
    expect(find.text('Готовим озвучку'), findsNothing);
    expect(find.textContaining('говорит собеседник'), findsOneWidget);
    expect(find.text('Ещё раз'), findsOneWidget);

    // Тихий фолбэк засчитан: у реплики БЫЛ адрес, а прозвучала она системным голосом.
    expect(cache.trouble.silentFallbacks, greaterThan(0));

    await tester.pumpWidget(const SizedBox.shrink());
    await tester.pumpAndSettle();
  });
}

class _DeadAdapter implements HttpClientAdapter {
  @override
  void close({bool force = false}) {}

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<List<int>>? requestStream,
    Future<void>? cancelFuture,
  ) async => ResponseBody.fromBytes(const [], 503);
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
