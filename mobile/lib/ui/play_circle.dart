import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/theme/theme.dart';

/// КРУЖОК ВОСПРОИЗВЕДЕНИЯ — 44 у слова и фразы (контур 1.5 `.28`), 28 у реплики собеседника
/// (контур 1.5 `.22`), 32 в списках. Иконка Lucide volume-2 по размеру. Тап пульсирует один раз
/// (4е «Воспроизведение в аудировании»), под «уменьшением движения» — не двигается.
class PlayCircle extends StatefulWidget {
  const PlayCircle({super.key, required this.onTap, this.size = 44, this.label});

  final VoidCallback onTap;
  final double size;
  final String? label;

  @override
  State<PlayCircle> createState() => _PlayCircleState();
}

class _PlayCircleState extends State<PlayCircle> with SingleTickerProviderStateMixin {
  late final AnimationController _pulse = AnimationController(
    vsync: this,
    duration: AppMotion.wavePulse,
    lowerBound: 1.0,
    upperBound: 1.04,
  );

  @override
  void dispose() {
    _pulse.dispose();
    super.dispose();
  }

  void _tap() {
    AppHaptics.light();
    if (!MediaQuery.of(context).disableAnimations) {
      _pulse.forward(from: 1.0).then((_) => _pulse.reverse());
    }
    widget.onTap();
  }

  @override
  Widget build(BuildContext context) {
    final small = widget.size <= 32;

    return Semantics(
      button: true,
      label: widget.label,
      child: InkResponse(
        onTap: _tap,
        radius: widget.size / 2 + 6,
        child: ScaleTransition(
          scale: _pulse,
          child: Container(
            width: widget.size,
            height: widget.size,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              border: Border.all(
                color: small ? AppColors.markerOutline : AppColors.playOutline,
                width: 1.5,
              ),
            ),
            child: Icon(LucideIcons.volume2, size: widget.size * 0.45, color: AppColors.ink),
          ),
        ),
      ),
    );
  }
}
