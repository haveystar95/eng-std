import 'package:flutter/material.dart';

import '../../core/design.dart';
import '../../data/models.dart';

/// A thin learned-progress bar for a collection tile: green fill = learned share,
/// with a `learned/total` label and an amber "due now" hint when something is due.
class CollectionProgressBar extends StatelessWidget {
  const CollectionProgressBar({super.key, required this.progress});
  final CollectionProgress progress;

  @override
  Widget build(BuildContext context) {
    if (progress.total == 0) return const SizedBox.shrink();

    return Padding(
      padding: const EdgeInsets.only(top: 8),
      child: Row(
        children: [
          Expanded(
            child: ClipRRect(
              borderRadius: BorderRadius.circular(AppRadii.pill),
              child: TweenAnimationBuilder<double>(
                tween: Tween(begin: 0, end: progress.ratio.toDouble()),
                duration: const Duration(milliseconds: 400),
                curve: Curves.easeOut,
                builder: (_, v, _) => LinearProgressIndicator(
                  value: v,
                  minHeight: 6,
                  backgroundColor: Colors.white.withValues(alpha: 0.08),
                  valueColor: const AlwaysStoppedAnimation(AppColors.know),
                ),
              ),
            ),
          ),
          const SizedBox(width: 10),
          Text('${progress.learned}/${progress.total}',
              style: const TextStyle(color: AppColors.textSecondary, fontSize: 12, fontWeight: FontWeight.w700)),
          if (progress.due > 0) ...[
            const SizedBox(width: 8),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
              decoration: BoxDecoration(
                color: AppColors.review.withValues(alpha: 0.18),
                borderRadius: BorderRadius.circular(AppRadii.sm),
              ),
              child: Text('${progress.due}',
                  style: const TextStyle(color: AppColors.review, fontSize: 11, fontWeight: FontWeight.w800)),
            ),
          ],
        ],
      ),
    );
  }
}
