import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/theme/theme.dart';

import '../../data/models.dart';
import '../../data/local/cached_image_provider.dart';

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
    // «Сохранённые» gets a cover of its OWN, not a placeholder waiting for a photo — because no
    // photo is ever coming. Every other collection is ABOUT something a camera can point at; this
    // one is about the act of saving, and a stock image of anything at all would be arbitrary. So
    // it is drawn: ink ground, paper bookmark. It also costs no image search and works offline,
    // which the folder that exists on every account should.
    final placeholder = collection.isDefault
        ? DecoratedBox(
            decoration: BoxDecoration(color: AppColors.ink, borderRadius: br),
            child: Icon(LucideIcons.bookmark, size: size < 64 ? 20 : 30, color: AppColors.paper),
          )
        : DecoratedBox(
            decoration: BoxDecoration(color: AppColors.track, borderRadius: br),
            child: Icon(
              collection.isAi ? LucideIcons.sparkles : LucideIcons.image,
              size: size < 64 ? 20 : 26,
              color: AppColors.tertiary,
            ),
          );
    // …and it is never overridden by one either: the default folder keeps its drawn identity even
    // if a photo somehow lands on the row.
    final url = collection.isDefault ? null : collection.imageUrl;
    return SizedBox(
      width: size,
      height: size,
      child: (url == null || url.isEmpty)
          ? placeholder
          : ClipRRect(
              borderRadius: br,
              child: Image(
                image: CachedNetworkImage(url),
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
