import 'package:flutter/material.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

import '../../../../data/local/cached_image_provider.dart';
import '../../../../data/plan/day_window.dart';
import 'window_bits.dart';
import 'window_texts.dart';

/// КОМПАКТНАЯ ШАПКА 56 (кадры 23-0a…0d «прокручено»): фото кружком 32 · «День N · сцена» с полосой
/// прогресса дня по этапам (`day_progress`, не по карточкам) · минуты справа. Ниже статус-бара на
/// бумаге; кнопки назад в строке нет.
class WindowCompactHeader extends StatelessWidget {
  const WindowCompactHeader({super.key, required this.window});

  final DayWindow window;

  /// Строка без статус-бара: отступ 8 и сама строка 56.
  static const height = 8.0 + 56.0;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final day = window.day;
    final dpr = MediaQuery.maybeDevicePixelRatioOf(context) ?? 2;
    final photo = day.image?.urlFor(32, dpr);
    final minutes = WindowTexts.compactMinutes(l, day);

    return ColoredBox(
      color: AppColors.ground,
      child: Padding(
        padding: EdgeInsets.fromLTRB(24, MediaQuery.paddingOf(context).top + 8, 24, 0),
        child: SizedBox(
          height: 56,
          child: Row(
            children: [
              SceneCircle(
                image: photo == null ? null : CachedNetworkImage(photo),
                tone: AppColors.wireTone(day.imageTone) ?? AppColors.photoSlot,
                size: 32,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(
                      l.planRouteDayTitle(day.index, WindowTexts.title(l, day)),
                      maxLines: 1,
                      softWrap: false,
                      overflow: TextOverflow.clip,
                      style: AppTextWindow.compactTitle,
                    ),
                    const SizedBox(height: 6),
                    WindowBar(share: window.dayProgress, track: AppColors.barTrack, fill: AppColors.verdictKnown),
                  ],
                ),
              ),
              if (minutes != null) ...[
                const SizedBox(width: 12),
                Text(minutes, maxLines: 1, softWrap: false, style: AppTextWindow.compactMinutes),
              ],
            ],
          ),
        ),
      ),
    );
  }
}
