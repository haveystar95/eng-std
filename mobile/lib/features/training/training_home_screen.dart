import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';
import 'package:eng_std/l10n/app_localizations.dart';

import '../../data/local/app_database.dart' show PoolWordRow;
import '../../data/local/cached_image_provider.dart';
import '../../data/local/sync_service.dart' show SyncState;
import '../../data/models.dart';
import '../../data/pronouncer.dart';
import '../../data/providers.dart';
import '../../data/word_status.dart' show ladderRungFor, ladderRungLabel;
import '../collections/generate_screen.dart';
import '../collections/my_words_screen.dart';
import '../collections/store_view.dart' show showStorePreview;
import '../home/streak.dart';
import '../search/search_pair.dart' show LearningPair;
import '../word_card/word_card_screen.dart';
import '../word_card/word_card_subject.dart';
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

  /// «Выучено 146 · за неделю 23 · в работе 41» — three numbers on the morning screen (кадр 19-1),
  /// one line in the evening (кадр 19-2). One key, because it is one block wearing two shapes:
  /// morning numbers are a decision, evening numbers are a summary.
  static const stats = Key('home-stats');

  /// «Завтра выпадет 14 слов →» (кадры 19-1, 19-2).
  static const tomorrow = Key('home-tomorrow');

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
              data: (view) =>
                  _blocks(context, view, streak: streak, learned: learned, online: online),
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
    required bool online,
  }) {
    final l = AppLocalizations.of(context);
    final gap = const SizedBox(height: AppSpacing.sectionAiry);

    if (!online) {
      return [
        const _OfflineBanner(),
        gap,
        ..._blocks(context, view, streak: streak, learned: learned, online: true),
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
                // [место слово-вызова — DAILY-1] Nothing is drawn here yet, and the column
                // survives it — the Spacer below simply gives back less room.
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
      // The evening names what the day got wrong straight under what it closed — «Сегодня закрыто»
      // and «Далось труднее всего» are one thought, and the summary comes between them nowhere.
      if (evening && plan.hardest.isNotEmpty) ...[
        gap,
        _HardestSection(key: HomeBlockKeys.hardest, terms: plan.hardest, onTap: _openWordCard),
      ],
      if (stats.isNotEmpty) ...[
        gap,
        // Three plates in the morning, one line in the evening. Same three numbers: in the morning
        // they are part of the decision to start, and by the evening they are a receipt.
        evening
            ? _StatsLine(key: HomeBlockKeys.stats, cells: stats, onTap: _openMyWords)
            : _StatsTile(key: HomeBlockKeys.stats, cells: stats, onTap: _openMyWords),
      ],
      // [место слово-вызова — DAILY-1]
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
    if (learned > 0) _StatCell(learned, l.homeStatLearned, l.homeStatLearnedInline(learned)),
    if (plan.learnedWeek != null)
      _StatCell(plan.learnedWeek!, l.homeStatWeek, l.homeStatWeekInline(plan.learnedWeek!)),
    if (plan.inWork.total > 0)
      _StatCell(plan.inWork.total, l.homeStatInWork, l.homeStatInWorkInline(plan.inWork.total)),
  ];

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

  /// A word from «На грани забывания» / «Далось труднее всего» opens THE word card — the same one
  /// «Мои слова» opens, because a word has one card wherever it is met.
  ///
  /// The row is looked up in the local pool mirror: the plan carries the term's text so the section
  /// can render offline, but the card wants the whole word (photo, examples, rung), and that lives
  /// in the mirror already.
  Future<void> _openWordCard(String termId) async {
    AppHaptics.light();
    final pool = ref.read(poolProvider).value ?? const <PoolWordRow>[];
    final row = pool.where((r) => r.term.id == termId).firstOrNull;
    if (row == null) return; // the mirror has not caught up — better nothing than a half card
    final word = poolWordToWord(row);
    final pairs = await ref.read(appDatabaseProvider).pairByTerms([termId]);
    final pair = pairs[termId];
    final speakLang =
        pair?.learned ?? ref.read(authControllerProvider).value?.profile?.targetLanguage ?? 'en';
    if (!mounted) return;
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => WordCardScreen(
          subject: WordCardSubject.fromWord(word),
          mode: WordCardMode.folder,
          // Which shelves «Добавить в коллекцию» may offer — one collection is one pair forever
          // (DECISIONS п. 81), so a folder of another pair is one the server refuses, not a worse
          // home. Same resolver the voice above reads.
          pair: pair == null ? null : LearningPair(learned: pair.learned, support: pair.support),
          onSpeak: () {
            AppHaptics.light();
            (_pronouncer ??= Pronouncer()).speak(word, targetLang: speakLang);
          },
          onUnenroll: () => ref.read(poolSyncProvider).unenroll(termId),
        ),
      ),
    );
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

/// «Сессия на сегодня: 32 слова · ≈ 9 минут» (кадр 17a) — the one dark surface on the screen, and
/// therefore the one primary action.
///
/// It counts the POOL and nothing else: repeats and the new words the day's quota allows. Words
/// sitting unsorted in a collection are catalogue, not queue — they are offered further down, once
/// the repeats are done — because counting them here means every set the learner adds arrives as
/// work they owe, and a shelf of five sets announces a two-hundred-word day to someone who takes
/// thirty.
///
/// The composition is drawn twice: as a bar whose segments are in proportion, and as labelled
/// lines. A part whose number is 0 is drawn in NEITHER — a zero-width segment and a «0 новых» line
/// are the same lie in two typefaces.
class _SessionCard extends StatelessWidget {
  const _SessionCard({super.key, required this.session, required this.onStart});

  final HomeSession session;
  final VoidCallback onStart;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final paper = AppColors.paper;
    final parts = <({int count, double opacity, String label})>[
      if (session.repeat > 0)
        (count: session.repeat, opacity: 1, label: l.homeSessionPartRepeat(session.repeat)),
      if (session.newTerms > 0)
        (count: session.newTerms, opacity: 0.55, label: l.homeSessionPartNew(session.newTerms)),
    ];

    return Container(
      decoration: BoxDecoration(
        color: AppColors.ink,
        borderRadius: BorderRadius.circular(AppRadii.card),
      ),
      padding: const EdgeInsets.fromLTRB(20, 18, 20, 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            l.homeSessionCardTitle.toUpperCase(),
            style: AppText.sectionLabel.copyWith(color: paper.withValues(alpha: 0.6)),
          ),
          const SizedBox(height: AppSpacing.s8),
          Row(
            crossAxisAlignment: CrossAxisAlignment.baseline,
            textBaseline: TextBaseline.alphabetic,
            children: [
              Text(
                l.homeSessionCardWords(session.total),
                style: AppText.counterLarge.copyWith(color: paper),
              ),
              const SizedBox(width: 10),
              // How many CARDS those words are, beside the minutes they cost. The headline is the
              // work; this is the length, and it is the number the session's own counter counts up
              // to — a card promising «32 слова» that ran to «14 / 61» is what put it here (Ч.3).
              if (session.cards > 0)
                Text(
                  l.sessionSizeCards(session.cards),
                  style: AppText.translation.copyWith(
                    fontSize: 15,
                    color: paper.withValues(alpha: 0.66),
                  ),
                ),
              if (session.cards > 0 && session.estimatedMinutes != null)
                Text(
                  ' · ',
                  style: AppText.translation.copyWith(
                    fontSize: 15,
                    color: paper.withValues(alpha: 0.4),
                  ),
                ),
              if (session.estimatedMinutes != null)
                Text(
                  l.homeSessionCardMinutes(session.estimatedMinutes!),
                  style: AppText.translation.copyWith(
                    fontSize: 15,
                    color: paper.withValues(alpha: 0.66),
                  ),
                ),
            ],
          ),
          const SizedBox(height: 13),
          Row(
            children: [
              for (var i = 0; i < parts.length; i++) ...[
                if (i > 0) const SizedBox(width: 3),
                Expanded(
                  flex: parts[i].count,
                  child: Container(
                    height: 4,
                    decoration: BoxDecoration(
                      color: paper.withValues(alpha: parts[i].opacity),
                      borderRadius: BorderRadius.circular(2),
                    ),
                  ),
                ),
              ],
            ],
          ),
          const SizedBox(height: 10),
          Wrap(
            spacing: 13,
            runSpacing: 6,
            children: [
              for (final part in parts)
                Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Container(
                      width: 7,
                      height: 7,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        color: paper.withValues(alpha: part.opacity),
                      ),
                    ),
                    const SizedBox(width: 6),
                    Text(
                      part.label,
                      style: AppText.translation.copyWith(
                        fontSize: 12.5,
                        color: paper.withValues(alpha: part.opacity < 1 ? 0.8 : 1),
                      ),
                    ),
                  ],
                ),
            ],
          ),
          const SizedBox(height: 15),
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
    required this.onExtra,
  });

  final HomeToday? today;
  final HomeSession session;

  /// «+5 слов продвинулись · reluctant дошло до „написание"» — null on a day that moved nothing,
  /// and then the line is not drawn at all.
  final HomeDayAward? award;
  final VoidCallback onExtra;

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
    final canSort = session.triage > 0 && session.triageCollectionId != null;
    // «Следующий повтор — завтра, 14 слов» is gone from here: «Завтра выпадет N слов →» says the
    // same thing further down and is a DOOR rather than a sentence. Two of them on one screen is
    // the screen telling the learner the same fact twice in two voices.
    final lines = <String>[
      if (canSort) l.homeExtraFromCollection(session.triage, session.triageCollectionTitle ?? ''),
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
                // Full ink, where the lines under it are secondary: the palette has no accent to
                // spend (paper/ink, no brand colour), so the reward is emphasised by WEIGHT of tone
                // rather than by hue. It is the one thing on this card that is news, not arithmetic.
                color: AppColors.ink,
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
          if (canSort) ...[
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

/// «Далось труднее всего» (кадр 17b) — the evening's occupant of the same slot: what the run that
/// just finished actually got wrong.
class _HardestSection extends StatelessWidget {
  const _HardestSection({super.key, required this.terms, required this.onTap});

  final List<HomeHardTerm> terms;
  final void Function(String termId) onTap;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return _TermSection(
      title: l.homeHardestTitle,
      count: terms.length,
      rows: [
        for (final t in terms)
          _TermRowData(
            termId: t.termId,
            text: t.text,
            translation: t.translation,
            trailing: l.homeHardestErrors(t.errors),
          ),
      ],
      onTap: onTap,
    );
  }
}

class _TermRowData {
  const _TermRowData({
    required this.termId,
    required this.text,
    required this.trailing,
    this.translation,
  });

  final String termId, text, trailing;
  final String? translation;
}

/// A labelled list of words on one raised sheet — the shape both «На грани» and «Труднее всего»
/// take, so the two sections cannot drift apart typographically.
class _TermSection extends StatelessWidget {
  const _TermSection({
    required this.title,
    required this.count,
    required this.rows,
    required this.onTap,
  });

  final String title;
  final int count;
  final List<_TermRowData> rows;
  final void Function(String termId) onTap;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          children: [
            Expanded(child: Text(title.toUpperCase(), style: AppText.sectionLabel)),
            Text(
              l.homeSectionCount(count),
              style: AppText.translation.copyWith(fontSize: 12.5, color: AppColors.tertiary),
            ),
          ],
        ),
        const SizedBox(height: AppSpacing.s8),
        PaperCard(
          radius: 22,
          padding: const EdgeInsets.symmetric(horizontal: AppSpacing.s16),
          child: Column(
            children: [
              for (var i = 0; i < rows.length; i++)
                _TermRow(
                  data: rows[i],
                  divided: i < rows.length - 1,
                  onTap: () => onTap(rows[i].termId),
                ),
            ],
          ),
        ),
      ],
    );
  }
}

class _TermRow extends StatelessWidget {
  const _TermRow({required this.data, required this.divided, required this.onTap});

  final _TermRowData data;
  final bool divided;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: AppSpacing.wordRowPadV),
        decoration: divided
            ? const BoxDecoration(
                border: Border(bottom: BorderSide(color: AppColors.dividerFaint)),
              )
            : null,
        child: Row(
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    data.text,
                    style: AppText.termInList,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  if (data.translation != null && data.translation!.isNotEmpty) ...[
                    const SizedBox(height: 3),
                    Text(
                      data.translation!,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: AppText.translation.copyWith(
                        fontSize: 12.5,
                        color: AppColors.secondary,
                      ),
                    ),
                  ],
                ],
              ),
            ),
            const SizedBox(width: AppSpacing.s12),
            Text(
              data.trailing,
              style: AppText.translation.copyWith(fontSize: 12.5, color: AppColors.secondary),
            ),
          ],
        ),
      ),
    );
  }
}

/// One number of the statistics block, with both the shapes it is said in.
///
/// [label] is the plate's caption on the morning screen («ВЫУЧЕНО» under 146); [inline] is the whole
/// segment of the evening's single line («Выучено 151»). Built together so the two cannot come to
/// name the same number differently.
class _StatCell {
  const _StatCell(this.value, this.label, this.inline);
  final int value;
  final String label, inline;
}

/// «146 Выучено · 23 За неделю · 41 В работе» (кадр 19-1) — the morning's three plates.
///
/// The block answers «сколько уже сделано», which is the second of the three questions the screen
/// exists for, and it sits directly under the answer to the first. The week of dots is deliberately
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

/// «Выучено 151 · за неделю 28 · в работе 41» (кадр 19-2) — the same three numbers, in the evening.
///
/// One line rather than three plates, and that is the whole design of the evening screen: in the
/// morning these numbers are part of a decision, and by the time the day is closed they are a
/// receipt. Giving a receipt the weight of a decision is how a finished day starts asking for more.
class _StatsLine extends StatelessWidget {
  const _StatsLine({super.key, required this.cells, required this.onTap});

  final List<_StatCell> cells;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return MinTapHeight(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 6),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            for (var i = 0; i < cells.length; i++)
              Flexible(
                child: Text(
                  cells[i].inline,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: AppText.translation.copyWith(
                    fontSize: 13,
                    // The first segment leads and the rest follow it — the line is one sentence,
                    // not three labels of equal weight.
                    color: i == 0 ? AppColors.inkBody : AppColors.secondary,
                  ),
                ),
              ),
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

    return PaperCard(
      radius: 24,
      padding: const EdgeInsets.all(AppSpacing.s16),
      onTap: withField ? null : () => _open(context),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              // The spark, on an ink plate. The frame draws a brass gradient here; the palette has
              // no brass and no gradients (paper/ink, tokens.html), and where the token list and a
              // frame disagree the token list wins.
              Container(
                width: 36,
                height: 36,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: AppColors.ink,
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
                const Icon(LucideIcons.arrowRight, size: 16, color: AppColors.secondary),
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
          height: height,
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

/// One cover: the photograph, with the name and «16 слов · A2» over its lower third.
///
/// Over rather than under, and that is the point of the strip — the caption belongs to the picture,
/// so the eye reads one object per deck instead of a picture and a line about it. The scrim is the
/// palette's own ([AppColors.scrim] is ink at .42), because white type on an unknown photograph is
/// legible only if something guarantees the ground under it.
///
/// A deck with no cover gets the paper plate and the SAME layout — ink type instead of white — so a
/// strip of mixed decks does not change shape halfway along.
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
    );

    return GestureDetector(
      onTap: onTap,
      child: SizedBox(
        width: width,
        height: height,
        child: ClipRRect(
          borderRadius: radius,
          child: Stack(
            fit: StackFit.expand,
            children: [
              if (hasPhoto)
                Image(
                  image: CachedNetworkImage(url),
                  fit: BoxFit.cover,
                  loadingBuilder: (_, child, progress) => progress == null ? child : plate,
                  errorBuilder: (_, _, _) => plate,
                )
              else
                plate,
              if (hasPhoto)
                Positioned(
                  left: 0,
                  right: 0,
                  bottom: 0,
                  height: height * 0.72,
                  child: const DecoratedBox(
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        begin: Alignment.topCenter,
                        end: Alignment.bottomCenter,
                        colors: [Colors.transparent, AppColors.scrim, AppColors.ink],
                      ),
                    ),
                  ),
                ),
              Positioned(
                left: large ? 12 : 6,
                right: large ? 12 : 6,
                bottom: large ? 10 : 6,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      item.title,
                      // TWO LINES even in the narrow strip. The frame's mock decks are called
                      // «Аэропорт»; the catalogue's are called «Знакомство и small talk», and on one
                      // line at 96 pt that came out as «Знакомст…» — a cover whose caption names no
                      // deck. Two lines and the meta below still sit inside the gradient.
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: AppText.collectionNameCard.copyWith(
                        fontSize: large ? 16.5 : 12.5,
                        height: 1.1,
                        color: hasPhoto ? AppColors.paper : AppColors.ink,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      meta,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: AppText.translation.copyWith(
                        // The level is the whole point of this line, so it must not be what gets
                        // cut: «20 слов · A2–B1» has to fit the cover's width, and at 10.5 it did
                        // not — the strip printed «20 слов · A2…».
                        fontSize: large ? 11.5 : 9.5,
                        color: hasPhoto
                            ? AppColors.paper.withValues(alpha: 0.82)
                            : AppColors.secondary,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
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
