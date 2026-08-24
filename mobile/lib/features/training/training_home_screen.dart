import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';
import 'package:eng_std/l10n/app_localizations.dart';

import '../../data/models.dart';
import '../../data/providers.dart';
import '../collections/generate_screen.dart';
import '../collections/my_words_screen.dart';
import '../home/home_cta.dart';
import '../home/home_providers.dart';
import '../home/limit_reached_card.dart';
import '../home/streak.dart';
import 'session_screen.dart';
import 'collections_strip.dart';
import 'triage_screen.dart';

/// «Главная» (кадр 2.1). Everything reads the local DB — the screen renders in
/// airplane mode: daily goal + streak, the state-dependent primary action, the
/// generation card, «Слово дня» (client-side), and the collections strip. The
/// new-user state falls out of the same tree: no collections → no CTA, no word,
/// no strip, and the generation card leads.
class TrainingHomeScreen extends ConsumerWidget {
  const TrainingHomeScreen({super.key, this.onOpenCollections});

  /// Switches the shell to the Collections tab («Все»).
  final VoidCallback? onOpenCollections;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final stats = ref.watch(statsProvider).value;
    final collections = ref.watch(collectionsProvider).value ?? const <WordCollection>[];
    final progress =
        ref.watch(collectionsProgressProvider).value ?? const <String, CollectionProgress>{};
    final cta = ref.watch(homeCtaProvider);
    final word = ref.watch(wordOfDayProvider).value;
    final online = ref.watch(connectivityProvider).value ?? true;

    final reviewsToday = stats?.reviewsTotal ?? 0;
    final streak = stats?.streakDays ?? 0;
    // The daily goal, from the ONE counter every screen showing it reads (QA-BUG-2): new words
    // taken into the pool today, against the day's new-word target.
    final ring = ref.watch(dailyGoalProvider);

    // 9b «всё повторено»: the daily goal is met and there's nothing due / learnable / to triage
    // (cta == none) while words already exist. Free practice is NOT offered here — it lives only on
    // the collection screen — so the card just affirms the goal and points at a new collection.
    // «Met» is asked of the goal counter, not of today's answers: the goal is new words, and a long
    // repeat session used to close it without a single new word in it.
    final allDone =
        cta.kind == HomeCtaKind.none &&
        collections.isNotEmpty &&
        ring.goal > 0 &&
        ring.done >= ring.goal;

    final bottomInset =
        AppTabBarMetrics.height +
        AppTabBarMetrics.bottomInset +
        MediaQuery.viewPaddingOf(context).bottom +
        AppSpacing.s8;

    // Dark status-bar glyphs on the paper background. An AnnotatedRegion in the
    // tree is authoritative — it overrides the (still dark) global theme's
    // default, which the one-shot SystemChrome call in main() can't hold.
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.dark,
      child: SafeArea(
        bottom: false,
        child: RefreshIndicator(
          color: AppColors.ink,
          backgroundColor: AppColors.surfaceRaised,
          onRefresh: () async {
            // Full resync (authoritative snapshot) so pull-to-refresh also reaps ghost
            // collections removed server-side without a tombstone.
            await ref.read(syncServiceProvider).resync();
            ref.invalidate(dueCardsProvider);
          },
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: EdgeInsets.fromLTRB(0, AppSpacing.s8, 0, bottomInset),
            children: [
              if (!online) ...[
                _pad(const _OfflineBanner()),
                const SizedBox(height: AppSpacing.sectionAiry),
              ],
              _pad(_GoalStreak(done: ring.done, goal: ring.goal, streak: streak)),
              if (allDone) ...[
                const SizedBox(height: AppSpacing.sectionAiry),
                _pad(
                  _AllDoneCard(
                    // «N повторений сделано» — the card's own line is about ANSWERS and stays that way;
                    // it is a fact under the headline, not the goal counter above it.
                    done: reviewsToday,
                    onGenerate: () => Navigator.of(
                      context,
                    ).push(MaterialPageRoute(builder: (_) => const GenerateScreen())),
                  ),
                ),
              ] else ...[
                if (cta.kind != HomeCtaKind.none) ...[
                  const SizedBox(height: AppSpacing.sectionAiry),
                  _pad(
                    _CtaButton(
                      cta: cta,
                      collections: collections,
                      progress: progress,
                      onReview: () => _openSession(context, l.homeSessionTitle),
                      onLearn: () => _openSession(context, l.homeSessionTitle, learn: true),
                      onTriage: (id, title) => Navigator.of(context).push(
                        MaterialPageRoute(
                          builder: (_) => TriageScreen(collectionId: id, title: title),
                        ),
                      ),
                    ),
                  ),
                ],
                // The two ways into the pool from the main screen, under the primary action and
                // deliberately quiet. The CTA answers «что делать сейчас»; these answer «что я вообще
                // учу» and «хочу именно эту тему» — real questions, but not the daily one.
                if (collections.isNotEmpty) ...[
                  const SizedBox(height: AppSpacing.s12),
                  _pad(
                    _PoolEntries(
                      collections: collections,
                      onMyWords: () => Navigator.of(
                        context,
                      ).push(MaterialPageRoute(builder: (_) => const MyWordsScreen())),
                      onTopic: (id, title) => Navigator.of(context).push(
                        MaterialPageRoute(
                          builder: (_) => SessionScreen(title: title, collectionId: id),
                        ),
                      ),
                    ),
                  ),
                ],
                const SizedBox(height: AppSpacing.sectionAiry),
                _pad(_GenerationCard(showQuotaNote: collections.isEmpty, offline: !online)),
              ],
              if (word != null) ...[
                const SizedBox(height: AppSpacing.sectionAiry),
                _pad(_WordOfDay(word: word)),
              ],
              if (collections.isNotEmpty) ...[
                const SizedBox(height: AppSpacing.sectionAiry),
                CollectionsStrip(
                  collections: collections,
                  progress: progress,
                  onSeeAll: onOpenCollections,
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  /// Screen-edge padding for a block. The collections strip is exempt (it bleeds).
  static Widget _pad(Widget child) => Padding(
    padding: const EdgeInsets.symmetric(horizontal: AppSpacing.screenH),
    child: child,
  );

  void _openSession(
    BuildContext context,
    String title, {
    bool practice = false,
    bool learn = false,
  }) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => SessionScreen(title: title, practice: practice, learn: learn),
      ),
    );
  }
}

/// «Мои слова» and «Тренировка по теме» — the pool's two entrances from the main screen.
///
/// A themed session is the SAME study session as the primary CTA, narrowed to one collection's
/// words: «потренировать аптечные перед аптекой». It is a filter on the pool, never a source — a
/// word of that collection the learner never took into study stays out of it, exactly as it does
/// everywhere else.
class _PoolEntries extends StatelessWidget {
  const _PoolEntries({required this.collections, required this.onMyWords, required this.onTopic});

  final List<WordCollection> collections;
  final VoidCallback onMyWords;
  final void Function(String collectionId, String title) onTopic;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    // «Тренировка по теме» is the longest of the two and takes a second line at half a screen;
    // IntrinsicHeight + stretch keeps the pair the same height instead of letting one grow past
    // the other (QA-OBS-10 — the Russian label used to overflow the row outright).
    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Expanded(
            child: QuietButton(
              label: l.myWordsTitle,
              icon: LucideIcons.bookMarked,
              onPressed: () {
                AppHaptics.light();
                onMyWords();
              },
            ),
          ),
          const SizedBox(width: AppSpacing.s8),
          Expanded(
            child: QuietButton(
              label: l.topicSessionAction,
              icon: LucideIcons.layers,
              onPressed: () => _pickTopic(context, l),
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _pickTopic(BuildContext context, AppLocalizations l) async {
    AppHaptics.light();
    await showAppBottomSheet<void>(
      context: context,
      builder: (sheetContext) => Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(l.topicSessionTitle, style: AppText.sectionLabel),
          const SizedBox(height: AppSpacing.s12),
          for (final c in collections)
            InkWell(
              onTap: () {
                Navigator.of(sheetContext).maybePop();
                onTopic(c.id, c.title);
              },
              child: Padding(
                padding: const EdgeInsets.symmetric(vertical: 14),
                child: Text(c.title, style: AppText.termInList),
              ),
            ),
        ],
      ),
    );
  }
}

/// Daily goal + streak (кадр 2.1).
class _GoalStreak extends StatelessWidget {
  const _GoalStreak({required this.done, required this.goal, required this.streak});
  final int done, goal, streak;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final label = AppText.translation.copyWith(fontSize: 13);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            Expanded(child: Text(l.homeDailyGoal, style: label)),
            Text(l.homeGoalCount(done, goal), style: AppText.counterHeader),
          ],
        ),
        const SizedBox(height: 6),
        ProgressLine(value: goal > 0 ? done / goal : 0, height: AppProgress.heightCard),
        const SizedBox(height: AppSpacing.s8),
        Row(
          children: [
            Expanded(
              child: Text(
                streak > 0 ? l.homeStreakActive(streak) : l.homeStreakStartToday,
                style: label,
              ),
            ),
            _StreakDots(streak: streak),
          ],
        ),
      ],
    );
  }
}

/// The streak week — exactly [kStreakWeek] dots: past days filled, today an
/// outline, the rest on track (§4 density). Layout counts from [streakDots].
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
          if (i > 0) const SizedBox(width: AppSpacing.s8),
          _dot(dots[i]),
        ],
      ],
    );
  }

  Widget _dot(StreakDot kind) {
    return Container(
      width: 8,
      height: 8,
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
}

/// State-dependent primary action (§ rule 10: главное действие — повторение).
class _CtaButton extends StatelessWidget {
  const _CtaButton({
    required this.cta,
    required this.collections,
    required this.progress,
    required this.onReview,
    required this.onLearn,
    required this.onTriage,
  });

  final HomeCta cta;
  final List<WordCollection> collections;
  final Map<String, CollectionProgress> progress;
  final VoidCallback onReview;
  final VoidCallback onLearn;
  final void Function(String collectionId, String title) onTriage;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);

    late final String label;
    String? subtitle;
    late final VoidCallback onTap;

    switch (cta.kind) {
      case HomeCtaKind.review:
        label = l.homeReviewButton(cta.count);
        final dueTitles = [
          for (final c in collections)
            if ((progress[c.id]?.due ?? 0) > 0) c.title,
        ].take(3).toList();
        subtitle = dueTitles.isEmpty ? null : dueTitles.join(', ');
        onTap = onReview;
      case HomeCtaKind.learn:
        label = l.homeLearnButton(cta.count);
        subtitle = l.homeLearnSubtitle;
        onTap =
            onLearn; // a non-practice session introduces the new words (F8); empty ⇒ quota spent
      case HomeCtaKind.triage:
        label = l.homeTriageButton(cta.count);
        final target = collections.where((c) => c.id == cta.collectionId).firstOrNull;
        subtitle = target?.title;
        onTap = () => target == null ? onReview() : onTriage(target.id, target.title);
      case HomeCtaKind.limitReached:
        // Quota spent but new words remain — an inactive card, not a blocked session (F13).
        return const LimitReachedCard();
      case HomeCtaKind.practice: // practice is never a home CTA — render nothing if it ever appears
      case HomeCtaKind.none:
        return const SizedBox.shrink();
    }

    return Material(
      color: AppColors.ink,
      borderRadius: BorderRadius.circular(AppRadii.button),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () {
          AppHaptics.light();
          onTap();
        },
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 12, 12, 12),
          child: Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(label, style: AppText.primaryButton),
                    if (subtitle != null) ...[
                      const SizedBox(height: 3),
                      Text(
                        subtitle,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: AppText.primaryButtonSub,
                      ),
                    ],
                  ],
                ),
              ),
              const SizedBox(width: AppSpacing.s12),
              Container(
                width: 36,
                height: 36,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: AppColors.paper.withValues(alpha: 0.16),
                ),
                child: const Icon(LucideIcons.arrowRight, size: 17, color: AppColors.paper),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// The generation card — visual centre of the home (кадр 2.1). Antiqua title,
/// a topic field with a mic, example chips. The field/mic/chips open the full
/// [GenerateScreen] (chips prefill the topic); voice-fill lands with that screen.
class _GenerationCard extends StatelessWidget {
  const _GenerationCard({required this.showQuotaNote, this.offline = false});
  final bool showQuotaNote;

  /// Offline (кадр 9c): the card becomes a quiet dashed variant explaining that generation needs a
  /// connection. It still opens the create screen — A3.5 durably queues the prompt for when the
  /// network returns — so the topic is never lost.
  final bool offline;

  void _open(BuildContext context, {String? topic, bool startVoice = false}) {
    AppHaptics.light();
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => GenerateScreen(initialTopic: topic, startVoice: startVoice),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    if (offline) {
      return GestureDetector(
        onTap: () => _open(context),
        child: DottedBorderBox(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                l.homeGenerateTitle,
                style: AppText.translation.copyWith(
                  fontSize: 14,
                  fontWeight: FontWeight.w600,
                  color: AppColors.tertiary,
                ),
              ),
              const SizedBox(height: 6),
              Text(
                l.homeGenerateOfflineNote,
                style: AppText.transcription.copyWith(fontSize: 12.5, color: AppColors.tertiary),
              ),
            ],
          ),
        ),
      );
    }
    return PaperCard(
      radius: AppRadii.card,
      padding: const EdgeInsets.all(AppSpacing.s16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(l.homeGenerateTitle, style: AppText.generationCardTitle),
          const SizedBox(height: 6),
          Text(l.homeGenerateSubtitle, style: AppText.translation.copyWith(fontSize: 13)),
          const SizedBox(height: AppSpacing.s12),
          // Entrance, not a form (2.1↔2.4): the field never accepts input or generates here — a tap
          // (field, arrow, or the mic) opens the full create screen with the topic carried and the
          // input focused. The home never runs a generation itself.
          GestureDetector(
            onTap: () => _open(context),
            child: Container(
              height: 46,
              padding: const EdgeInsets.only(left: 14, right: 6),
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
                  _IconTap(
                    icon: LucideIcons.mic,
                    color: AppColors.secondary,
                    // The home mic opens the create screen already recording (кадр 6c).
                    onTap: () => _open(context, startVoice: true),
                  ),
                  // The arrow is an explicit entrance too (go to create), not a submit — home never
                  // generates. Its own tap target so behaviour doesn't depend on gesture bubbling.
                  GestureDetector(
                    onTap: () => _open(context),
                    child: Container(
                      width: 44,
                      height: 38,
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
          const SizedBox(height: AppSpacing.s8),
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            child: Row(
              children: [
                for (final chip in [
                  l.homeGenerateChipDoctor,
                  l.homeGenerateChipRent,
                  l.homeGenerateChipInterview,
                ]) ...[
                  _ExampleChip(
                    label: chip,
                    onTap: () => _open(context, topic: chip),
                  ),
                  const SizedBox(width: AppSpacing.s8),
                ],
              ],
            ),
          ),
          if (showQuotaNote) ...[
            const SizedBox(height: 11),
            Text(
              l.homeGenerateFreeTier,
              style: AppText.transcription.copyWith(fontSize: 12, color: AppColors.tertiary),
            ),
          ],
        ],
      ),
    );
  }
}

class _IconTap extends StatelessWidget {
  const _IconTap({required this.icon, required this.color, required this.onTap});
  final IconData icon;
  final Color color;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkResponse(
      onTap: onTap,
      radius: 22,
      child: SizedBox(width: 44, height: 44, child: Icon(icon, size: 20, color: color)),
    );
  }
}

/// Outline example-topic chip (кадр 2.1).
class _ExampleChip extends StatelessWidget {
  const _ExampleChip({required this.label, required this.onTap});
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    // Same 44pt tap zone around a 29pt chip as every other chip in the app (QA-OBS-15).
    return MinTapHeight(
      onTap: onTap,
      child: Material(
        color: Colors.transparent,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadii.chip),
          side: const BorderSide(color: AppColors.hairline),
        ),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 13, vertical: 8),
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

/// «На сегодня всё» (кадр 9b): shown when the daily goal is met and nothing is due / learnable / to
/// triage. Free practice is not offered here (it lives on the collection screen) — the card affirms
/// the goal and points at a new collection.
class _AllDoneCard extends StatelessWidget {
  const _AllDoneCard({required this.done, required this.onGenerate});
  final int done;
  final VoidCallback onGenerate;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return PaperCard(
      radius: AppRadii.chip,
      padding: const EdgeInsets.fromLTRB(AppSpacing.s22, 24, AppSpacing.s22, 22),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            l.homeAllDoneTitle,
            textAlign: TextAlign.center,
            style: AppText.stepTitle.copyWith(fontSize: 26),
          ),
          const SizedBox(height: 9),
          Text(
            l.homeAllDoneSubtitle(done),
            textAlign: TextAlign.center,
            style: AppText.translation.copyWith(
              fontSize: 13.5,
              height: 1.45,
              color: AppColors.secondary,
            ),
          ),
          const SizedBox(height: 18),
          PrimaryButton(
            label: l.homeAllDoneGenerate,
            minHeight: 52,
            onPressed: () {
              AppHaptics.light();
              onGenerate();
            },
          ),
        ],
      ),
    );
  }
}

/// «Слово дня» — client-side deterministic term (кадр 2.1). Speaker button is
/// deferred with TTS wiring (same scope line as the triage back face).
class _WordOfDay extends StatelessWidget {
  const _WordOfDay({required this.word});
  final Word word;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return PaperCard(
      radius: AppRadii.alert,
      padding: const EdgeInsets.fromLTRB(AppSpacing.cardPadding, 9, AppSpacing.cardPadding, 9),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(l.homeWordOfDay, style: AppText.sectionLabel),
          const SizedBox(height: 7),
          Text(word.term, style: AppText.displayTerm.copyWith(fontSize: 23)),
          const SizedBox(height: 5),
          Row(
            children: [
              if (word.transcription != null && word.transcription!.isNotEmpty) ...[
                Text('/${word.transcription}/', style: AppText.transcription),
                const SizedBox(width: 9),
              ],
              _TypeBadge(type: word.type),
            ],
          ),
          const SizedBox(height: 5),
          Text(word.translation, style: AppText.translation.copyWith(fontSize: 14.5)),
          if (word.example != null && word.example!.isNotEmpty) ...[
            const SizedBox(height: 8),
            Text(word.example!, style: AppText.usageExample.copyWith(fontSize: 14.5)),
          ],
        ],
      ),
    );
  }
}

/// Type badge (§2), copy reused from the triage keys.
class _TypeBadge extends StatelessWidget {
  const _TypeBadge({required this.type});
  final String type;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final label = switch (type) {
      'word' => l.triageTermTypeWord,
      'phrase' => l.triageTermTypePhrase,
      'idiom' => l.triageTermTypeIdiom,
      'phrasal_verb' => l.triageTermTypePhrasalVerb,
      _ => l.triageTermTypePhrase,
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 2),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(AppRadii.thumb),
        border: Border.all(color: AppColors.hairline),
      ),
      child: Text(label.toUpperCase(), style: AppText.badge),
    );
  }
}
