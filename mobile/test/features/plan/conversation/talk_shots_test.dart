import 'dart:async';
import 'dart:io';
import 'dart:ui' as ui;

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/plan/conversation/conversation_models.dart';
import 'package:eng_std/data/plan/plan_models.dart';
import 'package:eng_std/data/plan/session/session_models.dart';
import 'package:eng_std/data/plan/session/session_outcomes.dart';
import 'package:eng_std/features/plan/conversation/conversation_controller.dart';
import 'package:eng_std/features/plan/conversation/talk_entry.dart';
import 'package:eng_std/features/plan/conversation/talk_screen.dart';
import 'package:eng_std/features/plan/conversation/talk_summary.dart';
import 'package:eng_std/features/plan/session/cards/card_host.dart';
import 'package:eng_std/features/plan/session/cards/card_kit.dart';
import 'package:eng_std/features/plan/session/parts/session_stage.dart';
import 'package:eng_std/features/plan/session/session_mic.dart';
import 'package:eng_std/features/plan/session/session_texts.dart';
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../../support/plan_goldens.dart' show setUpPlanGoldens;
import '../../../support/session_harness.dart';
import '../../../support/talk_harness.dart';

/// СНИМКИ НАРЯДА CLIENT-CONV-1a — каждое состояние кадром 390 × 844 @2×, для архитектора ДО сборки
/// на телефон (наряд, «Живая проверка»).
///
/// Это не golden-тест: сравнивать не с чем. Без ключа файл просто проверяет, что каждый экран
/// рисуется и ничего не переполняет, — поэтому и живёт в `test/`.
///
/// ```bash
/// flutter test test/features/plan/conversation/talk_shots_test.dart --dart-define=TALK_SHOTS=true
/// ```
/// Кадры ложатся в `../backend2/docs/research/client-conv-1a/shots/`.
DioException _offline() => DioException(requestOptions: RequestOptions(path: '/x'), type: DioExceptionType.connectionError);

DioException _unavailable() => DioException(
  requestOptions: RequestOptions(path: '/x'),
  type: DioExceptionType.badResponse,
  response: Response(requestOptions: RequestOptions(path: '/x'), statusCode: 503, data: const {'code': 'plan_conversation_unavailable'}),
);

void main() {
  const writeShots = bool.fromEnvironment('TALK_SHOTS');
  const frame = Size(390, 844);
  final shotKey = GlobalKey();
  final day = sessionFixture('day-doctor');
  final open = talkFixture('conversation-day-open');
  final ended = talkFixture('conversation-day-ended');
  final rehearsal = talkFixture('conversation-rehearsal-ended');
  const phrases = {'p1': 'My son has a fever.', 'p2': 'He has had it for three days.'};

  setUpAll(setUpPlanGoldens);

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
    final file = File('../backend2/docs/research/client-conv-1a/shots/$name.png')..createSync(recursive: true);
    file.writeAsBytesSync(bytes!.buffer.asUint8List());
  }

  Future<void> pumpShot(WidgetTester tester, Widget home) async {
    tester.view
      ..devicePixelRatio = 2
      ..physicalSize = frame * 2
      // The frames' phone has a 52 status bar over the screen, as a real one does: a shot shows the height a card
      // really has, not 52 more (the air over «Начать разговор» on 37-5 was that 52).
      ..padding = const FakeViewPadding(top: 52 * 2);
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

  /// The talk screen on a document, with the voice held (the role is speaking) or released.
  Future<TalkStand> pumpTalkShot(
    WidgetTester tester,
    PlanConversation document, {
    bool speaking = false,
    bool hints = true,
    Object? failMove,
  }) async {
    final probe = TalkProbe()
      ..documents.add(document)
      ..failMove = failMove;
    final voice = HeldVoice();
    final mics = <SessionMic>[];
    final talk = ConversationController(
      backend: FakeTalkBackend(probe),
      planId: 'ulid-plan',
      day: 1,
      voice: voice,
      hints: hints,
    );
    addTearDown(talk.dispose);
    await pumpShot(
      tester,
      TalkView(
        controller: talk,
        scene: day.scene,
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
    if (failMove != null) {
      await talk.say('He has had it for three days.');
      await tester.pump();
      await tester.pump();
    }
    return (talk: talk, voice: voice, mics: mics);
  }

  Future<void> pumpCardShot(WidgetTester tester, CardEnv Function() build) =>
      pumpShot(tester, Builder(builder: (_) => sessionCardFor(build())));

  // ── 37-5 · вход в разговор ────────────────────────────────────────────────────────────────────
  testWidgets('01 вход в разговор — день', (tester) async {
    await pumpShot(
      tester,
      TalkEntryView(
        scene: day.scene,
        minutes: 3,
        rehearsal: false,
        noHints: false,
        onNoHints: (_) {},
        onStart: () {},
        onBack: () {},
      ),
    );
    await shoot(tester, '01-37-5-entry-day');
  });

  testWidgets('02 вход в разговор — «Без подсказок» включено', (tester) async {
    await pumpShot(
      tester,
      TalkEntryView(
        scene: day.scene,
        minutes: 6,
        rehearsal: true,
        noHints: true,
        onNoHints: (_) {},
        onStart: () {},
        onBack: () {},
      ),
    );
    await shoot(tester, '02-37-5-entry-rehearsal-no-hints');
  });

  // ── 37-6…37-9 · лента ─────────────────────────────────────────────────────────────────────────
  testWidgets('03 роль говорит — микрофон погашен', (tester) async {
    await pumpTalkShot(tester, open, speaking: true);
    await shoot(tester, '03-37-6-agent-speaking');
  });

  testWidgets('04 твоя очередь — «Не понял» и «Подсказать»', (tester) async {
    await pumpTalkShot(tester, open);
    await shoot(tester, '04-37-7-your-turn');
    await settleTalk(tester);
  });

  testWidgets('05 чип подсказки встал сам', (tester) async {
    await pumpTalkShot(tester, open);
    await tester.pump(const Duration(seconds: 6));
    await shoot(tester, '05-37-7-hint-chip');
    await settleTalk(tester);
  });

  testWidgets('06 слушаю — живая строка', (tester) async {
    await pumpTalkShot(tester, open);
    await tester.tap(find.byKey(const ValueKey('talk-mic')));
    await tester.pump();
    await enterHeard(tester, 'He has had it for three days');
    await tester.pump(const Duration(milliseconds: 200));
    await shoot(tester, '06-37-7-listening');
    await settleTalk(tester);
  });

  // Since CLIENT-CONV-1b the role's text is open with its voice — there is no «текст» to tap any more.
  testWidgets('07 текст реплики открыт с голосом', (tester) async {
    await pumpTalkShot(tester, open);
    await shoot(tester, '07-37-6-text-open');
    await settleTalk(tester);
  });

  testWidgets('08 прервал реплику роли', (tester) async {
    await pumpTalkShot(tester, open, speaking: true);
    await tester.tap(find.byKey(const ValueKey('talk-mic')));
    await tester.pump();
    await tester.pump();
    await shoot(tester, '08-37-9-interrupted');
    await settleTalk(tester);
  });

  testWidgets('09 в «Без подсказок» — ни чипа, ни кнопки, тексты закрыты', (tester) async {
    final blind = talkFixtureEdited('conversation-day-open', (json) {
      (json['hints'] as Map<String, dynamic>)
        ..['enabled'] = false
        ..['native'] = null;
    });
    await pumpTalkShot(tester, blind, hints: false);
    await tester.pump(const Duration(seconds: 6));
    await shoot(tester, '09-37-7-no-hints');
    await settleTalk(tester);
  });

  // ── 37-10 · сбои ──────────────────────────────────────────────────────────────────────────────
  testWidgets('10 не расслышал', (tester) async {
    final stand = await pumpTalkShot(tester, open);
    await stand.talk.say('  ');
    await tester.pump();
    await shoot(tester, '10-37-10-unheard');
    await settleTalk(tester);
  });

  testWidgets('11 связь пропала', (tester) async {
    final stand = await pumpTalkShot(tester, open, failMove: _offline());
    expect(stand.talk.trouble, TalkTrouble.offline);
    await shoot(tester, '11-37-10-offline');
    await settleTalk(tester);
  });

  testWidgets('12 собеседник не отвечает', (tester) async {
    final stand = await pumpTalkShot(tester, open, failMove: _unavailable());
    expect(stand.talk.trouble, TalkTrouble.agentSilent);
    await shoot(tester, '12-37-10-agent-silent');
    await settleTalk(tester);
  });

  // ── 37-11 · 37-12 ─────────────────────────────────────────────────────────────────────────────
  testWidgets('13 разговор окончен', (tester) async {
    await pumpTalkShot(tester, ended);
    await shoot(tester, '13-37-11-ended');
    await settleTalk(tester);
  });

  testWidgets('14 итог разговора — день', (tester) async {
    await pumpShot(
      tester,
      TalkSummaryView(talk: ended, scene: day.scene, voice: HeldVoice(), onAgain: () {}, onNext: () {}, onClose: () {}),
    );
    await shoot(tester, '14-37-12-summary-day');
  });

  testWidgets('15 итог разговора — репетиция', (tester) async {
    await pumpShot(
      tester,
      TalkSummaryView(talk: rehearsal, scene: day.scene, voice: HeldVoice(), onAgain: () {}, onNext: () {}, onClose: () {}),
    );
    await shoot(tester, '15-37-12-summary-rehearsal');
  });

  // ── 30-1 · 30-7 · шесть этапов ────────────────────────────────────────────────────────────────
  testWidgets('16 вход в этап — шесть рядов', (tester) async {
    await pumpShot(
      tester,
      Builder(
        builder: (context) {
          final l = AppLocalizations.of(context);
          return SessionStageEntry(
            stage: PlanStage.words,
            stageName: (s) => SessionTexts.stage(l, s),
            description: SessionTexts.description(l, PlanStage.words, 8),
            minutes: 6,
            rows: [
              for (final s in [PlanStage.words, PlanStage.phrases, PlanStage.dialogue, PlanStage.listen, PlanStage.speak, PlanStage.conversation])
                (
                  stage: s,
                  status: s == PlanStage.words ? StageRowStatus.current : StageRowStatus.ahead,
                  replay: false,
                ),
            ],
            scene: day.scene,
            noHints: false,
            onNoHints: (_) {},
            onStart: () {},
            onBack: () {},
          );
        },
      ),
    );
    await shoot(tester, '16-30-1-six-stages');
  });

  testWidgets('17 итог дня — шестой ряд и «Что было хорошо»', (tester) async {
    await pumpShot(
      tester,
      Builder(
        builder: (context) {
          final l = AppLocalizations.of(context);
          return SessionDaySummary(
            title: 'День пройден · 19 минут',
            stages: const [
              PlanStage.words,
              PlanStage.phrases,
              PlanStage.dialogue,
              PlanStage.listen,
              PlanStage.speak,
              PlanStage.conversation,
            ],
            stageName: (s) => SessionTexts.stage(l, s),
            highlights: const [
              'Сказал сам 6 реплик из 8',
              'В разговоре использовал 5 фраз из 7',
              'Понял все вопросы врача',
            ],
            returnsLine: '3 карточки: 1 слово, 1 фраза и 1 реплика.',
            nextDay: 'День 2 — собираю',
            scene: day.scene,
            onClose: () {},
            onCloseDay: () {},
          );
        },
      ),
    );
    await shoot(tester, '17-30-7-day-summary');
  });

  // ── 34-5 · 35-2 · 32-x ────────────────────────────────────────────────────────────────────────
  testWidgets('18 «Что прозвучит в ответ?» — до прослушивания', (tester) async {
    await pumpCardShot(tester, () => probeEnv(fixtureCard(day, SessionKind.listenPredict), CardProbe(), day: day));
    await shoot(tester, '18-34-5-before');
    await settleCard(tester);
  });

  testWidgets('19 «Что прозвучит в ответ?» — промах, тексты открыты', (tester) async {
    final card = fixtureCard(day, SessionKind.listenPredict);
    final p = card.payload as ListenPredictPayload;
    final wrong = p.options.firstWhere((o) => o.id != p.correct);
    await pumpCardShot(tester, () => probeEnv(card, CardProbe(), day: day));
    await tester.tap(find.byKey(ValueKey('option-${wrong.id}')));
    await tester.pump();
    await tester.tap(find.byKey(ValueKey('option-${wrong.id}')));
    await tester.pump();
    await tester.tap(find.byKey(const ValueKey('predict-answer')));
    await tester.pump();
    await shoot(tester, '19-34-5-miss');
    await settleCard(tester);
  });

  testWidgets('20 «Ответь своими словами» — чип подсказки', (tester) async {
    final speak = day.stageOf(PlanStage.speak)!.cards;
    final answer = speak.firstWhere((c) => c.kind == SessionKind.speakAnswer && (c.payload as SpeakAnswerPayload).partnerLine != null);
    await pumpCardShot(tester, () => probeEnv(answer, CardProbe(), day: day));
    await tester.pump(const Duration(seconds: 6));
    await shoot(tester, '20-35-2-hint-chip');
    await settleCard(tester);
  });

  testWidgets('21 «Ответь своими словами» — не зачтено', (tester) async {
    final speak = day.stageOf(PlanStage.speak)!.cards;
    final answer = speak.firstWhere((c) => c.kind == SessionKind.speakAnswer && (c.payload as SpeakAnswerPayload).partnerLine != null);
    final probe = CardProbe()..verdict = (_) => const SessionJudgeOutcome(accepted: false, reasonNative: 'Ты не сказал, когда становится хуже', attempts: 1);
    await pumpCardShot(tester, () => probeEnv(answer, probe, day: day));
    await tester.pump(const Duration(milliseconds: 300));
    await sayDebug(tester, 'Yes worse');
    await tester.pump();
    await shoot(tester, '21-35-2-rejected');
    await settleCard(tester);
  });

  testWidgets('22 «Спроси сам» — намерение плашкой', (tester) async {
    final speak = day.stageOf(PlanStage.speak)!.cards;
    final ask = speak.firstWhere((c) => c.kind == SessionKind.speakAnswer && (c.payload as SpeakAnswerPayload).partnerLine == null);
    await pumpCardShot(tester, () => probeEnv(ask, CardProbe(), day: day));
    await shoot(tester, '22-35-2-ask');
    await settleCard(tester);
  });

  testWidgets('23 каркас-урок — плашки значений', (tester) async {
    await pumpCardShot(tester, () => probeEnv(fixtureCard(day, SessionKind.phraseIntro), CardProbe(), day: day));
    await shoot(tester, '23-32-1-meanings');
    await settleCard(tester);
  });

  testWidgets('24 каркас-урок — одно значение и «В разговоре»', (tester) async {
    final one = fixtureCardEdited('day-doctor', 'phrase_intro', (p) {
      final frame = p['frame'] as Map<String, dynamic>;
      final slot = frame['slot'] as Map<String, dynamic>;
      slot['fillers'] = [(slot['fillers'] as List).first];
    });
    await pumpCardShot(tester, () => probeEnv(one, CardProbe(), day: day));
    await shoot(tester, '24-32-1-whole');
    await settleCard(tester);
  });

  testWidgets('25 «Вставь в окно» — без «прослушать» у вариантов', (tester) async {
    final beginner = sessionFixture('day-doctor-beginner');
    await pumpCardShot(tester, () => probeEnv(fixtureCard(beginner, SessionKind.phraseSlot), CardProbe(), day: beginner, level: PlanLevel.beginner));
    await shoot(tester, '25-32-4-slot');
    await settleCard(tester);
  });

  testWidgets('26 «Скажи целиком» — плашки значений и «своё слово»', (tester) async {
    await pumpCardShot(tester, () => probeEnv(fixtureCard(day, SessionKind.phraseOtherSlot), CardProbe(), day: day));
    await shoot(tester, '26-32-7-rounds');
    await settleCard(tester);
  });
}
