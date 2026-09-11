import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/theme/theme.dart';

import 'speech_wave.dart';

/// СОСТОЯНИЕ МИКРОФОНА 80 на карточках дня (кадры 23-3, 23-5, 23-8).
enum RecordState {
  /// Ждёт нажатия — «Скажи вслух».
  idle,

  /// Пишет — амплитуда живёт, «Слушаем…».
  listening,

  /// 300 мс после записи: столбики гаснут, вердикта ещё нет.
  thinking,

  /// Услышали — микрофон шалфеем.
  heard,

  /// Реплика собеседника ещё звучит — кнопка не нажимается.
  waiting,
}

/// МИКРОФОН 80 (токен-лист 2б «Микрофон») — ink, шалфей после «Услышали»; под ним амплитуда 5×3 и
/// подпись 14 tertiary. Тень `0 10 28 rgba(46,38,32,.22)`.
///
/// Один виджет на «произнеси», «повтори вслух» и «говорю сам»: поведение одно, и три копии —
/// три места, где чинить одну опечатку.
class RecordButton extends StatelessWidget {
  const RecordButton({
    super.key,
    required this.state,
    required this.caption,
    required this.onTap,
    this.level = 0,
  });

  final RecordState state;
  final String caption;
  final VoidCallback onTap;

  /// Уровень звука 0…1 во время записи.
  final double level;

  @override
  Widget build(BuildContext context) {
    final reduce = MediaQuery.of(context).disableAnimations;
    final heard = state == RecordState.heard;
    final waiting = state == RecordState.waiting;
    final tappable = state == RecordState.idle || state == RecordState.listening;

    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Semantics(
          button: tappable,
          enabled: tappable,
          label: caption,
          child: GestureDetector(
            onTap: tappable ? onTap : null,
            child: AnimatedContainer(
              duration: reduce ? Duration.zero : AppMotion.answerCorrect,
              width: 80,
              height: 80,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: heard ? AppColors.verdictKnown : AppColors.ink,
                boxShadow: const [
                  BoxShadow(color: AppColors.micShadow, blurRadius: 28, offset: Offset(0, 10)),
                ],
              ),
              child: Opacity(
                opacity: waiting ? 0.5 : 1,
                child: const Icon(LucideIcons.mic, color: AppColors.paper, size: 32),
              ),
            ),
          ),
        ),
        const SizedBox(height: 10),
        AmplitudeBars(level: level, active: state == RecordState.listening),
        const SizedBox(height: 10),
        Text(caption, textAlign: TextAlign.center, style: AppTextDay.quiet),
      ],
    );
  }
}
