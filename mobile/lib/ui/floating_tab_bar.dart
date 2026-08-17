import 'dart:ui' as ui;

import 'package:flutter/material.dart';

import 'package:eng_std/theme/theme.dart';

/// Один таб плавающей пилюли.
class FloatingTabItem {
  const FloatingTabItem({required this.icon, required this.label});
  final IconData icon;
  final String label;
}

/// Плавающая таб-пилюля (rule 09, §3): полупрозрачное стекло (blur 22,
/// saturate 1.5), контент уходит под неё. Ширина по содержимому. Вызывающий
/// центрирует её и добавляет контенту нижний отступ
/// ([AppTabBarMetrics.height] + [AppTabBarMetrics.bottomInset] + safe-area).
class FloatingTabBar extends StatelessWidget {
  const FloatingTabBar({
    super.key,
    required this.items,
    required this.currentIndex,
    required this.onTap,
  });

  final List<FloatingTabItem> items;
  final int currentIndex;
  final ValueChanged<int> onTap;

  static ui.ImageFilter _glassFilter() {
    return ui.ImageFilter.compose(
      outer: ui.ColorFilter.matrix(_saturation(AppTabBarMetrics.saturate)),
      inner: ui.ImageFilter.blur(
        sigmaX: AppTabBarMetrics.blur,
        sigmaY: AppTabBarMetrics.blur,
      ),
    );
  }

  static List<double> _saturation(double s) {
    const lr = 0.2126, lg = 0.7152, lb = 0.0722;
    final ir = (1 - s) * lr, ig = (1 - s) * lg, ib = (1 - s) * lb;
    return [
      ir + s, ig, ib, 0, 0, //
      ir, ig + s, ib, 0, 0, //
      ir, ig, ib + s, 0, 0, //
      0, 0, 0, 1, 0, //
    ];
  }

  @override
  Widget build(BuildContext context) {
    final radius = BorderRadius.circular(AppRadii.pill);
    return DecoratedBox(
      decoration: BoxDecoration(borderRadius: radius, boxShadow: AppShadows.pill),
      child: ClipRRect(
        borderRadius: radius,
        child: BackdropFilter(
          filter: _glassFilter(),
          child: Container(
            height: AppTabBarMetrics.height,
            color: AppTabBarMetrics.glass,
            padding: const EdgeInsets.symmetric(
              horizontal: AppTabBarMetrics.padH,
              vertical: AppTabBarMetrics.padV,
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                for (var i = 0; i < items.length; i++)
                  _TabButton(
                    item: items[i],
                    active: i == currentIndex,
                    onTap: () => onTap(i),
                  ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _TabButton extends StatelessWidget {
  const _TabButton({required this.item, required this.active, required this.onTap});
  final FloatingTabItem item;
  final bool active;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final color = active ? AppColors.ink : AppColors.secondary;
    return SizedBox(
      width: AppTabBarMetrics.item,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppRadii.pill),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(item.icon, size: 17, color: color),
            const SizedBox(height: 3),
            Text(item.label, style: active ? AppText.tabActive : AppText.tabInactive),
          ],
        ),
      ),
    );
  }
}
