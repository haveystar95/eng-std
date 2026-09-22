import 'dart:async';
import 'dart:io';
import 'dart:ui' as ui;

import 'package:dio/dio.dart';
import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/app_version.dart';
import 'package:eng_std/data/audio_mixer.dart';
import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart' show AppUser;
import 'package:eng_std/data/plan/conversation/conversation_models.dart';
import 'package:eng_std/data/plan/day_providers.dart';
import 'package:eng_std/data/plan/day_window.dart';
import 'package:eng_std/data/plan/plan_models.dart';
import 'package:eng_std/data/plan/session/session_day.dart';
import 'package:eng_std/data/plan/session/session_models.dart';
import 'package:eng_std/data/plan/session/session_outcomes.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/plan/conversation/conversation_controller.dart';
import 'package:eng_std/features/plan/conversation/talk_entry.dart';
import 'package:eng_std/features/plan/conversation/talk_screen.dart';
import 'package:eng_std/features/plan/conversation/talk_summary.dart';
import 'package:eng_std/features/plan/day/day_window_screen.dart';
import 'package:eng_std/features/plan/plan_providers.dart';
import 'package:eng_std/features/plan/plan_tab_screen.dart';
import 'package:eng_std/features/plan/session/cards/card_host.dart';
import 'package:eng_std/features/plan/session/cards/card_kit.dart';
import 'package:eng_std/features/plan/session/session_controller.dart';
import 'package:eng_std/features/plan/session/session_mic.dart';
import 'package:eng_std/features/plan/session/session_screen.dart';
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../../support/day_window_harness.dart' show RecordingLines, kWindowInsets;
import '../../../support/nbsp.dart';
import '../../../support/plan_goldens.dart' show planFixture, planFrom, planGoldenApp, setUpPlanGoldens;
import '../../../support/server_fixtures.dart';
import '../../../support/session_harness.dart';
import '../../../support/talk_harness.dart';

/// СНИМКИ НАРЯДА CLIENT-CONV-1c (§10) — каждый изменённый экран и состояние кадром 390 × 844 @2×, для архитектора ДО
/// сборки на телефон.
///
/// Не golden-тест: сравнивать не с чем. Без ключа файл проверяет, что каждый экран рисуется и ничего не переполняет.
///
/// ```bash
/// flutter test test/features/plan/session/conv1c_shots_test.dart --dart-define=CONV1C_SHOTS=true
/// ```
/// Кадры ложатся в `../backend2/docs/research/client-conv-1c/shots/`. Ответы дней и документы разговора — фикстуры
/// сервера BACK-TAILS-2 (`server_fixtures.dart`: минуты рядов, `targets` ряда разговора, `sources`, этап `repetition`);
/// свой JSON поверх них — только то, чего в фикстурах нет: пройденные дни с `talk_again`, лента, обрезанная до
/// нужного хода.
void main() {
  const writeShots = bool.fromEnvironment('CONV1C_SHOTS');
  const frame = Size(390, 844);
  final shotKey = GlobalKey();
  final doctor = sessionFixture('day-doctor');
  final plan = planFrom('plan_rehearsal');
  final booking = plan.scenes.firstWhere((s) => s.titleNative == 'Запись к врачу');
  final visit = plan.scenes.firstWhere((s) => s.titleNative == 'Приём у врача');
  final openV2 = talkV2('talk_day_open_v2');
  // The talk's end and summaries as the server keeps them (BACK-TAILS-2's re-shot documents).
  final endedDay = serverTalk('conversation-day-ended');
  final rehearsalTalk = serverTalk('conversation-rehearsal-ended');

  /// The talk before «Не понял»: the role's first question, the learner's first line, the role's second question.
  final beforeRescue = talkV2('talk_day_open_v2', (json) {
    json['turns'] = (json['turns'] as List).take(3).toList();
    json['turns_left'] = 3;
  });

  /// The talk one move on: «He has had it for three days.» said, p2 ticked by the server, the role's next question.
  final afterMove = talkV2('talk_day_open_v2', (json) {
    final turns = (json['turns'] as List).cast<Map<String, dynamic>>();
    final learner = Map<String, dynamic>.of(turns[1]);
    final partner = Map<String, dynamic>.of(turns[2]);
    final p2 = ((json['targets'] as List).cast<Map<String, dynamic>>())[1];
    turns.addAll([
      {
        ...learner,
        'index': 6,
        'text_target': 'He has had it for three days.',
        'phrases_used': [
          {'scene_id': p2['scene_id'], 'ref': 'p2', 'text_target': p2['text_target'], 'text_native': p2['text_native']},
        ],
      },
      {...partner, 'index': 7, 'text_target': 'Has he had a temperature?', 'text_native': 'У него была температура?', 'understood': true},
    ]);
    p2['said'] = true;
    json['turns_left'] = 1;
  });

  /// The talk row of a day's window as the server sends it — its title, scenes, minutes and «Скажи в разговоре».
  WindowStage talkRow(String day) =>
      DayWindow.fromJson(serverFixtureJson(day)['window']).stages.firstWhere((s) => s.stage == PlanStage.conversation);

  setUpAll(setUpPlanGoldens);

  Map<String, dynamic> dayJson(String name) => serverFixtureJson(name);
  Map<String, dynamic> windowOf(Map<String, dynamic> json) => json['window'] as Map<String, dynamic>;
  Map<String, dynamic> windowDay(Map<String, dynamic> json) => windowOf(json)['day'] as Map<String, dynamic>;
  List<Map<String, dynamic>> rows(Map<String, dynamic> json) => (windowOf(json)['stages'] as List).cast<Map<String, dynamic>>();
  List<Map<String, dynamic>> cardsOf(Map<String, dynamic> json, String stage) =>
      ((json['stages'] as List).cast<Map<String, dynamic>>().firstWhere((s) => s['stage'] == stage)['cards'] as List).cast<Map<String, dynamic>>();

  void passed(Map<String, dynamic> json) {
    windowDay(json)
      ..['status'] = 'passed'
      ..['minutes_spent'] = 21;
    windowOf(json)['stages'] = [
      for (final row in rows(json)) {...row, 'state': 'done', 'done_count': null, 'total': null, 'minutes_left': null, 'share': 1.0},
    ];
  }

  void muteSound(WidgetTester tester) {
    final messenger = tester.binding.defaultBinaryMessenger;
    for (final channel in [
      const MethodChannel('flutter_tts'),
      const MethodChannel('com.denis.engstd/app_info'),
      AudioMixer.channel,
      const MethodChannel('dev.fluttercommunity.plus/connectivity'),
      const MethodChannel('dev.fluttercommunity.plus/connectivity_status'),
    ]) {
      messenger.setMockMethodCallHandler(channel, (call) async => switch (call.method) {
        'speak' => 0,
        'check' => ['wifi'],
        _ => null,
      });
      addTearDown(() => messenger.setMockMethodCallHandler(channel, null));
    }
  }

  Future<void> shoot(WidgetTester tester, String name, {bool settle = true}) async {
    if (settle) await tester.pump(const Duration(milliseconds: 400));
    expect(tester.takeException(), isNull, reason: '«$name» — ничего не переполнено');
    if (!writeShots) return;
    final boundary = tester.renderObject<RenderRepaintBoundary>(find.byKey(shotKey));
    final bytes = await tester.runAsync(() async {
      final image = await boundary.toImage(pixelRatio: 2);
      final data = await image.toByteData(format: ui.ImageByteFormat.png);
      image.dispose();
      return data;
    });
    final file = File('../backend2/docs/research/client-conv-1c/shots/$name.png')..createSync(recursive: true);
    file.writeAsBytesSync(bytes!.buffer.asUint8List());
  }

  void phone(WidgetTester tester, {Size size = frame}) {
    tester.view
      ..devicePixelRatio = 2
      ..physicalSize = size * 2
      ..padding = const FakeViewPadding(top: 52 * 2);
    addTearDown(tester.view.reset);
  }

  Future<void> pumpShot(WidgetTester tester, Widget home, {Size size = frame}) async {
    phone(tester, size: size);
    muteSound(tester);
    await tester.pumpWidget(
      RepaintBoundary(
        key: shotKey,
        child: ProviderScope(
          child: MaterialApp(
            debugShowCheckedModeBanner: false,
            theme: buildAppTheme(),
            locale: const Locale('ru'),
            localizationsDelegates: AppLocalizations.localizationsDelegates,
            supportedLocales: const [Locale('ru'), Locale('en')],
            builder: (context, child) => MediaQuery(data: MediaQuery.of(context).copyWith(disableAnimations: true), child: child!),
            home: Scaffold(backgroundColor: AppColors.ground, body: SafeArea(bottom: false, child: home)),
          ),
        ),
      ),
    );
    await tester.pump();
  }

  Future<void> pumpCardShot(WidgetTester tester, CardEnv Function() build) =>
      pumpShot(tester, KeyedSubtree(key: UniqueKey(), child: Builder(builder: (_) => sessionCardFor(build()))));

  /// The day window over [room] — the real screen, the frame's status bar and home strip.
  Future<void> pumpWindowShot(WidgetTester tester, PlanDayRoom room, {Plan? of}) async {
    tester.view
      ..devicePixelRatio = 2
      ..physicalSize = frame * 2;
    addTearDown(tester.view.reset);
    muteSound(tester);
    await tester.pumpWidget(
      RepaintBoundary(
        key: shotKey,
        child: ProviderScope(
          overrides: [
            dayRoomProvider.overrideWith((ref, address) async => room),
            lineAudioCacheProvider.overrideWithValue(RecordingLines()),
          ],
          child: MaterialApp(
            debugShowCheckedModeBanner: false,
            theme: buildAppTheme(),
            locale: const Locale('ru'),
            supportedLocales: const [Locale('ru'), Locale('en')],
            localizationsDelegates: AppLocalizations.localizationsDelegates,
            builder: (context, child) => MediaQuery(
              data: MediaQuery.of(context).copyWith(disableAnimations: true, padding: kWindowInsets, viewPadding: kWindowInsets),
              child: child!,
            ),
            home: DayWindowScreen(plan: of ?? plan, number: room.day.number),
          ),
        ),
      ),
    );
    for (var i = 0; i < 4; i++) {
      await tester.pump(const Duration(milliseconds: 50));
    }
  }

  /// The real session screen of day [number] over [raw], at its first unfinished stage's entry; [stageMinutes] — the
  /// stage's minutes as the server's answer reply carries them.
  Future<void> pumpSessionShot(WidgetTester tester, Map<String, dynamic> raw, {int number = 1, int stageMinutes = 6}) async {
    phone(tester);
    muteSound(tester);
    await tester.pumpWidget(
      RepaintBoundary(
        key: shotKey,
        child: ProviderScope(
          overrides: [
            appDatabaseProvider.overrideWith((ref) {
              final db = AppDatabase.forTesting(NativeDatabase.memory());
              ref.onDispose(db.close);
              return db;
            }),
            // The doctor's day has live audio addresses: a cache that loads nothing leaves no retry behind the shot.
            lineAudioCacheProvider.overrideWithValue(RecordingLines()),
            speechRecognizerProvider.overrideWithValue(SilentRecognizer()),
            authControllerProvider.overrideWith(_Auth.new),
            appVersionProvider.overrideWith((ref) async => null),
          ],
          child: MaterialApp(
            debugShowCheckedModeBanner: false,
            theme: buildAppTheme(),
            locale: const Locale('ru'),
            localizationsDelegates: AppLocalizations.localizationsDelegates,
            supportedLocales: const [Locale('ru'), Locale('en')],
            builder: (context, child) => MediaQuery(data: MediaQuery.of(context).copyWith(disableAnimations: true), child: child!),
            home: SessionScreen(plan: plan, number: number, backend: _Backend(raw, plan, stageMinutes: stageMinutes)),
          ),
        ),
      ),
    );
    for (var i = 0; i < 4; i++) {
      await tester.pump(const Duration(milliseconds: 100));
    }
  }

  Future<TalkStand> pumpTalkShot(
    WidgetTester tester,
    PlanConversation document, {
    PlanConversation? next,
    bool speaking = false,
    bool hints = true,
    Completer<void>? hold,
    Object? failMove,
  }) async {
    final probe = TalkProbe()
      ..documents.addAll([document, next ?? document])
      ..holdMove = hold
      ..failMove = failMove;
    final voice = HeldVoice();
    final mics = <SessionMic>[];
    final talk = ConversationController(backend: FakeTalkBackend(probe), planId: 'ulid-plan', day: 1, voice: voice, hints: hints);
    addTearDown(talk.dispose);
    await pumpShot(
      tester,
      TalkView(
        controller: talk,
        scene: doctor.scene,
        voice: voice,
        makeMic: () {
          final mic = SessionMic(recognizer: ListeningRecognizer(), localeId: 'en_US', expected: '');
          mics.add(mic);
          return mic;
        },
        onSummary: () {},
        onClose: () {},
      ),
    );
    unawaited(talk.open());
    await tester.pump();
    await tester.pump();
    if (!speaking) {
      voice.finish();
      await tester.pump();
      await tester.pump();
    }
    return (talk: talk, voice: voice, mics: mics);
  }

  /// Said through the debug field «what I heard» — the road of a real recording: the pause, then the move.
  Future<void> sayInTalk(WidgetTester tester, String text) async {
    await tester.tap(find.byKey(const ValueKey('talk-mic')));
    await tester.pump();
    await enterHeard(tester, text);
    await tester.pump(const Duration(milliseconds: 1600));
    await tester.pump();
  }

  // ── 37-5 · вход в разговор с блоком фраз ──────────────────────────────────────────────────────
  /// The day's talk entry — the talk row of day-doctor.json as the server sends it: its title, minutes, scenes and six
  /// phrases — on a phone [size].
  Future<void> pumpDayEntry(WidgetTester tester, {Size size = frame}) {
    final row = talkRow('day-doctor');
    return pumpShot(
      tester,
      TalkEntryView(
        scene: doctor.scene,
        minutes: row.minutes,
        title: row.talkTitleNative,
        scenesCount: row.scenesCount,
        targets: row.targets,
        rehearsal: false,
        noHints: false,
        onNoHints: (_) {},
        onStart: () {},
        onBack: () {},
      ),
      size: size,
    );
  }

  double photoBand(WidgetTester tester) => tester.getSize(find.byKey(const ValueKey('talk-entry-photo'))).height;

  testWidgets('01 37-5 вход в разговор дня — «Скажи в разговоре»', (tester) async {
    await pumpDayEntry(tester);
    await shoot(tester, '01-37-5-entry-day');
  });

  // The photo band stands 64 whole or not at all (приёмка 22.09, третий заход): under a title of two lines and the cut
  // third phrase it needs 84 of air (64 and its 20) — the 390 phone has it from 909 high, not yet at 874.
  testWidgets('01b 37-5 вход дня на 390 × 874 — полосе фото ещё мало места', (tester) async {
    await pumpDayEntry(tester, size: const Size(390, 874));
    expect(photoBand(tester), 0);
    await shoot(tester, '01b-37-5-entry-day-874');
  });

  testWidgets('01c 37-5 вход дня на 390 × 909 — полоса фото 64 целиком', (tester) async {
    await pumpDayEntry(tester, size: const Size(390, 909));
    expect(photoBand(tester), 64);
    await shoot(tester, '01c-37-5-entry-day-909');
  });

  testWidgets('02 37-5b репетиция — «Разговор целиком · 2 сцены», «Скажи в разговоре»', (tester) async {
    final row = talkRow('day-rehearsal');
    await pumpShot(
      tester,
      TalkEntryView(
        scene: booking,
        minutes: row.minutes,
        title: row.talkTitleNative,
        scenesCount: row.scenesCount,
        targets: row.targets,
        rehearsal: true,
        noHints: false,
        onNoHints: (_) {},
        onStart: () {},
        onBack: () {},
      ),
    );
    await shoot(tester, '02-37-5b-entry-rehearsal');
  });

  // ── 37-6…37-11 · лента с полоской фраз ────────────────────────────────────────────────────────
  testWidgets('03 37-6 роль говорит — текст открыт, полоска', (tester) async {
    await pumpTalkShot(tester, beforeRescue, speaking: true);
    await shoot(tester, '03-37-6-speaking');
  });

  testWidgets('04 37-7 твоя очередь — ждём тапа', (tester) async {
    await pumpTalkShot(tester, beforeRescue);
    await shoot(tester, '04-37-7-your-turn');
    await settleTalk(tester);
  });

  testWidgets('05 37-7b слушаю', (tester) async {
    await pumpTalkShot(tester, beforeRescue);
    await tester.tap(find.byKey(const ValueKey('talk-mic')));
    await tester.pump();
    await shoot(tester, '05-37-7b-listening');
    await tester.tap(find.byKey(const ValueKey('talk-mic')));
    await settleTalk(tester);
  });

  testWidgets('06 37-7c подсказка после 5 с', (tester) async {
    await pumpTalkShot(tester, beforeRescue);
    await tester.pump(const Duration(seconds: 6));
    await shoot(tester, '06-37-7c-hint-chip');
    await settleTalk(tester);
  });

  testWidgets('07 37-7d после «Не понял» — свой пузырь «Sorry?»', (tester) async {
    await pumpTalkShot(tester, openV2);
    await shoot(tester, '07-37-7d-after-rescue-sorry');
    await settleTalk(tester);
  });

  testWidgets('08 37-7 «Без подсказок»', (tester) async {
    final blind = talkV2('talk_day_open_v2', (json) {
      json['turns'] = (json['turns'] as List).take(3).toList();
      (json['hints'] as Map<String, dynamic>)
        ..['enabled'] = false
        ..['native'] = null;
    });
    await pumpTalkShot(tester, blind, hints: false);
    await tester.pump(const Duration(seconds: 6));
    await shoot(tester, '08-37-7-no-hints');
    await settleTalk(tester);
  });

  testWidgets('08b 37-7 «Без подсказок» — чип «текст» открыл текст этой реплики', (tester) async {
    final blind = talkV2('talk_day_open_v2', (json) {
      json['turns'] = (json['turns'] as List).take(3).toList();
      (json['hints'] as Map<String, dynamic>)
        ..['enabled'] = false
        ..['native'] = null;
    });
    await pumpTalkShot(tester, blind, hints: false);
    await tester.tap(find.byKey(const ValueKey('talk-open-text')).last);
    await tester.pump();
    expect(find.byKey(const ValueKey('talk-open-text')), findsOneWidget, reason: 'the first question keeps its chip');
    await shoot(tester, '08b-37-7-no-hints-text-opened');
    await settleTalk(tester);
  });

  testWidgets('09 37-8 врач думает', (tester) async {
    final hold = Completer<void>();
    await pumpTalkShot(tester, beforeRescue, hold: hold);
    await sayInTalk(tester, 'He has had it for three days.');
    await shoot(tester, '09-37-8-thinking', settle: false);
    hold.complete();
    await settleTalk(tester);
  });

  testWidgets('10 37-8b ответ врача — пузырь с волной, полоска «2 из 5» вспыхнула', (tester) async {
    final stand = await pumpTalkShot(tester, openV2, next: afterMove);
    await sayInTalk(tester, 'He has had it for three days.');
    expect(find.text(nb('фразы · 2 из 5')), findsOneWidget);
    expect(find.byKey(const ValueKey('talk-strip-check')), findsOneWidget, reason: 'the flash is on');
    await shoot(tester, '10-37-8b-answer-strip-flash', settle: false);
    stand.voice.finish();
    await settleTalk(tester);
  });

  testWidgets('11 37-8d лист фраз открыт', (tester) async {
    await pumpTalkShot(tester, afterMove);
    await tester.tap(find.byKey(const ValueKey('talk-strip')));
    await tester.pumpAndSettle();
    await shoot(tester, '11-37-8d-phrase-sheet');
    await tester.tap(find.byKey(const ValueKey('talk-phrase-sheet-close')));
    await tester.pumpAndSettle();
    await settleTalk(tester);
  });

  testWidgets('12 37-9 перебил врача', (tester) async {
    await pumpTalkShot(tester, beforeRescue, speaking: true);
    await tester.tap(find.byKey(const ValueKey('talk-mic')));
    await tester.pump();
    await tester.pump();
    await shoot(tester, '12-37-9-interrupted');
    await settleTalk(tester);
  });

  testWidgets('13 37-10 не расслышал', (tester) async {
    await pumpTalkShot(tester, beforeRescue);
    await tester.tap(find.byKey(const ValueKey('talk-mic')));
    await tester.pump();
    await tester.tap(find.byKey(const ValueKey('talk-mic')));
    await tester.pump();
    await tester.pump();
    await shoot(tester, '13-37-10-unheard');
    await settleTalk(tester);
  });

  testWidgets('14 37-10b связь пропала', (tester) async {
    await pumpTalkShot(tester, beforeRescue, failMove: DioException(requestOptions: RequestOptions(path: '/x'), type: DioExceptionType.connectionError));
    await sayInTalk(tester, 'He has had it for three days.');
    await shoot(tester, '14-37-10b-offline');
    await settleTalk(tester);
  });

  testWidgets('15 37-10c врач не отвечает', (tester) async {
    await pumpTalkShot(
      tester,
      beforeRescue,
      failMove: DioException(
        requestOptions: RequestOptions(path: '/x'),
        type: DioExceptionType.badResponse,
        response: Response(requestOptions: RequestOptions(path: '/x'), statusCode: 503, data: {'code': 'plan_conversation_unavailable'}),
      ),
    );
    await sayInTalk(tester, 'He has had it for three days.');
    await shoot(tester, '15-37-10c-agent-silent');
    await settleTalk(tester);
  });

  testWidgets('16 37-11 прощание — полоска с итогом фраз', (tester) async {
    await pumpTalkShot(tester, endedDay);
    await shoot(tester, '16-37-11-goodbye');
    await settleTalk(tester);
  });

  // ── 37-12 ─────────────────────────────────────────────────────────────────────────────────────
  testWidgets('17 37-12 итог разговора дня', (tester) async {
    await pumpShot(tester, TalkSummaryView(talk: endedDay, scene: doctor.scene, voice: HeldVoice(), onAgain: () {}, onNext: () {}, onClose: () {}));
    await shoot(tester, '17-37-12-summary-day');
  });

  testWidgets('18 37-12b итог репетиции', (tester) async {
    await pumpShot(
      tester,
      TalkSummaryView(talk: rehearsalTalk, scene: visit, voice: HeldVoice(), onAgain: () {}, onNext: () {}, onClose: () {}),
    );
    await shoot(tester, '18-37-12b-summary-rehearsal');
  });

  // ── 30-6 · итог этапа — один компонент (Слова, Диалог, Говорю сам) ──────────────────────────────
  /// The doctor's day at the last card of [stage]: every card of the stages before it and of it but the last is
  /// answered, and [spoil] writes the answers the three lines count; the next stage's minutes are the server's own
  /// (`minutes` of every row, BACK-TAILS-2).
  Map<String, dynamic> atLastCard(String stage, List<String> before, void Function(Map<String, dynamic> json) spoil) {
    final json = sessionFixtureJson('day-doctor');
    for (final s in [...before, stage]) {
      final cards = cardsOf(json, s);
      for (final c in s == stage ? cards.take(cards.length - 1) : cards) {
        c
          ..['result'] = 'passed'
          ..['attempts'] = 1
          ..['returns'] = false;
      }
    }
    spoil(json);
    return json;
  }

  void answer(Map<String, dynamic> json, String stage, int position, String result, {int attempts = 1, bool returns = false, Map<String, dynamic>? response}) {
    final c = cardsOf(json, stage).firstWhere((x) => x['position'] == position);
    c
      ..['result'] = result
      ..['attempts'] = attempts
      ..['returns'] = returns;
    if (response != null) c['response'] = response;
  }

  /// Through the stage's entry to its last card, and past it to the summary; [pick] — the key of the chip the last card
  /// is answered with, where it asks for one (a dialogue card without a microphone answers with chips).
  Future<void> finishStage(WidgetTester tester, {String? pick}) async {
    final go = find.text('Продолжить').evaluate().isNotEmpty ? find.text('Продолжить') : find.text('Начать');
    if (go.evaluate().isEmpty) {
      debugPrint('[shots] on screen: ${[for (final e in find.byType(Text).evaluate()) (e.widget as Text).data].join(' | ')}');
    }
    await tester.tap(go);
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 400));
    if (pick != null) {
      await tester.ensureVisible(find.byKey(ValueKey(pick)));
      await tester.tap(find.byKey(ValueKey(pick)));
      await tester.pump(const Duration(milliseconds: 400));
    }
    // The last card, whatever its kind: «Дальше» once it stands, else a skip where the card has one, else an option (a
    // choice, or the check after a skip) — until the stage summary stands.
    final option = find.byWidgetPredicate((w) => w.key is ValueKey<String> && (w.key! as ValueKey<String>).value.startsWith('option-'));
    for (var step = 0; step < 8 && find.byKey(const ValueKey('stage-summary-title')).evaluate().isEmpty; step++) {
      // A dialogue card without a microphone answers with a chip (a picked one takes no second tap).
      if (find.byKey(const ValueKey('chip-0')).evaluate().isNotEmpty) {
        await tester.ensureVisible(find.byKey(const ValueKey('chip-0')));
        await tester.tap(find.byKey(const ValueKey('chip-0')), warnIfMissed: false);
        await tester.pump(const Duration(milliseconds: 400));
      }
      final exit = [
        find.text('Дальше'),
        find.byKey(const ValueKey('session-skip')),
        find.byKey(const ValueKey('exit-skip')),
        option,
      ].firstWhere((f) => f.evaluate().isNotEmpty, orElse: () => find.byKey(const ValueKey('none')));
      if (exit.evaluate().isNotEmpty) await tester.tap(exit.first, warnIfMissed: false);
      for (var i = 0; i < 5; i++) {
        await tester.pump(const Duration(milliseconds: 200));
      }
    }
    if (find.byKey(const ValueKey('stage-summary-title')).evaluate().isEmpty) {
      debugPrint('[shots] stuck on: ${[for (final e in find.byType(Text).evaluate()) (e.widget as Text).data].join(' | ')}');
      debugPrint('[shots] keys: ${[
        for (final e in find.byWidgetPredicate((w) => w.key is ValueKey<String>).evaluate()) (e.widget.key! as ValueKey<String>).value,
      ].join(' ')}');
    }
  }

  testWidgets('19 30-6 «Слова пройдены»', (tester) async {
    final json = atLastCard('words', const [], (json) {
      answer(json, 'words', 3, 'skipped', attempts: 2, returns: true);
      answer(json, 'words', 6, 'passed', returns: true);
      answer(json, 'words', 12, 'failed', returns: true);
    });
    await pumpSessionShot(tester, json);
    await finishStage(tester);
    expect(tester.widget<Text>(find.byKey(const ValueKey('stage-summary-title'))).data, startsWith('Слова пройдены'));
    await shoot(tester, '19-30-6-words');
  });

  testWidgets('20 30-6 «Диалог пройден»', (tester) async {
    // The ask of x8 is answered too, and x5's answer is the card left: an ask holds its answer for its check, and the
    // check waits for a line the shot's voice never finishes.
    final json = atLastCard('dialogue', const ['words', 'phrases'], (json) {
      answer(json, 'dialogue', 2, 'passed', attempts: 2, response: {'mode': 'voice_hint'});
      answer(json, 'dialogue', 4, 'passed', response: {'mode': 'chips', 'filler_index': 0});
      answer(json, 'dialogue', 6, 'passed', response: {'mode': 'voice_hint'});
      answer(json, 'dialogue', 8, 'hinted', response: {'mode': 'voice_hint'});
      answer(json, 'dialogue', 13, 'passed', response: {'mode': 'voice_hint'});
      final x5 = cardsOf(json, 'dialogue').firstWhere((c) => c['position'] == 11);
      x5
        ..['result'] = null
        ..['attempts'] = 0;
    });
    await pumpSessionShot(tester, json);
    await finishStage(tester, pick: 'chip-0');
    expect(tester.widget<Text>(find.byKey(const ValueKey('stage-summary-title'))).data, startsWith('Диалог пройден'));
    await shoot(tester, '20-30-6-dialogue');
  });

  testWidgets('21 30-6 «Говорю сам — пройдено»', (tester) async {
    final json = atLastCard('speak', const ['words', 'phrases', 'dialogue', 'listen'], (json) {
      answer(json, 'speak', 2, 'hinted', returns: true);
      answer(json, 'speak', 5, 'skipped', attempts: 2, returns: true);
    });
    await pumpSessionShot(tester, json);
    await finishStage(tester);
    expect(tester.widget<Text>(find.byKey(const ValueKey('stage-summary-title'))).data, nbPassed('Говорю сам — пройдено · 6 минут'));
    await shoot(tester, '21-30-6-speak');
  });

  // ── 35-2 · 32-1 · 35-3 · 35-4 ─────────────────────────────────────────────────────────────────
  testWidgets('22 35-2 не зачтено — строка судьи, «услышал», чип сразу под ними', (tester) async {
    final answerCard = doctor.stageOf(PlanStage.speak)!.cards.firstWhere(
      (c) => c.kind == SessionKind.speakAnswer && (c.payload as SpeakAnswerPayload).partnerLine != null,
    );
    final probe = CardProbe()
      ..verdict = (_) => const SessionJudgeOutcome(
        accepted: false,
        reasonNative: 'Ты не сказал, где болит',
        attempts: 1,
        heard: 'It hurts in his car',
      );
    await pumpCardShot(tester, () => probeEnv(answerCard, probe, day: doctor));
    await sayDebug(tester, 'It hurts in his car');
    await tester.pump();
    await shoot(tester, '22-35-2-miss-chip');
    await settleCard(tester);
  });

  testWidgets('23 32-1 значение выбрано — плашка бумагой в латуни', (tester) async {
    final card = fixtureCard(doctor, SessionKind.phraseIntro);
    final p = card.payload as PhraseIntroPayload;
    await pumpCardShot(tester, () => probeEnv(card, CardProbe(), day: doctor));
    final other = p.frame.fillers.firstWhere((f) => f.index != p.said.fillerIndex);
    await tester.tap(find.byKey(ValueKey('meaning-${other.index}')));
    await tester.pump();
    await shoot(tester, '23-32-1-meaning-selected');
    await settleCard(tester);
  });

  testWidgets('24 35-3 эхо без partner_line — своя реплика открыта с переводом', (tester) async {
    final card = fixtureCardEdited('day-doctor', 'speak_echo', (p) => p.remove('partner_line'));
    final own = (card.payload as SpeakEchoPayload).ownLine;
    await pumpCardShot(tester, () => probeEnv(card, CardProbe(), day: doctor));
    await tester.pump(const Duration(milliseconds: 300));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 3000));
    await tester.pump();
    await sayDebug(tester, own.textTarget.replaceAll(RegExp(r'[,.?!]'), ''));
    await tester.pump(const Duration(milliseconds: 250));
    await shoot(tester, '24-35-3-echo-own-line');
    await settleCard(tester);
  });

  testWidgets('25 35-4 «Повтори свою реплику» без partner_line', (tester) async {
    final card = fixtureCardEdited('day-doctor', 'speak_retell', (p) => p.remove('partner_line'));
    await pumpCardShot(tester, () => probeEnv(card, CardProbe(), day: doctor));
    await sayDebug(tester, 'Do we need a follow up appointment');
    await tester.pump();
    await shoot(tester, '25-35-4-retell-own-line');
    await settleCard(tester);
  });

  // ── 37-1 · 37-2 · окно: минуты, «Из каких сцен/дней», «Повторение», «Повторить разговор» ───────────
  testWidgets('26 37-1 репетиция — идёт, минуты рядов, «Из каких сцен»', (tester) async {
    await pumpWindowShot(tester, PlanDayRoom.fromJson(dayJson('day-rehearsal')));
    await shoot(tester, '26-37-1-rehearsal-minutes-sources');
  });

  testWidgets('27 37-2 повторение — «Повторение», минуты рядов, «Из каких дней»', (tester) async {
    await pumpWindowShot(tester, PlanDayRoom.fromJson(dayJson('day-review')));
    await shoot(tester, '27-37-2-review-repetition-sources');
  });

  testWidgets('28 37-1 репетиция пройдена — «Повторить разговор» кнопкой', (tester) async {
    // The fixtures carry no walked rehearsal: the reply of day-rehearsal.json, passed, as the server writes one.
    final json = dayJson('day-rehearsal');
    passed(json);
    windowOf(json)
      ..['allowed_action'] = null
      ..['talk_again'] = true;
    await pumpWindowShot(tester, PlanDayRoom.fromJson(json));
    await shoot(tester, '28-37-1-passed-talk-again');
  });

  testWidgets('29 23-0a день пройден — «Ещё раз» и ссылка «Повторить разговор»', (tester) async {
    final json = planFixture('room_window_passed');
    final data = (json['data'] as Map<String, dynamic>?) ?? json;
    windowOf(data)['talk_again'] = true;
    await pumpWindowShot(tester, PlanDayRoom.fromJson(json), of: planFrom('plan_window'));
    await shoot(tester, '29-23-0a-passed-again-and-talk-again');
  });

  testWidgets('30 23-0a день идёт — «≈ N мин» у рядов впереди', (tester) async {
    // day-doctor.json as the server sends it: «Слова» under way, every row with its planned minutes.
    await pumpWindowShot(tester, PlanDayRoom.fromJson(dayJson('day-doctor')), of: planFrom('plan_window'));
    await shoot(tester, '30-23-0a-in-progress-row-minutes');
  });

  // ── третий заход (приёмка снимков 22.09): число со словом, вход этапа по state сервера, подсказка таба ─────────
  testWidgets('31 30-6 двузначные минуты — «Слушаю и отвечаю — пройдено · 13 минут» не рвётся', (tester) async {
    // The longest title of 30-6; the stage's minutes are the server's answer reply (`stage.minutes_spent`).
    final json = atLastCard('listen', const ['words', 'phrases', 'dialogue'], (json) {});
    await pumpSessionShot(tester, json, stageMinutes: 13);
    await finishStage(tester);
    expect(tester.widget<Text>(find.byKey(const ValueKey('stage-summary-title'))).data, nbPassed('Слушаю и отвечаю — пройдено · 13 минут'));
    await shoot(tester, '31-30-6-listen-two-digit-minutes');
  });

  testWidgets('32 30-1 вход «Вспомнить» — ряд «идёт» по state сервера, не по счёту карточек', (tester) async {
    // day-rehearsal.json: «Вспомнить» is `current` while none of its eleven cards is answered yet.
    await pumpSessionShot(tester, dayJson('day-rehearsal'), number: 3);
    final row = find.ancestor(of: find.text('идёт'), matching: find.byType(Row)).first;
    expect(find.descendant(of: row, matching: find.text('Вспомнить')), findsOneWidget);
    expect(find.text('не начат'), findsNothing);
    await shoot(tester, '32-30-1-recall-entry-in-progress');
  });

  testWidgets('33 21-2c таб «План» на дне повторения — «Начни с этапа «Повторение»»', (tester) async {
    // plan_rehearsal.json: day 2 (the review) is today; its room is day-review.json, whose first row is «Повторение».
    tester.view
      ..devicePixelRatio = 2
      ..physicalSize = frame * 2;
    addTearDown(tester.view.reset);
    muteSound(tester);
    await tester.pumpWidget(
      RepaintBoundary(
        key: shotKey,
        child: planGoldenApp(
          ProviderScope(
            overrides: [
              planTabProvider.overrideWith(
                () => _StubTab(PlanTabState(plan: plan, room: PlanDayRoom.fromJson(dayJson('day-review')), finished: const [])),
              ),
            ],
            child: const Scaffold(extendBody: true, backgroundColor: AppColors.ground, body: PlanTabScreen()),
          ),
          hints: const PlanHints(tabShown: false, closeShown: false, howShown: false),
        ),
      ),
    );
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 400));
    expect(find.text('Начни с этапа «Повторение». Остальные откроются по порядку'), findsOneWidget);
    await shoot(tester, '33-21-2c-review-day-first-stage-hint');
  });
}

class _StubTab extends PlanTabController {
  _StubTab(this._state);

  final PlanTabState _state;

  @override
  Future<PlanTabState> build() async => _state;

  @override
  Future<void> refresh({bool silent = true}) async {}
}

class _Backend implements SessionBackend {
  _Backend(this.raw, this._plan, {this.stageMinutes = 6});

  final Map<String, dynamic> raw;
  final Plan _plan;
  final int stageMinutes;

  @override
  Future<SessionDay> day(String planId, int number) async => SessionDay.fromJson(_served());

  /// The window's rows as the server derives them from the answers (BACK-TAILS-2): a stage with every card answered —
  /// `done`, the first one short of that — `current`, the rest — `locked`. The session re-reads the day after each
  /// stage, and without this the shots' stand would keep answering the state it was loaded with.
  Map<String, dynamic> _served() {
    final cards = {
      for (final s in (raw['stages'] as List).cast<Map<String, dynamic>>())
        s['stage'] as String: (s['cards'] as List).cast<Map<String, dynamic>>(),
    };
    var current = false;
    for (final row in ((raw['window'] as Map<String, dynamic>)['stages'] as List).cast<Map<String, dynamic>>()) {
      final own = cards[row['stage']] ?? const <Map<String, dynamic>>[];
      final done = own.isNotEmpty && own.every((c) => c['result'] != null);
      row['state'] = done ? 'done' : (current ? 'locked' : 'current');
      if (!done) current = true;
    }
    return raw;
  }

  @override
  Future<void> open(String planId, int number) async {}

  @override
  Future<Plan> plan(String planId) async => _plan;

  @override
  Future<Plan> retryLesson(String planId, String sceneId) async => _plan;

  @override
  Future<SessionAnswerOutcome> answer(String planId, int number, String cardId, SessionAnswer answer) async {
    final card = [
      for (final s in (raw['stages'] as List).cast<Map<String, dynamic>>()) ...(s['cards'] as List).cast<Map<String, dynamic>>(),
    ].firstWhere((c) => c['id'] == cardId);
    card
      ..['result'] = answer.result.wire
      ..['attempts'] = answer.attempts;
    return SessionAnswerOutcome.fromJson({
      'card': {...card, 'result': answer.result.wire, 'attempts': answer.attempts, 'returns': false},
      'requeued': null,
      'unit': {'kind': card['unit']['kind'], 'ref': card['unit']['ref'], 'returns_tomorrow': false, 'returns_day': null},
      'day': {'cards_total': 78, 'cards_done': 40, 'minutes_spent': 14},
      'stage': {'stage': card['stage'], 'minutes_spent': stageMinutes},
    });
  }

  @override
  Future<SessionJudgeOutcome> judge(String planId, int number, String cardId, {required String heard, required bool hinted}) =>
      throw UnimplementedError();

  @override
  Future<SessionDay> close(String planId, int number) => throw UnimplementedError();
}

class _Auth extends AuthController {
  @override
  Future<AppUser?> build() async => AppUser(id: '01TEST', name: 'Тест');
}
