import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

import '../../data/local/cached_image_provider.dart';
import '../../data/models.dart';
import '../collections/collection_detail_screen.dart';

/// Horizontal collections strip with large photo covers (кадр 2.1). Bleeds to
/// the screen edges; the label row stays within the screen padding.
class CollectionsStrip extends StatelessWidget {
  const CollectionsStrip({
    super.key,
    required this.collections,
    required this.progress,
    this.onSeeAll,
  });
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
                      Text(
                        l.homeSeeAll,
                        style: AppText.translation.copyWith(
                          fontSize: 12.5,
                          fontWeight: FontWeight.w600,
                          color: AppColors.ink,
                        ),
                      ),
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
          height: _CollectionCard.height,
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

  static const _titleSize = 15.0;

  /// Two lines of the title, always — the carousel is a fixed-height row, and a title that grew
  /// from one line to two used to push the progress line and its count off the bottom of the card
  /// («Ordering Takeaway Coffee», overflow 7 px — QA-OBS-11). Reserving the taller of the two
  /// states costs a blank line under short titles and buys a card whose bottom half never moves.
  static const _titleHeight = _titleSize * 1.15 * 2; // AppText.collectionNameCard.height = 1.15

  /// The badge's own chip height at [_badgeSize] — the count line is sized to hold it, so a card
  /// with a pair label and one without are exactly as tall.
  static const _metaHeight = _badgeSize * 1.5;

  /// Smaller than the shelf's 12 (DECISIONS п. 148 states the badge's shape, not one diameter):
  /// this card is 150 pt wide against a full-width shelf row, and the label has to stay a label.
  static const _badgeSize = 11.0;

  /// Cover + gap + two title lines + gap + the meta line + gap + bar, with a point of slack.
  static const height =
      104 + AppSpacing.s8 + _titleHeight + 3 + _metaHeight + 7 + AppProgress.heightCard + 1;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final total = progress?.total ?? collection.wordsCount;
    final done = progress?.mastered ?? 0;
    return GestureDetector(
      onTap: () => Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) =>
              CollectionDetailScreen(collectionId: collection.id, title: collection.title),
        ),
      ),
      child: SizedBox(
        width: 150,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _Cover(imageUrl: collection.imageUrl),
            const SizedBox(height: AppSpacing.s8),
            SizedBox(
              height: _titleHeight,
              child: Text(
                collection.title,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: AppText.collectionNameCard.copyWith(fontSize: _titleSize),
              ),
            ),
            const SizedBox(height: 3),
            // The carousel is the FOURTH surface a collection is seen on, and the pair has to be
            // readable on all of them or it reads as a property of the shelf (DECISIONS п. 148).
            // It rides the count line here rather than the title line the shelf uses: the title is
            // two fixed lines on this card, and a badge beside a two-line block hangs off nothing.
            SizedBox(
              height: _metaHeight,
              child: Row(
                children: [
                  Expanded(
                    child: Text(
                      l.homeCollectionProgress(done, total),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: AppText.transcription.copyWith(fontSize: 11.5),
                    ),
                  ),
                  const SizedBox(width: 6),
                  // The SAME source as the shelf: the collection's own pair off the local mirror,
                  // and its derived reference flag — not the profile, not the session.
                  PairBadge(
                    learned: collection.targetLang,
                    support: collection.sourceLang,
                    reference: collection.isReference,
                    size: _badgeSize,
                  ),
                ],
              ),
            ),
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
              child: Image(
                image: CachedNetworkImage(imageUrl!),
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
