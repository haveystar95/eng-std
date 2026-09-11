import 'dart:ui' show ImageFilter;

import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/theme/theme.dart';

import 'verdict_marker.dart';

/// ОДИН ЭТАП НА ПЛИТЕ (4н «Строки этапов»): имя, счётчик в колонке 64, полоска 6 px в трёх цветах
/// вердиктов; у текущего — вторая строка под именем.
class DayPlateStage {
  const DayPlateStage({
    required this.name,
    required this.done,
    required this.total,
    required this.passed,
    required this.hinted,
    required this.failed,
    this.current = false,
    this.locked = false,
    this.sub,
  });

  final String name;
  final int done;
  final int total;

  /// Как разложились пройденные карточки — доли полоски.
  final int passed;
  final int hinted;
  final int failed;
  final bool current;
  final bool locked;

  /// «начни отсюда · 8 новых слов» / «не закончен · 10 карточек · ≈ 4 мин» / «2 с подсказкой».
  final String? sub;

  /// Охра во второй строке — «N с подсказкой».
  bool get subOchre => false;
}

/// ОДНО ИЗ ТРЁХ ЧИСЕЛ ПЛИТЫ — «12 · КАРТОЧЕК». [unit] — знак после числа («%»), в меньшем кегле.
class DayPlateNumber {
  const DayPlateNumber({required this.value, required this.label, this.unit});

  final int value;
  final String label;
  final String? unit;
}

/// ПОДВАЛ ПЛИТЫ — три состояния (4н).
sealed class DayPlateFooter {
  const DayPlateFooter();
}

/// Не начат — бумажная кнопка «Начать».
class DayPlateStart extends DayPlateFooter {
  const DayPlateStart({required this.button});
  final String button;
}

/// Идёт — три числа Literata 26 с латунными лейблами и «Продолжить · осталось N».
class DayPlateProgress extends DayPlateFooter {
  const DayPlateProgress({required this.numbers, required this.button});
  final List<DayPlateNumber> numbers;
  final String button;
}

/// Закрыт — галка 30, «День N закрыт», три числа Literata 56; кнопки нет.
class DayPlateClosed extends DayPlateFooter {
  const DayPlateClosed({required this.title, required this.numbers, this.animate = false});
  final String title;
  final List<DayPlateNumber> numbers;

  /// Проиграть закрытие (23-0b → 23-0c): числа набираются 420, галка 220 с задержкой 120,
  /// маркеры «научишься» разом 220 с задержкой 260.
  final bool animate;
}

/// ПЛИТА ДНЯ (токен-лист 4н) — один компонент, два размера.
///
/// [DayPlate.card] — карточка на табе: четыре угла radius 28, воздух вокруг, без фото, материал
/// 4и. [DayPlate.header] — шапка кабинета: от верха экрана под статус-баром, от края до края,
/// скруглена только снизу 28; фото дня под материалом на всю шапку — blur 24, saturate .8, скрим
/// `rgba(51,42,35,.88)` → `rgba(41,34,25,.95)`; без снимка — сплошной материал.
class DayPlate extends StatelessWidget {
  const DayPlate.card({
    super.key,
    required this.label,
    required this.title,
    required this.meta,
    required this.goals,
    required this.stages,
    required this.footer,
    this.goalsDone = false,
    this.onTap,
  }) : header = false,
       photo = null,
       onBack = null,
       backLabel = null;

  const DayPlate.header({
    super.key,
    required this.label,
    required this.title,
    required this.meta,
    required this.goals,
    required this.stages,
    required this.footer,
    this.goalsDone = false,
    this.photo,
    this.onBack,
    this.backLabel,
  }) : header = true,
       onTap = null;

  final bool header;
  final String label;
  final String title;
  final String meta;
  final List<String> goals;

  /// В закрытом дне маркеры «научишься» залиты шалфеем разом.
  final bool goalsDone;
  final List<DayPlateStage> stages;
  final DayPlateFooter footer;
  final ImageProvider? photo;
  final VoidCallback? onBack;
  final String? backLabel;
  final VoidCallback? onTap;

  static const _radius = 28.0;

  @override
  Widget build(BuildContext context) {
    final top = header ? MediaQuery.paddingOf(context).top : 0.0;
    final radius = header
        ? const BorderRadius.vertical(bottom: Radius.circular(_radius))
        : BorderRadius.circular(_radius);

    final content = Padding(
      padding: EdgeInsets.fromLTRB(22, header ? top + 6 : 20, 22, 20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        mainAxisSize: MainAxisSize.min,
        children: [
          if (header && onBack != null)
            Align(
              alignment: Alignment.centerLeft,
              child: Semantics(
                button: true,
                label: backLabel,
                child: InkResponse(
                  onTap: onBack,
                  radius: 22,
                  child: const SizedBox(
                    width: AppSpacing.minTap,
                    height: AppSpacing.minTap,
                    child: Icon(LucideIcons.arrowLeft, size: 22, color: AppColors.paper),
                  ),
                ),
              ),
            ),
          SizedBox(height: header ? 4 : 0),
          Text(label.toUpperCase(), style: AppTextDay.plateLabel),
          const SizedBox(height: 6),
          Text(title, style: AppTextDay.plateTitle),
          const SizedBox(height: 8),
          Text(meta, style: AppTextDay.plateMeta),
          if (goals.isNotEmpty) ...[
            const SizedBox(height: 16),
            _Goals(goals: goals, done: goalsDone, animateDelay: footer is DayPlateClosed && (footer as DayPlateClosed).animate),
          ],
          const SizedBox(height: 14),
          for (final stage in stages) _StageRow(stage: stage),
          _Footer(footer: footer),
        ],
      ),
    );

    final plate = DecoratedBox(
      decoration: BoxDecoration(borderRadius: radius, boxShadow: AppShadows.plate),
      child: ClipRRect(
        borderRadius: radius,
        child: Stack(
          fit: StackFit.passthrough,
          children: [
            Positioned.fill(
              child: photo == null
                  ? const DecoratedBox(
                      decoration: BoxDecoration(
                        gradient: LinearGradient(
                          begin: Alignment.topCenter,
                          end: Alignment.bottomCenter,
                          colors: [AppColors.plateTop, AppColors.plateBottom],
                        ),
                      ),
                    )
                  : _BlurredPhoto(photo: photo!),
            ),
            if (photo != null)
              const Positioned.fill(
                child: DecoratedBox(
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      begin: Alignment.topCenter,
                      end: Alignment.bottomCenter,
                      colors: [AppColors.scrimTop, AppColors.scrimBottom],
                    ),
                  ),
                ),
              ),
            content,
          ],
        ),
      ),
    );

    if (onTap == null) return plate;

    return Material(
      color: Colors.transparent,
      child: InkWell(onTap: onTap, borderRadius: radius, child: plate),
    );
  }
}

/// Снимок под материалом: blur 24, saturate .8. Снимок даёт тон, не картинку — чуть шире плиты,
/// чтобы размытие не показывало края.
class _BlurredPhoto extends StatelessWidget {
  const _BlurredPhoto({required this.photo});
  final ImageProvider photo;

  // saturate(.8) — матрица насыщенности по Rec. 709.
  static const _saturate = ColorFilter.matrix(<double>[
    0.8298, 0.1428, 0.0274, 0, 0,
    0.0426, 0.9300, 0.0274, 0, 0,
    0.0426, 0.1428, 0.8146, 0, 0,
    0, 0, 0, 1, 0,
  ]);

  @override
  Widget build(BuildContext context) => ColoredBox(
    color: AppColors.plateUnderPhoto,
    child: ImageFiltered(
      imageFilter: ImageFilter.blur(sigmaX: 24, sigmaY: 24),
      child: ColorFiltered(
        colorFilter: _saturate,
        child: Transform.scale(
          scale: 1.15,
          child: Image(image: photo, fit: BoxFit.cover, errorBuilder: (_, _, _) => const SizedBox.shrink()),
        ),
      ),
    ),
  );
}

class _Goals extends StatefulWidget {
  const _Goals({required this.goals, required this.done, required this.animateDelay});
  final List<String> goals;
  final bool done;
  final bool animateDelay;

  @override
  State<_Goals> createState() => _GoalsState();
}

class _GoalsState extends State<_Goals> {
  late bool _filled = widget.done && !widget.animateDelay;

  @override
  void initState() {
    super.initState();
    if (widget.done && widget.animateDelay) {
      Future<void>.delayed(AppMotion.plateMarkersDelay, () {
        if (mounted) setState(() => _filled = true);
      });
    }
  }

  @override
  void didUpdateWidget(_Goals old) {
    super.didUpdateWidget(old);
    if (widget.done != old.done) {
      if (widget.done && widget.animateDelay) {
        Future<void>.delayed(AppMotion.plateMarkersDelay, () {
          if (mounted) setState(() => _filled = true);
        });
      } else {
        _filled = widget.done;
      }
    }
  }

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      for (var i = 0; i < widget.goals.length; i++) ...[
        if (i > 0) const SizedBox(height: 8),
        Row(
          children: [
            VerdictMarker(state: _filled ? MarkerState.passed : MarkerState.empty, size: 18, onPlate: true),
            const SizedBox(width: 10),
            Expanded(child: Text(widget.goals[i], style: AppTextDay.plateGoal)),
          ],
        ),
      ],
    ],
  );
}

class _StageRow extends StatelessWidget {
  const _StageRow({required this.stage});
  final DayPlateStage stage;

  @override
  Widget build(BuildContext context) {
    final opacity = stage.locked ? 0.5 : 1.0;
    final total = stage.total <= 0 ? 1 : stage.total;

    return Padding(
      padding: EdgeInsets.symmetric(vertical: stage.current ? 9 : 7),
      child: Opacity(
        opacity: opacity,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(stage.name, style: stage.current ? AppTextDay.plateStageCurrent : AppTextDay.plateStage),
                      if (stage.sub case final sub?) ...[
                        const SizedBox(height: 3),
                        Text(
                          sub,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: AppTextDay.plateStageSub,
                        ),
                      ],
                    ],
                  ),
                ),
                const SizedBox(width: 12),
                SizedBox(
                  width: 64,
                  child: Text(
                    // Пока сервер не раздал карточки (день не открыт), счётчика нет — «0 / 0» врал бы.
                    stage.total == 0 ? '' : '${stage.done} / ${stage.total}',
                    textAlign: TextAlign.right,
                    maxLines: 1,
                    softWrap: false,
                    style: AppTextDay.plateCount,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 6),
            StageBar(
              passed: stage.passed / total,
              hinted: stage.hinted / total,
              failed: stage.failed / total,
              height: 6,
              track: AppColors.paperTrack,
            ),
          ],
        ),
      ),
    );
  }
}

/// ПОЛОСКА ЭТАПА В ТРЁХ ЦВЕТАХ — шалфей · охра · терракота слева направо, остальное — подложка.
/// Одна и та же на плите (6 px), в итоге этапа (6 px) и в шите (8 px).
class StageBar extends StatelessWidget {
  const StageBar({
    super.key,
    required this.passed,
    required this.hinted,
    required this.failed,
    this.height = 6,
    this.track = AppColors.barTrack,
  });

  final double passed, hinted, failed;
  final double height;
  final Color track;

  @override
  Widget build(BuildContext context) {
    final reduce = MediaQuery.of(context).disableAnimations;
    final segments = [
      (passed, AppColors.verdictKnown),
      (hinted, AppColors.verdictUnsure),
      (failed, AppColors.destructiveText),
    ];

    return ClipRRect(
      borderRadius: BorderRadius.circular(height / 2),
      child: SizedBox(
        height: height,
        child: LayoutBuilder(
          builder: (context, c) => Stack(
            children: [
              Positioned.fill(child: ColoredBox(color: track)),
              Positioned.fill(
                child: Row(
                  children: [
                    for (final (share, color) in segments)
                      AnimatedContainer(
                        duration: reduce ? Duration.zero : AppMotion.goalBar,
                        curve: AppMotion.easeOut,
                        width: c.maxWidth * share.clamp(0.0, 1.0),
                        color: color,
                      ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Footer extends StatelessWidget {
  const _Footer({required this.footer});
  final DayPlateFooter footer;

  @override
  Widget build(BuildContext context) => switch (footer) {
    DayPlateStart(:final button) => Padding(
      padding: const EdgeInsets.only(top: 16),
      child: _PaperButton(label: button),
    ),
    DayPlateProgress(:final numbers, :final button) => Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Container(
          margin: const EdgeInsets.only(top: 16),
          padding: const EdgeInsets.only(top: 14),
          decoration: const BoxDecoration(border: Border(top: BorderSide(color: AppColors.paperTrack))),
          child: Row(
            children: [
              for (final n in numbers)
                Expanded(child: _Number(number: n, big: false, animate: false)),
            ],
          ),
        ),
        const SizedBox(height: 16),
        _PaperButton(label: button),
      ],
    ),
    DayPlateClosed(:final title, :final numbers, :final animate) => Container(
      margin: const EdgeInsets.only(top: 18),
      padding: const EdgeInsets.only(top: 16),
      decoration: const BoxDecoration(border: Border(top: BorderSide(color: AppColors.paperTrack))),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              _ClosedCheck(animate: animate),
              const SizedBox(width: 12),
              Expanded(child: Text(title, style: AppTextDay.plateClosedTitle)),
            ],
          ),
          const SizedBox(height: 20),
          Row(
            children: [
              for (final n in numbers) Expanded(child: _Number(number: n, big: true, animate: animate)),
            ],
          ),
        ],
      ),
    ),
  };
}

/// Бумажная кнопка на плите — 52, radius 16, 17/700 ink. Тап ловит вся плита ([DayPlate.onTap])
/// или экран; кнопка — визуальный ответ на вопрос «что дальше».
class _PaperButton extends StatelessWidget {
  const _PaperButton({required this.label});
  final String label;

  @override
  Widget build(BuildContext context) => Container(
    height: 52,
    alignment: Alignment.center,
    decoration: BoxDecoration(color: AppColors.paper, borderRadius: BorderRadius.circular(16)),
    child: Text(label, style: AppTextDay.plateButton, maxLines: 1, overflow: TextOverflow.ellipsis),
  );
}

class _ClosedCheck extends StatelessWidget {
  const _ClosedCheck({required this.animate});
  final bool animate;

  @override
  Widget build(BuildContext context) {
    final reduce = MediaQuery.of(context).disableAnimations;
    final check = Container(
      width: 30,
      height: 30,
      decoration: const BoxDecoration(shape: BoxShape.circle, color: AppColors.paper),
      child: const Icon(LucideIcons.check, size: 16, color: AppColors.plateBottom, weight: 700),
    );
    if (!animate || reduce) return check;

    return TweenAnimationBuilder<double>(
      tween: Tween(begin: 0, end: 1),
      duration: AppMotion.plateCheck + AppMotion.plateCheckDelay,
      curve: const Interval(0.35, 1, curve: Curves.easeOut),
      builder: (_, t, child) => Transform.scale(scale: t, child: Opacity(opacity: t, child: child)),
      child: check,
    );
  }
}

/// Число с латунным лейблом. Большое (56) набирается 420 мс при закрытии дня.
class _Number extends StatelessWidget {
  const _Number({required this.number, required this.big, required this.animate});
  final DayPlateNumber number;
  final bool big;
  final bool animate;

  @override
  Widget build(BuildContext context) {
    final reduce = MediaQuery.of(context).disableAnimations;
    Widget value(int v) => Text.rich(
      TextSpan(
        style: big ? AppTextDay.plateNumberBig : AppTextDay.plateNumber,
        children: [
          TextSpan(text: '$v'),
          if (number.unit case final unit?)
            TextSpan(text: unit, style: big ? AppTextDay.plateNumberBigUnit : AppTextDay.plateNumberUnit),
        ],
      ),
      maxLines: 1,
      softWrap: false,
    );

    final numberWidget = animate && !reduce
        ? TweenAnimationBuilder<double>(
            tween: Tween(begin: 0, end: number.value.toDouble()),
            duration: AppMotion.plateNumbersCount,
            curve: AppMotion.easeOut,
            builder: (_, v, _) => value(v.round()),
          )
        : value(number.value);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        FittedBox(fit: BoxFit.scaleDown, alignment: Alignment.centerLeft, child: numberWidget),
        SizedBox(height: big ? 10 : 6),
        Text(number.label.toUpperCase(), style: AppTextDay.plateNumberLabel, maxLines: 1, softWrap: false),
      ],
    );
  }
}
