import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/config.dart';
import '../../core/design.dart';
import '../../core/glass.dart';
import '../../data/models.dart';

/// A small type pill: слово / фраза / идиома / фраз. глагол (unknown → фраза). Also stands in as the
/// no-image affordance on a word card.
class TypeBadge extends StatelessWidget {
  const TypeBadge({super.key, required this.type});
  final String type;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(AppRadii.sm),
      ),
      child: Text(termTypeLabel(type),
          style: const TextStyle(color: AppColors.textSecondary, fontWeight: FontWeight.w700, fontSize: 11)),
    );
  }
}

/// The term's Pexels photo once it has synced, or a typed placeholder until then. Square; the drift
/// stream rebuilds the card when the image lands, so it swaps in with no reload.
class TermThumb extends StatelessWidget {
  const TermThumb({super.key, required this.word, this.size = 60});
  final Word word;
  final double size;

  @override
  Widget build(BuildContext context) {
    final placeholder = Container(
      width: size,
      height: size,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.05),
        borderRadius: BorderRadius.circular(AppRadii.md),
        border: Border.all(color: Colors.white.withValues(alpha: 0.10)),
      ),
      child: Icon(word.isPhrase ? Icons.format_quote_rounded : Icons.abc_rounded,
          color: AppColors.textMuted, size: size * 0.42),
    );
    if (word.imageUrl == null) return placeholder;
    return ClipRRect(
      borderRadius: BorderRadius.circular(AppRadii.md),
      child: Image.network(
        word.imageUrl!,
        width: size,
        height: size,
        fit: BoxFit.cover,
        loadingBuilder: (_, child, progress) => progress == null ? child : placeholder,
        errorBuilder: (_, _, _) => placeholder,
      ),
    );
  }
}

/// "Фото: Author · Pexels" — the licence credit. The author name opens their Pexels page.
class PexelsCredit extends StatelessWidget {
  const PexelsCredit({super.key, required this.author, required this.authorUrl});
  final String? author;
  final String? authorUrl;

  @override
  Widget build(BuildContext context) {
    // Hidden until public release (Pexels requires it then); the data still syncs regardless.
    if (!kShowPhotoAttribution) return const SizedBox.shrink();
    if (author == null || author!.isEmpty) return const SizedBox.shrink();
    final url = authorUrl;
    final text = Text(
      'Фото: $author · Pexels',
      maxLines: 1,
      overflow: TextOverflow.ellipsis,
      style: TextStyle(
        color: AppColors.textMuted,
        fontSize: 11,
        decoration: url != null ? TextDecoration.underline : null,
      ),
    );
    if (url == null) return text;
    return GestureDetector(
      onTap: () async {
        AppFeedback.tap();
        final uri = Uri.tryParse(url);
        if (uri != null) {
          await launchUrl(uri, mode: LaunchMode.externalApplication);
        }
      },
      child: text,
    );
  }
}
