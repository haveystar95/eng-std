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
import 'package:eng_std/data/plan/session/session_outcomes.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/plan/session/parts/session_mic_panel.dart';
import 'package:eng_std/features/plan/session/session_controller.dart';
import 'package:eng_std/features/plan/session/session_screen.dart';
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../../support/nbsp.dart';
import '../../../support/session_harness.dart';
import '../../../support/speech_probe_channel.dart';

/// THE MICROPHONE'S PRE-PERMISSION ON THE REAL SESSION SCREEN (frame 41-3, work order CLIENT-START §4): asked on the
/// first card with a microphone, never before; the system's dialogs only after «Разрешить микрофон»; «Позже» — this
/// card offers «Пропустить», the next microphone card asks again; iOS already answered — no sheet.
void main() {
  final probe = mockSpeechProbe();

  Future<void> undetermined() async {
    probe
      ..microphone = 'not_determined'
      ..recognition = 'not_determined';
  }

  // ЛОВИТ: вопрос о микрофоне на входе в этап (до карточки) и системный алерт без предразрешения.
  testWidgets('вход в этап не спрашивает; первая карточка с микрофоном — лист, а не iOS', (tester) async {
    await undetermined();
    final recognizer = _CountingRecognizer();
    await _open(tester, _Backend(_day(open: {'speak': {1, 2}})), recognizer);

    expect(find.text('Говорю сам'), findsWidgets);
    expect(find.byKey(const ValueKey('mic-ask')), findsNothing, reason: 'the stage entry has no microphone');

    await _start(tester);
    expect(find.byKey(const ValueKey('mic-ask')), findsOneWidget);
    expect(find.text(nbTypo('Ritora слушает, как ты говоришь')), findsOneWidget);
    expect(recognizer.prepares, 0, reason: 'iOS is asked only after «Разрешить микрофон»');
    await _close(tester);
  });

  // ЛОВИТ: «Позже», после которого карточка ждёт голоса, которого не будет, и «Позже», которое запоминается навсегда.
  testWidgets('«Позже» — карточка предлагает «Пропустить»; следующая карточка с микрофоном спрашивает снова', (tester) async {
    await undetermined();
    final recognizer = _CountingRecognizer();
    final backend = _Backend(_day(open: {'speak': {1, 2}}));
    await _open(tester, backend, recognizer);
    await _start(tester);

    await tester.tap(find.text('Позже'));
    await _settle(tester);
    expect(find.byKey(const ValueKey('mic-ask')), findsNothing);
    expect(find.byType(SessionNoMicView), findsOneWidget);
    expect(recognizer.prepares, 0);

    await tester.tap(find.text('Пропустить'));
    await _settle(tester);
    expect(backend.answered, hasLength(1));
    expect(find.byKey(const ValueKey('mic-ask')), findsOneWidget, reason: 'the next microphone card asks again');
    await _close(tester);
  });

  // ЛОВИТ: «Разрешить», которое не доходит до системного вопроса.
  testWidgets('«Разрешить микрофон» — только теперь спрашивается iOS', (tester) async {
    await undetermined();
    final recognizer = _CountingRecognizer();
    await _open(tester, _Backend(_day(open: {'speak': {1, 2}})), recognizer);
    await _start(tester);

    await tester.tap(find.text('Разрешить микрофон'));
    await _settle(tester);
    expect(recognizer.prepares, 1);
    expect(find.byKey(const ValueKey('mic-ask')), findsNothing);
    await _close(tester);
  });

  // ЛОВИТ: лист у того, кто уже разрешил, — и у того, кто запретил в Настройках (его карточка, как прежде, сама
  // предлагает «Пропустить» и Настройки).
  for (final (answered, what) in [('granted', 'разрешил'), ('denied', 'запретил')]) {
    testWidgets('iOS уже ответил ($what) — листа нет', (tester) async {
      probe
        ..microphone = answered
        ..recognition = answered;
      final recognizer = _CountingRecognizer();
      await _open(tester, _Backend(_day(open: {'speak': {1, 2}})), recognizer);
      await _start(tester);

      expect(find.byKey(const ValueKey('mic-ask')), findsNothing);
      expect(recognizer.prepares, 0);
      await _close(tester);
    });
  }
}

Future<void> _start(WidgetTester tester) async {
  await tester.tap(find.text('Начать'));
  await _settle(tester);
}

/// The card's post-frame ask goes through the (mocked) probe channel — a few frames bring the sheet up.
Future<void> _settle(WidgetTester tester) async {
  for (var i = 0; i < 6; i++) {
    await tester.pump(const Duration(milliseconds: 100));
  }
}

Future<void> _close(WidgetTester tester) async {
  await tester.pumpWidget(const SizedBox());
  await tester.pump(const Duration(seconds: 5));
}

class _CountingRecognizer extends SilentRecognizer {
  int prepares = 0;

  @override
  Future<bool> prepare() async {
    prepares++;
    return true;
  }
}

/// Every card answered but [open] (positions per stage).
Map<String, dynamic> _day({Map<String, Set<int>> open = const {}}) {
  final raw = sessionFixtureJson('day-doctor');
  for (final s in (raw['stages'] as List).cast<Map<String, dynamic>>()) {
    for (final c in (s['cards'] as List).cast<Map<String, dynamic>>()) {
      if (open[s['stage']]?.contains(c['position']) ?? false) continue;
      c['result'] = 'passed';
      c['attempts'] = 1;
      c['returns'] = false;
    }
  }
  return raw;
}

class _Backend implements SessionBackend {
  _Backend(this.raw);

  final Map<String, dynamic> raw;
  final List<String> answered = [];

  @override
  Future<SessionDay> day(String planId, int number) async => SessionDay.fromJson(raw);

  @override
  Future<void> open(String planId, int number) async {}

  @override
  Future<Plan> plan(String planId) async => _plan();

  @override
  Future<Plan> retryLesson(String planId, String sceneId) async => _plan();

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

Plan _plan() => Plan.fromJson({
  'id': 'ulid-0001',
  'status': 'active',
  'goal_text': 'врач',
  'target_lang': 'en',
  'native_lang': 'ru',
  'level': 'intermediate',
  'days_total': 2,
  'days_requested': 2,
  'route_summary': '',
  'days': [
    for (final n in [1, 2])
      {
        'id': 'ulid-day-$n',
        'number': n,
        'type': 'scene',
        'status': n == 1 ? 'in_progress' : 'locked',
        'slot': {'code': n == 1 ? 'today' : 'tomorrow'},
        'cards_total': 0,
        'cards_done': 0,
        'minutes_spent': 0,
        'lesson_status': 'ready',
      },
  ],
  'scenes': const <Object>[],
  'rescue_kit': const <Object>[],
  'versions': const <String, dynamic>{},
});

Future<void> _open(WidgetTester tester, _Backend backend, SilentRecognizer recognizer) async {
  tester.view.physicalSize = const Size(390, 844) * 2;
  tester.view.devicePixelRatio = 2;
  addTearDown(tester.view.reset);
  final messenger = tester.binding.defaultBinaryMessenger;
  for (final channel in [const MethodChannel('flutter_tts'), const MethodChannel('com.denis.engstd/app_info'), AudioMixer.channel]) {
    messenger.setMockMethodCallHandler(channel, (call) async => null);
    addTearDown(() => messenger.setMockMethodCallHandler(channel, null));
  }
  final plan = _plan();
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        appDatabaseProvider.overrideWith((ref) {
          final db = AppDatabase.forTesting(NativeDatabase.memory());
          ref.onDispose(db.close);
          return db;
        }),
        lineAudioCacheProvider.overrideWithValue(LineAudioCache(directory: Directory.systemTemp)),
        speechRecognizerProvider.overrideWithValue(recognizer),
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
                  MaterialPageRoute<void>(builder: (_) => SessionScreen(plan: plan, number: 1, backend: backend)),
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
