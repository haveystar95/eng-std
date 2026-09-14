import 'package:flutter/material.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import 'window_texts.dart';

/// ВКЛАДКИ ПРОГРАММЫ 44 (кадры 23-0a…0d): Слова · Фразы · Диалог через 24, у активной — латунная
/// черта 2 под словом, под всем рядом хайрлайн `.10`. Прижатые под шапку, вкладки получают тень —
/// «примагничиваются» за 160 мс. Смена — тапом (220 мс) или свайпом ленты; черта и цвет следуют за
/// лентой.
class WindowTabBar extends StatelessWidget {
  const WindowTabBar({super.key, required this.controller, required this.pinned});

  final TabController controller;

  /// Вкладки прижаты под компактную шапку.
  final bool pinned;

  static const height = 44.0;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final reduce = MediaQuery.of(context).disableAnimations;

    return AnimatedContainer(
      duration: reduce ? Duration.zero : AppMotion.windowTabsSnap,
      curve: AppMotion.easeOut,
      height: height,
      padding: const EdgeInsets.symmetric(horizontal: 24),
      decoration: BoxDecoration(
        color: AppColors.ground,
        border: const Border(bottom: BorderSide(color: AppColors.dividerFaint)),
        boxShadow: [
          BoxShadow(color: pinned ? AppColors.windowTabsShadow : AppColors.groundClear, offset: const Offset(0, 8), blurRadius: 18),
        ],
      ),
      child: AnimatedBuilder(
        animation: controller.animation!,
        builder: (context, _) => Row(
          children: [
            for (final tab in WindowTab.values) ...[
              if (tab.index > 0) const SizedBox(width: 24),
              _Tab(
                label: WindowTexts.tabName(l, tab),
                active: (1 - (controller.animation!.value - tab.index).abs()).clamp(0.0, 1.0),
                onTap: () => _select(controller, tab.index, reduce),
              ),
            ],
          ],
        ),
      ),
    );
  }

  static void _select(TabController controller, int index, bool reduce) {
    if (controller.index == index) return;
    AppHaptics.light();
    controller.animateTo(index, duration: reduce ? Duration.zero : AppMotion.windowTabSwitch, curve: AppMotion.easeOut);
  }
}

class _Tab extends StatelessWidget {
  const _Tab({required this.label, required this.active, required this.onTap});

  final String label;

  /// 1 — активная, 0 — нет; между ними — на ходу ленты.
  final double active;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Semantics(
    button: true,
    selected: active > .5,
    child: GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTap: onTap,
      child: SizedBox(
        height: WindowTabBar.height,
        child: Stack(
          alignment: Alignment.center,
          children: [
            Text(label, style: AppTextWindow.tab.copyWith(color: Color.lerp(AppColors.secondary, AppColors.ink, active))),
            Positioned(
              left: 0,
              right: 0,
              bottom: 0,
              child: Opacity(opacity: active, child: const SizedBox(height: 2, child: ColoredBox(color: AppColors.brassInk))),
            ),
          ],
        ),
      ),
    ),
  );
}
