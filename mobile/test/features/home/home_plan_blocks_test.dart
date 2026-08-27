import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/daily/word_challenge.dart';
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
/// The three frames of the rewrite are asserted the same way — by which keys are present:
///
///   19-1 УТРО        session · statistics · [word challenge] · tomorrow · generate · store LINE
///   19-2 ВЕЧЕР       done+award · hardest · statistics · tomorrow · generate · store WINDOW
///   19-3 ПЕРВЫЙ ДЕНЬ store WINDOW · generate+field+chips · promise, and nothing else at all
///
/// The slot the word challenge will take (DAILY-1) is deliberately not asserted: nothing is drawn
/// there yet, and a test for an absent widget that was never written would pass for the wrong
/// reason. What IS asserted is that the column reads correctly without it.
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

  HomeStoreItem deck(String id, String title, {String? level = 'A2', String? image}) =>
      HomeStoreItem(
        id: id,
        title: title,
        termsCount: 16,
        level: level,
        imageUrl: image,
        sourceLang: 'ru',
        targetLang: 'en',
      );

  HomeStore stockedStore() => HomeStore(
    count: 17,
    topics: const ['У врача', 'Аэропорт', 'Аренда'],
    items: [deck('a', 'Аэропорт'), deck('b', 'У врача'), deck('c', 'Аренда жилья')],
  );

  HomePlan plan({
    HomeStateKind state = HomeStateKind.plan,
    HomeSession? session_,
    HomeInWork? inWork,
    List<HomeHardTerm> hardest = const [],
    HomeToday? today,
    HomeNextReview? nextReview,
    HomeStore? store,
    int? edgeTomorrow,
    HomeDayAward? dayAward,
    int? learnedWeek = 23,
  }) => HomePlan(
    state: state,
    session: session_ ?? session(repeat: 12, newTerms: 5, triage: 15, minutes: 9),
    inWork:
        inWork ??
        const HomeInWork(total: 41, waiting: 20, perDay: 20, newRemaining: 20, daysUntilQueue: 2),
    hardest: hardest,
    today: today,
    nextReview: nextReview,
    store: store ?? stockedStore(),
    edgeTomorrow: edgeTomorrow,
    dayAward: dayAward,
    learnedWeek: learnedWeek,
  );

  WordChallenge word() => const WordChallenge(
    termId: 't1',
    text: 'reluctant',
    translation: 'неохотный',
    options: ['неохотный', 'надёжный', 'заметный'],
    streak: 6,
  );

  Future<void> pumpHome(
    WidgetTester tester,
    HomePlan? day, {
    int streak = 5,
    int learned = 146,
    WordChallenge? challenge,
  }) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          homePlanProvider.overrideWith(
            (ref) => Stream.value(
              day == null ? const HomePlanView.missing() : HomePlanView.ready(day),
            ),
          ),
          statsProvider.overrideWith(
            (ref) => Stream.value(
              Stats(
                totalWords: 41,
                learned: learned,
                mastered: 0,
                dueToday: 0,
                reviewsTotal: 0,
                streakDays: streak,
              ),
            ),
          ),
          connectivityProvider.overrideWith((ref) => Stream.value(true)),
          wordChallengeProvider.overrideWith((ref) => Stream.value(challenge)),
        ],
        child: MaterialApp(
          supportedLocales: kSupportedLocales,
          localizationsDelegates: AppLocalizations.localizationsDelegates,
          locale: const Locale('ru'),
          // The shell always passes this; without a destination the store link and the window's
          // «все 17 →» have no reason to exist, and their absence would be the guard passing for the
          // wrong reason.
          home: Scaffold(body: TrainingHomeScreen(onOpenStore: () {})),
        ),
      ),
    );
    await tester.pump();
  }

  group('19-1 — утро, сессия ждёт', () {
    testWidgets('the day, the three numbers, tomorrow, and one quiet door to the store', (
      tester,
    ) async {
      await pumpHome(tester, plan(edgeTomorrow: 14));

      expect(find.byKey(HomeBlockKeys.session), findsOneWidget);
      expect(find.byKey(HomeBlockKeys.stats), findsOneWidget);
      expect(find.byKey(HomeBlockKeys.tomorrow), findsOneWidget);
      expect(find.byKey(HomeBlockKeys.generate), findsOneWidget);
      // The morning offers the store as a SENTENCE. A strip of shop covers under a session waiting
      // to be started is a shop in the doorway.
      expect(find.byKey(HomeBlockKeys.storeLink), findsOneWidget);
      expect(find.byKey(HomeBlockKeys.storeShowcase), findsNothing);
      // …and the evening's blocks are not also on screen.
      expect(find.byKey(HomeBlockKeys.done), findsNothing);
      expect(find.byKey(HomeBlockKeys.hardest), findsNothing);
    });

    testWidgets('a tomorrow with nothing in it is no row, not «завтра выпадет 0»', (tester) async {
      // The server sends null rather than 0 for exactly this.
      await pumpHome(tester, plan());

      expect(find.byKey(HomeBlockKeys.session), findsOneWidget);
      expect(find.byKey(HomeBlockKeys.tomorrow), findsNothing);
      expect(find.textContaining('0 слов'), findsNothing);
    });

    testWidgets('a number nobody has yet is one plate fewer, never a zero', (tester) async {
      // A learner who has taken words in but graduated none: «в работе» is real, the other two are
      // not facts about them yet.
      await pumpHome(tester, plan(learnedWeek: null), learned: 0);

      expect(find.byKey(HomeBlockKeys.stats), findsOneWidget);
      expect(find.text('41'), findsOneWidget);
      expect(find.text('0'), findsNothing);
    });

    testWidgets('on a day with no numbers at all there is no statistics block', (tester) async {
      await pumpHome(
        tester,
        plan(
          learnedWeek: null,
          inWork: const HomeInWork(total: 0, waiting: 0, perDay: 20, newRemaining: 20),
        ),
        learned: 0,
      );

      expect(find.byKey(HomeBlockKeys.stats), findsNothing);
    });

    testWidgets('the composition is a price list, and a row with 0 is not in it', (tester) async {
      await pumpHome(tester, plan(session_: session(repeat: 12, minutes: 2)));

      // Label left, number right — and only the rows that have something in them. «12» twice: the
      // headline and its one row, which on a repeats-only day are the same number.
      expect(find.text('Повторить'), findsOneWidget);
      expect(find.text('12'), findsNWidgets(2));
      expect(find.text('Новых'), findsNothing);
      expect(find.text('Разобрать'), findsNothing);
      expect(find.textContaining('0 новых'), findsNothing);
    });

    testWidgets('«Разобрать» is a row of the composition — but not part of the headline', (
      tester,
    ) async {
      // 12 repeats, 5 new, and 131 words nobody has sorted.
      await pumpHome(tester, plan(session_: session(repeat: 12, newTerms: 5, triage: 131, minutes: 2)));

      // Named, because a day that has 131 unsorted words is not honestly described without them.
      expect(find.text('Разобрать'), findsOneWidget);
      expect(find.text('131'), findsOneWidget);
      // …and NOT added to the number over it: the headline is the server's `total`, which is the
      // POOL's work. A collection is a catalogue, so adding a set must not add its size to «сегодня».
      expect(find.text('17'), findsOneWidget);
      expect(find.text('148'), findsNothing);
    });

    testWidgets('the card count is out of the headline — minutes are what a decision needs', (
      tester,
    ) async {
      await pumpHome(
        tester,
        plan(session_: session(repeat: 12, newTerms: 5, cards: 158, minutes: 26)),
      );

      expect(find.textContaining('26'), findsWidgets);
      expect(find.textContaining('158'), findsNothing);
    });
  });

  group('19-2 — вечер, сессия сделана', () {
    HomePlan evening({HomeDayAward? award, List<HomeHardTerm> hardest = const []}) => plan(
      state: HomeStateKind.done,
      session_: session(),
      today: const HomeToday(answered: 32, seconds: 400),
      hardest: hardest,
      edgeTomorrow: 14,
      dayAward: award,
      learnedWeek: 28,
    );

    testWidgets('what the day closed, what it bought, and the shop window under it', (
      tester,
    ) async {
      await pumpHome(
        tester,
        evening(
          award: const HomeDayAward(promoted: 5, termId: 'r', text: 'reluctant', step: 4),
          hardest: const [HomeHardTerm(termId: 'a', text: 'withdraw', errors: 3)],
        ),
        learned: 151,
      );

      expect(find.byKey(HomeBlockKeys.done), findsOneWidget);
      expect(find.byKey(HomeBlockKeys.award), findsOneWidget);
      expect(find.byKey(HomeBlockKeys.hardest), findsOneWidget);
      expect(find.byKey(HomeBlockKeys.stats), findsOneWidget);
      expect(find.byKey(HomeBlockKeys.tomorrow), findsOneWidget);
      // The evening ends on covers, not on a sentence: it cures the emptiness under a finished day.
      expect(find.byKey(HomeBlockKeys.storeShowcase), findsOneWidget);
      expect(find.byKey(HomeBlockKeys.storeLink), findsNothing);
      expect(find.byKey(HomeBlockKeys.session), findsNothing);

      // The day's progress is answered cards, and the denominator is what the day held.
      expect(find.text('32 из 32'), findsOneWidget);
      // The reward names the rung in the interface's own words — the server sent the number 4.
      expect(find.textContaining('reluctant'), findsOneWidget);
      expect(find.textContaining('написание'), findsOneWidget);
    });

    testWidgets('a word taken after the day closed is offered right there', (tester) async {
      // The bug from the owner's phone: «Учить сразу» on a closed evening put the word in the queue
      // and in «Мои слова», and this screen — the one they were looking at — had no sentence in
      // which such a word could appear.
      await pumpHome(
        tester,
        plan(
          state: HomeStateKind.done,
          session_: session(newTerms: 1, triage: 12),
          today: const HomeToday(answered: 14, seconds: 45),
        ),
      );

      expect(find.byKey(HomeBlockKeys.done), findsOneWidget);
      expect(find.textContaining('уже ждёт очереди'), findsOneWidget);
      expect(find.text('Ещё 1 слово'), findsOneWidget);
    });

    testWidgets('with nothing queued the evening still offers the swipe pass', (tester) async {
      await pumpHome(
        tester,
        plan(
          state: HomeStateKind.done,
          session_: session(triage: 12),
          today: const HomeToday(answered: 14, seconds: 45),
        ),
      );

      expect(find.textContaining('Ветклиника'), findsOneWidget);
      expect(find.text('Ещё 12 слов'), findsOneWidget);
    });

    testWidgets('a day that promoted nothing has no reward line', (tester) async {
      await pumpHome(tester, evening());

      expect(find.byKey(HomeBlockKeys.done), findsOneWidget);
      expect(find.byKey(HomeBlockKeys.award), findsNothing);
    });

    testWidgets('a run with no mistakes draws no «труднее всего»', (tester) async {
      await pumpHome(tester, evening());

      expect(find.byKey(HomeBlockKeys.done), findsOneWidget);
      expect(find.byKey(HomeBlockKeys.hardest), findsNothing);
    });

    testWidgets('the evening says the numbers in the SAME plates the morning does', (tester) async {
      await pumpHome(tester, evening(), learned: 151);

      // Same block, same shape. It was a single line here at first — and one screen saying three
      // numbers in two different shapes made them read as two different facts.
      expect(find.byKey(HomeBlockKeys.stats), findsOneWidget);
      expect(find.text('151'), findsOneWidget);
      expect(find.text('28'), findsOneWidget);
      expect(find.text('41'), findsOneWidget);
      expect(find.text('ВЫУЧЕНО'), findsOneWidget);
    });
  });

  group('19-3 — первый день', () {
    testWidgets('the window, the invitation, the promise — and nothing that does not exist yet', (
      tester,
    ) async {
      await pumpHome(
        tester,
        plan(
          state: HomeStateKind.empty,
          session_: session(),
          inWork: const HomeInWork(total: 0, waiting: 0, perDay: 20, newRemaining: 20),
          learnedWeek: null,
        ),
        streak: 0,
        learned: 0,
      );

      expect(find.byKey(HomeBlockKeys.firstDay), findsOneWidget);
      expect(find.byKey(HomeBlockKeys.storeShowcase), findsOneWidget);
      expect(find.byKey(HomeBlockKeys.generate), findsOneWidget);
      expect(find.byKey(HomeBlockKeys.promise), findsOneWidget);
      // Стрик, статистика и «завтра выпадет» здесь не существуют — не нулями, а вовсе.
      expect(find.byKey(HomeBlockKeys.header), findsNothing);
      expect(find.byKey(HomeBlockKeys.stats), findsNothing);
      expect(find.byKey(HomeBlockKeys.tomorrow), findsNothing);
      expect(find.byKey(HomeBlockKeys.session), findsNothing);
    });

    testWidgets('the first day offers example situations, and each is a way in', (tester) async {
      await pumpHome(
        tester,
        plan(state: HomeStateKind.empty, session_: session(), learnedWeek: null),
        streak: 0,
        learned: 0,
      );

      // The chips the frame names. Tappable, unlike the topic captions they replace.
      expect(find.text('Собеседование'), findsOneWidget);
      expect(find.text('Ветклиника'), findsOneWidget);
      expect(find.text('Переезд'), findsOneWidget);
    });

    testWidgets('an empty store leaves the generation card as the only door', (tester) async {
      await pumpHome(
        tester,
        plan(
          state: HomeStateKind.empty,
          session_: session(),
          store: const HomeStore(count: 0, topics: []),
          learnedWeek: null,
        ),
        streak: 0,
        learned: 0,
      );

      expect(find.byKey(HomeBlockKeys.storeShowcase), findsNothing);
      expect(find.byKey(HomeBlockKeys.generate), findsOneWidget);
    });
  });

  group('17d — слова есть, плана нет', () {
    testWidgets('«Всё повторено», the numbers, and the quiet store line', (tester) async {
      await pumpHome(
        tester,
        plan(
          state: HomeStateKind.idle,
          session_: session(),
          nextReview: const HomeNextReview(date: '2026-08-28', count: 14),
          edgeTomorrow: 14,
        ),
      );

      expect(find.byKey(HomeBlockKeys.idle), findsOneWidget);
      expect(find.byKey(HomeBlockKeys.stats), findsOneWidget);
      expect(find.byKey(HomeBlockKeys.tomorrow), findsOneWidget);
      expect(find.byKey(HomeBlockKeys.storeLink), findsOneWidget);
      expect(find.byKey(HomeBlockKeys.session), findsNothing);
      expect(find.byKey(HomeBlockKeys.done), findsNothing);
    });
  });

  group('the store', () {
    testWidgets('an empty store is no line and no window', (tester) async {
      await pumpHome(tester, plan(store: const HomeStore(count: 0, topics: [])));

      expect(find.byKey(HomeBlockKeys.generate), findsOneWidget);
      expect(find.byKey(HomeBlockKeys.storeLink), findsNothing);
      expect(find.byKey(HomeBlockKeys.storeShowcase), findsNothing);
    });

    testWidgets('a stocked store shows its decks by name and size', (tester) async {
      await pumpHome(
        tester,
        plan(state: HomeStateKind.done, session_: session(), today: const HomeToday(answered: 4, seconds: 30)),
      );

      expect(find.byKey(HomeBlockKeys.storeShowcase), findsOneWidget);
      expect(find.text('Аэропорт'), findsOneWidget);
      // «16 слов · A2» — the level only where the deck has one.
      expect(find.textContaining('16 слов · A2'), findsWidgets);
    });

    testWidgets('a deck with no level prints its size and stops there', (tester) async {
      await pumpHome(
        tester,
        plan(
          state: HomeStateKind.done,
          session_: session(),
          today: const HomeToday(answered: 4, seconds: 30),
          store: HomeStore(count: 1, topics: const ['Быт'], items: [deck('x', 'Быт', level: null)]),
        ),
      );

      expect(find.text('16 слов'), findsOneWidget);
      expect(find.textContaining('·'), findsNothing);
    });
  });

  group('слово-вызов (кадр 19-4)', () {
    testWidgets('sits on all three screens, above the «завтра» row', (tester) async {
      for (final state in [HomeStateKind.plan, HomeStateKind.done, HomeStateKind.empty]) {
        await pumpHome(
          tester,
          plan(
            state: state,
            session_: session(repeat: 3, minutes: 1),
            today: state == HomeStateKind.done ? const HomeToday(answered: 3, seconds: 30) : null,
            edgeTomorrow: 14,
          ),
          challenge: word(),
        );

        expect(find.byKey(HomeBlockKeys.challenge), findsOneWidget, reason: '$state');
        expect(find.text('reluctant'), findsOneWidget, reason: '$state');
      }
    });

    testWidgets('a day the mirror cannot produce a word for draws no card', (tester) async {
      // Null is a real answer — an empty mirror, a pair with fewer than three translations, a
      // learner studying everything they own. None of them is a card apologising for itself.
      await pumpHome(tester, plan(edgeTomorrow: 14));

      expect(find.byKey(HomeBlockKeys.challenge), findsNothing);
      // …and the row below it is still there: the column survives the gap.
      expect(find.byKey(HomeBlockKeys.tomorrow), findsOneWidget);
    });
  });

  testWidgets('nothing synced yet: no invented day — and the screen says why (BUG-1)', (
    tester,
  ) async {
    await pumpHome(tester, null);

    // The blank page this replaced was ONLY the generate row, which read as «всё в порядке, просто
    // пусто» while the server was down. The door still works and stays; what is new is the sentence
    // above it. The states themselves are pinned in home_no_day_test.dart.
    expect(find.byKey(HomeBlockKeys.unreachable), findsOneWidget);
    expect(find.byKey(HomeBlockKeys.generate), findsOneWidget);
    expect(find.byKey(HomeBlockKeys.session), findsNothing);
    expect(find.byKey(HomeBlockKeys.stats), findsNothing);
    expect(find.byKey(HomeBlockKeys.firstDay), findsNothing);
  });
}
