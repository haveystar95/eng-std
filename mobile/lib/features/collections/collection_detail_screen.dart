import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/design.dart';
import '../../core/glass.dart';
import '../../core/pronouncer.dart';
import '../../data/models.dart';
import '../../data/providers.dart';
import 'word_edit_dialog.dart';

class CollectionDetailScreen extends ConsumerStatefulWidget {
  const CollectionDetailScreen({super.key, required this.collectionId, required this.title});

  final String collectionId;
  final String title;

  @override
  ConsumerState<CollectionDetailScreen> createState() => _CollectionDetailScreenState();
}

class _CollectionDetailScreenState extends ConsumerState<CollectionDetailScreen> {
  final _pronouncer = Pronouncer();

  Future<void> _speak(Word word) async {
    AppFeedback.tap();
    final target = ref.read(authControllerProvider).value?.profile?.targetLanguage ?? 'en';
    await _pronouncer.speak(word, targetLang: target);
  }

  Future<void> _delete(Word word) async {
    await ref.read(apiClientProvider).removeWord(widget.collectionId, word.termId);
    ref.invalidate(collectionWordsProvider(widget.collectionId));
    ref.invalidate(collectionsProvider);
    ref.invalidate(statsProvider);
  }

  @override
  Widget build(BuildContext context) {
    final words = ref.watch(collectionWordsProvider(widget.collectionId));
    return Scaffold(
      extendBodyBehindAppBar: true,
      backgroundColor: Colors.transparent,
      appBar: AppBar(title: Text(widget.title)),
      floatingActionButton: _AddWordFab(
        onTap: () => showWordEditor(context, ref, collectionId: widget.collectionId),
      ),
      body: AmbientBackground(
        child: SafeArea(
          child: words.when(
            loading: () => const Center(child: CircularProgressIndicator()),
            error: (e, _) => Center(child: Text('Ошибка: $e', style: const TextStyle(color: AppColors.textSecondary))),
            data: (items) => items.isEmpty
                ? _empty(context)
                : ListView.separated(
                    padding: const EdgeInsets.fromLTRB(AppSpacing.md, AppSpacing.sm, AppSpacing.md, 110),
                    itemCount: items.length,
                    separatorBuilder: (_, _) => const SizedBox(height: 10),
                    itemBuilder: (context, i) {
                      final w = items[i];
                      return Dismissible(
                        key: ValueKey(w.id),
                        direction: DismissDirection.endToStart,
                        background: Container(
                          alignment: Alignment.centerRight,
                          padding: const EdgeInsets.only(right: 24),
                          decoration: BoxDecoration(
                            color: AppColors.danger.withValues(alpha: 0.85),
                            borderRadius: BorderRadius.circular(22),
                          ),
                          child: const Icon(Icons.delete_outline_rounded, color: Colors.white),
                        ),
                        confirmDismiss: (_) async {
                          AppFeedback.warn();
                          await _delete(w);
                          return true;
                        },
                        child: _WordCard(
                          word: w,
                          onSpeak: () => _speak(w),
                          onEdit: () => showWordEditor(context, ref, collectionId: widget.collectionId, existing: w),
                        ),
                      ).animate().fadeIn(delay: (30 * i).ms).slideY(begin: 0.06, end: 0);
                    },
                  ),
          ),
        ),
      ),
    );
  }

  Widget _empty(BuildContext context) => Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 88,
              height: 88,
              decoration: BoxDecoration(color: AppColors.primary.withValues(alpha: 0.14), shape: BoxShape.circle),
              child: const Icon(Icons.translate_rounded, color: AppColors.primary, size: 40),
            ),
            const SizedBox(height: AppSpacing.md),
            Text('Слов пока нет', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
            const SizedBox(height: 6),
            const Text('Нажми «+ Слово», чтобы добавить', style: TextStyle(color: AppColors.textSecondary)),
          ],
        ),
      );
}

class _AddWordFab extends StatelessWidget {
  const _AddWordFab({required this.onTap});
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return SpringTap(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 15),
        decoration: BoxDecoration(
          gradient: AppGradients.brand,
          borderRadius: BorderRadius.circular(AppRadii.pill),
          boxShadow: AppShadows.glow(AppColors.primary),
        ),
        child: const Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.add_rounded, color: Colors.white, size: 20),
            SizedBox(width: 8),
            Text('Слово', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 15)),
          ],
        ),
      ),
    );
  }
}

class _WordCard extends StatelessWidget {
  const _WordCard({required this.word, required this.onSpeak, required this.onEdit});
  final Word word;
  final VoidCallback onSpeak, onEdit;

  @override
  Widget build(BuildContext context) {
    return GlassCard(
      padding: const EdgeInsets.all(AppSpacing.md),
      onTap: onEdit,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(word.term,
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
              ),
              if (word.type == 'phrase')
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.08),
                    borderRadius: BorderRadius.circular(AppRadii.sm),
                  ),
                  child: Text('фраза',
                      style: Theme.of(context).textTheme.labelSmall?.copyWith(color: AppColors.textSecondary, fontWeight: FontWeight.w700)),
                ),
              const SizedBox(width: 4),
              SpringTap(
                feedback: false,
                scale: 0.85,
                onTap: onSpeak,
                child: Container(
                  width: 38,
                  height: 38,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: Colors.white.withValues(alpha: 0.06),
                    border: Border.all(color: Colors.white.withValues(alpha: 0.14)),
                  ),
                  child: const Icon(Icons.volume_up_rounded, color: AppColors.primary, size: 20),
                ),
              ),
            ],
          ),
          const SizedBox(height: 2),
          Text(word.translation, style: Theme.of(context).textTheme.bodyLarge?.copyWith(color: AppColors.primary, fontWeight: FontWeight.w600)),
          if (word.transcription != null && word.transcription!.isNotEmpty)
            Text('/${word.transcription}/', style: Theme.of(context).textTheme.bodySmall?.copyWith(color: AppColors.textMuted)),
          if (word.example != null && word.example!.isNotEmpty) ...[
            const SizedBox(height: 8),
            Text('“${word.example}”',
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(fontStyle: FontStyle.italic, color: AppColors.textSecondary)),
          ],
        ],
      ),
    );
  }
}
