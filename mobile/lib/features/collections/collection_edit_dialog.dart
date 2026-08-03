import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/design.dart';
import '../../core/glass.dart';
import '../../data/models.dart';
import '../../data/providers.dart';

const _emojis = [
  '📚', '✨', '☕', '✈️', '💼', '🏦', '🍽️', '🏥',
  '🏠', '🛒', '🎓', '💬', '🌍', '⚽', '🎨', '🎵',
  '💻', '🔬', '🚗', '🏋️', '🧳', '📱', '💊', '🐶',
  '🌦️', '🎬', '🍕', '👔', '💰', '📅', '❤️', '🔥',
];

/// Create (existing == null) or edit a collection's title + emoji — glass form.
Future<void> showCollectionEditor(BuildContext context, WidgetRef ref, {WordCollection? existing}) async {
  final titleCtrl = TextEditingController(text: existing?.title ?? '');
  String emoji = existing?.emoji ?? '📚';
  final profile = ref.read(authControllerProvider).value?.profile;

  await showDialog<void>(
    context: context,
    barrierColor: Colors.black.withValues(alpha: 0.72),
    builder: (context) => StatefulBuilder(
      builder: (context, setState) => Dialog(
        backgroundColor: Colors.transparent,
        elevation: 0,
        // Dialog already adds MediaQuery.viewInsets, so this stays fixed.
        insetPadding: const EdgeInsets.symmetric(horizontal: AppSpacing.lg, vertical: 24),
        child: GlassCard(
          solid: true,
          padding: const EdgeInsets.all(AppSpacing.lg),
          radius: 28,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(existing == null ? 'Новая коллекция' : 'Изменить коллекцию',
                  style: const TextStyle(color: AppColors.textPrimary, fontSize: 19, fontWeight: FontWeight.w800)),
              const SizedBox(height: AppSpacing.md),
              Flexible(
                child: SingleChildScrollView(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Container(
                            width: 60,
                            height: 60,
                            alignment: Alignment.center,
                            decoration: BoxDecoration(
                              gradient: AppGradients.brand,
                              borderRadius: BorderRadius.circular(AppRadii.md),
                              boxShadow: AppShadows.glow(AppColors.primary),
                            ),
                            child: Text(emoji, style: const TextStyle(fontSize: 32)),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: GlassField(
                              controller: titleCtrl,
                              label: 'Название',
                              hint: 'напр.: Путешествия',
                              autofocus: true,
                              textCapitalization: TextCapitalization.sentences,
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: AppSpacing.md),
                      const Text('Эмодзи',
                          style: TextStyle(color: AppColors.textSecondary, fontSize: 13, fontWeight: FontWeight.w600)),
                      const SizedBox(height: 10),
                      Wrap(
                        spacing: 8,
                        runSpacing: 8,
                        children: _emojis.map((e) {
                          final selected = e == emoji;
                          return SpringTap(
                            scale: 0.85,
                            onTap: () => setState(() => emoji = e),
                            child: AnimatedContainer(
                              duration: const Duration(milliseconds: 160),
                              width: 44,
                              height: 44,
                              alignment: Alignment.center,
                              decoration: BoxDecoration(
                                color: selected ? AppColors.primary.withValues(alpha: 0.28) : Colors.white.withValues(alpha: 0.06),
                                borderRadius: BorderRadius.circular(AppRadii.sm),
                                border: Border.all(
                                  color: selected ? AppColors.primary : Colors.white.withValues(alpha: 0.12),
                                  width: selected ? 1.5 : 1,
                                ),
                              ),
                              child: Text(e, style: const TextStyle(fontSize: 22)),
                            ),
                          );
                        }).toList(),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: AppSpacing.lg),
              Row(
                children: [
                  Expanded(child: GlassSecondaryButton(label: 'Отмена', onTap: () => Navigator.pop(context))),
                  const SizedBox(width: 12),
                  Expanded(
                    flex: 2,
                    child: GlassButton(
                        label: existing == null ? 'Создать' : 'Сохранить',
                        icon: existing == null ? Icons.add_rounded : Icons.check_rounded,
                        onTap: () async {
                          final title = titleCtrl.text.trim();
                          if (title.isEmpty) {
                            AppFeedback.warn();
                            return;
                          }
                          Navigator.pop(context);
                          final api = ref.read(apiClientProvider);
                          try {
                            // Emoji is chosen locally for the tile look; backend2 has no emoji field.
                            if (existing == null) {
                              await api.createCollection(
                                title: title,
                                sourceLang: profile?.nativeLanguage,
                                targetLang: profile?.targetLanguage,
                              );
                            } else {
                              await api.updateCollection(existing.id, title: title);
                            }
                            // Pull the new/updated collection into the local mirror; the read
                            // streams update when it lands.
                            ref.read(syncServiceProvider).sync();
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
        ).animate().fadeIn(duration: 180.ms).scale(begin: const Offset(0.92, 0.92), end: const Offset(1, 1), curve: Curves.easeOutBack),
      ),
    ),
  );
}
