import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/languages.dart';
import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/training/session/session_exercise.dart';
import 'package:eng_std/features/training/session/session_grading.dart';
import 'package:eng_std/l10n/app_localizations.dart';

/// Ч.2 — «поле ответа знает язык».
///
/// Two halves that only make sense together. The card tells the keyboard which language it wants
/// (a statement iOS does not listen to — Flutter honours `hintLocales` on Android only), and it
/// refuses to grade an answer typed entirely in an alphabet the card's language is not written in.
/// The second half is what actually protects the learner on this platform: «ФЕЬ» at an English card
/// is a keyboard left on, and charging a mistake for it teaches the scheduler something untrue.
void main() {
  group('looksLikeWrongKeyboard — по алфавиту, без эвристик', () {
    test('Cyrillic at an English card is a wrong keyboard', () {
      expect(looksLikeWrongKeyboard('en', 'ФЕЬ'), isTrue);
      expect(looksLikeWrongKeyboard('en', 'ыджуещту'), isTrue);
    });

    test('an honest wrong English answer is NOT one', () {
      // The distinction the whole feature rests on. A wrong answer contains letters of the card's
      // own alphabet; a wrong keyboard contains none.
      expect(looksLikeWrongKeyboard('en', 'reservetion'), isFalse);
      expect(looksLikeWrongKeyboard('en', 'qwerty'), isFalse);
    });

    test('one letter of the right alphabet is enough to make it an answer', () {
      // All-or-nothing on purpose: «ФЕЬa» is somebody typing in the right alphabet and getting it
      // wrong, and that is a mistake the trainer exists to record.
      expect(looksLikeWrongKeyboard('en', 'ФЕЬa'), isFalse);
    });

    test('the mirror: Latin at a Russian card', () {
      expect(looksLikeWrongKeyboard('ru', 'ghbdtn'), isTrue);
      expect(looksLikeWrongKeyboard('ru', 'привед'), isFalse);
    });

    test('non-letters cannot vote, and an answer with no letters is not a keyboard problem', () {
      expect(looksLikeWrongKeyboard('en', '123'), isFalse);
      expect(looksLikeWrongKeyboard('en', '   '), isFalse);
      expect(looksLikeWrongKeyboard('en', "don't"), isFalse);
      // Digits and punctuation ride along with the wrong letters without rescuing them.
      expect(looksLikeWrongKeyboard('en', 'ФЕЬ, 12!'), isTrue);
    });

    test('a language the table has no opinion about always passes', () {
      // Silence, not a guess. A check that fires on a language nobody taught it about is a check
      // that gets switched off.
      expect(looksLikeWrongKeyboard('sw', 'ФЕЬ'), isFalse);
      expect(looksLikeWrongKeyboard('', 'ФЕЬ'), isFalse);
    });

    test('two Cyrillic languages do not accuse each other', () {
      // The guard is about SCRIPT, not about spelling: «привет» at a Ukrainian card is a Russian
      // word, not a Russian keyboard, and telling the learner to switch layouts would be nonsense.
      expect(looksLikeWrongKeyboard('uk', 'привет'), isFalse);
    });
  });

  group('keyboardLocaleFor', () {
    test('is the same table the voice reads', () {
      expect(keyboardLocaleFor('en'), const Locale('en', 'US'));
      expect(keyboardLocaleFor('uk'), const Locale('uk', 'UA'));
      // A language with no entry falls back exactly as the voice does, rather than throwing.
      expect(keyboardLocaleFor('sw'), const Locale('en', 'US'));
    });
  });

  group('карточка · подсказка про раскладку', () {
    SessionCard typingCard() => SessionCard(
      termId: 'T1',
      mode: ExerciseMode.typing,
      type: 'word',
      prompt: 'бронь',
      answer: 'reservation',
    );

    Widget host(SessionCard card, void Function(SessionAnswer) onAnswered) => ProviderScope(
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
              speechLocaleId: 'en_US',
              answerLang: 'en',
              autoPronounce: false,
              onAnswered: onAnswered,
              onSpeak: (text, {bool slow = false}) async {},
              showDue: false,
            ),
          ),
        ),
      ),
    );

    testWidgets('«ФЕЬ» on an en card is a hint, and the attempt is NOT spent', (tester) async {
      final answers = <SessionAnswer>[];
      await tester.pumpWidget(host(typingCard(), answers.add));
      await tester.pump(const Duration(milliseconds: 300));

      await tester.enterText(find.byType(TextField), 'ФЕЬ');
      await tester.testTextInput.receiveAction(TextInputAction.done);
      await tester.pump();

      expect(find.byKey(sessionWrongKeyboardKey), findsOneWidget);
      // Nothing was graded, nothing was uploaded, and the card is still answerable.
      expect(answers, isEmpty);
      expect(find.text('Не помню'), findsOneWidget);
    });

    testWidgets('the text is kept, so the learner retypes rather than starts over', (tester) async {
      await tester.pumpWidget(host(typingCard(), (_) {}));
      await tester.pump(const Duration(milliseconds: 300));

      await tester.enterText(find.byType(TextField), 'ФЕЬ');
      await tester.testTextInput.receiveAction(TextInputAction.done);
      await tester.pump();

      expect(find.text('ФЕЬ'), findsOneWidget);
    });

    testWidgets('the next keystroke takes the hint away', (tester) async {
      await tester.pumpWidget(host(typingCard(), (_) {}));
      await tester.pump(const Duration(milliseconds: 300));

      await tester.enterText(find.byType(TextField), 'ФЕЬ');
      await tester.testTextInput.receiveAction(TextInputAction.done);
      await tester.pump();
      expect(find.byKey(sessionWrongKeyboardKey), findsOneWidget);

      await tester.enterText(find.byType(TextField), 'res');
      await tester.pump();

      expect(find.byKey(sessionWrongKeyboardKey), findsNothing);
    });

    testWidgets('after switching, the same card grades the retyped answer normally', (tester) async {
      final answers = <SessionAnswer>[];
      await tester.pumpWidget(host(typingCard(), answers.add));
      await tester.pump(const Duration(milliseconds: 300));

      await tester.enterText(find.byType(TextField), 'ФЕЬ');
      await tester.testTextInput.receiveAction(TextInputAction.done);
      await tester.pump();

      await tester.enterText(find.byType(TextField), 'reservation');
      await tester.testTextInput.receiveAction(TextInputAction.done);
      await tester.pump();

      expect(answers.single.response, 'reservation');
      expect(answers.single.verdict, LocalCheck.correct);
    });

    testWidgets('an honest wrong en answer is an ERROR, not a layout hint', (tester) async {
      // The guard must never become an excuse: this is the case it would be worst to swallow.
      final answers = <SessionAnswer>[];
      await tester.pumpWidget(host(typingCard(), answers.add));
      await tester.pump(const Duration(milliseconds: 300));

      await tester.enterText(find.byType(TextField), 'restaurant');
      await tester.testTextInput.receiveAction(TextInputAction.done);
      await tester.pump();

      expect(find.byKey(sessionWrongKeyboardKey), findsNothing);
      expect(answers.single.verdict, LocalCheck.wrong);
    });

    testWidgets('a card whose ANSWER is foreign to its language keeps quiet', (tester) async {
      // «Wi-Fi» and «IT» are Russian vocabulary written in Latin. On such a card the correct answer
      // is exactly the shape the guard was built to stop, and blocking it would make the card
      // unanswerable — a client refusing what the server accepts, which is the one direction the
      // local check may never take. (Found by the invariant review before this shipped.)
      final answers = <SessionAnswer>[];
      await tester.pumpWidget(
        ProviderScope(
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
                  card: SessionCard(
                    termId: 'T2',
                    mode: ExerciseMode.typing,
                    type: 'word',
                    prompt: 'беспроводная сеть',
                    answer: 'Wi-Fi',
                  ),
                  speechLocaleId: 'ru_RU',
                  answerLang: 'ru',
                  autoPronounce: false,
                  onAnswered: answers.add,
                  onSpeak: (text, {bool slow = false}) async {},
                  showDue: false,
                ),
              ),
            ),
          ),
        ),
      );
      await tester.pump(const Duration(milliseconds: 300));

      await tester.enterText(find.byType(TextField), 'Wi-Fi');
      await tester.testTextInput.receiveAction(TextInputAction.done);
      await tester.pump();

      expect(find.byKey(sessionWrongKeyboardKey), findsNothing);
      expect(answers.single.verdict, LocalCheck.correct);
    });

    testWidgets('the field asks the keyboard for the card\'s own language', (tester) async {
      await tester.pumpWidget(host(typingCard(), (_) {}));
      await tester.pump(const Duration(milliseconds: 300));

      final field = tester.widget<TextField>(find.byType(TextField));
      expect(field.hintLocales, [const Locale('en', 'US')]);
    });
  });
}
