import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/design.dart';
import '../../core/glass.dart';
import '../../core/languages.dart';
import '../../data/providers.dart';

Future<void> showGenerateDialog(BuildContext context, WidgetRef ref) async {
  final topicCtrl = TextEditingController();
  final profile = ref.read(authControllerProvider).value?.profile;
  final source = languageByCode(profile?.nativeLanguage ?? 'ru');
  final target = languageByCode(profile?.targetLanguage ?? 'en');
  final levels = <String>{profile?.cefrLevel ?? 'B1'};
  int size = 15;

  await showDialog<void>(
    context: context,
    barrierColor: Colors.black.withValues(alpha: 0.72),
    builder: (context) => StatefulBuilder(
      builder: (context, setState) => Dialog(
        backgroundColor: Colors.transparent,
        elevation: 0,
        insetPadding: const EdgeInsets.symmetric(horizontal: AppSpacing.lg, vertical: 40),
        child: GlassCard(
          solid: true,
          padding: const EdgeInsets.all(AppSpacing.lg),
          radius: 28,
          child: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Container(
                      width: 40,
                      height: 40,
                      alignment: Alignment.center,
                      decoration: BoxDecoration(gradient: AppGradients.brand, borderRadius: BorderRadius.circular(AppRadii.sm)),
                      child: const Icon(Icons.auto_awesome_rounded, color: Colors.white, size: 22),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text('Новая коллекция',
                              style: TextStyle(color: AppColors.textPrimary, fontSize: 19, fontWeight: FontWeight.w800)),
                          const SizedBox(height: 2),
                          Text('${target.flag} ${target.name} → ${source.flag} ${source.name}',
                              style: const TextStyle(color: AppColors.textSecondary, fontSize: 12, fontWeight: FontWeight.w600)),
                        ],
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: AppSpacing.md),
                TextField(
                  controller: topicCtrl,
                  autofocus: true,
                  minLines: 1,
                  maxLines: 3,
                  style: const TextStyle(color: AppColors.textPrimary),
                  decoration: const InputDecoration(
                    labelText: 'Тема или ситуация',
                    hintText: 'напр.: иду открывать счёт в банке',
                  ),
                ),
                const SizedBox(height: 8),
                const Text('Опиши ситуацию или цель — ИИ подберёт нужные слова и фразы',
                    style: TextStyle(color: AppColors.textMuted, fontSize: 12)),
                const SizedBox(height: AppSpacing.md),
                const Text('Уровни (можно несколько)',
                    style: TextStyle(color: AppColors.textSecondary, fontSize: 13, fontWeight: FontWeight.w700)),
                const SizedBox(height: 10),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: kCefrLevels.map((lvl) {
                    final selected = levels.contains(lvl);
                    return GlassChip(
                      label: lvl,
                      selected: selected,
                      onTap: () => setState(() {
                        if (selected) {
                          if (levels.length > 1) levels.remove(lvl);
                        } else {
                          levels.add(lvl);
                        }
                      }),
                    );
                  }).toList(),
                ),
                const SizedBox(height: AppSpacing.md),
                Row(
                  children: [
                    const Text('Слов: ', style: TextStyle(color: AppColors.textSecondary)),
                    Text('$size', style: const TextStyle(color: AppColors.textPrimary, fontWeight: FontWeight.w800, fontSize: 16)),
                  ],
                ),
                SliderTheme(
                  data: SliderTheme.of(context).copyWith(
                    activeTrackColor: AppColors.primary,
                    inactiveTrackColor: Colors.white.withValues(alpha: 0.12),
                    thumbColor: AppColors.primary,
                    overlayColor: AppColors.primary.withValues(alpha: 0.18),
                  ),
                  child: Slider(
                    value: size.toDouble(),
                    min: 8,
                    max: 25,
                    divisions: 17,
                    label: '$size',
                    onChanged: (v) => setState(() => size = v.round()),
                  ),
                ),
                const SizedBox(height: AppSpacing.sm),
                Row(
                  children: [
                    Expanded(
                      child: GlassSecondaryButton(label: 'Отмена', onTap: () => Navigator.pop(context)),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      flex: 2,
                      child: GlassButton(
                        label: 'Создать',
                        icon: Icons.auto_awesome_rounded,
                        onTap: () async {
                          final topic = topicCtrl.text.trim();
                          if (topic.isEmpty) {
                            AppFeedback.warn();
                            return;
                          }
                          Navigator.pop(context);
                          await _runGeneration(
                            context,
                            ref,
                            topic: topic,
                            levels: levels.toList(),
                            size: size,
                            sourceLang: source.code,
                            targetLang: target.code,
                          );
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

Future<void> _runGeneration(
  BuildContext context,
  WidgetRef ref, {
  required String topic,
  required List<String> levels,
  required int size,
  required String sourceLang,
  required String targetLang,
}) async {
  final messenger = ScaffoldMessenger.of(context);
  final api = ref.read(apiClientProvider);

  messenger.showSnackBar(
    const SnackBar(content: Text('ИИ генерирует коллекцию…'), duration: Duration(seconds: 8)),
  );

  try {
    final jobId = await api.generateCollection(
      topic: topic,
      levels: levels,
      size: size,
      sourceLang: sourceLang,
      targetLang: targetLang,
    );

    for (var i = 0; i < 30; i++) {
      await Future<void>.delayed(const Duration(seconds: 2));
      final s = await api.jobStatus(jobId);
      if (s.status == 'done') {
        ref.invalidate(collectionsProvider);
        ref.invalidate(statsProvider);
        ref.invalidate(dueCardsProvider);
        ref.invalidate(collectionsProgressProvider);
        AppFeedback.success();
        messenger
          ..clearSnackBars()
          ..showSnackBar(const SnackBar(content: Text('Коллекция готова! 🎉')));
        return;
      }
      if (s.status == 'failed') {
        AppFeedback.warn();
        messenger
          ..clearSnackBars()
          ..showSnackBar(SnackBar(content: Text('Ошибка генерации: ${s.error ?? ''}')));
        return;
      }
    }
    messenger
      ..clearSnackBars()
      ..showSnackBar(const SnackBar(content: Text('Генерация ещё идёт — обнови список позже.')));
  } catch (e) {
    AppFeedback.warn();
    messenger
      ..clearSnackBars()
      ..showSnackBar(SnackBar(content: Text('Ошибка: $e')));
  }
}
