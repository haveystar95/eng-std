import 'package:flutter/material.dart';

import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/native_text.dart';

import '../../../../data/plan/day_window.dart';
import '../../../../data/plan/plan_models.dart';
import 'window_bits.dart';
import 'window_returns.dart';

/// ВКЛАДКА «СЛОВА» (кадры 23-0a…0d): сетка в две колонки через 12. Карточка — фото 4:3 со скруглением
/// 12 (тон → фото), маркер 14 в углу на бумажной подложке; через 8 слово Literata 22; через 4 чтение
/// кириллицей 13 и перевод 15 столбиком, справа «прослушать» 28. Тап по карточке открывает шит слова
/// 23-0e. Длинное слово переносится, а не режется троеточием.
class WindowWords extends StatelessWidget {
  const WindowWords({super.key, required this.words, required this.onListen, required this.onOpen, this.imageOf});

  final List<WindowWord> words;
  final WindowListen onListen;
  final ValueChanged<WindowWord> onOpen;

  /// Фото сцены-источника для полосы группы «Вернулось из дня N» (наряд FIX-3 §4).
  final PlanImage? Function(String sceneId)? imageOf;

  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (context, box) {
      final width = (box.maxWidth - 12) / 2;
      // Свои слова дня — сеткой без заголовка; вернувшиеся — своей сеткой под заголовком группы.
      final grouped = windowReturnGroups(words, sourceOf: (w) => w.source, sceneOf: (w) => w.scene);
      Widget grid(List<WindowWord> items) => Wrap(
        spacing: 12,
        runSpacing: 12,
        children: [
          for (final word in items)
            SizedBox(width: width, child: WindowWordCard(word: word, onListen: onListen, onOpen: () => onOpen(word))),
        ],
      );

      return Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          grid(grouped.own),
          for (final group in grouped.returns) ...[
            WindowReturnHeading(
              scene: group.scene,
              image: group.scene == null ? null : imageOf?.call(group.scene!.id),
            ),
            grid(group.items),
          ],
        ],
      );
    },
  );
}

class WindowWordCard extends StatelessWidget {
  const WindowWordCard({super.key, required this.word, required this.onListen, required this.onOpen});

  final WindowWord word;
  final WindowListen onListen;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) => GestureDetector(
    behavior: HitTestBehavior.opaque,
    onTap: () {
      AppHaptics.light();
      onOpen();
    },
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        ClipRRect(
          borderRadius: BorderRadius.circular(12),
          child: AspectRatio(
            aspectRatio: 4 / 3,
            child: Stack(
              fit: StackFit.expand,
              children: [
                WindowPhoto(url: word.image?.url, tone: AppColors.wireTone(word.imageTone) ?? AppColors.photoSlot),
                Positioned(top: 8, right: 8, child: WindowUnitMarker(state: word.state, onPhoto: true)),
              ],
            ),
          ),
        ),
        const SizedBox(height: 8),
        Text(word.term, style: AppTextWindow.target),
        const SizedBox(height: 4),
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (word.pronunciation case final reading?) ...[
                    Text(reading, style: AppTextWindow.reading),
                    const SizedBox(height: 4),
                  ],
                  Text(context.nativeText(word.translation), style: AppTextWindow.translation),
                ],
              ),
            ),
            const SizedBox(width: 8),
            WindowListenButton(onTap: () => onListen(word.term, word.audioUrl)),
          ],
        ),
      ],
    ),
  );
}
