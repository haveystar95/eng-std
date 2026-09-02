import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/plan_models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/data/review_sync.dart';
import 'package:eng_std/data/session_completion_sync.dart';
import 'package:eng_std/features/plan/plan_builder_screen.dart';
import 'package:eng_std/features/plan/plan_day_screen.dart';
import 'package:eng_std/features/plan/plan_day_summary.dart';
import 'package:eng_std/features/plan/plan_screen.dart';
import 'package:eng_std/features/plan/plan_tab_screen.dart';
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/ui/ui.dart';

/// The plan screens, on data rather than on a live server.
///
/// What is asserted here is the handful of things the design is EXPLICIT about and that a later
/// refactor could quietly lose: the empty tab explains the difference between a collection and a
/// plan, a day separates phrases from words and marks every word with its stage, and the day's
/// summary says «в работе» rather than «выучено».
MaterialApp _app(Widget home) => MaterialApp(
  locale: const Locale('ru'),
  localizationsDelegates: AppLocalizations.localizationsDelegates,
  supportedLocales: const [Locale('ru')],
  home: Scaffold(body: home),
);

LearningPlan _plan({int focus = 1}) => LearningPlan.fromJson({
  ..._planJson(),
  'focus_day_index': focus,
  'next_day_index': focus + 1,
});

/// The plan's payload minus the two fields a test usually wants to move. Spread rather than copied,
/// so a day-list test can replace `days` without restating everything above it.
Map<String, dynamic> _planJson() => {
  'id': '01PLAN',
  'status': 'active',
  'title': 'К врачу из-за боли',
  'goal_text': 'Иду к врачу, болит спина',
  'support_lang': 'ru',
  'target_lang': 'en',
  'level': 'basic',
  'event_date': '2026-09-02',
  'minutes_per_day': 20,
  'readiness': 0.5,
  'days_to_event': 2,
  'deadline_tight': false,
  'can_already': [
    {'text': 'Сказать, зачем пришёл', 'day_index': 1, 'hit': false},
  ],
  'days': [
    {'id': 'd1', 'index': 1, 'kind': 'intro', 'title': 'Начать приём', 'status': 'ready'},
    {'id': 'd2', 'index': 2, 'kind': 'intro', 'title': 'Уточнить симптомы', 'status': 'ready'},
  ],
};

PlanDayDetail _day() => PlanDayDetail.fromJson({
  'id': 'd2',
  'index': 2,
  'kind': 'intro',
  'title': 'Уточнить симптомы и помощь',
  'status': 'ready',
  'outcome': ['Описать, какая это боль'],
  'plan_id': '01PLAN',
  'plan_title': 'К врачу из-за боли',
  'support_lang': 'ru',
  'target_lang': 'en',
  'terms': [
    {
      'id': 't1',
      'text': "It's a sharp pain.",
      'translation': 'Это острая боль.',
      'type': 'phrase',
      'stage': 'a',
      'from_day_index': 2,
    },
    {
      'id': 't2',
      'text': 'sharp',
      'translation': 'острый',
      'type': 'word',
      'stage': 'a',
      'from_day_index': 2,
    },
    {
      'id': 't3',
      'text': 'back',
      'translation': 'спина',
      'type': 'word',
      'stage': 'b',
      'from_day_index': 1,
    },
  ],
});

SessionCard _card(String id, String type) => SessionCard.fromJson({
  'term_id': id,
  'exercise_mode': 'multiple_choice',
  'type': type,
  'answer': id,
});

/// A plan session envelope with no carried words — enough for the summary's arithmetic.
class _Envelope implements PlanSessionEnvelope {
  const _Envelope({this.dayCards = 1 << 30, this.kinds = const []});

  /// How many of the cards are the DAY's — everything before this index. The default is «all of
  /// them», which is what a session with nothing else due looks like.
  final int dayCards;

  /// What each card IS in its day — `word` / `chunk` / `line`, positionally. Empty means «the
  /// server did not say», which is the old payload and falls back to «фраза».
  final List<String?> kinds;

  @override
  String get planId => '01PLAN';
  @override
  int get dayIndex => 1;
  @override
  bool get strict => true;
  @override
  String? stageLetterAt(int i) => 'A';
  @override
  ({int ordinal, int of})? stepAt(int i) => (ordinal: 1, of: 3);
  @override
  int? carriedFromAt(int i) => null;
  @override
  bool isDayTaskAt(int i) => i < dayCards;
  @override
  int get dayTaskCount => dayCards;
  @override
  ({String kind, String title})? originAt(int i) => null;
  @override
  String? kindAt(int i) => i >= 0 && i < kinds.length ? kinds[i] : null;
  @override
  // The summary does not read it; the SESSION card does, and that side is covered on the model
  // itself (`plan_contract_v02_test.dart`) rather than by driving the whole session screen.
  String? speakerAt(int i) => null;
}

/// Counts the «this run ended» calls without touching the queue or the network.
///
/// A subclass rather than a mock: [SessionCompletionSync.record] is the whole surface under test,
/// and its real body writes to drift and fires a request. `null` for both collaborators is safe
/// exactly because nothing below the override is ever reached.
class _CompletionSpy implements SessionCompletionSync {
  final List<String> recorded = [];

  @override
  Future<void> record({required String sessionId, DateTime? endedAt}) async {
    recorded.add(sessionId);
  }

  @override
  Future<void> flush() async {}

  @override
  Future<int> pendingCount() async => 0;

  @override
  noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

/// Never sends anything: the summary flushes reviews on the way in, and this test is not about that.
class _ReviewSyncStub implements ReviewSync {
  @override
  noSuchMethod(Invocation invocation) => Future<void>.value();
}

/// [PlanDaySummary] under everything it reaches for on the way in: the plan it names, and the two
/// queues it closes the run through. Without the last two the screen would write to drift and fire
/// a request from a widget test.
ProviderScope _summaryScope(_CompletionSpy spy, Widget child) => ProviderScope(
  overrides: [
    planProvider('01PLAN').overrideWith((ref) async => _plan()),
    sessionCompletionSyncProvider.overrideWithValue(spy),
    reviewSyncProvider.overrideWithValue(_ReviewSyncStub()),
  ],
  child: child,
);

void main() {
  testWidgets('every example goal is one the SERVER would accept', (tester) async {
    // The live run found this the fastest way there is: «Врач» is four characters, the server's
    // `CreatePlanRequest` refuses a goal under five, and tapping an example the screen itself
    // suggests produced «Не получилось собрать план». An example that cannot be used is worse than
    // no example — so the chips are held to the server's own floor here.
    await tester.pumpWidget(
      ProviderScope(child: _app(const PlanBuilderScreen())),
    );
    await tester.pumpAndSettle();

    final chips = tester
        .widgetList<Text>(find.descendant(of: find.byType(Wrap), matching: find.byType(Text)))
        .map((t) => t.data ?? '')
        .where((s) => s.isNotEmpty);

    expect(chips, isNotEmpty);
    for (final chip in chips) {
      expect(
        chip.trim().length,
        greaterThanOrEqualTo(5),
        reason: '«$chip» is shorter than the server\'s goal_text floor of 5',
      );
    }
  });

  testWidgets('«Собрать план» stays shut until the goal clears that same floor', (tester) async {
    await tester.pumpWidget(
      ProviderScope(child: _app(const PlanBuilderScreen(initialGoal: 'Врач'))),
    );
    await tester.pumpAndSettle();

    // Four characters: the button must not offer to spend a request on a 422.
    final short = tester.widget<PrimaryButton>(
      find.widgetWithText(PrimaryButton, 'Собрать план'),
    );
    expect(short.enabled && short.onPressed != null, isFalse);
  });

  testWidgets('the empty План tab explains the difference from a collection', (tester) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          activePlanProvider.overrideWith((ref) async => null),
          planArchiveProvider.overrideWith((ref) async => <PlanSummary>[]),
        ],
        child: _app(const PlanTabScreen()),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Подготовиться к чему-то конкретному'), findsOneWidget);
    expect(find.text('Говоришь цель и дату'), findsOneWidget);
    expect(find.text('Составить план'), findsOneWidget);
  });

  testWidgets('a day sets phrases apart from words and marks every word with its stage', (
    tester,
  ) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          planDayProvider((planId: '01PLAN', dayIndex: 2)).overrideWith((ref) async => _day()),
        ],
        child: _app(PlanDayScreen(plan: _plan(focus: 2), dayIndex: 2)),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('ФРАЗЫ ДНЯ'), findsOneWidget);
    expect(find.text("It's a sharp pain."), findsOneWidget);
    expect(find.text('СЛОВА В ЭТИХ ФРАЗАХ'), findsOneWidget);
    expect(find.text('sharp'), findsOneWidget);
    // The day's own word stands on A…
    expect(find.text('A'), findsOneWidget);
    // …and yesterday's is carried in with the day it came from named beside its stage.
    expect(find.text('B · со дня 1'), findsOneWidget);
    // The conversation is present and honestly locked, not hidden.
    expect(find.text('РАЗГОВОР'), findsOneWidget);
  });

  testWidgets('a running plan can always be given up — there is no other way out', (tester) async {
    // The server allows ONE running plan per learner. Without this link the only exit from a plan is
    // its own event, which makes a plan that went wrong a trap. No frame of «Фаза 4» draws it; the
    // product needs it, so the guard names it rather than leaving it to be «tidied away» later.
    await tester.pumpWidget(
      ProviderScope(
        overrides: [planProvider('01PLAN').overrideWith((ref) async => _plan())],
        child: _app(const PlanScreen(planId: '01PLAN')),
      ),
    );
    await tester.pumpAndSettle();

    await tester.scrollUntilVisible(find.text('Отказаться от плана'), 300);
    expect(find.text('Отказаться от плана'), findsOneWidget);
  });

  testWidgets('the day list tells queued, being written and burned apart (Д-20)', (tester) async {
    // The live plan's list: day 2 burned, days 3 and 4 untouched with zero attempts — and all of
    // them wearing «Собирается», with «Продолжить день 2» underneath. The learner found out day 2
    // was dead by pressing it.
    final plan = LearningPlan.fromJson({
      ..._planJson(),
      'focus_day_index': 2,
      'next_day_index': 3,
      'days': [
        {'id': 'd1', 'index': 1, 'kind': 'intro', 'title': 'Открыть приём', 'status': 'done'},
        {
          'id': 'd2',
          'index': 2,
          'kind': 'intro',
          'title': 'Ответить на вопросы врача',
          'status': 'failed',
          'generation_attempts': 2,
          'fail_code': 'day.example_is_a_term',
        },
        {'id': 'd3', 'index': 3, 'kind': 'intro', 'title': 'Понять назначение', 'status': 'pending'},
        {
          'id': 'd4',
          'index': 4,
          'kind': 'intro',
          'title': 'Забрать лекарство',
          'status': 'generating',
        },
      ],
    });

    await tester.pumpWidget(
      ProviderScope(
        overrides: [planProvider('01PLAN').overrideWith((ref) async => plan)],
        child: _app(const PlanScreen(planId: '01PLAN')),
      ),
    );
    await tester.pumpAndSettle();

    // Three states, three sentences — and «Собирается» belongs to the ONE day a worker holds.
    expect(find.text('Не собрался'), findsOneWidget);
    expect(find.text('В очереди'), findsOneWidget);
    expect(find.text('Собирается'), findsOneWidget);

    // …and the burned focus day is not offered as something to continue.
    expect(find.text('Продолжить день 2'), findsNothing);
    expect(find.text('Открыть день 2'), findsOneWidget);
  });

  testWidgets('a day out of attempts does not offer a button that cannot work', (tester) async {
    // The server claims a day at most twice; after that `PlanDay::claim()` returns false forever,
    // so «Собрать день» there is a button no number of presses can make work. Found on the owner's
    // phone: a day failed the validator twice and the screen kept offering to build it.
    final dead = PlanDayDetail.fromJson({
      'id': 'd1',
      'index': 1,
      'kind': 'intro',
      'title': 'Представиться и начать разговор',
      'status': 'failed',
      'generation_attempts': 2,
      'fail_reason': 'day.key_not_support_language',
      'plan_id': '01PLAN',
      'terms': [],
    });

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          planDayProvider((planId: '01PLAN', dayIndex: 1)).overrideWith((ref) async => dead),
        ],
        child: _app(PlanDayScreen(plan: _plan(focus: 1), dayIndex: 1)),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Собрать день'), findsNothing);
    expect(find.text('Собрать план заново'), findsOneWidget);
    expect(find.textContaining('больше не будет пытаться'), findsOneWidget);
  });

  testWidgets('a burned day names the CAUSE the server reported, not a stock one (Д-19)', (
    tester,
  ) async {
    // The live day died on `day.example_is_a_term` and the screen said the model had answered in
    // the wrong language — one hard-coded sentence used for every failure there is.
    final dead = PlanDayDetail.fromJson({
      'id': 'd1',
      'index': 1,
      'kind': 'intro',
      'title': 'Ответить на вопросы врача',
      'status': 'failed',
      'generation_attempts': 2,
      'fail_code': 'day.example_is_a_term',
      'plan_id': '01PLAN',
      'terms': [],
    });

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          planDayProvider((planId: '01PLAN', dayIndex: 1)).overrideWith((ref) async => dead),
        ],
        child: _app(PlanDayScreen(plan: _plan(focus: 1), dayIndex: 1)),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.textContaining('пример к карточке повторял другую карточку'), findsOneWidget);
    // …and the sentence that used to be said about everything is gone from this day.
    expect(find.textContaining('не на том языке'), findsNothing);
  });

  testWidgets('an UNKNOWN fail code gets the neutral sentence, never an invented one', (
    tester,
  ) async {
    final dead = PlanDayDetail.fromJson({
      'id': 'd1',
      'index': 1,
      'kind': 'intro',
      'title': 'Ответить на вопросы врача',
      'status': 'failed',
      'generation_attempts': 2,
      // A code this build of the app has never heard of — a server ahead of the client.
      'fail_code': 'day.something_new_entirely',
      'plan_id': '01PLAN',
      'terms': [],
    });

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          planDayProvider((planId: '01PLAN', dayIndex: 1)).overrideWith((ref) async => dead),
        ],
        child: _app(PlanDayScreen(plan: _plan(focus: 1), dayIndex: 1)),
      ),
    );
    await tester.pumpAndSettle();

    // A plausible wrong reason costs more than an honest missing one.
    expect(find.textContaining('не удалось собрать день'), findsOneWidget);
  });

  testWidgets('the FINAL day offers the run-through, never «Собрать день» (Д-27)', (tester) async {
    // The dead end. The final day introduces nothing and owns no collection, so the server refuses
    // to build it (404) — and the screen drew «Собрать день» anyway, took the refusal and printed
    // «материал не прошёл проверку». The plan could not be finished from the app at all; the live
    // run closed it from tinker.
    final finalDay = PlanDayDetail.fromJson({
      'id': 'd5',
      'index': 5,
      'kind': 'final',
      'title': 'Прогон перед событием',
      'status': 'pending',
      'plan_id': '01PLAN',
      'terms': <dynamic>[],
    });

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          planDayProvider((planId: '01PLAN', dayIndex: 5)).overrideWith((ref) async => finalDay),
        ],
        child: _app(PlanDayScreen(plan: _plan(focus: 5), dayIndex: 5)),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Пройти прогон'), findsOneWidget);
    expect(find.text('Собрать день'), findsNothing);
    expect(find.text('Собрать план заново'), findsNothing);
  });

  testWidgets('the interlocutor’s line is marked in the day’s register (Д-8)', (tester) async {
    final day = PlanDayDetail.fromJson({
      'id': 'd1',
      'index': 1,
      'kind': 'intro',
      'title': 'Открыть приём для ребёнка',
      'status': 'ready',
      'plan_id': '01PLAN',
      'terms': [
        {
          'id': 't1',
          'text': 'Hello. What seems to be the problem with your child?',
          'translation': 'Здравствуйте. Что с вашим ребёнком?',
          'type': 'phrase',
          'kind': 'line',
          'speaker': 'role',
          'stage': 'a',
          'from_day_index': 1,
        },
        {
          'id': 't2',
          'text': 'I came with my son.',
          'translation': 'Я пришёл с сыном.',
          'type': 'phrase',
          'kind': 'line',
          'speaker': 'learner',
          'stage': 'a',
          'from_day_index': 1,
        },
      ],
    });

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          planDayProvider((planId: '01PLAN', dayIndex: 1)).overrideWith((ref) async => day),
        ],
        child: _app(PlanDayScreen(plan: _plan(focus: 1), dayIndex: 1)),
      ),
    );
    await tester.pumpAndSettle();

    // Both lines are shown; exactly one of them says whose it is. Without the caption the doctor's
    // question stood among the learner's own phrases and read as one to learn to say.
    expect(find.text('Hello. What seems to be the problem with your child?'), findsOneWidget);
    expect(find.text('I came with my son.'), findsOneWidget);
    expect(find.text('СОБЕСЕДНИК:'), findsOneWidget);
  });

  testWidgets('a day that failed ONCE is still offered a retry', (tester) async {
    final retryable = PlanDayDetail.fromJson({
      'id': 'd1',
      'index': 1,
      'kind': 'intro',
      'title': 'Представиться и начать разговор',
      'status': 'failed',
      'generation_attempts': 1,
      'plan_id': '01PLAN',
      'terms': [],
    });

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          planDayProvider((planId: '01PLAN', dayIndex: 1)).overrideWith((ref) async => retryable),
        ],
        child: _app(PlanDayScreen(plan: _plan(focus: 1), dayIndex: 1)),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Собрать день'), findsOneWidget);
    expect(find.text('Собрать план заново'), findsNothing);
  });

  testWidgets('the day opened out of turn warns BEFORE the button, not after the session', (
    tester,
  ) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          planDayProvider((planId: '01PLAN', dayIndex: 2)).overrideWith((ref) async => _day()),
        ],
        // Focus is day 1, so day 2 is ahead of it — a soft run.
        child: _app(PlanDayScreen(plan: _plan(focus: 1), dayIndex: 2)),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.textContaining('пройдёт мягко'), findsOneWidget);
  });

  testWidgets('the day summary counts TERMS and says «в работе», never «выучено»', (tester) async {
    await tester.pumpWidget(
      _summaryScope(
        _CompletionSpy(),
        _app(
          PlanDaySummary(
            envelope: const _Envelope(
              kinds: ['line', 'line', 'word', 'word', 'word'],
            ),
            // Five cards, three distinct terms: one word arrives as several cards inside a stage
            // and the summary must not count the questions.
            cards: [
              _card('t1', 'phrase'),
              _card('t1', 'phrase'),
              _card('t2', 'word'),
              _card('t2', 'word'),
              _card('t3', 'word'),
            ],
            onDone: () {},
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('День 1 пройден'), findsOneWidget);
    expect(find.text('3 фразы и слова в работе'), findsOneWidget);
    expect(find.text('Ступень A пройдена'), findsOneWidget);
    expect(find.textContaining('выучен'), findsNothing);

    // Nothing else was due, so there is no revision row to draw.
    expect(find.text('Повторение'), findsNothing);
  });

  testWidgets('the summary counts the day apart from what the queue added to the sitting', (
    tester,
  ) async {
    // The live complaint, in one screen: a day of THREE terms topped up with two the ordinary
    // queue had due. Counting them together announced a day that was twice the size it was — and on
    // the account it happened to, two of the extras were French inside an English plan.
    await tester.pumpWidget(
      _summaryScope(
        _CompletionSpy(),
        _app(
          PlanDaySummary(
            envelope: const _Envelope(
              dayCards: 4,
              kinds: ['line', 'line', 'word', 'word', 'line', 'word'],
            ),
            cards: [
              _card('t1', 'phrase'),
              _card('t1', 'phrase'),
              _card('t2', 'word'),
              _card('t3', 'word'),
              // Past the seam: the top-up.
              _card('r1', 'phrase'),
              _card('r2', 'word'),
            ],
            onDone: () {},
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();

    // THREE, not five: the headline is the day.
    expect(find.text('3 фразы и слова в работе'), findsOneWidget);

    // …and the revision is said, on its own row, in its own words.
    expect(find.text('Повторение'), findsOneWidget);
    expect(find.text('2 слова'), findsOneWidget);
  });

  testWidgets('the milestone screen does NOT close the run — the session does (Д-1, Д-28)', (
    tester,
  ) async {
    // The blocker, and the shape of its real fix. `record` used to live in the summary WIDGETS, one
    // copy each, and this was the copy that was missing: the live run answered 103 cards over three
    // plan days and sent zero completions. Copying the call in here would have fixed the symptom
    // and left the design that produced it — so the call moved to the SESSION, which is the thing
    // that actually ends, and this screen went back to being only a screen.
    //
    // The behaviour that replaced it is pinned end to end in plan_session_closes_test.dart, on the
    // real session screen: the last card answered closes the run, whichever summary is drawn.
    final spy = _CompletionSpy();

    await tester.pumpWidget(
      _summaryScope(
        spy,
        _app(
          PlanDaySummary(
            envelope: const _Envelope(kinds: ['word']),
            cards: [_card('t1', 'word')],
            onDone: () {},
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(spy.recorded, isEmpty);
  });

  testWidgets('the day is counted by KIND — «4 слова · 2 связки · 8 фраз» (Д-5)', (tester) async {
    // The live day, exactly: 4 word + 2 chunk + 8 line. It was announced as «3 слова · 11 фраз»,
    // because the screen counted words in the text — so a two-word term was a phrase and so was
    // the connector «five».
    const kinds = [
      'word', 'word', 'word', 'word',
      'chunk', 'chunk',
      'line', 'line', 'line', 'line', 'line', 'line', 'line', 'line',
    ];

    await tester.pumpWidget(
      _summaryScope(
        _CompletionSpy(),
        _app(
          PlanDaySummary(
            envelope: const _Envelope(kinds: kinds),
            // Every card a single word of text on purpose: if the count still read the text, all
            // fourteen would come out «слово» and the assertion below would fail loudly.
            cards: [for (var i = 0; i < kinds.length; i++) _card('t$i', 'word')],
            onDone: () {},
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('4 слова · 2 связки · 8 фраз'), findsOneWidget);
  });
}
