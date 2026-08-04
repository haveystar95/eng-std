import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/design.dart';
import '../../core/glass.dart';
import '../../core/pronouncer.dart';
import '../../data/models.dart';
import '../../data/providers.dart';
import '../training/triage_screen.dart';
import 'word_edit_dialog.dart';
import 'word_media.dart';

class CollectionDetailScreen extends ConsumerStatefulWidget {
  const CollectionDetailScreen({
    super.key,
    required this.collectionId,
    required this.title,
    this.offerTriage = false,
  });

  final String collectionId;
  final String title;

  /// First-contact nudge: when arriving from a freshly generated collection, offer «Разобрать»
  /// (triage) at the top so the user's first move is to sort what they already know.
  final bool offerTriage;

  @override
  ConsumerState<CollectionDetailScreen> createState() => _CollectionDetailScreenState();
}

class _CollectionDetailScreenState extends ConsumerState<CollectionDetailScreen> {
  final _pronouncer = Pronouncer();
  late bool _showTriagePrompt = widget.offerTriage;

  void _openTriage() {
    AppFeedback.tap();
    Navigator.of(context).push(MaterialPageRoute(
      builder: (_) => TriageScreen(collectionId: widget.collectionId, title: widget.title),
    ));
  }

  Future<void> _speak(Word word) async {
    AppFeedback.tap();
    final target = ref.read(authControllerProvider).value?.profile?.targetLanguage ?? 'en';
    await _pronouncer.speak(word, targetLang: target);
  }

  Future<void> _delete(Word word) async {
    await ref.read(apiClientProvider).removeWord(widget.collectionId, word.termId);
    // The item tombstone comes back through sync and drops the local row.
    ref.read(syncServiceProvider).sync();
  }

  @override
  Widget build(BuildContext context) {
    final words = ref.watch(collectionWordsProvider(widget.collectionId));
    return Scaffold(
      extendBodyBehindAppBar: true,
      backgroundColor: Colors.transparent,
      appBar: AppBar(
        title: Text(widget.title),
        actions: [
          IconButton(
            tooltip: 'Разобрать (триаж)',
            icon: const Icon(Icons.style_outlined),
            onPressed: _openTriage,
          ),
        ],
      ),
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
                    // A leading slot for the «Разобрать» banner, only when it's showing.
                    itemCount: items.length + (_showTriagePrompt ? 1 : 0),
                    separatorBuilder: (_, _) => const SizedBox(height: 10),
                    itemBuilder: (context, rawIndex) {
                      if (_showTriagePrompt && rawIndex == 0) {
                        return _TriageBanner(
                          onStart: _openTriage,
                          onDismiss: () => setState(() => _showTriagePrompt = false),
                        ).animate().fadeIn().slideY(begin: -0.1, end: 0);
                      }
                      final i = rawIndex - (_showTriagePrompt ? 1 : 0);
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

/// First-contact nudge on a freshly generated collection: sort what you already know before study.
class _TriageBanner extends StatelessWidget {
  const _TriageBanner({required this.onStart, required this.onDismiss});
  final VoidCallback onStart;
  final VoidCallback onDismiss;

  @override
  Widget build(BuildContext context) {
    return GlassCard(
      tint: AppColors.accent,
      padding: const EdgeInsets.all(AppSpacing.md),
      child: Row(
        children: [
          const Icon(Icons.style_rounded, color: AppColors.accent, size: 24),
          const SizedBox(width: 12),
          const Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('Разбери коллекцию',
                    style: TextStyle(color: AppColors.textPrimary, fontWeight: FontWeight.w800, fontSize: 15)),
                SizedBox(height: 2),
                Text('Отметь, что уже знаешь — остальное пойдёт в тренировку',
                    style: TextStyle(color: AppColors.textSecondary, fontSize: 12)),
              ],
            ),
          ),
          const SizedBox(width: 8),
          SpringTap(
            onTap: onStart,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
              decoration: BoxDecoration(gradient: AppGradients.brand, borderRadius: BorderRadius.circular(AppRadii.pill)),
              child: const Text('Начать', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 13)),
            ),
          ),
          SpringTap(
            feedback: false,
            onTap: onDismiss,
            child: const Padding(
              padding: EdgeInsets.only(left: 4),
              child: Icon(Icons.close_rounded, color: AppColors.textMuted, size: 18),
            ),
          ),
        ],
      ),
    );
  }
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

/// The per-word learning status shown on the collection view, mapped from the local progress
/// state. A not-started word (no progress row, or `new`) shows no badge — the absence is the
/// status. `review`/`known` mirror the "Выучено"/"Усвоено" wording used on the stats card.
({Color color, String label})? _statusOf(String? status) => switch (status) {
      'known' => (color: AppColors.accent, label: 'Усвоено'),
      'review' => (color: AppColors.know, label: 'Выучено'),
      'learning' || 'relearning' => (color: AppColors.review, label: 'Учу'),
      'unknown' => (color: AppColors.textMuted, label: 'Не знаю'), // triaged «не знаю»: still new
      _ => null,
    };

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
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              TermThumb(word: word),
              const SizedBox(width: AppSpacing.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: Text(word.term,
                              style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
                        ),
                        const SizedBox(width: 6),
                        TypeBadge(type: word.type),
                        const SizedBox(width: 4),
                        SpringTap(
                          feedback: false,
                          scale: 0.85,
                          onTap: onSpeak,
                          child: Container(
                            width: 36,
                            height: 36,
                            alignment: Alignment.center,
                            decoration: BoxDecoration(
                              shape: BoxShape.circle,
                              color: Colors.white.withValues(alpha: 0.06),
                              border: Border.all(color: Colors.white.withValues(alpha: 0.14)),
                            ),
                            child: const Icon(Icons.volume_up_rounded, color: AppColors.primary, size: 19),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 2),
                    Text(word.translation,
                        style: Theme.of(context).textTheme.bodyLarge?.copyWith(color: AppColors.primary, fontWeight: FontWeight.w600)),
                    if (word.transcription != null && word.transcription!.isNotEmpty)
                      Text('/${word.transcription}/',
                          style: Theme.of(context).textTheme.bodySmall?.copyWith(color: AppColors.textMuted)),
                  ],
                ),
              ),
            ],
          ),
          if (_statusOf(word.status) case final st?) ...[
            const SizedBox(height: 8),
            Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(width: 8, height: 8, decoration: BoxDecoration(color: st.color, shape: BoxShape.circle)),
                const SizedBox(width: 6),
                Text(st.label,
                    style: Theme.of(context).textTheme.labelSmall?.copyWith(color: st.color, fontWeight: FontWeight.w700)),
              ],
            ),
          ],
          if (word.example != null && word.example!.isNotEmpty) ...[
            const SizedBox(height: 8),
            Text('“${word.example}”',
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(fontStyle: FontStyle.italic, color: AppColors.textSecondary)),
          ],
          if (word.imageAuthor != null) ...[
            const SizedBox(height: 8),
            PexelsCredit(author: word.imageAuthor, authorUrl: word.imageAuthorUrl),
          ],
        ],
      ),
    );
  }
}
