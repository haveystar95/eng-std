import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/theme/theme.dart';

/// ПЛИТА ДНЯ — компонент 4н токен-листа, в свёрнутом размере (карточка на табе «План»,
/// кадры 21-2 … 21-4, 22-5a/b).
///
/// Один компонент, два размера: здесь — карточка с четырьмя углами radius 28 и без фото
/// (материал 4и: градиент #332A23 → #292219, лейбл «ДЕНЬ N» латунью, обложка кружком 52 с
/// латунным кантом). Шапка кабинета — второй размер того же компонента — придёт с нарядом DAY-UI
/// и добавит [DayPlateSize.header], не второй виджет.
///
/// Строки этапов — без иконок (записка: «индикатор 14 + имя + счётчик»): контур rgba(paper,.30)
/// у запертого, контур paper у текущего, шалфей с галкой у закрытого; вторая строка 14 alpha .60
/// только у текущего. Подвал — по состоянию: бумажная кнопка «Начать» / «Продолжить», шиммер
/// строк у дня, который ещё пишется (22-5a), и — у закрытого дня (21-4) — та же карточка на
/// СВЕТЛОЙ бумаге с галкой в круге 30 и терракотовой строкой «Вернутся в день N».
///
/// `lib/ui/` knows no languages: every string here is the caller's.
class DayPlate extends StatelessWidget {
  const DayPlate({
    super.key,
    required this.label,
    required this.title,
    required this.stages,
    this.meta,
    this.cover,
    this.footer = const DayPlateFooter.none(),
    this.closed = false,
    this.returnLine,
    this.building = false,
    this.onTap,
  });

  /// «ДЕНЬ 2» — латунью, caps.
  final String label;

  /// «Приём у врача» — сервер; одна строка, обрезается многоточием.
  final String title;

  /// «75 карточек · ≈ 20 минут» — под названием, alpha .55. Null — строки нет.
  final String? meta;

  /// Обложка плана — кружок 52 с латунным кантом. Null — пустая подложка #E3DCCF.
  final ImageProvider? cover;

  final List<DayPlateStage> stages;
  final DayPlateFooter footer;

  /// Закрытый день (кадр 21-4): светлая бумага, галка в круге 30, Literata 23, счётчики 600.
  final bool closed;

  /// «Вернутся в день 3 · 3 карточки» — терракотой, только у закрытого дня. Null — строки нет.
  final String? returnLine;

  /// День ещё пишется (кадр 22-5a): строки этапов — шиммер, кнопка приглушена, обложки нет.
  final bool building;

  /// Тап по карточке — в кабинет дня.
  final VoidCallback? onTap;

  static const double _radius = 28;

  @override
  Widget build(BuildContext context) {
    final br = BorderRadius.circular(_radius);
    final ink = closed ? AppColors.ink : AppColors.paper;
    final body = Padding(
      padding: const EdgeInsets.fromLTRB(20, 20, 20, 18),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (closed) _ClosedHead(title: title) else _Head(label: label, title: title, cover: cover, building: building),
          if (meta != null) ...[
            const SizedBox(height: 10),
            Text(
              meta!,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                fontFamily: AppFonts.inter,
                fontSize: 14,
                color: closed ? AppColors.secondary : AppColors.paper.withValues(alpha: .55),
                fontFeatures: const [FontFeature.tabularFigures()],
              ),
            ),
          ],
          const SizedBox(height: 12),
          if (building)
            const _SkeletonRows()
          else
            for (var i = 0; i < stages.length; i++)
              _StageRow(stage: stages[i], last: i == stages.length - 1, ink: ink, closed: closed),
          if (returnLine != null) ...[
            const SizedBox(height: 14),
            Row(
              children: [
                Container(
                  width: 16,
                  height: 16,
                  decoration: const BoxDecoration(
                    shape: BoxShape.circle,
                    color: AppColors.verdictUnknown,
                  ),
                  child: const Icon(LucideIcons.undo2, size: 10, color: AppColors.paper),
                ),
                const SizedBox(width: 9),
                Expanded(
                  child: Text(
                    returnLine!,
                    style: const TextStyle(
                      fontFamily: AppFonts.inter,
                      fontSize: 14,
                      fontWeight: FontWeight.w600,
                      color: AppColors.destructiveText,
                    ),
                  ),
                ),
              ],
            ),
          ],
          ..._footer(),
        ],
      ),
    );

    return Semantics(
      button: onTap != null,
      child: DecoratedBox(
        decoration: BoxDecoration(
          borderRadius: br,
          gradient: closed
              ? null
              : const LinearGradient(
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                  colors: [AppColors.plateTop, AppColors.plateBottom],
                ),
          color: closed ? AppColors.surfaceRaised : null,
          boxShadow: closed ? AppShadows.card : AppShadows.plate,
        ),
        child: Material(
          type: MaterialType.transparency,
          borderRadius: br,
          clipBehavior: Clip.antiAlias,
          child: InkWell(
            onTap: onTap,
            splashColor: ink.withValues(alpha: .06),
            highlightColor: ink.withValues(alpha: .04),
            child: body,
          ),
        ),
      ),
    );
  }

  List<Widget> _footer() => switch (footer) {
    DayPlateFooterNone() => const [],
    DayPlateFooterButton(:final label, :final onTap, :final enabled) => [
      const SizedBox(height: 14),
      _PaperButton(label: label, onTap: onTap, enabled: enabled && !building),
    ],
  };
}

/// Where the plate's footer stands: nothing (a closed day), or the one bumаga button.
sealed class DayPlateFooter {
  const DayPlateFooter();

  const factory DayPlateFooter.none() = DayPlateFooterNone;
  const factory DayPlateFooter.button({
    required String label,
    VoidCallback? onTap,
    bool enabled,
  }) = DayPlateFooterButton;
}

class DayPlateFooterNone extends DayPlateFooter {
  const DayPlateFooterNone();
}

class DayPlateFooterButton extends DayPlateFooter {
  const DayPlateFooterButton({required this.label, this.onTap, this.enabled = true});

  final String label;
  final VoidCallback? onTap;
  final bool enabled;
}

/// One stage row: name, «done / total», its state, and the second line the current one carries.
class DayPlateStage {
  const DayPlateStage({
    required this.name,
    required this.count,
    required this.state,
    this.note,
    this.noteColor,
  });

  final String name;

  /// «0 / 32» — the caller's string (plan.plate.stage.count); `lib/ui/` composes no copy.
  final String count;
  final DayPlateStageState state;

  /// «начни отсюда · 8 новых слов» / «не закончен · 10 карточек» / «1 с подсказкой».
  final String? note;

  /// Охра у «с подсказкой»; null — paper .60 (dark) / secondary (paper).
  final Color? noteColor;
}

enum DayPlateStageState { locked, current, done }

class _Head extends StatelessWidget {
  const _Head({required this.label, required this.title, this.cover, required this.building});

  final String label;
  final String title;
  final ImageProvider? cover;
  final bool building;

  @override
  Widget build(BuildContext context) => Row(
    children: [
      Container(
        width: 52,
        height: 52,
        clipBehavior: Clip.antiAlias,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          color: building ? AppColors.paper.withValues(alpha: .10) : AppColors.photoPlate,
          border: Border.all(color: AppColors.brassHairline),
        ),
        child: cover == null ? null : Image(image: cover!, fit: BoxFit.cover),
      ),
      const SizedBox(width: 14),
      Expanded(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              label.toUpperCase(),
              style: const TextStyle(
                fontFamily: AppFonts.inter,
                fontSize: 11,
                fontWeight: FontWeight.w700,
                letterSpacing: 1.54,
                color: AppColors.brass,
              ),
            ),
            const SizedBox(height: 5),
            Text(
              title,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                fontFamily: AppFonts.inter,
                fontSize: building ? 16 : 22,
                fontWeight: building ? FontWeight.w600 : FontWeight.w700,
                letterSpacing: building ? 0 : -0.33,
                height: 1.1,
                color: AppColors.paper,
              ),
            ),
          ],
        ),
      ),
    ],
  );
}

class _ClosedHead extends StatelessWidget {
  const _ClosedHead({required this.title});

  final String title;

  @override
  Widget build(BuildContext context) => Row(
    children: [
      Container(
        width: 30,
        height: 30,
        decoration: const BoxDecoration(shape: BoxShape.circle, color: AppColors.ink),
        child: const Icon(LucideIcons.check, size: 16, color: AppColors.paper),
      ),
      const SizedBox(width: 11),
      Expanded(
        child: Text(
          title,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(
            fontFamily: AppFonts.literata,
            fontSize: 23,
            fontWeight: FontWeight.w500,
            letterSpacing: -0.23,
            color: AppColors.ink,
          ),
        ),
      ),
    ],
  );
}

class _StageRow extends StatelessWidget {
  const _StageRow({
    required this.stage,
    required this.last,
    required this.ink,
    required this.closed,
  });

  final DayPlateStage stage;
  final bool last;
  final Color ink;
  final bool closed;

  @override
  Widget build(BuildContext context) {
    final line = BorderSide(color: ink.withValues(alpha: closed ? .10 : .14));
    final current = stage.state == DayPlateStageState.current;
    final locked = stage.state == DayPlateStageState.locked;
    final done = stage.state == DayPlateStageState.done;
    // Текущий — 600 / alpha 1, остальные 400 / .72, запертые .5 (записка); на светлой бумаге
    // закрытого дня все строки — ink-body / ink 600.
    final nameAlpha = closed ? 1.0 : (current ? 1.0 : (locked ? .5 : .72));
    final countAlpha = closed || current || done ? 1.0 : .5;
    final noteColor = stage.noteColor ?? (closed ? AppColors.secondary : ink.withValues(alpha: .6));

    return Container(
      padding: const EdgeInsets.symmetric(vertical: 9),
      decoration: BoxDecoration(border: Border(top: line, bottom: last ? line : BorderSide.none)),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.only(top: 3),
            child: _Indicator(state: stage.state, ink: ink, closed: closed),
          ),
          const SizedBox(width: 11),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        stage.name,
                        style: TextStyle(
                          fontFamily: AppFonts.inter,
                          fontSize: 14,
                          fontWeight: current && !closed ? FontWeight.w600 : FontWeight.w400,
                          color: (closed ? AppColors.inkBody : ink).withValues(alpha: nameAlpha),
                        ),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Text(
                      stage.count,
                      style: TextStyle(
                        fontFamily: AppFonts.inter,
                        fontSize: 14.5,
                        fontWeight: FontWeight.w600,
                        color: ink.withValues(alpha: countAlpha),
                        fontFeatures: const [FontFeature.tabularFigures()],
                      ),
                    ),
                  ],
                ),
                if (stage.note != null) ...[
                  const SizedBox(height: 3),
                  Text(
                    stage.note!,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      fontFamily: AppFonts.inter,
                      fontSize: 14,
                      height: 1.3,
                      color: noteColor,
                    ),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _Indicator extends StatelessWidget {
  const _Indicator({required this.state, required this.ink, required this.closed});

  final DayPlateStageState state;
  final Color ink;
  final bool closed;

  @override
  Widget build(BuildContext context) {
    switch (state) {
      case DayPlateStageState.done:
        return Container(
          width: 14,
          height: 14,
          decoration: const BoxDecoration(shape: BoxShape.circle, color: AppColors.verdictKnown),
          child: const Icon(LucideIcons.check, size: 9, color: AppColors.paper),
        );
      case DayPlateStageState.current:
        return Container(
          width: 14,
          height: 14,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            border: Border.all(color: closed ? AppColors.ink : AppColors.paper, width: 1.5),
          ),
        );
      case DayPlateStageState.locked:
        return Container(
          width: 14,
          height: 14,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            border: Border.all(color: ink.withValues(alpha: .30), width: 1.5),
          ),
        );
    }
  }
}

/// Пять строк-скелетов на месте этапов (кадр 22-5a): контур индикатора и две плашки .14.
class _SkeletonRows extends StatelessWidget {
  const _SkeletonRows();

  @override
  Widget build(BuildContext context) {
    final line = BorderSide(color: AppColors.paper.withValues(alpha: .14));
    const widths = [64.0, 60.0, 70.0, 150.0, 100.0];

    return Column(
      children: [
        for (var i = 0; i < widths.length; i++)
          Container(
            padding: const EdgeInsets.symmetric(vertical: 11),
            decoration: BoxDecoration(
              border: Border(top: line, bottom: i == widths.length - 1 ? line : BorderSide.none),
            ),
            child: Row(
              children: [
                Container(
                  width: 14,
                  height: 14,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    border: Border.all(color: AppColors.paper.withValues(alpha: .30), width: 1.5),
                  ),
                ),
                const SizedBox(width: 11),
                _bone(widths[i]),
                const Spacer(),
                _bone(40),
              ],
            ),
          ),
      ],
    );
  }

  Widget _bone(double width) => Container(
    width: width,
    height: 12,
    decoration: BoxDecoration(
      color: AppColors.paper.withValues(alpha: .14),
      borderRadius: BorderRadius.circular(3),
    ),
  );
}

/// Бумажная кнопка плиты — 52 / radius 16 / 17 / 700 (4и).
class _PaperButton extends StatelessWidget {
  const _PaperButton({required this.label, this.onTap, required this.enabled});

  final String label;
  final VoidCallback? onTap;
  final bool enabled;

  @override
  Widget build(BuildContext context) {
    final on = enabled && onTap != null;

    return Semantics(
      button: true,
      enabled: on,
      label: label,
      child: Material(
        color: on ? AppColors.paper : AppColors.paper.withValues(alpha: .10),
        borderRadius: BorderRadius.circular(16),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: on ? onTap : null,
          child: Container(
            height: 52,
            alignment: Alignment.center,
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  label,
                  style: TextStyle(
                    fontFamily: AppFonts.inter,
                    fontSize: 17,
                    fontWeight: FontWeight.w700,
                    color: on ? AppColors.ink : AppColors.paper.withValues(alpha: .45),
                  ),
                ),
                if (on) ...[
                  const SizedBox(width: 9),
                  const Icon(LucideIcons.arrowRight, size: 17, color: AppColors.ink),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }
}
