import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:ui' as ui;

import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/ui/mic_button.dart';
import 'package:eng_std/data/models.dart' as training;
import 'package:eng_std/data/plan/plan_models.dart';
import 'package:eng_std/data/plan/session/dialogue_feed.dart';
import 'package:eng_std/data/plan/session/session_day.dart';
import 'package:eng_std/data/plan/session/session_models.dart';
import 'package:eng_std/data/plan/session/session_outcomes.dart';
import 'package:eng_std/data/practice/learning_ladder.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/data/speech/speech_recognizer.dart';
import 'package:eng_std/features/plan/session/cards/card_host.dart';
import 'package:eng_std/features/plan/session/cards/card_kit.dart';
import 'package:eng_std/features/plan/session/parts/session_stage.dart';
import 'package:eng_std/features/plan/session/session_texts.dart';
import 'package:eng_std/features/training/session/session_exercise.dart';
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../../support/plan_goldens.dart' show setUpPlanGoldens;
import '../../../support/session_harness.dart';

/// СНИМКИ НАРЯДА FIX-1 — каждый ИЗМЕНЁННЫЙ экран кадром 390 × 844 @2×, как просит наряд.
///
/// Это не golden-тест: сравнивать не с чем, кадры канвы для «Скажи целиком» ещё не нарисованы. Файл рисует экраны в
/// PNG, чтобы архитектор посмотрел их до сборки на телефон. Без ключа он просто проверяет, что каждый из этих
/// экранов рисуется и ничего не переполняет, — поэтому и живёт в `test/`.
///
/// ```bash
/// flutter test test/features/plan/session/fix1_shots_test.dart --dart-define=FIX1_SHOTS=1
/// ```
/// Кадры ложатся в `../backend2/docs/research/fix-1/`.
void main() {
  const writeShots = bool.fromEnvironment('FIX1_SHOTS');
  const frame = Size(390, 844);
  final shotKey = GlobalKey();
  final day = sessionFixture('day-doctor');

  setUpAll(setUpPlanGoldens);

  Future<void> shoot(WidgetTester tester, String name) async {
    // The chips and the window cross-fade between states (`TweenAnimationBuilder` ignores `disableAnimations`):
    // a frame taken mid-transition shows neither state.
    await tester.pump(const Duration(milliseconds: 400));
    expect(tester.takeException(), isNull, reason: '«$name» — ничего не переполнено');
    if (!writeShots) return;
    final boundary = tester.renderObject<RenderRepaintBoundary>(find.byKey(shotKey));
    // `toImage` needs the real event loop: inside the test's fake-async zone the encode never completes.
    final bytes = await tester.runAsync(() async {
      final image = await boundary.toImage(pixelRatio: 2);
      final data = await image.toByteData(format: ui.ImageByteFormat.png);
      image.dispose();
      return data;
    });
    final file = File('../backend2/docs/research/fix-1/$name.png')..createSync(recursive: true);
    file.writeAsBytesSync(bytes!.buffer.asUint8List());
  }

  /// The card on the owner's frame, real fonts, animations off.
  Future<void> pumpShot(WidgetTester tester, Widget home, {List<Object> overrides = const []}) async {
    tester.view
      ..devicePixelRatio = 2
      ..physicalSize = frame * 2;
    addTearDown(tester.view.reset);
    await tester.pumpWidget(
      RepaintBoundary(
        key: shotKey,
        child: ProviderScope(
          overrides: [authControllerProvider.overrideWith(_Auth.new), ...overrides.cast()],
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

  Future<void> pumpCardShot(WidgetTester tester, CardEnvBuilder build) => pumpShot(tester, Builder(builder: (_) => sessionCardFor(build())));

  // ── §1 · диалог: задание и вопрос над закрытым ответом ────────────────────────────────────────
  testWidgets('01 диалог: вопрос и варианты на экране под длинной лентой', (tester) async {
    final raw = jsonDecode(File('../backend2/docs/fixtures/day-doctor.json').readAsStringSync()) as Map<String, dynamic>;
    final dialogue = (raw['stages'] as List).cast<Map<String, dynamic>>().firstWhere((s) => s['stage'] == 'dialogue');
    for (final c in (dialogue['cards'] as List).cast<Map<String, dynamic>>().where((c) => (c['position'] as int) < 12)) {
      c['result'] = 'passed';
      c['attempts'] = 1;
    }
    final cards = SessionDay.fromJson(raw).stageOf(PlanStage.dialogue)!.cards;
    final card = cards.firstWhere((c) => c.position == 12);
    await pumpCardShot(tester, () => probeEnv(card, CardProbe(), feed: DialogueFeed.before(cards, card)));
    await sayDebug(tester, 'Do we need an X-ray');
    await tester.pump();
    await shoot(tester, '01-dialogue-question');
    await settleCard(tester);
  });

  // Тот же блок у 33-1 — экран, который владелец и видел живьём (проверка обмена x6).
  testWidgets('07 диалог: проверка реплики собеседника (33-1) на шестом обмене', (tester) async {
    final raw = jsonDecode(File('../backend2/docs/fixtures/day-doctor.json').readAsStringSync()) as Map<String, dynamic>;
    final dialogue = (raw['stages'] as List).cast<Map<String, dynamic>>().firstWhere((s) => s['stage'] == 'dialogue');
    for (final c in (dialogue['cards'] as List).cast<Map<String, dynamic>>().where((c) => (c['position'] as int) < 9)) {
      c['result'] = 'passed';
      c['attempts'] = 1;
    }
    final cards = SessionDay.fromJson(raw).stageOf(PlanStage.dialogue)!.cards;
    final card = cards.firstWhere((c) => c.position == 9);
    await pumpCardShot(tester, () => probeEnv(card, CardProbe(), feed: DialogueFeed.before(cards, card)));
    await tester.pump(const Duration(milliseconds: 300));
    await shoot(tester, '07-dialogue-partner-question');
    await settleCard(tester);
  });

  // ── §6 · «Скажи целиком»: круг значения и круг своего слова ───────────────────────────────────
  testWidgets('02 «Скажи целиком»: круг значения, плашки — состояние', (tester) async {
    await pumpCardShot(tester, () => probeEnv(fixtureCard(day, SessionKind.phraseOtherSlot), CardProbe()));
    await sayDebug(tester, 'It hurts in his lower back');
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 700));
    await shoot(tester, '02-say-whole-round');
    await settleCard(tester);
  });

  testWidgets('03 «Скажи целиком»: последний круг — своё слово', (tester) async {
    await pumpCardShot(tester, () => probeEnv(fixtureCard(day, SessionKind.phraseOwnSlot), CardProbe()));
    for (final said in ['It started three days ago', 'It started last night', 'It started this morning']) {
      await sayDebug(tester, said);
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 700));
    }
    await shoot(tester, '03-say-whole-own-word');
    await settleCard(tester);
  });

  // ── §5 · повтор ───────────────────────────────────────────────────────────────────────────────
  testWidgets('04 повтор: состояние этапа', (tester) async {
    await pumpShot(
      tester,
      Builder(
        builder: (context) => SessionStageEntry(
          stage: PlanStage.speak,
          stageName: (s) => SessionTexts.stage(AppLocalizations.of(context), s),
          description: SessionTexts.description(AppLocalizations.of(context), PlanStage.speak, 6),
          minutes: 5,
          rows: [
            for (final s in PlanStage.known)
              (
                stage: s,
                status: s == PlanStage.speak ? StageRowStatus.current : StageRowStatus.done,
                started: false,
                replay: s == PlanStage.speak,
              ),
          ],
          scene: day.scene,
          noHints: false,
          onNoHints: (_) {},
          onStart: () {},
          onBack: () {},
          buildLabel: 'сборка 1.0.0 (10)',
        ),
      ),
    );
    await tester.pump();
    await shoot(tester, '04-replay-stage-entry');
  });

  testWidgets('05 повтор: свободный ответ зачтён телефоном, без «по смыслу ✓»', (tester) async {
    final probe = CardProbe()
      ..verdict = (_) => const SessionJudgeOutcome(accepted: true, result: SessionResult.passed, attempts: 1);
    await pumpCardShot(tester, () => probeEnv(fixtureCard(day, SessionKind.speakAnswer), probe, replay: true));
    await sayDebug(tester, 'It hurts in his lower back');
    await tester.pump();
    await shoot(tester, '05-replay-free-answer');
    await settleCard(tester);
  });

  // ── §3 · коллекции: термин из двух слов ───────────────────────────────────────────────────────
  testWidgets('06 коллекции: термин из двух слов распознан целиком', (tester) async {
    final recognizer = _ScriptedRecognizer([const SpeechAttempt.heard('boarding pass')]);
    await pumpShot(
      tester,
      SingleChildScrollView(
        child: SessionExerciseCard(
          card: training.SessionCard(
            termId: 'T1',
            mode: training.ExerciseMode.speaking,
            type: 'word',
            prompt: 'посадочный талон',
            answer: 'boarding pass',
            example: 'Here is my boarding pass.',
            exampleTranslation: 'Вот мой посадочный талон.',
            ladderStep: LearningLadder.stepAssembly,
          ),
          autoPronounce: false,
          onAnswered: (_) {},
          onSkipped: () {},
          onSpeak: (String text, {bool slow = false}) async {},
          speechLocaleId: 'en_US',
          answerLang: 'en',
          showDue: false,
        ),
      ),
      overrides: [
        appDatabaseProvider.overrideWith((ref) {
          final db = AppDatabase.forTesting(NativeDatabase.memory());
          ref.onDispose(db.close);
          return db;
        }),
        speechRecognizerProvider.overrideWithValue(recognizer),
      ],
    );
    await tester.pump();
    await tester.tap(find.byType(MicButton));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 1100));
    await tester.pump();
    await shoot(tester, '06-collections-two-words');
    await tester.pump(const Duration(seconds: 2));
  });
}

typedef CardEnvBuilder = CardEnv Function();

class _Auth extends AuthController {
  @override
  Future<training.AppUser?> build() async => training.AppUser(id: '01TEST', name: 'Денис');
}

/// A recogniser that says its script once, as a partial then as the final chunk.
class _ScriptedRecognizer implements SpeechRecognizer {
  _ScriptedRecognizer(this._script);

  final List<SpeechAttempt> _script;
  int _calls = 0;

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
  }) async {
    final attempt = _script[_calls.clamp(0, _script.length - 1)];
    _calls++;
    // The script said its piece: the reopened window hears nothing more, and the pause closes the recording — as it
    // does when a person has finished speaking.
    if (_calls > _script.length) return Completer<SpeechAttempt>().future;
    if (attempt.isHeard) onPartial?.call(attempt.text);
    return attempt;
  }

  @override
  Future<void> stop() async {}

  @override
  Future<void> cancel() async {}
}
