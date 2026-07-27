import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/design.dart';
import '../../data/models.dart';
import '../../data/providers.dart';
import 'session_screen.dart';

class TrainingHomeScreen extends ConsumerWidget {
  const TrainingHomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final stats = ref.watch(statsProvider);
    final user = ref.watch(authControllerProvider).value;

    return Scaffold(
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: () async => ref.invalidate(statsProvider),
          child: stats.when(
            loading: () => const Center(child: CircularProgressIndicator()),
            error: (e, _) => _errorList(context, '$e'),
            data: (s) => ListView(
              padding: const EdgeInsets.fromLTRB(AppSpacing.md, AppSpacing.md, AppSpacing.md, AppSpacing.xl),
              children: [
                Text('Привет${user?.name != null ? ', ${user!.name.split(' ').first}' : ''} 👋',
                    style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w800)),
                const SizedBox(height: 4),
                Text('Что сегодня повторим?',
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(color: AppColors.textSecondary)),
                const SizedBox(height: AppSpacing.lg),
                _ProgressMonitor(stats: s),
                const SizedBox(height: AppSpacing.lg),
                _MixCard(dueCount: s.dueToday),
                const SizedBox(height: AppSpacing.lg),
                Text('По коллекциям',
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
                const SizedBox(height: AppSpacing.md),
                if (s.collections.isEmpty)
                  Text('Сгенерируй коллекцию во вкладке «Коллекции»',
                      style: Theme.of(context).textTheme.bodyMedium?.copyWith(color: AppColors.textMuted))
                else
                  ...s.collections.asMap().entries.map((e) => Padding(
                        padding: const EdgeInsets.only(bottom: 12),
                        child: _CollectionRow(stat: e.value, index: e.key)
                            .animate()
                            .fadeIn(delay: (40 * e.key).ms)
                            .slideY(begin: 0.06, end: 0),
                      )),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _errorList(BuildContext context, String msg) => ListView(children: [
        const SizedBox(height: 120),
        const Icon(Icons.cloud_off_rounded, size: 44, color: AppColors.textMuted),
        const SizedBox(height: 12),
        Center(child: Text(msg, style: const TextStyle(color: AppColors.textMuted))),
      ]);
}

class _ProgressMonitor extends StatelessWidget {
  const _ProgressMonitor({required this.stats});
  final Stats stats;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(AppSpacing.md),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(AppRadii.lg),
        boxShadow: AppShadows.card,
      ),
      child: Row(
        children: [
          _metric(context, '${stats.learned}', 'Выучено', AppColors.know),
          _divider(),
          _metric(context, '${stats.mastered}', 'Освоено', AppColors.primary),
          _divider(),
          _metric(context, '${stats.dueToday}', 'На сегодня', AppColors.review),
        ],
      ),
    );
  }

  Widget _divider() => Container(width: 1, height: 34, color: const Color(0x22FFFFFF));

  Widget _metric(BuildContext context, String value, String label, Color c) {
    return Expanded(
      child: Column(
        children: [
          Text(value, style: TextStyle(color: c, fontSize: 24, fontWeight: FontWeight.w800)),
          const SizedBox(height: 2),
          Text(label, style: const TextStyle(color: AppColors.textSecondary, fontSize: 12)),
        ],
      ),
    );
  }
}

class _MixCard extends StatelessWidget {
  const _MixCard({required this.dueCount});
  final int dueCount;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () => Navigator.of(context).push(MaterialPageRoute(
        builder: (_) => const SessionScreen(title: 'Перемешка', shuffle: true),
      )),
      child: Container(
        padding: const EdgeInsets.all(AppSpacing.lg),
        decoration: BoxDecoration(
          gradient: AppGradients.brand,
          borderRadius: BorderRadius.circular(AppRadii.xl),
          boxShadow: AppShadows.glow(AppColors.primary),
        ),
        child: Row(
          children: [
            Container(
              width: 56, height: 56,
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: 0.22),
                borderRadius: BorderRadius.circular(AppRadii.md),
              ),
              child: const Icon(Icons.shuffle_rounded, color: Colors.white, size: 28),
            ),
            const SizedBox(width: AppSpacing.md),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text('Перемешать всё',
                      style: TextStyle(color: Colors.white, fontSize: 19, fontWeight: FontWeight.w800)),
                  const SizedBox(height: 2),
                  Text(dueCount > 0 ? '$dueCount карточек на сегодня' : 'Повторение из всех коллекций',
                      style: TextStyle(color: Colors.white.withValues(alpha: 0.9), fontSize: 13)),
                ],
              ),
            ),
            const Icon(Icons.play_circle_fill_rounded, color: Colors.white, size: 38),
          ],
        ),
      ),
    );
  }
}

class _CollectionRow extends StatelessWidget {
  const _CollectionRow({required this.stat, required this.index});
  final CollectionStat stat;
  final int index;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: AppColors.surface,
      borderRadius: BorderRadius.circular(AppRadii.lg),
      child: InkWell(
        borderRadius: BorderRadius.circular(AppRadii.lg),
        onTap: () => Navigator.of(context).push(MaterialPageRoute(
          builder: (_) => SessionScreen(title: stat.title, collectionId: stat.id),
        )),
        child: Container(
          decoration: BoxDecoration(borderRadius: BorderRadius.circular(AppRadii.lg), boxShadow: AppShadows.card),
          padding: const EdgeInsets.all(AppSpacing.md),
          child: Row(
            children: [
              Container(
                width: 52, height: 52,
                decoration: BoxDecoration(gradient: AppGradients.tileFor(index), borderRadius: BorderRadius.circular(AppRadii.md)),
                child: Icon(stat.source == 'ai' ? Icons.auto_awesome_rounded : Icons.style_rounded, color: Colors.white, size: 26),
              ),
              const SizedBox(width: AppSpacing.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(stat.title,
                              maxLines: 1, overflow: TextOverflow.ellipsis,
                              style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
                        ),
                        if (stat.due > 0)
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                            decoration: BoxDecoration(color: AppColors.review.withValues(alpha: 0.18), borderRadius: BorderRadius.circular(AppRadii.sm)),
                            child: Text('${stat.due}',
                                style: const TextStyle(color: AppColors.review, fontWeight: FontWeight.w700, fontSize: 12)),
                          ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    ClipRRect(
                      borderRadius: BorderRadius.circular(AppRadii.pill),
                      child: LinearProgressIndicator(
                        value: stat.progress,
                        minHeight: 6,
                        backgroundColor: AppColors.surfaceAlt,
                        valueColor: const AlwaysStoppedAnimation(AppColors.know),
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text('${stat.learned} из ${stat.total} выучено',
                        style: const TextStyle(color: AppColors.textMuted, fontSize: 12)),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
