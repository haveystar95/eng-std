import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/design.dart';
import '../../core/glass.dart';
import '../../data/models.dart';
import '../../data/providers.dart';
import 'session_screen.dart';

/// Home / training dashboard — glass stats + collection deck launchers.
class TrainingHomeScreen extends ConsumerWidget {
  const TrainingHomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final stats = ref.watch(statsProvider);
    final user = ref.watch(authControllerProvider).value;
    final collections = ref.watch(collectionsProvider).value ?? const <WordCollection>[];
    final firstName = user?.name.split(' ').first;
    // The real "to study now" count = due + newly introduced cards from /study/due;
    // fall back to the stats due count until that request resolves.
    final dueCards = ref.watch(dueCardsProvider).value;

    return Scaffold(
      backgroundColor: Colors.transparent,
      body: AmbientBackground(
        child: SafeArea(
          bottom: false,
          child: RefreshIndicator(
            onRefresh: () async {
              ref.invalidate(statsProvider);
              ref.invalidate(collectionsProvider);
              ref.invalidate(dueCardsProvider);
            },
            child: stats.when(
              loading: () => const Center(child: CircularProgressIndicator()),
              error: (e, _) => _errorList('$e'),
              data: (s) => ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(AppSpacing.md, AppSpacing.md, AppSpacing.md, 120),
                children: [
                  Text('Привет${firstName != null ? ', $firstName' : ''} 👋',
                          style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w800))
                      .animate()
                      .fadeIn()
                      .slideY(begin: 0.1, end: 0),
                  const SizedBox(height: 4),
                  Text('Что сегодня повторим?',
                          style: Theme.of(context).textTheme.bodyMedium?.copyWith(color: AppColors.textSecondary))
                      .animate()
                      .fadeIn(delay: 60.ms),
                  const SizedBox(height: AppSpacing.lg),
                  _MixCard(dueCount: dueCards?.length ?? s.dueToday)
                      .animate()
                      .fadeIn(delay: 100.ms)
                      .slideY(begin: 0.08, end: 0),
                  const SizedBox(height: AppSpacing.md),
                  _StatsCard(stats: s).animate().fadeIn(delay: 160.ms).slideY(begin: 0.08, end: 0),
                  const SizedBox(height: AppSpacing.lg),
                  Text('По коллекциям',
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
                  const SizedBox(height: AppSpacing.md),
                  if (collections.isEmpty)
                    _emptyHint(context)
                  else
                    ...collections.asMap().entries.map((e) => Padding(
                          padding: const EdgeInsets.only(bottom: 12),
                          child: _CollectionRow(collection: e.value, index: e.key)
                              .animate()
                              .fadeIn(delay: (220 + 40 * e.key).ms)
                              .slideY(begin: 0.08, end: 0),
                        )),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _emptyHint(BuildContext context) => GlassCard(
        child: Row(
          children: [
            const Icon(Icons.auto_awesome_rounded, color: AppColors.accent, size: 24),
            const SizedBox(width: 12),
            Expanded(
              child: Text('Сгенерируй коллекцию во вкладке «Коллекции»',
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(color: AppColors.textSecondary)),
            ),
          ],
        ),
      );

  Widget _errorList(String msg) => ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md),
        children: [
          const SizedBox(height: 120),
          const Icon(Icons.cloud_off_rounded, size: 44, color: AppColors.textMuted),
          const SizedBox(height: 12),
          Center(child: Text(msg, textAlign: TextAlign.center, style: const TextStyle(color: AppColors.textMuted))),
        ],
      );
}

/// Glass dashboard: three headline metrics + a streak / reviews footer.
class _StatsCard extends StatelessWidget {
  const _StatsCard({required this.stats});
  final Stats stats;

  @override
  Widget build(BuildContext context) {
    return GlassCard(
      padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md, vertical: AppSpacing.lg),
      child: Column(
        children: [
          Row(
            children: [
              _metric('${stats.learned}', 'Выучено', AppColors.know),
              _divider(),
              _metric('${stats.mastered}', 'Освоено', AppColors.accent),
              _divider(),
              _metric('${stats.totalWords}', 'Всего слов', AppColors.primary),
            ],
          ),
          const SizedBox(height: AppSpacing.md),
          Divider(height: 1, color: Colors.white.withValues(alpha: 0.10)),
          const SizedBox(height: AppSpacing.md),
          Row(
            children: [
              _footChip('🔥', 'Серия', _plural(stats.streakDays, 'день', 'дня', 'дней')),
              const SizedBox(width: 10),
              _footChip('✅', 'Сегодня', _plural(stats.reviewsTotal, 'повтор', 'повтора', 'повторов')),
            ],
          ),
        ],
      ),
    );
  }

  Widget _divider() => Container(width: 1, height: 38, color: Colors.white.withValues(alpha: 0.10));

  Widget _metric(String value, String label, Color c) => Expanded(
        child: Column(
          children: [
            Text(value, style: TextStyle(color: c, fontSize: 26, fontWeight: FontWeight.w800)),
            const SizedBox(height: 3),
            Text(label, style: const TextStyle(color: AppColors.textSecondary, fontSize: 12)),
          ],
        ),
      );

  Widget _footChip(String emoji, String label, String value) => Expanded(
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
          decoration: BoxDecoration(
            color: Colors.white.withValues(alpha: 0.06),
            borderRadius: BorderRadius.circular(AppRadii.md),
            border: Border.all(color: Colors.white.withValues(alpha: 0.10)),
          ),
          child: Row(
            children: [
              Text(emoji, style: const TextStyle(fontSize: 18)),
              const SizedBox(width: 8),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(label, style: const TextStyle(color: AppColors.textMuted, fontSize: 11)),
                    Text(value, style: const TextStyle(color: AppColors.textPrimary, fontSize: 13, fontWeight: FontWeight.w700)),
                  ],
                ),
              ),
            ],
          ),
        ),
      );

  String _plural(int n, String one, String few, String many) {
    final mod10 = n % 10, mod100 = n % 100;
    final String word;
    if (mod10 == 1 && mod100 != 11) {
      word = one;
    } else if (mod10 >= 2 && mod10 <= 4 && (mod100 < 10 || mod100 >= 20)) {
      word = few;
    } else {
      word = many;
    }
    return '$n $word';
  }
}

class _MixCard extends StatelessWidget {
  const _MixCard({required this.dueCount});
  final int dueCount;

  @override
  Widget build(BuildContext context) {
    return SpringTap(
      onTap: () => Navigator.of(context).push(MaterialPageRoute(
        builder: (_) => const SessionScreen(title: 'Занятие', shuffle: true),
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
              width: 56,
              height: 56,
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: 0.22),
                borderRadius: BorderRadius.circular(AppRadii.md),
              ),
              child: Icon(dueCount > 0 ? Icons.bolt_rounded : Icons.check_rounded, color: Colors.white, size: 28),
            ),
            const SizedBox(width: AppSpacing.md),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(dueCount > 0 ? 'Заниматься' : 'На сегодня всё готово',
                      style: const TextStyle(color: Colors.white, fontSize: 19, fontWeight: FontWeight.w800)),
                  const SizedBox(height: 2),
                  Text(dueCount > 0 ? '$dueCount карточек: повторение и новые' : 'Свободная тренировка из всех слов',
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
  const _CollectionRow({required this.collection, required this.index});
  final WordCollection collection;
  final int index;

  @override
  Widget build(BuildContext context) {
    return GlassCard(
      padding: const EdgeInsets.all(AppSpacing.md),
      onTap: () => Navigator.of(context).push(MaterialPageRoute(
        builder: (_) => SessionScreen(
          title: collection.title,
          collectionId: collection.id,
          shuffle: true,
        ),
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
                : Icon(collection.isAi ? Icons.auto_awesome_rounded : Icons.style_rounded, color: Colors.white, size: 26),
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
                const SizedBox(height: 4),
                Text('${collection.wordsCount} слов',
                    style: const TextStyle(color: AppColors.textMuted, fontSize: 12)),
              ],
            ),
          ),
          const Icon(Icons.play_circle_outline_rounded, color: AppColors.primary),
        ],
      ),
    );
  }
}
