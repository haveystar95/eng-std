import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';
import 'package:eng_std/l10n/app_localizations.dart';

import '../../data/feature_flags.dart';
import '../../data/local/app_database.dart';
import '../../data/models.dart';
import '../../data/pending_content_refresher.dart';
import '../../data/providers.dart';
import '../../data/store_providers.dart';
import '../home/home_cta.dart';
import 'collection_cover.dart';
import 'collection_cta.dart';
import 'collection_detail_screen.dart';
import 'collection_edit_dialog.dart';
import 'generate_screen.dart';
import 'pending_generation_card.dart';
import 'store_view.dart';

/// The Collections tab (кадр 2.5): a paper screen with a title + «+» that opens the create flow, the
/// in-flight generation states (shimmer / error / undelivered) at the top of the list, then the
/// collection rows — 96px cover, Literata title, «N слов · освоено M», three ink-density segments,
/// and a state-dependent action hint. Everything reads the local DB (renders offline). Pull-to-
/// refresh does a full resync (ghost cleanup).
///
/// When the store flag is on (A3.9), a «Мои»/«Готовые» segment (кадр 2.8) pins under the header and
/// switches the body to the store. With the flag off the screen is exactly as before — no segment.
class CollectionsScreen extends ConsumerStatefulWidget {
  const CollectionsScreen({super.key, this.initialSegment = 0});

  /// Which segment opens first — 0 = «Мои», 1 = «Готовые». Defaults to «Мои» (production); the store
  /// preview harness passes 1 to land straight on the showcase. Ignored when the store flag is off.
  final int initialSegment;

  @override
  ConsumerState<CollectionsScreen> createState() => _CollectionsScreenState();
}

class _CollectionsScreenState extends ConsumerState<CollectionsScreen> {
  /// Covers land asynchronously after a generation; this pulls `/sync` on a widening backoff while
  /// any of them is still missing, so the shelf fills in on its own. Ends on its own budget — a
  /// collection that never gets a cover is a settled fact, not a thing to keep waiting for.
  PendingContentRefresher? _refresher;

  late int _segment = widget.initialSegment; // 0 = Мои, 1 = Готовые (store)

  @override
  void initState() {
    super.initState();
    // The shell keeps this screen alive in an IndexedStack, so a caller that wants to LAND on the
    // store (the home screen's «или выбрать из N готовых →») cannot pass a constructor argument to
    // an instance that was built long ago. It sets the shared segment instead, and this listener is
    // how the request reaches a screen that is not being rebuilt for it.
    _segmentRequest = ref.read(collectionsSegmentProvider)..addListener(_applySegmentRequest);
  }

  late final ValueNotifier<int> _segmentRequest;

  void _applySegmentRequest() {
    final next = _segmentRequest.value;
    if (mounted && next != _segment) setState(() => _segment = next);
  }

  @override
  void dispose() {
    _segmentRequest.removeListener(_applySegmentRequest);
    _refresher?.dispose();
    super.dispose();
  }

  double get _bottomInset =>
      AppTabBarMetrics.height +
      AppTabBarMetrics.bottomInset +
      MediaQuery.viewPaddingOf(context).bottom +
      AppSpacing.s8;

  @override
  Widget build(BuildContext context) {
    final storeOn = ref.watch(featureFlagsProvider).storeEnabled;

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.dark,
      child: Scaffold(
        backgroundColor: AppColors.paper,
        body: SafeArea(bottom: false, child: storeOn ? _withStore() : _mineList(withHeader: true)),
      ),
    );
  }

  /// Store on: pinned header + segment, then the selected body fills the rest.
  Widget _withStore() {
    final l = AppLocalizations.of(context);
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(
            AppSpacing.screenH,
            AppSpacing.s12,
            AppSpacing.screenH,
            AppSpacing.s12,
          ),
          child: _Header(),
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(
            AppSpacing.screenH,
            0,
            AppSpacing.screenH,
            AppSpacing.s12,
          ),
          child: _Segmented(
            labels: [l.storeSegmentMine, l.storeSegmentReady],
            index: _segment,
            onChanged: (i) {
              AppHaptics.light();
              setState(() => _segment = i);
              // Keep the shared value in step, so «открыть магазин» from the home does not have to
              // fight a stale one the next time it is asked for.
              _segmentRequest.value = i;
            },
          ),
        ),
        Expanded(
          child: _segment == 0
              ? _mineList(withHeader: false)
              : RefreshIndicator(
                  color: AppColors.ink,
                  backgroundColor: AppColors.surfaceRaised,
                  onRefresh: () async {
                    final pair = ref.read(storeLangPairProvider);
                    if (pair != null) ref.invalidate(storeCollectionsProvider(pair));
                  },
                  child: StoreView(bottomInset: _bottomInset),
                ),
        ),
      ],
    );
  }

  /// «Мои» — the collection list. [withHeader] includes the scrolling title (store off); when the
  /// store segment is shown the header is pinned above, so it's omitted here.
  Widget _mineList({required bool withHeader}) {
    final l = AppLocalizations.of(context);
    final collections = ref.watch(collectionsProvider).value ?? const <WordCollection>[];
    // A finished generation whose collection has already been mirrored is no longer a card of its
    // own — the collection below IS it (QA-3, see [visiblePendingGenerations]).
    final pending = visiblePendingGenerations(
      ref.watch(pendingGenerationsProvider).value ?? const <PendingGeneration>[],
      collections,
    );
    final empty = collections.isEmpty && pending.isEmpty;
    // A cover still missing means an image job is probably still running; keep looking briefly.
    // A generation still in flight is already polled by the generation controller, so it is not
    // counted here — two pollers on one fact is how a screen ends up refreshing forever.
    (_refresher ??= PendingContentRefresher(ref.read(syncServiceProvider))).nudge(
      pending: collections.any((c) => !c.isDefault && (c.imageUrl == null || c.imageUrl!.isEmpty)),
    );

    return RefreshIndicator(
      color: AppColors.ink,
      backgroundColor: AppColors.surfaceRaised,
      onRefresh: () => ref.read(syncServiceProvider).resync(),
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: EdgeInsets.only(bottom: _bottomInset),
        children: [
          if (withHeader)
            Padding(
              padding: const EdgeInsets.fromLTRB(
                AppSpacing.screenH,
                AppSpacing.s12,
                AppSpacing.screenH,
                AppSpacing.s16,
              ),
              child: _Header(),
            ),
          if (empty)
            _Empty(l: l)
          else ...[
            for (var i = 0; i < pending.length; i++)
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: AppSpacing.screenH),
                child: PendingGenerationCard(
                  row: pending[i],
                  showDivider: i < pending.length - 1 || collections.isNotEmpty,
                ),
              ),
            for (var i = 0; i < collections.length; i++)
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: AppSpacing.screenH),
                child: _CollectionRow(
                  collection: collections[i],
                  showDivider: i < collections.length - 1,
                ),
              ),
          ],
        ],
      ),
    );
  }
}

/// «Мои»/«Готовые» pill segment (кадр 2.8): a faint ink track with a raised paper thumb on the
/// selected side.
class _Segmented extends StatelessWidget {
  const _Segmented({required this.labels, required this.index, required this.onChanged});
  final List<String> labels;
  final int index;
  final ValueChanged<int> onChanged;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(3),
      decoration: BoxDecoration(
        color: AppColors.ink.withValues(alpha: 0.07),
        borderRadius: BorderRadius.circular(14),
      ),
      child: Row(
        children: [
          for (var i = 0; i < labels.length; i++)
            Expanded(
              child: Semantics(
                button: true,
                selected: i == index,
                label: labels[i],
                child: GestureDetector(
                  onTap: i == index ? null : () => onChanged(i),
                  behavior: HitTestBehavior.opaque,
                  child: AnimatedContainer(
                    duration: const Duration(milliseconds: 160),
                    height: 34,
                    alignment: Alignment.center,
                    decoration: i == index
                        ? BoxDecoration(
                            color: AppColors.surfaceRaised,
                            borderRadius: BorderRadius.circular(11),
                            boxShadow: [
                              BoxShadow(
                                color: AppColors.ink.withValues(alpha: 0.10),
                                blurRadius: 4,
                                offset: const Offset(0, 1),
                              ),
                            ],
                          )
                        : null,
                    child: Text(
                      labels[i],
                      style: TextStyle(
                        fontFamily: AppFonts.inter,
                        fontSize: 13.5,
                        fontWeight: i == index ? FontWeight.w700 : FontWeight.w600,
                        color: i == index ? AppColors.ink : AppColors.secondary,
                      ),
                    ),
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _Header extends ConsumerWidget {
  /// The «+» asks WHICH new collection (SLV-6). It used to walk straight into generation, which
  /// made the manual collection — the one the search results and the word card save into —
  /// reachable only from the generation screen's own footer, i.e. only by first asking the AI for
  /// something you didn't want.
  Future<void> _create(BuildContext context, WidgetRef ref, AppLocalizations l) async {
    AppHaptics.light();
    final choice = await showAppBottomSheet<bool>(
      context: context,
      builder: (sheet) => Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Padding(
            padding: const EdgeInsets.symmetric(vertical: AppSpacing.s8),
            child: Text(l.collectionsNewCollection, style: AppText.sectionLabel),
          ),
          ListTile(
            leading: const Icon(LucideIcons.sparkles, size: 20, color: AppColors.ink),
            title: Text(l.collectionsCreateGenerate, style: AppText.translation),
            subtitle: Text(
              l.collectionsCreateGenerateHint,
              style: AppText.transcription.copyWith(fontSize: 12),
            ),
            onTap: () => Navigator.of(sheet).pop(true),
          ),
          ListTile(
            leading: const Icon(LucideIcons.pencil, size: 20, color: AppColors.ink),
            title: Text(l.collectionsCreateManual, style: AppText.translation),
            subtitle: Text(
              l.collectionsCreateManualHint,
              style: AppText.transcription.copyWith(fontSize: 12),
            ),
            onTap: () => Navigator.of(sheet).pop(false),
          ),
        ],
      ),
    );
    if (choice == null || !context.mounted) return;
    if (choice) {
      await Navigator.of(context).push(MaterialPageRoute(builder: (_) => const GenerateScreen()));
    } else {
      await showCollectionEditor(context, ref);
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    return Row(
      children: [
        Expanded(child: Text(l.collectionsTitle, style: AppText.screenTitle)),
        Semantics(
          button: true,
          label: l.collectionsNewCollection,
          child: InkResponse(
            radius: 26,
            onTap: () => _create(context, ref, l),
            child: Container(
              width: 36,
              height: 36,
              alignment: Alignment.center,
              decoration: const BoxDecoration(color: AppColors.ink, shape: BoxShape.circle),
              child: const Icon(LucideIcons.plus, size: 18, color: AppColors.paper),
            ),
          ),
        ),
      ],
    );
  }
}

/// One collection as a flat list row (кадр 7a): cover, title, «N слов · освоено M», ink-density
/// segments, and a review/triage action hint. Tap → detail; long-press → own-collection menu.
class _CollectionRow extends ConsumerWidget {
  const _CollectionRow({required this.collection, required this.showDivider});
  final WordCollection collection;
  final bool showDivider;

  Future<void> _confirmDelete(BuildContext context, WidgetRef ref) async {
    final l = AppLocalizations.of(context);
    final ok = await showCenterAlert(
      context: context,
      title: l.collectionDeleteTitle(collection.title),
      message: l.collectionDeleteMessage,
      confirmLabel: l.actionDelete,
      cancelLabel: l.commonCancel,
    );
    if (ok != true) return;
    AppHaptics.warning();
    try {
      await ref.read(apiClientProvider).deleteCollection(collection.id);
      // Drop it locally right away — the delta feed doesn't reliably carry a collection tombstone.
      await ref.read(appDatabaseProvider).deleteCollectionLocal(collection.id);
      ref.read(syncServiceProvider).sync();
    } catch (_) {
      AppHaptics.warning(); // network/5xx: keep the row, the user can retry
    }
  }

  Future<void> _confirmUnsubscribe(BuildContext context, WidgetRef ref) async {
    final l = AppLocalizations.of(context);
    final ok = await showCenterAlert(
      context: context,
      title: l.collectionUnsubscribeTitle(collection.title),
      message: l.collectionUnsubscribeMessage,
      confirmLabel: l.collectionMenuRemoveFromMine,
      cancelLabel: l.commonCancel,
    );
    if (ok != true) return;
    AppHaptics.warning();
    await unsubscribeCollectionById(ref, collection.id);
  }

  Future<void> _menu(BuildContext anchor, WidgetRef ref) async {
    AppHaptics.light();
    final l = AppLocalizations.of(anchor);
    // Read-only store set: «Убрать из моих» (unsubscribe) only; own collections keep rename + delete.
    await showFloatingContextMenu(
      context: anchor,
      anchorContext: anchor,
      barrierLabel: l.commonCloseMenu,
      actions: collection.readOnly
          ? [
              ContextMenuAction(
                icon: LucideIcons.circleMinus,
                label: l.collectionMenuRemoveFromMine,
                destructive: true,
                onSelected: () => _confirmUnsubscribe(anchor, ref),
              ),
            ]
          : [
              ContextMenuAction(
                icon: LucideIcons.pencil,
                label: l.collectionMenuRename,
                onSelected: () => showCollectionEditor(anchor, ref, existing: collection),
              ),
              // «Сохранённые» keeps rename and loses delete: it is the destination the app promises
              // when it says «сохранено в …», and a one-tap save with nowhere to land is a broken
              // button. The server refuses it too (409) — this is the honest face of that rule, not
              // the rule itself.
              if (!collection.isDefault)
                ContextMenuAction(
                  icon: LucideIcons.trash2,
                  label: l.collectionMenuDelete,
                  destructive: true,
                  onSelected: () => _confirmDelete(anchor, ref),
                ),
            ],
    );
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final prog = ref.watch(collectionsProgressProvider).value?[collection.id];
    final density =
        ref.watch(collectionDensityProvider(collection.id)).value ??
        const CollectionDensity(mastered: 0, inWork: 0, toSort: 0);
    final untriaged = ref.watch(untriagedByCollectionProvider).value?[collection.id] ?? 0;
    final learnable = ref.watch(learnableByCollectionProvider).value?[collection.id] ?? 0;
    final total = prog?.total ?? collection.wordsCount;
    final mastered = prog?.mastered ?? 0;
    final remainingNewQuota = ref.watch(statsProvider).value?.newRemaining ?? 0;
    final cta = computeCollectionCta(
      untriaged: untriaged,
      learnable: learnable,
      due: prog?.due ?? 0,
      remainingNewQuota: remainingNewQuota,
    );

    return DecoratedBox(
      decoration: BoxDecoration(
        border: showDivider ? const Border(bottom: BorderSide(color: AppColors.hairline)) : null,
      ),
      child: Builder(
        builder: (anchor) => InkWell(
          onTap: () => Navigator.of(context).push(
            MaterialPageRoute(
              builder: (_) =>
                  CollectionDetailScreen(collectionId: collection.id, title: collection.title),
            ),
          ),
          onLongPress: () => _menu(anchor, ref),
          child: Padding(
            padding: const EdgeInsets.symmetric(vertical: AppSpacing.s16),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.center,
              children: [
                CollectionCover(collection: collection, size: 96),
                const SizedBox(width: 13),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              collection.title,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: AppText.collectionNameCard,
                            ),
                          ),
                          // The pair rides the TITLE line rather than the counter line: the counter
                          // already carries two numbers, and a third label there reads as a third
                          // number. Trailing, so a long title still ellipsises against it.
                          const SizedBox(width: 8),
                          PairBadge(
                            learned: collection.targetLang,
                            support: collection.sourceLang,
                            reference: collection.isReference,
                          ),
                        ],
                      ),
                      const SizedBox(height: 5),
                      Text(
                        // A phrasebook has no progress to count (the server leaves it out of
                        // /study/progress entirely), so it says how many words it holds and stops.
                        collection.isReference
                            ? l.collectionWordsCount(collection.wordsCount)
                            : l.collectionsTileMastered(total, mastered),
                        style: AppText.translation.copyWith(fontSize: 12.5),
                      ),
                      if (!collection.isReference) ...[
                        const SizedBox(height: 11),
                        InkSegments.fromCounts(
                          mastered: density.mastered,
                          inWork: density.inWork,
                          toSort: density.toSort,
                          height: 6,
                        ),
                      ],
                      if (_hint(l, cta) case final hint? when !collection.isReference) ...[
                        const SizedBox(height: 8),
                        Text(
                          hint,
                          style: AppText.transcription.copyWith(
                            fontSize: 11.5,
                            color: AppColors.tertiary,
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  String? _hint(AppLocalizations l, HomeCta cta) => switch (cta.kind) {
    HomeCtaKind.triage => l.collectionTriageButton(cta.count),
    HomeCtaKind.learn => l.collectionLearnButton(cta.count),
    HomeCtaKind.review => l.collectionReviewButton(cta.count),
    _ => null,
  };
}

class _Empty extends StatelessWidget {
  const _Empty({required this.l});
  final AppLocalizations l;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(AppSpacing.screenH, 60, AppSpacing.screenH, 24),
      child: Column(
        children: [
          const Icon(LucideIcons.layoutGrid, size: 40, color: AppColors.tertiary),
          const SizedBox(height: AppSpacing.s16),
          Text(
            l.collectionsEmptyTitle,
            style: AppText.stepTitle.copyWith(fontSize: 22),
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: 8),
          Text(
            l.collectionsEmptyBody,
            textAlign: TextAlign.center,
            style: AppText.translation.copyWith(color: AppColors.secondary),
          ),
        ],
      ),
    );
  }
}
