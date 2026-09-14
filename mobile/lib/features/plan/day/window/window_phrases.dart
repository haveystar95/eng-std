import 'package:flutter/material.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

import '../../../../data/plan/day_window.dart';
import 'window_bits.dart';

/// Сказать строку: текст и чья она — реплика собеседника звучит голосом сервера, если файл докачан,
/// фраза ученика — голосом телефона.
typedef WindowListen = void Function(String text, {required bool partner});

/// ВКЛАДКА «ФРАЗЫ» (кадр 23-0d): маркер 14 у левой кромки, карточка бумаги со скруглением 16 —
/// фраза Literata 17, перевод второй строкой и «прослушать» 28 справа (голос телефона).
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
      Padding(padding: const EdgeInsets.only(top: 19), child: WindowUnitMarker(state: phrase.state)),
      const SizedBox(width: 12),
      Expanded(
        child: Container(
          constraints: const BoxConstraints(minHeight: 102),
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
                    const SizedBox(height: 6),
                    Text(phrase.translation, style: AppTextWindow.translation),
                  ],
                ),
              ),
              const SizedBox(width: 12),
              PlayCircle(
                size: 28,
                label: AppLocalizations.of(context).planWindowListen,
                onTap: () => onListen(phrase.text, partner: false),
              ),
            ],
          ),
        ),
      ),
    ],
  );
}
