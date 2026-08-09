import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/training/session/session_exercise.dart';
import 'package:eng_std/l10n/app_localizations.dart';

/// Listening (12g/12h) and cloze (12i/12j) render/behaviour — the two modes the server only started
/// emitting once `enabled_modes` gained them. Device-batched for the full loop; pinned here so the
/// card structure (no term text on listening, a blank cut from the example on cloze, the slow-replay
/// control) can't silently regress.
void main() {
  // Records each speak call as (text, slow) so the test can assert autoplay + «замедленно».
  late List<({String text, bool slow})> spoken;

  Future<void> onSpeak(String text, {bool slow = false}) async => spoken.add((text: text, slow: slow));

  Widget host(SessionCard card) => ProviderScope(
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
          supportedLocales: const [Locale('ru')],
          home: Scaffold(
            body: SingleChildScrollView(
              child: SessionExerciseCard(
                card: card,
                autoPronounce: true,
                onAnswered: (_) {},
                onSpeak: onSpeak,
              ),
            ),
          ),
        ),
      );

  setUp(() => spoken = []);

  testWidgets('listening: no term text, autoplay on appear, and a «замедленно» slow replay', (tester) async {
    await tester.pumpWidget(host(SessionCard(
      termId: '01AAAAAAAAAAAAAAAAAAAAAAA1',
      mode: ExerciseMode.listening,
      type: 'phrase',
      prompt: 'снять наличные',
      answer: 'withdraw cash',
    )));
    await tester.pumpAndSettle();

    expect(tester.takeException(), isNull);
    // The term is heard, never shown.
    expect(find.text('withdraw cash'), findsNothing);
    // Instruction + slow-replay control.
    expect(find.text('прослушай и напиши по-английски'), findsOneWidget);
    expect(find.text('Замедленно'), findsOneWidget);

    // Autoplay fired once at normal speed on appearance.
    expect(spoken, [(text: 'withdraw cash', slow: false)]);

    // Tapping «Замедленно» replays slowly.
    await tester.tap(find.text('Замедленно'));
    await tester.pump();
    expect(spoken.last, (text: 'withdraw cash', slow: true));
  });

  testWidgets('cloze: the answer is cut from the example as a blank, translation shown, no autoplay', (tester) async {
    await tester.pumpWidget(host(SessionCard(
      termId: '01AAAAAAAAAAAAAAAAAAAAAAA2',
      mode: ExerciseMode.cloze,
      type: 'phrase',
      prompt: 'снять наличные',
      answer: 'withdraw cash',
      example: 'I need to withdraw cash before we leave.',
      exampleTranslation: 'Мне нужно снять наличные, прежде чем мы уйдём.',
    )));
    await tester.pumpAndSettle();

    expect(tester.takeException(), isNull);
    // «Вставь слово» prompt label (upper-cased in the card).
    expect(find.text('ВСТАВЬ СЛОВО'), findsOneWidget);
    // The blanked word is gone; the surrounding sentence and the translation remain.
    expect(find.textContaining('withdraw cash'), findsNothing);
    expect(find.textContaining('before we leave'), findsOneWidget);
    expect(find.textContaining('снять наличные'), findsOneWidget);
    // Cloze is a typed prompt, not audio — nothing was spoken.
    expect(spoken, isEmpty);

    // Typing goes straight INTO the blank so the user sees their answer in the sentence (the fix for
    // «input не влазит» — the capture field is invisible, the blank shows the live text).
    await tester.enterText(find.byType(TextField), 'withdr');
    await tester.pump();
    // The blank now shows the live text (the invisible capture field holds it too → ≥ 1).
    expect(find.textContaining('withdr'), findsAtLeastNWidgets(1));
  });
}
