import 'dart:math' as math;

import 'package:flutter/material.dart';

import 'package:eng_std/theme/theme.dart';

/// КНОПКА ГЛАВНОГО ДЕЙСТВИЯ (кадры 23-0a…0d): одна, прижата к низу поверх ленты — 56, скругление
/// 18, `#1B1A18`; над ней 24 бумажного градиента, в который уходит лента. Градиент касаний не ловит:
/// лента под ним прокручивается.
class WindowActionBar extends StatelessWidget {
  const WindowActionBar({super.key, required this.label, required this.onTap});

  final String label;
  final VoidCallback onTap;

  static const fade = 24.0;
  static const button = 56.0;

  /// Сколько места снизу закрывает полоса — столько лента оставляет пустым под последней строкой.
  static double coverOf(BuildContext context) => fade + button + _bottom(context);

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
          child: Semantics(
            button: true,
            child: GestureDetector(
              onTap: () {
                AppHaptics.light();
                onTap();
              },
              child: Container(
                height: button,
                alignment: Alignment.center,
                decoration: BoxDecoration(color: AppColors.windowInk, borderRadius: BorderRadius.circular(18)),
                child: Text(label, maxLines: 1, style: AppTextWindow.action),
              ),
            ),
          ),
        ),
      ),
    ],
  );
}
