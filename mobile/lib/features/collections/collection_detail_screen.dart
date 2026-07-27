import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_tts/flutter_tts.dart';

import '../../core/design.dart';
import '../../data/models.dart';
import '../../data/providers.dart';
import 'word_edit_dialog.dart';

class CollectionDetailScreen extends ConsumerStatefulWidget {
  const CollectionDetailScreen({super.key, required this.collectionId, required this.title});

  final int collectionId;
  final String title;

  @override
  ConsumerState<CollectionDetailScreen> createState() => _CollectionDetailScreenState();
}

class _CollectionDetailScreenState extends ConsumerState<CollectionDetailScreen> {
  final _tts = FlutterTts();

  Future<void> _speak(String term) async {
    await _tts.setLanguage('en-US');
    await _tts.setSpeechRate(0.45);
    await _tts.speak(term);
  }

  Future<void> _delete(Word word) async {
    await ref.read(apiClientProvider).deleteWord(widget.collectionId, word.id);
    ref.invalidate(collectionWordsProvider(widget.collectionId));
    ref.invalidate(collectionsProvider);
    ref.invalidate(statsProvider);
  }

  @override
  Widget build(BuildContext context) {
    final words = ref.watch(collectionWordsProvider(widget.collectionId));
    return Scaffold(
      appBar: AppBar(title: Text(widget.title)),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => showWordEditor(context, ref, collectionId: widget.collectionId),
        backgroundColor: AppColors.primary,
        foregroundColor: Colors.white,
        icon: const Icon(Icons.add_rounded),
        label: const Text('Слово', style: TextStyle(fontWeight: FontWeight.w600)),
      ),
      body: words.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(child: Text('Ошибка: $e', style: const TextStyle(color: AppColors.textSecondary))),
        data: (items) => items.isEmpty
            ? _empty(context)
            : ListView.separated(
                padding: const EdgeInsets.fromLTRB(AppSpacing.md, AppSpacing.md, AppSpacing.md, 96),
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
                      decoration: BoxDecoration(color: AppColors.danger, borderRadius: BorderRadius.circular(AppRadii.lg)),
                      child: const Icon(Icons.delete_outline_rounded, color: Colors.white),
                    ),
                    confirmDismiss: (_) async {
                      await _delete(w);
                      return true;
                    },
                    child: _WordCard(
                      word: w,
                      onSpeak: () => _speak(w.term),
                      onEdit: () => showWordEditor(context, ref, collectionId: widget.collectionId, existing: w),
                    ),
                  );
                },
              ),
      ),
    );
  }

  Widget _empty(BuildContext context) => Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.translate_rounded, size: 48, color: AppColors.textMuted),
            const SizedBox(height: 12),
            Text('Слов пока нет', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
            const SizedBox(height: 6),
            const Text('Нажми «+ Слово», чтобы добавить', style: TextStyle(color: AppColors.textSecondary)),
          ],
        ),
      );
}

class _WordCard extends StatelessWidget {
  const _WordCard({required this.word, required this.onSpeak, required this.onEdit});
  final Word word;
  final VoidCallback onSpeak, onEdit;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: AppColors.surface,
      borderRadius: BorderRadius.circular(AppRadii.lg),
      child: InkWell(
        borderRadius: BorderRadius.circular(AppRadii.lg),
        onTap: onEdit,
        child: Container(
          decoration: BoxDecoration(borderRadius: BorderRadius.circular(AppRadii.lg), boxShadow: AppShadows.card),
          padding: const EdgeInsets.all(AppSpacing.md),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(word.term, style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
                  ),
                  if (word.cefrLevel != null)
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                      decoration: BoxDecoration(color: AppColors.surfaceAlt, borderRadius: BorderRadius.circular(AppRadii.sm)),
                      child: Text(word.cefrLevel!, style: Theme.of(context).textTheme.labelSmall?.copyWith(color: AppColors.textSecondary, fontWeight: FontWeight.w700)),
                    ),
                  IconButton(
                    onPressed: onSpeak,
                    icon: const Icon(Icons.volume_up_rounded, color: AppColors.primary),
                    visualDensity: VisualDensity.compact,
                  ),
                ],
              ),
              Text(word.translation, style: Theme.of(context).textTheme.bodyLarge?.copyWith(color: AppColors.primary)),
              if (word.transcription != null && word.transcription!.isNotEmpty)
                Text('/${word.transcription}/', style: Theme.of(context).textTheme.bodySmall?.copyWith(color: AppColors.textMuted)),
              if (word.example != null && word.example!.isNotEmpty) ...[
                const SizedBox(height: 8),
                Text('“${word.example}”', style: Theme.of(context).textTheme.bodyMedium?.copyWith(fontStyle: FontStyle.italic, color: AppColors.textSecondary)),
              ],
            ],
          ),
        ),
      ),
    );
  }
}
