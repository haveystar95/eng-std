import 'package:flutter/material.dart';

import 'package:eng_std/theme/theme.dart';

/// THE ACTION BUTTON 56 — charcoal `#1B1A18`, corner radius 18, 17/600 in paper; disabled — an 8 % backing.
///
/// One button for every main action of the app's new screens: the session's dock (canvases 30-x), the sheets of
/// 30-8 / 41-3 / 42-2 / 42-4 / 43-1 and «Начать» of the last «why» sheet (41-2e). [busy] — the action is on its way: a
/// spinner in the label's place.
///
/// [destructive] — the same button drawn as an outline in terracotta #9A4430 (42-3 «Удалить аккаунт»: «с заливкой —
/// контуром»); disabled it is an ink outline at .22 with a grey label («Удаляем…»).
class DockButton extends StatelessWidget {
  const DockButton({
    super.key,
    required this.label,
    required this.onTap,
    this.enabled = true,
    this.busy = false,
    this.destructive = false,
  });

  final String label;
  final VoidCallback? onTap;
  final bool enabled;

  /// The answer is still being sent to the server — the button waits.
  final bool busy;

  final bool destructive;

  @override
  Widget build(BuildContext context) {
    final on = enabled && onTap != null && !busy;
    final BoxDecoration decoration;
    final TextStyle style;
    if (destructive) {
      decoration = BoxDecoration(
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: enabled ? AppColors.destructiveText : AppColors.markerOutline, width: 1.5),
      );
      style = AppTextSession.dock.copyWith(color: enabled ? AppColors.destructiveText : AppColors.tertiary);
    } else {
      decoration = BoxDecoration(
        color: enabled ? AppColors.windowInk : AppColors.sessionSheetShadow,
        borderRadius: BorderRadius.circular(18),
      );
      style = enabled ? AppTextSession.dock : AppTextSession.dock.copyWith(color: AppColors.tertiary);
    }
    return Semantics(
      button: true,
      enabled: on,
      label: label,
      child: GestureDetector(
        onTap: on
            ? () {
                AppHaptics.light();
                onTap!();
              }
            : null,
        child: AnimatedContainer(
          duration: AppMotion.sessionChipSelect,
          height: 56,
          alignment: Alignment.center,
          decoration: decoration,
          child: busy
              ? SizedBox(
                  width: 20,
                  height: 20,
                  child: CircularProgressIndicator(strokeWidth: 2, color: destructive ? AppColors.destructiveText : AppColors.paper),
                )
              : Text(label, style: style),
        ),
      ),
    );
  }
}
