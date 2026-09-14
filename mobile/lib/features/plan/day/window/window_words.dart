import 'package:flutter/material.dart';

import 'package:eng_std/theme/theme.dart';

import '../../../../data/plan/day_window.dart';
import 'window_bits.dart';

/// ВКЛАДКА «СЛОВА» (кадры 23-0a…0d): сетка в две колонки через 12. Карточка — фото 4:3 со скруглением
/// 12 (тон → фото), маркер 14 в углу на бумажной подложке; через 8 слово Literata 22; через 4 чтение
/// кириллицей 13 и перевод 15 столбиком, справа «прослушать» 28. Тап по карточке открывает шит слова
/// 23-0e. Длинное слово переносится, а не режется троеточием.
class WindowWords extends StatelessWidget {
  const WindowWords({super.key, required this.words, required this.onListen, required this.onOpen});

  final List<WindowWord> words;
  final WindowListen onListen;
  final ValueChanged<WindowWord> onOpen;

  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (context, box) {
      final width = (box.maxWidth - 12) / 2;

      return Wrap(
        spacing: 12,
        runSpacing: 12,
        children: [
          for (final word in words)
            SizedBox(width: width, child: WindowWordCard(word: word, onListen: onListen, onOpen: () => onOpen(word))),
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
                  Text(word.translation, style: AppTextWindow.translation),
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
