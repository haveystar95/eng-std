import 'package:flutter/material.dart';

import 'package:eng_std/theme/theme.dart';

/// Слоёная бумага (rule 13): поверхность светлее фона + тёплая двойная тень
/// вместо рамки. Never draw a border on this.
class PaperCard extends StatelessWidget {
  const PaperCard({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(AppSpacing.cardPadding),
    this.radius = AppRadii.card,
    this.onTap,
    this.color = AppColors.surfaceRaised,
    this.shadow = AppShadows.card,
    this.clipContent = false,
  });

  final Widget child;
  final EdgeInsetsGeometry padding;
  final double radius;
  final VoidCallback? onTap;
  final Color color;
  final List<BoxShadow> shadow;

  /// Clip the child to the card radius (для карточек с фото-обложкой во всю ширину).
  final bool clipContent;

  @override
  Widget build(BuildContext context) {
    final br = BorderRadius.circular(radius);
    Widget content = Padding(padding: padding, child: child);

    if (onTap != null) {
      content = InkWell(
        onTap: onTap,
        borderRadius: br,
        splashColor: AppColors.ink.withValues(alpha: 0.05),
        highlightColor: AppColors.ink.withValues(alpha: 0.03),
        child: content,
      );
    }

    return DecoratedBox(
      decoration: BoxDecoration(color: color, borderRadius: br, boxShadow: shadow),
      child: Material(
        type: MaterialType.transparency,
        borderRadius: br,
        clipBehavior: clipContent ? Clip.antiAlias : Clip.none,
        child: content,
      ),
    );
  }
}
