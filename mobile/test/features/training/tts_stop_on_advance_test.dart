import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/data/review_sync.dart';
import 'package:eng_std/features/training/session_screen.dart';
import 'package:eng_std/l10n/app_localizations.dart';

/// Leaving a card must silence it (QA-21).
///
/// The verdict's auto-pronounce is fired on a timer AFTER the feedback settles, so a «Дальше»
/// tapped while it is still speaking used to carry the previous card's word over the slide and
/// finish it on top of the next card. The fix cuts the engine at every way forward, and this pins
/// it at the flutter_tts method channel — the only place a "did the TTS actually stop" question has
/// an answer that does not depend on the plugin's internals.
class _SilentReviewSync extends ReviewSync {
  _SilentReviewSync(Ref ref)
      : super(
          ref.read(apiClientProvider),
          ref.read(reviewQueueProvider),
          ref.read(seqCounterProvider),
          ref,
        );

  @override
  Future<void> record({
    required String termId,
    required String exerciseMode,
    required String response,
    bool usedHint = false,
    bool isPractice = false,
    int? latencyMs,
    String? sessionId,
    int? ladderStep,
  }) async {}

  @override
  Future<void> flush() async {}
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  const ttsChannel = MethodChannel('flutter_tts');
  final ttsCalls = <String>[];

  // The mirror is closed AFTER the widget tree is gone: closing a drift database from inside
  // `onDispose` schedules a timer the test binding then reports as still pending.
  AppDatabase? db;

  setUp(() {
    ttsCalls.clear();
    FlutterSecureStorage.setMockInitialValues({});
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(ttsChannel, (call) async {
      ttsCalls.add(call.method);
      return 1; // flutter_tts reads 1 as "queued/ok" for speak & friends
    });
  });

  tearDown(() async {
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(ttsChannel, null);
    await db?.close();
    db = null;
  });

  SessionCard card(String id, String answer, List<String> options) => SessionCard(
        termId: id,
        mode: ExerciseMode.multipleChoice,
        type: 'word',
        prompt: 'бронь',
        answer: answer,
        options: options,
      );

  Widget host() => ProviderScope(
        overrides: [
          appDatabaseProvider.overrideWith((ref) => db = AppDatabase.forTesting(NativeDatabase.memory())),
          reviewSyncProvider.overrideWith((ref) => _SilentReviewSync(ref)),
          studySessionProvider.overrideWith((ref, args) async => StudySession(
                sessionId: args.sessionId,
                cards: [
                  card('01M08AP74HM20AA0FDH2RSF5XS', 'reservation', const ['reservation', 'registration']),
                  card('01M08AP74HM20AA0FDH2RSF5XT', 'luggage', const ['luggage', 'baggage claim']),
                ],
              )),
        ],
        child: const MaterialApp(
          locale: Locale('ru'),
          localizationsDelegates: AppLocalizations.localizationsDelegates,
          supportedLocales: [Locale('ru')],
          home: SessionScreen(title: 'Аэропорт'),
        ),
      );

  /// Tear the tree down INSIDE the test and drain what that schedules — drift cancels its query
  /// streams with a zero-duration timer the binding would otherwise report as pending.
  Future<void> close(WidgetTester tester) async {
    await tester.pumpWidget(const SizedBox.shrink());
    await tester.pump(const Duration(milliseconds: 1));
  }

  testWidgets('«Дальше» stops the current utterance before the next card', (tester) async {
    await tester.pumpWidget(host());
    await tester.pumpAndSettle();

    await tester.tap(find.text('reservation'));
    await tester.pumpAndSettle();

    ttsCalls.clear(); // the verdict's own pronounce is not what this is about
    await tester.tap(find.text('Дальше'));
    await tester.pumpAndSettle();

    expect(ttsCalls, contains('stop'));
    expect(find.text('2 из 2'), findsOneWidget, reason: 'and it still advanced');

    await close(tester);
  });

  testWidgets('the LAST card\'s «Дальше» — the jump to the summary — stops it too', (tester) async {
    await tester.pumpWidget(host());
    await tester.pumpAndSettle();

    await tester.tap(find.text('reservation'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Дальше'));
    await tester.pumpAndSettle();

    await tester.tap(find.text('luggage'));
    await tester.pumpAndSettle();

    ttsCalls.clear();
    await tester.tap(find.text('Дальше'));
    await tester.pumpAndSettle();

    // The summary is a different screen, and a word still finishing over it is the same bug.
    expect(ttsCalls, contains('stop'));

    await close(tester);
  });

  testWidgets('leaving by the ✕ stops it as the confirm dialog opens, not after the pop', (tester) async {
    await tester.pumpWidget(host());
    await tester.pumpAndSettle();

    await tester.tap(find.text('reservation'));
    await tester.pumpAndSettle();

    ttsCalls.clear();
    await tester.tap(find.bySemanticsLabel('Закрыть').first);
    await tester.pumpAndSettle();

    // The dialog is up and the word is already silent — `dispose`'s own `release` only runs once
    // the screen is actually gone, which is too late for someone reading a confirm dialog.
    expect(find.text('Выйти'), findsOneWidget);
    expect(ttsCalls, contains('stop'));

    await close(tester);
  });
}
