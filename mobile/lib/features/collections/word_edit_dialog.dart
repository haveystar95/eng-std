import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/design.dart';
import '../../core/glass.dart';
import '../../core/languages.dart';
import '../../data/models.dart';
import '../../data/providers.dart';

/// Add (existing == null) or edit a word inside a collection — glass form.
Future<void> showWordEditor(
  BuildContext context,
  WidgetRef ref, {
  required String collectionId,
  Word? existing,
}) async {
  final term = TextEditingController(text: existing?.term ?? '');
  final translation = TextEditingController(text: existing?.translation ?? '');
  final transcription = TextEditingController(text: existing?.transcription ?? '');
  final example = TextEditingController(text: existing?.example ?? '');
  String? level;

  await showDialog<void>(
    context: context,
    barrierColor: Colors.black.withValues(alpha: 0.55),
    builder: (context) => StatefulBuilder(
      builder: (context, setState) => Dialog(
        backgroundColor: Colors.transparent,
        elevation: 0,
        insetPadding: const EdgeInsets.symmetric(horizontal: AppSpacing.lg, vertical: 40),
        child: GlassCard(
          padding: const EdgeInsets.all(AppSpacing.lg),
          radius: 28,
          child: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(existing == null ? 'Новое слово' : 'Изменить слово',
                    style: const TextStyle(color: AppColors.textPrimary, fontSize: 19, fontWeight: FontWeight.w800)),
                const SizedBox(height: AppSpacing.lg),
                GlassField(controller: term, label: 'Слово / фраза', hint: 'Codebase', autofocus: true),
                const SizedBox(height: 14),
                GlassField(controller: translation, label: 'Перевод', hint: 'Кодовая база'),
                const SizedBox(height: 14),
                GlassField(controller: transcription, label: 'Транскрипция', hint: 'необязательно'),
                const SizedBox(height: 14),
                GlassField(controller: example, label: 'Пример', hint: 'необязательно', minLines: 2, maxLines: 3),
                const SizedBox(height: AppSpacing.md),
                const Text('Уровень',
                    style: TextStyle(color: AppColors.textSecondary, fontSize: 13, fontWeight: FontWeight.w600)),
                const SizedBox(height: 10),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: kCefrLevels
                      .map((l) => GlassChip(
                            label: l,
                            selected: level == l,
                            onTap: () => setState(() => level = level == l ? null : l),
                          ))
                      .toList(),
                ),
                const SizedBox(height: AppSpacing.lg),
                Row(
                  children: [
                    Expanded(child: GlassSecondaryButton(label: 'Отмена', onTap: () => Navigator.pop(context))),
                    const SizedBox(width: 12),
                    Expanded(
                      flex: 2,
                      child: GlassButton(
                        label: existing == null ? 'Добавить' : 'Сохранить',
                        icon: existing == null ? Icons.add_rounded : Icons.check_rounded,
                        onTap: () async {
                          if (term.text.trim().isEmpty || translation.text.trim().isEmpty) {
                            AppFeedback.warn();
                            return;
                          }
                          Navigator.pop(context);
                          final type = term.text.trim().contains(' ') ? 'phrase' : 'word';
                          final api = ref.read(apiClientProvider);
                          try {
                            // backend2 has no word-update; editing = remove old + add new.
                            if (existing != null) {
                              await api.removeWord(collectionId, existing.termId);
                            }
                            await api.addWord(
                              collectionId,
                              text: term.text.trim(),
                              translation: translation.text.trim(),
                              type: type,
                            );
                            ref.invalidate(collectionWordsProvider(collectionId));
                            ref.invalidate(collectionsProvider);
                            ref.invalidate(statsProvider);
                          } catch (e) {
                            AppFeedback.warn();
                            if (context.mounted) {
                              ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Ошибка: $e')));
                            }
                          }
                        },
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ).animate().fadeIn(duration: 180.ms).scale(begin: const Offset(0.92, 0.92), end: const Offset(1, 1), curve: Curves.easeOutBack),
      ),
    ),
  );
}
