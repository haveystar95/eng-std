import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/theme/theme.dart';

import '../../data/models.dart';

/// A collection's cover: the Pexels photo once it's synced, or a monochrome paper placeholder
/// (track fill + image glyph) until then. Images dock in asynchronously — this widget swaps in on
/// the next rebuild from the drift stream, no reload. Used at tile size in the collections list.
class CollectionCover extends StatelessWidget {
  const CollectionCover({
    super.key,
    required this.collection,
    this.size = 96,
    this.radius = AppRadii.thumb,
  });

  final WordCollection collection;
  final double size;
  final double radius;

  @override
  Widget build(BuildContext context) {
    final br = BorderRadius.circular(radius);
    final placeholder = DecoratedBox(
      decoration: BoxDecoration(color: AppColors.track, borderRadius: br),
      child: Icon(collection.isAi ? LucideIcons.sparkles : LucideIcons.image,
          size: size < 64 ? 20 : 26, color: AppColors.tertiary),
    );
    final url = collection.imageUrl;
    return SizedBox(
      width: size,
      height: size,
      child: (url == null || url.isEmpty)
          ? placeholder
          : ClipRRect(
              borderRadius: br,
              child: Image.network(
                url,
                width: size,
                height: size,
                fit: BoxFit.cover,
                // Keep the placeholder while loading and if the URL fails — never a broken-image icon.
                loadingBuilder: (_, child, progress) => progress == null ? child : placeholder,
                errorBuilder: (_, _, _) => placeholder,
              ),
            ),
    );
  }
}
