import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/design.dart';
import '../../data/models.dart';
import '../../data/providers.dart';

/// Add (existing == null) or edit a word inside a collection.
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
    builder: (context) => StatefulBuilder(
      builder: (context, setState) => AlertDialog(
        title: Text(existing == null ? 'Новое слово' : 'Изменить слово'),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _field(term, 'Слово / фраза', autofocus: true),
              _field(translation, 'Перевод'),
              _field(transcription, 'Транскрипция (необязательно)'),
              _field(example, 'Пример (необязательно)', lines: 2),
              const SizedBox(height: 12),
              const Text('Уровень', style: TextStyle(color: AppColors.textSecondary, fontSize: 13, fontWeight: FontWeight.w600)),
              const SizedBox(height: 8),
              Wrap(
                spacing: 8,
                children: ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'].map((l) {
                  final selected = level == l;
                  return GestureDetector(
                    onTap: () => setState(() => level = selected ? null : l),
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                      decoration: BoxDecoration(
                        color: selected ? AppColors.primary : AppColors.surfaceAlt,
                        borderRadius: BorderRadius.circular(AppRadii.pill),
                      ),
                      child: Text(l, style: TextStyle(color: selected ? Colors.white : AppColors.textSecondary, fontWeight: FontWeight.w700)),
                    ),
                  );
                }).toList(),
              ),
            ],
          ),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context), child: const Text('Отмена')),
          FilledButton(
            style: FilledButton.styleFrom(minimumSize: const Size(88, 44)),
            onPressed: () async {
              if (term.text.trim().isEmpty || translation.text.trim().isEmpty) return;
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
                if (context.mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Ошибка: $e')));
                }
              }
            },
            child: Text(existing == null ? 'Добавить' : 'Сохранить'),
          ),
        ],
      ),
    ),
  );
}

Widget _field(TextEditingController c, String label, {bool autofocus = false, int lines = 1}) {
  return Padding(
    padding: const EdgeInsets.only(bottom: 10),
    child: TextField(
      controller: c,
      autofocus: autofocus,
      minLines: lines,
      maxLines: lines,
      decoration: InputDecoration(labelText: label),
    ),
  );
}
