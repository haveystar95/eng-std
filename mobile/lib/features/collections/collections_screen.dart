import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';
import 'package:eng_std/l10n/app_localizations.dart';

import '../../data/local/app_database.dart';
import '../../data/models.dart';
import '../../data/providers.dart';
import '../home/home_cta.dart';
import 'collection_cover.dart';
import 'collection_cta.dart';
import 'collection_detail_screen.dart';
import 'collection_edit_dialog.dart';
import 'generate_screen.dart';
import 'pending_generation_card.dart';

/// The Collections tab (кадр 2.5): a paper screen with a title + «+» that opens the create flow, the
/// in-flight generation states (shimmer / error / undelivered) at the top of the list, then the
/// collection rows — 96px cover, Literata title, «N слов · освоено M», three ink-density segments,
/// and a state-dependent action hint. Everything reads the local DB (renders offline). Pull-to-
/// refresh does a full resync (ghost cleanup).
class CollectionsScreen extends ConsumerWidget {
  const CollectionsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final collections = ref.watch(collectionsProvider).value ?? const <WordCollection>[];
    final pending = ref.watch(pendingGenerationsProvider).value ?? const <PendingGeneration>[];

    final bottomInset = AppTabBarMetrics.height +
        AppTabBarMetrics.bottomInset +
        MediaQuery.viewPaddingOf(context).bottom +
        AppSpacing.s8;

    final empty = collections.isEmpty && pending.isEmpty;

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.dark,
      child: Scaffold(
        backgroundColor: AppColors.paper,
        body: SafeArea(
          bottom: false,
          child: RefreshIndicator(
            color: AppColors.ink,
            backgroundColor: AppColors.surfaceRaised,
            onRefresh: () => ref.read(syncServiceProvider).resync(),
            child: ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: EdgeInsets.only(bottom: bottomInset),
              children: [
                Padding(
                  padding: const EdgeInsets.fromLTRB(AppSpacing.screenH, AppSpacing.s12, AppSpacing.screenH, AppSpacing.s16),
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
          ),
        ),
      ),
    );
  }
}

class _Header extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return Row(
      children: [
        Expanded(child: Text(l.collectionsTitle, style: AppText.screenTitle)),
        Semantics(
          button: true,
          label: l.collectionsNewCollection,
          child: InkResponse(
            radius: 26,
            onTap: () {
              AppHaptics.light();
              Navigator.of(context).push(MaterialPageRoute(builder: (_) => const GenerateScreen()));
            },
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

  Future<void> _menu(BuildContext anchor, WidgetRef ref) async {
    AppHaptics.light();
    final l = AppLocalizations.of(anchor);
    await showFloatingContextMenu(
      context: anchor,
      anchorContext: anchor,
      barrierLabel: l.commonCloseMenu,
      actions: [
        ContextMenuAction(
          icon: LucideIcons.pencil,
          label: l.collectionMenuRename,
          onSelected: () => showCollectionEditor(anchor, ref, existing: collection),
        ),
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
    final density = ref.watch(collectionDensityProvider(collection.id)).value ??
        const CollectionDensity(confirmed: 0, familiar: 0, inProgress: 0);
    final untriaged = ref.watch(untriagedByCollectionProvider).value?[collection.id] ?? 0;
    final learnable = ref.watch(learnableByCollectionProvider).value?[collection.id] ?? 0;
    final total = prog?.total ?? collection.wordsCount;
    final mastered = prog?.mastered ?? 0;
    final cta = computeCollectionCta(
        untriaged: untriaged, learnable: learnable, due: prog?.due ?? 0, total: total);

    return DecoratedBox(
      decoration: BoxDecoration(
        border: showDivider ? const Border(bottom: BorderSide(color: AppColors.hairline)) : null,
      ),
      child: Builder(
        builder: (anchor) => InkWell(
          onTap: () => Navigator.of(context).push(MaterialPageRoute(
            builder: (_) => CollectionDetailScreen(collectionId: collection.id, title: collection.title),
          )),
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
                      Text(collection.title,
                          maxLines: 1, overflow: TextOverflow.ellipsis, style: AppText.collectionNameCard),
                      const SizedBox(height: 5),
                      Text(l.collectionsTileMastered(total, mastered),
                          style: AppText.translation.copyWith(fontSize: 12.5)),
                      const SizedBox(height: 11),
                      InkSegments.fromCounts(
                        confirmed: density.confirmed,
                        familiar: density.familiar,
                        inProgress: density.inProgress,
                        height: 6,
                      ),
                      if (_hint(l, cta) case final hint?) ...[
                        const SizedBox(height: 8),
                        Text(hint, style: AppText.transcription.copyWith(fontSize: 11.5, color: AppColors.tertiary)),
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
          Text(l.collectionsEmptyTitle, style: AppText.stepTitle.copyWith(fontSize: 22), textAlign: TextAlign.center),
          const SizedBox(height: 8),
          Text(l.collectionsEmptyBody,
              textAlign: TextAlign.center, style: AppText.translation.copyWith(color: AppColors.secondary)),
        ],
      ),
    );
  }
}
