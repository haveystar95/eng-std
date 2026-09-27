import 'package:flutter/material.dart';

import 'package:eng_std/theme/theme.dart';

/// THE SWITCH — 44 × 26, sage when on, ink at .16 when off, a paper knob 20 with its small shadow (the session's «Без
/// подсказок» 30-1 / 37-5, the profile's «Звуки в сессии» and «Напоминать о дне» 42-1, the reminders sheet 42-4). Only
/// the look: the row around it owns the tap, so the whole row switches, not just the pill.
class PaperSwitch extends StatelessWidget {
  const PaperSwitch({super.key, required this.value});

  final bool value;

  @override
  Widget build(BuildContext context) => AnimatedContainer(
    duration: AppMotion.sessionChipSelect,
    width: 44,
    height: 26,
    padding: const EdgeInsets.all(3),
    alignment: value ? Alignment.centerRight : Alignment.centerLeft,
    decoration: BoxDecoration(
      color: value ? AppColors.verdictKnown : AppColors.sessionToggleTrack,
      borderRadius: BorderRadius.circular(13),
    ),
    child: Container(
      width: 20,
      height: 20,
      decoration: const BoxDecoration(
        shape: BoxShape.circle,
        color: AppColors.paper,
        boxShadow: [BoxShadow(color: AppColors.sessionToggleKnobShadow, blurRadius: 3, offset: Offset(0, 1))],
      ),
    ),
  );
}
