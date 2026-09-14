import 'package:flutter/material.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

import '../../../../data/plan/plan_models.dart';
import '../../plan_stage_text.dart';
import 'window_texts.dart';

/// ПИЛЮЛЯ ВКЛАДОК (кадры 23-0a…0d) — сегмент 48 на шве тёмное → бумага: верхняя половина на плите,
/// нижняя на бумаге (перекрытие 24), поля 16, бумага `#F6F3EC`, скругление 24, тень `0 2 8 .08` —
/// одна и та же на шве и под компактной шапкой. Три сегмента через 2, значок 16 · 6 · слово 15/600;
/// активный — чип чернила 40 со скруглением 20, слово бумагой.
///
/// Чип — ОДНА вещь, у которой одна анимация: он стоит там, где сейчас значение `TabController.animation`,
/// — едет за пальцем при свайпе и скользит к сегменту 220 мс ease-out-cubic по тапу. Содержимое
/// сдвигается тем же контроллером (`TabBarView`), поэтому чип и лента не расходятся ни на кадр.
class WindowPill extends StatelessWidget {
  const WindowPill({super.key, required this.controller});

  final TabController controller;

  static const height = 48.0;

  /// Поля пилюли от краёв экрана.
  static const inset = 16.0;

  static const _padding = 4.0;
  static const _gap = 2.0;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final reduce = MediaQuery.of(context).disableAnimations;

    return Container(
      height: height,
      padding: const EdgeInsets.all(_padding),
      decoration: BoxDecoration(
        color: AppColors.paper,
        borderRadius: BorderRadius.circular(height / 2),
        boxShadow: const [BoxShadow(color: AppColors.windowPillShadow, offset: Offset(0, 2), blurRadius: 8)],
      ),
      child: LayoutBuilder(
        builder: (context, box) {
          final count = WindowTab.values.length;
          final segment = (box.maxWidth - _gap * (count - 1)) / count;

          return AnimatedBuilder(
            animation: controller.animation!,
            builder: (context, _) {
              final value = controller.animation!.value;

              return Stack(
                children: [
                  Positioned(
                    key: const ValueKey('window-pill-chip'),
                    left: value * (segment + _gap),
                    top: 0,
                    width: segment,
                    height: height - 2 * _padding,
                    child: const DecoratedBox(
                      decoration: BoxDecoration(color: AppColors.ink, borderRadius: BorderRadius.all(Radius.circular(20))),
                    ),
                  ),
                  Row(
                    children: [
                      for (final tab in WindowTab.values) ...[
                        if (tab.index > 0) const SizedBox(width: _gap),
                        SizedBox(
                          width: segment,
                          child: _Segment(
                            tab: tab,
                            label: WindowTexts.tabName(l, tab),
                            active: (1 - (value - tab.index).abs()).clamp(0.0, 1.0),
                            onTap: () => select(controller, tab.index, reduce),
                          ),
                        ),
                      ],
                    ],
                  ),
                ],
              );
            },
          );
        },
      ),
    );
  }

  /// Смена вкладки тапом — чип и содержимое одним движением контроллера.
  static void select(TabController controller, int index, bool reduce) {
    if (controller.index == index) return;
    AppHaptics.light();
    controller.animateTo(
      index,
      duration: reduce ? Duration.zero : AppMotion.windowTabChip,
      curve: AppMotion.windowEaseOutCubic,
    );
  }
}

class _Segment extends StatelessWidget {
  const _Segment({required this.tab, required this.label, required this.active, required this.onTap});

  final WindowTab tab;
  final String label;

  /// 1 — чип под сегментом, 0 — нет; между ними — на ходу.
  final double active;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final color = Color.lerp(AppColors.secondary, AppColors.ground, active)!;

    return Semantics(
      button: true,
      selected: active > .5,
      child: GestureDetector(
        behavior: HitTestBehavior.opaque,
        onTap: onTap,
        child: SizedBox(
          height: WindowPill.height - 2 * WindowPill._padding,
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              PlanStageGlyph(kind: planStageMark(_stageOf(tab)), color: color, size: 16),
              const SizedBox(width: 6),
              Flexible(
                child: Text(label, maxLines: 1, softWrap: false, overflow: TextOverflow.clip, style: AppTextWindow.tab.copyWith(color: color)),
              ),
            ],
          ),
        ),
      ),
    );
  }

  static PlanStage _stageOf(WindowTab tab) => switch (tab) {
    WindowTab.words => PlanStage.words,
    WindowTab.phrases => PlanStage.phrases,
    WindowTab.dialogue => PlanStage.dialogue,
  };
}
