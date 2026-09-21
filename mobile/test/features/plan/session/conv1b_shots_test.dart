import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:ui' as ui;

import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/app_version.dart';
import 'package:eng_std/data/audio_mixer.dart';
import 'package:eng_std/data/line_audio.dart';
import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart' show AppUser;
import 'package:eng_std/data/plan/conversation/conversation_models.dart';
import 'package:eng_std/data/plan/day_providers.dart';
import 'package:eng_std/data/plan/plan_models.dart';
import 'package:eng_std/data/plan/session/dialogue_feed.dart';
import 'package:eng_std/data/plan/session/session_day.dart';
import 'package:eng_std/data/plan/session/session_models.dart';
import 'package:eng_std/data/plan/session/session_outcomes.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/plan/conversation/conversation_controller.dart';
import 'package:eng_std/features/plan/conversation/talk_entry.dart';
import 'package:eng_std/features/plan/conversation/talk_screen.dart';
import 'package:eng_std/features/plan/conversation/talk_summary.dart';
import 'package:eng_std/features/plan/day/day_window_screen.dart';
import 'package:eng_std/features/plan/session/cards/card_host.dart';
import 'package:eng_std/features/plan/session/cards/card_kit.dart';
import 'package:eng_std/features/plan/session/session_controller.dart';
import 'package:eng_std/features/plan/session/session_mic.dart';
import 'package:eng_std/features/plan/session/session_screen.dart';
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../../support/day_window_harness.dart' show RecordingLines, kWindowInsets;
import '../../../support/plan_goldens.dart' show planFrom, setUpPlanGoldens;
import '../../../support/session_harness.dart';
import '../../../support/talk_harness.dart';

/// СНИМКИ НАРЯДА CLIENT-CONV-1b — каждый изменённый экран кадром 390 × 844 @2×, для архитектора ДО сборки на телефон.
///
/// Не golden-тест: сравнивать не с чем. Без ключа файл проверяет, что каждый экран рисуется и ничего не переполняет.
///
/// ```bash
/// flutter test test/features/plan/session/conv1b_shots_test.dart --dart-define=CONV1B_SHOTS=true
/// ```
/// Кадры ложатся в `../backend2/docs/research/client-conv-1b/shots/`.
void main() {
  const writeShots = bool.fromEnvironment('CONV1B_SHOTS');
  const frame = Size(390, 844);
  final shotKey = GlobalKey();
  final doctor = sessionFixture('day-doctor');
  final rehearsalDay = sessionFixture('day-rehearsal');
  final plan = planFrom('plan_rehearsal');
  final open = talkFixture('conversation-day-open');
  final ended = talkFixture('conversation-day-ended');
  final rehearsalTalk = talkFixture('conversation-rehearsal-ended');
  const phrases = {'p1': 'My son has a fever.', 'p2': 'He has had it for three days.'};

  setUpAll(setUpPlanGoldens);

  Map<String, dynamic> dayJson(String name) =>
      jsonDecode(File('../backend2/docs/fixtures/$name.json').readAsStringSync()) as Map<String, dynamic>;
  Map<String, dynamic> windowDay(Map<String, dynamic> json) => (json['window'] as Map<String, dynamic>)['day'] as Map<String, dynamic>;
  Map<String, dynamic> notDealt(Map<String, dynamic> json) {
    for (final s in (json['stages'] as List).cast<Map<String, dynamic>>()) {
      s['cards'] = <dynamic>[];
    }
    windowDay(json)['status'] = 'not_started';
    (json['window'] as Map<String, dynamic>)['allowed_action'] = 'start';
    (json['window'] as Map<String, dynamic>)['stages'] = [
      for (final row in ((json['window'] as Map<String, dynamic>)['stages'] as List).cast<Map<String, dynamic>>())
        {...row, 'state': 'locked', 'done_count': null, 'total': null, 'minutes_left': null, 'share': 0.0},
    ];
    return json;
  }

  void muteSound(WidgetTester tester) {
    final messenger = tester.binding.defaultBinaryMessenger;
    for (final channel in [
      const MethodChannel('flutter_tts'),
      const MethodChannel('com.denis.engstd/app_info'),
      AudioMixer.channel,
      // The session watches the network; a shot's real async (the PNG encoding) lets that stream open.
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

  Future<void> shoot(WidgetTester tester, String name) async {
    await tester.pump(const Duration(milliseconds: 400));
    expect(tester.takeException(), isNull, reason: '«$name» — ничего не переполнено');
    if (!writeShots) return;
    final boundary = tester.renderObject<RenderRepaintBoundary>(find.byKey(shotKey));
    final bytes = await tester.runAsync(() async {
      final image = await boundary.toImage(pixelRatio: 2);
      final data = await image.toByteData(format: ui.ImageByteFormat.png);
      image.dispose();
      return data;
    });
    final file = File('../backend2/docs/research/client-conv-1b/shots/$name.png')..createSync(recursive: true);
    file.writeAsBytesSync(bytes!.buffer.asUint8List());
  }

  void phone(WidgetTester tester) {
    tester.view
      ..devicePixelRatio = 2
      ..physicalSize = frame * 2
      ..padding = const FakeViewPadding(top: 52 * 2);
    addTearDown(tester.view.reset);
  }

  Future<void> pumpShot(WidgetTester tester, Widget home) async {
    phone(tester);
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

  /// The day window (37-1, 37-2) over [room] — the real screen, the frame's status bar and home strip.
  Future<void> pumpWindowShot(WidgetTester tester, PlanDayRoom room) async {
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
            home: DayWindowScreen(plan: plan, number: room.day.number),
          ),
        ),
      ),
    );
    for (var i = 0; i < 4; i++) {
      await tester.pump(const Duration(milliseconds: 50));
    }
  }

  /// The real session screen of the rehearsal (day 3) over [raw], started at its entry.
  Future<_Backend> pumpSessionShot(WidgetTester tester, Map<String, dynamic> raw) async {
    phone(tester);
    muteSound(tester);
    final backend = _Backend(raw, plan);
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
            lineAudioCacheProvider.overrideWithValue(LineAudioCache(directory: Directory.systemTemp)),
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
            home: SessionScreen(plan: plan, number: 3, backend: backend),
          ),
        ),
      ),
    );
    for (var i = 0; i < 4; i++) {
      await tester.pump(const Duration(milliseconds: 100));
    }
    return backend;
  }

  Future<TalkStand> pumpTalkShot(WidgetTester tester, PlanConversation document, {bool speaking = false, bool hints = true, Completer<void>? hold}) async {
    final probe = TalkProbe()
      ..documents.addAll([document, document])
      ..holdMove = hold;
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
        phraseTexts: phrases,
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

  // ── 37-1 · 37-2 · окно повторения и репетиции ─────────────────────────────────────────────────
  testWidgets('01 37-1 репетиция — не начата', (tester) async {
    final json = notDealt(dayJson('day-rehearsal'));
    (json['day'] as Map<String, dynamic>)['slot'] = {'code': 'date', 'date': '2026-09-24', 'label_native': null};
    await pumpWindowShot(tester, PlanDayRoom.fromJson(json));
    await shoot(tester, '01-37-1-rehearsal-not-started');
  });

  testWidgets('02 37-1 репетиция — идёт', (tester) async {
    await pumpWindowShot(tester, PlanDayRoom.fromJson(dayJson('day-rehearsal')));
    await shoot(tester, '02-37-1-rehearsal-in-progress');
  });

  testWidgets('03 37-1 репетиция — пройдена', (tester) async {
    final json = dayJson('day-rehearsal');
    windowDay(json)
      ..['status'] = 'passed'
      ..['minutes_spent'] = 21;
    // The rehearsal has no «Говорю сам» to replay: the server offers no action on a passed one (`WindowStatus::action`).
    (json['window'] as Map<String, dynamic>)['allowed_action'] = null;
    (json['window'] as Map<String, dynamic>)['stages'] = [
      for (final row in ((json['window'] as Map<String, dynamic>)['stages'] as List).cast<Map<String, dynamic>>())
        {...row, 'state': 'done', 'done_count': null, 'total': null, 'minutes_left': null, 'share': 1.0},
    ];
    await pumpWindowShot(tester, PlanDayRoom.fromJson(json));
    await shoot(tester, '03-37-1-rehearsal-passed');
  });

  testWidgets('04 37-2 повторение — не начато', (tester) async {
    await pumpWindowShot(tester, PlanDayRoom.fromJson(notDealt(dayJson('day-review'))));
    await shoot(tester, '04-37-2-review-not-started');
  });

  testWidgets('05 37-2 повторение — идёт', (tester) async {
    await pumpWindowShot(tester, PlanDayRoom.fromJson(dayJson('day-review')));
    await shoot(tester, '05-37-2-review-in-progress');
  });

  testWidgets('06 37-2 повторение — пройдено', (tester) async {
    final json = dayJson('day-review');
    windowDay(json)
      ..['status'] = 'passed'
      ..['minutes_spent'] = 4;
    (json['window'] as Map<String, dynamic>)['allowed_action'] = 'again';
    (json['window'] as Map<String, dynamic>)['stages'] = [
      for (final row in ((json['window'] as Map<String, dynamic>)['stages'] as List).cast<Map<String, dynamic>>())
        {...row, 'state': 'done', 'done_count': null, 'total': null, 'minutes_left': null, 'share': 1.0},
    ];
    await pumpWindowShot(tester, PlanDayRoom.fromJson(json));
    await shoot(tester, '06-37-2-review-passed');
  });

  // ── 30-1 · 37-3 · 35-4 · 37-4 — «Вспомнить» на настоящем экране ───────────────────────────────
  testWidgets('07 30-1 вход в «Вспомнить»', (tester) async {
    await pumpSessionShot(tester, dayJson('day-rehearsal'));
    await shoot(tester, '07-30-1-recall-entry');
  });

  testWidgets('08 37-3 обзор — первая сцена', (tester) async {
    await pumpSessionShot(tester, dayJson('day-rehearsal'));
    await tester.tap(find.text('Начать'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 400));
    await shoot(tester, '08-37-3-overview-scene-1');
  });

  testWidgets('09 37-3 обзор — последняя сцена, «Дальше — повтори вслух»', (tester) async {
    await pumpSessionShot(tester, dayJson('day-rehearsal'));
    await tester.tap(find.text('Начать'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 400));
    await tester.tap(find.byKey(const ValueKey('recall-next')));
    await tester.pump();
    await shoot(tester, '09-37-3-overview-last-scene');
  });

  testWidgets('10 37-3 обзор — реплика играет', (tester) async {
    final overview = fixtureCard(rehearsalDay, SessionKind.recallScenes);
    final scene = (overview.payload as RecallScenesPayload).scenes.first;
    final voice = QuietVoice();
    await pumpCardShot(tester, () => probeEnv(overview, CardProbe(), day: rehearsalDay, voice: voice));
    voice.playing.value = 'recall-${scene.sceneId}-${scene.lines[1].ref}';
    await tester.pump();
    await shoot(tester, '10-37-3-line-playing');
    voice.playing.value = null;
  });

  testWidgets('11 35-4 пересказ на репетиции — девять реплик двух сцен', (tester) async {
    await pumpSessionShot(tester, dayJson('day-rehearsal'));
    await tester.tap(find.text('Начать'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 400));
    await tester.tap(find.byKey(const ValueKey('recall-next')));
    await tester.pump();
    await tester.tap(find.byKey(const ValueKey('recall-next')));
    for (var i = 0; i < 6; i++) {
      await tester.pump(const Duration(milliseconds: 200));
    }
    await shoot(tester, '11-35-4-retell-rehearsal');
    await tester.pump(const Duration(seconds: 2));
  });

  testWidgets('12 37-4 итог «Вспомнить»', (tester) async {
    final raw = dayJson('day-rehearsal');
    final recall = ((raw['stages'] as List).cast<Map<String, dynamic>>().firstWhere((s) => s['stage'] == 'recall')['cards'] as List)
        .cast<Map<String, dynamic>>();
    // Every card of the stage but the last retell is answered: the session stands at the stage's entry, and a skip of
    // the last one is the way to its summary.
    for (final c in recall.take(recall.length - 1)) {
      c['result'] = 'passed';
      c['attempts'] = 1;
    }
    await pumpSessionShot(tester, raw);
    final go = find.text('Продолжить').evaluate().isNotEmpty ? find.text('Продолжить') : find.text('Начать');
    await tester.tap(go);
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 400));
    await tester.tap(find.byKey(const ValueKey('session-skip')));
    for (var i = 0; i < 6; i++) {
      await tester.pump(const Duration(milliseconds: 200));
    }
    expect(find.textContaining('Вспомнил'), findsOneWidget);
    await shoot(tester, '12-37-4-recall-summary');
  });

  // ── 37-5 · вход в разговор ────────────────────────────────────────────────────────────────────
  testWidgets('13 37-5 разговор целиком — значки у правил', (tester) async {
    await pumpShot(
      tester,
      TalkEntryView(scene: plan.scenes.first, minutes: 6, rehearsal: true, noHints: false, onNoHints: (_) {}, onStart: () {}, onBack: () {}),
    );
    await shoot(tester, '13-37-5-entry-rehearsal');
  });

  testWidgets('14 37-5 вход в разговор дня', (tester) async {
    await pumpShot(
      tester,
      TalkEntryView(scene: doctor.scene, minutes: 3, rehearsal: false, noHints: false, onNoHints: (_) {}, onStart: () {}, onBack: () {}),
    );
    await shoot(tester, '14-37-5-entry-day');
  });

  // ── 37-6…37-9 · лента ─────────────────────────────────────────────────────────────────────────
  testWidgets('15 37-6 роль говорит — текст открыт, «слушай»', (tester) async {
    await pumpTalkShot(tester, open, speaking: true);
    await shoot(tester, '15-37-6-speaking-text-open');
  });

  testWidgets('16 37-7 твоя очередь — чип подсказки', (tester) async {
    await pumpTalkShot(tester, open);
    await tester.pump(const Duration(seconds: 6));
    await shoot(tester, '16-37-7-hint-chip');
    await settleTalk(tester);
  });

  testWidgets('17 37-8 ход в полёте — «слушай» над погашенной кнопкой', (tester) async {
    final hold = Completer<void>();
    final stand = await pumpTalkShot(tester, open, hold: hold);
    unawaited(stand.talk.say('He has had it for three days.'));
    await tester.pump();
    await tester.pump();
    await shoot(tester, '17-37-8-sending');
    hold.complete();
    await settleTalk(tester);
  });

  testWidgets('18 37-7 «Без подсказок» — тексты закрыты', (tester) async {
    final blind = talkFixtureEdited('conversation-day-open', (json) {
      (json['hints'] as Map<String, dynamic>)
        ..['enabled'] = false
        ..['native'] = null;
    });
    await pumpTalkShot(tester, blind, hints: false);
    await shoot(tester, '18-37-7-no-hints-closed');
    await settleTalk(tester);
  });

  // ── 37-12 · 37-12b ────────────────────────────────────────────────────────────────────────────
  testWidgets('19 37-12 итог разговора дня — галка у «понял»', (tester) async {
    await pumpShot(tester, TalkSummaryView(talk: ended, scene: doctor.scene, voice: HeldVoice(), onAgain: () {}, onNext: () {}, onClose: () {}));
    await shoot(tester, '19-37-12-summary-day');
  });

  testWidgets('20 37-12b итог репетиции — группы по сценам', (tester) async {
    await pumpShot(
      tester,
      TalkSummaryView(talk: rehearsalTalk, scene: doctor.scene, voice: HeldVoice(), onAgain: () {}, onNext: () {}, onClose: () {}),
    );
    await shoot(tester, '20-37-12b-summary-rehearsal');
  });

  testWidgets('21 37-12b итог репетиции — прокручен к «повтори перед приёмом»', (tester) async {
    await pumpShot(
      tester,
      TalkSummaryView(talk: rehearsalTalk, scene: doctor.scene, voice: HeldVoice(), onAgain: () {}, onNext: () {}, onClose: () {}),
    );
    final notSaid = find.byWidgetPredicate((w) => w.key is ValueKey<String> && (w.key! as ValueKey<String>).value.endsWith('-not-said'));
    await tester.ensureVisible(notSaid.first);
    await tester.pumpAndSettle();
    await shoot(tester, '21-37-12b-summary-rehearsal-not-said');
  });

  // ── 32 · 33 · 34 · 35 — правки прохода 21.09 ──────────────────────────────────────────────────
  testWidgets('22 32-1 тап по значению — оно в окне', (tester) async {
    final card = fixtureCard(doctor, SessionKind.phraseIntro);
    final p = card.payload as PhraseIntroPayload;
    await pumpCardShot(tester, () => probeEnv(card, CardProbe(), day: doctor));
    final other = p.frame.fillers.firstWhere((f) => f.index != p.said.fillerIndex);
    await tester.tap(find.byKey(ValueKey('meaning-${other.index}')));
    await tester.pump();
    await shoot(tester, '22-32-1-meaning-tapped');
    await settleCard(tester);
  });

  Future<void> assemble(WidgetTester tester, PhraseAssemblePayload p, int filler) async {
    for (final w in p.expectedWords) {
      final i = p.tiles.indexOf(w);
      await tester.ensureVisible(find.byKey(ValueKey('tray-$i')));
      await tester.tap(find.byKey(ValueKey('tray-$i')));
      await tester.pump();
    }
    final c = p.tiles.length + p.chips.indexWhere((f) => f.index == filler);
    await tester.tap(find.byKey(ValueKey('tray-$c')));
    await tester.pump();
  }

  testWidgets('23 32-2 собрано — до «Проверить», тишина', (tester) async {
    final card = fixtureCard(doctor, SessionKind.phraseAssemble);
    await pumpCardShot(tester, () => probeEnv(card, CardProbe(), day: doctor));
    await assemble(tester, card.payload as PhraseAssemblePayload, 1);
    await shoot(tester, '23-32-2-assembled');
    await settleCard(tester);
  });

  testWidgets('24 32-2 неверно — фраза звучит после «Проверить»', (tester) async {
    final card = fixtureCard(doctor, SessionKind.phraseAssemble);
    await pumpCardShot(tester, () => probeEnv(card, CardProbe(), day: doctor));
    await assemble(tester, card.payload as PhraseAssemblePayload, 0);
    await tester.tap(find.text('Проверить'));
    await tester.pump();
    await shoot(tester, '24-32-2-wrong');
    await settleCard(tester);
  });

  testWidgets('25 33-1 блок «Что тебе сказали?» под пузырём', (tester) async {
    final raw = sessionFixtureJson('day-doctor');
    final dialogue = (raw['stages'] as List).cast<Map<String, dynamic>>().firstWhere((s) => s['stage'] == 'dialogue');
    for (final c in (dialogue['cards'] as List).cast<Map<String, dynamic>>().where((c) => (c['position'] as int) < 3)) {
      c['result'] = 'passed';
      c['attempts'] = 1;
    }
    final cards = SessionDay.fromJson(raw).stageOf(PlanStage.dialogue)!.cards;
    final card = cards.firstWhere((c) => c.position == 3);
    await pumpCardShot(tester, () => probeEnv(card, CardProbe(), day: doctor, feed: DialogueFeed.before(cards, card)));
    await shoot(tester, '25-33-1-check-block');
    await settleCard(tester);
  });

  testWidgets('26 33-5 спросил — блок «Что тебе сказали?» под ответом', (tester) async {
    final cards = doctor.stageOf(PlanStage.dialogue)!.cards;
    final card = cards.firstWhere((c) => c.position == 12);
    await pumpCardShot(tester, () => probeEnv(card, CardProbe(), day: doctor));
    await sayDebug(tester, 'Do we need an X-ray');
    await tester.pump();
    await shoot(tester, '26-33-5-check-block');
    await settleCard(tester);
  });

  testWidgets('27 35-2 промах — строка судьи, «услышал», чип сразу', (tester) async {
    final speak = doctor.stageOf(PlanStage.speak)!.cards;
    final answer = speak.firstWhere((c) => c.kind == SessionKind.speakAnswer && (c.payload as SpeakAnswerPayload).partnerLine != null);
    final probe = CardProbe()..verdict = (_) => const SessionJudgeOutcome(accepted: false, reasonNative: 'Ты не сказал, когда это началось', attempts: 1);
    await pumpCardShot(tester, () => probeEnv(answer, probe, day: doctor));
    await sayDebug(tester, 'It started the car');
    await tester.pump();
    await shoot(tester, '27-35-2-miss');
    await settleCard(tester);
  });

  testWidgets('28 32-7 своё слово — отказ, «услышал», латунный «Пропустить»', (tester) async {
    final card = fixtureCard(doctor, SessionKind.phraseOtherSlot);
    final probe = CardProbe()..verdict = (_) => const SessionJudgeOutcome(accepted: false, reasonNative: 'Ты сказал не про место — назови, где болит.', attempts: 1);
    await pumpCardShot(tester, () => probeEnv(card, probe, day: doctor));
    for (final round in (card.payload as PhraseOtherSlotPayload).rounds) {
      await sayDebug(tester, round.expectedText);
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 700));
    }
    await sayDebug(tester, 'It hurts in the kitchen');
    await tester.pump();
    await shoot(tester, '28-32-7-own-word-refusal');
    await settleCard(tester);
  });

  testWidgets('29 34-5 свой пузырь широкий — до ответа', (tester) async {
    await pumpCardShot(tester, () => probeEnv(fixtureCard(doctor, SessionKind.listenPredict), CardProbe(), day: doctor));
    await shoot(tester, '29-34-5-before');
    await settleCard(tester);
  });

  testWidgets('30 34-5 свой пузырь широкий — после ответа', (tester) async {
    final card = fixtureCard(doctor, SessionKind.listenPredict);
    final p = card.payload as ListenPredictPayload;
    await pumpCardShot(tester, () => probeEnv(card, CardProbe(), day: doctor));
    for (var i = 0; i < 2; i++) {
      await tester.tap(find.byKey(ValueKey('option-${p.correct}')));
      await tester.pump();
    }
    await tester.tap(find.text('Это ответ'));
    await tester.pump();
    await shoot(tester, '30-34-5-after');
    await settleCard(tester);
  });

  for (final (n, kind, name) in [
    (31, SessionKind.phraseChooseBack, '32-3-choose-back'),
    (32, SessionKind.phraseRepeat, '32-6-repeat'),
    (33, SessionKind.phraseCombine, '32-8-combine'),
  ]) {
    testWidgets('$n $name — один лист', (tester) async {
      await pumpCardShot(tester, () => probeEnv(fixtureCard(doctor, kind), CardProbe(), day: doctor));
      await shoot(tester, '$n-$name');
      await settleCard(tester);
    });
  }

  testWidgets('34 32-4 «Вставь в окно» — один лист', (tester) async {
    final beginner = sessionFixture('day-doctor-beginner');
    await pumpCardShot(tester, () => probeEnv(fixtureCard(beginner, SessionKind.phraseSlot), CardProbe(), day: beginner, level: PlanLevel.beginner));
    await shoot(tester, '34-32-4-slot');
    await settleCard(tester);
  });
}

class _Backend implements SessionBackend {
  _Backend(this.raw, this._plan);

  final Map<String, dynamic> raw;
  final Plan _plan;
  final List<String> answered = [];

  @override
  Future<SessionDay> day(String planId, int number) async => SessionDay.fromJson(raw);

  @override
  Future<void> open(String planId, int number) async {}

  @override
  Future<Plan> plan(String planId) async => _plan;

  @override
  Future<Plan> retryLesson(String planId, String sceneId) async => _plan;

  @override
  Future<SessionAnswerOutcome> answer(String planId, int number, String cardId, SessionAnswer answer) async {
    answered.add(cardId);
    final card = [
      for (final s in (raw['stages'] as List).cast<Map<String, dynamic>>()) ...(s['cards'] as List).cast<Map<String, dynamic>>(),
    ].firstWhere((c) => c['id'] == cardId);
    // The server keeps the answer: the day read after the stage has it.
    card
      ..['result'] = answer.result.wire
      ..['attempts'] = answer.attempts;
    return SessionAnswerOutcome.fromJson({
      'card': {...card, 'result': answer.result.wire, 'attempts': answer.attempts, 'returns': false},
      'requeued': null,
      'unit': {'kind': card['unit']['kind'], 'ref': card['unit']['ref'], 'returns_tomorrow': false, 'returns_day': null},
      'day': {'cards_total': 10, 'cards_done': answered.length, 'minutes_spent': 6},
      'stage': {'stage': card['stage'], 'minutes_spent': 6},
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
