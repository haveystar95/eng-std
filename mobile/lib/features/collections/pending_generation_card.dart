import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/design.dart';
import '../../core/glass.dart';
import '../../data/local/app_database.dart';
import '../../data/models.dart';
import '../../data/providers.dart';
import 'collection_cover.dart';
import 'collection_detail_screen.dart';

/// The card for one in-flight / finished generation, driven entirely by its drift row (survives an
/// app kill; reconciled on launch). Three faces: generating (spinner), failed (reason + retry), and
/// ready (the collection's cover/title once synced, tap to open with a «Разобрать» nudge).
class PendingGenerationCard extends ConsumerWidget {
  const PendingGenerationCard({super.key, required this.row, required this.index});

  final PendingGeneration row;
  final int index;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final card = switch (row.status) {
      'failed' => _failed(context, ref),
      'succeeded' => _ready(context, ref),
      _ => _generating(context),
    };
    return card.animate().fadeIn().slideY(begin: 0.06, end: 0);
  }

  Widget _generating(BuildContext context) {
    return GlassCard(
      padding: const EdgeInsets.all(AppSpacing.md),
      child: Row(
        children: [
          const SizedBox(
            width: 26,
            height: 26,
            child: CircularProgressIndicator(strokeWidth: 2.6, color: AppColors.primary),
          ),
          const SizedBox(width: AppSpacing.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text('ИИ генерирует коллекцию…',
                    style: TextStyle(color: AppColors.textPrimary, fontWeight: FontWeight.w700)),
                const SizedBox(height: 2),
                Text('«${row.topic}»',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(color: AppColors.textSecondary, fontSize: 13)),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _failed(BuildContext context, WidgetRef ref) {
    final ctrl = ref.read(generationControllerProvider);
    return GlassCard(
      tint: AppColors.danger,
      padding: const EdgeInsets.all(AppSpacing.md),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.error_outline_rounded, color: AppColors.danger, size: 22),
              const SizedBox(width: 10),
              Expanded(
                child: Text('Не удалось: «${row.topic}»',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(color: AppColors.textPrimary, fontWeight: FontWeight.w700)),
              ),
            ],
          ),
          if ((row.error ?? '').isNotEmpty) ...[
            const SizedBox(height: 6),
            Text(row.error!,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(color: AppColors.textSecondary, fontSize: 12)),
          ],
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(child: GlassSecondaryButton(label: 'Убрать', onTap: () => ctrl.dismiss(row.id))),
              const SizedBox(width: 12),
              Expanded(
                flex: 2,
                child: GlassButton(
                  label: 'Повторить',
                  icon: Icons.refresh_rounded,
                  onTap: () {
                    AppFeedback.tap();
                    ctrl.retry(row);
                  },
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _ready(BuildContext context, WidgetRef ref) {
    final cols = ref.watch(collectionsProvider).value ?? const <WordCollection>[];
    final col = _findCollection(cols, row.collectionId);

    // Collection not yet in the local mirror (sync still in flight) — a brief "почти готово".
    if (col == null) {
      return GlassCard(
        padding: const EdgeInsets.all(AppSpacing.md),
        child: Row(
          children: [
            const Icon(Icons.check_circle_rounded, color: AppColors.know, size: 26),
            const SizedBox(width: AppSpacing.md),
            Expanded(
              child: Text('Готово — загружаю «${row.topic}»…',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(color: AppColors.textPrimary, fontWeight: FontWeight.w700)),
            ),
          ],
        ),
      );
    }

    final under = row.delivered != null && row.requested != null && row.delivered! < row.requested!;
    return GlassCard(
      padding: const EdgeInsets.all(AppSpacing.md),
      onTap: () {
        AppFeedback.success();
        ref.read(generationControllerProvider).dismiss(row.id);
        Navigator.of(context).push(MaterialPageRoute(
          builder: (_) => CollectionDetailScreen(collectionId: col.id, title: col.title, offerTriage: true),
        ));
      },
      child: Row(
        children: [
          CollectionCover(collection: col, index: index),
          const SizedBox(width: AppSpacing.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    const Icon(Icons.auto_awesome_rounded, color: AppColors.accent, size: 15),
                    const SizedBox(width: 6),
                    const Text('Готово',
                        style: TextStyle(color: AppColors.accent, fontSize: 12, fontWeight: FontWeight.w800)),
                  ],
                ),
                const SizedBox(height: 3),
                Text(col.title,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
                const SizedBox(height: 2),
                Text(
                  under ? 'получилось ${row.delivered} из ${row.requested}' : '${col.wordsCount} слов',
                  style: TextStyle(
                      color: under ? AppColors.review : AppColors.textSecondary, fontSize: 13, fontWeight: FontWeight.w600),
                ),
              ],
            ),
          ),
          const Icon(Icons.chevron_right_rounded, color: AppColors.textMuted),
        ],
      ),
    );
  }

  static WordCollection? _findCollection(List<WordCollection> cols, String? id) {
    if (id == null) return null;
    for (final c in cols) {
      if (c.id == id) return c;
    }
    return null;
  }
}
