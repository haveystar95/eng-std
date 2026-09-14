import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../../../data/plan/day_window.dart';
import '../../../../data/plan/plan_models.dart';
import 'window_bits.dart';
import 'window_stage_row.dart';
import 'window_texts.dart';

/// ПЛИТА ОКНА ДНЯ (кадры 23-0a…0c) — фото дня под слоем `.86 → .96`, от верха экрана, скруглена
/// снизу 28: стрелка назад, бровь «ДЕНЬ N», название Literata 44, статус словами, «научишься» с
/// кружками целей, пять рядов этапов; у пройденного дня — строка итога «День пройден · N минут».
/// Кнопки на плите нет: она одна и живёт внизу экрана.
class WindowPlate extends StatelessWidget {
  const WindowPlate({super.key, required this.window, this.onBack, this.poppedStages = const {}});

  final DayWindow window;
  final VoidCallback? onBack;

  /// Этапы, закрытые с прошлого показа окна, — их галки появляются `om-check-pop`.
  final Set<PlanStage> poppedStages;

  /// Сколько плита заходит на бумагу под собой (кадр: фон 635 при содержимом до 629).
  static const overlap = 6.0;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final day = window.day;
    final top = MediaQuery.paddingOf(context).top;
    const radius = BorderRadius.vertical(bottom: Radius.circular(28));
    final passed = day.status == WindowDayStatus.passed;

    final content = Padding(
      padding: EdgeInsets.fromLTRB(24, top + 8, 24, 24 + overlap),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          _Back(onTap: onBack, label: l.planWindowBack),
          Text(l.planPlateLabel(day.index).toUpperCase(), style: AppTextWindow.brow),
          const SizedBox(height: 8),
          Text(WindowTexts.title(l, day), style: AppTextWindow.title),
          const SizedBox(height: 8),
          Text(WindowTexts.status(l, day), style: AppTextWindow.status),
          if (day.goals.isNotEmpty) ...[
            const SizedBox(height: 32),
            Text(l.planWindowGoalsLabel.toUpperCase(), style: AppTextWindow.label),
            const SizedBox(height: 14),
            for (final (i, goal) in day.goals.indexed) ...[
              if (i > 0) const SizedBox(height: 8),
              _GoalRow(goal: goal),
            ],
          ],
          const SizedBox(height: 24),
          _Ruled(
            top: 24,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                for (final (i, stage) in window.stages.indexed) ...[
                  if (i > 0) const SizedBox(height: 10),
                  WindowStageRow(stage: stage, popCheck: poppedStages.contains(stage.stage)),
                ],
              ],
            ),
          ),
          if (passed && day.minutesSpent != null) ...[
            const SizedBox(height: 24),
            _Ruled(
              top: 20,
              child: Row(
                children: [
                  const _Check(size: 20, glyph: 11),
                  const SizedBox(width: 12),
                  Expanded(child: Text(l.planWindowPassedLine(l.planMinutesCount(day.minutesSpent!)), style: AppTextWindow.passed)),
                ],
              ),
            ),
          ],
        ],
      ),
    );

    return DecoratedBox(
      decoration: const BoxDecoration(
        borderRadius: radius,
        boxShadow: [BoxShadow(color: AppColors.windowPlateShadow, offset: Offset(0, 14), blurRadius: 30)],
      ),
      child: ClipRRect(
        borderRadius: radius,
        child: Stack(
          children: [
            // Без фото плита — материал `#2A231D`; с фото — тон фото, пока байты в пути.
            Positioned.fill(
              child: WindowPhoto(
                url: day.image?.url,
                tone: day.image == null ? AppColors.windowPlate : (AppColors.wireTone(day.imageTone) ?? AppColors.windowPlate),
              ),
            ),
            Positioned.fill(child: LayoutBuilder(builder: (context, box) => _Scrim(height: box.maxHeight))),
            content,
          ],
        ),
      ),
    );
  }
}

/// Слой над фото: `.86` → `.82` на 150 → `.90` на 430 → `.96` у низа плиты.
class _Scrim extends StatelessWidget {
  const _Scrim({required this.height});

  final double height;

  @override
  Widget build(BuildContext context) {
    final h = height <= 0 ? 1.0 : height;

    return DecoratedBox(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
          colors: const [AppColors.windowScrimTop, AppColors.windowScrimHigh, AppColors.windowScrimLow, AppColors.windowScrimBottom],
          stops: [0, (150 / h).clamp(0.0, 1.0), (430 / h).clamp(0.0, 1.0), 1],
        ),
      ),
    );
  }
}

/// Стрелка назад 24 у левой кромки; тап ловит поле 44 × 40.
class _Back extends StatelessWidget {
  const _Back({required this.onTap, required this.label});

  final VoidCallback? onTap;
  final String label;

  @override
  Widget build(BuildContext context) => Semantics(
    button: true,
    label: label,
    child: GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTap: onTap,
      child: const SizedBox(
        width: 44,
        height: 40,
        child: Align(alignment: Alignment.topLeft, child: Icon(LucideIcons.arrowLeft, size: 24, color: AppColors.paper)),
      ),
    ),
  );
}

/// Хайрлайн `.16` над блоком и отступ под ним — этапы (24) и строка итога (20).
class _Ruled extends StatelessWidget {
  const _Ruled({required this.top, required this.child});

  final double top;
  final Widget child;

  @override
  Widget build(BuildContext context) => Container(
    padding: EdgeInsets.only(top: top),
    decoration: const BoxDecoration(border: Border(top: BorderSide(color: AppColors.windowPaperLine))),
    child: child,
  );
}

/// Цель «научишься»: кружок 20 — контур у непройденного дня, шалфей с галкой у пройденного.
class _GoalRow extends StatelessWidget {
  const _GoalRow({required this.goal});

  final WindowGoal goal;

  @override
  Widget build(BuildContext context) => Row(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      goal.passed
          ? const _Check(size: 20, glyph: 11)
          : Container(
              width: 20,
              height: 20,
              decoration: BoxDecoration(shape: BoxShape.circle, border: Border.all(color: AppColors.windowAhead, width: 1.5)),
            ),
      const SizedBox(width: 12),
      Expanded(child: Text(goal.text, style: AppTextWindow.goal)),
    ],
  );
}

class _Check extends StatelessWidget {
  const _Check({required this.size, required this.glyph});

  final double size;
  final double glyph;

  @override
  Widget build(BuildContext context) => Container(
    width: size,
    height: size,
    alignment: Alignment.center,
    decoration: const BoxDecoration(shape: BoxShape.circle, color: AppColors.verdictKnown),
    child: Icon(LucideIcons.check, size: glyph, color: AppColors.paper),
  );
}
