import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';
import 'package:eng_std/l10n/app_localizations.dart';

import '../../data/models.dart';
import '../../data/providers.dart';
import '../collections/collection_detail_screen.dart';
import '../collections/generate_screen.dart';
import '../home/home_cta.dart';
import '../home/home_providers.dart';
import '../home/streak.dart';
import 'session_screen.dart';
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
    final user = ref.watch(authControllerProvider).value;
    final collections = ref.watch(collectionsProvider).value ?? const <WordCollection>[];
    final progress = ref.watch(collectionsProgressProvider).value ?? const <String, CollectionProgress>{};
    final cta = ref.watch(homeCtaProvider);
    final word = ref.watch(wordOfDayProvider).value;
    final online = ref.watch(connectivityProvider).value ?? true;

    final goal = user?.profile?.dailyGoal ?? 20;
    final done = stats?.reviewsTotal ?? 0;
    final streak = stats?.streakDays ?? 0;

    // 9b «всё повторено»: the daily goal is met and there's nothing due or to triage (the CTA has
    // fallen through to free practice). We swap the plain practice button for the «На сегодня всё»
    // card, which carries its own practice + «собрать коллекцию» actions.
    final allDone = cta.kind == HomeCtaKind.practice && goal > 0 && done >= goal;

    final bottomInset = AppTabBarMetrics.height +
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
            _pad(_GoalStreak(done: done, goal: goal, streak: streak)),
            if (allDone) ...[
              const SizedBox(height: AppSpacing.sectionAiry),
              _pad(_AllDoneCard(
                done: done,
                onPractice: () => _openSession(context, l.homeSessionTitle, practice: true),
                onGenerate: () => Navigator.of(context).push(MaterialPageRoute(
                      builder: (_) => const GenerateScreen(),
                    )),
              )),
            ] else ...[
              if (cta.kind != HomeCtaKind.none) ...[
                const SizedBox(height: AppSpacing.sectionAiry),
                _pad(_CtaButton(
                  cta: cta,
                  collections: collections,
                  progress: progress,
                  onReview: () => _openSession(context, l.homeSessionTitle),
                  onPractice: () => _openSession(context, l.homeSessionTitle, practice: true),
                  onTriage: (id, title) => Navigator.of(context).push(MaterialPageRoute(
                        builder: (_) => TriageScreen(collectionId: id, title: title),
                      )),
                )),
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
              _CollectionsStrip(
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
  static Widget _pad(Widget child) =>
      Padding(padding: const EdgeInsets.symmetric(horizontal: AppSpacing.screenH), child: child);

  void _openSession(BuildContext context, String title, {bool practice = false}) {
    Navigator.of(context).push(MaterialPageRoute(
      builder: (_) => SessionScreen(title: title, practice: practice),
    ));
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
    required this.onPractice,
    required this.onTriage,
  });

  final HomeCta cta;
  final List<WordCollection> collections;
  final Map<String, CollectionProgress> progress;
  final VoidCallback onReview;
  final VoidCallback onPractice;
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
      case HomeCtaKind.triage:
        label = l.homeTriageButton(cta.count);
        final target = collections.where((c) => c.id == cta.collectionId).firstOrNull;
        subtitle = target?.title;
        onTap = () => target == null ? onReview() : onTriage(target.id, target.title);
      case HomeCtaKind.practice:
        label = l.homePracticeButton;
        subtitle = l.homePracticeSubtitle;
        onTap = onPractice;
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
                      Text(subtitle, maxLines: 1, overflow: TextOverflow.ellipsis, style: AppText.primaryButtonSub),
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
    Navigator.of(context).push(MaterialPageRoute(
      builder: (_) => GenerateScreen(initialTopic: topic, startVoice: startVoice),
    ));
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
              Text(l.homeGenerateTitle,
                  style: AppText.translation.copyWith(
                      fontSize: 14, fontWeight: FontWeight.w600, color: AppColors.tertiary)),
              const SizedBox(height: 6),
              Text(l.homeGenerateOfflineNote,
                  style: AppText.transcription.copyWith(fontSize: 12.5, color: AppColors.tertiary)),
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
                      style: AppText.translation.copyWith(fontSize: 14.5, color: AppColors.tertiary),
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
                  _ExampleChip(label: chip, onTap: () => _open(context, topic: chip)),
                  const SizedBox(width: AppSpacing.s8),
                ],
              ],
            ),
          ),
          if (showQuotaNote) ...[
            const SizedBox(height: 11),
            Text(l.homeGenerateFreeTier,
                style: AppText.transcription.copyWith(fontSize: 12, color: AppColors.tertiary)),
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
    return Material(
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
            style: AppText.translation.copyWith(fontSize: 12.5, fontWeight: FontWeight.w600, color: AppColors.inkBody),
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
            child: Text(l.homeOfflineBanner,
                style: AppText.translation.copyWith(fontSize: 12.5, color: AppColors.inkBody)),
          ),
        ],
      ),
    );
  }
}

/// «На сегодня всё» (кадр 9b): shown when the daily goal is met and nothing is due or to triage.
/// A centred paper card with a free-practice button and a quiet «собрать коллекцию» link.
class _AllDoneCard extends StatelessWidget {
  const _AllDoneCard({required this.done, required this.onPractice, required this.onGenerate});
  final int done;
  final VoidCallback onPractice, onGenerate;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return PaperCard(
      radius: AppRadii.chip,
      padding: const EdgeInsets.fromLTRB(AppSpacing.s22, 24, AppSpacing.s22, 22),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(l.homeAllDoneTitle, textAlign: TextAlign.center, style: AppText.stepTitle.copyWith(fontSize: 26)),
          const SizedBox(height: 9),
          Text(
            l.homeAllDoneSubtitle(done),
            textAlign: TextAlign.center,
            style: AppText.translation.copyWith(fontSize: 13.5, height: 1.45, color: AppColors.secondary),
          ),
          const SizedBox(height: 18),
          PrimaryButton(label: l.homeAllDonePractice, minHeight: 52, onPressed: () {
            AppHaptics.light();
            onPractice();
          }),
          const SizedBox(height: 14),
          Center(
            child: InkWell(
              onTap: () {
                AppHaptics.light();
                onGenerate();
              },
              borderRadius: BorderRadius.circular(AppRadii.small),
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
                child: Text(l.homeAllDoneGenerate,
                    style: AppText.translation.copyWith(fontSize: 13, fontWeight: FontWeight.w600, color: AppColors.ink)),
              ),
            ),
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

/// Horizontal collections strip with large photo covers (кадр 2.1). Bleeds to
/// the screen edges; the label row stays within the screen padding.
class _CollectionsStrip extends StatelessWidget {
  const _CollectionsStrip({required this.collections, required this.progress, this.onSeeAll});
  final List<WordCollection> collections;
  final Map<String, CollectionProgress> progress;
  final VoidCallback? onSeeAll;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: AppSpacing.screenH),
          child: Row(
            children: [
              Expanded(child: Text(l.homeMyCollections, style: AppText.sectionLabel)),
              InkWell(
                onTap: onSeeAll,
                borderRadius: BorderRadius.circular(AppRadii.small),
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 4),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(l.homeSeeAll,
                          style: AppText.translation.copyWith(fontSize: 12.5, fontWeight: FontWeight.w600, color: AppColors.ink)),
                      const SizedBox(width: 5),
                      const Icon(LucideIcons.chevronRight, size: 14, color: AppColors.tertiary),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: AppSpacing.s8),
        SizedBox(
          height: 168,
          child: ListView.separated(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: AppSpacing.screenH),
            itemCount: collections.length,
            separatorBuilder: (_, _) => const SizedBox(width: AppSpacing.wordRowGap),
            itemBuilder: (context, i) {
              final c = collections[i];
              return _CollectionCard(collection: c, progress: progress[c.id]);
            },
          ),
        ),
      ],
    );
  }
}

class _CollectionCard extends StatelessWidget {
  const _CollectionCard({required this.collection, required this.progress});
  final WordCollection collection;
  final CollectionProgress? progress;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final total = progress?.total ?? collection.wordsCount;
    final done = progress?.mastered ?? 0;
    return GestureDetector(
      onTap: () => Navigator.of(context).push(MaterialPageRoute(
        builder: (_) => CollectionDetailScreen(collectionId: collection.id, title: collection.title),
      )),
      child: SizedBox(
        width: 150,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _Cover(imageUrl: collection.imageUrl),
            const SizedBox(height: AppSpacing.s8),
            Text(
              collection.title,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: AppText.collectionNameCard.copyWith(fontSize: 15),
            ),
            const SizedBox(height: 3),
            Text(l.homeCollectionProgress(done, total),
                style: AppText.transcription.copyWith(fontSize: 11.5)),
            const SizedBox(height: 7),
            ProgressLine(value: total > 0 ? done / total : 0, height: AppProgress.heightCard),
          ],
        ),
      ),
    );
  }
}

class _Cover extends StatelessWidget {
  const _Cover({required this.imageUrl});
  final String? imageUrl;

  @override
  Widget build(BuildContext context) {
    final radius = BorderRadius.circular(AppRadii.thumb);
    final placeholder = DecoratedBox(
      decoration: BoxDecoration(color: AppColors.track, borderRadius: radius),
      child: const Center(child: Icon(LucideIcons.image, size: 22, color: AppColors.tertiary)),
    );
    return SizedBox(
      width: 150,
      height: 104,
      child: (imageUrl == null || imageUrl!.isEmpty)
          ? placeholder
          : ClipRRect(
              borderRadius: radius,
              child: Image.network(
                imageUrl!,
                width: 150,
                height: 104,
                fit: BoxFit.cover,
                loadingBuilder: (_, child, p) => p == null ? child : placeholder,
                errorBuilder: (_, _, _) => placeholder,
              ),
            ),
    );
  }
}
