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
            return Column(
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

class _LangPairRow extends ConsumerWidget {
  const _LangPairRow({required this.pair});
  final StoreLangPair pair;

  Future<void> _pickTarget(BuildContext context, WidgetRef ref) async {
    final l = AppLocalizations.of(context);
    final chosen = await showAppBottomSheet<String>(
      context: context,
      builder: (_) => _TargetLangSheet(title: l.storeLangPairSheetTitle, current: pair.target, exclude: pair.source),
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
        child: Row(
          children: [
            MiniFlag(languageCode: pair.source, size: 20),
            const SizedBox(width: 9),
            Text(languageByCode(pair.source).name, style: _pairName),
            const SizedBox(width: 9),
            const Icon(LucideIcons.arrowRight, size: 14, color: AppColors.tertiary),
            const SizedBox(width: 9),
            MiniFlag(languageCode: pair.target, size: 20),
            const SizedBox(width: 9),
            Expanded(child: Text(languageByCode(pair.target).name, style: _pairName)),
            const Icon(LucideIcons.chevronDown, size: 16, color: AppColors.tertiary),
          ],
        ),
      ),
    );
  }

  static const _pairName =
      TextStyle(fontFamily: AppFonts.inter, fontSize: 14, fontWeight: FontWeight.w600, color: AppColors.ink);
}

class _Section extends StatelessWidget {
  const _Section({required this.section});
  final StoreSection section;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final label = (section.topic == null || section.topic!.isEmpty) ? l.storeSectionOther : section.topic!;
    return Padding(
      padding: const EdgeInsets.only(top: 20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: AppSpacing.screenH),
            child: Text(label.toUpperCase(), style: AppText.sectionLabel.copyWith(color: AppColors.secondary)),
          ),
          const SizedBox(height: 12),
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: AppSpacing.screenH),
            child: Row(
              children: [
                for (var i = 0; i < section.items.length; i++) ...[
                  if (i > 0) const SizedBox(width: 12),
                  _StoreCard(collection: section.items[i]),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _StoreCard extends ConsumerWidget {
  const _StoreCard({required this.collection});
  final StoreCollection collection;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    return GestureDetector(
      onTap: () {
        AppHaptics.light();
        showStorePreview(context, ref, collection);
      },
      child: SizedBox(
        width: 152,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _StoreCover(
              imageUrl: collection.imageUrl,
              width: 152,
              height: 104,
              premium: collection.isPremium,
              subscribed: collection.isSubscribed,
            ),
            const SizedBox(height: 8),
            Text(collection.title,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: AppText.collectionNameCard.copyWith(fontSize: 15)),
            const SizedBox(height: 3),
            Text(l.storeWordsCount(collection.itemsCount),
                style: AppText.transcription.copyWith(fontSize: 11.5, color: AppColors.secondary)),
          ],
        ),
      ),
    );
  }
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
            child: Image.network(
              url,
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
                    Text(AppLocalizations.of(context).storeInLibrary,
                        style: const TextStyle(
                            fontFamily: AppFonts.inter, fontSize: 10.5, fontWeight: FontWeight.w700, color: AppColors.ink)),
                  ],
                ),
              ),
            )
          else if (premium)
            Positioned(
              top: 8,
              right: 8,
              child: Container(
                width: 26,
                height: 26,
                alignment: Alignment.center,
                decoration: BoxDecoration(color: AppColors.ink.withValues(alpha: 0.55), shape: BoxShape.circle),
                child: const Icon(LucideIcons.lock, size: 13, color: AppColors.field),
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
          Text(l.storeEmptyTitle, style: AppText.stepTitle.copyWith(fontSize: 22), textAlign: TextAlign.center),
          const SizedBox(height: 8),
          Text(l.storeEmptyBody,
              textAlign: TextAlign.center, style: AppText.translation.copyWith(color: AppColors.secondary)),
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
        await showPaywall(context, ref,
            PaywallArgs(PaywallEntry.store, collectionTitle: c.title, otherSetsCount: _otherPremiumCount()));
      case StoreSubscribeResult.error:
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(l.storeSubscribeError)));
      case StoreSubscribeResult.unsubscribed:
        break;
    }
  }

  Future<void> _openPaywall() async {
    await showPaywall(context, ref,
        PaywallArgs(PaywallEntry.store, collectionTitle: c.title, otherSetsCount: _otherPremiumCount()));
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
          Text(c.description!,
              style: AppText.translation.copyWith(fontSize: 13.5, color: AppColors.secondary, height: 1.45)),
        ],
        const SizedBox(height: 10),
        Text(l.storeWordsCount(c.itemsCount),
            style: AppText.transcription.copyWith(fontSize: 12.5, color: AppColors.tertiary)),
        const SizedBox(height: 18),
        if (c.isSubscribed)
          _InLibraryButton(label: l.storeInLibrary)
        else if (locked) ...[
          _PrimaryLockButton(label: l.storeAvailableWithPremium, onTap: _busy ? null : _openPaywall),
          const SizedBox(height: 11),
          Center(
            child: Text(l.storeAllSetsUnlock(_otherPremiumCount() + 1),
                style: AppText.transcription.copyWith(fontSize: 12.5, color: AppColors.secondary)),
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
      decoration: BoxDecoration(color: AppColors.faintInk, borderRadius: BorderRadius.circular(AppRadii.field)),
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
                        child: Text(lang.name,
                            style: const TextStyle(fontFamily: AppFonts.inter, fontSize: 15.5, color: AppColors.ink)),
                      ),
                      if (lang.code == current) const Icon(Icons.check, size: 18, color: AppColors.ink),
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
