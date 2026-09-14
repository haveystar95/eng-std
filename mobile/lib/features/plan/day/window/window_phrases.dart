import 'package:flutter/material.dart';

import 'package:eng_std/theme/theme.dart';

import '../../../../data/plan/day_window.dart';
import 'window_bits.dart';

/// ВКЛАДКА «ФРАЗЫ» (кадр 23-0d): маркер 14 у левой кромки, через 12 карточка бумаги со скруглением 16 и
/// полями 14 — фраза Literata 22, через 4 чтение кириллицей 13, через 4 перевод 15; справа «прослушать» 28
/// голосом ученика сцены (DAY-UI-3: у фраз есть голос сервера; нет файла — читает телефон).
class WindowPhrases extends StatelessWidget {
  const WindowPhrases({super.key, required this.phrases, required this.onListen});

  final List<WindowPhrase> phrases;
  final WindowListen onListen;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      for (final (i, phrase) in phrases.indexed) ...[
        if (i > 0) const SizedBox(height: 12),
        _PhraseRow(phrase: phrase, onListen: onListen),
      ],
    ],
  );
}

class _PhraseRow extends StatelessWidget {
  const _PhraseRow({required this.phrase, required this.onListen});

  final WindowPhrase phrase;
  final WindowListen onListen;

  @override
  Widget build(BuildContext context) => Row(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Padding(padding: const EdgeInsets.only(top: 21), child: WindowUnitMarker(state: phrase.state)),
      const SizedBox(width: 12),
      Expanded(
        child: Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(color: AppColors.paper, borderRadius: BorderRadius.circular(16)),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(phrase.text, style: AppTextWindow.target),
                    if (phrase.pronunciation case final reading?) ...[
                      const SizedBox(height: 4),
                      Text(reading, style: AppTextWindow.reading),
                    ],
                    const SizedBox(height: 4),
                    Text(phrase.translation, style: AppTextWindow.translation),
                  ],
                ),
              ),
              const SizedBox(width: 12),
              WindowListenButton(onTap: () => onListen(phrase.text, phrase.audioUrl)),
            ],
          ),
        ),
      ),
    ],
  );
}
