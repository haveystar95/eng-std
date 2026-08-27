import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';
import 'package:eng_std/l10n/app_localizations.dart';

import '../../data/local/cached_image_provider.dart';
import '../../data/local/sync_service.dart' show SyncState;
import '../../data/models.dart';
import '../../data/pronouncer.dart';
import '../../data/providers.dart';
import '../../data/word_status.dart' show ladderRungFor, ladderRungLabel;
import '../collections/generate_screen.dart';
import '../collections/my_words_screen.dart';
import '../collections/store_view.dart' show showStorePreview;
import '../daily/word_challenge.dart';
import '../daily/word_challenge_card.dart';
import '../home/streak.dart';
import 'session_screen.dart';
import 'triage_screen.dart';

/// «Главная» (кадры 17a–17d) — ONE QUESTION: what do I do right now, and how long will it take.
///
/// The screen used to be five blocks that each answered a different question with a different
/// counter: a daily goal that counted words TAKEN into study, a state-dependent CTA, a word of the
/// day nobody tapped, a carousel that duplicated the Collections tab, and a generation form. It now
/// answers the one question, and everything else is a line or lives in its own tab.
///
/// The day comes from the server ([homePlanProvider], `GET /home-plan`) and is read out of the
/// LOCAL DB like every other screen here, so the home opens on a plane with the last known day.
/// Its state — plan / done / idle / empty — is named by the server too: the composition on the card
/// is what the session builder would actually deal, and two places deriving that separately is
/// exactly how a screen ends up promising a session that comes back empty.
///
/// THE EMPTY RULE, everywhere: a block with nothing to say is not drawn. Not drawn as a zero, not
/// drawn greyed out — absent. «0 слов» is not a sentence this screen says, and
/// `test/features/home/home_plan_blocks_test.dart` is the guard that keeps it that way.
/// The blocks of the home screen, by name.
///
/// Keys rather than widget types because the rule they guard is about ABSENCE: «блок без данных не
/// рисуется» is only checkable if the test can ask for a block that may legitimately not be there,
/// and a private widget type is not something a test can name.
/// Pinned by `test/features/home/home_plan_blocks_test.dart`.
abstract final class HomeBlockKeys {
  static const header = Key('home-header');
  static const session = Key('home-session-card');
  static const done = Key('home-done-card');
  static const idle = Key('home-idle-card');

  /// «Выучено 146 · за неделю 23 · в работе 41» — the three plates (кадры 19-1, 19-2). The same
  /// block and the same shape whatever the day is doing: numbers the learner checks daily must not
  /// change form by time of day, or they read as two different facts.
  static const stats = Key('home-stats');

  /// «Завтра выпадет 14 слов →» (кадры 19-1, 19-2).
  static const tomorrow = Key('home-tomorrow');

  /// Слово-вызов (кадр 19-4) — the same card on all three screens, above the «завтра» row.
  static const challenge = Key('home-challenge');

  static const hardest = Key('home-hardest');

  /// «+5 слов продвинулись · reluctant дошло до „написание"» — inside the evening card (кадр 19-2).
  static const award = Key('home-day-award');

  static const generate = Key('home-generate');

  /// The quiet line under the generation card (кадры 19-1, 19-4). The evening and the first day
  /// offer the store as [storeShowcase] instead — a strip of covers rather than a sentence.
  static const storeLink = Key('home-store-link');
  static const storeShowcase = Key('home-store-showcase');

  static const firstDay = Key('home-first-day');

  /// «5 минут в день — 20 слов в неделю» (кадр 19-3) — the only number on a screen with no
  /// statistics yet.
  static const promise = Key('home-promise');

  /// The day is not known YET — the local row has not arrived, or the first sync is still running.
  static const loading = Key('home-loading');

  /// The day is not known, and it is not coming: nothing was ever cached and the last sync did not
  /// land. Its twin below is the same card for a different cause, kept apart so a test (and a log
  /// reader) can tell which one the learner is looking at.
  static const unreachable = Key('home-unreachable');
  static const unreadable = Key('home-unreadable');
}

class TrainingHomeScreen extends ConsumerStatefulWidget {
  const TrainingHomeScreen({super.key, this.onOpenStore});

  /// Switches the shell to the Collections tab, on «Готовые» — the store. Used by the quiet
  /// «или выбрать из N готовых →» line, which is the store's entrance from the main screen.
  final VoidCallback? onOpenStore;

  @override
  ConsumerState<TrainingHomeScreen> createState() => _TrainingHomeScreenState();
}

class _TrainingHomeScreenState extends ConsumerState<TrainingHomeScreen> {
  /// Built on the first word-card tap, not on mount: the home screen speaks nothing by itself, and
  /// a TTS engine constructed for a screen that may never open a card is a platform channel opened
  /// for nothing (and one more thing a widget test has to stand up).
  Pronouncer? _pronouncer;

  /// Words taken into study FROM THE CHALLENGE this session.
  ///
  /// The button's own state, not the pool's: the enrolment rides a durable queue and the mirror
  /// catches up a frame or three later, and a button that stays pressable in the meantime invites a
  /// second tap on a word already taken.
  final Set<String> _enrolledFromChallenge = {};

  @override
  void dispose() {
    _pronouncer?.release();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    // The WHOLE AsyncValue, not `.value`: «ещё не знаю» and «знаю, что дня нет» are different
    // answers and this screen owes a different picture to each (BUG-1).
    final day = ref.watch(homePlanProvider);
    final stats = ref.watch(statsProvider).value;
    final streak = stats?.streakDays ?? 0;
    // «Выучено» is the dashboard's own total and lives on `/stats`; the plan owns the other two
    // numbers of the tile. Reading it from there rather than adding a third copy to `/home-plan`.
    final learned = stats?.learned ?? 0;
    // Null while it is still being built, and null when there is nothing to build — and both mean
    // the same thing to this screen: no card. A challenge is never worth a placeholder.
    final challenge = ref.watch(wordChallengeProvider).value;
    final online = ref.watch(connectivityProvider).value ?? true;

    final bottomInset =
        AppTabBarMetrics.height +
        AppTabBarMetrics.bottomInset +
        MediaQuery.viewPaddingOf(context).bottom +
        AppSpacing.s8;

    // Dark status-bar glyphs on the paper background. An AnnotatedRegion in the tree is
    // authoritative — it overrides the global theme's default.
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.dark,
      child: SafeArea(
        bottom: false,
        child: RefreshIndicator(
          color: AppColors.ink,
          backgroundColor: AppColors.surfaceRaised,
          onRefresh: () async {
            // Full resync (authoritative snapshot) so pull-to-refresh also reaps ghost collections
            // removed server-side without a tombstone — and re-reads the day.
            await ref.read(syncServiceProvider).resync();
          },
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: EdgeInsets.fromLTRB(
              AppSpacing.screenH,
              AppSpacing.s8,
              AppSpacing.screenH,
              bottomInset,
            ),
            children: day.when(
              // Nothing is known yet — and «ещё не знаю» is drawn as waiting, never as an empty
              // page. The row usually arrives within a frame; on a cold start it may not.
              loading: () => const [_DayPlaceholder(key: HomeBlockKeys.loading)],
              // The local read itself failed. Vanishingly rare, and still not a blank page.
              error: (e, st) {
                debugPrint('[home] the cached day could not be read: $e\n$st');
                return [_UnreachableCard(key: HomeBlockKeys.unreadable)];
              },
              data: (view) => _blocks(
                context,
                view,
                streak: streak,
                learned: learned,
                challenge: challenge,
                online: online,
              ),
            ),
          ),
        ),
      ),
    );
  }

  /// The screen, block by block. A list rather than a column of `if`s so the guard test can ask the
  /// simple question it needs to ask: is this widget in the tree at all.
  List<Widget> _blocks(
    BuildContext context,
    HomePlanView view, {
    required int streak,
    required int learned,
    required WordChallenge? challenge,
    required bool online,
  }) {
    final l = AppLocalizations.of(context);
    final gap = const SizedBox(height: AppSpacing.sectionAiry);

    if (!online) {
      return [
        const _OfflineBanner(),
        gap,
        ..._blocks(
          context,
          view,
          streak: streak,
          learned: learned,
          challenge: challenge,
          online: true,
        ),
      ];
    }

    final plan = view.plan;
    if (plan == null) {
      // No day, and now the screen says WHY — instead of the bare «создай коллекцию» row that made
      // a dead server look like a working app with nothing in it (BUG-1).
      return [_NoDay(cache: view.cache)];
    }

    // КАДР 19-3 — the first day. Two doors, and the shop window is the bigger of them: a photograph
    // sells a topic and a bulleted list of topics does not. There is no streak, no statistics and no
    // «завтра» here — not as zeroes, but as blocks that do not exist yet.
    if (plan.state == HomeStateKind.empty) {
      return [
        // ONE child, and it is as tall as the page: the promise is a FOOTER (кадр 19-3 pins it with
        // `margin-top:auto`), and a first day is short enough that a plain column leaves it stranded
        // in the middle of the paper with a screenful of nothing under it. A minimum height plus a
        // Spacer puts it where the frame puts it and still lets the column scroll if it outgrows the
        // screen — which it will the day the word challenge lands in the slot below.
        ConstrainedBox(
          constraints: BoxConstraints(minHeight: _pageHeight(context)),
          // IntrinsicHeight, because a MINIMUM is not a height: inside a list the column still
          // receives an unbounded maximum, and a Spacer cannot divide infinity. This measures the
          // column, the minimum above raises the answer to a full page, and the Spacer then has
          // something finite to take the slack out of.
          child: IntrinsicHeight(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  l.homeFirstDayTitle,
                  key: HomeBlockKeys.firstDay,
                  style: AppText.stepTitle.copyWith(fontSize: 26),
                ),
                const SizedBox(height: AppSpacing.s16),
                if (plan.store.items.isNotEmpty) ...[
                  _StoreShowcase(
                    key: HomeBlockKeys.storeShowcase,
                    store: plan.store,
                    large: true,
                    onOpenStore: widget.onOpenStore,
                    onOpen: _openStoreDeck,
                  ),
                  const SizedBox(height: AppSpacing.s16),
                ],
                _GenerateCard(key: HomeBlockKeys.generate, withField: true, withChips: true),
                if (challenge != null) ...[
                  const SizedBox(height: AppSpacing.s12),
                  _challengeCard(challenge),
                ],
                const Spacer(),
                const SizedBox(height: AppSpacing.sectionAiry),
                Center(
                  child: Text(
                    l.homeFirstDayPromise,
                    key: HomeBlockKeys.promise,
                    style: AppText.translation.copyWith(fontSize: 13, color: AppColors.tertiary),
                  ),
                ),
              ],
            ),
          ),
        ),
      ];
    }

    final session = plan.session;
    final evening = plan.state == HomeStateKind.done;
    final stats = _statCells(l, plan, learned: learned);

    return [
      _DayHeader(key: HomeBlockKeys.header, streak: streak),
      gap,
      if (plan.state == HomeStateKind.plan)
        _SessionCard(key: HomeBlockKeys.session, session: session, onStart: () => _startDay(plan))
      else if (evening)
        _DoneCard(
          key: HomeBlockKeys.done,
          today: plan.today,
          session: session,
          award: plan.dayAward,
          hardest: plan.hardest,
          onTakeNew: () => _openSession(learn: true),
          onExtra: () =>
              _openTriage(session.triageCollectionId!, session.triageCollectionTitle ?? ''),
        )
      else
        _IdleCard(
          key: HomeBlockKeys.idle,
          inWork: plan.inWork,
          session: session,
          nextReview: plan.nextReview,
          onTakeNew: () => _openSession(learn: true),
          onSort: () =>
              _openTriage(session.triageCollectionId!, session.triageCollectionTitle ?? ''),
        ),
      if (stats.isNotEmpty) ...[
        gap,
        // THE SAME PLATES IN EVERY STATE. They were a single line in the evening at first, on the
        // theory that a closed day turns the numbers from a decision into a receipt — and on the
        // phone that reading did not survive contact: one screen said the three numbers in two
        // different shapes, so they read as two different facts. Three numbers the learner checks
        // every day are one block, and a block that changes shape by time of day is a block they
        // have to re-find.
        _StatsTile(key: HomeBlockKeys.stats, cells: stats, onTap: _openMyWords),
      ],
      if (challenge != null) ...[gap, _challengeCard(challenge)],
      if (plan.edgeTomorrow != null) ...[
        gap,
        _TomorrowRow(key: HomeBlockKeys.tomorrow, count: plan.edgeTomorrow!, onTap: _openMyWords),
      ],
      gap,
      _GenerateCard(key: HomeBlockKeys.generate),
      // The evening ends on the shop window — it cures the emptiness under a finished day and sells
      // the next set with photographs. The morning gets the quiet line instead: the day is the point
      // of that screen, and a strip of covers under a session waiting to be started is a shop in the
      // doorway.
      if (evening && plan.store.items.isNotEmpty) ...[
        gap,
        _StoreShowcase(
          key: HomeBlockKeys.storeShowcase,
          store: plan.store,
          onOpenStore: widget.onOpenStore,
          onOpen: _openStoreDeck,
        ),
      ] else if (!evening && plan.store.count > 0 && widget.onOpenStore != null) ...[
        const SizedBox(height: 9),
        _StoreLink(
          key: HomeBlockKeys.storeLink,
          count: plan.store.count,
          onTap: widget.onOpenStore!,
        ),
      ],
    ];
  }

  /// How much room a full page of this screen has, inside the list's own padding.
  ///
  /// The same three subtractions [build] makes for the list's bottom inset, so «as tall as the page»
  /// means the same thing in both places: the floating tab bar, the device's own bottom inset, and
  /// the list's top padding are not page.
  double _pageHeight(BuildContext context) {
    final media = MediaQuery.of(context);
    final chrome =
        AppTabBarMetrics.height +
        AppTabBarMetrics.bottomInset +
        media.viewPadding.bottom +
        media.viewPadding.top +
        AppSpacing.s8 * 2;

    return (media.size.height - chrome).clamp(0.0, double.infinity);
  }

  /// The statistics block's cells, in the order the frames read them, with the empty rule applied
  /// per cell: a number nobody has yet is not a «0» on the screen, it is one plate fewer.
  ///
  /// «Выучено» comes from `/stats` and «в работе» from the plan's own pool count — the two payloads
  /// this screen already watches, and deliberately not a third source for a number that exists.
  List<_StatCell> _statCells(AppLocalizations l, HomePlan plan, {required int learned}) => [
    if (learned > 0) _StatCell(learned, l.homeStatLearned),
    if (plan.learnedWeek != null) _StatCell(plan.learnedWeek!, l.homeStatWeek),
    if (plan.inWork.total > 0) _StatCell(plan.inWork.total, l.homeStatInWork),
  ];

  /// The challenge, wired to the three acts it offers.
  ///
  /// «Учить» is a REAL enrolment — the same `PoolSync.enroll` the word card's own button calls — so
  /// «Завтра выпадет N» and «В работе» move under it. That is the whole difference between a quiz
  /// and a word the learner now owns.
  Widget _challengeCard(WordChallenge challenge) => WordChallengeCard(
    key: HomeBlockKeys.challenge,
    challenge: challenge,
    enrolled: _enrolledFromChallenge.contains(challenge.termId),
    onAnswer: (option) {
      AppHaptics.light();
      ref
          .read(wordChallengeStoreProvider)
          .answer(now: DateTime.now(), challenge: challenge, option: option);
    },
    onLearn: () {
      AppHaptics.light();
      setState(() => _enrolledFromChallenge.add(challenge.termId));
      ref.read(poolSyncProvider).enroll(challenge.termId);
      _refreshDay();
    },
    onTomorrow: () {
      AppHaptics.light();
      ref.read(wordChallengeStoreProvider).collapse(now: DateTime.now());
    },
  );

  void _openMyWords() {
    AppHaptics.light();
    Navigator.of(context).push(MaterialPageRoute(builder: (_) => const MyWordsScreen()));
  }

  /// A tapped cover opens THE STORE'S OWN preview sheet — the same one the store screen opens, built
  /// from the same row. A second sheet living here would be a second thing to keep in step with the
  /// subscribe flow, the paywall and the pair badge.
  void _openStoreDeck(HomeStoreItem item) {
    AppHaptics.light();
    showStorePreview(context, ref, item.toStoreCollection());
  }

  /// «Начать» — the day's one button, and it opens the day: the study session.
  ///
  /// No branch to the swipe pass any more. The card is drawn only while repeats are due, and the
  /// swipe pass has its own offer for when they are not — one button, one meaning.
  void _startDay(HomePlan plan) => _openSession();

  Future<void> _openSession({bool learn = false}) async {
    AppHaptics.light();
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) =>
            SessionScreen(title: AppLocalizations.of(context).homeSessionTitle, learn: learn),
      ),
    );
    _refreshDay();
  }

  Future<void> _openTriage(String collectionId, String title) async {
    AppHaptics.light();
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => TriageScreen(collectionId: collectionId, title: title),
      ),
    );
    _refreshDay();
  }

  /// Re-read the day after the learner did something that changes it.
  ///
  /// The plan is a cached server answer, refreshed by [SyncService] on start, on resume, on the
  /// network returning and on entering the tab. Coming BACK from a pushed session is none of those:
  /// the home was never disposed and nothing fires, so the card kept yesterday's arithmetic until
  /// something else happened to sync. Twenty answered cards and an unchanged «Сессия на сегодня» is
  /// the app calling the learner's work invisible.
  void _refreshDay() {
    if (mounted) ref.read(syncServiceProvider).sync();
  }

}

/// The date and the streak — «Вторник, 26 августа · Стрик 5» plus the week of dots (кадр 17a).
///
/// Not drawn on the first day (17c): a streak of nothing is not a zero to show, it is a block that
/// does not exist yet.
class _DayHeader extends StatelessWidget {
  const _DayHeader({super.key, required this.streak});
  final int streak;

  @override
  Widget build(BuildContext context) {
    final locale = Localizations.localeOf(context).languageCode;
    final date = DateFormat('EEEE, d MMMM', locale).format(DateTime.now());
    final label = AppText.translation.copyWith(fontSize: 13, color: AppColors.secondary);

    return Row(
      children: [
        Expanded(
          child: Text(
            date.isEmpty ? date : '${date[0].toUpperCase()}${date.substring(1)}',
            style: label,
          ),
        ),
        if (streak > 0) ...[
          Text(AppLocalizations.of(context).homeStreakBadge(streak), style: label),
          const SizedBox(width: 9),
          _StreakDots(streak: streak),
        ],
      ],
    );
  }
}

/// The streak week — exactly [kStreakWeek] dots: past days filled, today an outline, the rest on
/// track (§4 density). Layout counts from [streakDots].
class _StreakDots extends StatelessWidget {
  const _StreakDots({required this.streak});
  final int streak;

  @override
  Widget build(BuildContext context) {
    final dots = streakDots(streak);
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        for (var i = 0; i < dots.length; i++) ...[
          if (i > 0) const SizedBox(width: 5),
          _dot(dots[i]),
        ],
      ],
    );
  }

  Widget _dot(StreakDot kind) => Container(
    width: 7,
    height: 7,
    decoration: BoxDecoration(
      shape: BoxShape.circle,
      color: switch (kind) {
        StreakDot.filled => AppColors.ink,
        StreakDot.today => null,
        StreakDot.empty => AppColors.track,
      },
      border: kind == StreakDot.today ? Border.all(color: AppColors.ink, width: 1.5) : null,
    ),
  );
}

/// «32 слова · ≈ 9 минут» (кадр 19-1) — the one dark surface on the screen, and therefore the one
/// primary action. «Один акцент на экран»: it is the only thing here that gets a fill, and the brass
/// badge is the only warm mark the product spends.
///
/// THE COMPOSITION IS A PRICE LIST — «Повторить 12 / Новых 5 / Разобрать 15», label left, number
/// right, hairline between. It was a proportional bar with a dot legend until the frames landed, and
/// the bar was answering a question nobody asks: the ratio between repeats and new words is not a
/// fact the learner acts on, while the three counts are. A row whose number is 0 is not drawn — «0
/// новых» is the same lie a zero-width segment was.
///
/// «Разобрать» is IN the list and not only in an offer below. What that row counts is genuinely a
/// different population from the two above it — words in the learner's folders that have never been
/// sorted, which the session does not deal — and the card names it because the day is not honestly
/// described without it. What it does NOT do is join the headline: [HomeSession.total] is the
/// server's `repeat + new`, and the swipe pass is priced separately in `triage_minutes`.
class _SessionCard extends StatelessWidget {
  const _SessionCard({super.key, required this.session, required this.onStart});

  final HomeSession session;
  final VoidCallback onStart;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final paper = AppColors.paper;
    final rows = <({String label, int count})>[
      if (session.repeat > 0) (label: l.homeSessionRowRepeat, count: session.repeat),
      if (session.newTerms > 0) (label: l.homeSessionRowNew, count: session.newTerms),
      if (session.triage > 0) (label: l.homeSessionRowTriage, count: session.triage),
    ];

    return Container(
      decoration: BoxDecoration(
        color: AppColors.ink,
        borderRadius: BorderRadius.circular(AppRadii.card),
      ),
      padding: const EdgeInsets.fromLTRB(20, 20, 20, 18),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              // The number is the headline and the unit is not: 60 pt of Literata beside 18 pt, on
              // one baseline. That is why the count and the word «слова» are two strings — one
              // localised phrase could not be set in two sizes.
              Expanded(
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    Text(
                      '${session.total}',
                      style: AppText.counterLarge.copyWith(
                        color: paper,
                        fontSize: 56,
                        height: 0.86,
                      ),
                    ),
                    const SizedBox(width: 11),
                    Padding(
                      padding: const EdgeInsets.only(bottom: 4),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Text(
                            l.homeSessionUnitWords(session.total),
                            style: AppText.stepTitle.copyWith(
                              color: paper,
                              fontSize: 18,
                              height: 1.1,
                            ),
                          ),
                          // Minutes and nothing else. The card count moved out of the headline: the
                          // learner reads this to decide whether they have time, and «~158 карточек»
                          // is a second unit for the same decision. It still has a home — the
                          // collection button's own caption (INPUT-1).
                          if (session.estimatedMinutes != null) ...[
                            const SizedBox(height: 3),
                            Text(
                              l.homeSessionCardMinutes(session.estimatedMinutes!),
                              style: AppText.translation.copyWith(
                                fontSize: 12.5,
                                color: paper.withValues(alpha: 0.55),
                              ),
                            ),
                          ],
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              Padding(
                padding: const EdgeInsets.only(bottom: 7, left: AppSpacing.s8),
                child: Text(
                  l.homeSessionBadge.toUpperCase(),
                  style: AppText.sectionLabel.copyWith(color: AppColors.brass),
                ),
              ),
            ],
          ),
          if (rows.isNotEmpty) ...[
            const SizedBox(height: AppSpacing.s16),
            for (var i = 0; i < rows.length; i++)
              Container(
                padding: const EdgeInsets.symmetric(vertical: 9),
                decoration: BoxDecoration(
                  border: Border(
                    top: BorderSide(color: paper.withValues(alpha: 0.14)),
                    // Only the last row closes the list, so the hairlines read as separators
                    // between items rather than as a box drawn around each of them.
                    bottom: i == rows.length - 1
                        ? BorderSide(color: paper.withValues(alpha: 0.14))
                        : BorderSide.none,
                  ),
                ),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.baseline,
                  textBaseline: TextBaseline.alphabetic,
                  children: [
                    Expanded(
                      child: Text(
                        rows[i].label,
                        style: AppText.translation.copyWith(
                          fontSize: 13.5,
                          color: paper.withValues(alpha: 0.72),
                        ),
                      ),
                    ),
                    Text(
                      '${rows[i].count}',
                      style: AppText.translation.copyWith(
                        fontSize: 14.5,
                        fontWeight: FontWeight.w600,
                        color: paper,
                      ),
                    ),
                  ],
                ),
              ),
          ],
          const SizedBox(height: AppSpacing.s16),
          _InvertedButton(label: l.homeSessionStart, onPressed: onStart),
        ],
      ),
    );
  }
}

/// The paper-on-ink button that lives inside the dark card — the only place in the app where the
/// primary button is light on dark, because the surface it sits on is the dark one.
class _InvertedButton extends StatelessWidget {
  const _InvertedButton({required this.label, required this.onPressed});
  final String label;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: AppColors.paper,
      borderRadius: BorderRadius.circular(AppRadii.field),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onPressed,
        child: SizedBox(
          height: 50,
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Text(label, style: AppText.primaryButton.copyWith(color: AppColors.ink)),
              const SizedBox(width: AppSpacing.s8),
              const Icon(LucideIcons.arrowRight, size: 17, color: AppColors.ink),
            ],
          ),
        ),
      ),
    );
  }
}

/// «Сегодня закрыто — 32 из 32 · 6 мин 40 с» (кадр 17b).
///
/// The day's progress is ANSWERED CARDS, not words taken into study: the old «дневная цель» counted
/// the second thing under the first thing's name, and two screens printed different numbers for the
/// same day. The denominator is what the day held — today's answers plus whatever is still left,
/// which in this state is nothing.
class _DoneCard extends StatelessWidget {
  const _DoneCard({
    super.key,
    required this.today,
    required this.session,
    required this.award,
    required this.hardest,
    required this.onExtra,
    required this.onTakeNew,
  });

  final HomeToday? today;
  final HomeSession session;

  /// «+5 слов продвинулись · reluctant дошло до „написание"» — null on a day that moved nothing,
  /// and then the line is not drawn at all.
  final HomeDayAward? award;

  /// What the day got wrong, worst first. Empty on a clean run — and then neither the rule above it
  /// nor its label is drawn either.
  final List<HomeHardTerm> hardest;

  /// Open the swipe pass over the fullest unsorted collection.
  final VoidCallback onExtra;

  /// Deal the words already in the queue that today's quota still allows — «Ещё N слов».
  final VoidCallback onTakeNew;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final answered = today?.answered ?? 0;
    // What the day HELD: what was answered plus what is still owed. `repeat` alone, and not
    // `total`: in this state it is 0 by construction (the day is closed precisely because nothing
    // is due), while leftover new words and unswiped cards are «сверх плана» — counting them into
    // the denominator would print «32 из 47» on a day that was finished.
    final planned = answered + session.repeat;
    // The swipe offer comes from the day's own count, not from the «продолжить» candidate: that one
    // requires a collection the learner has already started, and a set added an hour ago and never
    // opened is exactly the case this offer is for.
    // TWO WAYS to go past a closed day, and the FIRST is words the learner has already taken.
    //
    // The card used to offer only the swipe pass, and that is how «Учить сразу» on a closed evening
    // came to produce nothing visible: the word went into the queue, «Мои слова» listed it, and this
    // screen — the one the learner was looking at — had no sentence in which such a word could
    // appear. New words come first because they are the ones already chosen; the swipe pass is an
    // offer to choose more.
    final canTakeNew = session.newTerms > 0;
    final canSort = session.triage > 0 && session.triageCollectionId != null;
    // «Следующий повтор — завтра, 14 слов» is gone from here: «Завтра выпадет N слов →» says the
    // same thing further down and is a DOOR rather than a sentence. Two of them on one screen is
    // the screen telling the learner the same fact twice in two voices.
    final lines = <String>[
      if (canTakeNew) l.homeExtraNew(session.newTerms),
      if (!canTakeNew && canSort)
        l.homeExtraFromCollection(session.triage, session.triageCollectionTitle ?? ''),
    ];

    return PaperCard(
      radius: AppRadii.card,
      padding: const EdgeInsets.all(20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(l.homeDoneTitle.toUpperCase(), style: AppText.sectionLabel),
          const SizedBox(height: 9),
          Row(
            crossAxisAlignment: CrossAxisAlignment.baseline,
            textBaseline: TextBaseline.alphabetic,
            children: [
              Text(l.homeDoneOf(answered, planned), style: AppText.counterLarge),
              const SizedBox(width: 10),
              if (today != null && today!.seconds > 0)
                Text(
                  formatSessionDuration(l, today!.seconds),
                  style: AppText.translation.copyWith(fontSize: 15, color: AppColors.secondary),
                ),
            ],
          ),
          const SizedBox(height: 14),
          Container(
            height: 4,
            decoration: BoxDecoration(color: AppColors.ink, borderRadius: BorderRadius.circular(2)),
          ),
          if (award != null) ...[
            const SizedBox(height: AppSpacing.s12),
            Text(
              _awardLine(l, award!),
              key: HomeBlockKeys.award,
              style: AppText.translation.copyWith(
                fontSize: 13.5,
                height: 1.45,
                // Brass on paper — кадр 19-2 gives the reward the screen's one warm mark, and it
                // is the one thing on this card that is news rather than arithmetic.
                color: AppColors.brassInk,
              ),
            ),
          ],
          if (lines.isNotEmpty) ...[
            const SizedBox(height: AppSpacing.s12),
            Text(
              lines.join(' '),
              style: AppText.translation.copyWith(
                fontSize: 13.5,
                height: 1.45,
                color: AppColors.inkBody,
              ),
            ),
          ],
          // «ДАЛОСЬ ТРУДНЕЕ ВСЕГО», inside the card and under a rule — кадр 19-2 draws it as part of
          // «Сегодня закрыто» rather than as a section of its own, and it is one thought: what the
          // day closed, and what it closed badly. Compressed to a line of names and the worst count,
          // because a list of rows under a summary is a report.
          if (hardest.isNotEmpty)
            // Keyed even though it is nested now: the rule it answers to is «блок без данных не
            // рисуется», and a guard can only ask for a block it can name.
            Column(
              key: HomeBlockKeys.hardest,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const SizedBox(height: 17),
                const Divider(height: 1, thickness: 1, color: AppColors.dividerFaint),
                const SizedBox(height: 13),
                Text(l.homeHardestTitle.toUpperCase(), style: AppText.sectionLabel),
                const SizedBox(height: AppSpacing.s8),
                Row(
                  crossAxisAlignment: CrossAxisAlignment.baseline,
                  textBaseline: TextBaseline.alphabetic,
                  children: [
                    Expanded(
                      child: Text(
                        hardest.map((t) => t.text).join(' · '),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: AppText.stepTitle.copyWith(fontSize: 17),
                      ),
                    ),
                    const SizedBox(width: AppSpacing.s12),
                    Text(
                      l.homeHardestErrors(hardest.first.errors),
                      style: AppText.translation.copyWith(fontSize: 12, color: AppColors.tertiary),
                    ),
                  ],
                ),
              ],
            ),
          if (canTakeNew) ...[
            const SizedBox(height: AppSpacing.s16),
            QuietButton(label: l.homeExtraButton(session.newTerms), onPressed: onTakeNew),
          ] else if (canSort) ...[
            const SizedBox(height: AppSpacing.s16),
            QuietButton(label: l.homeExtraButton(session.triage), onPressed: onExtra),
          ],
        ],
      ),
    );
  }
}

/// «Всё повторено» (кадр 17d): nothing is due and nothing was answered — the schedule is simply
/// ahead. One button, and it is the one thing that would move the day: take new words.
class _IdleCard extends StatelessWidget {
  const _IdleCard({
    super.key,
    required this.inWork,
    required this.session,
    required this.nextReview,
    required this.onTakeNew,
    required this.onSort,
  });

  final HomeInWork inWork;
  final HomeSession session;
  final HomeNextReview? nextReview;
  final VoidCallback onTakeNew;
  final VoidCallback onSort;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    // Two offers, in the order they are useful: take the words already queued, or sort more out of
    // a collection. Neither is work the day claimed — this is the state where the day claimed none.
    final canTakeNew = inWork.waiting > 0 && inWork.newRemaining > 0;
    final canSort = session.triage > 0 && session.triageCollectionId != null;
    final sortOffer = canSort
        ? [
            l.homeSortOffer(session.triage, session.triageCollectionTitle ?? ''),
            if (session.triageMinutes != null) l.homeSessionCardMinutes(session.triageMinutes!),
          ].join(' · ')
        : null;

    final lines = <String>[
      if (nextReview != null) _nextReviewLine(context, l, nextReview!),
      if (canTakeNew) l.homeIdleQueueStalled,
      if (!canTakeNew && sortOffer != null) sortOffer,
    ];

    return PaperCard(
      radius: AppRadii.card,
      padding: const EdgeInsets.all(20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(l.homeDoneTitle.toUpperCase(), style: AppText.sectionLabel),
          const SizedBox(height: 9),
          Text(
            // «Всё повторено» to someone who has never taken a word into study is a strange thing to
            // say — they repeated nothing. With an empty pool the honest headline is the invitation.
            inWork.total == 0 && canSort ? l.homeSortFirstTitle : l.homeIdleTitle,
            style: AppText.counterLarge.copyWith(fontSize: 28),
          ),
          if (lines.isNotEmpty) ...[
            const SizedBox(height: 9),
            Text(
              lines.join(' '),
              style: AppText.translation.copyWith(
                fontSize: 13.5,
                height: 1.45,
                color: AppColors.inkBody,
              ),
            ),
          ],
          if (canTakeNew) ...[
            const SizedBox(height: AppSpacing.s16),
            PrimaryButton(label: l.homeIdleTakeNew, minHeight: 52, onPressed: onTakeNew),
          ] else if (canSort) ...[
            const SizedBox(height: AppSpacing.s16),
            PrimaryButton(
              label: l.homeTriageAction(session.triage),
              minHeight: 52,
              onPressed: onSort,
            ),
          ],
        ],
      ),
    );
  }
}

/// «Следующий повтор — завтра, 14 слов.» — «завтра» when it is, the date otherwise.
String _nextReviewLine(BuildContext context, AppLocalizations l, HomeNextReview next) {
  final locale = Localizations.localeOf(context).languageCode;
  final parsed = DateTime.tryParse(next.date);
  final today = DateTime.now();
  final tomorrow = DateTime(today.year, today.month, today.day).add(const Duration(days: 1));
  final isTomorrow =
      parsed != null &&
      parsed.year == tomorrow.year &&
      parsed.month == tomorrow.month &&
      parsed.day == tomorrow.day;
  final when = isTomorrow || parsed == null
      ? l.homeWhenTomorrow
      : DateFormat('d MMMM', locale).format(parsed);

  return l.homeNextReviewLine(when, next.count);
}

/// «6 мин 40 с», or plain seconds under a minute. Public so the widget test can state the rule
/// rather than re-derive it.
String formatSessionDuration(AppLocalizations l, int seconds) {
  if (seconds < 60) return l.homeDoneDurationSeconds(seconds);
  return l.homeDoneDuration(seconds ~/ 60, seconds % 60);
}

/// One number of the statistics block: the figure and its caption («146» under «ВЫУЧЕНО»).
class _StatCell {
  const _StatCell(this.value, this.label);
  final int value;
  final String label;
}

/// «146 Выучено · 23 За неделю · 41 В работе» (кадр 19-1) — the three plates, in every state.
///
/// The block answers «сколько уже сделано», which is the second of the three questions the screen
/// exists for, and it sits directly under the answer to the first — in the morning under the session
/// waiting to be started, in the evening under the day just closed. The week of dots is deliberately
/// NOT here: it lives once, in the header beside the streak, and two calendars on one screen is one
/// calendar too many.
///
/// A tap leads to «Мои слова» — the list behind «в работе», and the same door the row this replaced
/// carried. No chevron: the plates are a statement, and the frame draws them without one.
class _StatsTile extends StatelessWidget {
  const _StatsTile({super.key, required this.cells, required this.onTap});

  final List<_StatCell> cells;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return PaperCard(
      radius: 24,
      padding: const EdgeInsets.symmetric(horizontal: 20, vertical: AppSpacing.s16),
      onTap: onTap,
      // IntrinsicHeight so the hairline between the plates spans exactly the tallest of them. The
      // row is three cells, so measuring them twice costs nothing — and a divider given a literal
      // height is a divider that stops matching the type the day the type changes.
      child: IntrinsicHeight(
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            for (var i = 0; i < cells.length; i++) ...[
              if (i > 0)
                Container(
                  width: 1,
                  margin: const EdgeInsets.symmetric(horizontal: AppSpacing.s12),
                  color: AppColors.dividerFaint,
                ),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text('${cells[i].value}', style: AppText.counterLarge.copyWith(fontSize: 26)),
                    const SizedBox(height: 6),
                    Text(
                      cells[i].label.toUpperCase(),
                      // Two lines, because a caption is not a number: «IN PROGRESS» does not fit a
                      // third of 390 pt at this tracking and came out as «IN PROGRE…» on the live
                      // run. All three plates grow together (IntrinsicHeight), so the row stays
                      // even, and Russian still needs one line.
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: AppText.sectionLabel.copyWith(color: AppColors.tertiary),
                    ),
                  ],
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

/// «Завтра выпадет 14 слов →» (кадры 19-1, 19-2) — the shelf for tomorrow, in one row.
///
/// It replaces the LIST of words about to slip. Three words with dates answered a question nobody
/// asks («which ones exactly»); the question they do ask before closing the app is «сколько будет
/// завтра», and the honest form of that answer is a number and a door. The door is «Мои слова»,
/// which is the list behind it for anyone who does want the names.
///
/// Not drawn at all when nothing falls tomorrow — the server sends null rather than 0 precisely so
/// this row can be absent instead of saying «Завтра выпадет 0 слов».
class _TomorrowRow extends StatelessWidget {
  const _TomorrowRow({super.key, required this.count, required this.onTap});

  final int count;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return MinTapHeight(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 6),
        child: Row(
          children: [
            Expanded(
              child: Text(
                l.homeTomorrowRow(count),
                style: AppText.translation.copyWith(fontSize: 13.5, color: AppColors.inkBody),
              ),
            ),
            const SizedBox(width: AppSpacing.s8),
            const Icon(LucideIcons.arrowRight, size: 15, color: AppColors.tertiary),
          ],
        ),
      ),
    );
  }
}

/// «Сгенерировать набор · Опишите ситуацию — соберём набор под неё» (кадры 19-1, 19-2, 19-3).
///
/// A CARD that opens the generation screen, not a field that generates in place. The inline field
/// was the visual centre of a screen whose centre is the day: a text input outranks everything
/// around it simply by being an input, and it outranked «Начать».
///
/// The first day is the exception ([withField]): there is no day to outrank yet, so the invitation
/// is spelled out and three example topics stand under it. Tapping the field opens the same screen —
/// it is a written invitation, not a second place to type.
class _GenerateCard extends StatelessWidget {
  const _GenerateCard({super.key, this.withField = false, this.withChips = false});

  final bool withField, withChips;

  void _open(BuildContext context, {String? topic}) {
    AppHaptics.light();
    Navigator.of(
      context,
    ).push(MaterialPageRoute(builder: (_) => GenerateScreen(initialTopic: topic)));
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final chips = [l.homeGenerateChipInterview, l.homeGenerateChipVet, l.homeGenerateChipMoving];

    final card = Container(
      decoration: BoxDecoration(
        color: AppColors.surfaceRaised,
        borderRadius: BorderRadius.circular(withField ? 24 : 22),
        // BRASS, and the only outline on the paper ground. Кадры 19-1 / 19-2 / 19-3 mark this card
        // and nothing else: it is the screen's second door, and the border is how they say so.
        border: Border.all(color: AppColors.brassHairline),
      ),
      padding: EdgeInsets.all(withField ? AppSpacing.s16 : 14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              // The spark on a brass plate. The frame fills it with a gradient; the palette has no
              // gradients, so it is the flat token — same colour, one technique fewer.
              Container(
                width: 36,
                height: 36,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: AppColors.brassPlate,
                  borderRadius: BorderRadius.circular(AppRadii.small),
                ),
                child: const Icon(LucideIcons.sparkles, size: 19, color: AppColors.paper),
              ),
              const SizedBox(width: AppSpacing.s12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      l.homeGenerateCardTitle,
                      style: AppText.translation.copyWith(
                        fontSize: 15,
                        fontWeight: FontWeight.w700,
                        color: AppColors.ink,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      l.homeGenerateCardHint,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: AppText.translation.copyWith(fontSize: 12, color: AppColors.tertiary),
                    ),
                  ],
                ),
              ),
              if (!withField) ...[
                const SizedBox(width: AppSpacing.s8),
                const Icon(LucideIcons.arrowRight, size: 16, color: AppColors.brassInk),
              ],
            ],
          ),
          if (withField) ...[
            const SizedBox(height: 13),
            GestureDetector(
              onTap: () => _open(context),
              child: Container(
                height: 46,
                padding: const EdgeInsets.only(left: 14, right: 5),
                decoration: BoxDecoration(
                  color: AppColors.field,
                  borderRadius: BorderRadius.circular(AppRadii.field),
                  border: Border.all(color: AppColors.hairline),
                ),
                child: Row(
                  children: [
                    Expanded(
                      child: Text(
                        l.homeGeneratePlaceholder,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: AppText.translation.copyWith(
                          fontSize: 14,
                          color: AppColors.tertiary,
                        ),
                      ),
                    ),
                    Container(
                      width: 40,
                      height: 36,
                      alignment: Alignment.center,
                      decoration: BoxDecoration(
                        color: AppColors.ink,
                        borderRadius: BorderRadius.circular(AppRadii.small),
                      ),
                      child: const Icon(LucideIcons.arrowRight, size: 17, color: AppColors.paper),
                    ),
                  ],
                ),
              ),
            ),
          ],
          if (withChips) ...[
            const SizedBox(height: 11),
            Wrap(
              spacing: 7,
              runSpacing: 7,
              children: [
                for (final chip in chips)
                  // Tappable, unlike the topic chips they replace: an EXAMPLE the learner cannot
                  // act on is a caption, and these are the fastest way into the screen above.
                  _ExampleChip(
                    label: chip,
                    onTap: () => _open(context, topic: chip),
                  ),
              ],
            ),
          ],
        ],
      ),
    );

    // With a field the card has its own tap targets — the field and the three chips — and a wrapper
    // over them would swallow the chip taps. Without one, the whole card is the button.
    return withField
        ? card
        : GestureDetector(
            behavior: HitTestBehavior.opaque,
            onTap: () => _open(context),
            child: card,
          );
  }
}

/// An outline pill under the first-day generation card — one example situation, and a way in.
class _ExampleChip extends StatelessWidget {
  const _ExampleChip({required this.label, required this.onTap});

  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      borderRadius: BorderRadius.circular(AppRadii.chip),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 7),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(AppRadii.chip),
            border: Border.all(color: AppColors.track),
          ),
          child: Text(
            label,
            style: AppText.translation.copyWith(
              fontSize: 12.5,
              fontWeight: FontWeight.w600,
              color: AppColors.inkBody,
            ),
          ),
        ),
      ),
    );
  }
}

/// «Готовые наборы · все 17 →» over a strip of real covers (кадры 19-2, 19-3).
///
/// The store used to be offered here as three topic WORDS in outline chips. Three words are a table
/// of contents, not an invitation: a photograph of an airport sells «Аэропорт» and the word
/// «Аэропорт» does not. In the evening the strip also cures the emptiness under a finished day —
/// the screen has said «закрыто» and then had nothing else to show.
///
/// [large] is the first day (кадр 19-3), where the window IS the main entrance and so gets the room:
/// two covers across instead of three and a half.
class _StoreShowcase extends StatelessWidget {
  const _StoreShowcase({
    super.key,
    required this.store,
    required this.onOpen,
    this.onOpenStore,
    this.large = false,
  });

  final HomeStore store;
  final void Function(HomeStoreItem item) onOpen;
  final VoidCallback? onOpenStore;
  final bool large;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    // 3.5 covers across at 390 pt in the strip; two across on the first day. The half at the edge is
    // what says «здесь есть ещё» — a strip that ends exactly at the fold reads as the whole shop.
    final width = large ? 171.0 : 96.0;
    final height = large ? 108.0 : 70.0;
    // Photo plus the caption under it: the gap, a title of up to two lines, and the meta. Fixed,
    // because a horizontal list needs a height and every tile must get the same one — a two-line
    // title is not a reason for a taller neighbour.
    final tile = height + (large ? 68.0 : 56.0);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          children: [
            Expanded(
              child: Text(l.homeStoreShowcaseTitle.toUpperCase(), style: AppText.sectionLabel),
            ),
            if (onOpenStore != null)
              MinTapHeight(
                onTap: () {
                  AppHaptics.light();
                  onOpenStore!();
                },
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      l.homeStoreShowcaseAll(store.count),
                      style: AppText.translation.copyWith(
                        fontSize: 12.5,
                        color: AppColors.secondary,
                      ),
                    ),
                    const SizedBox(width: 5),
                    const Icon(LucideIcons.arrowRight, size: 13, color: AppColors.secondary),
                  ],
                ),
              ),
          ],
        ),
        const SizedBox(height: AppSpacing.s8),
        SizedBox(
          height: tile,
          child: ListView.separated(
            scrollDirection: Axis.horizontal,
            padding: EdgeInsets.zero,
            itemCount: store.items.length,
            separatorBuilder: (_, _) => const SizedBox(width: AppSpacing.s8),
            itemBuilder: (_, i) => _StoreCoverTile(
              item: store.items[i],
              width: width,
              height: height,
              large: large,
              onTap: () => onOpen(store.items[i]),
            ),
          ),
        ),
      ],
    );
  }
}

/// One deck of the window: the photograph, and UNDER it the name and «16 слов · A2».
///
/// Under and not over. The caption was set over the lower third of the photo on a scrim first — a
/// reasonable way to build a cover, and not the one the frames draw: кадры 19-2 / 19-3 put ink type
/// on paper below the picture, which is what the rest of this product looks like. White type on an
/// unknown photograph is a second design language, and the screen has room for one.
///
/// A deck with NO photograph gets a paper plate carrying the initial of its name — not a grey box
/// with a picture icon. The «Drop an image» placeholders in the frame are a mock-up artefact; in the
/// app a deck without a cover must still look like a deck rather than like a failure to load.
class _StoreCoverTile extends StatelessWidget {
  const _StoreCoverTile({
    required this.item,
    required this.width,
    required this.height,
    required this.large,
    required this.onTap,
  });

  final HomeStoreItem item;
  final double width, height;
  final bool large;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final radius = BorderRadius.circular(large ? 18 : 14);
    final url = item.imageUrl;
    final hasPhoto = url != null && url.isNotEmpty;
    final meta = [
      l.storeWordsCount(item.termsCount),
      if (item.level != null && item.level!.trim().isNotEmpty) item.level!.trim(),
    ].join(' · ');

    final plate = DecoratedBox(
      decoration: BoxDecoration(color: AppColors.photoPlate, borderRadius: radius),
      child: Center(
        child: Text(
          _initial,
          style: AppText.stepTitle.copyWith(
            fontSize: large ? 34 : 24,
            color: AppColors.plateLabel,
          ),
        ),
      ),
    );

    return GestureDetector(
      onTap: onTap,
      child: SizedBox(
        width: width,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            SizedBox(
              width: width,
              height: height,
              child: ClipRRect(
                borderRadius: radius,
                child: hasPhoto
                    ? Image(
                        image: CachedNetworkImage(url),
                        fit: BoxFit.cover,
                        loadingBuilder: (_, child, progress) => progress == null ? child : plate,
                        errorBuilder: (_, _, _) => plate,
                      )
                    : plate,
              ),
            ),
            SizedBox(height: large ? 9 : 7),
            Text(
              item.title,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: AppText.collectionNameCard.copyWith(
                fontSize: large ? 16.5 : 13,
                height: 1.15,
              ),
            ),
            const SizedBox(height: 2),
            Text(
              meta,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: AppText.translation.copyWith(
                fontSize: large ? 11.5 : 10.5,
                color: AppColors.tertiary,
              ),
            ),
          ],
        ),
      ),
    );
  }

  /// The first letter of the deck's name, for a cover with no photograph.
  String get _initial {
    final trimmed = item.title.trim();

    return trimmed.isEmpty ? '·' : trimmed.substring(0, 1).toUpperCase();
  }
}

/// «или взять из 17 готовых →» — the store's quiet entrance on the morning screen (кадр 19-1).
///
/// A sentence and not a strip, deliberately: the morning screen's centre is the day waiting to be
/// started, and a row of shop covers under it is a shop in the doorway. The evening, which has
/// nothing left to start, gets the covers.
class _StoreLink extends StatelessWidget {
  const _StoreLink({super.key, required this.count, required this.onTap});

  final int count;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return MinTapHeight(
      onTap: () {
        AppHaptics.light();
        onTap();
      },
      child: Padding(
        padding: const EdgeInsets.only(left: 3),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              l.homeStoreLink(count),
              style: AppText.translation.copyWith(fontSize: 13, color: AppColors.secondary),
            ),
            const SizedBox(width: 6),
            const Icon(LucideIcons.arrowRight, size: 14, color: AppColors.secondary),
          ],
        ),
      ),
    );
  }
}

/// «+5 слов продвинулись · reluctant дошло до „написание"» — the evening card's reward line.
///
/// The rung is named HERE, from the same `ladderStep*` strings the word card uses, because the
/// server sends a number: a rung spelled «написание» inside a JSON payload is Russian copy shipped
/// by a server that also answers in English.
///
/// Without a nameable rung the line falls back to the count alone — «+5 слов продвинулись» is still
/// true and still worth saying; «дошло до „“» is not.
String _awardLine(AppLocalizations l, HomeDayAward award) {
  final rung = ladderRungFor(award.step);
  final promoted = l.homeAwardPromoted(award.promoted);
  if (rung == null || award.text.isEmpty) return promoted;

  return '$promoted · ${l.homeAwardExample(award.text, ladderRungLabel(l, rung))}';
}

/// THE DAY IS NOT KNOWN — and which of the two that is, is decided here rather than at build time.
///
/// The sync state is a [ValueNotifier], so «первый синк ещё идёт» has to be LISTENED to: read once
/// during build, a failed sync (syncing → offline, with no row written) would leave the placeholder
/// spinning for as long as the screen stayed open.
class _NoDay extends ConsumerWidget {
  const _NoDay({required this.cache});

  final HomePlanCache cache;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return ValueListenableBuilder<SyncState>(
      valueListenable: ref.watch(syncServiceProvider).state,
      builder: (context, state, _) {
        // A first sync still in flight is not «нет связи» — it is waiting, and it is drawn as
        // waiting. Only `missing`: an unreadable row will still be unreadable when this sync lands.
        if (cache == HomePlanCache.missing && state == SyncState.syncing) {
          return const _DayPlaceholder(key: HomeBlockKeys.loading);
        }

        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            _UnreachableCard(
              key: cache == HomePlanCache.unreadable
                  ? HomeBlockKeys.unreadable
                  : HomeBlockKeys.unreachable,
            ),
            const SizedBox(height: AppSpacing.sectionAiry),
            // The one door that does not need the day. It DOES need the server, and the card above
            // has just said the server is not answering — so it stays, quietly, instead of being
            // the whole page and pretending everything is fine.
            _GenerateCard(key: HomeBlockKeys.generate),
          ],
        );
      },
    );
  }
}

/// «Ещё не знаю». Deliberately not a skeleton of the session card: the day may turn out to be any
/// of четыре states, and a placeholder shaped like one of them is a promise the screen may break.
class _DayPlaceholder extends StatelessWidget {
  const _DayPlaceholder({super.key});

  @override
  Widget build(BuildContext context) {
    return const Padding(
      padding: EdgeInsets.symmetric(vertical: 64),
      child: Center(
        child: SizedBox(
          width: 22,
          height: 22,
          child: CircularProgressIndicator(strokeWidth: 2, color: AppColors.ink),
        ),
      ),
    );
  }
}

/// «Сервер не отвечает» — the day could not be fetched and nothing usable is cached.
///
/// Not an error dump and not a dead end: pull-to-refresh is the way out and the card says so, which
/// is the whole difference from the blank page this replaced.
class _UnreachableCard extends StatelessWidget {
  const _UnreachableCard({super.key});

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return Container(
      padding: const EdgeInsets.all(AppSpacing.s16),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(AppRadii.field),
        border: Border.all(color: AppColors.hairline),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(l.homeUnreachableTitle, style: AppText.stepTitle.copyWith(fontSize: 19)),
          const SizedBox(height: AppSpacing.s8),
          Text(
            l.homeUnreachableBody,
            style: AppText.translation.copyWith(fontSize: 12.5, color: AppColors.inkBody),
          ),
        ],
      ),
    );
  }
}

/// Quiet offline banner (кадр 9c): a hairline-outlined row with a ring dot. No colour, no warning
/// icon — being offline is normal; reviews keep working, and we say so.
class _OfflineBanner extends StatelessWidget {
  const _OfflineBanner();

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(AppRadii.field),
        border: Border.all(color: AppColors.hairline),
      ),
      child: Row(
        children: [
          Container(
            width: 9,
            height: 9,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              border: Border.all(color: AppColors.secondary, width: 1.5),
            ),
          ),
          const SizedBox(width: 9),
          Expanded(
            child: Text(
              l.homeOfflineBanner,
              style: AppText.translation.copyWith(fontSize: 12.5, color: AppColors.inkBody),
            ),
          ),
        ],
      ),
    );
  }
}
