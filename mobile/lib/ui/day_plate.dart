import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/theme/theme.dart';

import 'plan_marks.dart';
import 'plan_preloader.dart';

/// ПЛИТА ДНЯ — компонент 4н токен-листа, в свёрнутом размере (карточка на табе «План»,
/// кадры 21-2 … 21-4, 22-5a/b).
///
/// Карточка с четырьмя углами radius 28 и без фото (материал 4и: градиент #332A23 → #292219,
/// лейбл «ДЕНЬ N» латунью, обложка кружком 52 с латунным кантом). Плита окна дня на фото — другой
/// компонент (`features/plan/day/window/window_plate.dart`, наряд DAY-UI-2).
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
    this.nextDayLine,
    this.notice,
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

  /// «3 карточки вернутся в день 3 →» — терракотой, только у закрытого дня. Null — строки нет.
  final String? returnLine;

  /// «День 3 откроется завтра, 12 сентября» — первая строка подвала закрытого дня (21-4): экран
  /// НЕ даёт кнопки «дальше», следующий день открывается со своего узла в маршруте, и подвал
  /// говорит только когда.
  final String? nextDayLine;

  /// СТРОКА ВМЕСТО ЭТАПОВ (22-5a, 22-5c): день ещё пишется или не собрался. Пока она стоит,
  /// строк этапов на плите нет — их ещё нечем заполнить.
  final DayPlateNotice? notice;

  /// Тап по карточке — в кабинет дня.
  final VoidCallback? onTap;

  static const double _radius = 28;

  /// Закрытый день — светлая бумага чуть меньшего радиуса (21-4: 26 против 28 у тёмной плиты).
  static const double _closedRadius = 26;

  @override
  Widget build(BuildContext context) {
    final br = BorderRadius.circular(closed ? _closedRadius : _radius);
    final ink = closed ? AppColors.ink : AppColors.paper;
    final body = Padding(
      padding: const EdgeInsets.fromLTRB(20, 20, 20, 18),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (closed)
            _ClosedHead(title: title, meta: meta)
          else
            _Head(label: label, title: title, cover: cover),
          // На тёмной плите счёт дня — своя строка под шапкой; у закрытого он уже внутри шапки.
          if (meta != null && !closed) ...[
            const SizedBox(height: 10),
            Text(
              meta!,
              style: TextStyle(
                fontFamily: AppFonts.inter,
                fontSize: 14,
                color: AppColors.paper.withValues(alpha: .55),
                fontFeatures: const [FontFeature.tabularFigures()],
              ),
            ),
          ],
          const SizedBox(height: 12),
          if (notice != null)
            _NoticeRow(notice: notice!)
          else
            for (var i = 0; i < stages.length; i++)
              _StageRow(stage: stages[i], last: i == stages.length - 1, ink: ink, closed: closed),
          // ПОДВАЛ ЗАКРЫТОГО ДНЯ (21-4) — две строки текстом под волосяной линией: когда откроется
          // следующий день и какие карточки в него вернутся. Значков и кнопок здесь нет: действия
          // на этом экране тоже нет.
          if (nextDayLine != null || returnLine != null) ...[
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.only(top: 12),
              decoration: BoxDecoration(
                border: Border(top: BorderSide(color: AppColors.ink.withValues(alpha: .10))),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (nextDayLine != null)
                    Text(
                      nextDayLine!,
                      style: const TextStyle(
                        fontFamily: AppFonts.inter,
                        fontSize: 14,
                        height: 1.4,
                        color: AppColors.tertiary,
                      ),
                    ),
                  if (returnLine != null) ...[
                    if (nextDayLine != null) const SizedBox(height: 4),
                    Text(
                      returnLine!,
                      style: const TextStyle(
                        fontFamily: AppFonts.inter,
                        fontSize: 14,
                        fontWeight: FontWeight.w600,
                        height: 1.4,
                        color: AppColors.destructiveText,
                      ),
                    ),
                  ],
                ],
              ),
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
          color: closed ? AppColors.paper : null,
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
      _PaperButton(label: label, onTap: onTap, enabled: enabled),
    ],
    DayPlateFooterLocked(:final note, :final action, :final onTap) => [
      const SizedBox(height: 16),
      SizedBox(
        height: 52,
        child: Row(
          children: [
            Expanded(
              child: Text(
                note,
                style: TextStyle(fontFamily: AppFonts.inter, fontSize: 13, height: 18 / 13, color: AppColors.paper.withValues(alpha: .62)),
              ),
            ),
            const SizedBox(width: 12),
            BrassOutlineButton(key: const ValueKey('day-plate-subscription'), label: action, onTap: onTap),
          ],
        ),
      ),
    ],
  };
}

/// THE BRASS OUTLINE BUTTON on a dark plate — 40 tall, radius 14, a 1.5 brass outline, paper 15/600 (21-3 / 23-0a «по
/// подписке»: «Подписка»).
class BrassOutlineButton extends StatelessWidget {
  const BrassOutlineButton({super.key, required this.label, required this.onTap, this.onPaper = false});

  final String label;
  final VoidCallback? onTap;

  /// On the paper dock of the day window (23-0a) the label is ink, on the dark plate — paper.
  final bool onPaper;

  @override
  Widget build(BuildContext context) => Semantics(
    button: true,
    label: label,
    child: GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTap: onTap == null
          ? null
          : () {
              AppHaptics.light();
              onTap!();
            },
      child: Container(
        height: 40,
        padding: const EdgeInsets.symmetric(horizontal: 16),
        alignment: Alignment.center,
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: AppColors.brassInk, width: 1.5),
        ),
        child: Text(
          label,
          style: TextStyle(
            fontFamily: AppFonts.inter,
            fontSize: 15,
            fontWeight: FontWeight.w600,
            color: onPaper ? AppColors.ink : AppColors.paper,
          ),
        ),
      ),
    ),
  );
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
  const factory DayPlateFooter.locked({required String note, required String action, VoidCallback? onTap}) =
      DayPlateFooterLocked;
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

/// A day that opens with a subscription (21-3 / 23-0a «по подписке»): «Откроется с подпиской» 13 grey and the brass
/// outline «Подписка» in place of «Начать».
class DayPlateFooterLocked extends DayPlateFooter {
  const DayPlateFooterLocked({required this.note, required this.action, this.onTap});

  final String note;
  final String action;
  final VoidCallback? onTap;
}

/// One stage row: name, «done / total», its state, and the second line the current one carries.
class DayPlateStage {
  const DayPlateStage({
    required this.kind,
    required this.name,
    required this.count,
    required this.state,
    this.note,
    this.noteColor,
  });

  /// КАКОЙ ЭТО ЭТАП — значок канвы стоит слева в строке вместо кружка-маркера, и состояние он
  /// несёт тонировкой (`assets/stages/`, правило — в [PlanStageMark]).
  final PlanStageMarkKind kind;

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
  const _Head({required this.label, required this.title, this.cover});

  final String label;
  final String title;
  final ImageProvider? cover;

  @override
  Widget build(BuildContext context) => Row(
    children: [
      Container(
        width: 52,
        height: 52,
        clipBehavior: Clip.antiAlias,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          color: AppColors.photoPlate,
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
            // НАЗВАНИЕ ДНЯ ПЕРЕНОСИТСЯ НА ДВЕ СТРОКИ, обрезки нет (канва 21-2): длинное название
            // сцены («Ресторан с ребёнком», «Повторный визит к врачу») в одну строку с троеточием
            // теряло ровно то слово, которым день и отличается от соседнего.
            Text(
              title,
              maxLines: 2,
              overflow: TextOverflow.clip,
              style: TextStyle(
                fontFamily: AppFonts.inter,
                fontSize: 22,
                fontWeight: FontWeight.w700,
                letterSpacing: -0.33,
                height: 1.15,
                color: AppColors.paper,
              ),
            ),
          ],
        ),
      ),
    ],
  );
}

/// ШАПКА ЗАКРЫТОГО ДНЯ (кадр 21-4): галка в круге ink 34, «День 2 закрыт» Literata 22 и под ней
/// счёт дня — обе строки в одной колонке, потому что это один итог, а не заголовок с подписью.
class _ClosedHead extends StatelessWidget {
  const _ClosedHead({required this.title, this.meta});

  final String title;
  final String? meta;

  @override
  Widget build(BuildContext context) => Row(
    children: [
      Container(
        width: 34,
        height: 34,
        decoration: const BoxDecoration(shape: BoxShape.circle, color: AppColors.ink),
        child: const Icon(LucideIcons.check, size: 18, color: AppColors.paper),
      ),
      const SizedBox(width: 12),
      Expanded(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              title,
              style: const TextStyle(
                fontFamily: AppFonts.literata,
                fontSize: 22,
                fontWeight: FontWeight.w500,
                letterSpacing: -0.33,
                height: 1.15,
                color: AppColors.ink,
              ),
            ),
            if (meta != null) ...[
              const SizedBox(height: 3),
              Text(
                meta!,
                style: const TextStyle(
                  fontFamily: AppFonts.inter,
                  fontSize: 14,
                  height: 1.35,
                  color: AppColors.secondary,
                ),
              ),
            ],
          ],
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

    // ДВА НАБОРА СТИЛЕЙ, а не один с поправками: тёмная плита идущего дня (21-2, 21-3) и светлая
    // бумага закрытого (21-4) — это разные строки в канве, вплоть до кегля имени и цвета счёта.
    //
    // тёмная:  имя 14, текущий w600/1, пройденный w400/.72, запертый w400/.5; счёт 14.5 w600,
    //          alpha 1 у пройденного и текущего, .5 у запертого; вторая строка paper .60.
    // светлая: имя 15 w600 ink; счёт 14 w600 ШАЛФЕЕМ (день сдан, и счёт об этом говорит);
    //          вторая строка 13 tertiary.
    final nameStyle = closed
        ? const TextStyle(
            fontFamily: AppFonts.inter,
            fontSize: 15,
            fontWeight: FontWeight.w600,
            height: 1.3,
            color: AppColors.ink,
          )
        : TextStyle(
            fontFamily: AppFonts.inter,
            fontSize: 14,
            fontWeight: current ? FontWeight.w600 : FontWeight.w400,
            color: ink.withValues(alpha: current ? 1 : (locked ? .5 : .72)),
          );
    final countStyle = closed
        ? const TextStyle(
            fontFamily: AppFonts.inter,
            fontSize: 14,
            fontWeight: FontWeight.w600,
            color: AppColors.verdictKnown,
            fontFeatures: [FontFeature.tabularFigures()],
          )
        : TextStyle(
            fontFamily: AppFonts.inter,
            fontSize: 14.5,
            fontWeight: FontWeight.w600,
            color: ink.withValues(alpha: locked ? .5 : 1),
            fontFeatures: const [FontFeature.tabularFigures()],
          );
    final noteStyle = TextStyle(
      fontFamily: AppFonts.inter,
      fontSize: closed ? 13 : 14,
      height: closed ? 1.35 : 1.3,
      color: stage.noteColor ?? (closed ? AppColors.tertiary : ink.withValues(alpha: .6)),
    );

    final name = Text(stage.name, style: nameStyle);
    final count = Text(stage.count, style: countStyle);
    final note = stage.note == null ? null : Text(stage.note!, style: noteStyle);

    return Container(
      // 9 на тёмной плите, 11 на светлой бумаге закрытого дня — числа канвы.
      padding: EdgeInsets.symmetric(vertical: closed ? 11 : 9),
      decoration: BoxDecoration(border: Border(top: line, bottom: last ? line : BorderSide.none)),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Значок этапа — 20, и он же маркер состояния; кружков в строках больше нет.
          PlanStageMark(
            kind: stage.kind,
            state: switch (stage.state) {
              DayPlateStageState.done => PlanStageMarkState.done,
              DayPlateStageState.current => PlanStageMarkState.current,
              DayPlateStageState.locked => PlanStageMarkState.locked,
            },
            onDark: !closed,
          ),
          const SizedBox(width: 11),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [Expanded(child: name), const SizedBox(width: 10), count],
                ),
                if (note != null) ...[const SizedBox(height: 3), note],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// СТРОКА ВМЕСТО ЭТАПОВ (кадры 22-5a, 22-5c) — то, что стоит на плите, когда этапов ещё или уже
/// нет: «Собираем день 1 · около минуты · можно закрыть приложение» и «День не собрался».
///
/// Не шиммер-скелет: канва заменила пять фальшивых строк ОДНОЙ честной — срок назван словами и
/// уходить разрешено, а пять серых полосок обещали содержимое, которого пока нет.
class DayPlateNotice {
  const DayPlateNotice({required this.title, this.sub, this.preloaderLines, this.offline = false});

  /// «Собираем день 1» / «Не получилось собрать день» — 15/600 paper.
  final String title;

  /// «около минуты · можно закрыть приложение» / «Нет сети» — 14 paper .62; null — no second line.
  final String? sub;

  /// The trouble is the network (a retry that could not leave): the cloud mark. Otherwise a failed day is a lesson
  /// that did not pass its checks twice (plan-api «Для CLIENT-START») — an alert mark, not a cloud.
  final bool offline;

  /// 22-5a: три строки статуса живого прелоадера под строкой — тот же прелоадер, что на 22-4a, на
  /// угольной плите. Null — день не собрался (22-5c): значок вместо прелоадера.
  final List<String>? preloaderLines;
}

/// СТРОКА-ИЗВЕЩЕНИЕ НА ПЛИТЕ (22-5a, 22-5c) — под волосяной линией на месте этапов.
class _NoticeRow extends StatelessWidget {
  const _NoticeRow({required this.notice});

  final DayPlateNotice notice;

  @override
  Widget build(BuildContext context) {
    final lines = notice.preloaderLines;
    final text = Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          notice.title,
          style: const TextStyle(fontFamily: AppFonts.inter, fontSize: 15, fontWeight: FontWeight.w600, color: AppColors.paper),
        ),
        if (notice.sub != null) ...[
          const SizedBox(height: 4),
          Text(
            notice.sub!,
            style: TextStyle(fontFamily: AppFonts.inter, fontSize: 14, height: 1.35, color: AppColors.paper.withValues(alpha: .62)),
          ),
        ],
      ],
    );

    return Container(
      margin: const EdgeInsets.only(top: 4),
      padding: const EdgeInsets.only(top: 16),
      decoration: BoxDecoration(border: Border(top: BorderSide(color: AppColors.paper.withValues(alpha: .14)))),
      child: lines == null
          ? Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                SizedBox(
                  width: 30,
                  height: 30,
                  child: Icon(
                    notice.offline ? LucideIcons.cloudOff : LucideIcons.circleAlert,
                    size: 30,
                    color: AppColors.destructiveOnPlate,
                  ),
                ),
                const SizedBox(width: 14),
                Expanded(child: text),
              ],
            )
          : Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                text,
                const SizedBox(height: 14),
                Center(child: PlanPreloader(lines: lines, onDark: true)),
              ],
            ),
    );
  }
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

/// ПОЛОСКА ЭТАПА В ТРЁХ ЦВЕТАХ — шалфей · охра · терракота слева направо, остальное — подложка.
/// Одна и та же в итоге этапа сессии (23-9, 6 px).
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
