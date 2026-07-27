import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/design.dart';
import '../../data/providers.dart';

Future<void> showGenerateDialog(BuildContext context, WidgetRef ref) async {
  final topicCtrl = TextEditingController();
  final levels = <String>{'B1'};
  int size = 15;

  await showDialog<void>(
    context: context,
    builder: (context) => StatefulBuilder(
      builder: (context, setState) => AlertDialog(
        title: const Text('Новая коллекция (ИИ)'),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              TextField(
                controller: topicCtrl,
                autofocus: true,
                minLines: 1,
                maxLines: 3,
                decoration: const InputDecoration(
                  labelText: 'Тема или ситуация',
                  hintText: 'напр.: иду открывать счёт в банке',
                ),
              ),
              const SizedBox(height: 6),
              const Text('Опиши ситуацию или цель — ИИ подберёт нужные слова и фразы',
                  style: TextStyle(color: AppColors.textMuted, fontSize: 12)),
              const SizedBox(height: 16),
              const Text('Уровни (можно несколько)',
                  style: TextStyle(color: AppColors.textSecondary, fontSize: 13, fontWeight: FontWeight.w600)),
              const SizedBox(height: 8),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'].map((lvl) {
                  final selected = levels.contains(lvl);
                  return GestureDetector(
                    onTap: () => setState(() {
                      if (selected) {
                        if (levels.length > 1) levels.remove(lvl);
                      } else {
                        levels.add(lvl);
                      }
                    }),
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                      decoration: BoxDecoration(
                        color: selected ? AppColors.primary : AppColors.surfaceAlt,
                        borderRadius: BorderRadius.circular(AppRadii.pill),
                      ),
                      child: Text(lvl,
                          style: TextStyle(
                            color: selected ? Colors.white : AppColors.textSecondary,
                            fontWeight: FontWeight.w700,
                          )),
                    ),
                  );
                }).toList(),
              ),
              const SizedBox(height: 16),
              Row(
                children: [
                  const Text('Слов: ', style: TextStyle(color: AppColors.textSecondary)),
                  Text('$size', style: const TextStyle(fontWeight: FontWeight.w700)),
                ],
              ),
              Slider(
                value: size.toDouble(),
                min: 5,
                max: 30,
                divisions: 5,
                label: '$size',
                onChanged: (v) => setState(() => size = v.round()),
              ),
            ],
          ),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context), child: const Text('Отмена')),
          FilledButton(
            style: FilledButton.styleFrom(minimumSize: const Size(88, 44)),
            onPressed: () async {
              final topic = topicCtrl.text.trim();
              if (topic.isEmpty) return;
              Navigator.pop(context);
              await _runGeneration(context, ref, topic: topic, levels: levels.toList(), size: size);
            },
            child: const Text('Создать'),
          ),
        ],
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
}) async {
  final messenger = ScaffoldMessenger.of(context);
  final api = ref.read(apiClientProvider);

  messenger.showSnackBar(
    const SnackBar(content: Text('ИИ генерирует коллекцию…'), duration: Duration(seconds: 8)),
  );

  try {
    final jobId = await api.generateCollection(topic: topic, levels: levels, size: size);

    for (var i = 0; i < 30; i++) {
      await Future<void>.delayed(const Duration(seconds: 2));
      final s = await api.jobStatus(jobId);
      if (s.status == 'done') {
        ref.invalidate(collectionsProvider);
        ref.invalidate(statsProvider);
        ref.invalidate(dueCardsProvider);
        messenger
          ..clearSnackBars()
          ..showSnackBar(const SnackBar(content: Text('Коллекция готова! 🎉')));
        return;
      }
      if (s.status == 'failed') {
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
    messenger
      ..clearSnackBars()
      ..showSnackBar(SnackBar(content: Text('Ошибка: $e')));
  }
}
