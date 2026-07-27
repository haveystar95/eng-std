import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/design.dart';
import '../../data/models.dart';
import '../../data/providers.dart';

const _emojis = [
  '📚', '✨', '☕', '✈️', '💼', '🏦', '🍽️', '🏥',
  '🏠', '🛒', '🎓', '💬', '🌍', '⚽', '🎨', '🎵',
  '💻', '🔬', '🚗', '🏋️', '🧳', '📱', '💊', '🐶',
  '🌦️', '🎬', '🍕', '👔', '💰', '📅', '❤️', '🔥',
];

/// Create (existing == null) or edit a collection's title + emoji.
Future<void> showCollectionEditor(BuildContext context, WidgetRef ref, {WordCollection? existing}) async {
  final titleCtrl = TextEditingController(text: existing?.title ?? '');
  String emoji = existing?.emoji ?? '📚';

  await showDialog<void>(
    context: context,
    builder: (context) => StatefulBuilder(
      builder: (context, setState) => AlertDialog(
        title: Text(existing == null ? 'Новая коллекция' : 'Изменить коллекцию'),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(
                    width: 56, height: 56,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      gradient: AppGradients.brand,
                      borderRadius: BorderRadius.circular(AppRadii.md),
                    ),
                    child: Text(emoji, style: const TextStyle(fontSize: 30)),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: TextField(
                      controller: titleCtrl,
                      autofocus: true,
                      decoration: const InputDecoration(labelText: 'Название', hintText: 'напр.: Путешествия'),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              const Text('Эмодзи', style: TextStyle(color: AppColors.textSecondary, fontSize: 13, fontWeight: FontWeight.w600)),
              const SizedBox(height: 8),
              Wrap(
                spacing: 6,
                runSpacing: 6,
                children: _emojis.map((e) {
                  final selected = e == emoji;
                  return GestureDetector(
                    onTap: () => setState(() => emoji = e),
                    child: Container(
                      width: 42, height: 42,
                      alignment: Alignment.center,
                      decoration: BoxDecoration(
                        color: selected ? AppColors.primary.withValues(alpha: 0.25) : AppColors.surfaceAlt,
                        borderRadius: BorderRadius.circular(AppRadii.sm),
                        border: selected ? Border.all(color: AppColors.primary, width: 1.5) : null,
                      ),
                      child: Text(e, style: const TextStyle(fontSize: 22)),
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
              final title = titleCtrl.text.trim();
              if (title.isEmpty) return;
              Navigator.pop(context);
              final api = ref.read(apiClientProvider);
              try {
                if (existing == null) {
                  await api.createCollection(title: title, emoji: emoji);
                } else {
                  await api.updateCollection(existing.id, title: title, emoji: emoji);
                }
                ref.invalidate(collectionsProvider);
                ref.invalidate(statsProvider);
              } catch (e) {
                if (context.mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Ошибка: $e')));
                }
              }
            },
            child: Text(existing == null ? 'Создать' : 'Сохранить'),
          ),
        ],
      ),
    ),
  );
}
