import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/plan/conversation/conversation_models.dart';
import 'package:eng_std/data/plan/day_window.dart';
import 'package:eng_std/data/plan/session/session_day.dart';
import 'package:eng_std/features/plan/conversation/conversation_controller.dart';
import 'package:eng_std/data/plan/plan_models.dart' show PlanStage;
import 'package:eng_std/features/plan/conversation/talk_entry.dart';
import 'package:eng_std/features/plan/conversation/talk_ribbon.dart' show TalkDock;
import 'package:eng_std/features/plan/conversation/talk_screen.dart';
import 'package:eng_std/features/plan/conversation/talk_summary.dart';
import 'package:eng_std/data/speech/speech_recognizer.dart';
import 'package:eng_std/features/plan/session/session_mic.dart';
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../../support/plan_goldens.dart' show kGoldenDpr, planFrom, setUpPlanGoldens;
import '../../../support/rehearsal_run.dart';
import '../../../support/server_fixtures.dart';
import '../../../support/talk_harness.dart';

/// THE FRAMES OF НАРЯД CLIENT-FIX-4 AS GOLDENS — each state 390 × 844 @2× under the frames' 52 status bar, off the
/// server's own answers (the live rehearsal of FIX-4b, [rehearsalTalk]; the talks of FIX-3 for a day's summary), on
/// the real screens. The architect reads them before the build; they then catch a drift on their own.
///
/// ```bash
/// flutter test --update-goldens test/features/plan/conversation/talk_fix4_golden_test.dart
/// ```
/// The PNGs — `test/goldens/talk/`, named by the frame; the same files go to `docs/research/client-fix-4/shots/`.
void main() {
  final plan = planFrom('plan_rehearsal');
  const frame = Size(390, 844);

  setUpAll(() async {
    await setUpPlanGoldens();
    // The frames show the dock of the phone's release build — without the debug «что услышал» field.
    TalkDock.debugHeardField = false;
  });
  tearDownAll(() => TalkDock.debugHeardField = true);

  void muteChannels(WidgetTester tester) {
    final messenger = tester.binding.defaultBinaryMessenger;
    for (final channel in [const MethodChannel('flutter_tts'), const MethodChannel('com.denis.engstd/app_info')]) {
      messenger.setMockMethodCallHandler(channel, (call) async => null);
      addTearDown(() => messenger.setMockMethodCallHandler(channel, null));
    }
  }

  Future<void> pumpFrame(WidgetTester tester, Widget home, {Size size = frame}) async {
    tester.view
      ..devicePixelRatio = kGoldenDpr
      ..physicalSize = size * kGoldenDpr
      ..padding = const FakeViewPadding(top: 52 * kGoldenDpr);
    addTearDown(tester.view.reset);
    muteChannels(tester);
    await tester.pumpWidget(
      ProviderScope(
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
    );
    await tester.pump();
  }

  Future<void> expectFrame(WidgetTester tester, String name, {Duration settle = const Duration(milliseconds: 400)}) async {
    await tester.pump(settle);
    await expectLater(find.byType(MaterialApp), matchesGoldenFile('../../../goldens/talk/$name.png'));
  }

  /// The talk on its real screen, opened on the first of [documents]; later calls get the next ones.
  Future<TalkStand> pumpTalkFrame(
    WidgetTester tester,
    List<PlanConversation> documents, {
    bool hints = true,
    SpeechRecognizer? recognizer,
  }) async {
    final probe = TalkProbe()..documents.addAll(documents);
    final voice = HeldVoice();
    final mics = <SessionMic>[];
    final talk = ConversationController(backend: FakeTalkBackend(probe), planId: plan.id, day: 3, voice: voice, hints: hints);
    addTearDown(talk.dispose);
    await pumpFrame(
      tester,
      TalkView(
        controller: talk,
        scene: plan.sceneById(documents.first.currentSceneId),
        sceneById: plan.sceneById,
        voice: voice,
        makeMic: () {
          final mic = SessionMic(recognizer: recognizer ?? ListeningRecognizer(), localeId: 'en_US', expected: '');
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
    return (talk: talk, voice: voice, mics: mics);
  }

  Future<void> answer(WidgetTester tester, TalkStand stand, int step) async {
    unawaited(stand.talk.say(rehearsalMove(step)!));
    await tester.pump();
    await tester.pump();
  }

  // ── 39-1 · переход между сценами ─────────────────────────────────────────────────────────────────
  testWidgets('39-1 момент перехода', (tester) async {
    final stand = await pumpTalkFrame(tester, [rehearsalTalk(RehearsalStep.almostSaid), rehearsalTalk(RehearsalStep.sceneChange)]);
    await finishLine(tester, stand);
    await answer(tester, stand, RehearsalStep.sceneChange);
    await finishLine(tester, stand);
    await expectFrame(tester, '39-1');
    await settleTalk(tester);
  });

  testWidgets('39-1b после «Продолжить»', (tester) async {
    final stand = await pumpTalkFrame(tester, [rehearsalTalk(RehearsalStep.sceneChange)]);
    await tester.tap(find.byKey(const ValueKey('talk-scene-continue')));
    await tester.pump(const Duration(milliseconds: 300));
    await tester.pump();
    await finishLine(tester, stand);
    await expectFrame(tester, '39-1b');
    await settleTalk(tester);
  });

  // ── 37-5b · вход в репетицию ─────────────────────────────────────────────────────────────────────
  Widget rehearsalEntry() {
    final day = SessionDay.fromJson(serverFixtureJson('day-rehearsal'));
    final row = day.window!.stages.firstWhere((s) => s.stage == PlanStage.conversation);
    final scenes = talkEntryScenes(
      row.targets,
      order: [for (final WindowSourceRef s in day.window!.sources) (sceneId: s.sceneId, title: s.titleNative, female: s.partnerFemale)],
      sceneById: plan.sceneById,
    );
    return TalkEntryView(
      scene: plan.sceneById(scenes.first.sceneId),
      minutes: row.minutes,
      title: row.talkTitleNative,
      scenesCount: row.scenesCount,
      targets: row.targets,
      scenes: scenes,
      rehearsal: true,
      noHints: false,
      onNoHints: (_) {},
      onStart: () {},
      onBack: () {},
    );
  }

  testWidgets('37-5b вход в репетицию', (tester) async {
    await pumpFrame(tester, rehearsalEntry());
    await expectFrame(tester, '37-5b');
  });

  testWidgets('37-5b вход в репетицию — вся длина прокрутки', (tester) async {
    await pumpFrame(tester, rehearsalEntry(), size: const Size(390, 1340));
    await expectFrame(tester, '37-5b-full');
  });

  // ── 37-7 · лента, твоя очередь ───────────────────────────────────────────────────────────────────
  testWidgets('37-7 ждём тапа, подсказка видна', (tester) async {
    final stand = await pumpTalkFrame(tester, [rehearsalTalk(RehearsalStep.secondScene)]);
    await finishLine(tester, stand);
    await expectFrame(tester, '37-7');
    await settleTalk(tester);
  });

  testWidgets('37-7b слушаю', (tester) async {
    final stand = await pumpTalkFrame(tester, [rehearsalTalk(RehearsalStep.secondScene)], recognizer: _Speaking("He's been sick for"));
    await finishLine(tester, stand);
    await tester.tap(find.byKey(const ValueKey('talk-mic')));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 200));
    await expectFrame(tester, '37-7b');
    await settleTalk(tester);
  });

  testWidgets('37-7c «Без подсказок»', (tester) async {
    final stand = await pumpTalkFrame(tester, [rehearsalTalk(RehearsalStep.secondScene, blind)], hints: false);
    await finishLine(tester, stand);
    await expectFrame(tester, '37-7c');
    await settleTalk(tester);
  });

  testWidgets('37-7d после «Sorry?» — перефраз', (tester) async {
    final rephrased = rehearsalTalk(RehearsalStep.secondScene, (json) {
      final turns = json['turns'] as List<dynamic>;
      final last = turns.last as Map<String, dynamic>;
      final index = last['index'] as int;
      turns.addAll([
        {...last, 'index': index + 1, 'speaker': 'learner', 'kind': 'rescue', 'text_target': 'Sorry?', 'text_native': null, 'audio': null},
        {
          ...last,
          'index': index + 2,
          'text_target': 'How many days has he been ill?',
          'text_native': 'Сколько дней он болеет?',
          'audio': null,
        },
      ]);
    });
    final stand = await pumpTalkFrame(tester, [rehearsalTalk(RehearsalStep.secondScene), rephrased]);
    await finishLine(tester, stand);
    await tester.tap(find.byKey(const ValueKey('talk-rescue')));
    await tester.pump();
    await tester.pump();
    await finishLine(tester, stand);
    await expectFrame(tester, '37-7d');
    await settleTalk(tester);
  });

  testWidgets('37-7e тап по подсказке', (tester) async {
    final stand = await pumpTalkFrame(tester, [rehearsalTalk(RehearsalStep.secondScene)]);
    await finishLine(tester, stand);
    await tester.tap(find.byKey(const ValueKey('talk-hint-plate')));
    await tester.pump();
    await expectFrame(tester, '37-7e');
    await settleTalk(tester);
  });

  // ── 37-8 · свой пузырь и ответ ───────────────────────────────────────────────────────────────────
  testWidgets('37-8 сказал — плашка на мгновение в шалфее', (tester) async {
    final stand = await pumpTalkFrame(tester, [rehearsalTalk(RehearsalStep.secondScene), rehearsalTalk(RehearsalStep.extraSaid)]);
    await finishLine(tester, stand);
    await answer(tester, stand, RehearsalStep.extraSaid);
    // Inside the plate's 600 ms of sage — before it leaves.
    await expectFrame(tester, '37-8', settle: const Duration(milliseconds: 200));
    await tester.pump(const Duration(milliseconds: 600));
    await finishLine(tester, stand);
    await settleTalk(tester);
  });

  testWidgets('37-8b ответ роли — сказанной плашки в ряду нет', (tester) async {
    final stand = await pumpTalkFrame(tester, [rehearsalTalk(RehearsalStep.secondScene), rehearsalTalk(RehearsalStep.extraSaid)]);
    await finishLine(tester, stand);
    await answer(tester, stand, RehearsalStep.extraSaid);
    await tester.pump(const Duration(milliseconds: 600));
    await tester.pump();
    await finishLine(tester, stand);
    await expectFrame(tester, '37-8b');
    await settleTalk(tester);
  });

  testWidgets('37-8d лист по тапу — все конструкции сцены', (tester) async {
    final stand = await pumpTalkFrame(tester, [rehearsalTalk(RehearsalStep.almost)]);
    await finishLine(tester, stand);
    await tester.tap(find.byKey(const ValueKey('talk-constructions')));
    await tester.pumpAndSettle();
    await expectFrame(tester, '37-8d');
    await tester.tap(find.byKey(const ValueKey('talk-construction-sheet-close')));
    await tester.pumpAndSettle();
    await settleTalk(tester);
  });

  testWidgets('37-8e после «почти»', (tester) async {
    final stand = await pumpTalkFrame(tester, [rehearsalTalk(RehearsalStep.beforeAlmost), rehearsalTalk(RehearsalStep.almost)]);
    await finishLine(tester, stand);
    await answer(tester, stand, RehearsalStep.almost);
    await finishLine(tester, stand);
    await expectFrame(tester, '37-8e');
    await settleTalk(tester);
  });

  // ── 37-11 · конец разговора ──────────────────────────────────────────────────────────────────────
  testWidgets('37-11 прощание — лист «Разговор окончен»', (tester) async {
    final stand = await pumpTalkFrame(tester, [rehearsalTalk(RehearsalStep.extraSaid), rehearsalTalk(RehearsalStep.ended)]);
    await finishLine(tester, stand);
    await answer(tester, stand, RehearsalStep.ended);
    await finishLine(tester, stand);
    await expectFrame(tester, '37-11');
    await settleTalk(tester);
  });

  testWidgets('37-11b все цели сказаны — ряд сложился', (tester) async {
    // A day's talk: its one scene has every target said, and the talk goes on to its last move (FIX-3 §7).
    final all = serverTalk('conversation-day-open', (json) {
      for (final t in (json['targets'] as List).cast<Map<String, dynamic>>()) {
        t
          ..['said'] = true
          ..['value_target'] ??= t['example_target'];
      }
    });
    final stand = await pumpTalkFrame(tester, [all]);
    await finishLine(tester, stand);
    await expectFrame(tester, '37-11b');
    await settleTalk(tester);
  });

  // ── 37-12 · итог разговора ───────────────────────────────────────────────────────────────────────
  PlanConversation dayByTime() => serverTalk('conversation-day-ended', (json) {
    final summary = json['summary'] as Map<String, dynamic>;
    summary
      ..['ended_by_limit'] = true
      ..['returns_tomorrow'] = true;
    final phrases = (summary['phrases'] as List).cast<Map<String, dynamic>>();
    for (final p in phrases.skip(3).take(2)) {
      p
        ..['said'] = false
        ..['value_target'] = null;
    }
    // Two constructions of the scene said beyond the targets — the targets' own shape, as the server sends them.
    summary['extra_said'] = [
      for (final p in phrases.skip(5).take(2)) {...p, 'said': true, 'value_target': p['example_target']},
    ];
    summary['phrases'] = phrases.take(5).toList();
  });

  Widget summary(PlanConversation talk) => TalkSummaryView(
    talk: talk,
    scene: plan.sceneById(talk.currentSceneId),
    sceneById: plan.sceneById,
    onNext: () {},
    onClose: () {},
  );

  testWidgets('37-12 день — по времени', (tester) async {
    await pumpFrame(tester, summary(dayByTime()));
    await expectFrame(tester, '37-12');
  });

  testWidgets('37-12 день — по времени, вся длина прокрутки', (tester) async {
    await pumpFrame(tester, summary(dayByTime()), size: const Size(390, 1190));
    await expectFrame(tester, '37-12-full');
  });

  testWidgets('37-12b репетиция — по прощанию', (tester) async {
    await pumpFrame(tester, summary(rehearsalTalk(RehearsalStep.ended)));
    await expectFrame(tester, '37-12b');
  });

  testWidgets('37-12b репетиция — вся длина прокрутки', (tester) async {
    await pumpFrame(tester, summary(rehearsalTalk(RehearsalStep.ended)), size: const Size(390, 1260));
    await expectFrame(tester, '37-12b-full');
  });
}

/// A learner mid-sentence: the recording opens, [partial] is heard so far, and it stays open until something stops it.
class _Speaking extends ListeningRecognizer {
  _Speaking(this.partial);

  final String partial;

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
    final open = super.listenOnce(expected: expected, localeId: localeId, timeout: timeout, pauseFor: pauseFor);
    onPartial?.call(partial);
    return open;
  }
}
