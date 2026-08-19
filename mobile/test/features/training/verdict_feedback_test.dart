import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/training/session/session_exercise.dart';
import 'package:eng_std/features/training/session/session_grading.dart';
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

/// The verdict's sound + haptic (QA-21, sounds replaced in QA-22). It fires from `_commit`, which
/// every practice mode shares, so this is pinned on ordinary TAPPED cards rather than on speaking —
/// the point is precisely that it is NOT a speaking-only affordance.
///
/// Two channels now: the haptic still goes out on `flutter/platform`, while the sound is our own
/// `AppFeedback.channel` into `AppDelegate.swift`. Both are mocked so the pair can be asserted
/// together — which is how they are meant to be read, and how a mode that buzzes without a sound
/// would be caught.
void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  final platformCalls = <MethodCall>[];
  final soundCalls = <MethodCall>[];

  setUp(() {
    platformCalls.clear();
    soundCalls.clear();
    final messenger = TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger;
    messenger.setMockMethodCallHandler(SystemChannels.platform, (call) async {
      platformCalls.add(call);
      return null;
    });
    messenger.setMockMethodCallHandler(AppFeedback.channel, (call) async {
      soundCalls.add(call);
      return null;
    });
  });

  tearDown(() {
    final messenger = TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger;
    messenger.setMockMethodCallHandler(SystemChannels.platform, null);
    messenger.setMockMethodCallHandler(AppFeedback.channel, null);
  });

  /// The verdict's haptic — the LAST one, not all of them: tapping an option fires Material's own
  /// press feedback first, and that belongs to the tap, not to the judgement.
  String? haptic() {
    final all = platformCalls.where((c) => c.method == 'HapticFeedback.vibrate').toList();

    return all.isEmpty ? null : '${all.last.arguments}';
  }

  /// Which sound the verdict asked the native side to play.
  String? sound() {
    if (soundCalls.isEmpty) return null;
    final call = soundCalls.last;
    expect(call.method, 'play');

    return (call.arguments as Map)['sound'] as String?;
  }

  SessionCard choiceCard() => SessionCard(
        termId: 'T1',
        mode: ExerciseMode.multipleChoice,
        type: 'word',
        prompt: 'бронь',
        answer: 'reservation',
        options: const ['reservation', 'registration'],
      );

  Widget host(SessionCard card, List<SessionAnswer> answers) => ProviderScope(
        overrides: [
          appDatabaseProvider.overrideWith((ref) {
            final db = AppDatabase.forTesting(NativeDatabase.memory());
            ref.onDispose(db.close);
            return db;
          }),
        ],
        child: MaterialApp(
          locale: const Locale('ru'),
          localizationsDelegates: AppLocalizations.localizationsDelegates,
          supportedLocales: const [Locale('ru'), Locale('en')],
          home: Scaffold(
            body: SingleChildScrollView(
              child: SessionExerciseCard(
                card: card,
                autoPronounce: false,
                onAnswered: answers.add,
                onSpeak: (String text, {bool slow = false}) async {},
                showDue: false,
              ),
            ),
          ),
        ),
      );

  testWidgets('a correct answer plays the accepted sound and a light haptic', (tester) async {
    final answers = <SessionAnswer>[];
    await tester.pumpWidget(host(choiceCard(), answers));
    await tester.pumpAndSettle();

    await tester.tap(find.text('reservation'));
    await tester.pumpAndSettle();

    expect(answers.single.verdict, LocalCheck.correct);
    expect(haptic(), 'HapticFeedbackType.lightImpact');
    expect(sound(), AppFeedback.correctSound);
    // Our own asset, not a system tick — the identifier the native side resolves to a bundled file.
    expect(sound(), 'verdict_correct');
  });

  testWidgets('a wrong answer plays the other sound and a heavier haptic', (tester) async {
    final answers = <SessionAnswer>[];
    await tester.pumpWidget(host(choiceCard(), answers));
    await tester.pumpAndSettle();

    await tester.tap(find.text('registration'));
    await tester.pumpAndSettle();

    expect(answers.single.verdict, LocalCheck.wrong);
    // A different pair from the accepted one — the two verdicts must never feel the same.
    expect(haptic(), 'HapticFeedbackType.mediumImpact');
    expect(sound(), AppFeedback.wrongSound);
    expect(sound(), 'verdict_wrong');
  });

  testWidgets('the sound is fire-and-forget — a native failure never takes the answer down', (tester) async {
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(AppFeedback.channel, (call) async {
      throw PlatformException(code: 'no_sound', message: 'unknown sound');
    });

    final answers = <SessionAnswer>[];
    await tester.pumpWidget(host(choiceCard(), answers));
    await tester.pumpAndSettle();

    await tester.tap(find.text('reservation'));
    await tester.pumpAndSettle();

    // The verdict still landed and the haptic still fired: a sound effect is never allowed to be
    // the thing that loses someone's answer.
    expect(answers.single.verdict, LocalCheck.correct);
    expect(haptic(), 'HapticFeedbackType.lightImpact');
    expect(tester.takeException(), isNull);
  });

  testWidgets('a typed answer gets the same feedback — the verdict is shared, so its feel is too', (tester) async {
    final answers = <SessionAnswer>[];
    final typing = SessionCard(
      termId: 'T2',
      mode: ExerciseMode.typing,
      type: 'word',
      prompt: 'бронь',
      answer: 'reservation',
    );
    await tester.pumpWidget(host(typing, answers));
    await tester.pumpAndSettle();

    await tester.enterText(find.byType(TextField), 'reservation');
    platformCalls.clear(); // typing itself is not the verdict
    await tester.testTextInput.receiveAction(TextInputAction.done);
    await tester.pumpAndSettle();

    expect(answers.single.verdict, LocalCheck.correct);
    expect(haptic(), 'HapticFeedbackType.lightImpact');
    expect(sound(), AppFeedback.correctSound);
  });
}
