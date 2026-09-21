import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../../../data/plan/day_window.dart';
import '../../../../data/plan/plan_models.dart';
import 'window_bits.dart';
import 'window_stage_row.dart';
import 'window_texts.dart';

/// ПЛИТА ОКНА ДНЯ (кадры 23-0a…0c) — один слой с бумагой под ней: тёмное во всю ширину от самого верха
/// (статус-бар и стрелка внутри), фото дня под скримом до краёв, скругление только снизу 28, тень
/// `0 8 24 .18` на бумагу; зазора между плитой и бумагой нет.
///
/// Внутри, поля 24: стрелка и бровь «ДЕНЬ N» одной строкой 24; название Literata 30 в одну строку (26,
/// когда не встаёт); статус словами — у пройденного дня на его месте строка итога с галкой; цели ОДНИМ
/// предложением; хайрлайн и пять рядов этапов 48 с полосой — цифра «N / M» только у текущего. Низ 48:
/// 24 заходит пилюля вкладок, 24 воздуха. Кнопки на плите нет: она одна и живёт внизу экрана.
class WindowPlate extends StatelessWidget {
  const WindowPlate({super.key, required this.window, this.onBack, this.poppedStages = const {}, this.system});

  final DayWindow window;
  final VoidCallback? onBack;

  /// Этапы, закрытые с прошлого показа окна, — их галки появляются через 300 мс (`om-check-pop`).
  final Set<PlanStage> poppedStages;

  /// A REVIEW OR THE REHEARSAL (кадры 37-1, 37-2, наряд CLIENT-CONV-1b) — the same plate with its own words: the
  /// day's kind for the brow, its own title, one status line and one sentence of what the day holds in place of the
  /// goals; no tab pill under it, so the plate ends 28 below the last row. Null — a scene day (23-0a…0c).
  final WindowSystemDay? system;

  /// Скругление низа плиты.
  static const radius = 28.0;

  /// Сколько пилюля вкладок заходит на плиту: она стоит на шве тёмное → бумага.
  static const pillOverlap = 24.0;

  /// Под плитой без пилюли (37-1, 37-2) — 28 воздуха под последним рядом.
  static const systemBottom = 28.0;

  /// Ряд этапа, последний — без воздуха под полосой (низ плиты даёт свои 48).
  static const stageRow = 48.0;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final day = window.day;
    final top = MediaQuery.paddingOf(context).top;
    const shape = BorderRadius.vertical(bottom: Radius.circular(radius));
    final system = this.system;
    final passed = day.status == WindowDayStatus.passed && system == null;
    final goals = system == null ? WindowTexts.goalsList(l, day.goals) : '';

    final content = Padding(
      padding: EdgeInsets.fromLTRB(24, top, 24, system == null ? 2 * pillOverlap : systemBottom),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        mainAxisSize: MainAxisSize.min,
        children: [
          SizedBox(
            height: 24,
            child: Row(
              children: [
                _Back(onTap: onBack, label: l.planWindowBack),
                const SizedBox(width: 12),
                Text((system?.brow ?? l.planPlateLabel(day.index)).toUpperCase(), style: AppTextWindow.brow),
              ],
            ),
          ),
          const SizedBox(height: 8),
          WindowTitle(text: system?.title ?? WindowTexts.title(l, day)),
          if (system != null) ...[
            const SizedBox(height: 4),
            Text(system.status, key: const ValueKey('window-system-status'), style: AppTextWindow.status),
            const SizedBox(height: 24),
            Text(system.lead, key: const ValueKey('window-system-lead'), style: AppTextWindow.goals),
          ] else if (passed) ...[
            const SizedBox(height: 2),
            Row(
              children: [
                const WindowCheck(size: 20, glyph: 11),
                const SizedBox(width: 10),
                Expanded(child: Text(WindowTexts.status(l, day), style: AppTextWindow.passed)),
              ],
            ),
          ] else ...[
            const SizedBox(height: 4),
            Text(WindowTexts.status(l, day), style: AppTextWindow.status),
          ],
          if (goals.isNotEmpty) ...[
            const SizedBox(height: 24),
            WindowGoalsSentence(goals: goals, passed: passed),
          ],
          const SizedBox(height: 28),
          Container(
            padding: const EdgeInsets.only(top: 10),
            decoration: const BoxDecoration(border: Border(top: BorderSide(color: AppColors.windowPaperLine))),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                for (final (i, stage) in window.stages.indexed)
                  SizedBox(
                    height: i == window.stages.length - 1 ? null : stageRow,
                    child: Align(
                      alignment: Alignment.topCenter,
                      child: WindowStageRow(stage: stage, popCheck: poppedStages.contains(stage.stage)),
                    ),
                  ),
              ],
            ),
          ),
        ],
      ),
    );

    return DecoratedBox(
      decoration: const BoxDecoration(
        borderRadius: shape,
        boxShadow: [BoxShadow(color: AppColors.windowPlateShadow, offset: Offset(0, 8), blurRadius: 24)],
      ),
      child: ClipRRect(
        borderRadius: shape,
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

/// НАЗВАНИЕ ДНЯ — Literata 30 в одну строку; не встаёт в ширину — 26 с переносом, без троеточия.
class WindowTitle extends StatelessWidget {
  const WindowTitle({super.key, required this.text});

  final String text;

  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (context, box) {
      final painter = TextPainter(
        text: TextSpan(text: text, style: AppTextWindow.title),
        textDirection: Directionality.of(context),
        textScaler: MediaQuery.textScalerOf(context),
        maxLines: 1,
      )..layout(maxWidth: box.maxWidth);
      final fits = !painter.didExceedMaxLines;
      painter.dispose();

      return Text(text, style: fits ? AppTextWindow.title : AppTextWindow.titleWrapped, maxLines: fits ? 1 : null);
    },
  );
}

/// ЦЕЛИ ОДНИМ ПРЕДЛОЖЕНИЕМ: «Научишься описать, где и как болит, … и спросить про ограничения»; у
/// пройденного дня — «Научился:» шалфеем с галкой 14 и те же цели дальше тем же текстом.
class WindowGoalsSentence extends StatelessWidget {
  const WindowGoalsSentence({super.key, required this.goals, required this.passed});

  /// Цели сервера, уже склеенные в одну строку ([WindowTexts.goalsList]).
  final String goals;
  final bool passed;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    if (!passed) return Text(l.planWindowGoalsLearn(goals), style: AppTextWindow.goals);

    return Text.rich(
      TextSpan(
        style: AppTextWindow.goals,
        children: [
          WidgetSpan(
            alignment: PlaceholderAlignment.middle,
            child: Padding(
              padding: const EdgeInsets.only(right: 6),
              child: const WindowCheck(size: 14, glyph: 8),
            ),
          ),
          TextSpan(text: l.planWindowGoalsLearned, style: const TextStyle(color: AppColors.windowDone)),
          TextSpan(text: ' $goals'),
        ],
      ),
    );
  }
}

/// Скрим над фото: `.86` → `.82` на 120 → `.90` на 300 → `.96` у низа плиты.
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
          stops: [0, (120 / h).clamp(0.0, 1.0), (300 / h).clamp(0.0, 1.0), 1],
        ),
      ),
    );
  }
}

/// Стрелка назад 24 в строке брови; тап ловит поле 44 × 24.
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
        width: 24,
        height: 24,
        child: Icon(LucideIcons.arrowLeft, size: 24, color: AppColors.paper),
      ),
    ),
  );
}
