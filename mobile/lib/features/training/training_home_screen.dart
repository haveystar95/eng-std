import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';
import 'package:eng_std/l10n/app_localizations.dart';

import '../../data/local/app_database.dart' show PoolWordRow;
import '../../data/models.dart';
import '../../data/pronouncer.dart';
import '../../data/providers.dart';
import '../collections/collection_detail_screen.dart';
import '../collections/generate_screen.dart';
import '../collections/my_words_screen.dart';
import '../home/streak.dart';
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
  static const inWork = Key('home-in-work');
  static const edge = Key('home-edge');
  static const hardest = Key('home-hardest');
  static const unfinished = Key('home-continue');
  static const generate = Key('home-generate');
  static const storeLink = Key('home-store-link');
  static const firstDay = Key('home-first-day');
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
    final plan = ref.watch(homePlanProvider).value;
    final streak = ref.watch(statsProvider).value?.streakDays ?? 0;
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
            children: _blocks(context, plan, streak: streak, online: online),
          ),
        ),
      ),
    );
  }

  /// The screen, block by block. A list rather than a column of `if`s so the guard test can ask the
  /// simple question it needs to ask: is this widget in the tree at all.
  List<Widget> _blocks(
    BuildContext context,
    HomePlan? plan, {
    required int streak,
    required bool online,
  }) {
    final l = AppLocalizations.of(context);
    final gap = const SizedBox(height: AppSpacing.sectionAiry);

    if (!online) {
      return [
        const _OfflineBanner(),
        gap,
        ..._blocks(context, plan, streak: streak, online: true),
      ];
    }

    // Nothing has ever synced (fresh install, still offline). The day is unknown — say nothing
    // about it rather than invent a zero, and keep the one door that works without it.
    if (plan == null) {
      return [_GenerateRow(key: HomeBlockKeys.generate, storeCount: 0, onOpenStore: widget.onOpenStore)];
    }

    if (plan.state == HomeStateKind.empty) {
      return [
        Text(
          l.homeFirstDayTitle,
          key: HomeBlockKeys.firstDay,
          style: AppText.stepTitle.copyWith(fontSize: 26),
        ),
        const SizedBox(height: AppSpacing.s16),
        _FirstDayReadyCard(store: plan.store, onOpen: widget.onOpenStore),
        const SizedBox(height: AppSpacing.s12),
        const _FirstDayOwnCard(),
      ];
    }

    final session = plan.session;
    return [
      _DayHeader(key: HomeBlockKeys.header, streak: streak),
      gap,
      if (plan.state == HomeStateKind.plan)
        _SessionCard(key: HomeBlockKeys.session, session: session, onStart: () => _startDay(plan))
      else if (plan.state == HomeStateKind.done)
        _DoneCard(
          key: HomeBlockKeys.done,
          today: plan.today,
          session: session,
          nextReview: plan.nextReview,
          unfinished: plan.unfinished,
          onExtra: () => _openTriage(plan.unfinished!.collectionId, plan.unfinished!.title),
        )
      else
        _IdleCard(
          key: HomeBlockKeys.idle,
          inWork: plan.inWork,
          nextReview: plan.nextReview,
          onTakeNew: () => _openSession(learn: true),
        ),
      gap,
      _InWorkRow(
        key: HomeBlockKeys.inWork,
        inWork: plan.inWork,
        queueStands: plan.state == HomeStateKind.idle,
        onTap: () => Navigator.of(
          context,
        ).push(MaterialPageRoute(builder: (_) => const MyWordsScreen())),
      ),
      if (plan.edge.isNotEmpty && plan.state != HomeStateKind.done) ...[
        gap,
        _EdgeSection(key: HomeBlockKeys.edge, terms: plan.edge, onTap: _openWordCard),
      ],
      if (plan.hardest.isNotEmpty && plan.state == HomeStateKind.done) ...[
        gap,
        _HardestSection(key: HomeBlockKeys.hardest, terms: plan.hardest, onTap: _openWordCard),
      ],
      if (plan.unfinished != null && plan.state == HomeStateKind.plan) ...[
        gap,
        _ContinueCard(
          key: HomeBlockKeys.unfinished,
          unfinished: plan.unfinished!,
          onTap: () => Navigator.of(context).push(
            MaterialPageRoute(
              builder: (_) => CollectionDetailScreen(
                collectionId: plan.unfinished!.collectionId,
                title: plan.unfinished!.title,
              ),
            ),
          ),
        ),
      ],
      gap,
      _GenerateRow(
        key: HomeBlockKeys.generate,
        storeCount: plan.store.count,
        onOpenStore: widget.onOpenStore,
      ),
    ];
  }

  /// «Начать» — the day's one button.
  ///
  /// The routing keeps the existing priority (study first, then the swipe pass) because the two are
  /// two different mechanics and this наряд changes neither: study cards are dealt by the server,
  /// swipes are a local deck over a collection. The composition line above the button says what the
  /// day holds; the button opens the part of it that comes first.
  void _startDay(HomePlan plan) {
    final session = plan.session;
    if (session.repeat + session.newTerms > 0) {
      _openSession();
      return;
    }
    final id = session.triageCollectionId;
    if (id != null) _openTriage(id, session.triageCollectionTitle ?? '');
  }

  void _openSession({bool learn = false}) {
    AppHaptics.light();
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => SessionScreen(
          title: AppLocalizations.of(context).homeSessionTitle,
          learn: learn,
        ),
      ),
    );
  }

  void _openTriage(String collectionId, String title) {
    AppHaptics.light();
    Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => TriageScreen(collectionId: collectionId, title: title)),
    );
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
    final speakLang =
        pairs[termId]?.learned ??
        ref.read(authControllerProvider).value?.profile?.targetLanguage ??
        'en';
    if (!mounted) return;
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => WordCardScreen(
          subject: WordCardSubject.fromWord(word),
          mode: WordCardMode.folder,
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
/// The composition is drawn twice: as a bar whose three segments are in proportion, and as three
/// labelled lines. A part whose number is 0 is drawn in NEITHER — a zero-width segment and a
/// «0 новых» line are the same lie in two typefaces.
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
      if (session.triage > 0)
        (count: session.triage, opacity: 0.28, label: l.homeSessionPartTriage(session.triage)),
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
    required this.nextReview,
    required this.unfinished,
    required this.onExtra,
  });

  final HomeToday? today;
  final HomeSession session;
  final HomeNextReview? nextReview;
  final HomeContinue? unfinished;
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
    final lines = <String>[
      if (nextReview != null) _nextReviewLine(context, l, nextReview!),
      if (unfinished != null)
        l.homeExtraFromCollection(unfinished!.remaining, unfinished!.title),
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
            decoration: BoxDecoration(
              color: AppColors.ink,
              borderRadius: BorderRadius.circular(2),
            ),
          ),
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
          if (unfinished != null) ...[
            const SizedBox(height: AppSpacing.s16),
            QuietButton(label: l.homeExtraButton(unfinished!.remaining), onPressed: onExtra),
          ],
        ],
      ),
    );
  }
}

/// «Всё повторено» (кадр 17d): nothing is due and nothing was answered — the schedule is simply
/// ahead. One button, and it is the one thing that would move the day: take new words.
class _IdleCard extends StatelessWidget {
  const _IdleCard({super.key, required this.inWork, required this.nextReview, required this.onTakeNew});

  final HomeInWork inWork;
  final HomeNextReview? nextReview;
  final VoidCallback onTakeNew;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final canTakeNew = inWork.waiting > 0 && inWork.newRemaining > 0;
    final lines = <String>[
      if (nextReview != null) _nextReviewLine(context, l, nextReview!),
      if (canTakeNew) l.homeIdleQueueStalled,
    ];

    return PaperCard(
      radius: AppRadii.card,
      padding: const EdgeInsets.all(20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(l.homeDoneTitle.toUpperCase(), style: AppText.sectionLabel),
          const SizedBox(height: 9),
          Text(l.homeIdleTitle, style: AppText.counterLarge.copyWith(fontSize: 28)),
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

/// «В работе — 41 слово · 20 ждут очереди · при 20 в день новым до очереди ~2 дня».
///
/// The размер ящика — the question the product research found unanswered: how much have I taken on,
/// and when does the app get to it. Present in every state but the first day, and a tap leads to
/// «Мои слова», which is the list behind the number.
class _InWorkRow extends StatelessWidget {
  const _InWorkRow({super.key, required this.inWork, required this.queueStands, required this.onTap});

  final HomeInWork inWork;

  /// State Б (кадр 17d): the day's quota is untouched, so the honest second half of the line is
  /// «возьмёте N сейчас — очередь двинется сегодня» rather than an arithmetic of days.
  final bool queueStands;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final parts = <String>[
      if (inWork.waiting > 0) l.homeInWorkWaiting(inWork.waiting),
      if (inWork.waiting > 0 && queueStands && inWork.newRemaining > 0)
        l.homeInWorkQueueStands(
          inWork.waiting < inWork.newRemaining ? inWork.waiting : inWork.newRemaining,
        )
      else if (inWork.daysUntilQueue != null)
        l.homeInWorkPace(inWork.perDay, inWork.daysUntilQueue!),
    ];

    return PaperCard(
      radius: AppRadii.button,
      padding: const EdgeInsets.symmetric(horizontal: AppSpacing.s16, vertical: 13),
      onTap: onTap,
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  l.homeInWorkTitle(inWork.total),
                  style: AppText.translation.copyWith(
                    fontSize: 14.5,
                    fontWeight: FontWeight.w700,
                    color: AppColors.ink,
                  ),
                ),
                if (parts.isNotEmpty) ...[
                  const SizedBox(height: 4),
                  Text(
                    parts.join(' · '),
                    style: AppText.translation.copyWith(
                      fontSize: 12.5,
                      height: 1.35,
                      color: AppColors.secondary,
                    ),
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(width: AppSpacing.s12),
          const Icon(LucideIcons.chevronRight, size: 18, color: AppColors.tertiary),
        ],
      ),
    );
  }
}

/// «На грани забывания» (кадр 17a) — 2–3 of the learner's OWN words with the day they fall due.
/// The old «слово дня» was a random term with no relation to their progress; this is the same slot
/// answering a question they actually have.
class _EdgeSection extends StatelessWidget {
  const _EdgeSection({super.key, required this.terms, required this.onTap});

  final List<HomeEdgeTerm> terms;
  final void Function(String termId) onTap;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return _TermSection(
      title: l.homeEdgeTitle,
      count: terms.length,
      rows: [
        for (final t in terms)
          _TermRowData(
            termId: t.termId,
            text: t.text,
            translation: t.translation,
            trailing: t.inDays == 1 ? l.homeEdgeTomorrow : l.homeEdgeInDays(t.inDays),
          ),
      ],
      onTap: onTap,
    );
  }
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
                  Text(data.text, style: AppText.termInList, maxLines: 1, overflow: TextOverflow.ellipsis),
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

/// «Продолжить „Ветклинику“ — 4 из 16 слов · брошено 5 дней назад» (кадр 17a). The one thing on the
/// screen that would otherwise never be opened again.
class _ContinueCard extends StatelessWidget {
  const _ContinueCard({super.key, required this.unfinished, required this.onTap});

  final HomeContinue unfinished;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final subtitle = <String>[
      l.homeCollectionProgress(unfinished.done, unfinished.total),
      if (unfinished.abandonedDays != null && unfinished.abandonedDays! > 0)
        l.homeContinueAbandoned(unfinished.abandonedDays!),
    ].join(' · ');

    return PaperCard(
      radius: AppRadii.button,
      padding: const EdgeInsets.symmetric(horizontal: AppSpacing.s16, vertical: AppSpacing.s12),
      onTap: onTap,
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  l.homeContinueLabel.toUpperCase(),
                  style: AppText.sectionLabel.copyWith(color: AppColors.tertiary),
                ),
                const SizedBox(height: 4),
                Text(
                  unfinished.title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: AppText.collectionNameCard,
                ),
                const SizedBox(height: 3),
                Text(
                  subtitle,
                  style: AppText.translation.copyWith(
                    fontSize: 12.5,
                    color: AppColors.secondary,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: AppSpacing.s12),
          const Icon(LucideIcons.chevronRight, size: 18, color: AppColors.tertiary),
        ],
      ),
    );
  }
}

/// The generation entrance, folded down to one row, with the store under it as a quiet line
/// (кадры 17a/17b/17d). The big form is gone: it was the visual centre of a screen whose centre is
/// now the day.
///
/// The row never generates anything itself — a tap opens the create screen with the topic carried.
class _GenerateRow extends StatelessWidget {
  const _GenerateRow({super.key, required this.storeCount, this.onOpenStore});

  final int storeCount;
  final VoidCallback? onOpenStore;

  void _open(BuildContext context, {bool startVoice = false}) {
    AppHaptics.light();
    Navigator.of(
      context,
    ).push(MaterialPageRoute(builder: (_) => GenerateScreen(startVoice: startVoice)));
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        GestureDetector(
          onTap: () => _open(context),
          child: Container(
            height: 52,
            padding: const EdgeInsets.only(left: 15, right: 6),
            decoration: BoxDecoration(
              color: AppColors.field,
              borderRadius: BorderRadius.circular(AppRadii.field),
              border: Border.all(color: AppColors.hairline),
            ),
            child: Row(
              children: [
                Expanded(
                  child: Text(
                    l.homeGenerateRow,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: AppText.translation.copyWith(
                      fontSize: 14.5,
                      color: AppColors.tertiary,
                    ),
                  ),
                ),
                InkResponse(
                  onTap: () => _open(context, startVoice: true),
                  radius: 22,
                  child: const SizedBox(
                    width: 44,
                    height: 44,
                    child: Icon(LucideIcons.mic, size: 20, color: AppColors.secondary),
                  ),
                ),
                GestureDetector(
                  onTap: () => _open(context),
                  child: Container(
                    width: 44,
                    height: 40,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      color: AppColors.ink,
                      borderRadius: BorderRadius.circular(AppRadii.small),
                    ),
                    child: const Icon(LucideIcons.arrowRight, size: 18, color: AppColors.paper),
                  ),
                ),
              ],
            ),
          ),
        ),
        // The store's entrance from the main screen: generation is not the only way to get words,
        // and before this line the ready-made sets were reachable only from another tab.
        if (storeCount > 0 && onOpenStore != null) ...[
          const SizedBox(height: 9),
          MinTapHeight(
            key: HomeBlockKeys.storeLink,
            onTap: () {
              AppHaptics.light();
              onOpenStore!();
            },
            child: Padding(
              padding: const EdgeInsets.only(left: 3),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    l.homeStoreLink(storeCount),
                    style: AppText.translation.copyWith(
                      fontSize: 13,
                      color: AppColors.secondary,
                    ),
                  ),
                  const SizedBox(width: 6),
                  const Icon(LucideIcons.arrowRight, size: 14, color: AppColors.secondary),
                ],
              ),
            ),
          ),
        ],
      ],
    );
  }
}

/// «Взять готовый набор (17 тем)» (кадр 17c) — the first of the two equal doors on the first day,
/// with three real topics under it so «17 тем» is not an abstraction.
class _FirstDayReadyCard extends StatelessWidget {
  const _FirstDayReadyCard({required this.store, this.onOpen});

  final HomeStore store;
  final VoidCallback? onOpen;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return PaperCard(
      radius: AppRadii.card,
      padding: const EdgeInsets.all(20),
      onTap: onOpen == null
          ? null
          : () {
              AppHaptics.light();
              onOpen!();
            },
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Text(
                  l.homeFirstDayReadyTitle(store.count),
                  style: AppText.stepTitle.copyWith(fontSize: 21),
                ),
              ),
              const SizedBox(width: AppSpacing.s12),
              Container(
                width: 38,
                height: 38,
                alignment: Alignment.center,
                decoration: const BoxDecoration(shape: BoxShape.circle, color: AppColors.ink),
                child: const Icon(LucideIcons.arrowRight, size: 17, color: AppColors.paper),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.s8),
          Text(
            l.homeFirstDayReadyHint,
            style: AppText.translation.copyWith(
              fontSize: 13.5,
              height: 1.45,
              color: AppColors.secondary,
            ),
          ),
          if (store.topics.isNotEmpty) ...[
            const SizedBox(height: AppSpacing.s16),
            Wrap(
              spacing: AppSpacing.s8,
              runSpacing: AppSpacing.s8,
              children: [for (final topic in store.topics) _TopicChip(label: topic)],
            ),
          ],
        ],
      ),
    );
  }
}

/// «Собрать свою по описанию» (кадр 17c) — the second door, with the field expanded because on the
/// first day there is nothing else competing for the screen.
class _FirstDayOwnCard extends StatelessWidget {
  const _FirstDayOwnCard();

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    void open() {
      AppHaptics.light();
      Navigator.of(context).push(MaterialPageRoute(builder: (_) => const GenerateScreen()));
    }

    return PaperCard(
      radius: AppRadii.card,
      padding: const EdgeInsets.all(20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(l.homeFirstDayOwnTitle, style: AppText.stepTitle.copyWith(fontSize: 21)),
          const SizedBox(height: AppSpacing.s8),
          Text(
            l.homeFirstDayOwnHint,
            style: AppText.translation.copyWith(
              fontSize: 13.5,
              height: 1.45,
              color: AppColors.secondary,
            ),
          ),
          const SizedBox(height: AppSpacing.s16),
          GestureDetector(
            onTap: open,
            child: Container(
              height: 52,
              padding: const EdgeInsets.only(left: 15, right: 6),
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
                        fontSize: 14.5,
                        color: AppColors.tertiary,
                      ),
                    ),
                  ),
                  Container(
                    width: 44,
                    height: 40,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      color: AppColors.ink,
                      borderRadius: BorderRadius.circular(AppRadii.small),
                    ),
                    child: const Icon(LucideIcons.arrowRight, size: 18, color: AppColors.paper),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// An outline topic chip under the ready-made-sets card. Not tappable on purpose: it is a PREVIEW
/// of what the store holds, and the card itself is the door.
class _TopicChip extends StatelessWidget {
  const _TopicChip({required this.label});
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 13, vertical: 8),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(AppRadii.chip),
        border: Border.all(color: AppColors.hairline),
      ),
      child: Text(
        label,
        style: AppText.translation.copyWith(
          fontSize: 12.5,
          fontWeight: FontWeight.w600,
          color: AppColors.inkBody,
        ),
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
