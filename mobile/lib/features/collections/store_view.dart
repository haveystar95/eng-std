import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';
import 'package:eng_std/l10n/app_localizations.dart';

import '../../data/feature_flags.dart';
import '../../data/languages.dart' show kLanguages, languageByCode;
import '../../data/models.dart';
import '../../data/store_providers.dart';
import '../paywall/paywall_screen.dart';
import '../../data/local/cached_image_provider.dart';

/// The store surface (кадр 2.8) — the «Готовые» segment of the Collections tab. Language-pair row +
/// topic sections of horizontal cards; premium sets carry a lock badge, already-added sets a «В моих»
/// pill. Reads `GET /store/collections` (behind the store flag; empty until Session B publishes).
class StoreView extends ConsumerWidget {
  const StoreView({super.key, required this.bottomInset});
  final double bottomInset;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final pair = ref.watch(storeLangPairProvider);
    if (pair == null) return const SizedBox.shrink();
    final sectionsAsync = ref.watch(storeCollectionsProvider(pair));

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: EdgeInsets.only(bottom: bottomInset),
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(AppSpacing.screenH, 0, AppSpacing.screenH, 0),
          child: _LangPairRow(pair: pair),
        ),
        sectionsAsync.when(
          loading: () => const Padding(
            padding: EdgeInsets.only(top: 60),
            child: Center(child: CircularProgressIndicator(color: AppColors.ink)),
          ),
          error: (_, _) => _Empty(l: l),
          data: (sections) {
            if (sections.isEmpty) return _Empty(l: l);
            // Section headers only when there are ≥2 meaningfully-named topics; otherwise (a single
            // group, or collections with no topic yet) render one flat vertical grid, no «OTHER»
            // header. Headers return by themselves once collections carry real topics.
            final titled = sections
                .where((s) => s.topic != null && s.topic!.trim().isNotEmpty)
                .toList();
            if (titled.length < 2) {
              final all = [for (final s in sections) ...s.items];
              return Padding(
                padding: const EdgeInsets.only(top: 20, bottom: 8),
                child: _Grid(items: all),
              );
            }
            return Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                for (final s in sections) _Section(section: s),
                const SizedBox(height: 8),
              ],
            );
          },
        ),
      ],
    );
  }
}

/// The pair badge in the FILTER row above the grid, as opposed to the one on each card.
///
/// The row and the cards now draw the same badge (Ч.5а — the row used to have a format and a
/// direction of its own), which is the point and is also why a test asking «does this card name a
/// pair» needs a way to leave the filter out of the count.
const Key storePairFilterKey = Key('store-pair-filter');

class _LangPairRow extends ConsumerWidget {
  const _LangPairRow({required this.pair});
  final StoreLangPair pair;

  Future<void> _pickTarget(BuildContext context, WidgetRef ref) async {
    final l = AppLocalizations.of(context);
    final chosen = await showAppBottomSheet<String>(
      context: context,
      builder: (_) => _TargetLangSheet(
        title: l.storeLangPairSheetTitle,
        current: pair.target,
        exclude: pair.source,
      ),
    );
    if (chosen != null && chosen != pair.target) {
      ref.read(storeLangPairProvider.notifier).setPair((source: pair.source, target: chosen));
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Padding(
      padding: const EdgeInsets.only(top: 14),
      child: PaperCard(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
        onTap: () {
          AppHaptics.light();
          _pickTarget(context, ref);
        },
        // THE SAME BADGE the cards under it carry, and pointing the same way. This row used to
        // draw its own pair — `source → target`, i.e. «Русский → English» — while every other pair
        // on the screen read «изучаемый → язык поддержки», so the filter above the grid and the
        // cards inside it described one pair in two opposite directions (Ч.5а). The endonym of the
        // LEARNED language stays beside it: this row is a picker, and «English» is what the sheet
        // it opens is a list of.
        child: Row(
          children: [
            PairBadge(
              key: storePairFilterKey,
              learned: pair.target,
              support: pair.source,
              size: 15,
            ),
            const SizedBox(width: 9),
            Expanded(child: Text(languageByCode(pair.target).endonym, style: _pairName)),
            const Icon(LucideIcons.chevronDown, size: 16, color: AppColors.tertiary),
          ],
        ),
      ),
    );
  }

  static const _pairName = TextStyle(
    fontFamily: AppFonts.inter,
    fontSize: 14,
    fontWeight: FontWeight.w600,
    color: AppColors.ink,
  );
}

/// A topic section: header + a 2-column grid of its cards. A section with no meaningful topic (the
/// null bucket alongside titled ones) renders header-less.
class _Section extends StatelessWidget {
  const _Section({required this.section});
  final StoreSection section;

  @override
  Widget build(BuildContext context) {
    final titled = section.topic != null && section.topic!.trim().isNotEmpty;
    return Padding(
      padding: const EdgeInsets.only(top: 20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (titled) ...[
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: AppSpacing.screenH),
              child: Text(
                section.topic!.toUpperCase(),
                style: AppText.sectionLabel.copyWith(color: AppColors.secondary),
              ),
            ),
            const SizedBox(height: 12),
          ],
          _Grid(items: section.items),
        ],
      ),
    );
  }
}

/// The vertical showcase (кадр 2.8): a 2-column grid of full-width cards, so premium sets and their
/// lock badges are all visible on the first screen with no horizontal scroll.
class _Grid extends StatelessWidget {
  const _Grid({required this.items});
  final List<StoreCollection> items;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: AppSpacing.screenH),
      child: LayoutBuilder(
        builder: (context, c) {
          const gap = 12.0;
          final w = (c.maxWidth - gap) / 2;
          return Wrap(
            spacing: gap,
            runSpacing: 20,
            children: [
              for (final item in items)
                SizedBox(
                  width: w,
                  child: _StoreCard(collection: item, width: w),
                ),
            ],
          );
        },
      ),
    );
  }
}

class _StoreCard extends ConsumerWidget {
  const _StoreCard({required this.collection, required this.width});
  final StoreCollection collection;
  final double width;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final desc = collection.description;
    return GestureDetector(
      onTap: () {
        AppHaptics.light();
        showStorePreview(context, ref, collection);
      },
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _StoreCover(
            imageUrl: collection.imageUrl,
            width: width,
            height: width * 0.66,
            premium: collection.isPremium,
            subscribed: collection.isSubscribed,
            radius: 16,
          ),
          const SizedBox(height: 9),
          Text(
            collection.title,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: AppText.collectionNameCard.copyWith(fontSize: 16),
          ),
          if (desc != null && desc.trim().isNotEmpty) ...[
            const SizedBox(height: 3),
            Text(
              desc,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: AppText.translation.copyWith(fontSize: 12.5, color: AppColors.secondary),
            ),
          ],
          const SizedBox(height: 3),
          Row(
            children: [
              Flexible(
                child: Text(
                  _meta(l, collection),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: AppText.transcription.copyWith(fontSize: 11.5, color: AppColors.tertiary),
                ),
              ),
              // The store already states the pair once, in the row above the grid — but that row is
              // a FILTER, and a card can outlive the filter it was found under (a saved set, a
              // deep link, a scroll back). The card says which pair it is itself.
              const SizedBox(width: 6),
              PairBadge(
                learned: collection.targetLang,
                support: collection.sourceLang,
                // «Не сказано» is read as «not a phrasebook», which is the ONLY safe way round: the
                // feed has carried the flag since A-4.1, so a null here means an older server, and
                // on an older server there is no zh/ja deck to be wrong about. The reverse default
                // would print «справочник» over the whole catalogue.
                reference: collection.isReference ?? false,
                size: 11,
              ),
            ],
          ),
        ],
      ),
    );
  }
}

/// «16 слов» or «16 слов · A2–B1» (кадр 2.8) — the level suffix only when the feed carries it.
String _meta(AppLocalizations l, StoreCollection c) {
  final words = l.storeWordsCount(c.itemsCount);
  final level = c.cefr?.trim();
  return (level == null || level.isEmpty) ? words : '$words · $level';
}

/// Store cover with the premium lock / «В моих» overlay. Photo once synced, monochrome placeholder
/// until then.
class _StoreCover extends StatelessWidget {
  const _StoreCover({
    required this.imageUrl,
    required this.width,
    required this.height,
    required this.premium,
    required this.subscribed,
    this.radius = 14,
  });

  final String? imageUrl;
  final double width, height, radius;
  final bool premium, subscribed;

  @override
  Widget build(BuildContext context) {
    final br = BorderRadius.circular(radius);
    final placeholder = DecoratedBox(
      decoration: BoxDecoration(color: AppColors.track, borderRadius: br),
      child: const Icon(LucideIcons.image, size: 24, color: AppColors.tertiary),
    );
    final url = imageUrl;
    final image = (url == null || url.isEmpty)
        ? placeholder
        : ClipRRect(
            borderRadius: br,
            child: Image(
              image: CachedNetworkImage(url),
              width: width,
              height: height,
              fit: BoxFit.cover,
              loadingBuilder: (_, child, progress) => progress == null ? child : placeholder,
              errorBuilder: (_, _, _) => placeholder,
            ),
          );

    return SizedBox(
      width: width,
      height: height,
      child: Stack(
        children: [
          Positioned.fill(child: image),
          if (subscribed)
            Positioned(
              top: 8,
              right: 8,
              child: Container(
                height: 24,
                padding: const EdgeInsets.symmetric(horizontal: 9),
                decoration: BoxDecoration(
                  color: AppColors.paper.withValues(alpha: 0.92),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Row(
                  children: [
                    const Icon(LucideIcons.check, size: 12, color: AppColors.ink),
                    const SizedBox(width: 5),
                    Text(
                      AppLocalizations.of(context).storeInLibrary,
                      style: const TextStyle(
                        fontFamily: AppFonts.inter,
                        fontSize: 10.5,
                        fontWeight: FontWeight.w700,
                        color: AppColors.ink,
                      ),
                    ),
                  ],
                ),
              ),
            )
          else if (premium)
            Positioned(
              top: 8,
              right: 8,
              child: Container(
                width: 28,
                height: 28,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: AppColors.ink.withValues(alpha: 0.55),
                  shape: BoxShape.circle,
                ),
                child: const Icon(LucideIcons.lock, size: 15, color: AppColors.field),
              ),
            ),
        ],
      ),
    );
  }
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
          const Icon(LucideIcons.store, size: 40, color: AppColors.tertiary),
          const SizedBox(height: AppSpacing.s16),
          Text(
            l.storeEmptyTitle,
            style: AppText.stepTitle.copyWith(fontSize: 22),
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: 8),
          Text(
            l.storeEmptyBody,
            textAlign: TextAlign.center,
            style: AppText.translation.copyWith(color: AppColors.secondary),
          ),
        ],
      ),
    );
  }
}

// ── Preview sheet (кадры 8c/8d/15d) ──────────────────────────────────────────

/// Opens the store preview sheet for [collection]. NB the store contract carries no term list (only
/// `items_count`), so the preview shows the cover, title, description and count — the design's
/// «Что внутри» five-word teaser needs a preview endpoint that doesn't exist yet (reported).
void showStorePreview(BuildContext context, WidgetRef ref, StoreCollection collection) {
  showAppBottomSheet<void>(
    context: context,
    builder: (_) => _StorePreview(collection: collection),
  );
}

class _StorePreview extends ConsumerStatefulWidget {
  const _StorePreview({required this.collection});
  final StoreCollection collection;

  @override
  ConsumerState<_StorePreview> createState() => _StorePreviewState();
}

class _StorePreviewState extends ConsumerState<_StorePreview> {
  bool _busy = false;

  StoreCollection get c => widget.collection;

  int _otherPremiumCount() {
    final pair = ref.read(storeLangPairProvider);
    if (pair == null) return 0;
    final sections = ref.read(storeCollectionsProvider(pair)).value ?? const <StoreSection>[];
    var n = 0;
    for (final s in sections) {
      for (final item in s.items) {
        if (item.isPremium && item.id != c.id) n++;
      }
    }
    return n;
  }

  Future<void> _add() async {
    setState(() => _busy = true);
    final res = await subscribeToStore(ref, c);
    if (!mounted) return;
    setState(() => _busy = false);
    final l = AppLocalizations.of(context);
    switch (res) {
      case StoreSubscribeResult.subscribed:
        Navigator.of(context).maybePop();
        AppHaptics.success();
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(l.storePreviewAdded)));
      case StoreSubscribeResult.subscriptionRequired:
        // Server says free tier — route to the paywall (store entrance).
        await showPaywall(
          context,
          ref,
          PaywallArgs(
            PaywallEntry.store,
            collectionTitle: c.title,
            otherSetsCount: _otherPremiumCount(),
          ),
        );
      case StoreSubscribeResult.error:
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(l.storeSubscribeError)));
      case StoreSubscribeResult.unsubscribed:
        break;
    }
  }

  Future<void> _openPaywall() async {
    await showPaywall(
      context,
      ref,
      PaywallArgs(
        PaywallEntry.store,
        collectionTitle: c.title,
        otherSetsCount: _otherPremiumCount(),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final premium = ref.watch(premiumProvider);
    // Premium set on a free tier → the CTA leads to the paywall; otherwise it adds directly.
    final locked = c.isPremium && !premium && !c.isSubscribed;

    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _StoreCover(
          imageUrl: c.imageUrl,
          width: double.infinity,
          height: 132,
          premium: c.isPremium,
          subscribed: c.isSubscribed,
          radius: 16,
        ),
        const SizedBox(height: 14),
        Text(c.title, style: AppText.displayTerm.copyWith(fontSize: 24)),
        if (c.description != null && c.description!.isNotEmpty) ...[
          const SizedBox(height: 7),
          Text(
            c.description!,
            style: AppText.translation.copyWith(
              fontSize: 13.5,
              color: AppColors.secondary,
              height: 1.45,
            ),
          ),
        ],
        const SizedBox(height: 10),
        Text(
          _meta(l, c),
          style: AppText.transcription.copyWith(fontSize: 12.5, color: AppColors.tertiary),
        ),
        // «Что внутри» — the term teaser (кадры 8c/8d). Shown for premium sets too: the lock is on
        // adding, not on seeing the value. Loader while fetching; offline → no list (as before).
        _PreviewList(collectionId: c.id),
        const SizedBox(height: 18),
        if (c.isSubscribed)
          _InLibraryButton(label: l.storeInLibrary)
        else if (locked) ...[
          _PrimaryLockButton(
            label: l.storeAvailableWithPremium,
            onTap: _busy ? null : _openPaywall,
          ),
          const SizedBox(height: 11),
          Center(
            child: Text(
              l.storeAllSetsUnlock(_otherPremiumCount() + 1),
              style: AppText.transcription.copyWith(fontSize: 12.5, color: AppColors.secondary),
            ),
          ),
        ] else
          PrimaryButton(
            label: l.storeAddToMine,
            minHeight: 52,
            enabled: !_busy,
            onPressed: _busy ? null : _add,
          ),
        const SizedBox(height: 6),
      ],
    );
  }
}

/// The «Что внутри» teaser: the first terms + «и ещё N слов» (кадры 8c/8d). A skeleton fills the same
/// space while the request is in flight; offline (error) collapses to nothing so the sheet reads as
/// it did before the preview endpoint existed.
class _PreviewList extends ConsumerWidget {
  const _PreviewList({required this.collectionId});
  final String collectionId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    return ref
        .watch(storePreviewProvider(collectionId))
        .when(
          loading: () => const _PreviewSkeleton(),
          error: (_, _) => const SizedBox.shrink(),
          data: (p) {
            if (p.items.isEmpty) return const SizedBox.shrink();
            return Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const SizedBox(height: 18),
                Text(
                  l.storeInsideLabel.toUpperCase(),
                  style: AppText.sectionLabel.copyWith(fontSize: 11.5, color: AppColors.secondary),
                ),
                const SizedBox(height: 8),
                for (var i = 0; i < p.items.length; i++)
                  _PreviewRow(item: p.items[i], last: i == p.items.length - 1 && p.more == 0),
                if (p.more > 0)
                  Padding(
                    padding: const EdgeInsets.only(top: 9),
                    child: Text(
                      l.storeMoreWords(p.more),
                      style: AppText.transcription.copyWith(
                        fontSize: 12,
                        color: AppColors.tertiary,
                      ),
                    ),
                  ),
              ],
            );
          },
        );
  }
}

class _PreviewRow extends StatelessWidget {
  const _PreviewRow({required this.item, required this.last});
  final StorePreviewItem item;
  final bool last;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 7),
      decoration: last
          ? null
          : BoxDecoration(
              border: Border(bottom: BorderSide(color: AppColors.ink.withValues(alpha: 0.09))),
            ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Text(
              item.term,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: AppText.termInList.copyWith(fontSize: 16),
            ),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Text(
              item.translation,
              textAlign: TextAlign.right,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: AppText.translation.copyWith(fontSize: 13),
            ),
          ),
        ],
      ),
    );
  }
}

/// Five placeholder rows while the preview loads.
class _PreviewSkeleton extends StatelessWidget {
  const _PreviewSkeleton();

  @override
  Widget build(BuildContext context) {
    Widget bar(double w) => Container(
      width: w,
      height: 12,
      decoration: BoxDecoration(color: AppColors.track, borderRadius: BorderRadius.circular(4)),
    );
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const SizedBox(height: 18),
        bar(96),
        const SizedBox(height: 12),
        for (var i = 0; i < 5; i++)
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 7),
            child: Row(children: [bar(110), const Spacer(), bar(70)]),
          ),
      ],
    );
  }
}

/// «Доступно с Premium» — чернильная кнопка с иконкой замка (кадр 15d).
class _PrimaryLockButton extends StatelessWidget {
  const _PrimaryLockButton({required this.label, required this.onTap});
  final String label;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: AppColors.ink,
      borderRadius: BorderRadius.circular(AppRadii.field),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Container(
          height: 52,
          alignment: Alignment.center,
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(LucideIcons.lock, size: 17, color: AppColors.paper),
              const SizedBox(width: 9),
              Text(label, style: AppText.primaryButton.copyWith(fontSize: 15.5)),
            ],
          ),
        ),
      ),
    );
  }
}

/// Already-in-library state — a quiet, inert confirmation.
class _InLibraryButton extends StatelessWidget {
  const _InLibraryButton({required this.label});
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 52,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: AppColors.faintInk,
        borderRadius: BorderRadius.circular(AppRadii.field),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(LucideIcons.check, size: 17, color: AppColors.secondary),
          const SizedBox(width: 9),
          Text(label, style: AppText.sheetButton.copyWith(color: AppColors.secondary)),
        ],
      ),
    );
  }
}

class _TargetLangSheet extends StatelessWidget {
  const _TargetLangSheet({required this.title, required this.current, required this.exclude});
  final String title, current, exclude;

  @override
  Widget build(BuildContext context) {
    final langs = kLanguages.where((lang) => lang.code != exclude).toList();
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(title, style: AppText.sheetButton.copyWith(fontSize: 17)),
        const SizedBox(height: 8),
        Flexible(
          child: ListView.builder(
            shrinkWrap: true,
            itemCount: langs.length,
            itemBuilder: (context, i) {
              final lang = langs[i];
              return InkWell(
                onTap: () => Navigator.of(context).pop(lang.code),
                borderRadius: BorderRadius.circular(AppRadii.field),
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 12),
                  child: Row(
                    children: [
                      MiniFlag(languageCode: lang.code),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Text(
                          lang.endonym,
                          style: const TextStyle(
                            fontFamily: AppFonts.inter,
                            fontSize: 15.5,
                            color: AppColors.ink,
                          ),
                        ),
                      ),
                      if (lang.code == current)
                        const Icon(Icons.check, size: 18, color: AppColors.ink),
                    ],
                  ),
                ),
              );
            },
          ),
        ),
      ],
    );
  }
}
