import 'package:flutter/material.dart';

import '../../core/design.dart';
import '../../data/models.dart';

/// A collection's cover: the Pexels photo once it's synced, or a gradient placeholder with an
/// origin icon until then (images dock in asynchronously — this widget swaps in on the next rebuild
/// from the drift stream, no reload). Used at tile size and as a wide header.
class CollectionCover extends StatelessWidget {
  const CollectionCover({
    super.key,
    required this.collection,
    required this.index,
    this.size = 52,
    this.width,
    this.height,
    this.radius = AppRadii.md,
  });

  final WordCollection collection;
  final int index;
  final double size; // square shorthand when width/height omitted
  final double? width;
  final double? height;
  final double radius;

  @override
  Widget build(BuildContext context) {
    final w = width ?? size;
    final h = height ?? size;
    final placeholder = _placeholder(w, h);
    final url = collection.imageUrl;

    return ClipRRect(
      borderRadius: BorderRadius.circular(radius),
      child: url == null
          ? placeholder
          : Image.network(
              url,
              width: w,
              height: h,
              fit: BoxFit.cover,
              // Keep the placeholder while loading and if the URL fails — never a broken-image icon.
              loadingBuilder: (_, child, progress) => progress == null ? child : placeholder,
              errorBuilder: (_, _, _) => placeholder,
            ),
    );
  }

  Widget _placeholder(double w, double h) => Container(
        width: w,
        height: h,
        alignment: Alignment.center,
        decoration: BoxDecoration(gradient: AppGradients.tileFor(index)),
        child: Icon(
          collection.isAi ? Icons.auto_awesome_rounded : Icons.style_rounded,
          color: Colors.white,
          size: (w < 80 ? 26 : 40),
        ),
      );
}
