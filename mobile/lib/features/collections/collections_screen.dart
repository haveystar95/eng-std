import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/design.dart';
import '../../core/glass.dart';
import '../../data/models.dart';
import '../../data/providers.dart';
import 'collection_detail_screen.dart';
import 'collection_edit_dialog.dart';
import 'generate_dialog.dart';

class CollectionsScreen extends ConsumerWidget {
  const CollectionsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final collections = ref.watch(collectionsProvider);

    return Scaffold(
      backgroundColor: Colors.transparent,
      body: AmbientBackground(
        child: SafeArea(
          bottom: false,
          child: collections.when(
            loading: () => const Center(child: CircularProgressIndicator()),
            error: (e, _) => Center(child: Text('Ошибка: $e', style: const TextStyle(color: AppColors.textSecondary))),
            data: (items) => RefreshIndicator(
              onRefresh: () async => ref.invalidate(collectionsProvider),
              child: CustomScrollView(
                physics: const AlwaysScrollableScrollPhysics(),
                slivers: [
                  SliverToBoxAdapter(child: _Header(items: items)),
                  if (items.isEmpty)
                    const SliverFillRemaining(hasScrollBody: false, child: _EmptyState())
                  else
                    SliverPadding(
                      padding: const EdgeInsets.fromLTRB(AppSpacing.md, 0, AppSpacing.md, 120),
                      sliver: SliverList.separated(
                        itemCount: items.length,
                        separatorBuilder: (_, _) => const SizedBox(height: 12),
                        itemBuilder: (context, i) => _CollectionTile(collection: items[i], index: i)
                            .animate()
                            .fadeIn(delay: (40 * i).ms)
                            .slideY(begin: 0.06, end: 0),
                      ),
                    ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _Header extends ConsumerWidget {
  const _Header({required this.items});
  final List<WordCollection> items;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final totalWords = items.fold<int>(0, (s, c) => s + c.wordsCount);
    return Padding(
      padding: const EdgeInsets.fromLTRB(AppSpacing.md, AppSpacing.md, AppSpacing.md, AppSpacing.md),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Text('Коллекции',
                  style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w800)),
              const Spacer(),
              SpringTap(
                onTap: () => showCollectionEditor(context, ref),
                child: Container(
                  width: 44,
                  height: 44,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: Colors.white.withValues(alpha: 0.08),
                    border: Border.all(color: Colors.white.withValues(alpha: 0.16)),
                  ),
                  child: const Icon(Icons.add_rounded, color: AppColors.textPrimary),
                ),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.md),
          Row(
            children: [
              _stat(context, '${items.length}', 'наборов', Icons.style_rounded),
              const SizedBox(width: 12),
              _stat(context, '$totalWords', 'слов', Icons.translate_rounded),
            ],
          ),
          const SizedBox(height: AppSpacing.md),
          GlassButton(
            label: 'Сгенерировать коллекцию',
            icon: Icons.auto_awesome_rounded,
            onTap: () => showGenerateDialog(context, ref),
          ),
        ],
      ),
    ).animate().fadeIn().slideY(begin: 0.06, end: 0);
  }

  Widget _stat(BuildContext context, String value, String label, IconData icon) {
    return Expanded(
      child: GlassCard(
        padding: const EdgeInsets.all(AppSpacing.md),
        child: Row(
          children: [
            Icon(icon, color: AppColors.primary, size: 22),
            const SizedBox(width: 10),
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(value, style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800)),
                Text(label, style: Theme.of(context).textTheme.bodySmall?.copyWith(color: AppColors.textSecondary)),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _CollectionTile extends ConsumerWidget {
  const _CollectionTile({required this.collection, required this.index});
  final WordCollection collection;
  final int index;

  Future<void> _confirmDelete(BuildContext context, WidgetRef ref) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Удалить коллекцию?'),
        content: Text('«${collection.title}» и её слова будут удалены.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Отмена')),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: AppColors.danger),
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Удалить'),
          ),
        ],
      ),
    );
    if (ok == true) {
      await ref.read(apiClientProvider).deleteCollection(collection.id);
      ref.invalidate(collectionsProvider);
      ref.invalidate(statsProvider);
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final isAi = collection.source == 'ai';
    return GlassCard(
      padding: const EdgeInsets.all(AppSpacing.md),
      onTap: () => Navigator.of(context).push(MaterialPageRoute(
        builder: (_) => CollectionDetailScreen(collectionId: collection.id, title: collection.title),
      )),
      child: Row(
        children: [
          Container(
            width: 52,
            height: 52,
            alignment: Alignment.center,
            decoration: BoxDecoration(gradient: AppGradients.tileFor(index), borderRadius: BorderRadius.circular(AppRadii.md)),
            child: collection.emoji != null
                ? Text(collection.emoji!, style: const TextStyle(fontSize: 26))
                : Icon(isAi ? Icons.auto_awesome_rounded : Icons.style_rounded, color: Colors.white, size: 26),
          ),
          const SizedBox(width: AppSpacing.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(collection.title,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
                const SizedBox(height: 2),
                Row(
                  children: [
                    Text('${collection.wordsCount} слов',
                        style: Theme.of(context).textTheme.bodySmall?.copyWith(color: AppColors.textSecondary)),
                    if (isAi) ...[
                      const SizedBox(width: 8),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                        decoration: BoxDecoration(color: AppColors.accent.withValues(alpha: 0.18), borderRadius: BorderRadius.circular(AppRadii.sm)),
                        child: Text('ИИ', style: Theme.of(context).textTheme.labelSmall?.copyWith(color: AppColors.accent, fontWeight: FontWeight.w700)),
                      ),
                    ],
                  ],
                ),
              ],
            ),
          ),
          PopupMenuButton<String>(
            icon: const Icon(Icons.more_vert_rounded, color: AppColors.textMuted),
            color: AppColors.surfaceHi,
            onSelected: (v) {
              if (v == 'edit') showCollectionEditor(context, ref, existing: collection);
              if (v == 'delete') _confirmDelete(context, ref);
            },
            itemBuilder: (_) => const [
              PopupMenuItem(value: 'edit', child: Text('Изменить')),
              PopupMenuItem(value: 'delete', child: Text('Удалить')),
            ],
          ),
        ],
      ),
    );
  }
}

class _EmptyState extends StatelessWidget {
  const _EmptyState();

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.xl),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 88,
              height: 88,
              decoration: BoxDecoration(color: AppColors.primary.withValues(alpha: 0.14), shape: BoxShape.circle),
              child: const Icon(Icons.auto_awesome_rounded, color: AppColors.primary, size: 40),
            ).animate(onPlay: (c) => c.repeat(reverse: true)).scale(
                  begin: const Offset(1, 1),
                  end: const Offset(1.08, 1.08),
                  duration: 1400.ms,
                  curve: Curves.easeInOut,
                ),
            const SizedBox(height: AppSpacing.md),
            Text('Пока нет коллекций',
                style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
            const SizedBox(height: 6),
            Text('Нажми «Сгенерировать» или «+», чтобы создать набор слов',
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(color: AppColors.textSecondary)),
          ],
        ),
      ),
    );
  }
}
