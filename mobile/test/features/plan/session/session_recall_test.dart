import 'dart:io';

import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/app_version.dart';
import 'package:eng_std/data/audio_mixer.dart';
import 'package:eng_std/data/line_audio.dart';
import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart' show AppUser;
import 'package:eng_std/data/plan/plan_models.dart';
import 'package:eng_std/data/plan/session/session_day.dart';
import 'package:eng_std/data/plan/session/session_models.dart';
import 'package:eng_std/data/plan/session/session_outcomes.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/plan/session/session_controller.dart';
import 'package:eng_std/features/plan/session/session_screen.dart';
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../../support/plan_goldens.dart' show planFrom;
import '../../../support/session_harness.dart';

/// «ВСПОМНИТЬ» — THE REHEARSAL'S OVERVIEW AND ITS STAGE (кадры 37-3, 35-4, 37-4; наряд CLIENT-CONV-1b), over the
/// rehearsal the e2e stand dealt (the server's `day-rehearsal.json` of BACK-TAILS-2): two scenes named as the plan names
/// them, seven own lines each, ten retells.
void main() {
  final day = sessionFixture('day-rehearsal');
  final overview = fixtureCard(day, SessionKind.recallScenes);
  final payload = overview.payload as RecallScenesPayload;
  final first = payload.scenes.first;
  final last = payload.scenes.last;

  group('37-3 · обзор', () {
    // ПРАВИЛО (кадр 37-3): страница на сцену — свои реплики сцены с переводом и «прослушать» 44; сцены листаются
    // «Дальше» и свайпом, точки показывают, где ты; на последней кнопка — «Дальше — повтори вслух», и её тап —
    // единственный ответ карточки (`passed`). Ничего не проверяется и не выбирается.
    // ЛОВИТ: ответ на каждой странице, «Дальше» без смены подписи на последней, обзор, который уходит, не показав
    // вторую сцену.
    testWidgets('сцены по одной: «Дальше» листает, на последней — «Дальше — повтори вслух», ответ один', (tester) async {
      final probe = CardProbe();
      final shown = <String>[];
      await pumpCard(tester, probeEnv(overview, probe, day: day, showScene: shown.add), size: const Size(390, 844));
      await tester.pump();
      expect(find.text('Вспомни свои реплики'), findsOneWidget);
      expect(shown, [first.sceneId], reason: 'the strip names the scene on screen');
      for (final line in first.lines.take(3)) {
        expect(find.byKey(ValueKey('recall-line-${first.sceneId}-${line.ref}')), findsOneWidget);
        expect(find.text(line.textTarget), findsOneWidget);
        expect(find.text(line.textNative), findsOneWidget);
      }
      expect(find.byKey(const ValueKey('recall-dot-0-current')), findsOneWidget);
      expect(find.byKey(const ValueKey('recall-dot-1')), findsOneWidget);
      expect(find.text('Дальше'), findsOneWidget);

      await tester.tap(find.byKey(const ValueKey('recall-next')));
      await tester.pump();
      expect(probe.answers, isEmpty, reason: 'turning a page is not an answer');
      expect(shown, [first.sceneId, last.sceneId]);
      expect(find.byKey(const ValueKey('recall-dot-1-current')), findsOneWidget);
      expect(find.text('Дальше — повтори вслух'), findsOneWidget);
      expect(find.text(last.lines.first.textTarget), findsOneWidget);

      await tester.tap(find.byKey(const ValueKey('recall-next')));
      await tester.pump();
      expect([for (final a in probe.answers) a.result], [SessionResult.passed]);
      expect(probe.nexts, 1);
      await settleCard(tester);
    });

    testWidgets('свайп листает сцены так же, как «Дальше»', (tester) async {
      final shown = <String>[];
      await pumpCard(tester, probeEnv(overview, CardProbe(), day: day, showScene: shown.add), size: const Size(390, 844));
      await tester.pump();
      await tester.fling(find.byKey(const ValueKey('recall-pages')), const Offset(-300, 0), 1500);
      await tester.pumpAndSettle();
      expect(shown.last, last.sceneId);
      expect(find.text('Дальше — повтори вслух'), findsOneWidget);
      await settleCard(tester);
    });

    // ПРАВИЛО (кадр 37-3): «прослушать» 44 играет эту реплику своим файлом (без файла — телефон); пока она звучит,
    // кружок уступает место латунной волне.
    testWidgets('«прослушать» играет свою реплику, пока звучит — волна', (tester) async {
      final voice = QuietVoice();
      await pumpCard(tester, probeEnv(overview, CardProbe(), day: day, voice: voice), size: const Size(390, 844));
      await tester.pump();
      final line = first.lines[1];
      final key = 'recall-${first.sceneId}-${line.ref}';
      await tester.tap(find.byKey(ValueKey('recall-listen-$key')));
      await tester.pump();
      expect(voice.played.last, '${line.audio!.ref}@1.0');
      expect(voice.fallbacks.last, line.textTarget);
      voice.playing.value = key;
      await tester.pump();
      expect(find.descendant(of: find.byKey(ValueKey('recall-listen-$key')), matching: find.byKey(const ValueKey('recall-listen-wave'))), findsOneWidget);
      voice.playing.value = null;
      await settleCard(tester);
    });

    // ПРАВИЛО (живой проход CLIENT-CONV-1b): семь реплик сцены выше экрана — страница прокручивается, её края тают, а
    // не режут строку пополам; прокрученная до конца, последняя реплика стоит над краем целиком.
    // ЛОВИТ: седьмую реплику, разрезанную точками страниц.
    testWidgets('длинная сцена прокручивается, края тают, последняя реплика достаётся целиком', (tester) async {
      await pumpCard(tester, probeEnv(overview, CardProbe(), day: day), size: const Size(390, 844));
      await tester.pump();
      expect(find.byKey(const ValueKey('recall-soft-edges')), findsOneWidget);
      final pages = tester.getRect(find.byKey(const ValueKey('recall-pages')));
      final lastLine = find.byKey(ValueKey('recall-line-${first.sceneId}-${first.lines.last.ref}'));
      expect(tester.getRect(lastLine).bottom, greaterThan(pages.bottom), reason: 'seven lines are taller than the page');
      await tester.drag(find.byKey(ValueKey('recall-page-${first.sceneId}')), const Offset(0, -600));
      await tester.pumpAndSettle();
      expect(tester.getRect(lastLine).bottom, lessThanOrEqualTo(pages.bottom - 28), reason: 'clear of the bottom fade');
      await settleCard(tester);
    });
  });

  group('«Вспомнить» на экране сессии', () {
    // ПРАВИЛО (кадры 37-3, 35-4): у обзора в шапке — минуты этапа, бусин нет; полоса сцены идёт за страницей обзора и
    // за карточкой пересказа — у репетиции своей сцены нет. Пересказы считаются по обменам СВОИХ сцен: десять
    // пересказов двух сцен — «ещё 10 реплик» и десять бусин.
    // ЛОВИТ: пустую полосу сцены на репетиции, бусины у обзора, «ещё 8 реплик» на десяти пересказах.
    testWidgets('обзор → пересказы: минуты в шапке, полоса идёт за сценой, десять реплик', (tester) async {
      final backend = _Backend(sessionFixtureJson('day-rehearsal'));
      await _open(tester, backend);
      expect(find.text('Вспомнить'), findsWidgets);
      expect(find.text('Свои реплики всех сцен — посмотри, послушай и скажи вслух'), findsOneWidget);
      expect(find.textContaining('Запись к врачу'), findsOneWidget, reason: 'the entry names the first scene');

      await tester.tap(find.text('Начать'));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 400));
      expect(find.text('Вспомни свои реплики'), findsOneWidget);
      expect(find.text('≈ 7 мин'), findsOneWidget, reason: 'the stage\'s minutes from the window, not a count of lines');
      expect(_beads(), findsNothing);
      expect(find.textContaining('Запись к врачу'), findsOneWidget);

      await tester.tap(find.byKey(const ValueKey('recall-next')));
      await tester.pump();
      expect(find.textContaining('Приём у врача'), findsOneWidget, reason: 'the second page — the second scene');

      await tester.tap(find.byKey(const ValueKey('recall-next')));
      for (var i = 0; i < 6; i++) {
        await tester.pump(const Duration(milliseconds: 200));
      }
      expect(backend.answered, [overview.id]);
      expect(find.text('Повтори свою реплику'), findsOneWidget);
      expect(find.text('ещё 10 реплик'), findsOneWidget);
      expect(_beads(), findsNWidgets(10));
      expect(find.textContaining('Запись к врачу'), findsOneWidget, reason: 'the first retell is the first scene\'s');
      await tester.pump(const Duration(seconds: 2));
    });
  });
}

Finder _beads() => find.byWidgetPredicate((w) => w.runtimeType.toString() == '_Bead');

class _Backend implements SessionBackend {
  _Backend(this.raw);

  final Map<String, dynamic> raw;
  final List<String> answered = [];

  @override
  Future<SessionDay> day(String planId, int number) async => SessionDay.fromJson(raw);

  @override
  Future<void> open(String planId, int number) async {}

  @override
  Future<Plan> plan(String planId) async => planFrom('plan_rehearsal');

  @override
  Future<Plan> retryLesson(String planId, String sceneId) async => planFrom('plan_rehearsal');

  @override
  Future<SessionAnswerOutcome> answer(String planId, int number, String cardId, SessionAnswer answer) async {
    answered.add(cardId);
    final card = [
      for (final s in (raw['stages'] as List).cast<Map<String, dynamic>>()) ...(s['cards'] as List).cast<Map<String, dynamic>>(),
    ].firstWhere((c) => c['id'] == cardId);
    return SessionAnswerOutcome.fromJson({
      'card': {...card, 'result': answer.result.wire, 'attempts': answer.attempts, 'returns': false},
      'requeued': null,
      'unit': {'kind': card['unit']['kind'], 'ref': card['unit']['ref'], 'returns_tomorrow': false, 'returns_day': null},
      'day': {'cards_total': 10, 'cards_done': answered.length, 'minutes_spent': 1},
      'stage': {'stage': card['stage'], 'minutes_spent': 1},
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

Future<void> _open(WidgetTester tester, _Backend backend) async {
  tester.view.physicalSize = const Size(390, 844) * 2;
  tester.view.devicePixelRatio = 2;
  addTearDown(tester.view.reset);
  final messenger = tester.binding.defaultBinaryMessenger;
  for (final channel in [const MethodChannel('flutter_tts'), const MethodChannel('com.denis.engstd/app_info'), AudioMixer.channel]) {
    messenger.setMockMethodCallHandler(channel, (call) async => null);
    addTearDown(() => messenger.setMockMethodCallHandler(channel, null));
  }
  final plan = planFrom('plan_rehearsal');
  await tester.pumpWidget(
    ProviderScope(
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
        theme: buildAppTheme(),
        locale: const Locale('ru'),
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        supportedLocales: const [Locale('ru'), Locale('en')],
        builder: (context, child) => MediaQuery(data: MediaQuery.of(context).copyWith(disableAnimations: true), child: child!),
        home: Builder(
          builder: (context) => Scaffold(
            body: Center(
              child: TextButton(
                onPressed: () => Navigator.of(context).push(
                  MaterialPageRoute<void>(builder: (_) => SessionScreen(plan: plan, number: 3, backend: backend)),
                ),
                child: const Text('window'),
              ),
            ),
          ),
        ),
      ),
    ),
  );
  await tester.tap(find.text('window'));
  await tester.pump();
  await tester.pump(const Duration(milliseconds: 400));
  await tester.pump();
}
