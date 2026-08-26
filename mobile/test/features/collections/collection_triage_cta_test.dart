import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/collections/collection_detail_screen.dart';
import 'package:eng_std/l10n/app_localizations.dart';

/// QA-25 — the triage button survives a PARTIAL pass over a collection.
///
/// The primary CTA is a priority (due → learn → triage), and the first «не знаю» swipe enrols a
/// word, which makes it learnable, which outranks triage. Three words swiped out of forty therefore
/// removed the only button that reached the other thirty-seven: the collection could not be sorted
/// to the end. The rule is now stated on the screen and not only in the priority — «Разобрать N»
/// lives for exactly as long as the collection holds a word nobody has swiped.
void main() {
  WordCollection collection() => WordCollection(
    id: 'c1',
    title: 'Собеседование',
    source: 'curated',
    type: 'custom',
    wordsCount: 40,
    sourceLang: 'ru',
    targetLang: 'en',
  );

  final word = Word(
    termId: 't1',
    term: 'cover letter',
    translation: 'сопроводительное',
    type: 'phrase',
  );

  Future<void> pump(
    WidgetTester tester, {
    required int untriaged,
    required int learnable,
    int due = 0,
    int newRemaining = 20,
  }) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          authControllerProvider.overrideWith(() => _FakeAuth()),
          connectivityProvider.overrideWith((ref) => Stream.value(true)),
          collectionsProvider.overrideWith((ref) => Stream.value([collection()])),
          collectionWordsProvider('c1').overrideWith((ref) => Stream.value([word])),
          collectionDensityProvider('c1').overrideWith(
            (ref) =>
                Stream.value(const CollectionDensity(mastered: 0, inWork: 0, toSort: 1)),
          ),
          collectionsProgressProvider.overrideWith(
            (ref) => Stream.value({
              'c1': CollectionProgress(
                collectionId: 'c1',
                total: 40,
                learned: 0,
                mastered: 0,
                due: due,
              ),
            }),
          ),
          untriagedByCollectionProvider.overrideWith((ref) => Stream.value({'c1': untriaged})),
          learnableByCollectionProvider.overrideWith((ref) => Stream.value({'c1': learnable})),
          statsProvider.overrideWith(
            (ref) => Stream.value(
              Stats(
                totalWords: 40,
                learned: 0,
                mastered: 0,
                dueToday: due,
                reviewsTotal: 0,
                streakDays: 0,
                newGoal: 20,
                newRemaining: newRemaining,
              ),
            ),
          ),
        ],
        child: const MaterialApp(
          locale: Locale('ru'),
          localizationsDelegates: AppLocalizations.localizationsDelegates,
          supportedLocales: [Locale('ru')],
          home: CollectionDetailScreen(collectionId: 'c1', title: 'Собеседование'),
        ),
      ),
    );
    await tester.pumpAndSettle();
  }

  testWidgets('a fresh collection offers the pass as the PRIMARY action, once', (tester) async {
    await pump(tester, untriaged: 40, learnable: 0);

    expect(find.text('Разобрать 40'), findsOneWidget); // primary, not doubled by the secondary
  });

  testWidgets('three words swiped, thirty-seven left → the button is still there', (tester) async {
    // The exact phone repro: the swipes enrolled three words, so «Учить 3» took the primary slot.
    await pump(tester, untriaged: 37, learnable: 3);

    expect(find.text('Учить 3'), findsOneWidget);
    expect(find.text('Разобрать 37'), findsOneWidget);
  });

  testWidgets('…and it survives a due count too — reviews outrank it, they do not replace it', (
    tester,
  ) async {
    await pump(tester, untriaged: 37, learnable: 3, due: 12);

    expect(find.text('Разобрать 37'), findsOneWidget);
  });

  testWidgets('…and the daily new quota being spent does not take it away either', (tester) async {
    // The primary CTA is the inactive limit card here; the pass is unrelated to the new-term quota.
    await pump(tester, untriaged: 37, learnable: 3, newRemaining: 0);

    expect(find.text('Разобрать 37'), findsOneWidget);
  });

  testWidgets('the last word swiped → the button goes away', (tester) async {
    await pump(tester, untriaged: 0, learnable: 40);

    // The BUTTON, by its label — not «any text saying Разобрать». The density legend uses the same
    // word for the same state (Ч.4: one vocabulary), and a finder that could not tell the two apart
    // would fail the day the legend started speaking the app's own language.
    expect(find.text('Разобрать 0'), findsNothing);
    // …and the primary CTA is «Учить», capped at the day's remaining new-term quota (F13b).
    expect(find.text('Учить 20'), findsOneWidget);
  });
}

class _FakeAuth extends AuthController {
  @override
  Future<AppUser?> build() async => AppUser(
    id: 'u1',
    name: 'D',
    profile: Profile(nativeLanguage: 'ru', targetLanguage: 'en', cefrLevel: 'B1', dailyGoal: 20),
  );
}
