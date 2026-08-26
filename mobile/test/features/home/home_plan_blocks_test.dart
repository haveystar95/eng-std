import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/training/training_home_screen.dart';
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/data/locale_controller.dart';

/// THE EMPTY RULE, as a guard: a block with no data is not in the tree.
///
/// Not «drawn as 0», not «drawn greyed out» — absent. The rule is easy to state and easy to lose:
/// every one of these blocks is a nullable field on the server's plan, and the natural way to write
/// a card is to give the null a default and print it. «0 слов» is a sentence this screen must never
/// say, and the only way to be sure is to ask the tree whether the widget exists at all.
///
/// The four states of кадры 17a–17d are asserted the same way — by which keys are present.
void main() {
  // `total` is the POOL's work — repeats and new words — and never the swipe pass: a collection is
  // a catalogue, so adding one must not add its size to «сегодня».
  HomeSession session({
    int repeat = 0,
    int newTerms = 0,
    int triage = 0,
    int? minutes,
    int? cards,
    String? triageCollectionId,
  }) => HomeSession(
    repeat: repeat,
    newTerms: newTerms,
    triage: triage,
    total: repeat + newTerms,
    // A repeat is one card, a first meeting is its whole chain — the shape the server sends. Stated
    // rather than derived here: these tests are about which blocks exist, and the arithmetic behind
    // the number is the server's and is pinned there.
    cards: cards ?? (repeat + newTerms * 3),
    estimatedMinutes: minutes,
    avgSecondsPerCard: 8,
    triageMinutes: triage > 0 ? 1 : null,
    triageCollectionId: triage > 0 ? (triageCollectionId ?? 'col') : null,
    triageCollectionTitle: triage > 0 ? 'Ветклиника' : null,
  );

  HomePlan plan({
    HomeStateKind state = HomeStateKind.plan,
    HomeSession? session_,
    HomeInWork? inWork,
    List<HomeEdgeTerm> edge = const [],
    List<HomeHardTerm> hardest = const [],
    HomeToday? today,
    HomeNextReview? nextReview,
    HomeContinue? unfinished,
    HomeStore? store,
  }) => HomePlan(
    state: state,
    session: session_ ?? session(repeat: 12, newTerms: 5, triage: 15, minutes: 9),
    inWork:
        inWork ??
        const HomeInWork(total: 41, waiting: 20, perDay: 20, newRemaining: 20, daysUntilQueue: 2),
    edge: edge,
    hardest: hardest,
    today: today,
    nextReview: nextReview,
    unfinished: unfinished,
    store: store ?? const HomeStore(count: 17, topics: ['У врача', 'Аэропорт', 'Аренда']),
  );

  Future<void> pumpHome(WidgetTester tester, HomePlan? day, {int streak = 5}) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          homePlanProvider.overrideWith((ref) => Stream.value(day)),
          statsProvider.overrideWith(
            (ref) => Stream.value(
              Stats(
                totalWords: 41,
                learned: 0,
                mastered: 0,
                dueToday: 0,
                reviewsTotal: 0,
                streakDays: streak,
              ),
            ),
          ),
          connectivityProvider.overrideWith((ref) => Stream.value(true)),
        ],
        child: MaterialApp(
          supportedLocales: kSupportedLocales,
          localizationsDelegates: AppLocalizations.localizationsDelegates,
          locale: const Locale('ru'),
          // The shell always passes this; without a destination the store LINE has no reason to
          // exist, and its absence would be the guard passing for the wrong reason.
          home: Scaffold(body: TrainingHomeScreen(onOpenStore: () {})),
        ),
      ),
    );
    await tester.pump();
  }

  group('17a — план на сегодня', () {
    testWidgets('draws the day, the box and the words about to slip', (tester) async {
      await pumpHome(
        tester,
        plan(
          edge: const [HomeEdgeTerm(termId: 'a', text: 'withdraw', inDays: 1)],
          unfinished: const HomeContinue(
            collectionId: 'c',
            title: 'Ветклиника',
            done: 4,
            total: 16,
            remaining: 12,
            abandonedDays: 5,
          ),
        ),
      );

      expect(find.byKey(HomeBlockKeys.session), findsOneWidget);
      expect(find.byKey(HomeBlockKeys.inWork), findsOneWidget);
      expect(find.byKey(HomeBlockKeys.edge), findsOneWidget);
      expect(find.byKey(HomeBlockKeys.unfinished), findsOneWidget);
      expect(find.byKey(HomeBlockKeys.generate), findsOneWidget);
      // …and the evening's occupant of the edge slot is not also on screen.
      expect(find.byKey(HomeBlockKeys.hardest), findsNothing);
      expect(find.byKey(HomeBlockKeys.done), findsNothing);
    });

    testWidgets('no words near the edge → no section, not an empty one', (tester) async {
      await pumpHome(tester, plan());

      expect(find.byKey(HomeBlockKeys.session), findsOneWidget);
      expect(find.byKey(HomeBlockKeys.edge), findsNothing);
      expect(find.byKey(HomeBlockKeys.unfinished), findsNothing);
    });

    testWidgets('a part whose count is 0 is not a line on the card', (tester) async {
      await pumpHome(tester, plan(session_: session(repeat: 12, minutes: 2)));

      expect(find.textContaining('12'), findsWidgets);
      // «0 новых» does not exist on this screen.
      expect(find.textContaining('0 новых'), findsNothing);
    });

    testWidgets('an unsorted collection is not part of the day', (tester) async {
      // 12 repeats and 131 words nobody has sorted. The card is about the pool: the swipe pass is
      // an offer for later, and counting it here is what made every added set inflate «сегодня».
      await pumpHome(tester, plan(session_: session(repeat: 12, triage: 131, minutes: 2)));

      expect(find.textContaining('12'), findsWidgets);
      expect(find.textContaining('143'), findsNothing);
      expect(find.textContaining('131'), findsNothing);
    });
  });

  group('17b — сегодня закрыто', () {
    testWidgets('shows what today produced and what it got wrong', (tester) async {
      await pumpHome(
        tester,
        plan(
          state: HomeStateKind.done,
          session_: session(),
          today: const HomeToday(answered: 32, seconds: 400),
          nextReview: const HomeNextReview(date: '2026-08-28', count: 14),
          hardest: const [HomeHardTerm(termId: 'a', text: 'withdraw', errors: 2)],
        ),
      );

      expect(find.byKey(HomeBlockKeys.done), findsOneWidget);
      expect(find.byKey(HomeBlockKeys.hardest), findsOneWidget);
      expect(find.byKey(HomeBlockKeys.inWork), findsOneWidget);
      // The day's progress is answered cards, and the denominator is what the day held.
      expect(find.text('32 из 32'), findsOneWidget);
      expect(find.byKey(HomeBlockKeys.session), findsNothing);
    });

    testWidgets('a run with no mistakes draws no «труднее всего»', (tester) async {
      await pumpHome(
        tester,
        plan(
          state: HomeStateKind.done,
          session_: session(),
          today: const HomeToday(answered: 8, seconds: 60),
        ),
      );

      expect(find.byKey(HomeBlockKeys.done), findsOneWidget);
      expect(find.byKey(HomeBlockKeys.hardest), findsNothing);
    });
  });

  group('17c — первый день', () {
    testWidgets('two doors, and nothing else exists yet', (tester) async {
      await pumpHome(
        tester,
        plan(
          state: HomeStateKind.empty,
          session_: session(),
          inWork: const HomeInWork(total: 0, waiting: 0, perDay: 20, newRemaining: 20),
        ),
        streak: 0,
      );

      expect(find.byKey(HomeBlockKeys.firstDay), findsOneWidget);
      // Стрик, «В работе» и «На грани» здесь не существует — не нулями, а вовсе.
      expect(find.byKey(HomeBlockKeys.header), findsNothing);
      expect(find.byKey(HomeBlockKeys.inWork), findsNothing);
      expect(find.byKey(HomeBlockKeys.edge), findsNothing);
      expect(find.byKey(HomeBlockKeys.session), findsNothing);
    });
  });

  group('17d — слова есть, плана нет', () {
    testWidgets('«Всё повторено», the next date, and the box still on screen', (tester) async {
      await pumpHome(
        tester,
        plan(
          state: HomeStateKind.idle,
          session_: session(),
          nextReview: const HomeNextReview(date: '2026-08-28', count: 14),
        ),
      );

      expect(find.byKey(HomeBlockKeys.idle), findsOneWidget);
      expect(find.byKey(HomeBlockKeys.inWork), findsOneWidget);
      expect(find.byKey(HomeBlockKeys.session), findsNothing);
      expect(find.byKey(HomeBlockKeys.done), findsNothing);
    });
  });

  group('the store line', () {
    testWidgets('an empty store is no line at all', (tester) async {
      await pumpHome(
        tester,
        plan(store: const HomeStore(count: 0, topics: [])),
      );

      expect(find.byKey(HomeBlockKeys.generate), findsOneWidget);
      expect(find.byKey(HomeBlockKeys.storeLink), findsNothing);
    });

    testWidgets('a stocked store is one quiet line under the generation row', (tester) async {
      await pumpHome(tester, plan());

      expect(find.byKey(HomeBlockKeys.storeLink), findsOneWidget);
    });
  });

  testWidgets('nothing synced yet: no invented day, just the door that still works', (
    tester,
  ) async {
    await pumpHome(tester, null);

    expect(find.byKey(HomeBlockKeys.generate), findsOneWidget);
    expect(find.byKey(HomeBlockKeys.session), findsNothing);
    expect(find.byKey(HomeBlockKeys.inWork), findsNothing);
    expect(find.byKey(HomeBlockKeys.firstDay), findsNothing);
  });
}
