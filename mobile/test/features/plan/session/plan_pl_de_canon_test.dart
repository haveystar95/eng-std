import 'dart:async';
import 'dart:io';

import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/app_version.dart';
import 'package:eng_std/data/audio_mixer.dart';
import 'package:eng_std/data/languages.dart' show sttLocaleFor;
import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart' show AppUser;
import 'package:eng_std/data/plan/conversation/conversation_models.dart';
import 'package:eng_std/data/plan/plan_models.dart';
import 'package:eng_std/data/plan/session/session_day.dart';
import 'package:eng_std/data/plan/session/session_outcomes.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/data/speech/speech_recognizer.dart';
import 'package:eng_std/features/plan/conversation/talk_ribbon.dart' show TalkDock;
import 'package:eng_std/features/plan/day/window/window_words.dart';
import 'package:eng_std/features/plan/session/cards/card_kit.dart';
import 'package:eng_std/features/plan/session/cards/phrase_cards.dart';
import 'package:eng_std/features/plan/session/cards/speak_cards.dart';
import 'package:eng_std/features/plan/session/cards/word_cards.dart';
import 'package:eng_std/features/plan/session/parts/session_mic_panel.dart';
import 'package:eng_std/features/plan/session/session_controller.dart';
import 'package:eng_std/features/plan/session/session_mic.dart';
import 'package:eng_std/features/plan/session/session_screen.dart';
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../../support/day_window_harness.dart';
import '../../../support/plan_goldens.dart' show planFixture, setUpPlanGoldens;
import '../../../support/talk_harness.dart' show FakeTalkBackend, TalkProbe;

/// A PLAN IN A PAIR WITHOUT RUSSIAN OR ENGLISH (work order LANG-1, part C): German studied, Polish native.
///
/// The fixture `test/fixtures/plan/plan_pl_de.json` is SYNTHETIC — shapes of the server's answers (the plan, the day
/// with its cards and window, the talk of day 1), every text written anew: German lines, Polish translations, and
/// readings of German in Polish orthography («ryken», «mir tut der ryken we», «gutn morgn») — the Latin-script reading
/// lesson_day.v4.8 writes for a Latin-script native. It pins three things the pair decides:
///
///  * every microphone of the day listens in the TARGET's locale — de_DE, never the native's pl_PL (and never the
///    en_US every other fixture of the session has);
///  * the reading is printed as the server sent it — the phone neither transliterates nor hides a Latin reading;
///  * the hint strings reach the recognizer as the day has them.
///
/// The recognizer's own request (on the device or on Apple's server) is pinned in
/// `test/data/speech/speech_recognizer_test.dart`; here the locale it is asked in, and that German stays on the device.
Map<String, dynamic> _fixture() => planFixture('plan_pl_de');

Plan _plan() => Plan.fromJson(_fixture()['plan'] as Map<String, dynamic>);

List<Map<String, dynamic>> _cardsOf(Map<String, dynamic> day) => [
  for (final s in (day['stages'] as List).cast<Map<String, dynamic>>()) ...(s['cards'] as List).cast<Map<String, dynamic>>(),
];

/// The day as the server reads it with every card answered except [open] (stage → positions); [talkNext] — every card
/// answered and the window's talk row current, so the session opens on the talk's entry.
Map<String, dynamic> _day({Map<String, Set<int>> open = const {}, bool talkNext = false}) {
  final raw = _fixture()['day'] as Map<String, dynamic>;
  for (final c in _cardsOf(raw)) {
    if (open[c['stage']]?.contains(c['position']) ?? false) continue;
    c
      ..['result'] = 'passed'
      ..['attempts'] = 1;
  }
  if (talkNext) {
    final window = raw['window'] as Map<String, dynamic>;
    window['stages'] = [
      for (final r in (window['stages'] as List).cast<Map<String, dynamic>>())
        {...r, 'state': r['stage'] == 'conversation' ? 'current' : 'done'},
    ];
  }
  return raw;
}

/// A MICROPHONE THAT WRITES DOWN WHAT IT WAS ASKED FOR — the locale and the hint strings of every attempt; the
/// recording stays open until something stops it, as a real one does.
class _RecordingRecognizer implements SpeechRecognizer {
  final List<({String localeId, List<String> contextual, List<String> expected})> asked = [];
  Completer<SpeechAttempt>? _open;

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
  }) {
    asked.add((localeId: localeId, contextual: List.of(contextualStrings), expected: List.of(expected)));
    final open = Completer<SpeechAttempt>();
    _open = open;
    return open.future;
  }

  @override
  Future<void> stop() async => _close();

  @override
  Future<void> cancel() async => _close();

  void _close() {
    final open = _open;
    _open = null;
    if (open != null && !open.isCompleted) open.complete(const SpeechAttempt.silent());
  }
}

class _Backend implements SessionBackend {
  _Backend(this.raw);

  final Map<String, dynamic> raw;

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
  Future<SessionDay> close(String planId, int number) => throw UnimplementedError();
}

class _Auth extends AuthController {
  @override
  Future<AppUser?> build() async => AppUser(id: '01TEST', name: 'Test');
}

/// The real session of day 1 over [backend], its talk over [talk], its microphone [recognizer].
Future<void> _openSession(WidgetTester tester, _Backend backend, _RecordingRecognizer recognizer, {TalkProbe? talk}) async {
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
        lineAudioCacheProvider.overrideWithValue(RecordingLines()),
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
        home: SessionScreen(
          plan: plan,
          number: 1,
          backend: backend,
          talkBackend: talk == null ? null : FakeTalkBackend(talk),
        ),
      ),
    ),
  );
  for (var i = 0; i < 4; i++) {
    await tester.pump(const Duration(milliseconds: 100));
  }
}

/// «Начать» on the stage entry, and the first open card of the stage on screen.
Future<void> _start(WidgetTester tester) async {
  await tester.tap(find.text('Начать'));
  await tester.pump();
  await tester.pump(const Duration(milliseconds: 100));
}

/// The environment the screen handed the card on screen.
CardEnv _envOnScreen(WidgetTester tester) {
  for (final (type, env) in <(Type, CardEnv Function(Widget))>[
    (WordIntroCard, (w) => (w as WordIntroCard).env),
    (WordRepeatCard, (w) => (w as WordRepeatCard).env),
    (PhraseIntroCard, (w) => (w as PhraseIntroCard).env),
    (PhraseRepeatCard, (w) => (w as PhraseRepeatCard).env),
    (SpeakAnswerCard, (w) => (w as SpeakAnswerCard).env),
    (SpeakEchoCard, (w) => (w as SpeakEchoCard).env),
    (SpeakRetellCard, (w) => (w as SpeakRetellCard).env),
  ]) {
    final found = find.byType(type);
    if (found.evaluate().isNotEmpty) return env(tester.widget(found));
  }
  throw StateError('no card of a known kind on screen');
}

/// The microphone the card on screen built — the dock of 30-3, or the talk's dock on «Ответь сам» (35-2).
SessionMic _micOnScreen(WidgetTester tester) {
  final panel = find.byType(SessionMicPanel);
  if (panel.evaluate().isNotEmpty) return tester.widget<SessionMicPanel>(panel).mic;
  final dock = find.byType(TalkDock);
  expect(dock, findsOneWidget, reason: 'a voice card has a microphone');
  return tester.widget<TalkDock>(dock).debugMic!;
}

/// Leave the screen and let the voice's and the microphone's timers run out.
Future<void> _leave(WidgetTester tester) async {
  await tester.pumpWidget(const SizedBox());
  await tester.pump(const Duration(seconds: 20));
}

void main() {
  // Real fonts and a network stub for the scene's photos (the talk's ribbon draws them).
  setUpAll(setUpPlanGoldens);

  final day = _fixture()['day'] as Map<String, dynamic>;
  final window = day['window'] as Map<String, dynamic>;
  final program = window['program'] as Map<String, dynamic>;
  List<Map<String, dynamic>> itemsOf(String tab) => ((program[tab] as Map<String, dynamic>)['items'] as List).cast<Map<String, dynamic>>();

  test('the fixture is the pair it claims: de studied, pl native, and not a letter of Cyrillic', () {
    final plan = _plan();
    expect(plan.targetLang, 'de');
    expect(plan.nativeLang, 'pl');
    expect(RegExp('[\u0400-\u04FF]').hasMatch(planFixtureText()), isFalse, reason: 'readings in the native\'s own alphabet');
  });

  // RULE (LANG-1: seven targets; SESSION-2b §4: every card is recognized in the TARGET language): the STT locale is
  // one table read by code — every target maps to its own locale, none falls through to the en_US default.
  // CATCHES: a target missing from the table (its microphone silently listening for English), a hyphenated locale the
  // plugin does not take.
  test('sttLocaleFor — every one of the seven targets has its own locale', () {
    expect(
      {for (final code in ['en', 'pl', 'ro', 'es', 'it', 'de', 'fr']) code: sttLocaleFor(code)},
      {'en': 'en_US', 'pl': 'pl_PL', 'ro': 'ro_RO', 'es': 'es_ES', 'it': 'it_IT', 'de': 'de_DE', 'fr': 'fr_FR'},
    );
  });

  group('the microphone of a German day listens in German', () {
    // RULE: the card's recognizer locale is the plan's TARGET — de_DE for a German plan whose native is Polish; the
    // microphone the card builds is in that locale, and German keeps the on-device request (only pl and ro go to the
    // server).
    // CATCHES: the native's locale on a card (pl_PL — the recognizer after Polish while the learner says German), the
    // en_US default of every other fixture left in, a German plan sent to the server.
    testWidgets('every voice card of the day: the env, the card\'s own microphone and the recognizer — de_DE', (tester) async {
      for (final (stage, position, kind) in [
        ('words', 3, WordRepeatCard),
        ('phrases', 4, PhraseRepeatCard),
        ('speak', 1, SpeakAnswerCard),
        ('speak', 3, SpeakEchoCard),
        ('speak', 4, SpeakRetellCard),
      ]) {
        final recognizer = _RecordingRecognizer();
        await _openSession(tester, _Backend(_day(open: {stage: {position}})), recognizer);
        await _start(tester);
        expect(find.byType(kind), findsOneWidget, reason: '$stage $position');
        final env = _envOnScreen(tester);
        expect(env.targetLang, 'de', reason: '$kind');
        expect(env.localeId, 'de_DE', reason: '$kind');

        final mic = _micOnScreen(tester);
        expect(mic.localeId, 'de_DE', reason: '$kind: the card\'s own microphone');
        unawaited(mic.tap());
        await tester.pump();
        await tester.pump();
        expect(recognizer.asked, isNotEmpty, reason: '$kind: the tap reaches the recognizer');
        expect(recognizer.asked.first.localeId, 'de_DE', reason: '$kind');
        expect(PluginSpeechRecognizer.onDeviceFor(recognizer.asked.first.localeId), isTrue, reason: 'German stays on the device');
        await _leave(tester);
      }
    });

    // RULE (SESSION-2b §4 — the pattern of session_day_summary_test): the talk's microphone is in the target language
    // too, and its hint strings are the day's phrases exactly as the window has them — umlauts, capitals, the stop.
    // CATCHES: the talk listening in pl_PL or en_US; the hints folded, re-cased or re-ordered on the way.
    testWidgets('the talk: its microphone is de_DE and its hints are the day\'s phrases, unchanged', (tester) async {
      final recognizer = _RecordingRecognizer();
      final probe = TalkProbe()..documents.add(PlanConversation.fromJson(_fixture()['conversation']));
      await _openSession(tester, _Backend(_day(talkNext: true)), recognizer, talk: probe);
      expect(find.byKey(const ValueKey('talk-entry-start')), findsOneWidget, reason: 'every card answered — the talk is next');

      await tester.tap(find.byKey(const ValueKey('talk-entry-start')));
      await tester.pump();
      await tester.pump();
      await tester.pump();
      expect(probe.starts, 1);
      expect(find.text('Guten Morgen! Was fehlt Ihnen?'), findsOneWidget, reason: 'the role\'s German line on the ribbon');

      await tester.tap(find.byKey(const ValueKey('talk-mic')));
      await tester.pump();
      await tester.pump();
      expect(recognizer.asked, isNotEmpty, reason: 'the tap reaches the recognizer');
      final asked = recognizer.asked.first;
      expect(asked.localeId, 'de_DE');
      expect(asked.contextual, [for (final p in itemsOf('phrases')) p['text'] as String]);
      expect(asked.contextual, contains('Mir tut der Rücken weh.'));
      await _leave(tester);
    });
  });

  group('the reading is printed as it came — Latin letters for a Polish native', () {
    // RULE (lesson_day.v4.8: the reading is written in the native's alphabet, not «Cyrillic for Russian only»): the
    // phone prints `pronunciation` verbatim — no transliteration, no «Cyrillic only» filter that drops a Latin one.
    // CATCHES: a reading hidden because it is not Cyrillic, one rebuilt from the term, one run through a transliterator.
    testWidgets('the day window: the word\'s and the phrase\'s reading, verbatim', (tester) async {
      final server = WindowServer(PlanDayRoom.fromJson(_fixture()['day'] as Map<String, dynamic>), plan: _plan());
      await pumpDayWindowServer(tester, server);

      final words = itemsOf('words');
      expect(words.map((w) => w['pronunciation']), ['ryken', 'fiber']);
      for (final w in words) {
        final card = find.ancestor(of: find.text(w['term'] as String).first, matching: find.byType(WindowWordCard));
        expect(find.descendant(of: card, matching: find.text(w['pronunciation'] as String)), findsOneWidget, reason: '${w['term']}');
      }

      await scrollToHeader(tester);
      await openWindowTab(tester, 'Фразы');
      for (final p in itemsOf('phrases')) {
        expect(find.text(p['pronunciation'] as String), findsOneWidget, reason: '${p['text']}');
      }
      expect(find.text('mir tut der ryken we'), findsOneWidget);
    });

    testWidgets('the phrase card (32-1) and the word card: the reading line is the server\'s string', (tester) async {
      await _openSession(tester, _Backend(_day(open: {'phrases': {1, 2, 3, 4}})), _RecordingRecognizer());
      await _start(tester);
      expect(find.byType(PhraseIntroCard), findsOneWidget);
      expect(tester.widget<Text>(find.byKey(const ValueKey('lesson-reading'))).data, 'mir tut der ryken we');
      expect(tester.widget<Text>(find.byKey(const ValueKey('lesson-native'))).data, 'Bolą mnie plecy.');
      await _leave(tester);

      await _openSession(tester, _Backend(_day(open: {'words': {1, 2, 3, 4}})), _RecordingRecognizer());
      await _start(tester);
      expect(find.byType(WordIntroCard), findsOneWidget);
      expect(find.text('ryken'), findsOneWidget, reason: 'the word\'s reading under the German term');
      expect(find.text('Rücken'), findsWidgets);
      await _leave(tester);
    });
  });
}

/// The fixture file as text — for the one check that reads it as letters, not as JSON.
String planFixtureText() => File('test/fixtures/plan/plan_pl_de.json').readAsStringSync();
