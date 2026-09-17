import 'dart:io';

import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/app_version.dart';
import 'package:eng_std/data/line_audio.dart';
import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart' show AppUser;
import 'package:eng_std/data/plan/plan_models.dart';
import 'package:eng_std/data/plan/session/session_day.dart';
import 'package:eng_std/data/plan/session/session_outcomes.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/plan/session/cards/speak_cards.dart';
import 'package:eng_std/features/plan/session/session_controller.dart';
import 'package:eng_std/features/plan/session/session_screen.dart';
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../../support/session_harness.dart';

/// THE DAY SUMMARY AND «CLOSE THE DAY» ON THE REAL SCREEN (work order SESSION-1c §5, canvas 30-7): a day with every
/// card answered opens on its summary; «Close the day» is the contract's `POST …/days/{n}/close`, then the screen gives
/// way to the day window. And the recognizer's locale the screen hands each card (§1).
class _Backend implements SessionBackend {
  _Backend(this.raw);

  final Map<String, dynamic> raw;
  int closes = 0;

  @override
  Future<SessionDay> day(String planId, int number) async => SessionDay.fromJson(raw);

  @override
  Future<void> open(String planId, int number) async {}

  @override
  Future<Plan> plan(String planId) async => _plan();

  @override
  Future<Plan> retryLesson(String planId, String sceneId) async => _plan();

  @override
  Future<SessionAnswerOutcome> answer(String planId, int number, String cardId, SessionAnswer answer) => throw UnimplementedError();

  @override
  Future<SessionJudgeOutcome> judge(String planId, int number, String cardId, {required String heard, required bool hinted}) =>
      throw UnimplementedError();

  @override
  Future<SessionDay> close(String planId, int number) async {
    closes++;
    final closed = sessionFixtureJson('day-doctor');
    (closed['day'] as Map<String, dynamic>)['status'] = 'closed';
    return SessionDay.fromJson(closed);
  }
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
    for (final (n, status) in [(1, 'ready'), (2, 'building')])
      {
        'id': 'ulid-day-$n',
        'number': n,
        'type': 'scene',
        'status': n == 1 ? 'in_progress' : 'locked',
        'slot': {'code': n == 1 ? 'today' : 'tomorrow'},
        'cards_total': 0,
        'cards_done': 0,
        'minutes_spent': 0,
        'lesson_status': status,
      },
  ],
  'scenes': const <Object>[],
  'rescue_kit': const <Object>[],
  'versions': const <String, dynamic>{},
});

/// Every card answered; [open] — the positions per stage left unanswered.
Map<String, dynamic> _day({Map<String, Set<int>> open = const {}, Map<String, Set<int>> returns = const {}}) {
  final raw = sessionFixtureJson('day-doctor');
  (raw['day'] as Map<String, dynamic>)['minutes_spent'] = 19;
  for (final s in (raw['stages'] as List).cast<Map<String, dynamic>>()) {
    for (final c in (s['cards'] as List).cast<Map<String, dynamic>>()) {
      if (open[s['stage']]?.contains(c['position']) ?? false) continue;
      c['result'] = 'passed';
      c['attempts'] = 1;
      c['returns'] = returns[s['stage']]?.contains(c['position']) ?? false;
    }
  }
  return raw;
}

Future<void> _open(WidgetTester tester, _Backend backend) async {
  tester.view.physicalSize = const Size(390, 844) * 2;
  tester.view.devicePixelRatio = 2;
  addTearDown(tester.view.reset);
  final messenger = tester.binding.defaultBinaryMessenger;
  for (final name in ['flutter_tts', 'com.denis.engstd/app_info']) {
    messenger.setMockMethodCallHandler(MethodChannel(name), (call) async => null);
    addTearDown(() => messenger.setMockMethodCallHandler(MethodChannel(name), null));
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

void main() {
  // CATCHES: a finished day that opens on a locked entry (1b), a summary that counts on the phone, a close that is
  // never sent, and a screen that stays after the day is closed.
  testWidgets('every card answered — 30-7: minutes, the plate, what returns, the next day, day_done; «Close the day» — POST and back', (tester) async {
    final sounds = recordSessionSounds(tester);
    final backend = _Backend(_day(returns: {'words': {3}, 'phrases': {2}, 'dialogue': {1}}));
    await _open(tester, backend);

    expect(find.text('День пройден · 19 минут'), findsOneWidget);
    expect(find.byKey(const ValueKey('day-summary-plate')), findsOneWidget);
    for (final stage in ['Слова', 'Фразы', 'Диалог', 'Слушаю и отвечаю', 'Говорю сам']) {
      expect(find.descendant(of: find.byKey(const ValueKey('day-summary-plate')), matching: find.text(stage)), findsOneWidget, reason: stage);
    }
    expect(find.text('ВЕРНЁТСЯ ЗАВТРА'), findsOneWidget);
    expect(find.text('3 карточки: 1 слово, 1 фраза и 1 реплика.'), findsOneWidget);
    expect(find.text('День 2 — собираю'), findsOneWidget);
    expect(sounds, contains('day_done'));

    await tester.tap(find.byKey(const ValueKey('day-summary-close')));
    await tester.pump();
    // The page transition back runs 450 ms.
    await tester.pump(const Duration(seconds: 1));
    await tester.pump();
    expect(backend.closes, 1);
    expect(find.byType(SessionScreen), findsNothing, reason: 'back to the day window, which reads the plan again');
    expect(find.text('window'), findsOneWidget);
  });

  // CATCHES: a retelling recognized in the target language, an echo recognized in the native one.
  testWidgets('the screen hands each card its recognition locale: speak_retell — ru_RU, speak_echo — en_US', (tester) async {
    for (final (position, kind, locale) in [(8, SpeakRetellCard, 'ru_RU'), (7, SpeakEchoCard, 'en_US')]) {
      await _open(tester, _Backend(_day(open: {'speak': {position}})));
      expect(find.text('Говорю сам'), findsWidgets);
      await tester.tap(find.text('Начать'));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 100));
      final card = find.byType(kind);
      expect(card, findsOneWidget);
      final env = switch (tester.widget(card)) {
        final SpeakRetellCard c => c.env,
        final SpeakEchoCard c => c.env,
        _ => throw StateError('unexpected card'),
      };
      expect(env.localeId, locale, reason: '$kind');
      await tester.pumpWidget(const SizedBox());
      await tester.pump(const Duration(seconds: 5));
    }
  });
}
