import 'dart:io';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/plan/plan_models.dart';
import 'package:eng_std/data/plan/session/session_models.dart';
import 'package:eng_std/features/plan/session/cards/card_host.dart';
import 'package:eng_std/features/plan/session/cards/card_kit.dart';
import 'package:eng_std/features/plan/session/parts/session_chrome.dart';
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../../support/plan_goldens.dart' show setUpPlanGoldens;
import '../../../support/server_fixtures.dart';
import '../../../support/session_harness.dart';

/// СНИМКИ НАРЯДА FIX-2 — каждый ИЗМЕНЁННЫЙ экран кадром 390 × 844 @2×, как просит наряд (§5 сдачи).
///
/// Это не golden-тест: сравнивать не с чем — канва серии 37 ещё не нарисована. Файл рисует экраны в PNG, чтобы
/// архитектор посмотрел их ДО сборки на телефон. Без ключа он просто проверяет, что каждый из этих экранов рисуется
/// и ничего не переполняет, — поэтому и живёт в `test/`.
///
/// ```bash
/// flutter test test/features/plan/session/fix2_shots_test.dart --dart-define=FIX2_SHOTS=true
/// ```
/// Кадры ложатся в `../backend2/docs/research/fix-2/shots/`.
void main() {
  const writeShots = bool.fromEnvironment('FIX2_SHOTS');
  const frame = Size(390, 844);
  final shotKey = GlobalKey();
  final intermediate = sessionFixture('day-doctor');
  final beginner = sessionFixture('day-doctor-beginner');

  setUpAll(setUpPlanGoldens);

  Future<void> shoot(WidgetTester tester, String name) async {
    // Окно и плашки перетекают между состояниями (`TweenAnimationBuilder` не слушает `disableAnimations`): кадр,
    // снятый посреди перехода, не показывает ни одного из них.
    await tester.pump(const Duration(milliseconds: 400));
    expect(tester.takeException(), isNull, reason: '«$name» — ничего не переполнено');
    if (!writeShots) return;
    final boundary = tester.renderObject<RenderRepaintBoundary>(find.byKey(shotKey));
    // `toImage` нужен настоящий цикл событий: внутри fake-async теста кодирование не завершается никогда.
    final bytes = await tester.runAsync(() async {
      final image = await boundary.toImage(pixelRatio: 2);
      final data = await image.toByteData(format: ui.ImageByteFormat.png);
      image.dispose();
      return data;
    });
    final file = File('../backend2/docs/research/fix-2/shots/$name.png')..createSync(recursive: true);
    file.writeAsBytesSync(bytes!.buffer.asUint8List());
  }

  /// Экран на кадре владельца, настоящие шрифты, анимации выключены.
  Future<void> pumpShot(WidgetTester tester, Widget home) async {
    tester.view
      ..devicePixelRatio = 2
      ..physicalSize = frame * 2;
    addTearDown(tester.view.reset);
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

  Future<void> pumpCardShot(WidgetTester tester, CardEnv Function() build) => pumpShot(tester, Builder(builder: (_) => sessionCardFor(build())));

  // ── §5 · «Скажи целиком»: круги — теперь с сервера, и у обоих уровней ─────────────────────────
  testWidgets('01 «Скажи целиком» beginner: первый круг из двух значений', (tester) async {
    await pumpCardShot(tester, () => probeEnv(fixtureCard(beginner, SessionKind.phraseOtherSlot), CardProbe(), day: beginner, level: PlanLevel.beginner));
    await shoot(tester, '01-say-whole-beginner-first-round');
    await settleCard(tester);
  });

  testWidgets('02 «Скажи целиком» beginner: круг своего слова после двух значений', (tester) async {
    await pumpCardShot(tester, () => probeEnv(fixtureCard(beginner, SessionKind.phraseOtherSlot), CardProbe(), day: beginner, level: PlanLevel.beginner));
    for (final said in ['It hurts in his lower back', 'It hurts in his neck']) {
      await sayDebug(tester, said);
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 700));
    }
    await shoot(tester, '02-say-whole-beginner-own-word');
    await settleCard(tester);
  });

  testWidgets('03 «Скажи целиком» intermediate: третий круг значений', (tester) async {
    await pumpCardShot(tester, () => probeEnv(fixtureCard(intermediate, SessionKind.phraseOtherSlot), CardProbe()));
    for (final said in ['It hurts in his lower back', 'It hurts in his neck']) {
      await sayDebug(tester, said);
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 700));
    }
    await shoot(tester, '03-say-whole-intermediate-third-round');
    await settleCard(tester);
  });

  // ── §3 · «Говорю сам»: у обмена ask вопроса нет ───────────────────────────────────────────────
  testWidgets('04 «Говорю сам» ask: «Спроси сам» и намерение вместо чужого вопроса', (tester) async {
    final speak = intermediate.stageOf(PlanStage.speak)!.cards;
    final ask = speak.firstWhere((c) => c.kind == SessionKind.speakAnswer && (c.payload as SpeakAnswerPayload).partnerLine == null);
    await pumpCardShot(tester, () => probeEnv(ask, CardProbe()));
    await shoot(tester, '04-speak-answer-ask-no-question');
    await settleCard(tester);
  });

  testWidgets('05 «Говорю сам» answer: вопрос своего обмена, как было', (tester) async {
    final speak = intermediate.stageOf(PlanStage.speak)!.cards;
    final answer = speak.firstWhere((c) => c.kind == SessionKind.speakAnswer && (c.payload as SpeakAnswerPayload).partnerLine != null);
    await pumpCardShot(tester, () => probeEnv(answer, CardProbe()));
    await shoot(tester, '05-speak-answer-answer-own-question');
    await settleCard(tester);
  });

  // ── §1 · варианты на родном: ни одного второго значения своего окна ───────────────────────────
  testWidgets('06 обратный перевод: четыре разных смысла', (tester) async {
    await pumpCardShot(tester, () => probeEnv(fixtureCard(intermediate, SessionKind.phraseChooseBack), CardProbe()));
    await shoot(tester, '06-choose-back-four-meanings');
    await settleCard(tester);
  });

  // ── §2 · повтор: текст на экране судится по смысловым словам ──────────────────────────────────
  testWidgets('07 «Повтори вслух»: один круг у каркаса без окна', (tester) async {
    await pumpCardShot(tester, () => probeEnv(fixtureCard(beginner, SessionKind.phraseRepeat), CardProbe(), day: beginner, level: PlanLevel.beginner));
    await shoot(tester, '07-phrase-repeat-one-round');
    await settleCard(tester);
  });

  testWidgets('08 эхо: реплика сказана целиком', (tester) async {
    final echo = intermediate.stageOf(PlanStage.speak)!.cards.firstWhere((c) => c.kind == SessionKind.speakEcho);
    await pumpCardShot(tester, () => probeEnv(echo, CardProbe()));
    await tester.pump(const Duration(milliseconds: 3400));
    await sayDebug(tester, 'It looks like a muscle strain so he should rest and use a heating pad');
    await tester.pump();
    await shoot(tester, '08-speak-echo-said-whole');
    await settleCard(tester);
  });

  // ── §6 · полоса сцены без кружка ученика ──────────────────────────────────────────────────────
  testWidgets('09 полоса сцены: фото сцены и строка, кружка справа нет', (tester) async {
    final raw = serverFixtureJson('day-doctor');
    final scene = PlanScene.fromJson(raw['scene'] as Map<String, dynamic>);
    await pumpShot(tester, Column(children: [SessionSceneStrip(scene: scene)]));
    await shoot(tester, '09-scene-strip-no-learner-circle');
  });
}
