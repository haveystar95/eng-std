import 'dart:math' as math;

import 'package:flutter/material.dart';

import 'package:eng_std/theme/theme.dart';

/// КНОПКА ГЛАВНОГО ДЕЙСТВИЯ (кадры 23-0a…0d): одна, прижата к низу поверх ленты — 56, скругление
/// 18, `#1B1A18`; над ней 24 бумажного градиента, в который уходит лента. Градиент касаний не ловит:
/// лента под ним прокручивается.
///
/// A SECOND ACTION stands over the button as a brass link, 14 above it, when the day has two things to offer — «Повторить
/// разговор» over «Ещё раз» on a walked scene day (наряд CLIENT-CONV-1c §9г; the canvas draws the talk's replay as the one
/// button of the walked rehearsal, 37-1, and has no frame for a day that offers both). [busy] — the action is on its way
/// to the server (a talk's replay waits on a model): the button holds a spinner and takes no second tap.
class WindowActionBar extends StatelessWidget {
  const WindowActionBar({
    super.key,
    required this.label,
    required this.onTap,
    this.secondaryLabel,
    this.onSecondary,
    this.busy = false,
  });

  final String label;
  final VoidCallback onTap;
  final String? secondaryLabel;
  final VoidCallback? onSecondary;
  final bool busy;

  static const fade = 24.0;
  static const button = 56.0;

  /// The brass link over the button: its line and the 14 under it.
  static const secondary = 20.0 + 14.0;

  /// Сколько места снизу закрывает полоса — столько лента оставляет пустым под последней строкой.
  static double coverOf(BuildContext context, {bool withSecondary = false}) =>
      fade + button + (withSecondary ? secondary : 0) + _bottom(context);

  static double _bottom(BuildContext context) => math.max(24, MediaQuery.paddingOf(context).bottom + 8);

  @override
  Widget build(BuildContext context) => Column(
    mainAxisSize: MainAxisSize.min,
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      const IgnorePointer(
        child: SizedBox(
          height: fade,
          child: DecoratedBox(
            decoration: BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topCenter,
                end: Alignment.bottomCenter,
                colors: [AppColors.groundClear, AppColors.ground],
              ),
            ),
          ),
        ),
      ),
      ColoredBox(
        color: AppColors.ground,
        child: Padding(
          padding: EdgeInsets.fromLTRB(24, 0, 24, _bottom(context)),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              if (secondaryLabel case final text?) ...[
                Center(
                  child: Semantics(
                    button: true,
                    child: GestureDetector(
                      key: const ValueKey('window-action-secondary'),
                      behavior: HitTestBehavior.opaque,
                      onTap: busy || onSecondary == null
                          ? null
                          : () {
                              AppHaptics.light();
                              onSecondary!();
                            },
                      child: SizedBox(
                        height: 20,
                        child: Padding(
                          padding: const EdgeInsets.symmetric(horizontal: 16),
                          child: Text(text, maxLines: 1, style: AppTextSession.sheetStay),
                        ),
                      ),
                    ),
                  ),
                ),
                const SizedBox(height: 14),
              ],
              Semantics(
                button: true,
                child: GestureDetector(
                  key: const ValueKey('window-action'),
                  onTap: busy
                      ? null
                      : () {
                          AppHaptics.light();
                          onTap();
                        },
                  child: Container(
                    height: button,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(color: AppColors.windowInk, borderRadius: BorderRadius.circular(18)),
                    child: busy
                        ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: AppColors.paper))
                        : Text(label, maxLines: 1, style: AppTextWindow.action),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    ],
  );
}
